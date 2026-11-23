(()=>{
'use strict';
const form=document.getElementById('paidResumeForm');
if(!form)return;
const client=window.TrustWorthyClient;
if(!client)return;
let lastResult=null,busy=false,checking=false,pollTimer=null,pollController=null,pollDeadline=0,pollAttempts=0,pollGeneration=0;
const workspace=document.getElementById('paidWorkspace'),activity=document.getElementById('paidActivity');
const status=document.getElementById('paidStatus'),button=document.getElementById('paidResumeButton');
const result=document.getElementById('paidResult'),verdict=document.getElementById('paidVerdict'),probability=document.getElementById('paidProbability');
const note=document.getElementById('paidNote'),analysis=document.getElementById('paidAnalysis'),sources=document.getElementById('paidSources');
const recoveryMessage=document.getElementById('paidRecoveryMessage'),checkButton=document.getElementById('paidCheckStatus');
const recoveryLink=document.getElementById('paidRecoveryLink');
const log=(title,detail='')=>{const row=document.createElement('div');row.className='event';const b=document.createElement('b');b.textContent=title;row.appendChild(b);if(detail){const s=document.createElement('span');s.textContent=detail;row.appendChild(s)}activity.prepend(row)};
const stage=(name,state)=>{const el=document.querySelector(`[data-stage="${name}"]`);if(!el)return;el.classList.remove('active','done');if(state)el.classList.add(state)};
const safeUrl=client.sourceUrl;
const consume=r=>{if(!r||typeof r!=='object')return;document.querySelectorAll('#stageList li.active').forEach(el=>el.classList.remove('active'));stage(r.stage,'done');const order=['dependency','counter','context','synthesis'];const i=order.indexOf(r.stage);if(i>=0&&i<order.length-1)stage(order[i+1],'active');let detail='';if(Number.isFinite(r.source_count))detail=`${r.source_count} evidence URL${r.source_count===1?'':'s'} attached`;if(r.research_mode==='reuse_no_web')detail='Analyzed the evidence already collected; no new web search used for this stage';if(r.stage==='synthesis'&&r.usage&&Number.isFinite(r.usage.total_tokens))detail=`Final synthesis complete · ${r.usage.total_tokens} total provider tokens across the investigation`;log(r.label||r.stage||'Stage complete',detail)};
const render=out=>{if(!client.validFinding(out))throw new Error('The saved finding did not satisfy its evidence contract. Keep the case reference and contact Bobsome1; do not pay again.');lastResult=out;workspace.hidden=false;document.querySelectorAll('#stageList li.active').forEach(el=>el.classList.remove('active'));for(const name of ['dependency','counter','context','synthesis'])stage(name,'done');result.hidden=false;button.disabled=true;status.textContent='COMPLETE';verdict.textContent=out.verdict;probability.textContent=Number.isFinite(out.probability)?`${out.probability}%`:'NO SCORE';const m=out.metrics||{},u=m.usage||{};note.textContent=Number.isFinite(out.probability)?`Evidence-conditioned experimental probability. ${m.unique_sources||0} unique URLs across ${m.source_families||0} source families. ${Number.isFinite(u.total_tokens)?u.total_tokens+' total provider tokens used.':''}`:(m.evidence_floor_met===false?'No probability was issued because the evidence floor was not met.':'The investigator returned no verdict despite meeting the minimum source-diversity checks.');analysis.textContent=out.analysis;sources.textContent='';out.sources.forEach(src=>{const href=safeUrl(src?.url||'');if(!href)return;const a=document.createElement('a');a.href=href;a.target='_blank';a.rel='noopener noreferrer';a.textContent=(src.title||href)+(src.family?` · ${src.family}`:'');sources.appendChild(a)});result.scrollIntoView({behavior:'smooth',block:'start'})};
const parse=response=>client.readStream(response,ev=>{if(ev.type==='start'){status.textContent='INVESTIGATING';stage('dependency','active');log(ev.label||'Paid continuation started')}else if(ev.type==='receipt')consume(ev.receipt);else if(ev.type==='complete')render(ev.result)},{allowPaywall:false});
const stopPolling=()=>{pollGeneration++;if(pollTimer)clearTimeout(pollTimer);pollTimer=null;if(pollController)pollController.abort();pollController=null;};
const readSaved=async (signal,isCurrent=()=>true)=>{
 const values=new FormData(form),data=new URLSearchParams();data.set('case_id',String(values.get('case_id')||''));data.set('recovery_token',String(values.get('recovery_token')||''));
 const response=await fetch('/truth/paid-status.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:data.toString(),credentials:'same-origin',cache:'no-store',signal});
 let saved={};try{saved=await response.json()}catch{}
 if(!isCurrent())return null;
 if(!response.ok||!saved.ok)throw new Error(saved.message||`Saved status could not be checked (${response.status}). Keep the private recovery link and do not pay again.`);
 if(saved.case_id!==values.get('case_id')||!['paid','resume_authorized','finishing','completed','needs_review'].includes(saved.state))throw new Error('The saved status did not match this investigation.');
 if(recoveryMessage)recoveryMessage.textContent=saved.message||'';
 form.dataset.state=saved.state;
 if(saved.state==='completed')render(saved.result);
 else if(saved.state==='paid'){
  if(typeof saved.resume_token!=='string'||!saved.resume_token)throw new Error('The saved continuation authorization was incomplete.');
  form.elements.namedItem('resume_token').value=saved.resume_token;button.disabled=busy;status.textContent='READY';
 }else{button.disabled=true;status.textContent=saved.state==='needs_review'?'REVIEW NEEDED':'INVESTIGATING';}
 return saved.state;
};
const beginRecovery=async()=>{
 if(checking||busy)return;stopPolling();checking=true;checkButton.disabled=true;pollDeadline=Date.now()+120000;pollAttempts=0;const generation=pollGeneration;
 const tick=async()=>{
  if(generation!==pollGeneration)return;
  const remaining=pollDeadline-Date.now();
  if(remaining<=0||pollAttempts>=24){checking=false;checkButton.disabled=false;recoveryMessage.textContent='The investigation is still pending. Use Check saved status to check again. This will not start another investigation or payment.';return;}
  pollAttempts++;const controller=new AbortController();pollController=controller;const timeout=setTimeout(()=>controller.abort(),Math.min(15000,remaining));
  try{const state=await readSaved(controller.signal,()=>generation===pollGeneration&&!busy);clearTimeout(timeout);if(generation!==pollGeneration)return;pollController=null;
   if(['resume_authorized','finishing'].includes(state)){pollTimer=setTimeout(tick,Math.min(5000,Math.max(1,pollDeadline-Date.now())));return;}
   checking=false;checkButton.disabled=false;
  }catch(err){clearTimeout(timeout);if(generation!==pollGeneration)return;pollController=null;checking=false;checkButton.disabled=false;recoveryMessage.textContent=err instanceof Error?err.message:'Saved status is temporarily unavailable. Keep the private recovery link; do not pay again.';}
 };
 await tick();
};
// The same private GET capability makes refresh/back navigation recoverable;
// this updates browser history only and never submits another paid request.
if(recoveryLink){try{const url=new URL(recoveryLink.href);if(url.origin===location.origin)history.replaceState(null,'',url.pathname+url.search);}catch{}}
form.addEventListener('submit',async e=>{
 e.preventDefault();if(busy||form.dataset.state!=='paid')return;stopPolling();checking=false;busy=true;checkButton.disabled=true;button.disabled=true;workspace.hidden=false;activity.textContent='';result.hidden=true;status.textContent='CONNECTING';const data=new URLSearchParams(new FormData(form));
 try{const response=await fetch('/truth/paid-resume.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:data.toString(),credentials:'same-origin',signal:AbortSignal.timeout(450000)});await parse(response);}
 catch(err){status.textContent='CHECKING SAVED STATUS';log('Live connection stopped',err instanceof Error?err.message:'Unknown error');}
 finally{busy=false;checkButton.disabled=false;button.disabled=true;void beginRecovery();}
});
checkButton.addEventListener('click',()=>void beginRecovery());
document.getElementById('exportPaidFinding').addEventListener('click',()=>{if(lastResult)client.downloadFinding(lastResult)});
document.querySelectorAll('[data-branch]').forEach(btn=>btn.addEventListener('click',()=>{if(lastResult)client.openBranch(lastResult,btn.dataset.branch)}));
window.addEventListener('pagehide',stopPolling);
void beginRecovery();
})();
