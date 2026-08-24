<?php
declare(strict_types=1);
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function section_list(string $title, mixed $items): void {
    if (!is_array($items) || $items === []) return;
    echo '<h2>' . h($title) . '</h2><ul class="rules">';
    foreach ($items as $item) if (is_scalar($item) && trim((string)$item) !== '') echo '<li>' . h(trim((string)$item)) . '</li>';
    echo '</ul>';
}
$path = __DIR__ . '/daily-data/latest.json';
$trial = [];
if (is_file($path)) {
    $decoded = json_decode((string)file_get_contents($path), true);
    if (is_array($decoded)) $trial = $decoded;
}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Today's Trust-Worthy AI Daily Truth Trial. Question everything, inspect the evidence, and you be the judge."><title>Today's Truth Trial | Trust-Worthy AI</title><link rel="stylesheet" href="/truth/truth-worthy.css"></head><body><header class="topbar"><div class="wrap nav"><a class="brand" href="/truth/">TRUST-WORTHY <span>AI</span></a><nav class="navlinks"><a href="/truth/">Truth Home</a><a href="/truth/#ask">Ask a Question</a></nav></div></header><main>
<?php if ($trial === []): ?>
<section class="hero"><div class="wrap"><div class="eyebrow">Daily Truth Trial</div><h1>THE NEXT CLAIM IS BEING CHOSEN.</h1><p>The Daily Truth Trial desk is live. No reviewed case has been published through the new desk yet.</p><div class="manifesto"><strong>QUESTION EVERYTHING!</strong><br>When the evidence trail is ready, it will appear here. <strong>YOU BE THE JUDGE.</strong></div></div></section>
<?php else: ?>
<section class="hero"><div class="wrap"><div class="eyebrow">EXTRA! EXTRA! · TODAY'S CLAIM GOES ON TRIAL</div><h1><?=h((string)($trial['headline'] ?? 'Daily Truth Trial'))?></h1><p><?=h((string)($trial['summary'] ?? ''))?></p><div class="manifesto"><strong>The Claim:</strong> <?=h((string)($trial['claim'] ?? ''))?></div></div></section>
<section class="section"><div class="wrap grid"><article class="card">
<?php section_list("What's Proven", $trial['proven'] ?? []); ?>
<?php section_list("What's Strongly Indicated", $trial['strongly_indicated'] ?? []); ?>
<?php section_list('Contradictions & Missing Pieces', $trial['contradictions_missing_pieces'] ?? []); ?>
<?php section_list('Motives / Incentives / Who Benefits', $trial['motives_incentives_who_benefits'] ?? []); ?>
<?php section_list('Logic & Common Sense Test', $trial['logic_common_sense'] ?? []); ?>
<?php section_list('Counterevidence & Alternative Explanations', $trial['counterevidence_alternatives'] ?? []); ?>
<?php section_list('What Remains Unknown', $trial['unknowns'] ?? []); ?>
<?php section_list('What Would Change This Finding', $trial['what_would_change_the_finding'] ?? []); ?>
<div class="bobinated"><div class="eyebrow">Bobinated Opinion</div><p><?=h((string)($trial['bobinated_opinion']['opinion'] ?? ''))?></p><?php if (!empty($trial['bobinated_opinion']['reasoning'])): ?><ol><?php foreach ($trial['bobinated_opinion']['reasoning'] as $r): ?><li><?=h((string)$r)?></li><?php endforeach; ?></ol><?php endif; ?></div>
<div class="judge">YOU BE THE JUDGE.<small>QUESTION EVERYTHING.</small></div>
</article><aside class="card"><h3>Source Trail</h3><ul class="rules"><?php foreach (($trial['sources'] ?? []) as $s): $u=(string)($s['url']??''); if(!filter_var($u,FILTER_VALIDATE_URL)) continue; ?><li><a href="<?=h($u)?>" target="_blank" rel="noopener"><?=h((string)($s['title'] ?? 'Source'))?></a><?php if(!empty($s['role'])): ?> — <?=h((string)$s['role'])?><?php endif; ?></li><?php endforeach; ?></ul><div class="finding"><strong>Confidence:</strong> <?=h(strtoupper((string)($trial['confidence'] ?? 'low')))?>.<br>This case is open to challenge and revision.</div><p><a class="button" href="/truth/#ask">Challenge or Submit Evidence</a></p></aside></div></section>
<?php endif; ?>
</main><footer class="footer"><div class="wrap"><strong>QUESTION EVERYTHING!</strong> · Trust-Worthy AI · <strong>YOU BE THE JUDGE.</strong></div></footer></body></html>
