<?php
/**
 * includes/momo.php — MTN Mobile Money client for AgriConnect.
 *
 * Two flows:
 *   - Collection   (momo_request_to_pay): charge a buyer's MoMo wallet.
 *   - Disbursement (momo_transfer):       pay out to a farmer/driver's MoMo wallet.
 *
 * Works in SIMULATION mode out of the box (no credentials needed) so the app
 * runs and demos. Drop real sandbox credentials into includes/momo_config.php
 * (copy from momo_config.sample.php) and it makes live MTN MoMo sandbox calls.
 *
 * MoMo has no official PHP SDK, so this uses cURL directly against the REST API.
 */

if (!function_exists('momo_config')) {
    function momo_config(): ?array {
        // Env vars take priority; otherwise includes/momo_config.php
        $env_sub = getenv('MOMO_COLLECTION_SUBKEY');
        if ($env_sub) {
            return [
                'base_url'              => getenv('MOMO_BASE_URL') ?: 'https://sandbox.momodeveloper.mtn.com',
                'environment'           => getenv('MOMO_ENVIRONMENT') ?: 'sandbox',
                'currency'              => getenv('MOMO_CURRENCY') ?: 'EUR',
                'country_code'          => getenv('MOMO_COUNTRY_CODE') ?: '250',
                'collection_subkey'     => $env_sub,
                'collection_apiuser'    => getenv('MOMO_COLLECTION_APIUSER') ?: '',
                'collection_apikey'     => getenv('MOMO_COLLECTION_APIKEY') ?: '',
                'disbursement_subkey'   => getenv('MOMO_DISBURSEMENT_SUBKEY') ?: '',
                'disbursement_apiuser'  => getenv('MOMO_DISBURSEMENT_APIUSER') ?: '',
                'disbursement_apikey'   => getenv('MOMO_DISBURSEMENT_APIKEY') ?: '',
            ];
        }
        $cfg = __DIR__ . '/momo_config.php';
        if (is_file($cfg)) {
            $data = include $cfg;
            if (is_array($data) && !empty($data['collection_subkey'])) return $data;
        }
        return null;
    }
}

function momo_is_live(): bool {
    $c = momo_config();
    return $c !== null && !empty($c['collection_apiuser']) && !empty($c['collection_apikey']);
}

function momo_uuid(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/** Normalise a local Rwandan number (07...) to MSISDN (2507...). */
function momo_msisdn(string $phone, string $country_code = '250'): string {
    $p = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($p, '0')) $p = $country_code . substr($p, 1);
    elseif (!str_starts_with($p, $country_code)) $p = $country_code . $p;
    return $p;
}

/** Fetch an OAuth token for 'collection' or 'disbursement'. Returns token or null. */
function momo_token(array $c, string $product): ?string {
    $apiuser = $c[$product . '_apiuser'] ?? '';
    $apikey  = $c[$product . '_apikey'] ?? '';
    $subkey  = $c[$product . '_subkey'] ?? '';
    if (!$apiuser || !$apikey || !$subkey) return null;

    $ch = curl_init($c['base_url'] . "/$product/token/");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERPWD => "$apiuser:$apikey",
        CURLOPT_HTTPHEADER => [
            'Ocp-Apim-Subscription-Key: ' . $subkey,
            'Content-Length: 0',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        $j = json_decode($resp, true);
        return $j['access_token'] ?? null;
    }
    return null;
}

/**
 * Collection — request payment from a buyer's MoMo wallet.
 * @return array ['status'=>'SUCCESSFUL'|'PENDING'|'FAILED', 'reference'=>..., 'simulated'=>bool, 'message'=>...]
 */
