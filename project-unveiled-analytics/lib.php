<?php
declare(strict_types=1);

/**
 * Shared safeguards for Project Unveiled's first-party analytics collector and
 * private dashboard. Runtime state remains outside public_html and Git.
 */

final class PuAnalyticsException extends RuntimeException
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

function pu_analytics_env_int(string $name, int $default, int $minimum, int $maximum): int
{
    $raw = getenv($name);
    if ($raw === false || !preg_match('/^\d+$/D', $raw)) {
        return $default;
    }
    $value = (int)$raw;
    return ($value >= $minimum && $value <= $maximum) ? $value : $default;
}

function pu_analytics_config(): array
{
    return [
        'max_body_bytes' => pu_analytics_env_int('PU_ANALYTICS_MAX_BODY_BYTES', 8192, 1024, 32768),
        'collector_hour_limit' => pu_analytics_env_int('PU_ANALYTICS_HOURLY_LIMIT', 240, 10, 2000),
        'collector_day_limit' => pu_analytics_env_int('PU_ANALYTICS_DAILY_LIMIT', 1500, 50, 10000),
        'login_hour_limit' => pu_analytics_env_int('PU_ANALYTICS_LOGIN_HOURLY_LIMIT', 10, 3, 50),
        'login_day_limit' => pu_analytics_env_int('PU_ANALYTICS_LOGIN_DAILY_LIMIT', 30, 5, 200),
        'max_rate_entries' => pu_analytics_env_int('PU_ANALYTICS_MAX_RATE_IDENTITIES', 5000, 100, 20000),
        'retention_days' => 100,
        'max_daily_events' => pu_analytics_env_int('PU_ANALYTICS_MAX_DAILY_EVENTS', 20000, 100, 100000),
        'max_daily_bytes' => pu_analytics_env_int('PU_ANALYTICS_MAX_DAILY_BYTES', 8388608, 1048576, 33554432),
        'max_event_line_bytes' => 12288,
        'dashboard_max_files' => 90,
        'dashboard_max_events' => pu_analytics_env_int('PU_ANALYTICS_DASHBOARD_MAX_EVENTS', 50000, 1000, 200000),
        'migration_max_bytes' => pu_analytics_env_int('PU_ANALYTICS_MIGRATION_MAX_BYTES', 134217728, 1048576, 536870912),
    ];
}

function pu_analytics_private_root(): string
{
    if (defined('PU_ANALYTICS_PRIVATE_ROOT_OVERRIDE')) {
        $override = constant('PU_ANALYTICS_PRIVATE_ROOT_OVERRIDE');
        if (is_string($override) && $override !== '') {
            return rtrim($override, '/');
        }
    }

    $fallback = dirname(__DIR__);
    $docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if (!is_string($docRoot) || $docRoot === '') {
        $docRoot = $fallback;
    }
    return dirname($docRoot) . '/site-private/project-unveiled-analytics';
}

function pu_analytics_assert_regular_or_absent(string $path, string $label): void
{
    clearstatcache(true, $path);
    if (is_link($path)) {
        throw new PuAnalyticsException($label . ' may not be a symbolic link.');
    }
    $stat = @lstat($path);
    if ($stat === false) {
        return;
    }
    if (!is_int($stat['mode'] ?? null) || (($stat['mode'] & 0170000) !== 0100000)) {
        throw new PuAnalyticsException($label . ' must be a regular file.');
    }
}

function pu_analytics_assert_open_regular(string $path, $handle, string $label): void
{
    clearstatcache(true, $path);
    if (is_link($path)) {
        throw new PuAnalyticsException($label . ' may not be a symbolic link.');
    }
    $pathStat = @lstat($path);
    $handleStat = is_resource($handle) ? @fstat($handle) : false;
    if (!is_array($pathStat)
        || !is_array($handleStat)
        || !is_int($pathStat['mode'] ?? null)
        || (($pathStat['mode'] & 0170000) !== 0100000)
        || !isset($pathStat['dev'], $pathStat['ino'], $handleStat['dev'], $handleStat['ino'])
        || $pathStat['dev'] !== $handleStat['dev']
        || $pathStat['ino'] !== $handleStat['ino']
    ) {
        throw new PuAnalyticsException($label . ' changed or is not a regular file.');
    }
}

function pu_analytics_require_mode(string $path, int $mode, string $label): void
{
    pu_analytics_assert_regular_or_absent($path, $label);
    if (!@chmod($path, $mode)) {
        throw new PuAnalyticsException($label . ' permissions could not be secured.');
    }
    clearstatcache(true, $path);
    $permissions = @fileperms($path);
    if (!is_int($permissions) || ($permissions & 0777) !== $mode) {
        throw new PuAnalyticsException($label . ' permissions could not be verified.');
    }
}

function pu_analytics_prepare_dir(string $path, int $mode = 0700): void
{
    if (is_link($path)) {
        throw new PuAnalyticsException('Private analytics storage may not be a symbolic link.');
    }
    if (!is_dir($path) && !@mkdir($path, $mode, true) && !is_dir($path)) {
        throw new PuAnalyticsException('Private analytics storage is unavailable.');
    }
    if (!is_writable($path) || !@chmod($path, $mode)) {
        throw new PuAnalyticsException('Private analytics storage could not be secured.');
    }
}

function pu_analytics_prepare_private_root(string $privateRoot): void
{
    pu_analytics_prepare_dir($privateRoot, 0700);
}

function pu_analytics_load_dashboard_config(string $path): array
{
    if (is_link($path)) {
        throw new PuAnalyticsException('Analytics configuration is unavailable.', 503);
    }
    if (!is_file($path)) {
        throw new PuAnalyticsException('Analytics configuration is unavailable.', 503);
    }
    clearstatcache(true, $path);
    $size = @filesize($path);
    if (!is_int($size) || $size < 1 || $size > 16384) {
        throw new PuAnalyticsException('Analytics configuration is invalid.', 503);
    }
    pu_analytics_require_mode($path, 0600, 'Analytics configuration');
    pu_analytics_assert_regular_or_absent($path, 'Analytics configuration');
    try {
        $config = require $path;
    } catch (Throwable $error) {
        throw new PuAnalyticsException('Analytics configuration could not be loaded.', 503);
    }
    if (!is_array($config)
        || !is_string($config['password_hash'] ?? null)
        || password_get_info($config['password_hash'])['algo'] === null
    ) {
        throw new PuAnalyticsException('Analytics configuration is invalid.', 503);
    }
    return $config;
}

function pu_analytics_write_all($handle, string $bytes): void
{
    $length = strlen($bytes);
    $offset = 0;
    while ($offset < $length) {
        $written = @fwrite($handle, substr($bytes, $offset));
        if ($written === false || $written === 0) {
            throw new PuAnalyticsException('Private analytics storage could not be written.');
        }
        $offset += $written;
    }
    if (!@fflush($handle)) {
        throw new PuAnalyticsException('Private analytics storage could not be flushed.');
    }
    if (function_exists('fsync') && !@fsync($handle)) {
        throw new PuAnalyticsException('Private analytics storage could not be synchronized.');
    }
}

