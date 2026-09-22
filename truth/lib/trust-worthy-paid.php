<?php
declare(strict_types=1);

require_once __DIR__ . '/trust-worthy-deep.php';

/**
 * Paid continuation foundation for Trust-Worthy.
 *
 * Disabled unless owner-only PayPal Orders credentials exist outside public_html.
 * The price is fixed server-side at $2.99 USD. Client input can never alter it.
 */
function tw_paid_config(): array {
    $cfg = [
        'enabled'=>false,
        'environment'=>'sandbox',
        'client_id'=>'',
        'client_secret'=>'',
        'amount'=>'2.99',
        'currency'=>'USD',
        'case_ttl_seconds'=>86400,
        'timeout_seconds'=>30,
    ];

    $path = tw_private_dir() . '/paypal-orders.json';
    if (tw_ai_private_path_exists($path)) {
        $raw = tw_ai_read_owner_regular_file_readonly($path, 2, 16384);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $cfg['enabled'] = ($decoded['enabled'] ?? false) === true;
                $env = strtolower(trim((string)($decoded['environment'] ?? 'sandbox')));
                $cfg['environment'] = in_array($env, ['sandbox','live'], true) ? $env : 'sandbox';
                $cfg['client_id'] = trim((string)($decoded['client_id'] ?? ''));
                $cfg['client_secret'] = trim((string)($decoded['client_secret'] ?? ''));
            }
        }
    }

    $envEnabled = trim((string)getenv('TW_PAYPAL_ENABLED'));
    if ($envEnabled !== '') $cfg['enabled'] = $envEnabled === '1';
    $envMode = strtolower(trim((string)getenv('TW_PAYPAL_ENVIRONMENT')));
    if (in_array($envMode, ['sandbox','live'], true)) $cfg['environment'] = $envMode;
    $clientId = trim((string)getenv('TW_PAYPAL_CLIENT_ID'));
    $clientSecret = trim((string)getenv('TW_PAYPAL_CLIENT_SECRET'));
    if ($clientId !== '') $cfg['client_id'] = $clientId;
    if ($clientSecret !== '') $cfg['client_secret'] = $clientSecret;

    return $cfg;
}

function tw_paid_ready(): bool {
    $cfg = tw_paid_config();
    return ($cfg['enabled'] ?? false) === true
        && is_string($cfg['client_id'] ?? null) && trim((string)$cfg['client_id']) !== ''
        && is_string($cfg['client_secret'] ?? null) && trim((string)$cfg['client_secret']) !== ''
        && ($cfg['amount'] ?? '') === '2.99'
        && ($cfg['currency'] ?? '') === 'USD';
}

function tw_paid_api_base(array $cfg): string {
    return ($cfg['environment'] ?? 'sandbox') === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

function tw_paid_case_path(string $caseId): string {
    if (preg_match('/^[a-f0-9]{32}$/D', $caseId) !== 1) return '';
    return tw_private_dir() . '/paid-case-' . $caseId . '.json';
}

function tw_paid_sign_state(string $caseId, string $purpose, string $secret, int $ttl = 1800): string {
    if (preg_match('/^[a-f0-9]{32}$/D', $caseId) !== 1 || $secret === '' || !in_array($purpose, ['checkout','resume'], true)) return '';
    $payload = ['v'=>1,'case_id'=>$caseId,'purpose'=>$purpose,'exp'=>time()+max(60,min($ttl,86400))];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) return '';
    $body = tw_deep_b64url_encode($json);
    return $body . '.' . hash_hmac('sha256', 'paid-state|' . $body, $secret);
}

function tw_paid_verify_state(string $token, string $purpose, string $secret): ?array {
    if ($secret === '' || strlen($token) > 4096 || !str_contains($token, '.')) return null;
    [$body,$signature] = explode('.', $token, 2);
    if (preg_match('/^[a-f0-9]{64}$/D', $signature) !== 1) return null;
    if (!hash_equals(hash_hmac('sha256','paid-state|'.$body,$secret), $signature)) return null;
    $raw = tw_deep_b64url_decode($body);
    if (!is_string($raw)) return null;
    $payload = json_decode($raw, true);
    if (!is_array($payload) || (int)($payload['v'] ?? 0) !== 1 || ($payload['purpose'] ?? '') !== $purpose) return null;
    if ((int)($payload['exp'] ?? 0) < time()) return null;
    $caseId = (string)($payload['case_id'] ?? '');
    return preg_match('/^[a-f0-9]{32}$/D', $caseId) === 1 ? $payload : null;
}

