<?php
/**
 * Vi2ionai Fleet — Chatbot API
 * Keyword-matched responses + lead collection flow with session state.
 */

session_start();

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

$userText = '';
if (!empty($body['messages']) && is_array($body['messages'])) {
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
    echo json_encode(['content' => [['type' => 'text', 'text' => "G'day! I'm Chris, Vi2ionai's fleet specialist. I can help with pricing, GPS tracking, dash cams, compliance, installation or anything else. What would you like to know?"]]]);
    exit;
}

// ── Lead collection flow ──────────────────────────────────────────────────
$step = $_SESSION['chat_lead_step'] ?? null;

if ($step) {
    $reply = handleLeadFlow($userText, $step);
    echo json_encode(['content' => [['type' => 'text', 'text' => $reply]]]);
    exit;
}

echo json_encode(['content' => [['type' => 'text', 'text' => getReply($userText)]]]);

// ── Lead collection state machine ─────────────────────────────────────────
function handleLeadFlow(string $input, string $step): string
{
    $q = strtolower(trim($input));

    // User said no / cancel at any point
    if (has($q, ['no', 'nope', 'nah', 'cancel', 'stop', 'nevermind', 'never mind', 'skip'])) {
        unset($_SESSION['chat_lead_step'], $_SESSION['chat_lead']);
        return "No worries! You can always reach us at 📞 0422 55 7927 or 📧 info@vi2ionai.com.au. Is there anything else I can help with?";
    }

    switch ($step) {
        case 'name':
            if (strlen($input) < 2) {
                return "Could you share your name so I know who I'm talking to?";
            }
            $_SESSION['chat_lead']['name'] = $input;
            $_SESSION['chat_lead_step'] = 'email';
            return "Nice to meet you, {$input}! What's the best email address to reach you?";

        case 'email':
            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                return "That doesn't look quite right — could you double-check your email address?";
            }
            $_SESSION['chat_lead']['email'] = $input;
            $_SESSION['chat_lead_step'] = 'fleet_size';
            return "Got it! How many vehicles are in your fleet? (Even a rough number is fine.)";

        case 'fleet_size':
            $_SESSION['chat_lead']['fleet_size'] = $input;
            $_SESSION['chat_lead_step'] = 'message';
            return "Almost done! Anything specific you'd like us to cover in the demo, or any questions you'd like answered? (Or just say \"none\" to skip.)";

        case 'message':
            $lead = $_SESSION['chat_lead'] ?? [];
            $name  = $lead['name'] ?? 'Unknown';
            $email = $lead['email'] ?? '';
            $fleet = $lead['fleet_size'] ?? '';
            $notes = ($q === 'none' || $q === 'no') ? '' : $input;

            $saved = saveLead($name, $email, $fleet, $notes);

            unset($_SESSION['chat_lead_step'], $_SESSION['chat_lead']);

            if ($saved) {
                $first = explode(' ', $name)[0];
                return "You're all set, {$first}! 🎉 One of our Australian fleet specialists will be in touch at {$email} within 1 business day to schedule your free demo. If you need anything urgent, call us on 0422 55 7927. Looking forward to showing you what Vi2ionai can do!";
            } else {
                return "Thanks {$name}! Something went wrong saving your details on our end — please email us directly at info@vi2ionai.com.au or call 0422 55 7927 and we'll get you sorted right away.";
            }
    }

    unset($_SESSION['chat_lead_step']);
    return getReply($input);
}

