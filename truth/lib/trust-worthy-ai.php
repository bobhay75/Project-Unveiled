<?php
declare(strict_types=1);
require_once __DIR__ . '/intake.php';

/**
 * Trust-Worthy AI gateway.
 * Secrets stay outside public_html in site-private/trust-worthy/openai-key.txt.
 * Conservative defaults protect a small prepaid API balance.
 */
function tw_private_dir(): string {
    if (defined('TW_PRIVATE_DIR_OVERRIDE')) {
        return rtrim((string)constant('TW_PRIVATE_DIR_OVERRIDE'), '/');
    }
    return dirname(__DIR__, 3) . '/site-private/trust-worthy';
}

function tw_ai_config(): array {
    return [
        'model' => getenv('TW_OPENAI_MODEL') ?: 'gpt-5.6-luna',
        // This ceiling includes visible output AND reasoning tokens.
        'max_output_tokens' => 3000,
        'reasoning_effort' => 'low',
        'daily_request_cap' => 20,
        'per_ip_daily_cap' => 3,
        'max_web_search_calls' => 1,
        'max_question_characters' => 3000,
        'max_context_characters' => 3000,
        'timeout_seconds' => 60,
    ];
}

function tw_ai_private_path_exists(string $path): bool {
    return file_exists($path) || is_link($path);
}

function tw_ai_prepare_private_dir(): bool {
    $dir = tw_private_dir();
    if (is_link($dir)) return false;
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) return false;
    if (!is_dir($dir) || !is_writable($dir) || !@chmod($dir, 0750)) return false;
    clearstatcache(true, $dir);
    $mode = @fileperms($dir);
    return is_int($mode) && ($mode & 0777) === 0750;
}

function tw_ai_secure_open_regular_file($handle, string $path, int $mode = 0600): bool {
    if (!is_resource($handle)) return false;
    $opened = @fstat($handle);
    if (!is_array($opened) || ((((int)$opened['mode']) & 0170000) !== 0100000)) return false;
    // Establish that the opened inode is still the non-symlink path before
    // changing its mode. Otherwise a path-swap race could chmod an unrelated
    // symlink target even though the subsequent identity check fails closed.
    clearstatcache(true, $path);
    if (is_link($path)) return false;
    $pathStat = @lstat($path);
    if (!is_array($pathStat)
        || ((((int)$pathStat['mode']) & 0170000) !== 0100000)
        || (int)$pathStat['dev'] !== (int)$opened['dev']
        || (int)$pathStat['ino'] !== (int)$opened['ino']
        || !@fchmod($handle, $mode)) return false;
    clearstatcache(true, $path);
    if (is_link($path)) return false;
    $pathStat = @lstat($path);
    $opened = @fstat($handle);
    return is_array($pathStat)
        && is_array($opened)
        && ((((int)$pathStat['mode']) & 0170000) === 0100000)
        && ((((int)$opened['mode']) & 0170000) === 0100000)
        && (int)$pathStat['dev'] === (int)$opened['dev']
        && (int)$pathStat['ino'] === (int)$opened['ino']
        && ((((int)$pathStat['mode']) & 0777) === $mode)
        && ((((int)$opened['mode']) & 0777) === $mode);
}

function tw_ai_secure_existing_regular_file(string $path, int $mode = 0600): bool {
    if (!tw_ai_private_path_exists($path)) return false;
    $handle = @fopen($path, 'r+b');
    if (!is_resource($handle)) return false;
    try {
        return tw_ai_secure_open_regular_file($handle, $path, $mode);
    } finally {
        @fclose($handle);
    }
}

function tw_ai_read_secure_regular_file(string $path, int $minimumBytes, int $maximumBytes): ?string {
    if ($minimumBytes < 0 || $maximumBytes < $minimumBytes || !tw_ai_private_path_exists($path)) return null;
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) return null;
    try {
        if (!tw_ai_secure_open_regular_file($handle, $path, 0600)) return null;
        $before = @fstat($handle);
        if (!is_array($before)
            || (int)$before['size'] < $minimumBytes
            || (int)$before['size'] > $maximumBytes) return null;
        $raw = @stream_get_contents($handle, $maximumBytes + 1);
        if (!is_string($raw) || strlen($raw) !== (int)$before['size']) return null;
        if (!tw_ai_secure_open_regular_file($handle, $path, 0600)) return null;
        $after = @fstat($handle);
        if (!is_array($after)
            || (int)$after['size'] !== (int)$before['size']
            || (int)$after['dev'] !== (int)$before['dev']
            || (int)$after['ino'] !== (int)$before['ino']) return null;
        return $raw;
    } finally {
        @fclose($handle);
    }
}

function tw_ai_read_owner_regular_file_readonly(string $path, int $minimumBytes, int $maximumBytes): ?string {
    if ($minimumBytes < 0 || $maximumBytes < $minimumBytes) return null;
    clearstatcache(true, $path);
    if (is_link($path)) return null;
    $beforePath = @lstat($path);
    if (!is_array($beforePath)
        || ((((int)$beforePath['mode']) & 0170000) !== 0100000)
        || ((((int)$beforePath['mode']) & 0777) !== 0600)) return null;
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) return null;
    try {
        $beforeOpen = @fstat($handle);
        if (!is_array($beforeOpen)
            || ((((int)$beforeOpen['mode']) & 0170000) !== 0100000)
            || ((((int)$beforeOpen['mode']) & 0777) !== 0600)
            || (int)$beforeOpen['dev'] !== (int)$beforePath['dev']
            || (int)$beforeOpen['ino'] !== (int)$beforePath['ino']
            || (int)$beforeOpen['size'] < $minimumBytes
            || (int)$beforeOpen['size'] > $maximumBytes) return null;
        $raw = @stream_get_contents($handle, $maximumBytes + 1);
        if (!is_string($raw) || strlen($raw) !== (int)$beforeOpen['size']) return null;
        clearstatcache(true, $path);
        if (is_link($path)) return null;
        $afterPath = @lstat($path);
        $afterOpen = @fstat($handle);
        if (!is_array($afterPath)
            || !is_array($afterOpen)
            || ((((int)$afterPath['mode']) & 0170000) !== 0100000)
            || ((((int)$afterOpen['mode']) & 0170000) !== 0100000)
            || ((((int)$afterPath['mode']) & 0777) !== 0600)
            || ((((int)$afterOpen['mode']) & 0777) !== 0600)
            || (int)$afterPath['dev'] !== (int)$beforeOpen['dev']
            || (int)$afterPath['ino'] !== (int)$beforeOpen['ino']
            || (int)$afterOpen['dev'] !== (int)$beforeOpen['dev']
            || (int)$afterOpen['ino'] !== (int)$beforeOpen['ino']
            || (int)$afterOpen['size'] !== (int)$beforeOpen['size']) return null;
        return $raw;
    } finally {
        @fclose($handle);
    }
}

function tw_ai_write_all($handle, string $bytes): bool {
    $length = strlen($bytes);
    $offset = 0;
    while ($offset < $length) {
        $written = @fwrite($handle, substr($bytes, $offset));
        if ($written === false || $written === 0) return false;
        $offset += $written;
    }
    if (!@fflush($handle)) return false;
    return !function_exists('fsync') || @fsync($handle);
}

