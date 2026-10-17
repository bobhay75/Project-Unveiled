<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$requestMethod = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($requestMethod, ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    echo '<h1>Method not allowed.</h1>';
    exit;
}

$privateRoot = pu_analytics_private_root();
$configFile = $privateRoot . '/config.php';
try {
    pu_analytics_prepare_private_root($privateRoot);
    $privateConfig = pu_analytics_load_dashboard_config($configFile);
} catch (Throwable $error) {
    http_response_code(503);
    echo '<h1>Analytics is not configured.</h1>';
    exit;
}
$limits = pu_analytics_config();

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name('pu_analytics_dashboard');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/project-unveiled-analytics/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (!@session_start()) {
    http_response_code(503);
    echo '<h1>Analytics login is temporarily unavailable.</h1>';
    exit;
}

if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$authenticated = !empty($_SESSION['authenticated']);
if ($authenticated) {
    $lastSeen = (int)($_SESSION['last_seen'] ?? 0);
    if ($lastSeen <= 0 || time() - $lastSeen > 14400) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        $authenticated = false;
    } else {
        $_SESSION['last_seen'] = time();
    }
}

$loginError = '';
if ($requestMethod === 'POST') {
    try {
        $post = pu_analytics_dashboard_post(2048);
    } catch (PuAnalyticsException $error) {
        http_response_code($error->httpStatus);
        $post = [];
        $loginError = 'Please reload and try again.';
    }
    $action = (string)($post['action'] ?? '');
    $csrf = (string)($post['csrf'] ?? '');
    $validOrigin = pu_analytics_request_origin_is_allowed(false);
    if ($post === []) {
        // The bounded form parser already chose the response status.
    } elseif (!$validOrigin || !preg_match('/^[a-f0-9]{32}$/D', $csrf) || !hash_equals((string)($_SESSION['csrf'] ?? ''), $csrf)) {
        http_response_code(403);
        $loginError = 'Please reload and try again.';
    } elseif ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Strict',
            ]);
        }
        session_destroy();
        header('Location: /project-unveiled-analytics/');
        exit;
    } elseif ($action === 'login' && !$authenticated) {
        $password = (string)($post['password'] ?? '');
        if ($password === '' || strlen($password) > 512) {
            http_response_code(422);
            $loginError = 'Incorrect dashboard password.';
        } else {
            try {
                pu_analytics_consume_rate(
                    $privateRoot,
                    'dashboard-login',
                    (int)$limits['login_hour_limit'],
                    (int)$limits['login_day_limit'],
                    (int)$limits['max_rate_entries']
                );
                if (password_verify($password, $privateConfig['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['authenticated'] = true;
                    $_SESSION['last_seen'] = time();
                    $_SESSION['csrf'] = bin2hex(random_bytes(16));
                    header('Location: /project-unveiled-analytics/');
                    exit;
                }
                usleep(350000);
                $loginError = 'Incorrect dashboard password.';
            } catch (PuAnalyticsException $error) {
                http_response_code($error->httpStatus);
                if ($error->retryAfter !== null) {
                    header('Retry-After: ' . max(1, $error->retryAfter));
                }
                $loginError = $error->httpStatus === 429
                    ? 'Too many login attempts. Please wait and try again.'
                    : 'Login protection is temporarily unavailable.';
            }
        }
    } else {
        http_response_code(400);
        $loginError = 'Please reload and try again.';
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
    echo '<form method="post"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '"><label for="password">Dashboard password</label><input id="password" name="password" type="password" autocomplete="current-password" maxlength="512" required autofocus><button type="submit">Open Dashboard</button></form><p class="muted">No cookies or IP addresses are used for reader tracking. The login uses a secure session cookie only for this private dashboard.</p></main></body></html>';
    exit;
}

$range = is_string($_GET['range'] ?? null) ? (int)$_GET['range'] : 30;
if (!in_array($range, [7, 30, 90], true)) $range = 30;
$startDate = new DateTimeImmutable('-' . ($range - 1) . ' days', new DateTimeZone('UTC'));
$startDate = $startDate->setTime(0, 0, 0);
$dataDir = $privateRoot . '/data';
$counts = [];
$sessions = [];
$sessionPaths = [];
$pages = [];
$chapters = [];
$sources = [];
$campaigns = [];
$daily = [];
$eventTotal = 0;
$journeyShareClicks = 0;
$exportCsv = is_string($_GET['export'] ?? null) && $_GET['export'] === 'csv';

$aggregate = static function (array $event) use (
    &$counts,
    &$sessions,
    &$sessionPaths,
    &$pages,
    &$chapters,
    &$sources,
    &$campaigns,
    &$daily,
    &$journeyShareClicks
): void {
    $name = (string)($event['event'] ?? 'unknown');
    $counts[$name] = ($counts[$name] ?? 0) + 1;
    $sid = (string)($event['session'] ?? 'anonymous');
    $sessions[$sid] = true;
    $path = (string)($event['path'] ?? '/');
    if ($name === 'share_click' && in_array($path, ['/unveiled/confirmed.html', '/unveiled/welcome.html'], true)) {
        $journeyShareClicks++;
    }
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
};

try {
    if (is_dir($dataDir)) {
        pu_analytics_with_event_lock($privateRoot, LOCK_EX, static function () use ($dataDir, $limits): void {
            pu_analytics_prepare_dir($dataDir, 0700);
            pu_analytics_prune_event_files($dataDir, time(), (int)$limits['retention_days']);
            pu_analytics_sanitize_retained_event_files($dataDir, $limits);
        });
    }
    pu_analytics_with_event_lock($privateRoot, LOCK_SH, static function () use (
        $dataDir,
        $startDate,
        $limits,
        $range,
        $exportCsv,
        $aggregate,
        &$eventTotal
    ): void {
        $files = pu_analytics_select_event_files(
            $dataDir,
            $startDate->format('Y-m-d'),
            gmdate('Y-m-d'),
            (int)$limits['dashboard_max_files'],
            (int)$limits['max_daily_bytes']
        );

        if ($exportCsv) {
            // Validate the bounded snapshot before sending CSV headers. The
            // shared event lock keeps that snapshot stable for the export.
            $eventTotal = pu_analytics_visit_event_files(
                $files,
                (int)$limits['max_daily_bytes'],
                (int)$limits['max_event_line_bytes'],
                (int)$limits['dashboard_max_events'],
                static function (array $_event): void {}
            );
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="project-unveiled-traffic-' . $range . '-days.csv"');
            $out = fopen('php://output', 'wb');
            if (!is_resource($out)) {
                throw new PuAnalyticsException('The analytics export could not be opened.');
            }
            fputcsv($out, ['timestamp_utc','event','path','chapter','session','source','medium','campaign','content','referrer','target','label']);
            pu_analytics_visit_event_files(
                $files,
                (int)$limits['max_daily_bytes'],
                (int)$limits['max_event_line_bytes'],
                (int)$limits['dashboard_max_events'],
                static function (array $event) use ($out): void {
                    $cells = [
                        $event['t'] ?? '', $event['event'] ?? '', $event['path'] ?? '', $event['chapter'] ?? '',
                        $event['session'] ?? '', $event['source'] ?? '', $event['medium'] ?? '',
                        $event['campaign'] ?? '', $event['content'] ?? '', $event['referrer'] ?? '',
                        $event['target'] ?? '', $event['label'] ?? '',
                    ];
                    fputcsv($out, array_map('pu_analytics_csv_cell', $cells));
                }
            );
            fclose($out);
            return;
        }

        $eventTotal = pu_analytics_visit_event_files(
            $files,
            (int)$limits['max_daily_bytes'],
            (int)$limits['max_event_line_bytes'],
            (int)$limits['dashboard_max_events'],
            $aggregate
        );
    });
} catch (Throwable $error) {
    http_response_code(503);
    if ($exportCsv) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false]);
    } else {
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Analytics unavailable</title></head><body><h1>Analytics report unavailable.</h1><p>The bounded private-data check did not pass, so no report was produced.</p></body></html>';
    }
    exit;
}

