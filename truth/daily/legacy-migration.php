<?php
declare(strict_types=1);

/**
 * One-time retirement for sensitive Daily Desk files used before Release 16.
 *
 * This file deliberately has its own narrowly prefixed filesystem helpers so
 * the deployment CLI can load it beside the public AI gateway without function
 * name collisions. Only the three exact legacy paths below are ever retired.
 */

function tw_daily_legacy_path_exists(string $path): bool {
    return file_exists($path) || is_link($path);
}

function tw_daily_legacy_regular_stat(mixed $stat): bool {
    return is_array($stat) && ((((int)($stat['mode'] ?? 0)) & 0170000) === 0100000);
}

function tw_daily_legacy_same_file(mixed $left, mixed $right): bool {
    return is_array($left)
        && is_array($right)
        && is_int($left['dev'] ?? null)
        && is_int($left['ino'] ?? null)
        && $left['dev'] === ($right['dev'] ?? null)
        && $left['ino'] === ($right['ino'] ?? null);
}

function tw_daily_legacy_private_dir(string $dir): string {
    if ($dir === '' || $dir[0] !== '/' || str_contains($dir, "\0") || is_link($dir)) {
        throw new RuntimeException('Daily legacy migration private directory is unsafe.');
    }
    clearstatcache(true, $dir);
    $stat = lstat($dir);
    $mode = is_array($stat) ? (int)($stat['mode'] ?? 0) : 0;
    if (!is_array($stat)
        || ($mode & 0170000) !== 0040000
        || !in_array($mode & 0777, [0700, 0750], true)
    ) {
        throw new RuntimeException('Daily legacy migration private directory is unavailable or insecure.');
    }
    return rtrim($dir, '/');
}

function tw_daily_legacy_assert_regular(string $path, string $label): ?array {
    if (is_link($path)) throw new RuntimeException($label . ' must not be a symlink.');
    if (!file_exists($path)) return null;
    clearstatcache(true, $path);
    $stat = lstat($path);
    if (is_link($path) || !tw_daily_legacy_regular_stat($stat)) {
        throw new RuntimeException($label . ' is not a regular file.');
    }
    return $stat;
}

/** @return resource */
function tw_daily_legacy_open_file(string $path, string $mode, string $label, ?array $expected = null) {
    $before = $expected ?? tw_daily_legacy_assert_regular($path, $label);
    $handle = fopen($path, $mode);
    if ($handle === false) throw new RuntimeException($label . ' could not be opened safely.');
    try {
        clearstatcache(true, $path);
        $pathStat = lstat($path);
        $handleStat = fstat($handle);
        if (is_link($path)
            || !tw_daily_legacy_regular_stat($pathStat)
            || !tw_daily_legacy_regular_stat($handleStat)
            || !tw_daily_legacy_same_file($pathStat, $handleStat)
            || ($before !== null && !tw_daily_legacy_same_file($before, $handleStat))
        ) {
            throw new RuntimeException($label . ' changed before it could be secured.');
        }
        if ((((int)($handleStat['mode'] ?? 0)) & 0777) !== 0600 && !chmod($path, 0600)) {
            throw new RuntimeException($label . ' permissions could not be restricted.');
        }
        clearstatcache(true, $path);
        $pathStat = lstat($path);
        $handleStat = fstat($handle);
        if (is_link($path)
            || !tw_daily_legacy_same_file($pathStat, $handleStat)
            || !tw_daily_legacy_regular_stat($handleStat)
            || ((((int)($pathStat['mode'] ?? 0)) & 0777) !== 0600)
            || ((((int)($handleStat['mode'] ?? 0)) & 0777) !== 0600)
        ) {
            throw new RuntimeException($label . ' permissions could not be verified.');
        }
        return $handle;
    } catch (Throwable $error) {
        fclose($handle);
        throw $error;
    }
}