function tw_ai_atomic_private_write(string $path, string $bytes): bool {
    if (!tw_ai_prepare_private_dir() || dirname($path) !== tw_private_dir()) return false;
    if (tw_ai_private_path_exists($path) && !tw_ai_secure_existing_regular_file($path, 0600)) return false;
    try {
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(12));
    } catch (Throwable $error) {
        return false;
    }
    $handle = @fopen($temporary, 'x+b');
    if (!is_resource($handle)) return false;
    $ok = tw_ai_secure_open_regular_file($handle, $temporary, 0600) && tw_ai_write_all($handle, $bytes);
    if (!@fclose($handle)) $ok = false;
    if (!$ok) {
        @unlink($temporary);
        return false;
    }
    if (tw_ai_private_path_exists($path) && !tw_ai_secure_existing_regular_file($path, 0600)) {
        @unlink($temporary);
        return false;
    }
    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        return false;
    }
    // The exclusive temp file was already verified regular and 0600 before any
    // byte was written. rename() preserves that inode and mode atomically.
    return true;
}

function tw_ai_with_private_file_lock(string $lockPath, callable $operation): mixed {
    if (!tw_ai_prepare_private_dir() || dirname($lockPath) !== tw_private_dir()) {
        throw new RuntimeException('Private research storage is unavailable.');
    }
    if (tw_ai_private_path_exists($lockPath) && !tw_ai_secure_existing_regular_file($lockPath, 0600)) {
        throw new RuntimeException('Private research lock is unsafe.');
    }
    $lock = @fopen($lockPath, 'c+b');
    if (!is_resource($lock)) throw new RuntimeException('Private research lock is unavailable.');
    if (!tw_ai_secure_open_regular_file($lock, $lockPath, 0600) || !@flock($lock, LOCK_EX)) {
        @fclose($lock);
        throw new RuntimeException('Private research lock could not be secured.');
    }
    try {
        if (!tw_ai_secure_open_regular_file($lock, $lockPath, 0600)) {
            throw new RuntimeException('Private research lock changed unexpectedly.');
        }
        return $operation();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function tw_openai_key(): string {
    $env = trim((string)getenv('OPENAI_API_KEY'));
    $file = tw_private_dir() . '/openai-key.txt';
    if (tw_ai_private_path_exists($file)) {
        if (!tw_ai_prepare_private_dir()) return '';
        $raw = tw_ai_read_secure_regular_file($file, 1, 8192);
        if (!is_string($raw) || trim($raw) === '') return '';
        return $env !== '' ? $env : trim($raw);
    }
    return $env;
}

function tw_openai_key_readonly(): string {
    $env = trim((string)getenv('OPENAI_API_KEY'));
    $file = tw_private_dir() . '/openai-key.txt';
    if (!tw_ai_private_path_exists($file)) return $env;
    $raw = tw_ai_read_owner_regular_file_readonly($file, 1, 8192);
    if (!is_string($raw) || trim($raw) === '') return '';
    return $env !== '' ? $env : trim($raw);
}

function tw_question_secret_readonly(): string {
    $raw = tw_ai_read_owner_regular_file_readonly(tw_private_dir() . '/question-secret.txt', 1, 8192);
    if (!is_string($raw)) return '';
    $secret = trim($raw);
    return preg_match('/^[a-f0-9]{64}$/D', $secret) === 1 ? $secret : '';
}

function tw_question_secret(): string {
    try {
        return tw_intake_secret('question');
    } catch (Throwable $error) {
        return '';
    }
}

function tw_usage_file(): string {
    return tw_private_dir() . '/ai-usage-' . gmdate('Y-m-d') . '.json';
}

function tw_maintain_ai_usage_files(): bool {
    if (!tw_ai_prepare_private_dir()) return false;
    $matches = glob(tw_private_dir() . '/ai-usage-*.json*');
    $legacyTemporary = glob(tw_private_dir() . '/.ai-usage-*');
    if (!is_array($matches) || !is_array($legacyTemporary)) return false;
    $matches = array_values(array_filter($matches, static fn(string $path): bool =>
        preg_match('/^ai-usage-\d{4}-\d{2}-\d{2}\.json(?:\.lock)?$/D', basename($path)) === 1
    ));
    $legacyTemporary = array_values(array_filter($legacyTemporary, static fn(string $path): bool =>
        preg_match('/^\.ai-usage-[a-zA-Z0-9]{6}$/D', basename($path)) === 1
    ));
    if (count($matches) + count($legacyTemporary) > 10000) return false;
    foreach ($legacyTemporary as $path) {
        if (!tw_ai_private_path_exists($path)) continue;
        if (!tw_ai_secure_existing_regular_file($path, 0600)) return false;
        $modified = @filemtime($path);
        // These are leftovers from the pre-Release-16 tempnam writer. A live
        // request running old code may still own a fresh file during a rolling
        // deployment, so only retire one that has been abandoned for a day.
        if (!is_int($modified) || $modified >= time() - 86400) continue;
        if (!@unlink($path) && tw_ai_private_path_exists($path)) return false;
    }
    $cutoff = time() - (45 * 86400);
    foreach ($matches as $path) {
        $name = basename($path);
        if (!tw_ai_private_path_exists($path)) continue;
        if (!tw_ai_secure_existing_regular_file($path, 0600)) {
            if (!tw_ai_private_path_exists($path)) continue;
            return false;
        }
        if (!preg_match('/^ai-usage-(\d{4}-\d{2}-\d{2})\.json(?:\.lock)?$/D', $name, $match)) continue;
        $timestamp = strtotime($match[1] . ' 00:00:00 UTC');
        if (!is_int($timestamp) || gmdate('Y-m-d', $timestamp) !== $match[1]) return false;
        if ($timestamp < $cutoff && !@unlink($path) && tw_ai_private_path_exists($path)) return false;
    }
    return tw_ai_maintain_atomic_temporaries();
}

function tw_ai_maintain_atomic_temporaries(): bool {
    if (!tw_ai_prepare_private_dir()) return false;
    $matches = glob(tw_private_dir() . '/*.tmp-*');
    if (!is_array($matches) || count($matches) > 10000) return false;
    $cutoff = time() - 86400;
    foreach ($matches as $path) {
        $name = basename($path);
        if (preg_match('/^(?:ai-usage-\d{4}-\d{2}-\d{2}\.json|ai-investigations\.jsonl|openai-errors\.jsonl)\.tmp-[a-f0-9]{16,64}$/D', $name) !== 1) continue;
        if (!tw_ai_private_path_exists($path)) continue;
        if (!tw_ai_secure_existing_regular_file($path, 0600)) return false;
        $modified = @filemtime($path);
        if (is_int($modified) && $modified < $cutoff && !@unlink($path) && tw_ai_private_path_exists($path)) return false;
    }
    return true;
}

function tw_ai_validate_usage_bytes(string $raw): array {
    if (!is_string($raw) || trim($raw) === '') return [false, []];
    $usage = json_decode($raw, true);
    if (!is_array($usage)) return [false, []];

    $total = $usage['total'] ?? null;
    $ips = $usage['ips'] ?? null;
    $reservations = $usage['reservations'] ?? [];
    $limits = tw_ai_config();
    if (!is_int($total)
        || $total < 0
        || $total > $limits['daily_request_cap']
        || !is_array($ips)
        || count($ips) > $limits['daily_request_cap']
        || !is_array($reservations)
        || count($reservations) > $limits['daily_request_cap']) {
        return [false, []];
    }
    $ipTotal = 0;
    foreach ($ips as $hash => $count) {
        if (!is_string($hash)
            || !preg_match('/^[a-f0-9]{64}$/', $hash)
            || !is_int($count)
            || $count < 1
            || $count > $limits['per_ip_daily_cap']) return [false, []];
        $ipTotal += $count;
    }
    if ($ipTotal !== $total || count($reservations) > $total) return [false, []];
    $pendingByIp = [];
    $todayStart = strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
    if (!is_int($todayStart)) return [false, []];
    foreach ($reservations as $token => $reservation) {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token) || !is_array($reservation)) return [false, []];
        $fields = array_keys($reservation);
        sort($fields, SORT_STRING);
        if ($fields !== ['at_utc', 'ip_hash']) return [false, []];
        $reservedIp = $reservation['ip_hash'] ?? null;
        $reservedAt = tw_ai_parse_utc_timestamp($reservation['at_utc'] ?? null);
        if (!is_string($reservedIp)
            || preg_match('/^[a-f0-9]{64}$/', $reservedIp) !== 1
            || !isset($ips[$reservedIp])
            || $reservedAt === null
            || $reservedAt < $todayStart
            || $reservedAt > time() + 300) return [false, []];
        $pendingByIp[$reservedIp] = ($pendingByIp[$reservedIp] ?? 0) + 1;
        if ($pendingByIp[$reservedIp] > $ips[$reservedIp]) return [false, []];
    }

    $usage['version'] = 2;
    $usage['total'] = $total;
    $usage['ips'] = $ips;
    $usage['reservations'] = $reservations;
    return [true, $usage];
}

