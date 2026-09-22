<?php
declare(strict_types=1);
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
$secret=tw_question_secret();
$case=$secret!==''?tw_funnel_consume_resume($caseId,$resume,$secret):null;
if(!is_array($case)||($case['state']??'')!=='resume_authorized'){http_response_code(403);exit('Paid continuation authorization was invalid, expired, or already used.');}

header('Content-Type: application/x-ndjson; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'");
@ini_set('output_buffering','off');@ini_set('zlib.output_compression','0');while(ob_get_level()>0)@ob_end_flush();
$emit=static function(array $event):void{echo json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";@flush();};
$emit(['type'=>'start','label'=>'Paid continuation authorized — resuming stored evidence graph','at_utc'=>gmdate('c')]);
$result=tw_funnel_finish_run($caseId,static function(array $receipt)use($emit):void{$emit(['type'=>'receipt','receipt'=>$receipt]);});
if(!($result['ok']??false)){$emit(['type'=>'error','message'=>(string)($result['message']??'Paid continuation failed.'),'stage'=>$result['stage']??null]);exit;}
$emit(['type'=>'complete','result'=>$result]);
