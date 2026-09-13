<?php
declare(strict_types=1);
header_remove('X-Powered-By');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\'; img-src \'self\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  header('Content-Type: text/plain; charset=UTF-8');
  exit('POST required.');
}

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

function page(string $title,string $body,int $status=200): never {
  http_response_code($status);
  header('Content-Type: text/html; charset=UTF-8');
  ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?> | Truth on Trial</title><link rel="stylesheet" href="/truth/truth-worthy.css"></head><body><header class="topbar"><div class="wrap nav"><a class="brand" href="/truth/">PROJECT UNVEILED <span>TRUTH ON TRIAL</span></a></div></header><main><section class="section"><div class="wrap"><article class="card"><div class="eyebrow">FREE PRELIMINARY INVESTIGATION</div><h1><?=htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></h1><div class="trial-result"><?=render_trial_body($body)?></div><p><a class="button" href="/truth/deep-dive.php">Request the Deep Dive</a> <a class="button" href="/truth/#ask">Try Another Question</a></p><p><small>This is an AI-assisted preliminary synthesis, not a final verdict. Claims requiring current or specialized evidence should be verified against the cited primary record during a full investigation.</small></p></article></div></section></main></body></html><?php exit;
}

$contentLength=(string)($_SERVER['CONTENT_LENGTH']??'');
if($contentLength!==''&&(!ctype_digit($contentLength)||(int)$contentLength>16384)) { http_response_code(413); exit('Submission was too large.'); }
$contentType=strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''),2)[0]));
if($contentType!=='application/x-www-form-urlencoded') { http_response_code(415); exit('Form encoding was not accepted.'); }
try {
  tw_intake_enforce_raw_body_limit(16384);
} catch (TwIntakeException $bodyError) {
  http_response_code($bodyError->httpStatus); exit($bodyError->getMessage());
}
$parsedBytes=0;
foreach($_POST as $field=>$value) {
  if(!is_string($field)||!is_string($value)) { http_response_code(422); exit('Form fields must contain plain text.'); }
  $parsedBytes+=strlen($field)+strlen($value);
  if($parsedBytes>16384) { http_response_code(413); exit('Submission was too large.'); }
}

$hostValue=strtolower(rtrim((string)($_SERVER['HTTP_HOST']??''),'.'));
$host=preg_match('/^((?:www\.)?bobsome1\.com)(?::443)?$/',$hostValue,$hostMatch)?$hostMatch[1]:'';
$originValue=trim((string)($_SERVER['HTTP_ORIGIN']??''));
$originParts=$originValue!==''?parse_url($originValue):false;
$originHost=is_array($originParts)?strtolower((string)($originParts['host']??'')):'';
$originScheme=is_array($originParts)?strtolower((string)($originParts['scheme']??'')):'';
$originPort=is_array($originParts)?($originParts['port']??null):null;
$originHasExtra=is_array($originParts)&&(isset($originParts['user'])||isset($originParts['pass'])||isset($originParts['query'])||isset($originParts['fragment'])||!in_array((string)($originParts['path']??''),['','/'],true));
$fetchSite=strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'')));
$allowedHosts=['bobsome1.com','www.bobsome1.com'];
if(!in_array($host,$allowedHosts,true)||$originHost!==$host||$originScheme!=='https'||($originPort!==null&&$originPort!==443)||$originHasExtra||($fetchSite!==''&&$fetchSite!=='same-origin')) {
  http_response_code(403); exit('Request origin was not accepted.');
}

$website=$_POST['website']??'';
if(!is_string($website)) page('Submission not accepted','Form fields must contain plain text.',422);
if(trim($website)!=='') page('Thank you','Your submission was received.');
$openedValue=$_POST['opened_at']??'';
$opened=is_string($openedValue)&&preg_match('/^\d{1,12}$/',$openedValue)?(int)$openedValue:0;
$formAge=time()-$opened;
if($opened<1||$formAge<4||$formAge>7200) page('Try again','Please reload the page, then take a few seconds to review your question before submitting it.',422);
[$validInput,$question,$context,$inputError]=tw_validate_trial_input($_POST['question']??null,$_POST['context']??'');
if(!$validInput) page('Question needed',$inputError,422);

$secret=tw_question_secret();
if($secret==='') page('Research engine unavailable','Private research storage could not be secured. Please try again later.',503);
try {
  // Secure and redact any pre-Release-16 raw-question records before another
  // provider request can create metadata that needs to be appended.
  tw_prepare_ai_investigation_log($secret);
  tw_prepare_ai_diagnostic_log($secret);
} catch (Throwable $logError) {
  page('Research engine unavailable','Private investigation history could not be migrated safely. No record was overwritten.',503);
}
$remoteAddress=(string)($_SERVER['REMOTE_ADDR']??'');
if(filter_var($remoteAddress,FILTER_VALIDATE_IP)===false) page('Request not accepted','The request address could not be validated.',403);
$ipHash=hash_hmac('sha256','ai-ip|'.$remoteAddress,$secret);
[$allowed,$reason,$reservation,$rateStatus]=tw_rate_limit($ipHash);
if(!$allowed) {
  if($rateStatus==='allowance_exhausted') {
    header('Retry-After: 3600');
    page('Research limit reached',$reason.' You can still submit the question for a full investigation.',429);
  }
  page('Research engine unavailable','Research allowance storage is unavailable. Please try again later.',503);
}

$result=tw_short_investigation($question,$context,$secret);
if(!($result['ok']??false)) {
  tw_finalize_rate_limit($ipHash,$reservation,(bool)($result['quota_consumed']??true));
  page('Research engine unavailable',(string)($result['message']??'Please try again later.'),503);
}
if(!tw_finalize_rate_limit($ipHash,$reservation,true)) {
  page('Research engine unavailable','Research allowance storage could not be finalized safely. Please try again later.',503);
}

$responseId=is_string($result['response_id']??null)?$result['response_id']:'';
$record=[
  'at_utc'=>gmdate('c'),
  'ip_hash'=>$ipHash,
  'question_hash'=>hash_hmac('sha256','question|'.$question,$secret),
  'question_characters'=>mb_strlen($question,'UTF-8'),
  'model'=>tw_ai_safe_model_identifier($result['model']??null),
  'input_tokens'=>(int)($result['input_tokens']??0),
  'output_tokens'=>(int)($result['output_tokens']??0),
  'reasoning_tokens'=>(int)($result['reasoning_tokens']??0),
  'web_search_calls'=>(int)($result['web_search_calls']??0),
  'source_count'=>(int)($result['source_count']??0),
  'incomplete'=>(bool)($result['incomplete']??false),
];
if($responseId!=='') {
  $record['response_id_hash']=hash_hmac('sha256','response-id|'.$responseId,$secret);
  $record['response_id_characters']=tw_ai_utf8_length($responseId)??0;
}
try {
  tw_append_ai_investigation_record($record,$secret);
} catch (Throwable $logError) {
  page('Research engine unavailable','The completed investigation could not be recorded in secure private storage.',503);
}
page('Truth Trial: '.$question,(string)$result['text']);
