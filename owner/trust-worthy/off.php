<?php
declare(strict_types=1);
session_start(); header('Cache-Control: no-store, max-age=0'); require_once dirname(__DIR__,2).'/truth/lib/ollama-provider.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!hash_equals((string)($_SESSION['tw_csrf']??''),(string)($_POST['csrf']??''))){http_response_code(403);exit('Request rejected.');}
if(!tw_disable_research()){http_response_code(500);exit('Kill switch could not be written.');}
header('Location: index.php',true,303);
