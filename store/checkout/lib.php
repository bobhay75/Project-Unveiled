<?php
declare(strict_types=1);

/* Small, fail-closed checkout. No card information, buyer email, webhook,
 * marketing signup, external mail, or client-selected price is handled here.
 */
const BC_ORIGIN = 'https://bobsome1.com';
const BC_BASE = '/store/checkout/';
const BC_SKU = 'project-unveiled-digital-edition';
const BC_AMOUNT = '7.00';
const BC_CURRENCY = 'USD';
const BC_MAX_RECORDS = 5000;
const BC_MAX_LEDGER_BYTES = 10485760;
const BC_CHECKOUT_TTL = 10800;
const BC_DOWNLOAD_TTL = 86400;
const BC_DOWNLOAD_LIMIT = 10;
const BC_PAID_RETENTION = 15552000; // 180 days after verified capture.
const BC_ABANDONED_RETENTION = 604800; // Seven days after checkout creation.
const BC_HOURLY_CREATION_LIMIT = 60;

final class BcException extends RuntimeException
{
    public function __construct(string $message, public int $httpStatus = 503)
    {
        parent::__construct($message);
    }
}

function bc_fail(string $message = 'Checkout is temporarily unavailable.', int $status = 503): never
{
    throw new BcException($message, $status);
}

function bc_inside(string $path, string $root): bool
{
    return $path === rtrim($root, '/') || str_starts_with($path, rtrim($root, '/') . '/');
}

function bc_private_path(string $path, string $publicRoot, bool $directory = false): string
{
    clearstatcache(true, $path);
    $real = realpath($path);
    $repositoryRoot = realpath(dirname(__DIR__, 2));
    if ($path === '' || $path[0] !== '/' || is_link($path) || $real === false
        || ($directory ? !is_dir($real) : !is_file($real))
        || bc_inside($real, $publicRoot)
        || ($repositoryRoot !== false && bc_inside($real, $repositoryRoot))
        || (fileperms($real) & 0077) !== 0) {
        bc_fail('Checkout needs its private configuration and delivery file verified.');
    }
    return $real;
}

function bc_config(): array
{
    static $config;
    if (is_array($config)) return $config;
    $path = (string)getenv('BOBSOME1_COMMERCE_CONFIG');
    if ($path === '') bc_fail('Automatic checkout is not active yet. Please use the purchase instructions in the store.');
    $root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($root === false || $root === '') bc_fail();
    $path = bc_private_path($path, $root);
    $candidate = require $path;
    if (!is_array($candidate) || ($candidate['enabled'] ?? false) !== true) {
        bc_fail('Automatic checkout is not active yet. Please use the purchase instructions in the store.');
    }
    $config = bc_validate_config($candidate, $root);
    return $config;
}

