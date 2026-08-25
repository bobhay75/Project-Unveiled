(()=>{
'use strict';
const API='/book/reader-community.php';
function node(tag,attrs={},text=''){
  const n=document.createElement(tag);
  Object.entries(attrs).forEach(([k,v])=>{if(k==='class')n.className=v;else if(k==='type')n.type=v;else if(k==='name')n.name=v;else if(k==='value')n.value=v;else if(k==='required')n.required=!!v;else if(k==='checked')n.checked=!!v;else if(k==='min'||k==='max'||k==='rows'||k==='maxlength')n.setAttribute(k,String(v));else if(k.startsWith('data-'))n.setAttribute(k,String(v));else n.setAttribute(k,String(v));});
  if(text)n.textContent=text;return n;
}
function slugFromPath(){const m=location.pathname.match(/\/(chapter-[a-z0-9-]+)\.html$/i);return m?m[1].toLowerCase():'chapter';}
function pageTitle(){return (document.querySelector('h1')?.textContent||document.title||'Project Unveiled').trim().slice(0,220);}
function prettyDate(value){try{return new Intl.DateTimeFormat(undefined,{year:'numeric',month:'short',day:'numeric'}).format(new Date(value));}catch(_){return value||'';}}
function stars(rating){return '★'.repeat(Math.max(0,Math.min(5,Number(rating)||0)))+'☆'.repeat(Math.max(0,5-(Number(rating)||0)));}
async function api(url,options={}){const res=await fetch(url,{credentials:'same-origin',...options});let data={};try{data=await res.json();}catch(_){throw new Error('The reader service returned an unreadable response.');}if(!res.ok||!data.ok)throw new Error(data.message||'The reader service could not complete the request.');return data;}
function statusBox(){return node('div',{class:'pu-community-status','aria-live':'polite'});}
function setStatus(box,message,type='ok'){box.textContent=message;box.className='pu-community-status '+type;box.hidden=false;}
function labelWrap(labelText,input){const l=node('label',{class:'pu-community-field'});l.append(node('span',{},labelText),input);return l;}
function hiddenInput(name,value){return node('input',{type:'hidden',name,value});}
function renderEntry(item,kind,onReply,ownerName){
  const article=node('article',{class:'pu-community-entry'+(item.featured?' featured':'')});
  const head=node('div',{class:'pu-community-entry-head'});const who=node('div');who.append(node('strong',{},item.name||'Reader'));
  const badges=node('div',{class:'pu-community-badges'});
  if(item.featured)badges.append(node('span',{class:'pu-community-badge featured'},'Featured'));
  if(item.verified)badges.append(node('span',{class:'pu-community-badge'},'Verified reader'));
  else if(item.reader_completed)badges.append(node('span',{class:'pu-community-badge muted'},'Read the book'));
  head.append(who,badges);article.append(head);
  if(kind==='review'){
    const rating=node('div',{class:'pu-community-rating','aria-label':`${item.rating} out of 5 stars`},stars(item.rating));article.append(rating);
    if(item.title)article.append(node('h4',{},item.title));
  }
  article.append(node('p',{class:'pu-community-body'},item.body||''));
  const foot=node('div',{class:'pu-community-entry-foot'});foot.append(node('time',{},prettyDate(item.created_at_utc)));
  if(kind==='comment'&&!item.parent_id){const reply=node('button',{type:'button',class:'pu-community-text-button'},'Reply');reply.addEventListener('click',()=>onReply(item));foot.append(reply);}
  article.append(foot);
  if(item.owner_reply){const reply=node('div',{class:'pu-community-owner-reply'});reply.append(node('strong',{},`${ownerName||'Robert J. Hayes'} replied`),node('p',{},item.owner_reply));article.append(reply);}
  return article;
}
function renderItems(container,items,kind,onReply,ownerName,limit=0){
  container.textContent='';let parents=items.filter(i=>!i.parent_id);const replies=items.filter(i=>i.parent_id);
  if(limit>0)parents=parents.slice(0,limit);
  if(!parents.length){container.append(node('p',{class:'pu-community-empty'},kind==='review'?'No approved reviews yet. Be the first reader to submit one.':'No approved comments yet. Start the conversation.'));return;}
  parents.forEach(item=>{const wrap=node('div',{class:'pu-community-thread'});wrap.append(renderEntry(item,kind,onReply,ownerName));const child=replies.filter(r=>r.parent_id===item.id);if(child.length){const nest=node('div',{class:'pu-community-replies'});child.forEach(r=>nest.append(renderEntry(r,kind,()=>{},ownerName)));wrap.append(nest);}container.append(wrap);});
}
function makeForm(kind,state,status,refresh){
  const form=node('form',{class:'pu-community-form'});const parentNotice=node('div',{class:'pu-community-replying'});parentNotice.hidden=true;
  const cancelReply=node('button',{type:'button',class:'pu-community-text-button'},'Cancel reply');cancelReply.addEventListener('click',()=>{state.parentId='';parentNotice.hidden=true;form.querySelector('[name="parent_id"]').value='';});parentNotice.append(node('span',{},''),cancelReply);
  const name=node('input',{name:'name',maxlength:100,required:true,autocomplete:'name',placeholder:'Your display name'});form.append(parentNotice,labelWrap('Display name',name));
  if(kind==='review'){
    const rating=node('select',{name:'rating',required:true});rating.append(node('option',{value:''},'Choose a rating'));for(let i=5;i>=1;i--)rating.append(node('option',{value:i},`${i} star${i===1?'':'s'}`));form.append(labelWrap('Rating',rating));
    const title=node('input',{name:'title',maxlength:180,placeholder:'Optional review title'});form.append(labelWrap('Review title',title));
  }
  const body=node('textarea',{name:'body',rows:kind==='review'?7:5,maxlength:5000,required:true,placeholder:kind==='review'?'What did the book make you question, understand, or see differently?':'Add to the discussion with honesty and respect.'});form.append(labelWrap(kind==='review'?'Your review':'Your comment',body));
  if(kind==='review'){
    const completed=node('input',{type:'checkbox',name:'reader_completed',value:'1'});const c=node('label',{class:'pu-community-check'});c.append(completed,node('span',{},'I have read Project Unveiled.'));form.append(c);
  }
  const consent=node('input',{type:'checkbox',name:'consent',value:'1',required:true});const consentLabel=node('label',{class:'pu-community-check'});consentLabel.append(consent,node('span',{},'I understand this may be published after owner moderation.'));form.append(consentLabel);
  const hp=node('label',{class:'pu-community-honeypot','aria-hidden':'true'},'Website');hp.append(node('input',{name:'website',tabindex:'-1',autocomplete:'off'}));form.append(hp);
  form.append(hiddenInput('type',kind),hiddenInput('chapter',state.chapter),hiddenInput('issued',state.issued),hiddenInput('token',state.token),hiddenInput('opened_at',state.openedAt),hiddenInput('page_title',pageTitle()),hiddenInput('source_url',location.href),hiddenInput('parent_id',''));
  const submit=node('button',{type:'submit',class:'pu-community-submit'},kind==='review'?'Submit Review for Approval':'Submit Comment for Approval');form.append(submit);
  form.addEventListener('submit',async e=>{
    e.preventDefault();submit.disabled=true;setStatus(status,'Submitting…','working');
    try{const bodyData=new URLSearchParams(new FormData(form));const data=await api(API,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:bodyData.toString()});setStatus(status,data.message,'ok');form.reset();state.parentId='';parentNotice.hidden=true;await refresh(false);}
    catch(err){setStatus(status,err.message||'Submission failed.','bad');}
    finally{submit.disabled=false;}
  });
  state.beginReply=item=>{state.parentId=item.id;form.querySelector('[name="parent_id"]').value=item.id;parentNotice.querySelector('span').textContent=`Replying to ${item.name}`;parentNotice.hidden=false;if(typeof state.openForm==='function')state.openForm();body.focus();form.scrollIntoView({behavior:'smooth',block:'center'});};
  return form;
}
function normalizedPath(){return location.pathname.replace(/\/index\.html$/,'/');}
function prepareReviewSurfaces(){
  const path=normalizedPath();
  if(path==='/book/reviews.html'||path==='/book/reviews'){
    const formHost=document.querySelector('#reader-review [data-pu-community][data-kind="review"]');
    if(formHost)formHost.dataset.formOnly='true';
    const current=[...document.querySelectorAll('section')].find(section=>(section.querySelector('.eyebrow')?.textContent||'').trim()==='Current Public Record');
    if(current&&!current.querySelector('[data-pu-review-live]')){
      const heading=current.querySelector('.section-heading');
      const title=heading?.querySelector('h2');const copy=heading?.querySelector('p');
      if(title)title.textContent='What readers are saying.';
      if(copy)copy.textContent='Approved reader reviews are published here from the live moderation system. Nothing is padded, invented, or silently promoted.';
      current.querySelector('.empty')?.remove();
      const container=current.querySelector('.container')||current;
      const live=node('div',{'data-pu-review-live':'1'});
      live.append(node('div',{'data-pu-community':'','data-kind':'review','data-display-only':'true'}));
      container.append(live);
    }
  }

  const isBookLanding=path==='/book/';
  const isReaderLanding=path==='/book/read/';
  if(isBookLanding||isReaderLanding){
    const host=document.querySelector('[data-pu-community][data-kind="review"]');
    const main=document.querySelector('main');
    if(host&&main&&!host.closest('main')){
      host.dataset.displayOnly='true';host.dataset.limit='3';
      const section=node('section',{class:'section-dark pu-review-showcase',id:'reader-reviews'});
      const container=node('div',{class:'container'});section.append(container);container.append(host);
      const support=main.querySelector('#support');
      if(support)main.insertBefore(section,support);else main.append(section);
    }
  }
}
async function mount(host){
  const kind=host.dataset.kind==='review'?'review':'comment';const chapter=kind==='review'?'book':(host.dataset.chapter||slugFromPath());
  const displayOnly=host.dataset.displayOnly==='true';const formOnly=host.dataset.formOnly==='true';const limit=Math.max(0,parseInt(host.dataset.limit||'0',10)||0);
  const state={kind,chapter,ownerName:'Robert J. Hayes',issued:0,token:'',openedAt:Math.floor(Date.now()/1000),parentId:'',beginReply:()=>{},openForm:()=>{}};
  host.classList.add('pu-community');host.textContent='';
  const shell=node('section',{class:'pu-community-shell'+(displayOnly?' pu-community-showcase':'')+(formOnly?' pu-community-form-only':'')});const intro=node('div',{class:'pu-community-heading'});
  intro.append(node('div',{class:'pu-community-kicker'},kind==='review'?'READER REVIEWS':'CHAPTER DISCUSSION'),node('h2',{},kind==='review'?'What Readers Are Saying':'Join the Conversation'));
  intro.append(node('p',{},kind==='review'?(displayOnly?'Approved reader reviews, shown from the live moderation record.':'Rate the complete book and share what stood out. Reviews appear only after moderation.'):'Questions, insights, disagreement, and respectful discussion are welcome. Comments appear only after moderation.'));
  const stats=node('div',{class:'pu-community-stats'});const status=statusBox();status.hidden=true;const list=node('div',{class:'pu-community-list'});const formWrap=node('div',{class:'pu-community-form-wrap'});
  if(formOnly)shell.append(status,formWrap);else shell.append(intro,stats,status,list,formWrap);host.append(shell);
  let form=null;
  async function refresh(rebuildForm=true){
    try{
      const data=await api(`${API}?action=list&type=${encodeURIComponent(kind)}&chapter=${encodeURIComponent(chapter)}`);state.issued=data.issued;state.token=data.token;state.ownerName=data.owner_name||'Robert J. Hayes';state.openedAt=Math.floor(Date.now()/1000);
      if(!formOnly){stats.textContent='';if(kind==='review'){stats.append(node('strong',{},data.stats.count?`${data.stats.average_rating} / 5`:'No rating yet'),node('span',{},`${data.stats.count} approved review${data.stats.count===1?'':'s'}`));}else{stats.append(node('strong',{},String(data.stats.count)),node('span',{},`approved comment${data.stats.count===1?'':'s'}`));}}
      formWrap.textContent='';
      if(displayOnly){
        const link=node('a',{class:'pu-community-form-toggle',href:'/book/reviews.html#reader-review'},kind==='review'?'Read All Reviews / Write Yours':'Open the Discussion');formWrap.append(link);
      }else if(data.enabled){
        if(rebuildForm||!form){form=makeForm(kind,state,status,refresh);}
        form.querySelector('[name="issued"]').value=state.issued;form.querySelector('[name="token"]').value=state.token;form.querySelector('[name="opened_at"]').value=state.openedAt;
        const closedLabel=kind==='review'?'Write a Reader Review':'Add Your Question or Comment';
        const openLabel=kind==='review'?'Close Review Form':'Close Comment Form';
        const toggle=node('button',{type:'button',class:'pu-community-form-toggle','aria-expanded':'false'},closedLabel);
        form.hidden=true;
        state.openForm=()=>{form.hidden=false;toggle.setAttribute('aria-expanded','true');toggle.textContent=openLabel;};
        toggle.addEventListener('click',()=>{if(form.hidden){state.openForm();}else{form.hidden=true;toggle.setAttribute('aria-expanded','false');toggle.textContent=closedLabel;}});
        formWrap.append(toggle,form);
      }else{form=null;formWrap.append(node('p',{class:'pu-community-closed'},'Reader submissions are currently closed for this page.'));}
      if(!formOnly)renderItems(list,data.items||[],kind,item=>state.beginReply(item),state.ownerName,limit);
    }catch(err){setStatus(status,err.message||'Reader community could not load.','bad');formWrap.textContent='';}
  }
  await refresh(true);
}
prepareReviewSurfaces();
document.querySelectorAll('[data-pu-community]').forEach(host=>mount(host));
})();
