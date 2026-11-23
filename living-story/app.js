'use strict';
const $ = id => document.getElementById(id);
const sampleKey = 'pu-living-story-sample-v1';
let demo, authorized = false, csrf = '', busy = false, revision = 0, sampleNode = null;
let retry = null;
function savedSample() { try { return localStorage.getItem(sampleKey); } catch { return null; } }
function message(text) { $('error').textContent = text; $('error').hidden = false; }
function show(section) {
  for (const name of ['home', 'reader', 'loading']) $(name).hidden = name !== section;
  $('error').hidden = true; $('restart').hidden = section === 'home';
  $('restart').disabled = busy; $('logout').disabled = busy;
}
function setMode(value) {
  authorized = value; $('character-field').hidden = !value; $('logout').hidden = !value;
  $('begin').textContent = value ? 'Begin your AI investigation' : 'Enter the sample story';
  $('mode-note').textContent = value ? 'Private AI preview · up to eight scenes per story · daily generation limits apply. Progress is kept for this preview session.' : 'Prepared scenes and existing illustrations. Progress is saved on this browser; no AI request is made.';
  $('resume').hidden = value || !savedSample();
}
async function request(body) {
  const response = await fetch('/living-story/api.php', {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json', 'X-CSRF-Token':csrf}, body:JSON.stringify(body)});
  let data;
  try { data = await response.json(); } catch { throw Error('The server did not return a story. Please try again later.'); }
  if (!response.ok) { if(response.status === 401) { csrf = ''; setMode(false); } throw Error(data.error || 'The story could not continue.'); }
  return data;
}
function render(page, label, choose) {
  $('chapter').textContent = label; $('scene-title').textContent = page.title; $('scene').textContent = page.scene;
  $('source-note').textContent = page.source_note;
  const validImage = typeof page.image === 'string' && (page.image.startsWith('/book/images/timeline/') || page.image.startsWith('data:image/webp;base64,'));
  $('image').hidden = !validImage; $('noimage').hidden = validImage;
  if (validImage) { $('image').src = page.image; } else { $('image').removeAttribute('src'); }
  $('sources').replaceChildren();
  for (const source of page.sources) {
    if (!/^\/book\/read\/[a-z0-9-]+\.html(?:#[a-z0-9-]+)?$/.test(source.url)) continue;
    const li = document.createElement('li'), a = document.createElement('a'); a.href=source.url; a.textContent=source.label; li.append(a); $('sources').append(li);
  }
  $('choices').replaceChildren();
  page.choices.forEach((choice, index) => { const button = document.createElement('button'); button.type='button'; button.className='choice'; button.textContent=`${index+1}. ${typeof choice === 'string' ? choice : choice.label}`; button.addEventListener('click', () => choose(choice)); $('choices').append(button); });
  $('question-form').hidden = !authorized; $('custom').value = '';
  show('reader'); $('scene-title').focus({preventScroll:true}); window.scrollTo({top:0, behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
}
async function sample(node) {
  try {
    if (!demo) { const r=await fetch('/living-story/demo.json'); if(!r.ok) throw Error('Sample unavailable.'); demo=await r.json(); }
    if (!Object.hasOwn(demo,node)) throw Error('Choose a sample path to begin.');
    sampleNode=node; const page=demo[node];
    try { localStorage.setItem(sampleKey,node); } catch { /* Sample remains usable with storage disabled. */ }
    render({...page, source_note:'Prepared imaginative reconstruction. The scene is a companion to the chapter, not a historical transcript.', sources:[{label:'Read the chapter behind this scene',url:`/book/read/${page.chapter}#start-reading`},{label:'Inspect the book’s works consulted',url:'/book/read/appendix-e.html'}]}, 'THE LIVING STORY · SAMPLE', choice => sample(choice.next));
  } catch (error) { show('home'); message(error.message); }
}
async function generate(action, choice = '') {
  if (busy) return;
  const signature=JSON.stringify({action, choice, revision, path:$('path').value, character:$('character').value.trim()});
  if (!retry || retry.signature !== signature) retry={signature,id:crypto.randomUUID()};
  busy=true; show('loading');
  const timer=setTimeout(() => { $('progress').textContent='Still preparing the scene. Illustrations can take longer; keep this page open.'; },25000);
  try {
    const data=await request({action, choice, path:$('path').value, character:$('character').value.trim(), revision, request_id:retry.id});
    revision=data.revision; retry=null; busy=false;
    render(data.page, `THE LIVING STORY · AI SCENE ${revision}`, next => generate('continue',next));
  } catch(error) { busy=false; show(action==='start'?'home':'reader'); message(error.message); }
  finally { busy=false; clearTimeout(timer); $('restart').disabled=false; $('logout').disabled=false; $('progress').textContent='Preparing your scene and illustration. This can take a minute.'; }
}
$('image').addEventListener('error',()=>{ $('image').hidden=true; $('noimage').hidden=false; });
$('story-form').addEventListener('submit',event=>{event.preventDefault(); if(authorized)generate('start'); else sample($('path').value);});
$('resume').addEventListener('click',()=>sample(savedSample()));
$('question-form').addEventListener('submit',event=>{event.preventDefault(); const q=$('custom').value.trim(); if(q)generate('continue',q);});
$('restart').addEventListener('click',()=>{if(busy)return; retry=null; show('home'); setMode(authorized); $('path').focus();});
$('access-form').addEventListener('submit',async event=>{
  event.preventDefault(); if(busy)return; busy=true;
  const button=$('access-form').querySelector('button'); button.disabled=true;
  try {const data=await request({action:'login',code:$('access-code').value});csrf=data.csrf;$('access-code').value='';setMode(true);$('preview-access').open=false;show('home');$('character').focus();}
  catch(error){message(error.message);}finally{busy=false;button.disabled=false;}
});
$('logout').addEventListener('click',async()=>{if(busy)return;try{await request({action:'logout'});csrf='';revision=0;setMode(false);show('home');}catch(error){message(error.message);}});
setMode(false);
fetch('/living-story/api.php?action=status',{credentials:'same-origin',cache:'no-store'}).then(r=>r.json()).then(data=>{
  $('preview-access').hidden=!data.available;
  if(data.authorized){csrf=data.csrf;setMode(true);if(data.story?.page){revision=data.story.revision;render(data.story.page,`THE LIVING STORY · AI SCENE ${revision}`,choice=>generate('continue',choice));}}
}).catch(()=>{/* Public sample works even while the AI preview is unavailable. */});
