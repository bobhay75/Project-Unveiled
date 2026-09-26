<?php
declare(strict_types=1);

/* Offline synthetic fixtures only. Never calls PayPal, sends mail, or charges. */
require_once dirname(__DIR__, 2) . '/store/checkout/lib.php';
$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function rejects(callable $callback, string $message, ?int $status = null): void
{
    try { $callback(); } catch (Throwable $error) {
        check($status === null || ($error instanceof BcException && $error->httpStatus === $status), $message . ': wrong exception');
        return;
    }
    check(false, $message . ': unexpectedly accepted');
}
function fixture(array $order): array
{
    return ['id' => $order['paypal_order_id'], 'status' => 'COMPLETED', 'purchase_units' => [[
        'custom_id' => $order['id'], 'reference_id' => BC_SKU, 'payee' => ['merchant_id' => 'MERCHANTTEST1'],
        'amount' => ['value' => '7.00', 'currency_code' => 'USD'],
        'payments' => ['captures' => [[
            'id' => 'CAPTURETEST12345', 'status' => 'COMPLETED', 'amount' => ['value' => '7.00', 'currency_code' => 'USD'],
        ]]],
    ]]];
}

$temp = sys_get_temp_dir() . '/bobsome1-commerce-test-' . bin2hex(random_bytes(8));
mkdir($temp, 0700);
$token = str_repeat('d', 64);
$local = bc_new_order(str_repeat('a', 32), $token, time());
$local['paypal_order_id'] = 'ORDERTEST1234567';
$local['state'] = 'created';
try {
    check($local['token_hash'] !== $token && $local['token_hash'] === hash('sha256', $token), 'Only token hash is persisted');
    check(!str_contains(json_encode($local), $token), 'Raw token must not enter record');
    check($local['create_request_id'] !== $local['capture_request_id'], 'API operation IDs differ');
    check(strlen($local['create_request_id']) <= 38, 'Idempotency key compatible with conservative limit');
    rejects(fn() => bc_new_order('invalid', $token, time()), 'Invalid local ID');
    rejects(fn() => bc_new_order(str_repeat('a', 32), 'short', time()), 'Invalid token entropy');

    $payload = bc_create_payload(['merchant_id' => 'MERCHANTTEST1', 'price' => '0.01'], $local + ['price' => '0.01']);
    check($payload['purchase_units'][0]['amount'] === ['currency_code' => 'USD', 'value' => '7.00'], 'Price is server owned');
    check($payload['purchase_units'][0]['payee']['merchant_id'] === 'MERCHANTTEST1', 'Payee explicitly pinned');
    check($payload['payment_source']['paypal']['experience_context']['return_url'] === 'https://bobsome1.com/store/checkout/return.php', 'Canonical return URL');
    check($payload['payment_source']['paypal']['experience_context']['shipping_preference'] === 'NO_SHIPPING', 'Digital delivery needs no shipping');
    foreach (['7', '7.0', '7.00'] as $value) check(bc_money(['value' => $value, 'currency_code' => 'USD']), 'Valid exact amount ' . $value);
    foreach (['0.07', '6.99', '7.01', '7.000', '7e0', '007.00', '-7.00', '', 7, 7.0, null] as $value) check(!bc_money(['value' => $value, 'currency_code' => 'USD']), 'Malformed/incorrect amount rejected');
    check(!bc_money(['value' => '7.00', 'currency_code' => 'CAD']), 'Wrong currency');
    $remote = fixture($local);
    check(bc_validate_paid_order($remote, $local, 'MERCHANTTEST1') === 'CAPTURETEST12345', 'Valid order verified');
    foreach (['APPROVED', 'CREATED', 'PAYER_ACTION_REQUIRED', 'VOIDED', '', 'PENDING'] as $state) {
        $copy = $remote; $copy['status'] = $state;
        rejects(fn() => bc_validate_paid_order($copy, $local, 'MERCHANTTEST1'), 'Unpaid order status ' . $state, 409);
    }
    $copy = $remote; $copy['id'] = 'DIFFERENTORDER12';
    rejects(fn() => bc_validate_paid_order($copy, $local, 'MERCHANTTEST1'), 'Wrong order', 409);
    $copy = $remote; $copy['purchase_units'][0]['custom_id'] = str_repeat('b', 32);
    rejects(fn() => bc_validate_paid_order($copy, $local, 'MERCHANTTEST1'), 'Wrong local reference', 409);
    $copy = $remote; $copy['purchase_units'][0]['reference_id'] = 'different-product';
    rejects(fn() => bc_validate_paid_order($copy, $local, 'MERCHANTTEST1'), 'Wrong SKU', 409);
    $copy = $remote; $copy['purchase_units'][0]['payee']['merchant_id'] = 'WRONGMERCHANT';
    rejects(fn() => bc_validate_paid_order($copy, $local, 'MERCHANTTEST1'), 'Wrong merchant', 409);
    $copy = $remote; unset($copy['purchase_units'][0]['payee']);
    rejects(fn() => bc_validate_paid_order($copy, $local, 'MERCHANTTEST1'), 'Missing merchant', 409);
    $copy = $remote; $copy['purchase_units'][0]['amount']['value'] = '6.99';
    rejects(fn() => bc_validate_paid_order($copy, $local, 'MERCHANTTEST1'), 'Wrong order amount', 409);
    $copy = $remote; $copy['purchase_units'][] = $copy['purchase_units'][0];
    rejects(fn() => bc_validate_paid_order($copy, $local, 'MERCHANTTEST1'), 'Multiple purchase units', 409);
    $copy = $remote; $copy['purchase_units'][0]['payments']['captures'][] = $copy['purchase_units'][0]['payments']['captures'][0];
    rejects(fn() => bc_validate_paid_order($copy, $local, 'MERCHANTTEST1'), 'Multiple captures', 409);
    foreach (['PENDING', 'REFUNDED', 'PARTIALLY_REFUNDED', 'REVERSED', 'DECLINED', 'DENIED', 'FAILED', ''] as $state) {
        $copy = $remote; $copy['purchase_units'][0]['payments']['captures'][0]['status'] = $state;
        rejects(fn() => bc_validate_paid_order($copy, $local, 'MERCHANTTEST1'), 'Unsafe capture state ' . $state, 409);
    }
    $capture = $remote['purchase_units'][0]['payments']['captures'][0];
    check(bc_validate_capture($capture, 'CAPTURETEST12345') === 'CAPTURETEST12345', 'Known remote capture reconciles');
    rejects(fn() => bc_validate_capture($capture, 'OTHERREFERENCE1'), 'Wrong capture reference', 409);
    $copy = $capture; $copy['amount']['currency_code'] = 'EUR';
    rejects(fn() => bc_validate_capture($copy), 'Wrong capture currency', 409);
    $copy = $capture; $copy['amount']['value'] = '0.01';
    rejects(fn() => bc_validate_capture($copy), 'Wrong capture amount', 409);
    $copy = $capture; unset($copy['amount']);
    rejects(fn() => bc_validate_capture($copy), 'Missing capture amount', 409);

    $paid = bc_paid_transition($local, 'CAPTURETEST12345', time());
    check($paid['state'] === 'paid' && $paid['download_count'] === 0, 'Successful paid transition');
    check(bc_paid_transition($paid, 'CAPTURETEST12345', time() + 5000) === $paid, 'Duplicate capture does not extend grant');
    rejects(fn() => bc_paid_transition($paid, 'CHANGEDCAPTURE12', time()), 'Changed capture rejected', 409);
    $blocked = $paid; $blocked['state'] = 'blocked';
    rejects(fn() => bc_paid_transition($blocked, 'CAPTURETEST12345', time()), 'Refund cannot be undone by delayed completed event', 409);
    bc_download_eligible($paid, $token, time()); check(true, 'Paid token eligible');
    rejects(fn() => bc_download_eligible($local, $token, time()), 'Unpaid token rejected', 403);
    rejects(fn() => bc_download_eligible($blocked, $token, time()), 'Blocked token rejected', 403);
    rejects(fn() => bc_download_eligible($paid, str_repeat('c', 64), time()), 'Guessed token rejected', 403);
    rejects(fn() => bc_download_eligible($paid, $token, $paid['download_expires_at']), 'Exact expiry rejected', 403);
    $limit = $paid; $limit['download_count'] = BC_DOWNLOAD_LIMIT;
    rejects(fn() => bc_download_eligible($limit, $token, time()), 'Download limit enforced', 403);

    foreach (['https://www.sandbox.paypal.com/checkoutnow?token=ORDERTEST1234567', 'https://sandbox.paypal.com/checkoutnow?token=ORDERTEST1234567'] as $url) {
        check(bc_approval_url(['links' => [['rel' => 'payer-action', 'href' => $url]]], 'sandbox') === $url, 'Sandbox approval URL');
    }
    foreach (['http://www.paypal.com/x', 'https://paypal.com.evil.example/x', 'https://www.paypal.com@evil.example/x', 'https://evil.example/x', 'https://www.paypal.com:444/x', 'https://www.paypal.com/x#fragment', 'https://www.sandbox.paypal.com/x'] as $url) {
        rejects(fn() => bc_approval_url(['links' => [['rel' => 'approve', 'href' => $url]]], 'live'), 'Unapproved redirect');
    }
    rejects(fn() => bc_approval_url(['links' => [['rel' => 'self', 'href' => 'https://www.paypal.com/x']]], 'live'), 'Wrong link relation');

    $_SERVER['HTTP_ORIGIN'] = BC_ORIGIN; $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    $_SERVER['CONTENT_LENGTH'] = '100'; $_SESSION = ['csrf' => str_repeat('e', 64)]; $_POST = ['csrf' => str_repeat('e', 64)];
    bc_post(); check(true, 'Valid CSRF');
    $_POST['csrf'] = 'bad'; rejects(fn() => bc_post(), 'Invalid CSRF', 403);
    $_POST['csrf'] = ['array']; rejects(fn() => bc_post(), 'CSRF array rejected', 403);
    $_POST['csrf'] = $_SESSION['csrf']; $_SERVER['HTTP_ORIGIN'] = 'https://evil.example'; rejects(fn() => bc_post(), 'Cross-origin rejected', 403);
    $_SERVER['HTTP_ORIGIN'] = BC_ORIGIN; $_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site'; rejects(fn() => bc_post(), 'Cross-site rejected', 403);
    unset($_SERVER['HTTP_SEC_FETCH_SITE']); $_SERVER['CONTENT_LENGTH'] = '8193'; rejects(fn() => bc_post(), 'Oversized request', 400);
    $_SERVER['CONTENT_LENGTH'] = '100'; $_SERVER['CONTENT_TYPE'] = 'application/json'; rejects(fn() => bc_post(), 'Unexpected content type', 400);
    putenv('BOBSOME1_COMMERCE_CONFIG'); rejects(fn() => bc_config(), 'Checkout off without explicit config', 503);
    check(bc_manual_allowed([], []), 'Fresh disabled checkout retains manual sale path');
    check(!bc_manual_allowed(['bobsome1_checkout' => 'existing'], []), 'Existing session cannot show new manual payment CTA');
    check(!bc_manual_allowed([], ['order_id' => $local['id']]), 'Existing order cannot show new manual payment CTA');
    check(str_contains(bc_manual_content(), 'https://paypal.me/Bobsome1975/7USD'), 'Existing approved PayPal link preserved');
    check(str_contains(bc_manual_content(), 'Delivery is not instant yet.'), 'Manual fulfillment limitation remains explicit');
    check(bc_capture_may_charge($local, time()), 'Fresh approved checkout may capture');
    check(!bc_capture_may_charge($local, $local['checkout_expires_at']), 'Expired checkout is read-only reconciliation');
    $uncertain = $local; $uncertain['capture_attempted_at'] = time(); $uncertain['checkout_expires_at'] = time() - 1;
    check(!bc_capture_may_charge($uncertain, time()), 'Expired uncertain capture never charges again');
    rejects(fn() => bc_allow_new_order(['orders' => []], $uncertain, time()), 'Uncertain prior checkout cannot become a new charge', 409);
    rejects(fn() => bc_allow_new_order(['orders' => []], $local, time()), 'Existing checkout never silently replaced', 409);
    $rateLedger = ['orders' => array_fill(0, BC_HOURLY_CREATION_LIMIT, ['created_at' => time()])];
    rejects(fn() => bc_allow_new_order($rateLedger, null, time()), 'Global creation limit works across new sessions', 429);
    bc_allow_new_order(['orders' => [['created_at' => time() - 3601]]], null, time()); check(true, 'Old checkouts do not consume hourly budget');

    $privateFile = $temp . '/asset.pdf'; file_put_contents($privateFile, '%PDF-' . str_repeat('fixture ', 20)); chmod($privateFile, 0600);
    check(bc_private_path($privateFile, '/var/www/public') === $privateFile, 'Private file accepted');
    rejects(fn() => bc_private_path($privateFile, $temp), 'Public file rejected', 503);
    chmod($privateFile, 0644); rejects(fn() => bc_private_path($privateFile, '/var/www/public'), 'Readable-to-others asset rejected', 503); chmod($privateFile, 0600);
    if (function_exists('symlink')) {
        symlink($privateFile, $temp . '/link.pdf');
        rejects(fn() => bc_private_path($temp . '/link.pdf', '/var/www/public'), 'Asset symlink rejected', 503);
        unlink($temp . '/link.pdf');
    }

    bc_store($temp, static function (array &$ledger) use ($local): void { $ledger['orders'][$local['id']] = $local; });
    check((fileperms($temp . '/orders.json') & 0777) === 0600, 'Ledger permissions');
    check((fileperms($temp . '/orders.lock') & 0777) === 0600, 'Lock permissions');
    check(bc_order(['private_dir' => $temp], $local['id']) === $local, 'Order read roundtrip');
    rejects(fn() => bc_order(['private_dir' => $temp], '../arbitrary'), 'Order path injection rejected', 400);
    $contents = file_get_contents($temp . '/orders.json');
    rejects(fn() => bc_store($temp, static function (array &$ledger): void { $ledger['orders'] = []; throw new RuntimeException('Abort'); }), 'Failed mutation aborts');
    check(file_get_contents($temp . '/orders.json') === $contents, 'Aborted transaction leaves ledger unchanged');
    if (function_exists('symlink')) {
        rename($temp . '/orders.json', $temp . '/saved.json');
        symlink($privateFile, $temp . '/orders.json');
        rejects(fn() => bc_store($temp, static fn(array &$ledger) => null), 'Ledger symlink rejected', 503);
        check(str_starts_with(file_get_contents($privateFile), '%PDF-'), 'Symlink target untouched');
        unlink($temp . '/orders.json'); rename($temp . '/saved.json', $temp . '/orders.json');
    }
    file_put_contents($temp . '/orders.json', '{malformed');
    rejects(fn() => bc_store($temp, static fn(array &$ledger) => null), 'Corrupt ledger fails closed');
    file_put_contents($temp . '/orders.json', $contents);
    bc_store($temp, static function (array &$ledger) use ($token): void {
        $old = bc_new_order(str_repeat('b', 32), $token, time() - BC_ABANDONED_RETENTION - 1);
        $ledger['orders'][$old['id']] = $old;
        $oldPaid = bc_new_order(str_repeat('c', 32), $token, time() - BC_PAID_RETENTION - 20);
        $oldPaid['state'] = 'created';
        $oldPaid = bc_paid_transition($oldPaid, 'OLDCAPTURE123456', time() - BC_PAID_RETENTION - 1);
        $ledger['orders'][$oldPaid['id']] = $oldPaid;
    });
    $count = bc_store($temp, static fn(array &$ledger): int => count($ledger['orders']));
    check($count === 1, 'Expired pending and paid records pruned');

    if (function_exists('pcntl_fork')) {
        $children = [];
        for ($i = 0; $i < 4; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    for ($j = 0; $j < 5; $j++) bc_store($temp, static function (array &$ledger) use ($local): void { $ledger['orders'][$local['id']]['download_count']++; });
                    exit(0);
                } catch (Throwable $e) { exit(1); }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) { pcntl_waitpid($pid, $status); check(pcntl_wexitstatus($status) === 0, 'Concurrent store worker passed'); }
        check(bc_order(['private_dir' => $temp], $local['id'])['download_count'] === 20, 'Concurrent changes are not lost');
    }
    echo "Commerce backend: {$checks} checks passed (offline; no PayPal calls).\n";
} finally {
    // Only this exact, randomly named test directory and its known children.
    foreach (glob($temp . '/*') ?: [] as $path) if (is_file($path) || is_link($path)) unlink($path);
    foreach (glob($temp . '/.orders-*.tmp') ?: [] as $path) if (is_file($path)) unlink($path);
    if (is_dir($temp)) rmdir($temp);
}
