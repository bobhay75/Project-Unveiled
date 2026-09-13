<?php
declare(strict_types=1);

/**
 * Shared, dependency-free safeguards for the private question and challenge
 * queues. Runtime data and secrets remain outside public_html and outside Git.
 */

final class TwIntakeException extends RuntimeException
{
    public int $httpStatus;
    public ?int $retryAfter;

    public function __construct(string $message, int $httpStatus = 500, ?int $retryAfter = null)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->retryAfter = $retryAfter;
    }
}

function tw_intake_private_dir(): string
{
    if (defined('TW_INTAKE_PRIVATE_DIR_OVERRIDE')) {
        $override = constant('TW_INTAKE_PRIVATE_DIR_OVERRIDE');
        if (is_string($override) && $override !== '') {
            return $override;
        }
    }
    return dirname(__DIR__, 3) . '/site-private/trust-worthy';
}

function tw_intake_env_int(string $name, int $default, int $minimum, int $maximum): int
{
    $raw = getenv($name);
    if ($raw === false || !preg_match('/^\d+$/D', $raw)) {
        return $default;
    }

    $value = (int)$raw;
    return ($value >= $minimum && $value <= $maximum) ? $value : $default;
}

function tw_intake_config(): array
{
    return [
        'max_records' => tw_intake_env_int('TW_INTAKE_MAX_RECORDS', 1000, 100, 5000),
        'max_file_bytes' => tw_intake_env_int('TW_INTAKE_MAX_FILE_BYTES', 8388608, 1048576, 33554432),
        'hour_limit' => tw_intake_env_int('TW_INTAKE_HOURLY_LIMIT', 5, 1, 30),
        'day_limit' => tw_intake_env_int('TW_INTAKE_DAILY_LIMIT', 12, 1, 100),
        'retention_days' => tw_intake_env_int('TW_INTAKE_RETENTION_DAYS', 180, 30, 180),
        'form_ttl_seconds' => tw_intake_env_int('TW_INTAKE_FORM_TTL_SECONDS', 7200, 300, 86400),
    ];
}

function tw_intake_prepare_private_dir(): string
{
    $dir = tw_intake_private_dir();
    clearstatcache(true, $dir);
    if (is_link($dir) || (file_exists($dir) && !is_dir($dir))) {
        throw new TwIntakeException('Private intake directory must be a real directory, not a symlink.');
    }
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new TwIntakeException('Private intake storage is unavailable.');
    }
    clearstatcache(true, $dir);
    $stat = @lstat($dir);
    if (is_link($dir)
        || !is_array($stat)
        || ((((int)$stat['mode']) & 0170000) !== 0040000)
        || !is_writable($dir)
        || !@chmod($dir, 0750)
    ) {
        throw new TwIntakeException('Private intake storage is unavailable.');
    }
    clearstatcache(true, $dir);
    $stat = @lstat($dir);
    if (!is_array($stat)
        || ((((int)$stat['mode']) & 0170000) !== 0040000)
        || ((((int)$stat['mode']) & 0777) !== 0750)
    ) {
        throw new TwIntakeException('Private intake directory permissions could not be verified.');
    }
    return $dir;
}

function tw_intake_write_all($handle, string $bytes): void
{
    $length = strlen($bytes);
    $written = 0;
    while ($written < $length) {
        $count = @fwrite($handle, substr($bytes, $written));
        if ($count === false || $count === 0) {
            throw new TwIntakeException('Private intake storage could not be written.');
        }
        $written += $count;
    }
    if (!@fflush($handle)) {
        throw new TwIntakeException('Private intake storage could not be flushed.');
    }
    if (function_exists('fsync') && !@fsync($handle)) {
        throw new TwIntakeException('Private intake storage could not be synchronized.');
    }
}

