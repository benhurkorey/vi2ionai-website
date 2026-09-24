<?php
/**
 * Vi2ionai Fleet — KORE SMS API helper (server-side only)
 * Used by admin/kore-sms.php's Send Command panel via api/kore-send-command.php.
 * Never expose the OAuth token, Client Secret, or API key to the browser.
 */

require_once __DIR__ . '/config.php';

/**
 * Get a cached OAuth access token, refreshing via client_credentials if expired.
 * Cache lives outside the web root's servable paths (storage/ is nginx-denied).
 */
function koreGetAccessToken(): ?string {
    $cacheFile = __DIR__ . '/../storage/.kore_sms_token.json';

    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['access_token']) && !empty($cached['expires_at']) && $cached['expires_at'] > time() + 30) {
            return $cached['access_token'];
        }
    }

    if (KORE_SMS_CLIENT_ID === '' || KORE_SMS_CLIENT_SECRET === '') {
        return null;
    }

    $ch = curl_init(KORE_SMS_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => KORE_SMS_CLIENT_ID,
            'client_secret' => KORE_SMS_CLIENT_SECRET,
        ]),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) {
        error_log('VI2ION koreGetAccessToken failed: HTTP ' . $code);
        return null;
    }

    $data = json_decode($resp, true);
    if (empty($data['access_token'])) {
        return null;
    }

    $expiresIn = (int) ($data['expires_in'] ?? 3300);
    @file_put_contents($cacheFile, json_encode([
        'access_token' => $data['access_token'],
        'expires_at'   => time() + $expiresIn,
    ]));
    @chmod($cacheFile, 0600);

    return $data['access_token'];
}

/**
 * Send an SMS command to a SIM via the KORE SMS API.
 *
 * @param string $targetType 'iccid' or 'msisdn'
 * @param string $targetValue the ICCID or MSISDN
 * @param string $message the command text, e.g. "STATUS#"
 * @return array{ok:bool, http_code:int, mid:?string, status:?string, message:?string, raw:string}
 */
function koreSendSms(string $targetType, string $targetValue, string $message): array {
    $token = koreGetAccessToken();
    if ($token === null) {
        return ['ok' => false, 'http_code' => 0, 'mid' => null, 'status' => null, 'message' => 'Could not obtain KORE access token', 'raw' => ''];
    }

    $body = ['message' => $message];
    if ($targetType === 'iccid') {
        $body['iccid'] = $targetValue;
    } else {
        $body['msisdn'] = $targetValue;
    }

    $url = KORE_SMS_API_BASE . '/messages/send?' . http_build_query(['account-id' => KORE_SMS_ACCOUNT_ID]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'x-api-key: ' . KORE_SMS_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($body),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'http_code' => 0, 'mid' => null, 'status' => null, 'message' => $err ?: 'Network error', 'raw' => ''];
    }

    $data = json_decode($resp, true);
    return [
        'ok'        => $code === 200,
        'http_code' => $code,
        'mid'       => is_array($data) ? ($data['mid'] ?? null) : null,
        'status'    => is_array($data) ? ($data['status'] ?? null) : null,
        'message'   => is_array($data) ? ($data['message'] ?? null) : null,
        'raw'       => $resp,
    ];
}