function pu_analytics_atomic_json(string $path, array $value): void
{
    try {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    } catch (JsonException $error) {
        throw new PuAnalyticsException('Private analytics state could not be encoded.');
    }
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
    pu_analytics_assert_regular_or_absent($path, 'Private analytics state');
    pu_analytics_assert_regular_or_absent($temporary, 'Private analytics temporary file');
    $handle = @fopen($temporary, 'xb');
    if (!is_resource($handle)) {
        throw new PuAnalyticsException('Private analytics state could not be prepared.');
    }
    try {
        pu_analytics_assert_open_regular($temporary, $handle, 'Private analytics temporary file');
        pu_analytics_require_mode($temporary, 0600, 'Private analytics temporary file');
        pu_analytics_write_all($handle, $json);
    } catch (Throwable $error) {
        @fclose($handle);
        @unlink($temporary);
        throw $error;
    }
    @fclose($handle);
    pu_analytics_assert_regular_or_absent($path, 'Private analytics state');
    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        throw new PuAnalyticsException('Private analytics state could not be committed.');
    }
    pu_analytics_require_mode($path, 0600, 'Private analytics state');
}

function pu_analytics_atomic_bytes(string $path, string $bytes): void
{
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
    pu_analytics_assert_regular_or_absent($path, 'Private analytics data');
    pu_analytics_assert_regular_or_absent($temporary, 'Private analytics temporary file');
    $handle = @fopen($temporary, 'xb');
    if (!is_resource($handle)) {
        throw new PuAnalyticsException('Private analytics data could not be prepared.');
    }
    try {
        pu_analytics_assert_open_regular($temporary, $handle, 'Private analytics temporary file');
        pu_analytics_require_mode($temporary, 0600, 'Private analytics temporary file');
        pu_analytics_write_all($handle, $bytes);
    } catch (Throwable $error) {
        @fclose($handle);
        @unlink($temporary);
        throw $error;
    }
    @fclose($handle);
    pu_analytics_assert_regular_or_absent($path, 'Private analytics data');
    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        throw new PuAnalyticsException('Private analytics data could not be committed.');
    }
    pu_analytics_require_mode($path, 0600, 'Private analytics data');
}

function pu_analytics_read_json_file(string $path, int $maxBytes): ?array
{
    pu_analytics_assert_regular_or_absent($path, 'Private analytics state');
    if (!file_exists($path)) {
        return null;
    }
    clearstatcache(true, $path);
    $size = @filesize($path);
    if (!is_int($size) || $size < 2 || $size > $maxBytes) {
        throw new PuAnalyticsException('Private analytics state is invalid or over capacity.');
    }
    pu_analytics_assert_regular_or_absent($path, 'Private analytics state');
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        throw new PuAnalyticsException('Private analytics state could not be opened.');
    }
    try {
        pu_analytics_assert_open_regular($path, $handle, 'Private analytics state');
        $raw = stream_get_contents($handle, $maxBytes + 1);
        if (!is_string($raw) || strlen($raw) !== $size || !feof($handle)) {
            throw new PuAnalyticsException('Private analytics state could not be read completely.');
        }
    } finally {
        @fclose($handle);
    }
    try {
        $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new PuAnalyticsException('Private analytics state is corrupted; no data was overwritten.');
    }
    if (!is_array($decoded)) {
        throw new PuAnalyticsException('Private analytics state has an invalid structure.');
    }
    return $decoded;
}

function pu_analytics_secret(string $privateRoot): string
{
    pu_analytics_prepare_private_root($privateRoot);
    $path = $privateRoot . '/rate-secret.txt';
    $lockPath = $path . '.lock';
    pu_analytics_assert_regular_or_absent($path, 'Private analytics secret');
    pu_analytics_assert_regular_or_absent($lockPath, 'Private analytics secret lock');
    $lock = @fopen($lockPath, 'c+b');
    if (!is_resource($lock)) {
        throw new PuAnalyticsException('Private analytics secret could not be locked.');
    }
    try {
        pu_analytics_assert_open_regular($lockPath, $lock, 'Private analytics secret lock');
        pu_analytics_require_mode($lockPath, 0600, 'Private analytics secret lock');
        if (!@flock($lock, LOCK_EX)) {
            throw new PuAnalyticsException('Private analytics secret could not be locked.');
        }
        pu_analytics_assert_open_regular($lockPath, $lock, 'Private analytics secret lock');
        pu_analytics_assert_regular_or_absent($path, 'Private analytics secret');
        if (!file_exists($path)) {
            pu_analytics_assert_regular_or_absent($path, 'Private analytics secret');
            $created = @fopen($path, 'xb');
            if (!is_resource($created)) {
                throw new PuAnalyticsException('Private analytics secret could not be created.');
            }
            try {
                pu_analytics_assert_open_regular($path, $created, 'Private analytics secret');
                pu_analytics_require_mode($path, 0600, 'Private analytics secret');
                pu_analytics_write_all($created, bin2hex(random_bytes(32)) . "\n");
            } catch (Throwable $error) {
                @fclose($created);
                @unlink($path);
                throw $error;
            }
            @fclose($created);
        }
        pu_analytics_require_mode($path, 0600, 'Private analytics secret');
        pu_analytics_assert_regular_or_absent($path, 'Private analytics secret');
        $secretHandle = @fopen($path, 'rb');
        if (!is_resource($secretHandle)) {
            throw new PuAnalyticsException('Private analytics secret could not be read.');
        }
        try {
            pu_analytics_assert_open_regular($path, $secretHandle, 'Private analytics secret');
            $raw = stream_get_contents($secretHandle, 129);
            if (!is_string($raw) || !feof($secretHandle)) {
                throw new PuAnalyticsException('Private analytics secret is invalid.');
            }
        } finally {
            @fclose($secretHandle);
        }
        $secret = is_string($raw) ? trim($raw) : '';
        if (!preg_match('/^[a-f0-9]{64}$/D', $secret)) {
            throw new PuAnalyticsException('Private analytics secret is invalid.');
        }
        return $secret;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function pu_analytics_client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        throw new PuAnalyticsException('The request address could not be validated.', 403);
    }
    return $ip;
}

