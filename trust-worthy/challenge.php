<?php
$id = isset($_GET['id']) ? (string) $_GET['id'] : (isset($_POST['case_id']) ? (string) $_POST['case_id'] : 'TW-CASE-000001');
if (!preg_match('/^TW-CASE-[0-9]{6}$/', $id)) { http_response_code(400); exit('Invalid case ID.'); }
$notice = '';$noticeClass='';
$types = ['source/authenticity','provenance','chronology','translation/language','context','logic','counterevidence','assumption','omitted alternative'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $honeypot = trim((string)($_POST['website'] ?? ''));
  $type = trim((string)($_POST['challenge_type'] ?? ''));
  $component = trim((string)($_POST['component'] ?? ''));
  $argument = trim((string)($_POST['argument'] ?? ''));
  $source = trim((string)($_POST['source'] ?? ''));
  $materiality = trim((string)($_POST['materiality'] ?? ''));
  $name = trim((string)($_POST['display_name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  if ($honeypot !== '') { http_response_code(400); $notice='Submission rejected.'; $noticeClass='error'; }
  elseif (!in_array($type,$types,true) || strlen($argument) < 20 || strlen($materiality) < 10) { $notice='Please choose a valid challenge type and explain both your argument and why it materially changes the case.'; $noticeClass='error'; }
  elseif ($email !== '' && !filter_var($email,FILTER_VALIDATE_EMAIL)) { $notice='Please enter a valid email address or leave it blank.'; $noticeClass='error'; }
  else {
    $record = [
      'challenge_id'=>'TW-CH-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(3)),
      'case_id'=>$id,'submitted_at'=>date(DATE_ATOM),'challenge_type'=>$type,'component'=>$component,
      'argument'=>$argument,'source'=>$source,'materiality'=>$materiality,'display_name'=>$name,'email'=>$email,
      'status'=>'pending-review'
    ];
    $base = dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__),1) . '/private/trust-worthy/pending-challenges';
    if (!is_dir($base)) { @mkdir($base,0700,true); }
    $file = $base . '/' . preg_replace('/[^A-Za-z0-9_-]/','_',$record['challenge_id']) . '.json';
    if (is_dir($base) && is_writable($base) && file_put_contents($file,json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX)!==false) {
      @chmod($file,0600); $notice='Challenge received. It is pending independent review and will not alter the public finding unless the evidence materially survives investigation.'; $noticeClass='success';
    } else { http_response_code(503); $notice='The challenge could not be stored safely yet. The private intake directory must be configured before launch.'; $noticeClass='error'; }
  }
}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="en-US"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Challenge <?= e($id) ?> | Trust-Worthy AI</title><meta name="robots" content="index,follow"><link rel="stylesheet" href="/trust-worthy/assets/trust-worthy.css"></head><body>
<header class="site-header"><div class="container nav"><a class="brand" href="/">PROJECT <span>UNVEILED</span></a><div><a href="/trust-worthy/">Trust-Worthy</a><a href="/trust-worthy/case.php?id=<?= rawurlencode($id) ?>">Case</a><a href="/trust-worthy/method/">Method</a></div></div></header>
<main><section class="hero"><div class="container"><div class="case-id"><?= e($id) ?></div><h1 style="font-size:clamp(2.7rem,6vw,5rem)">Challenge the finding.</h1><p class="lead">Do not argue louder. Bring something that changes the evidence: a better source, a chronology problem, a translation issue, missing context, a logical failure, or credible counterevidence.</p></div></section>
<section class="section"><div class="container"><?php if($notice): ?><div class="notice <?= e($noticeClass) ?>"><?= e($notice) ?></div><?php endif; ?><form method="post" class="panel" novalidate><input type="hidden" name="case_id" value="<?= e($id) ?>"><div style="position:absolute;left:-10000px" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div><div class="form-grid"><div class="field"><label for="challenge_type">Challenge type</label><select id="challenge_type" name="challenge_type" required><option value="">Choose one</option><?php foreach($types as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></select></div><div class="field"><label for="component">What specifically are you challenging?</label><input id="component" name="component" maxlength="240" placeholder="Source S4, chronology, finding sentence, assumption..."></div><div class="field full"><label for="argument">Your argument</label><textarea id="argument" name="argument" required maxlength="6000" placeholder="Explain the correction or counterargument precisely."></textarea></div><div class="field full"><label for="source">Source URL or citation</label><input id="source" name="source" maxlength="1000" placeholder="Primary source, edition, manuscript, article, book citation, archive URL..."></div><div class="field full"><label for="materiality">Why would this change the finding?</label><textarea id="materiality" name="materiality" required maxlength="3000" placeholder="Explain why this is material, not merely a different opinion."></textarea></div><div class="field"><label for="display_name">Public display name (optional)</label><input id="display_name" name="display_name" maxlength="100"></div><div class="field"><label for="email">Email for follow-up (optional, private)</label><input id="email" name="email" type="email" maxlength="254"></div></div><div class="notice" style="margin:1rem 0">Submitting evidence does not automatically change a case. Trust-Worthy must independently investigate it. Successful challenges create a new public version and preserve the previous finding.</div><button class="button" type="submit">Submit Challenge</button></form></div></section></main><footer><div class="container">Challenge the evidence, not the person. <a href="/trust-worthy/case.php?id=<?= rawurlencode($id) ?>">Return to the case.</a></div></footer></body></html>