<?php
declare(strict_types=1);
require_once __DIR__ . '/trust-worthy-queue.php';

function tw_ollama_config(): array {
    $cfg = [
        'planner_model' => 'deepseek-v4-flash:0731',
        'synthesis_model' => 'deepseek-v4-pro:0813',
        'daily_runs' => 3,
        'max_searches_per_run' => 8,
        'max_results_per_search' => 3,
        'max_input_tokens_per_run' => 300000,
        'max_output_tokens_per_run' => 20000,
        'daily_cost_usd' => 0.50,
        'run_cost_usd' => 0.20,
        'timeout_seconds' => 45,
    ];
    $file = tw_private_dir() . '/ollama-config.json';
    $custom = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (is_array($custom)) foreach (array_keys($cfg) as $key) if (array_key_exists($key, $custom)) $cfg[$key] = $custom[$key];
    return $cfg;
}

function tw_ollama_key(): string {
    $env = trim((string)getenv('OLLAMA_API_KEY'));
    if ($env !== '') return $env;
    $file = tw_private_dir() . '/ollama-api-key.txt';
    return is_file($file) ? trim((string)file_get_contents($file)) : '';
}

function tw_research_enabled(): bool {
    $file = tw_private_dir() . '/research-enabled.txt';
    return is_file($file) && trim((string)file_get_contents($file)) === 'enabled';
}

function tw_disable_research(): bool {
    if (!tw_ensure_private_dir()) return false;
    $ok = file_put_contents(tw_private_dir() . '/research-enabled.txt', "disabled\n", LOCK_EX) !== false;
    @chmod(tw_private_dir() . '/research-enabled.txt', 0640);
    return $ok;
}

function tw_usage_path(): string { return tw_private_dir() . '/ollama-usage-' . gmdate('Y-m-d') . '.json'; }
function tw_usage(): array {
    $data = is_file(tw_usage_path()) ? json_decode((string)file_get_contents(tw_usage_path()), true) : [];
    return is_array($data) ? $data + ['runs'=>0,'reserved_usd'=>0.0,'actual_usd'=>0.0,'input_tokens'=>0,'output_tokens'=>0,'searches'=>0] : [];
}
function tw_write_usage(array $usage): bool {
    $json = json_encode($usage, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    return $json !== false && file_put_contents(tw_usage_path(), $json, LOCK_EX) !== false;
}
function tw_reserve_run(): array {
    $cfg = tw_ollama_config();
    $lock = fopen(tw_private_dir() . '/ollama-usage.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) { if (is_resource($lock)) fclose($lock); return [false, 'Could not lock the usage budget.']; }
    @chmod(tw_private_dir() . '/ollama-usage.lock', 0640);
    try {
        $usage = tw_usage();
        if ((int)$usage['runs'] >= (int)$cfg['daily_runs']) return [false, 'Daily run cap reached.'];
        if ((float)$usage['reserved_usd'] + (float)$cfg['run_cost_usd'] > (float)$cfg['daily_cost_usd']) return [false, 'Daily cost guardrail reached.'];
        $usage['runs'] = (int)$usage['runs'] + 1;
        $usage['reserved_usd'] = round((float)$usage['reserved_usd'] + (float)$cfg['run_cost_usd'], 6);
        return tw_write_usage($usage) ? [true, ''] : [false, 'Could not reserve the usage budget.'];
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function tw_ollama_post(string $path, array $payload): array {
    $key = tw_ollama_key();
    if ($key === '' || !function_exists('curl_init')) return ['ok'=>false, 'error'=>'Provider is not configured.'];
    $ch = curl_init('https://ollama.com' . $path);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_SLASHES), CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>(int)tw_ollama_config()['timeout_seconds'], CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
    $raw = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if ($error !== '' || $status < 200 || $status >= 300 || !is_array($data)) return ['ok'=>false, 'error'=>'Provider request failed safely (HTTP ' . $status . ').'];
    return ['ok'=>true, 'data'=>$data];
}

function tw_json_object(string $text): ?array {
    $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)) ?? '');
    $start = strpos($text, '{'); $end = strrpos($text, '}');
    if ($start === false || $end === false || $end < $start) return null;
    $value = json_decode(substr($text, $start, $end - $start + 1), true);
    return is_array($value) ? $value : null;
}

