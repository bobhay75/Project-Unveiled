<?php
declare(strict_types=1);
header_remove('X-Powered-By');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit('POST required.'); }
require_once __DIR__ . '/lib/trust-worthy-paid.php';
$caseId=is_string($_POST['case_id']??null)?trim((string)$_POST['case_id']):'';
$resume=is_string($_POST['resume_token']??null)?trim((string)$_POST['resume_token']):'';
$secret=tw_question_secret();
$verified=$secret!==''?tw_paid_verify_state($resume,'resume',$secret):null;
$case=($verified!==null&&($verified['case_id']??'')===$caseId)?tw_paid_load_case($caseId):null;
if(!is_array($case)||($case['state']??'')!=='paid'){http_response_code(403);exit('Paid continuation authorization was invalid, expired, or already used.');}
$claim=(string)($case['payload']['question']??'Investigation');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#041322"><title>Unveiling the Truth</title><link rel="stylesheet" href="/truth/investigation.css"><script defer src="/truth/paid-continue.js"></script></head><body><header class="topbar"><a href="/truth/" class="brand">TW <span>UNVEILING THE TRUTH // PAID CONTINUATION</span></a><div class="status" id="paidStatus">READY</div></header><main class="shell"><section class="panel intro"><div class="kicker">THE EVIDENCE GRAPH CONTINUES</div><h1>Unveiling the Truth</h1><p class="claim"><?=htmlspecialchars($claim,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></p><p class="muted">Your free origin, primary-evidence, and corroboration work has been preserved. The paid phase now continues from that stored evidence instead of starting over.</p><form id="paidResumeForm"><input type="hidden" name="case_id" value="<?=htmlspecialchars($caseId,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><input type="hidden" name="resume_token" value="<?=htmlspecialchars($resume,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><button class="primary" type="submit" id="paidResumeButton">RESUME THE INVESTIGATION →</button></form></section><section class="workspace" id="paidWorkspace" hidden><aside class="panel stages"><div class="kicker">PAID CONTINUATION // LIVE RECEIPTS</div><h2>Remaining investigation path</h2><ol id="stageList"><li class="done">Origin preserved</li><li class="done">Primary evidence preserved</li><li class="done">Corroboration preserved</li><li data-stage="dependency">Trace dependencies + echo chains</li><li data-stage="counter">Search counterevidence</li><li data-stage="context">Build context + chronology</li><li data-stage="synthesis">Generate final finding</li></ol></aside><section class="panel live"><div class="kicker">WHAT THE ENGINE HAS ACTUALLY DONE</div><div id="paidActivity" class="activity" aria-live="polite"></div></section><section class="panel result" id="paidResult" hidden><div class="kicker">TRUST-WORTHY FINDING</div><div class="verdict-row"><div><span>Verdict</span><strong id="paidVerdict">—</strong></div><div><span>Probability</span><strong id="paidProbability">—</strong></div></div><p class="warning" id="paidNote"></p><article id="paidAnalysis"></article><div class="sources"><h3>Evidence trail</h3><div id="paidSources"></div></div></section></section></main></body></html>
