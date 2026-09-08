<?php
declare(strict_types=1);

function tw_private_dir(): string {
    return dirname(__DIR__, 3) . '/site-private/trust-worthy';
}

function tw_ensure_private_dir(): bool {
    $dir = tw_private_dir();
    return is_dir($dir) ? is_writable($dir) : (mkdir($dir, 0750, true) && chmod($dir, 0750));
}

function tw_questions_file(): string {
    return tw_private_dir() . '/questions.json';
}

function tw_read_questions(): array {
    $file = tw_questions_file();
    if (!is_file($file)) return [];
    $items = json_decode((string)file_get_contents($file), true);
    return is_array($items) ? array_values($items) : [];
}

function tw_write_questions(array $items): bool {
    if (!tw_ensure_private_dir()) return false;
    $file = tw_questions_file();
    $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode(array_values($items), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    @chmod($file, 0640);
    return true;
}

function tw_with_queue_lock(callable $callback) {
    if (!tw_ensure_private_dir()) return false;
    $handle = fopen(tw_private_dir() . '/questions.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) { if (is_resource($handle)) fclose($handle); return false; }
    @chmod(tw_private_dir() . '/questions.lock', 0640);
    try { return $callback(); }
    finally { flock($handle, LOCK_UN); fclose($handle); }
}

function tw_clean(string $value, int $limit): string {
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return mb_substr($value, 0, $limit);
}

function tw_enqueue_question(array $input, string $ip): array {
    if (!tw_ensure_private_dir()) return ['ok'=>false, 'message'=>'Private storage is unavailable.'];
    $secretFile = tw_private_dir() . '/question-secret.txt';
    if (!is_file($secretFile)) {
        file_put_contents($secretFile, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod($secretFile, 0640);
    }
    $secret = trim((string)file_get_contents($secretFile));
    $item = [
        'id' => bin2hex(random_bytes(8)),
        'topic' => tw_clean((string)($input['topic'] ?? 'other'), 64),
        'question' => tw_clean((string)($input['question'] ?? ''), 3000),
        'context' => tw_clean((string)($input['context'] ?? ''), 2000),
        'name' => tw_clean((string)($input['name'] ?? ''), 100),
        'email' => tw_clean((string)($input['email'] ?? ''), 190),
        'status' => 'queued',
        'submitted_at_utc' => gmdate('c'),
        'ip_hash' => hash_hmac('sha256', $ip, $secret ?: 'unavailable'),
        'research' => [
            'provider' => null,
            'model' => null,
            'proposition' => null,
            'evidence' => [],
            'counterevidence' => [],
            'facts' => [],
            'inferences' => [],
            'disputed' => [],
            'unknowns' => [],
            'limitations' => [],
            'verification_paths' => [],
            'draft_finding' => null,
            'human_verdict' => null,
            'human_reviewed_at_utc' => null,
        ],
    ];
    $saved = tw_with_queue_lock(function() use ($item): bool {
        $items = tw_read_questions();
        $items[] = $item;
        return tw_write_questions($items);
    });
    return $saved
        ? ['ok'=>true, 'item'=>$item]
        : ['ok'=>false, 'message'=>'Your question could not be saved.'];
}

function tw_find_question(string $id): ?array {
    foreach (tw_read_questions() as $index => $item) {
        if (is_array($item) && hash_equals((string)($item['id'] ?? ''), $id)) return [$index, $item];
    }
    return null;
}

function tw_replace_question(int $index, array $item): bool {
    return (bool)tw_with_queue_lock(function() use ($index, $item): bool {
        $items = tw_read_questions();
        if (!isset($items[$index])) return false;
        $items[$index] = $item;
        return tw_write_questions($items);
    });
}
