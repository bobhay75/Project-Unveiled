<?php
declare(strict_types=1);
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/lib/trust-worthy-queue.php';

function out(bool $ok,string $message,int $code=200): never { http_response_code($code); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>$ok,'message'=>$message],JSON_UNESCAPED_SLASHES); exit; }
function received(): never { header('Location: /truth/question-received.php',true,303); exit; }

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') out(false,'POST required.',405);
$host=strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??''))??'');
$origin=strtolower((string)parse_url((string)($_SERVER['HTTP_ORIGIN']??''),PHP_URL_HOST));
if(!in_array($host,['bobsome1.com','www.bobsome1.com'],true)||($origin!==''&&!in_array($origin,['bobsome1.com','www.bobsome1.com'],true))) out(false,'Request origin was not accepted.',403);
if(trim((string)($_POST['website']??''))!=='') received();
$opened=(int)($_POST['opened_at']??0);
if($opened<1||time()-$opened<4) out(false,'Please take a few seconds to review your question, then try again.',422);
$topic=tw_clean((string)($_POST['topic']??'other'),64); $question=tw_clean((string)($_POST['question']??''),3000); $email=tw_clean((string)($_POST['email']??''),190);
if(!in_array($topic,['news','propaganda','jesus','doctrine','history','science','current-events','other'],true)) out(false,'Choose a valid topic.',422);
if(mb_strlen($question)<20) out(false,'Please state the question in enough detail to investigate.',422);
if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) out(false,'Enter a valid email address or leave it blank.',422);
$result=tw_enqueue_question($_POST,(string)($_SERVER['REMOTE_ADDR']??''));
if(!($result['ok']??false)) out(false,(string)($result['message']??'Your question could not be saved.'),500);
received();
