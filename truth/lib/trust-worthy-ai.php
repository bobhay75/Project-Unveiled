<?php
declare(strict_types=1);

/**
 * Trust-Worthy AI gateway.
 * Secrets stay outside public_html in site-private/trust-worthy/openai-key.txt.
 * Conservative defaults protect a small prepaid API balance.
 */
function tw_private_dir(): string {
    return dirname(__DIR__, 3) . '/site-private/trust-worthy';
}

function tw_ai_config(): array {
    return [
        'model' => getenv('TW_OPENAI_MODEL') ?: 'gpt-5.6-luna',
        // This ceiling includes visible output AND reasoning tokens.
        'max_output_tokens' => 3000,
        'reasoning_effort' => 'low',
        'daily_request_cap' => 20,
        'per_ip_daily_cap' => 3,
        'max_web_search_calls' => 1,
        'timeout_seconds' => 60,
    ];
}

function tw_openai_key(): string {
    $env = trim((string)getenv('OPENAI_API_KEY'));
    if ($env !== '') return $env;
    $file = tw_private_dir() . '/openai-key.txt';
    return is_file($file) ? trim((string)file_get_contents($file)) : '';
}

function tw_usage_file(): string {
    return tw_private_dir() . '/ai-usage-' . gmdate('Y-m-d') . '.json';
}

