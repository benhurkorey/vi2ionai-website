<?php
/**
 * Vi2ionai Admin — KORE SMS Replies + Send Command
 * Send commands via KORE SMS API and view tracker SMS-MO replies,
 * captured automatically via /api/kore-sms-webhook.php.
 */
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db.php';

$db = getDB();

$search = trim((string) ($_GET['q'] ?? ''));
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

$where  = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE from_sim LIKE ? OR to_number LIKE ? OR message LIKE ? OR mid LIKE ?';
    $like  = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM kore_sms_events $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pages = (int) ceil($total / $limit) ?: 1;
if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $limit; }

$stmt = $db->prepare(
    "SELECT id, mid, event_type, direction, to_number, from_sim, message, received_at
     FROM kore_sms_events $where
     ORDER BY received_at DESC, id DESC
     LIMIT ? OFFSET ?"
);
foreach ($params as $i => $p) { $stmt->bindValue($i + 1, $p); }
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll();

$latest = (int) $db->query('SELECT COUNT(*) FROM kore_sms_events WHERE received_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)')->fetchColumn();

// ── Outbound commands log (most recent 25) + best-effort reply correlation ──
$outbound = $db->query(
    'SELECT id, target_type, target_value, command, kore_mid, api_http_code, api_status, api_message, created_at
     FROM kore_outbound_commands ORDER BY created_at DESC LIMIT 25'
)->fetchAll();

// Pull recent inbound replies (wider window than the paged table above) to correlate against.
$recentInbound = $db->query(
    "SELECT id, from_sim, message, received_at FROM kore_sms_events
     WHERE received_at > DATE_SUB(NOW(), INTERVAL 2 DAY)
     ORDER BY received_at ASC"
)->fetchAll();

$usedInboundIds = [];
foreach ($outbound as &$cmd) {
    $cmd['matched_message']  = null;
    $cmd['matched_time']     = null;
    $cmd['matched_confidence'] = null;
    $sentAt = strtotime($cmd['created_at']);

    foreach ($recentInbound as $in) {
        if (in_array($in['id'], $usedInboundIds, true)) continue;
        $recvAt = strtotime($in['received_at']);
        if ($recvAt < $sentAt) continue;

        if ($cmd['target_type'] === 'msisdn' && $in['from_sim'] === $cmd['target_value']) {
            $cmd['matched_message']    = $in['message'];
            $cmd['matched_time']       = $in['received_at'];
            $cmd['matched_confidence'] = 'confirmed';
            $usedInboundIds[] = $in['id'];
            break;
        }
        // ICCID target: we can't verify the SIM identity directly, so only
        // accept a very close time-proximity match (within 10 minutes) and
        // label it as unverified.
        if ($cmd['target_type'] === 'iccid' && $cmd['matched_confidence'] === null && ($recvAt - $sentAt) <= 600) {
            $cmd['matched_message']    = $in['message'];
            $cmd['matched_time']       = $in['received_at'];
            $cmd['matched_confidence'] = 'likely';
            $usedInboundIds[] = $in['id'];
            // keep scanning in case a msisdn-confirmed command claims this row first for a later cmd —
            // simplistic best-effort matching, not a guarantee.
        }
    }
}
unset($cmd);

