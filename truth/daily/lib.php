<?php
declare(strict_types=1);

function tw_daily_path_exists(string $path): bool {
    return file_exists($path) || is_link($path);
}

function tw_daily_stat_is_regular(mixed $stat): bool {
    return is_array($stat) && ((((int)($stat['mode'] ?? 0)) & 0170000) === 0100000);
}

function tw_daily_stats_match(mixed $left, mixed $right): bool {
    return is_array($left)
        && is_array($right)
        && is_int($left['dev'] ?? null)
        && is_int($left['ino'] ?? null)
        && $left['dev'] === ($right['dev'] ?? null)
        && $left['ino'] === ($right['ino'] ?? null);
}

function tw_daily_prepare_directory(
    string $dir,
    int $createMode,
    string $label,
    bool $create = true,
    array $allowedModes = []
): string {
    if ($dir === '' || str_contains($dir, "\0") || $dir[0] !== '/') {
        throw new RuntimeException($label . ' path is invalid.');
    }
    if (is_link($dir)) throw new RuntimeException($label . ' must not be a symlink.');
    if (!file_exists($dir)) {
        if (!$create) throw new RuntimeException($label . ' is unavailable.');
        if (!mkdir($dir, $createMode, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create ' . strtolower($label) . '.');
        }
    }
    clearstatcache(true, $dir);
    $stat = lstat($dir);
    $mode = is_array($stat) ? (int)($stat['mode'] ?? 0) : 0;
    $permissionBits = $mode & 0777;
    if ($allowedModes === []) $allowedModes = [$createMode];
    if (is_link($dir)
        || !is_array($stat)
        || ($mode & 0170000) !== 0040000
        || !in_array($permissionBits, $allowedModes, true)
    ) {
        throw new RuntimeException($label . ' is not a secure directory.');
    }
    return rtrim($dir, '/');
}

function tw_daily_assert_absent_or_regular(string $path, string $label): ?array {
    if (is_link($path)) throw new RuntimeException($label . ' must not be a symlink.');
    if (!file_exists($path)) return null;
    clearstatcache(true, $path);
    $stat = lstat($path);
    if (is_link($path) || !tw_daily_stat_is_regular($stat)) {
        throw new RuntimeException($label . ' is not a regular file.');
    }
    return $stat;
}

/** @return resource */
function tw_daily_open_regular_file(string $path, string $openMode, string $label, ?int $requiredMode = null) {
    $expected = tw_daily_assert_absent_or_regular($path, $label);
    $handle = fopen($path, $openMode);
    if ($handle === false) throw new RuntimeException($label . ' could not be opened safely.');
    try {
        clearstatcache(true, $path);
        $pathStat = lstat($path);
        $handleStat = fstat($handle);
        if (is_link($path)
            || !tw_daily_stat_is_regular($pathStat)
            || !tw_daily_stat_is_regular($handleStat)
            || !tw_daily_stats_match($pathStat, $handleStat)
            || ($expected !== null && !tw_daily_stats_match($expected, $handleStat))
        ) {
            throw new RuntimeException($label . ' changed before it could be secured.');
        }
        if ($requiredMode !== null) {
            if (!fchmod($handle, $requiredMode)) {
                throw new RuntimeException($label . ' permissions could not be restricted.');
            }
            clearstatcache(true, $path);
            $pathStat = lstat($path);
            $handleStat = fstat($handle);
            if (is_link($path)
                || !tw_daily_stats_match($pathStat, $handleStat)
                || !tw_daily_stat_is_regular($handleStat)
                || ((((int)($pathStat['mode'] ?? 0)) & 0777) !== $requiredMode)
                || ((((int)($handleStat['mode'] ?? 0)) & 0777) !== $requiredMode)
            ) {
                throw new RuntimeException($label . ' permissions could not be verified.');
            }
        }
        return $handle;
    } catch (Throwable $error) {
        fclose($handle);
        throw $error;
    }
}

function tw_private_dir(): string {
    if (PHP_SAPI === 'cli' && defined('TW_DAILY_TEST_PRIVATE_DIR')) {
        $testDir = (string)constant('TW_DAILY_TEST_PRIVATE_DIR');
        if ($testDir === '') throw new RuntimeException('Invalid Daily Desk test private directory.');
        return tw_daily_prepare_directory($testDir, 0700, 'Daily Desk test private directory', true, [0700]);
    }
    $home = dirname(__DIR__, 3);
    $dir = $home . '/site-private/trust-worthy';
    // The shared gateway deliberately uses 0750 on cPanel so the account's PHP
    // service group can traverse it; owner-only 0700 remains valid for tests or
    // installations that do not need that group boundary.
    return tw_daily_prepare_directory($dir, 0750, 'Trust-Worthy private storage', true, [0700, 0750]);
}

function tw_public_daily_data_dir(): string {
    if (PHP_SAPI === 'cli' && defined('TW_DAILY_TEST_PUBLIC_DIR')) {
        $testDir = (string)constant('TW_DAILY_TEST_PUBLIC_DIR');
        if ($testDir === '') throw new RuntimeException('Invalid Daily Desk test public directory.');
        return rtrim($testDir, '/');
    }
    return dirname(__DIR__) . '/daily-data';
}

function tw_require_method(string $required): void {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === $required) return;
    http_response_code(405);
    header('Allow: ' . $required);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $required . " required.\n";
    exit;
}

function tw_post_scalar(string $name, int $maxBytes): ?string {
    if (!array_key_exists($name, $_POST) || !is_string($_POST[$name])) return null;
    $value = $_POST[$name];
    if (strlen($value) > $maxBytes) return null;
    return $value;
}

function tw_daily_request_origin_is_allowed(): bool {
    $hostValue = strtolower(rtrim((string)($_SERVER['HTTP_HOST'] ?? ''), '.'));
    if (preg_match('/^((?:www\.)?bobsome1\.com)(?::443)?$/D', $hostValue, $hostMatch) !== 1) return false;
    $host = $hostMatch[1];
    $fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite !== '' && $fetchSite !== 'same-origin') return false;

    $validateUrl = static function (string $raw, bool $origin) use ($host): bool {
        $parts = parse_url($raw);
        if (!is_array($parts)) return false;
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
        return !$origin || (!isset($parts['query']) && in_array((string)($parts['path'] ?? ''), ['', '/'], true));
    };

    $trustedSignal = false;
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        if (!$validateUrl($origin, true)) return false;
        $trustedSignal = true;
    }
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer !== '') {
        if (!$validateUrl($referer, false)) return false;
        $trustedSignal = true;
    }
    return $trustedSignal || $fetchSite === 'same-origin';
}

function tw_daily_measure_raw_body(int $maxBodyBytes): int {
    $input = fopen('php://input', 'rb');
    if ($input === false) throw new RuntimeException('The request body could not be validated.');
    $total = 0;
    try {
        while (!feof($input)) {
            $remaining = $maxBodyBytes - $total;
            $chunk = fread($input, min(8192, $remaining + 1));
            if ($chunk === false || ($chunk === '' && !feof($input))) {
                throw new RuntimeException('The request body could not be validated.');
            }
            $total += strlen($chunk);
            if ($total > $maxBodyBytes) throw new LengthException('The submitted form is too large.');
        }
    } finally {
        fclose($input);
    }
    return $total;
}

