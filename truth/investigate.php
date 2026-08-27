<?php
declare(strict_types=1);
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/lib/trust-worthy-ai.php';

function render_trial_body(string $body): string {
  $headings = [
    'CLAIM ON TRIAL','WHAT IS WELL ESTABLISHED','STRONGEST EVIDENCE FOR',
    'STRONGEST COUNTEREVIDENCE / ALTERNATIVE','WHAT REMAINS UNKNOWN',
    'PROVISIONAL FINDING','SOURCE TRAIL · WEB CHECK','SYSTEM NOTE'
  ];
  $lines = preg_split('/\R/u', trim($body)) ?: [];
  $html = '';
  $inList = false;
  foreach ($lines as $raw) {
    $line = trim($raw);
    if ($line === '') {
      if ($inList) { $html .= '</ul>'; $inList = false; }
      continue;
    }
    if (in_array($line, $headings, true)) {
      if ($inList) { $html .= '</ul>'; $inList = false; }
      $html .= '<h2>' . htmlspecialchars($line, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '</h2>';
      continue;
    }
    if (str_starts_with($line, '- ')) {
      if (!$inList) { $html .= '<ul class="rules">'; $inList = true; }
      $item = trim(substr($line, 2));
      if (preg_match('#^(.+?):\s+(https?://\S+)$#u', $item, $m)) {
        $label = htmlspecialchars(trim($m[1]), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $url = filter_var($m[2], FILTER_VALIDATE_URL) ? $m[2] : '';
        if ($url !== '') {
          $safeUrl = htmlspecialchars($url, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
          $html .= '<li><a href="'.$safeUrl.'" target="_blank" rel="noopener noreferrer">'.$label.'</a></li>';
        } else {
          $html .= '<li>' . htmlspecialchars($item, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '</li>';
        }
      } else {
        $html .= '<li>' . htmlspecialchars($item, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') . '</li>';
      }
      continue;
    }
    if ($inList) { $html .= '</ul>'; $inList = false; }
    $safe = htmlspecialchars($line, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
    if (strcasecmp($line, 'You be the judge.') === 0) {
      $html .= '<div class="judge">YOU BE THE JUDGE.<small>POWERED BY TRUST-WORTHY</small></div>';
    } else {
      $html .= '<p>' . $safe . '</p>';
    }
  }
  if ($inList) $html .= '</ul>';
  return $html;
}

function page(string $title,string $body): never {
  http_response_code(200);
  ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($title)?> | Truth on Trial</title><link rel="stylesheet" href="/truth/truth-worthy.css"></head><body><header class="topbar"><div class="wrap nav"><a class="brand" href="/truth/">PROJECT UNVEILED <span>TRUTH ON TRIAL</span></a></div></header><main><section class="section"><div class="wrap"><article class="card"><div class="eyebrow">FREE PRELIMINARY INVESTIGATION</div><h1><?=htmlspecialchars($title)?></h1><div class="trial-result"><?=render_trial_body($body)?></div><p><a class="button" href="/truth/deep-dive.php">Request the Deep Dive</a> <a class="button" href="/truth/#ask">Try Another Question</a></p><p><small>This is an AI-assisted preliminary synthesis, not a final verdict. Claims requiring current or specialized evidence should be verified against the cited primary record during a full investigation.</small></p></article></div></section></main></body></html><?php exit;
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
if(!($result['ok']??false)) {
  tw_release_rate_limit($ipHash);
  page('Research engine unavailable',(string)($result['message']??'Please try again later.'));
}

$log=$dir.'/ai-investigations.jsonl';
$record=[
  'at_utc'=>gmdate('c'),
  'ip_hash'=>$ipHash,
  'question'=>$question,
  'model'=>$result['model']??'',
  'response_id'=>$result['response_id']??'',
  'input_tokens'=>(int)($result['input_tokens']??0),
  'output_tokens'=>(int)($result['output_tokens']??0),
  'reasoning_tokens'=>(int)($result['reasoning_tokens']??0),
  'web_search_calls'=>(int)($result['web_search_calls']??0),
  'source_count'=>(int)($result['source_count']??0),
  'incomplete'=>(bool)($result['incomplete']??false),
];
file_put_contents($log,json_encode($record,JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX); @chmod($log,0640);
page('Truth Trial: '.$question,(string)$result['text']);
