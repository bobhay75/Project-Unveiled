<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
try {
    $config = bc_boot('POST');
    bc_post();
    $now = time();
    if ($now - (int)($_SESSION['last_start_at'] ?? 0) < 5) bc_fail('Please wait a moment before retrying checkout.', 429);
    $_SESSION['last_start_at'] = $now;
    $order = isset($_SESSION['order_id']) ? bc_session_order($config) : null;
    if (is_array($order) && $order['state'] === 'paid') bc_success($order);
    if (is_array($order) && ($order['checkout_expires_at'] <= $now || !in_array($order['state'], ['creating', 'created'], true))) {
        bc_allow_new_order(['orders' => []], $order, $now); // Always rejects replacement.
    }
    if (is_array($order) && is_int($order['capture_attempted_at'] ?? null)) {
        header('Location: ' . BC_BASE . 'return.php?token=' . rawurlencode($order['paypal_order_id']), true, 303);
        exit;
    }
    if (!is_array($order)) {
        $id = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $order = bc_new_order($id, $token, $now);
        bc_store($config['private_dir'], static function (array &$ledger) use ($id, $order): void {
            bc_allow_new_order($ledger, null, time());
            if (count($ledger['orders']) >= BC_MAX_RECORDS) bc_fail('Checkout capacity needs an owner review.');
            $ledger['orders'][$id] = $order;
        });
        $_SESSION['order_id'] = $id;
        $_SESSION['download_token'] = $token;
    }
    if ($order['state'] === 'created') {
        $url = bc_approval_url(['links' => [['rel' => 'approve', 'href' => $order['approval_url']]]], $config['mode']);
    } else {
        $response = bc_api($config, 'POST', '/v2/checkout/orders', bc_create_payload($config, $order), $order['create_request_id']);
        if (!is_string($response['id'] ?? null) || !bc_paypal_id($response['id'])) bc_fail();
        $url = bc_approval_url($response, $config['mode']);
        bc_store($config['private_dir'], static function (array &$ledger) use ($order, $response, $url): void {
            $stored = &$ledger['orders'][$order['id']];
            if ($stored['state'] !== 'creating') bc_fail('Checkout changed. Reload the page.', 409);
            $stored['paypal_order_id'] = $response['id'];
            $stored['approval_url'] = $url;
            $stored['state'] = 'created';
        });
    }
    session_write_close();
    header('Location: ' . $url, true, 303);
    exit;
} catch (Throwable $error) { bc_error($error); }
