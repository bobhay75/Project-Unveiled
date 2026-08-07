<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
function pu_out(bool $ok,string $message,int $code=200):never{http_response_code($code);echo json_encode(['ok'=>$ok,'message'=>$message],JSON_UNESCAPED_SLASHES);exit;}
function pu_write_json(string $file,array $data):bool{$tmp=$file.'.tmp-'.bin2hex(random_bytes(4));$json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);if($json===false||file_put_contents($tmp,$json,LOCK_EX)===false)return false;@chmod($tmp,0640);if(!@rename($tmp,$file)){@unlink($tmp);return false;}return true;}
function pu_len(string $value):int{return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value);}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')pu_out(false,'POST required.',405);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=strtolower((string)($_SERVER['HTTP_HOST']??''));$host=preg_replace('/:\d+$/','',$host)??'';$allowedHosts=['bobsome1.com','www.bobsome1.com'];
if(!in_array($host,$allowedHosts,true))pu_out(false,'Host rejected.',403);
if($origin!==''&&!in_array(strtolower((string)parse_url($origin,PHP_URL_HOST)),$allowedHosts,true))pu_out(false,'Origin rejected.',403);
if(trim((string)($_POST['website']??''))!=='')pu_out(true,'Thank you.');
$started=(int)($_POST['started_ms']??0);if($started>0&&(int)(microtime(true)*1000)-$started<1800)pu_out(false,'Please wait a moment and try again.',429);
$name=trim((string)preg_replace('/\s+/u',' ',(string)($_POST['first_name']??'')));
$email=strtolower(trim((string)($_POST['email']??'')));$consent=(string)($_POST['consent']??'');$version=substr(trim((string)($_POST['consent_version']??'2026-07-v2')),0,80);
if($name===''||pu_len($name)>80)pu_out(false,'Enter your first name.',422);
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)pu_out(false,'Enter a valid email address.',422);
if($consent!=='yes')pu_out(false,'Consent is required to join the email list.',422);
$private=dirname(__DIR__,2).'/site-private/project-unveiled';if(!is_dir($private)&&!mkdir($private,0750,true))pu_out(false,'Storage is unavailable.',500);
$dataFile=$private.'/subscribers.json';$rateFile=$private.'/subscriber-rate.json';$secretFile=$private.'/subscriber-secret.txt';
if(!is_file($secretFile)){file_put_contents($secretFile,bin2hex(random_bytes(32)),LOCK_EX);@chmod($secretFile,0640);} $secret=trim((string)file_get_contents($secretFile));
$ip=(string)($_SERVER['REMOTE_ADDR']??'');$ua=(string)($_SERVER['HTTP_USER_AGENT']??'');$ipHash=hash_hmac('sha256',$ip,$secret);$uaHash=hash_hmac('sha256',$ua,$secret);
$rates=is_file($rateFile)?json_decode((string)file_get_contents($rateFile),true):[];if(!is_array($rates))$rates=[];$now=time();foreach($rates as $k=>$t)if(!is_int($t)||$t<$now-3600)unset($rates[$k]);if(isset($rates[$ipHash])&&$rates[$ipHash]>$now-30)pu_out(false,'Please wait before trying again.',429);$rates[$ipHash]=$now;pu_write_json($rateFile,$rates);
$items=is_file($dataFile)?json_decode((string)file_get_contents($dataFile),true):[];if(!is_array($items))$items=[];
$source=substr((string)($_POST['source_url']??''),0,1200);$parts=parse_url($source);$query=[];if(is_array($parts)&&isset($parts['query']))parse_str((string)$parts['query'],$query);
$token=bin2hex(random_bytes(24));$found=false;$stamp=gmdate('c');
foreach($items as &$row){if(is_array($row)&&strtolower((string)($row['email']??''))===$email){$row=array_merge($row,['first_name'=>$name,'email'=>$email,'status'=>'subscribed','updated_at'=>$stamp,'source_url'=>$source,'utm_source'=>substr((string)($query['utm_source']??''),0,120),'utm_medium'=>substr((string)($query['utm_medium']??''),0,120),'utm_campaign'=>substr((string)($query['utm_campaign']??''),0,180),'utm_content'=>substr((string)($query['utm_content']??''),0,180),'consent_text'=>'I agree to receive Project Unveiled email updates. I can unsubscribe at any time.','consent_version'=>$version,'consent_at'=>$stamp,'unsubscribe_token'=>$token,'ip_hash'=>$ipHash,'ua_hash'=>$uaHash]);$found=true;break;}}unset($row);
if(!$found)$items[]=['id'=>bin2hex(random_bytes(8)),'first_name'=>$name,'email'=>$email,'status'=>'subscribed','created_at'=>$stamp,'updated_at'=>$stamp,'source_url'=>$source,'utm_source'=>substr((string)($query['utm_source']??''),0,120),'utm_medium'=>substr((string)($query['utm_medium']??''),0,120),'utm_campaign'=>substr((string)($query['utm_campaign']??''),0,180),'utm_content'=>substr((string)($query['utm_content']??''),0,180),'consent_text'=>'I agree to receive Project Unveiled email updates. I can unsubscribe at any time.','consent_version'=>$version,'consent_at'=>$stamp,'unsubscribe_token'=>$token,'ip_hash'=>$ipHash,'ua_hash'=>$uaHash];
if(!pu_write_json($dataFile,$items))pu_out(false,'Signup could not be saved.',500);
$unsubscribe='https://bobsome1.com/book/unsubscribe.php?token='.rawurlencode($token);$subject='Welcome to Project Unveiled';$body="Hello {$name},\n\nYou are on the Project Unveiled reader list. We do not sell, rent, or trade your personal information. Ever.\n\nUnsubscribe: {$unsubscribe}\n";@mail($email,$subject,$body,"From: Project Unveiled <noreply@bobsome1.com>\r\nContent-Type: text/plain; charset=UTF-8");
pu_out(true,$found?'Your subscription has been updated.':'You are signed up. Welcome to Project Unveiled.');
