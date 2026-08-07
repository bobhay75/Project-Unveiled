<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false]);
    exit;
}

$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > 20000) {
    http_response_code(413);
    echo json_encode(['ok' => false]);
    exit;
}

$allowedHosts = ['bobsome1.com', 'www.bobsome1.com'];
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
$sourceUrl = $origin !== '' ? $origin : $referer;
if ($sourceUrl !== '') {
    $host = strtolower((string)(parse_url($sourceUrl, PHP_URL_HOST) ?? ''));
    if ($host !== '' && !in_array($host, $allowedHosts, true)) {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$allowedEvents = [
    'pageview', 'chapter_start', 'chapter_next', 'share_click',
    'support_page_click', 'paypal_click', 'book_complete'
];
$event = strtolower((string)($data['event'] ?? ''));
if (!in_array($event, $allowedEvents, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

function pu_clean(mixed $value, int $limit): string {
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', (string)$value) ?? '';
    if (function_exists('mb_substr')) return mb_substr($text, 0, $limit, 'UTF-8');
    return substr($text, 0, $limit);
}

$path = pu_clean($data['path'] ?? '', 240);
if ($path === '' || $path[0] !== '/') $path = '/';
$session = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($data['session'] ?? '')) ?? '';
$session = substr($session, 0, 80);
if (strlen($session) < 8) $session = 'anonymous';

$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)) ?: dirname(__DIR__);
$privateRoot = dirname($docRoot) . '/site-private/project-unveiled-analytics';
$dataDir = $privateRoot . '/data';
if (!is_dir($dataDir) && !@mkdir($dataDir, 0750, true)) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}

$record = [
    't' => gmdate('c'),
    'event' => $event,
    'path' => $path,
    'title' => pu_clean($data['title'] ?? '', 240),
    'session' => $session,
    'chapter' => max(0, min(13, (int)($data['chapter'] ?? 0))),
    'referrer' => pu_clean($data['referrer'] ?? '', 240),
    'source' => pu_clean($data['source'] ?? '', 120),
    'medium' => pu_clean($data['medium'] ?? '', 120),
    'campaign' => pu_clean($data['campaign'] ?? '', 120),
    'content' => pu_clean($data['content'] ?? '', 120),
    'term' => pu_clean($data['term'] ?? '', 120),
    'target' => pu_clean($data['target'] ?? '', 300),
    'label' => pu_clean($data['label'] ?? '', 180),
];

$file = $dataDir . '/events-' . gmdate('Y-m-d') . '.jsonl';
$line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
    exit;
}
@chmod($file, 0640);
echo json_encode(['ok' => true]);
