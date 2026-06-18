<?php
// Copy this file to .env.php on the server — never commit .env.php to git
// Generate admin password hash with: php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_BCRYPT, ['cost'=>12]);"

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'vi2ion_fleet');
define('DB_USER', 'vi2ion_user');
define('DB_PASS', 'CHANGE_ME');

// bcrypt hash of admin password — generate fresh on server
define('ADMIN_USER', 'vi2ionai_admin');
define('ADMIN_PASS_HASH', '$2y$12$REPLACE_WITH_REAL_HASH');

// Email address to receive contact form submissions
define('CONTACT_TO_EMAIL', 'enquiries@vi2ionai.com.au');
define('CONTACT_FROM_NAME', 'Vi2ionai Fleet Contact');
define('CONTACT_FROM_EMAIL', 'noreply@vi2ionai.com.au');

// App environment: 'production' | 'development'
define('APP_ENV', 'production');

// Base URL (no trailing slash)
define('APP_URL', 'https://vi2ionai.com.au');

// ── Analytics (leave empty to disable; paste IDs on the VPS .env.php only) ──
// Google Analytics 4 — Measurement ID from GA4 > Admin > Data Streams
define('GA4_MEASUREMENT_ID', '');          // e.g. 'G-XXXXXXXXXX'

// Meta Pixel — from Meta Events Manager > Pixels
define('META_PIXEL_ID', '');               // e.g. '1234567890123456'

// Google Search Console — HTML tag verification code (content= value only)
define('GSC_VERIFICATION', '');            // e.g. 'abc123xyz...'