function tw_paid_create_case(array $payload, string $secret): array {
    if ($secret === '' || !tw_ai_prepare_private_dir()) return ['ok'=>false,'message'=>'Private paid-continuation storage is unavailable.'];
    try { $caseId = bin2hex(random_bytes(16)); } catch (Throwable $error) { return ['ok'=>false,'message'=>'Could not create investigation state.']; }
    $cfg = tw_paid_config();
    $record = [
        'v'=>1,
        'case_id'=>$caseId,
        'state'=>'awaiting_payment',
        'amount'=>'2.99',
        'currency'=>'USD',
        'created_at'=>time(),
        'expires_at'=>time() + (int)$cfg['case_ttl_seconds'],
        'paypal_order_id'=>'',
        'capture_id'=>'',
        'paid_at'=>null,
        'resume_consumed_at'=>null,
        'payload'=>$payload,
    ];
    $json = json_encode($record, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > 262144) return ['ok'=>false,'message'=>'Investigation continuation state exceeded its safety limit.'];
    $path = tw_paid_case_path($caseId);
    if ($path === '' || !tw_ai_atomic_private_write($path, $json . "\n")) return ['ok'=>false,'message'=>'Could not persist investigation continuation state.'];
    $token = tw_paid_sign_state($caseId, 'checkout', $secret, 3600);
    if ($token === '') return ['ok'=>false,'message'=>'Could not authorize checkout.'];
    return ['ok'=>true,'case_id'=>$caseId,'checkout_state'=>$token,'amount'=>'2.99','currency'=>'USD'];
}

function tw_paid_load_case(string $caseId): ?array {
    $path = tw_paid_case_path($caseId);
    if ($path === '') return null;
    $raw = tw_ai_read_secure_regular_file($path, 2, 262144);
    if (!is_string($raw)) return null;
    $record = json_decode(trim($raw), true);
    if (!is_array($record) || ($record['case_id'] ?? '') !== $caseId || (int)($record['v'] ?? 0) !== 1) return null;
    if ((int)($record['expires_at'] ?? 0) < time()) return null;
    return $record;
}

function tw_paid_update_case(string $caseId, callable $mutator): ?array {
    $path = tw_paid_case_path($caseId);
    if ($path === '') return null;
    try {
        return tw_ai_with_private_file_lock(tw_private_dir() . '/paid-cases.lock', static function() use ($path,$caseId,$mutator): ?array {
            $raw = tw_ai_read_secure_regular_file($path, 2, 262144);
            if (!is_string($raw)) return null;
            $record = json_decode(trim($raw), true);
            if (!is_array($record) || ($record['case_id'] ?? '') !== $caseId || (int)($record['v'] ?? 0) !== 1) return null;
            $updated = $mutator($record);
            if (!is_array($updated) || ($updated['case_id'] ?? '') !== $caseId) return null;
            $json = json_encode($updated, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if (!is_string($json) || strlen($json) > 262144 || !tw_ai_atomic_private_write($path, $json . "\n")) return null;
            return $updated;
        });
    } catch (Throwable $error) {
        return null;
    }
}

function tw_paid_build_order_payload(string $caseId, string $stateToken): array {
    $return = 'https://bobsome1.com/truth/paypal-return.php?state=' . rawurlencode($stateToken);
    $cancel = 'https://bobsome1.com/truth/paypal-return.php?cancel=1&state=' . rawurlencode($stateToken);
    return [
        'intent'=>'CAPTURE',
        'purchase_units'=>[[
            'custom_id'=>$caseId,
            'description'=>'Trust-Worthy — Unveiling the Truth',
            'amount'=>['currency_code'=>'USD','value'=>'2.99'],
        ]],
        'payment_source'=>['paypal'=>['experience_context'=>[
            'brand_name'=>'Trust-Worthy / Project Unveiled',
            'shipping_preference'=>'NO_SHIPPING',
            'user_action'=>'PAY_NOW',
            'return_url'=>$return,
            'cancel_url'=>$cancel,
        ]]],
    ];
}

function tw_paid_http(string $method, string $url, array $headers, ?string $body = null): array {
    $ch = curl_init($url);
    if ($ch === false) return ['ok'=>false,'status'=>0,'body'=>null];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>(int)tw_paid_config()['timeout_seconds'],
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_POSTFIELDS=>$body,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($raw) || $status < 200 || $status >= 300 || strlen($raw) > 1048576) return ['ok'=>false,'status'=>$status,'body'=>null];
    $decoded = json_decode($raw, true);
    return ['ok'=>is_array($decoded),'status'=>$status,'body'=>is_array($decoded)?$decoded:null];
}

