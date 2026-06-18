<?php
/**
 * Admin Logout
 * Accepts POST (with CSRF, from dashboard button) or GET (direct link).
 * Always destroys the session and expires the cookie.
 */
require_once __DIR__ . '/auth.php';
initAdminSession();

// Accept POST+CSRF (button) or GET (link/direct nav)
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if (!$isPost || verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    // Clear all session data
    $_SESSION = [];

    // Expire the session cookie in the browser
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    // Clear admin maintenance-bypass cookie so nginx stops granting access
    clearAdminBypassCookie();

    // Destroy the server-side session
    session_destroy();
}

header('Location: /admin/index.php');
exit;
