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

// KORE SMS-MO webhook shared secret — required as ?token= query param
// Generate with: openssl rand -hex 32
define('KORE_WEBHOOK_SECRET', 'CHANGE_ME');

// KORE SMS API — outbound commands (admin "Send SMS Command" panel)
// From KORE Developer Portal → Manage → Manage API Client → Vi2ionAI SMS Webhook → Keys
define('KORE_SMS_TOKEN_URL',     'https://api.korewireless.com/Api/api/token');
define('KORE_SMS_API_BASE',      'https://api.korewireless.com/sms/v1');
define('KORE_SMS_CLIENT_ID',     'CHANGE_ME');
define('KORE_SMS_CLIENT_SECRET', 'CHANGE_ME');
define('KORE_SMS_API_KEY',       'CHANGE_ME');
define('KORE_SMS_ACCOUNT_ID',    'cmp-k1-org-41377');

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