function bc_validate_config(array $config, string $publicRoot): array
{
    if (!in_array($config['mode'] ?? '', ['sandbox', 'live'], true)
        || !is_string($config['client_id'] ?? null) || strlen($config['client_id']) < 10
        || !is_string($config['client_secret'] ?? null) || strlen($config['client_secret']) < 10
        || !is_string($config['merchant_id'] ?? null)
        || !preg_match('/^[A-Z0-9]{8,20}$/D', $config['merchant_id'])
        || !is_string($config['asset_sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $config['asset_sha256'])) {
        bc_fail('Checkout needs its payment configuration verified.');
    }
    $config['private_dir'] = bc_private_path((string)($config['private_dir'] ?? ''), $publicRoot, true);
    $config['asset_path'] = bc_private_path((string)($config['asset_path'] ?? ''), $publicRoot);
    $size = filesize($config['asset_path']);
    $handle = fopen($config['asset_path'], 'rb');
    $magic = $handle === false ? '' : fread($handle, 5);
    if (is_resource($handle)) fclose($handle);
    if (!is_int($size) || $size < 100 || $size > 52428800 || $magic !== '%PDF-'
        || !hash_equals($config['asset_sha256'], (string)hash_file('sha256', $config['asset_path']))) {
        bc_fail('The approved digital edition is not ready for automatic delivery.');
    }
    if (!function_exists('curl_init')) bc_fail('Automatic checkout is not available on this server yet.');
    return $config;
}

function bc_headers(): void
{
    ini_set('display_errors', '0');
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    $destinations = in_array(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')), ['index.php', 'start.php'], true)
        ? ' https://www.paypal.com https://paypal.com https://www.sandbox.paypal.com https://sandbox.paypal.com' : '';
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; form-action 'self'" . $destinations . "; base-uri 'none'; frame-ancestors 'none'");
}

function bc_boot(string $method): array
{
    bc_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        bc_fail('This checkout action uses ' . $method . '.', 405);
    }
    if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'bobsome1.com'
        || !in_array(strtolower((string)($_SERVER['HTTPS'] ?? '')), ['on', '1'], true)) {
        bc_fail('Please open checkout securely at https://bobsome1.com/store/checkout/.', 400);
    }
    $config = bc_config();
    $sessionDir = $config['private_dir'] . '/sessions';
    if (is_link($sessionDir)) bc_fail();
    if (!is_dir($sessionDir) && !mkdir($sessionDir, 0700)) bc_fail();
    if ((fileperms($sessionDir) & 0077) !== 0) bc_fail();
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)BC_DOWNLOAD_TTL);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_name('bobsome1_checkout');
    session_save_path($sessionDir);
    session_cache_limiter('');
    session_set_cookie_params(['lifetime' => 0, 'path' => BC_BASE, 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    if (!session_start()) bc_fail();
    if (!isset($_SESSION['csrf'])) {
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $config;
}

function bc_post(): void
{
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $fetchSite = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    if (($origin !== '' && $origin !== BC_ORIGIN) || $fetchSite === 'cross-site') {
        bc_fail('Open checkout on bobsome1.com and try again.', 403);
    }
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/x-www-form-urlencoded' || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
        bc_fail('Unsupported checkout request.', 400);
    }
    $csrf = $_POST['csrf'] ?? null;
    if (!is_string($csrf) || !isset($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $csrf)) {
        bc_fail('Your checkout session expired. Reload checkout and try again.', 403);
    }
}

function bc_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bc_page(string $title, string $content, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
        . bc_h($title) . ' | Bobsome1</title><style>body{font:1.05rem/1.6 system-ui,sans-serif;background:#10151b;color:#f6f8fa;margin:0}main{max-width:40rem;margin:4rem auto;padding:1.4rem}h1{line-height:1.2}a{color:#98d8ff}button{font:inherit;font-weight:700;padding:.85rem 1.3rem;background:#f2bd65;border:0;border-radius:.4rem;color:#17130c;cursor:pointer}button:focus-visible,a:focus-visible{outline:3px solid #fff;outline-offset:4px}.notice{border-left:4px solid #f2bd65;padding:.6rem 1rem;background:#1b2632}.small{font-size:.92rem;color:#c7d0da}</style></head><body><main><a href="/store/">Bobsome1 Store</a><h1>'
        . bc_h($title) . '</h1>' . $content . '<p class="small"><a href="/privacy.html">Privacy</a> · <a href="mailto:thebobsomest1@gmail.com">Purchase support</a></p></main></body></html>';
    exit;
}

function bc_error(Throwable $error): never
{
    bc_headers();
    $message = $error instanceof BcException ? $error->getMessage() : 'Checkout could not be confirmed. Please try again later.';
    $status = $error instanceof BcException ? $error->httpStatus : 503;
    if (!$error instanceof BcException) error_log('Bobsome1 checkout failed safely: ' . get_class($error));
    bc_page('Checkout needs attention', '<p>' . bc_h($message) . '</p><p>If PayPal already shows a charge, do not pay again. Keep your PayPal receipt and contact purchase support so Robert can verify and deliver your edition.</p><p><a href="/store/">Return to the store</a></p>', $status);
}

function bc_id(string $value): bool
{
    return (bool)preg_match('/^[a-f0-9]{32}$/D', $value);
}

function bc_paypal_id(string $value): bool
{
    return (bool)preg_match('/^[A-Z0-9]{10,40}$/D', $value);
}

/* Caller provides a callback that mutates the bounded ledger. A separate
 * owner-only lock survives atomic renames. No payment or token is logged.
 */
function bc_store(string $directory, callable $callback): mixed
{
    $path = $directory . '/orders.json';
    $lockPath = $directory . '/orders.lock';
    if (!is_dir($directory) || is_link($directory) || (fileperms($directory) & 0077) !== 0
        || is_link($path) || is_link($lockPath)) bc_fail();
    $oldMask = umask(0077);
    $lock = fopen($lockPath, 'c+b');
    umask($oldMask);
    if ($lock === false) bc_fail();
    if ((fileperms($lockPath) & 0077) !== 0) { fclose($lock); bc_fail(); }
    $deadline = microtime(true) + 2.0;
    while (!flock($lock, LOCK_EX | LOCK_NB)) {
        if (microtime(true) >= $deadline) { fclose($lock); bc_fail('Checkout is busy. Please try once more in a moment.'); }
        usleep(20000);
    }
    $temp = null;
    try {
        if (is_link($path) || (file_exists($path) && (!is_file($path) || (fileperms($path) & 0077) !== 0))) bc_fail();
        $ledger = ['schema' => 1, 'orders' => []];
        if (is_file($path)) {
            $size = filesize($path);
            if ($size === false || $size > BC_MAX_LEDGER_BYTES) bc_fail();
            $bytes = file_get_contents($path);
            if ($bytes === false) bc_fail();
            $ledger = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        }
        if (!is_array($ledger) || ($ledger['schema'] ?? null) !== 1 || !is_array($ledger['orders'] ?? null)
            || count($ledger['orders']) > BC_MAX_RECORDS) bc_fail();
        $before = $ledger;
        // Minimize retained identifiers. Cleanup happens on checkout traffic;
        // the owner must schedule private cleanup when the site is inactive.
        foreach ($ledger['orders'] as $id => $entry) {
            if (!is_array($entry) || !bc_id((string)$id) || !is_int($entry['created_at'] ?? null)) bc_fail();
            $paidAt = $entry['paid_at'] ?? null;
            $deleteAfter = is_int($paidAt) ? $paidAt + BC_PAID_RETENTION : $entry['created_at'] + BC_ABANDONED_RETENTION;
            if ($deleteAfter <= time()) unset($ledger['orders'][$id]);
        }
        $result = $callback($ledger);
        if ($ledger !== $before) {
            if (count($ledger['orders']) > BC_MAX_RECORDS) bc_fail('Checkout capacity needs an owner review.');
            $bytes = json_encode($ledger, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (strlen($bytes) > BC_MAX_LEDGER_BYTES) bc_fail('Checkout capacity needs an owner review.');
            $temp = $directory . '/.orders-' . bin2hex(random_bytes(12)) . '.tmp';
            $oldMask = umask(0077);
            $handle = fopen($temp, 'xb');
            umask($oldMask);
            if ($handle === false) bc_fail();
            try {
                $offset = 0;
                while ($offset < strlen($bytes)) {
                    $written = fwrite($handle, substr($bytes, $offset));
                    if ($written === false || $written === 0) bc_fail();
                    $offset += $written;
                }
                if (!fflush($handle)) bc_fail();
                if (function_exists('fsync') && !fsync($handle)) bc_fail();
            } finally { fclose($handle); }
            if (!rename($temp, $path)) bc_fail();
            $temp = null;
        }
        return $result;
    } finally {
        if (is_string($temp) && is_file($temp)) unlink($temp);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function bc_order(array $config, string $id): array
{
    if (!bc_id($id)) bc_fail('The checkout session could not be found.', 400);
    return bc_store($config['private_dir'], static function (array &$ledger) use ($id): array {
        $order = $ledger['orders'][$id] ?? null;
        if (!is_array($order)) bc_fail('The checkout session could not be found.', 400);
        return $order;
    });
}

function bc_session_order(array $config): array
{
    $id = $_SESSION['order_id'] ?? null;
    if (!is_string($id)) bc_fail('Your checkout session expired. If you paid, contact purchase support.', 400);
    return bc_order($config, $id);
}

function bc_new_order(string $id, string $token, int $now): array
{
    if (!bc_id($id) || !preg_match('/^[a-f0-9]{64}$/D', $token)) bc_fail();
    return ['id' => $id, 'sku' => BC_SKU, 'state' => 'creating', 'created_at' => $now,
        'checkout_expires_at' => $now + BC_CHECKOUT_TTL, 'paypal_order_id' => null,
        'capture_id' => null, 'approval_url' => null, 'paid_at' => null, 'capture_attempted_at' => null,
        'download_expires_at' => null, 'download_count' => 0,
        'token_hash' => hash('sha256', $token),
        'create_request_id' => 'new-' . $id, 'capture_request_id' => 'cap-' . $id];
}

function bc_allow_new_order(array $ledger, ?array $existing, int $now): void
{
    // A lost capture response is not proof of nonpayment. Never silently
    // replace an existing checkout with a fresh payable order, even after TTL.
    if ($existing !== null) bc_fail('An earlier checkout still needs verification. Do not pay again; contact purchase support.', 409);
    $recent = 0;
    foreach ($ledger['orders'] as $entry) if (($entry['created_at'] ?? 0) > $now - 3600) $recent++;
    if ($recent >= BC_HOURLY_CREATION_LIMIT) bc_fail('Checkout is temporarily busy. Please try later.', 429);
}

function bc_manual_allowed(array $cookies, array $session): bool
{
    return !isset($cookies['bobsome1_checkout']) && !isset($session['order_id']);
}

function bc_capture_may_charge(array $order, int $now): bool
{
    if ($order['state'] !== 'created' || !bc_paypal_id((string)$order['paypal_order_id'])) {
        bc_fail('This checkout cannot be captured. If you paid, contact purchase support.', 409);
    }
    // The caller may still make a read-only PayPal GET after this returns false.
    return $order['checkout_expires_at'] > $now;
}

function bc_manual_content(): string
{
    return '<p><strong>Project Unveiled digital edition · $7 USD</strong></p><p>Automatic delivery is not active yet. You can still purchase using the existing PayPal path and personal delivery.</p><p><a href="https://paypal.me/Bobsome1975/7USD" rel="noopener noreferrer">Pay $7 USD with PayPal</a></p><p>Current fulfillment is personal: after payment, send the PayPal transaction number and delivery email to <a href="mailto:thebobsomest1@gmail.com?subject=Project%20Unveiled%20digital%20edition&amp;body=PayPal%20transaction%20number%3A%0ADelivery%20email%3A%0A">Robert</a>. Delivery is not instant yet.</p><p>If you have already paid, do not pay again. Send your existing PayPal transaction number for verification.</p><p><a href="/book/read/">Read free before buying</a></p>';
}

function bc_create_payload(array $config, array $order): array
{
    return ['intent' => 'CAPTURE', 'purchase_units' => [[
        'reference_id' => BC_SKU, 'custom_id' => $order['id'], 'invoice_id' => 'PU-' . $order['id'],
        'description' => 'Project Unveiled digital edition (PDF)',
        'payee' => ['merchant_id' => $config['merchant_id']],
        'amount' => ['currency_code' => BC_CURRENCY, 'value' => BC_AMOUNT],
    ]], 'payment_source' => ['paypal' => ['experience_context' => [
        'brand_name' => 'Bobsome1', 'shipping_preference' => 'NO_SHIPPING',
        'user_action' => 'PAY_NOW', 'return_url' => BC_ORIGIN . BC_BASE . 'return.php',
        'cancel_url' => BC_ORIGIN . BC_BASE . '?cancelled=1',
    ]]]];
}

function bc_approval_url(array $response, string $mode): string
{
    foreach (($response['links'] ?? []) as $link) {
        if (!is_array($link) || !in_array($link['rel'] ?? '', ['approve', 'payer-action'], true)) continue;
        $url = $link['href'] ?? null;
        if (!is_string($url)) continue;
        $parts = parse_url($url);
        $hosts = $mode === 'live' ? ['www.paypal.com', 'paypal.com'] : ['www.sandbox.paypal.com', 'sandbox.paypal.com'];
        if (is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower((string)($parts['host'] ?? '')), $hosts, true)
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['fragment'])
            && (!isset($parts['port']) || $parts['port'] === 443)) return $url;
    }
    bc_fail('PayPal did not return a verified checkout destination.');
}

function bc_money(array $amount): bool
{
    // No floats: exact whole-cent representations only.
    return ($amount['currency_code'] ?? '') === BC_CURRENCY
        && is_string($amount['value'] ?? null)
        && in_array($amount['value'], ['7.00', '7.0', '7'], true);
}

function bc_validate_capture(array $capture, ?string $expectedId = null): string
{
    $id = $capture['id'] ?? null;
    if (!is_string($id) || !bc_paypal_id($id) || ($expectedId !== null && !hash_equals($expectedId, $id))
        || ($capture['status'] ?? '') !== 'COMPLETED'
        || !is_array($capture['amount'] ?? null) || !bc_money($capture['amount'])) {
        bc_fail('A completed payment for this edition could not be verified.', 409);
    }
    return $id;
}

function bc_validate_paid_order(array $remote, array $local, string $merchantId): string
{
    $units = $remote['purchase_units'] ?? null;
    if (($remote['id'] ?? null) !== $local['paypal_order_id'] || ($remote['status'] ?? '') !== 'COMPLETED'
        || !is_array($units) || count($units) !== 1 || !isset($units[0]) || !is_array($units[0])) {
        bc_fail('This order has not been verified as paid.', 409);
    }
    $unit = $units[0];
    $captures = $unit['payments']['captures'] ?? null;
    if (($unit['custom_id'] ?? '') !== $local['id'] || ($unit['reference_id'] ?? '') !== BC_SKU
        || ($unit['payee']['merchant_id'] ?? '') !== $merchantId
        || !is_array($unit['amount'] ?? null) || !bc_money($unit['amount'])
        || !is_array($captures) || count($captures) !== 1 || !isset($captures[0]) || !is_array($captures[0])) {
        bc_fail('The payment details do not match this edition.', 409);
    }
    return bc_validate_capture($captures[0]);
}

function bc_paid_transition(array $order, string $captureId, int $now): array
{
    if (!bc_paypal_id($captureId) || !in_array($order['state'], ['created', 'paid'], true)) bc_fail('This order cannot be fulfilled automatically.', 409);
    if ($order['state'] === 'paid') {
        if ($order['capture_id'] !== $captureId) bc_fail('The payment reference changed unexpectedly.', 409);
        return $order; // Duplicate captures never extend grants or redeliver.
    }
    $order['state'] = 'paid';
    $order['capture_id'] = $captureId;
    $order['paid_at'] = $now;
    $order['download_expires_at'] = $now + BC_DOWNLOAD_TTL;
    return $order;
}

function bc_download_eligible(array $order, string $token, int $now): void
{
    if ($order['state'] !== 'paid' || !preg_match('/^[a-f0-9]{64}$/D', $token)
        || !hash_equals($order['token_hash'], hash('sha256', $token))
        || !is_int($order['download_expires_at']) || $order['download_expires_at'] <= $now
        || $order['download_count'] >= BC_DOWNLOAD_LIMIT) {
        bc_fail('This download is unavailable or expired. Contact purchase support with your PayPal receipt.', 403);
    }
}

/* Fixed API host + fixed route patterns; never follow server-supplied links. */
function bc_http(string $url, array $options): array
{
    $handle = curl_init($url);
    if ($handle === false) bc_fail();
    $body = '';
    $options += [CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_WRITEFUNCTION => static function ($unused, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 524288) return 0;
            $body .= $chunk;
            return strlen($chunk);
        }];
    curl_setopt_array($handle, $options);
    try {
        $ok = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($ok === false || $status < 200 || $status >= 300) bc_fail('PayPal could not confirm this request. Please try again without creating another payment.');
        $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) bc_fail();
        return $data;
    } finally { curl_close($handle); }
}

