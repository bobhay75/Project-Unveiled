<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['message'=>'POST required.']);exit;}
$raw=file_get_contents('php://input');
if($raw===false||strlen($raw)>20000){http_response_code(413);echo json_encode(['message'=>'Visual request is too large.']);exit;}
$data=json_decode($raw,true);
$scene=trim((string)($data['scene']??''));$when=trim((string)($data['when']??''));$emotion=trim((string)($data['emotion']??''));$note=trim((string)($data['visual_note']??''));$truth=trim((string)($data['truth_status']??'From the storyteller’s words'));
if(strlen($scene)<12){http_response_code(422);echo json_encode(['message'=>'Approve a specific story scene before generating imagery.']);exit;}
$key=getenv('INSIDE_OF_ME_OPENAI_API_KEY')?:'';
$model=getenv('INSIDE_OF_ME_IMAGE_MODEL')?:'gpt-image-2';
if($key===''){http_response_code(503);echo json_encode(['configured'=>false,'message'=>'Image generation is not configured yet. Set INSIDE_OF_ME_OPENAI_API_KEY on the server.']);exit;}
$scene=substr($scene,0,1600);$when=substr($when,0,120);$emotion=substr($emotion,0,80);$note=substr($note,0,420);$truth=substr($truth,0,120);
$prompt="Create one vertical cinematic film still for a truthful autobiographical storyboard.\n\nSTORY MOMENT: {$scene}\nTIME/AGE LABEL: {$when}\nEMOTIONAL CENTER: {$emotion}\nUSER VISUAL DIRECTION: {$note}\nTRUTH STATUS: {$truth}\n\nRequirements:\n- 9:16-oriented cinematic composition suitable for a life-story reel.\n- Emotion must be visible through composition, posture, distance, light, environment, and perspective rather than written text.\n- No captions, logos, typography, watermarks, or UI.\n- Do not invent factual details that change the event. When a detail is unknown, keep it visually ambiguous rather than fabricating specificity.\n- If exact appearance of real people is unknown, avoid pretending to reproduce their identity; favor non-identifying angles, distance, silhouette, hands, environment, or restrained facial detail.\n- If the scene involves abuse, violence, death, or a child in distress, make it non-graphic and emotionally truthful; show perspective, atmosphere, consequence, or aftermath rather than explicit harm.\n- Photorealistic, natural cinematic lighting, believable period details only when supported by the story, emotionally restrained rather than sensational.\n- The frame should feel like one remembered moment from a serious feature film.";
$payload=['model'=>$model,'prompt'=>$prompt,'size'=>'1024x1536','quality'=>'low','output_format'=>'webp','output_compression'=>70,'n'=>1];
$ch=curl_init('https://api.openai.com/v1/images/generations');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>150,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
if($body===false||$status<200||$status>=300){$detail=null;$decoded=is_string($body)?json_decode($body,true):null;if(is_array($decoded))$detail=$decoded['error']['message']??null;http_response_code($status===401||$status===403?503:502);echo json_encode(['message'=>$detail?:'Image provider request failed.','provider_status'=>$status,'detail'=>$error?:null]);exit;}
$response=json_decode($body,true);$image=$response['data'][0]['b64_json']??null;if(!is_string($image)||$image===''){http_response_code(502);echo json_encode(['message'=>'Image provider returned no usable image.']);exit;}
echo json_encode(['image'=>$image,'mime'=>'image/webp','model'=>$model,'size'=>'1024x1536','quality'=>'low'],JSON_UNESCAPED_SLASHES);
