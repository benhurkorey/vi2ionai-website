<?php
/**
 * Admin Login Page — /admin/
 */
require_once __DIR__ . '/auth.php';
initAdminSession();

// Already logged in → redirect to dashboard
if (!empty($_SESSION['admin_user']) && !empty($_SESSION['admin_expires'])
    && $_SESSION['admin_expires'] > time()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error   = '';
$expired = !empty($_GET['expired']);
$ip      = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST[CSRF_TOKEN_NAME] ?? '';

    if (!verifyCsrf($csrf)) {
        $error = 'Invalid request. Please try again.';
    } elseif (isRateLimited($ip)) {
        $error = 'Too many failed attempts. Try again in 15 minutes.';
    } elseif ($username !== ADMIN_USER || !password_verify($password, ADMIN_PASS_HASH)) {
        recordLoginAttempt($ip);
        $error = 'Invalid credentials.';
        // Artificial delay to slow brute force
        usleep(500000);
    } else {
        // Successful login
        session_regenerate_id(true);
        $_SESSION['admin_user']    = $username;
        $_SESSION['admin_expires'] = time() + SESSION_LIFETIME;
        // Set nginx-readable bypass cookie so admin can browse through maintenance mode
        setAdminBypassCookie();
        // Clear CSRF token to force a new one on dashboard
        unset($_SESSION[CSRF_TOKEN_NAME]);
        header('Location: /admin/dashboard.php');
        exit;
    }
}

$csrf = generateCsrf();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login — Vi2ionai</title>
  <meta name="robots" content="noindex, nofollow" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Manrope', sans-serif;
      background: #0a0a0a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .login-card {
      background: #111;
      border: 1px solid #222;
      border-radius: 16px;
      padding: 48px 40px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 24px 64px rgba(0,0,0,.4);
    }
    .logo { margin-bottom: 12px; }
    .logo img { height: 36px; width: auto; }
    h1 { font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 32px; margin-top: 4px; }
    label { display: block; font-size: .78rem; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
    input {
      width: 100%; background: #1a1a1a; border: 1px solid #2a2a2a;
      border-radius: 8px; padding: 12px 14px; font-size: .9rem;
      color: #fff; font-family: inherit; margin-bottom: 18px;
      transition: border-color .15s;
    }
    input:focus { outline: none; border-color: #444; }
    .btn {
      width: 100%; background: #fff; color: #000; border: none;
      border-radius: 8px; padding: 14px; font-size: .9rem;
      font-weight: 700; font-family: inherit; cursor: pointer;
      transition: opacity .15s; margin-top: 8px;
    }
    .btn:hover { opacity: .88; }
    .error {
      background: #2a0a0a; border: 1px solid #4a1a1a;
      color: #f87171; border-radius: 8px; padding: 12px 14px;
      font-size: .85rem; margin-bottom: 20px;
    }
    .notice {
      background: #0a1a2a; border: 1px solid #1a2a3a;
      color: #60a5fa; border-radius: 8px; padding: 12px 14px;
      font-size: .85rem; margin-bottom: 20px;
    }
    .back { text-align: center; margin-top: 24px; }
    .back a { color: #555; font-size: .8rem; text-decoration: none; }
    .back a:hover { color: #888; }
  </style>
</head>
<body>
<div class="login-card">
  <div class="logo">
    <img src="/assets/img/logo-dark-bg.png" alt="Vi2ion AI" width="144" height="36" loading="eager">
  </div>
  <h1>Admin Login</h1>

  <?php if ($expired): ?>
    <div class="notice">Session expired. Please log in again.</div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrf) ?>" />

    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autocomplete="off"
           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" />

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required autocomplete="off" />

    <button type="submit" class="btn">Sign In →</button>
  </form>
  <div class="back"><a href="/">← Back to site</a></div>
</div>
</body>
</html>