function bc_api(array $config, string $method, string $route, ?array $payload = null, ?string $requestId = null): array
{
    if (!in_array($method, ['GET', 'POST'], true)
        || !preg_match('#^/v2/(?:checkout/orders(?:/[A-Z0-9]{10,40}(?:/capture)?)?|payments/captures/[A-Z0-9]{10,40})$#D', $route)) bc_fail();
    $base = $config['mode'] === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    static $accessTokens = [];
    $key = hash('sha256', $config['mode'] . $config['client_id']);
    if (!isset($accessTokens[$key])) {
        $auth = bc_http($base . '/v1/oauth2/token', [CURLOPT_POST => true,
            CURLOPT_USERPWD => $config['client_id'] . ':' . $config['client_secret'],
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']]);
        if (!is_string($auth['access_token'] ?? null) || !preg_match('/^[A-Za-z0-9._~-]+$/D', $auth['access_token'])) bc_fail();
        $accessTokens[$key] = $auth['access_token'];
    }
    $headers = ['Authorization: Bearer ' . $accessTokens[$key], 'Accept: application/json', 'Content-Type: application/json', 'Prefer: return=representation'];
    if ($requestId !== null) {
        if (!preg_match('/^(?:new|cap)-[a-f0-9]{32}$/D', $requestId)) bc_fail();
        $headers[] = 'PayPal-Request-Id: ' . $requestId;
    }
    $options = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers];
    if ($method === 'POST') $options[CURLOPT_POSTFIELDS] = $payload === null ? '{}' : json_encode($payload, JSON_THROW_ON_ERROR);
    return bc_http($base . $route, $options);
}

function bc_success(array $order): never
{
    $token = $_SESSION['download_token'] ?? '';
    if (!is_string($token)) bc_fail();
    bc_download_eligible($order, $token, time());
    bc_page('Your digital edition is ready', '<p>Your $7 USD payment was verified with PayPal. Download your Project Unveiled PDF below.</p><form action="download.php" method="post"><input type="hidden" name="csrf" value="' . bc_h((string)$_SESSION['csrf']) . '"><input type="hidden" name="token" value="' . bc_h($token) . '"><button type="submit">Download my digital edition</button></form><p class="small">Keep this browser session open until your download completes. Access lasts 24 hours with up to 10 download attempts. Save the PDF to your device. If you lose access, contact purchase support with your PayPal receipt. No marketing subscription was added.</p>');
}
