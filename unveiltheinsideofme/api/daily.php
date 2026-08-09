<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['message'=>'POST required.']);exit;}
$raw=file_get_contents('php://input');if($raw===false||strlen($raw)>60000){http_response_code(413);echo json_encode(['message'=>'Reflection request is too large.']);exit;}
$d=json_decode($raw,true);if(!is_array($d)){http_response_code(400);echo json_encode(['message'=>'Invalid request.']);exit;}
$mode=in_array(($d['mode']??''),['reflect','scenario'],true)?$d['mode']:'reflect';
$text=trim((string)($d['text']??''));$reply=trim((string)($d['reply']??''));$story=!empty($d['use_story_context'])?substr(trim((string)($d['story_context']??'')),0,12000):'';$faith=!empty($d['faith_mode']);$emotions=is_array($d['emotions']??null)?array_slice(array_map('strval',$d['emotions']),0,10):[];
if($text===''&&$reply===''){http_response_code(422);echo json_encode(['message'=>'Tell me what happened or what you want to say first.']);exit;}
$key=getenv('INSIDE_OF_ME_OPENAI_API_KEY')?:'';$model=getenv('INSIDE_OF_ME_OPENAI_MODEL')?:'';if($key===''||$model===''){http_response_code(503);echo json_encode(['configured'=>false,'message'=>'Deep reflection is not configured on the server yet.']);exit;}
$system=<<<'PROMPT'
You are the Truth Mirror and Scenario Lab for "Inside of Me," a private self-reflection application.
Purpose: help a user release emotion, see a situation more clearly, and explore plausible consequences before they choose their own next move.
Hard rules:
- Never tell the user what they must say or do, except when immediate safety requires directing them toward real-world emergency/professional help.
- Never claim to predict the future. Generate plausible branches only. Do not use fake numeric probabilities.
- Do not automatically agree with the user. Point out claims that exceed the evidence, internal contradictions, manipulation, retaliation, demeaning language, or obvious factual/logical misalignment.
- Separate observable facts, subjective feelings, interpretations, assumptions, and unknowns.
- A feeling can be fully real without proving someone else's hidden motive.
- Do not diagnose mental illness or claim a childhood event certainly caused a present behavior.
- If explicitly enabled story context is supplied, recurring-pattern observations are hypotheses until the user confirms them.
- If faith mode is ON, compare the user's proposed words/actions with broad Christian values: truth, love, mercy, justice, humility, courage, forgiveness, and self-control. Never claim to speak for God, pronounce divine judgment, or say "God told me."
- Preserve agency. The user decides what fits and what to do.
- For explicit imminent self-harm, suicide intent, or intent to harm another person, stop ordinary role-play and prioritize immediate real-world safety support.
Return JSON only, no markdown fences.
PROMPT;
if($mode==='reflect')$shape='Return exactly these keys: summary string; facts array; feelings array; interpretations array; unknowns array; reality_check array; alignment array; pattern_hypotheses array; questions array.';
else $shape='Return exactly these keys: setup string; branches array of exactly 4 objects each with title, likely_dynamic, possible_response, consequence, tradeoff; reality_check array; questions array. Use calibrated words like possible, plausible, may, could.';
$user="MODE: {$mode}\nFAITH MODE: ".($faith?'ON':'OFF')."\nEMOTIONS USER SELECTED: ".json_encode($emotions)."\n{$shape}\n\nSITUATION / VENT:\n{$text}\n\nWHAT THE USER IS CONSIDERING SAYING:\n{$reply}\n\nOPTIONAL SAVED LIFE-STORY CONTEXT (explicit opt-in only):\n{$story}";
$payload=['model'=>$model,'input'=>[['role'=>'system','content'=>[['type'=>'input_text','text'=>$system]]],['role'=>'user','content'=>[['type'=>'input_text','text'=>$user]]]],'max_output_tokens'=>1700];
$ch=curl_init('https://api.openai.com/v1/responses');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
if($body===false||$status<200||$status>=300){http_response_code(502);echo json_encode(['message'=>'Deep reflection provider request failed.','provider_status'=>$status,'detail'=>$error?:null]);exit;}
$r=json_decode($body,true);$txt=extract_text($r);if($txt===null){http_response_code(502);echo json_encode(['message'=>'Deep reflection returned an unreadable response.']);exit;}
$out=json_decode(trim_fence($txt),true);if(!is_array($out)){http_response_code(502);echo json_encode(['message'=>'Deep reflection did not return valid JSON.']);exit;}
echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
function extract_text(mixed $node):?string{if(is_array($node)){if(isset($node['text'])&&is_string($node['text'])&&trim($node['text'])!=='')return $node['text'];foreach($node as $v){$f=extract_text($v);if($f!==null)return $f;}}return null;}
function trim_fence(string $text):string{$text=trim($text);$text=preg_replace('/^```(?:json)?\s*/i','',$text)??$text;$text=preg_replace('/\s*```$/','',$text)??$text;return trim($text);}