function tw_require_same_origin_form_post(int $maxBodyBytes): void {
    tw_require_method('POST');
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
    if ($contentType !== 'application/x-www-form-urlencoded' || trim((string)($_SERVER['HTTP_CONTENT_ENCODING'] ?? '')) !== '') {
        http_response_code(415);
        header('Content-Type: text/plain; charset=UTF-8');
        exit("Form-encoded, uncompressed POST required.\n");
    }
    $lengthRaw = (string)($_SERVER['CONTENT_LENGTH'] ?? '');
    if (!preg_match('/^\d+$/D', $lengthRaw)) {
        http_response_code(411);
        header('Content-Type: text/plain; charset=UTF-8');
        exit("A valid Content-Length is required.\n");
    }
    if ((int)$lengthRaw > $maxBodyBytes) {
        http_response_code(413);
        header('Content-Type: text/plain; charset=UTF-8');
        exit("The submitted form is too large.\n");
    }
    if (!tw_daily_request_origin_is_allowed()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit("Request origin was not accepted.\n");
    }
    try {
        $actualBytes = tw_daily_measure_raw_body($maxBodyBytes);
    } catch (LengthException) {
        http_response_code(413);
        header('Content-Type: text/plain; charset=UTF-8');
        exit("The submitted form is too large.\n");
    } catch (Throwable) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        exit("The request body could not be validated.\n");
    }
    if ($actualBytes !== (int)$lengthRaw) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        exit("The request body length did not match its declaration.\n");
    }
}

function tw_admin_private_headers(?string $scriptNonce = null): void {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    $script = $scriptNonce === null ? "'none'" : "'nonce-" . $scriptNonce . "'";
    header("Content-Security-Policy: default-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'; object-src 'none'; img-src 'self' data:; style-src 'self'; script-src {$script}");
}

function tw_json_read_strict(
    string $path,
    array $fallback = [],
    int $maxBytes = 8388608,
    ?int $requiredMode = null
): array {
    if ($maxBytes < 1 || $maxBytes > 33554432) throw new InvalidArgumentException('Invalid JSON read limit.');
    if (!tw_daily_path_exists($path)) return $fallback;
    $label = basename($path);
    $handle = tw_daily_open_regular_file($path, 'rb', $label, $requiredMode);
    $locked = false;
    try {
        if (!flock($handle, LOCK_SH)) throw new RuntimeException('Unable to lock ' . $label . ' for reading.');
        $locked = true;
        $before = fstat($handle);
        $size = is_array($before) ? ($before['size'] ?? null) : null;
        if (!is_int($size) || $size < 0 || $size > $maxBytes) {
            throw new RuntimeException($label . ' exceeds its safe read limit.');
        }
        $raw = stream_get_contents($handle, $maxBytes + 1);
        $after = fstat($handle);
        clearstatcache(true, $path);
        $pathAfter = lstat($path);
        if (!is_string($raw)
            || strlen($raw) !== $size
            || !tw_daily_stats_match($before, $after)
            || !tw_daily_stats_match($after, $pathAfter)
            || !tw_daily_stat_is_regular($after)
            || (($after['size'] ?? null) !== $size)
            || (($pathAfter['size'] ?? null) !== $size)
            || ($requiredMode !== null && ((((int)($after['mode'] ?? 0)) & 0777) !== $requiredMode))
            || ($requiredMode !== null && ((((int)($pathAfter['mode'] ?? 0)) & 0777) !== $requiredMode))
        ) {
            throw new RuntimeException('Unable to read ' . $label . ' safely.');
        }
    } finally {
        if ($locked) flock($handle, LOCK_UN);
        fclose($handle);
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Refusing to replace invalid ' . basename($path) . '.', 0, $e);
    }
    if (!is_array($data)) throw new RuntimeException('Invalid data structure in ' . basename($path) . '.');
    return $data;
}

function tw_private_json_read_strict(string $path, array $fallback = [], int $maxBytes = 8388608): array {
    return tw_json_read_strict($path, $fallback, $maxBytes, 0600);
}

function tw_json_write_mode(string $path, array $data, int $mode): void {
    tw_daily_assert_absent_or_regular($path, basename($path));
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    try {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    } catch (JsonException $e) {
        throw new RuntimeException('Unable to encode ' . basename($path), 0, $e);
    }
    $handle = tw_daily_open_regular_file($tmp, 'x+b', basename($tmp), $mode);
    // Restrict the empty temporary file before any private or secret bytes are written.
    $ok = flock($handle, LOCK_EX);
    $offset = 0;
    $length = strlen($json);
    while ($ok && $offset < $length) {
        $written = fwrite($handle, substr($json, $offset));
        if ($written === false || $written === 0) {
            $ok = false;
            break;
        }
        $offset += $written;
    }
    if ($ok) $ok = fflush($handle);
    if ($ok && function_exists('fsync')) $ok = fsync($handle);
    $tmpStat = fstat($handle);
    if ($ok) {
        clearstatcache(true, $tmp);
        $ok = tw_daily_stats_match($tmpStat, lstat($tmp));
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    if ($ok) {
        try {
            tw_daily_assert_absent_or_regular($path, basename($path));
        } catch (Throwable $error) {
            @unlink($tmp);
            throw $error;
        }
    }
    if (!$ok || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to write ' . basename($path));
    }
    clearstatcache(true, $path);
    $writtenStat = lstat($path);
    if (!tw_daily_stats_match($tmpStat, $writtenStat)
        || !tw_daily_stat_is_regular($writtenStat)
        || ((((int)($writtenStat['mode'] ?? 0)) & 0777) !== $mode)
    ) {
        throw new RuntimeException('Unable to verify ' . basename($path) . ' after replacement.');
    }
}

function tw_json_write(string $path, array $data): void {
    tw_json_write_mode($path, $data, 0600);
}

function tw_json_write_public(string $path, array $data): void {
    tw_json_write_mode($path, $data, 0644);
}

function tw_with_private_lock(string $name, callable $callback): mixed {
    if (!preg_match('/^[a-z0-9-]{1,64}$/', $name)) throw new InvalidArgumentException('Invalid lock name.');
    $dir = tw_private_dir() . '/locks';
    tw_daily_prepare_directory($dir, 0700, 'Private lock storage', true, [0700]);
    $path = $dir . '/' . $name . '.lock';
    $handle = tw_daily_open_regular_file($path, 'c+b', 'Private lock', 0600);
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to acquire private lock.');
    }
    try {
        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function tw_admin_bootstrap_validate_ledger(array $state, int $now): array {
    if (array_keys($state) !== ['bootstraps']
        || !is_array($state['bootstraps'])
        || count($state['bootstraps']) > 8
    ) {
        throw new RuntimeException('Daily admin bootstrap ledger is corrupted; original was preserved.');
    }
    foreach ($state['bootstraps'] as $digest => $record) {
        if (!is_string($digest)
            || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1
            || !is_array($record)
            || array_keys($record) !== ['created_at_utc', 'expires_at']
        ) {
            throw new RuntimeException('Daily admin bootstrap ledger is corrupted; original was preserved.');
        }
        $created = tw_daily_parse_utc_timestamp($record['created_at_utc'] ?? null);
        $expires = $record['expires_at'] ?? null;
        if (!is_int($created)
            || !is_int($expires)
            || $created <= 0
            || $created > $now + 300
            || $expires <= $created
            || $expires > $created + 1800
            || $expires > $now + 1800
        ) {
            throw new RuntimeException('Daily admin bootstrap ledger is corrupted; original was preserved.');
        }
    }
    return $state['bootstraps'];
}

function tw_admin_bootstrap_create(int $ttlSeconds = 600): string {
    $plain = bin2hex(random_bytes(32));
    $digest = hash('sha256', $plain);
    $now = time();
    $path = tw_private_dir() . '/daily-admin-bootstraps.json';
    tw_with_private_lock('daily-admin-bootstrap', static function () use ($path, $digest, $now, $ttlSeconds): void {
        $state = tw_private_json_read_strict($path, ['bootstraps' => []], 1048576);
        $bootstraps = tw_admin_bootstrap_validate_ledger($state, $now);
        $active = [];
        foreach ($bootstraps as $hash => $record) {
            if ($record['expires_at'] >= $now) $active[$hash] = $record;
        }
        $active[$digest] = [
            'created_at_utc' => gmdate('c', $now),
            'expires_at' => $now + max(60, min(1800, $ttlSeconds)),
        ];
        if (count($active) > 8) $active = array_slice($active, -8, null, true);
        tw_json_write($path, ['bootstraps' => $active]);
    });
    return $plain;
}

function tw_admin_bootstrap_consume(string $plain): bool {
    if (!preg_match('/^[a-f0-9]{64}$/', $plain)) return false;
    $digest = hash('sha256', $plain);
    $now = time();
    $path = tw_private_dir() . '/daily-admin-bootstraps.json';
    return tw_with_private_lock('daily-admin-bootstrap', static function () use ($path, $digest, $now): bool {
        $state = tw_private_json_read_strict($path, ['bootstraps' => []], 1048576);
        $bootstraps = tw_admin_bootstrap_validate_ledger($state, $now);
        $active = [];
        $accepted = false;
        foreach ($bootstraps as $hash => $record) {
            $expires = $record['expires_at'];
            $matches = hash_equals($hash, $digest);
            if ($matches && $expires >= $now) $accepted = true;
            if (!$matches && $expires >= $now) $active[$hash] = $record;
        }
        // Successful codes are one-use; expired codes are purged on every attempt.
        tw_json_write($path, ['bootstraps' => $active]);
        return $accepted;
    });
}

function tw_admin_session_start(bool $allowCreate = false): bool {
    if (session_status() === PHP_SESSION_ACTIVE) return true;
    $name = 'TW_DAILY_ADMIN';
    $sessionCookie = is_string($_COOKIE[$name] ?? null) ? trim($_COOKIE[$name]) : '';
    if (!$allowCreate && ($sessionCookie === '' || !preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $sessionCookie))) return false;
    $dir = tw_private_dir() . '/sessions';
    tw_daily_prepare_directory($dir, 0700, 'Private session storage', true, [0700]);
    if (!is_writable($dir)) throw new RuntimeException('Private session storage is not writable.');
    session_name($name);
    session_save_path($dir);
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', '7200');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/truth/daily/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (!session_start()) throw new RuntimeException('Unable to start the private editor session.');
    return true;
}

function tw_admin_login(): void {
    tw_admin_session_start(true);
    if (!session_regenerate_id(true)) throw new RuntimeException('Unable to secure the private editor session.');
    $_SESSION = [
        'tw_daily_admin' => true,
        'created_at' => time(),
        'last_seen_at' => time(),
        'csrf' => bin2hex(random_bytes(32)),
    ];
}

function tw_admin_is_authenticated(): bool {
    if (!tw_admin_session_start(false)) return false;
    $now = time();
    $lastSeen = is_int($_SESSION['last_seen_at'] ?? null) ? $_SESSION['last_seen_at'] : 0;
    $created = is_int($_SESSION['created_at'] ?? null) ? $_SESSION['created_at'] : 0;
    if (($_SESSION['tw_daily_admin'] ?? false) !== true || $lastSeen < $now - 7200 || $created < $now - 28800) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        return false;
    }
    $_SESSION['last_seen_at'] = $now;
    return true;
}

