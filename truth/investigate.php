<?php
declare(strict_types=1);
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/lib/trust-worthy-ai.php';

function page(string $title,string $body): never {
  http_response_code(200);
  ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($title)?> | Truth on Trial</title><link rel="stylesheet" href="/truth/truth-worthy.css"></head><body><header class="topbar"><div class="wrap nav"><a class="brand" href="/truth/">PROJECT UNVEILED <span>TRUTH ON TRIAL</span></a></div></header><main><section class="section"><div class="wrap"><article class="card"><div class="eyebrow">FREE PRELIMINARY INVESTIGATION</div><h1><?=htmlspecialchars($title)?></h1><div style="white-space:pre-wrap;line-height:1.7"><?=htmlspecialchars($body)?></div><p><a class="button" href="/truth/deep-dive.php">Request the Deep Dive</a> <a class="button" href="/truth/#ask">Try Another Question</a></p><p><small>This is an AI-assisted preliminary synthesis, not a final verdict. Claims requiring current or specialized evidence should be verified against the cited primary record during a full investigation.</small></p></article></div></section></main></body></html><?php exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /truth/#ask', true, 303); exit; }
$host=strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??''))??'');
$origin=strtolower((string)parse_url((string)($_SERVER['HTTP_ORIGIN']??''),PHP_URL_HOST));
if(!in_array($host,['bobsome1.com','www.bobsome1.com'],true)||($origin!==''&&!in_array($origin,['bobsome1.com','www.bobsome1.com'],true))) { http_response_code(403); exit('Request origin was not accepted.'); }
if(trim((string)($_POST['website']??''))!=='') page('Thank you','Your submission was received.');
$opened=(int)($_POST['opened_at']??0);
if($opened<1||time()-$opened<4) page('Try again','Please take a few seconds to review your question before submitting it.');
$question=trim(preg_replace('/\s+/u',' ',(string)($_POST['question']??''))??'');
$context=trim((string)($_POST['context']??''));
if(mb_strlen($question)<20||mb_strlen($question)>3000) page('Question needed','Please enter a question between 20 and 3,000 characters.');

$dir=tw_private_dir();
if(!is_dir($dir)) @mkdir($dir,0750,true);
$secretFile=$dir.'/question-secret.txt';
if(!is_file($secretFile)){file_put_contents($secretFile,bin2hex(random_bytes(32)),LOCK_EX);@chmod($secretFile,0640);}
$secret=is_file($secretFile)?trim((string)file_get_contents($secretFile)):'fallback';
$ipHash=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),$secret);
[$allowed,$reason]=tw_rate_limit($ipHash);
if(!$allowed) page('Research limit reached',$reason.' You can still submit the question for a full investigation.');

$result=tw_short_investigation($question,$context);
if(!($result['ok']??false)) page('Research engine unavailable',(string)($result['message']??'Please try again later.'));

$log=$dir.'/ai-investigations.jsonl';
$record=['at_utc'=>gmdate('c'),'ip_hash'=>$ipHash,'question'=>$question,'model'=>$result['model']??'','response_id'=>$result['response_id']??''];
file_put_contents($log,json_encode($record,JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX); @chmod($log,0640);
page('Truth Trial: '.$question,(string)$result['text']);
