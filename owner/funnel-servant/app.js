function sourceText(id){
  const el=document.getElementById(id);return el?(el.value||el.textContent||'').trim():'';
}
function csrfToken(){return document.querySelector('meta[name="csrf-token"]')?.content||'';}
function safeHttpUrl(value){
  try{const u=new URL(value,location.href);return /^https?:$/.test(u.protocol)?u.href:'';}catch(_){return '';}
}
async function copyText(text,el){
  if(!text)return false;
  try{await navigator.clipboard.writeText(text);return true}catch(_){
    try{
      if(el&&typeof el.select==='function'){el.focus();el.select();el.setSelectionRange?.(0,text.length);return document.execCommand('copy');}
      const t=document.createElement('textarea');t.value=text;t.style.position='fixed';t.style.opacity='0';document.body.appendChild(t);t.select();const ok=document.execCommand('copy');t.remove();return ok;
    }catch(__){return false}
  }
}
function setStatus(message,type='ok'){
  const box=document.getElementById('post-now-status');if(!box)return;
  box.textContent=message;box.className='post-now-status '+type;box.scrollIntoView({block:'nearest'});
}
async function activity(event,platform,detail=''){
  const csrf=csrfToken();if(!csrf||!event||!platform)return null;
  const body=new URLSearchParams({csrf,action:'social_activity',event,platform,detail});
  const res=await fetch(location.pathname,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString(),keepalive:true});
  if(!res.ok)throw new Error('Activity log failed');
  return res.json();
}
function selectedShareFiles(){
  const input=document.getElementById('share-media-file');if(!input||!input.files||!input.files.length)return [];
  return Array.from(input.files).slice(0,1);
}
function updateFileLabel(){
  const input=document.getElementById('share-media-file'),label=document.getElementById('share-file-name');if(!input||!label)return;
  label.textContent=input.files?.length?input.files[0].name:'No local media selected';
}
async function nativeShare({text,title,url,platform,fallbackUrl,sourceEl}){
  const tracked=safeHttpUrl(url),fallback=safeHttpUrl(fallbackUrl);
  const cleaned=tracked?text.replace(tracked,'').trim():text;
  const shareData={title:title||'Project Unveiled',text:cleaned};if(tracked)shareData.url=tracked;
  const files=selectedShareFiles();
  if(files.length&&navigator.canShare){try{if(navigator.canShare({files}))shareData.files=files;}catch(_){}}
  if(navigator.share){
    try{
      await navigator.share(shareData);
      await activity('share_handoff',platform,shareData.files?.length?'Local media included':'Text and link handed off');
      setStatus('Handed to the share target. This is not recorded as posted until you press “Mark Posted” after confirming it is public.','ok');
      return;
    }catch(err){
      if(err&&err.name==='AbortError'){
        await activity('share_canceled',platform,'Owner canceled the phone share sheet').catch(()=>{});
        setStatus('Share canceled. Nothing was recorded as posted.','warn');return;
      }
      await activity('share_failed',platform,String(err?.message||'Native share unavailable')).catch(()=>{});
    }
  }
  const copied=await copyText(text,sourceEl);
  if(copied)await activity('copy_ready',platform,'Native share fallback copied the platform copy').catch(()=>{});
  if(fallback){
    await activity('composer_opened',platform,'Native share fallback opened platform').catch(()=>{});
    setStatus((copied?'Copy is ready. ':'Select and copy the text. ')+'Opening '+platform+' as the fallback…','warn');
    location.assign(fallback);return;
  }
  setStatus(copied?'Copy is ready. Open the social app and paste it.':'Share is unavailable. Select the text and copy it manually.','warn');
}
function setCardState(platform,label,type='ok'){
  const el=document.querySelector(`[data-card-state="${CSS.escape(platform)}"]`);if(!el)return;
  el.textContent=label;el.className='platform-state '+type;
}
document.addEventListener('change',e=>{if(e.target?.id==='share-media-file')updateFileLabel();});
document.addEventListener('click',async(e)=>{
  const clearFile=e.target.closest('[data-clear-share-file]');
  if(clearFile){const input=document.getElementById('share-media-file');if(input){input.value='';updateFileLabel();}return;}

  const fill=e.target.closest('[data-fill]');
  if(fill){const t=document.querySelector('textarea[name="message"]');if(t){t.value=fill.dataset.fill;t.focus()}return;}

  const confirm=e.target.closest('[data-confirm-event]');
  if(confirm){
    e.preventDefault();const event=confirm.dataset.confirmEvent,platform=confirm.dataset.platform||'native';
    confirm.disabled=true;
    try{
      await activity(event,platform,event==='confirmed_posted'?'Owner manually confirmed the public post':'Owner confirmed no public post was made');
      if(event==='confirmed_posted'){
        setCardState(platform,'OWNER CONFIRMED POSTED','posted');
        setStatus(platform+' marked posted by owner confirmation. The publishing report now records it as public.','ok');
      }else{
        setCardState(platform,'NOT POSTED','warn');
        setStatus(platform+' marked not posted.','warn');
      }
    }catch(_){setStatus('Could not save the confirmation. Reload and try again.','bad');}
    finally{confirm.disabled=false;}return;
  }

  const asset=e.target.closest('[data-asset-open]');
  if(asset){
    e.preventDefault();const url=safeHttpUrl(asset.dataset.url||asset.getAttribute('href')||''),platform=asset.dataset.platform||'native';
    if(!url){setStatus('That media URL is invalid. Save a valid public HTTPS image or video URL first.','bad');return;}
    await activity('asset_opened',platform,asset.dataset.assetType||'media').catch(()=>{});location.assign(url);return;
  }

  const copy=e.target.closest('[data-copy-target]:not([data-native-share]):not([data-platform-action])');
  if(copy){
    e.preventDefault();const el=document.getElementById(copy.dataset.copyTarget),text=sourceText(copy.dataset.copyTarget),ok=await copyText(text,el),platform=copy.dataset.platform||'';
    if(ok&&platform)await activity('copy_ready',platform,'Owner pressed Copy').catch(()=>{});
    const old=copy.textContent;copy.textContent=ok?'Copied':'Select + Copy';setStatus(ok?'Copied to clipboard.':'Clipboard was blocked. Select the text and copy it manually.',ok?'ok':'warn');setTimeout(()=>copy.textContent=old,1300);return;
  }

  const native=e.target.closest('[data-native-share]');
  if(native){
    e.preventDefault();const el=document.getElementById(native.dataset.copyTarget),text=sourceText(native.dataset.copyTarget);
    await nativeShare({text,title:native.dataset.shareTitle,url:native.dataset.shareUrl,platform:native.dataset.platform||'native',fallbackUrl:native.dataset.url||'',sourceEl:el});return;
  }

  const action=e.target.closest('[data-platform-action]');
  if(action){
    e.preventDefault();const platform=action.dataset.platform||'native',mode=action.dataset.mode||'dashboard',url=safeHttpUrl(action.dataset.url||''),tracked=safeHttpUrl(action.dataset.shareUrl||'');
    const el=document.getElementById(action.dataset.copyTarget),text=sourceText(action.dataset.copyTarget);
    if(mode==='native'){
      await nativeShare({text,title:action.dataset.shareTitle||'Project Unveiled',url:tracked,platform,fallbackUrl:url,sourceEl:el});return;
    }
    if(!url){await activity('share_failed',platform,'Invalid or empty platform URL').catch(()=>{});setStatus('The platform URL is invalid. Nothing opened.','bad');return;}
    const copied=await copyText(text,el);
    if(copied)await activity('copy_ready',platform,'Platform copy prepared before opening').catch(()=>{});
    await activity('composer_opened',platform,'Official composer or dashboard opened').catch(()=>{});
    setStatus((copied?'Copy is ready. ':'Clipboard was blocked; the text remains on this page. ')+'Opening '+platform+'…',copied?'ok':'warn');
    location.assign(url);return;
  }

  const direct=e.target.closest('[data-direct-open]');
  if(direct){
    e.preventDefault();const platform=direct.dataset.platform||'native',url=safeHttpUrl(direct.dataset.url||direct.getAttribute('href')||'');
    if(!url){setStatus('That platform link is invalid. Nothing opened.','bad');return;}
    await activity('composer_opened',platform,'Direct Open used').catch(()=>{});location.assign(url);return;
  }
});
updateFileLabel();
const latest=document.getElementById('latest');if(latest)latest.scrollIntoView({block:'end'});

document.querySelectorAll('form[data-community-delete]').forEach(form=>{
  form.addEventListener('submit',event=>{
    if(!window.confirm('Permanently delete this reader submission? This cannot be undone.'))event.preventDefault();
  });
});