$csrf = generateCsrf();
$queryCommands = ['APN#', 'STATUS#', 'SERVER#', 'PARAM#', 'VERSION#'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KORE SMS Replies — Vi2ionai Admin</title>
  <meta name="robots" content="noindex, nofollow"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Manrope', sans-serif; background: #f8f9fa; color: #111; }
    a { text-decoration: none; color: inherit; }

    .sidebar { position: fixed; top: 0; left: 0; bottom: 0; width: 240px; background: #0a0a0a; color: #fff; padding: 28px 20px; display: flex; flex-direction: column; z-index: 100; }
    .sidebar-logo { margin-bottom: 36px; display: flex; align-items: center; gap: 10px; }
    .sidebar-logo img { height: 28px; width: auto; }
    .sidebar-logo-badge { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #444; background: #1a1a1a; border-radius: 4px; padding: 2px 7px; }
    .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; font-size: .85rem; font-weight: 500; color: #888; margin-bottom: 4px; transition: .15s; }
    .nav-item:hover, .nav-item.active { background: #1a1a1a; color: #fff; }
    .sidebar-footer { margin-top: auto; }

    .main { margin-left: 240px; padding: 40px 48px; }
    .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 12px; }
    .page-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -.02em; }
    .page-sub { font-size: .85rem; color: #888; margin-bottom: 24px; }
    .section-title { font-size: 1rem; font-weight: 800; margin: 32px 0 12px; }
    .section-title:first-of-type { margin-top: 0; }

    .stats-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .stat-pill { background: #fff; border: 1px solid #eee; border-radius: 10px; padding: 14px 20px; }
    .stat-pill .n { font-size: 1.4rem; font-weight: 800; }
    .stat-pill .l { font-size: .72rem; color: #888; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; }

    /* Send Command panel */
    .send-card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 24px; margin-bottom: 8px; }
    .send-grid { display: grid; grid-template-columns: 140px 1fr 1fr auto; gap: 14px; align-items: end; }
    .field label { display: block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 6px; }
    .field select, .field input { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; font-size: .85rem; background: #fff; }
    .btn-send { padding: 11px 26px; background: #000; color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: .85rem; cursor: pointer; font-family: inherit; white-space: nowrap; }
    .btn-send:disabled { opacity: .5; cursor: not-allowed; }
    .btn-send.danger { background: #dc2626; }
    .custom-row { margin-top: 14px; display: none; }
    .custom-row.visible { display: block; }
    .send-result { margin-top: 16px; padding: 14px 16px; border-radius: 8px; font-size: .85rem; display: none; }
    .send-result.visible { display: block; }
    .send-result.ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
    .send-result.err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
    .send-result .mid { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: .78rem; }
    .hint { font-size: .78rem; color: #999; margin-top: 10px; }

    .search-bar { display: flex; gap: 10px; margin-bottom: 18px; }
    .search-bar input { flex: 1; max-width: 380px; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; font-size: .85rem; }
    .search-bar button { padding: 10px 20px; background: #000; color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: .85rem; cursor: pointer; font-family: inherit; }
    .search-bar a.clear { padding: 10px 16px; color: #888; font-size: .82rem; align-self: center; }

    .card { background: #fff; border: 1px solid #eee; border-radius: 12px; overflow: hidden; margin-bottom: 8px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f8f9fa; text-align: left; padding: 12px 16px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #888; border-bottom: 1px solid #eee; white-space: nowrap; }
    td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: .85rem; vertical-align: top; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }
    .mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: .78rem; color: #444; }
    .msg-cell { max-width: 320px; font-weight: 700; word-break: break-word; }
    .dir-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: .68rem; font-weight: 700; background: #dbeafe; color: #1d4ed8; }
    .status-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: .68rem; font-weight: 700; }
    .status-ok   { background: #dcfce7; color: #15803d; }
    .status-fail { background: #fee2e2; color: #b91c1c; }
    .match-confirmed { background: #dcfce7; color: #15803d; }
    .match-likely     { background: #fef3c7; color: #92400e; }
    .match-none        { color: #bbb; font-style: italic; }
    .time-cell { white-space: nowrap; color: #666; font-size: .78rem; }
    .pagination { display: flex; gap: 8px; justify-content: center; padding: 20px; }
    .page-btn { padding: 6px 14px; border: 1px solid #eee; border-radius: 6px; font-size: .82rem; font-weight: 600; background: #fff; }
    .page-btn.active { background: #000; color: #fff; border-color: #000; }

    @media (max-width: 900px) { .send-grid { grid-template-columns: 1fr; } .main { margin-left: 0; padding: 20px 16px; } .sidebar { display: none; } }
  </style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-logo">
    <img src="/assets/img/logo-dark-bg.png" alt="Vi2ion AI" width="112" height="28" loading="eager">
    <span class="sidebar-logo-badge">Admin</span>
  </div>
  <a class="nav-item" href="/admin/dashboard.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Dashboard
  </a>
  <a class="nav-item" href="/admin/enquiries.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    Enquiries
  </a>
  <a class="nav-item active" href="/admin/kore-sms.php">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M8 10h8M8 14h5"/></svg>
    KORE SMS Replies
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

<div class="main">
  <div class="page-header">
    <div class="page-title">KORE SMS Replies</div>
  </div>
  <div class="page-sub">Send a command to a tracker and see its reply — no Swagger, no chat.</div>

  <div class="stats-row">
    <div class="stat-pill"><div class="n"><?= $total ?></div><div class="l">Total Replies</div></div>
    <div class="stat-pill"><div class="n"><?= $latest ?></div><div class="l">Last Hour</div></div>
  </div>

  <!-- ── Send SMS Command ──────────────────────────────────────────────── -->
  <div class="section-title">Send SMS Command</div>
  <div class="send-card">
    <div class="send-grid">
      <div class="field">
        <label for="target_type">Target Type</label>
        <select id="target_type">
          <option value="iccid">ICCID</option>
          <option value="msisdn">MSISDN</option>
        </select>
      </div>
      <div class="field">
        <label for="target_value">ICCID / MSISDN</label>
        <input type="text" id="target_value" placeholder="e.g. 8910390000101020253" inputmode="numeric">
      </div>
      <div class="field">
        <label for="command_select">Command</label>
        <select id="command_select">
          <option value="APN#">APN#  (query)</option>
          <option value="STATUS#">STATUS#  (query)</option>
          <option value="SERVER#">SERVER#  (query)</option>
          <option value="PARAM#">PARAM#  (query)</option>
          <option value="VERSION#">VERSION#  (query)</option>
          <option value="RESET#">RESET#  (⚠ action — reboots device)</option>
          <option value="__custom__">Custom command…</option>
        </select>
      </div>
      <button type="button" class="btn-send" id="send-btn">Send Command</button>
    </div>
    <div class="custom-row" id="custom-row">
      <div class="field">
        <label for="custom_command">Custom Command</label>
        <input type="text" id="custom_command" placeholder="Type the exact command text" maxlength="160">
      </div>
    </div>
    <div class="hint">Query commands (APN#, STATUS#, SERVER#, PARAM#, VERSION#) are read-only and send immediately. RESET# and any custom command require confirmation first.</div>
    <div class="send-result" id="send-result"></div>
  </div>

  <!-- ── Bulk Send ─────────────────────────────────────────────────────── -->
  <div class="section-title">Bulk Send</div>
  <div class="send-card">
    <div class="send-grid" style="grid-template-columns: 140px 1fr auto;">
      <div class="field">
        <label for="bulk_target_type">Target Type</label>
        <select id="bulk_target_type">
          <option value="iccid">ICCID</option>
          <option value="msisdn">MSISDN</option>
        </select>
      </div>
      <div class="field">
        <label for="bulk_command_select">Command</label>
        <select id="bulk_command_select">
          <option value="APN#">APN#  (query)</option>
          <option value="STATUS#">STATUS#  (query)</option>
          <option value="SERVER#">SERVER#  (query)</option>
          <option value="PARAM#">PARAM#  (query)</option>
          <option value="VERSION#">VERSION#  (query)</option>
          <option value="RESET#">RESET#  (⚠ action — reboots devices)</option>
          <option value="__custom__">Custom command…</option>
        </select>
      </div>
      <button type="button" class="btn-send" id="bulk-send-btn">Send to All</button>
    </div>
    <div class="custom-row" id="bulk-custom-row">
      <div class="field">
        <label for="bulk_custom_command">Custom Command</label>
        <input type="text" id="bulk_custom_command" placeholder="Type the exact command text" maxlength="160">
      </div>
    </div>
    <div class="field" style="margin-top:14px">
      <label for="bulk_targets">ICCIDs / MSISDNs — one per line or comma-separated (max 40)</label>
      <textarea id="bulk_targets" rows="4" placeholder="8834601371107&#10;8834601371115&#10;8834601371116" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-family:'SF Mono',Menlo,Consolas,monospace;font-size:.82rem;resize:vertical"></textarea>
    </div>
    <div class="hint">Sent one at a time (not simultaneously), with a short pause between each, using the same KORE integration as the single-send panel above.</div>
    <div class="send-result" id="bulk-send-result"></div>
    <div id="bulk-progress-wrap" class="card" style="display:none;margin-top:16px">
      <table>
        <thead>
          <tr><th>Target</th><th>Command</th><th>HTTP Status</th><th>KORE MID</th><th>Reply Received</th></tr>
        </thead>
        <tbody id="bulk-progress-tbody"></tbody>
      </table>
    </div>
  </div>

  <!-- ── Outbound Commands log ────────────────────────────────────────── -->
  <div class="section-title">Outbound Commands (last 25)</div>
  <div class="card">
    <table>
      <thead>
        <tr>
          <th>Time</th>
          <th>ICCID/MSISDN</th>
          <th>Command</th>
          <th>KORE MID</th>
          <th>API Status</th>
          <th>Response</th>
        </tr>
      </thead>
      <tbody id="outbound-tbody">
      <?php foreach ($outbound as $cmd): ?>
        <tr>
          <td class="time-cell"><?= date('d M, H:i:s', strtotime($cmd['created_at'])) ?></td>
          <td class="mono"><?= htmlspecialchars($cmd['target_value']) ?> <span style="color:#bbb">(<?= htmlspecialchars($cmd['target_type']) ?>)</span></td>
          <td class="mono"><?= htmlspecialchars($cmd['command']) ?></td>
          <td class="mono" title="<?= htmlspecialchars($cmd['kore_mid'] ?? '') ?>"><?= htmlspecialchars(substr((string)($cmd['kore_mid'] ?? ''), 0, 8)) ?: '—' ?></td>
          <td><span class="status-badge <?= $cmd['api_http_code'] == 200 ? 'status-ok' : 'status-fail' ?>"><?= (int)$cmd['api_http_code'] ?: '—' ?></span></td>
          <td class="msg-cell">
            <?php if ($cmd['matched_message'] !== null): ?>
              <?= htmlspecialchars($cmd['matched_message']) ?><br>
              <span class="status-badge <?= $cmd['matched_confidence'] === 'confirmed' ? 'match-confirmed' : 'match-likely' ?>">
                <?= $cmd['matched_confidence'] === 'confirmed' ? '✓ matched' : '≈ likely match' ?>
              </span>
              <span class="time-cell"><?= date('H:i:s', strtotime($cmd['matched_time'])) ?></span>
            <?php else: ?>
              <span class="match-none">waiting…</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($outbound)): ?>
        <tr><td colspan="6" style="text-align:center;color:#bbb;padding:40px">No commands sent yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="hint" style="margin-bottom:28px">MSISDN targets are matched exactly by SIM identity. ICCID targets are matched by close timing only (within 10 minutes) and marked "likely match" — always double-check the reply content makes sense for that command.</div>

  <!-- ── Replies table ─────────────────────────────────────────────────── -->
  <div class="section-title">All Replies</div>
  <form class="search-bar" method="GET">
    <input type="text" name="q" placeholder="Search MSISDN, message, or mid…" value="<?= htmlspecialchars($search) ?>">
    <button type="submit">Search</button>
    <?php if ($search !== ''): ?><a class="clear" href="/admin/kore-sms.php">Clear</a><?php endif; ?>
  </form>

  <div class="card">
    <table>
      <thead>
        <tr>
          <th>From (SIM)</th>
          <th>To</th>
          <th>Direction</th>
          <th>Response</th>
          <th>KORE mid</th>
          <th>Received</th>
        </tr>
      </thead>
      <tbody id="replies-tbody">
      <?php foreach ($events as $e): ?>
        <tr>
          <td class="mono"><?= htmlspecialchars($e['from_sim'] ?: '—') ?></td>
          <td class="mono"><?= htmlspecialchars($e['to_number'] ?: '—') ?></td>
          <td><?php if ($e['direction']): ?><span class="dir-badge"><?= htmlspecialchars($e['direction']) ?></span><?php else: ?>—<?php endif; ?></td>
          <td class="msg-cell"><?= htmlspecialchars($e['message'] ?: '—') ?></td>
          <td class="mono" title="<?= htmlspecialchars($e['mid'] ?? '') ?>"><?= htmlspecialchars(substr((string)($e['mid'] ?? ''), 0, 8)) ?: '—' ?></td>
          <td class="time-cell"><?= $e['received_at'] ? date('d M Y, H:i:s', strtotime($e['received_at'])) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($events)): ?>
        <tr><td colspan="6" style="text-align:center;color:#bbb;padding:40px">
          <?= $search !== '' ? 'No replies match your search.' : 'No SMS replies received yet.' ?>
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <?php if ($pages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?page=<?= $i ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;
const CSRF_NAME  = <?= json_encode(CSRF_TOKEN_NAME) ?>;
const QUERY_COMMANDS = <?= json_encode($queryCommands) ?>;

const commandSelect = document.getElementById('command_select');
const customRow      = document.getElementById('custom-row');
const customInput    = document.getElementById('custom_command');
const sendBtn         = document.getElementById('send-btn');
const resultBox        = document.getElementById('send-result');

commandSelect.addEventListener('change', () => {
  const isCustom = commandSelect.value === '__custom__';
  customRow.classList.toggle('visible', isCustom);
  sendBtn.classList.toggle('danger', commandSelect.value === 'RESET#');
});

function currentCommand() {
  return commandSelect.value === '__custom__' ? customInput.value.trim() : commandSelect.value;
}

function showResult(ok, html) {
  resultBox.className = 'send-result visible ' + (ok ? 'ok' : 'err');
  resultBox.innerHTML = html;
}

sendBtn.addEventListener('click', async () => {
  const targetType  = document.getElementById('target_type').value;
  const targetValue = document.getElementById('target_value').value.trim();
  const command      = currentCommand();

  if (!targetValue) { showResult(false, 'Enter an ICCID or MSISDN.'); return; }
  if (!command)       { showResult(false, 'Enter or choose a command.'); return; }

  const isQuery = QUERY_COMMANDS.includes(command.toUpperCase());
  let confirmed = isQuery;

  if (!isQuery) {
    const label = command === 'RESET#'
      ? 'RESET# will reboot this tracker. Continue?'
      : `"${command}" is a custom/config command and cannot be verified as read-only. Continue?`;
    confirmed = window.confirm(label);
    if (!confirmed) return;
  }

  sendBtn.disabled = true;
  sendBtn.textContent = 'Sending…';
  resultBox.classList.remove('visible');

  try {
    const res = await fetch('/api/kore-send-command.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        [CSRF_NAME]: CSRF_TOKEN,
        target_type: targetType,
        target_value: targetValue,
        command: command,
        confirmed: confirmed,
      }),
    });
    const data = await res.json();

    if (res.ok && data.success) {
      showResult(true,
        `<strong>${data.message}</strong><br>` +
        `KORE mid: <span class="mid">${data.mid || '—'}</span> · HTTP ${data.http_code}`
      );
      setTimeout(() => location.reload(), 1500);
    } else {
      showResult(false, data.error || 'Command failed.');
    }
  } catch (e) {
    showResult(false, 'Network error — please try again.');
  } finally {
    sendBtn.disabled = false;
    sendBtn.textContent = 'Send Command';
  }
});

// ── Bulk Send ────────────────────────────────────────────────────────────
const bulkTargetType   = document.getElementById('bulk_target_type');
const bulkCommandSelect = document.getElementById('bulk_command_select');
const bulkCustomRow      = document.getElementById('bulk-custom-row');
const bulkCustomInput    = document.getElementById('bulk_custom_command');
const bulkTargetsInput   = document.getElementById('bulk_targets');
const bulkSendBtn         = document.getElementById('bulk-send-btn');
const bulkResultBox        = document.getElementById('bulk-send-result');
const bulkProgressWrap     = document.getElementById('bulk-progress-wrap');
const bulkProgressBody     = document.getElementById('bulk-progress-tbody');

const MAX_BULK_TARGETS = 40;
const BULK_DELAY_MS    = 500;

bulkCommandSelect.addEventListener('change', () => {
  const isCustom = bulkCommandSelect.value === '__custom__';
  bulkCustomRow.classList.toggle('visible', isCustom);
  bulkSendBtn.classList.toggle('danger', bulkCommandSelect.value === 'RESET#');
});

function bulkCurrentCommand() {
  return bulkCommandSelect.value === '__custom__' ? bulkCustomInput.value.trim() : bulkCommandSelect.value;
}

function showBulkResult(ok, html) {
  bulkResultBox.className = 'send-result visible ' + (ok ? 'ok' : 'err');
  bulkResultBox.innerHTML = html;
}

function parseBulkTargets(raw) {
  return raw
    .split(/[\n,]+/)
    .map(s => s.trim())
    .filter(s => s.length > 0);
}

async function sendOne(targetType, targetValue, command, confirmed) {
  const res = await fetch('/api/kore-send-command.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      [CSRF_NAME]: CSRF_TOKEN,
      target_type: targetType,
      target_value: targetValue,
      command: command,
      confirmed: confirmed,
    }),
  });
  const data = await res.json().catch(() => ({}));
  return { ok: res.ok && data.success, data };
}

bulkSendBtn.addEventListener('click', async () => {
  const targetType = bulkTargetType.value;
  const command      = bulkCurrentCommand();
  const rawTargets   = parseBulkTargets(bulkTargetsInput.value);

  if (!command) { showBulkResult(false, 'Enter or choose a command.'); return; }
  if (rawTargets.length === 0) { showBulkResult(false, 'Paste at least one ICCID or MSISDN.'); return; }

  const validPattern = /^[0-9]{6,26}$/;
  const targets = [...new Set(rawTargets)].filter(t => validPattern.test(t));
  const skipped = rawTargets.length - targets.length;

  if (targets.length === 0) { showBulkResult(false, 'None of the entered values look like a valid numeric ICCID/MSISDN.'); return; }
  if (targets.length > MAX_BULK_TARGETS) {
    showBulkResult(false, `Too many targets (${targets.length}). Max ${MAX_BULK_TARGETS} per batch.`);
    return;
  }

  const isQuery = QUERY_COMMANDS.includes(command.toUpperCase());
  let confirmed = isQuery;

  if (!isQuery) {
    const label = command === 'RESET#'
      ? `RESET# will reboot ${targets.length} device${targets.length === 1 ? '' : 's'}. This cannot be undone. Continue?`
      : `"${command}" is a custom/config command and cannot be verified as read-only. It will be sent to ${targets.length} device${targets.length === 1 ? '' : 's'}. Continue?`;
    confirmed = window.confirm(label);
    if (!confirmed) return;
  }

  bulkSendBtn.disabled = true;
  bulkSendBtn.textContent = `Sending 0/${targets.length}…`;
  bulkResultBox.classList.remove('visible');
  bulkProgressBody.innerHTML = '';
  bulkProgressWrap.style.display = 'block';

  let okCount = 0, failCount = 0;

  for (let i = 0; i < targets.length; i++) {
    const target = targets[i];
    const row = document.createElement('tr');
    row.innerHTML = `
      <td class="mono">${target}</td>
      <td class="mono">${command}</td>
      <td>…</td>
      <td>…</td>
      <td class="match-none">sending…</td>
    `;
    bulkProgressBody.appendChild(row);

    try {
      const { ok, data } = await sendOne(targetType, target, command, true);
      const cells = row.querySelectorAll('td');
      cells[2].innerHTML = `<span class="status-badge ${ok ? 'status-ok' : 'status-fail'}">${data.http_code ?? '—'}</span>`;
      cells[3].textContent = data.mid ? data.mid.slice(0, 8) : '—';
      cells[4].innerHTML = ok ? '<span class="match-none">waiting…</span>' : `<span style="color:#b91c1c">${data.error || 'failed'}</span>`;
      ok ? okCount++ : failCount++;
    } catch (e) {
      const cells = row.querySelectorAll('td');
      cells[2].innerHTML = '<span class="status-badge status-fail">—</span>';
      cells[4].innerHTML = '<span style="color:#b91c1c">network error</span>';
      failCount++;
    }

    bulkSendBtn.textContent = `Sending ${i + 1}/${targets.length}…`;
    if (i < targets.length - 1) await new Promise(r => setTimeout(r, BULK_DELAY_MS));
  }

  bulkSendBtn.disabled = false;
  bulkSendBtn.textContent = 'Send to All';
  showBulkResult(failCount === 0,
    `Sent to ${okCount}/${targets.length} device${targets.length === 1 ? '' : 's'}.` +
    (failCount ? ` ${failCount} failed.` : '') +
    (skipped ? ` (${skipped} entr${skipped === 1 ? 'y' : 'ies'} skipped — not a valid ICCID/MSISDN.)` : '') +
    ' Replies will appear in the tables below as trackers respond — refresh to see matches.'
  );
});
</script>
</body>
</html>
