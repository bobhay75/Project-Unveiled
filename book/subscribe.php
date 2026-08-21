<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/unveiled-journey-lib.php';

function pu_out(bool $ok, string $message, int $code = 200): never
{
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function pu_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') pu_out(false, 'POST required.', 405);

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host) ?? '';
$allowedHosts = ['bobsome1.com', 'www.bobsome1.com'];
if (!in_array($host, $allowedHosts, true)) pu_out(false, 'Host rejected.', 403);
if ($origin !== '' && !in_array(strtolower((string) parse_url($origin, PHP_URL_HOST)), $allowedHosts, true)) {
    pu_out(false, 'Origin rejected.', 403);
}

if (trim((string) ($_POST['website'] ?? '')) !== '') pu_out(true, 'Thank you.');
$started = (int) ($_POST['started_ms'] ?? 0);
if ($started > 0 && (int) (microtime(true) * 1000) - $started < 1800) {
    pu_out(false, 'Please wait a moment and try again.', 429);
}

$name = trim((string) preg_replace('/\s+/u', ' ', (string) ($_POST['first_name'] ?? '')));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$consent = (string) ($_POST['consent'] ?? '');
$version = substr(trim((string) ($_POST['consent_version'] ?? '2026-07-v2')), 0, 80);
$journey = (string) ($_POST['journey'] ?? '') === '7-day-unveiled';

if ($name === '' || pu_len($name) > 80) pu_out(false, 'Enter your first name.', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) pu_out(false, 'Enter a valid email address.', 422);
if ($consent !== 'yes') pu_out(false, 'Consent is required to join the email list.', 422);

try {
    $private = pu_journey_ensure_private_dir();
} catch (Throwable $error) {
    pu_out(false, 'Storage is unavailable.', 500);
}

if ($journey && pu_journey_mailing_address() === '') {
    pu_out(false, 'The 7-Day Journey is awaiting final email activation. Please check back soon.', 503);
}

$dataFile = $private . '/subscribers.json';
$rateFile = $private . '/subscriber-rate.json';
$secretFile = $private . '/subscriber-secret.txt';
if (!is_file($secretFile)) {
    file_put_contents($secretFile, bin2hex(random_bytes(32)), LOCK_EX);
    @chmod($secretFile, 0640);
}
$secret = trim((string) file_get_contents($secretFile));
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$ipHash = hash_hmac('sha256', $ip, $secret);
$uaHash = hash_hmac('sha256', $ua, $secret);

$rates = pu_journey_read_json($rateFile);
$now = time();
foreach ($rates as $key => $timestamp) {
    if (!is_int($timestamp) || $timestamp < $now - 3600) unset($rates[$key]);
}
if (isset($rates[$ipHash]) && $rates[$ipHash] > $now - 30) pu_out(false, 'Please wait before trying again.', 429);
$rates[$ipHash] = $now;
pu_journey_write_json($rateFile, $rates);

$source = substr((string) ($_POST['source_url'] ?? ''), 0, 1200);
$parts = parse_url($source);
$query = [];
if (is_array($parts) && isset($parts['query'])) parse_str((string) $parts['query'], $query);

$unsubscribeToken = bin2hex(random_bytes(24));
$confirmationToken = $journey ? bin2hex(random_bytes(32)) : '';
$confirmationHash = $journey ? hash('sha256', $confirmationToken) : '';
$stamp = gmdate('c');
$found = false;
$subscriberStatus = $journey ? 'pending_confirmation' : 'subscribed';
$consentText = $journey
    ? 'I agree to receive the 7-Day Unveiled Journey and occasional Project Unveiled emails. I can unsubscribe at any time.'
    : 'I agree to receive Project Unveiled email updates. I can unsubscribe at any time.';

