<?php
/**
 * Vi2ionai Fleet — KORE SMS-MO Webhook
 * POST /api/kore-sms-webhook.php
 *
 * Receives connectivity.smsapi.mobileoriginated callbacks from the KORE
 * Developer Portal (Manage Rules → SMS → Alerting → SMS-MO) whenever an
 * OmniSIM tracker replies to KORE short code 567300905.
 *
 * Auth: KORE's Webhook action sends the configured "access-token" back in
 * the X-Kore-Notification header (see KORE Developer Portal → Getting
 * Started → Rules Engine and Streaming → Actions). A ?token= query param
 * is also accepted as a fallback for manual testing.
 *
 * Does not touch GPS/tracker server logic — this only logs the inbound
 * SMS payload for later use.
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── Auth: shared-secret via X-Kore-Notification header, or ?token= fallback ─
$expected = defined('KORE_WEBHOOK_SECRET') ? KORE_WEBHOOK_SECRET : '';
$provided = $_SERVER['HTTP_X_KORE_NOTIFICATION'] ?? $_GET['token'] ?? '';
if ($expected === '' || !hash_equals($expected, (string) $provided)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Parse payload ────────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

// KORE's actual envelope (confirmed via live test, rule 59528):
// { "event-name": "...", "events": [ { "id": "...", "data": { "mid", "direction", "to", "from", "message", "exact-date-time" } } ] }
// Fall back to a flat/"data"-wrapped body for forward-compatibility.
$eventType = trim((string) ($body['event-name'] ?? $body['event'] ?? $body['eventType'] ?? ''));

$events = $body['events'] ?? [$body['data'] ?? $body['payload'] ?? $body];
if (!is_array($events) || $events === []) {
    $events = [[]];
}

// ── Store one row per event ─────────────────────────────────────────────
try {
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO kore_sms_events
            (mid, event_type, direction, to_number, from_sim, message, raw_payload, received_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );

    foreach ($events as $eventWrapper) {
        $event = is_array($eventWrapper) ? ($eventWrapper['data'] ?? $eventWrapper) : [];

        $mid       = trim((string) ($event['mid'] ?? ''));
        $direction = trim((string) ($event['direction'] ?? ''));
        $toNumber  = trim((string) ($event['to'] ?? ''));
        $fromSim   = trim((string) ($event['from'] ?? ''));
        $message   = (string) ($event['message'] ?? '');

        $stmt->execute([
            $mid !== '' ? $mid : null,
            $eventType !== '' ? $eventType : null,
            $direction !== '' ? $direction : null,
            $toNumber !== '' ? $toNumber : null,
            $fromSim !== '' ? $fromSim : null,
            $message,
            $raw,
        ]);
    }
} catch (Exception $e) {
    error_log('VI2ION kore_sms_webhook insert failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not store event']);
    exit;
}

// KORE expects a fast 200 OK to acknowledge receipt.
http_response_code(200);
echo json_encode(['success' => true]);
