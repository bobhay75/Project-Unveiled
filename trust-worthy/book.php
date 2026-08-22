<?php
$member = isset($_GET['member']) ? (string) $_GET['member'] : 'TW-MEMBER-founder';
if (!preg_match('/^TW-MEMBER-[A-Za-z0-9_-]+$/', $member)) { http_response_code(400); exit('Invalid member ID.'); }
$profilePath = __DIR__ . '/profiles-public/' . $member . '.json';
if (!is_file($profilePath)) { http_response_code(404); exit('Member profile not found.'); }
$profile = json_decode((string) file_get_contents($profilePath), true);
if (!is_array($profile)) { http_response_code(500); exit('Profile record is invalid.'); }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function first_name($name){ $parts = preg_split('/\s+/', trim((string)$name)); return $parts[0] ?: 'Member'; }
function stance_label($stance){
  $map=['holds-p'=>'Holds P','holds-not-p'=>'Holds not-P','unresolved'=>'Unresolved','not-investigated'=>'Not yet investigated'];
  return $map[$stance] ?? $stance;
}
function qualifying_chapter($p){
  return !empty($p['linked_truth_trials']) || !empty($p['resolved_challenges']) || count($p['revision_history'] ?? []) > 1;
}
$name = $profile['display_name'] ?? 'Member';
$shortName = first_name($name);
$title = 'The First Book of ' . $shortName;
$positions = $profile['positions'] ?? [];
$qualified = array_values(array_filter($positions, 'qualifying_chapter'));
$working = array_values(array_filter($positions, static function($p){ return !qualifying_chapter($p); }));
?>
<!doctype html><html lang="en-US"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> | Trust-Worthy AI</title><meta name="description" content="A living, versioned record of beliefs examined through Trust-Worthy Truth Trials, challenges, and independent research."><link rel="stylesheet" href="/trust-worthy/assets/trust-worthy.css"><style>
.book-cover{padding:6rem 1.5rem;text-align:center;border:1px solid rgba(215,173,81,.5);background:linear-gradient(145deg,#171109,#070503);box-shadow:0 28px 80px rgba(0,0,0,.45)}
.book-cover h1{font-size:clamp(3rem,8vw,6rem);margin:.7rem 0;color:#fff3d7}.book-cover .subtitle{color:#d7ad51;letter-spacing:.18em;text-transform:uppercase;font-weight:900}.book-cover .author{margin-top:2rem;color:#b9ad95}
.declaration{max-width:850px;margin:2rem auto;padding:2rem;border-left:4px solid #d7ad51;background:rgba(215,173,81,.06);font:700 1.25rem/1.7 Georgia,"Times New Roman",serif}
.book-chapter{margin:1.2rem 0;padding:1.6rem;border:1px solid rgba(215,173,81,.28);background:#11100d}.book-chapter h2{margin:.3rem 0 1rem;color:#fff3d7}.chapter-meta{color:#d7ad51;text-transform:uppercase;letter-spacing:.12em;font-size:.72rem;font-weight:900}.pair{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.pair>div{padding:1rem;border:1px solid rgba(255,255,255,.1);background:#080604}.muted-note{color:#b9ad95}.pending{opacity:.78;border-style:dashed}@media(max-width:760px){.pair{grid-template-columns:1fr}}
</style></head><body>
<header class="site-header"><div class="container nav"><a class="brand" href="/">PROJECT <span>UNVEILED</span></a><div><a href="/trust-worthy/">Trust-Worthy</a><a href="/trust-worthy/profile.php?member=<?= rawurlencode($member) ?>">Theology Profile</a></div></div></header>
<main>
<section class="section"><div class="container"><div class="book-cover"><div class="subtitle">A record of what I put on trial</div><h1><?= e($title) ?></h1><div class="author">by <?= e($name) ?></div><p class="muted-note">Living Digital Edition · generated from public Theology Profile and versioned Trust-Worthy investigations</p></div>
<div class="declaration">This book does not claim to be Scripture. It is not a record of what I was told to believe. It is a record of what I believed strongly enough to challenge—and what remained after the challenge.<br><br>Some beliefs survived. Some changed. Some were destroyed. Some questions remain unanswered.<br><br><strong>I reserve the right to be wrong. Truth does not.</strong></div></div></section>
<section class="section"><div class="container"><div class="eyebrow">Table of contents</div><h2>Established and examined chapters</h2><?php if(!$qualified): ?><p class="muted-note">No completed qualifying investigations yet.</p><?php else: ?><ol class="clean"><?php foreach($qualified as $p): ?><li><a href="#<?= e($p['position_id']) ?>"><?= e($p['topic']) ?></a></li><?php endforeach; ?></ol><?php endif; ?></div></section>
<?php foreach($qualified as $index=>$p): ?>
<section class="section"><div class="container"><article class="book-chapter" id="<?= e($p['position_id']) ?>"><div class="chapter-meta">Chapter <?= $index+1 ?> · <?= e($p['topic']) ?></div><h2><?= e($p['topic']) ?></h2><div class="pair"><div><strong>P</strong><p><?= e($p['p']) ?></p></div><div><strong>not-P</strong><p><?= e($p['not_p'] ?? '') ?></p></div></div><p><strong>Current position:</strong> <?= e(stance_label($p['stance'] ?? '')) ?></p><p><strong>What I believe now:</strong> <?= e($p['member_explanation'] ?? '') ?></p>
<h3>Evidence I have offered</h3><ul class="clean"><?php foreach(($p['evidence'] ?? []) as $ev): ?><li><strong><?= e($ev['position'] ?? '') ?>:</strong> <?= e($ev['citation'] ?? 'Uncited') ?> — <?= e($ev['note'] ?? '') ?></li><?php endforeach; ?></ul>
<?php if(!empty($p['linked_truth_trials'])): ?><h3>Linked Truth Trials</h3><ul class="clean"><?php foreach($p['linked_truth_trials'] as $trial): ?><li><a href="/trust-worthy/case.php?id=<?= rawurlencode($trial) ?>"><?= e($trial) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
<h3>Revision Ledger</h3><div class="history"><?php foreach(($p['revision_history'] ?? []) as $r): ?><div class="history-item"><strong><?= e($r['date'] ?? '') ?></strong> · <?= e($r['from'] ?? '') ?> → <?= e($r['to'] ?? '') ?><br><span class="muted"><?= e($r['reason'] ?? '') ?></span></div><?php endforeach; ?></div>
<p class="muted-note">This chapter is a snapshot of a living record. Reopen the underlying proposition by challenging the member's public Theology Profile or linked Truth Trial.</p><div class="actions"><a class="button" href="/trust-worthy/member-challenge.php?member=<?= rawurlencode($member) ?>&position=<?= rawurlencode($p['position_id']) ?>">Challenge This Chapter</a></div></article></div></section>
<?php endforeach; ?>
<?php if($working): ?><section class="section"><div class="container"><div class="eyebrow">Questions still under examination</div><h2>Not yet earned as full chapters</h2><?php foreach($working as $p): ?><article class="book-chapter pending"><div class="chapter-meta"><?= e($p['topic']) ?> · working proposition</div><h2><?= e($p['topic']) ?></h2><p><?= e($p['member_explanation'] ?? '') ?></p><p><strong>P:</strong> <?= e($p['p']) ?></p><p><strong>not-P:</strong> <?= e($p['not_p'] ?? '') ?></p><p class="muted-note">This position remains visible, but it does not become a completed book chapter until it passes through a qualifying Truth Trial, Battle, successful challenge, or substantive revision.</p></article><?php endforeach; ?></div></section><?php endif; ?>
</main><footer><div class="container">A personal book records the member's tested beliefs. It does not replace Scripture and cannot be purchased into agreement. <a href="/trust-worthy/profile.php?member=<?= rawurlencode($member) ?>">View the underlying Theology Profile.</a></div></footer></body></html>