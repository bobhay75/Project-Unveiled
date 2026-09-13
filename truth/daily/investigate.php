<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/legacy-migration.php';

tw_admin_private_headers();
tw_require_same_origin_form_post(8192);
tw_require_admin();
tw_require_admin_csrf();
$privateDir = tw_private_dir();
tw_daily_prune_expired_candidate_derivatives($privateDir);

$config = require __DIR__ . '/config.php';
$idValue = tw_post_scalar('id', 80);
$id = $idValue === null ? '' : trim($idValue);
if (!preg_match('/^[a-zA-Z0-9-]{1,80}$/', $id)) {
    http_response_code(422);
    exit('A valid candidate identifier is required.');
}

$queue = tw_private_json_read_strict($privateDir . '/daily-candidates.json', [], 2097152);
$candidate = null;
foreach (($queue['candidates'] ?? []) as $row) {
    if (is_array($row) && ($row['id'] ?? '') === $id) {
        $candidate = $row;
        break;
    }
}
if (!is_array($candidate)) {
    http_response_code(404);
    exit('Candidate not found. Refresh the queue and try again.');
}
if (!tw_daily_private_candidate_is_current($candidate)) {
    http_response_code(410);
    exit('This private question has expired. Refresh the queue and choose another candidate.');
}

$reservation = null;
try {
    $dailyAi = is_array($config['daily_ai'] ?? null) ? $config['daily_ai'] : [];
    $reservation = tw_daily_ai_reserve($dailyAi);
    $providerSucceeded = false;
    $usage = [];
    try {
        $report = tw_run_investigation($candidate, (string)$config['openai_model'], $dailyAi);
        $providerSucceeded = true;
        $usage = is_array($report['_meta']['provider_usage'] ?? null) ? $report['_meta']['provider_usage'] : [];
    } finally {
        $finishId = $reservation;
        $reservation = null;
        tw_daily_ai_finish($finishId, $providerSucceeded, $usage);
    }
    tw_store_daily_draft($report);
    tw_admin_flash_set('success', tw_daily_draft_is_private_question($report)
        ? 'Private investigation drafted. Publication is disabled because the question was submitted privately.'
        : 'Investigation drafted and bound to its evidence record. Human review is required before publication.');
} catch (Throwable) {
    if ($reservation !== null) {
        try {
            tw_daily_ai_finish($reservation, false);
        } catch (Throwable $ignored) {
            // The active lease expires automatically. Never bypass a failed budget reconciliation.
        }
    }
    try {
        tw_json_write(tw_private_dir() . '/daily-safe-error.json', [
            'at_utc' => gmdate('c'),
            'category' => 'daily_investigation',
            'code' => 'TW_DAILY_INVESTIGATION_FAILED',
            'message' => 'The investigation stopped safely; no research draft was stored.',
        ]);
    } catch (Throwable $ignored) {
        // Never let a logging failure replace the stable owner-safe response.
    }
    tw_admin_flash_set('error', 'The investigation stopped safely; no research draft was stored. Reference: TW_DAILY_INVESTIGATION_FAILED.');
}

header('Location: /truth/daily/desk.php', true, 303);
exit;
