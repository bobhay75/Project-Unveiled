(function(){
  'use strict';
  const box=document.getElementById('pu-signup');
  if(!box||box.dataset.bound==='1')return;
  box.dataset.bound='1';
  const form=box.querySelector('form');
  const status=box.querySelector('.pu-signup-status');
  const button=form?form.querySelector('button[type="submit"]'):null;
  let started=Date.now();
  if(!form||!status)return;
  form.addEventListener('submit',async function(e){
    e.preventDefault();
    status.textContent='Submitting…';
    status.className='pu-signup-status';
    if(button)button.disabled=true;
    const fd=new FormData(form);
    fd.set('journey','7-day-unveiled');
    fd.set('source_url',location.href);
    fd.set('started_ms',String(started));
    try{
      const r=await fetch('/book/subscribe.php',{method:'POST',body:fd,credentials:'same-origin',headers:{Accept:'application/json'}});
      const text=await r.text();
      let data={};
      try{data=JSON.parse(text);}catch(_){throw new Error('The signup service returned an invalid response.');}
      if(!r.ok||!data.ok)throw new Error(data.message||'Signup could not be completed.');
      status.textContent=data.message||'You are signed up.';
      status.className='pu-signup-status ok';
      form.reset();
      started=Date.now();
    }catch(err){
      status.textContent=(err&&err.message)||'Signup could not be completed.';
      status.className='pu-signup-status bad';
    }finally{
      if(button)button.disabled=false;
    }
  });
})();
