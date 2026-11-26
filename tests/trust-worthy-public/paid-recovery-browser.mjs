import {execFileSync,spawn} from 'node:child_process';
import {createServer} from 'node:http';
import {once} from 'node:events';
import {mkdirSync,mkdtempSync,readFileSync,writeFileSync,rmSync} from 'node:fs';
import {basename,join,resolve} from 'node:path';
import {tmpdir} from 'node:os';
const root=resolve(import.meta.dirname,'../..'),output=resolve(process.argv[2]||'/tmp/trust-worthy-paid-recovery-browser');
mkdirSync(output,{recursive:true,mode:0o700});
let chrome;for(const candidate of [process.env.CHROME_BIN,'google-chrome-stable','google-chrome','chromium'].filter(Boolean)){try{chrome=execFileSync('which',[candidate],{encoding:'utf8'}).trim();break;}catch{}}
if(!chrome)throw Error('Chrome is required for paid recovery browser checks');
const env={...process.env,TW_GUIDED_RESEARCH_ENABLED:'1',TW_PAYPAL_ENABLED:'0'};delete env.OPENAI_API_KEY;
const privateDir=mkdtempSync(join(tmpdir(),'tw-paid-browser-private-'));
const fixture=`define('TW_PRIVATE_DIR_OVERRIDE',${JSON.stringify(privateDir)}); require ${JSON.stringify(root+'/truth/lib/trust-worthy-paid.php')}; $secret=str_repeat('a',64);tw_ai_prepare_private_dir();tw_ai_atomic_private_write(tw_private_dir().'/question-secret.txt',$secret);$new=tw_paid_create_case(['question'=>'Synthetic paid browser verification'],$secret);$id=$new['case_id'];$case=tw_paid_update_case($id,static function(array $r):array{$r['state']='paid';$r['paypal_order_id']='ORDER123';$r['capture_id']='CAPTURE123';$r['paid_at']=time();return $r;});$_SERVER=['REQUEST_METHOD'=>'POST','HTTP_HOST'=>'bobsome1.com','HTTP_ORIGIN'=>'https://bobsome1.com','CONTENT_TYPE'=>'application/x-www-form-urlencoded'];$_POST=['case_id'=>$id,'recovery_token'=>tw_paid_sign_recovery($case,$secret)]; require ${JSON.stringify(root+'/truth/paid-continue.php')};`;
let source;try{source=execFileSync('php',['-r',fixture],{encoding:'utf8',env});}finally{rmSync(privateDir,{recursive:true,force:true});}
if(!source.includes('paidRecoveryLink'))throw Error('Synthetic PHP recovery page did not render');
// Wait for the actual browser interaction to finish. A virtual-time DOM dump can
// end while Chromium is still reading the downloaded Blob on another thread.
async function runBrowserScenario({name, width, inner, wrapper, pending}) {
  let finish;
  let fail;
  const completion = new Promise((resolve, reject) => { finish = resolve; fail = reject; });
  const profile = mkdtempSync(join(tmpdir(), 'tw-paid-chrome-'));
  const pages = new Map([
    ['/' + basename(wrapper), wrapper],
    ['/' + basename(inner), inner],
  ]);
  const server = createServer(async (request, response) => {
    if (request.method === 'GET' && pages.has(request.url)) {
      response.writeHead(200, {'Content-Type':'text/html; charset=utf-8'});
      response.end(readFileSync(pages.get(request.url)));
      return;
    }
    if (request.method === 'POST' && request.url === '/result' &&
        request.headers.origin === origin) {
      try {
        let body = '';
        for await (const chunk of request) {
          body += chunk;
          if (body.length > 1024 * 1024) throw Error('Unexpectedly large browser evidence');
        }
        const {evidence, dom} = JSON.parse(body);
        writeFileSync(join(output, name + '-browser-dom.html'), dom);
        response.writeHead(204).end();
        finish(evidence);
      } catch (error) {
        response.writeHead(400).end();
        fail(error);
      }
      return;
    }
    response.writeHead(404).end();
  });
  let browser;
  let timer;
  let origin;
  const logs = [];
  try {
    server.listen(0, '127.0.0.1');
    await once(server, 'listening');
    origin = 'http://127.0.0.1:' + server.address().port;
    browser = spawn(chrome, ['--headless=new','--no-sandbox','--disable-gpu',
      '--disable-dev-shm-usage','--disable-background-networking',
      '--disable-background-timer-throttling','--force-device-scale-factor=1',
      '--user-data-dir=' + profile, '--window-size=' + Math.max(500, width) + ',2000',
      origin + '/' + basename(wrapper)], {stdio:['ignore','ignore','pipe']});
    browser.stderr.on('data', chunk => logs.push(chunk));
    browser.once('error', fail);
    browser.once('exit', (code, signal) => fail(Error(name + ': Chrome exited early (' + (signal || code) + ')')));
    timer = setTimeout(() => fail(Error(name + ': timed out waiting for browser evidence')), pending ? 160000 : 30000);
    return await completion;
  } finally {
    clearTimeout(timer);
    if (browser?.pid && browser.exitCode === null && browser.signalCode === null) {
      const stopped = once(browser, 'exit');
      browser.kill();
      await stopped;
    }
    server.closeAllConnections();
    server.close();
    writeFileSync(join(output, name + '-browser.log'), Buffer.concat(logs));
    rmSync(profile, {recursive:true, force:true, maxRetries:3});
  }
}

