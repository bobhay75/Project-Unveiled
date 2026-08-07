<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';
$env=fs_env();
try{$config=fs_config();}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);} 
fs_dir($env['data_dir']);
$lockPath=$env['data_dir'].'/cron.lock';$lock=fopen($lockPath,'c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){echo"Already running.\n";exit(0);} 
$settings=fs_settings();$now=time();
if(!$settings['enabled']){echo"Autopilot disabled.\n";flock($lock,LOCK_UN);fclose($lock);exit(0);} 
$pauseTs=!empty($settings['paused_until_utc'])?(strtotime((string)$settings['paused_until_utc'])?:0):0;
if($pauseTs>$now){echo"Autopilot paused.\n";flock($lock,LOCK_UN);fclose($lock);exit(0);} 
$nextTs=!empty($settings['next_run_utc'])?(strtotime((string)$settings['next_run_utc'])?:0):0;
if($nextTs>$now){echo"Not due.\n";flock($lock,LOCK_UN);fclose($lock);exit(0);} 
try{
    $report=fs_run_review($config,$settings,'cron');
    $draft=null;
    if(in_array($settings['mode'],['scout_draft','guarded_publish'],true))$draft=fs_auto_draft($report,$config);
    $published=[];
    if($settings['mode']==='guarded_publish'&&!empty($settings['website_auto_publish_approved'])){
        foreach(fs_load_drafts() as $d){if(!is_array($d)||($d['status']??'')!=='approved')continue;[$ok,$msg]=fs_publish_draft((string)$d['id'],$config);$published[]=$msg;if(count($published)>=1)break;}
    }
    $settings['last_run_utc']=gmdate('c');$settings['next_run_utc']=gmdate('c',$now+max(1,(int)$settings['run_interval_hours'])*3600);fs_save_settings($settings);
    fs_log('cron','Autopilot cycle completed',['report'=>$report['id'],'draft'=>$draft['id']??null,'published'=>$published]);
    echo"Autopilot cycle complete. Report {$report['id']}.".($draft?" Draft {$draft['id']}.":"")."\n";
}catch(Throwable $e){fs_log('error','Autopilot cycle failed',['error'=>$e->getMessage()]);fwrite(STDERR,$e->getMessage()."\n");flock($lock,LOCK_UN);fclose($lock);exit(1);} 
flock($lock,LOCK_UN);fclose($lock);