function tw_chat_json(string $model, string $system, string $prompt, int $maxOutput): array {
    $response = tw_ollama_post('/api/chat', ['model'=>$model,'stream'=>false,'messages'=>[['role'=>'system','content'=>$system],['role'=>'user','content'=>$prompt]],'options'=>['num_predict'=>$maxOutput,'temperature'=>0.1]]);
    if (!($response['ok'] ?? false)) return $response;
    $data = $response['data']; $json = tw_json_object((string)($data['message']['content'] ?? ''));
    if ($json === null) return ['ok'=>false, 'error'=>'Model output failed JSON validation.'];
    return ['ok'=>true,'json'=>$json,'input_tokens'=>(int)($data['prompt_eval_count']??0),'output_tokens'=>(int)($data['eval_count']??0)];
}

function tw_search(string $query): array {
    $response = tw_ollama_post('/api/web_search', ['query'=>$query,'max_results'=>(int)tw_ollama_config()['max_results_per_search']]);
    if (!($response['ok'] ?? false)) return $response;
    $results = $response['data']['results'] ?? [];
    return ['ok'=>true,'results'=>is_array($results)?$results:[]];
}

function tw_fetch_source(string $url): array {
    $response = tw_ollama_post('/api/web_fetch', ['url'=>$url]);
    if (!($response['ok'] ?? false)) return $response;
    $data = $response['data'];
    return ['ok'=>true,'title'=>tw_clean((string)($data['title']??''),300),'content'=>mb_substr(trim((string)($data['content']??'')),0,12000)];
}