function pu_analytics_consume_rate(
    string $privateRoot,
    string $scope,
    int $hourLimit,
    int $dayLimit,
    int $maxEntries,
    ?int $now = null
): void {
    if (!preg_match('/^[a-z][a-z0-9-]{1,40}$/D', $scope)) {
        throw new PuAnalyticsException('The rate-limit scope is invalid.');
    }
    $now ??= time();
    $identity = hash_hmac('sha256', $scope . '|ip|' . pu_analytics_client_ip(), pu_analytics_secret($privateRoot));
    $statePath = $privateRoot . '/rate-limits.json';
    $lockPath = $privateRoot . '/rate-limits.lock';
    pu_analytics_assert_regular_or_absent($statePath, 'Analytics rate-limit state');
    pu_analytics_assert_regular_or_absent($lockPath, 'Analytics rate-limit lock');
    $lock = @fopen($lockPath, 'c+b');
    if (!is_resource($lock)) {
        throw new PuAnalyticsException('Analytics rate controls are unavailable.');
    }
    try {
        pu_analytics_assert_open_regular($lockPath, $lock, 'Analytics rate-limit lock');
        pu_analytics_require_mode($lockPath, 0600, 'Analytics rate-limit lock');
        if (!@flock($lock, LOCK_EX)) {
            throw new PuAnalyticsException('Analytics rate controls are unavailable.');
        }
        pu_analytics_assert_open_regular($lockPath, $lock, 'Analytics rate-limit lock');
        pu_analytics_assert_regular_or_absent($statePath, 'Analytics rate-limit state');
        // Four MiB covers the configured hard ceiling of 20,000 compact
        // identities while keeping corrupt or attacker-inflated state bounded.
        $state = pu_analytics_read_json_file($statePath, 4194304) ?? ['version' => 1, 'entries' => []];
        if (($state['version'] ?? null) !== 1 || !is_array($state['entries'] ?? null)) {
            throw new PuAnalyticsException('Analytics rate controls are corrupted; the request was denied.');
        }

        $entries = [];
        $staleBefore = $now - 172800;
        foreach ($state['entries'] as $key => $entry) {
            if (!is_string($key)
                || !preg_match('/^[a-f0-9]{64}$/D', $key)
                || !is_array($entry)
                || !is_int($entry['hour'] ?? null)
                || !is_int($entry['hour_count'] ?? null)
                || !is_string($entry['day'] ?? null)
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $entry['day'])
                || !is_int($entry['day_count'] ?? null)
                || !is_int($entry['last'] ?? null)
                || $entry['hour_count'] < 0
                || $entry['day_count'] < 0
            ) {
                throw new PuAnalyticsException('Analytics rate controls are corrupted; the request was denied.');
            }
            if ($entry['last'] >= $staleBefore) {
                $entries[$key] = $entry;
            }
        }
        if (count($entries) > $maxEntries) {
            throw new PuAnalyticsException('Analytics rate controls are over capacity; the request was denied.');
        }
        if (!isset($entries[$identity]) && count($entries) >= $maxEntries) {
            throw new PuAnalyticsException('Analytics rate controls are at capacity; the request was denied.', 503);
        }

        $hour = intdiv($now, 3600) * 3600;
        $day = gmdate('Y-m-d', $now);
        $entry = $entries[$identity] ?? [
            'hour' => $hour,
            'hour_count' => 0,
            'day' => $day,
            'day_count' => 0,
            'last' => $now,
        ];
        if ($entry['hour'] !== $hour) {
            $entry['hour'] = $hour;
            $entry['hour_count'] = 0;
        }
        if ($entry['day'] !== $day) {
            $entry['day'] = $day;
            $entry['day_count'] = 0;
        }
        if ($entry['hour_count'] >= $hourLimit || $entry['day_count'] >= $dayLimit) {
            $retryAfter = $entry['hour_count'] >= $hourLimit
                ? max(1, ($hour + 3600) - $now)
                : max(1, strtotime($day . ' +1 day UTC') - $now);
            throw new PuAnalyticsException('Too many requests.', 429, $retryAfter);
        }
        $entry['hour_count']++;
        $entry['day_count']++;
        $entry['last'] = $now;
        $entries[$identity] = $entry;
        pu_analytics_atomic_json($statePath, ['version' => 1, 'entries' => $entries]);
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function pu_analytics_request_origin_is_allowed(bool $collector = false): bool
{
    $hostHeader = strtolower(rtrim(trim((string)($_SERVER['HTTP_HOST'] ?? '')), '.'));
    if (!preg_match('/^((?:www\.)?bobsome1\.com)(?::443)?$/D', $hostHeader, $match)) {
        return false;
    }
    $host = $match[1];
    $fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite !== '' && $fetchSite !== 'same-origin') {
        return false;
    }
    if ($collector) {
        $fetchDest = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')));
        if ($fetchDest !== '' && $fetchDest !== 'empty') {
            return false;
        }
    }

    $validateUrl = static function (string $raw, bool $originOnly) use ($host): bool {
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
        return !$originOnly
            || (!isset($parts['query']) && in_array((string)($parts['path'] ?? ''), ['', '/'], true));
    };

    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        return $validateUrl($origin, true);
    }
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    return $fetchSite === 'same-origin'
        && $referer !== ''
        && $validateUrl($referer, false);
}

function pu_analytics_allowed_events(): array
{
    return [
        'pageview',
        'engaged_30s',
        'scroll_25',
        'scroll_50',
        'scroll_75',
        'scroll_90',
        'chapter_start',
        'chapter_next',
        'share_click',
        'support_page_click',
        'paypal_click',
        'book_complete',
        'timeline_event',
        'search_use',
        'reviews_page_click',
        'review_submit_click',
        'scholarly_review_submit_click',
        'store_click',
        'product_checkout_click',
        'service_checkout_click',
        'free_reader_click',
        'qualified_lead_click',
        'partner_inquiry_click',
    ];
}

function pu_analytics_utf8_length(string $value): int
{
    if (preg_match('//u', $value) !== 1) {
        throw new PuAnalyticsException('Analytics fields must contain valid UTF-8.', 422);
    }
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    $count = preg_match_all('/./us', $value, $matches);
    if (!is_int($count)) {
        throw new PuAnalyticsException('Analytics fields must contain valid UTF-8.', 422);
    }
    return $count;
}

function pu_analytics_text_field(array $data, string $key, int $maxLength, bool $required = false): string
{
    if (!array_key_exists($key, $data)) {
        if ($required) {
            throw new PuAnalyticsException('A required analytics field is missing.', 422);
        }
        return '';
    }
    if (!is_string($data[$key])) {
        throw new PuAnalyticsException('Analytics fields must be plain text.', 422);
    }
    $value = $data[$key];
    if (preg_match('/[\x00-\x1F\x7F]/u', $value) === 1 || pu_analytics_utf8_length($value) > $maxLength) {
        throw new PuAnalyticsException('An analytics field is invalid or too long.', 422);
    }
    return $value;
}

