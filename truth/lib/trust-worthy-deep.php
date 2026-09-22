<?php
declare(strict_types=1);

require_once __DIR__ . '/trust-worthy-ai.php';

/**
 * Trust-Worthy Deep Investigation engine.
 *
 * This path is intentionally separate from tw_short_investigation(). A deep
 * investigation is multi-pass, receipt-driven, adversarial, and refuses to
 * emit a probability when the evidence floor is not met.
 */

function tw_deep_config(): array {
    $base = tw_ai_config();
    return [
        'model' => (string)($base['model'] ?? ''),
        'reasoning_effort' => 'medium',
        'max_output_tokens' => 4200,
        'max_tool_calls_per_research_pass' => 2,
        'timeout_seconds' => 90,
        'max_provider_body_bytes' => 2097152,
        'minimum_unique_sources' => 4,
        'minimum_counter_sources' => 1,
        'minimum_corroboration_sources' => 1,
        'authorization_ttl_seconds' => 900,
        'max_confirmed_map_characters' => 7000,
    ];
}

function tw_deep_extract_text(array $response): string {
    $chunks = [];
    foreach (($response['output'] ?? []) as $item) {
        if (!is_array($item) || ($item['type'] ?? '') !== 'message') continue;
        foreach (($item['content'] ?? []) as $content) {
            if (!is_array($content)) continue;
            $text = $content['text'] ?? null;
            if (is_string($text) && trim($text) !== '') $chunks[] = trim($text);
        }
    }
    return trim(implode("\n\n", $chunks));
}

function tw_deep_normalize_url(string $url): string {
    $url = trim($url);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) return '';
    $parts = parse_url($url);
    if (!is_array($parts)) return '';
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') return '';
    $path = (string)($parts['path'] ?? '/');
    $query = [];
    if (isset($parts['query'])) {
        parse_str((string)$parts['query'], $query);
        foreach (array_keys($query) as $key) {
            if (str_starts_with(strtolower((string)$key), 'utm_') || in_array(strtolower((string)$key), ['fbclid','gclid'], true)) {
                unset($query[$key]);
            }
        }
    }
    ksort($query);
    $normalized = $scheme . '://' . $host . ($path !== '' ? $path : '/');
    if ($query !== []) $normalized .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    return $normalized;
}

function tw_deep_extract_sources(array $response): array {
    $sources = [];
    foreach (($response['output'] ?? []) as $item) {
        if (!is_array($item) || ($item['type'] ?? '') !== 'web_search_call') continue;
        $action = $item['action'] ?? null;
        if (!is_array($action)) continue;
        foreach (($action['sources'] ?? []) as $source) {
            if (!is_array($source)) continue;
            $url = tw_deep_normalize_url((string)($source['url'] ?? ''));
            if ($url === '') continue;
            $title = trim((string)($source['title'] ?? 'Source'));
            $sources[$url] = ['url' => $url, 'title' => $title !== '' ? $title : 'Source'];
        }
    }
    return array_values($sources);
}