/** @return array{handle: resource, path: string, label: string, raw: string, stat: array} */
function tw_daily_legacy_open_record(string $path, string $label, int $maxBytes, array $expected): array {
    $handle = tw_daily_legacy_open_file($path, 'r+b', $label, $expected);
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException($label . ' could not be locked safely.');
    }
    try {
        $before = fstat($handle);
        $size = is_array($before) ? ($before['size'] ?? null) : null;
        if (!is_int($size) || $size < 0 || $size > $maxBytes) {
            throw new RuntimeException($label . ' exceeds its bounded migration size.');
        }
        if (fseek($handle, 0) !== 0) throw new RuntimeException($label . ' could not be read safely.');
        $raw = stream_get_contents($handle, $maxBytes + 1);
        $after = fstat($handle);
        clearstatcache(true, $path);
        $pathAfter = lstat($path);
        if (!is_string($raw)
            || strlen($raw) !== $size
            || !tw_daily_legacy_same_file($before, $after)
            || !tw_daily_legacy_same_file($after, $pathAfter)
            || !tw_daily_legacy_regular_stat($after)
            || ($after['size'] ?? null) !== $size
            || ($pathAfter['size'] ?? null) !== $size
        ) {
            throw new RuntimeException($label . ' changed while it was read.');
        }
        return ['handle' => $handle, 'path' => $path, 'label' => $label, 'raw' => $raw, 'stat' => $after];
    } catch (Throwable $error) {
        flock($handle, LOCK_UN);
        fclose($handle);
        throw $error;
    }
}

function tw_daily_legacy_write_all($handle, string $bytes): bool {
    $offset = 0;
    $length = strlen($bytes);
    while ($offset < $length) {
        $written = fwrite($handle, substr($bytes, $offset));
        if ($written === false || $written === 0) return false;
        $offset += $written;
    }
    if (!fflush($handle)) return false;
    return !function_exists('fsync') || fsync($handle);
}

function tw_daily_legacy_prepare_drafts_dir(string $privateDir): string {
    $dir = $privateDir . '/daily-drafts';
    if (is_link($dir)) throw new RuntimeException('Daily draft migration directory must not be a symlink.');
    if (!file_exists($dir) && !mkdir($dir, 0700)) {
        throw new RuntimeException('Daily draft migration directory could not be created.');
    }
    clearstatcache(true, $dir);
    $stat = lstat($dir);
    $mode = is_array($stat) ? (int)($stat['mode'] ?? 0) : 0;
    if (is_link($dir) || !is_array($stat) || ($mode & 0170000) !== 0040000 || ($mode & 0777) !== 0700) {
        throw new RuntimeException('Daily draft migration directory is insecure.');
    }
    return $dir;
}

function tw_daily_legacy_atomic_json(string $path, array $data): bool {
    $existing = tw_daily_legacy_assert_regular($path, basename($path));
    try {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
    } catch (Throwable $error) {
        throw new RuntimeException('Daily legacy migration output could not be prepared.', 0, $error);
    }
    if ($existing !== null) return false;

    $handle = tw_daily_legacy_open_file($temporary, 'x+b', basename($temporary));
    $ok = flock($handle, LOCK_EX) && tw_daily_legacy_write_all($handle, $json);
    $writtenStat = fstat($handle);
    if ($ok) {
        clearstatcache(true, $temporary);
        $ok = tw_daily_legacy_same_file($writtenStat, lstat($temporary));
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    if (!$ok) {
        @unlink($temporary);
        throw new RuntimeException('Daily legacy migration output could not be written.');
    }
    try {
        if (tw_daily_legacy_assert_regular($path, basename($path)) !== null) {
            @unlink($temporary);
            return false;
        }
    } catch (Throwable $error) {
        @unlink($temporary);
        throw $error;
    }
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Daily legacy migration output could not be installed.');
    }
    clearstatcache(true, $path);
    $final = lstat($path);
    if (!tw_daily_legacy_same_file($writtenStat, $final)
        || !tw_daily_legacy_regular_stat($final)
        || ((((int)($final['mode'] ?? 0)) & 0777) !== 0600)
    ) {
        throw new RuntimeException('Daily legacy migration output could not be verified.');
    }
    return true;
}

