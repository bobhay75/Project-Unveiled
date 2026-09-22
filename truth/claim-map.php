<?php
declare(strict_types=1);

header_remove('X-Powered-By');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'none'");

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405); header('Allow: POST'); exit('POST required.');
}

require_once __DIR__ . '/lib/trust-worthy-deep.php';

function tw_claim_map_fail(int $status,string $message): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok'=>false,'message'=>$message],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

$contentLength=(string)($_SERVER['CONTENT_LENGTH']??'');
if($contentLength!==''&&(!ctype_digit($contentLength)||(int)$contentLength>18000)) tw_claim_map_fail(413,'Submission was too large.');
$contentType=strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''),2)[0]));
if($contentType!=='application/x-www-form-urlencoded') tw_claim_map_fail(415,'Form encoding was not accepted.');
try { tw_intake_enforce_raw_body_limit(18000); } catch (TwIntakeException $e) { tw_claim_map_fail($e->httpStatus,$e->getMessage()); }

$hostValue=strtolower(rtrim((string)($_SERVER['HTTP_HOST']??''),'.'));
$host=preg_match('/^((?:www\.)?bobsome1\.com)(?::443)?$/',$hostValue,$hm)?$hm[1]:'';
$originValue=trim((string)($_SERVER['HTTP_ORIGIN']??''));
$originParts=$originValue!==''?parse_url($originValue):false;
$originHost=is_array($originParts)?strtolower((string)($originParts['host']??'')):'';
$originScheme=is_array($originParts)?strtolower((string)($originParts['scheme']??'')):'';
$originPort=is_array($originParts)?($originParts['port']??null):null;
$originHasExtra=is_array($originParts)&&(isset($originParts['user'])||isset($originParts['pass'])||isset($originParts['query'])||isset($originParts['fragment'])||!in_array((string)($originParts['path']??''),['','/'],true));
$fetchSite=strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'')));
if(!in_array($host,['bobsome1.com','www.bobsome1.com'],true)||$originHost!==$host||$originScheme!=='https'||($originPort!==null&&$originPort!==443)||$originHasExtra||($fetchSite!==''&&$fetchSite!=='same-origin')) tw_claim_map_fail(403,'Request origin was not accepted.');

$website=$_POST['website']??'';
if(!is_string($website)||trim($website)!=='') tw_claim_map_fail(422,'Submission was not accepted.');
$openedValue=$_POST['opened_at']??'';
$opened=is_string($openedValue)&&preg_match('/^\d{1,12}$/',$openedValue)?(int)$openedValue:0;
$age=time()-$opened;
if($opened<1||$age<4||$age>7200) tw_claim_map_fail(422,'Please reload and review the claim before starting the investigation.');

[$valid,$question,$context,$inputError]=tw_validate_trial_input($_POST['question']??null,$_POST['context']??'');
if(!$valid) tw_claim_map_fail(422,$inputError);

$secret=tw_question_secret();
if($secret==='') tw_claim_map_fail(503,'Private research storage is unavailable.');
$remote=(string)($_SERVER['REMOTE_ADDR']??'');
if(filter_var($remote,FILTER_VALIDATE_IP)===false) tw_claim_map_fail(403,'Request address could not be validated.');
$ipHash=hash_hmac('sha256','ai-ip|'.$remote,$secret);
[$allowed,$reason,$reservation,$rateStatus]=tw_rate_limit($ipHash);
if(!$allowed) {
    if($rateStatus==='allowance_exhausted') header('Retry-After: 3600');
    tw_claim_map_fail($rateStatus==='allowance_exhausted'?429:503,$reason);
}

$map=tw_deep_claim_map($question,$context);
if(!($map['ok']??false)) {
    tw_finalize_rate_limit($ipHash,$reservation,(bool)($map['quota_consumed']??true));
    tw_claim_map_fail(503,(string)($map['message']??'Claim analysis failed.'));
}

$token=tw_deep_issue_authorization($ipHash,$question,$context,$secret);
if($token==='') {
    tw_finalize_rate_limit($ipHash,$reservation,true);
    tw_claim_map_fail(503,'Claim analysis completed, but a secure one-time research authorization could not be issued.');
}
if(!tw_finalize_rate_limit($ipHash,$reservation,true)) {
    tw_claim_map_fail(503,'Claim analysis completed, but the research allowance could not be finalized safely.');
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'ok'=>true,
    'claim_map'=>(string)$map['text'],
    'authorization_token'=>$token,
    'expires_in'=>(int)tw_deep_config()['authorization_ttl_seconds'],
    'message'=>'Review and edit the claim map before research begins.',
],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
