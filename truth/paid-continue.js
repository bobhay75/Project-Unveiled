(()=>{
'use strict';
const form=document.getElementById('paidResumeForm');
if(!form)return;
const workspace=document.getElementById('paidWorkspace');
const activity=document.getElementById('paidActivity');
const status=document.getElementById('paidStatus');
const button=document.getElementById('paidResumeButton');
const result=document.getElementById('paidResult');
const verdict=document.getElementById('paidVerdict');
const probability=document.getElementById('paidProbability');
const note=document.getElementById('paidNote');
const analysis=document.getElementById('paidAnalysis');
const sources=document.getElementById('paidSources');

const log=(title,detail='')=>{const row=document.createElement('div');row.className='event';const b=document.createElement('b');b.textContent=title;row.appendChild(b);if(detail){const s=document.createElement('span');s.textContent=detail;row.appendChild(s)}activity.prepend(row)};
const stage=(name,state)=>{const el=document.querySelector(`[data-stage="${name}"]`);if(!el)return;el.classList.remove('active','done');if(state)el.classList.add(state)};
const safeUrl=value=>{try{const u=new URL(value,location.origin);return /^https?:$/.test(u.protocol)?u.href:''}catch{return''}};
const consume=r=>{if(!r||typeof r!=='object')return;document.querySelectorAll('#stageList li.active').forEach(el=>el.classList.remove('active'));stage(r.stage,'done');const order=['dependency','counter','context','synthesis'];const i=order.indexOf(r.stage);if(i>=0&&i<order.length-1)stage(order[i+1],'active');let detail='';if(Number.isFinite(r.source_count))detail=`${r.source_count} evidence URL${r.source_count===1?'':'s'} attached`;if(r.research_mode==='reuse_no_web')detail='Analyzed the evidence already collected; no new web search used for this stage';if(r.stage==='synthesis'&&r.usage&&Number.isFinite(r.usage.total_tokens))detail=`Final synthesis complete · ${r.usage.total_tokens} total provider tokens across the investigation`;log(r.label||r.stage||'Stage complete',detail)};
const render=out=>{result.hidden=false;status.textContent='COMPLETE';verdict.textContent=out.verdict||'UNRESOLVED';probability.textContent=Number.isFinite(out.probability)?`${out.probability}%`:'NO SCORE';const m=out.metrics||{},u=m.usage||{};note.textContent=Number.isFinite(out.probability)?`Evidence-conditioned experimental probability. ${m.unique_sources||0} unique URLs across ${m.source_families||0} source families. ${Number.isFinite(u.total_tokens)?u.total_tokens+' total provider tokens used.':''}`:'No probability was issued because the evidence floor was not met.';analysis.textContent=out.analysis||'';sources.textContent='';(Array.isArray(out.sources)?out.sources:[]).forEach(src=>{const href=safeUrl(src.url||'');if(!href)return;const a=document.createElement('a');a.href=href;a.target='_blank';a.rel='noopener noreferrer';a.textContent=(src.title||href)+(src.family?` · ${src.family}`:'');sources.appendChild(a)});result.scrollIntoView({behavior:'smooth',block:'start'})};
const parse=async response=>{if(!response.ok){let text='Paid continuation failed.';try{text=await response.text()}catch{}throw new Error(text||`Request failed (${response.status})`)}if(!response.body)throw new Error('Streaming continuation is unavailable.');const reader=response.body.getReader(),decoder=new TextDecoder();let buffer='';for(;;){const {value,done}=await reader.read();buffer+=decoder.decode(value||new Uint8Array(),{stream:!done});const lines=buffer.split('\n');buffer=lines.pop()||'';for(const line of lines){if(!line.trim())continue;let ev;try{ev=JSON.parse(line)}catch{continue}if(ev.type==='start'){status.textContent='INVESTIGATING';stage('dependency','active');log(ev.label||'Paid continuation started')}else if(ev.type==='receipt')consume(ev.receipt);else if(ev.type==='complete')render(ev.result||{});else if(ev.type==='error')throw new Error(ev.message||'Paid continuation failed.')}if(done)break}};

form.addEventListener('submit',async e=>{e.preventDefault();button.disabled=true;workspace.hidden=false;activity.textContent='';result.hidden=true;status.textContent='CONNECTING';const data=new URLSearchParams(new FormData(form));try{const response=await fetch('/truth/paid-resume.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:data.toString(),credentials:'same-origin'});await parse(response)}catch(err){status.textContent='STOPPED';log('Continuation stopped',err instanceof Error?err.message:'Unknown error')}finally{button.disabled=true}});
})();
