<?php
declare(strict_types=1);

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/unveiled-journey-lib.php';

$token = strtolower(trim((string) ($_GET['token'] ?? '')));
$confirmedEmail = '';
$errorMessage = '';

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    $errorMessage = 'This confirmation link is invalid.';
} else {
    $tokenHash = hash('sha256', $token);
    try {
        $matched = pu_journey_with_queue(function (array &$queue) use ($tokenHash, &$confirmedEmail, &$errorMessage): bool {
            foreach ($queue as &$entry) {
                if (!is_array($entry)) continue;
                $storedHash = (string) ($entry['confirmation_hash'] ?? '');
                if ($storedHash === '' || !hash_equals($storedHash, $tokenHash)) continue;
                if ((int) ($entry['confirmation_expires_at'] ?? 0) < time()) {
                    $entry['status'] = 'confirmation_expired';
                    $entry['last_error'] = 'Confirmation link expired.';
                    $errorMessage = 'This confirmation link has expired. Please return to the journey page and sign up again.';
                    return false;
                }
                $confirmedEmail = strtolower((string) ($entry['email'] ?? ''));
                $entry['status'] = 'active';
                $entry['confirmed_at'] = gmdate('c');
                $entry['confirmation_hash'] = '';
                $entry['confirmation_expires_at'] = null;
                $entry['next_step'] = 1;
                $entry['next_at'] = time();
                $entry['attempts'] = 0;
                $entry['last_error'] = null;
                return true;
            }
            $errorMessage = 'This confirmation link is invalid or has already been used.';
            return false;
        });
    } catch (Throwable $error) {
        $matched = false;
        $errorMessage = 'Confirmation is temporarily unavailable. Please try the link again shortly.';
    }

    if ($matched && $confirmedEmail !== '') {
        $private = pu_journey_private_dir();
        $dataFile = $private . '/subscribers.json';
        $lock = fopen($private . '/subscribers.lock', 'c');
        if ($lock !== false && flock($lock, LOCK_EX)) {
            try {
                $items = pu_journey_read_json($dataFile);
                foreach ($items as &$row) {
                    if (!is_array($row) || strtolower((string) ($row['email'] ?? '')) !== $confirmedEmail) continue;
                    $row['status'] = 'subscribed';
                    $row['journey_status'] = 'active';
                    $row['journey_confirmed_at'] = gmdate('c');
                    $row['updated_at'] = gmdate('c');
                    break;
                }
                unset($row);
                pu_journey_write_json($dataFile, $items);
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        header('Location: https://bobsome1.com/unveiled/welcome.html', true, 303);
        exit;
    }
}

http_response_code(400);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Confirmation Link | Project Unveiled</title>
  <style>body{margin:0;background:#080705;color:#f4ead2;font:18px/1.6 Arial,sans-serif}main{max-width:760px;margin:12vh auto;padding:34px;border:1px solid #8a6a2c;background:#11100c}h1{font-family:Georgia,serif;color:#e4b951}a{color:#e4b951}</style>
</head>
<body><main><h1>The link did not open.</h1><p><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p><p><a href="/unveiled/">Return to the 7-Day Unveiled Journey</a></p></main></body>
</html>
