<?php
/**
 * Admin Authentication Middleware
 * Include at the top of every protected admin page.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Secure session config — ini_set must run BEFORE session_start
function initAdminSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure',   '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime',  (string) SESSION_LIFETIME);
        session_start();
    }
}

function requireAdmin(): void {
    initAdminSession();
    if (empty($_SESSION['admin_user']) || empty($_SESSION['admin_expires'])) {
        header('Location: /admin/index.php?expired=1');
        exit;
    }
    if ($_SESSION['admin_expires'] < time()) {
        session_destroy();
        header('Location: /admin/index.php?expired=1');
        exit;
    }
    // Extend session on activity
    $_SESSION['admin_expires'] = time() + SESSION_LIFETIME;
}

function generateCsrf(): string {
    initAdminSession();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrf(string $token): bool {
    initAdminSession();
    return isset($_SESSION[CSRF_TOKEN_NAME])
        && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Set the admin maintenance-bypass cookie.
 * Nginx reads this cookie to allow logged-in admins through the maintenance gate.
 * Called immediately after successful login.
 */
function setAdminBypassCookie(): void {
    if (!defined('ADMIN_BYPASS_TOKEN') || ADMIN_BYPASS_TOKEN === '') return;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('vi2_admin_bypass', ADMIN_BYPASS_TOKEN, [
        'expires'  => time() + SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Clear the admin maintenance-bypass cookie on logout.
 */
function clearAdminBypassCookie(): void {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('vi2_admin_bypass', '', [
        'expires'  => time() - 42000,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function isRateLimited(string $ip): bool {
    try {
        $db = getDB();
        $cutoff = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_SEC);
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?'
        );
        $stmt->execute([$ip, $cutoff]);
        return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
    } catch (Exception $e) {
        return false;
    }
}

function recordLoginAttempt(string $ip): void {
    try {
        $db = getDB();
        $db->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')
           ->execute([$ip]);
        // Clean up old attempts (older than 24h)
        $db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
           ->execute([date('Y-m-d H:i:s', time() - 86400)]);
    } catch (Exception $e) {
        // Non-fatal
    }
}