function tw_read_usage_file(string $file): array {
    if (!tw_ai_private_path_exists($file)) {
        return [true, ['version' => 2, 'total' => 0, 'ips' => [], 'reservations' => []]];
    }
    $raw = tw_ai_read_secure_regular_file($file, 2, 262144);
    return is_string($raw) ? tw_ai_validate_usage_bytes($raw) : [false, []];
}

function tw_read_usage_file_readonly(string $file): array {
    if (!tw_ai_private_path_exists($file)) {
        return [true, ['version' => 2, 'total' => 0, 'ips' => [], 'reservations' => []]];
    }
    $raw = tw_ai_read_owner_regular_file_readonly($file, 2, 262144);
    return is_string($raw) ? tw_ai_validate_usage_bytes($raw) : [false, []];
}

function tw_write_usage_file(string $file, array $usage): bool {
    $json = json_encode($usage, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) return false;
    if (strlen($json) + 1 > 262144) return false;
    return tw_ai_atomic_private_write($file, $json . PHP_EOL);
}

function tw_with_usage_lock(callable $operation): array {
    $file = tw_usage_file();
    if (!tw_maintain_ai_usage_files()) return [false, 'Private storage unavailable.'];
    try {
        return tw_ai_with_private_file_lock($file . '.lock', static function () use ($file, $operation): array {
            [$valid, $usage] = tw_read_usage_file($file);
            if (!$valid) return [false, 'Research allowance storage is unavailable.'];
            return $operation($file, $usage);
        });
    } catch (Throwable $error) {
        return [false, 'Private storage unavailable.'];
    }
}

function tw_rate_limit(string $ipHash): array {
    $cfg = tw_ai_config();
    if (!preg_match('/^[a-f0-9]{64}$/', $ipHash)) {
        return [false, 'Research allowance storage is unavailable.', '', 'storage_unavailable'];
    }

    $result = tw_with_usage_lock(function (string $file, array $usage) use ($cfg, $ipHash): array {
        $total = $usage['total'];
        $ips = $usage['ips'];
        $mine = $ips[$ipHash] ?? 0;
        if ($total >= $cfg['daily_request_cap']) {
            return [false, 'Today’s research allowance has been reached.', '', 'allowance_exhausted'];
        }
        if ($mine >= $cfg['per_ip_daily_cap']) {
            return [false, 'Your free research allowance for today has been reached.', '', 'allowance_exhausted'];
        }

        try {
            $reservation = bin2hex(random_bytes(32));
        } catch (Throwable $error) {
            return [false, 'Research allowance storage is unavailable.', '', 'storage_unavailable'];
        }
        $usage['total'] = $total + 1;
        $ips[$ipHash] = $mine + 1;
        $usage['ips'] = $ips;
        $usage['reservations'][$reservation] = ['ip_hash' => $ipHash, 'at_utc' => gmdate('c')];
        if (!tw_write_usage_file($file, $usage)) {
            return [false, 'Research allowance storage is unavailable.', '', 'storage_unavailable'];
        }
        return [true, '', $reservation, 'reserved'];
    });

    if (count($result) === 2) return [$result[0], $result[1], '', 'storage_unavailable'];
    return $result;
}

function tw_release_rate_limit(string $ipHash, string $reservation): bool {
    if (!preg_match('/^[a-f0-9]{64}$/', $ipHash) || !preg_match('/^[a-f0-9]{64}$/', $reservation)) return false;
    $result = tw_with_usage_lock(function (string $file, array $usage) use ($ipHash, $reservation): array {
        $reserved = $usage['reservations'][$reservation] ?? null;
        if (!is_array($reserved) || !hash_equals((string)($reserved['ip_hash'] ?? ''), $ipHash)) {
            return [true, false];
        }

        $mine = $usage['ips'][$ipHash] ?? 0;
        if ($usage['total'] < 1 || $mine < 1) return [false, 'Research allowance storage is unavailable.'];
        $usage['total']--;
        if ($mine === 1) unset($usage['ips'][$ipHash]);
        else $usage['ips'][$ipHash] = $mine - 1;
        unset($usage['reservations'][$reservation]);
        if (!tw_write_usage_file($file, $usage)) return [false, 'Research allowance storage is unavailable.'];
        return [true, true];
    });
    return ($result[0] ?? false) === true && ($result[1] ?? false) === true;
}

function tw_commit_rate_limit(string $ipHash, string $reservation): bool {
    if (!preg_match('/^[a-f0-9]{64}$/', $ipHash) || !preg_match('/^[a-f0-9]{64}$/', $reservation)) return false;
    $result = tw_with_usage_lock(function (string $file, array $usage) use ($ipHash, $reservation): array {
        $reserved = $usage['reservations'][$reservation] ?? null;
        if (!is_array($reserved) || !hash_equals((string)($reserved['ip_hash'] ?? ''), $ipHash)) {
            return [true, false];
        }
        unset($usage['reservations'][$reservation]);
        if (!tw_write_usage_file($file, $usage)) return [false, 'Research allowance storage is unavailable.'];
        return [true, true];
    });
    return ($result[0] ?? false) === true && ($result[1] ?? false) === true;
}

function tw_finalize_rate_limit(string $ipHash, string $reservation, bool $quotaConsumed): bool {
    return $quotaConsumed
        ? tw_commit_rate_limit($ipHash, $reservation)
        : tw_release_rate_limit($ipHash, $reservation);
}

function tw_ai_utf8_length(string $value): ?int {
    if (preg_match('//u', $value) !== 1) return null;
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $count = preg_match_all('/./us', $value, $matches);
    return is_int($count) ? $count : null;
}

function tw_ai_safe_model_identifier(mixed $value): string {
    if (!is_string($value) || strlen($value) < 1 || strlen($value) > 128) return 'other';
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $value) === 1 ? $value : 'other';
}

function tw_ai_safe_response_id(mixed $value): string {
    if (!is_string($value) || strlen($value) < 13 || strlen($value) > 255) return '';
    return preg_match('/^resp_[A-Za-z0-9_-]{8,250}$/D', $value) === 1 ? $value : '';
}

function tw_ai_bounded_nonnegative_int(mixed $value, int $maximum = 100000000): int {
    return is_int($value) && $value >= 0 && $value <= $maximum ? $value : 0;
}

function tw_ai_parse_utc_timestamp(mixed $value): ?int {
    if (!is_string($value)
        || preg_match('/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:Z|\+00:00)$/D', $value, $parts) !== 1
        || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) return null;
    $timestamp = strtotime($value);
    if (!is_int($timestamp) || gmdate('Y-m-d\TH:i:s', $timestamp) !== substr($value, 0, 19)) return null;
    return $timestamp;
}

