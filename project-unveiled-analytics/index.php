<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)) ?: dirname(__DIR__);
$privateRoot = dirname($docRoot) . '/site-private/project-unveiled-analytics';
$configFile = $privateRoot . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    echo '<h1>Analytics is not configured.</h1>';
    exit;
}
$config = require $configFile;
if (!is_array($config) || empty($config['password_hash'])) {
    http_response_code(503);
    echo '<h1>Analytics configuration is invalid.</h1>';
    exit;
}

session_name('pu_analytics_dashboard');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/project-unveiled-analytics/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: /project-unveiled-analytics/');
    exit;
}

if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$loginError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['password'])) {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['csrf'], $csrf)) {
        $loginError = 'Please reload and try again.';
    } elseif (password_verify((string)$_POST['password'], (string)$config['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['last_seen'] = time();
        header('Location: /project-unveiled-analytics/');
        exit;
    } else {
        usleep(350000);
        $loginError = 'Incorrect dashboard password.';
    }
}

$authenticated = !empty($_SESSION['authenticated']);
if ($authenticated) {
    $lastSeen = (int)($_SESSION['last_seen'] ?? 0);
    if ($lastSeen > 0 && time() - $lastSeen > 14400) {
        $_SESSION = [];
        session_destroy();
        $authenticated = false;
    } else {
        $_SESSION['last_seen'] = time();
    }
}

function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function pct(float $value): string { return number_format($value, 1) . '%'; }
function labelPath(string $path): string {
    if ($path === '/') return 'Homepage';
    if ($path === '/book/read/' || $path === '/book/read') return 'Reader Directory';
    if (preg_match('/chapter-(\d{2})\.html/', $path, $m)) return 'Chapter ' . (int)$m[1];
    return $path;
}

if (!$authenticated) {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Project Unveiled Traffic Dashboard</title>';
    echo '<style>body{margin:0;background:#070605;color:#f4ead2;font-family:Arial,sans-serif;min-height:100vh;display:grid;place-items:center}.panel{width:min(440px,calc(100% - 36px));box-sizing:border-box;background:#12100c;border:1px solid #d7ad51;padding:30px;box-shadow:0 25px 80px #000}h1{font-family:Georgia,serif;color:#f3d47d;margin-top:0}label{display:block;margin:18px 0 7px}input{width:100%;box-sizing:border-box;padding:13px;background:#080706;border:1px solid #7e692f;color:#fff;font-size:17px}button{width:100%;margin-top:16px;padding:13px;border:0;background:#d7ad51;color:#080706;font-weight:800;font-size:16px;cursor:pointer}.bad{color:#ff958f}.muted{color:#bdb49f;font-size:14px}</style></head><body><main class="panel"><h1>Project Unveiled</h1><h2>Private Traffic Dashboard</h2><p>View reading sessions, chapter activity, campaign sources, support-page visits, and PayPal clicks.</p>';
    if ($loginError !== '') echo '<p class="bad">' . h($loginError) . '</p>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '"><label for="password">Dashboard password</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus><button type="submit">Open Dashboard</button></form><p class="muted">No cookies or IP addresses are used for reader tracking. The login uses a secure session cookie only for this private dashboard.</p></main></body></html>';
    exit;
}

$range = (int)($_GET['range'] ?? 30);
if (!in_array($range, [7, 30, 90], true)) $range = 30;
$startDate = new DateTimeImmutable('-' . ($range - 1) . ' days', new DateTimeZone('UTC'));
$startDate = $startDate->setTime(0, 0, 0);
$dataDir = $privateRoot . '/data';
$events = [];
if (is_dir($dataDir)) {
    foreach (glob($dataDir . '/events-*.jsonl') ?: [] as $file) {
        if (!preg_match('/events-(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $m)) continue;
        if ($m[1] < $startDate->format('Y-m-d')) continue;
        $handle = @fopen($file, 'rb');
        if (!$handle) continue;
        while (($line = fgets($handle)) !== false) {
            $row = json_decode($line, true);
            if (is_array($row)) $events[] = $row;
        }
        fclose($handle);
    }
}
usort($events, fn(array $a, array $b): int => strcmp((string)($a['t'] ?? ''), (string)($b['t'] ?? '')));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="project-unveiled-traffic-' . $range . '-days.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['timestamp_utc','event','path','chapter','session','source','medium','campaign','content','referrer','target','label']);
    foreach ($events as $e) {
        fputcsv($out, [
            $e['t'] ?? '', $e['event'] ?? '', $e['path'] ?? '', $e['chapter'] ?? '', $e['session'] ?? '',
            $e['source'] ?? '', $e['medium'] ?? '', $e['campaign'] ?? '', $e['content'] ?? '',
            $e['referrer'] ?? '', $e['target'] ?? '', $e['label'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

$counts = [];
$sessions = [];
$sessionPaths = [];
$pages = [];
$chapters = [];
$sources = [];
$campaigns = [];
$daily = [];
foreach ($events as $event) {
    $name = (string)($event['event'] ?? 'unknown');
    $counts[$name] = ($counts[$name] ?? 0) + 1;
    $sid = (string)($event['session'] ?? 'anonymous');
    $sessions[$sid] = true;
    $path = (string)($event['path'] ?? '/');
    $date = substr((string)($event['t'] ?? ''), 0, 10);
    if ($date !== '') $daily[$date] = ($daily[$date] ?? 0) + ($name === 'pageview' ? 1 : 0);
    if ($name === 'pageview') {
        $pages[$path] = ($pages[$path] ?? 0) + 1;
        $sessionPaths[$sid][$path] = true;
        $chapter = (int)($event['chapter'] ?? 0);
        if ($chapter >= 1 && $chapter <= 13) $chapters[$chapter] = ($chapters[$chapter] ?? 0) + 1;
        $source = trim((string)($event['source'] ?? ''));
        if ($source === '') {
            $ref = trim((string)($event['referrer'] ?? ''));
            if ($ref !== '' && !str_starts_with($ref, 'bobsome1.com') && !str_starts_with($ref, 'www.bobsome1.com')) {
                $source = explode('/', $ref, 2)[0];
            } else {
                $source = 'Direct / internal';
            }
        }
        $sources[$source] = ($sources[$source] ?? 0) + 1;
        $campaign = trim((string)($event['campaign'] ?? ''));
        if ($campaign !== '') $campaigns[$campaign] = ($campaigns[$campaign] ?? 0) + 1;
    }
}

$completedSessions = 0;
foreach ($sessionPaths as $paths) {
    $hasOne = isset($paths['/book/read/chapter-01.html']);
    $hasThirteen = isset($paths['/book/read/chapter-13.html']);
    if ($hasOne && $hasThirteen) $completedSessions++;
}

$pageviews = (int)($counts['pageview'] ?? 0);
$sessionCount = count($sessions);
$paypalClicks = (int)($counts['paypal_click'] ?? 0);
$supportClicks = (int)($counts['support_page_click'] ?? 0);
$shares = (int)($counts['share_click'] ?? 0);
$nextClicks = (int)($counts['chapter_next'] ?? 0);
$pageviewsPerSession = $sessionCount ? $pageviews / $sessionCount : 0;
$paypalRate = $sessionCount ? ($paypalClicks / $sessionCount) * 100 : 0;
$completionRate = $sessionCount ? ($completedSessions / $sessionCount) * 100 : 0;

arsort($pages); arsort($chapters); arsort($sources); arsort($campaigns); ksort($daily);
$maxDaily = max([1, ...array_values($daily)]);

$campaignLinks = [
    'Facebook launch' => 'https://bobsome1.com/book/read/?utm_source=facebook&utm_medium=organic_social&utm_campaign=project_unveiled_launch&utm_content=main_launch',
    'Facebook Chapter 1' => 'https://bobsome1.com/book/read/chapter-01.html?utm_source=facebook&utm_medium=organic_social&utm_campaign=project_unveiled_launch&utm_content=chapter_01',
    'Instagram bio' => 'https://bobsome1.com/book/read/?utm_source=instagram&utm_medium=organic_social&utm_campaign=project_unveiled_launch&utm_content=bio',
    'Facebook support post' => 'https://bobsome1.com/book/read/support-right-hand.html?utm_source=facebook&utm_medium=organic_social&utm_campaign=project_unveiled_launch&utm_content=support_post',
    'Direct PayPal' => 'https://paypal.me/Bobsome1975',
];

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Project Unveiled Traffic Dashboard</title>
<style>
:root{--bg:#070605;--panel:#12100c;--gold:#d7ad51;--gold2:#f3d47d;--cream:#f4ead2;--muted:#bdb49f;--green:#65d889;--red:#ff958f}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--cream);font-family:Arial,sans-serif;line-height:1.5}a{color:var(--gold2)}header{position:sticky;top:0;z-index:5;background:rgba(7,6,5,.96);border-bottom:1px solid #3d3421}.wrap{max-width:1180px;margin:auto;padding:22px}.top{display:flex;gap:18px;align-items:center;justify-content:space-between;flex-wrap:wrap}h1,h2,h3{font-family:Georgia,serif}h1{margin:0;color:var(--gold2);font-size:clamp(24px,4vw,38px)}.nav a{display:inline-block;margin-left:10px;padding:9px 12px;border:1px solid #7e692f;text-decoration:none}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.card,.panel{background:var(--panel);border:1px solid #3f3725;padding:18px}.metric{font-size:clamp(26px,4vw,42px);font-weight:800;color:var(--gold2)}.label{color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:.08em}.sub{color:var(--muted);font-size:13px}.sections{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;border-bottom:1px solid #302a1d;padding:9px 7px;vertical-align:top}.table th{color:var(--gold2)}.barrow{display:grid;grid-template-columns:110px 1fr 48px;gap:10px;align-items:center;margin:8px 0}.bar{height:13px;background:#292315}.bar span{display:block;height:100%;background:linear-gradient(90deg,#8d6423,var(--gold2))}.campaign{display:grid;grid-template-columns:190px 1fr auto;gap:10px;align-items:center;margin:10px 0}.campaign code{word-break:break-all;color:#d8cfba}.copy{border:1px solid var(--gold);background:transparent;color:var(--gold2);padding:8px 10px;cursor:pointer}.notice{border-left:4px solid var(--gold);padding:13px 16px;background:#0d0c09;margin:18px 0}.empty{color:var(--muted);padding:24px;text-align:center}.good{color:var(--green)}.warning{color:#ffd166}@media(max-width:880px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sections{grid-template-columns:1fr}.campaign{grid-template-columns:1fr}.nav a{margin:4px 0 4px 8px}}@media(max-width:520px){.grid{grid-template-columns:1fr 1fr}.wrap{padding:14px}.card{padding:14px}.barrow{grid-template-columns:85px 1fr 38px}}
</style>
</head>
<body>
<header><div class="wrap top"><div><div class="label">Private analytics</div><h1>Project Unveiled Traffic Dashboard</h1></div><nav class="nav"><a href="?range=7">7 days</a><a href="?range=30">30 days</a><a href="?range=90">90 days</a><a href="?range=<?= $range ?>&export=csv">Export CSV</a><a href="?logout=1">Log out</a></nav></div></header>
<main class="wrap">
<div class="notice"><strong>What this measures:</strong> anonymous reading sessions, page views, chapter movement, shares, support-page visits, and PayPal link clicks. It does <strong>not</strong> confirm whether a PayPal payment was completed.</div>
<section class="grid">
<div class="card"><div class="label">Reading sessions</div><div class="metric"><?= number_format($sessionCount) ?></div><div class="sub">Anonymous browser-tab sessions</div></div>
<div class="card"><div class="label">Page views</div><div class="metric"><?= number_format($pageviews) ?></div><div class="sub"><?= number_format($pageviewsPerSession, 1) ?> pages per session</div></div>
<div class="card"><div class="label">Reached Chapter 13</div><div class="metric"><?= number_format((int)($chapters[13] ?? 0)) ?></div><div class="sub"><?= pct($completionRate) ?> full-session completion estimate</div></div>
<div class="card"><div class="label">PayPal clicks</div><div class="metric"><?= number_format($paypalClicks) ?></div><div class="sub"><?= pct($paypalRate) ?> of sessions clicked PayPal</div></div>
<div class="card"><div class="label">Support page clicks</div><div class="metric"><?= number_format($supportClicks) ?></div><div class="sub">Interest before PayPal</div></div>
<div class="card"><div class="label">Chapter-next clicks</div><div class="metric"><?= number_format($nextClicks) ?></div><div class="sub">Reader progression</div></div>
<div class="card"><div class="label">Share clicks</div><div class="metric"><?= number_format($shares) ?></div><div class="sub">On-site share controls</div></div>
<div class="card"><div class="label">Reporting window</div><div class="metric"><?= $range ?></div><div class="sub">days ending today</div></div>
</section>

<?php if (!$events): ?>
<div class="panel empty" style="margin-top:18px"><h2>No traffic recorded yet</h2><p>Open the public reader in a separate browser tab, then reload this dashboard. Tracking starts after this installer is live.</p></div>
<?php else: ?>
<section class="sections">
<div class="panel"><h2>Daily page views</h2><?php foreach ($daily as $date => $value): ?><div class="barrow"><span><?= h(substr($date,5)) ?></span><div class="bar"><span style="width:<?= max(2,($value/$maxDaily)*100) ?>%"></span></div><strong><?= number_format($value) ?></strong></div><?php endforeach; ?></div>
<div class="panel"><h2>Top pages</h2><table class="table"><thead><tr><th>Page</th><th>Views</th></tr></thead><tbody><?php foreach (array_slice($pages,0,12,true) as $path=>$value): ?><tr><td><?= h(labelPath((string)$path)) ?><div class="sub"><?= h($path) ?></div></td><td><?= number_format($value) ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="panel"><h2>Chapter readership</h2><table class="table"><thead><tr><th>Chapter</th><th>Views</th></tr></thead><tbody><?php for($i=1;$i<=13;$i++): ?><tr><td>Chapter <?= $i ?></td><td><?= number_format((int)($chapters[$i] ?? 0)) ?></td></tr><?php endfor; ?></tbody></table></div>
<div class="panel"><h2>Traffic sources</h2><table class="table"><thead><tr><th>Source</th><th>Page views</th></tr></thead><tbody><?php foreach (array_slice($sources,0,12,true) as $source=>$value): ?><tr><td><?= h($source) ?></td><td><?= number_format($value) ?></td></tr><?php endforeach; ?></tbody></table><?php if ($campaigns): ?><h3>Campaigns</h3><table class="table"><?php foreach ($campaigns as $name=>$value): ?><tr><td><?= h($name) ?></td><td><?= number_format($value) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:18px"><h2>Copy-and-paste campaign links</h2><p class="sub">Use these exact links so the dashboard can identify where readers came from.</p><?php foreach($campaignLinks as $name=>$url): ?><div class="campaign"><strong><?= h($name) ?></strong><code><?= h($url) ?></code><button class="copy" type="button" data-copy="<?= h($url) ?>">Copy</button></div><?php endforeach; ?></section>
<section class="panel" style="margin-top:18px"><h2>Privacy design</h2><p>No advertising tracker is installed. No IP address is stored. No cross-site cookie is used. A random session identifier exists only in the visitor’s current browser tab. Do Not Track and Global Privacy Control are respected.</p><p><a href="/privacy.html" target="_blank" rel="noopener">View the public privacy notice</a></p></section>
</main>
<script>document.querySelectorAll('[data-copy]').forEach(function(button){button.addEventListener('click',function(){var value=button.getAttribute('data-copy')||'';navigator.clipboard.writeText(value).then(function(){var old=button.textContent;button.textContent='Copied';setTimeout(function(){button.textContent=old},1200)}).catch(function(){window.prompt('Copy this link:',value)})})});</script>
</body></html>
