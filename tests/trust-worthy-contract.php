<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/truth/lib/ollama-provider.php';

$failures=[];
function check(bool $condition,string $message):void{global $failures;if(!$condition)$failures[]=$message;}

check(tw_json_object('{"ok":true}')===['ok'=>true],'plain JSON should parse');
check(tw_json_object("```json\n{\"ok\":true}\n```")===['ok'=>true],'fenced JSON should parse');
check(tw_json_object('not JSON')===null,'non-JSON should fail closed');
$cfg=tw_ollama_config();
check((int)$cfg['daily_runs']>0 && (int)$cfg['daily_runs']<=20,'daily run cap must remain bounded');
check((int)$cfg['max_searches_per_run']>0 && (int)$cfg['max_searches_per_run']<=40,'search cap must remain bounded');
check((float)$cfg['run_cost_usd']>0 && (float)$cfg['daily_cost_usd']>0,'cost reservations must be positive');
check(!str_contains((string)file_get_contents(dirname(__DIR__).'/truth/investigate.php'),'tw_run_research'),'public endpoint must not invoke research');

if($failures){fwrite(STDERR,"Contract checks failed:\n- ".implode("\n- ",$failures)."\n");exit(1);}
echo "Trust-Worthy contract checks passed.\n";
