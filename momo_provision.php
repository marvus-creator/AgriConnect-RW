<?php
/**
 * momo_provision.php — one-time helper to create MTN MoMo sandbox API users + keys.
 *
 * Prerequisite: edit includes/momo_config.php and set collection_subkey and/or
 * disbursement_subkey to your Primary Subscription Keys from momodeveloper.mtn.com.
 *
 * Then run:   php momo_provision.php
 * Copy the printed api_user / api_key values back into includes/momo_config.php.
 *
 * Safe to run repeatedly — each run provisions a fresh API user.
 */

$cli = (php_sapi_name() === 'cli');
$nl = $cli ? "\n" : "<br>";

$cfg_path = __DIR__ . '/includes/momo_config.php';
if (!is_file($cfg_path)) {
    exit("Create includes/momo_config.php first (copy from momo_config.sample.php) and set your subscription keys.$nl");
}
$cfg = include $cfg_path;
$base = $cfg['base_url'] ?? 'https://sandbox.momodeveloper.mtn.com';
$callback = $cfg['callback_host'] ?? 'agriconnect.example.com';

function uuid4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function provision(string $base, string $subkey, string $callback): array {
    $apiuser = uuid4();

    // 1) Create API user
    $ch = curl_init("$base/v1_0/apiuser");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_POSTFIELDS => json_encode(['providerCallbackHost' => $callback]),
        CURLOPT_HTTPHEADER => [
            'X-Reference-Id: ' . $apiuser,
            'Ocp-Apim-Subscription-Key: ' . $subkey,
            'Content-Type: application/json',
        ],
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 201) return ['error' => "create apiuser failed (HTTP $code) — check the subscription key."];

    // 2) Create API key for that user
    $ch = curl_init("$base/v1_0/apiuser/$apiuser/apikey");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Ocp-Apim-Subscription-Key: ' . $subkey,
            'Content-Length: 0',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 201) return ['error' => "create apikey failed (HTTP $code)."];

    $apikey = json_decode($resp, true)['apiKey'] ?? null;
    return $apikey ? ['api_user' => $apiuser, 'api_key' => $apikey] : ['error' => 'no apiKey in response'];
}

echo $cli ? "=== MoMo Sandbox Provisioning ===\n" : "<h2>MoMo Sandbox Provisioning</h2><pre>";

foreach (['collection', 'disbursement'] as $product) {
    $subkey = $cfg["{$product}_subkey"] ?? '';
    if (!$subkey || str_starts_with($subkey, 'YOUR_')) {
        echo "[$product] skipped — no subscription key set in momo_config.php$nl";
        continue;
    }
    $r = provision($base, $subkey, $callback);
    if (isset($r['error'])) {
        echo "[$product] ERROR: {$r['error']}$nl";
    } else {
        echo "[$product] OK — paste these into momo_config.php:$nl";
        echo "    '{$product}_apiuser' => '{$r['api_user']}',$nl";
        echo "    '{$product}_apikey'  => '{$r['api_key']}',$nl";
    }
}
echo $cli ? "\nDone.\n" : "</pre>";
