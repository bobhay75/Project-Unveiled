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
    $remote = bc_api($config, 'GET', '/v2/checkout/orders/' . $order['paypal_order_id']);
    $captureId = bc_validate_paid_order($remote, $order, $config['merchant_id']);
    $paid = bc_store($config['private_dir'], static function (array &$ledger) use ($order, $captureId): array {
        foreach ($ledger['orders'] as $id => $existing) {
            if ($id !== $order['id'] && ($existing['capture_id'] ?? null) === $captureId) bc_fail('This payment has already been allocated.', 409);
        }
        $ledger['orders'][$order['id']] = bc_paid_transition($ledger['orders'][$order['id']], $captureId, time());
        return $ledger['orders'][$order['id']];
    });
    bc_success($paid);
} catch (Throwable $error) { bc_error($error); }
