<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store, max-age=0');
require dirname(__DIR__).'/owner/funnel-servant/bootstrap.php';

function rc_out(array $payload,int $status=200): never { http_response_code($status);echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit; }
function rc_same_origin(): bool {
    $rawHost=(string)($_SERVER['HTTP_HOST']??'');$host=strtolower((string)(parse_url('http://'.$rawHost,PHP_URL_HOST)??$rawHost));
    foreach(['HTTP_ORIGIN','HTTP_REFERER'] as $key){$value=(string)($_SERVER[$key]??'');if($value==='')continue;$h=strtolower((string)(parse_url($value,PHP_URL_HOST)??''));if($h!==''&&$h!==$host)return false;}
    return true;
}
function rc_source_url(array $config): string {
    $url=fs_clean($_POST['source_url']??'',600);if($url==='')return '';$parts=parse_url($url);$site=(string)(parse_url(fs_base_url($config),PHP_URL_HOST)??'');
    return is_array($parts)&&in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)&&strtolower((string)($parts['host']??''))===strtolower($site)?$url:'';
}

try{$config=fs_config();$settings=fs_settings();}catch(Throwable $e){rc_out(['ok'=>false,'message'=>'Reader community is temporarily unavailable.'],503);}
$kind=(string)($_REQUEST['type']??'comment');if(!in_array($kind,['comment','review'],true))$kind='comment';
$chapter=$kind==='review'?'book':fs_slug($_REQUEST['chapter']??'chapter','chapter');

if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $enabled=fs_community_enabled($kind,$chapter,$settings);$issued=time();$items=$enabled?fs_community_public($kind,$chapter,120):[];$summary=fs_community_summary();
    rc_out(['ok'=>true,'enabled'=>$enabled,'type'=>$kind,'chapter'=>$chapter,'owner_name'=>(string)($settings['community_owner_name']??'Robert J. Hayes'),'issued'=>$issued,'token'=>fs_community_token($kind,$chapter,$issued,$config),'minimum_seconds'=>4,'items'=>$items,'stats'=>[
        'count'=>count(array_filter($items,fn($r)=>empty($r['parent_id']))),
        'average_rating'=>$kind==='review'?$summary['average_rating']:0,
        'featured'=>$kind==='review'?$summary['featured']:0,
    ]]);
}

if(!rc_same_origin())rc_out(['ok'=>false,'message'=>'Request origin was not accepted.'],403);
if(!fs_community_enabled($kind,$chapter,$settings))rc_out(['ok'=>false,'message'=>'Reader submissions are currently closed for this page.'],403);
$issued=(int)($_POST['issued']??0);$token=(string)($_POST['token']??'');$opened=(int)($_POST['opened_at']??0);
if(!fs_community_verify_token($token,$kind,$chapter,$issued,$config))rc_out(['ok'=>false,'message'=>'The form expired. Reload the page and try again.'],419);
if(trim((string)($_POST['website']??''))!=='')rc_out(['ok'=>true,'message'=>'Thank you. Your submission is awaiting moderation.']);
if($opened<=0||time()-$opened<4)rc_out(['ok'=>false,'message'=>'Please take a few seconds to review your message, then submit it again.'],422);
if(empty($_POST['consent']))rc_out(['ok'=>false,'message'=>'Please confirm that your submission may be published.'],422);
$clientHash=fs_community_client_hash($config);if(!fs_community_rate_allow($clientHash))rc_out(['ok'=>false,'message'=>'Too many submissions were received from this connection. Try again later.'],429);
$name=fs_community_text($_POST['name']??'',100);$body=fs_community_text($_POST['body']??'',5000);$title=$kind==='review'?fs_community_text($_POST['title']??'',180):'';
if(fs_text_len($name)<2||fs_text_len($name)>100)rc_out(['ok'=>false,'message'=>'Enter a display name between 2 and 100 characters.'],422);
if(fs_text_len($body)<8||fs_text_len($body)>5000)rc_out(['ok'=>false,'message'=>'Your message must be between 8 and 5,000 characters.'],422);
$rating=$kind==='review'?(int)($_POST['rating']??0):0;if($kind==='review'&&($rating<1||$rating>5))rc_out(['ok'=>false,'message'=>'Choose a rating from 1 to 5 stars.'],422);
$parentId=$kind==='comment'?fs_clean($_POST['parent_id']??'',80):'';
if($parentId!==''){$parent=fs_community_find(fs_community_load(),$parentId);if(!$parent||($parent['kind']??'')!=='comment'||($parent['chapter']??'')!==$chapter||($parent['status']??'')!=='approved'||!empty($parent['parent_id']))rc_out(['ok'=>false,'message'=>'That comment can no longer receive a reply.'],422);}
$spamScore=fs_community_spam_score($name,$body,'',$opened);$status=$spamScore>=40?'spam':'pending';$pageTitle=fs_community_text($_POST['page_title']??($kind==='review'?'Project Unveiled':$chapter),220);
$row=[
    'id'=>fs_community_id(),'kind'=>$kind,'chapter'=>$chapter,'page_title'=>$pageTitle,'name'=>$name,'title'=>$title,'body'=>$body,'rating'=>$rating,
    'reader_completed'=>$kind==='review'&&!empty($_POST['reader_completed']),'verified'=>false,'featured'=>false,'parent_id'=>$parentId,'status'=>$status,
    'owner_reply'=>'','owner_reply_at_utc'=>null,'moderation_note'=>'','created_at_utc'=>gmdate('c'),'updated_at_utc'=>gmdate('c'),
    'source_url'=>rc_source_url($config),'client_hash'=>$clientHash,'user_agent_hash'=>hash('sha256',fs_clean($_SERVER['HTTP_USER_AGENT']??'',500)),'spam_score'=>$spamScore,
];
try{fs_community_add($row);fs_log('community_submission','Reader submission received',['id'=>$row['id'],'kind'=>$kind,'chapter'=>$chapter,'status'=>$status]);if($status==='pending')fs_community_notify($row,$settings,$config);}catch(Throwable $e){rc_out(['ok'=>false,'message'=>'Your submission could not be saved. Please try again.'],500);}
rc_out(['ok'=>true,'message'=>$status==='pending'?'Thank you. Your submission is awaiting moderation.':'Thank you. Your submission was received for review.']);
