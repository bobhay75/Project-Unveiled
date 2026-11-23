<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/trust-worthy-guided-access.php';
tw_guided_require_enabled();
header_remove('X-Powered-By');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
tw_guided_post_gate(12000);
require_once __DIR__ . '/lib/trust-worthy-paid.php';
$caseId = is_string($_POST['case_id'] ?? null) ? trim($_POST['case_id']) : '';
$token = is_string($_POST['recovery_token'] ?? null) ? trim($_POST['recovery_token']) : '';
$secret = tw_question_secret_readonly();
$case = tw_paid_recovery_case($caseId, $token, $secret);
if (!is_array($case)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'The private recovery receipt is invalid, expired, or belongs to another investigation. Keep your PayPal receipt and contact Bobsome1; do not pay again.']);
    exit;
}
$result = tw_paid_recovery_snapshot($case, $secret);
if (!($result['ok'] ?? false)) http_response_code(409);
echo json_encode($result, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