for(const [scenario,width] of [['completed',375],['lost',375],['lost',1280],['retry',1280],['pending',375]]){
 let html=source.replace(/<link\b[^>]*rel="stylesheet"[^>]*href="(\/truth\/[^"?]+)"[^>]*>/g,(_,path)=>`<style>${readFileSync(root+path,'utf8')}</style>`);
 const scripts=[];html=html.replace(/<script\b[^>]*src="(\/truth\/[^"?]+)"[^>]*><\/script>/g,(_,path)=>{scripts.push(`<script>${readFileSync(root+path,'utf8')}</script>`);return ''});
 const setup=`<script>window.__paidScenario=${JSON.stringify(scenario)};</script><script>${readFileSync(join(root,'tests/trust-worthy-public/paid-recovery-browser-fixture.js'),'utf8')}</script>`;
 const instrument=`<script>addEventListener('load',async()=>{let behavior;try{behavior=await window.__runPaidBehavior()}catch(error){behavior={passed:false,error:String(error)}}const evidence={behavior,width:innerWidth,documentWidth:document.documentElement.scrollWidth};const p=document.createElement('pre');p.id='paid-browser-result';p.hidden=true;p.textContent=btoa(JSON.stringify(evidence));document.body.append(p);parent.postMessage({type:'paid-browser-result',evidence},'*');});</script>`;
 html=html.replace('</body>',setup+scripts.join('\n')+instrument+'</body>');
 const name=`paid-${scenario}-${width}`,inner=join(output,name+'-content.html'),wrapper=join(output,name+'.html');writeFileSync(inner,html);
 const wrapperScript = `addEventListener('message', async event => {
   const frame = document.querySelector('iframe');
   if (event.origin !== location.origin || event.source !== frame.contentWindow ||
       event.data?.type !== 'paid-browser-result') return;
   await fetch('/result', {method:'POST', headers:{'Content-Type':'application/json'},
     body:JSON.stringify({evidence:event.data.evidence, dom:frame.contentDocument.documentElement.outerHTML})});
 });`;
 writeFileSync(wrapper,`<!doctype html><html><head><style>html,body{margin:0;overflow:hidden}iframe{width:${width}px;height:2000px;border:0}</style><script>${wrapperScript}</script></head><body><iframe src="${basename(inner)}"></iframe></body></html>`);
 const evidence = await runBrowserScenario({name, width, inner, wrapper, pending:scenario === 'pending'});
 if(!evidence.behavior.passed||evidence.width!==width||evidence.documentWidth>width+1)throw Error(`${name}: ${JSON.stringify(evidence)}`);
 writeFileSync(join(output,name+'-result.json'),JSON.stringify(evidence,null,2)+'\n');
 console.log(`${name}: ${evidence.behavior.checks} actual DOM recovery checks passed (${evidence.behavior.providerSubmissions} synthetic submissions)`);
}
