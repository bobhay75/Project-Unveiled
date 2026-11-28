<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/public-cases.php';

if (!tw_public_case_read_only()) {
    return;
}

$caseId = $_GET['case_id'] ?? null;
if (!is_string($caseId) || !tw_public_case_id_valid($caseId)) {
    tw_public_case_response(['error' => 'invalid_case_id'], 400);
    return;
}

try {
    $case = tw_public_case_read($caseId);
    if ($case === null) {
        tw_public_case_response(['error' => 'case_not_found'], 404);
        return;
    }
    tw_public_case_response($case, 200, true);
} catch (RuntimeException | JsonException $error) {
    tw_public_case_response(['error' => 'catalog_unavailable'], 503);
}