function tw_intake_require_file_mode(string $path, int $mode, string $label): void
{
    clearstatcache(true, $path);
    if (is_link($path)) {
        throw new TwIntakeException($label . ' must not be a symlink.');
    }
    $stat = @lstat($path);
    if (!is_array($stat) || ((((int)$stat['mode']) & 0170000) !== 0100000)) {
        throw new TwIntakeException($label . ' must be a regular file.');
    }
    if (!@chmod($path, $mode)) {
        throw new TwIntakeException($label . ' permissions could not be secured.');
    }
    clearstatcache(true, $path);
    if (is_link($path)) {
        throw new TwIntakeException($label . ' changed into a symlink.');
    }
    $stat = @lstat($path);
    $permissions = @fileperms($path);
    if (!is_array($stat)
        || ((((int)$stat['mode']) & 0170000) !== 0100000)
        || !is_int($permissions)
        || ($permissions & 0777) !== $mode
    ) {
        throw new TwIntakeException($label . ' permissions could not be verified.');
    }
}

function tw_intake_path_exists(string $path): bool
{
    return file_exists($path) || is_link($path);
}

function tw_intake_assert_absent_or_regular(string $path, string $label): void
{
    clearstatcache(true, $path);
    if (!tw_intake_path_exists($path)) return;
    if (is_link($path)) throw new TwIntakeException($label . ' must not be a symlink.');
    $stat = @lstat($path);
    if (!is_array($stat) || ((((int)$stat['mode']) & 0170000) !== 0100000)) {
        throw new TwIntakeException($label . ' must be a regular file.');
    }
}

function tw_intake_secure_open_file($handle, string $path, int $mode, string $label): void
{
    if (!is_resource($handle)) throw new TwIntakeException($label . ' is unavailable.');
    $opened = @fstat($handle);
    if (!is_array($opened) || ((((int)$opened['mode']) & 0170000) !== 0100000)) {
        throw new TwIntakeException($label . ' is not an open regular file.');
    }
    clearstatcache(true, $path);
    if (is_link($path)) throw new TwIntakeException($label . ' must not be a symlink.');
    $pathStat = @lstat($path);
    if (!is_array($pathStat)
        || ((((int)$pathStat['mode']) & 0170000) !== 0100000)
        || (int)$pathStat['dev'] !== (int)$opened['dev']
        || (int)$pathStat['ino'] !== (int)$opened['ino']
    ) {
        throw new TwIntakeException($label . ' changed unexpectedly or could not be verified.');
    }
    if (!@fchmod($handle, $mode)) {
        throw new TwIntakeException($label . ' permissions could not be secured.');
    }
    clearstatcache(true, $path);
    if (is_link($path)) throw new TwIntakeException($label . ' must not be a symlink.');
    $pathStat = @lstat($path);
    $opened = @fstat($handle);
    if (!is_array($pathStat)
        || !is_array($opened)
        || ((((int)$pathStat['mode']) & 0170000) !== 0100000)
        || ((((int)$opened['mode']) & 0170000) !== 0100000)
        || (int)$pathStat['dev'] !== (int)$opened['dev']
        || (int)$pathStat['ino'] !== (int)$opened['ino']
        || ((((int)$pathStat['mode']) & 0777) !== $mode)
        || ((((int)$opened['mode']) & 0777) !== $mode)
    ) {
        throw new TwIntakeException($label . ' changed unexpectedly or could not be verified.');
    }
}

/**
 * Restrict only the known legacy intake files. This migration never creates,
 * parses, truncates, or rewrites queue/secret content.
 */
