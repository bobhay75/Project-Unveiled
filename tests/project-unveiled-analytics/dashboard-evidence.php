<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$outputPath = $argv[1] ?? '';
if ($outputPath === '' || !str_starts_with($outputPath, '/')) {
    fwrite(STDERR, "Usage: php dashboard-evidence.php /absolute/output.html\n");
    exit(2);
}

function evidence_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function evidence_remove_tree(string $path): void
{
    if ($path === '' || !str_contains(basename($path), 'project-unveiled-dashboard-')) {
        throw new RuntimeException('Refusing to remove an unexpected evidence path.');
    }
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

function evidence_metric(string $html, string $label): int
{
    $pattern = '/<div class="card"><div class="label">'
        . preg_quote($label, '/')
        . '<\/div><div class="metric">([0-9,]+)<\/div>/';
    evidence_assert(preg_match($pattern, $html, $match) === 1, "Missing dashboard metric: {$label}");
    return (int)str_replace(',', '', $match[1]);
}

$temporaryRoot = sys_get_temp_dir() . '/project-unveiled-dashboard-' . bin2hex(random_bytes(8));
$privateRoot = $temporaryRoot . '/private';
$sessionRoot = $temporaryRoot . '/sessions';

try {
    evidence_assert(mkdir($privateRoot, 0700, true), 'Could not create the synthetic private root.');
    evidence_assert(mkdir($sessionRoot, 0700, true), 'Could not create the synthetic session root.');
    define('PU_ANALYTICS_PRIVATE_ROOT_OVERRIDE', $privateRoot);
    require_once $root . '/project-unveiled-analytics/lib.php';

    $passwordHash = password_hash('synthetic-test-only', PASSWORD_DEFAULT);
    evidence_assert(is_string($passwordHash), 'Could not create the synthetic dashboard password hash.');
    $config = "<?php\nreturn ['password_hash' => " . var_export($passwordHash, true) . "];\n";
    evidence_assert(file_put_contents($privateRoot . '/config.php', $config, LOCK_EX) !== false, 'Could not write synthetic dashboard configuration.');
    evidence_assert(chmod($privateRoot . '/config.php', 0600), 'Could not protect synthetic dashboard configuration.');

    $base = [
        'event' => 'pageview',
        'path' => '/',
        'title' => 'Synthetic evidence',
        'session' => 'session-alpha',
        'chapter' => 0,
        'referrer' => '',
        'source' => '',
        'medium' => '',
        'campaign' => '',
        'content' => '',
        'target' => '',
        'label' => '',
    ];
    $events = [
        ['event' => 'pageview', 'path' => '/unveiled/', 'session' => 'session-alpha', 'source' => 'owner-test', 'campaign' => 'unveiled_14_day_sprint'],
        ['event' => 'pageview', 'path' => '/unveiled/confirmed.html', 'session' => 'session-alpha'],
        ['event' => 'pageview', 'path' => '/unveiled/confirmed.html', 'session' => 'session-alpha'],
        ['event' => 'pageview', 'path' => '/unveiled/welcome.html', 'session' => 'session-beta'],
        ['event' => 'pageview', 'path' => '/unveiled/welcome.html', 'session' => 'session-beta'],
        ['event' => 'journey_signup_click', 'path' => '/unveiled/', 'session' => 'session-alpha'],
        ['event' => 'share_click', 'path' => '/unveiled/welcome.html', 'session' => 'session-beta', 'target' => 'https://www.facebook.com/sharer/sharer.php', 'label' => 'Share'],
        ['event' => 'share_click', 'path' => '/book/read/chapter-01.html', 'session' => 'session-alpha', 'target' => 'https://www.facebook.com/sharer/sharer.php', 'label' => 'Share'],
    ];
    $timestamp = time() - 30;
    foreach ($events as $offset => $event) {
        $payload = pu_analytics_validate_payload_data(array_replace($base, $event));
        pu_analytics_append_event($privateRoot, $payload, $timestamp + $offset);
    }

    session_save_path($sessionRoot);
    evidence_assert(session_save_path() === $sessionRoot, 'Could not select the synthetic session root.');
    session_name('pu_analytics_dashboard');
    $sessionId = 'syntheticdashboardreview';
    session_id($sessionId);
    evidence_assert(session_id() === $sessionId, 'Could not select the synthetic dashboard session.');
    evidence_assert(session_start(), 'Could not start the synthetic dashboard session.');
    $_SESSION['authenticated'] = true;
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf'] = str_repeat('a', 32);
    session_write_close();
    $_COOKIE['pu_analytics_dashboard'] = $sessionId;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['DOCUMENT_ROOT'] = $root;
    $_GET = ['range' => '30'];

    ob_start();
    require $root . '/project-unveiled-analytics/index.php';
    $html = ob_get_clean();
    evidence_assert(is_string($html) && str_contains($html, '<!doctype html>'), 'Authenticated dashboard did not render.');

    evidence_assert(evidence_metric($html, 'Journey landing sessions') === 1, 'Journey landing sessions did not deduplicate by browser-tab session.');
    evidence_assert(evidence_metric($html, 'Signup selections') === 1, 'Synthetic signup selection was not counted.');
    evidence_assert(evidence_metric($html, 'Check-email-page sessions') === 1, 'Repeated check-email visits in one tab were not deduplicated.');
    evidence_assert(evidence_metric($html, 'Welcome-page sessions') === 1, 'Repeated welcome visits in one tab were not deduplicated.');
    evidence_assert(evidence_metric($html, 'Share clicks') === 2, 'Sitewide share-button count did not include both synthetic clicks.');
    evidence_assert(evidence_metric($html, 'Journey share-button clicks') === 1, 'Unrelated share click leaked into the Journey share count.');
    evidence_assert(str_contains($html, 'not verified signup requests, confirmed subscribers, email deliveries, or completed shares'), 'Dashboard evidence limits are missing.');
    evidence_assert(!str_contains($html, '<div class="label">Signup requests</div>'), 'Unsupported signup-request label remains.');
    evidence_assert(!str_contains($html, '<div class="label">Confirmed readers</div>'), 'Unsupported confirmed-reader label remains.');
    evidence_assert(!str_contains($html, '% of Journey sessions'), 'Unsupported Journey conversion rate remains.');
    evidence_assert(!str_contains($html, '% of signup requests'), 'Unsupported signup conversion rate remains.');

    $outputDirectory = dirname($outputPath);
    evidence_assert(is_dir($outputDirectory) || mkdir($outputDirectory, 0700, true), 'Could not create the dashboard evidence output directory.');
    evidence_assert(file_put_contents($outputPath, $html, LOCK_EX) !== false, 'Could not write the synthetic dashboard render.');

    echo "Dashboard evidence checks passed: follow-up visits remain page sessions, repeated tab visits deduplicate, and unrelated shares stay outside the Journey share count.\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    evidence_remove_tree($temporaryRoot);
}