function tw_require_admin(): void {
    if (tw_admin_is_authenticated()) return;
    tw_admin_private_headers();
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Private Trust-Worthy desk.\nRun the CLI admin-url helper on the server to obtain a one-use sign-in URL.\n";
    exit;
}

function tw_admin_csrf_token(): string {
    $token = is_string($_SESSION['csrf'] ?? null) ? $_SESSION['csrf'] : '';
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf'] = $token;
    }
    return $token;
}

function tw_require_admin_csrf(): void {
    $provided = tw_post_scalar('csrf', 64) ?? '';
    $expected = is_string($_SESSION['csrf'] ?? null) ? $_SESSION['csrf'] : '';
    if ($provided !== '' && $expected !== '' && hash_equals($expected, $provided)) return;
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "The editor session check failed. Reload the desk and try again.\n";
    exit;
}

function tw_admin_flash_set(string $type, string $message): void {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION['tw_daily_flash'] = [
        'type' => preg_match('/^(?:success|error|notice)$/', $type) ? $type : 'notice',
        'message' => tw_clean_text($message, 600),
    ];
}

function tw_admin_flash_take(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) return [];
    $flash = $_SESSION['tw_daily_flash'] ?? [];
    unset($_SESSION['tw_daily_flash']);
    return is_array($flash) ? $flash : [];
}

function tw_daily_ai_limits(array $config): array {
    return [
        'max_calls_per_hour' => max(1, min(10, (int)($config['max_calls_per_hour'] ?? 2))),
        'max_calls_per_day' => max(1, min(30, (int)($config['max_calls_per_day'] ?? 6))),
        'max_concurrent' => max(1, min(2, (int)($config['max_concurrent'] ?? 1))),
        'max_output_tokens' => max(1200, min(6000, (int)($config['max_output_tokens'] ?? 4000))),
        'max_web_search_calls' => max(1, min(4, (int)($config['max_web_search_calls'] ?? 2))),
        'lease_seconds' => max(190, min(600, (int)($config['lease_seconds'] ?? 240))),
    ];
}

function tw_daily_ai_validate_ledger(array $state, int $now): void {
    $corrupt = static function (): void {
        throw new RuntimeException('Research budget ledger is corrupted; refusing to replace it.');
    };

    if (array_key_exists('version', $state) && $state['version'] !== 1) $corrupt();
    if (tw_daily_parse_utc_timestamp($state['updated_at_utc'] ?? null) === null) $corrupt();

    $storedLimits = $state['limits'] ?? null;
    if (!is_array($storedLimits)) $corrupt();
    $limitRanges = [
        'max_calls_per_hour' => [1, 10],
        'max_calls_per_day' => [1, 30],
        'max_concurrent' => [1, 2],
    ];
    if (count($storedLimits) !== count($limitRanges)) $corrupt();
    foreach ($limitRanges as $name => [$minimum, $maximum]) {
        $value = $storedLimits[$name] ?? null;
        if (!is_int($value) || $value < $minimum || $value > $maximum) $corrupt();
    }

    if (!array_key_exists('reservations', $state) || !is_array($state['reservations'])) $corrupt();
    foreach ($state['reservations'] as $reservationId => $record) {
        if (!is_string($reservationId) || !preg_match('/^[a-f0-9]{32}$/D', $reservationId) || !is_array($record)) $corrupt();

        $started = $record['started_at'] ?? null;
        $startedUtc = tw_daily_parse_utc_timestamp($record['started_at_utc'] ?? null);
        if (!is_int($started)
            || $started <= 0
            || $started > $now + 300
            || !is_int($startedUtc)
            || abs($startedUtc - $started) > 300
        ) {
            $corrupt();
        }

        $status = $record['status'] ?? null;
        if (!is_string($status) || !in_array($status, ['active', 'completed', 'failed'], true)) $corrupt();
        if ($status === 'active') {
            $allowedKeys = ['started_at', 'started_at_utc', 'lease_expires_at', 'status'];
            if (count($record) !== count($allowedKeys) || array_diff(array_keys($record), $allowedKeys) !== []) $corrupt();
            $leaseExpires = $record['lease_expires_at'] ?? null;
            if (!is_int($leaseExpires) || $leaseExpires <= $started || $leaseExpires > $started + 3600) $corrupt();
            if (array_key_exists('finished_at_utc', $record) || array_key_exists('usage', $record)) $corrupt();
            continue;
        }

        $allowedKeys = ['started_at', 'started_at_utc', 'status', 'finished_at_utc', 'usage'];
        if (count($record) !== count($allowedKeys) || array_diff(array_keys($record), $allowedKeys) !== []) $corrupt();
        if (array_key_exists('lease_expires_at', $record)) $corrupt();
        $finished = tw_daily_parse_utc_timestamp($record['finished_at_utc'] ?? null);
        if (!is_int($finished) || $finished < $started - 300 || $finished > $now + 300) $corrupt();
        $recordUsage = $record['usage'] ?? null;
        if (!is_array($recordUsage)) $corrupt();
        foreach ($recordUsage as $name => $value) {
            if (!is_string($name)
                || !in_array($name, ['input_tokens', 'output_tokens', 'total_tokens'], true)
                || !is_int($value)
                || $value < 0
            ) {
                $corrupt();
            }
        }
    }
}

