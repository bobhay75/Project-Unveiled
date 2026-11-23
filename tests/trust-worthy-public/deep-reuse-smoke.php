<?php
declare(strict_types=1);

$privateDir = trim((string)getenv('TW_DEEP_PRIVATE_DIR'));
if ($privateDir === '' || $privateDir[0] !== '/' || str_contains($privateDir, "\0")) {
    fwrite(STDERR, "Set TW_DEEP_PRIVATE_DIR to the existing owner-private Trust-Worthy directory.\n");
    exit(2);
}
$realParent = realpath(dirname($privateDir));
if ($realParent === false || !is_dir($privateDir) || is_link($privateDir)) {
    fwrite(STDERR, "The supplied private directory is unavailable or unsafe.\n");
    exit(2);
}
$realPrivate = realpath($privateDir);
if ($realPrivate === false || str_starts_with($realPrivate, '/home/bobsome1/public_html')) {
    fwrite(STDERR, "Refusing to use a public or unresolved private directory.\n");
    exit(2);
}
define('TW_PRIVATE_DIR_OVERRIDE', $realPrivate);
require_once dirname(__DIR__, 2) . '/truth/lib/trust-worthy-deep-reuse-v1.php';

if (tw_openai_key_readonly() === '') {
    fwrite(STDERR, "Research provider key is not available through the supplied private directory.\n");
    exit(3);
}

$claim = 'The United States Constitution itself fixes the Supreme Court at exactly nine justices.';
$receipts = [];
$result = tw_deep_run_reuse_v1(
    $claim,
    'Owner-run benign optimization smoke test. Prefer official primary records and refuse unsupported chronology.',
    static function(array $receipt) use (&$receipts): void {
        $receipts[] = $receipt;
        $usage = is_array($receipt['usage'] ?? null) ? $receipt['usage'] : [];
        printf(
            "RECEIPT stage=%s completed=%s sources=%d families=%d mode=%s total_tokens=%d floor=%s\n",
            preg_replace('/[^a-z0-9_-]/i', '', (string)($receipt['stage'] ?? 'unknown')),
            ($receipt['completed'] ?? false) ? 'yes' : 'no',
            (int)($receipt['source_count'] ?? 0),
            (int)($receipt['source_family_count'] ?? 0),
            preg_replace('/[^a-z0-9_-]/i', '', (string)($receipt['research_mode'] ?? 'provider_default')),
            (int)($usage['total_tokens'] ?? 0),
            array_key_exists('evidence_floor_met', $receipt) ? (($receipt['evidence_floor_met'] ?? false) ? 'met' : 'not_met') : 'n/a'
        );
    }
);

if (!($result['ok'] ?? false)) {
    fwrite(STDERR, "REUSE DEEP INVESTIGATION FAILED at stage=" . (string)($result['stage'] ?? 'unknown') . ".\n");
    exit(5);
}

$requiredStages = ['decompose','origin','primary','corroboration','dependency','counter','context','synthesis'];
$seen = [];
foreach ($receipts as $receipt) {
    if (is_array($receipt) && is_string($receipt['stage'] ?? null)) $seen[$receipt['stage']] = true;
}
foreach ($requiredStages as $stage) {
    if (!isset($seen[$stage])) {
        fwrite(STDERR, "REUSE DEEP INVESTIGATION MISSING RECEIPT: {$stage}\n");
        exit(6);
    }
}

$metrics = is_array($result['metrics'] ?? null) ? $result['metrics'] : [];
$usage = is_array($metrics['usage'] ?? null) ? $metrics['usage'] : [];
printf(
    "REUSE DEEP PROVIDER SMOKE PASSED; unique_sources=%d; source_families=%d; dependency_audited_sources=%d; dependency_mode=%s; context_mode=%s; counter_sources=%d; counter_families=%d; corroboration_sources=%d; corroboration_families=%d; independence=%s; input_tokens=%d; output_tokens=%d; reasoning_tokens=%d; total_tokens=%d; evidence_floor=%s; verdict=%s; probability=%s\n",
    (int)($metrics['unique_sources'] ?? 0),
    (int)($metrics['source_families'] ?? 0),
    (int)($metrics['dependency_audited_sources'] ?? 0),
    preg_replace('/[^a-z0-9_-]/i','',(string)($metrics['dependency_research_mode'] ?? 'unknown')),
    preg_replace('/[^a-z0-9_-]/i','',(string)($metrics['context_research_mode'] ?? 'unknown')),
    (int)($metrics['counter_sources'] ?? 0),
    (int)($metrics['counter_families'] ?? 0),
    (int)($metrics['corroboration_sources'] ?? 0),
    (int)($metrics['corroboration_families'] ?? 0),
    preg_replace('/[^a-z0-9_-]/i','',(string)($metrics['source_independence_status'] ?? 'unknown')),
    (int)($usage['input_tokens'] ?? 0),
    (int)($usage['output_tokens'] ?? 0),
    (int)($usage['reasoning_tokens'] ?? 0),
    (int)($usage['total_tokens'] ?? 0),
    ($metrics['evidence_floor_met'] ?? false) ? 'met' : 'not_met',
    preg_replace('/[^A-Z _—-]/u', '', strtoupper((string)($result['verdict'] ?? 'UNKNOWN'))),
    is_int($result['probability'] ?? null) ? (string)$result['probability'] : 'NONE'
);
