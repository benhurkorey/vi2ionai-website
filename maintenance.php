<?php
/**
 * Vi2ionai Fleet — Maintenance Mode Page
 *
 * Served by Nginx as the 503 error page when .maintenance flag is present.
 * Logged-in admins are redirected to the homepage (bypass).
 *
 * NEVER include full config/DB — this page must work stand-alone.
 */

// ── Admin session bypass ──────────────────────────────────────────────────────
// If an admin is already logged in they get redirected straight through.
// We replicate the minimal session setup from auth.php without pulling in the DB.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    // cookie_secure only when actually on HTTPS (Traefik passes HTTPS header)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

if (!empty($_SESSION['admin_user'])) {
    // Admin is logged in — refresh the nginx bypass cookie so they browse normally,
    // then redirect to the homepage (nginx will now let them through).
    // We load config.php to get the real token, but fail gracefully if unavailable.
    $bypassToken = '';
    try {
        $cfgFile = __DIR__ . '/includes/config.php';
        if (file_exists($cfgFile)) {
            require_once $cfgFile;
            $bypassToken = defined('ADMIN_BYPASS_TOKEN') ? ADMIN_BYPASS_TOKEN : '';
        }
    } catch (Throwable $e) { /* non-fatal — redirect will still work next request */ }

    if ($bypassToken !== '') {
        $isHttpsForCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                         || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie('vi2_admin_bypass', $bypassToken, [
            'expires'  => time() + 3600,
            'path'     => '/',
            'secure'   => $isHttpsForCookie,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    header('Location: /');
    exit;
}

// ── 503 response headers ──────────────────────────────────────────────────────
http_response_code(503);
header('Retry-After: 3600');
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>We'll Be Back Shortly — Vi2ionai Fleet</title>

  <!-- Prevent indexing of maintenance page -->
  <meta name="robots" content="noindex, nofollow">

  <!-- Basic OG so social previews look clean if someone shares during maintenance -->
  <meta property="og:title" content="We're Improving the Experience — Vi2ionai Fleet">
  <meta property="og:description" content="We'll be back shortly. Thank you for your patience.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --black:    #0a0a0a;
      --white:    #ffffff;
      --grey-50:  #f9f9f9;
      --grey-100: #f0f0f0;
      --grey-200: #e0e0e0;
      --grey-400: #a0a0a0;
      --grey-600: #5a5a5a;
      --grey-700: #3a3a3a;
      --grey-900: #1a1a1a;
      --accent:   #4f8ef7;
      --radius:   12px;
      --radius-lg: 20px;
    }

    html, body {
      height: 100%;
    }

    body {
      font-family: 'Manrope', system-ui, -apple-system, sans-serif;
      background: var(--black);
      color: var(--white);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 24px;
      text-rendering: optimizeLegibility;
      -webkit-font-smoothing: antialiased;
    }

    /* ── Animated grid background ── */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
      background-size: 48px 48px;
      pointer-events: none;
      z-index: 0;
    }

    .page-wrap {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 540px;
      text-align: center;
    }

    /* ── Logo ── */
    .logo {
      display: inline-flex;
      align-items: center;
      margin-bottom: 52px;
      text-decoration: none;
    }

    .logo img {
      height: 42px;
      width: auto;
    }

    /* ── Status badge ── */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 100px;
      padding: 6px 16px;
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: rgba(255,255,255,.7);
      margin-bottom: 32px;
    }

    .status-dot {
      width: 8px;
      height: 8px;
      background: #f59e0b;
      border-radius: 50%;
      animation: pulse-dot 2s ease-in-out infinite;
    }

    @keyframes pulse-dot {
      0%, 100% { opacity: 1; transform: scale(1); }
      50%       { opacity: .5; transform: scale(.75); }
    }

    /* ── Main heading ── */
    h1 {
      font-size: clamp(1.9rem, 5vw, 2.7rem);
      font-weight: 800;
      line-height: 1.15;
      letter-spacing: -.02em;
      margin-bottom: 20px;
    }

    h1 span {
      background: linear-gradient(135deg, #ffffff 0%, rgba(255,255,255,.55) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* ── Body copy ── */
    .body-copy {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 1rem;
      line-height: 1.7;
      color: rgba(255,255,255,.62);
      margin-bottom: 40px;
    }

    /* ── ETA card ── */
    .eta-card {
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: var(--radius-lg);
      padding: 28px 32px;
      margin-bottom: 40px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .eta-item {
      text-align: left;
    }

    .eta-label {
      font-size: .65rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: rgba(255,255,255,.4);
      margin-bottom: 6px;
    }

    .eta-value {
      font-size: .95rem;
      font-weight: 600;
      color: rgba(255,255,255,.88);
    }

    /* ── Contact button ── */
    .cta-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--white);
      color: var(--black);
      text-decoration: none;
      font-size: .88rem;
      font-weight: 700;
      padding: 14px 28px;
      border-radius: var(--radius);
      transition: background .18s, transform .18s;
      letter-spacing: .01em;
    }

    .cta-link:hover {
      background: var(--grey-100);
      transform: translateY(-1px);
    }

    /* ── Footer note ── */
    .footer-note {
      margin-top: 52px;
      font-size: .75rem;
      color: rgba(255,255,255,.28);
      line-height: 1.6;
    }

    .footer-note a {
      color: rgba(255,255,255,.45);
      text-decoration: none;
    }

    .footer-note a:hover {
      color: rgba(255,255,255,.7);
    }

    /* ── Decorative blur orb ── */
    .orb {
      position: fixed;
      width: 480px;
      height: 480px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(79,142,247,.12) 0%, transparent 70%);
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      pointer-events: none;
      z-index: 0;
    }

    @media (max-width: 480px) {
      .eta-card {
        grid-template-columns: 1fr;
        gap: 16px;
        padding: 20px;
      }
      h1 { font-size: 1.75rem; }
    }
  </style>