function tw_paid_access_token(): string {
    if (!tw_paid_ready()) return '';
    $cfg = tw_paid_config();
    $base = tw_paid_api_base($cfg);
    $credentials = base64_encode((string)$cfg['client_id'] . ':' . (string)$cfg['client_secret']);
    $response = tw_paid_http('POST', $base . '/v1/oauth2/token', [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ], 'grant_type=client_credentials');
    if (!($response['ok'] ?? false)) return '';
    $token = trim((string)($response['body']['access_token'] ?? ''));
    return strlen($token) >= 20 ? $token : '';
}

function tw_paid_create_paypal_order(string $caseId, string $stateToken): array {
    if (!tw_paid_ready()) return ['ok'=>false,'message'=>'Paid continuation is not configured.'];
    $case = tw_paid_load_case($caseId);
    if (!is_array($case) || ($case['state'] ?? '') !== 'awaiting_payment') return ['ok'=>false,'message'=>'Investigation checkout state is unavailable.'];
    $token = tw_paid_access_token();
    if ($token === '') return ['ok'=>false,'message'=>'Payment provider authorization failed.'];
    $payload = tw_paid_build_order_payload($caseId, $stateToken);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) return ['ok'=>false,'message'=>'Payment order encoding failed.'];
    $cfg = tw_paid_config();
    $response = tw_paid_http('POST', tw_paid_api_base($cfg) . '/v2/checkout/orders', [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
        'PayPal-Request-Id: tw-' . $caseId,
    ], $json);
    if (!($response['ok'] ?? false)) return ['ok'=>false,'message'=>'Payment order creation failed.'];
    $body = $response['body'];
    $orderId = trim((string)($body['id'] ?? ''));
    $approval = '';
    foreach (($body['links'] ?? []) as $link) {
        if (!is_array($link)) continue;
        if (in_array((string)($link['rel'] ?? ''), ['payer-action','approve'], true)) {
            $candidate = (string)($link['href'] ?? '');
            $parts = parse_url($candidate);
            $host = is_array($parts) ? strtolower((string)($parts['host'] ?? '')) : '';
            if (($parts['scheme'] ?? '') === 'https' && ($host === 'www.paypal.com' || $host === 'www.sandbox.paypal.com')) $approval = $candidate;
        }
    }
    if ($orderId === '' || $approval === '') return ['ok'=>false,'message'=>'Payment provider returned an incomplete approval handoff.'];
    $saved = tw_paid_update_case($caseId, static function(array $record) use ($orderId): array {
        if (($record['state'] ?? '') !== 'awaiting_payment') return $record;
        $record['paypal_order_id']=$orderId;
        $record['order_created_at']=time();
        return $record;
    });
    if (!is_array($saved) || ($saved['paypal_order_id'] ?? '') !== $orderId) return ['ok'=>false,'message'=>'Could not persist payment order state.'];
    return ['ok'=>true,'order_id'=>$orderId,'approval_url'=>$approval];
}