function tw_daily_legacy_read_json(string $path, int $maxBytes): array {
    $expected = tw_daily_legacy_assert_regular($path, basename($path));
    if ($expected === null) return [];
    $record = tw_daily_legacy_open_record($path, basename($path), $maxBytes, $expected);
    try {
        $decoded = json_decode($record['raw'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new RuntimeException('Daily migration JSON has an invalid structure.');
        return $decoded;
    } catch (JsonException $error) {
        throw new RuntimeException('Daily migration JSON is corrupted.', 0, $error);
    } finally {
        flock($record['handle'], LOCK_UN);
        fclose($record['handle']);
    }
}

function tw_daily_legacy_parse_utc(mixed $value): ?int {
    if (!is_string($value)
        || preg_match(
            '/^(\d{4})-(\d{2})-(\d{2})T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|\+00:00)$/D',
            trim($value),
            $parts
        ) !== 1
        || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])
    ) {
        return null;
    }
    $timestamp = strtotime(trim($value));
    return is_int($timestamp) ? $timestamp : null;
}

function tw_daily_legacy_retention_days(): int {
    $raw = getenv('TW_INTAKE_RETENTION_DAYS');
    if (!is_string($raw) || preg_match('/^\d+$/D', $raw) !== 1) return 180;
    $days = (int)$raw;
    return $days >= 30 && $days <= 180 ? $days : 180;
}

function tw_daily_legacy_private_candidate(array $candidate): bool {
    $source = is_string($candidate['source'] ?? null) ? $candidate['source'] : '';
    return ($candidate['visibility'] ?? '') === 'private'
        || ($candidate['candidate_type'] ?? '') === 'user-question'
        || strcasecmp($source, 'Private user question') === 0;
}

/** @return array{status: 'migrate', draft: array}|array{status: 'expired'} */
function tw_daily_legacy_prepare_draft(string $raw, int $now): array {
    try {
        $draft = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException('Legacy Daily draft is corrupted; original was preserved.', 0, $error);
    }
    if (!is_array($draft) || !is_array($draft['_meta'] ?? null) || !is_array($draft['_meta']['candidate'] ?? null)) {
        throw new RuntimeException('Legacy Daily draft has an unsupported structure; original was preserved.');
    }
    foreach (['headline', 'claim', 'summary'] as $field) {
        if (!is_string($draft[$field] ?? null) || trim($draft[$field]) === '') {
            throw new RuntimeException('Legacy Daily draft is missing required text; original was preserved.');
        }
    }
    if (!is_array($draft['sources'] ?? null)) {
        throw new RuntimeException('Legacy Daily draft has an invalid source list; original was preserved.');
    }

    $candidate = $draft['_meta']['candidate'];
    if (tw_daily_legacy_private_candidate($candidate)) {
        $submitted = $candidate['timestamp'] ?? null;
        if (!is_int($submitted) || $submitted <= 0 || $submitted > $now) {
            throw new RuntimeException('Legacy private Daily draft has an invalid submission time; original was preserved.');
        }
        $cap = $submitted + (tw_daily_legacy_retention_days() * 86400);
        if (array_key_exists('delete_after_utc', $candidate)) {
            $deleteAt = tw_daily_legacy_parse_utc($candidate['delete_after_utc']);
            if (!is_int($deleteAt) || $deleteAt <= $submitted) {
                throw new RuntimeException('Legacy private Daily draft has an invalid retention deadline; original was preserved.');
            }
            $deleteAt = min($deleteAt, $cap);
        } else {
            $deleteAt = $cap;
        }
        if ($deleteAt <= $now) return ['status' => 'expired'];
        $candidate['delete_after_utc'] = gmdate('c', $deleteAt);
        $draft['_meta']['candidate'] = $candidate;
        $draft['_meta']['delete_after_utc'] = gmdate('c', $deleteAt);
    }

    $sourceDigest = hash('sha256', $raw);
    $id = substr(hash('sha256', "release-16-daily-draft\0" . $raw), 0, 32);
    $draft['_meta']['draft_id'] = $id;
    $draft['_meta']['human_review_required'] = true;
    // Pre-Release-16 sources were not cryptographically bound to provider
    // evidence, so preserve the work for its owner without making it publishable.
    $draft['_meta']['publication_status'] = 'legacy-unverified-draft';
    $draft['_meta']['legacy_source_sha256'] = $sourceDigest;
    unset($draft['_meta']['provider_response_id'], $draft['_meta']['provider_usage']);
    return ['status' => 'migrate', 'draft' => $draft];
}

