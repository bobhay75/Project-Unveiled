<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

function suppression_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function suppression_remove_tree(string $path): void
{
    if ($path === '' || !str_contains(basename($path), 'project-unveiled-suppression-')) {
        throw new RuntimeException('Refusing to remove an unexpected suppression path.');
    }
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

function suppression_run_cron(string $cronPath): array
{
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, '-q', $cronPath], $descriptors, $pipes);
    suppression_assert(is_resource($process), 'Could not start the synthetic Journey cron.');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    return ['status' => $status, 'stdout' => (string)$stdout, 'stderr' => (string)$stderr];
}

$temporaryRoot = sys_get_temp_dir() . '/project-unveiled-suppression-' . bin2hex(random_bytes(8));
$bookRoot = $temporaryRoot . '/public_html/book';
$privateRoot = $temporaryRoot . '/site-private/project-unveiled';

try {
    suppression_assert(mkdir($bookRoot, 0700, true), 'Could not create the isolated public fixture.');
    suppression_assert(mkdir($privateRoot, 0700, true), 'Could not create the isolated private fixture.');
    foreach (['unveiled-journey-lib.php', 'unveiled-journey-content.php', 'unveiled-journey-cron.php'] as $name) {
        suppression_assert(copy($root . '/book/' . $name, $bookRoot . '/' . $name), "Could not copy {$name} into the isolated fixture.");
    }

    $email = 'owner-suppression-test@example.invalid';
    $token = str_repeat('b', 48);
    $subscribers = [[
        'email' => $email,
        'status' => 'unsubscribed',
        'unsubscribe_token' => $token,
        'unsubscribed_at' => gmdate('c', time() - 120),
    ]];
    $queueKey = hash('sha256', $email);
    $queue = [
        $queueKey => [
            'first_name' => 'Owner Test',
            'email' => $email,
            'status' => 'active',
            'unsubscribe_token' => $token,
            'next_step' => 2,
            'next_at' => time() - 60,
            'attempts' => 0,
            'last_error' => null,
        ],
    ];
    suppression_assert(file_put_contents($privateRoot . '/mailing-address.txt', "Synthetic test fixture\n", LOCK_EX) !== false, 'Could not create the synthetic mailing-address gate.');
    suppression_assert(file_put_contents($privateRoot . '/subscribers.json', json_encode($subscribers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false, 'Could not create the synthetic subscriber state.');
    suppression_assert(file_put_contents($privateRoot . '/journey-queue.json', json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false, 'Could not create the synthetic Journey queue.');

    $first = suppression_run_cron($bookRoot . '/unveiled-journey-cron.php');
    suppression_assert($first['status'] === 0, 'Synthetic Journey cron failed: ' . trim($first['stderr']));
    suppression_assert(str_contains($first['stdout'], '0 sent, 0 failed, 1 stopped'), 'Cron did not stop the unsubscribed entry before mail was attempted.');
    $after = json_decode((string)file_get_contents($privateRoot . '/journey-queue.json'), true);
    suppression_assert(is_array($after) && is_array($after[$queueKey] ?? null), 'Cron did not preserve the synthetic queue entry.');
    $entry = $after[$queueKey];
    suppression_assert(($entry['status'] ?? '') === 'stopped', 'Unsubscribed queue entry remains active.');
    suppression_assert(($entry['last_error'] ?? '') === 'Subscriber is not active.', 'Stopped queue entry lacks the suppression reason.');
    suppression_assert(!array_key_exists('last_sent_at', $entry), 'Suppressed queue entry was marked as sent.');
    suppression_assert(!is_file($privateRoot . '/smtp.json'), 'Synthetic test unexpectedly created SMTP configuration.');

    $second = suppression_run_cron($bookRoot . '/unveiled-journey-cron.php');
    suppression_assert($second['status'] === 0, 'Second synthetic Journey cron failed: ' . trim($second['stderr']));
    suppression_assert(str_contains($second['stdout'], '0 sent, 0 failed, 0 stopped'), 'Stopped entry was not idempotent on the next cron opportunity.');

    echo "Journey suppression check passed: an unsubscribed due entry was stopped before SMTP and remained stopped on the next cron run.\n";
} finally {
    suppression_remove_tree($temporaryRoot);
}