function tw_intake_migrate_private_permissions(): int
{
    $dir = tw_intake_private_dir();
    if (is_link($dir)) {
        throw new TwIntakeException('Private intake directory must not be a symlink.');
    }
    if (!file_exists($dir)) {
        return 0;
    }
    if (!is_dir($dir)) {
        throw new TwIntakeException('Private intake storage path is not a directory.');
    }
    $dir = tw_intake_prepare_private_dir();

    $names = [
        'question-secret.txt',
        'question-secret.txt.lock',
        'challenge-secret.txt',
        'challenge-secret.txt.lock',
        'questions.json',
        'questions.json.lock',
        'challenges.json',
        'challenges.json.lock',
    ];
    $targets = [];
    foreach ($names as $name) {
        $path = $dir . '/' . $name;
        if (is_link($path)) {
            throw new TwIntakeException('Private intake permission migration rejected a symlink.');
        }
        if (!file_exists($path)) {
            continue;
        }
        if (!is_file($path)) {
            throw new TwIntakeException('Private intake permission migration found a non-file path.');
        }
        $targets[] = $path;
    }
    if ($targets === []) {
        return 0;
    }

    $migrationLockPath = $dir . '/intake-permissions.lock';
    tw_intake_assert_absent_or_regular($migrationLockPath, 'Private intake permission lock');
    $migrationLock = @fopen($migrationLockPath, 'c+b');
    if (!is_resource($migrationLock)) {
        throw new TwIntakeException('Private intake permissions could not be locked.');
    }
    try {
        tw_intake_secure_open_file($migrationLock, $migrationLockPath, 0600, 'Private intake permission lock');
        if (!@flock($migrationLock, LOCK_EX)) {
            throw new TwIntakeException('Private intake permissions could not be locked.');
        }
        tw_intake_secure_open_file($migrationLock, $migrationLockPath, 0600, 'Private intake permission lock');
        foreach ($targets as $path) {
            if (is_link($path) || !is_file($path)) {
                throw new TwIntakeException('Private intake permission migration target changed unexpectedly.');
            }
            tw_intake_require_file_mode($path, 0600, 'Private intake runtime file');
        }
        return count($targets);
    } finally {
        @flock($migrationLock, LOCK_UN);
        @fclose($migrationLock);
    }
}

function tw_intake_secret(string $queue): string
{
    if (!in_array($queue, ['question', 'challenge'], true)) {
        throw new TwIntakeException('Unknown intake queue.');
    }

    $dir = tw_intake_prepare_private_dir();
    $path = $dir . '/' . $queue . '-secret.txt';
    $lockPath = $path . '.lock';
    tw_intake_assert_absent_or_regular($lockPath, 'Private intake secret lock');
    tw_intake_assert_absent_or_regular($path, 'Private intake secret');
    $lock = @fopen($lockPath, 'c+b');
    if (!is_resource($lock)) {
        throw new TwIntakeException('The private intake secret could not be locked.');
    }
    try {
        tw_intake_secure_open_file($lock, $lockPath, 0600, 'Private intake secret lock');
        if (!@flock($lock, LOCK_EX)) {
            throw new TwIntakeException('The private intake secret could not be locked.');
        }
        tw_intake_secure_open_file($lock, $lockPath, 0600, 'Private intake secret lock');
        tw_intake_assert_absent_or_regular($path, 'Private intake secret');
        if (!tw_intake_path_exists($path)) {
            $created = @fopen($path, 'xb');
            if (!is_resource($created)) {
                throw new TwIntakeException('The private intake secret could not be created.');
            }
            $createdSuccessfully = false;
            try {
                tw_intake_secure_open_file($created, $path, 0600, 'Private intake secret');
                tw_intake_write_all($created, bin2hex(random_bytes(32)) . "\n");
                tw_intake_secure_open_file($created, $path, 0600, 'Private intake secret');
                $createdSuccessfully = true;
            } finally {
                @fclose($created);
                if (!$createdSuccessfully) {
                    @unlink($path);
                }
            }
        }

        $secretHandle = @fopen($path, 'rb');
        if (!is_resource($secretHandle)) {
            throw new TwIntakeException('The private intake secret is unavailable or invalid.');
        }
        try {
            tw_intake_secure_open_file($secretHandle, $path, 0600, 'Private intake secret');
            $secretStat = @fstat($secretHandle);
            $raw = @stream_get_contents($secretHandle, 8193);
            tw_intake_secure_open_file($secretHandle, $path, 0600, 'Private intake secret');
            if (!is_array($secretStat)
                || (int)$secretStat['size'] < 1
                || (int)$secretStat['size'] > 8192
                || !is_string($raw)
                || strlen($raw) !== (int)$secretStat['size']
            ) {
                throw new TwIntakeException('The private intake secret is unavailable or invalid.');
            }
        } finally {
            @fclose($secretHandle);
        }
        $secret = is_string($raw) ? trim($raw) : '';
        if (!preg_match('/^[a-f0-9]{64}$/D', $secret)) {
            throw new TwIntakeException('The private intake secret is unavailable or invalid.');
        }
        return $secret;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function tw_intake_client_ip_hash(string $queue): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        throw new TwIntakeException('The request address could not be validated.', 403);
    }
    return hash_hmac('sha256', 'ip|' . $ip, tw_intake_secret($queue));
}

