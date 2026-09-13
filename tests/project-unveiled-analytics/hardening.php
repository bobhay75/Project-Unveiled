<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . '/project-unveiled-analytics-' . bin2hex(random_bytes(8));
$outsidePath = $testRoot . '-outside.txt';
define('PU_ANALYTICS_PRIVATE_ROOT_OVERRIDE', $testRoot);
require_once dirname(__DIR__, 2) . '/project-unveiled-analytics/lib.php';

function analytics_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function analytics_expect_status(callable $operation, int $status, string $message): void
{
    try {
        $operation();
    } catch (PuAnalyticsException $error) {
        analytics_assert($error->httpStatus === $status, $message . ' (wrong status ' . $error->httpStatus . ')');
        return;
    }
    throw new RuntimeException($message . ' (no exception)');
}

function analytics_payload(string $event = 'pageview'): array
{
    $payload = [
        'event' => $event,
        'path' => '/book/read/chapter-01.html',
        'title' => 'Chapter One',
        'session' => 'abcdef0123456789',
        'chapter' => 1,
        'referrer' => 'example.org',
        'source' => 'newsletter',
        'medium' => 'email',
        'campaign' => 'launch',
        'content' => 'chapter-one',
        'target' => '/book/read/chapter-02.html',
        'label' => 'Next chapter',
    ];
    if ($event === 'search_use') {
        $payload['target'] = '';
        $payload['label'] = '';
    }
    return $payload;
}

function analytics_stream(string $bytes)
{
    $stream = fopen('php://temp', 'w+b');
    if (!is_resource($stream)) {
        throw new RuntimeException('Could not open test stream.');
    }
    fwrite($stream, $bytes);
    rewind($stream);
    return $stream;
}

