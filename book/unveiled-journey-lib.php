<?php
declare(strict_types=1);

function pu_journey_private_dir(): string
{
    return dirname(__DIR__, 2) . '/site-private/project-unveiled';
}

function pu_journey_ensure_private_dir(): string
{
    $directory = pu_journey_private_dir();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Private storage is unavailable.');
    }
    return $directory;
}

function pu_journey_read_json(string $file): array
{
    if (!is_file($file)) return [];
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function pu_journey_write_json(string $file, array $data): bool
{
    $temporary = $file . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($temporary, $json, LOCK_EX) === false) return false;
    @chmod($temporary, 0640);
    if (!@rename($temporary, $file)) {
        @unlink($temporary);
        return false;
    }
    return true;
}

function pu_journey_with_queue(callable $callback)
{
    $directory = pu_journey_ensure_private_dir();
    $queueFile = $directory . '/journey-queue.json';
    $lock = fopen($directory . '/journey-queue.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) fclose($lock);
        throw new RuntimeException('Journey queue is unavailable.');
    }

    try {
        $queue = pu_journey_read_json($queueFile);
        $result = $callback($queue);
        if (!pu_journey_write_json($queueFile, $queue)) {
            throw new RuntimeException('Journey queue could not be saved.');
        }
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function pu_journey_email_key(string $email): string
{
    return hash('sha256', strtolower(trim($email)));
}

function pu_journey_mailing_address(): string
{
    $file = pu_journey_private_dir() . '/mailing-address.txt';
    return is_file($file) ? trim((string) file_get_contents($file)) : '';
}

function pu_smtp_response($socket): int
{
    $code = 0;
    while (($line = fgets($socket, 4096)) !== false) {
        if (preg_match('/^(\d{3})([ -])/', $line, $match)) {
            $code = (int) $match[1];
            if ($match[2] === ' ') break;
        }
    }
    return $code;
}

function pu_smtp_command($socket, string $command, array $expected): bool
{
    if (fwrite($socket, $command . "\r\n") === false) return false;
    return in_array(pu_smtp_response($socket), $expected, true);
}

function pu_smtp_mail(string $email, string $subject, string $body): bool
{
    $configFile = pu_journey_private_dir() . '/smtp.json';
    $config = is_file($configFile) ? json_decode((string) file_get_contents($configFile), true) : null;
    if (!is_array($config)) return false;

    $host = (string) ($config['host'] ?? '');
    $port = (int) ($config['port'] ?? 465);
    $username = (string) ($config['username'] ?? '');
    $password = (string) ($config['password'] ?? '');
    $from = (string) ($config['from'] ?? $username);
    if ($host === '' || $username === '' || $password === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) return false;

    $socket = @stream_socket_client('ssl://' . $host . ':' . $port, $errorNumber, $errorMessage, 20);
    if (!is_resource($socket)) return false;
    stream_set_timeout($socket, 20);

    try {
        if (pu_smtp_response($socket) !== 220) return false;
        if (!pu_smtp_command($socket, 'EHLO bobsome1.com', [250])) return false;
        if (!pu_smtp_command($socket, 'AUTH LOGIN', [334])) return false;
        if (!pu_smtp_command($socket, base64_encode($username), [334])) return false;
        if (!pu_smtp_command($socket, base64_encode($password), [235])) return false;
        if (!pu_smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250])) return false;
        if (!pu_smtp_command($socket, 'RCPT TO:<' . $email . '>', [250, 251])) return false;
        if (!pu_smtp_command($socket, 'DATA', [354])) return false;

        $safeSubject = str_replace(["\r", "\n"], '', $subject);
        $normalizedBody = str_replace(["\r\n", "\r"], "\n", wordwrap($body, 78));
        $normalizedBody = preg_replace('/^\./m', '..', $normalizedBody) ?? $normalizedBody;
        $message = implode("\r\n", [
            'Date: ' . date(DATE_RFC2822),
            'From: Robert Hayes - Project Unveiled <' . $from . '>',
            'Reply-To: ' . $from,
            'To: <' . $email . '>',
            'Subject: ' . $safeSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            str_replace("\n", "\r\n", $normalizedBody)
        ]);
        if (fwrite($socket, $message . "\r\n.\r\n") === false) return false;
        if (pu_smtp_response($socket) !== 250) return false;
        pu_smtp_command($socket, 'QUIT', [221]);
        return true;
    } finally {
        fclose($socket);
    }
}

function pu_journey_mail(string $email, string $subject, string $body, bool $requireAddress = true): bool
{
    $address = pu_journey_mailing_address();
    if ($requireAddress && $address === '') return false;
    if (function_exists('mb_encode_mimeheader')) {
        $subject = mb_encode_mimeheader($subject, 'UTF-8');
    }
    return pu_smtp_mail($email, $subject, $body);
}

function pu_journey_footer(string $unsubscribeToken): string
{
    $address = pu_journey_mailing_address();
    $unsubscribe = 'https://bobsome1.com/book/unsubscribe.php?token=' . rawurlencode($unsubscribeToken);
    return "\n\n---\nProject Unveiled\n{$address}\nhttps://bobsome1.com\n\nYou requested this email at bobsome1.com.\nUnsubscribe: {$unsubscribe}\n";
}
