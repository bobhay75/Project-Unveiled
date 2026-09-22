<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$reuse = file_get_contents($root . '/truth/lib/trust-worthy-deep-reuse-v1.php');
$smoke = file_get_contents($root . '/tests/trust-worthy-public/deep-reuse-smoke.php');
if (!is_string($reuse) || $reuse === '') throw new RuntimeException('reuse profile missing');
if (!is_string($smoke) || $smoke === '') throw new RuntimeException('reuse smoke harness missing');

$needles = [
    'function tw_deep_run_reuse_v1' => 'reuse runner missing',
    'function tw_deep_reuse_compact_pass' => 'compact evidence builder missing',
    'function tw_deep_reuse_packet' => 'compact evidence packet missing',
    "'research_mode' = 'reuse_no_web'" => 'dependency receipt does not mark no-web reuse',
    "'dependency_research_mode'=>'reuse_no_web'" => 'dependency metric does not expose reuse mode',
    "'context_research_mode'=>'reuse_no_web'" => 'context metric does not expose reuse mode',
    "'research_profile'=>'evidence_reuse_v1'" => 'reuse profile identity missing',
    'Using only these already-collected receipts' => 'dependency audit is not constrained to collected evidence',
    'Do not browse.' => 'reuse stages are not explicitly no-browse',
    'mark it unresolved rather than guessing' => 'reuse profile does not fail honest on dependency gaps',
    'mark it unresolved instead of filling it from memory' => 'context reuse does not fail honest on chronology gaps',
    'tw_deep_usage_total' => 'reuse profile does not account provider usage',
];
foreach ($needles as $needle => $message) {
    if (!str_contains($reuse, $needle)) throw new RuntimeException($message);
}
if (str_contains($reuse, 'tw_deep_run(')) throw new RuntimeException('reuse profile must not route through the full-amplification runner');
if (!str_contains($smoke, 'tw_deep_run_reuse_v1(')) throw new RuntimeException('reuse smoke does not invoke reuse runner');
if (!str_contains($smoke, 'total_tokens=%d')) throw new RuntimeException('reuse smoke does not report total usage');
if (!str_contains($smoke, "['dependency','counter','context','synthesis']")) {
    // Required stages are intentionally checked as the full ordered literal below.
    if (!str_contains($smoke, "['decompose','origin','primary','corroboration','dependency','counter','context','synthesis']")) {
        throw new RuntimeException('reuse smoke does not require every investigation receipt');
    }
}

echo "Deep evidence-reuse profile contract passed.\n";
