<?php
declare(strict_types=1);

$boundedEnvInt = static function (string $name, int $default, int $minimum, int $maximum): int {
    $raw = getenv($name);
    if ($raw === false || !preg_match('/^\d+$/', $raw)) return $default;
    return max($minimum, min($maximum, (int)$raw));
};

return [
    'openai_model' => getenv('TRUST_WORTHY_OPENAI_MODEL') ?: 'gpt-5.6-luna',
    'daily_ai' => [
        // A reservation is charged before a provider request starts. Failed calls remain
        // charged because an upstream provider may still have performed billable work.
        'max_calls_per_hour' => $boundedEnvInt('TRUST_WORTHY_DAILY_MAX_CALLS_PER_HOUR', 2, 1, 10),
        'max_calls_per_day' => $boundedEnvInt('TRUST_WORTHY_DAILY_MAX_CALLS_PER_DAY', 6, 1, 30),
        'max_concurrent' => $boundedEnvInt('TRUST_WORTHY_DAILY_MAX_CONCURRENT', 1, 1, 2),
        'max_output_tokens' => $boundedEnvInt('TRUST_WORTHY_DAILY_MAX_OUTPUT_TOKENS', 4000, 1200, 6000),
        'max_web_search_calls' => $boundedEnvInt('TRUST_WORTHY_DAILY_MAX_WEB_SEARCH_CALLS', 2, 1, 4),
        'lease_seconds' => 240,
    ],
];
