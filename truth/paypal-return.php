<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/trust-worthy-guided-access.php';
tw_guided_require_enabled();
header_remove('X-Powered-By');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); header('Allow: GET'); exit('GET required.'); }
require_once __DIR__ . '/lib/trust-worthy-paid.php';

$state=is_string($_GET['state']??null)?trim((string)$_GET['state']):'';
$secret=tw_question_secret_readonly();
$verified=$secret!==''?tw_paid_verify_state($state,'checkout',$secret):null;
$orderId=is_string($_GET['token']??null)?trim((string)$_GET['token']):'';
$renderRecovery=static function(array $record) use($secret): never {
    $caseId=(string)$record['case_id'];
    $recovery=tw_paid_sign_recovery($record,$secret);
    // A cross-site PayPal navigation can remain cross-site through a redirect.
    // This explicit form starts a new same-origin navigation instead, preserving
    // the continuation page's strict origin checks without another payment call.
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment already recorded</title><link rel="stylesheet" href="/truth/investigation.css"></head><body><main class="shell"><section class="panel intro"><div class="kicker">SAVED PAID INVESTIGATION</div><h1>Recover your investigation</h1><p class="muted">Your exact payment is already recorded. Continue to read the saved result or check the same investigation's status. No payment has been captured again and no research has been started by this recovery.</p><form id="paidRecoveryHandoff" method="post" action="/truth/paid-continue.php"><input type="hidden" name="case_id" value="<?=htmlspecialchars($caseId,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><input type="hidden" name="recovery_token" value="<?=htmlspecialchars($recovery,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><button class="primary" type="submit">OPEN SAVED INVESTIGATION →</button></form></section></main></body></html><?php
    exit;
};
// Recovery may use an expired checkout capability only while its paid record is
// still valid, and only for the exact recorded order. This branch never captures.
$recovered=$secret!==''?tw_paid_recover_checkout($state,$orderId,$secret):null;
if (is_array($recovered)) $renderRecovery($recovered);
if(!is_array($verified)){http_response_code(403);exit('Checkout state was invalid or expired. If payment was recorded, keep your PayPal receipt and private recovery link; do not pay again.');}
$caseId=(string)$verified['case_id'];
$cancel=((string)($_GET['cancel']??''))==='1';
if($cancel){
    $existing=tw_paid_load_case($caseId);
    if(is_array($existing)&&tw_paid_recovery_eligible($existing)){
        http_response_code(409);exit('Payment is already recorded for this investigation. Use your private recovery link or contact Bobsome1 with your case reference and PayPal receipt. Do not pay again.');
    }
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment cancelled</title><link rel="stylesheet" href="/truth/investigation.css"></head><body><main class="shell"><section class="panel intro"><div class="kicker">UNVEILING THE TRUTH</div><h1>Payment cancelled</h1><p class="muted">No paid continuation was unlocked. Your investigation state remains private until it expires.</p><a class="secondary-action" href="/truth/">Return to Trust-Worthy</a></section></main></body></html><?php
    exit;
}
if(!tw_paid_ready()){http_response_code(503);exit('Paid continuation is not configured.');}
$case=tw_paid_load_case($caseId);
if(!is_array($case)||($case['state']??'')!=='awaiting_payment'){
    http_response_code(409);
    exit('This return does not match an active checkout. Use your private recovery link or contact Bobsome1; do not pay again.');
}
$result=tw_paid_capture_paypal_order($caseId,$orderId);
if(!($result['ok']??false)){
    // A concurrent return may have completed the exact same capture meanwhile.
    $recovered=tw_paid_recover_checkout($state,$orderId,$secret);
    if(is_array($recovered)) $renderRecovery($recovered);
    http_response_code(402);exit(htmlspecialchars((string)($result['message']??'Payment could not be verified.'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
}
$record=tw_paid_load_case($caseId);
$recovery=is_array($record)?tw_paid_sign_recovery($record,$secret):'';
$resume=(string)$result['resume_token'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment verified</title><link rel="stylesheet" href="/truth/investigation.css"></head><body><main class="shell"><section class="panel intro"><div class="kicker">PAYMENT VERIFIED</div><h1>Continue Unveiling the Truth</h1><p class="muted">PayPal confirmed the exact $2.99 payment for this investigation. Continue below to resume the same evidence graph where the free phase stopped.</p><form method="post" action="/truth/paid-continue.php"><input type="hidden" name="case_id" value="<?=htmlspecialchars($caseId,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><input type="hidden" name="resume_token" value="<?=htmlspecialchars($resume,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><input type="hidden" name="recovery_token" value="<?=htmlspecialchars($recovery,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><button class="primary" type="submit">CONTINUE UNVEILING THE TRUTH →</button></form></section></main></body></html>
