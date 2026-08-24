<?php
declare(strict_types=1);

// Shared-hosting-safe secret loading. Environment variables still take priority,
// but cPanel users may store the key outside public_html at the private path below.
$privateKeyFile = dirname(__DIR__, 3) . '/site-private/trust-worthy/openai-key.txt';
if (!getenv('TRUST_WORTHY_OPENAI_API_KEY') && is_file($privateKeyFile)) {
    $privateKey = trim((string)file_get_contents($privateKeyFile));
    if ($privateKey !== '') putenv('TRUST_WORTHY_OPENAI_API_KEY=' . $privateKey);
    unset($privateKey);
}

require __DIR__ . '/lib.php';
$config = require __DIR__ . '/config.php';
$key = tw_require_admin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('POST required.'); }
$id = trim((string)($_POST['id'] ?? ''));
$queue = tw_json_read(tw_private_dir() . '/daily-candidates.json');
$candidate = null;
foreach (($queue['candidates'] ?? []) as $row) {
    if (($row['id'] ?? '') === $id) { $candidate = $row; break; }
}
if (!is_array($candidate)) { http_response_code(404); exit('Candidate not found. Refresh the queue and try again.'); }

try {
    $report = tw_run_investigation($candidate, (string)$config['openai_model']);
    tw_json_write(tw_private_dir() . '/daily-draft.json', $report);
    header('Location: /truth/daily/desk.php?key=' . rawurlencode($key) . '&generated=1', true, 303);
    exit;
} catch (Throwable $e) {
    tw_json_write(tw_private_dir() . '/daily-last-error.json', ['at_utc' => gmdate('c'), 'message' => $e->getMessage()]);
    header('Location: /truth/daily/desk.php?key=' . rawurlencode($key) . '&error=' . rawurlencode($e->getMessage()), true, 303);
    exit;
}