function saveLead(string $name, string $email, string $fleetSize, string $message): bool
{
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/db.php';

    try {
        $db = getDB();
        $parts = explode(' ', trim($name), 2);
        $first = $parts[0];
        $last  = $parts[1] ?? '-';

        $stmt = $db->prepare(
            'INSERT INTO enquiries (first_name, last_name, email, company, fleet_size, message, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $first,
            $last,
            $email,
            'Via Chat',
            $fleetSize,
            $message ?: 'Demo request via chatbot',
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ── Helper ─────────────────────────────────────────────────────────────────
function has(string $lower, array $words): bool {
    foreach ($words as $w) {
        if (str_contains($lower, $w)) return true;
    }
    return false;
}

function startLeadFlow(): string {
    $_SESSION['chat_lead_step'] = 'name';
    $_SESSION['chat_lead'] = [];
    return "Great, let's get that booked in! What's your name?";
}

// ── Main reply engine ──────────────────────────────────────────────────────
function getReply(string $msg): string
{
    $q = strtolower(trim($msg));

    // ── Greetings ──────────────────────────────────────────────────────────
    if (has($q, ['hello', 'hi ', 'hey', "g'day", 'gday', 'howdy', 'good morning', 'good afternoon', 'good evening'])) {
        return "G'day! I'm Chris, Vi2ionai's fleet specialist. I can help with pricing, GPS tracking, AI dash cams, NHVR compliance, installation and more. What can I help you with today?";
    }

    // ── Yes / agree (triggers lead flow if context suggests it) ───────────
    if (in_array($q, ['yes', 'yeah', 'yep', 'sure', 'ok', 'okay', 'absolutely', 'please', 'yes please', 'go ahead', 'sounds good', 'let\'s do it', 'lets do it'])) {
        return startLeadFlow();
    }

    // ── Demo / trial ───────────────────────────────────────────────────────
    if (has($q, ['demo', 'trial', 'free trial', 'test', 'try', 'see it', 'walkthrough', 'show me', 'book', 'schedule', 'appointment'])) {
        return "We offer a free live demo with one of our Australian fleet specialists — no hard sell, just a walkthrough tailored to your fleet. Want me to take your details so we can get in touch? (Just say yes!)";
    }

    // ── Quote ──────────────────────────────────────────────────────────────
    if (has($q, ['quote', 'custom quote', 'get a quote', 'proposal', 'pricing for my fleet'])) {
        return "I can get a custom quote organised for you! Want me to take your details now so one of our specialists can prepare something for your fleet size? (Just say yes!)";
    }

    // ── Callback / contact ─────────────────────────────────────────────────
    if (has($q, ['call me', 'call back', 'callback', 'ring me', 'get in touch', 'contact me', 'reach me', 'speak to', 'talk to', 'human', 'person', 'agent'])) {
        return "Absolutely — want me to take your details and have a fleet specialist call you back? (Just say yes!) Or you can reach us directly on 📞 0422 55 7927 Mon–Fri 8am–6pm AEST.";
    }

    // ── Pricing / cost ─────────────────────────────────────────────────────
    if (has($q, ['price', 'cost', 'how much', 'pricing', 'subscription', 'monthly', 'per month', 'fee', 'charge', 'afford', 'budget', 'cheap', 'expensive', 'value', 'worth'])) {
        return "Our plans start from \$25/month per vehicle — that includes the SIM card, data plan, and full platform access. Hardware is a one-off purchase from \$149. No lock-in contracts, ever — all plans are month-to-month. Volume pricing is available for larger fleets.\n\nWant a custom quote? Just say yes and I'll take your details!";
    }

    // ── Hardware / device ──────────────────────────────────────────────────
    if (has($q, ['hardware', 'device', 'tracker', 'unit', 'obd', 'hardwired', 'plug in', 'purchase', 'buy', 'order'])) {
        return "We have two main hardware options:\n\n• **OBD Plug-and-Play** — plugs into your vehicle's OBD-II port in under 5 minutes, no tools needed. From \$149.\n• **Hardwired Unit** — permanently wired in, takes under 30 minutes per vehicle.\n\nBoth include the SIM card and data plan. Want to book a demo to see them in action?";
    }

    // ── Installation ───────────────────────────────────────────────────────
    if (has($q, ['install', 'setup', 'set up', 'fit', 'fitting', 'how long', 'difficult', 'easy', 'diy'])) {
        return "Installation is straightforward:\n\n• **OBD plug-and-play**: under 5 minutes — just plug into the OBD-II port, no tools required.\n• **Hardwired unit**: under 30 minutes per vehicle.\n\nOur support team walks you through every step. Mon–Fri 8am–6pm AEST on 0422 55 7927.";
    }

    // ── GPS / real-time tracking ───────────────────────────────────────────
    if (has($q, ['gps', 'real-time', 'realtime', 'live', 'location', 'map', 'where is', 'position', 'update', 'how often', 'trip history', 'replay', 'route'])) {
        return "Vi2ionai GPS updates every 30 seconds while vehicles are moving:\n\n• Live vehicle locations on a real-time map\n• Full trip history with route replay\n• Geofence alerts when vehicles enter or leave set areas\n• Speed monitoring and idle-time reports\n• Works on any device — desktop, tablet or mobile\n\nWant to see it live? Say yes and I'll book you a demo!";
    }

    // ── Geofencing ─────────────────────────────────────────────────────────
    if (has($q, ['geofence', 'geo-fence', 'zone', 'boundary', 'alert', 'notification', 'enter', 'leave', 'area'])) {
        return "Yes, geofencing is included in all plans. You can create custom zones on the map — your depot, job sites, restricted areas — and get instant alerts when a vehicle enters or exits. Great for after-hours monitoring too.";
    }

    // ── Dash cams / AI camera ──────────────────────────────────────────────
    if (has($q, ['dash cam', 'dashcam', 'camera', 'ai cam', 'video', 'footage', 'recording', 'incident', 'collision', 'lane', 'fatigue detection', 'distraction'])) {
        return "Our AI dash cams are a game-changer for fleet safety:\n\n• **Forward collision warnings** — real-time alerts to prevent accidents\n• **Lane departure alerts** — keeps drivers on track\n• **Fatigue & distraction detection** — AI monitors driver alertness\n• **Automatic incident video upload** — footage saved to the cloud instantly\n• **In-dashboard review** — no separate app needed\n\nOne customer reduced insurance premiums by 22% after 6 months. Want a demo?";
    }

    // ── Driver behaviour ───────────────────────────────────────────────────
    if (has($q, ['driver', 'behaviour', 'behavior', 'speeding', 'harsh braking', 'acceleration', 'safety', 'score', 'performance'])) {
        return "Vi2ionai tracks driver behaviour in real time — speeding, harsh braking, rapid acceleration and idle time. Drivers get a safety score and you get detailed reports to coach your team. Combined with our AI dash cams, it's a complete picture of what's happening on the road.";
    }

    // ── Asset tracking ─────────────────────────────────────────────────────
    if (has($q, ['asset', 'trailer', 'equipment', 'non-powered', 'battery powered', 'ip67', 'weatherproof', 'tamper', 'machinery'])) {
        return "Our Asset Tracker is designed for non-powered assets like trailers, excavators and generators:\n\n• Up to **5 years battery life** — no wiring needed\n• **IP67 weatherproof** — built for Australian conditions\n• Instant alerts for movement or tamper events\n• Same dashboard as your vehicle trackers\n\nHow many assets are you looking to track?";
    }

    // ── Temperature / cold chain ───────────────────────────────────────────
    if (has($q, ['temperature', 'cold chain', 'refrigerat', 'fridge', 'cold storage', 'reefer', 'frozen', 'perishable', 'food safety'])) {
        return "Our temperature monitoring is built for refrigerated and cold chain fleets:\n\n• Real-time temperature readings from your reefer units\n• Instant alerts when temperature goes outside your set range\n• Full audit trail for food safety compliance (HACCP)\n• Integrated into the main fleet dashboard\n\nWant more details or a demo?";
    }

    // ── NHVR / EWD / compliance ────────────────────────────────────────────
    if (has($q, ['ewd', 'nhvr', 'compliance', 'chain of responsibility', 'cor', 'heavy vehicle', 'fatigue management', 'work diary', 'logbook', 'mass management', 'audit'])) {
        return "Yes — Vi2ionai is fully NHVR-compliant. Our Electronic Work Diary (EWD):\n\n• Automates fatigue record-keeping for heavy vehicle operators\n• Handles Chain of Responsibility (CoR) reporting\n• Manages mass management compliance\n• Keeps you audit-ready — no paper log books\n\nNeed more detail on how it works for your operation?";
    }

    // ── Contracts / cancel ─────────────────────────────────────────────────
    if (has($q, ['contract', 'lock-in', 'lock in', 'cancel', 'cancellation', 'commitment', 'minimum term'])) {
        return "No lock-in contracts — ever. All Vi2ionai plans are month-to-month. You can scale up, scale down, or cancel with no fees. Most customers stay because they love the platform, not because they're locked in.";
    }

    // ── Fleet size ─────────────────────────────────────────────────────────
    if (has($q, ['how many', 'fleet size', 'small fleet', 'large fleet', 'enterprise', 'one vehicle', 'single vehicle', 'minimum', 'scale'])) {
        return "We support fleets of all sizes — from a single vehicle to enterprise fleets of 200+:\n\n• **1–5 vehicles**: Starter plan from \$25/month per vehicle\n• **6–50 vehicles**: Growth plan with volume discounts\n• **50+ vehicles**: Enterprise plan with custom pricing\n\nWhat size is your fleet?";
    }

    // ── Industries ─────────────────────────────────────────────────────────
    if (has($q, ['construction', 'mining', 'transport', 'logistics', 'trades', 'tradies', 'plumber', 'electrician', 'builder', 'industry', 'sector'])) {
        return "Vi2ionai serves fleets across many Australian industries:\n\n• **Construction** — track machinery, vehicles and assets on site\n• **Mining** — rugged tracking with compliance reporting\n• **Transport & Logistics** — real-time ETAs, route optimisation, cold chain\n• **Refrigerated Transport** — temperature monitoring + GPS\n• **Trades** — manage your field team and job dispatch\n\nWhich industry are you in?";
    }

    // ── ROI / savings ──────────────────────────────────────────────────────
    if (has($q, ['roi', 'return on investment', 'save', 'saving', 'fuel', 'reduce cost', 'payback', 'efficient', 'benefit', 'worth it'])) {
        return "The ROI is typically very fast:\n\n• Customers see **10–20% fuel savings** within 3 months\n• One customer cut fuel costs by **18% in the first quarter**\n• Insurance savings from AI dash cam evidence add up fast\n• A 10-vehicle fleet at \$25/vehicle/month spends \$250/month — most recover that in fuel savings alone\n\nWant to see the numbers for your fleet? I can book you a free consultation.";
    }

    // ── Support / contact ──────────────────────────────────────────────────
    if (has($q, ['support', 'contact', 'speak to', 'talk to', 'call', 'phone', 'email', 'help me', 'assistance'])) {
        return "Our Australian support team is here for you:\n\n• 📞 **Call**: 0422 55 7927\n• 📧 **Email**: info@vi2ionai.com.au\n• 🕐 **Hours**: Monday–Friday, 8am–6pm AEST\n\nOr want me to take your details and have someone call you back? Just say yes!";
    }

    // ── Shipping ───────────────────────────────────────────────────────────
    if (has($q, ['ship', 'deliver', 'delivery', 'postage', 'arrival', 'dispatch', 'freight'])) {
        return "We ship Australia-wide:\n\n• **Free shipping** on orders over \$500\n• Standard delivery: 2–5 business days\n• Express shipping available at checkout\n\nAny questions about a specific order?";
    }

    // ── Security / theft ───────────────────────────────────────────────────
    if (has($q, ['theft', 'stolen', 'security', 'recover', 'tamper', 'after hours', 'weekend', 'unauthorised'])) {
        return "Vi2ionai helps protect your fleet:\n\n• Instant alerts if a vehicle moves outside business hours\n• Geofence alerts if a vehicle leaves an approved area\n• Real-time location to assist police recovery\n• Asset Tracker tamper alerts for trailers and equipment\n\nSeveral customers have recovered stolen vehicles using Vi2ionai's live tracking.";
    }

    // ── Mobile app ─────────────────────────────────────────────────────────
    if (has($q, ['app', 'mobile', 'phone', 'iphone', 'android', 'ios', 'smartphone', 'tablet'])) {
        return "The Vi2ionai platform is fully mobile-responsive and works on any device through your browser — no app download needed. Track your fleet, check alerts and review reports from your phone or tablet, anywhere, anytime.";
    }

    // ── Maintenance ────────────────────────────────────────────────────────
    if (has($q, ['maintenance', 'service reminder', 'rego', 'registration', 'odometer', 'km', 'kilometre'])) {
        return "Vi2ionai tracks odometer readings automatically from your GPS data. You can set maintenance reminders by distance or time — oil changes, tyre rotations, rego renewals — so nothing falls through the cracks and your fleet stays compliant and roadworthy.";
    }

    // ── About ──────────────────────────────────────────────────────────────
    if (has($q, ['about', 'who are', 'company', 'based', 'australian', 'founded', 'vi2ion'])) {
        return "Vi2ionai is an Australian fleet GPS tracking platform based in Artarmon, NSW. We built it for Australian fleet managers who want enterprise-grade technology without the enterprise-grade complexity — or the lock-in contracts. We currently track 5,000+ vehicles across 500+ Australian fleets with 99.9% platform uptime.";
    }

    // ── Thanks ─────────────────────────────────────────────────────────────
    if (has($q, ['thank', 'thanks', 'great', 'perfect', 'awesome', 'excellent', 'brilliant', 'sounds good', 'that\'s all'])) {
        return "Happy to help! If you'd like to take the next step, just say yes and I'll book you in for a free demo. Or call us on 0422 55 7927. Anything else I can help with?";
    }

    // ── Default ────────────────────────────────────────────────────────────
    $snippet = strlen($msg) > 3 ? '"' . htmlspecialchars(mb_substr($msg, 0, 60)) . '"' : 'that';
    return "Thanks for your question about {$snippet}! Our fleet specialists can give you the most accurate answer:\n\n• 📞 **Call**: 0422 55 7927\n• 📧 **Email**: info@vi2ionai.com.au\n• 🕐 Mon–Fri 8am–6pm AEST\n\nOr say **yes** and I'll take your details for a callback!";
}