function tw_ai_read_jsonl_strict(string $path, int $maxInputBytes, int $maxInputRecords, bool $readOnly = false): array {
    if (!tw_ai_private_path_exists($path)) return [];
    $raw = $readOnly
        ? tw_ai_read_owner_regular_file_readonly($path, 0, $maxInputBytes)
        : tw_ai_read_secure_regular_file($path, 0, $maxInputBytes);
    if (!is_string($raw)) throw new RuntimeException('Private research log is invalid, unsafe, or exceeds its migration limit.');
    if ($raw === '') return [];
    if (trim($raw) === '') {
        throw new RuntimeException('Private research log contains no valid records; no data was overwritten.');
    }
    $records = [];
    foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (strlen($line) > 65536 || ($line[0] ?? '') !== '{') {
            throw new RuntimeException('Private research log contains an invalid record; no data was overwritten.');
        }
        try {
            $record = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Private research log is corrupted; no data was overwritten.', 0, $error);
        }
        if (!is_array($record) || $record === []) {
            throw new RuntimeException('Private research log contains an invalid record; no data was overwritten.');
        }
        $records[] = $record;
        if (count($records) > $maxInputRecords) {
            throw new RuntimeException('Private research log exceeds its bounded migration record limit.');
        }
    }
    return $records;
}

function tw_ai_log_record_timestamp(array $record): int {
    $timestamp = tw_ai_parse_utc_timestamp($record['at_utc'] ?? null);
    if ($timestamp === null || $timestamp > time() + 300) {
        throw new RuntimeException('Private research log contains an invalid timestamp; no data was overwritten.');
    }
    return $timestamp;
}

function tw_ai_encode_bounded_jsonl(array $records, int $maxBytes): string {
    $lines = [];
    $bytes = 0;
    foreach ($records as $record) {
        try {
            $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Private research log could not be encoded safely.', 0, $error);
        }
        if (strlen($line) + 1 > $maxBytes) {
            throw new RuntimeException('A private research log record exceeds its storage limit.');
        }
        $lines[] = $line;
        $bytes += strlen($line) + 1;
    }
    while ($bytes > $maxBytes && count($lines) > 1) {
        $bytes -= strlen(array_shift($lines)) + 1;
    }
    if ($bytes > $maxBytes) throw new RuntimeException('Private research log exceeds its storage limit.');
    return $lines === [] ? '' : implode("\n", $lines) . "\n";
}

function tw_ai_rewrite_jsonl(
    string $path,
    callable $transform,
    ?array $append,
    int $retentionDays,
    int $maxRecords,
    int $maxBytes,
    int $maxInputBytes,
    int $maxInputRecords
): int {
    if (!tw_ai_maintain_atomic_temporaries()) {
        throw new RuntimeException('Private research temporary storage could not be maintained safely.');
    }
    return tw_ai_with_private_file_lock($path . '.lock', static function () use (
        $path,
        $transform,
        $append,
        $retentionDays,
        $maxRecords,
        $maxBytes,
        $maxInputBytes,
        $maxInputRecords
    ): int {
        $legacyDataLock = null;
        if (tw_ai_private_path_exists($path)) {
            if (!tw_ai_secure_existing_regular_file($path, 0600)) {
                throw new RuntimeException('Private research log is unsafe.');
            }
            $legacyDataLock = @fopen($path, 'r+b');
            if (!is_resource($legacyDataLock)
                || !tw_ai_secure_open_regular_file($legacyDataLock, $path, 0600)
                || !@flock($legacyDataLock, LOCK_EX)
                || !tw_ai_secure_open_regular_file($legacyDataLock, $path, 0600)
            ) {
                if (is_resource($legacyDataLock)) @fclose($legacyDataLock);
                throw new RuntimeException('Private research log could not coordinate with the legacy writer.');
            }
        }
        try {
            $records = tw_ai_read_jsonl_strict($path, $maxInputBytes, $maxInputRecords);
            $migrated = [];
            $cutoff = time() - ($retentionDays * 86400);
            foreach ($records as $record) {
                $record = $transform($record);
                if (!is_array($record) || $record === []) {
                    throw new RuntimeException('Private research log migration returned an invalid record.');
                }
                if (tw_ai_log_record_timestamp($record) >= $cutoff) $migrated[] = $record;
            }
            if ($append !== null) {
                $record = $transform($append);
                tw_ai_log_record_timestamp($record);
                $migrated[] = $record;
            }
            if (count($migrated) > $maxRecords) {
                $migrated = array_slice($migrated, -$maxRecords);
            }
            $encoded = tw_ai_encode_bounded_jsonl($migrated, $maxBytes);
            if (!tw_ai_atomic_private_write($path, $encoded)) {
                throw new RuntimeException('Private research log could not be replaced securely.');
            }
            return count($migrated);
        } finally {
            if (is_resource($legacyDataLock)) {
                @flock($legacyDataLock, LOCK_UN);
                @fclose($legacyDataLock);
            }
        }
    });
}

function tw_ai_record_contains_raw_prompt(array $record): bool {
    foreach ($record as $key => $value) {
        if (is_string($key) && in_array(strtolower($key), ['question', 'context'], true)) return true;
        if (is_array($value) && tw_ai_record_contains_raw_prompt($value)) return true;
    }
    return false;
}

function tw_ai_scrub_prompt_echoes(mixed $value, array $rawPrompts, string $secret): mixed {
    if (is_array($value)) {
        foreach ($value as $key => $nested) {
            $value[$key] = tw_ai_scrub_prompt_echoes($nested, $rawPrompts, $secret);
        }
        return $value;
    }
    if (!is_string($value)) return $value;
    foreach ($rawPrompts as $field => $prompt) {
        if (!is_string($prompt) || $prompt === '') continue;
        $contains = $value === $prompt || (strlen($prompt) >= 8 && str_contains($value, $prompt));
        if (!$contains) continue;
        $marker = '[redacted-' . $field . '-' . substr(hash_hmac('sha256', $field . '|' . $prompt, $secret), 0, 16) . ']';
        $value = $value === $prompt ? $marker : str_replace($prompt, $marker, $value);
    }
    return $value;
}

function tw_ai_redact_investigation_record(array $record, string $secret): array {
    $rawPrompts = [];
    foreach (['question', 'context'] as $field) {
        if (!array_key_exists($field, $record)) continue;
        $value = $record[$field];
        if (!is_string($value)) {
            throw new RuntimeException('Private investigation log contains a non-text raw prompt; no data was overwritten.');
        }
        if (tw_ai_utf8_length($value) === null) {
            throw new RuntimeException('Private investigation log contains invalid prompt encoding; no data was overwritten.');
        }
        $rawPrompts[$field] = $value;
        unset($record[$field]);
    }
    $record = tw_ai_scrub_prompt_echoes($record, $rawPrompts, $secret);
    foreach ($rawPrompts as $field => $value) {
        $record[$field . '_hash'] = hash_hmac('sha256', $field . '|' . $value, $secret);
        $record[$field . '_characters'] = tw_ai_utf8_length($value);
    }
    if (tw_ai_record_contains_raw_prompt($record)) {
        throw new RuntimeException('Private investigation log contains nested raw prompt data; no data was overwritten.');
    }
    if (!is_string($record['question_hash'] ?? null)
        || preg_match('/^[a-f0-9]{64}$/', $record['question_hash']) !== 1
        || !is_int($record['question_characters'] ?? null)
        || $record['question_characters'] < 0) {
        throw new RuntimeException('Private investigation log is missing its protected question identity; no data was overwritten.');
    }
    if (array_key_exists('response_id', $record)) {
        $responseId = $record['response_id'];
        if (!is_string($responseId) || tw_ai_utf8_length($responseId) === null || strlen($responseId) > 1024) {
            throw new RuntimeException('Private investigation log contains invalid provider metadata; no data was overwritten.');
        }
        if ($responseId !== '') {
            $record['response_id_hash'] = hash_hmac('sha256', 'response-id|' . $responseId, $secret);
            $record['response_id_characters'] = tw_ai_utf8_length($responseId);
        }
        unset($record['response_id']);
    }
    if (array_key_exists('response_id_hash', $record)
        && (!is_string($record['response_id_hash'])
            || preg_match('/^[a-f0-9]{64}$/D', $record['response_id_hash']) !== 1
            || !is_int($record['response_id_characters'] ?? null)
            || $record['response_id_characters'] < 0
            || $record['response_id_characters'] > 1024)) {
        throw new RuntimeException('Private investigation log contains invalid protected provider metadata; no data was overwritten.');
    }
    if (array_key_exists('model', $record)) {
        $record['model'] = tw_ai_safe_model_identifier($record['model']);
    }
    return $record;
}