function tw_daily_ai_reserve(array $config): string {
    $limits = tw_daily_ai_limits($config);
    $now = time();
    $id = bin2hex(random_bytes(16));
    $path = tw_private_dir() . '/daily-ai-budget.json';
    return tw_with_private_lock('daily-ai-budget', static function () use ($limits, $now, $id, $path): string {
        if (tw_daily_path_exists($path)) {
            $state = tw_private_json_read_strict($path, [], 1048576);
            tw_daily_ai_validate_ledger($state, $now);
        } else {
            $state = [
                'version' => 1,
                'updated_at_utc' => gmdate('c', $now),
                'limits' => [
                    'max_calls_per_hour' => $limits['max_calls_per_hour'],
                    'max_calls_per_day' => $limits['max_calls_per_day'],
                    'max_concurrent' => $limits['max_concurrent'],
                ],
                'reservations' => [],
            ];
        }
        $reservations = [];
        foreach ($state['reservations'] as $reservationId => $record) {
            $started = $record['started_at'];
            if ($started >= $now - 172800) $reservations[$reservationId] = $record;
        }

        $hourly = 0;
        $daily = 0;
        $active = 0;
        $today = gmdate('Y-m-d', $now);
        foreach ($reservations as $record) {
            $started = $record['started_at'];
            if ($started >= $now - 3600) $hourly++;
            if ($started > 0 && gmdate('Y-m-d', $started) === $today) $daily++;
            if ($record['status'] === 'active' && $record['lease_expires_at'] >= $now) $active++;
        }
        if ($hourly >= $limits['max_calls_per_hour']) {
            throw new RuntimeException('The hourly Daily Desk research limit has been reached. Try again later.');
        }
        if ($daily >= $limits['max_calls_per_day']) {
            throw new RuntimeException('The Daily Desk research allowance has been reached for today.');
        }
        if ($active >= $limits['max_concurrent']) {
            throw new RuntimeException('A Daily Desk investigation is already running. Wait for it to finish.');
        }

        $reservations[$id] = [
            'started_at' => $now,
            'started_at_utc' => gmdate('c', $now),
            'lease_expires_at' => $now + $limits['lease_seconds'],
            'status' => 'active',
        ];
        tw_json_write($path, [
            'version' => 1,
            'updated_at_utc' => gmdate('c', $now),
            'limits' => [
                'max_calls_per_hour' => $limits['max_calls_per_hour'],
                'max_calls_per_day' => $limits['max_calls_per_day'],
                'max_concurrent' => $limits['max_concurrent'],
            ],
            'reservations' => $reservations,
        ]);
        return $id;
    });
}

function tw_daily_ai_finish(string $id, bool $succeeded, array $usage = []): void {
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) throw new InvalidArgumentException('Invalid research reservation.');
    $path = tw_private_dir() . '/daily-ai-budget.json';
    tw_with_private_lock('daily-ai-budget', static function () use ($path, $id, $succeeded, $usage): void {
        $state = tw_private_json_read_strict($path, [], 1048576);
        tw_daily_ai_validate_ledger($state, time());
        $reservations = $state['reservations'];
        if (!isset($reservations[$id]) || $reservations[$id]['status'] !== 'active') {
            throw new RuntimeException('Research budget reservation could not be reconciled.');
        }
        $safeUsage = [];
        foreach (['input_tokens', 'output_tokens', 'total_tokens'] as $name) {
            $value = $usage[$name] ?? null;
            if (is_int($value) && $value >= 0 && $value <= 1000000000) $safeUsage[$name] = $value;
        }
        $reservations[$id]['status'] = $succeeded ? 'completed' : 'failed';
        $reservations[$id]['finished_at_utc'] = gmdate('c');
        $reservations[$id]['usage'] = $safeUsage;
        unset($reservations[$id]['lease_expires_at']);
        $state['updated_at_utc'] = gmdate('c');
        $state['reservations'] = $reservations;
        tw_json_write($path, $state);
    });
}

function tw_daily_draft_digest(array $draft): string {
    try {
        $json = json_encode($draft, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Unable to bind the research draft.', 0, $e);
    }
    return hash('sha256', $json);
}

function tw_daily_draft_path(string $id): string {
    if (!preg_match('/^[a-f0-9]{32}$/', $id)) throw new InvalidArgumentException('Invalid draft identifier.');
    return tw_private_dir() . '/daily-drafts/' . $id . '.json';
}

function tw_daily_intake_retention_days(): int {
    $raw = getenv('TW_INTAKE_RETENTION_DAYS');
    if (!is_string($raw) || !preg_match('/^\d+$/D', $raw)) return 180;
    $days = (int)$raw;
    return $days >= 30 && $days <= 180 ? $days : 180;
}

function tw_daily_parse_utc_timestamp(mixed $value): ?int {
    if (!is_string($value)
        || !preg_match(
            '/^(\d{4})-(\d{2})-(\d{2})T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,6})?(?:Z|\+00:00)$/D',
            trim($value),
            $parts
        )
        || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])
    ) {
        return null;
    }
    $timestamp = strtotime(trim($value));
    return is_int($timestamp) ? $timestamp : null;
}

function tw_daily_candidate_is_private_question(array $candidate): bool {
    $source = is_string($candidate['source'] ?? null) ? $candidate['source'] : '';
    return ($candidate['visibility'] ?? '') === 'private'
        || ($candidate['candidate_type'] ?? '') === 'user-question'
        || strcasecmp($source, 'Private user question') === 0;
}

function tw_daily_private_candidate_delete_at(array $candidate, ?int $now = null): ?int {
    if (!tw_daily_candidate_is_private_question($candidate)) return null;
    $now ??= time();
    $submitted = $candidate['timestamp'] ?? null;
    if (!is_int($submitted) || $submitted <= 0 || $submitted > $now) return null;

    $retentionCap = $submitted + (tw_daily_intake_retention_days() * 86400);
    if (!array_key_exists('delete_after_utc', $candidate)) return $retentionCap;
    $deleteAt = tw_daily_parse_utc_timestamp($candidate['delete_after_utc']);
    if (!is_int($deleteAt) || $deleteAt <= $submitted) return null;
    return min($deleteAt, $retentionCap);
}

function tw_daily_private_candidate_is_current(array $candidate, ?int $now = null): bool {
    if (!tw_daily_candidate_is_private_question($candidate)) return true;
    $now ??= time();
    $deleteAt = tw_daily_private_candidate_delete_at($candidate, $now);
    return is_int($deleteAt) && $deleteAt > $now;
}

function tw_daily_draft_is_private_question(array $draft): bool {
    $candidate = $draft['_meta']['candidate'] ?? [];
    return !is_array($candidate) || tw_daily_candidate_is_private_question($candidate);
}

function tw_prune_expired_private_daily_drafts(?int $now = null): int {
    $now ??= time();
    $dir = tw_private_dir() . '/daily-drafts';
    if (!tw_daily_path_exists($dir)) return 0;
    tw_daily_prepare_directory($dir, 0700, 'Private draft storage', false, [0700]);
    $deletedIds = [];
    $names = scandir($dir);
    if ($names === false) throw new RuntimeException('Private draft storage could not be scanned.');
    foreach ($names as $name) {
        if (!preg_match('/^([a-f0-9]{32})\.json$/D', $name, $match)) continue;
        $path = $dir . '/' . $name;
        $draft = tw_private_json_read_strict($path, [], 1048576);
        if (!tw_daily_draft_is_private_question($draft)) continue;
        $candidate = $draft['_meta']['candidate'] ?? null;
        if (is_array($candidate) && tw_daily_private_candidate_is_current($candidate, $now)) continue;
        if (!unlink($path)) throw new RuntimeException('Expired private draft could not be removed.');
        $deletedIds[] = $match[1];
    }

    $pointerPath = tw_private_dir() . '/daily-current-draft.json';
    if ($deletedIds !== [] && tw_daily_path_exists($pointerPath)) {
        $pointer = tw_private_json_read_strict($pointerPath, [], 65536);
        if (in_array($pointer['draft_id'] ?? null, $deletedIds, true)) {
            tw_json_write($pointerPath, ['draft_id' => '', 'updated_at_utc' => gmdate('c', $now)]);
        }
    }
    return count($deletedIds);
}