function tw_daily_legacy_install_draft(string $privateDir, array $draft): string {
    $id = is_string($draft['_meta']['draft_id'] ?? null) ? $draft['_meta']['draft_id'] : '';
    $digest = is_string($draft['_meta']['legacy_source_sha256'] ?? null)
        ? $draft['_meta']['legacy_source_sha256']
        : '';
    if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1 || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1) {
        throw new RuntimeException('Legacy Daily draft migration identity is invalid.');
    }
    $draftsDir = tw_daily_legacy_prepare_drafts_dir($privateDir);
    $target = $draftsDir . '/' . $id . '.json';
    if (tw_daily_legacy_path_exists($target)) {
        $existing = tw_daily_legacy_read_json($target, 2097152);
        if (($existing['_meta']['draft_id'] ?? null) !== $id
            || ($existing['_meta']['legacy_source_sha256'] ?? null) !== $digest
        ) {
            throw new RuntimeException('Existing Daily draft migration target does not match its source.');
        }
    } else {
        tw_daily_legacy_atomic_json($target, $draft);
    }

    $pointerPath = $privateDir . '/daily-current-draft.json';
    $replacePointer = true;
    if (tw_daily_legacy_path_exists($pointerPath)) {
        $pointer = tw_daily_legacy_read_json($pointerPath, 65536);
        $pointerId = $pointer['draft_id'] ?? null;
        if (!is_string($pointerId) || ($pointerId !== '' && preg_match('/^[a-f0-9]{32}$/D', $pointerId) !== 1)) {
            throw new RuntimeException('Existing Daily draft pointer is invalid; legacy source was preserved.');
        }
        if ($pointerId !== '' && $pointerId !== $id) {
            $pointedPath = $draftsDir . '/' . $pointerId . '.json';
            $pointed = tw_daily_legacy_assert_regular($pointedPath, 'Existing Daily draft');
            $replacePointer = $pointed === null;
        }
        if ($pointerId === $id) $replacePointer = false;
    }
    if ($replacePointer) {
        // An existing regular pointer is replaced only after it was parsed and
        // found empty or stale. The atomic writer refuses unsafe path types.
        if (tw_daily_legacy_path_exists($pointerPath)) {
            $replacement = $pointerPath . '.replacement-' . bin2hex(random_bytes(8));
            tw_daily_legacy_atomic_json($replacement, [
                'draft_id' => $id,
                'updated_at_utc' => gmdate('c'),
                'legacy_migration' => true,
            ]);
            if (tw_daily_legacy_assert_regular($pointerPath, 'Daily draft pointer') === null
                || !rename($replacement, $pointerPath)
            ) {
                @unlink($replacement);
                throw new RuntimeException('Daily draft pointer could not be replaced safely.');
            }
        } else {
            tw_daily_legacy_atomic_json($pointerPath, [
                'draft_id' => $id,
                'updated_at_utc' => gmdate('c'),
                'legacy_migration' => true,
            ]);
        }
    }
    return $id;
}