function momo_request_to_pay(float $amount, string $phone, string $payer_msg = '', string $payee_note = ''): array {
    $c = momo_config();
    $ref = momo_uuid();

    if (!momo_is_live()) {
        return ['status' => 'SUCCESSFUL', 'reference' => $ref, 'simulated' => true,
                'message' => 'Simulated MoMo collection (no live credentials configured).'];
    }

    $token = momo_token($c, 'collection');
    if (!$token) return ['status' => 'FAILED', 'reference' => $ref, 'simulated' => false, 'message' => 'Could not authenticate with MoMo.'];

    $body = json_encode([
        'amount'       => (string) (int) round($amount),
        'currency'     => $c['currency'],
        'externalId'   => substr(str_replace('-', '', $ref), 0, 12),
        'payer'        => ['partyIdType' => 'MSISDN', 'partyId' => momo_msisdn($phone, $c['country_code'])],
        'payerMessage' => $payer_msg ?: 'AgriConnect order payment',
        'payeeNote'    => $payee_note ?: 'AgriConnect',
    ]);

    $ch = curl_init($c['base_url'] . '/collection/v1_0/requesttopay');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'X-Reference-Id: ' . $ref,
            'X-Target-Environment: ' . $c['environment'],
            'Ocp-Apim-Subscription-Key: ' . $c['collection_subkey'],
            'Content-Type: application/json',
        ],
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 202) {
        return ['status' => 'FAILED', 'reference' => $ref, 'simulated' => false, 'message' => "MoMo declined the request (HTTP $code)."];
    }

    // Poll the final status (sandbox usually resolves immediately)
    $status = momo_poll_status($c, 'collection', 'requesttopay', $ref);
    return ['status' => $status, 'reference' => $ref, 'simulated' => false,
            'message' => $status === 'SUCCESSFUL' ? 'Payment confirmed via MoMo.' : "MoMo payment status: $status."];
}

/**
 * Disbursement — transfer funds to a farmer/driver's MoMo wallet (payout).
 */
function momo_transfer(float $amount, string $phone, string $note = ''): array {
    $c = momo_config();
    $ref = momo_uuid();
    $live = $c !== null && !empty($c['disbursement_apiuser']) && !empty($c['disbursement_apikey']);

    if (!$live) {
        return ['status' => 'SUCCESSFUL', 'reference' => $ref, 'simulated' => true,
                'message' => 'Simulated MoMo payout (no live disbursement credentials configured).'];
    }

    $token = momo_token($c, 'disbursement');
    if (!$token) return ['status' => 'FAILED', 'reference' => $ref, 'simulated' => false, 'message' => 'Could not authenticate with MoMo.'];

    $body = json_encode([
        'amount'      => (string) (int) round($amount),
        'currency'    => $c['currency'],
        'externalId'  => substr(str_replace('-', '', $ref), 0, 12),
        'payee'       => ['partyIdType' => 'MSISDN', 'partyId' => momo_msisdn($phone, $c['country_code'])],
        'payerMessage'=> $note ?: 'AgriConnect payout',
        'payeeNote'   => 'AgriConnect earnings',
    ]);

    $ch = curl_init($c['base_url'] . '/disbursement/v1_0/transfer');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'X-Reference-Id: ' . $ref,
            'X-Target-Environment: ' . $c['environment'],
            'Ocp-Apim-Subscription-Key: ' . $c['disbursement_subkey'],
            'Content-Type: application/json',
        ],
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 202) {
        return ['status' => 'FAILED', 'reference' => $ref, 'simulated' => false, 'message' => "MoMo payout failed (HTTP $code)."];
    }

    $status = momo_poll_status($c, 'disbursement', 'transfer', $ref);
    return ['status' => $status, 'reference' => $ref, 'simulated' => false,
            'message' => $status === 'SUCCESSFUL' ? 'Payout sent via MoMo.' : "MoMo payout status: $status."];
}

/** Poll a transaction's final status, retrying briefly. */
function momo_poll_status(array $c, string $product, string $resource, string $ref): string {
    $subkey = $c[$product . '_subkey'];
    $token = momo_token($c, $product);
    if (!$token) return 'PENDING';

    for ($i = 0; $i < 3; $i++) {
        $ch = curl_init($c['base_url'] . "/$product/v1_0/$resource/$ref");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'X-Target-Environment: ' . $c['environment'],
                'Ocp-Apim-Subscription-Key: ' . $subkey,
            ],
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $j = json_decode($resp, true);
        $status = $j['status'] ?? 'PENDING';
        if ($status !== 'PENDING') return $status;
        usleep(700000); // 0.7s
    }
    return 'PENDING';
}

/** Record a transaction in the ledger. */
function momo_log(mysqli $conn, array $result, string $type, int $user_id, ?int $order_id, float $amount, string $phone): void {
    $stmt = mysqli_prepare($conn,
        "INSERT INTO transactions (reference_id, type, user_id, order_id, amount, phone, status, simulated)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $ref = $result['reference'];
    $amt = (int) round($amount);
    $status = $result['status'];
    $sim = !empty($result['simulated']) ? 1 : 0;
    mysqli_stmt_bind_param($stmt, "ssiisssi", $ref, $type, $user_id, $order_id, $amt, $phone, $status, $sim);
    mysqli_stmt_execute($stmt);
}
