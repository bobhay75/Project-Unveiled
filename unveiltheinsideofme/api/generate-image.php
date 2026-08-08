<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['message'=>'POST required.']);exit;}
$raw=file_get_contents('php://input');
if($raw===false||strlen($raw)>24000){http_response_code(413);echo json_encode(['message'=>'Visual request is too large.']);exit;}
$data=json_decode($raw,true);
$scene=trim((string)($data['scene']??''));
$when=trim((string)($data['when']??''));
$emotion=trim((string)($data['emotion']??''));
$note=trim((string)($data['visual_note']??''));
$truth=trim((string)($data['truth_status']??'From the storyteller’s words'));
$role=trim((string)($data['scene_role']??'defining life moment'));
$context=trim((string)($data['story_context']??''));
if(strlen($scene)<12){http_response_code(422);echo json_encode(['message'=>'Approve a specific story scene before generating imagery.']);exit;}
$key=getenv('INSIDE_OF_ME_OPENAI_API_KEY')?:'';
$model=getenv('INSIDE_OF_ME_IMAGE_MODEL')?:'gpt-image-2';
$quality=strtolower(getenv('INSIDE_OF_ME_IMAGE_QUALITY')?:'high');
if(!in_array($quality,['medium','high'],true))$quality='high';
if($key===''){http_response_code(503);echo json_encode(['configured'=>false,'message'=>'Premium image generation is not configured yet. Set INSIDE_OF_ME_OPENAI_API_KEY on the server.']);exit;}
$scene=substr($scene,0,1800);$when=substr($when,0,120);$emotion=substr($emotion,0,80);$note=substr($note,0,520);$truth=substr($truth,0,140);$role=substr($role,0,120);$context=substr($context,0,800);
$prompt="Create ONE premium vertical cinematic keyframe for an autobiographical life-story trailer. This frame must be strong enough to sell the emotional reality of the finished film.\n\nSELECTED SCENE ROLE: {$role}\nSTORY MOMENT: {$scene}\nTIME / AGE: {$when}\nEMOTIONAL CENTER: {$emotion}\nUSER VISUAL DIRECTION: {$note}\nTRUTH STATUS: {$truth}\nNEARBY STORY CONTEXT: {$context}\n\nDIRECTOR REQUIREMENTS:\n- Compose for a 9:16 life-story trailer: intimate, cinematic, emotionally immediate, feature-film quality.\n- The emotion must be carried by body language, distance, environment, lens perspective, light, weather, negative space, and what is left unsaid.\n- Create a single decisive remembered moment, not a generic montage or stock-photo pose.\n- No captions, logos, typography, frames, watermarks, or UI.\n- Never change the event for drama. If a factual detail is unknown, keep it visually ambiguous rather than inventing specificity.\n- Do not claim an unknown face is the real person. Without an approved reference image, use restrained facial detail, profile, distance, silhouette, hands, environment, or over-the-shoulder framing when that better preserves truth.\n- For abuse, violence, death, incarceration, addiction, or a child in distress: keep the image non-graphic and non-exploitative. Show perspective, atmosphere, aftermath, separation, consequence, or emotional reality rather than explicit harm.\n- Use natural skin texture, believable anatomy, realistic materials, plausible period/environment details only when supported, cinematic depth of field, motivated practical light, and restrained color grading.\n- Avoid glossy AI-advertising aesthetics, melodrama, fantasy lighting, exaggerated tears, and inspirational-poster symbolism.\n- The result should feel like a frame from a serious autobiographical feature film.";
$payload=['model'=>$model,'prompt'=>$prompt,'size'=>'1024x1536','quality'=>$quality,'output_format'=>'webp','output_compression'=>88,'n'=>1];
$ch=curl_init('https://api.openai.com/v1/images/generations');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>180,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
if($body===false||$status<200||$status>=300){$detail=null;$decoded=is_string($body)?json_decode($body,true):null;if(is_array($decoded))$detail=$decoded['error']['message']??null;http_response_code($status===401||$status===403?503:502);echo json_encode(['message'=>$detail?:'Image provider request failed.','provider_status'=>$status,'detail'=>$error?:null]);exit;}
$response=json_decode($body,true);$image=$response['data'][0]['b64_json']??null;
if(!is_string($image)||$image===''){http_response_code(502);echo json_encode(['message'=>'Image provider returned no usable image.']);exit;}
echo json_encode(['image'=>$image,'mime'=>'image/webp','model'=>$model,'size'=>'1024x1536','quality'=>$quality],JSON_UNESCAPED_SLASHES);
