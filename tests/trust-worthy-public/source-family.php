<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/truth/lib/trust-worthy-deep.php';

$check = static function(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$check(tw_deep_source_family('https://www.example.com/a') === 'example.com', 'www prefix must collapse into the same source family');
$check(tw_deep_source_family('https://m.example.com/b') === 'example.com', 'mobile prefix must collapse into the same source family');
$check(tw_deep_source_family('https://news.bbc.co.uk/story') === 'bbc.co.uk', 'common country-code second level must preserve registrable family');
$check(tw_deep_source_family('https://constitution.congress.gov/browse/article-3/') === 'congress.gov', 'government subdomain must collapse to its domain family');
$check(tw_deep_source_family('not-a-url') === '', 'invalid URLs must not create a source family');

$metrics = tw_deep_family_metrics([
    ['url'=>'https://www.example.com/a','family'=>'example.com'],
    ['url'=>'https://m.example.com/b','family'=>'example.com'],
    ['url'=>'https://constitution.congress.gov/a','family'=>'congress.gov'],
]);
$check(($metrics['family_count'] ?? 0) === 2, 'multiple URLs from one domain family must not inflate diversity');

$cfg = tw_deep_config();
$check((int)($cfg['minimum_source_families'] ?? 0) >= 3, 'overall family floor must be at least three');
$check((int)($cfg['minimum_corroboration_families'] ?? 0) >= 2, 'corroboration must span at least two source families');

echo "Deep source-family classification test passed.\n";
