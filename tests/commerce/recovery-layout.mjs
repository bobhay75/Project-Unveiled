import {execFileSync, spawnSync} from 'node:child_process';
import {mkdirSync, writeFileSync} from 'node:fs';
import {basename, join, resolve} from 'node:path';
import {pathToFileURL} from 'node:url';

const root = resolve(import.meta.dirname, '../..');
const output = resolve(process.argv[2] || '/tmp/pu-bundle-recovery-layout');
mkdirSync(output, {recursive: true, mode: 0o700});
let chrome;
for (const candidate of [process.env.CHROME_BIN, 'google-chrome-stable', 'google-chrome', 'chromium'].filter(Boolean)) {
  try { chrome = execFileSync('which', [candidate], {encoding: 'utf8'}).trim(); break; } catch {}
}
if (!chrome) throw Error('Chrome is required for bundle checkout layout checks');
const instrument = `<script>addEventListener('load',()=>{
  const shown=e=>{const r=e.getBoundingClientRect();return r.width>0&&r.height>0};
  const controls=[...document.querySelectorAll('a[href],button,input:not([type="hidden"])')].filter(shown);
  const failures=[];for(const e of controls){e.focus({preventScroll:true});if(document.activeElement!==e||!e.textContent.trim())failures.push(e.tagName)}
  const result={width:innerWidth,documentWidth:document.documentElement.scrollWidth,heading:document.querySelector('h1')?.textContent.trim(),focusable:controls.length,focusFailures:failures,smallButtons:[...document.querySelectorAll('button')].filter(e=>e.getBoundingClientRect().height<44).length,actions:[...document.querySelectorAll('form')].map(e=>e.getAttribute('action')),methods:[...document.querySelectorAll('form')].map(e=>e.method),csrf:[...document.querySelectorAll('form')].every(e=>e.querySelector('input[name="csrf"]')?.type==='hidden')};
  scrollTo(0,0);parent.postMessage({type:'bundle-layout',result},'*');
});</script>`;
for (const state of ['expired', 'uncertain', 'resume']) {
  const fixture = `require ${JSON.stringify(root + '/store/checkout/lib.php')}; $now=time();$order=['state'=>'created','paypal_order_id'=>'ORDERRECOVERY123','checkout_expires_at'=>$now${state === 'expired' ? '-10' : '+100'},'capture_attempted_at'=>${state === 'resume' ? 'null' : '$now-10'}];bc_page('Your saved study-bundle checkout',bc_existing_checkout_content($order,'synthetic-csrf',$now));`;
  const source = execFileSync(process.env.PHP_BIN || 'php', ['-r', fixture], {encoding: 'utf8'});
  for (const width of [320, 375, 1280]) {
    const name = `checkout-${state}-${width}`;
    const inner = join(output, name + '-content.html');
    const wrapper = join(output, name + '.html');
    writeFileSync(inner, source.replace('</body>', instrument + '</body>'));
    writeFileSync(wrapper, `<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;overflow:hidden}iframe{width:${width}px;height:1800px;border:0;display:block}</style><script>addEventListener('message',e=>{if(e.data?.type!=='bundle-layout')return;const p=document.createElement('pre');p.id='bundle-layout-result';p.hidden=true;p.textContent=btoa(JSON.stringify(e.data.result));document.body.append(p)})</script></head><body><iframe title="Synthetic bundle checkout review" src="${basename(inner)}"></iframe></body></html>`);
    const flags = ['--headless=new', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--allow-file-access-from-files', '--force-device-scale-factor=1', `--window-size=${Math.max(width, 500)},1800`, '--virtual-time-budget=1500'];
    const run = spawnSync(chrome, [...flags, '--dump-dom', pathToFileURL(wrapper).href], {encoding: 'utf8', timeout: 30000, maxBuffer: 2 * 1024 * 1024});
    if (run.status !== 0) throw Error(`${name}: browser failed: ${run.error?.message || run.stderr.slice(-3000)}`);
    const match = run.stdout.match(/<pre\b[^>]*id="bundle-layout-result"[^>]*>([A-Za-z0-9+/=]+)<\/pre>/);
    if (!match) throw Error(`${name}: no browser evidence`);
    const evidence = JSON.parse(Buffer.from(match[1], 'base64').toString('utf8'));
    const expected = state === 'expired' ? ['check.php'] : ['check.php', state === 'resume' ? 'start.php' : 'capture.php'];
    if (evidence.width !== width || evidence.documentWidth > width + 1 || !evidence.heading || evidence.focusable < 4 || evidence.focusFailures.length || evidence.smallButtons || !evidence.csrf || evidence.methods.some(m => m !== 'post') || JSON.stringify(evidence.actions) !== JSON.stringify(expected)) {
      throw Error(`${name}: ${JSON.stringify(evidence)}`);
    }
    writeFileSync(join(output, name + '-result.json'), JSON.stringify(evidence, null, 2) + '\n');
    if (width === 375) {
      const capture = spawnSync(chrome, [...flags, `--screenshot=${join(output, name + '.png')}`, pathToFileURL(wrapper).href], {encoding: 'utf8', timeout: 30000});
      if (capture.status !== 0) throw Error(`${name}: screenshot failed`);
    }
    console.log(`${name}: rendered layout, focus, target size and safe form actions passed`);
  }
}
