<?php
declare(strict_types=1);

const TW_CASE_ID_PATTERN = '/^TW-CLAIM-[0-9]{6}$/';

function tw_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function tw_case_path(string $caseId): string
{
    return __DIR__ . '/cases/' . $caseId . '.json';
}

function tw_load_case(string $caseId): ?array
{
    if (!preg_match(TW_CASE_ID_PATTERN, $caseId)) {
        return null;
    }

    $path = tw_case_path($caseId);
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    try {
        $record = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }

    if (!is_array($record) || ($record['case_id'] ?? null) !== $caseId) {
        return null;
    }

    return $record;
}

function tw_all_cases(): array
{
    $records = [];
    foreach (glob(__DIR__ . '/cases/TW-CLAIM-*.json') ?: [] as $path) {
        $caseId = basename($path, '.json');
        $record = tw_load_case($caseId);
        if ($record !== null) {
            $records[] = $record;
        }
    }

    usort(
        $records,
        static fn(array $left, array $right): int => strcmp((string) $right['case_id'], (string) $left['case_id'])
    );

    return $records;
}

function tw_assessment_label(string $assessment): string
{
    return match ($assessment) {
        'supports-p' => 'Evidence supports P',
        'leans-p' => 'Evidence leans toward P',
        'insufficient' => 'Evidence is insufficient',
        'leans-not-p' => 'Evidence leans toward not-P',
        'supports-not-p' => 'Evidence supports not-P',
        'not-yet-investigated' => 'Not yet investigated',
        default => 'Assessment pending',
    };
}

function tw_public_path(array $record): string
{
    $path = parse_url((string) ($record['public_url'] ?? ''), PHP_URL_PATH);
    return is_string($path) && str_starts_with($path, '/truth/') ? $path : '/truth/';
}

function tw_list(array $items): void
{
    foreach ($items as $item) {
        echo '<li>' . tw_e($item) . '</li>';
    }
}
