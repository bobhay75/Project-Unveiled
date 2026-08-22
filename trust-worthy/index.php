<?php
$casePath = __DIR__ . '/cases-public/TW-CASE-000001-v1.json';
$case = is_file($casePath) ? json_decode((string) file_get_contents($casePath), true) : null;
?>
<!doctype html>
<html lang="en-US">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Trust-Worthy AI | Investigate Before You Believe</title>
<meta name="description" content="Trust-Worthy AI investigates difficult claims through primary sources, provenance, counterevidence, logic, uncertainty, and public challenge.">
<link rel="canonical" href="https://bobsome1.com/trust-worthy/">
<link rel="stylesheet" href="/trust-worthy/assets/trust-worthy.css">
</head>
<body>
<header class="site-header"><div class="container nav"><a class="brand" href="/">PROJECT <span>UNVEILED</span></a><div><a href="/trust-worthy/">Trust-Worthy</a><a href="/trust-worthy/method/">Method</a><a href="/book/">Read the Book</a></div></div></header>
<main>
<section class="hero"><div class="container"><div class="eyebrow">Trust-Worthy AI · v0.1</div><h1>Don't trust the answer. <span style="color:var(--gold2)">Examine the evidence.</span></h1><p class="lead">Trust-Worthy is not built to tell you what to believe. It investigates hard claims, traces sources backward, hunts for contrary evidence, tests competing explanations, exposes uncertainty, and publishes the reasoning so anyone can challenge it.</p><div class="actions"><a class="button" href="/trust-worthy/case.php?id=TW-CASE-000001">Open Truth Trial #000001</a><a class="button secondary" href="/trust-worthy/method/">See the Method</a></div></div></section>
<section class="section"><div class="container"><div class="eyebrow">The rule</div><h2>Nothing gets protected from the evidence.</h2><div class="grid"><div class="panel"><h3>Go to the source</h3><p class="muted">Trace claims toward primary texts, earliest witnesses, original language, historical context, artifacts, and genuine independent corroboration.</p></div><div class="panel"><h3>Attack the conclusion</h3><p class="muted">A separate adversarial pass must try to defeat the preliminary finding before anything is published.</p></div><div class="panel"><h3>Change publicly</h3><p class="muted">Successful challenges create a new version. Earlier findings remain visible, along with exactly why the assessment changed.</p></div></div></div></section>
<section class="section"><div class="container"><div class="eyebrow">Latest investigation</div><h2>Truth Trial #000001</h2><?php if ($case): ?><article class="case-card"><span class="status"><?= htmlspecialchars($case['finding']['assessment']) ?></span><h3><?= htmlspecialchars($case['title']) ?></h3><p class="muted"><?= htmlspecialchars($case['claim']) ?></p><p><strong>Current assessment:</strong> <?= htmlspecialchars($case['finding']['summary']) ?></p><div class="actions"><a class="button" href="/trust-worthy/case.php?id=<?= rawurlencode($case['case_id']) ?>">Examine the Case</a><a class="button secondary" href="/trust-worthy/challenge.php?id=<?= rawurlencode($case['case_id']) ?>">Challenge It</a></div></article><?php else: ?><div class="notice error">The first case record could not be loaded.</div><?php endif; ?></div></section>
<section class="section"><div class="container"><div class="eyebrow">What makes this different</div><h2>Truth has version control.</h2><p class="lead">Every claim gets a permanent case ID. Every finding has a version. Every source has provenance. Every serious challenge has a record. No silent rewrite. No protected conclusion. The goal is not to win an argument—it is to make the surviving evidence easier for everyone to inspect.</p></div></section>
</main>
<footer><div class="container">Trust-Worthy AI is a Project Unveiled research project. Findings are evidence assessments, not commands about what anyone must believe. <a href="/">bobsome1.com</a></div></footer>
</body></html>