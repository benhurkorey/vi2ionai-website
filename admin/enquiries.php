<?php
/**
 * Vi2ionai Admin — Enquiries
 * Lists all lead enquiries. Supports single-row and bulk deletion.
 */
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

$db = getDB();
$flash = null;

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $flash = ['type' => 'error', 'msg' => 'Security check failed. Please try again.'];
    } else {
        $action = $_POST['action'];
        $page   = max(1, (int)($_POST['current_page'] ?? 1));

        // ── Single delete ─────────────────────────────────────────────────────
        if ($action === 'delete') {
            $id = (int)($_POST['enquiry_id'] ?? 0);
            if ($id < 1) {
                $flash = ['type' => 'error', 'msg' => 'Invalid enquiry ID.'];
            } else {
                $check = $db->prepare('SELECT id FROM enquiries WHERE id = ? LIMIT 1');
                $check->execute([$id]);
                if (!$check->fetch()) {
                    $flash = ['type' => 'error', 'msg' => "Enquiry #$id not found."];
                } else {
                    $stmt = $db->prepare('DELETE FROM enquiries WHERE id = ? LIMIT 1');
                    $stmt->execute([$id]);
                    if ($stmt->rowCount() === 1) {
                        header("Location: /admin/enquiries.php?page=$page&deleted=$id"); exit;
                    }
                    $flash = ['type' => 'error', 'msg' => 'Delete failed — please try again.'];
                }
            }

        // ── Bulk delete ───────────────────────────────────────────────────────
        } elseif ($action === 'delete_bulk') {
            // Sanitise: cast each to int, drop anything ≤ 0
            $ids = array_values(array_filter(
                array_map('intval', (array)($_POST['enquiry_ids'] ?? [])),
                fn($i) => $i > 0
            ));

            if (empty($ids)) {
                $flash = ['type' => 'error', 'msg' => 'No enquiries selected.'];
            } else {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $db->prepare("DELETE FROM enquiries WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $deleted = $stmt->rowCount();
                header("Location: /admin/enquiries.php?page=$page&bulk_deleted=$deleted"); exit;
            }
        }
    }
}

// ── Flash from PRG redirect ───────────────────────────────────────────────────
if ($flash === null) {
    if (!empty($_GET['deleted'])) {
        $flash = ['type' => 'success', 'msg' => 'Enquiry #'.(int)$_GET['deleted'].' deleted.'];
    } elseif (!empty($_GET['bulk_deleted'])) {
        $n = (int)$_GET['bulk_deleted'];
        $flash = ['type' => 'success', 'msg' => $n.' enquir'.($n === 1 ? 'y' : 'ies').' deleted successfully.'];
    }
}

// ── Pagination + fetch ────────────────────────────────────────────────────────
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$total = (int)$db->query('SELECT COUNT(*) FROM enquiries')->fetchColumn();
$pages = (int)ceil($total / $limit) ?: 1;

if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $limit; }

$stmt = $db->prepare('SELECT * FROM enquiries ORDER BY created_at DESC LIMIT ? OFFSET ?');
$stmt->execute([$limit, $offset]);
$enquiries = $stmt->fetchAll();