function tw_deep_provider_pass(string $system, string $user, bool $withWeb): array {
    $cfg = tw_deep_config();
    $key = tw_openai_key();
    if ($key === '') return ['ok'=>false,'message'=>'Research provider key unavailable.','quota_consumed'=>false];
    if ($cfg['model'] === '') return ['ok'=>false,'message'=>'Research model unavailable.','quota_consumed'=>false];

    $payload = [
        'model' => $cfg['model'],
        'store' => false,
        'reasoning' => ['effort' => $cfg['reasoning_effort']],
        'max_output_tokens' => $cfg['max_output_tokens'],
        'input' => [
            ['role'=>'system','content'=>[['type'=>'input_text','text'=>$system]]],
            ['role'=>'user','content'=>[['type'=>'input_text','text'=>$user]]],
        ],
    ];
    if ($withWeb) {
        $payload['tools'] = [['type'=>'web_search']];
        $payload['tool_choice'] = 'required';
        $payload['max_tool_calls'] = $cfg['max_tool_calls_per_research_pass'];
        $payload['include'] = ['web_search_call.action.sources'];
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded)) return ['ok'=>false,'message'=>'Research request encoding failed.','quota_consumed'=>false];

    $raw = '';
    $tooLarge = false;
    $ch = curl_init('https://api.openai.com/v1/responses');
    if ($ch === false) return ['ok'=>false,'message'=>'Research provider initialization failed.','quota_consumed'=>false];
    $configured = curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $encoded,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => $cfg['timeout_seconds'],
        CURLOPT_WRITEFUNCTION => static function($handle, string $chunk) use (&$raw, &$tooLarge, $cfg): int {
            if (strlen($raw) + strlen($chunk) > $cfg['max_provider_body_bytes']) {
                $tooLarge = true;
                return 0;
            }
            $raw .= $chunk;
            return strlen($chunk);
        },
    ]);
    if (!$configured) {
        curl_close($ch);
        return ['ok'=>false,'message'=>'Research provider request setup failed.','quota_consumed'=>false];
    }
    $executed = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($tooLarge) return ['ok'=>false,'message'=>'Research provider response exceeded safety limit.','quota_consumed'=>true];
    if ($executed === false || $http < 200 || $http >= 300) {
        return ['ok'=>false,'message'=>'Research provider request failed.','diagnostic'=>$curlError !== '' ? $curlError : 'http_' . $http,'quota_consumed'=>true];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return ['ok'=>false,'message'=>'Research provider response was invalid.','quota_consumed'=>true];
    $text = tw_deep_extract_text($decoded);
    if ($text === '') return ['ok'=>false,'message'=>'Research provider returned no usable analysis.','quota_consumed'=>true];

    $usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
    return [
        'ok'=>true,
        'text'=>$text,
        'sources'=>tw_deep_extract_sources($decoded),
        'response_id'=>is_string($decoded['id'] ?? null) ? $decoded['id'] : '',
        'usage'=>[
            'input_tokens'=>(int)($usage['input_tokens'] ?? 0),
            'output_tokens'=>(int)($usage['output_tokens'] ?? 0),
            'reasoning_tokens'=>(int)($usage['output_tokens_details']['reasoning_tokens'] ?? 0),
        ],
        'quota_consumed'=>true,
    ];
}

function tw_deep_base_system(): string {
    return "You are Trust-Worthy, an evidence investigator. Distinguish verified fact, allegation, inference, framing, missing context, contradiction, and unknowns. Never treat absence of evidence as evidence of falsity. Cite and characterize sources honestly. Your job is to investigate, not to defend a side.";
}

function tw_deep_subject(string $question, string $context): string {
    return "CLAIM OR QUESTION:\n{$question}" . ($context !== '' ? "\n\nUSER CONTEXT:\n{$context}" : '');
}

function tw_deep_claim_map(string $question, string $context = ''): array {
    [$valid, $question, $context, $error] = tw_validate_trial_input($question, $context);
    if (!$valid) return ['ok'=>false,'message'=>$error,'quota_consumed'=>false];
    return tw_deep_provider_pass(
        tw_deep_base_system(),
        tw_deep_subject($question, $context) . "\n\nBefore any web research, decompose this into independently testable assertions. Identify: CENTRAL CLAIM; TESTABLE SUBCLAIMS; IMPLIED CLAIMS; KEY ENTITIES; IMPORTANT DATES OR TIME WINDOWS; LOADED OR PERSUASIVE FRAMING; and MATERIAL QUESTIONS THE EVIDENCE MUST ANSWER. Keep each item concise and editable by a user. Do not research yet and do not issue a verdict.",
        false
    );
}

function tw_deep_b64url_encode(string $bytes): string {
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function tw_deep_b64url_decode(string $value): ?string {
    if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) return null;
    $padding = (4 - (strlen($value) % 4)) % 4;
    $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
    return is_string($decoded) ? $decoded : null;
}

function tw_deep_authorization_path(string $nonce): string {
    return tw_private_dir() . '/deep-auth-' . $nonce . '.json';
}