function tw_store_daily_draft(array $draft): array {
    tw_prune_expired_private_daily_drafts();
    $id = bin2hex(random_bytes(16));
    $dir = tw_private_dir() . '/daily-drafts';
    tw_daily_prepare_directory($dir, 0700, 'Private draft storage', true, [0700]);
    $draft['_meta'] = is_array($draft['_meta'] ?? null) ? $draft['_meta'] : [];
    if (tw_daily_draft_is_private_question($draft)) {
        $candidate = $draft['_meta']['candidate'] ?? null;
        if (!is_array($candidate) || !tw_daily_private_candidate_is_current($candidate)) {
            throw new RuntimeException('The private question expired before its research draft could be stored.');
        }
        $deleteAt = tw_daily_private_candidate_delete_at($candidate);
        if (!is_int($deleteAt)) throw new RuntimeException('The private question retention deadline is invalid.');
        $candidate['delete_after_utc'] = gmdate('c', $deleteAt);
        $draft['_meta']['candidate'] = $candidate;
        $draft['_meta']['delete_after_utc'] = gmdate('c', $deleteAt);
    }
    $draft['_meta']['draft_id'] = $id;
    $draft['_meta']['human_review_required'] = true;
    $draft['_meta']['publication_status'] = 'private-draft';
    tw_json_write(tw_daily_draft_path($id), $draft);
    tw_json_write(tw_private_dir() . '/daily-current-draft.json', [
        'draft_id' => $id,
        'updated_at_utc' => gmdate('c'),
    ]);
    return $draft;
}

function tw_load_daily_draft(string $id): array {
    $draft = tw_private_json_read_strict(tw_daily_draft_path($id), [], 1048576);
    $storedId = is_string($draft['_meta']['draft_id'] ?? null) ? $draft['_meta']['draft_id'] : '';
    if ($draft === [] || !hash_equals($id, $storedId)) {
        throw new RuntimeException('The selected research draft is unavailable.');
    }
    if (tw_daily_draft_is_private_question($draft)) {
        $candidate = $draft['_meta']['candidate'] ?? null;
        if (!is_array($candidate) || !tw_daily_private_candidate_is_current($candidate)) {
            if (!unlink(tw_daily_draft_path($id))) {
                throw new RuntimeException('The expired private research draft could not be removed.');
            }
            $pointerPath = tw_private_dir() . '/daily-current-draft.json';
            if (tw_daily_path_exists($pointerPath)) {
                $pointer = tw_private_json_read_strict($pointerPath, [], 65536);
                if (($pointer['draft_id'] ?? null) === $id) {
                    tw_json_write($pointerPath, ['draft_id' => '', 'updated_at_utc' => gmdate('c')]);
                }
            }
            throw new RuntimeException('The selected private research draft has expired.');
        }
    }
    return $draft;
}

function tw_load_current_daily_draft(): array {
    $pointer = tw_private_json_read_strict(tw_private_dir() . '/daily-current-draft.json', [], 65536);
    $id = is_string($pointer['draft_id'] ?? null) ? $pointer['draft_id'] : '';
    if ($id === '') return [];
    return tw_load_daily_draft($id);
}

function tw_mark_daily_draft_published(array $draft, string $publicationId): void {
    $id = is_string($draft['_meta']['draft_id'] ?? null) ? $draft['_meta']['draft_id'] : '';
    $draft['_meta']['publication_status'] = 'published';
    $draft['_meta']['published_at_utc'] = gmdate('c');
    $draft['_meta']['publication_id'] = $publicationId;
    unset($draft['_meta']['pending_publication_id'], $draft['_meta']['pending_draft_digest']);
    tw_json_write(tw_daily_draft_path($id), $draft);
}

function tw_assert_public_daily_trial(array $published, string $publicationId): void {
    $allowed = [
        'headline', 'claim', 'summary', 'proven', 'strongly_indicated',
        'contradictions_missing_pieces', 'motives_incentives_who_benefits',
        'logic_common_sense', 'counterevidence_alternatives', 'unknowns',
        'bobinated_opinion', 'what_would_change_the_finding', 'confidence',
        'sources', '_meta',
    ];
    if (array_diff(array_keys($published), $allowed) !== []) {
        throw new RuntimeException('Pending publication contains a non-public field.');
    }
    foreach (['headline', 'claim', 'summary'] as $name) {
        if (!is_string($published[$name] ?? null) || trim($published[$name]) === '') {
            throw new RuntimeException('Pending publication is missing required public text.');
        }
    }
    if (!is_array($published['sources'] ?? null) || count($published['sources']) < 2) {
        throw new RuntimeException('Pending publication is missing its public source trail.');
    }
    $meta = $published['_meta'] ?? null;
    if (!is_array($meta)
        || array_diff(array_keys($meta), ['publication_id', 'published_at_utc', 'reviewed_by_human']) !== []
        || ($meta['publication_id'] ?? null) !== $publicationId
        || ($meta['reviewed_by_human'] ?? null) !== true
        || !is_string($meta['published_at_utc'] ?? null)) {
        throw new RuntimeException('Pending publication contains unsafe or incomplete public metadata.');
    }
}