function tw_prepare_ai_investigation_log(string $secret): void {
    if (preg_match('/^[a-f0-9]{64}$/', $secret) !== 1) {
        throw new RuntimeException('Private investigation log secret is invalid.');
    }
    tw_ai_rewrite_jsonl(
        tw_private_dir() . '/ai-investigations.jsonl',
        static fn(array $record): array => tw_ai_redact_investigation_record($record, $secret),
        null,
        180,
        4000,
        4194304,
        16777216,
        20000
    );
}

function tw_append_ai_investigation_record(array $record, string $secret): void {
    if (preg_match('/^[a-f0-9]{64}$/', $secret) !== 1) {
        throw new RuntimeException('Private investigation log secret is invalid.');
    }
    tw_ai_rewrite_jsonl(
        tw_private_dir() . '/ai-investigations.jsonl',
        static fn(array $entry): array => tw_ai_redact_investigation_record($entry, $secret),
        $record,
        180,
        4000,
        4194304,
        16777216,
        20000
    );
}

function tw_ai_redact_diagnostic_record(array $record, string $secret): array {
    if (preg_match('/^[a-f0-9]{64}$/D', $secret) !== 1) {
        throw new RuntimeException('Private diagnostic log secret is invalid.');
    }
    foreach (['error_message', 'curl_error'] as $field) {
        if (!array_key_exists($field, $record)) continue;
        if (!is_string($record[$field])) {
            throw new RuntimeException('Private diagnostic log contains an invalid message; no data was overwritten.');
        }
        $length = tw_ai_utf8_length($record[$field]);
        if ($length === null) {
            throw new RuntimeException('Private diagnostic log contains invalid encoding; no data was overwritten.');
        }
        $lengthField = $field === 'error_message' ? 'provider_message_characters' : 'curl_error_characters';
        $hashField = $field === 'error_message' ? 'provider_message_hash' : 'curl_error_hash';
        $record[$lengthField] = $length;
        $record[$hashField] = hash_hmac('sha256', $field . '|' . $record[$field], $secret);
        unset($record[$field]);
    }
    foreach (['question', 'context'] as $field) {
        if (array_key_exists($field, $record)) unset($record[$field]);
    }
    if (array_key_exists('error_type', $record)) {
        $record['error_type'] = tw_ai_safe_diagnostic_identifier($record['error_type'], 'type');
    }
    if (array_key_exists('error_code', $record)) {
        $record['error_code'] = tw_ai_safe_diagnostic_identifier($record['error_code'], 'code');
    }
    foreach (['provider_message', 'curl_error'] as $prefix) {
        $hashField = $prefix . '_hash';
        $lengthField = $prefix . '_characters';
        if (array_key_exists($hashField, $record)
            && (!is_string($record[$hashField])
                || preg_match('/^[a-f0-9]{64}$/D', $record[$hashField]) !== 1
                || !is_int($record[$lengthField] ?? null)
                || $record[$lengthField] < 0)) {
            throw new RuntimeException('Private diagnostic log contains invalid protected message metadata; no data was overwritten.');
        }
    }
    return tw_ai_scrub_nested_diagnostic_fields($record);
}

function tw_ai_scrub_nested_diagnostic_fields(array $record): array {
    foreach ($record as $key => $value) {
        $normalizedKey = is_string($key) ? strtolower($key) : '';
        if (in_array($normalizedKey, ['question', 'context', 'error_message', 'curl_error'], true)) {
            unset($record[$key]);
            continue;
        }
        if ($normalizedKey === 'error_type') {
            $record[$key] = tw_ai_safe_diagnostic_identifier($value, 'type');
            continue;
        }
        if ($normalizedKey === 'error_code') {
            $record[$key] = tw_ai_safe_diagnostic_identifier($value, 'code');
            continue;
        }
        if (is_array($value)) $record[$key] = tw_ai_scrub_nested_diagnostic_fields($value);
    }
    return $record;
}

function tw_ai_safe_diagnostic_identifier(mixed $value, string $kind): string {
    if (!is_string($value)) return '';
    $value = strtolower(trim($value));
    if ($value === '') return '';
    $allowed = $kind === 'type'
        ? [
            'api_error', 'authentication_error', 'invalid_request_error',
            'invalid_output_contract', 'incomplete_output', 'not_found_error',
            'permission_error', 'rate_limit_error', 'server_error',
            'unverified_output',
        ]
        : [
            'api_error', 'billing_hard_limit_reached', 'content_policy_violation',
            'context_length_exceeded', 'incomplete_output', 'insufficient_quota',
            'invalid_api_key', 'invalid_prompt', 'invalid_value', 'model_not_found',
            'rate_limit_exceeded', 'server_error', 'unsupported_parameter',
            'unsupported_value',
        ];
    return in_array($value, $allowed, true) ? $value : 'other';
}

function tw_prepare_ai_diagnostic_log(string $secret): void {
    tw_ai_rewrite_jsonl(
        tw_private_dir() . '/openai-errors.jsonl',
        static fn(array $record): array => tw_ai_redact_diagnostic_record($record, $secret),
        null,
        30,
        1000,
        1048576,
        4194304,
        5000
    );
}

function tw_log_openai_diagnostic(int $status, array $data, string $curlError, int $curlNumber, string $secret): bool {
    $error = is_array($data['error'] ?? null) ? $data['error'] : [];
    $errorMessage = is_string($error['message'] ?? null) ? $error['message'] : '';
    $record = [
        'at_utc' => gmdate('c'),
        'http_status' => $status >= 0 && $status <= 599 ? $status : 0,
        'error_type' => tw_ai_safe_diagnostic_identifier($error['type'] ?? '', 'type'),
        'error_code' => tw_ai_safe_diagnostic_identifier($error['code'] ?? '', 'code'),
        'error_message' => $errorMessage,
        'curl_error_code' => $curlNumber >= 0 && $curlNumber <= 999 ? $curlNumber : 0,
        'curl_error' => $curlError,
    ];
    try {
        tw_ai_rewrite_jsonl(
            tw_private_dir() . '/openai-errors.jsonl',
            static fn(array $entry): array => tw_ai_redact_diagnostic_record($entry, $secret),
            $record,
            30,
            1000,
            1048576,
            4194304,
            5000
        );
        return true;
    } catch (Throwable $logError) {
        return false;
    }
}

