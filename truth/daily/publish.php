<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
$key = tw_require_admin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('POST required.'); }

function field(string $name, int $limit = 12000): string {
    return mb_substr(trim((string)($_POST[$name] ?? '')), 0, $limit);
}
function lines(string $name): array {
    $raw = field($name);
    $parts = preg_split('/\r?\n/u', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) { $p = trim($p); if ($p !== '') $out[] = $p; }
    return $out;
}

$draft = tw_json_read(tw_private_dir() . '/daily-draft.json');
if ($draft === []) { http_response_code(409); exit('No research draft exists.'); }

$published = [
    'headline' => field('headline', 500),
    'claim' => field('claim', 2000),
    'summary' => field('summary', 4000),
    'proven' => lines('proven'),
    'strongly_indicated' => lines('strongly_indicated'),
    'contradictions_missing_pieces' => lines('contradictions_missing_pieces'),
    'motives_incentives_who_benefits' => lines('motives_incentives_who_benefits'),
    'logic_common_sense' => lines('logic_common_sense'),
    'counterevidence_alternatives' => lines('counterevidence_alternatives'),
    'unknowns' => lines('unknowns'),
    'bobinated_opinion' => [
        'opinion' => field('bobinated_opinion', 5000),
        'reasoning' => lines('bobinated_reasoning'),
    ],
    'what_would_change_the_finding' => lines('what_would_change_the_finding'),
    'confidence' => in_array(field('confidence', 20), ['high','medium','low'], true) ? field('confidence', 20) : 'low',
    'sources' => $draft['sources'] ?? [],
    '_meta' => [
        'published_at_utc' => gmdate('c'),
        'reviewed_by_human' => true,
        'origin' => $draft['_meta']['candidate'] ?? null,
        'model' => $draft['_meta']['model'] ?? null,
    ],
];
if ($published['headline'] === '' || $published['claim'] === '') { http_response_code(422); exit('Headline and claim are required.'); }

$dataDir = dirname(__DIR__) . '/daily-data';
if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true) && !is_dir($dataDir)) { http_response_code(500); exit('Unable to create public daily-data directory.'); }
$stamp = gmdate('Ymd-His');
$archive = $dataDir . '/trial-' . $stamp . '.json';
tw_json_write($archive, $published);
copy($archive, $dataDir . '/latest.json');
@chmod($dataDir . '/latest.json', 0644);

header('Location: /truth/today.php?published=1', true, 303);
exit;