function tw_apply_daily_publication_transaction(array $transaction, ?callable $draftMarker = null): array {
    $draftId = is_string($transaction['draft_id'] ?? null) ? $transaction['draft_id'] : '';
    $originalDigest = is_string($transaction['original_draft_digest'] ?? null) ? $transaction['original_draft_digest'] : '';
    $publicationId = is_string($transaction['publication_id'] ?? null) ? $transaction['publication_id'] : '';
    $archiveName = is_string($transaction['archive_name'] ?? null) ? $transaction['archive_name'] : '';
    $published = $transaction['published'] ?? null;
    if (!preg_match('/^[a-f0-9]{32}$/', $draftId)
        || !preg_match('/^[a-f0-9]{64}$/', $originalDigest)
        || !preg_match('/^[a-f0-9]{24}$/', $publicationId)
        || !preg_match('/^trial-\d{8}-\d{6}-[a-f0-9]{24}\.json$/', $archiveName)
        || !is_array($published)
        || ($published['_meta']['publication_id'] ?? '') !== $publicationId) {
        throw new RuntimeException('Invalid pending Daily Trial publication transaction.');
    }
    tw_assert_public_daily_trial($published, $publicationId);

    $draft = tw_load_daily_draft($draftId);
    if (tw_daily_draft_is_private_question($draft)) {
        throw new RuntimeException('Private user-question research cannot enter a public publication transaction.');
    }
    $providerEvidence = [];
    foreach (($draft['_meta']['provider_sources'] ?? []) as $providerSource) {
        if (!is_array($providerSource) || !is_string($providerSource['url'] ?? null)) continue;
        $key = tw_provider_url_key($providerSource['url']);
        if ($key !== null) $providerEvidence[$key] = true;
    }
    $boundPublicSources = [];
    foreach ($published['sources'] as $source) {
        if (!is_array($source)
            || !is_string($source['url'] ?? null)
            || !is_string($source['title'] ?? null)
            || trim($source['title']) === ''
            || !is_string($source['role'] ?? null)
            || trim($source['role']) === '') {
            throw new RuntimeException('Pending publication contains an invalid public source.');
        }
        $key = tw_provider_url_key($source['url']);
        if ($key === null || !isset($providerEvidence[$key])) {
            throw new RuntimeException('Pending publication contains a source not bound to provider evidence.');
        }
        $boundPublicSources[$key] = true;
    }
    if (count($boundPublicSources) < 2) throw new RuntimeException('Pending publication needs two distinct provider-bound sources.');
    $status = is_string($draft['_meta']['publication_status'] ?? null) ? $draft['_meta']['publication_status'] : '';
    if ($status === 'private-draft') {
        if (!hash_equals(tw_daily_draft_digest($draft), $originalDigest)) {
            throw new RuntimeException('Pending publication no longer matches its private draft.');
        }
        $draft['_meta']['publication_status'] = 'publishing';
        $draft['_meta']['pending_publication_id'] = $publicationId;
        $draft['_meta']['pending_draft_digest'] = $originalDigest;
        tw_json_write(tw_daily_draft_path($draftId), $draft);
        $status = 'publishing';
    }
    if ($status === 'publishing') {
        if (($draft['_meta']['pending_publication_id'] ?? '') !== $publicationId
            || ($draft['_meta']['pending_draft_digest'] ?? '') !== $originalDigest) {
            throw new RuntimeException('Private draft is bound to a different pending publication.');
        }
    } elseif ($status !== 'published' || ($draft['_meta']['publication_id'] ?? '') !== $publicationId) {
        throw new RuntimeException('Private draft publication state is inconsistent.');
    }

    $dataDir = tw_public_daily_data_dir();
    tw_daily_prepare_directory($dataDir, 0755, 'Public Daily Trial storage', true, [0755]);
    if (!is_writable($dataDir)) throw new RuntimeException('Public Daily Trial storage is not writable.');
    $archive = $dataDir . '/' . $archiveName;
    if (tw_daily_path_exists($archive)) {
        $existing = tw_json_read_strict($archive, [], 1048576, 0644);
        if (!hash_equals(tw_daily_draft_digest($existing), tw_daily_draft_digest($published))) {
            throw new RuntimeException('Existing Daily Trial archive does not match the pending publication.');
        }
    } else {
        tw_json_write_public($archive, $published);
    }

    // latest.json is replaced atomically. If the subsequent private-state write
    // fails, the journal remains and this same transaction is safely replayable.
    tw_json_write_public($dataDir . '/latest.json', $published);
    if ($status !== 'published') {
        if ($draftMarker !== null) {
            $draftMarker($draft, $publicationId);
        } else {
            tw_mark_daily_draft_published($draft, $publicationId);
        }
    }

    $pointerPath = tw_private_dir() . '/daily-current-draft.json';
    $pointer = tw_private_json_read_strict($pointerPath, [], 65536);
    if (($pointer['draft_id'] ?? '') === $draftId) {
        tw_json_write($pointerPath, ['draft_id' => '', 'updated_at_utc' => gmdate('c')]);
    }
    return ['publication_id' => $publicationId, 'archive' => $archiveName];
}

function tw_recover_daily_publication_transaction(?callable $draftMarker = null): ?array {
    $path = tw_private_dir() . '/daily-publish-transaction.json';
    if (!tw_daily_path_exists($path)) return null;
    $transaction = tw_private_json_read_strict($path, [], 2097152);
    $result = tw_apply_daily_publication_transaction($transaction, $draftMarker);
    if (!unlink($path)) throw new RuntimeException('Completed publication journal could not be cleared.');
    return $transaction + ['result' => $result];
}

function tw_clean_text(string $text, int $limit): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return mb_substr($text, 0, $limit);
}

function tw_daily_normalize_openai_key(string $key): string {
    $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
    $key = trim($key);
    if (preg_match('/^(?:TRUST_WORTHY_OPENAI_API_KEY|OPENAI_API_KEY|INSIDE_OF_ME_OPENAI_API_KEY)\s*=\s*(.+)$/is', $key, $match)) {
        $key = trim($match[1]);
    }
    $key = preg_replace('/^Bearer\s+/i', '', $key) ?? $key;
    return trim($key, " \t\n\r\0\x0B\"'");
}

function tw_openai_key(): string {
    $envKey = '';
    foreach (['TRUST_WORTHY_OPENAI_API_KEY', 'OPENAI_API_KEY', 'INSIDE_OF_ME_OPENAI_API_KEY'] as $name) {
        $raw = getenv($name);
        if (!is_string($raw)) continue;
        $candidate = tw_daily_normalize_openai_key($raw);
        if ($candidate === '') continue;
        if (strlen($candidate) > 8192) throw new RuntimeException('Configured OpenAI key exceeds the safe size limit.');
        $envKey = $candidate;
        break;
    }

    // A present fallback file is validated even when an environment key wins. An unsafe
    // object at the canonical secret path is a tamper signal, not something to ignore.
    $privatePath = tw_private_dir() . '/openai-key.txt';
    if (!file_exists($privatePath) && !is_link($privatePath)) return $envKey;
    clearstatcache(true, $privatePath);
    $pathBefore = lstat($privatePath);
    if (is_link($privatePath)
        || !is_array($pathBefore)
        || (((int)($pathBefore['mode'] ?? 0)) & 0170000) !== 0100000
        || !is_file($privatePath)
    ) {
        throw new RuntimeException('OpenAI key path is not a secure regular file.');
    }
    $sameFile = static function (mixed $left, mixed $right): bool {
        return is_array($left)
            && is_array($right)
            && is_int($left['dev'] ?? null)
            && is_int($left['ino'] ?? null)
            && ($left['dev'] === ($right['dev'] ?? null))
            && ($left['ino'] === ($right['ino'] ?? null));
    };
    $secureRegular = static function (mixed $stat): bool {
        return is_array($stat)
            && ((((int)($stat['mode'] ?? 0)) & 0170000) === 0100000)
            && ((((int)($stat['mode'] ?? 0)) & 0777) === 0600);
    };

    $handle = fopen($privatePath, 'rb');
    if ($handle === false) throw new RuntimeException('OpenAI key file could not be opened safely.');
    $locked = false;
    try {
        $opened = fstat($handle);
        if (!$sameFile($pathBefore, $opened)
            || !is_array($opened)
            || ((((int)($opened['mode'] ?? 0)) & 0170000) !== 0100000)
        ) {
            throw new RuntimeException('OpenAI key file changed before it could be secured.');
        }
        if (!flock($handle, LOCK_SH)) throw new RuntimeException('OpenAI key file could not be locked safely.');
        $locked = true;
        if (!fchmod($handle, 0600)) {
            throw new RuntimeException('OpenAI key permissions could not be restricted to owner-only access.');
        }

        $securedHandle = fstat($handle);
        clearstatcache(true, $privatePath);
        $securedPath = lstat($privatePath);
        if (!$sameFile($pathBefore, $securedHandle)
            || !$sameFile($securedHandle, $securedPath)
            || !$secureRegular($securedHandle)
            || !$secureRegular($securedPath)
        ) {
            throw new RuntimeException('OpenAI key file security could not be verified.');
        }
        $size = $securedHandle['size'] ?? null;
        if (!is_int($size) || $size < 1 || $size > 8192 || ($securedPath['size'] ?? null) !== $size) {
            throw new RuntimeException('OpenAI key file has an invalid size.');
        }
        if ($envKey !== '') return $envKey;

        $rawKey = stream_get_contents($handle, 8193);
        $afterHandle = fstat($handle);
        clearstatcache(true, $privatePath);
        $afterPath = lstat($privatePath);
        if (!is_string($rawKey)
            || strlen($rawKey) !== $size
            || !$sameFile($securedHandle, $afterHandle)
            || !$sameFile($afterHandle, $afterPath)
            || !$secureRegular($afterHandle)
            || !$secureRegular($afterPath)
            || ($afterHandle['size'] ?? null) !== $size
            || ($afterPath['size'] ?? null) !== $size
        ) {
            throw new RuntimeException('OpenAI key file changed or could not be read safely.');
        }
    } finally {
        if ($locked) flock($handle, LOCK_UN);
        fclose($handle);
    }
    return tw_daily_normalize_openai_key($rawKey);
}

