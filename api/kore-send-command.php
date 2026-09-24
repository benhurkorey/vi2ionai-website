<?php
/**
 * Vi2ionai Admin — Send a KORE SMS command
 * POST /api/kore-send-command.php  (admin-session + CSRF protected)
 *
 * Called only from admin/kore-sms.php's "Send SMS Command" panel.
 * Server obtains/uses the KORE OAuth token and sends via the KORE SMS API.
 * Client Secret / API key / OAuth token never reach the browser.
 */

require_once __DIR__ . '/../admin/auth.php';
requireAdminJson();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/kore_sms.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?: $_POST;

if (!verifyCsrf((string) ($body[CSRF_TOKEN_NAME] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'Security check failed. Please refresh the page and try again.']);
    exit;
}

// ── Rate limiting: max 60 sends per admin session per 5 minutes ────────────
// (raised from 10 to comfortably cover bulk-send batches of ~40 devices;
// still admin-session + CSRF gated, so not publicly abusable)
$db = getDB();
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
try {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM kore_outbound_commands WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
    );
    $stmt->execute([$ip]);
    if ((int) $stmt->fetchColumn() >= 60) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many commands sent. Please wait a few minutes and try again.']);
        exit;
    }
} catch (Exception $e) {
    error_log('VI2ION kore-send-command rate check failed: ' . $e->getMessage());
}

// ── Validate input ───────────────────────────────────────────────────────
$targetType  = strtolower(trim((string) ($body['target_type'] ?? '')));
$targetValue = trim((string) ($body['target_value'] ?? ''));
$command     = trim((string) ($body['command'] ?? ''));

if (!in_array($targetType, ['iccid', 'msisdn'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Target type must be ICCID or MSISDN.']);
    exit;
}
if ($targetValue === '' || !preg_match('/^[0-9]{6,26}$/', $targetValue)) {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a valid numeric ICCID or MSISDN.']);
    exit;
}
if ($command === '' || mb_strlen($command) > 160) {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a command (max 160 characters).']);
    exit;
}

// Known read-only/query presets — anything else (including RESET#) is treated
// as an action command and must be explicitly confirmed by the client before
// this endpoint is even called; we re-check here defensively.
$queryCommands = ['APN#', 'STATUS#', 'SERVER#', 'PARAM#', 'VERSION#'];
$isQuery = in_array(strtoupper($command), $queryCommands, true);
$confirmed = !empty($body['confirmed']);
if (!$isQuery && !$confirmed) {
    http_response_code(422);
    echo json_encode(['error' => 'This command changes device configuration and requires confirmation.']);
    exit;
}

// ── Send via KORE ────────────────────────────────────────────────────────
$result = koreSendSms($targetType, $targetValue, $command);

// ── Log outbound attempt ────────────────────────────────────────────────
try {
    $stmt = $db->prepare(
        'INSERT INTO kore_outbound_commands
            (target_type, target_value, command, kore_mid, api_http_code, api_status, api_message, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $targetType,
        $targetValue,
        $command,
        $result['mid'],
        $result['http_code'],
        $result['status'],
        $result['message'],
        $ip,
    ]);
    $logId = (int) $db->lastInsertId();
} catch (Exception $e) {
    error_log('VI2ION kore-send-command log insert failed: ' . $e->getMessage());
    $logId = null;
}

if (!$result['ok']) {
    http_response_code(502);
    echo json_encode([
        'error'     => 'KORE rejected the command: ' . ($result['message'] ?? 'Unknown error'),
        'http_code' => $result['http_code'],
        'log_id'    => $logId,
    ]);
    exit;
}

echo json_encode([
    'success'   => true,
    'mid'       => $result['mid'],
    'http_code' => $result['http_code'],
    'message'   => 'Command accepted by KORE — waiting for tracker response.',
    'log_id'    => $logId,
]);