$lock = fopen($private . '/subscribers.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX)) pu_out(false, 'Signup storage is busy. Please try again.', 503);
try {
    $items = pu_journey_read_json($dataFile);
    foreach ($items as &$row) {
        if (!is_array($row) || strtolower((string) ($row['email'] ?? '')) !== $email) continue;
        $existingStatus = (string) ($row['status'] ?? '');
        if ($journey && $existingStatus === 'subscribed') $subscriberStatus = 'subscribed';
        $row = array_merge($row, [
            'first_name' => $name,
            'email' => $email,
            'status' => $subscriberStatus,
            'updated_at' => $stamp,
            'source_url' => $source,
            'utm_source' => substr((string) ($query['utm_source'] ?? ''), 0, 120),
            'utm_medium' => substr((string) ($query['utm_medium'] ?? ''), 0, 120),
            'utm_campaign' => substr((string) ($query['utm_campaign'] ?? ''), 0, 180),
            'utm_content' => substr((string) ($query['utm_content'] ?? ''), 0, 180),
            'consent_text' => $consentText,
            'consent_version' => $version,
            'consent_at' => $stamp,
            'unsubscribe_token' => $unsubscribeToken,
            'ip_hash' => $ipHash,
            'ua_hash' => $uaHash
        ]);
        if ($journey) {
            $row['journey_status'] = 'pending_confirmation';
            $row['journey_requested_at'] = $stamp;
        }
        $found = true;
        break;
    }
    unset($row);

    if (!$found) {
        $row = [
            'id' => bin2hex(random_bytes(8)),
            'first_name' => $name,
            'email' => $email,
            'status' => $subscriberStatus,
            'created_at' => $stamp,
            'updated_at' => $stamp,
            'source_url' => $source,
            'utm_source' => substr((string) ($query['utm_source'] ?? ''), 0, 120),
            'utm_medium' => substr((string) ($query['utm_medium'] ?? ''), 0, 120),
            'utm_campaign' => substr((string) ($query['utm_campaign'] ?? ''), 0, 180),
            'utm_content' => substr((string) ($query['utm_content'] ?? ''), 0, 180),
            'consent_text' => $consentText,
            'consent_version' => $version,
            'consent_at' => $stamp,
            'unsubscribe_token' => $unsubscribeToken,
            'ip_hash' => $ipHash,
            'ua_hash' => $uaHash
        ];
        if ($journey) {
            $row['journey_status'] = 'pending_confirmation';
            $row['journey_requested_at'] = $stamp;
        }
        $items[] = $row;
    }

    if (!pu_journey_write_json($dataFile, $items)) pu_out(false, 'Signup could not be saved.', 500);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

if ($journey) {
    try {
        pu_journey_with_queue(function (array &$queue) use ($email, $name, $unsubscribeToken, $confirmationHash, $stamp): void {
            $queue[pu_journey_email_key($email)] = [
                'first_name' => $name,
                'email' => $email,
                'status' => 'pending_confirmation',
                'confirmation_hash' => $confirmationHash,
                'confirmation_expires_at' => time() + 172800,
                'requested_at' => $stamp,
                'confirmed_at' => null,
                'unsubscribe_token' => $unsubscribeToken,
                'next_step' => 1,
                'next_at' => null,
                'attempts' => 0,
                'last_error' => null
            ];
        });
    } catch (Throwable $error) {
        pu_out(false, 'The journey could not be prepared. Please try again.', 500);
    }

    $confirmUrl = 'https://bobsome1.com/book/confirm.php?token=' . rawurlencode($confirmationToken);
    $body = "Hello {$name},\n\nYou asked to begin the 7-Day Unveiled Journey.\n\nConfirm this email address and open Day One:\n{$confirmUrl}\n\nThis private link expires in 48 hours. If you did not request the journey, you can ignore this message.\n\nTruth is not afraid of questions.\n\nRobert J. Hayes\nProject Unveiled\nhttps://bobsome1.com\n";
    if (!pu_journey_mail($email, 'Confirm your place in the 7-Day Unveiled Journey', $body, false)) {
        pu_out(false, 'Your request was saved, but the confirmation email could not be sent. Please try again shortly.', 503);
    }
    pu_out(true, 'Check your email and click the private confirmation link to begin.');
}

$unsubscribe = 'https://bobsome1.com/book/unsubscribe.php?token=' . rawurlencode($unsubscribeToken);
$subject = 'Welcome to Project Unveiled';
$body = "Hello {$name},\n\nYou are on the Project Unveiled reader list. We do not sell, rent, or trade your personal information. Ever.\n\nUnsubscribe: {$unsubscribe}\n";
@mail($email, $subject, $body, "From: Project Unveiled <noreply@bobsome1.com>\r\nContent-Type: text/plain; charset=UTF-8");
pu_out(true, $found ? 'Your subscription has been updated.' : 'You are signed up. Welcome to Project Unveiled.');
