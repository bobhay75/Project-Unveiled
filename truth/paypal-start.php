<?php
declare(strict_types=1);
header_remove('X-Powered-By');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('POST required.'); }
require_once __DIR__ . '/lib/trust-worthy-paid.php';

$contentType=strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''),2)[0]));
if($contentType!=='application/x-www-form-urlencoded'){http_response_code(415);exit('Form encoding required.');}
$host=strtolower(rtrim((string)($_SERVER['HTTP_HOST']??''),'.'));
$origin=trim((string)($_SERVER['HTTP_ORIGIN']??''));
$parts=$origin!==''?parse_url($origin):false;
if(!in_array($host,['bobsome1.com','www.bobsome1.com'],true)||!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||strtolower((string)($parts['host']??''))!==$host){http_response_code(403);exit('Request origin was not accepted.');}
if(!tw_paid_ready()){http_response_code(503);exit('Paid continuation is not configured.');}
$caseId=is_string($_POST['case_id']??null)?trim((string)$_POST['case_id']):'';
$state=is_string($_POST['checkout_state']??null)?trim((string)$_POST['checkout_state']):'';
$secret=tw_question_secret();
$verified=$secret!==''?tw_paid_verify_state($state,'checkout',$secret):null;
if(!is_array($verified)||($verified['case_id']??'')!==$caseId){http_response_code(403);exit('Checkout authorization was invalid or expired.');}
$order=tw_paid_create_paypal_order($caseId,$state);
if(!($order['ok']??false)){http_response_code(502);exit(htmlspecialchars((string)($order['message']??'Payment handoff failed.'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));}
header('Location: '.(string)$order['approval_url'],true,303);
exit;