function pu_analytics_parse_payload(string $raw): array
{
    if ($raw === '') {
        throw new PuAnalyticsException('An analytics payload is required.', 400);
    }
    try {
        $decoded = json_decode($raw, false, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new PuAnalyticsException('The analytics payload is not valid JSON.', 400);
    }
    if (!$decoded instanceof stdClass) {
        throw new PuAnalyticsException('The analytics payload must be a JSON object.', 422);
    }
    return pu_analytics_validate_payload_data(get_object_vars($decoded));
}

function pu_analytics_validate_payload_data(array $data): array
{
    $known = [
        'event', 'path', 'title', 'session', 'chapter', 'referrer',
        'source', 'medium', 'campaign', 'content', 'target', 'label',
    ];
    foreach ($data as $key => $_value) {
        if (!is_string($key) || !in_array($key, $known, true)) {
            throw new PuAnalyticsException('The analytics payload contains an unknown field.', 422);
        }
    }

    $event = pu_analytics_text_field($data, 'event', 60, true);
    if (!in_array($event, pu_analytics_allowed_events(), true)) {
        throw new PuAnalyticsException('The analytics event is not allowed.', 422);
    }
    $path = pu_analytics_text_field($data, 'path', 240, true);
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '?') || str_contains($path, '#') || str_contains($path, '\\')) {
        throw new PuAnalyticsException('The analytics path is invalid.', 422);
    }
    $session = pu_analytics_text_field($data, 'session', 80, true);
    if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $session)) {
        throw new PuAnalyticsException('The analytics session is invalid.', 422);
    }
    if (!array_key_exists('chapter', $data) || !is_int($data['chapter']) || $data['chapter'] < 0 || $data['chapter'] > 13) {
        throw new PuAnalyticsException('The analytics chapter is invalid.', 422);
    }

    $payload = [
        'event' => $event,
        'path' => $path,
        'title' => pu_analytics_text_field($data, 'title', 240),
        'session' => $session,
        'chapter' => $data['chapter'],
        'referrer' => pu_analytics_text_field($data, 'referrer', 240),
        'source' => pu_analytics_text_field($data, 'source', 120),
        'medium' => pu_analytics_text_field($data, 'medium', 120),
        'campaign' => pu_analytics_text_field($data, 'campaign', 120),
        'content' => pu_analytics_text_field($data, 'content', 120),
        'target' => pu_analytics_text_field($data, 'target', 300),
        'label' => pu_analytics_text_field($data, 'label', 180),
    ];
    if ($payload['referrer'] !== '') {
        $validDomain = filter_var($payload['referrer'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
        $validIp = filter_var($payload['referrer'], FILTER_VALIDATE_IP) !== false;
        if (!$validDomain || $validIp) {
            throw new PuAnalyticsException('The analytics referrer must contain only a website name.', 422);
        }
    }
    if ($payload['target'] !== '') {
        $target = $payload['target'];
        if (str_contains($target, '?') || str_contains($target, '#')) {
            throw new PuAnalyticsException('Analytics link targets may not contain query or fragment data.', 422);
        }
        if (str_starts_with($target, '/')) {
            if (str_starts_with($target, '//') || str_contains($target, '\\')) {
                throw new PuAnalyticsException('The analytics link target is invalid.', 422);
            }
        } elseif (!preg_match('/^(?:mailto|tel):[^\s]+$/Di', $target)) {
            $parts = parse_url($target);
            $targetHost = is_array($parts) ? (string)($parts['host'] ?? '') : '';
            $literalHost = str_starts_with($targetHost, '[') && str_ends_with($targetHost, ']')
                ? substr($targetHost, 1, -1)
                : $targetHost;
            if (!is_array($parts)
                || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
                || $targetHost === ''
                || filter_var($literalHost, FILTER_VALIDATE_IP) !== false
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['query'])
                || isset($parts['fragment'])
            ) {
                throw new PuAnalyticsException('The analytics link target is invalid.', 422);
            }
        }
    }
    if ($event === 'search_use' && ($payload['label'] !== '' || $payload['target'] !== '')) {
        throw new PuAnalyticsException('Search text is not accepted by analytics.', 422);
    }
    return $payload;
}

function pu_analytics_validate_stored_event($decoded): array
{
    if (!$decoded instanceof stdClass) {
        throw new PuAnalyticsException('An analytics event record must be a JSON object.');
    }
    $row = get_object_vars($decoded);
    if (!is_string($row['t'] ?? null)
        || strlen($row['t']) > 40
        || strtotime($row['t']) === false
    ) {
        throw new PuAnalyticsException('An analytics event record has an invalid timestamp.');
    }
    $timestamp = $row['t'];
    unset($row['t']);
    // Release 15 records may contain the former UTM term field. Validation
    // removes it so the locked privacy migration can erase it from disk.
    if (array_key_exists('term', $row)) {
        if (!is_string($row['term'])) {
            throw new PuAnalyticsException('An analytics event record has an invalid legacy field.');
        }
        unset($row['term']);
    }
    if (is_string($row['referrer'] ?? null) && str_contains($row['referrer'], '/')) {
        $row['referrer'] = explode('/', $row['referrer'], 2)[0];
    }
    if (is_string($row['referrer'] ?? null)
        && filter_var($row['referrer'], FILTER_VALIDATE_IP) !== false
    ) {
        $row['referrer'] = '';
    }
    if (is_string($row['target'] ?? null) && $row['target'] !== '') {
        $legacyTarget = $row['target'];
        if (str_starts_with($legacyTarget, '/')) {
            $parts = parse_url($legacyTarget);
            $row['target'] = is_array($parts) ? (string)($parts['path'] ?? '') : '';
        } elseif (preg_match('/^(?:mailto|tel):/Di', $legacyTarget)) {
            $parts = preg_split('/[?#]/', $legacyTarget, 2);
            $row['target'] = is_array($parts) ? (string)($parts[0] ?? '') : '';
        } else {
            $parts = parse_url($legacyTarget);
            if (is_array($parts)
                && strtolower((string)($parts['scheme'] ?? '')) === 'https'
                && (string)($parts['host'] ?? '') !== ''
                && filter_var(
                    str_starts_with((string)$parts['host'], '[') && str_ends_with((string)$parts['host'], ']')
                        ? substr((string)$parts['host'], 1, -1)
                        : (string)$parts['host'],
                    FILTER_VALIDATE_IP
                ) === false
                && !isset($parts['user'])
                && !isset($parts['pass'])
            ) {
                $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
                $row['target'] = 'https://' . $parts['host'] . $port . (string)($parts['path'] ?? '/');
            } else {
                $row['target'] = '';
            }
        }
    }
    if (($row['event'] ?? null) === 'search_use') {
        $row['target'] = '';
        $row['label'] = '';
    }
    return ['t' => $timestamp] + pu_analytics_validate_payload_data($row);
}

