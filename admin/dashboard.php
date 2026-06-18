<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

$db = getDB();

// Maintenance flag lives in storage/ (writable by www-data, excluded from rsync)
$maintenanceFlagPath = __DIR__ . '/../storage/.maintenance';

// Business stats
$totalProducts   = (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$visibleProducts = (int)$db->query('SELECT COUNT(*) FROM products WHERE is_visible = 1')->fetchColumn();
$totalEnquiries  = (int)$db->query('SELECT COUNT(*) FROM enquiries')->fetchColumn();
$newEnquiries    = (int)$db->query('SELECT COUNT(*) FROM enquiries WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
$todayEnquiries  = (int)$db->query('SELECT COUNT(*) FROM enquiries WHERE DATE(created_at) = CURDATE()')->fetchColumn();
$products        = $db->query('SELECT id, name, price, category, badge, is_visible, sort_order FROM products ORDER BY sort_order, id')->fetchAll();

$maintenanceActive = file_exists($maintenanceFlagPath);
$csrf = generateCsrf();
$flash = null;

// ── Handle all POST actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $flash = ['type' => 'error', 'msg' => 'Invalid request — please try again.'];
    } else {
        $action = $_POST['action'] ?? '';

        // ── Maintenance mode toggle ──
        if ($action === 'maintenance_on') {
            $dir = dirname($maintenanceFlagPath);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            if (@touch($maintenanceFlagPath)) {
                header('Location: /admin/dashboard.php?mm=on'); exit;
            }
            $flash = ['type' => 'error', 'msg' => 'Could not enable maintenance mode — storage directory permission error. Contact your server admin.'];

        } elseif ($action === 'maintenance_off') {
            if (!file_exists($maintenanceFlagPath) || @unlink($maintenanceFlagPath)) {
                header('Location: /admin/dashboard.php?mm=off'); exit;
            }
            $flash = ['type' => 'error', 'msg' => 'Could not disable maintenance mode — permission error. Try: rm /var/www/vi2ionai/storage/.maintenance'];

        // ── Product quick actions ──
        } else {
            $pid = (int)($_POST['product_id'] ?? 0);
            if ($action === 'toggle_visible' && $pid) {
                $db->prepare('UPDATE products SET is_visible = 1 - is_visible WHERE id = ?')->execute([$pid]);
            } elseif ($action === 'delete' && $pid) {
                $db->prepare('DELETE FROM products WHERE id = ?')->execute([$pid]);
                $flash = ['type' => 'success', 'msg' => 'Product deleted.'];
            }
            if (!$flash) { header('Location: /admin/dashboard.php'); exit; }
        }
    }
}

