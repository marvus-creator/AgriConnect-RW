<?php
/**
 * includes/sms.php — SMS notifications for AgriConnect (Africa's Talking).
 *
 * Lets the platform reach farmers/buyers/drivers by SMS — critical in rural
 * Rwanda where many users don't have smartphones. Works in SIMULATION mode
 * (logged, not actually sent) until credentials are configured, then sends
 * real SMS via Africa's Talking.
 *
 * Config: env vars, or includes/sms_config.php (copy from sms_config.sample.php).
 * No official PHP SDK needed — simple form POST via cURL.
 */

function sms_config(): ?array {
    $u = getenv('AT_USERNAME');
    if ($u) {
        return [
            'username' => $u,
            'api_key'  => getenv('AT_API_KEY') ?: '',
            'sender'   => getenv('AT_SENDER') ?: '',
            'base_url' => getenv('AT_BASE_URL') ?: 'https://api.africastalking.com/version1/messaging',
            'country_code' => getenv('SMS_COUNTRY_CODE') ?: '250',
        ];
    }
    $cfg = __DIR__ . '/sms_config.php';
    if (is_file($cfg)) {
        $data = include $cfg;
        if (is_array($data) && !empty($data['username']) && !empty($data['api_key'])) return $data;
    }
    return null;
}

function sms_is_live(): bool {
    $c = sms_config();
    return $c !== null;
}

function sms_msisdn(string $phone, string $cc = '250'): string {
    $p = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($p, '0')) $p = $cc . substr($p, 1);
    elseif (!str_starts_with($p, $cc)) $p = $cc . $p;
    return '+' . $p;
}

/**
 * Send an SMS (or simulate it). Always logged to sms_log.
 * @return array ['status'=>..., 'simulated'=>bool]
 */
function sms_send(mysqli $conn, string $to, string $message, ?int $user_id = null): array {
    $c = sms_config();
    $simulated = ($c === null);
    $status = 'Sent';

    if (!$simulated) {
        $msisdn = sms_msisdn($to, $c['country_code']);
        $fields = ['username' => $c['username'], 'to' => $msisdn, 'message' => $message];
        if (!empty($c['sender'])) $fields['from'] = $c['sender'];

        $ch = curl_init($c['base_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => [
                'apiKey: ' . $c['api_key'],
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($code >= 200 && $code < 300) ? 'Sent' : 'Failed';
    }

    // Log every message (truncate to column size)
    $msg = mb_substr($message, 0, 480);
    $stmt = mysqli_prepare($conn,
        "INSERT INTO sms_log (user_id, recipient, message, status, simulated) VALUES (?, ?, ?, ?, ?)");
    $sim = $simulated ? 1 : 0;
    mysqli_stmt_bind_param($stmt, "isssi", $user_id, $to, $msg, $status, $sim);
    mysqli_stmt_execute($stmt);

    return ['status' => $status, 'simulated' => $simulated];
}
