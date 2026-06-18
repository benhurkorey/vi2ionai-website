<?php
/**
 * Vi2ionai Fleet — Chatbot API
 * Accepts POST with { messages: [{role, content}] } (Anthropic-compatible format)
 * Returns { content: [{type: "text", text: "..."}] } so the homepage JS works as-is.
 *
 * Keyword-matched stub — upgrade to a real LLM call when ready by replacing
 * the getReply() function with an API call.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = $raw ? (json_decode($raw, true) ?? []) : [];

// Accept both { messages: [{role, content}] } and { message: "string" }
$userText = '';
if (!empty($body['messages']) && is_array($body['messages'])) {
    // Walk backwards to find the last user message
    foreach (array_reverse($body['messages']) as $m) {
        if (($m['role'] ?? '') === 'user' && !empty($m['content'])) {
            $userText = trim(strip_tags(is_string($m['content']) ? $m['content'] : ''));
            break;
        }
    }
} elseif (!empty($body['message'])) {
    $userText = trim(strip_tags($body['message']));
}

if ($userText === '') {
    echo json_encode(['content' => [['type' => 'text', 'text' => "Hi! I'm Chris, Vi2ionai's fleet specialist. How can I help you today?"]]]);
    exit;
}

$reply = getReply($userText);

echo json_encode([
    'content' => [
        ['type' => 'text', 'text' => $reply]
    ]
]);

// ── Smart reply engine ────────────────────────────────────────────────────
function getReply(string $msg): string
{
    $lower = strtolower($msg);

    return match (true) {
        // Greetings
        str_contains($lower, 'hello') || str_contains($lower, 'hi') || str_contains($lower, 'hey') || $lower === 'g\'day'
            => "G'day! I'm Chris, your Vi2ionai fleet specialist. Ask me anything about GPS tracking, pricing, dash cams, or getting started. What can I help with?",

        // Pricing
        str_contains($lower, 'price') || str_contains($lower, 'cost') || str_contains($lower, 'how much') || str_contains($lower, 'pricing') || str_contains($lower, 'subscription')
            => "Our plans start from \$25/month per vehicle with no lock-in contracts. That includes the SIM, data plan, and platform access. Hardware is a one-off purchase from \$149. Want me to get someone to prepare a custom quote for your fleet size?",

        // Installation
        str_contains($lower, 'install') || str_contains($lower, 'setup') || str_contains($lower, 'fit') || str_contains($lower, 'plug')
            => "Installation takes under 30 minutes per vehicle. Our OBD plug-and-play unit just plugs into your vehicle's OBD-II port — no tools needed. Hardwired installs are also available for permanent fitting. We'll walk you through it step by step.",

        // Trial / demo
        str_contains($lower, 'trial') || str_contains($lower, 'demo') || str_contains($lower, 'free') || str_contains($lower, 'test')
            => "Absolutely! We offer a free demo with a live walkthrough of the platform. Fill out our quick form at vi2ionai.com.au/landing-page.php and a fleet specialist will contact you within 1 business day to set it up.",

        // Dash cam / camera / AI
        str_contains($lower, 'dash cam') || str_contains($lower, 'camera') || str_contains($lower, 'ai cam') || str_contains($lower, 'dashcam') || str_contains($lower, 'video')
            => "Our AI dash cams include forward collision warnings, lane departure alerts, fatigue/distraction detection, and automatic incident video upload to the cloud. They integrate directly with the fleet dashboard so you can review footage without leaving the platform.",

        // Asset tracking
        str_contains($lower, 'asset') || str_contains($lower, 'trailer') || str_contains($lower, 'equipment') || str_contains($lower, 'non-powered') || str_contains($lower, 'battery')
            => "Our Asset Tracker is battery-powered with up to 5 years of battery life — perfect for trailers, heavy equipment, and non-powered assets. It's IP67 weatherproof and sends alerts for movement or tamper events.",

        // Contracts
        str_contains($lower, 'contract') || str_contains($lower, 'lock') || str_contains($lower, 'lock-in') || str_contains($lower, 'cancel') || str_contains($lower, 'commitment')
            => "No lock-in contracts at all. All our plans are month-to-month. You can scale up or down as your fleet changes, with no cancellation fees. Most customers stay with us because they love the platform, not because they're locked in!",

        // Fleet size
        str_contains($lower, 'small') || str_contains($lower, 'one vehicle') || str_contains($lower, '1 vehicle') || str_contains($lower, 'single')
            => "We support fleets of all sizes — from a single vehicle all the way to enterprise fleets of 200+. Our Starter plan is designed specifically for small fleets and starts from \$25/month per vehicle.",

        // EWD / compliance / NHVR
        str_contains($lower, 'ewd') || str_contains($lower, 'nhvr') || str_contains($lower, 'compliance') || str_contains($lower, 'fatigue') || str_contains($lower, 'heavy vehicle') || str_contains($lower, 'chain of responsibility')
            => "Yes — our Electronic Work Diary (EWD) is NHVR-compliant and automates fatigue record-keeping for heavy vehicle operators. It handles chain of responsibility reporting and mass management, keeping you compliant without the paperwork.",

        // GPS / tracking / real-time
        str_contains($lower, 'gps') || str_contains($lower, 'track') || str_contains($lower, 'real-time') || str_contains($lower, 'live') || str_contains($lower, 'location')
            => "Our GPS trackers update every 30 seconds with live positions on the dashboard map. You get full trip history and replay, geofence alerts when vehicles enter or leave defined areas, and speed/idle monitoring — all from any device.",

        // Temperature / cold chain / fridge
        str_contains($lower, 'temperature') || str_contains($lower, 'cold chain') || str_contains($lower, 'refrigerat') || str_contains($lower, 'fridge') || str_contains($lower, 'cold')
            => "Our temperature monitoring solution provides real-time cold chain tracking for refrigerated fleets. You get instant alerts when temperature goes out of your set range, plus a full audit trail for compliance.",

        // Support
        str_contains($lower, 'support') || str_contains($lower, 'help') || str_contains($lower, 'contact') || str_contains($lower, 'speak') || str_contains($lower, 'call') || str_contains($lower, 'talk')
            => "Our Australian support team is available Mon–Fri 8 am – 6 pm AEST. Call us on 0422 55 7927, email info@vi2ionai.com.au, or I can take your details and have someone call you back. What would you prefer?",

        // Shipping / delivery
        str_contains($lower, 'ship') || str_contains($lower, 'deliver') || str_contains($lower, 'postage') || str_contains($lower, 'arrival')
            => "We offer free shipping on hardware orders over \$500. Standard orders typically arrive within 2–5 business days anywhere in Australia. Express shipping is available at checkout.",

        // Fuel / savings / ROI
        str_contains($lower, 'fuel') || str_contains($lower, 'save') || str_contains($lower, 'saving') || str_contains($lower, 'roi') || str_contains($lower, 'return on investment') || str_contains($lower, 'efficient')
            => "Most Vi2ionai customers see a 10–20% reduction in fuel costs within the first 3 months through route optimisation and idle-time reduction. A 10-vehicle fleet saving 15% on fuel typically covers the subscription cost several times over.",

        // Thanks / good / great
        str_contains($lower, 'thank') || str_contains($lower, 'great') || str_contains($lower, 'perfect') || str_contains($lower, 'awesome') || str_contains($lower, 'good')
            => "Happy to help! If you'd like to take the next step, fill out our quote form or call 0422 55 7927 and we'll get you set up quickly. Anything else I can help with?",

        // Default
        default
            => "Great question! For the most accurate answer about " . (strlen($msg) > 2 ? '"' . htmlspecialchars(mb_substr($msg, 0, 55)) . '"' : "that") . ", our team can help. Call us on 0422 55 7927, email info@vi2ionai.com.au, or <a href='/landing-page.php'>fill in our quick form</a> and we'll get back to you within 1 business day.",
    };
}
