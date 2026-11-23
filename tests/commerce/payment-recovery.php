<?php
declare(strict_types=1);

/* Uses the offline backend suite's fixtures and assertions. No provider calls. */
$recoveryDir = $temp . '/recovery';
mkdir($recoveryDir, 0700);
$recoveryConfig = $identity + ['private_dir' => $recoveryDir, 'merchant_id' => 'MERCHANTTEST1'];
$saved = bc_new_order(str_repeat('8', 32), $token, time() - BC_CHECKOUT_TTL - 30, $identity);
$saved['state'] = 'created';
$saved['paypal_order_id'] = 'ORDERRECOVERY123';
$saved['capture_attempted_at'] = time() - 60;
$saveRecovery = static function (array $order) use ($recoveryDir): void {
    bc_store($recoveryDir, static function (array &$ledger) use ($order): void { $ledger['orders'] = [$order['id'] => $order]; });
};
$calls = [];
$readOnly = static function (array $config, string $method, string $route) use (&$calls, $saved): array {
    $calls[] = [$method, $route];
    check($method === 'GET' && in_array($route, ['/v2/checkout/orders/' . $saved['paypal_order_id'], '/v2/payments/captures/CAPTURETEST12345'], true), 'Recovery reads only the saved PayPal order and its capture');
    $response = fixture($saved);
    return str_starts_with($route, '/v2/payments/captures/') ? $response['purchase_units'][0]['payments']['captures'][0] : $response;
};
try {
    $saveRecovery($saved);
    $recovered = bc_reconcile_order($recoveryConfig, $saved, $readOnly);
    check($recovered['state'] === 'paid', 'Expired uncertain capture can recover a verified completed payment');
    check($recovered['id'] === $saved['id'] && $recovered['capture_request_id'] === $saved['capture_request_id'], 'Recovery preserves original order and idempotency identity');
    check(count($calls) === 2 && $calls[0][0] === 'GET' && $calls[1][0] === 'GET', 'Recovery never creates or captures a payment');
    $firstGrant = $recovered['download_expires_at'];
    $again = bc_reconcile_order($recoveryConfig, $saved, $readOnly);
    check($again === $recovered && $again['download_expires_at'] === $firstGrant, 'Repeated stale-tab recovery preserves the first grant');

    foreach (['APPROVED', 'PENDING', 'VOIDED'] as $state) {
        $saveRecovery($saved); $before = file_get_contents($recoveryDir . '/orders.json');
        rejects(static function () use ($recoveryConfig, $saved, $state): void {
            bc_reconcile_order($recoveryConfig, $saved, static function () use ($saved, $state): array {
                $response = fixture($saved); $response['status'] = $state; return $response;
            });
        }, 'Recovery does not fulfill ' . $state, 409);
        check(file_get_contents($recoveryDir . '/orders.json') === $before, 'Unpaid recovery does not mutate ledger');
    }
    $saveRecovery($saved); $before = file_get_contents($recoveryDir . '/orders.json');
    rejects(fn() => bc_reconcile_order($recoveryConfig, $saved, static function (): never { throw new BcException('Synthetic provider timeout'); }), 'Provider failure remains retryable', 503);
    check(file_get_contents($recoveryDir . '/orders.json') === $before, 'Provider timeout leaves order unchanged');
    rejects(fn() => bc_reconcile_order($recoveryConfig, $saved, static function (array $config, string $method, string $route) use ($saved): array {
        $response = fixture($saved);
        if (str_starts_with($route, '/v2/payments/captures/')) {
            $response = $response['purchase_units'][0]['payments']['captures'][0];
            $response['status'] = 'REFUNDED';
        }
        return $response;
    }), 'An old completed order cannot restore a currently refunded capture', 409);
    check(file_get_contents($recoveryDir . '/orders.json') === $before, 'Current capture refund leaves order unchanged');
    foreach (['merchant', 'amount', 'refund'] as $fault) {
        rejects(fn() => bc_reconcile_order($recoveryConfig, $saved, static function () use ($saved, $fault): array {
            $response = fixture($saved);
            if ($fault === 'merchant') $response['purchase_units'][0]['payee']['merchant_id'] = 'OTHERSELLER1';
            if ($fault === 'amount') $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'] = '0.01';
            if ($fault === 'refund') $response['purchase_units'][0]['payments']['captures'][0]['status'] = 'REFUNDED';
            return $response;
        }), 'Recovery rejects incorrect or refunded payment: ' . $fault, 409);
    }
    $blockedRecovery = $saved; $blockedRecovery['state'] = 'blocked';
    $saveRecovery($blockedRecovery);
    rejects(fn() => bc_reconcile_order($recoveryConfig, $saved, $readOnly), 'Stale-tab recovery cannot revive a blocked order', 409);
    $moved = $saved; $moved['paypal_order_id'] = 'OTHERORDER12345'; $saveRecovery($moved);
    rejects(fn() => bc_reconcile_order($recoveryConfig, $saved, $readOnly), 'Concurrent order identity change fails closed', 409);
    $saveRecovery($saved);
    bc_store($recoveryDir, static function (array &$ledger) use ($saved): void {
        $other = $saved; $other['id'] = str_repeat('9', 32); $other['state'] = 'paid';
        $other['paid_at'] = time(); $other['capture_id'] = 'CAPTURETEST12345';
        $ledger['orders'][$other['id']] = $other;
    });
    rejects(fn() => bc_reconcile_order($recoveryConfig, $saved, $readOnly), 'A capture cannot fulfill two saved orders', 409);
    $changedAsset = $recoveryConfig; $changedAsset['asset_sha256'] = str_repeat('0', 64);
    $beforeCalls = count($calls);
    rejects(fn() => bc_reconcile_order($changedAsset, $saved, $readOnly), 'Changed bundle prevents recovery', 409);
    check(count($calls) === $beforeCalls, 'Bundle mismatch is rejected before contacting PayPal');

    $expiredMarkup = bc_existing_checkout_content($saved, 'csrf<&"', time());
    check(str_contains($expiredMarkup, 'action="check.php"') && !str_contains($expiredMarkup, 'action="start.php"') && !str_contains($expiredMarkup, 'action="capture.php"'), 'Expired checkout offers only read-only payment recovery');
    check(str_contains($expiredMarkup, 'csrf&lt;&amp;&quot;'), 'Recovery form escapes its CSRF field');
    $fresh = $saved; $fresh['checkout_expires_at'] = time() + 100; $fresh['capture_attempted_at'] = null;
    $markup = bc_existing_checkout_content($fresh, 'csrf', time());
    check(str_contains($markup, 'action="check.php"') && str_contains($markup, 'Resume my existing PayPal checkout'), 'Fresh saved order offers both payment check and same-order continuation');
    $fresh['capture_attempted_at'] = time();
    $markup = bc_existing_checkout_content($fresh, 'csrf', time());
    check(!str_contains($markup, 'action="start.php"') && str_contains($markup, 'Retry the same $7 payment confirmation'), 'Uncertain capture offers explicit same-request confirmation, never a new order');
    $fresh['state'] = 'creating'; $fresh['paypal_order_id'] = null; $fresh['capture_attempted_at'] = null;
    $markup = bc_existing_checkout_content($fresh, 'csrf', time());
    check(!str_contains($markup, 'action="check.php"') && str_contains($markup, 'action="start.php"'), 'Unreturned order creation resumes its original idempotency key');
} finally {
    foreach (glob($recoveryDir . '/*') ?: [] as $path) if (is_file($path)) unlink($path);
    if (is_dir($recoveryDir)) rmdir($recoveryDir);
}
