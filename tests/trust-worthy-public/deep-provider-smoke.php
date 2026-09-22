<?php
declare(strict_types=1);

/**
 * Owner-run provider smoke test for the Trust-Worthy Deep engine.
 *
 * This test never prints API keys, private file contents, provider response
 * bodies, or source URLs. It is intentionally excluded from automated CI
 * because GitHub Actions does not receive the production research secret.
 *
 * Usage on the shared host:
 *   TW_DEEP_PRIVATE_DIR=/home/bobsome1/site-private/trust-worthy \
 *     php tests/trust-worthy-public/deep-provider-smoke.php basic
 *
 *   TW_DEEP_PRIVATE_DIR=/home/bobsome1/site-private/trust-worthy \
 *     php tests/trust-worthy-public/deep-provider-smoke.php full
 */

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
require_once dirname(__DIR__, 2) . '/truth/lib/trust-worthy-deep.php';

$mode = strtolower(trim((string)($argv[1] ?? 'basic')));
if (!in_array($mode, ['basic', 'full'], true)) {
    fwrite(STDERR, "Mode must be basic or full.\n");
    exit(2);
}

if (tw_openai_key_readonly() === '') {
    fwrite(STDERR, "Research provider key is not available through the supplied private directory.\n");
    exit(3);
}

$printPass = static function(string $name, array $pass): void {
    if (!($pass['ok'] ?? false)) {
        $diagnostic = is_string($pass['diagnostic'] ?? null) ? $pass['diagnostic'] : 'none';
        fwrite(STDERR, strtoupper($name) . " FAILED; diagnostic={$diagnostic}\n");
        exit(4);
    }
    $usage = is_array($pass['usage'] ?? null) ? $pass['usage'] : [];
    $sources=is_array($pass['sources'] ?? null) ? $pass['sources'] : [];
    $families=tw_deep_family_metrics($sources);
    printf(
        "%s PASS; sources=%d; families=%d; input_tokens=%d; output_tokens=%d; reasoning_tokens=%d\n",
        strtoupper($name),
        count($sources),
        (int)($families['family_count'] ?? 0),
        (int)($usage['input_tokens'] ?? 0),
        (int)($usage['output_tokens'] ?? 0),
        (int)($usage['reasoning_tokens'] ?? 0)
    );
};

$system = tw_deep_base_system();
$noWeb = tw_deep_provider_pass(
    $system,
    "Provider smoke test. Do not browse. Reply with a short sentence confirming the request can be processed without issuing a factual verdict.",
    false
);
$printPass('non-web provider', $noWeb);

$web = tw_deep_provider_pass(
    $system,
    "Provider web-search smoke test. Search for an official United States government source describing 28 U.S.C. § 1. Return only a concise description of what kind of source was found; do not quote it at length.",
    true
);
$printPass('web provider', $web);

if ($mode === 'basic') {
    echo "BASIC DEEP PROVIDER SMOKE PASSED. No full investigation was run.\n";
    exit(0);
}

$claim = 'The United States Constitution itself fixes the Supreme Court at exactly nine justices.';
$receipts = [];
$result = tw_deep_run($claim, 'Owner-run benign release smoke test. Prefer official primary records.', static function(array $receipt) use (&$receipts): void {
    $receipts[] = $receipt;
    printf(
        "RECEIPT stage=%s completed=%s sources=%d families=%d floor=%s\n",
        preg_replace('/[^a-z0-9_-]/i', '', (string)($receipt['stage'] ?? 'unknown')),
        ($receipt['completed'] ?? false) ? 'yes' : 'no',
        (int)($receipt['source_count'] ?? 0),
        (int)($receipt['source_family_count'] ?? 0),
        array_key_exists('evidence_floor_met', $receipt) ? (($receipt['evidence_floor_met'] ?? false) ? 'met' : 'not_met') : 'n/a'
    );
});

if (!($result['ok'] ?? false)) {
    fwrite(STDERR, "FULL DEEP INVESTIGATION FAILED at stage=" . (string)($result['stage'] ?? 'unknown') . ".\n");
    exit(5);
}

$requiredStages = ['decompose','origin','primary','corroboration','counter','context','synthesis'];
$seen = [];
foreach ($receipts as $receipt) {
    if (is_array($receipt) && is_string($receipt['stage'] ?? null)) $seen[$receipt['stage']] = true;
}
foreach ($requiredStages as $stage) {
    if (!isset($seen[$stage])) {
        fwrite(STDERR, "FULL DEEP INVESTIGATION MISSING RECEIPT: {$stage}\n");
        exit(6);
    }
}

printf(
    "FULL DEEP PROVIDER SMOKE PASSED; unique_sources=%d; source_families=%d; counter_sources=%d; counter_families=%d; corroboration_sources=%d; corroboration_families=%d; independence=%s; evidence_floor=%s; verdict=%s; probability=%s\n",
    (int)($result['metrics']['unique_sources'] ?? 0),
    (int)($result['metrics']['source_families'] ?? 0),
    (int)($result['metrics']['counter_sources'] ?? 0),
    (int)($result['metrics']['counter_families'] ?? 0),
    (int)($result['metrics']['corroboration_sources'] ?? 0),
    (int)($result['metrics']['corroboration_families'] ?? 0),
    preg_replace('/[^a-z0-9_-]/i','',(string)($result['metrics']['source_independence_status'] ?? 'unknown')),
    ($result['metrics']['evidence_floor_met'] ?? false) ? 'met' : 'not_met',
    preg_replace('/[^A-Z _—-]/u', '', strtoupper((string)($result['verdict'] ?? 'UNKNOWN'))),
    is_int($result['probability'] ?? null) ? (string)$result['probability'] : 'NONE'
);
