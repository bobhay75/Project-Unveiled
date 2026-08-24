<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/meat-desk.php';
require __DIR__ . '/traditions-of-men.php';
require __DIR__ . '/queue-rotation.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$privateDir = tw_private_dir();
$candidates = tw_build_full_meat_queue($privateDir, 18);

$payload = [
    'refreshed_at_utc' => gmdate('c'),
    'source_count' => 0,
    'raw_item_count' => 0,
    'candidate_count' => count($candidates),
    'ranking_mode' => 'user-and-meat-library',
    'editorial_priority' => [
        'real user questions first',
        'consequential evergreen questions people need answered',
        'full Traditions of Men timeline rotated across historical eras and modern church culture',
        'current reporting is evidence to examine during an investigation, not the editorial agenda'
    ],
    'news_policy' => 'Routine RSS headlines do not occupy Truth Trial candidate slots. Current sources are searched only when a selected investigation needs them.',
    'errors' => [],
    'candidates' => $candidates,
];

$path = $privateDir . '/daily-candidates.json';
tw_json_write($path, $payload);

echo "Saved " . count($candidates) . " user-and-Meat-Desk candidates to {$path}\n";
echo "The queue rotates across the full library daily; routine news does not consume a slot.\n";
echo "Private desk: https://bobsome1.com/truth/daily/desk.php?key=" . tw_admin_token() . "\n";
