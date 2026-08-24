<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/compat.php';
require __DIR__ . '/trial-filter.php';
require __DIR__ . '/meat-desk.php';
$config = require __DIR__ . '/config.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$items = []; $errors = [];
foreach ($config['feeds'] as $feed) {
    try {
        $xml = tw_fetch_feed((string)$feed['url']);
        $parsed = tw_parse_feed_compat($xml, (string)$feed['name'], (int)$config['max_items_per_feed']);
        $items = array_merge($items, $parsed);
        echo sprintf("OK   %-18s %d items\n", $feed['name'], count($parsed));
    } catch (Throwable $e) {
        $errors[] = ['source' => $feed['name'], 'error' => $e->getMessage()];
        echo sprintf("FAIL %-18s %s\n", $feed['name'], $e->getMessage());
    }
}

$ranked = tw_rank_candidates($items, (int)$config['lookback_hours'], (int)$config['max_candidates']);
$newsCandidates = tw_trial_focus($ranked, 12);
$privateDir = tw_private_dir();
$candidates = tw_build_meat_queue($newsCandidates, $privateDir, 18);

$payload = [
    'refreshed_at_utc' => gmdate('c'),
    'source_count' => count($config['feeds']),
    'raw_item_count' => count($items),
    'candidate_count' => count($candidates),
    'ranking_mode' => 'need-to-know-meat-desk',
    'editorial_priority' => [
        'real user questions',
        'high-consequence evergreen questions',
        'current news only when it contributes meaningful evidence or context'
    ],
    'errors' => $errors,
    'candidates' => $candidates,
];
$path = $privateDir . '/daily-candidates.json';
tw_json_write($path, $payload);

echo "\nSaved " . count($candidates) . " need-to-know Meat Desk candidates to {$path}\n";
echo "Private desk: https://bobsome1.com/truth/daily/desk.php?key=" . tw_admin_token() . "\n";
