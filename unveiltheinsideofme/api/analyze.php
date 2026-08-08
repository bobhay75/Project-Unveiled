<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['message'=>'POST required.']); exit; }
$raw=file_get_contents('php://input');
if($raw===false||strlen($raw)>120000){http_response_code(413);echo json_encode(['message'=>'Story payload is too large.']);exit;}
$data=json_decode($raw,true);$story=trim((string)($data['story']??''));
if(strlen($story)<80){http_response_code(422);echo json_encode(['message'=>'Please provide more story before requesting Deep AI Reflection.']);exit;}
$key=getenv('INSIDE_OF_ME_OPENAI_API_KEY')?:'';$model=getenv('INSIDE_OF_ME_OPENAI_MODEL')?:'';
if($key===''||$model===''){http_response_code(503);echo json_encode(['configured'=>false,'message'=>'Deep AI Reflection is intentionally disabled until INSIDE_OF_ME_OPENAI_API_KEY and INSIDE_OF_ME_OPENAI_MODEL are configured on the server. Local Reflection remains fully available.']);exit;}
$system=<<<'PROMPT'
You are the reflection engine for "Inside of Me," a life-story application.
Help the user see possible connections between life experiences, learned adaptations, strengths, present-day costs, values, and future choices.
Hard rules:
- Do not diagnose mental illness, personality disorders, trauma disorders, attachment disorders, or medical conditions.
- Do not claim one event caused a later behavior. Use calibrated language such as may, could, appears, and one possible connection.
- Distinguish facts the user stated from interpretation.
- Do not invent events, motives, memories, family history, or abuse.
- Surface strengths alongside wounds/adaptations.
- Do not shame, flatter, preach, or tell the user who they are.
- If evidence is thin, say so.
- If the story indicates immediate danger or intent to harm self/others, do not analyze it as a personality pattern; advise immediate local emergency/professional support.
- Return JSON only with: summary, patterns[{name,evidence[],possible_adaptation,strength,possible_cost,reflection_question,confidence}], story_arc{opening,pressure,turning_point,becoming,next_chapter}.
PROMPT;
$payload=['model'=>$model,'input'=>[['role'=>'system','content'=>[['type'=>'input_text','text'=>$system]]],['role'=>'user','content'=>[['type'=>'input_text','text'=>"LIFE STORY:\n".$story]]]],'max_output_tokens'=>1800];
$ch=curl_init('https://api.openai.com/v1/responses');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES)]);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
if($body===false||$status<200||$status>=300){http_response_code(502);echo json_encode(['message'=>'Deep AI provider request failed.','provider_status'=>$status,'detail'=>$error?:null]);exit;}
$response=json_decode($body,true);$text=extract_text($response);if($text===null){http_response_code(502);echo json_encode(['message'=>'Deep AI returned an unreadable response.']);exit;}
$reflection=json_decode(trim_fence($text),true);if(!is_array($reflection)){http_response_code(502);echo json_encode(['message'=>'Deep AI did not return valid reflection JSON.']);exit;}echo json_encode($reflection,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
function extract_text(mixed $node):?string{if(is_array($node)){if(isset($node['text'])&&is_string($node['text'])&&trim($node['text'])!=='')return $node['text'];foreach($node as $v){$f=extract_text($v);if($f!==null)return $f;}}return null;}
function trim_fence(string $text):string{$text=trim($text);$text=preg_replace('/^```(?:json)?\s*/i','',$text)??$text;$text=preg_replace('/\s*```$/','',$text)??$text;return trim($text);}