function tw_daily_legacy_prepare_locks_dir(string $privateDir): string {
    $dir = $privateDir . '/locks';
    if (is_link($dir)) throw new RuntimeException('Daily derivative lock directory must not be a symlink.');
    if (!file_exists($dir) && !mkdir($dir, 0700)) {
        throw new RuntimeException('Daily derivative lock directory could not be created.');
    }
    clearstatcache(true, $dir);
    $stat = lstat($dir);
    $mode = is_array($stat) ? (int)($stat['mode'] ?? 0) : 0;
    if (is_link($dir) || !is_array($stat) || ($mode & 0170000) !== 0040000 || ($mode & 0777) !== 0700) {
        throw new RuntimeException('Daily derivative lock directory is insecure.');
    }
    return $dir;
}

function tw_daily_legacy_replace_json(string $path, array $data, array $expected): void {
    $replacement = $path . '.replacement-' . bin2hex(random_bytes(8));
    tw_daily_legacy_atomic_json($replacement, $data);
    $replacementStat = tw_daily_legacy_assert_regular($replacement, 'Daily candidate replacement');
    try {
        $current = tw_daily_legacy_assert_regular($path, 'Daily candidate queue');
        if (!tw_daily_legacy_same_file($expected, $current) || !rename($replacement, $path)) {
            throw new RuntimeException('Daily candidate queue changed before its retention update.');
        }
    } catch (Throwable $error) {
        @unlink($replacement);
        throw $error;
    }
    clearstatcache(true, $path);
    $final = lstat($path);
    if (!tw_daily_legacy_same_file($replacementStat, $final)
        || !tw_daily_legacy_regular_stat($final)
        || ((((int)($final['mode'] ?? 0)) & 0777) !== 0600)
    ) {
        throw new RuntimeException('Daily candidate retention update could not be verified.');
    }
}

/**
 * Remove private-question derivatives when their source retention has ended.
 * Nonprivate library candidates are preserved byte-for-data; malformed queue
 * containers fail closed rather than being reset.
 */
function tw_daily_prune_expired_candidate_derivatives(string $privateDir, ?int $now = null): int {
    if ($privateDir === '' || $privateDir[0] !== '/' || str_contains($privateDir, "\0")) {
        throw new RuntimeException('Daily candidate retention path is invalid.');
    }
    if (!tw_daily_legacy_path_exists($privateDir)) return 0;
    $privateDir = tw_daily_legacy_private_dir($privateDir);
    $path = $privateDir . '/daily-candidates.json';
    $preflight = tw_daily_legacy_assert_regular($path, 'Daily candidate queue');
    if ($preflight === null) return 0;

    $locksDir = tw_daily_legacy_prepare_locks_dir($privateDir);
    $lockPath = $locksDir . '/daily-candidates.lock';
    $lockExpected = tw_daily_legacy_assert_regular($lockPath, 'Daily candidate retention lock');
    $lock = tw_daily_legacy_open_file($lockPath, 'c+b', 'Daily candidate retention lock', $lockExpected);
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        throw new RuntimeException('Daily candidate retention lock could not be acquired.');
    }
    try {
        $expected = tw_daily_legacy_assert_regular($path, 'Daily candidate queue');
        if ($expected === null) return 0;
        $queue = tw_daily_legacy_read_json($path, 2097152);
        $candidates = $queue['candidates'] ?? null;
        if (!is_array($candidates) || !array_is_list($candidates)) {
            throw new RuntimeException('Daily candidate queue is corrupted; original was preserved.');
        }
        $now ??= time();
        $kept = [];
        $removed = 0;
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                throw new RuntimeException('Daily candidate queue contains an invalid record; original was preserved.');
            }
            if (!tw_daily_legacy_private_candidate($candidate)) {
                $kept[] = $candidate;
                continue;
            }
            $submitted = $candidate['timestamp'] ?? null;
            $deleteAt = null;
            if (is_int($submitted) && $submitted > 0 && $submitted <= $now) {
                $cap = $submitted + (tw_daily_legacy_retention_days() * 86400);
                if (!array_key_exists('delete_after_utc', $candidate)) {
                    $deleteAt = $cap;
                } else {
                    $explicit = tw_daily_legacy_parse_utc($candidate['delete_after_utc']);
                    if (is_int($explicit) && $explicit > $submitted) $deleteAt = min($explicit, $cap);
                }
            }
            if (!is_int($deleteAt) || $deleteAt <= $now) {
                $removed++;
                continue;
            }
            $candidate['delete_after_utc'] = gmdate('c', $deleteAt);
            $kept[] = $candidate;
        }
        if ($removed === 0 && $kept === $candidates) return 0;
        $queue['candidates'] = $kept;
        $queue['candidate_count'] = count($kept);
        $queue['private_retention_pruned_at_utc'] = gmdate('c', $now);
        clearstatcache(true, $path);
        $afterRead = lstat($path);
        if (!tw_daily_legacy_same_file($expected, $afterRead)) {
            throw new RuntimeException('Daily candidate queue changed during retention review.');
        }
        tw_daily_legacy_replace_json($path, $queue, $afterRead);
        return $removed;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** @param array<string, array{handle: resource, path: string, label: string, raw: string, stat: array}> $records */