function tw_intake_request_origin_is_allowed(): bool
{
    $hostValue = strtolower(rtrim((string)($_SERVER['HTTP_HOST'] ?? ''), '.'));
    if (!preg_match('/^((?:www\.)?bobsome1\.com)(?::443)?$/D', $hostValue, $hostMatch)) {
        return false;
    }
    $host = $hostMatch[1];
    $fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite !== '' && $fetchSite !== 'same-origin') {
        return false;
    }

    $validateUrl = static function (string $raw, bool $isOrigin) use ($host): bool {
        $parts = parse_url($raw);
        if (!is_array($parts)) {
            return false;
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $urlHost = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        $port = isset($parts['port']) ? (int)$parts['port'] : 443;
        if ($scheme !== 'https'
            || $urlHost !== $host
            || $port !== 443
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            return false;
        }
        return !$isOrigin || (!isset($parts['query']) && in_array((string)($parts['path'] ?? ''), ['', '/'], true));
    };

    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        return $validateUrl($origin, true);
    }
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer !== '') {
        return $validateUrl($referer, false);
    }
    return $fetchSite === 'same-origin';
}

function tw_intake_enforce_raw_body_limit(int $maxBodyBytes): void
{
    $input = @fopen('php://input', 'rb');
    if (!is_resource($input)) {
        throw new TwIntakeException('The request body could not be validated.', 400);
    }
    $total = 0;
    try {
        while (!feof($input)) {
            $remaining = $maxBodyBytes - $total;
            $chunk = @fread($input, min(8192, $remaining + 1));
            if ($chunk === false) {
                throw new TwIntakeException('The request body could not be validated.', 400);
            }
            if ($chunk === '') {
                if (feof($input)) {
                    break;
                }
                throw new TwIntakeException('The request body could not be validated.', 400);
            }
            $total += strlen($chunk);
            if ($total > $maxBodyBytes) {
                throw new TwIntakeException('The submission is too large.', 413);
            }
        }
    } finally {
        @fclose($input);
    }
}

