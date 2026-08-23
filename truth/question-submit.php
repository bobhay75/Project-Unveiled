<?php
declare(strict_types=1);
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function out(bool $ok,string $message,int $code=200): never {
  http_response_code($code);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode(['ok'=>$ok,'message'=>$message],JSON_UNESCAPED_SLASHES);
  exit;
}
function received(): never {
  header('Location: /truth/question-received.php',true,303);
  exit;
}
function clean(string $value,int $limit): string {
  $value=trim(preg_replace('/\s+/u',' ',$value)??'');
  return mb_substr($value,0,$limit);
}

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') out(false,'POST required.',405);
$host=strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??''))??'');
$origin=strtolower((string)parse_url((string)($_SERVER['HTTP_ORIGIN']??''),PHP_URL_HOST));
if(!in_array($host,['bobsome1.com','www.bobsome1.com'],true)||($origin!==''&&!in_array($origin,['bobsome1.com','www.bobsome1.com'],true))) out(false,'Request origin was not accepted.',403);
if(trim((string)($_POST['website']??''))!=='') out(true,'Thank you.');
$opened=(int)($_POST['opened_at']??0);
if($opened<1||time()-$opened<4) out(false,'Please take a few seconds to review your question, then try again.',422);

$topic=clean((string)($_POST['topic']??''),64);
$question=clean((string)($_POST['question']??''),3000);
$context=clean((string)($_POST['context']??''),2000);
$name=clean((string)($_POST['name']??''),100);
$email=clean((string)($_POST['email']??''),190);

$topics=['jesus','doctrine','history','science','current-events','other'];
if(!in_array($topic,$topics,true)) out(false,'Choose a valid topic.',422);
if(mb_strlen($question)<20) out(false,'Please state the question in enough detail to investigate.',422);
if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) out(false,'Enter a valid email address or leave it blank.',422);

$dir=dirname(__DIR__,2).'/site-private/trust-worthy';
if(!is_dir($dir)&&!mkdir($dir,0750,true)) out(false,'Private storage is unavailable.',500);
$file=$dir.'/questions.json';
$items=is_file($file)?json_decode((string)file_get_contents($file),true):[];
if(!is_array($items)) $items=[];
$secretFile=$dir.'/question-secret.txt';
if(!is_file($secretFile)){file_put_contents($secretFile,bin2hex(random_bytes(32)),LOCK_EX);@chmod($secretFile,0640);}
$ipHash=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),trim((string)file_get_contents($secretFile)));
$items[]=[
  'id'=>bin2hex(random_bytes(8)),
  'topic'=>$topic,
  'question'=>$question,
  'context'=>$context,
  'name'=>$name,
  'email'=>$email,
  'status'=>'submitted',
  'submitted_at_utc'=>gmdate('c'),
  'ip_hash'=>$ipHash
];
$tmp=$file.'.tmp-'.bin2hex(random_bytes(4));
if(file_put_contents($tmp,json_encode($items,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX)===false||!rename($tmp,$file)){
  @unlink($tmp);
  out(false,'Your question could not be saved. Please try again.',500);
}
@chmod($file,0640);
received();