function tw_daily_legacy_retire_records(array $records): int {
    if ($records === []) return 0;
    $nonce = bin2hex(random_bytes(8));
    $moved = [];
    try {
        foreach ($records as $name => &$record) {
            clearstatcache(true, $record['path']);
            $current = lstat($record['path']);
            $handleStat = fstat($record['handle']);
            if (is_link($record['path'])
                || !tw_daily_legacy_same_file($current, $handleStat)
                || !tw_daily_legacy_same_file($record['stat'], $handleStat)
            ) {
                throw new RuntimeException($record['label'] . ' changed before retirement.');
            }
            $retired = $record['path'] . '.retired-' . $nonce;
            if (tw_daily_legacy_path_exists($retired) || !rename($record['path'], $retired)) {
                throw new RuntimeException($record['label'] . ' could not be staged for retirement.');
            }
            clearstatcache(true, $retired);
            if (!tw_daily_legacy_same_file(lstat($retired), $handleStat)) {
                throw new RuntimeException($record['label'] . ' changed during retirement.');
            }
            $record['retired_path'] = $retired;
            $moved[] = $name;
        }
        unset($record);
    } catch (Throwable $error) {
        unset($record);
        foreach (array_reverse($moved) as $name) {
            $retired = $records[$name]['retired_path'];
            if (!tw_daily_legacy_path_exists($records[$name]['path']) && tw_daily_legacy_path_exists($retired)) {
                @rename($retired, $records[$name]['path']);
            }
        }
        throw $error;
    }

    $zeroed = [];
    foreach ($records as $name => $record) {
        $handle = $record['handle'];
        $ok = ftruncate($handle, 0)
            && fseek($handle, 0) === 0
            && fflush($handle)
            && (!function_exists('fsync') || fsync($handle));
        $stat = fstat($handle);
        if (!$ok || !is_array($stat) || ($stat['size'] ?? null) !== 0) {
            // Best-effort rollback preserves originals when retirement itself
            // cannot securely erase every staged inode.
            $restored = true;
            foreach ($records as $restore) {
                $restoreHandle = $restore['handle'];
                $restoreOk = ftruncate($restoreHandle, 0)
                    && fseek($restoreHandle, 0) === 0
                    && tw_daily_legacy_write_all($restoreHandle, $restore['raw']);
                $restored = $restored && $restoreOk;
            }
            foreach (array_reverse($moved) as $movedName) {
                $retired = $records[$movedName]['retired_path'];
                $original = $records[$movedName]['path'];
                if (!tw_daily_legacy_path_exists($original) && tw_daily_legacy_path_exists($retired)) {
                    $restored = @rename($retired, $original) && $restored;
                }
            }
            throw new RuntimeException($restored
                ? 'Daily legacy retirement failed; original files were restored.'
                : 'Daily legacy retirement failed closed during rollback.');
        }
        $zeroed[] = $name;
    }

    // Raw bytes are already erased and synced. A failed unlink can leave only
    // an owner-only, zero-byte quarantine inode—not the credential or prompt.
    foreach ($records as $record) {
        $retired = $record['retired_path'];
        if (tw_daily_legacy_path_exists($retired)) @unlink($retired);
    }
    return count($zeroed);
}

