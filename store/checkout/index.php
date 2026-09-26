<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
$booted = false;
try {
    $config = bc_boot('GET');
    $booted = true;
    $notice = $config['mode'] === 'sandbox' ? '<p class="notice">Sandbox test checkout. This is not a live purchase.</p>' : '';
    if (($_GET['cancelled'] ?? '') === '1') $notice .= '<p class="notice">You returned without completing this checkout. If PayPal shows a charge, contact support before paying again.</p>';
    if (isset($_SESSION['order_id'])) {
        $current = bc_session_order($config);
        if ($current['state'] === 'paid') bc_success($current);
    }
    bc_page('Project Unveiled digital edition', $notice . '<p>Get the complete, owner-approved PDF edition to save and read on your own device.</p><p><strong>$7 USD · one-time purchase · no subscription</strong></p><p>Approve the purchase securely with PayPal, return here, then confirm payment to unlock your download. Card details are handled by PayPal, not this website.</p><form action="start.php" method="post"><input type="hidden" name="csrf" value="' . bc_h((string)$_SESSION['csrf']) . '"><button type="submit">Continue to PayPal — $7 USD</button></form><p class="small">After verified payment, your download is available in this browser session for 24 hours, with up to 10 download attempts. Save it to your device. If delivery fails, contact purchase support using your PayPal receipt.</p><p><a href="/book/">Read online before buying</a></p>');
} catch (Throwable $error) {
    if (!$booted && $error instanceof BcException && $error->httpStatus === 503
        && bc_manual_allowed($_COOKIE, $_SESSION ?? [])) {
        bc_page('Get the digital edition', bc_manual_content());
    }
    bc_error($error);
}