function tw_rate_limit(string $ipHash): array {
    $cfg = tw_ai_config();
    $dir = tw_private_dir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) return [false, 'Private storage unavailable.'];
    $file = tw_usage_file();
    $usage = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
    if (!is_array($usage)) $usage = [];
    $total = (int)($usage['total'] ?? 0);
    $ips = is_array($usage['ips'] ?? null) ? $usage['ips'] : [];
    $mine = (int)($ips[$ipHash] ?? 0);
    if ($total >= $cfg['daily_request_cap']) return [false, 'Today’s research allowance has been reached.'];
    if ($mine >= $cfg['per_ip_daily_cap']) return [false, 'Your free research allowance for today has been reached.'];
    $usage['total'] = $total + 1;
    $ips[$ipHash] = $mine + 1;
    $usage['ips'] = $ips;
    file_put_contents($file, json_encode($usage, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($file, 0640);
    return [true, ''];
}

function tw_release_rate_limit(string $ipHash): void {
    $file = tw_usage_file();
    if (!is_file($file)) return;
    $usage = json_decode((string)file_get_contents($file), true);
    if (!is_array($usage)) return;
    $usage['total'] = max(0, (int)($usage['total'] ?? 0) - 1);
    $ips = is_array($usage['ips'] ?? null) ? $usage['ips'] : [];
    if (isset($ips[$ipHash])) {
        $ips[$ipHash] = max(0, (int)$ips[$ipHash] - 1);
        if ($ips[$ipHash] === 0) unset($ips[$ipHash]);
    }
    $usage['ips'] = $ips;
    file_put_contents($file, json_encode($usage, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function tw_log_openai_diagnostic(int $status, array $data, string $curlError = ''): void {
    $dir = tw_private_dir();
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $error = is_array($data['error'] ?? null) ? $data['error'] : [];
    $record = [
        'at_utc' => gmdate('c'),
        'http_status' => $status,
        'error_type' => (string)($error['type'] ?? ''),
        'error_code' => (string)($error['code'] ?? ''),
        'error_message' => mb_substr((string)($error['message'] ?? ''), 0, 500),
        'curl_error' => mb_substr($curlError, 0, 300),
    ];
    file_put_contents($dir . '/openai-errors.jsonl', json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND|LOCK_EX);
    @chmod($dir . '/openai-errors.jsonl', 0640);
}

function tw_clean_source_url(string $url): string {
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) return '';
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) return '';
    $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
    $host = strtolower((string)$parts['host'];
    $path = (string)($parts['path'] ?? '/');
    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
        foreach (array_keys($query) as $key) {
            if (str_starts_with(strtolower((string)$key), 'utm_')) unset($query[$key]);
        }
    }
    $clean = $scheme . '://' . $host . $path;
    if ($query) $clean .= '?' . http_build_query($query);
    return $clean;
}

function tw_extract_text_and_sources(array $data): array {
    $text = trim((string)($data['output_text'] ?? ''));
    $sources = [];
    $webCalls = 0;

    foreach (($data['output'] ?? []) as $item) {
        if (!is_array($item)) continue;
        if (($item['type'] ?? '') === 'web_search_call') $webCalls++;
        foreach (($item['content'] ?? []) as $part) {
            if (!is_array($part) || ($part['type'] ?? '') !== 'output_text') continue;
            if ($text === '') $text .= (string)($part['text'] ?? '');
            foreach (($part['annotations'] ?? []) as $annotation) {
                if (!is_array($annotation) || ($annotation['type'] ?? '') !== 'url_citation') continue;
                $url = tw_clean_source_url((string)($annotation['url'] ?? ''));
                if ($url === '') continue;
                $sources[$url] = trim((string)($annotation['title'] ?? 'Source')) ?: 'Source';
            }
        }
        $actionSources = $item['action']['sources'] ?? [];
        if (is_array($actionSources)) {
            foreach ($actionSources as $source) {
                if (!is_array($source)) continue;
                $url = tw_clean_source_url((string)($source['url'] ?? ''));
                if ($url === '') continue;
                $sources[$url] = trim((string)($source['title'] ?? 'Source')) ?: 'Source';
            }
        }
    }

    $text = trim($text);
    if ($text !== '' && $sources) {
        $text .= "\n\nSOURCE TRAIL · WEB CHECK\n";
        $i = 0;
        foreach ($sources as $url => $title) {
            $text .= "\n- " . preg_replace('/\s+/u', ' ', $title) . ': ' . $url;
            if (++$i >= 6) break;
        }
    }
    return [$text, array_keys($sources), $webCalls];
}

function tw_short_investigation(string $question, string $context = ''): array {
    $key = tw_openai_key();
    if ($key === '') return ['ok'=>false,'message'=>'The research engine is not configured yet.'];
    if (!function_exists('curl_init')) return ['ok'=>false,'message'=>'The server needs PHP cURL enabled.'];

    $cfg = tw_ai_config();
    $system = <<<'PROMPT'
You are the research synthesis layer for Project Unveiled: Truth on Trial, powered by the Trust-Worthy method.
This is a concise preliminary investigation, not a final verdict. Search the web once to check the factual record before answering. Prefer primary, official, declassified, court, legislative, academic, or earliest-accessible sources over summaries when available. Do not assume a source is truthful merely because it is primary; primary means closer to the event, not infallible.

Return a COMPLETE short investigation in roughly 500–750 words using these exact section labels, each on its own line:
CLAIM ON TRIAL
WHAT IS WELL ESTABLISHED
STRONGEST EVIDENCE FOR
STRONGEST COUNTEREVIDENCE / ALTERNATIVE
WHAT REMAINS UNKNOWN
PROVISIONAL FINDING

OUTPUT FORMAT RULES:
- Plain text only. Do not use Markdown heading markers, asterisks, underscores, tables, code fences, or Markdown links.
- Do not put URLs or citations inline. The application appends the verified source trail separately.
- Bullets may begin with a simple hyphen and space.
- Use exact dates when known. Do not collapse separate events into a date range when the distinction matters.
- Keep direct quotations very short; paraphrase wherever possible.

Be adversarial toward every conclusion, including the user's premise. Distinguish documented fact, inference, disputed claim, and unknown. Do not equate motive with proof. Do not turn evidence of influence into evidence of total control unless the evidence supports that stronger claim.
Never fabricate citations, quotations, documents, dates, statistics, or source access. If current or primary evidence is insufficient, say so explicitly. When evidence shows that some officials had notice of uncertainty while others lacked the complete intelligence picture, say that instead of generalizing that “the government knew.” Keep the free answer useful but leave source-by-source provenance analysis, extended contradictions, and a full confidence ledger for the paid Deep Dive.
End with exactly: You be the judge.
PROMPT;
    $input = "QUESTION ON TRIAL:\n" . $question;
    if ($context !== '') $input .= "\n\nSUBMITTED CONTEXT:\n" . $context;

    $payload = [
        'model' => $cfg['model'],
        'instructions' => $system,
        'input' => $input,
        'reasoning' => ['effort' => $cfg['reasoning_effort']],
        'text' => ['verbosity' => 'low'],
        'max_output_tokens' => $cfg['max_output_tokens'],
        'tools' => [['type' => 'web_search']],
        'tool_choice' => 'required',
        'max_tool_calls' => $cfg['max_web_search_calls'],
        'include' => ['web_search_call.action.sources'],
    ];
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $cfg['timeout_seconds'],
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $err !== '') {
        tw_log_openai_diagnostic($status, [], $err);
        return ['ok'=>false,'message'=>'The research engine could not connect. Diagnostic: network_error'];
    }
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) $data = [];
    if ($status < 200 || $status >= 300) {
        tw_log_openai_diagnostic($status, $data);
        $error = is_array($data['error'] ?? null) ? $data['error'] : [];
        $code = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string)($error['code'] ?? $error['type'] ?? 'api_error')) ?: 'api_error';
        return ['ok'=>false,'message'=>'OpenAI API request failed safely. Diagnostic: HTTP '.$status.' · '.$code];
    }

    [$text, $sources, $webCalls] = tw_extract_text_and_sources($data);
    if ($text === '') {
        tw_log_openai_diagnostic($status, ['error'=>['type'=>'empty_output','message'=>'Successful response contained no output text.']]);
        return ['ok'=>false,'message'=>'The research engine returned no usable answer. Diagnostic: empty_output'];
    }

    $incomplete = (($data['status'] ?? '') === 'incomplete');
    if ($incomplete && (($data['incomplete_details']['reason'] ?? '') === 'max_output_tokens')) {
        $text .= "\n\nSYSTEM NOTE\nThis preliminary answer reached its output ceiling. A Deep Dive can continue the investigation.";
    }

    $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
    return [
        'ok'=>true,
        'text'=>$text,
        'model'=>$cfg['model'],
        'response_id'=>(string)($data['id'] ?? ''),
        'input_tokens'=>(int)($usage['input_tokens'] ?? 0),
        'output_tokens'=>(int)($usage['output_tokens'] ?? 0),
        'reasoning_tokens'=>(int)($usage['output_tokens_details']['reasoning_tokens'] ?? 0),
        'web_search_calls'=>$webCalls,
        'source_count'=>count($sources),
        'incomplete'=>$incomplete,
    ];
}
