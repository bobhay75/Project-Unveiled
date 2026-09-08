<?php
declare(strict_types=1);
session_start(); header('Cache-Control: no-store, max-age=0'); require_once dirname(__DIR__,2).'/truth/lib/ollama-provider.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!hash_equals((string)($_SESSION['tw_csrf']??''),(string)($_POST['csrf']??''))){http_response_code(403);exit('Request rejected.');}
$id=preg_replace('/[^a-f0-9]/','',(string)($_POST['id']??'')); $found=tw_find_question($id);
if($found===null){http_response_code(404);exit('Queue item not found.');} [$index,$item]=$found;
if(($item['status']??'')!=='queued'){http_response_code(409);exit('Only queued items can run.');}
$result=tw_run_research($item); if(!($result['ok']??false)){http_response_code(422);exit(htmlspecialchars((string)($result['error']??'Research failed safely.'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));}
$item['research']=$result['research']; $item['status']='draft';
if(!tw_replace_question($index,$item)){http_response_code(500);exit('Draft could not be saved.');}
header('Location: review.php?id='.rawurlencode($id),true,303);
