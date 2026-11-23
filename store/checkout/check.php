<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
try {
    $config = bc_boot('POST');
    bc_post();
    $order = bc_session_order($config);
    $now = time();
    if ($now - (int)($_SESSION['last_check_at'] ?? 0) < 5) {
        bc_fail('Please wait a moment before checking the same payment again.', 429);
    }
    $_SESSION['last_check_at'] = $now;
    // This endpoint has no order-creation or payment-capture operation.
    $paid = bc_reconcile_order($config, $order);
    bc_success($paid);
} catch (Throwable $error) { bc_error($error); }