if ($exportCsv) {
    exit;
}

$completedSessions = 0;
$journeyLandingSessions = 0;
$journeyCheckEmailSessions = 0;
$journeyWelcomeSessions = 0;
foreach ($sessionPaths as $paths) {
    $hasOne = isset($paths['/book/read/chapter-01.html']);
    $hasThirteen = isset($paths['/book/read/chapter-13.html']);
    if ($hasOne && $hasThirteen) $completedSessions++;
    if (isset($paths['/unveiled/']) || isset($paths['/unveiled/index.html'])) $journeyLandingSessions++;
    if (isset($paths['/unveiled/confirmed.html'])) $journeyCheckEmailSessions++;
    if (isset($paths['/unveiled/welcome.html'])) $journeyWelcomeSessions++;
}

$pageviews = (int)($counts['pageview'] ?? 0);
$sessionCount = count($sessions);
$paypalClicks = (int)($counts['paypal_click'] ?? 0);
$supportClicks = (int)($counts['support_page_click'] ?? 0);
$shares = (int)($counts['share_click'] ?? 0);
$nextClicks = (int)($counts['chapter_next'] ?? 0);
$journeyCtaClicks = (int)($counts['journey_cta_click'] ?? 0);
$journeySignupClicks = (int)($counts['journey_signup_click'] ?? 0);
$pageviewsPerSession = $sessionCount ? $pageviews / $sessionCount : 0;
$paypalRate = $sessionCount ? ($paypalClicks / $sessionCount) * 100 : 0;
$completionRate = $sessionCount ? ($completedSessions / $sessionCount) * 100 : 0;

