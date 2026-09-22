<?php
declare(strict_types=1);

$private = sys_get_temp_dir() . '/tw-paid-' . bin2hex(random_bytes(6));
if (!mkdir($private, 0750, true) || !chmod($private, 0750)) throw new RuntimeException('temp private dir failed');
define('TW_PRIVATE_DIR_OVERRIDE', $private);
require_once dirname(__DIR__, 2) . '/truth/lib/trust-worthy-funnel-v1.php';

$fail = static function(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$secret = str_repeat('a', 64);
$caseId = str_repeat('b', 32);

$fail(tw_paid_ready() === false, 'paid continuation must fail closed without owner PayPal credentials');
$fail(tw_paid_config()['amount'] === '2.99', 'server-side price must be exactly 2.99');
$fail(tw_paid_config()['currency'] === 'USD', 'server-side currency must be USD');

$state = tw_paid_sign_state($caseId, 'checkout', $secret, 600);
$fail($state !== '', 'checkout state signing failed');
$verified = tw_paid_verify_state($state, 'checkout', $secret);
$fail(is_array($verified) && ($verified['case_id'] ?? '') === $caseId, 'signed checkout state did not verify');
$fail(tw_paid_verify_state($state . 'x', 'checkout', $secret) === null, 'tampered checkout state was accepted');
$fail(tw_paid_verify_state($state, 'resume', $secret) === null, 'checkout state crossed purpose boundary');

$orderPayload = tw_paid_build_order_payload($caseId, $state);
$unit = $orderPayload['purchase_units'][0] ?? [];
$amount = is_array($unit) ? ($unit['amount'] ?? []) : [];
$fail(($unit['custom_id'] ?? '') === $caseId, 'PayPal custom_id is not bound to investigation case');
$fail(($amount['value'] ?? '') === '2.99' && ($amount['currency_code'] ?? '') === 'USD', 'PayPal amount is not fixed at 2.99 USD');
$experience = $orderPayload['payment_source']['paypal']['experience_context'] ?? [];
$fail(($experience['shipping_preference'] ?? '') === 'NO_SHIPPING', 'digital investigation must not request shipping');
$fail(str_starts_with((string)($experience['return_url'] ?? ''), 'https://bobsome1.com/truth/paypal-return.php?state='), 'return URL must stay on first-party HTTPS origin');

$created = tw_paid_create_case(['claim'=>'test claim','free_passes'=>['origin'=>['ok'=>true]]], $secret);
$fail(($created['ok'] ?? false) === true, 'paid case creation failed');
$createdId = (string)$created['case_id'];
$loaded = tw_paid_load_case($createdId);
$fail(is_array($loaded) && ($loaded['state'] ?? '') === 'awaiting_payment', 'paid case did not persist fail-closed state');
$fail(($loaded['amount'] ?? '') === '2.99' && ($loaded['currency'] ?? '') === 'USD', 'persisted paid case price changed');

$completed = [
    'status'=>'COMPLETED',
    'purchase_units'=>[[
        'custom_id'=>$caseId,
        'payments'=>['captures'=>[[
            'id'=>'CAPTURE123',
            'status'=>'COMPLETED',
            'amount'=>['currency_code'=>'USD','value'=>'2.99'],
        ]]],
    ]],
];
$fail(tw_paid_order_is_exact($completed, $caseId) === true, 'exact completed payment was rejected');
$wrongAmount = $completed;
$wrongAmount['purchase_units'][0]['payments']['captures'][0]['amount']['value'] = '2.98';
$fail(tw_paid_order_is_exact($wrongAmount, $caseId) === false, 'wrong amount was accepted');
$wrongCase = $completed;
$wrongCase['purchase_units'][0]['custom_id'] = str_repeat('c', 32);
$fail(tw_paid_order_is_exact($wrongCase, $caseId) === false, 'payment for another investigation was accepted');
$pending = $completed;
$pending['status'] = 'APPROVED';
$fail(tw_paid_order_is_exact($pending, $caseId) === false, 'uncaptured payment was accepted');

$paid = tw_paid_update_case($createdId, static function(array $record): array { $record['state']='paid'; $record['paid_at']=time(); return $record; });
$fail(is_array($paid) && ($paid['state'] ?? '') === 'paid', 'test case could not enter paid state');
$resume = tw_paid_sign_state($createdId, 'resume', $secret, 600);
$first = tw_funnel_consume_resume($createdId, $resume, $secret);
$fail(is_array($first) && ($first['state'] ?? '') === 'resume_authorized', 'first paid resume authorization failed');
$second = tw_funnel_consume_resume($createdId, $resume, $secret);
$fail($second === null, 'paid resume token replay was accepted');

foreach (glob($private . '/*') ?: [] as $path) @unlink($path);
@rmdir($private);
echo "Paid continuation integrity tests passed.\n";