function tw_migrate_ai_private_storage(): array {
    $dir = tw_private_dir();
    if (!tw_ai_private_path_exists($dir)) {
        // A clean deployment can be configured entirely through the server
        // environment. Initialize its private state here, while the
        // authenticated deployment command is running, rather than making the
        // public health endpoint create directories or secrets. An
        // unconfigured clean install remains a no-op.
        if (trim((string)getenv('OPENAI_API_KEY')) === '') {
            return ['key' => 0, 'investigations' => 0, 'diagnostics' => 0];
        }
        if (!tw_ai_prepare_private_dir()) {
            throw new RuntimeException('Private AI storage directory could not be initialized.');
        }
    }
    if (!tw_ai_prepare_private_dir()) {
        throw new RuntimeException('Private AI storage directory is unsafe.');
    }
    // Authenticated deployment migration initializes the HMAC secret so the
    // public read-only health route never has to create private state.
    $secret = tw_question_secret();
    if ($secret === '') throw new RuntimeException('Private AI storage secret is unavailable.');

    $keyPath = $dir . '/openai-key.txt';
    $keyMigrated = 0;
    if (tw_ai_private_path_exists($keyPath)) {
        $keyBytes = tw_ai_read_secure_regular_file($keyPath, 1, 8192);
        if (!is_string($keyBytes) || trim($keyBytes) === '') {
            throw new RuntimeException('OpenAI key is not a secure regular file.');
        }
        $keyMigrated = 1;
    }
    if (!tw_maintain_ai_usage_files()) {
        throw new RuntimeException('AI usage storage could not be migrated safely.');
    }

    $investigationPath = $dir . '/ai-investigations.jsonl';
    $investigationMigrated = 0;
    if (tw_ai_private_path_exists($investigationPath)) {
        tw_prepare_ai_investigation_log($secret);
        $investigationMigrated = 1;
    }
    $diagnosticPath = $dir . '/openai-errors.jsonl';
    $diagnosticMigrated = 0;
    if (tw_ai_private_path_exists($diagnosticPath)) {
        tw_prepare_ai_diagnostic_log($secret);
        $diagnosticMigrated = 1;
    }
    foreach ([$investigationPath . '.lock', $diagnosticPath . '.lock'] as $lockPath) {
        if (tw_ai_private_path_exists($lockPath) && !tw_ai_secure_existing_regular_file($lockPath, 0600)) {
            throw new RuntimeException('Private AI log lock is unsafe.');
        }
    }
    return [
        'key' => $keyMigrated,
        'investigations' => $investigationMigrated,
        'diagnostics' => $diagnosticMigrated,
    ];
}

function tw_ai_owner_regular_file(string $path): bool {
    clearstatcache(true, $path);
    if (is_link($path)) return false;
    $stat = @lstat($path);
    return is_array($stat)
        && ((((int)$stat['mode']) & 0170000) === 0100000)
        && ((((int)$stat['mode']) & 0777) === 0600);
}

function tw_ai_record_contains_raw_diagnostic(array $record): bool {
    foreach ($record as $key => $value) {
        $normalized = is_string($key) ? strtolower($key) : '';
        if (in_array($normalized, ['question', 'context', 'error_message', 'curl_error'], true)) return true;
        if (is_array($value) && tw_ai_record_contains_raw_diagnostic($value)) return true;
    }
    return false;
}

/**
 * Non-destructive readiness validation for every private path that can make a
 * public investigation fail after health.php has otherwise reported ready.
 */
function tw_ai_private_storage_ready(string $secret): bool {
    if (preg_match('/^[a-f0-9]{64}$/D', $secret) !== 1) return false;
    $dir = tw_private_dir();
    clearstatcache(true, $dir);
    $dirStat = @lstat($dir);
    if (is_link($dir)
        || !is_array($dirStat)
        || ((((int)$dirStat['mode']) & 0170000) !== 0040000)
        || ((((int)$dirStat['mode']) & 0777) !== 0750)
        || !is_writable($dir)) return false;

    try {
        $usagePaths = glob($dir . '/ai-usage-*.json*');
        $legacyUsagePaths = glob($dir . '/.ai-usage-*');
        $temporaryPaths = glob($dir . '/*.tmp-*');
        if (!is_array($usagePaths) || !is_array($legacyUsagePaths) || !is_array($temporaryPaths)) return false;
        $sensitivePaths = [];
        foreach ($usagePaths as $path) {
            if (preg_match('/^ai-usage-\d{4}-\d{2}-\d{2}\.json(?:\.lock)?$/D', basename($path)) === 1) $sensitivePaths[] = $path;
        }
        foreach ($legacyUsagePaths as $path) {
            if (preg_match('/^\.ai-usage-[a-zA-Z0-9]{6}$/D', basename($path)) === 1) $sensitivePaths[] = $path;
        }
        foreach ($temporaryPaths as $path) {
            if (preg_match('/^(?:ai-usage-\d{4}-\d{2}-\d{2}\.json|ai-investigations\.jsonl|openai-errors\.jsonl)\.tmp-[a-f0-9]{16,64}$/D', basename($path)) === 1) $sensitivePaths[] = $path;
        }
        if (count($sensitivePaths) > 10000) return false;
        foreach ($sensitivePaths as $path) {
            if (tw_ai_private_path_exists($path) && !tw_ai_owner_regular_file($path)) return false;
        }

        $usagePath = tw_usage_file();
        if (tw_ai_private_path_exists($usagePath)) {
            [$usageValid] = tw_read_usage_file_readonly($usagePath);
            if (!$usageValid) return false;
        }

        $investigationPath = $dir . '/ai-investigations.jsonl';
        $diagnosticPath = $dir . '/openai-errors.jsonl';
        foreach ([$dir . '/question-secret.txt.lock', $investigationPath, $investigationPath . '.lock', $diagnosticPath, $diagnosticPath . '.lock'] as $path) {
            if (tw_ai_private_path_exists($path) && !tw_ai_owner_regular_file($path)) return false;
        }
        foreach (tw_ai_read_jsonl_strict($investigationPath, 16777216, 20000, true) as $record) {
            tw_ai_log_record_timestamp($record);
            if (tw_ai_record_contains_raw_prompt($record) || array_key_exists('response_id', $record)) return false;
            tw_ai_redact_investigation_record($record, $secret);
        }
        foreach (tw_ai_read_jsonl_strict($diagnosticPath, 4194304, 5000, true) as $record) {
            tw_ai_log_record_timestamp($record);
            if (tw_ai_record_contains_raw_diagnostic($record)) return false;
            tw_ai_redact_diagnostic_record($record, $secret);
        }
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function tw_clean_source_url(string $url): string {
    $url = trim($url);
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) return '';
    if (!preg_match('#^https?://#i', $url)) return '';
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return '';
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $rawHost = strtolower((string)$parts['host']);
    if (!in_array($scheme, ['http', 'https'], true)) return '';

    // parse_url() retains brackets on IPv6 literals. Reject every IP literal,
    // public or private, so provider-controlled citations can never render a
    // direct link to an address on the reader's local network.
    $host = rtrim($rawHost, '.');
    $ipCandidate = str_starts_with($host, '[') && str_ends_with($host, ']')
        ? substr($host, 1, -1)
        : $host;
    if (filter_var($ipCandidate, FILTER_VALIDATE_IP) !== false) return '';

    $lastDot = strrpos($host, '.');
    $lastLabel = $lastDot === false ? '' : substr($host, $lastDot + 1);
    if ($host === ''
        || !str_contains($host, '.')
        || preg_match('/[a-z]/', $lastLabel) !== 1
        || strlen($host) > 253
        || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        || preg_match('/(?:^|\.)(?:localhost|local|localdomain|internal|home|lan)$/i', $host) === 1
        || preg_match('/(?:^|\.)home\.arpa$/i', $host) === 1) return '';
    $port = isset($parts['port']) ? (int)$parts['port'] : null;
    $defaultPort = $scheme === 'https' ? 443 : 80;
    if ($port !== null && $port !== $defaultPort) return '';
    $path = (string)($parts['path'] ?? '/');
    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
        foreach (array_keys($query) as $key) {
            if (str_starts_with(strtolower((string)$key), 'utm_')) unset($query[$key]);
        }
    }
    $clean = $scheme . '://' . $host . $path;
    if ($query) $clean .= '?' . http_build_query($query);
    return $clean;
}