arsort($pages); arsort($chapters); arsort($sources); arsort($campaigns); ksort($daily);
$maxDaily = max([1, ...array_values($daily)]);

$campaignLinks = [
    'Facebook Journey post' => 'https://bobsome1.com/unveiled/?utm_source=facebook&utm_medium=organic&utm_campaign=unveiled_14_day_sprint&utm_content=day_01_pinned',
    'Instagram Journey bio' => 'https://bobsome1.com/unveiled/?utm_source=instagram&utm_medium=organic&utm_campaign=unveiled_14_day_sprint&utm_content=bio',
    'YouTube Journey description' => 'https://bobsome1.com/unveiled/?utm_source=youtube&utm_medium=organic&utm_campaign=unveiled_14_day_sprint&utm_content=description',
    'Partner referral' => 'https://bobsome1.com/unveiled/?utm_source=partner&utm_medium=referral&utm_campaign=unveiled_14_day_sprint&utm_content=partner_name',
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
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--cream);font-family:Arial,sans-serif;line-height:1.5}a{color:var(--gold2)}header{position:sticky;top:0;z-index:5;background:rgba(7,6,5,.96);border-bottom:1px solid #3d3421}.wrap{max-width:1180px;margin:auto;padding:22px}.top{display:flex;gap:18px;align-items:center;justify-content:space-between;flex-wrap:wrap}h1,h2,h3{font-family:Georgia,serif}h1{margin:0;color:var(--gold2);font-size:clamp(24px,4vw,38px)}.nav{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.nav a,.nav button{display:inline-block;margin:0;padding:9px 12px;border:1px solid #7e692f;background:transparent;color:var(--gold2);font:inherit;text-decoration:none;cursor:pointer}.nav form{margin:0}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.card,.panel{background:var(--panel);border:1px solid #3f3725;padding:18px}.metric{font-size:clamp(26px,4vw,42px);font-weight:800;color:var(--gold2)}.label{color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:.08em}.sub{color:var(--muted);font-size:13px}.sections{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;border-bottom:1px solid #302a1d;padding:9px 7px;vertical-align:top}.table th{color:var(--gold2)}.barrow{display:grid;grid-template-columns:110px 1fr 48px;gap:10px;align-items:center;margin:8px 0}.bar{height:13px;background:#292315}.bar span{display:block;height:100%;background:linear-gradient(90deg,#8d6423,var(--gold2))}.campaign{display:grid;grid-template-columns:190px 1fr auto;gap:10px;align-items:center;margin:10px 0}.campaign code{word-break:break-all;color:#d8cfba}.copy{border:1px solid var(--gold);background:transparent;color:var(--gold2);padding:8px 10px;cursor:pointer}.notice{border-left:4px solid var(--gold);padding:13px 16px;background:#0d0c09;margin:18px 0}.empty{color:var(--muted);padding:24px;text-align:center}.good{color:var(--green)}.warning{color:#ffd166}@media(max-width:880px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sections{grid-template-columns:1fr}.campaign{grid-template-columns:1fr}}@media(max-width:520px){.grid{grid-template-columns:1fr 1fr}.wrap{padding:14px}.card{padding:14px}.barrow{grid-template-columns:85px 1fr 38px}}
</style>
</head>
<body>
<header><div class="wrap top"><div><div class="label">Private analytics</div><h1>Project Unveiled Traffic Dashboard</h1></div><nav class="nav"><a href="?range=7">7 days</a><a href="?range=30">30 days</a><a href="?range=90">90 days</a><a href="?range=<?= $range ?>&amp;export=csv">Export CSV</a><form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button type="submit">Log out</button></form></nav></div></header>
<main class="wrap">
<div class="notice"><strong>What this measures:</strong> anonymous reading sessions, page views, Journey calls to action, signup-button selections, visits to the check-email page, welcome-page visits, chapter movement, share-button clicks, support-page visits, and PayPal link clicks. It does <strong>not</strong> collect form names or email addresses, and it does not confirm whether a PayPal payment was completed.</div>
<div class="notice"><strong>Journey measurement limits:</strong> these are browser-tab visits and button selections, not verified signup requests, confirmed subscribers, email deliveries, or completed shares. Both follow-up pages can be opened directly. A new tab, another device, tracking opt-outs, and the reporting window can change these counts. No subscriber conversion rate or post-to-confirmation attribution is established here. Journey cards include all sources; the source and campaign tables below count sitewide page views.</div>
<section class="grid">
<div class="card"><div class="label">Reading sessions</div><div class="metric"><?= number_format($sessionCount) ?></div><div class="sub">Anonymous browser-tab sessions</div></div>
<div class="card"><div class="label">Page views</div><div class="metric"><?= number_format($pageviews) ?></div><div class="sub"><?= number_format($pageviewsPerSession, 1) ?> pages per session</div></div>
<div class="card"><div class="label">Reached Chapter 13</div><div class="metric"><?= number_format((int)($chapters[13] ?? 0)) ?></div><div class="sub"><?= pct($completionRate) ?> full-session completion estimate</div></div>
<div class="card"><div class="label">PayPal clicks</div><div class="metric"><?= number_format($paypalClicks) ?></div><div class="sub"><?= pct($paypalRate) ?> of sessions clicked PayPal</div></div>
<div class="card"><div class="label">Support page clicks</div><div class="metric"><?= number_format($supportClicks) ?></div><div class="sub">Interest before PayPal</div></div>
<div class="card"><div class="label">Chapter-next clicks</div><div class="metric"><?= number_format($nextClicks) ?></div><div class="sub">Reader progression</div></div>
<div class="card"><div class="label">Share clicks</div><div class="metric"><?= number_format($shares) ?></div><div class="sub">On-site share controls</div></div>
<div class="card"><div class="label">Journey CTA clicks</div><div class="metric"><?= number_format($journeyCtaClicks) ?></div><div class="sub">On-site Journey controls only</div></div>
<div class="card"><div class="label">Journey landing sessions</div><div class="metric"><?= number_format($journeyLandingSessions) ?></div><div class="sub">Anonymous landing-page sessions</div></div>
<div class="card"><div class="label">Signup selections</div><div class="metric"><?= number_format($journeySignupClicks) ?></div><div class="sub">Button selections, including invalid attempts</div></div>
<div class="card"><div class="label">Check-email-page sessions</div><div class="metric"><?= number_format($journeyCheckEmailSessions) ?></div><div class="sub">Page visits; requests are not verified</div></div>
<div class="card"><div class="label">Welcome-page sessions</div><div class="metric"><?= number_format($journeyWelcomeSessions) ?></div><div class="sub">Page visits; confirmations are not verified</div></div>
<div class="card"><div class="label">Journey share-button clicks</div><div class="metric"><?= number_format($journeyShareClicks) ?></div><div class="sub">Check-email and welcome pages only; sharing is not verified</div></div>
<div class="card"><div class="label">Reporting window</div><div class="metric"><?= $range ?></div><div class="sub">days ending today</div></div>
</section>

<?php if ($eventTotal === 0): ?>
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
<section class="panel" style="margin-top:18px"><h2>Privacy design</h2><p>No advertising tracker is installed. No IP address is stored with events; a secret-protected address digest exists only for abuse prevention, and stale entries are discarded when rate controls next run. No cross-site cookie is used. A random session identifier exists only in the visitor’s current browser tab. Search text is not collected. Do Not Track and Global Privacy Control are respected.</p><p><a href="/privacy.html" target="_blank" rel="noopener">View the public privacy notice</a></p></section>
</main>
<script>document.querySelectorAll('[data-copy]').forEach(function(button){button.addEventListener('click',function(){var value=button.getAttribute('data-copy')||'';navigator.clipboard.writeText(value).then(function(){var old=button.textContent;button.textContent='Copied';setTimeout(function(){button.textContent=old},1200)}).catch(function(){window.prompt('Copy this link:',value)})})});</script>
</body></html>
