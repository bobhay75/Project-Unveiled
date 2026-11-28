<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/public-cases.php';

if (!tw_public_case_read_only()) {
    return;
}

$query = $_GET['q'] ?? null;
$limit = $_GET['limit'] ?? '20';
$after = $_GET['after'] ?? null;
if (($query !== null && !is_string($query)) || !is_string($limit)
    || preg_match('/\A(?:[1-9]|[1-4][0-9]|50)\z/', $limit) !== 1
    || ($after !== null && (!is_string($after) || !tw_public_case_id_valid($after)))) {
    tw_public_case_response(['error' => 'invalid_query'], 400);
    return;
}

try {
    $terms = $query === null ? [] : tw_public_case_query_terms($query);
    $results = tw_public_case_catalog($terms, (int)$limit, $after);
    tw_public_case_response($results, 200, $query === null);
} catch (InvalidArgumentException $error) {
    tw_public_case_response(['error' => 'invalid_query'], 400);
} catch (RuntimeException | JsonException $error) {
    tw_public_case_response(['error' => 'catalog_unavailable'], 503);
}
