import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const root = resolve(import.meta.dirname, '../..');
const output = resolve(process.argv[2] || '/tmp/trust-worthy-guided-layout');
mkdirSync(output, { recursive: true, mode: 0o700 });
let chrome;
for (const candidate of [process.env.CHROME_BIN, 'google-chrome-stable', 'google-chrome', 'chromium'].filter(Boolean)) {
  try { chrome = execFileSync('which', [candidate], { encoding: 'utf8' }).trim(); break; } catch {}
}
if (!chrome) throw new Error('Chrome is required for the guided release layout check');
const env = { ...process.env, TW_GUIDED_RESEARCH_ENABLED: '1' };
delete env.OPENAI_API_KEY;
const fixture = String.raw`$_SERVER=['REQUEST_METHOD'=>'POST','HTTP_HOST'=>'bobsome1.com','HTTP_ORIGIN'=>'https://bobsome1.com','CONTENT_TYPE'=>'application/x-www-form-urlencoded']; $_POST=['question'=>'Synthetic layout question: which original records establish this claim?','context'=>'Synthetic review only','opened_at'=>(string)(time()-6),'website'=>''];`;
const instrument = String.raw`<script>
window.addEventListener('load',async function(){
 const behavior=window.__runGuidedBehavior?await window.__runGuidedBehavior():null;
 const shown=e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e);return r.width>0&&r.height>0&&s.visibility!=='hidden'&&s.display!=='none'};
 const controls=[...document.querySelectorAll('a[href],button:not([disabled]),input:not([type="hidden"]):not([disabled]),textarea:not([disabled]),select:not([disabled])')].filter(shown);
 const failures=[];
 controls.forEach(e=>{e.focus({preventScroll:true});if(document.activeElement!==e)failures.push(e.id||e.tagName)});
 window.scrollTo(0,0);
 const result={behavior,width:innerWidth,documentWidth:document.documentElement.scrollWidth,focusable:controls.length,focusFailures:failures,heading:document.querySelector('h1')?.textContent?.trim(),deepAction:document.querySelector('[value="deep"]')?.getAttribute('formaction')||null};
 const pre=document.createElement('pre');pre.id='guided-layout-result';pre.hidden=true;pre.textContent=btoa(JSON.stringify(result));document.body.append(pre);parent.postMessage({type:'guided-layout-result',result},'*');
});</script>`;
for (const page of ['guided','investigation']) {
  let html = execFileSync('php', ['-r', `${fixture} require '${root}/truth/${page}.php';`], { encoding:'utf8', env });
  html = html.replace(/<link\b[^>]*rel="stylesheet"[^>]*href="(\/truth\/[^"?]+)"[^>]*>/g,
    (_, path) => `<style>${readFileSync(root + path, 'utf8')}</style>`);
  const scripts = [];
  html = html.replace(/<script\b[^>]*src="(\/truth\/[^"?]+)"[^>]*><\/script>/g,
    (_, path) => { scripts.push(`<script>${readFileSync(root + path, 'utf8')}</script>`); return ''; });
  html = html.replace('</body>', scripts.join('\n') + '</body>');
  if(page==='investigation') html=html.replace('</body>',`<script>${readFileSync(join(root,'tests/trust-worthy-public/guided-browser-fixture.js'),'utf8')}</script></body>`);
  html = html.replace('</body>', instrument + '</body>');
  for (const width of [375, 1280]) {
    const name = `${page}-${width}`;
    const inner = join(output, name + '-content.html');
    const wrapper = join(output, name + '.html');
    const png = join(output, name + '.png');
    writeFileSync(inner, html);
    writeFileSync(wrapper, `<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;overflow:hidden}iframe{width:${width}px;height:2000px;border:0;display:block}</style><script>addEventListener('message',e=>{if(e.data?.type!=='guided-layout-result')return;const p=document.createElement('pre');p.id='guided-layout-result';p.hidden=true;p.textContent=btoa(JSON.stringify(e.data.result));document.body.append(p)})</script></head><body><iframe title="Synthetic guided review" src="${basename(inner)}"></iframe></body></html>`);
    const flags=['--headless=new','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--hide-scrollbars','--allow-file-access-from-files','--force-device-scale-factor=1',`--window-size=${Math.max(500,width)},2000`,'--virtual-time-budget=3000'];
    const result=spawnSync(chrome,[...flags,'--dump-dom',pathToFileURL(wrapper).href],{encoding:'utf8',maxBuffer:12*1024*1024,timeout:30000});
    if(result.status!==0)throw new Error(`Chrome could not render ${name}: ${result.stderr}`);
    const match=result.stdout.match(/<pre\b[^>]*id="guided-layout-result"[^>]*>([A-Za-z0-9+/=]+)<\/pre>/);
    if(!match)throw new Error(`${name} did not produce browser evidence`);
    const evidence=JSON.parse(Buffer.from(match[1],'base64').toString('utf8'));
    if(evidence.width!==width||evidence.documentWidth>width+1||evidence.focusFailures.length||evidence.focusable<3||!evidence.heading)throw new Error(`${name} failed layout/focus: ${JSON.stringify(evidence)}`);
    if(page==='investigation'&&(!evidence.behavior?.passed||evidence.behavior.checks!==9))throw new Error(`${name} failed actual UI behavior: ${JSON.stringify(evidence.behavior)}`);
    if(page==='guided'&&evidence.deepAction!=='/truth/investigation.php')throw new Error('Guided form has the wrong action');
    writeFileSync(join(output,name+'-result.json'),JSON.stringify(evidence,null,2)+'\n');
    const capture=spawnSync(chrome,[...flags,`--screenshot=${png}`,pathToFileURL(wrapper).href],{encoding:'utf8',timeout:30000});
    if(capture.status!==0||!existsSync(png))throw new Error(`Screenshot failed for ${name}`);
    console.log(`${name}: layout, keyboard focus and guided route/interaction checks passed`);
  }
}
