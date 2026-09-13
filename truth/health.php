<?php
declare(strict_types=1);

header_remove('X-Powered-By');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'");

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_SLASHES) . "\n";
    exit;
}

require_once __DIR__ . '/lib/trust-worthy-ai.php';

$privateDir = tw_private_dir();
$questionSecret = tw_question_secret_readonly();
$ready = PHP_VERSION_ID >= 80100
    && function_exists('ctype_digit')
    && function_exists('curl_init')
    && function_exists('mb_check_encoding')
    && function_exists('mb_strlen')
    && function_exists('mb_substr')
    && is_dir($privateDir)
    && is_writable($privateDir)
    && tw_openai_key_readonly() !== ''
    && $questionSecret !== ''
    && tw_ai_private_storage_ready($questionSecret);

http_response_code($ready ? 200 : 503);

if ($method === 'HEAD') exit;

echo json_encode([
    'service' => 'truth-on-trial',
    'status' => $ready ? 'ready' : 'unavailable',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
