<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/truth/daily/story-scout.php';

function scout_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$strong = tw_story_scout_score([
    'claim' => 'A government enacted a newly reported policy affecting a large population.',
    'domain' => 'politics',
    'source_url' => 'https://example.gov/record',
    'published_at_utc' => '2026-09-22T12:00:00Z',
    'public_importance' => 90,
    'factual_testability' => 95,
    'evidence_availability' => 90,
    'novelty' => 80,
    'potential_harm' => 70,
    'virality' => 20,
]);
scout_check($strong['eligible_for_editor_review'] === true, 'strong testable claim should reach editor review');
scout_check($strong['truth_status'] === 'UNRESOLVED', 'Scout must not decide truth');
scout_check($strong['publication_status'] === 'HOLD', 'Scout must not auto-publish');
scout_check($strong['priority_score'] > 0, 'strong candidate should receive priority score');

$viralButUntestable = tw_story_scout_score([
    'claim' => 'A sensational viral claim with no presently retrievable evidence.',
    'domain' => 'other',
    'source_url' => 'https://example.com/post',
    'public_importance' => 70,
    'factual_testability' => 20,
    'evidence_availability' => 10,
    'novelty' => 90,
    'potential_harm' => 80,
    'virality' => 100,
]);
scout_check($viralButUntestable['eligible_for_editor_review'] === false, 'virality must not override evidence floor');
scout_check($viralButUntestable['priority_score'] === 0, 'ineligible candidate must fail closed');

$duplicateA = [
    'claim' => 'Officials announced a new policy today.',
    'domain' => 'politics',
    'source_url' => 'https://example.gov/a',
    'factual_testability' => 90,
    'evidence_availability' => 80,
    'public_importance' => 80,
    'novelty' => 70,
];
$duplicateB = $duplicateA;
$duplicateB['claim'] = '  OFFICIALS announced a new policy today!  ';
$duplicateB['source_url'] = 'https://example.org/repeat';
$duplicateB['public_importance'] = 60;
$ranked = tw_story_scout_rank([$duplicateA, $duplicateB]);
scout_check(count($ranked) === 1, 'normalized duplicate claims must collapse');
scout_check($ranked[0]['source_url'] === 'https://example.gov/a', 'best duplicate should survive');

$badUrl = tw_story_scout_score([
    'claim' => 'Test claim',
    'domain' => 'science',
    'source_url' => 'http://example.com/not-secure',
    'factual_testability' => 100,
    'evidence_availability' => 100,
]);
scout_check($badUrl['eligible_for_editor_review'] === false, 'Scout source must be HTTPS');

echo "Story Scout contract passed.\n";
