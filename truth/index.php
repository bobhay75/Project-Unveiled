<?php
declare(strict_types=1);
require_once __DIR__ . '/truth-case-lib.php';
$cases = tw_all_cases();
?><!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="description" content="Trust-Worthy AI publishes open, adversarial investigations that trace difficult claims to evidence and invite anyone to challenge the findings.">
<link rel="canonical" href="https://bobsome1.com/truth/">
<title>Trust-Worthy AI | Open Truth Trials</title>
<link rel="stylesheet" href="/truth/truth-worthy.css">
<style>.case-list{grid-template-columns:repeat(2,minmax(0,1fr))}@media(max-width:800px){.case-list{grid-template-columns:1fr}}</style>
</head>
<body>
<header class="topbar"><div class="wrap nav"><a class="brand" href="/">PROJECT <span>UNVEILED</span></a><nav class="navlinks"><a href="#trials">Truth Trials</a><a href="/book/research.html">Research Standards</a><a href="/book/">Project Unveiled</a></nav></div></header>
<main>
<section class="hero"><div class="wrap"><div class="eyebrow">Trust-Worthy AI · Public Evidence Record</div><h1>Don't trust the answer. Examine the evidence.</h1><p>Hard questions deserve more than canned answers. Trust-Worthy traces claims toward primary evidence, tests provenance and context, searches for counterevidence, attacks its own reasoning, publishes uncertainty, and lets anyone try to overturn the finding.</p><div class="manifesto"><strong>No protected conclusions.</strong> Christianity, skepticism, institutions, Project Unveiled, and Trust-Worthy itself face the same rule: if better evidence wins, the public finding changes.</div></div></section>
<section class="section" id="trials"><div class="wrap"><div class="eyebrow">Published &amp; Open Investigations</div><h2>Every claim gets one permanent record.</h2>
<?php if (!$cases): ?>
<div class="card"><p>No public cases are available yet.</p></div>
<?php else: ?>
<div class="grid case-list">
<?php foreach ($cases as $case): $finding = $case['finding']; ?>
<article class="card"><div class="case-head"><div><div class="case-id"><?= tw_e($case['case_id']) ?> · VERSION <?= tw_e($case['version']) ?></div><h2><?= tw_e($case['title']) ?></h2></div><div class="status"><?= tw_e($finding['maturity']) ?></div></div><p><?= tw_e($case['question']) ?></p><div class="finding"><strong><?= tw_e(tw_assessment_label((string) $finding['epistemic_assessment'])) ?>:</strong> <?= tw_e($finding['summary']) ?></div><p><a class="button" href="<?= tw_e(tw_public_path($case)) ?>">Examine the evidence</a></p></article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div></section>
<section class="section"><div class="wrap grid"><article class="card"><div class="eyebrow">The Method</div><h2>Investigate before concluding.</h2><div class="steps"><div class="step"><div class="num">1</div><div><strong>Define</strong><span>Separate the proposition from assumptions hidden inside the question.</span></div></div><div class="step"><div class="num">2</div><div><strong>Trace</strong><span>Follow citations backward toward primary and earliest accessible evidence.</span></div></div><div class="step"><div class="num">3</div><div><strong>Corroborate</strong><span>Distinguish independent evidence from repetition of one source.</span></div></div><div class="step"><div class="num">4</div><div><strong>Attack</strong><span>Build the strongest credible case against the leading explanation.</span></div></div><div class="step"><div class="num">5</div><div><strong>Reason</strong><span>Separate evidence, inference, interpretation, and unresolved questions.</span></div></div><div class="step"><div class="num">6</div><div><strong>Publish &amp; revise</strong><span>Material challenges create new versions without erasing the old record.</span></div></div></div></article><aside class="card"><h3>The Trial Rules</h3><ul class="rules"><li>No third-party verdict is treated as authority.</li><li>Primary and earliest accessible evidence receives priority.</li><li>Contrary evidence must be actively sought.</li><li>Evidence and interpretation remain visibly separate.</li><li>“We do not know” is a legitimate finding.</li><li>No founder belief receives immunity.</li><li>Successful challenges permanently revise the public record.</li></ul></aside></div></section>
</main>
<footer class="footer"><div class="wrap">Trust-Worthy AI · Project Unveiled · Truth is not afraid of questions.</div></footer>
</body></html>
