<?php
declare(strict_types=1);

const TW_PUBLIC_CASES_DIR = __DIR__ . '/../cases';
const TW_PUBLIC_CASE_BYTES = 262144;

function tw_public_case_response(array | stdClass $body, int $status, bool $cacheable = false): void
{
    http_response_code($status);
    header_remove('X-Powered-By');
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: *');
    header('Referrer-Policy: no-referrer');
    header('Link: </truth/api/openapi.json>; rel="service-desc"; type="application/json"');
    header('Cache-Control: ' . ($cacheable ? 'public, max-age=300' : 'no-store'));

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n";
    }
}

function tw_public_case_read_only(): bool
{
    if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
        return true;
    }
    header('Allow: GET, HEAD');
    tw_public_case_response(['error' => 'method_not_allowed'], 405);
    return false;
}

function tw_public_case_id_valid(string $caseId): bool
{
    return preg_match('/\ATW-CLAIM-[0-9]{6}\z/', $caseId) === 1;
}

function tw_public_case_read(string $caseId): ?stdClass
{
    if (!tw_public_case_id_valid($caseId)) {
        throw new InvalidArgumentException('Invalid public case ID.');
    }
    if (!is_dir(TW_PUBLIC_CASES_DIR) || is_link(TW_PUBLIC_CASES_DIR)) {
        throw new RuntimeException('Public case directory is unavailable.');
    }
    $path = TW_PUBLIC_CASES_DIR . '/' . $caseId . '.json';
    if (!file_exists($path) && !is_link($path)) {
        return null;
    }
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException('Invalid public case file.');
    }

    $contents = @file_get_contents($path, false, null, 0, TW_PUBLIC_CASE_BYTES + 1);
    if ($contents === false || strlen($contents) > TW_PUBLIC_CASE_BYTES) {
        throw new RuntimeException('Could not read public case file.');
    }
    $case = json_decode($contents, false, 64, JSON_THROW_ON_ERROR);
    $assessment = $case->current_assessment ?? null;
    $publicUrl = 'https://bobsome1.com/truth/case-' . substr($caseId, -6) . '.php';
    if (!$case instanceof stdClass || ($case->case_id ?? null) !== $caseId
        || !is_int($case->version ?? null) || $case->version < 1
        || !is_string($case->status ?? null) || !is_string($case->title ?? null)
        || !is_string($case->claim_under_test ?? null) || !is_string($case->protocol ?? null)
        || !is_array($case->revision_history ?? null)
        || !$assessment instanceof stdClass || !is_string($assessment->classification ?? null)
        || !is_string($assessment->summary ?? null)
        || ($case->public_url ?? null) !== $publicUrl) {
        throw new RuntimeException('Invalid public case record.');
    }

    return $case;
}

function tw_public_case_query_terms(string $query): array
{
    if (strlen($query) > 240 || preg_match_all('/[\p{L}\p{N}]+/u', $query, $matches) === false
        || !$matches[0] || count($matches[0]) > 12) {
        throw new InvalidArgumentException('Invalid public case query.');
    }
    return array_unique($matches[0]);
}

function tw_public_case_catalog(array $terms, int $limit, ?string $after): array
{
    $paths = glob(TW_PUBLIC_CASES_DIR . '/TW-CLAIM-*.json');
    if (!$paths) {
        throw new RuntimeException('Public case catalog is unavailable.');
    }

    $cases = [];
    foreach ($paths as $path) {
        $caseId = basename($path, '.json');
        if (!tw_public_case_id_valid($caseId) || ($after !== null && strcmp($caseId, $after) <= 0)) {
            continue;
        }
        $case = tw_public_case_read($caseId);
        if ($case === null) {
            throw new RuntimeException('Public case catalog changed mid-read.');
        }
        $text = implode(' ', [$caseId, $case->title, $case->claim_under_test, $case->current_assessment->summary]);
        foreach ($terms as $term) {
            $termPattern = preg_quote($term, '/');
            if (preg_match('/(?<![\p{L}\p{N}])' . $termPattern . '(?![\p{L}\p{N}])/iu', $text) !== 1) {
                continue 2;
            }
        }

        if (count($cases) === $limit) {
            return ['cases' => $cases, 'next_after' => $cases[$limit - 1]['case_id']];
        }
        $cases[] = [
            'case_id' => $caseId,
            'version' => $case->version,
            'status' => $case->status,
            'title' => $case->title,
            'claim_under_test' => $case->claim_under_test,
            'public_url' => $case->public_url,
            'record_url' => 'https://bobsome1.com/truth/api/case.php?case_id=' . $caseId,
        ];
    }

    return ['cases' => $cases, 'next_after' => null];
}
