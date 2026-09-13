<?php
declare(strict_types=1);

$tempDir = sys_get_temp_dir() . '/trust-worthy-intake-' . bin2hex(random_bytes(8));
$testOwnerPid = getmypid();
define('TW_INTAKE_PRIVATE_DIR_OVERRIDE', $tempDir);
require_once dirname(__DIR__, 2) . '/truth/lib/intake.php';

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_expect_status(callable $callback, int $status, string $message): void
{
    try {
        $callback();
    } catch (TwIntakeException $error) {
        test_assert($error->httpStatus === $status, $message . ' (wrong status)');
        return;
    }
    throw new RuntimeException($message . ' (no exception)');
}

function test_record(string $identity, ?int $submitted = null, ?int $deleteAfter = null): array
{
    $submitted ??= time();
    $deleteAfter ??= time() + 86400;
    return [
        'schema_version' => 2,
        'id' => bin2hex(random_bytes(16)),
        'submitted_at_utc' => gmdate('c', $submitted),
        'delete_after_utc' => gmdate('c', $deleteAfter),
        'ip_hash' => hash('sha256', $identity),
    ];
}

function test_read_json(string $path): array
{
    return json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
}

function test_remove_tree(string $dir): void
{
    if (!str_starts_with($dir, sys_get_temp_dir() . '/trust-worthy-intake-') || !is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function test_symlink_target_is_untouched(string $runtimePath, callable $operation, string $label): void
{
    $backup = $runtimePath . '.real-fixture';
    $target = dirname($runtimePath) . '/outside-' . substr(hash('sha256', $label), 0, 12) . '.txt';
    test_assert(is_file($runtimePath), $label . ' fixture is missing');
    test_assert(rename($runtimePath, $backup), $label . ' fixture could not be moved');
    $targetBytes = "outside target must remain untouched: {$label}\n";
    test_assert(file_put_contents($target, $targetBytes, LOCK_EX) === strlen($targetBytes), $label . ' target could not be created');
    test_assert(chmod($target, 0644), $label . ' target mode could not be set');
    test_assert(symlink($target, $runtimePath), $label . ' symlink could not be created');
    try {
        test_expect_status($operation, 500, $label . ' symlink was followed');
        clearstatcache(true, $target);
        test_assert(file_get_contents($target) === $targetBytes, $label . ' target content changed');
        test_assert((fileperms($target) & 0777) === 0644, $label . ' target mode changed');
    } finally {
        @unlink($runtimePath);
        @unlink($target);
        test_assert(rename($backup, $runtimePath), $label . ' fixture could not be restored');
    }
}

try {
    test_assert(tw_intake_migrate_private_permissions() === 0, 'absent migration directory must be a no-op');
    test_assert(!file_exists($tempDir), 'no-op migration created the private directory');
    if (function_exists('symlink')) {
        $directoryTarget = $tempDir . '-outside-directory';
        test_assert(mkdir($directoryTarget, 0700), 'private directory symlink target could not be created');
        $directoryMarker = $directoryTarget . '/marker.txt';
        test_assert(file_put_contents($directoryMarker, "untouched\n", LOCK_EX) === 10, 'private directory marker could not be created');
        test_assert(chmod($directoryMarker, 0644), 'private directory marker mode could not be set');
        test_assert(symlink($directoryTarget, $tempDir), 'private directory symlink could not be created');
        test_expect_status(static fn() => tw_intake_prepare_private_dir(), 500, 'symlinked private directory was followed');
        clearstatcache(true, $directoryMarker);
        test_assert(file_get_contents($directoryMarker) === "untouched\n", 'symlinked directory target content changed');
        test_assert((fileperms($directoryMarker) & 0777) === 0644, 'symlinked directory target mode changed');
        unlink($tempDir);
        unlink($directoryMarker);
        rmdir($directoryTarget);
    }
    test_assert(mkdir($tempDir, 0700), 'test private directory could not be created');
    test_assert(tw_intake_migrate_private_permissions() === 0, 'empty migration directory must be a no-op');
    test_assert(!file_exists($tempDir . '/intake-permissions.lock'), 'empty migration created a lock file');

    putenv('TW_INTAKE_HOURLY_LIMIT=1');
    putenv('TW_INTAKE_DAILY_LIMIT=12');

    $secret = tw_intake_secret('question');
    test_assert((bool)preg_match('/^[a-f0-9]{64}$/D', $secret), 'secret must have 256 bits of encoded entropy');
    test_assert(tw_intake_secret('question') === $secret, 'secret must remain stable');
    if (DIRECTORY_SEPARATOR === '/') {
        clearstatcache();
        $secretMode = fileperms($tempDir . '/question-secret.txt') & 0777;
        $secretLockMode = fileperms($tempDir . '/question-secret.txt.lock') & 0777;
        test_assert($secretMode === 0600, 'secret must be owner-only');
        test_assert($secretLockMode === 0600, 'secret lock must be owner-only');
    }
    if (function_exists('symlink')) {
        test_symlink_target_is_untouched(
            $tempDir . '/question-secret.txt',
            static fn() => tw_intake_secret('question'),
            'private intake secret'
        );
        test_symlink_target_is_untouched(
            $tempDir . '/question-secret.txt.lock',
            static fn() => tw_intake_secret('question'),
            'private intake secret lock'
        );
    }

    $questionPath = $tempDir . '/questions.json';
    tw_intake_append_record('question', test_record('same-ip'));
    test_assert(count(test_read_json($questionPath)) === 1, 'first record was not stored');
    if (DIRECTORY_SEPARATOR === '/') {
        clearstatcache();
        test_assert((fileperms($questionPath) & 0777) === 0600, 'question queue must be owner-only');
        test_assert((fileperms($questionPath . '.lock') & 0777) === 0600, 'question queue lock must be owner-only');
    }
    test_expect_status(
        static fn() => tw_intake_append_record('question', test_record('same-ip')),
        429,
        'same-IP hourly limit did not fail closed'
    );
    test_assert(count(test_read_json($questionPath)) === 1, 'rate-limited record changed storage');
    if (function_exists('symlink')) {
        test_symlink_target_is_untouched(
            $questionPath,
            static fn() => tw_intake_append_record('question', test_record('queue-symlink')),
            'private intake queue'
        );
        test_symlink_target_is_untouched(
            $questionPath . '.lock',
            static fn() => tw_intake_append_record('question', test_record('queue-lock-symlink')),
            'private intake queue lock'
        );
    }

    // A malformed queue must be preserved byte-for-byte instead of being reset to [].
    $challengePath = $tempDir . '/challenges.json';
    file_put_contents($challengePath, "{broken-json\n", LOCK_EX);
    $brokenBefore = file_get_contents($challengePath);
    test_expect_status(
        static fn() => tw_intake_append_record('challenge', test_record('corrupt-check')),
        500,
        'corrupt storage did not fail closed'
    );
    test_assert(file_get_contents($challengePath) === $brokenBefore, 'corrupt storage was overwritten');

    // Expired version-2 records are removed during the next successful locked append.
    putenv('TW_INTAKE_HOURLY_LIMIT=30');
    putenv('TW_INTAKE_RETENTION_DAYS=181');
    test_assert(tw_intake_config()['retention_days'] === 180, 'retention accepted a value above the public 180-day ceiling');
    putenv('TW_INTAKE_RETENTION_DAYS=180');
    file_put_contents(
        $challengePath,
        json_encode([test_record('expired', time() - 7200, time() - 3600)], JSON_THROW_ON_ERROR) . "\n",
        LOCK_EX
    );
    tw_intake_append_record('challenge', test_record('current'));
    $retained = test_read_json($challengePath);
    test_assert(count($retained) === 1, 'expired records were not pruned under the queue lock');
    test_assert($retained[0]['ip_hash'] === hash('sha256', 'current'), 'wrong record survived retention pruning');
    if (DIRECTORY_SEPARATOR === '/') {
        clearstatcache();
        test_assert((fileperms($challengePath) & 0777) === 0600, 'challenge queue must be owner-only');
        test_assert((fileperms($challengePath . '.lock') & 0777) === 0600, 'challenge queue lock must be owner-only');
    }

    // Legacy records receive the configured retention window from their
    // submitted timestamp instead of being kept indefinitely.
    $legacyExpired = test_record('legacy-expired', time() - (181 * 86400));
    $legacyCurrent = test_record('legacy-current');
    unset($legacyExpired['delete_after_utc'], $legacyCurrent['delete_after_utc']);
    file_put_contents(
        $challengePath,
        json_encode([$legacyExpired, $legacyCurrent], JSON_THROW_ON_ERROR) . "\n",
        LOCK_EX
    );
    tw_intake_append_record('challenge', test_record('after-legacy-prune'));
    $retained = test_read_json($challengePath);
    $retainedHashes = array_column($retained, 'ip_hash');
    test_assert(count($retained) === 2, 'legacy retention window was not applied');
    test_assert(!in_array(hash('sha256', 'legacy-expired'), $retainedHashes, true), 'expired legacy record survived');
    test_assert(in_array(hash('sha256', 'legacy-current'), $retainedHashes, true), 'current legacy record was removed');

    // Current policy is an upper bound even for an explicit version-2 date.
    putenv('TW_INTAKE_RETENTION_DAYS=30');
    $overlongExisting = test_record('overlong-existing', time() - (31 * 86400), time() + 365 * 86400);
    $cappedExisting = test_record('capped-existing', time() - 86400, time() + 365 * 86400);
    file_put_contents($challengePath, json_encode([$overlongExisting, $cappedExisting], JSON_THROW_ON_ERROR) . "\n", LOCK_EX);
    tw_intake_append_record('challenge', test_record('after-retention-cap'));
    $retained = test_read_json($challengePath);
    test_assert(count($retained) === 2, 'lowered retention did not expire only the overlong stale record');
    test_assert($retained[0]['ip_hash'] === hash('sha256', 'capped-existing'), 'current capped record was not retained');
    $cappedSubmitted = tw_intake_parse_utc_timestamp($retained[0]['submitted_at_utc'] ?? null);
    $cappedDeleteAfter = tw_intake_parse_utc_timestamp($retained[0]['delete_after_utc'] ?? null);
    test_assert(is_int($cappedSubmitted) && $cappedDeleteAfter === $cappedSubmitted + (30 * 86400), 'stored far-future expiry was not normalized to the current retention cap');
    $beforeOverlongAppend = file_get_contents($challengePath);
    test_expect_status(
        static fn() => tw_intake_append_record('challenge', test_record('overlong-new', time(), time() + 31 * 86400)),
        500,
        'new record beyond the configured retention cap was accepted'
    );
    test_assert(file_get_contents($challengePath) === $beforeOverlongAppend, 'rejected overlong record changed storage');
    putenv('TW_INTAKE_RETENTION_DAYS=180');

    $invalidLegacy = $legacyCurrent;
    $invalidLegacy['submitted_at_utc'] = 'not-a-timestamp';
    file_put_contents($challengePath, json_encode([$invalidLegacy], JSON_THROW_ON_ERROR) . "\n", LOCK_EX);
    $invalidBefore = file_get_contents($challengePath);
    test_expect_status(
        static fn() => tw_intake_append_record('challenge', test_record('invalid-legacy-check')),
        500,
        'invalid legacy timestamp did not fail closed'
    );
    test_assert(file_get_contents($challengePath) === $invalidBefore, 'invalid legacy queue was overwritten');
    $invalidLegacy['submitted_at_utc'] = 'yesterday';
    file_put_contents($challengePath, json_encode([$invalidLegacy], JSON_THROW_ON_ERROR) . "\n", LOCK_EX);
    $relativeTimestampBefore = file_get_contents($challengePath);
    test_expect_status(
        static fn() => tw_intake_append_record('challenge', test_record('relative-timestamp-check')),
        500,
        'relative legacy timestamp did not fail closed'
    );
    test_assert(file_get_contents($challengePath) === $relativeTimestampBefore, 'relative-timestamp queue was overwritten');

    // The deployment migration changes only modes on an exact allowlist; even
    // invalid queue content must remain byte-for-byte untouched.
    tw_intake_secret('challenge');
    $migrationTargets = [
        $tempDir . '/question-secret.txt',
        $tempDir . '/question-secret.txt.lock',
        $tempDir . '/challenge-secret.txt',
        $tempDir . '/challenge-secret.txt.lock',
        $questionPath,
        $questionPath . '.lock',
        $challengePath,
        $challengePath . '.lock',
    ];
    $migrationContents = [];
    foreach ($migrationTargets as $target) {
        test_assert(chmod($target, 0644), 'could not loosen test fixture mode');
        $migrationContents[$target] = file_get_contents($target);
    }
    test_assert(tw_intake_migrate_private_permissions() === count($migrationTargets), 'migration skipped an exact runtime file');
    foreach ($migrationTargets as $target) {
        clearstatcache(true, $target);
        test_assert((fileperms($target) & 0777) === 0600, 'migration did not enforce owner-only mode');
        test_assert(file_get_contents($target) === $migrationContents[$target], 'migration changed private file content');
    }
    clearstatcache(true, $tempDir . '/intake-permissions.lock');
    test_assert((fileperms($tempDir . '/intake-permissions.lock') & 0777) === 0600, 'migration lock must be owner-only');

    $outside = $tempDir . '/outside-fixture.txt';
    file_put_contents($outside, "must not change\n", LOCK_EX);
    $outsideBefore = file_get_contents($outside);
    unlink($challengePath);
    test_assert(symlink($outside, $challengePath), 'could not create migration symlink fixture');
    test_expect_status(
        static fn() => tw_intake_migrate_private_permissions(),
        500,
        'permission migration accepted a symlink target'
    );
    test_assert(file_get_contents($outside) === $outsideBefore, 'migration changed a symlink target');
    unlink($challengePath);

    test_assert(mkdir($challengePath), 'could not create migration non-file fixture');
    test_expect_status(
        static fn() => tw_intake_migrate_private_permissions(),
        500,
        'permission migration accepted a non-file target'
    );
    rmdir($challengePath);

    unlink($tempDir . '/intake-permissions.lock');
    test_assert(mkdir($tempDir . '/intake-permissions.lock'), 'could not create migration lock non-file fixture');
    test_expect_status(
        static fn() => tw_intake_migrate_private_permissions(),
        500,
        'permission migration accepted a non-file lock'
    );
    rmdir($tempDir . '/intake-permissions.lock');

    tw_intake_assert_known_case('TW-CLAIM-000002');
    test_expect_status(
        static fn() => tw_intake_assert_known_case('TW-CLAIM-999999'),
        422,
        'syntactically valid nonexistent case was accepted'
    );

    $_SERVER = [
        'HTTP_HOST' => 'bobsome1.com',
        'HTTP_ORIGIN' => 'https://bobsome1.com',
    ];
    test_assert(tw_intake_request_origin_is_allowed(), 'canonical same-origin request was rejected');
    $_SERVER['HTTP_ORIGIN'] = 'https://bobsome1.com.attacker.example';
    test_assert(!tw_intake_request_origin_is_allowed(), 'lookalike cross-origin request was accepted');
    $_SERVER['HTTP_ORIGIN'] = 'https://bobsome1.com';
    $_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
    test_assert(!tw_intake_request_origin_is_allowed(), 'cross-site fetch metadata was accepted');
    unset($_SERVER['HTTP_SEC_FETCH_SITE']);
    unset($_SERVER['HTTP_ORIGIN']);
    $_SERVER['HTTP_REFERER'] = 'https://bobsome1.com/truth/case-000002.php';
    test_assert(tw_intake_request_origin_is_allowed(), 'owned HTTPS referer fallback was rejected');
    $_SERVER['HTTP_REFERER'] = 'https://www.bobsome1.com/truth/case-000002.php';
    test_assert(!tw_intake_request_origin_is_allowed(), 'cross-subdomain referer was accepted as same-origin');
    $_SERVER['HTTP_REFERER'] = 'http://bobsome1.com/truth/case-000002.php';
    test_assert(!tw_intake_request_origin_is_allowed(), 'insecure referer was accepted');

    $_POST = ['field' => ['nested']];
    $_FILES = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'POST',
        'HTTP_HOST' => 'bobsome1.com',
        'HTTP_ORIGIN' => 'https://bobsome1.com',
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'CONTENT_LENGTH' => '16',
    ];
    test_expect_status(
        static fn() => tw_intake_enforce_post_request(1024),
        422,
        'nested form field was accepted'
    );
    $_POST = [];
    $_FILES = ['evidence' => ['name' => 'private.txt']];
    test_expect_status(
        static fn() => tw_intake_enforce_post_request(1024),
        422,
        'nonempty file-upload input was accepted'
    );
    $_FILES = [];

    // Exercise the stable companion lock with real parallel writers when pcntl is available.
    @unlink($questionPath);
    putenv('TW_INTAKE_HOURLY_LIMIT=30');
    putenv('TW_INTAKE_DAILY_LIMIT=100');
    $writerCount = 12;
    if (function_exists('pcntl_fork')
        && function_exists('pcntl_waitpid')
        && function_exists('pcntl_wifexited')
        && function_exists('pcntl_wexitstatus')
    ) {
        $children = [];
        for ($index = 0; $index < $writerCount; $index++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new RuntimeException('could not fork concurrency test');
            }
            if ($pid === 0) {
                try {
                    tw_intake_append_record('question', test_record('parallel-' . $index));
                    exit(0);
                } catch (Throwable $error) {
                    fwrite(STDERR, 'parallel writer failed safely: ' . get_class($error) . "\n");
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            test_assert(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'parallel writer failed');
        }
    } else {
        for ($index = 0; $index < $writerCount; $index++) {
            tw_intake_append_record('question', test_record('sequential-' . $index));
        }
    }
    test_assert(count(test_read_json($questionPath)) === $writerCount, 'locked writers lost or duplicated records');

    putenv('TW_INTAKE_MAX_RECORDS=100');
    for ($index = $writerCount; $index < 100; $index++) {
        tw_intake_append_record('question', test_record('capacity-' . $index));
    }
    test_assert(count(test_read_json($questionPath)) === 100, 'record capacity setup failed');
    test_expect_status(
        static fn() => tw_intake_append_record('question', test_record('over-capacity')),
        503,
        'record-count cap did not fail closed'
    );
    test_assert(count(test_read_json($questionPath)) === 100, 'over-capacity append changed storage');

    echo "Trust-Worthy intake tests passed: atomic queues, corruption guard, rate/record caps, legacy retention, origin checks, case registry, and permissions.\n";
} finally {
    if (getmypid() === $testOwnerPid) {
        test_remove_tree($tempDir);
    }
}
