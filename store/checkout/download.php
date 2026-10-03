<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
try {
    $config = bc_boot('POST');
    bc_post();
    $order = bc_session_order($config);
    $token = $_POST['token'] ?? null;
    if (!is_string($token)) bc_fail('Invalid download request.', 403);
    bc_download_eligible($order, $token, time());
    if (!bc_paypal_id((string)$order['capture_id'])) bc_fail();
    $file = bc_open_delivery_file($config);
    try {
        // Check the authoritative capture EACH time: refunded, partially refunded,
        // reversed, pending, malformed, or unreachable payment evidence fails closed.
        $capture = bc_api($config, 'GET', '/v2/payments/captures/' . $order['capture_id']);
        try {
            bc_validate_capture($capture, $order['capture_id']);
        } catch (BcException $verificationError) {
            if (in_array($capture['status'] ?? '', ['REFUNDED', 'PARTIALLY_REFUNDED', 'REVERSED', 'DECLINED', 'DENIED', 'FAILED'], true)) {
                bc_store($config['private_dir'], static function (array &$ledger) use ($order): void {
                    $ledger['orders'][$order['id']]['state'] = 'blocked';
                });
            }
            throw $verificationError;
        }
        bc_store($config['private_dir'], static function (array &$ledger) use ($config, $order, $token): void {
            bc_require_order_asset($config, $ledger['orders'][$order['id']]);
            bc_download_eligible($ledger['orders'][$order['id']], $token, time());
            $ledger['orders'][$order['id']]['download_count']++;
        });
        session_write_close();
        foreach (bc_download_headers($config) as $header) header($header);
        fpassthru($file);
    } finally { fclose($file); }
    exit;
} catch (Throwable $error) { bc_error($error); }
