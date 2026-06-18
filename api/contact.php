<?php
/**
 * Vi2ionai Fleet — Contact Form API
 * POST /api/contact.php
 *
 * Replaces Google Forms entirely.
 * Stores enquiry in MySQL + sends email notification.
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate limiting: max 5 submissions per IP per hour
// Use REMOTE_ADDR — Nginx ngx_http_realip_module (set_real_ip_from) replaces this
// with the real client IP extracted from X-Forwarded-For before PHP sees it.
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    $db = getDB();
    $hourAgo = date('Y-m-d H:i:s', time() - 3600);
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM enquiries WHERE ip_address = ? AND created_at > ?'
    );
    $stmt->execute([$ip, $hourAgo]);
    if ((int) $stmt->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many submissions. Please try again later.']);
        exit;
    }
} catch (Exception $e) {
    // DB rate limit failed — allow through but log
    error_log('VI2ION contact rate limit check failed: ' . $e->getMessage());
}

// Parse JSON or form data
$body = [];
$raw  = file_get_contents('php://input');
if ($raw) {
    $body = json_decode($raw, true) ?? [];
}
if (empty($body)) {
    $body = $_POST;
}

// Sanitize inputs
$first  = trim(strip_tags($body['first_name'] ?? $body['first'] ?? ''));
$last   = trim(strip_tags($body['last_name']  ?? $body['last']  ?? ''));
$email  = trim(filter_var($body['email'] ?? '', FILTER_SANITIZE_EMAIL));
$company = trim(strip_tags($body['company'] ?? ''));
$fleet  = trim(strip_tags($body['fleet_size'] ?? $body['fleet'] ?? ''));
$msg    = trim(strip_tags($body['message'] ?? $body['msg'] ?? ''));

// Validation
$errors = [];
if (empty($first))  $errors[] = 'First name is required';
if (empty($email))  $errors[] = 'Email is required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';
// Block personal emails (company email only)
if (preg_match('/@(gmail|hotmail|yahoo|outlook|icloud|me|live|aol)\./i', $email)) {
    $errors[] = 'Please use your company email address';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['error' => implode('. ', $errors)]);
    exit;
}

// Store in DB
try {
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO enquiries (first_name, last_name, email, company, fleet_size, message, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $first, $last, $email, $company, $fleet, $msg,
        $ip,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
    ]);
    $enquiryId = $db->lastInsertId();
} catch (Exception $e) {
    error_log('VI2ION contact insert failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not save enquiry. Please call 0422 55 7927.']);
    exit;
}

// Send email notification
$emailTo   = CONTACT_TO_EMAIL;
$emailFrom = CONTACT_FROM_EMAIL;
$subject   = "New Fleet Enquiry — {$first} {$last} ({$fleet})";
$emailBody = "New enquiry from the Vi2ionai website.\n\n"
    . "Name:       {$first} {$last}\n"
    . "Email:      {$email}\n"
    . "Company:    {$company}\n"
    . "Fleet Size: {$fleet}\n"
    . "Message:\n{$msg}\n\n"
    . "---\nEnquiry ID: #{$enquiryId}\nIP: {$ip}\n"
    . "Time: " . date('d M Y H:i:s T') . "\n";

$headers = "From: Vi2ionai Website <{$emailFrom}>\r\n"
    . "Reply-To: {$email}\r\n"
    . "X-Mailer: Vi2ionai/2.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n";

@mail($emailTo, $subject, $emailBody, $headers);

echo json_encode([
    'success' => true,
    'message' => "Thanks {$first}! We'll be in touch within 1 business day.",
    'id'      => (int) $enquiryId,
]);
