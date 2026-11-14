<?php
declare(strict_types=1);
define('LIVING_STORY_ENTRY', true);
require __DIR__ . '/../../living-story/lib.php';
umask(0077);
$root = sys_get_temp_dir() . '/ls-test-' . bin2hex(random_bytes(8));
mkdir($root, 0700); putenv('LIVING_STORY_PRIVATE_DIR=' . $root);
function check(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } }
function denied(callable $fn, string $message): void { try { $fn(); } catch (Throwable $e) { return; } throw new RuntimeException('FAIL: ' . $message); }
try {
    denied(fn() => ls_config(), 'missing config fails closed');
    $config = ['enabled' => true, 'api_key' => 'synthetic-test-credential-only', 'access_code_hash' => password_hash('synthetic-preview-code', PASSWORD_DEFAULT), 'daily_scenes' => 2];
    ls_atomic($root . '/config.json', $config); $c=ls_config();
    check($c['daily_scenes'] === 2, 'private config');
    chmod($root . '/config.json', 0644); denied(fn() => ls_config(), 'insecure config denied'); chmod($root . '/config.json', 0600);
    denied(fn() => ls_context('../../privacy'), 'source traversal denied');
    foreach(array_keys(ls_paths()) as $path) { $ctx=ls_context($path); check(strlen($ctx['excerpt'])>500 && count($ctx['sources'])===2 && strlen($ctx['chapter_sha256'])===64,'chapter context '.$path); }
    $story=['path'=>'manuscripts','character'=>'An investigator','appearance'=>'A blue coat and weathered notebook','pages'=>[]];
    $calls=[];
    $fake=function($config,$endpoint,$payload) use (&$calls) {
        $calls[]=[$endpoint,$payload];
        if($endpoint==='responses') return ['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode(['title'=>'The archive','scene'=>'An explicitly imagined scene.','choices'=>['Inspect','Compare','Question'],'visual'=>'An archive with amber light','character'=>'A changed character'])]]]]];
        throw new RuntimeException('synthetic image outage');
    };
    $page=ls_generate($c,$story,'Investigate',$fake);
    check($page['image']===null && $page['character']===$story['appearance'],'image failure keeps scene and fixed appearance');
    check($calls[0][1]['store']===false && $calls[0][1]['text']['format']['strict']===true,'nonstored structured response');
    check(str_contains($calls[0][1]['input'],'reading_context') && str_contains($calls[0][1]['input'],'evidence_notes'),'actual source context included');
    check(count($page['sources'])===2 && !str_contains(json_encode($page),$config['api_key']),'allowlisted sources and no credential leak');
    denied(fn()=>ls_generate($c,$story,'x',fn()=>['status'=>'incomplete','output'=>[]]),'incomplete result rejected');
    denied(fn()=>ls_generate($c,$story,'x',fn()=>['status'=>'completed','output'=>[['content'=>[['type'=>'refusal']]]]]),'refusal rejected');
    denied(fn()=>ls_generate($c,$story,'x',fn()=>['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>'{}']]]]]),'malformed schema rejected');
    ls_budget($c,false); ls_budget($c,false); denied(fn()=>ls_budget($c,false),'daily cap enforced');
    $before=file_get_contents($root.'/usage.json'); denied(fn()=>ls_budget($c,false),'repeat cap enforced'); check(file_get_contents($root.'/usage.json')===$before,'denied calls do not reset budget');
    file_put_contents($root.'/usage.json','{corrupt'); denied(fn()=>ls_budget($c,false),'corrupt budget fails closed'); check(file_get_contents($root.'/usage.json')==='{corrupt','corrupt state preserved');
    unlink($root.'/usage.json');
    for($i=0;$i<8;$i++)ls_budget($c,true); denied(fn()=>ls_budget($c,true),'login attempt cap enforced');
    unlink($root.'/usage.json'); symlink($root.'/config.json',$root.'/usage.json'); denied(fn()=>ls_budget($c,false),'symlinked usage denied'); unlink($root.'/usage.json');
    $demo=json_decode(file_get_contents(__DIR__.'/../../living-story/demo.json'),true,512,JSON_THROW_ON_ERROR);
    foreach($demo as $node){check(count($node['choices'])===3,'three sample choices');foreach($node['choices'] as $choice)check(isset($demo[$choice['next']]),'sample graph target');check(is_file(__DIR__.'/../..'.$node['image']),'sample asset exists');}
    echo "Living Story backend tests passed: sources, schema, refusals, appearance, failure recovery, private permissions, budgets, and sample graph.\n";
} finally {
    foreach(glob($root.'/*') as $p){if(is_file($p)||is_link($p))unlink($p);}rmdir($root);
}