function tw_intake_enforce_post_request(int $maxBodyBytes): void
{
    if ($maxBodyBytes < 1) {
        throw new TwIntakeException('The request size policy is invalid.');
    }
    if ((string)($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new TwIntakeException('POST required.', 405);
    }
    if (!tw_intake_request_origin_is_allowed()) {
        throw new TwIntakeException('Request origin was not accepted.', 403);
    }

    $files = $_FILES ?? [];
    if (!is_array($files) || $files !== []) {
        throw new TwIntakeException('File uploads are not accepted.', 422);
    }

    // Existing same-origin forms use the browser default URL encoding. Keeping
    // one encoding makes php://input measurable; PHP may consume multipart
    // bodies before application code can verify their actual byte length.
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
    if ($contentType !== 'application/x-www-form-urlencoded') {
        throw new TwIntakeException('URL-encoded form data is required.', 415);
    }
    $contentLength = (string)($_SERVER['CONTENT_LENGTH'] ?? '');
    if ($contentLength !== '' && (!preg_match('/^\d+$/D', $contentLength) || (int)$contentLength > $maxBodyBytes)) {
        throw new TwIntakeException('The submission is too large.', 413);
    }
    tw_intake_enforce_raw_body_limit($maxBodyBytes);
    $parsedBytes = 0;
    foreach ($_POST as $key => $value) {
        if (!is_string($key) || !is_string($value)) {
            throw new TwIntakeException('Form fields must contain plain text.', 422);
        }
        $parsedBytes += strlen($key) + strlen($value);
        if ($parsedBytes > $maxBodyBytes) {
            throw new TwIntakeException('The submission is too large.', 413);
        }
    }
    // A request to either public intake endpoint upgrades permissions for both
    // legacy queues. The deployment migration performs the same exact-file
    // operation immediately during a real cPanel deploy.
    tw_intake_migrate_private_permissions();
}

function tw_intake_post_scalar(string $key, bool $required = false): string
{
    if (!array_key_exists($key, $_POST)) {
        if ($required) {
            throw new TwIntakeException('A required form field is missing.', 422);
        }
        return '';
    }
    if (!is_string($_POST[$key])) {
        throw new TwIntakeException('Form fields must contain plain text.', 422);
    }
    return $_POST[$key];
}

function tw_intake_clean_text(string $value, int $minimum, int $maximum, bool $singleLine = false): string
{
    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        throw new TwIntakeException('A form field contains invalid text.', 422);
    }
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
        throw new TwIntakeException('A form field contains unsupported control characters.', 422);
    }
    if ($singleLine) {
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    } else {
        $value = preg_replace('/[\t ]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? '';
    }
    $value = trim($value);
    $length = mb_strlen($value, 'UTF-8');
    if ($length < $minimum || $length > $maximum) {
        throw new TwIntakeException('A form field is outside its allowed length.', 422);
    }
    return $value;
}

function tw_intake_opened_at(): int
{
    $raw = tw_intake_post_scalar('opened_at', true);
    if (!preg_match('/^\d{1,10}$/D', $raw)) {
        throw new TwIntakeException('The form timestamp is invalid. Reload it and try again.', 422);
    }
    return (int)$raw;
}

function tw_intake_assert_known_case(string $caseId): void
{
    if (!preg_match('/^TW-CLAIM-\d{6}$/D', $caseId)) {
        throw new TwIntakeException('Unknown case.', 422);
    }
    $path = dirname(__DIR__) . '/cases/' . $caseId . '.json';
    if (!is_file($path)) {
        throw new TwIntakeException('Unknown case.', 422);
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw)) {
        throw new TwIntakeException('The public case registry could not be read.');
    }
    try {
        $case = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new TwIntakeException('The public case registry is invalid.');
    }
    if (!is_array($case) || ($case['case_id'] ?? null) !== $caseId) {
        throw new TwIntakeException('The public case registry is invalid.');
    }
}

function tw_intake_is_list(array $values): bool
{
    $expected = 0;
    foreach ($values as $key => $_value) {
        if ($key !== $expected) {
            return false;
        }
        $expected++;
    }
    return true;
}

function tw_intake_parse_utc_timestamp(mixed $value): ?int
{
    if (!is_string($value)
        || preg_match('/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:Z|\+00:00)$/D', $value, $parts) !== 1
        || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])
    ) {
        return null;
    }
    $timestamp = strtotime($value);
    if (!is_int($timestamp)) return null;
    $canonical = str_ends_with($value, 'Z') ? substr($value, 0, -1) . '+00:00' : $value;
    return gmdate('Y-m-d\TH:i:s+00:00', $timestamp) === $canonical ? $timestamp : null;
}

