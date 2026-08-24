<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
$config = require __DIR__ . '/config.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$items = []; $errors = [];
foreach ($config['feeds'] as $feed) {
    try {
        $xml = tw_fetch_feed((string)$feed['url']);
        $parsed = tw_parse_feed($xml, (string)$feed['name'], (int)$config['max_items_per_feed']);
        $items = array_merge($items, $parsed);
        echo sprintf("OK   %-18s %d items\n", $feed['name'], count($parsed));
    } catch (Throwable $e) {
        $errors[] = ['source' => $feed['name'], 'error' => $e->getMessage()];
        echo sprintf("FAIL %-18s %s\n", $feed['name'], $e->getMessage());
    }
}

$candidates = tw_rank_candidates($items, (int)$config['lookback_hours'], (int)$config['max_candidates']);
$payload = [
    'refreshed_at_utc' => gmdate('c'),
    'source_count' => count($config['feeds']),
    'raw_item_count' => count($items),
    'candidate_count' => count($candidates),
    'errors' => $errors,
    'candidates' => $candidates,
];
$path = tw_private_dir() . '/daily-candidates.json';
tw_json_write($path, $payload);

echo "\nSaved " . count($candidates) . " ranked candidates to {$path}\n";
echo "Private desk: https://bobsome1.com/truth/daily/desk.php?key=" . tw_admin_token() . "\n";