</head>
<body>
  <div class="orb"></div>

  <div class="page-wrap">

    <!-- Logo -->
    <a href="/" class="logo">
      <img src="/assets/img/logo-dark-bg.png"
           alt="Vi2ion AI"
           width="180" height="42"
           loading="eager">
    </a>

    <!-- Status badge -->
    <div>
      <span class="status-badge">
        <span class="status-dot"></span>
        Maintenance in Progress
      </span>
    </div>

    <!-- Heading -->
    <h1>We're improving<br><span>the experience.</span></h1>

    <!-- Body copy -->
    <p class="body-copy">
      We'll be back shortly — our team is making improvements<br>
      to deliver you a better platform. Thank you for your patience.
    </p>

    <!-- ETA card -->
    <div class="eta-card">
      <div class="eta-item">
        <div class="eta-label">Status</div>
        <div class="eta-value">🔧 In Progress</div>
      </div>
      <div class="eta-item">
        <div class="eta-label">Estimated Return</div>
        <div class="eta-value">Within 1–2 hours</div>
      </div>
      <div class="eta-item">
        <div class="eta-label">What's changing</div>
        <div class="eta-value">Platform upgrades</div>
      </div>
      <div class="eta-item">
        <div class="eta-label">Need urgent help?</div>
        <div class="eta-value">Email us below</div>
      </div>
    </div>

    <!-- Contact CTA -->
    <a href="mailto:info@vi2ionai.com.au" class="cta-link">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
      </svg>
      Contact Us
    </a>

    <!-- Footer note -->
    <p class="footer-note">
      Vi2ionai Fleet &mdash; Australian Fleet Management Solutions<br>
      <a href="mailto:info@vi2ionai.com.au">info@vi2ionai.com.au</a>
      &nbsp;&bull;&nbsp;
      <a href="https://vi2ionai.com.au/admin/" onclick="return confirm('Continue to admin login?')">Admin Login</a>
    </p>

  </div>
</body>
</html>
