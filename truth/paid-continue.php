<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/trust-worthy-guided-access.php';
tw_guided_require_enabled();
header_remove('X-Powered-By');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=(string)($_SERVER['REQUEST_METHOD']??'');
if($method==='POST'){
    tw_guided_post_gate(12000);
    $input=$_POST;
}elseif($method==='GET'){
    // A bookmarked private receipt may navigate here. Cross-site embedding and
    // explicit foreign origins are rejected; the signed receipt is still required.
    $site=strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'')));
    $server=$_SERVER;
    if(!isset($server['HTTP_ORIGIN']))$server['HTTP_ORIGIN']='https://'.(string)($server['HTTP_HOST']??'');
    $server['HTTP_SEC_FETCH_SITE']=$site==='none'?'':$site;
    if(!in_array($site,['','same-origin','none'],true)||!tw_guided_valid_origin($server)){http_response_code(403);exit('Recovery must be opened directly from its private link.');}
    $input=$_GET;
}else{http_response_code(405);header('Allow: GET, POST');exit('GET or POST required.');}
require_once __DIR__ . '/lib/trust-worthy-paid.php';
$caseId=is_string($input['case_id']??null)?trim((string)$input['case_id']):'';
$resume=is_string($input['resume_token']??null)?trim((string)$input['resume_token']):'';
$recovery=is_string($input['recovery_token']??null)?trim((string)$input['recovery_token']):'';
$secret=tw_question_secret_readonly();
$case=$recovery!==''?tw_paid_recovery_case($caseId,$recovery,$secret):null;
if(!is_array($case)&&$method==='POST'&&$recovery===''){
    // Preserve the older signed return form. Replaying it reads existing state;
    // only paid-resume.php can consume its one-time authorization.
    $verified=$secret!==''?tw_paid_verify_state($resume,'resume',$secret):null;
    $candidate=($verified!==null&&($verified['case_id']??'')===$caseId)?tw_paid_load_case($caseId,true):null;
    if(is_array($candidate)&&tw_paid_recovery_eligible($candidate))$case=$candidate;
}
if(!is_array($case)){http_response_code(403);exit('The private continuation receipt is invalid, expired, or belongs to another investigation. Keep your PayPal receipt and contact Bobsome1; do not pay again.');}
$recovery=tw_paid_sign_recovery($case,$secret);
$recoveryUrl='/truth/paid-continue.php?'.http_build_query(['case_id'=>$caseId,'recovery_token'=>$recovery],'','&',PHP_QUERY_RFC3986);
$snapshot=tw_paid_recovery_snapshot($case,$secret);
$resume=(string)($snapshot['resume_token']??'');
$claim=(string)($case['payload']['question']??'Investigation');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#041322"><title>Unveiling the Truth</title><link rel="stylesheet" href="/truth/investigation.css"><script defer src="/truth/investigation-client.js"></script><script defer src="/truth/paid-continue.js"></script></head><body><header class="topbar"><a href="/truth/" class="brand">TW <span>UNVEILING THE TRUTH // PAID CONTINUATION</span></a><div class="status" id="paidStatus">READY</div></header><main class="shell"><section class="panel intro"><div class="kicker">THE EVIDENCE GRAPH CONTINUES</div><h1>Unveiling the Truth</h1><p class="claim"><?=htmlspecialchars($claim,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></p><p class="muted">Your free origin, primary-evidence, and corroboration work has been preserved. The paid phase now continues from that stored evidence instead of starting over.</p><p class="muted">Case reference: <code><?=htmlspecialchars($caseId,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></code>. If continuation stops, retain this reference and your PayPal receipt and <a href="/services/#contact">contact Bobsome1</a>. Do not pay again for the same investigation.</p><p class="muted" id="paidRecoveryMessage"><?=htmlspecialchars((string)($snapshot['message']??'Check the saved status of this investigation.'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?></p><p class="muted"><a id="paidRecoveryLink" href="<?=htmlspecialchars($recoveryUrl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>">Keep this private recovery link</a> · Available until <?=htmlspecialchars(gmdate('Y-m-d H:i \U\T\C',(int)$case['expires_at']),ENT_QUOTES,'UTF-8')?>. Anyone with this link can view this case.</p><button type="button" class="secondary-action" id="paidCheckStatus">CHECK SAVED STATUS</button><form id="paidResumeForm" method="post" action="/truth/paid-resume.php" data-state="<?=htmlspecialchars((string)$case['state'],ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="case_id" value="<?=htmlspecialchars($caseId,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><input type="hidden" name="resume_token" value="<?=htmlspecialchars($resume,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><input type="hidden" name="recovery_token" value="<?=htmlspecialchars($recovery,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"><button class="primary" type="submit" id="paidResumeButton" <?=($case['state']??'')==='paid'?'':'disabled'?>>RESUME THE INVESTIGATION →</button></form></section><section class="workspace" id="paidWorkspace" hidden><aside class="panel stages"><div class="kicker">PAID CONTINUATION // LIVE RECEIPTS</div><h2>Remaining investigation path</h2><ol id="stageList"><li class="done">Origin preserved</li><li class="done">Primary evidence preserved</li><li class="done">Corroboration preserved</li><li data-stage="dependency">Trace dependencies + echo chains</li><li data-stage="counter">Search counterevidence</li><li data-stage="context">Build context + chronology</li><li data-stage="synthesis">Generate final finding</li></ol></aside><section class="panel live"><div class="kicker">WHAT THE ENGINE HAS ACTUALLY DONE</div><div id="paidActivity" class="activity" aria-live="polite"></div></section><section class="panel result" id="paidResult" hidden><div class="kicker">TRUST-WORTHY FINDING</div><div class="verdict-row"><div><span>Verdict</span><strong id="paidVerdict">—</strong></div><div><span>Probability</span><strong id="paidProbability">—</strong></div></div><p class="warning" id="paidNote"></p><article id="paidAnalysis"></article><div class="sources"><h3>Evidence trail</h3><div id="paidSources"></div></div><button type="button" class="secondary-action" id="exportPaidFinding">DOWNLOAD FINDING + SOURCES</button><div class="branch-actions"><button type="button" data-branch="Challenge this finding">Challenge this finding</button><button type="button" data-branch="Dig deeper into the weakest evidence">Dig deeper here</button><button type="button" data-branch="Show only primary evidence">Primary evidence only</button><button type="button" data-branch="Argue the opposite conclusion">Argue the opposite</button><button type="button" data-branch="Follow the money or incentives if relevant">Follow the money</button><button type="button" data-branch="Investigate the key person or organization further">Investigate an entity</button></div><p class="muted branch-note">A follow-up opens a new investigation in another tab with the original claim and earlier source leads. Review its claim map before new research begins. The previous finding remains available here; new research follows the same allowance and optional checkout rules.</p></section></section></main></body></html>
