(function(){
 const scenario=window.__paidScenario,requests=[];
 let state=scenario==='completed'?'completed':scenario==='pending'?'finishing':'paid',attempts=0;
 const caseId=document.querySelector('[name="case_id"]').value;
 const finding={ok:true,investigation_id:'TW-P-SYNTHETIC-RECOVERY',claim:'Synthetic original paid claim',verdict:'MIXED',probability:47,analysis:'Saved synthetic finding <img src=x onerror=alert(1)> remains text.',sources:[{url:'https://example.com/evidence',title:'Synthetic evidence'}],metrics:{evidence_floor_met:true,unique_sources:1,source_families:1}};
 let exported=null;const originalBlob=URL.createObjectURL,originalClick=HTMLAnchorElement.prototype.click;
 URL.createObjectURL=function(blob){exported=blob;return originalBlob.call(URL,blob)};
 HTMLAnchorElement.prototype.click=function(){if(this.download)return;return originalClick.call(this)};
 window.fetch=async function(url,options){
  requests.push({url,fields:Object.fromEntries(new URLSearchParams(options?.body))});
  if(url==='/truth/paid-status.php')return Response.json({ok:true,case_id:caseId,order_id:'ORDER123',state,message:'Synthetic saved status',resume_token:state==='paid'?'synthetic-unused-resume':undefined,result:state==='completed'?finding:undefined});
  if(url!=='/truth/paid-resume.php')throw Error('Unexpected external request in synthetic paid UI test');
  attempts++;
  if(scenario==='retry'&&attempts===1)return Response.json({ok:false,message:'Synthetic request rejected before consumption'},{status:503});
  state='completed';
  return new Response(JSON.stringify({type:'start'})+'\n'+JSON.stringify({type:'receipt',receipt:{stage:'dependency',completed:true,label:'Synthetic dependency receipt'}})+'\n',{headers:{'Content-Type':'application/x-ndjson'}});
 };
 window.__runPaidBehavior=async()=>{
  let checks=0;const check=(value,label)=>{checks++;if(!value)throw Error(label)};
  const waitFor=async predicate=>{for(let i=0;i<100;i++){if(predicate())return;await new Promise(resolve=>setTimeout(resolve,5));}throw Error('Synthetic paid UI did not settle')};
  const result=document.getElementById('paidResult'),button=document.getElementById('paidResumeButton'),status=document.getElementById('paidStatus');
  try{
   if(scenario==='pending'){
    await new Promise(resolve=>setTimeout(resolve,121000));
    check(requests.length>=2&&requests.length<=24,'Saved-status polling is bounded');
    check(requests.every(request=>request.url==='/truth/paid-status.php'),'Pending recovery never starts another provider run');
    check(button.disabled&&result.hidden&&status.textContent!=='COMPLETE','Pending case never fabricates completion');
    check(!document.getElementById('paidCheckStatus').disabled&&document.getElementById('paidRecoveryMessage').textContent.includes('still pending'),'Finite polling returns manual status control');
   }else{
    await waitFor(()=>status.textContent===(scenario==='completed'?'COMPLETE':'READY'));
    if(scenario!=='completed'){
     button.click();button.click();
     if(scenario==='retry'){
      await waitFor(()=>status.textContent==='READY'&&!button.disabled);
      check(attempts===1,'Rejected request is not automatically resubmitted');button.click();
     }
     await waitFor(()=>status.textContent==='COMPLETE');
     check(attempts===(scenario==='retry'?2:1),'Duplicate clicks or lost stream did not create another run');
    }else check(attempts===0,'Reloaded completion did not create a provider run');
    check(!result.hidden&&document.getElementById('paidProbability').textContent==='47%','Saved finding recovered into visible controls');
    check(document.getElementById('paidAnalysis').textContent===finding.analysis&&!document.getElementById('paidAnalysis').querySelector('img'),'Recovered analysis is text, not executable markup');
    check(button.disabled,'Completed case cannot be submitted again');
    check(requests.filter(request=>request.url==='/truth/paid-status.php').every(request=>request.fields.recovery_token&&!request.fields.resume_token),'Read-only polling sends only the recovery capability');
    document.getElementById('exportPaidFinding').click();const download=JSON.parse(await exported.text());
    check(download.result.investigation_id===finding.investigation_id&&!JSON.stringify(download).includes('recovery_token'),'Recovered finding can be exported without credentials');
   }
   return {passed:true,checks,requests:requests.length,providerSubmissions:attempts};
  }catch(error){return {passed:false,checks,error:String(error),requests:requests.length,providerSubmissions:attempts};}
 };
})();