function pu_analytics_read_request_body(int $maxBytes, $stream = null): string
{
    $contentLength = trim((string)($_SERVER['CONTENT_LENGTH'] ?? ''));
    if ($contentLength !== '' && (!preg_match('/^\d+$/D', $contentLength) || (int)$contentLength > $maxBytes)) {
        throw new PuAnalyticsException('The analytics payload is too large.', 413);
    }
    $contentEncoding = strtolower(trim((string)($_SERVER['HTTP_CONTENT_ENCODING'] ?? '')));
    if ($contentEncoding !== '' && $contentEncoding !== 'identity') {
        throw new PuAnalyticsException('Encoded analytics payloads are not accepted.', 415);
    }
    $openedHere = false;
    if (!is_resource($stream)) {
        $stream = @fopen('php://input', 'rb');
        $openedHere = true;
    }
    if (!is_resource($stream)) {
        throw new PuAnalyticsException('The analytics payload could not be read.', 400);
    }
    $raw = @stream_get_contents($stream, $maxBytes + 1);
    if ($openedHere) {
        @fclose($stream);
    }
    if (!is_string($raw)) {
        throw new PuAnalyticsException('The analytics payload could not be read.', 400);
    }
    if (strlen($raw) > $maxBytes) {
        throw new PuAnalyticsException('The analytics payload is too large.', 413);
    }
    if ($contentLength !== '' && strlen($raw) !== (int)$contentLength) {
        throw new PuAnalyticsException('The analytics payload length did not match its declaration.', 400);
    }
    return $raw;
}

function pu_analytics_dashboard_post(int $maxBytes = 2048, $stream = null): array
{
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
    if ($contentType !== 'application/x-www-form-urlencoded') {
        throw new PuAnalyticsException('URL-encoded form data is required.', 415);
    }
    $contentLength = trim((string)($_SERVER['CONTENT_LENGTH'] ?? ''));
    if ($contentLength === '') {
        throw new PuAnalyticsException('A bounded Content-Length is required.', 411);
    }
    if (!preg_match('/^\d+$/D', $contentLength) || (int)$contentLength < 1 || (int)$contentLength > $maxBytes) {
        throw new PuAnalyticsException('The dashboard request is too large.', 413);
    }
    $transferEncoding = strtolower(trim((string)($_SERVER['HTTP_TRANSFER_ENCODING'] ?? '')));
    if ($transferEncoding !== '' && $transferEncoding !== 'identity') {
        throw new PuAnalyticsException('Chunked dashboard requests are not accepted.', 411);
    }
    if (!empty($_FILES)) {
        throw new PuAnalyticsException('File uploads are not accepted.', 422);
    }

    $raw = pu_analytics_read_request_body($maxBytes, $stream);
    $parsed = [];
    parse_str($raw, $parsed);
    if (!is_array($parsed)) {
        throw new PuAnalyticsException('Dashboard form data is invalid.', 422);
    }
    $allowed = ['action', 'csrf', 'password'];
    $parsedBytes = 0;
    $fields = [];
    foreach ($parsed as $key => $value) {
        if (!is_string($key) || !in_array($key, $allowed, true) || !is_string($value)) {
            throw new PuAnalyticsException('Dashboard fields must contain bounded plain text.', 422);
        }
        $parsedBytes += strlen($key) + strlen($value);
        if ($parsedBytes > $maxBytes) {
            throw new PuAnalyticsException('The dashboard request is too large.', 413);
        }
        $fields[$key] = $value;
    }
    return $fields;
}

function pu_analytics_scan_event_file(string $path, int $maxBytes, int $maxEvents, int $maxLineBytes): array
{
    pu_analytics_assert_regular_or_absent($path, 'Analytics event file');
    if (!file_exists($path)) {
        throw new PuAnalyticsException('An analytics event file is unavailable.');
    }
    clearstatcache(true, $path);
    $size = @filesize($path);
    if (!is_int($size) || $size < 0 || $size > $maxBytes) {
        throw new PuAnalyticsException('An analytics event file is invalid or over capacity.');
    }
    if ($size === 0) {
        return ['bytes' => 0, 'events' => 0];
    }
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        throw new PuAnalyticsException('An analytics event file could not be read.');
    }
    $count = 0;
    try {
        pu_analytics_assert_open_regular($path, $handle, 'Analytics event file');
        if (!@flock($handle, LOCK_SH)) {
            throw new PuAnalyticsException('An analytics event file could not be locked for validation.');
        }
        while (($line = fgets($handle, $maxLineBytes + 2)) !== false) {
            if (strlen($line) > $maxLineBytes || !str_ends_with($line, "\n")) {
                throw new PuAnalyticsException('An analytics event file contains an invalid record.');
            }
            try {
                $decoded = json_decode($line, false, 16, JSON_THROW_ON_ERROR);
                pu_analytics_validate_stored_event($decoded);
            } catch (Throwable $error) {
                throw new PuAnalyticsException('An analytics event file is corrupted; no event was written.');
            }
            $count++;
            if ($count > $maxEvents) {
                throw new PuAnalyticsException('An analytics event file is over its record limit.');
            }
        }
        if (!feof($handle)) {
            throw new PuAnalyticsException('An analytics event file could not be read completely.');
        }
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
    return ['bytes' => $size, 'events' => $count];
}

function pu_analytics_sanitize_event_file(string $path, array $config): array
{
    pu_analytics_assert_regular_or_absent($path, 'Analytics event file');
    if (!file_exists($path)) {
        throw new PuAnalyticsException('An analytics event file is unavailable.');
    }
    clearstatcache(true, $path);
    $size = @filesize($path);
    if (!is_int($size) || $size < 0 || $size > (int)$config['max_daily_bytes']) {
        throw new PuAnalyticsException('An analytics event file is invalid or over capacity.');
    }
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        throw new PuAnalyticsException('An analytics event file could not be read.');
    }
    $sanitized = '';
    $count = 0;
    $changed = false;
    try {
        pu_analytics_assert_open_regular($path, $handle, 'Analytics event file');
        if (!@flock($handle, LOCK_EX)) {
            throw new PuAnalyticsException('An analytics event file could not be locked for privacy migration.');
        }
        while (($line = fgets($handle, (int)$config['max_event_line_bytes'] + 2)) !== false) {
            if (strlen($line) > (int)$config['max_event_line_bytes'] || !str_ends_with($line, "\n")) {
                throw new PuAnalyticsException('An analytics event file contains an invalid record.');
            }
            try {
                $decoded = json_decode($line, false, 16, JSON_THROW_ON_ERROR);
                $row = pu_analytics_validate_stored_event($decoded);
                $cleanLine = json_encode(
                    $row,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ) . "\n";
            } catch (Throwable $error) {
                throw new PuAnalyticsException('An analytics event file is corrupted; no data was overwritten.');
            }
            if (strlen($cleanLine) > (int)$config['max_event_line_bytes']) {
                throw new PuAnalyticsException('A sanitized analytics event exceeded its record limit.');
            }
            if ($cleanLine !== $line) {
                $changed = true;
            }
            $sanitized .= $cleanLine;
            if (strlen($sanitized) > (int)$config['max_daily_bytes']) {
                throw new PuAnalyticsException('Sanitized analytics data exceeded its daily byte limit.');
            }
            $count++;
            if ($count > (int)$config['max_daily_events']) {
                throw new PuAnalyticsException('An analytics event file is over its record limit.');
            }
        }
        if (!feof($handle)) {
            throw new PuAnalyticsException('An analytics event file could not be read completely.');
        }
        if ($changed) {
            pu_analytics_atomic_bytes($path, $sanitized);
        } else {
            pu_analytics_require_mode($path, 0600, 'Analytics event file');
        }
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
    return ['bytes' => strlen($sanitized), 'events' => $count];
}