function tw_intake_read_records(string $path, int $maxFileBytes): array
{
    if (!tw_intake_path_exists($path)) {
        return [];
    }
    tw_intake_assert_absent_or_regular($path, 'Private intake data file');
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        throw new TwIntakeException('Private intake storage could not be opened safely.');
    }
    try {
        tw_intake_secure_open_file($handle, $path, 0600, 'Private intake data file');
        $stat = @fstat($handle);
        $size = is_array($stat) ? (int)$stat['size'] : -1;
        if ($size < 2 || $size > $maxFileBytes) {
            throw new TwIntakeException('Private intake storage is invalid or over capacity.');
        }
        $raw = @stream_get_contents($handle, $maxFileBytes + 1);
        tw_intake_secure_open_file($handle, $path, 0600, 'Private intake data file');
    } finally {
        @fclose($handle);
    }
    if (!is_int($size) || $size < 2 || $size > $maxFileBytes) {
        throw new TwIntakeException('Private intake storage is invalid or over capacity.');
    }
    if (!is_string($raw) || strlen($raw) !== $size) {
        throw new TwIntakeException('Private intake storage could not be read completely.');
    }
    try {
        $records = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new TwIntakeException('Private intake storage is corrupted; no data was overwritten.');
    }
    if (!is_array($records) || !tw_intake_is_list($records)) {
        throw new TwIntakeException('Private intake storage has an invalid structure; no data was overwritten.');
    }
    foreach ($records as $record) {
        if (!is_array($record)
            || !is_string($record['ip_hash'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $record['ip_hash'])
            || tw_intake_parse_utc_timestamp($record['submitted_at_utc'] ?? null) === null
        ) {
            throw new TwIntakeException('Private intake storage contains an invalid record; no data was overwritten.');
        }
    }
    return $records;
}

function tw_intake_atomic_replace(string $path, string $json): void
{
    tw_intake_assert_absent_or_regular($path, 'Private intake data file');
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(8));
    $handle = @fopen($tmp, 'xb');
    if (!is_resource($handle)) {
        throw new TwIntakeException('Private intake storage could not be prepared.');
    }
    try {
        // The empty file must be owner-only before any private record is written.
        tw_intake_secure_open_file($handle, $tmp, 0600, 'Private intake temporary file');
        tw_intake_write_all($handle, $json);
        tw_intake_secure_open_file($handle, $tmp, 0600, 'Private intake temporary file');
    } catch (Throwable $error) {
        @fclose($handle);
        @unlink($tmp);
        throw $error;
    }
    if (!@fclose($handle)) {
        @unlink($tmp);
        throw new TwIntakeException('Private intake storage could not be finalized.');
    }
    try {
        tw_intake_require_file_mode($tmp, 0600, 'Private intake temporary file');
        tw_intake_assert_absent_or_regular($path, 'Private intake data file');
        if (!@rename($tmp, $path)) {
            throw new TwIntakeException('Private intake storage could not be replaced.');
        }
        // The exclusive temp inode was verified regular and 0600 before any
        // private bytes were written; rename preserves both atomically.
    } catch (Throwable $error) {
        @unlink($tmp);
        throw $error;
    }
}

