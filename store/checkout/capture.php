<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
try {
    $config = bc_boot('POST');
    bc_post();
    $order = bc_session_order($config);
    if ($order['state'] === 'paid') bc_success($order);
    $mayCharge = bc_capture_may_charge($order, time());
    // A timeout may follow a successful capture. Reconcile through PayPal below;
    // never create a fresh order or idempotency key to resolve that ambiguity.
    if ($mayCharge) {
        bc_store($config['private_dir'], static function (array &$ledger) use ($order): void {
            $ledger['orders'][$order['id']]['capture_attempted_at'] ??= time();
        });
        try {
            bc_api($config, 'POST', '/v2/checkout/orders/' . $order['paypal_order_id'] . '/capture', null, $order['capture_request_id']);
        } catch (BcException $captureError) {
            // The authoritative GET below determines whether delivery is allowed.
        }
    }
    $paid = bc_reconcile_order($config, $order);
    bc_success($paid);
} catch (Throwable $error) { bc_error($error); }
