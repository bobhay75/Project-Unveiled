<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/legacy-migration.php';
require __DIR__ . '/meat-desk.php';
require __DIR__ . '/traditions-of-men.php';
require __DIR__ . '/queue-rotation.php';
if (PHP_SAPI !== 'cli') { tw_admin_private_headers(); http_response_code(404); exit; }

$privateDir = tw_private_dir();
$legacy = tw_daily_retire_legacy_private_artifacts($privateDir);
$staleCandidates = tw_daily_prune_expired_candidate_derivatives($privateDir);
$expiredDrafts = tw_prune_expired_private_daily_drafts();
$path = $privateDir . '/daily-candidates.json';
$candidates = tw_with_private_lock('daily-candidates', static function () use ($privateDir, $path): array {
    $candidates = tw_build_full_meat_queue($privateDir, 18);
    tw_json_write($path, [
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
    ]);
    return $candidates;
});

echo "Saved " . count($candidates) . " user-and-Meat-Desk candidates to {$path}\n";
echo "Removed {$expiredDrafts} expired private research draft(s).\n";
echo "Removed {$staleCandidates} expired private question candidate derivative(s) from the prior queue.\n";
echo "Retired {$legacy['retired']} obsolete sensitive Daily artifact(s); preserved {$legacy['migrated_drafts']} valid legacy draft(s).\n";
echo "The queue rotates across the full library daily; routine news does not consume a slot.\n";
echo "Run truth/daily/admin-url.php separately when a one-use private desk sign-in is needed.\n";