function tw_paid_order_is_exact(array $order, string $caseId): bool {
    if (($order['status'] ?? '') !== 'COMPLETED') return false;
    $units = $order['purchase_units'] ?? null;
    if (!is_array($units) || count($units) !== 1 || !is_array($units[0])) return false;
    $unit = $units[0];
    if (($unit['custom_id'] ?? '') !== $caseId) return false;
    $captures = $unit['payments']['captures'] ?? null;
    if (!is_array($captures) || count($captures) < 1) return false;
    foreach ($captures as $capture) {
        if (!is_array($capture) || ($capture['status'] ?? '') !== 'COMPLETED') continue;
        $amount = $capture['amount'] ?? null;
        if (is_array($amount) && ($amount['currency_code'] ?? '') === 'USD' && ($amount['value'] ?? '') === '2.99') return true;
    }
    return false;
}

function tw_paid_capture_paypal_order(string $caseId, string $orderId): array {
    if (!tw_paid_ready() || preg_match('/^[A-Z0-9-]{6,64}$/D', $orderId) !== 1) return ['ok'=>false,'message'=>'Payment verification is unavailable.'];
    $case = tw_paid_load_case($caseId);
    if (!is_array($case) || ($case['paypal_order_id'] ?? '') !== $orderId) return ['ok'=>false,'message'=>'Payment order did not match this investigation.'];
    $token = tw_paid_access_token();
    if ($token === '') return ['ok'=>false,'message'=>'Payment provider authorization failed.'];
    $cfg = tw_paid_config();
    $base = tw_paid_api_base($cfg);
    $headers=['Authorization: Bearer '.$token,'Accept: application/json','Content-Type: application/json'];
    $get = tw_paid_http('GET', $base . '/v2/checkout/orders/' . rawurlencode($orderId), $headers, null);
    $order = ($get['ok'] ?? false) ? $get['body'] : null;
    if (is_array($order) && ($order['status'] ?? '') === 'APPROVED') {
        $capture = tw_paid_http('POST', $base . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', array_merge($headers,['PayPal-Request-Id: capture-'.$caseId]), '{}');
        if (!($capture['ok'] ?? false)) return ['ok'=>false,'message'=>'Payment capture failed.'];
        $order = $capture['body'];
    }
    if (!is_array($order) || !tw_paid_order_is_exact($order, $caseId)) return ['ok'=>false,'message'=>'PayPal did not confirm a completed $2.99 payment for this investigation.'];
    $captureId='';
    foreach (($order['purchase_units'][0]['payments']['captures'] ?? []) as $capture) {
        if (is_array($capture) && ($capture['status'] ?? '') === 'COMPLETED') { $captureId=(string)($capture['id'] ?? ''); break; }
    }
    $saved = tw_paid_update_case($caseId, static function(array $record) use ($orderId,$captureId): array {
        if (($record['paypal_order_id'] ?? '') !== $orderId) return $record;
        $record['state']='paid';
        $record['capture_id']=$captureId;
        $record['paid_at']=time();
        return $record;
    });
    if (!is_array($saved) || ($saved['state'] ?? '') !== 'paid') return ['ok'=>false,'message'=>'Payment was verified but continuation state could not be updated.'];
    $secret=tw_question_secret();
    $resume=$secret!==''?tw_paid_sign_state($caseId,'resume',$secret,3600):'';
    return $resume!=='' ? ['ok'=>true,'resume_token'=>$resume,'capture_id'=>$captureId] : ['ok'=>false,'message'=>'Payment verified but resume authorization failed.'];
}

function tw_paid_consume_resume(string $caseId, string $resumeToken, string $secret): ?array {
    $payload = tw_paid_verify_state($resumeToken, 'resume', $secret);
    if (!is_array($payload) || ($payload['case_id'] ?? '') !== $caseId) return null;
    return tw_paid_update_case($caseId, static function(array $record): array {
        if (($record['state'] ?? '') !== 'paid' || ($record['resume_consumed_at'] ?? null) !== null) return $record;
        $record['state']='resume_authorized';
        $record['resume_consumed_at']=time();
        return $record;
    });
}
