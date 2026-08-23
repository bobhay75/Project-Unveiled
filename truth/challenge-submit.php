<?php
declare(strict_types=1);

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function wants_json(): bool
{
    return str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function out(bool $ok, string $message, int $code = 200, ?string $redirect = null): never
{
    http_response_code($code);
    if ($ok && $redirect !== null && !wants_json()) {
        header('Location: ' . $redirect, true, 303);
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function clean(string $value, int $limit): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    out(false, 'POST required.', 405);
}

$host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
$origin = strtolower((string) parse_url((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), PHP_URL_HOST));
if (!in_array($host, ['bobsome1.com', 'www.bobsome1.com'], true) || ($origin !== '' && !in_array($origin, ['bobsome1.com', 'www.bobsome1.com'], true))) {
    out(false, 'Request origin was not accepted.', 403);
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    out(true, 'Thank you.');
}

$opened = (int) ($_POST['opened_at'] ?? 0);
$elapsed = time() - $opened;
if ($opened < 1 || $elapsed < 4 || $elapsed > 7200) {
    out(false, 'Please review the challenge and submit it from the case page.', 422);
}

$caseId = clean((string) ($_POST['case_id'] ?? ''), 64);
$type = clean((string) ($_POST['challenge_type'] ?? ''), 64);
$name = clean((string) ($_POST['name'] ?? ''), 100);
$email = clean((string) ($_POST['email'] ?? ''), 190);
$argument = clean((string) ($_POST['argument'] ?? ''), 6000);
$source = clean((string) ($_POST['source'] ?? ''), 2000);
$materiality = clean((string) ($_POST['materiality'] ?? ''), 3000);

$allowed = ['source', 'provenance', 'date', 'translation', 'context', 'logic', 'counterevidence', 'assumption', 'alternative', 'incentive'];
if (!preg_match('/^TW-CLAIM-[0-9]{6}$/', $caseId)) {
    out(false, 'Unknown case.', 422);
}

$caseFile = __DIR__ . '/cases/' . $caseId . '.json';
$case = is_file($caseFile) ? json_decode((string) file_get_contents($caseFile), true) : null;
if (!is_array($case) || ($case['case_id'] ?? null) !== $caseId) {
    out(false, 'Unknown case.', 422);
}
if (!in_array($type, $allowed, true)) {
    out(false, 'Choose a valid challenge type.', 422);
}
if (strlen($argument) < 20 || strlen($materiality) < 10) {
    out(false, 'Explain the challenge and why it could materially change the assessment.', 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(false, 'Enter a valid email address or leave it blank.', 422);
}

$dir = dirname(__DIR__, 2) . '/site-private/trust-worthy';
if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
    out(false, 'Private storage is unavailable.', 503);
}

$secretFile = $dir . '/challenge-secret.txt';
if (!is_file($secretFile)) {
    if (file_put_contents($secretFile, bin2hex(random_bytes(32)), LOCK_EX) === false) {
        out(false, 'Private storage is unavailable.', 503);
    }
    @chmod($secretFile, 0640);
}

$secret = trim((string) file_get_contents($secretFile));
$ipHash = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $secret);
$rateFile = $dir . '/rate-' . $ipHash . '.txt';
$rateHandle = fopen($rateFile, 'c+');
if ($rateHandle === false || !flock($rateHandle, LOCK_EX)) {
    if (is_resource($rateHandle)) {
        fclose($rateHandle);
    }
    out(false, 'The challenge queue is temporarily unavailable.', 503);
}

$lastSubmission = (int) trim((string) stream_get_contents($rateHandle));
if ($lastSubmission > 0 && time() - $lastSubmission < 30) {
    flock($rateHandle, LOCK_UN);
    fclose($rateHandle);
    out(false, 'Please wait before submitting another challenge.', 429);
}
rewind($rateHandle);
ftruncate($rateHandle, 0);
fwrite($rateHandle, (string) time());
fflush($rateHandle);
flock($rateHandle, LOCK_UN);
fclose($rateHandle);
@chmod($rateFile, 0640);

$challengeId = 'TW-CH-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
$record = [
    'challenge_id' => $challengeId,
    'case_id' => $caseId,
    'challenge_type' => $type,
    'name' => $name,
    'email' => $email,
    'argument' => $argument,
    'source' => $source,
    'materiality' => $materiality,
    'status' => 'pending-independent-review',
    'submitted_at_utc' => gmdate('c'),
    'ip_hash' => $ipHash,
];

$recordFile = $dir . '/' . $challengeId . '.json';
$encoded = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($encoded === false || file_put_contents($recordFile, $encoded, LOCK_EX) === false) {
    out(false, 'Your challenge could not be saved. Please try again.', 503);
}
@chmod($recordFile, 0640);

$redirect = (string) ($case['public_url'] ?? 'https://bobsome1.com/truth/');
$separator = str_contains($redirect, '?') ? '&' : '?';
out(true, 'Challenge received. Trust-Worthy will investigate it rather than automatically defend the current finding.', 200, $redirect . $separator . 'challenge=received');