function pu_analytics_event_file_digest(string $path, int $maxBytes): array
{
    pu_analytics_assert_regular_or_absent($path, 'Analytics event file');
    if (!file_exists($path)) {
        throw new PuAnalyticsException('An analytics event file is unavailable.');
    }
    clearstatcache(true, $path);
    $size = @filesize($path);
    if (!is_int($size) || $size < 0 || $size > $maxBytes) {
        throw new PuAnalyticsException('An analytics event file is invalid or over capacity.');
    }
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        throw new PuAnalyticsException('An analytics event file could not be opened for integrity checking.');
    }
    try {
        pu_analytics_assert_open_regular($path, $handle, 'Analytics event file');
        if (!@flock($handle, LOCK_SH)) {
            throw new PuAnalyticsException('An analytics event file could not be locked for integrity checking.');
        }
        $context = hash_init('sha256');
        $hashed = hash_update_stream($context, $handle, $maxBytes + 1);
        clearstatcache(true, $path);
        $after = @fstat($handle);
        if ($hashed !== $size
            || !feof($handle)
            || !is_array($after)
            || !is_int($after['size'] ?? null)
            || $after['size'] !== $size
        ) {
            throw new PuAnalyticsException('An analytics event file changed during integrity checking.');
        }
        $digest = hash_final($context);
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
    return ['bytes' => $size, 'sha256' => $digest];
}

function pu_analytics_metadata_integrity(string $date, int $bytes, int $events, string $sha256): string
{
    return hash('sha256', "2\n" . $date . "\n" . $bytes . "\n" . $events . "\n" . $sha256);
}

function pu_analytics_event_metadata_status(
    ?array $metadata,
    string $date,
    array $config,
    ?array $actualDigest = null
): ?array {
    if ($metadata === null) {
        return null;
    }
    $version = $metadata['version'] ?? null;
    $bytes = $metadata['bytes'] ?? null;
    $events = $metadata['events'] ?? null;
    if (($version !== 1 && $version !== 2)
        || ($metadata['date'] ?? null) !== $date
        || !is_int($bytes)
        || !is_int($events)
        || $bytes < 0
        || $events < 0
        || $bytes > (int)$config['max_daily_bytes']
        || $events > (int)$config['max_daily_events']
    ) {
        throw new PuAnalyticsException('Analytics event metadata is corrupted; no data was overwritten.');
    }
    if ($version === 1) {
        return ['current' => false, 'bytes' => $bytes, 'events' => $events];
    }
    $sha256 = $metadata['sha256'] ?? null;
    $integrity = $metadata['integrity'] ?? null;
    if (($metadata['sanitized'] ?? null) !== true
        || !is_string($sha256)
        || !preg_match('/^[a-f0-9]{64}$/D', $sha256)
        || !is_string($integrity)
        || !hash_equals(pu_analytics_metadata_integrity($date, $bytes, $events, $sha256), $integrity)
    ) {
        throw new PuAnalyticsException('Analytics event metadata is corrupted; no data was overwritten.');
    }
    if ($actualDigest !== null
        && (($actualDigest['bytes'] ?? null) !== $bytes || ($actualDigest['sha256'] ?? null) !== $sha256)
    ) {
        return ['current' => false, 'bytes' => $bytes, 'events' => $events];
    }
    return ['current' => true, 'bytes' => $bytes, 'events' => $events, 'sha256' => $sha256];
}

function pu_analytics_write_event_metadata(string $eventFile, array $stats, ?int $maxBytes = null): void
{
    if (!preg_match('/events-(\d{4}-\d{2}-\d{2})\.jsonl$/D', $eventFile, $match)) {
        throw new PuAnalyticsException('Analytics event metadata received an invalid path.');
    }
    $maxBytes ??= (int)pu_analytics_config()['max_daily_bytes'];
    $digest = pu_analytics_event_file_digest($eventFile, $maxBytes);
    if (!is_int($stats['bytes'] ?? null)
        || !is_int($stats['events'] ?? null)
        || $stats['bytes'] !== $digest['bytes']
        || $stats['events'] < 0
    ) {
        throw new PuAnalyticsException('Analytics event metadata did not match its event file.');
    }
    $integrity = pu_analytics_metadata_integrity($match[1], $stats['bytes'], $stats['events'], $digest['sha256']);
    pu_analytics_atomic_json($eventFile . '.meta.json', [
        'version' => 2,
        'sanitized' => true,
        'date' => $match[1],
        'bytes' => (int)$stats['bytes'],
        'events' => (int)$stats['events'],
        'sha256' => $digest['sha256'],
        'integrity' => $integrity,
    ]);
}

function pu_analytics_list_retained_event_files(string $dataDir, array $config): array
{
    if (!is_dir($dataDir)) {
        return [];
    }
    if (is_link($dataDir)) {
        throw new PuAnalyticsException('Analytics data storage may not be a symbolic link.');
    }
    $paths = [];
    $iterator = new DirectoryIterator($dataDir);
    foreach ($iterator as $item) {
        if ($item->isDot() || !preg_match('/^events-\d{4}-\d{2}-\d{2}\.jsonl$/D', $item->getFilename())) {
            continue;
        }
        if ($item->isLink() || !$item->isFile()) {
            throw new PuAnalyticsException('Analytics data contains an unsafe event file.');
        }
        $paths[] = $item->getPathname();
        if (count($paths) > (int)$config['retention_days'] + 2) {
            throw new PuAnalyticsException('Analytics event storage contains too many daily files.');
        }
    }
    sort($paths, SORT_STRING);
    return $paths;
}

function pu_analytics_sanitize_retained_event_files(string $dataDir, array $config, int $workBytes = 16777216): void
{
    $paths = pu_analytics_list_retained_event_files($dataDir, $config);
    $processed = 0;
    $incomplete = false;
    foreach ($paths as $path) {
        if (!preg_match('/events-(\d{4}-\d{2}-\d{2})\.jsonl$/D', $path, $dateMatch)) {
            throw new PuAnalyticsException('Analytics data contains an invalid event path.');
        }
        clearstatcache(true, $path);
        $actualBytes = @filesize($path);
        if (!is_int($actualBytes) || $actualBytes < 0 || $actualBytes > (int)$config['max_daily_bytes']) {
            throw new PuAnalyticsException('An analytics event file is invalid or over capacity.');
        }
        $metadata = pu_analytics_read_json_file($path . '.meta.json', 4096);
        $status = pu_analytics_event_metadata_status($metadata, $dateMatch[1], $config);
        if (($status['current'] ?? false) === true && $status['bytes'] === $actualBytes) {
            pu_analytics_require_mode($path, 0600, 'Analytics event file');
            continue;
        }
        if ($processed > 0 && $processed + $actualBytes > $workBytes) {
            $incomplete = true;
            continue;
        }
        $stats = pu_analytics_sanitize_event_file($path, $config);
        pu_analytics_write_event_metadata($path, $stats, (int)$config['max_daily_bytes']);
        $processed += $actualBytes;
    }
    if ($incomplete) {
        throw new PuAnalyticsException('Analytics privacy migration requires another bounded pass.', 503);
    }
}