$csrf = generateCsrf();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Enquiries — Vi2ionai Admin</title>
  <meta name="robots" content="noindex, nofollow"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Manrope', sans-serif; background: #f8f9fa; color: #111; }
    a { text-decoration: none; color: inherit; }

    /* Sidebar */
    .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 240px; background: #0a0a0a; color: #fff; padding: 28px 20px; display: flex; flex-direction: column; z-index: 100; }
    .sidebar-logo { margin-bottom: 36px; display: flex; align-items: center; gap: 10px; }
    .sidebar-logo img { height: 28px; width: auto; }
    .sidebar-logo-badge { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #444; background: #1a1a1a; border-radius: 4px; padding: 2px 7px; }
    .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; font-size: .85rem; font-weight: 500; color: #888; margin-bottom: 4px; transition: .15s; }
    .nav-item:hover, .nav-item.active { background: #1a1a1a; color: #fff; }
    .sidebar-footer { margin-top: auto; }

    /* Layout */
    .main { margin-left: 240px; padding: 40px 48px; }
    .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
    .page-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -.02em; }

    /* Flash */
    .flash {
      display: flex; align-items: flex-start; gap: 12px;
      padding: 14px 18px; border-radius: 10px; margin-bottom: 24px;
      font-size: .87rem; font-weight: 600; line-height: 1.4;
    }
    .flash-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
    .flash-error   { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

    /* Card & table */
    .card { background: #fff; border: 1px solid #eee; border-radius: 12px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; }
    th {
      background: #f8f9fa; text-align: left; padding: 12px 16px;
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .06em; color: #888; border-bottom: 1px solid #eee;
    }
    th.th-check { width: 44px; padding-left: 18px; }
    td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: .85rem; vertical-align: top; }
    td.td-check { padding-left: 18px; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }
    tr.row-selected td { background: #f0f4ff; }
    .msg-cell { max-width: 240px; color: #555; font-size: .8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .email-link { color: #1d4ed8; }

    /* Checkbox */
    input[type="checkbox"] {
      width: 16px; height: 16px; cursor: pointer;
      accent-color: #0a0a0a;
    }

    /* Toolbar */
    .toolbar {
      display: flex; align-items: center; gap: 12px;
      padding: 12px 18px; border-bottom: 1px solid #eee;
      background: #fff; min-height: 54px;
    }
    .toolbar-count {
      font-size: .82rem; font-weight: 600; color: #888;
      transition: color .15s;
    }
    .toolbar-count.has-selection { color: #111; }

    .btn-delete-selected {
      display: inline-flex; align-items: center; gap: 7px;
      background: #dc2626; color: #fff; border: none;
      border-radius: 7px; padding: 7px 16px; font-size: .82rem;
      font-weight: 700; cursor: pointer; font-family: inherit;
      transition: .15s; opacity: .4; pointer-events: none;
    }
    .btn-delete-selected.active { opacity: 1; pointer-events: auto; }
    .btn-delete-selected.active:hover { background: #b91c1c; }

    /* Per-row delete */
    .btn-delete {
      display: inline-flex; align-items: center; gap: 6px;
      background: transparent; border: 1px solid #fee2e2; color: #dc2626;
      border-radius: 7px; padding: 5px 12px; font-size: .76rem; font-weight: 700;
      cursor: pointer; font-family: inherit; transition: .15s; white-space: nowrap;
    }
    .btn-delete:hover { background: #fef2f2; border-color: #fca5a5; }

    /* Modal overlay */
    .overlay {
      display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
      z-index: 1000; align-items: center; justify-content: center;
    }
    .overlay.visible { display: flex; }
    .modal {
      background: #fff; border-radius: 16px; padding: 36px 32px;
      max-width: 420px; width: 92%; box-shadow: 0 24px 64px rgba(0,0,0,.2);
      text-align: center;
    }
    .modal-icon {
      width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 18px;
      display: flex; align-items: center; justify-content: center;
    }
    .modal-icon-red  { background: #fef2f2; color: #dc2626; }
    .modal-title  { font-size: 1.1rem; font-weight: 800; margin-bottom: 8px; letter-spacing: -.01em; }
    .modal-detail { font-size: .875rem; color: #555; line-height: 1.6; margin-bottom: 28px; }
    .modal-detail strong { color: #111; }
    .modal-actions { display: flex; gap: 10px; justify-content: center; }
    .btn-cancel {
      padding: 11px 24px; border: 1.5px solid #e5e7eb; border-radius: 8px;
      background: #fff; font-size: .875rem; font-weight: 700; cursor: pointer;
      font-family: inherit; transition: .15s;
    }
    .btn-cancel:hover { background: #f9fafb; }
    .btn-confirm-del {
      padding: 11px 24px; background: #dc2626; color: #fff; border: none;
      border-radius: 8px; font-size: .875rem; font-weight: 700; cursor: pointer;
      font-family: inherit; transition: .15s;
    }
    .btn-confirm-del:hover { background: #b91c1c; }

    /* Pagination */
    .pagination { display: flex; gap: 8px; justify-content: center; padding: 20px; }
    .page-btn { padding: 6px 14px; border: 1px solid #eee; border-radius: 6px; font-size: .82rem; font-weight: 600; background: #fff; cursor: pointer; }
    .page-btn.active { background: #000; color: #fff; border-color: #000; }
    .page-btn:hover:not(.active) { background: #f8f9fa; }

    @media (max-width: 768px) {
      .main { margin-left: 0; padding: 20px 16px; }
      .sidebar { display: none; }
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <div class="sidebar-logo">
    <img src="/assets/img/logo-dark-bg.png" alt="Vi2ion AI" width="112" height="28" loading="eager">
    <span class="sidebar-logo-badge">Admin</span>
  </div>
  <a class="nav-item" href="/admin/dashboard.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a class="nav-item active" href="/admin/enquiries.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    Enquiries (<?= $total ?>)
  </a>
  <a class="nav-item" href="/admin/product-edit.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Add Product
  </a>
  <div class="sidebar-footer">
    <a href="/" style="display:block;color:#555;font-size:.78rem;padding:8px 12px;margin-bottom:8px">← View Site</a>
    <form method="POST" action="/admin/logout.php">
      <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrf) ?>">
      <button type="submit" style="width:100%;background:none;border:none;color:#555;font-size:.78rem;padding:8px 12px;text-align:left;cursor:pointer;font-family:inherit;">Sign Out</button>
    </form>
  </div>
</div>

<!-- Main -->
<div class="main">
  <div class="page-header">
    <div class="page-title">
      Enquiries
      <span style="font-size:1rem;font-weight:500;color:#999">(<?= $total ?> total)</span>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="flash flash-<?= $flash['type'] ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;margin-top:1px">
        <?php if ($flash['type'] === 'success'): ?>
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        <?php else: ?>
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        <?php endif; ?>
      </svg>
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <div class="card">

    <!-- Bulk action toolbar -->
    <div class="toolbar">
      <span class="toolbar-count" id="toolbar-count">0 selected</span>
      <button type="button" class="btn-delete-selected" id="bulk-delete-btn" onclick="openBulkConfirm()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        Delete Selected
      </button>
    </div>

    <table>
      <thead>
        <tr>
          <th class="th-check">
            <input type="checkbox" id="select-all" title="Select all on this page" onchange="toggleSelectAll(this)">
          </th>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Company</th>
          <th>Fleet Size</th>
          <th>Message</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="enquiry-tbody">
      <?php foreach ($enquiries as $e): ?>
        <tr id="row-<?= (int)$e['id'] ?>">
          <td class="td-check">
            <input type="checkbox"
              class="row-check"
              value="<?= (int)$e['id'] ?>"
              onchange="updateSelection()">
          </td>
          <td style="color:#bbb;font-size:.78rem"><?= (int)$e['id'] ?></td>
          <td><strong><?= htmlspecialchars(trim($e['first_name'].' '.$e['last_name'])) ?></strong></td>
          <td><a href="mailto:<?= htmlspecialchars($e['email']) ?>" class="email-link"><?= htmlspecialchars($e['email']) ?></a></td>
          <td><?= htmlspecialchars($e['company'] ?? '—') ?></td>
          <td><?= htmlspecialchars($e['fleet_size'] ?? '—') ?></td>
          <td class="msg-cell" title="<?= htmlspecialchars($e['message'] ?? '') ?>"><?= htmlspecialchars($e['message'] ?? '') ?></td>
          <td style="white-space:nowrap;color:#888;font-size:.78rem"><?= date('d M Y', strtotime($e['created_at'])) ?></td>
          <td>
            <button
              class="btn-delete"
              type="button"
              data-id="<?= (int)$e['id'] ?>"
              data-name="<?= htmlspecialchars(trim($e['first_name'].' '.$e['last_name']), ENT_QUOTES) ?>"
              data-email="<?= htmlspecialchars($e['email'], ENT_QUOTES) ?>"
              onclick="openSingleConfirm(this)"
            >
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              Delete
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($enquiries)): ?>
        <tr><td colspan="9" style="text-align:center;color:#bbb;padding:40px">No enquiries yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Confirmation modal (single + bulk reuse) ──────────────────────────── -->
<div class="overlay" id="confirm-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-title">
  <div class="modal">
    <div class="modal-icon modal-icon-red">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
    </div>
    <div class="modal-title" id="modal-title">Delete Enquiry?</div>
    <div class="modal-detail" id="modal-detail">This cannot be undone.</div>
    <div class="modal-actions">
      <button class="btn-cancel" type="button" onclick="closeModal()">Cancel</button>
      <button class="btn-confirm-del" type="button" id="modal-confirm-btn" onclick="executeDelete()">Delete</button>
    </div>
  </div>
</div>

<!-- Hidden single-delete form -->
<form id="single-delete-form" method="POST" action="/admin/enquiries.php" style="display:none">
  <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="enquiry_id" id="single-delete-id" value="">
  <input type="hidden" name="current_page" value="<?= $page ?>">
</form>

<!-- Hidden bulk-delete form (IDs injected by JS) -->
<form id="bulk-delete-form" method="POST" action="/admin/enquiries.php" style="display:none">
  <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" name="action" value="delete_bulk">
  <input type="hidden" name="current_page" value="<?= $page ?>">
  <div id="bulk-id-container"></div>
</form>

<script>
// ── Checkbox state ────────────────────────────────────────────────────────────
let pendingMode = null; // 'single' | 'bulk'

function getChecked() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
}

function updateSelection() {
  const checked = getChecked();
  const n = checked.length;
  const total = document.querySelectorAll('.row-check').length;
  const allCb = document.getElementById('select-all');
  const countEl = document.getElementById('toolbar-count');
  const delBtn = document.getElementById('bulk-delete-btn');

  // Update select-all indeterminate state
  allCb.indeterminate = n > 0 && n < total;
  allCb.checked = n > 0 && n === total;

  // Update toolbar
  countEl.textContent = n === 0 ? '0 selected' : `${n} selected`;
  countEl.classList.toggle('has-selection', n > 0);
  delBtn.classList.toggle('active', n > 0);

  // Highlight rows
  document.querySelectorAll('.row-check').forEach(cb => {
    cb.closest('tr').classList.toggle('row-selected', cb.checked);
  });
}

function toggleSelectAll(cb) {
  document.querySelectorAll('.row-check').forEach(box => { box.checked = cb.checked; });
  updateSelection();
}

// ── Single delete ─────────────────────────────────────────────────────────────
function openSingleConfirm(btn) {
  pendingMode = 'single';
  const name  = escHtml(btn.dataset.name);
  const email = escHtml(btn.dataset.email);
  document.getElementById('single-delete-id').value = btn.dataset.id;
  document.getElementById('modal-title').textContent = 'Delete Enquiry?';
  document.getElementById('modal-detail').innerHTML =
    'You are about to permanently delete the enquiry from<br>' +
    '<strong>' + name + '</strong> (' + email + ').<br><br>' +
    'This <strong>cannot</strong> be undone.';
  document.getElementById('modal-confirm-btn').textContent = 'Delete';
  openModal();
}

// ── Bulk delete ───────────────────────────────────────────────────────────────
function openBulkConfirm() {
  const ids = getChecked();
  if (!ids.length) return;
  pendingMode = 'bulk';
  const n = ids.length;
  document.getElementById('modal-title').textContent = `Delete ${n} Enqu${n === 1 ? 'iry' : 'iries'}?`;
  document.getElementById('modal-detail').innerHTML =
    'You are about to permanently delete <strong>' + n + ' selected enqu' + (n === 1 ? 'iry' : 'iries') + '</strong>.<br><br>' +
    'This <strong>cannot</strong> be undone.';
  document.getElementById('modal-confirm-btn').textContent = `Delete ${n}`;
  openModal();
}

// ── Modal helpers ─────────────────────────────────────────────────────────────
function openModal() {
  document.getElementById('confirm-overlay').classList.add('visible');
  document.getElementById('modal-confirm-btn').focus();
}

function closeModal() {
  pendingMode = null;
  document.getElementById('confirm-overlay').classList.remove('visible');
}

function executeDelete() {
  if (pendingMode === 'single') {
    document.getElementById('single-delete-form').submit();
  } else if (pendingMode === 'bulk') {
    // Inject selected IDs as hidden inputs
    const container = document.getElementById('bulk-id-container');
    container.innerHTML = '';
    getChecked().forEach(id => {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'enquiry_ids[]';
      inp.value = id;
      container.appendChild(inp);
    });
    document.getElementById('bulk-delete-form').submit();
  }
}

// Close on backdrop click or Escape
document.getElementById('confirm-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});

// Auto-dismiss success flash after 6 s
const flashEl = document.querySelector('.flash-success');
if (flashEl) setTimeout(() => {
  flashEl.style.transition = 'opacity .5s';
  flashEl.style.opacity = '0';
  setTimeout(() => flashEl.remove(), 500);
}, 6000);

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