function tw_extract_response_text(array $node): ?string {
    if (isset($node['output_text']) && is_string($node['output_text']) && trim($node['output_text']) !== '') return $node['output_text'];
    if (isset($node['text']) && is_string($node['text']) && trim($node['text']) !== '') return $node['text'];
    foreach ($node as $value) {
        if (is_array($value)) {
            $found = tw_extract_response_text($value);
            if ($found !== null) return $found;
        }
    }
    return null;
}

function tw_trim_json_fence(string $text): string {
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
    $text = preg_replace('/\s*```$/', '', $text) ?? $text;
    return trim($text);
}

function tw_count_response_type(array $node, string $type): int {
    $count = (($node['type'] ?? null) === $type) ? 1 : 0;
    foreach ($node as $value) {
        if (is_array($value)) $count += tw_count_response_type($value, $type);
    }
    return $count;
}

function tw_provider_url_key(string $url): ?string {
    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
    $parts = parse_url($url);
    if (!is_array($parts)) return null;
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) return null;
    $literalHost = trim($host, '[]');
    if ($literalHost === 'localhost'
        || !str_contains($literalHost, '.')
        || preg_match('/(?:^|\.)(?:localhost|local|internal|home|lan|test|invalid|example)$/i', $literalHost)) {
        return null;
    }
    if (filter_var($literalHost, FILTER_VALIDATE_IP) !== false
        && filter_var($literalHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return null;
    }
    $port = isset($parts['port']) ? (int)$parts['port'] : null;
    if ($port !== null && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) return null;
    $portPart = ($port !== null && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) ? ':' . $port : '';
    $path = (string)($parts['path'] ?? '/');
    if ($path === '') $path = '/';
    if ($path !== '/') $path = rtrim($path, '/');
    $queryPart = '';
    if (isset($parts['query']) && $parts['query'] !== '') {
        parse_str((string)$parts['query'], $query);
        if (is_array($query)) {
            foreach (array_keys($query) as $name) {
                if (preg_match('/^(?:utm_.+|fbclid|gclid|mc_cid|mc_eid)$/i', (string)$name)) unset($query[$name]);
            }
            ksort($query);
            if ($query !== []) $queryPart = '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
    }
    return $scheme . '://' . $host . $portPart . $path . $queryPart;
}

function tw_provider_evidence_sources(array $response): array {
    $sources = [];
    $visit = static function (array $node) use (&$visit, &$sources): void {
        if (($node['type'] ?? '') === 'web_search_call' && is_array($node['action'] ?? null)) {
            $actionSources = $node['action']['sources'] ?? [];
            if (!is_array($actionSources)) $actionSources = [];
            foreach ($actionSources as $source) {
                if (!is_array($source)) continue;
                $url = is_string($source['url'] ?? null) ? trim($source['url']) : '';
                $key = tw_provider_url_key($url);
                if ($key === null) continue;
                $sources[$key] = [
                    'url' => $url,
                    'title' => is_string($source['title'] ?? null) ? tw_clean_text($source['title'], 500) : '',
                    'evidence' => 'web_search_result',
                ];
            }
        }
        if (($node['type'] ?? '') === 'url_citation') {
            $citation = is_array($node['url_citation'] ?? null) ? $node['url_citation'] : $node;
            $url = is_string($citation['url'] ?? null) ? trim($citation['url']) : '';
            $key = tw_provider_url_key($url);
            if ($key !== null) {
                $sources[$key] = [
                    'url' => $url,
                    'title' => is_string($citation['title'] ?? null) ? tw_clean_text($citation['title'], 500) : '',
                    'evidence' => 'url_citation',
                ];
            }
        }
        foreach ($node as $value) {
            if (is_array($value)) $visit($value);
        }
    };
    $visit($response);
    return $sources;
}

function tw_validate_provider_response_contract(array $response, array $dailyAiConfig): array {
    if (($response['status'] ?? null) !== 'completed') {
        throw new RuntimeException('Research provider response did not complete successfully.');
    }
    $limits = tw_daily_ai_limits($dailyAiConfig);
    $webSearchCalls = tw_count_response_type($response, 'web_search_call');
    if ($webSearchCalls < 1) throw new RuntimeException('Research response completed without the required web search.');
    if ($webSearchCalls > $limits['max_web_search_calls']) {
        throw new RuntimeException('Research provider exceeded the configured web-search limit.');
    }
    $providerSources = tw_provider_evidence_sources($response);
    if (count($providerSources) < 2) {
        throw new RuntimeException('Research provider returned fewer than two auditable web sources.');
    }
    return ['web_search_calls' => $webSearchCalls, 'sources' => $providerSources];
}

function tw_daily_report_list(array $report, string $name, bool $required = false): array {
    $value = $report[$name] ?? null;
    if (!is_array($value)) throw new RuntimeException('Research response omitted the required ' . $name . ' list.');
    $out = [];
    foreach ($value as $item) {
        if (!is_string($item)) throw new RuntimeException('Research response returned an invalid ' . $name . ' item.');
        $item = tw_clean_text($item, 3000);
        if ($item !== '') $out[] = $item;
        if (count($out) >= 30) break;
    }
    if ($required && $out === []) throw new RuntimeException('Research response returned an empty ' . $name . ' list.');
    return $out;
}

function tw_validate_daily_report(array $report, array $providerSources, int $webSearchCalls): array {
    if ($webSearchCalls < 1) throw new RuntimeException('Research response completed without the required web search.');
    if (count($providerSources) < 2) throw new RuntimeException('Research provider returned fewer than two auditable web sources.');
    $headline = isset($report['headline']) && is_string($report['headline']) ? tw_clean_text($report['headline'], 500) : '';
    $claim = isset($report['claim']) && is_string($report['claim']) ? tw_clean_text($report['claim'], 2000) : '';
    $summary = isset($report['summary']) && is_string($report['summary']) ? tw_clean_text($report['summary'], 4000) : '';
    if ($headline === '' || $claim === '' || $summary === '') {
        throw new RuntimeException('Research response omitted its headline, claim, or summary.');
    }

    $opinionNode = $report['bobinated_opinion'] ?? null;
    if (!is_array($opinionNode) || !is_string($opinionNode['opinion'] ?? null)) {
        throw new RuntimeException('Research response omitted the Bobinated Opinion boundary.');
    }
    $opinion = tw_clean_text((string)$opinionNode['opinion'], 5000);
    if ($opinion === '') throw new RuntimeException('Research response returned an empty Bobinated Opinion.');
    $opinionReasoning = tw_daily_report_list(['reasoning' => $opinionNode['reasoning'] ?? null], 'reasoning', true);

    $sources = [];
    $seenUrls = [];
    if (!is_array($report['sources'] ?? null)) throw new RuntimeException('Research response omitted its source trail.');
    foreach ($report['sources'] as $source) {
        if (!is_array($source)) throw new RuntimeException('Research response returned an invalid source entry.');
        $url = is_string($source['url'] ?? null) ? trim($source['url']) : '';
        $urlKey = tw_provider_url_key($url);
        if ($urlKey === null) throw new RuntimeException('Research response returned an invalid source URL.');
        if (!isset($providerSources[$urlKey])) {
            throw new RuntimeException('Research response cited a URL that was not returned by provider evidence.');
        }
        if (isset($seenUrls[$urlKey])) continue;
        $providerSource = $providerSources[$urlKey];
        $providerUrl = is_string($providerSource['url'] ?? null) ? trim($providerSource['url']) : '';
        $providerTitle = is_string($providerSource['title'] ?? null) ? tw_clean_text($providerSource['title'], 500) : '';
        $modelTitle = is_string($source['title'] ?? null) ? tw_clean_text($source['title'], 500) : '';
        $title = $providerTitle !== '' ? $providerTitle : $modelTitle;
        $role = is_string($source['role'] ?? null) ? tw_clean_text($source['role'], 1000) : '';
        if ($title === '' || $role === '') continue;
        $seenUrls[$urlKey] = true;
        $sources[] = ['title' => $title, 'url' => $providerUrl, 'role' => $role];
        if (count($sources) >= 30) break;
    }
    if (count($sources) < 2) throw new RuntimeException('Research response did not provide at least two usable sources.');

    $confidence = is_string($report['confidence'] ?? null) ? strtolower(trim($report['confidence'])) : '';
    if (!in_array($confidence, ['high', 'medium', 'low'], true)) {
        throw new RuntimeException('Research response returned an invalid confidence label.');
    }

    return [
        'headline' => $headline,
        'claim' => $claim,
        'summary' => $summary,
        'proven' => tw_daily_report_list($report, 'proven'),
        'strongly_indicated' => tw_daily_report_list($report, 'strongly_indicated'),
        'contradictions_missing_pieces' => tw_daily_report_list($report, 'contradictions_missing_pieces', true),
        'motives_incentives_who_benefits' => tw_daily_report_list($report, 'motives_incentives_who_benefits'),
        'logic_common_sense' => tw_daily_report_list($report, 'logic_common_sense'),
        'counterevidence_alternatives' => tw_daily_report_list($report, 'counterevidence_alternatives', true),
        'unknowns' => tw_daily_report_list($report, 'unknowns', true),
        'bobinated_opinion' => ['opinion' => $opinion, 'reasoning' => $opinionReasoning],
        'sources' => $sources,
        'what_would_change_the_finding' => tw_daily_report_list($report, 'what_would_change_the_finding', true),
        'confidence' => $confidence,
    ];
}

function tw_daily_provider_private_metadata(array $response): array {
    $metadata = ['provider_usage' => []];
    $responseId = $response['id'] ?? null;
    if (is_string($responseId) && $responseId !== '' && strlen($responseId) <= 512) {
        // Provider IDs are operationally useful only for correlation. Persist a
        // one-way fingerprint so an untrusted ID can never echo prompt content.
        $metadata['provider_response_id_sha256'] = hash('sha256', $responseId);
        $metadata['provider_response_id_bytes'] = strlen($responseId);
    }
    $usage = $response['usage'] ?? null;
    if (is_array($usage)) {
        foreach (['input_tokens', 'output_tokens', 'total_tokens'] as $field) {
            $value = $usage[$field] ?? null;
            if (is_int($value) && $value >= 0 && $value <= 1000000000) {
                $metadata['provider_usage'][$field] = $value;
            }
        }
    }
    return $metadata;
}

function tw_daily_capture_provider_chunk(
    string &$buffer,
    bool &$overflow,
    string $chunk,
    int $maxBytes = 4194304
): int {
    $chunkBytes = strlen($chunk);
    if ($maxBytes < 1 || $maxBytes > 8388608 || $overflow || $chunkBytes > $maxBytes - strlen($buffer)) {
        $overflow = true;
        return 0;
    }
    $buffer .= $chunk;
    return $chunkBytes;
}

function tw_run_investigation(array $candidate, string $model, array $dailyAiConfig = []): array {
    if (!tw_daily_private_candidate_is_current($candidate)) {
        throw new RuntimeException('The private question expired before research could begin.');
    }
    $key = tw_openai_key();
    if ($key === '') throw new RuntimeException('No OpenAI key is configured for Trust-Worthy.');
    $limits = tw_daily_ai_limits($dailyAiConfig);

    $system = <<<'PROMPT'
You are the research engine for Trust-Worthy AI. Motto: QUESTION EVERYTHING! Final invitation: YOU BE THE JUDGE.
Investigate from first principles. Do not treat a fact-checker, institution, outsider, government, corporation, religious authority, political faction, or media outlet as automatically authoritative or automatically deceptive. Trace claims to primary or earliest accessible evidence when possible. Question provenance, chronology, context, funding, incentives, beneficiaries, omissions, contradictions, and supposedly independent corroboration. Actively seek the strongest counterevidence and strongest reasonable alternative explanation.
No hard proof does not mean nothing reasonable can be inferred. You may identify a conclusion as strongly indicated or a reasonable inference when the total pattern supports it, but never relabel inference as proven fact. Motive is context, not proof by itself.
A Bobinated Opinion is explicitly opinion: state what appears most likely, show the logic, acknowledge material evidence cutting the other way, and name important unknowns.
Do not invent sources, quotations, documents, motives, people, events, or evidence. If a source cannot be verified, say so. For serious allegations about identifiable people, report the record precisely and distinguish allegation, evidence, inference, and opinion.
Return valid JSON only. No Markdown fences. Use this exact top-level schema:
{
  "headline": string,
  "claim": string,
  "summary": string,
  "proven": [string],
  "strongly_indicated": [string],
  "contradictions_missing_pieces": [string],
  "motives_incentives_who_benefits": [string],
  "logic_common_sense": [string],
  "counterevidence_alternatives": [string],
  "unknowns": [string],
  "bobinated_opinion": {"opinion": string, "reasoning": [string]},
  "sources": [{"title": string, "url": string, "role": string}],
  "what_would_change_the_finding": [string],
  "confidence": "high"|"medium"|"low"
}
PROMPT;

    try {
        $candidateJson = json_encode([
            'source' => $candidate['source'] ?? '',
            'headline' => $candidate['title'] ?? '',
            'url' => $candidate['url'] ?? '',
            'published_at_utc' => $candidate['published_at_utc'] ?? '',
            'summary' => $candidate['summary'] ?? '',
            'corroborating_sources' => $candidate['corroborating_sources'] ?? [],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Candidate could not be encoded for research.', 0, $e);
    }

    $payload = [
        'model' => $model,
        'store' => false,
        'tools' => [['type' => 'web_search']],
        'include' => ['web_search_call.action.sources'],
        'input' => [
            ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $system]]],
            ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => "Investigate this candidate claim. Begin by identifying exactly what is being claimed, then research broadly and deeply. Candidate: {$candidateJson}"]]],
        ],
        'max_output_tokens' => $limits['max_output_tokens'],
        'max_tool_calls' => $limits['max_web_search_calls'],
    ];

    try {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Research request could not be encoded.', 0, $e);
    }
    $body = '';
    $responseTooLarge = false;
    $ch = curl_init('https://api.openai.com/v1/responses');
    if ($ch === false) throw new RuntimeException('Unable to initialize research provider request.');
    $configured = curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$responseTooLarge): int {
            return tw_daily_capture_provider_chunk($body, $responseTooLarge, $chunk);
        },
    ]);
    if (!$configured) {
        curl_close($ch);
        throw new RuntimeException('Unable to configure research provider request.');
    }
    $completed = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($responseTooLarge) {
        throw new RuntimeException('Research provider response exceeded the safe 4 MiB limit (code TW_DAILY_PROVIDER_OVERSIZE).');
    }
    if ($completed === false || $status < 200 || $status >= 300) {
        throw new RuntimeException(
            'Research provider request failed safely (HTTP ' . $status . '; code TW_DAILY_PROVIDER_FAILED).'
        );
    }
    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) throw new RuntimeException('Research provider returned invalid JSON.');
    $providerContract = tw_validate_provider_response_contract($decoded, $dailyAiConfig);
    $providerSources = $providerContract['sources'];
    $webSearchCalls = $providerContract['web_search_calls'];
    $text = tw_extract_response_text($decoded);
    if ($text === null) throw new RuntimeException('Research provider returned no usable text.');
    $report = json_decode(tw_trim_json_fence($text), true);
    if (!is_array($report)) throw new RuntimeException('Research response was not valid investigation JSON.');
    $report = tw_validate_daily_report($report, $providerSources, $webSearchCalls);
    $providerMetadata = tw_daily_provider_private_metadata($decoded);
    $report['_meta'] = array_merge([
        'generated_at_utc' => gmdate('c'),
        'candidate' => $candidate,
        'model' => $model,
    ], $providerMetadata, [
        'provider_web_search_calls' => $webSearchCalls,
        'provider_source_count' => count($providerSources),
        'provider_sources' => array_values($providerSources),
        'human_review_required' => true,
    ]);
    return $report;
}

function tw_safe_url(string $url): string {
    return preg_match('#^https?://#i', $url) ? $url : '#';
}