function pu_analytics_with_event_lock(string $privateRoot, int $mode, callable $operation)
{
    pu_analytics_prepare_private_root($privateRoot);
    $lockPath = $privateRoot . '/events.lock';
    pu_analytics_assert_regular_or_absent($lockPath, 'Analytics event lock');
    $lock = @fopen($lockPath, 'c+b');
    if (!is_resource($lock)) {
        throw new PuAnalyticsException('Analytics event storage could not be locked.');
    }
    try {
        pu_analytics_assert_open_regular($lockPath, $lock, 'Analytics event lock');
        pu_analytics_require_mode($lockPath, 0600, 'Analytics event lock');
        if (!@flock($lock, $mode)) {
            throw new PuAnalyticsException('Analytics event storage could not be locked.');
        }
        pu_analytics_assert_open_regular($lockPath, $lock, 'Analytics event lock');
        return $operation();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function pu_analytics_prune_event_files(string $dataDir, int $now, int $retentionDays): void
{
    $cutoff = gmdate('Y-m-d', $now - (($retentionDays - 1) * 86400));
    $kept = 0;
    $iterator = new DirectoryIterator($dataDir);
    foreach ($iterator as $item) {
        if ($item->isDot()) {
            continue;
        }
        $name = $item->getFilename();
        if (!preg_match('/^events-(\d{4}-\d{2}-\d{2})\.jsonl(?:\.meta\.json)?$/D', $name, $match)) {
            continue;
        }
        if ($item->isLink()) {
            throw new PuAnalyticsException('Analytics event storage contains a symbolic link.');
        }
        if ($match[1] < $cutoff) {
            if (!@unlink($item->getPathname())) {
                throw new PuAnalyticsException('Expired analytics data could not be retired.');
            }
            continue;
        }
        if (str_ends_with($name, '.jsonl')) {
            $kept++;
        }
    }
    if ($kept > $retentionDays + 2) {
        throw new PuAnalyticsException('Analytics event storage contains too many daily files.');
    }
}

/**
 * Convert retained Release 15 JSONL records to the minimized Release 16 shape.
 * The first pass validates every bounded input before any retained event bytes
 * are replaced. Each successful replacement and its integrity metadata use an
 * atomic rename while the shared event lock excludes collectors/readers.
 */
function pu_analytics_migrate_private_storage(
    string $privateRoot,
    ?array $config = null,
    ?int $now = null
): array {
    clearstatcache(true, $privateRoot);
    if (is_link($privateRoot)) {
        throw new PuAnalyticsException('Private analytics storage may not be a symbolic link.');
    }
    if (!file_exists($privateRoot)) {
        return ['files' => 0, 'events' => 0, 'bytes' => 0];
    }
    if (!is_dir($privateRoot)) {
        throw new PuAnalyticsException('Private analytics storage is not a directory.');
    }

    $config ??= pu_analytics_config();
    $now ??= time();
    $workLimit = (int)($config['migration_max_bytes'] ?? 0);
    if ($workLimit < 1) {
        throw new PuAnalyticsException('Analytics privacy migration has an invalid byte limit.');
    }
    $dataDir = $privateRoot . '/data';

    return pu_analytics_with_event_lock($privateRoot, LOCK_EX, static function () use (
        $dataDir,
        $config,
        $now,
        $workLimit
    ): array {
        if (is_link($dataDir)) {
            throw new PuAnalyticsException('Analytics data storage may not be a symbolic link.');
        }
        if (!file_exists($dataDir)) {
            return ['files' => 0, 'events' => 0, 'bytes' => 0];
        }
        pu_analytics_prepare_dir($dataDir, 0700);
        pu_analytics_prune_event_files($dataDir, $now, (int)$config['retention_days']);
        $paths = pu_analytics_list_retained_event_files($dataDir, $config);

        $validated = [];
        $totalBytes = 0;
        $totalEvents = 0;
        foreach ($paths as $path) {
            if (!preg_match('/events-(\d{4}-\d{2}-\d{2})\.jsonl$/D', $path, $dateMatch)) {
                throw new PuAnalyticsException('Analytics data contains an invalid event path.');
            }
            clearstatcache(true, $path);
            $size = @filesize($path);
            if (!is_int($size)
                || $size < 0
                || $size > (int)$config['max_daily_bytes']
                || $totalBytes + $size > $workLimit
            ) {
                throw new PuAnalyticsException('Analytics privacy migration exceeded its bounded byte limit.');
            }
            $metadata = pu_analytics_read_json_file($path . '.meta.json', 4096);
            pu_analytics_event_metadata_status($metadata, $dateMatch[1], $config);
            $stats = pu_analytics_scan_event_file(
                $path,
                (int)$config['max_daily_bytes'],
                (int)$config['max_daily_events'],
                (int)$config['max_event_line_bytes']
            );
            $validated[] = ['path' => $path, 'stats' => $stats];
            $totalBytes += $size;
            $totalEvents += $stats['events'];
        }

        foreach ($validated as $item) {
            $stats = pu_analytics_sanitize_event_file($item['path'], $config);
            if ($stats['events'] !== $item['stats']['events']) {
                throw new PuAnalyticsException('Analytics event storage changed during privacy migration.');
            }
            pu_analytics_write_event_metadata(
                $item['path'],
                $stats,
                (int)$config['max_daily_bytes']
            );
        }
        return ['files' => count($validated), 'events' => $totalEvents, 'bytes' => $totalBytes];
    });
}

function pu_analytics_append_event(string $privateRoot, array $payload, ?int $now = null, ?array $config = null): void
{
    $now ??= time();
    $config ??= pu_analytics_config();
    $dataDir = $privateRoot . '/data';
    pu_analytics_prepare_private_root($privateRoot);
    pu_analytics_prepare_dir($dataDir, 0700);

    pu_analytics_with_event_lock($privateRoot, LOCK_EX, static function () use ($dataDir, $payload, $now, $config): void {
        pu_analytics_prune_event_files($dataDir, $now, (int)$config['retention_days']);
        pu_analytics_sanitize_retained_event_files($dataDir, $config);
        $date = gmdate('Y-m-d', $now);
        $file = $dataDir . '/events-' . $date . '.jsonl';
        $metaFile = $file . '.meta.json';
        pu_analytics_assert_regular_or_absent($file, 'Analytics event file');
        pu_analytics_assert_regular_or_absent($metaFile, 'Analytics event metadata');

        $metadata = pu_analytics_read_json_file($metaFile, 4096);
        if (file_exists($file)) {
            $digest = pu_analytics_event_file_digest($file, (int)$config['max_daily_bytes']);
            $status = pu_analytics_event_metadata_status($metadata, $date, $config, $digest);
            if (($status['current'] ?? false) === true) {
                $stats = ['bytes' => $status['bytes'], 'events' => $status['events']];
            } else {
                $stats = pu_analytics_sanitize_event_file($file, $config);
                pu_analytics_write_event_metadata($file, $stats, (int)$config['max_daily_bytes']);
            }
        } elseif ($metadata !== null) {
            throw new PuAnalyticsException('Analytics event metadata has no matching event file.');
        } else {
            $stats = ['bytes' => 0, 'events' => 0];
        }

        $record = ['t' => gmdate('c', $now)] + $payload;
        try {
            $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException $error) {
            throw new PuAnalyticsException('The analytics event could not be encoded.', 422);
        }
        if (strlen($line) > (int)$config['max_event_line_bytes']) {
            throw new PuAnalyticsException('The analytics event is too large.', 413);
        }
        if ($stats['events'] >= (int)$config['max_daily_events']
            || $stats['bytes'] + strlen($line) > (int)$config['max_daily_bytes']
        ) {
            throw new PuAnalyticsException('Daily analytics storage is at capacity.', 507);
        }

        if (!file_exists($file)) {
            pu_analytics_assert_regular_or_absent($file, 'Analytics event file');
            $created = @fopen($file, 'xb');
            if (!is_resource($created)) {
                throw new PuAnalyticsException('The analytics event file could not be created.');
            }
            try {
                pu_analytics_assert_open_regular($file, $created, 'Analytics event file');
                pu_analytics_require_mode($file, 0600, 'Analytics event file');
            } finally {
                @fclose($created);
            }
        }
        pu_analytics_assert_regular_or_absent($file, 'Analytics event file');
        $handle = @fopen($file, 'ab');
        if (!is_resource($handle)) {
            throw new PuAnalyticsException('The analytics event could not be stored.');
        }
        try {
            pu_analytics_assert_open_regular($file, $handle, 'Analytics event file');
            if (!@flock($handle, LOCK_EX)) {
                throw new PuAnalyticsException('The analytics event file could not be locked for append.');
            }
            pu_analytics_require_mode($file, 0600, 'Analytics event file');
            clearstatcache(true, $file);
            $actualBytes = @filesize($file);
            if (!is_int($actualBytes) || $actualBytes !== $stats['bytes']) {
                throw new PuAnalyticsException('Analytics event storage changed unexpectedly.');
            }
            pu_analytics_write_all($handle, $line);
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
        pu_analytics_write_event_metadata($file, [
            'bytes' => $stats['bytes'] + strlen($line),
            'events' => $stats['events'] + 1,
        ], (int)$config['max_daily_bytes']);
    });
}

function pu_analytics_select_event_files(
    string $dataDir,
    string $startDate,
    string $endDate,
    int $maxFiles,
    int $maxFileBytes
): array {
    if (!is_dir($dataDir)) {
        return [];
    }
    if (is_link($dataDir)) {
        throw new PuAnalyticsException('Analytics data storage may not be a symbolic link.');
    }
    pu_analytics_prepare_dir($dataDir, 0700);
    $files = [];
    $iterator = new DirectoryIterator($dataDir);
    foreach ($iterator as $item) {
        if ($item->isDot()) {
            continue;
        }
        $name = $item->getFilename();
        if (!preg_match('/^events-(\d{4}-\d{2}-\d{2})\.jsonl$/D', $name, $match)) {
            continue;
        }
        if ($match[1] < $startDate || $match[1] > $endDate) {
            continue;
        }
        if ($item->isLink() || !$item->isFile()) {
            throw new PuAnalyticsException('Analytics data contains an unsafe event file.');
        }
        pu_analytics_require_mode($item->getPathname(), 0600, 'Analytics event file');
        $size = $item->getSize();
        if ($size < 0 || $size > $maxFileBytes) {
            throw new PuAnalyticsException('An analytics event file is over the dashboard read limit.');
        }
        $files[] = $item->getPathname();
        if (count($files) > $maxFiles) {
            throw new PuAnalyticsException('The analytics dashboard file limit was exceeded.');
        }
    }
    sort($files, SORT_STRING);
    return $files;
}

function pu_analytics_visit_event_files(
    array $files,
    int $maxFileBytes,
    int $maxLineBytes,
    int $maxEvents,
    callable $visitor
): int {
    $total = 0;
    foreach ($files as $file) {
        if (!is_string($file)) {
            throw new PuAnalyticsException('The analytics dashboard received an unsafe event path.');
        }
        pu_analytics_assert_regular_or_absent($file, 'Analytics event file');
        if (!file_exists($file)) {
            throw new PuAnalyticsException('An analytics event file is unavailable.');
        }
        clearstatcache(true, $file);
        $size = @filesize($file);
        if (!is_int($size) || $size < 0 || $size > $maxFileBytes) {
            throw new PuAnalyticsException('An analytics event file is over the dashboard read limit.');
        }
        $handle = @fopen($file, 'rb');
        if (!is_resource($handle)) {
            throw new PuAnalyticsException('An analytics event file could not be opened.');
        }
        try {
            pu_analytics_assert_open_regular($file, $handle, 'Analytics event file');
            if (!@flock($handle, LOCK_SH)) {
                throw new PuAnalyticsException('An analytics event file could not be locked for dashboard reading.');
            }
            while (($line = fgets($handle, $maxLineBytes + 2)) !== false) {
                if (strlen($line) > $maxLineBytes || !str_ends_with($line, "\n")) {
                    throw new PuAnalyticsException('An analytics event record exceeded its read limit.');
                }
                try {
                    $decoded = json_decode($line, false, 16, JSON_THROW_ON_ERROR);
                } catch (JsonException $error) {
                    throw new PuAnalyticsException('An analytics event record is corrupted.');
                }
                $row = pu_analytics_validate_stored_event($decoded);
                $total++;
                if ($total > $maxEvents) {
                    throw new PuAnalyticsException('The analytics dashboard event limit was exceeded.');
                }
                $visitor($row);
            }
            if (!feof($handle)) {
                throw new PuAnalyticsException('An analytics event file could not be read completely.');
            }
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }
    return $total;
}

function pu_analytics_csv_cell($value): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }
    $cell = (string)($value ?? '');
    if (preg_match('/^[\t\r\n ]*[=+\-@]/', $cell) === 1) {
        return "'" . $cell;
    }
    return $cell;
}
