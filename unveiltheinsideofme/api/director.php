<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['message'=>'POST required.']);exit;}
$raw=file_get_contents('php://input');
if($raw===false||strlen($raw)>90000){http_response_code(413);echo json_encode(['message'=>'Director request is too large.']);exit;}
$data=json_decode($raw,true);
$events=$data['events']??[];$theme=trim((string)($data['film_theme']??''));
if(!is_array($events)||count($events)<2){http_response_code(422);echo json_encode(['message'=>'Build at least two timeline events before asking the Director to choose reveal scenes.']);exit;}
$clean=[];
foreach(array_slice($events,0,30) as $e){if(!is_array($e))continue;$id=trim((string)($e['id']??''));$text=trim((string)($e['text']??''));if($id===''||strlen($text)<8)continue;$clean[]=['id'=>substr($id,0,120),'when'=>substr(trim((string)($e['when']??'')),0,120),'text'=>substr($text,0,900),'source'=>substr(trim((string)($e['source']??'story')),0,40)];}
if(count($clean)<2){http_response_code(422);echo json_encode(['message'=>'Not enough usable timeline events.']);exit;}
$key=getenv('INSIDE_OF_ME_OPENAI_API_KEY')?:'';$model=getenv('INSIDE_OF_ME_OPENAI_MODEL')?:'';
if($key===''||$model===''){http_response_code(503);echo json_encode(['configured'=>false,'message'=>'Deep Director is not configured. The browser Director will choose scenes locally.']);exit;}
$system=<<<'PROMPT'
You are the cinematic story director for "Inside of Me," an autobiographical storytelling application.
Choose exactly THREE moments from the user's supplied timeline that will make the strongest emotional 15-30 second trailer and best motivate the user to want the full 4-minute life film.
You may ONLY select supplied event IDs. Never invent, merge, rewrite, or intensify events.
Score scene-worthiness using: emotional importance, turning-point significance, visual specificity, connection to later identity, contrast across the three moments, and ability to form an opening-pressure-becoming arc.
Prefer moments that can be shown visually. Avoid choosing three moments that all communicate the same emotion unless the story truly demands it.
For traumatic, violent, abusive, incarceration, addiction, death, or child-distress content, choose non-graphic, non-exploitative visual direction focused on perspective, separation, atmosphere, aftermath, consequence, or resilience rather than explicit harm.
Do not diagnose or make psychological causal claims.
Return JSON only in this exact shape:
{"selected":[{"id":"existing-id","role":"Opening|Pressure|Turning point|Becoming|Next chapter","score":0,"emotion":"one concise emotional center","reason":"why this scene earns premium imagery","visual_direction":"one concise cinematic direction grounded only in known facts"}]}
The selected array must contain exactly three unique supplied IDs when at least three usable events were provided. If only two events exist, return two.
PROMPT;
$user=['film_theme'=>$theme,'events'=>$clean];
$payload=['model'=>$model,'input'=>[['role'=>'system','content'=>[['type'=>'input_text','text'=>$system]]],['role'=>'user','content'=>[['type'=>'input_text','text'=>json_encode($user,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]]]],'max_output_tokens'=>1200];
$ch=curl_init('https://api.openai.com/v1/responses');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
if($body===false||$status<200||$status>=300){http_response_code(502);echo json_encode(['message'=>'Deep Director request failed.','provider_status'=>$status,'detail'=>$error?:null]);exit;}
$response=json_decode($body,true);$text=extract_text($response);if($text===null){http_response_code(502);echo json_encode(['message'=>'Deep Director returned an unreadable response.']);exit;}
$out=json_decode(trim_fence($text),true);if(!is_array($out)||!isset($out['selected'])||!is_array($out['selected'])){http_response_code(502);echo json_encode(['message'=>'Deep Director did not return valid scene-selection JSON.']);exit;}
$allowed=array_column($clean,null,'id');$selected=[];$seen=[];
foreach($out['selected'] as $s){if(!is_array($s))continue;$id=(string)($s['id']??'');if(!isset($allowed[$id])||isset($seen[$id]))continue;$seen[$id]=true;$selected[]=['id'=>$id,'role'=>substr((string)($s['role']??'Defining moment'),0,80),'score'=>max(0,min(100,(int)($s['score']??75))),'emotion'=>substr((string)($s['emotion']??'Uncertainty'),0,80),'reason'=>substr((string)($s['reason']??''),0,320),'visual_direction'=>substr((string)($s['visual_direction']??''),0,420)];if(count($selected)>=3)break;}
if(count($selected)<min(3,count($clean))){http_response_code(502);echo json_encode(['message'=>'Deep Director returned too few valid scene IDs. Use the local Director fallback.']);exit;}
echo json_encode(['selected'=>$selected,'source'=>'deep-director'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
function extract_text(mixed $node):?string{if(is_array($node)){if(isset($node['text'])&&is_string($node['text'])&&trim($node['text'])!=='')return $node['text'];foreach($node as $v){$found=extract_text($v);if($found!==null)return $found;}}return null;}
function trim_fence(string $text):string{$text=trim($text);$text=preg_replace('/^```(?:json)?\s*/i','',$text)??$text;$text=preg_replace('/\s*```$/','',$text)??$text;return trim($text);}
