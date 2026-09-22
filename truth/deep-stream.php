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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405); header('Allow: POST'); exit('POST required.');
}

require_once __DIR__ . '/lib/trust-worthy-deep.php';

function tw_deep_stream_fail(int $status, string $message): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok'=>false,'message'=>$message], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

$contentLength=(string)($_SERVER['CONTENT_LENGTH']??'');
if($contentLength!==''&&(!ctype_digit($contentLength)||(int)$contentLength>30000)) tw_deep_stream_fail(413,'Submission was too large.');
$contentType=strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''),2)[0]));
if($contentType!=='application/x-www-form-urlencoded') tw_deep_stream_fail(415,'Form encoding was not accepted.');
try { tw_intake_enforce_raw_body_limit(30000); } catch (TwIntakeException $e) { tw_deep_stream_fail($e->httpStatus,$e->getMessage()); }

$hostValue=strtolower(rtrim((string)($_SERVER['HTTP_HOST']??''),'.'));
$host=preg_match('/^((?:www\.)?bobsome1\.com)(?::443)?$/',$hostValue,$hm)?$hm[1]:'';
$originValue=trim((string)($_SERVER['HTTP_ORIGIN']??''));
$originParts=$originValue!==''?parse_url($originValue):false;
$originHost=is_array($originParts)?strtolower((string)($originParts['host']??'')):'';
$originScheme=is_array($originParts)?strtolower((string)($originParts['scheme']??'')):'';
$originPort=is_array($originParts)?($originParts['port']??null):null;
$originHasExtra=is_array($originParts)&&(isset($originParts['user'])||isset($originParts['pass'])||isset($originParts['query'])||isset($originParts['fragment'])||!in_array((string)($originParts['path']??''),['','/'],true));
$fetchSite=strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'')));
if(!in_array($host,['bobsome1.com','www.bobsome1.com'],true)||$originHost!==$host||$originScheme!=='https'||($originPort!==null&&$originPort!==443)||$originHasExtra||($fetchSite!==''&&$fetchSite!=='same-origin')) tw_deep_stream_fail(403,'Request origin was not accepted.');

$website=$_POST['website']??'';
if(!is_string($website)||trim($website)!=='') tw_deep_stream_fail(422,'Submission was not accepted.');
$openedValue=$_POST['opened_at']??'';
$opened=is_string($openedValue)&&preg_match('/^\d{1,12}$/',$openedValue)?(int)$openedValue:0;
$age=time()-$opened;
if($opened<1||$age<4||$age>7200) tw_deep_stream_fail(422,'Please reload and review the claim before starting the investigation.');

[$valid,$question,$baseContext,$inputError]=tw_validate_trial_input($_POST['question']??null,$_POST['context']??'');
if(!$valid) tw_deep_stream_fail(422,$inputError);
$confirmedMap=$_POST['confirmed_map']??'';
if(!is_string($confirmedMap)||trim($confirmedMap)==='') tw_deep_stream_fail(422,'Confirm the claim map before research begins.');
$confirmedMap=trim($confirmedMap);
if(mb_strlen($confirmedMap,'UTF-8')>(int)tw_deep_config()['max_confirmed_map_characters']) tw_deep_stream_fail(413,'Confirmed claim map was too large.');
$authorization=$_POST['authorization_token']??'';
if(!is_string($authorization)||trim($authorization)==='') tw_deep_stream_fail(403,'Deep research authorization was missing. Rebuild the claim map.');
$authorization=trim($authorization);
$goals=$_POST['goals']??'';
if(!is_string($goals)) tw_deep_stream_fail(422,'Investigation goals were invalid.');
$goals=mb_substr(trim($goals),0,1000,'UTF-8');

$secret=tw_question_secret();
if($secret==='') tw_deep_stream_fail(503,'Private research storage is unavailable.');
$remote=(string)($_SERVER['REMOTE_ADDR']??'');
if(filter_var($remote,FILTER_VALIDATE_IP)===false) tw_deep_stream_fail(403,'Request address could not be validated.');
$ipHash=hash_hmac('sha256','ai-ip|'.$remote,$secret);
if(!tw_deep_consume_authorization($authorization,$ipHash,$question,$baseContext,$secret)) {
    tw_deep_stream_fail(403,'Deep research authorization was expired, already used, or did not match this claim. Rebuild the claim map.');
}

$context=$baseContext;
if($goals!=='') $context=trim($context."\n\nUSER INVESTIGATION GOALS:\n".$goals);

header('Content-Type: application/x-ndjson; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'");
@ini_set('output_buffering','off');
@ini_set('zlib.output_compression','0');
while (ob_get_level() > 0) @ob_end_flush();

$emit=static function(array $event): void {
    echo json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
    @flush();
};

$emit(['type'=>'start','label'=>'Confirmed deep investigation started','at_utc'=>gmdate('c')]);
$result=tw_deep_run($question,$context,static function(array $receipt) use ($emit): void {
    $emit(['type'=>'receipt','receipt'=>$receipt]);
},$confirmedMap);

if(!($result['ok']??false)) {
    $emit(['type'=>'error','message'=>(string)($result['message']??'Deep investigation failed.'),'stage'=>$result['stage']??null]);
    exit;
}

$emit(['type'=>'complete','result'=>$result]);
