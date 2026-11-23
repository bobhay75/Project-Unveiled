<?php
declare(strict_types=1);

/** Do not publish contradictory, unscored, or malformed machine findings. */
function tw_deep_parse_finding(string $text, bool $evidenceFloorMet): array {
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    $rejected = ['ok'=>false,'message'=>'The research provider did not return a finding consistent with the evidence checks. No verdict was published.','quota_consumed'=>true];
    if (preg_match_all('/^[\t ]*(?:VERDICT|PROBABILITY)[\t ]*:/mi', $text) !== 2
        || preg_match('/\AVERDICT:[\t ]*([^\n]+)\nPROBABILITY:[\t ]*([^\n]+)\n([\s\S]+)\z/', $text, $match) !== 1) {
        return $rejected;
    }
    $verdict = trim($match[1]);
    $score = trim($match[2]);
    $analysis = trim($match[3]);
    if ($analysis === '') return $rejected;
    if ($verdict === 'INSUFFICIENT EVIDENCE — NO VERDICT') {
        if ($score !== 'NONE') return $rejected;
        $probability = null;
    } else {
        if (!$evidenceFloorMet || !in_array($verdict, ['SUPPORTED','LIKELY','MIXED','UNLIKELY','CONTRADICTED'], true)
            || preg_match('/\A(?:100|[1-9]?[0-9])\z/', $score) !== 1) return $rejected;
        $probability = (int)$score;
    }
    return ['ok'=>true,'verdict'=>$verdict,'probability'=>$probability,'text'=>$text];
}

/** Validate the provider boundary without a network call or fabricated receipts. */
function tw_deep_parse_provider_response(array $response, bool $withWeb): array {
    if (($response['status'] ?? '') !== 'completed' || !is_array($response['output'] ?? null)) {
        return ['ok'=>false,'message'=>'The research provider did not complete this investigation stage.','quota_consumed'=>true];
    }
    $calls = 0;
    foreach ($response['output'] as $item) {
        if (is_array($item) && ($item['type'] ?? '') === 'web_search_call') {
            if (($item['status'] ?? '') !== 'completed') {
                return ['ok'=>false,'message'=>'A required research search did not complete.','quota_consumed'=>true];
            }
            $calls++;
        }
    }
    if (($withWeb && ($calls < 1 || $calls > (int)tw_deep_config()['max_tool_calls_per_research_pass']))
        || (!$withWeb && $calls !== 0)) {
        return ['ok'=>false,'message'=>'The research provider did not follow the required search boundary.','quota_consumed'=>true];
    }
    $text = tw_deep_extract_text($response);
    if ($text === '' || !mb_check_encoding($text, 'UTF-8')
        || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text) === 1) {
        return ['ok'=>false,'message'=>'The research provider returned no usable analysis.','quota_consumed'=>true];
    }
    $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
    $details = is_array($usage['output_tokens_details'] ?? null) ? $usage['output_tokens_details'] : [];
    return [
        'ok'=>true, 'text'=>$text, 'sources'=>tw_deep_extract_sources($response),
        'response_id'=>tw_ai_safe_response_id($response['id'] ?? null),
        'usage'=>[
            'input_tokens'=>tw_ai_bounded_nonnegative_int($usage['input_tokens'] ?? null),
            'output_tokens'=>tw_ai_bounded_nonnegative_int($usage['output_tokens'] ?? null),
            'reasoning_tokens'=>tw_ai_bounded_nonnegative_int($details['reasoning_tokens'] ?? null),
        ],
        'quota_consumed'=>true,
    ];
}