function analytics_remove_tree(string $path): void
{
    if (!str_starts_with($path, sys_get_temp_dir() . '/project-unveiled-analytics-') || !is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function analytics_assert_file_unchanged(string $path, string $bytes, int $mode, string $message): void
{
    clearstatcache(true, $path);
    analytics_assert(file_get_contents($path) === $bytes, $message . ' (bytes changed)');
    if (DIRECTORY_SEPARATOR === '/') {
        analytics_assert((fileperms($path) & 0777) === $mode, $message . ' (mode changed)');
    }
}

try {
    $_SERVER = [
        'HTTP_HOST' => 'bobsome1.com',
        'HTTP_ORIGIN' => 'https://bobsome1.com',
        'HTTP_SEC_FETCH_SITE' => 'same-origin',
        'HTTP_SEC_FETCH_DEST' => 'empty',
        'REMOTE_ADDR' => '192.0.2.25',
    ];
    analytics_assert(pu_analytics_request_origin_is_allowed(true), 'same-origin beacon request was rejected');

    unset($_SERVER['HTTP_ORIGIN']);
    $_SERVER['HTTP_REFERER'] = 'https://bobsome1.com/book/read/';
    analytics_assert(pu_analytics_request_origin_is_allowed(true), 'same-origin metadata/referer fallback was rejected');
    unset($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_SEC_FETCH_SITE']);
    analytics_assert(!pu_analytics_request_origin_is_allowed(true), 'request with all origin evidence omitted was accepted');

    $_SERVER['HTTP_ORIGIN'] = 'https://bobsome1.com.attacker.example';
    analytics_assert(!pu_analytics_request_origin_is_allowed(true), 'lookalike origin was accepted');
    $_SERVER['HTTP_ORIGIN'] = 'https://bobsome1.com';
    $_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
    analytics_assert(!pu_analytics_request_origin_is_allowed(true), 'cross-site fetch metadata was accepted');
    $_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
    $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
    analytics_assert(!pu_analytics_request_origin_is_allowed(true), 'document navigation was accepted as a beacon');
    analytics_assert(pu_analytics_request_origin_is_allowed(false), 'same-origin dashboard form request was rejected');
    $_SERVER['HTTP_SEC_FETCH_DEST'] = 'empty';

    pu_analytics_prepare_private_root($testRoot);
    $configPath = $testRoot . '/config.php';
    $passwordHash = password_hash('test-only-password', PASSWORD_DEFAULT);
    file_put_contents($configPath, "<?php return ['password_hash' => " . var_export($passwordHash, true) . "];\n", LOCK_EX);
    chmod($configPath, 0644);
    $loadedConfig = pu_analytics_load_dashboard_config($configPath);
    analytics_assert($loadedConfig['password_hash'] === $passwordHash, 'valid private dashboard config did not load');
    if (DIRECTORY_SEPARATOR === '/') {
        clearstatcache(true, $configPath);
        analytics_assert((fileperms($configPath) & 0777) === 0600, 'dashboard config was not migrated to owner-only mode');
        $configLink = $testRoot . '/linked-config.php';
        symlink($configPath, $configLink);
        analytics_expect_status(
            static fn() => pu_analytics_load_dashboard_config($configLink),
            503,
            'symlinked dashboard configuration was accepted'
        );

        $outsideBytes = "OUTSIDE FILE MUST REMAIN UNCHANGED\n";
        file_put_contents($outsidePath, $outsideBytes, LOCK_EX);
        chmod($outsidePath, 0644);

        $secretLinkRoot = $testRoot . '/secret-link-case';
        pu_analytics_prepare_private_root($secretLinkRoot);
        symlink($outsidePath, $secretLinkRoot . '/rate-secret.txt');
        analytics_expect_status(
            static fn() => pu_analytics_secret($secretLinkRoot),
            500,
            'symlinked analytics secret was opened'
        );
        analytics_assert_file_unchanged($outsidePath, $outsideBytes, 0644, 'symlinked secret touched an outside file');

        $secretLockRoot = $testRoot . '/secret-lock-link-case';
        pu_analytics_prepare_private_root($secretLockRoot);
        symlink($outsidePath, $secretLockRoot . '/rate-secret.txt.lock');
        analytics_expect_status(
            static fn() => pu_analytics_secret($secretLockRoot),
            500,
            'symlinked analytics secret lock was opened'
        );
        analytics_assert_file_unchanged($outsidePath, $outsideBytes, 0644, 'symlinked secret lock touched an outside file');

        $rateStateRoot = $testRoot . '/rate-state-link-case';
        pu_analytics_secret($rateStateRoot);
        symlink($outsidePath, $rateStateRoot . '/rate-limits.json');
        $_SERVER['REMOTE_ADDR'] = '192.0.2.77';
        analytics_expect_status(
            static fn() => pu_analytics_consume_rate($rateStateRoot, 'symlink-test', 2, 3, 10),
            500,
            'symlinked analytics rate state was read'
        );
        analytics_assert_file_unchanged($outsidePath, $outsideBytes, 0644, 'symlinked rate state touched an outside file');

        $rateLockRoot = $testRoot . '/rate-lock-link-case';
        pu_analytics_secret($rateLockRoot);
        symlink($outsidePath, $rateLockRoot . '/rate-limits.lock');
        analytics_expect_status(
            static fn() => pu_analytics_consume_rate($rateLockRoot, 'symlink-test', 2, 3, 10),
            500,
            'symlinked analytics rate lock was opened'
        );
        analytics_assert_file_unchanged($outsidePath, $outsideBytes, 0644, 'symlinked rate lock touched an outside file');

        $eventLockRoot = $testRoot . '/event-lock-link-case';
        pu_analytics_prepare_private_root($eventLockRoot);
        symlink($outsidePath, $eventLockRoot . '/events.lock');
        analytics_expect_status(
            static fn() => pu_analytics_with_event_lock($eventLockRoot, LOCK_EX, static fn() => null),
            500,
            'symlinked analytics event lock was opened'
        );
        analytics_assert_file_unchanged($outsidePath, $outsideBytes, 0644, 'symlinked event lock touched an outside file');
    }

    foreach (pu_analytics_allowed_events() as $event) {
        $parsed = pu_analytics_parse_payload(json_encode(analytics_payload($event), JSON_THROW_ON_ERROR));
        analytics_assert($parsed['event'] === $event, 'allowed event was not preserved: ' . $event);
    }
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload('[]'),
        422,
        'JSON list was accepted as an event object'
    );
    $unknown = analytics_payload();
    $unknown['surprise'] = 'value';
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload(json_encode($unknown, JSON_THROW_ON_ERROR)),
        422,
        'unknown analytics field was accepted'
    );
    $nested = analytics_payload();
    $nested['label'] = ['not', 'scalar'];
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload(json_encode($nested, JSON_THROW_ON_ERROR)),
        422,
        'nested analytics field was accepted'
    );
    $badChapter = analytics_payload();
    $badChapter['chapter'] = 1.5;
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload(json_encode($badChapter, JSON_THROW_ON_ERROR)),
        422,
        'non-integer chapter was accepted'
    );
    $badPath = analytics_payload();
    $badPath['path'] = '//attacker.example/path';
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload(json_encode($badPath, JSON_THROW_ON_ERROR)),
        422,
        'authority-like path was accepted'
    );
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload("{\"event\":\"pageview\",\"path\":\"/\",\"session\":\"abcdefgh\",\"chapter\":0,\"label\":\"\xFF\"}"),
        400,
        'invalid UTF-8 JSON was accepted'
    );
    $searchText = analytics_payload('search_use');
    $searchText['label'] = 'private medical question';
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload(json_encode($searchText, JSON_THROW_ON_ERROR)),
        422,
        'collector accepted free-text search content'
    );
    $referrerPath = analytics_payload();
    $referrerPath['referrer'] = 'example.org/private/account-name';
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload(json_encode($referrerPath, JSON_THROW_ON_ERROR)),
        422,
        'collector accepted a referrer path'
    );
    $ipReferrer = analytics_payload();
    $ipReferrer['referrer'] = '192.0.2.80';
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload(json_encode($ipReferrer, JSON_THROW_ON_ERROR)),
        422,
        'collector accepted an IP-literal referrer'
    );
    $utmTerm = analytics_payload();
    $utmTerm['term'] = 'private search words';
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload(json_encode($utmTerm, JSON_THROW_ON_ERROR)),
        422,
        'collector accepted the retired UTM term field'
    );
    $legacyStored = ['t' => gmdate('c')] + analytics_payload();
    $legacyStored['term'] = 'old-campaign-term';
    $legacyStored['referrer'] = 'example.org/private/account-name';
    $legacyStored['target'] = 'mailto:owner@example.org?subject=private&body=secret';
    $legacyRead = pu_analytics_validate_stored_event(json_decode(json_encode($legacyStored, JSON_THROW_ON_ERROR)));
    analytics_assert(!array_key_exists('term', $legacyRead), 'legacy UTM term was not discarded during dashboard read');
    analytics_assert($legacyRead['referrer'] === 'example.org', 'legacy referrer path was not discarded during dashboard read');
    analytics_assert($legacyRead['target'] === 'mailto:owner@example.org', 'legacy target query/body was not discarded during dashboard read');
    $legacyIpReferrer = ['t' => gmdate('c')] + analytics_payload();
    $legacyIpReferrer['referrer'] = '192.0.2.81';
    $legacyIpRead = pu_analytics_validate_stored_event(json_decode(json_encode($legacyIpReferrer, JSON_THROW_ON_ERROR)));
    analytics_assert($legacyIpRead['referrer'] === '', 'legacy IP-literal referrer was not discarded during privacy migration');
    $legacySearch = ['t' => gmdate('c')] + analytics_payload('search_use');
    $legacySearch['label'] = 'private medical question';
    $legacySearchRead = pu_analytics_validate_stored_event(json_decode(json_encode($legacySearch, JSON_THROW_ON_ERROR)));
    analytics_assert($legacySearchRead['label'] === '', 'legacy search text was not discarded during dashboard read');
    $targetQuery = analytics_payload();
    $targetQuery['target'] = 'mailto:owner@example.org?subject=private&body=secret';
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload(json_encode($targetQuery, JSON_THROW_ON_ERROR)),
        422,
        'collector accepted query data in a click target'
    );
    $ipTarget = analytics_payload();
    $ipTarget['target'] = 'https://192.0.2.82/private';
    analytics_expect_status(
        static fn() => pu_analytics_parse_payload(json_encode($ipTarget, JSON_THROW_ON_ERROR)),
        422,
        'collector accepted an IP-literal external target'
    );
    $legacyIpTarget = ['t' => gmdate('c')] + analytics_payload();
    $legacyIpTarget['target'] = 'https://192.0.2.83/private';
    $legacyIpTargetRead = pu_analytics_validate_stored_event(json_decode(json_encode($legacyIpTarget, JSON_THROW_ON_ERROR)));
    analytics_assert($legacyIpTargetRead['target'] === '', 'legacy IP-literal external target was not discarded during privacy migration');

    unset($_SERVER['CONTENT_LENGTH'], $_SERVER['HTTP_CONTENT_ENCODING']);
    $stream = analytics_stream(str_repeat('x', 33));
    analytics_expect_status(
        static fn() => pu_analytics_read_request_body(32, $stream),
        413,
        'undeclared/chunked oversized body bypassed the actual-byte cap'
    );
    fclose($stream);
    $_SERVER['CONTENT_LENGTH'] = '99';
    $stream = analytics_stream('{}');
    analytics_expect_status(
        static fn() => pu_analytics_read_request_body(128, $stream),
        400,
        'declared body-length mismatch was accepted'
    );
    fclose($stream);
    $_SERVER['CONTENT_LENGTH'] = '129';
    $stream = analytics_stream('{}');
    analytics_expect_status(
        static fn() => pu_analytics_read_request_body(128, $stream),
        413,
        'oversized declared body was accepted'
    );
    fclose($stream);
    unset($_SERVER['CONTENT_LENGTH']);
    $_SERVER['HTTP_CONTENT_ENCODING'] = 'gzip';
    $stream = analytics_stream('{}');
    analytics_expect_status(
        static fn() => pu_analytics_read_request_body(128, $stream),
        415,
        'encoded request body was accepted'
    );
    fclose($stream);
    unset($_SERVER['HTTP_CONTENT_ENCODING']);

    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    $formRaw = http_build_query(
        ['action' => 'login', 'csrf' => str_repeat('a', 32), 'password' => 'short'],
        '',
        '&',
        PHP_QUERY_RFC3986
    );
    $_SERVER['CONTENT_LENGTH'] = (string)strlen($formRaw);
    $_FILES = [];
    $formStream = analytics_stream($formRaw);
    $form = pu_analytics_dashboard_post(2048, $formStream);
    fclose($formStream);
    analytics_assert($form['action'] === 'login', 'bounded dashboard form was rejected');
    $nestedForm = 'action=login&csrf=' . str_repeat('a', 32) . '&password%5B%5D=nested';
    $_SERVER['CONTENT_LENGTH'] = (string)strlen($nestedForm);
    $formStream = analytics_stream($nestedForm);
    analytics_expect_status(
        static fn() => pu_analytics_dashboard_post(2048, $formStream),
        422,
        'dashboard accepted a non-scalar password'
    );
    fclose($formStream);
    $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=test';
    $formStream = analytics_stream($formRaw);
    analytics_expect_status(
        static fn() => pu_analytics_dashboard_post(2048, $formStream),
        415,
        'dashboard accepted multipart input'
    );
    fclose($formStream);
    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    unset($_SERVER['CONTENT_LENGTH']);
    $formStream = analytics_stream($formRaw);
    analytics_expect_status(
        static fn() => pu_analytics_dashboard_post(2048, $formStream),
        411,
        'dashboard accepted an unbounded form body'
    );
    fclose($formStream);
    $_SERVER['CONTENT_LENGTH'] = (string)strlen($formRaw);
    $_SERVER['HTTP_TRANSFER_ENCODING'] = 'chunked';
    $formStream = analytics_stream($formRaw);
    analytics_expect_status(
        static fn() => pu_analytics_dashboard_post(2048, $formStream),
        411,
        'dashboard accepted chunked form input'
    );
    fclose($formStream);
    unset($_SERVER['HTTP_TRANSFER_ENCODING']);
    $_SERVER['CONTENT_LENGTH'] = (string)(strlen($formRaw) + 1);
    $formStream = analytics_stream($formRaw);
    analytics_expect_status(
        static fn() => pu_analytics_dashboard_post(2048, $formStream),
        400,
        'dashboard accepted a declared form-length mismatch'
    );
    fclose($formStream);
    $_POST = [];

    $_SERVER['REMOTE_ADDR'] = '192.0.2.25';
    pu_analytics_consume_rate($testRoot, 'collector-test', 2, 3, 2, 1789257600);
    pu_analytics_consume_rate($testRoot, 'collector-test', 2, 3, 2, 1789257601);
    analytics_expect_status(
        static fn() => pu_analytics_consume_rate($testRoot, 'collector-test', 2, 3, 2, 1789257602),
        429,
        'per-IP hourly limit was not enforced atomically'
    );
    pu_analytics_consume_rate($testRoot, 'collector-test', 2, 3, 2, 1789261200);
    analytics_expect_status(
        static fn() => pu_analytics_consume_rate($testRoot, 'collector-test', 2, 3, 2, 1789261201),
        429,
        'per-IP daily limit was not enforced atomically'
    );
    $rateBytes = (string)file_get_contents($testRoot . '/rate-limits.json');
    analytics_assert(!str_contains($rateBytes, '192.0.2.25'), 'raw IP address entered private rate state');

    $_SERVER['REMOTE_ADDR'] = '198.51.100.10';
    pu_analytics_consume_rate($testRoot, 'collector-test', 2, 3, 2, 1789257602);
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
    analytics_expect_status(
        static fn() => pu_analytics_consume_rate($testRoot, 'collector-test', 2, 3, 2, 1789257603),
        503,
        'bounded rate ledger accepted an extra identity'
    );
    file_put_contents($testRoot . '/rate-limits.json', "{broken\n", LOCK_EX);
    $corruptRate = file_get_contents($testRoot . '/rate-limits.json');
    analytics_expect_status(
        static fn() => pu_analytics_consume_rate($testRoot, 'collector-test', 2, 3, 2, 1789257604),
        500,
        'corrupt rate state did not fail closed'
    );
    analytics_assert(file_get_contents($testRoot . '/rate-limits.json') === $corruptRate, 'corrupt rate state was overwritten');
    unlink($testRoot . '/rate-limits.json');

    $eventConfig = pu_analytics_config();
    $eventConfig['retention_days'] = 90;
    $eventConfig['max_daily_events'] = 2;
    $eventConfig['max_daily_bytes'] = 1048576;
    $now = 1789257600;
    $payload = pu_analytics_parse_payload(json_encode(analytics_payload(), JSON_THROW_ON_ERROR));
    pu_analytics_append_event($testRoot, $payload, $now, $eventConfig);
    pu_analytics_append_event($testRoot, $payload, $now + 1, $eventConfig);
    analytics_expect_status(
        static fn() => pu_analytics_append_event($testRoot, $payload, $now + 2, $eventConfig),
        507,
        'daily event-count cap was not enforced'
    );
    $eventFile = $testRoot . '/data/events-' . gmdate('Y-m-d', $now) . '.jsonl';
    analytics_assert(substr_count((string)file_get_contents($eventFile), "\n") === 2, 'event file count changed after capacity rejection');
    $byteCapConfig = $eventConfig;
    $byteCapConfig['max_daily_events'] = 10;
    $byteCapConfig['max_daily_bytes'] = (int)filesize($eventFile);
    analytics_expect_status(
        static fn() => pu_analytics_append_event($testRoot, $payload, $now + 3, $byteCapConfig),
        507,
        'daily byte cap was not enforced'
    );
    if (DIRECTORY_SEPARATOR === '/') {
        clearstatcache(true, $eventFile);
        analytics_assert((fileperms($testRoot) & 0777) === 0700, 'private analytics root is not owner-only');
        analytics_assert((fileperms($testRoot . '/data') & 0777) === 0700, 'analytics data directory is not owner-only');
        analytics_assert((fileperms($eventFile) & 0777) === 0600, 'event file is not owner-only');
        analytics_assert((fileperms($eventFile . '.meta.json') & 0777) === 0600, 'event metadata is not owner-only');
        analytics_assert((fileperms($testRoot . '/rate-secret.txt') & 0777) === 0600, 'rate secret is not owner-only');
    }

    $oldDate = gmdate('Y-m-d', $now - (100 * 86400));
    $oldFile = $testRoot . '/data/events-' . $oldDate . '.jsonl';
    file_put_contents($oldFile, "{}\n", LOCK_EX);
    $nextDay = $now + 86400;
    $eventConfig['max_daily_events'] = 10;
    pu_analytics_append_event($testRoot, $payload, $nextDay, $eventConfig);
    analytics_assert(!file_exists($oldFile), 'expired daily event file survived retention pruning');

    $metaFile = $testRoot . '/data/events-' . gmdate('Y-m-d', $nextDay) . '.jsonl.meta.json';
    file_put_contents($metaFile, "{broken\n", LOCK_EX);
    $metaBefore = file_get_contents($metaFile);
    analytics_expect_status(
        static fn() => pu_analytics_append_event($testRoot, $payload, $nextDay + 1, $eventConfig),
        500,
        'corrupt daily metadata did not fail closed'
    );
    analytics_assert(file_get_contents($metaFile) === $metaBefore, 'corrupt daily metadata was overwritten');
    unlink($metaFile);
    pu_analytics_with_event_lock($testRoot, LOCK_EX, static function () use ($testRoot, $eventConfig): void {
        pu_analytics_sanitize_retained_event_files($testRoot . '/data', $eventConfig);
    });

    $corruptEventFile = $testRoot . '/data/events-' . gmdate('Y-m-d', $nextDay + 86400) . '.jsonl';
    file_put_contents($corruptEventFile, "{broken-json\n", LOCK_EX);
    $corruptEventBefore = file_get_contents($corruptEventFile);
    analytics_expect_status(
        static fn() => pu_analytics_append_event($testRoot, $payload, $nextDay + 86400, $eventConfig),
        500,
        'corrupt event storage did not fail closed'
    );
    analytics_assert(file_get_contents($corruptEventFile) === $corruptEventBefore, 'corrupt event storage was appended to or overwritten');

    $sameSizeRoot = $testRoot . '/same-size-corruption-case';
    $sameSizeConfig = pu_analytics_config();
    $sameSizeConfig['max_daily_events'] = 10;
    $sameSizeConfig['max_daily_bytes'] = 1048576;
    pu_analytics_append_event($sameSizeRoot, $payload, $now, $sameSizeConfig);
    $sameSizeFile = $sameSizeRoot . '/data/events-' . gmdate('Y-m-d', $now) . '.jsonl';
    $sameSizeMeta = $sameSizeFile . '.meta.json';
    $validBytes = (string)file_get_contents($sameSizeFile);
    $validMeta = (string)file_get_contents($sameSizeMeta);
    $corruptedBytes = str_replace('"pageview"', '"pageviXw"', $validBytes, $replacementCount);
    analytics_assert($replacementCount === 1 && strlen($corruptedBytes) === strlen($validBytes), 'same-size corruption fixture was invalid');
    file_put_contents($sameSizeFile, $corruptedBytes, LOCK_EX);
    analytics_expect_status(
        static fn() => pu_analytics_append_event($sameSizeRoot, $payload, $now + 1, $sameSizeConfig),
        500,
        'content-hashed metadata masked same-size corrupt event JSON'
    );
    analytics_assert(file_get_contents($sameSizeFile) === $corruptedBytes, 'same-size corrupt event bytes were overwritten');
    analytics_assert(file_get_contents($sameSizeMeta) === $validMeta, 'metadata was rewritten after same-size event corruption');

    $migrationConfig = pu_analytics_config();
    $migrationConfig['retention_days'] = 100;
    $migrationConfig['max_daily_events'] = 20;
    $migrationConfig['max_daily_bytes'] = 1048576;
    $migrationConfig['migration_max_bytes'] = 1048576;
    $missingMigrationRoot = $testRoot . '/missing-migration-case';
    $missingResult = pu_analytics_migrate_private_storage($missingMigrationRoot, $migrationConfig, $now);
    analytics_assert($missingResult === ['files' => 0, 'events' => 0, 'bytes' => 0], 'absent analytics migration was not a no-op');
    analytics_assert(!file_exists($missingMigrationRoot), 'absent analytics migration created private storage');

    $migrationRoot = $testRoot . '/legacy-migration-case';
    $migrationData = $migrationRoot . '/data';
    pu_analytics_prepare_private_root($migrationRoot);
    pu_analytics_prepare_dir($migrationData, 0700);
    $migrationDate = gmdate('Y-m-d', $now);
    $migrationFile = $migrationData . '/events-' . $migrationDate . '.jsonl';
    $diskSearch = ['t' => gmdate('c', $now)] + analytics_payload('search_use');
    $diskSearch['term'] = 'persisted-private-utm-term';
    $diskSearch['label'] = 'persisted-private-search-text';
    $diskSearch['referrer'] = 'example.org/private/account-name';
    $diskSearch['target'] = 'mailto:owner@example.org?subject=private&body=persisted-private-body';
    $diskClick = ['t' => gmdate('c', $now + 1)] + analytics_payload('paypal_click');
    $diskClick['target'] = 'https://payments.example/checkout/account?token=persisted-private-token#receipt';
    $legacyBytes = json_encode($diskSearch, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
        . json_encode($diskClick, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    file_put_contents($migrationFile, $legacyBytes, LOCK_EX);
    chmod($migrationFile, 0644);
    $migrationResult = pu_analytics_migrate_private_storage($migrationRoot, $migrationConfig, $now);
    analytics_assert($migrationResult['files'] === 1 && $migrationResult['events'] === 2, 'legacy analytics migration returned incorrect bounds');
    $migratedBytes = (string)file_get_contents($migrationFile);
    foreach (['persisted-private-utm-term', 'persisted-private-search-text', 'persisted-private-body', 'persisted-private-token', '"term"'] as $forbidden) {
        analytics_assert(!str_contains($migratedBytes, $forbidden), 'legacy private value survived persisted migration: ' . $forbidden);
    }
    analytics_assert(str_contains($migratedBytes, '"referrer":"example.org"'), 'legacy referrer path was not removed on disk');
    analytics_assert(str_contains($migratedBytes, '"target":"https://payments.example/checkout/account"'), 'legacy target query/fragment was not removed on disk');
    $migrationMetadata = json_decode((string)file_get_contents($migrationFile . '.meta.json'), true, 16, JSON_THROW_ON_ERROR);
    analytics_assert(($migrationMetadata['version'] ?? null) === 2, 'migration did not write current metadata');
    analytics_assert(($migrationMetadata['sha256'] ?? '') === hash_file('sha256', $migrationFile), 'migration metadata was not bound to event bytes');
    analytics_assert(
        ($migrationMetadata['integrity'] ?? '') === pu_analytics_metadata_integrity(
            $migrationDate,
            (int)$migrationMetadata['bytes'],
            (int)$migrationMetadata['events'],
            (string)$migrationMetadata['sha256']
        ),
        'migration metadata integrity checksum was invalid'
    );
    if (DIRECTORY_SEPARATOR === '/') {
        clearstatcache(true, $migrationFile);
        analytics_assert((fileperms($migrationRoot) & 0777) === 0700, 'migration root is not owner-only');
        analytics_assert((fileperms($migrationData) & 0777) === 0700, 'migration data directory is not owner-only');
        analytics_assert((fileperms($migrationFile) & 0777) === 0600, 'migrated event file is not owner-only');
        analytics_assert((fileperms($migrationFile . '.meta.json') & 0777) === 0600, 'migration metadata is not owner-only');
        analytics_assert((fileperms($migrationRoot . '/events.lock') & 0777) === 0600, 'migration lock is not owner-only');
    }

    $corruptMigrationRoot = $testRoot . '/corrupt-migration-case';
    $corruptMigrationData = $corruptMigrationRoot . '/data';
    pu_analytics_prepare_dir($corruptMigrationData, 0700);
    $corruptMigrationFile = $corruptMigrationData . '/events-' . $migrationDate . '.jsonl';
    $corruptMigrationBytes = "{broken-json\n";
    file_put_contents($corruptMigrationFile, $corruptMigrationBytes, LOCK_EX);
    analytics_expect_status(
        static fn() => pu_analytics_migrate_private_storage($corruptMigrationRoot, $migrationConfig, $now),
        500,
        'corrupt legacy analytics storage did not fail closed'
    );
    analytics_assert(file_get_contents($corruptMigrationFile) === $corruptMigrationBytes, 'corrupt migration input was overwritten');
    analytics_assert(!file_exists($corruptMigrationFile . '.meta.json'), 'corrupt migration input received trusted metadata');

    $oversizedMigrationRoot = $testRoot . '/oversized-migration-case';
    $oversizedMigrationData = $oversizedMigrationRoot . '/data';
    pu_analytics_prepare_dir($oversizedMigrationData, 0700);
    $oversizedMigrationFile = $oversizedMigrationData . '/events-' . $migrationDate . '.jsonl';
    $oversizedMigrationBytes = str_repeat('x', 65);
    file_put_contents($oversizedMigrationFile, $oversizedMigrationBytes, LOCK_EX);
    $tinyMigrationConfig = $migrationConfig;
    $tinyMigrationConfig['max_daily_bytes'] = 64;
    analytics_expect_status(
        static fn() => pu_analytics_migrate_private_storage($oversizedMigrationRoot, $tinyMigrationConfig, $now),
        500,
        'oversized legacy analytics storage did not fail closed'
    );
    analytics_assert(file_get_contents($oversizedMigrationFile) === $oversizedMigrationBytes, 'oversized migration input was overwritten');

    if (DIRECTORY_SEPARATOR === '/') {
        $symlinkMigrationRoot = $testRoot . '/symlink-migration-case';
        $symlinkMigrationData = $symlinkMigrationRoot . '/data';
        pu_analytics_prepare_dir($symlinkMigrationData, 0700);
        $symlinkMigrationFile = $symlinkMigrationData . '/events-' . $migrationDate . '.jsonl';
        symlink($outsidePath, $symlinkMigrationFile);
        analytics_expect_status(
            static fn() => pu_analytics_migrate_private_storage($symlinkMigrationRoot, $migrationConfig, $now),
            500,
            'symlinked legacy analytics event was migrated'
        );
        analytics_assert_file_unchanged($outsidePath, $outsideBytes, 0644, 'analytics migration touched a symlink target');

        $metadataLinkRoot = $testRoot . '/metadata-link-migration-case';
        $metadataLinkData = $metadataLinkRoot . '/data';
        pu_analytics_prepare_dir($metadataLinkData, 0700);
        $metadataLinkFile = $metadataLinkData . '/events-' . $migrationDate . '.jsonl';
        file_put_contents($metadataLinkFile, json_encode(['t' => gmdate('c', $now)] + analytics_payload(), JSON_THROW_ON_ERROR) . "\n", LOCK_EX);
        symlink($outsidePath, $metadataLinkFile . '.meta.json');
        analytics_expect_status(
            static fn() => pu_analytics_migrate_private_storage($metadataLinkRoot, $migrationConfig, $now),
            500,
            'symlinked analytics metadata was read or replaced'
        );
        analytics_assert_file_unchanged($outsidePath, $outsideBytes, 0644, 'analytics metadata migration touched a symlink target');
    }

    $dataDir = $testRoot . '/data';
    analytics_expect_status(
        static fn() => pu_analytics_select_event_files($dataDir, gmdate('Y-m-d', $now), gmdate('Y-m-d', $nextDay), 1, 1048576),
        500,
        'dashboard file-count cap was not enforced'
    );
    $files = pu_analytics_select_event_files($dataDir, gmdate('Y-m-d', $now), gmdate('Y-m-d', $nextDay), 2, 1048576);
    analytics_expect_status(
        static fn() => pu_analytics_visit_event_files($files, 1048576, 12288, 1, static function (array $_event): void {}),
        500,
        'dashboard total-event cap was not enforced'
    );

    foreach (['=2+3', '+cmd', '-1+2', '@SUM(A1:A2)', " \t=HYPERLINK(\"https://example.test\")"] as $formula) {
        analytics_assert(str_starts_with(pu_analytics_csv_cell($formula), "'"), 'spreadsheet formula was not neutralized');
    }
    analytics_assert(pu_analytics_csv_cell('ordinary text') === 'ordinary text', 'safe CSV text was changed');

    echo "Project Unveiled analytics hardening checks passed.\n";
} finally {
    analytics_remove_tree($testRoot);
    if (is_file($outsidePath) || is_link($outsidePath)) {
        unlink($outsidePath);
    }
}
