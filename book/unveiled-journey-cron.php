<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/unveiled-journey-lib.php';
$messages = require __DIR__ . '/unveiled-journey-content.php';

if (pu_journey_mailing_address() === '') {
    fwrite(STDERR, "Journey paused: create site-private/project-unveiled/mailing-address.txt with a valid postal address.\n");
    exit(2);
}

$subscriberRows = pu_journey_read_json(pu_journey_private_dir() . '/subscribers.json');
$subscriberStatus = [];
foreach ($subscriberRows as $row) {
    if (!is_array($row)) continue;
    $email = strtolower((string) ($row['email'] ?? ''));
    if ($email !== '') $subscriberStatus[$email] = (string) ($row['status'] ?? '');
}

$sent = 0;
$failed = 0;
$stopped = 0;
$now = time();

try {
    pu_journey_with_queue(function (array &$queue) use ($messages, $subscriberStatus, $now, &$sent, &$failed, &$stopped): void {
        foreach ($queue as &$entry) {
            if ($sent + $failed >= 25) break;
            if (!is_array($entry) || (string) ($entry['status'] ?? '') !== 'active') continue;
            if ((int) ($entry['next_at'] ?? PHP_INT_MAX) > $now) continue;

            $email = strtolower((string) ($entry['email'] ?? ''));
            if (!in_array(($subscriberStatus[$email] ?? ''), ['subscribed', 'pending_confirmation'], true)) {
                $entry['status'] = 'stopped';
                $entry['stopped_at'] = gmdate('c');
                $entry['last_error'] = 'Subscriber is not active.';
                $stopped++;
                continue;
            }

            $step = (int) ($entry['next_step'] ?? 1);
            if (!isset($messages[$step])) {
                $entry['status'] = 'complete';
                $entry['completed_at'] = gmdate('c');
                $entry['next_at'] = null;
                continue;
            }

            $firstName = trim((string) ($entry['first_name'] ?? 'Friend'));
            if ($firstName === '') $firstName = 'Friend';
            $body = str_replace('[FIRST_NAME]', $firstName, (string) $messages[$step]['body']);
            $body .= pu_journey_footer((string) ($entry['unsubscribe_token'] ?? ''));

            if (pu_journey_mail($email, (string) $messages[$step]['subject'], $body, true)) {
                $entry['last_sent_step'] = $step;
                $entry['last_sent_at'] = gmdate('c');
                $entry['attempts'] = 0;
                $entry['last_error'] = null;
                $entry['next_step'] = $step + 1;
                if ($step >= 8) {
                    $entry['status'] = 'complete';
                    $entry['completed_at'] = gmdate('c');
                    $entry['next_at'] = null;
                } else {
                    $entry['next_at'] = $now + 86400;
                }
                $sent++;
            } else {
                $entry['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;
                $entry['last_error'] = 'mail() returned false at ' . gmdate('c');
                $entry['next_at'] = $now + 3600;
                $failed++;
            }
        }
        unset($entry);
    });
} catch (Throwable $error) {
    fwrite(STDERR, 'Journey error: ' . $error->getMessage() . "\n");
    exit(1);
}

echo "Journey run complete: {$sent} sent, {$failed} failed, {$stopped} stopped.\n";
