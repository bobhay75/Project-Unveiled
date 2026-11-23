import {execFileSync,spawnSync} from 'node:child_process';
import {existsSync,mkdirSync,mkdtempSync,readFileSync,writeFileSync,rmSync} from 'node:fs';
import {basename,join,resolve} from 'node:path';
import {tmpdir} from 'node:os';
import {pathToFileURL} from 'node:url';
const root=resolve(import.meta.dirname,'../..'),output=resolve(process.argv[2]||'/tmp/trust-worthy-paid-recovery-browser');
mkdirSync(output,{recursive:true,mode:0o700});
let chrome;for(const candidate of [process.env.CHROME_BIN,'google-chrome-stable','google-chrome','chromium'].filter(Boolean)){try{chrome=execFileSync('which',[candidate],{encoding:'utf8'}).trim();break;}catch{}}
if(!chrome)throw Error('Chrome is required for paid recovery browser checks');
const env={...process.env,TW_GUIDED_RESEARCH_ENABLED:'1',TW_PAYPAL_ENABLED:'0'};delete env.OPENAI_API_KEY;
const privateDir=mkdtempSync(join(tmpdir(),'tw-paid-browser-private-'));
const fixture=`define('TW_PRIVATE_DIR_OVERRIDE',${JSON.stringify(privateDir)}); require ${JSON.stringify(root+'/truth/lib/trust-worthy-paid.php')}; $secret=str_repeat('a',64);tw_ai_prepare_private_dir();tw_ai_atomic_private_write(tw_private_dir().'/question-secret.txt',$secret);$new=tw_paid_create_case(['question'=>'Synthetic paid browser verification'],$secret);$id=$new['case_id'];$case=tw_paid_update_case($id,static function(array $r):array{$r['state']='paid';$r['paypal_order_id']='ORDER123';$r['capture_id']='CAPTURE123';$r['paid_at']=time();return $r;});$_SERVER=['REQUEST_METHOD'=>'POST','HTTP_HOST'=>'bobsome1.com','HTTP_ORIGIN'=>'https://bobsome1.com','CONTENT_TYPE'=>'application/x-www-form-urlencoded'];$_POST=['case_id'=>$id,'recovery_token'=>tw_paid_sign_recovery($case,$secret)]; require ${JSON.stringify(root+'/truth/paid-continue.php')};`;
let source;try{source=execFileSync('php',['-r',fixture],{encoding:'utf8',env});}finally{rmSync(privateDir,{recursive:true,force:true});}
if(!source.includes('paidRecoveryLink'))throw Error('Synthetic PHP recovery page did not render');
for(const [scenario,width] of [['completed',375],['lost',375],['lost',1280],['retry',1280],['pending',375]]){
 let html=source.replace(/<link\b[^>]*rel="stylesheet"[^>]*href="(\/truth\/[^"?]+)"[^>]*>/g,(_,path)=>`<style>${readFileSync(root+path,'utf8')}</style>`);
 const scripts=[];html=html.replace(/<script\b[^>]*src="(\/truth\/[^"?]+)"[^>]*><\/script>/g,(_,path)=>{scripts.push(`<script>${readFileSync(root+path,'utf8')}</script>`);return ''});
 const setup=`<script>window.__paidScenario=${JSON.stringify(scenario)};</script><script>${readFileSync(join(root,'tests/trust-worthy-public/paid-recovery-browser-fixture.js'),'utf8')}</script>`;
 const instrument=`<script>addEventListener('load',async()=>{let behavior;try{behavior=await window.__runPaidBehavior()}catch(error){behavior={passed:false,error:String(error)}}const evidence={behavior,width:innerWidth,documentWidth:document.documentElement.scrollWidth};const p=document.createElement('pre');p.id='paid-browser-result';p.hidden=true;p.textContent=btoa(JSON.stringify(evidence));document.body.append(p);parent.postMessage({type:'paid-browser-result',evidence},'*');});</script>`;
 html=html.replace('</body>',setup+scripts.join('\n')+instrument+'</body>');
 const name=`paid-${scenario}-${width}`,inner=join(output,name+'-content.html'),wrapper=join(output,name+'.html');writeFileSync(inner,html);
 writeFileSync(wrapper,`<!doctype html><html><head><style>html,body{margin:0;overflow:hidden}iframe{width:${width}px;height:2000px;border:0}</style><script>addEventListener('message',event=>{if(event.data?.type!=='paid-browser-result')return;const p=document.createElement('pre');p.id='paid-browser-result';p.hidden=true;p.textContent=btoa(JSON.stringify(event.data.evidence));document.body.append(p)});</script></head><body><iframe src="${basename(inner)}"></iframe></body></html>`);
 const flags=['--headless=new','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--allow-file-access-from-files','--force-device-scale-factor=1',`--window-size=${Math.max(500,width)},2000`,`--virtual-time-budget=${scenario==='pending'?130000:15000}`,'--dump-dom',pathToFileURL(wrapper).href];
 const run=spawnSync(chrome,flags,{encoding:'utf8',timeout:30000,maxBuffer:12*1024*1024});
 if(run.status!==0)throw Error(`${name}: Chrome failed ${run.stderr}`);
 writeFileSync(join(output,name+'-browser-dom.html'),run.stdout);
 writeFileSync(join(output,name+'-browser.log'),run.stderr);
 const match=run.stdout.match(/<pre\b[^>]*id="paid-browser-result"[^>]*>([A-Za-z0-9+/=]+)<\/pre>/);
 if(!match)throw Error(`${name}: browser result missing`);
 const evidence=JSON.parse(Buffer.from(match[1],'base64').toString('utf8'));
 if(!evidence.behavior.passed||evidence.width!==width||evidence.documentWidth>width+1)throw Error(`${name}: ${JSON.stringify(evidence)}`);
 writeFileSync(join(output,name+'-result.json'),JSON.stringify(evidence,null,2)+'\n');
 console.log(`${name}: ${evidence.behavior.checks} actual DOM recovery checks passed (${evidence.behavior.providerSubmissions} synthetic submissions)`);
}
