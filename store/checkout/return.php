<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
try {
    $config = bc_boot('GET');
    $order = bc_session_order($config);
    $remoteId = $_GET['token'] ?? null;
    if (!is_string($remoteId) || $remoteId !== $order['paypal_order_id']) bc_fail('The PayPal return does not match this browser checkout.', 400);
    if ($order['state'] === 'paid') bc_success($order);
    if ($order['state'] !== 'created') bc_fail('This checkout needs owner verification. If you paid, contact purchase support.', 409);
    $reconcileOnly = $order['checkout_expires_at'] <= time();
    $notice = $config['mode'] === 'sandbox' ? '<p class="notice">Sandbox test: no live purchase.</p>' : '';
    $title = $reconcileOnly ? 'Verify your existing payment' : 'Confirm your $7 purchase';
    $button = $reconcileOnly ? 'Check my existing payment' : 'Pay $7 USD and unlock my PDF';
    $explanation = $reconcileOnly ? '<p>This checkout has expired for new charges. We can check PayPal for an existing completed payment without charging again.</p>' : '<p>PayPal returned you to Bobsome1. Your download is not unlocked until the payment is captured and verified.</p>';
    bc_page($title, $notice . $explanation . '<form action="capture.php" method="post"><input type="hidden" name="csrf" value="' . bc_h((string)$_SESSION['csrf']) . '"><button type="submit">' . bc_h($button) . '</button></form><p class="small">Click once. If confirmation is interrupted, you may retry this same action; the same payment request is reused.</p>');
} catch (Throwable $error) { bc_error($error); }