// ── Flash from PRG redirect ───────────────────────────────────────────────────
if (!$flash && isset($_GET['mm'])) {
    // Re-read actual flag state after redirect
    $maintenanceActive = file_exists($maintenanceFlagPath);
    if ($_GET['mm'] === 'on') {
        $flash = ['type' => 'warn', 'msg' => 'Maintenance mode is now <strong>ON</strong>. Visitors see the 503 page. Your admin access is unaffected.'];
    } elseif ($_GET['mm'] === 'off') {
        $flash = ['type' => 'success', 'msg' => 'Maintenance mode <strong>disabled</strong>. The public site is now live.'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — Vi2ionai Admin</title>
  <meta name="robots" content="noindex, nofollow"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Manrope', sans-serif; background: #f8f9fa; color: #111; }
    a { text-decoration: none; color: inherit; }

    /* Layout */
    .sidebar {
      position: fixed; top: 0; left: 0; bottom: 0; width: 240px;
      background: #0a0a0a; color: #fff; padding: 28px 20px;
      display: flex; flex-direction: column; z-index: 100;
    }
    .sidebar-logo { margin-bottom: 36px; display: flex; align-items: center; gap: 10px; }
    .sidebar-logo img { height: 28px; width: auto; }
    .sidebar-logo-badge { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #444; background: #1a1a1a; border-radius: 4px; padding: 2px 7px; }
    .nav-item {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 8px;
      font-size: .85rem; font-weight: 500; color: #888;
      margin-bottom: 4px; cursor: pointer; transition: .15s;
    }
    .nav-item:hover, .nav-item.active { background: #1a1a1a; color: #fff; }
    .sidebar-footer { margin-top: auto; }
    .logout-btn {
      display: block; width: 100%; background: #1a1a1a; color: #888;
      border: none; border-radius: 8px; padding: 10px 12px;
      font-size: .82rem; font-weight: 600; font-family: inherit;
      cursor: pointer; text-align: left; transition: .15s;
    }
    .logout-btn:hover { color: #fff; }

    .main { margin-left: 240px; padding: 40px 48px; }
    .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 36px; }
    .page-title { font-size: 1.6rem; font-weight: 800; letter-spacing: -.02em; }
    .btn-primary {
      display: inline-flex; align-items: center; gap: 8px;
      background: #000; color: #fff; border: none;
      border-radius: 8px; padding: 10px 20px; font-size: .85rem;
      font-weight: 700; font-family: inherit; cursor: pointer;
      transition: opacity .15s; text-decoration: none;
    }
    .btn-primary:hover { opacity: .8; }

    /* Flash */
    .flash {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 14px 18px; border-radius: 10px; margin-bottom: 24px;
      font-size: .875rem; font-weight: 500; line-height: 1.5;
    }
    .flash-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
    .flash-error   { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
    .flash-warn    { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

    /* Stats */
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
    .stat-card {
      background: #fff; border: 1px solid #eee; border-radius: 12px;
      padding: 24px 28px; position: relative; overflow: hidden;
    }
    .stat-card-dark  { background: #0a0a0a; border-color: #0a0a0a; color: #fff; }
    .stat-card-warn  { background: #fffbeb; border-color: #fde68a; }
    .stat-card-green { border-left: 3px solid #16a34a; }
    .stat-label { font-size: .72rem; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 10px; }
    .stat-card-dark .stat-label  { color: #666; }
    .stat-card-warn .stat-label  { color: #92400e; }
    .stat-value { font-size: 2.2rem; font-weight: 800; letter-spacing: -.04em; line-height: 1; }
    .stat-card-dark .stat-value  { color: #fff; }
    .stat-card-warn .stat-value  { color: #92400e; font-size: 1.1rem; padding-top: 4px; }
    .stat-sub { font-size: .78rem; color: #999; margin-top: 6px; }
    .stat-card-dark .stat-sub    { color: #555; }
    .stat-card-warn .stat-sub    { color: #b45309; }
    .stat-icon {
      position: absolute; top: 20px; right: 20px;
      width: 32px; height: 32px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      background: #f3f4f6;
    }
    .stat-card-dark .stat-icon  { background: #1a1a1a; }
    .stat-card-warn .stat-icon  { background: #fef3c7; }
    .stat-badge-new {
      display: inline-block; background: #0a0a0a; color: #fff;
      border-radius: 20px; padding: 2px 8px; font-size: .68rem;
      font-weight: 700; margin-left: 6px; vertical-align: middle;
    }
    @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }

    /* Maintenance mode panel */
    .maint-panel {
      background: #fff; border: 1px solid #eee; border-radius: 12px;
      padding: 24px 28px; margin-bottom: 28px; display: flex;
      align-items: center; justify-content: space-between; gap: 24px;
      flex-wrap: wrap;
    }
    .maint-panel-warn { background: #fffbeb; border-color: #fde68a; }
    .maint-info { display: flex; align-items: center; gap: 14px; }
    .maint-dot {
      width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0;
    }
    .maint-dot-on  { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.2); animation: pulse-dot 2s ease-in-out infinite; }
    .maint-dot-off { background: #16a34a; }
    @keyframes pulse-dot { 0%,100%{ opacity:1; transform:scale(1); } 50%{ opacity:.6; transform:scale(.85); } }
    .maint-label { font-size: .88rem; font-weight: 700; }
    .maint-sub   { font-size: .78rem; color: #888; margin-top: 2px; }
    .maint-panel-warn .maint-sub { color: #b45309; }

    .btn-maint-enable {
      display: inline-flex; align-items: center; gap: 8px;
      background: #fff; border: 1.5px solid #d1d5db; border-radius: 8px;
      padding: 10px 20px; font-size: .85rem; font-weight: 700;
      color: #374151; cursor: pointer; font-family: inherit; transition: .15s;
      white-space: nowrap;
    }
    .btn-maint-enable:hover { border-color: #f59e0b; color: #92400e; background: #fffbeb; }

    .btn-maint-disable {
      display: inline-flex; align-items: center; gap: 8px;
      background: #0a0a0a; color: #fff; border: none;
      border-radius: 8px; padding: 10px 20px; font-size: .85rem;
      font-weight: 700; cursor: pointer; font-family: inherit; transition: .15s;
      white-space: nowrap;
    }
    .btn-maint-disable:hover { background: #16a34a; }

    /* Products table */
    .section-card { background: #fff; border: 1px solid #eee; border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
    .section-header {
      padding: 20px 24px; border-bottom: 1px solid #eee;
      display: flex; align-items: center; justify-content: space-between;
    }
    .section-title { font-size: 1rem; font-weight: 700; }
    table { width: 100%; border-collapse: collapse; }
    th {
      background: #f8f9fa; text-align: left; padding: 12px 16px;
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .06em; color: #888; border-bottom: 1px solid #eee;
    }
    td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: .875rem; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }
    .product-name { font-weight: 700; }
    .product-cat  { font-size: .78rem; color: #999; margin-top: 2px; }

    .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .7rem; font-weight: 700; }
    .badge-dark    { background: #0a0a0a; color: #fff; }
    .badge-blue    { background: #dbeafe; color: #1d4ed8; }
    .badge-outline { background: transparent; border: 1.5px solid #ddd; color: #555; }

    .vis-toggle {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: .78rem; font-weight: 600; cursor: pointer;
      padding: 4px 10px; border-radius: 6px; border: 1.5px solid;
      transition: .15s; background: transparent; font-family: inherit;
    }
    .vis-on  { border-color: #16a34a; color: #16a34a; }
    .vis-on:hover  { background: #f0fdf4; }
    .vis-off { border-color: #dc2626; color: #dc2626; }
    .vis-off:hover { background: #fef2f2; }

    .action-btns { display: flex; gap: 8px; }
    .btn-edit {
      padding: 6px 14px; background: #f8f9fa; border: 1px solid #eee;
      border-radius: 6px; font-size: .78rem; font-weight: 600;
      cursor: pointer; font-family: inherit; transition: .15s;
    }
    .btn-edit:hover { background: #000; color: #fff; border-color: #000; }
    .btn-del {
      padding: 6px 10px; background: transparent; border: 1px solid #fee2e2;
      border-radius: 6px; font-size: .78rem; font-weight: 600;
      color: #dc2626; cursor: pointer; font-family: inherit; transition: .15s;
    }
    .btn-del:hover { background: #fef2f2; }
  </style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-logo">
    <img src="/assets/img/logo-dark-bg.png" alt="Vi2ion AI" width="112" height="28" loading="eager">
    <span class="sidebar-logo-badge">Admin</span>
  </div>
  <a class="nav-item active" href="/admin/dashboard.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a class="nav-item" href="/admin/product-edit.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Product
  </a>
  <a class="nav-item" href="/admin/enquiries.php">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    Enquiries <span style="background:#000;color:#fff;border-radius:10px;padding:1px 7px;font-size:.7rem;margin-left:4px"><?= (int)$newEnquiries ?></span>
  </a>
  <div class="sidebar-footer">
    <a href="/" style="display:block;color:#555;font-size:.78rem;padding:8px 12px;margin-bottom:8px">← View Site</a>
    <form method="POST" action="/admin/logout.php">
      <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrf) ?>">
      <button type="submit" class="logout-btn">Sign Out</button>
    </form>
  </div>
</div>

<div class="main">
  <div class="page-header">
    <div class="page-title">Dashboard</div>
    <a href="/admin/product-edit.php" class="btn-primary">+ Add Product</a>
  </div>

  <?php if ($flash): ?>
    <div class="flash flash-<?= $flash['type'] ?>">
      <?php if ($flash['type'] === 'success'): ?>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;margin-top:1px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <?php elseif ($flash['type'] === 'warn'): ?>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;margin-top:1px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <?php else: ?>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php endif; ?>
      <span><?= $flash['msg'] ?></span>
    </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card stat-card-dark">
      <div class="stat-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
      </div>
      <div class="stat-label">Total Products</div>
      <div class="stat-value"><?= $totalProducts ?></div>
      <div class="stat-sub"><?= $visibleProducts ?> visible &middot; <?= $totalProducts - $visibleProducts ?> hidden</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      </div>
      <div class="stat-label">Live on Site</div>
      <div class="stat-value"><?= $visibleProducts ?></div>
      <div class="stat-sub">Visible to visitors</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      </div>
      <div class="stat-label">Total Enquiries</div>
      <div class="stat-value">
        <?= $totalEnquiries ?>
        <?php if ($todayEnquiries > 0): ?>
          <span class="stat-badge-new">+<?= $todayEnquiries ?> today</span>
        <?php endif; ?>
      </div>
      <div class="stat-sub"><?= $newEnquiries ?> this week</div>
    </div>

    <?php if ($maintenanceActive): ?>
    <div class="stat-card stat-card-warn">
      <div class="stat-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div class="stat-label">Site Status</div>
      <div class="stat-value">Maintenance</div>
      <div class="stat-sub">Visitors see 503 page</div>
    </div>
    <?php else: ?>
    <div class="stat-card">
      <div class="stat-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div class="stat-label">Site Status</div>
      <div class="stat-value" style="font-size:1.3rem;padding-top:2px;color:#16a34a">Live</div>
      <div class="stat-sub">Public site is online</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Maintenance Mode Toggle ─────────────────────────────────────────── -->
  <?php if ($maintenanceActive): ?>
  <div class="maint-panel maint-panel-warn">
    <div class="maint-info">
      <span class="maint-dot maint-dot-on"></span>
      <div>
        <div class="maint-label">Maintenance Mode is ON</div>
        <div class="maint-sub">Public visitors see the maintenance page. Admin panel remains accessible.</div>
      </div>
    </div>
    <form method="POST">
      <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="maintenance_off">
      <button type="submit" class="btn-maint-disable">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Disable — Bring Site Live
      </button>
    </form>
  </div>
  <?php else: ?>
  <div class="maint-panel">
    <div class="maint-info">
      <span class="maint-dot maint-dot-off"></span>
      <div>
        <div class="maint-label">Maintenance Mode is OFF</div>
        <div class="maint-sub">Site is live. Enable to show a maintenance page to public visitors.</div>
      </div>
    </div>
    <form method="POST" onsubmit="return confirm('Enable maintenance mode?\n\nVisitors will see the maintenance page until you disable it.\nYour admin panel will stay accessible.')">
      <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="maintenance_on">
      <button type="submit" class="btn-maint-enable">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Enable Maintenance Mode
      </button>
    </form>
  </div>
  <?php endif; ?>

  <!-- ── Products Table ──────────────────────────────────────────────────── -->
  <div class="section-card">
    <div class="section-header">
      <div class="section-title">Products</div>
      <a href="/admin/product-edit.php" class="btn-primary" style="font-size:.8rem;padding:8px 16px">+ New</a>
    </div>
    <table>
      <thead>
        <tr>
          <th>Order</th>
          <th>Product</th>
          <th>Price</th>
          <th>Badge</th>
          <th>Visible</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td style="color:#bbb;font-size:.8rem"><?= (int)$p['sort_order'] ?></td>
          <td>
            <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="product-cat"><?= htmlspecialchars($p['category']) ?></div>
          </td>
          <td><?= $p['price'] > 0 ? '$'.number_format((float)$p['price'],2) : '<span style="color:#999">Contact</span>' ?></td>
          <td>
            <?php if ($p['badge']): ?>
              <span class="badge <?= htmlspecialchars($p['badge_class'] ?? 'badge-dark') ?>">
                <?= htmlspecialchars($p['badge']) ?>
              </span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="action" value="toggle_visible">
              <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="vis-toggle <?= $p['is_visible'] ? 'vis-on' : 'vis-off' ?>">
                <?= $p['is_visible'] ? '● Visible' : '○ Hidden' ?>
              </button>
            </form>
          </td>
          <td>
            <div class="action-btns">
              <a href="/admin/product-edit.php?id=<?= (int)$p['id'] ?>" class="btn-edit">Edit</a>
              <form method="POST" onsubmit="return confirm('Delete \'<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>\'?\nThis cannot be undone.')">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <button type="submit" class="btn-del">✕</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
