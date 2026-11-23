<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/trust-worthy-guided-access.php';
tw_guided_require_enabled();
tw_guided_post_gate(30000);
header_remove('X-Powered-By');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');exit('POST required.');}
require_once __DIR__.'/lib/trust-worthy-funnel-v1.php';

$contentLength=(string)($_SERVER['CONTENT_LENGTH']??'');
if($contentLength!==''&&(!ctype_digit($contentLength)||(int)$contentLength>12000)){http_response_code(413);exit('Submission too large.');}
$contentType=strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''),2)[0]));
if($contentType!=='application/x-www-form-urlencoded'){http_response_code(415);exit('Form encoding required.');}
$host=strtolower(rtrim((string)($_SERVER['HTTP_HOST']??''),'.'));
$origin=trim((string)($_SERVER['HTTP_ORIGIN']??''));
$parts=$origin!==''?parse_url($origin):false;
$fetchSite=strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'')));
if(!in_array($host,['bobsome1.com','www.bobsome1.com'],true)||!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||strtolower((string)($parts['host']??''))!==$host||($fetchSite!==''&&$fetchSite!=='same-origin')){http_response_code(403);exit('Request origin was not accepted.');}
$caseId=is_string($_POST['case_id']??null)?trim((string)$_POST['case_id']):'';
$resume=is_string($_POST['resume_token']??null)?trim((string)$_POST['resume_token']):'';
$recovery=is_string($_POST['recovery_token']??null)?trim((string)$_POST['recovery_token']):'';
$secret=tw_question_secret();
if($recovery!==''&&tw_paid_recovery_case($caseId,$recovery,$secret)===null){http_response_code(403);exit('The private recovery receipt did not match this paid investigation.');}
$resumeProof=$secret!==''?tw_paid_verify_state($resume,'resume',$secret):null;
$existing=is_array($resumeProof)&&($resumeProof['case_id']??'')===$caseId?tw_paid_load_case($caseId,true):null;
if(!is_array($existing)||($existing['state']??'')!=='paid'||($existing['resume_consumed_at']??null)!==null){http_response_code(403);exit('Continuation was already used or is unavailable. Check saved status through the private recovery link; do not pay again.');}
tw_guided_require_provider_access();
$case=$secret!==''?tw_funnel_consume_resume($caseId,$resume,$secret):null;
if(!is_array($case)||($case['state']??'')!=='resume_authorized'){http_response_code(403);exit('Paid continuation authorization was invalid, expired, or already used.');}

// A reader closing their tab must not silently abandon an already paid job.
ignore_user_abort(true);
register_shutdown_function(static function() use ($caseId): void { tw_paid_mark_failed($caseId, 'interrupted'); });

header('Content-Type: application/x-ndjson; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'");
@ini_set('output_buffering','off');@ini_set('zlib.output_compression','0');while(ob_get_level()>0)@ob_end_flush();
$emit=static function(array $event):void{echo json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";@flush();};
$emit(['type'=>'start','label'=>'Paid continuation authorized — resuming stored evidence graph','at_utc'=>gmdate('c')]);
try {
    $result=tw_funnel_finish_run($caseId,static function(array $receipt)use($emit):void{$emit(['type'=>'receipt','receipt'=>$receipt]);});
} catch (Throwable $error) {
    tw_paid_mark_failed($caseId, 'interrupted');
    $result=['ok'=>false,'message'=>'Paid continuation stopped. Keep your case reference and PayPal receipt, and contact Bobsome1 for recovery or refund review. Do not pay again for this investigation.','stage'=>'interrupted'];
}
if(!($result['ok']??false)){$emit(['type'=>'error','message'=>(string)($result['message']??'Paid continuation failed.'),'stage'=>$result['stage']??null]);exit;}
$emit(['type'=>'complete','result'=>$result]);