function tw_extract_text_and_sources(array $data): array {
    $outputText = $data['output_text'] ?? '';
    $text = is_string($outputText) ? trim($outputText) : '';
    $sources = [];
    $webCalls = 0;
    $chunks = [];

    $output = $data['output'] ?? [];
    if (!is_array($output)) $output = [];
    foreach ($output as $item) {
        if (!is_array($item)) continue;
        $isWebSearchCall = ($item['type'] ?? '') === 'web_search_call';
        if ($isWebSearchCall) $webCalls++;
        $content = $item['content'] ?? [];
        if (!is_array($content)) $content = [];
        foreach ($content as $part) {
            if (!is_array($part) || ($part['type'] ?? '') !== 'output_text') continue;
            $partText = $part['text'] ?? '';
            if (is_string($partText)) $chunks[] = $partText;
            $annotations = $part['annotations'] ?? [];
            if (!is_array($annotations)) $annotations = [];
            foreach ($annotations as $annotation) {
                if (!is_array($annotation) || ($annotation['type'] ?? '') !== 'url_citation') continue;
                $annotationUrl = $annotation['url'] ?? '';
                if (!is_string($annotationUrl)) continue;
                $url = tw_clean_source_url($annotationUrl);
                if ($url === '') continue;
                $annotationTitle = $annotation['title'] ?? 'Source';
                $sources[$url] = is_string($annotationTitle) && trim($annotationTitle) !== '' ? trim($annotationTitle) : 'Source';
            }
        }
        $action = $isWebSearchCall ? ($item['action'] ?? []) : [];
        $actionSources = is_array($action) ? ($action['sources'] ?? []) : [];
        if (is_array($actionSources)) {
            foreach ($actionSources as $source) {
                if (!is_array($source)) continue;
                $sourceUrl = $source['url'] ?? '';
                if (!is_string($sourceUrl)) continue;
                $url = tw_clean_source_url($sourceUrl);
                if ($url === '') continue;
                $sourceTitle = $source['title'] ?? 'Source';
                $sources[$url] = is_string($sourceTitle) && trim($sourceTitle) !== '' ? trim($sourceTitle) : 'Source';
            }
        }
    }

    if ($text === '') $text = implode('', $chunks);
    $text = trim($text);
    return [$text, $sources, $webCalls];
}

function tw_validate_investigation_text(string $text): array {
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '' || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text)) {
        return [false, 'empty_or_control_characters', ''];
    }
    if (preg_match('#(?:https?://|www\.)#iu', $text)) return [false, 'inline_url', ''];
    if (preg_match('/^\s*(?:SOURCE TRAIL · WEB CHECK|SYSTEM NOTE)\s*$/imu', $text)) {
        return [false, 'reserved_heading', ''];
    }

    $headings = [
        'CLAIM ON TRIAL',
        'WHAT IS WELL ESTABLISHED',
        'STRONGEST EVIDENCE FOR',
        'STRONGEST COUNTEREVIDENCE / ALTERNATIVE',
        'WHAT REMAINS UNKNOWN',
        'PROVISIONAL FINDING',
    ];
    $lines = preg_split('/\n/u', $text);
    if (!is_array($lines)) return [false, 'invalid_encoding', ''];
    $positions = [];
    foreach ($headings as $heading) {
        $matches = [];
        foreach ($lines as $index => $line) {
            if (trim($line) === $heading) $matches[] = $index;
        }
        if (count($matches) !== 1) return [false, 'missing_or_duplicate_section', ''];
        $positions[] = $matches[0];
    }
    if ($positions[0] !== 0) return [false, 'content_before_first_section', ''];
    for ($index = 1; $index < count($positions); $index++) {
        if ($positions[$index] <= $positions[$index - 1]) return [false, 'section_order', ''];
    }

    $judgeLines = [];
    foreach ($lines as $index => $line) {
        if (strcasecmp(trim($line), 'You be the judge.') === 0) $judgeLines[] = $index;
    }
    if (count($judgeLines) !== 1 || $judgeLines[0] !== count($lines) - 1) {
        return [false, 'missing_or_misplaced_judge_line', ''];
    }

    foreach ($positions as $index => $start) {
        $end = $positions[$index + 1] ?? $judgeLines[0];
        $section = trim(implode("\n", array_slice($lines, $start + 1, $end - $start - 1)));
        if ($section === '' || mb_strlen($section, 'UTF-8') < 12) return [false, 'empty_section', ''];
    }
    return [true, '', $text];
}

function tw_append_verified_source_trail(string $text, array $sources): string {
    $text = rtrim($text);
    $text .= "\n\nSOURCE TRAIL · WEB CHECK\n";
    $count = 0;
    foreach ($sources as $url => $title) {
        $safeUrl = tw_clean_source_url((string)$url);
        if ($safeUrl === '') continue;
        $safeTitle = preg_replace('/[\x00-\x20\x7f]+/u', ' ', trim((string)$title));
        $safeTitle = $safeTitle === null || $safeTitle === '' ? 'Source' : mb_substr($safeTitle, 0, 180, 'UTF-8');
        $text .= "\n- " . $safeTitle . ': ' . $safeUrl;
        if (++$count >= 6) break;
    }
    return $text;
}

function tw_validate_trial_input(mixed $questionValue, mixed $contextValue): array {
    if (!is_string($questionValue) || !is_string($contextValue)) {
        return [false, '', '', 'Question and context must be plain text.'];
    }
    if (!mb_check_encoding($questionValue, 'UTF-8') || !mb_check_encoding($contextValue, 'UTF-8')) {
        return [false, '', '', 'Question and context must use valid text encoding.'];
    }
    if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $questionValue . $contextValue)) {
        return [false, '', '', 'Question and context contained unsupported control characters.'];
    }

    $question = preg_replace('/\s+/u', ' ', trim($questionValue));
    $context = preg_replace('/[\t ]+/u', ' ', trim($contextValue));
    if (!is_string($question) || !is_string($context)) {
        return [false, '', '', 'Question and context could not be read safely.'];
    }
    $cfg = tw_ai_config();
    if (mb_strlen($question, 'UTF-8') < 20 || mb_strlen($question, 'UTF-8') > $cfg['max_question_characters']) {
        return [false, '', '', 'Please enter a question between 20 and 3,000 characters.'];
    }
    if (mb_strlen($context, 'UTF-8') > $cfg['max_context_characters']) {
        return [false, '', '', 'Submitted context must be 3,000 characters or fewer.'];
    }
    return [true, $question, $context, ''];
}

