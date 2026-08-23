<?php
declare(strict_types=1);
require_once __DIR__ . '/truth-case-lib.php';

$defaultCaseId = isset($caseId) && is_string($caseId) ? $caseId : 'TW-CLAIM-000001';
$requestedCaseId = isset($_GET['id']) ? (string) $_GET['id'] : $defaultCaseId;
if (!preg_match(TW_CASE_ID_PATTERN, $requestedCaseId)) {
    http_response_code(400);
    exit('Invalid case ID.');
}

$case = tw_load_case($requestedCaseId);
if ($case === null) {
    http_response_code(404);
    exit('Case not found.');
}

$finding = $case['finding'];
$primary = $case['propositions'][0];
$canonical = (string) $case['public_url'];
$challengeReceived = isset($_GET['challenge']) && $_GET['challenge'] === 'received';
?><!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="description" content="<?= tw_e($case['description']) ?>">
<link rel="canonical" href="<?= tw_e($canonical) ?>">
<title><?= tw_e($case['title']) ?> | Trust-Worthy AI</title>
<link rel="stylesheet" href="/truth/truth-worthy.css">
<style>.dossier{max-width:980px;margin:auto}.dossier h2{margin-top:2rem}.evidence{padding:1rem 1.1rem;margin:1rem 0;border-left:3px solid #d7ad51;background:#11100d}.source{padding:1rem 1.1rem;margin:1rem 0;border:1px solid rgba(255,255,255,.14);background:#11100d}.source a{overflow-wrap:anywhere}.pair{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.pair>div{padding:1rem;border:1px solid rgba(255,255,255,.12);background:#090704}.notice{padding:1rem;border-left:4px solid #d7ad51;background:rgba(215,173,81,.08);margin:1rem 0}.notice.success{border-color:#8fd2a0}.source-meta{color:#b9ad95;font-size:.88rem}.history{border-left:3px solid #d7ad51;padding-left:1rem}.history-item{margin:0 0 1rem}@media(max-width:760px){.pair{grid-template-columns:1fr}}</style>
</head>
<body>
<header class="topbar"><div class="wrap nav"><a class="brand" href="/truth/">TRUST-WORTHY <span>AI</span></a><nav class="navlinks"><a href="/truth/">Truth Trials</a><a href="/book/research.html">Research Standards</a><a href="#challenge">Challenge</a></nav></div></header>
<main>
<section class="hero"><div class="wrap dossier"><div class="eyebrow"><?= tw_e($case['case_id']) ?> · Version <?= tw_e($case['version']) ?> · <?= tw_e($finding['maturity']) ?></div><h1><?= tw_e($case['title']) ?></h1><p><?= tw_e($case['question']) ?></p><div class="manifesto"><strong>Scope:</strong> <?= tw_e($case['scope']) ?></div></div></section>
<section class="section"><div class="wrap dossier">
<?php if ($challengeReceived): ?><div class="notice success">Challenge received. It is now part of the private review queue and cannot alter the public finding without independent investigation.</div><?php endif; ?>
<div class="finding"><div class="eyebrow">Current epistemic assessment</div><h2><?= tw_e(tw_assessment_label((string) $finding['epistemic_assessment'])) ?></h2><p><?= tw_e($finding['summary']) ?></p><p><strong>Strongest objection:</strong> <?= tw_e($finding['strongest_objection']) ?></p><p class="muted"><strong>Maturity:</strong> <?= tw_e($finding['maturity']) ?> · <strong>Destination:</strong> <?= tw_e($finding['destination']) ?></p></div>

<h2>Proposition under trial</h2><div class="pair"><div><div class="eyebrow">P</div><p><?= tw_e($primary['p']) ?></p></div><div><div class="eyebrow">not-P</div><p><?= tw_e($primary['not_p']) ?></p></div></div><p class="muted"><?= tw_e($primary['scope']) ?></p>

<h2>Evidence for P</h2><ul class="rules"><?php tw_list($case['evidence_for_p']); ?></ul>
<h2>Evidence for not-P</h2><ul class="rules"><?php tw_list($case['evidence_for_not_p']); ?></ul>

<h2>Source and provenance record</h2>
<?php foreach ($case['sources'] as $source): ?>
<article class="source"><div class="case-id"><?= tw_e($source['source_id']) ?> · <?= tw_e($source['source_type']) ?> · <?= tw_e($source['position']) ?></div><h3><?= tw_e($source['title']) ?></h3><p class="source-meta"><?= tw_e($source['citation']) ?></p><p><?= tw_e($source['provenance']) ?></p><?php if ($source['lineage_parent'] !== null): ?><p class="source-meta"><strong>Lineage:</strong> depends on or parallels <?= tw_e($source['lineage_parent']) ?></p><?php endif; ?><p class="source-meta"><strong>Independence:</strong> <?= $source['independent'] === true ? 'yes' : ($source['independent'] === false ? 'no' : 'not established') ?> · <strong>Limit:</strong> <?= tw_e($source['limitations']) ?></p><?php if ($source['url'] !== null): ?><a href="<?= tw_e($source['url']) ?>" rel="noopener noreferrer">Open source</a><?php endif; ?></article>
<?php endforeach; ?>

<h2>Alternative explanations</h2><ul class="rules"><?php tw_list($case['alternative_hypotheses']); ?></ul>
<h2>Logic audit</h2><ul class="rules"><?php tw_list($case['logic_audit']); ?></ul>

<h2>Adversarial review</h2><div class="evidence"><p><strong>Separate pass completed:</strong> <?= $case['adversarial_review']['performed'] ? 'Yes' : 'Not yet' ?></p><p><strong>Strongest case against the leading assessment:</strong> <?= tw_e($case['adversarial_review']['strongest_case']) ?></p><p><strong>Current response:</strong> <?= tw_e($case['adversarial_review']['response']) ?></p><ul class="rules"><?php tw_list($case['adversarial_review']['unresolved']); ?></ul></div>

<?php if ($case['christ_consistency'] !== null): ?><h2>Christ-consistency analysis</h2><div class="evidence"><p><?= tw_e($case['christ_consistency']['summary']) ?></p><ul class="rules"><?php tw_list($case['christ_consistency']['cautions']); ?></ul></div><?php endif; ?>

<h2>What remains unresolved</h2><ul class="rules"><?php tw_list($case['epistemic_limits']); ?></ul>
<h2>What would change the assessment</h2><ul class="rules"><?php tw_list($finding['what_would_change_it']); ?></ul>

<h2>Revision history</h2><div class="history"><?php foreach ($case['revision_history'] as $revision): ?><div class="history-item"><strong>Version <?= tw_e($revision['version']) ?> · <?= tw_e($revision['date']) ?></strong><br><span class="muted"><?= tw_e($revision['change']) ?></span></div><?php endforeach; ?></div>
</div></section>

<section class="section" id="challenge"><div class="wrap dossier"><div class="card"><div class="eyebrow">Challenge this finding</div><h2>Bring evidence that can change the record.</h2><p class="muted">Target the source, provenance, date, language, context, logic, counterevidence, assumption, alternative explanation, or incentive analysis. Challenge the claim—not the person.</p><form class="challenge" id="challengeForm" action="/truth/challenge-submit.php" method="post"><input type="hidden" name="case_id" value="<?= tw_e($case['case_id']) ?>"><input type="hidden" name="opened_at" id="openedAt" value=""><div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><div class="row"><div><label for="challenge_type">Challenge type</label><select id="challenge_type" name="challenge_type" required><option value="">Choose one</option><option value="source">Source / authenticity</option><option value="provenance">Provenance / lineage</option><option value="date">Date / chronology</option><option value="translation">Translation / language</option><option value="context">Context</option><option value="logic">Logic / inference</option><option value="counterevidence">Counterevidence</option><option value="assumption">Hidden assumption</option><option value="alternative">Omitted alternative</option><option value="incentive">Power / incentive analysis</option></select></div><div><label for="name">Name or handle</label><input id="name" name="name" maxlength="100" autocomplete="name"></div></div><label for="argument">Your challenge</label><textarea id="argument" name="argument" minlength="20" maxlength="6000" required></textarea><label for="source">Source or evidence</label><textarea id="source" name="source" maxlength="2000"></textarea><label for="materiality">Why would this change the assessment?</label><textarea id="materiality" name="materiality" minlength="10" maxlength="3000" required></textarea><label for="email">Email (optional and kept private)</label><input id="email" type="email" name="email" maxlength="190" autocomplete="email"><button class="button" type="submit">Submit Evidence Challenge</button><div class="msg" id="formMsg" role="status" aria-live="polite"></div></form><p class="muted">Email stays private. Challenges are reviewed before any public change. See the <a href="/privacy.html">privacy notice</a>.</p></div></div></section>
</main>
<footer class="footer"><div class="wrap">Published evidence is challengeable. Reality is not changed by votes, ownership, or confidence.</div></footer>
<script>
(function(){
const form=document.getElementById('challengeForm'),msg=document.getElementById('formMsg'),opened=document.getElementById('openedAt');
opened.value=Math.floor(Date.now()/1000);
form.addEventListener('submit',async function(event){event.preventDefault();msg.textContent='Submitting challenge…';const button=form.querySelector('button');button.disabled=true;try{const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'Accept':'application/json'}});const data=await response.json();msg.textContent=data.message||'Submission processed.';if(data.ok){form.reset();opened.value=Math.floor(Date.now()/1000);}}catch(error){msg.textContent='The challenge could not be submitted. Please try again.';}finally{button.disabled=false;}});
})();
</script>
</body></html>