function tw_run_research(array $item): array {
    if (!tw_research_enabled()) return ['ok'=>false,'error'=>'Research is disabled.'];
    [$reserved,$reason] = tw_reserve_run(); if (!$reserved) return ['ok'=>false,'error'=>$reason];
    $cfg = tw_ollama_config();
    $plannerSystem = 'Return one JSON object only. Define the neutral proposition and bounded search plan. Seek primary or earliest accessible sources and direct counterevidence. Schema: {"proposition":"...","search_queries":["..."],"counter_queries":["..."]}. No more than 8 total queries.';
    $planner = tw_chat_json((string)$cfg['planner_model'], $plannerSystem, "QUESTION:\n".$item['question']."\nCONTEXT:\n".($item['context']??''), 1200);
    if (!($planner['ok']??false)) return $planner;
    $plan=$planner['json']; $proposition=tw_clean((string)($plan['proposition']??''),1000);
    $queries=array_values(array_filter(array_merge(is_array($plan['search_queries']??null)?$plan['search_queries']:[],is_array($plan['counter_queries']??null)?$plan['counter_queries']:[]),'is_string'));
    $queries=array_slice(array_values(array_unique(array_map(fn($q)=>tw_clean($q,300),$queries))),0,(int)$cfg['max_searches_per_run']);
    if ($proposition==='' || count($queries)<2) return ['ok'=>false,'error'=>'Search plan failed validation.'];
    $sources=[];
    foreach($queries as $query){ $found=tw_search($query); if(!($found['ok']??false)) return $found; foreach($found['results'] as $result){
        if(!is_array($result))continue; $url=filter_var((string)($result['url']??''),FILTER_VALIDATE_URL); if(!$url)continue;
        $sources[$url]=['id'=>'S'.(count($sources)+1),'title'=>tw_clean((string)($result['title']??'Source'),300),'url'=>$url,'snippet'=>mb_substr(trim((string)($result['content']??$result['snippet']??'')),0,2500),'query'=>$query];
        if(count($sources)>=16)break 2;
    }}
    if(count($sources)<2) return ['ok'=>false,'error'=>'Insufficient source diversity; no draft was saved.'];
    $fetched=0;
    foreach($sources as $url=>&$source){
        if($fetched>=8)break;
        $page=tw_fetch_source($url);
        if($page['ok']??false){if($page['title']!=='')$source['title']=$page['title'];if($page['content']!=='')$source['content']=$page['content'];$fetched++;}
    }
    unset($source);
    $sourceText=json_encode(array_values($sources),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $schema='{"facts":[{"claim":"...","source_ids":["S1"]}],"inferences":[{"claim":"...","source_ids":["S1"]}],"disputed":[{"claim":"...","source_ids":["S1","S2"]}],"counterevidence":[{"claim":"...","source_ids":["S2"]}],"unknowns":["..."],"limitations":["..."],"verification_paths":["..."],"draft_finding":"...","uncertainty":"low|medium|high"}';
    $system='Return exactly one JSON object matching this schema: '.$schema.'. Treat snippets as leads, not verified full documents. Never invent evidence, quotes, dates, or source IDs. Separate facts from inferences and disputed claims. Include material counterevidence and unknowns. A source ID must exist in the supplied ledger. The finding is a draft for human review, never a verdict.';
    $synth=tw_chat_json((string)$cfg['synthesis_model'],$system,"PROPOSITION:\n$proposition\nSOURCE LEDGER:\n$sourceText",1800);
    if(!($synth['ok']??false))return $synth; $draft=$synth['json'];
    $validIds=array_column(array_values($sources),'id');
    foreach(['facts','inferences','disputed','counterevidence'] as $section){ if(!is_array($draft[$section]??null))return ['ok'=>false,'error'=>'Draft schema failed validation.']; foreach($draft[$section] as $row){ if(!is_array($row)||tw_clean((string)($row['claim']??''),2000)===''||!is_array($row['source_ids']??null)||array_diff($row['source_ids'],$validIds))return ['ok'=>false,'error'=>'Draft citation validation failed.']; }}
    foreach(['unknowns','limitations','verification_paths'] as $section) if(!is_array($draft[$section]??null)) return ['ok'=>false,'error'=>'Draft schema failed validation.'];
    if(tw_clean((string)($draft['draft_finding']??''),5000)==='')return ['ok'=>false,'error'=>'Draft finding was empty.'];
    $input=(int)$planner['input_tokens']+(int)$synth['input_tokens']; $output=(int)$planner['output_tokens']+(int)$synth['output_tokens'];
    if($input>(int)$cfg['max_input_tokens_per_run']||$output>(int)$cfg['max_output_tokens_per_run'])return ['ok'=>false,'error'=>'Token guardrail exceeded; draft rejected.'];
    $usage=tw_usage(); $usage['input_tokens']=(int)$usage['input_tokens']+$input; $usage['output_tokens']=(int)$usage['output_tokens']+$output; $usage['searches']=(int)$usage['searches']+count($queries); tw_write_usage($usage);
    return ['ok'=>true,'research'=>['provider'=>'ollama-cloud','model'=>$cfg['synthesis_model'],'planner_model'=>$cfg['planner_model'],'proposition'=>$proposition,'search_queries'=>$queries,'sources'=>array_values($sources),'facts'=>$draft['facts'],'inferences'=>$draft['inferences'],'disputed'=>$draft['disputed'],'counterevidence'=>$draft['counterevidence'],'unknowns'=>$draft['unknowns'],'limitations'=>$draft['limitations'],'verification_paths'=>$draft['verification_paths'],'draft_finding'=>$draft['draft_finding'],'uncertainty'=>$draft['uncertainty']??'high','human_verdict'=>null,'human_reviewed_at_utc'=>null,'generated_at_utc'=>gmdate('c'),'usage'=>['input_tokens'=>$input,'output_tokens'=>$output,'searches'=>count($queries)]]];
}