function tw_intake_append_record(string $queue, array $record): void
{
    if (!in_array($queue, ['question', 'challenge'], true)) {
        throw new TwIntakeException('Unknown intake queue.');
    }
    $config = tw_intake_config();
    $dir = tw_intake_prepare_private_dir();
    $path = $dir . '/' . $queue . 's.json';
    $lockPath = $path . '.lock';
    tw_intake_assert_absent_or_regular($lockPath, 'Private intake queue lock');
    tw_intake_assert_absent_or_regular($path, 'Private intake data file');
    $lock = @fopen($lockPath, 'c+b');
    if (!is_resource($lock)) {
        throw new TwIntakeException('Private intake storage could not be locked.');
    }

    try {
        tw_intake_secure_open_file($lock, $lockPath, 0600, 'Private intake queue lock');
        if (!@flock($lock, LOCK_EX)) {
            throw new TwIntakeException('Private intake storage could not be locked.');
        }
        tw_intake_secure_open_file($lock, $lockPath, 0600, 'Private intake queue lock');
        clearstatcache(true, $path);
        tw_intake_assert_absent_or_regular($path, 'Private intake data file');
        $records = tw_intake_read_records($path, $config['max_file_bytes']);
        $now = time();
        $retentionSeconds = $config['retention_days'] * 86400;
        $retainedRecords = [];
        foreach ($records as $existing) {
            $submittedAt = tw_intake_parse_utc_timestamp($existing['submitted_at_utc'] ?? null);
            if (!is_int($submittedAt) || $submittedAt > $now + 300) {
                throw new TwIntakeException('Private intake storage contains an invalid timestamp; no data was overwritten.');
            }
            if (!array_key_exists('delete_after_utc', $existing)) {
                $effectiveDeleteAfter = $submittedAt + $retentionSeconds;
            } else {
                $storedDeleteAfter = tw_intake_parse_utc_timestamp($existing['delete_after_utc']);
                if (!is_int($storedDeleteAfter) || $storedDeleteAfter <= $submittedAt) {
                    throw new TwIntakeException('Private intake storage contains an invalid retention date; no data was overwritten.');
                }
                $effectiveDeleteAfter = min($storedDeleteAfter, $submittedAt + $retentionSeconds);
            }
            if ($effectiveDeleteAfter <= $now) continue;
            $existing['submitted_at_utc'] = gmdate('c', $submittedAt);
            $existing['delete_after_utc'] = gmdate('c', $effectiveDeleteAfter);
            $retainedRecords[] = $existing;
        }
        $records = $retainedRecords;
        if (count($records) >= $config['max_records']) {
            throw new TwIntakeException('Intake is temporarily full. Please try again later.', 503);
        }

        $ipHash = $record['ip_hash'] ?? null;
        if (!is_string($ipHash) || !preg_match('/^[a-f0-9]{64}$/D', $ipHash)) {
            throw new TwIntakeException('The submission identity could not be protected.');
        }
        if (($record['schema_version'] ?? null) !== 2
            || !is_string($record['id'] ?? null)
            || !preg_match('/^[a-f0-9]{32}$/D', $record['id'])
            || !is_string($record['submitted_at_utc'] ?? null)
        ) {
            throw new TwIntakeException('The submission record is invalid.');
        }
        $submittedAt = tw_intake_parse_utc_timestamp($record['submitted_at_utc']);
        if (!is_int($submittedAt) || $submittedAt < $now - 600 || $submittedAt > $now + 300) {
            throw new TwIntakeException('The submission timestamp is invalid.');
        }
        $deleteAfter = tw_intake_parse_utc_timestamp($record['delete_after_utc'] ?? null);
        if (!is_int($deleteAfter)
            || $deleteAfter <= $now
            || $deleteAfter <= $submittedAt
            || $deleteAfter > $submittedAt + $retentionSeconds
        ) {
            throw new TwIntakeException('The submission retention date is invalid.');
        }
        $hourCount = 0;
        $dayCount = 0;
        foreach ($records as $existing) {
            if (!hash_equals($existing['ip_hash'], $ipHash)) {
                continue;
            }
            $submitted = tw_intake_parse_utc_timestamp($existing['submitted_at_utc'] ?? null);
            if (!is_int($submitted) || $submitted > $now + 300) {
                throw new TwIntakeException('Private intake storage contains an invalid timestamp; no data was overwritten.');
            }
            if ($submitted >= $now - 86400) {
                $dayCount++;
            }
            if ($submitted >= $now - 3600) {
                $hourCount++;
            }
        }
        if ($hourCount >= $config['hour_limit']) {
            throw new TwIntakeException('Too many recent submissions. Please try again in an hour.', 429, 3600);
        }
        if ($dayCount >= $config['day_limit']) {
            throw new TwIntakeException('The daily submission limit was reached. Please try again tomorrow.', 429, 86400);
        }

        $records[] = $record;
        try {
            $json = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException $error) {
            throw new TwIntakeException('The submission could not be encoded safely.');
        }
        if (strlen($json) > $config['max_file_bytes']) {
            throw new TwIntakeException('Intake is temporarily full. Please try again later.', 503);
        }
        tw_intake_atomic_replace($path, $json);
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function tw_intake_record_expiry(?int $submittedAt = null): string
{
    $days = tw_intake_config()['retention_days'];
    $submittedAt ??= time();
    return gmdate('c', $submittedAt + ($days * 86400));
}
