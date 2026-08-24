<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
$key = tw_require_admin();
$queue = tw_json_read(tw_private_dir() . '/daily-candidates.json');
$draft = tw_json_read(tw_private_dir() . '/daily-draft.json');
$error = trim((string)($_GET['error'] ?? ''));
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function join_lines(mixed $v): string { return is_array($v) ? implode("\n", array_map('strval', $v)) : ''; }
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Trust-Worthy Private Meat Desk</title><link rel="stylesheet" href="/truth/truth-worthy.css"></head><body>
<header class="topbar"><div class="wrap nav"><a class="brand" href="/truth/">TRUST-WORTHY <span>AI</span></a><nav class="navlinks"><a href="/truth/today.php">Public Daily Trial</a><a href="/truth/">Truth Home</a></nav></div></header><main>
<section class="hero"><div class="wrap"><div class="eyebrow">Private Editor Desk</div><h1>THE MEAT DESK</h1><p>Questions people need answered. Real user questions first. High-consequence truths next. News is evidence — not our editorial calendar.</p></div></section>
<?php if ($error !== ''): ?><section class="section"><div class="wrap"><div class="card"><strong>Research error:</strong> <?=h($error)?></div></div></section><?php endif; ?>
<section class="section"><div class="wrap"><div class="eyebrow">Need-to-Know Queue</div><h2><?=h((string)($queue['candidate_count'] ?? 0))?> candidates</h2><p class="muted">Last refresh: <?=h((string)($queue['refreshed_at_utc'] ?? 'not run yet'))?>. Priority: people asking → evergreen consequential questions → current evidence signals.</p>
<div class="grid">
<?php foreach (array_slice($queue['candidates'] ?? [], 0, 18) as $c): ?>
<article class="card"><div class="case-id"><?=h((string)($c['trial_lane'] ?? 'Truth Trial'))?> · <?=h((string)($c['source'] ?? ''))?></div><h3><?=h((string)($c['title'] ?? ''))?></h3><p class="muted"><?=h((string)($c['why_trial_worthy'] ?? ''))?></p><?php if (!empty($c['summary'])): ?><p><?=h((string)$c['summary'])?></p><?php endif; ?><?php if (!empty($c['corroborating_sources'])): ?><p><strong>Also appearing in:</strong> <?=h(implode(', ', $c['corroborating_sources']))?></p><?php endif; ?><?php if (!empty($c['url'])): ?><p><a href="<?=h(tw_safe_url((string)$c['url']))?>" rel="noopener noreferrer" target="_blank">Open source signal</a></p><?php endif; ?>
<form action="/truth/daily/investigate.php" method="post"><input type="hidden" name="key" value="<?=h($key)?>"><input type="hidden" name="id" value="<?=h((string)($c['id'] ?? ''))?>"><button class="button" type="submit">Put This Question on Trial</button></form></article>
<?php endforeach; ?>
</div></div></section>
<?php if ($draft !== []): ?>
<section class="section"><div class="wrap"><article class="card"><div class="eyebrow">Human Review Required</div><h2>Edit before publishing</h2><p class="muted">The AI/web-search pass is a research draft, not an automatic verdict. Correct wording, remove anything unsupported, and publish only after you are satisfied with the evidence trail.</p>
<form class="challenge" action="/truth/daily/publish.php" method="post"><input type="hidden" name="key" value="<?=h($key)?>">
<label>Headline</label><input name="headline" value="<?=h((string)($draft['headline'] ?? ''))?>">
<label>Claim</label><textarea name="claim"><?=h((string)($draft['claim'] ?? ''))?></textarea>
<label>Summary</label><textarea name="summary"><?=h((string)($draft['summary'] ?? ''))?></textarea>
<label>What's Proven — one item per line</label><textarea name="proven"><?=h(join_lines($draft['proven'] ?? []))?></textarea>
<label>What's Strongly Indicated — one item per line</label><textarea name="strongly_indicated"><?=h(join_lines($draft['strongly_indicated'] ?? []))?></textarea>
<label>Contradictions & Missing Pieces — one item per line</label><textarea name="contradictions_missing_pieces"><?=h(join_lines($draft['contradictions_missing_pieces'] ?? []))?></textarea>
<label>Motives / Incentives / Who Benefits — one item per line</label><textarea name="motives_incentives_who_benefits"><?=h(join_lines($draft['motives_incentives_who_benefits'] ?? []))?></textarea>
<label>Logic & Common Sense — one item per line</label><textarea name="logic_common_sense"><?=h(join_lines($draft['logic_common_sense'] ?? []))?></textarea>
<label>Counterevidence / Alternatives — one item per line</label><textarea name="counterevidence_alternatives"><?=h(join_lines($draft['counterevidence_alternatives'] ?? []))?></textarea>
<label>Unknowns — one item per line</label><textarea name="unknowns"><?=h(join_lines($draft['unknowns'] ?? []))?></textarea>
<label>Bobinated Opinion</label><textarea name="bobinated_opinion"><?=h((string)($draft['bobinated_opinion']['opinion'] ?? ''))?></textarea>
<label>Bobinated reasoning — one item per line</label><textarea name="bobinated_reasoning"><?=h(join_lines($draft['bobinated_opinion']['reasoning'] ?? []))?></textarea>
<label>What would change the finding — one item per line</label><textarea name="what_would_change_the_finding"><?=h(join_lines($draft['what_would_change_the_finding'] ?? []))?></textarea>
<label>Confidence</label><select name="confidence"><option value="high" <?=($draft['confidence']??'')==='high'?'selected':''?>>High</option><option value="medium" <?=($draft['confidence']??'')==='medium'?'selected':''?>>Medium</option><option value="low" <?=($draft['confidence']??'')==='low'?'selected':''?>>Low</option></select>
<h3 style="margin-top:28px">Sources returned by the research pass</h3><ul class="rules"><?php foreach (($draft['sources'] ?? []) as $s): ?><li><a href="<?=h(tw_safe_url((string)($s['url'] ?? '')))?>" target="_blank" rel="noopener noreferrer"><?=h((string)($s['title'] ?? $s['url'] ?? 'Source'))?></a> — <?=h((string)($s['role'] ?? ''))?></li><?php endforeach; ?></ul>
<button class="button" type="submit">Approve & Publish Today's Trial</button></form>
</article></div></section>
<?php endif; ?>
</main><footer class="footer"><div class="wrap"><strong>QUESTION EVERYTHING!</strong> · The Meat Desk · <strong>YOU BE THE JUDGE.</strong></div></footer></body></html>
