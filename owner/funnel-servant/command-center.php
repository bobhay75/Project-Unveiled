<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow,noarchive');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
require __DIR__ . '/bootstrap.php';
try { $config = fs_config(); } catch (Throwable $e) { http_response_code(503); echo '<h1>Configuration unavailable</h1>'; exit; }
$env = fs_env();
session_name('pu_private_funnel_servant');
session_set_cookie_params(['lifetime'=>0,'path'=>'/owner/funnel-servant/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
session_start();
if (empty($_SESSION['authenticated']) || empty($_SESSION['last_seen']) || time()-(int)$_SESSION['last_seen']>14400) {
    header('Location: /owner/funnel-servant/'); exit;
}
$_SESSION['last_seen']=time();
function v7h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function v7_count_json(string $file): int { $d=fs_read_json($file,[]); return is_array($d)?count($d):0; }
function v7_count_jsonl(string $file): int { if(!is_file($file)) return 0; $n=0; $h=@fopen($file,'rb'); if(!$h)return 0; while(($l=fgets($h))!==false){if(trim($l)!=='')$n++;} fclose($h); return $n; }
$settings=fs_settings();
$drafts=fs_read_json($env['data_dir'].'/drafts.json',[]); if(!is_array($drafts))$drafts=[];
$activeDrafts=0;$blockedDrafts=0;foreach($drafts as $d){if(!is_array($d))continue;$blocked=!empty($d['quality_blocked'])||(($d['quality_score']??0)<70);if($blocked)$blockedDrafts++;else$activeDrafts++;}
$community=fs_read_json($env['data_dir'].'/community-submissions.json',[]); if(!is_array($community))$community=[];
$pending=0;$approved=0;foreach($community as $r){if(!is_array($r))continue;$s=(string)($r['status']??'pending');if($s==='pending')$pending++;if($s==='approved')$approved++;}
$activity=v7_count_jsonl($env['data_dir'].'/social-activity.jsonl');
$logs=v7_count_jsonl($env['data_dir'].'/action-log.jsonl');
$analytics=fs_analytics_summary(7,0);
$lastReview=fs_read_json($env['data_dir'].'/last-review.json',[]); if(!is_array($lastReview))$lastReview=[];
$modules=[
 ['title'=>'Autopilot','desc'=>'Review attributed traffic and decide the next move.','href'=>'/owner/funnel-servant/?tab=autopilot','status'=>!empty($settings['enabled'])?'AUTO ON':'AUTO OFF','metric'=>'7-day sessions: '.(int)($analytics['sessions']??0)],
 ['title'=>'Campaign Studio','desc'=>'Build, quality-check, and approve reader-facing campaigns.','href'=>'/owner/funnel-servant/campaign-studio.php','status'=>$activeDrafts.' active','metric'=>$blockedDrafts.' legacy blocked'],
 ['title'=>'Post Now','desc'=>'Prepare platform copy, media, and tracked destinations.','href'=>'/owner/funnel-servant/?tab=publisher','status'=>'Manual control','metric'=>'Owner confirmation required'],
 ['title'=>'Publishing Report','desc'=>'Separate preparation events from confirmed public posts.','href'=>'/owner/funnel-servant/?tab=activity','status'=>$activity.' events','metric'=>'Grouped and filterable'],
 ['title'=>'Reader Community','desc'=>'Moderate comments, reviews, replies, and testimonials.','href'=>'/owner/funnel-servant/?tab=community','status'=>$pending.' pending','metric'=>$approved.' approved'],
 ['title'=>'Analytics','desc'=>'Inspect traffic sources, chapters, progression, and support clicks.','href'=>'/owner/funnel-servant/?tab=dashboard','status'=>(int)($analytics['pageviews']??0).' views','metric'=>'Last 7 days'],
 ['title'=>'Direct Chat','desc'=>'Issue private commands and review guarded actions.','href'=>'/owner/funnel-servant/?tab=chat','status'=>'Private','metric'=>'No external AI calls'],
 ['title'=>'System Log','desc'=>'Inspect activity history, settings changes, and moderation actions.','href'=>'/owner/funnel-servant/?tab=logs','status'=>$logs.' records','metric'=>'Audit preserved'],
];
$routeChecks=[
 ['Owner application',__DIR__.'/index.php'],['Bootstrap',__DIR__.'/bootstrap.php'],['Stylesheet',__DIR__.'/app.css'],
 ['Book hub',dirname(__DIR__,2).'/book/index.html'],['Reader',dirname(__DIR__,2).'/book/read/index.html'],['Analytics directory',$env['analytics_dir']],['Private data directory',$env['data_dir']],
];
$healthy=0;foreach($routeChecks as $c){if(file_exists($c[1])||is_dir($c[1]))$healthy++;}
$healthPct=(int)round(($healthy/max(1,count($routeChecks)))*100);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Project Unveiled OS · Command Center</title><link rel="stylesheet" href="v7-foundation.css"><link rel="stylesheet" href="v7-1-shell.css"><script defer src="v7-2-shell.js"></script></head>
<body><header class="top"><div><div class="eyebrow">PROJECT UNVEILED OS · V7.2 CAMPAIGN STUDIO</div><h1>Owner Command Center</h1><p>One authenticated launch point for publishing, readers, analytics, and system control.</p></div><div class="health"><strong><?=v7h((string)$healthPct)?>%</strong><span>system routes healthy</span></div></header>
<nav class="quick"><a class="active" href="command-center.php">Command Center</a><a href="search.php">Search</a><a href="notifications.php">Notifications</a><button class="command-launch" type="button" data-command-launch>Quick Command</button><a href="/owner/funnel-servant/?tab=autopilot">Autopilot</a><a href="/owner/funnel-servant/campaign-studio.php">Campaign Studio</a><a href="/owner/funnel-servant/?tab=publisher">Post Now</a><a href="/owner/funnel-servant/?tab=activity">Reports</a><a href="/owner/funnel-servant/?tab=community">Readers</a><a href="/owner/funnel-servant/?logout=1">Log out</a><a href="/owner/funnel-servant/subscribers.php">Reader Signups</a></nav>
<main>
<section class="priority"><div><div class="eyebrow">TODAY'S CONTROL POINT</div><h2><?= $pending>0 ? 'Moderate reader submissions first' : ($activeDrafts>0 ? 'Review the active campaign draft' : 'Run an attributed traffic review') ?></h2><p><?= $pending>0 ? 'Nothing appears publicly until you approve it.' : ($activeDrafts>0 ? 'A quality-approved campaign is waiting for owner review.' : 'Generate the next evidence-based campaign move without treating direct traffic as a known source.') ?></p></div><a class="primary" href="<?= $pending>0?'/?tab=community':($activeDrafts>0?'/owner/funnel-servant/campaign-studio.php':'/owner/funnel-servant/?tab=autopilot') ?>">Open priority</a></section>
<section class="stats"><div><span>Active drafts</span><strong><?=$activeDrafts?></strong></div><div><span>Pending readers</span><strong><?=$pending?></strong></div><div><span>7-day sessions</span><strong><?=(int)($analytics['sessions']??0)?></strong></div><div><span>7-day pageviews</span><strong><?=(int)($analytics['pageviews']??0)?></strong></div></section>
<section><div class="section-head"><div><div class="eyebrow">UNIFIED MODULE MAP</div><h2>Everything has one place</h2></div></div><div class="grid">
<?php foreach($modules as $m): ?><a class="card" href="<?=v7h($m['href'])?>"><div class="card-top"><h3><?=v7h($m['title'])?></h3><span><?=v7h($m['status'])?></span></div><p><?=v7h($m['desc'])?></p><small><?=v7h($m['metric'])?></small></a><?php endforeach; ?>
</div></section>
<section class="two"><article><div class="eyebrow">CANONICAL OWNER ROUTES</div><h2>Stable navigation</h2><p>All modules remain under the authenticated <code>/owner/funnel-servant/</code> session path. This prevents installer buttons from jumping outside the valid login cookie scope.</p><div class="routes"><code>/owner/funnel-servant/command-center.php</code><code>/owner/funnel-servant/?tab=drafts</code><code>/owner/funnel-servant/?tab=publisher</code><code>/owner/funnel-servant/?tab=activity</code></div></article><article><div class="eyebrow">SYSTEM HEALTH</div><h2><?=$healthy?> of <?=count($routeChecks)?> checks passed</h2><ul><?php foreach($routeChecks as $c): $ok=file_exists($c[1])||is_dir($c[1]); ?><li class="<?=$ok?'ok':'bad'?>"><span><?=$ok?'PASS':'CHECK'?></span><?=v7h($c[0])?></li><?php endforeach; ?></ul></article></section>
</main><footer>Project Unveiled OS · v7.1 · Existing v6 data preserved · Owner-controlled publishing</footer></body></html>
