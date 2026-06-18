<?php
/**
 * Vi2ionai Fleet — Configuration
 * Secrets loaded from environment variables — never hardcoded here.
 * Set via /etc/environment or /var/www/vi2ionai/.env.php on the server.
 */

// Load .env.php from the same directory (includes/) — blocked from web by Nginx
// Fallback: also check project root for legacy placement
foreach ([__DIR__ . '/.env.php', __DIR__ . '/../.env.php'] as $envFile) {
    if (file_exists($envFile)) {
        require_once $envFile;
        break;
    }
}

// ── App ───────────────────────────────────────────────────────────────────
define('APP_NAME',    'Vi2ionai Fleet');
if (!defined('APP_URL'))  define('APP_URL',  getenv('APP_URL')  ?: 'https://vi2ionai.com.au');
if (!defined('APP_ENV'))  define('APP_ENV',  getenv('APP_ENV')  ?: 'production');

// ── Database ──────────────────────────────────────────────────────────────
if (!defined('DB_HOST'))    define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
if (!defined('DB_NAME'))    define('DB_NAME',    getenv('DB_NAME')    ?: 'vi2ion_fleet');
if (!defined('DB_USER'))    define('DB_USER',    getenv('DB_USER')    ?: 'vi2ion_user');
if (!defined('DB_PASS'))    define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── Admin ─────────────────────────────────────────────────────────────────
// ADMIN_PASS_HASH is set in .env.php on the server
// Generate with: php -r "echo password_hash('yourpass', PASSWORD_BCRYPT, ['cost'=>12]);"
if (!defined('ADMIN_USER'))         define('ADMIN_USER',         getenv('ADMIN_USER')         ?: 'admin');
if (!defined('ADMIN_PASS_HASH'))    define('ADMIN_PASS_HASH',    getenv('ADMIN_PASS_HASH')     ?: '');
// ADMIN_BYPASS_TOKEN: secret cookie value that lets nginx bypass maintenance mode for admins.
// Set in .env.php on the server; generated automatically on first deploy via GitHub Actions.
if (!defined('ADMIN_BYPASS_TOKEN')) define('ADMIN_BYPASS_TOKEN', getenv('ADMIN_BYPASS_TOKEN') ?: '');

// ── Sessions ──────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600);       // 1 hour
define('CSRF_TOKEN_NAME',  'vi2_csrf');

// ── Security ──────────────────────────────────────────────────────────────
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SEC',  900);      // 15 min
define('UPLOAD_MAX_MB',      10);
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg','image/png','image/webp','image/gif']);
define('UPLOAD_DIR',   __DIR__ . '/../uploads/');
define('UPLOAD_URL',   APP_URL . '/uploads/');

// ── Email (contact form) ──────────────────────────────────────────────────
if (!defined('CONTACT_TO_EMAIL'))   define('CONTACT_TO_EMAIL',   getenv('CONTACT_TO_EMAIL')   ?: 'info@vi2ionai.com.au');
if (!defined('CONTACT_FROM_EMAIL')) define('CONTACT_FROM_EMAIL', getenv('CONTACT_FROM_EMAIL') ?: 'noreply@vi2ionai.com.au');
if (!defined('CONTACT_FROM_NAME'))  define('CONTACT_FROM_NAME',  getenv('CONTACT_FROM_NAME')  ?: 'Vi2ionai Fleet');

// ── Timezone ──────────────────────────────────────────────────────────────
date_default_timezone_set('Australia/Sydney');
