<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Private runtime migration is CLI-only.\n");
    exit(2);
}
if (($argc ?? 0) !== 2 || !in_array($argv[1] ?? '', ['--pre-sync', '--post-sync'], true)) {
    fwrite(STDERR, "Usage: migrate-intake-permissions.php --pre-sync|--post-sync\n");
    exit(2);
}
$phase = $argv[1];

$privateDir = '/home/bobsome1/site-private/trust-worthy';
$analyticsDir = '/home/bobsome1/site-private/project-unveiled-analytics';
if ((string)getenv('PROJECT_UNVEILED_DEPLOY_TEST_MODE') === '1') {
    $testDir = (string)getenv('TW_INTAKE_MIGRATION_TEST_DIR');
    if ($testDir === '' || $testDir[0] !== '/' || str_contains($testDir, "\0")) {
        fwrite(STDERR, "A safe absolute test migration directory is required.\n");
        exit(2);
    }
    $privateDir = rtrim($testDir, '/');
    $analyticsTestDir = (string)getenv('PU_ANALYTICS_MIGRATION_TEST_DIR');
    if ($analyticsTestDir !== '') {
        if ($analyticsTestDir[0] !== '/' || str_contains($analyticsTestDir, "\0")) {
            fwrite(STDERR, "A safe absolute analytics test migration directory is required.\n");
            exit(2);
        }
        $analyticsDir = rtrim($analyticsTestDir, '/');
        if ($analyticsDir === '') {
            fwrite(STDERR, "The filesystem root is not a safe analytics test migration directory.\n");
            exit(2);
        }
    } else {
        // Existing intake-only tests remain isolated and the analytics
        // migration remains a no-op when its nested fixture is absent.
        $analyticsDir = $privateDir . '/project-unveiled-analytics';
    }
}

define('TW_INTAKE_PRIVATE_DIR_OVERRIDE', $privateDir);
define('TW_PRIVATE_DIR_OVERRIDE', $privateDir);
require_once dirname(__DIR__) . '/truth/lib/trust-worthy-ai.php';
require_once dirname(__DIR__) . '/truth/daily/legacy-migration.php';
require_once dirname(__DIR__) . '/project-unveiled-analytics/lib.php';

try {
    $count = tw_intake_migrate_private_permissions();
    $ai = tw_migrate_ai_private_storage();
    $daily = ['retired' => 0, 'migrated_drafts' => 0];
    $dailyCandidates = 0;
    // Release 15 remains live until rsync succeeds. Its reusable token and
    // single-draft path may therefore be retired only in the post-sync phase.
    if ($phase === '--post-sync') {
        $daily = tw_daily_retire_legacy_private_artifacts($privateDir);
        $dailyCandidates = tw_daily_prune_expired_candidate_derivatives($privateDir);
    }
    $analytics = pu_analytics_migrate_private_storage($analyticsDir);
    echo "Private runtime migration {$phase} passed: {$count} intake files; "
        . $ai['key'] . " AI key; "
        . $ai['investigations'] . " investigation log; "
        . $ai['diagnostics'] . " diagnostic log; "
        . $daily['retired'] . " legacy Daily artifact(s) retired; "
        . $daily['migrated_drafts'] . " legacy Daily draft(s) preserved; "
        . $dailyCandidates . " expired Daily candidate derivative(s) pruned; "
        . $analytics['files'] . " analytics file(s) privacy-migrated.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Private runtime migration failed safely: {$error->getMessage()}\n");
    exit(1);
}
