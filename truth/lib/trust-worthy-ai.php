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
        'model' => getenv('TW_OPENAI_MODEL') ?: 'gpt-5-mini',
        'max_output_tokens' => 1400,
        'daily_request_cap' => 20,
        'per_ip_daily_cap' => 3,
        'timeout_seconds' => 45,
    ];
}

function tw_openai_key(): string {
    $env = trim((string)getenv('OPENAI_API_KEY'));
    if ($env !== '') return $env;
    $file = tw_private_dir() . '/openai-key.txt';
    return is_file($file) ? trim((string)file_get_contents($file)) : '';
}

function tw_rate_limit(string $ipHash): array {
    $cfg = tw_ai_config();
    $dir = tw_private_dir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) return [false, 'Private storage unavailable.'];
    $day = gmdate('Y-m-d');
    $file = $dir . '/ai-usage-' . $day . '.json';
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

function tw_short_investigation(string $question, string $context = ''): array {
    $key = tw_openai_key();
    if ($key === '') return ['ok'=>false,'message'=>'The research engine is not configured yet.'];
    if (!function_exists('curl_init')) return ['ok'=>false,'message'=>'The server needs PHP cURL enabled.'];

    $cfg = tw_ai_config();
    $system = <<<'PROMPT'
You are the research synthesis layer for Project Unveiled: Truth on Trial, powered by the Trust-Worthy method.
Do not act like an oracle and do not declare contested claims true merely because they are repeated. Give a concise preliminary investigation, not a final verdict.
Separate: (1) the claim being tested, (2) what is well established, (3) strongest evidence supporting it, (4) strongest counterevidence or alternative explanation, (5) what remains unknown, and (6) a provisional finding with calibrated confidence.
Never fabricate citations, quotations, documents, statistics, or source access. If the supplied material is insufficient to verify something, say so. Prefer primary/official/earliest sources when they are actually known to you, but explicitly state that this short answer has not independently browsed or authenticated sources unless source material was supplied.
End with: “You be the judge.”
PROMPT;
    $input = "QUESTION ON TRIAL:\n" . $question;
    if ($context !== '') $input .= "\n\nSUBMITTED CONTEXT:\n" . $context;

    $payload = [
        'model' => $cfg['model'],
        'instructions' => $system,
        'input' => $input,
        'max_output_tokens' => $cfg['max_output_tokens'],
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
    if ($raw === false || $err !== '') return ['ok'=>false,'message'=>'The research engine could not connect.'];
    $data = json_decode((string)$raw, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        error_log('Trust-Worthy OpenAI error HTTP ' . $status);
        return ['ok'=>false,'message'=>'The research engine is temporarily unavailable.'];
    }
    $text = trim((string)($data['output_text'] ?? ''));
    if ($text === '' && isset($data['output']) && is_array($data['output'])) {
        foreach ($data['output'] as $item) {
            foreach (($item['content'] ?? []) as $part) {
                if (($part['type'] ?? '') === 'output_text') $text .= (string)($part['text'] ?? '');
            }
        }
        $text = trim($text);
    }
    if ($text === '') return ['ok'=>false,'message'=>'The research engine returned no usable answer.'];
    return ['ok'=>true,'text'=>$text,'model'=>$cfg['model'],'response_id'=>(string)($data['id'] ?? '')];
}