function tw_deep_cleanup_authorizations(): void {
    if (!tw_ai_prepare_private_dir()) return;
    $matches = glob(tw_private_dir() . '/deep-auth-*.json');
    if (!is_array($matches) || count($matches) > 10000) return;
    $cutoff = time() - 3600;
    foreach ($matches as $path) {
        if (!preg_match('/^deep-auth-[a-f0-9]{32}\.json$/D', basename($path))) continue;
        if (!tw_ai_private_path_exists($path) || !tw_ai_secure_existing_regular_file($path, 0600)) continue;
        $modified = @filemtime($path);
        if (is_int($modified) && $modified < $cutoff) @unlink($path);
    }
}

function tw_deep_issue_authorization(string $ipHash, string $question, string $context, string $secret): string {
    if (!preg_match('/^[a-f0-9]{64}$/D', $ipHash) || $secret === '') return '';
    try { $nonce = bin2hex(random_bytes(16)); } catch (Throwable $error) { return ''; }
    $cfg = tw_deep_config();
    $payload = [
        'v'=>1,
        'nonce'=>$nonce,
        'exp'=>time() + (int)$cfg['authorization_ttl_seconds'],
        'ip_hash'=>$ipHash,
        'question_hash'=>hash_hmac('sha256','deep-question|'.$question,$secret),
        'context_hash'=>hash_hmac('sha256','deep-context|'.$context,$secret),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) return '';
    $body = tw_deep_b64url_encode($json);
    $signature = hash_hmac('sha256','deep-auth|'.$body,$secret);
    $record = json_encode([
        'state'=>'issued',
        'exp'=>$payload['exp'],
        'payload_hash'=>hash('sha256',$body),
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($record) || !tw_ai_atomic_private_write(tw_deep_authorization_path($nonce), $record . "\n")) return '';
    tw_deep_cleanup_authorizations();
    return $body . '.' . $signature;
}

function tw_deep_consume_authorization(string $token, string $ipHash, string $question, string $context, string $secret): bool {
    if ($secret === '' || strlen($token) > 4096 || !str_contains($token,'.')) return false;
    [$body,$signature] = explode('.',$token,2);
    if (!preg_match('/^[a-f0-9]{64}$/D',$signature)) return false;
    $expected = hash_hmac('sha256','deep-auth|'.$body,$secret);
    if (!hash_equals($expected,$signature)) return false;
    $decoded = tw_deep_b64url_decode($body);
    if (!is_string($decoded)) return false;
    $payload = json_decode($decoded,true);
    if (!is_array($payload) || (int)($payload['v']??0)!==1) return false;
    $nonce=(string)($payload['nonce']??'');
    if (!preg_match('/^[a-f0-9]{32}$/D',$nonce)) return false;
    $exp=(int)($payload['exp']??0);
    if ($exp < time() || $exp > time() + 1800) return false;
    if (!hash_equals((string)($payload['ip_hash']??''),$ipHash)) return false;
    if (!hash_equals((string)($payload['question_hash']??''),hash_hmac('sha256','deep-question|'.$question,$secret))) return false;
    if (!hash_equals((string)($payload['context_hash']??''),hash_hmac('sha256','deep-context|'.$context,$secret))) return false;

    try {
        return (bool)tw_ai_with_private_file_lock(tw_private_dir().'/deep-auth.lock', static function() use ($nonce,$body,$exp): bool {
            $path=tw_deep_authorization_path($nonce);
            $raw=tw_ai_read_secure_regular_file($path,2,4096);
            if (!is_string($raw)) return false;
            $record=json_decode(trim($raw),true);
            if (!is_array($record) || ($record['state']??'')!=='issued') return false;
            if ((int)($record['exp']??0)!==$exp || $exp < time()) return false;
            if (!hash_equals((string)($record['payload_hash']??''),hash('sha256',$body))) return false;
            $consumed=json_encode(['state'=>'consumed','exp'=>$exp,'payload_hash'=>hash('sha256',$body),'consumed_at'=>time()],JSON_UNESCAPED_SLASHES);
            return is_string($consumed) && tw_ai_atomic_private_write($path,$consumed."\n");
        });
    } catch (Throwable $error) {
        return false;
    }
}

function tw_deep_receipt(string $stage, string $label, array $pass): array {
    return [
        'stage'=>$stage,
        'label'=>$label,
        'completed'=>(bool)($pass['ok'] ?? false),
        'source_count'=>count(is_array($pass['sources'] ?? null) ? $pass['sources'] : []),
        'sources'=>is_array($pass['sources'] ?? null) ? $pass['sources'] : [],
        'summary'=>is_string($pass['text'] ?? null) ? $pass['text'] : '',
        'at_utc'=>gmdate('c'),
    ];
}

function tw_deep_run(string $question, string $context = '', ?callable $onReceipt = null, string $confirmedClaimMap = ''): array {
    [$valid, $question, $context, $error] = tw_validate_trial_input($question, $context);
    if (!$valid) return ['ok'=>false,'message'=>$error];

    $emit = static function(array $receipt) use ($onReceipt): void {
        if ($onReceipt !== null) $onReceipt($receipt);
    };

    $baseSystem = tw_deep_base_system();
    $subject = tw_deep_subject($question,$context);
    $cfg = tw_deep_config();

    $confirmedClaimMap = trim($confirmedClaimMap);
    if ($confirmedClaimMap !== '') {
        if (mb_strlen($confirmedClaimMap,'UTF-8') > (int)$cfg['max_confirmed_map_characters']) {
            return ['ok'=>false,'message'=>'Confirmed claim map was too large.'];
        }
        $decompose=['ok'=>true,'text'=>$confirmedClaimMap,'sources'=>[],'quota_consumed'=>false];
        $receipt=tw_deep_receipt('decompose','Claim map confirmed',$decompose); $emit($receipt);
    } else {
        $decompose = tw_deep_claim_map($question,$context);
        if (!($decompose['ok'] ?? false)) return $decompose;
        $receipt = tw_deep_receipt('decompose','Claim decomposed',$decompose); $emit($receipt);
    }

    $passes = [];
    $researchPlan = [
        'origin' => ['Trace origin','Search for the earliest accessible origin of the claim, quote, statistic, image narrative, or allegation. Distinguish original evidence from later repetition. Identify likely first publication or earliest confirmed appearance and any uncertainty.'],
        'primary' => ['Primary evidence','Search specifically for primary or first-party records capable of verifying the material assertions: court records, laws, filings, transcripts, datasets, official documents, original studies, direct statements, archived pages, or equivalent records. Say explicitly when no primary record is found.'],
        'corroboration' => ['Independent corroboration','Search for independent corroboration. Detect when multiple outlets merely repeat one underlying source. Prefer sources with independent reporting or direct access to records.'],
        'counter' => ['Counterevidence','Actively try to disprove or materially weaken the claim. Search for contradictory records, corrections, alternate explanations, expert criticism, missing qualifiers, changed facts, and evidence that the framing overstates what the underlying event proves.'],
        'context' => ['Context and chronology','Build the relevant chronology and context. Identify what happened before and after, which facts are current versus historical, and which omitted facts materially change interpretation.'],
    ];

    foreach ($researchPlan as $stage => [$label,$instruction]) {
        $pass = tw_deep_provider_pass(
            $baseSystem,
            $subject . "\n\nCONFIRMED CLAIM MAP:\n" . $decompose['text'] . "\n\nPASS OBJECTIVE:\n{$instruction}\n\nReturn concise findings plus what remains unresolved.",
            true
        );
        if (!($pass['ok'] ?? false)) return ['ok'=>false,'message'=>'Deep investigation stopped during ' . strtolower($label) . '.','stage'=>$stage,'detail'=>$pass['message'] ?? 'unknown'];
        $passes[$stage] = $pass;
        $receipt = tw_deep_receipt($stage,$label,$pass); $emit($receipt);
    }

    $uniqueSources = [];
    foreach ($passes as $pass) {
        foreach (($pass['sources'] ?? []) as $source) {
            if (!is_array($source)) continue;
            $url = (string)($source['url'] ?? '');
            if ($url !== '') $uniqueSources[$url] = $source;
        }
    }

    $counterSources = count($passes['counter']['sources'] ?? []);
    $corroborationSources = count($passes['corroboration']['sources'] ?? []);
    $evidenceFloorMet = count($uniqueSources) >= $cfg['minimum_unique_sources']
        && $counterSources >= $cfg['minimum_counter_sources']
        && $corroborationSources >= $cfg['minimum_corroboration_sources'];

    $evidencePacket = [];
    foreach ($passes as $stage => $pass) {
        $evidencePacket[] = strtoupper($stage) . " PASS:\n" . $pass['text'];
    }

    $synthesisInstruction = $evidenceFloorMet
        ? "The evidence floor is met. Produce a final synthesis. Begin with exactly two machine-readable lines: VERDICT: one of SUPPORTED, LIKELY, MIXED, UNLIKELY, CONTRADICTED; PROBABILITY: integer 0-100. The probability is your evidence-conditioned confidence in the central factual claim, not objective truth. Then explain: what is verified; what is inferred; what is misleading or omitted; strongest evidence; strongest counterevidence; unresolved unknowns; why the probability is not higher or lower."
        : "The evidence floor is NOT met. You must not issue a probability. Begin with exactly: VERDICT: INSUFFICIENT EVIDENCE — NO VERDICT and PROBABILITY: NONE. Then explain what was searched, what evidence was found, which material gaps remain, and what would be needed to reach a responsible verdict.";

    $synthesis = tw_deep_provider_pass(
        $baseSystem,
        $subject . "\n\nCONFIRMED CLAIM MAP:\n" . $decompose['text'] . "\n\nEVIDENCE PACKET:\n" . implode("\n\n", $evidencePacket) . "\n\n" . $synthesisInstruction,
        false
    );
    if (!($synthesis['ok'] ?? false)) return $synthesis;

    $verdict = 'INSUFFICIENT EVIDENCE — NO VERDICT';
    if (preg_match('/^VERDICT:\s*(.+)$/mi', $synthesis['text'], $m)) $verdict = trim($m[1]);
    $probability = null;
    if ($evidenceFloorMet && preg_match('/^PROBABILITY:\s*(\d{1,3})\s*$/mi', $synthesis['text'], $m)) {
        $candidate = (int)$m[1];
        if ($candidate >= 0 && $candidate <= 100) $probability = $candidate;
    }
    if (!$evidenceFloorMet) {
        $verdict = 'INSUFFICIENT EVIDENCE — NO VERDICT';
        $probability = null;
    }

    $finalReceipt = [
        'stage'=>'synthesis',
        'label'=>'Finding generated',
        'completed'=>true,
        'source_count'=>count($uniqueSources),
        'evidence_floor_met'=>$evidenceFloorMet,
        'verdict'=>$verdict,
        'probability'=>$probability,
        'at_utc'=>gmdate('c'),
    ];
    $emit($finalReceipt);

    try { $investigationId='TW-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); }
    catch (Throwable $error) { $investigationId='TW-' . gmdate('Ymd-His'); }

    $receipts=[tw_deep_receipt('decompose',$confirmedClaimMap!==''?'Claim map confirmed':'Claim decomposed',$decompose)];
    foreach($passes as $stage=>$pass) $receipts[]=tw_deep_receipt($stage,$researchPlan[$stage][0],$pass);
    $receipts[]=$finalReceipt;

    return [
        'ok'=>true,
        'investigation_id'=>$investigationId,
        'claim'=>$question,
        'claim_map'=>$decompose['text'],
        'receipts'=>$receipts,
        'sources'=>array_values($uniqueSources),
        'metrics'=>[
            'unique_sources'=>count($uniqueSources),
            'counter_sources'=>$counterSources,
            'corroboration_sources'=>$corroborationSources,
            'evidence_floor_met'=>$evidenceFloorMet,
        ],
        'verdict'=>$verdict,
        'probability'=>$probability,
        'analysis'=>$synthesis['text'],
    ];
}
