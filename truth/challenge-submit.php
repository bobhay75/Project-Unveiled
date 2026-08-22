<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function out(bool $ok,string $message,int $code=200): never {
  http_response_code($code);
  echo json_encode(['ok'=>$ok,'message'=>$message],JSON_UNESCAPED_SLASHES);
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
if($opened<1||time()-$opened<4) out(false,'Please take a few seconds to review your challenge, then try again.',422);

$caseId=clean((string)($_POST['case_id']??''),64);
$type=clean((string)($_POST['challenge_type']??''),64);
$name=clean((string)($_POST['name']??''),100);
$email=clean((string)($_POST['email']??''),190);
$argument=clean((string)($_POST['argument']??''),6000);
$source=clean((string)($_POST['source']??''),2000);

$allowed=['source','date','translation','context','logic','counterevidence','assumption','other'];
if(!preg_match('/^TW-CLAIM-\d{6}$/',$caseId)) out(false,'Unknown case.',422);
if(!in_array($type,$allowed,true)) out(false,'Choose a valid challenge type.',422);
if(mb_strlen($argument)<20) out(false,'Please explain the challenge in enough detail to investigate it.',422);
if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) out(false,'Enter a valid email address or leave it blank.',422);

$dir=dirname(__DIR__,2).'/site-private/trust-worthy';
if(!is_dir($dir)&&!mkdir($dir,0750,true)) out(false,'Private storage is unavailable.',500);
$file=$dir.'/challenges.json';
$items=is_file($file)?json_decode((string)file_get_contents($file),true):[];
if(!is_array($items)) $items=[];
$secretFile=$dir.'/challenge-secret.txt';
if(!is_file($secretFile)){file_put_contents($secretFile,bin2hex(random_bytes(32)),LOCK_EX);@chmod($secretFile,0640);}
$ipHash=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),trim((string)file_get_contents($secretFile)));
$items[]=[
  'id'=>bin2hex(random_bytes(8)),
  'case_id'=>$caseId,
  'challenge_type'=>$type,
  'name'=>$name,
  'email'=>$email,
  'argument'=>$argument,
  'source'=>$source,
  'status'=>'pending',
  'submitted_at_utc'=>gmdate('c'),
  'ip_hash'=>$ipHash
];
$tmp=$file.'.tmp-'.bin2hex(random_bytes(4));
if(file_put_contents($tmp,json_encode($items,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX)===false||!rename($tmp,$file)){
  @unlink($tmp);
  out(false,'Your challenge could not be saved. Please try again.',500);
}
@chmod($file,0640);
out(true,'Challenge received. Trust-Worthy will treat it as evidence to investigate, not as a comment to defeat.');