/** @return array{retired: int, migrated_drafts: int, expired_or_invalid_drafts: int} */
function tw_daily_retire_legacy_private_artifacts(string $privateDir): array {
    if ($privateDir === '' || $privateDir[0] !== '/' || str_contains($privateDir, "\0")) {
        throw new RuntimeException('Daily legacy migration private directory path is invalid.');
    }
    if (!tw_daily_legacy_path_exists($privateDir)) {
        return ['retired' => 0, 'migrated_drafts' => 0, 'expired_or_invalid_drafts' => 0];
    }
    $privateDir = tw_daily_legacy_private_dir($privateDir);
    $spec = [
        'daily-last-error.json' => ['Daily legacy error record', 1048576],
        'daily-draft.json' => ['Daily legacy draft', 2097152],
        'daily-admin-token.txt' => ['Daily legacy admin credential', 8192],
    ];

    // Validate every exact path before opening, migrating, or retiring any one
    // of them. Unsafe aliases and special files stop the whole operation.
    $expected = [];
    foreach ($spec as $name => [$label]) {
        $stat = tw_daily_legacy_assert_regular($privateDir . '/' . $name, $label);
        if ($stat !== null) $expected[$name] = $stat;
    }

    $lockPath = $privateDir . '/.daily-legacy-migration.lock';
    $lockExpected = tw_daily_legacy_assert_regular($lockPath, 'Daily legacy migration lock');
    $lock = tw_daily_legacy_open_file($lockPath, 'c+b', 'Daily legacy migration lock', $lockExpected);
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        throw new RuntimeException('Daily legacy migration lock could not be acquired.');
    }

    $records = [];
    try {
        // Revalidate under the lock. A path that appeared or changed since the
        // initial all-file preflight is treated as unsafe.
        foreach ($spec as $name => [$label, $maxBytes]) {
            $path = $privateDir . '/' . $name;
            $current = tw_daily_legacy_assert_regular($path, $label);
            $before = $expected[$name] ?? null;
            if (($before === null) !== ($current === null)
                || ($before !== null && !tw_daily_legacy_same_file($before, $current))
            ) {
                throw new RuntimeException($label . ' changed during migration preflight.');
            }
            if ($current !== null) $records[$name] = tw_daily_legacy_open_record($path, $label, $maxBytes, $current);
        }

        $migrated = 0;
        $retiredDraft = 0;
        if (isset($records['daily-draft.json'])) {
            $prepared = tw_daily_legacy_prepare_draft($records['daily-draft.json']['raw'], time());
            if ($prepared['status'] === 'expired') {
                $retiredDraft = 1;
            } else {
                tw_daily_legacy_install_draft($privateDir, $prepared['draft']);
                $migrated = 1;
            }
        }
        $retired = tw_daily_legacy_retire_records($records);
        return [
            'retired' => $retired,
            'migrated_drafts' => $migrated,
            'expired_or_invalid_drafts' => $retiredDraft,
        ];
    } finally {
        foreach ($records as $record) {
            if (is_resource($record['handle'])) {
                flock($record['handle'], LOCK_UN);
                fclose($record['handle']);
            }
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