function tw_short_investigation(string $question, string $context = '', string $secret = ''): array {
    [$validInput, $question, $context, $inputError] = tw_validate_trial_input($question, $context);
    if (!$validInput) return ['ok'=>false, 'message'=>$inputError, 'diagnostic'=>'invalid_input', 'quota_consumed'=>false];
    $key = tw_openai_key();
    if ($key === '') return ['ok'=>false,'message'=>'The research engine is not configured yet.','quota_consumed'=>false];
    if (!function_exists('curl_init')) return ['ok'=>false,'message'=>'The server needs PHP cURL enabled.','quota_consumed'=>false];
    if (preg_match('/^[a-f0-9]{64}$/D', $secret) !== 1) $secret = tw_question_secret();
    if (preg_match('/^[a-f0-9]{64}$/D', $secret) !== 1) {
        return ['ok'=>false,'message'=>'Private research diagnostics could not be secured.','quota_consumed'=>false];
    }
    try {
        tw_prepare_ai_diagnostic_log($secret);
    } catch (Throwable $logError) {
        return ['ok'=>false,'message'=>'Private research diagnostics could not be migrated safely.','quota_consumed'=>false];
    }

    $cfg = tw_ai_config();
    $system = <<<'PROMPT'
You are the research synthesis layer for Project Unveiled: Truth on Trial, powered by the Trust-Worthy method.
This is a concise preliminary investigation, not a final verdict. Search the web once to check the factual record before answering. Prefer primary, official, declassified, court, legislative, academic, or earliest-accessible sources over summaries when available. Do not assume a source is truthful merely because it is primary; primary means closer to the event, not infallible.

Return a COMPLETE short investigation in roughly 500–750 words using these exact section labels, each on its own line:
CLAIM ON TRIAL
WHAT IS WELL ESTABLISHED
STRONGEST EVIDENCE FOR
STRONGEST COUNTEREVIDENCE / ALTERNATIVE
WHAT REMAINS UNKNOWN
PROVISIONAL FINDING

OUTPUT FORMAT RULES:
- Plain text only. Do not use Markdown heading markers, asterisks, underscores, tables, code fences, or Markdown links.
- Do not put URLs or citations inline. The application appends the verified source trail separately.
- Bullets may begin with a simple hyphen and space.
- Use exact dates when known. Do not collapse separate events into a date range when the distinction matters.
- Keep direct quotations very short; paraphrase wherever possible.

Be adversarial toward every conclusion, including the user's premise. Distinguish documented fact, inference, disputed claim, and unknown. Do not equate motive with proof. Do not turn evidence of influence into evidence of total control unless the evidence supports that stronger claim.
Never fabricate citations, quotations, documents, dates, statistics, or source access. If current or primary evidence is insufficient, say so explicitly. When evidence shows that some officials had notice of uncertainty while others lacked the complete intelligence picture, say that instead of generalizing that “the government knew.” Keep the free answer useful but leave source-by-source provenance analysis, extended contradictions, and a full confidence ledger for the paid Deep Dive.
End with exactly: You be the judge.
PROMPT;
    $input = "QUESTION ON TRIAL:\n" . $question;
    if ($context !== '') $input .= "\n\nSUBMITTED CONTEXT:\n" . $context;

    $payload = [
        'model' => $cfg['model'],
        'store' => false,
        'instructions' => $system,
        'input' => $input,
        'reasoning' => ['effort' => $cfg['reasoning_effort']],
        'text' => ['verbosity' => 'low'],
        'max_output_tokens' => $cfg['max_output_tokens'],
        'tools' => [['type' => 'web_search']],
        'tool_choice' => 'required',
        'max_tool_calls' => $cfg['max_web_search_calls'],
        'include' => ['web_search_call.action.sources'],
    ];
    $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($encodedPayload)) {
        return ['ok'=>false,'message'=>'The research request could not be prepared safely.','quota_consumed'=>false];
    }
    $ch = curl_init('https://api.openai.com/v1/responses');
    if ($ch === false) return ['ok'=>false,'message'=>'The research engine could not initialize.','quota_consumed'=>false];
    $raw = '';
    $responseTooLarge = false;
    $maximumResponseBytes = 4194304;
    $configured = @curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $encodedPayload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $cfg['timeout_seconds'],
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$raw, &$responseTooLarge, $maximumResponseBytes): int {
            $length = strlen($chunk);
            if (strlen($raw) + $length > $maximumResponseBytes) {
                $responseTooLarge = true;
                return 0;
            }
            $raw .= $chunk;
            return $length;
        },
    ]);
    if (!$configured) {
        curl_close($ch);
        return ['ok'=>false,'message'=>'The research request could not be configured safely.','quota_consumed'=>false];
    }
    $executed = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    $curlNumber = curl_errno($ch);
    curl_close($ch);
    if ($responseTooLarge) {
        tw_log_openai_diagnostic($status, ['error'=>['type'=>'invalid_output_contract','code'=>'context_length_exceeded','message'=>'Provider response exceeded the local response-size boundary.']], '', 0, $secret);
        return ['ok'=>false,'message'=>'The research engine returned more data than can be verified safely. Diagnostic: response_too_large','quota_consumed'=>true];
    }
    if ($executed === false || $err !== '') {
        tw_log_openai_diagnostic($status, [], $err, $curlNumber, $secret);
        $providerReached = !in_array($curlNumber, [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT], true);
        return ['ok'=>false,'message'=>'The research engine could not connect. Diagnostic: network_error','quota_consumed'=>$providerReached];
    }
    $data = json_decode($raw, true, 64);
    if (!is_array($data)) $data = [];
    if ($status < 200 || $status >= 300) {
        tw_log_openai_diagnostic($status, $data, '', 0, $secret);
        $error = is_array($data['error'] ?? null) ? $data['error'] : [];
        $code = tw_ai_safe_diagnostic_identifier($error['code'] ?? $error['type'] ?? 'api_error', 'code') ?: 'api_error';
        return ['ok'=>false,'message'=>'OpenAI API request failed safely. Diagnostic: HTTP '.$status.' · '.$code,'quota_consumed'=>true];
    }

    $incomplete = (($data['status'] ?? '') === 'incomplete');
    if ($incomplete || (($data['status'] ?? '') !== 'completed')) {
        tw_log_openai_diagnostic($status, ['error'=>['type'=>'incomplete_output','message'=>'Response did not complete the required investigation contract.']], '', 0, $secret);
        return ['ok'=>false,'message'=>'The research engine did not complete a verifiable answer. Diagnostic: incomplete_output','quota_consumed'=>true];
    }

    [$text, $sources, $webCalls] = tw_extract_text_and_sources($data);
    [$validText, $contractError, $text] = tw_validate_investigation_text($text);
    if (!$validText) {
        tw_log_openai_diagnostic($status, ['error'=>['type'=>'invalid_output_contract','code'=>$contractError,'message'=>'Response failed the required investigation output contract.']], '', 0, $secret);
        return ['ok'=>false,'message'=>'The research engine returned an answer that failed verification. Diagnostic: invalid_output_contract','quota_consumed'=>true];
    }
    if ($webCalls < 1 || $webCalls > $cfg['max_web_search_calls'] || count($sources) < 1) {
        tw_log_openai_diagnostic($status, ['error'=>['type'=>'unverified_output','message'=>'Response lacked the required bounded web search and cited source trail.']], '', 0, $secret);
        return ['ok'=>false,'message'=>'The research engine returned an answer without a verifiable web source trail. Diagnostic: unverified_output','quota_consumed'=>true];
    }
    $text = tw_append_verified_source_trail($text, $sources);

    $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
    $outputTokenDetails = is_array($usage['output_tokens_details'] ?? null) ? $usage['output_tokens_details'] : [];
    return [
        'ok'=>true,
        'text'=>$text,
        'model'=>tw_ai_safe_model_identifier($cfg['model']),
        'response_id'=>tw_ai_safe_response_id($data['id'] ?? null),
        'input_tokens'=>tw_ai_bounded_nonnegative_int($usage['input_tokens'] ?? null),
        'output_tokens'=>tw_ai_bounded_nonnegative_int($usage['output_tokens'] ?? null),
        'reasoning_tokens'=>tw_ai_bounded_nonnegative_int($outputTokenDetails['reasoning_tokens'] ?? null),
        'web_search_calls'=>$webCalls,
        'source_count'=>count($sources),
        'incomplete'=>$incomplete,
        'quota_consumed'=>true,
    ];
}
