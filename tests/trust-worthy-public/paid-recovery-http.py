#!/usr/bin/env python3
"""Recover real paid PHP routes from synthetic records without external providers."""
import base64
import contextlib
import hashlib
import hmac
from html import unescape
import json
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT=Path(__file__).resolve().parents[2]
PHP=shutil.which('php')
SECRET='a'*64
CASE='b'*32
OTHER='c'*32
ORDER='ORDER123'
CAPTURE='CAPTURE123'
CHECKS=0


def check(value,label):
    global CHECKS
    CHECKS+=1
    if not value: raise AssertionError(label)


def sign(payload,prefix):
    raw=json.dumps(payload,separators=(',',':')).encode()
    body=base64.urlsafe_b64encode(raw).decode().rstrip('=')
    return body+'.'+hmac.new(SECRET.encode(),(prefix+body).encode(),hashlib.sha256).hexdigest()


def state(purpose,expires):
    return sign({'v':1,'case_id':CASE,'purpose':purpose,'exp':expires},'paid-state|')


def receipt(expires,**changes):
    fields={'v':1,'case_id':CASE,'order_id':ORDER,'capture_id':CAPTURE,'exp':expires}
    fields.update(changes)
    return sign(fields,'paid-recovery|')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None


@contextlib.contextmanager
def server(enabled=True):
    with tempfile.TemporaryDirectory(prefix='tw-paid-recovery-') as name:
        directory=Path(name);private=directory/'private';private.mkdir(mode=0o750)
        secret=private/'question-secret.txt';secret.write_text(SECRET+'\n');secret.chmod(0o600)
        router=directory/'router.php'
        router.write_text("<?php define('TW_PRIVATE_DIR_OVERRIDE',"+json.dumps(str(private))+");$root="+json.dumps(str(ROOT))+";$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);if(!is_string($path)||!preg_match('#^/truth/[a-z-]+\\.php$#D',$path)){http_response_code(404);exit;}require $root.$path;")
        with socket.socket() as listener:listener.bind(('127.0.0.1',0));port=listener.getsockname()[1]
        env=dict(os.environ)
        for key in list(env):
            if key.startswith(('TW_','OPENAI_','PROSPER_')):env.pop(key)
        env['TW_GUIDED_RESEARCH_ENABLED']='1' if enabled else '0';env['TW_PAYPAL_ENABLED']='0'
        with (directory/'server.log').open('wb') as log:
            process=subprocess.Popen([PHP,'-d','disable_functions=curl_init,curl_exec,fsockopen,pfsockopen,stream_socket_client','-S',f'127.0.0.1:{port}',str(router)],cwd=ROOT,env=env,stdout=log,stderr=log)
            try:
                for _ in range(80):
                    if process.poll() is not None:raise RuntimeError('PHP server stopped')
                    try:
                        with socket.create_connection(('127.0.0.1',port),timeout=.2):break
                    except OSError:time.sleep(.05)
                else:raise RuntimeError('PHP server did not start')
                yield f'http://127.0.0.1:{port}',private
            finally:
                process.terminate()
                try:process.wait(timeout=5)
                except subprocess.TimeoutExpired:process.kill();process.wait(timeout=5)


def request(base,path,fields=None,origin='https://bobsome1.com',site='same-origin',method=None):
    headers={'Host':'bobsome1.com','Sec-Fetch-Site':site}
    if origin is not None:headers['Origin']=origin
    body=None
    if fields is not None:body=urllib.parse.urlencode(fields).encode();headers['Content-Type']='application/x-www-form-urlencoded'
    req=urllib.request.Request(base+path,data=body,headers=headers,method=method)
    try:response=urllib.request.build_opener(NoRedirect).open(req,timeout=10)
    except urllib.error.HTTPError as error:response=error
    with response:return response.status,response.read().decode(),dict(response.headers)


def snapshot(directory):
    return sorted((path.name,path.read_bytes(),path.stat().st_mode&0o777) for path in directory.iterdir() if path.is_file())


if not PHP:raise SystemExit('PHP is required')
with server(False) as (base,private):
    before=snapshot(private)
    for route in ['paid-status','paid-continue','paypal-return']:
        status,_,headers=request(base,f'/truth/{route}.php',{'case_id':CASE})
        check(status==404 and 'no-store' in headers.get('Cache-Control',''),'Disabled recovery route exposed')
    check(snapshot(private)==before,'Disabled recovery touched private state')

with server() as (base,private):
    now=int(time.time());expiry=now+3600;path=private/f'paid-case-{CASE}.json'
    finding={'ok':True,'investigation_id':'TW-P-SYNTHETIC','claim':'Synthetic <script>claim</script> for recovery','verdict':'MIXED','probability':47,'analysis':'Synthetic saved finding.','sources':[{'url':'https://example.com/evidence','title':'Synthetic source'}],'metrics':{'evidence_floor_met':True,'unique_sources':1,'source_families':1}}
    record={'v':1,'case_id':CASE,'state':'paid','amount':'2.99','currency':'USD','created_at':now-60,'expires_at':expiry,'paypal_order_id':ORDER,'capture_id':CAPTURE,'paid_at':now-30,'resume_consumed_at':None,'payload':{'question':finding['claim'],'context':'PRIVATE SYNTHETIC SOURCE CONTEXT'}}
    def save():path.write_text(json.dumps(record));path.chmod(0o600)
    save();token=receipt(expiry);fields={'case_id':CASE,'recovery_token':token};before=snapshot(private)
    for checkout in [state('checkout',expiry),state('checkout',now-60)]:
        query=urllib.parse.urlencode({'state':checkout,'token':ORDER})
        status,body,headers=request(base,'/truth/paypal-return.php?'+query,origin=None,site='cross-site')
        check(status==200 and 'paidRecoveryHandoff' in body,'Lost cross-site PayPal handoff did not recover without configured PayPal')
        handoff={key:unescape(value) for key,value in re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)">',body)}
        check(set(handoff)=={'case_id','recovery_token'} and handoff['case_id']==CASE,'Recovered handoff did not use the read-only capability')
        status,body,page_headers=request(base,'/truth/paid-continue.php',handoff)
        match=re.search(r'id="paidRecoveryLink" href="([^"]+)"',body)
        check(bool(match),'Recovery workspace did not supply a private refresh link')
        recovered_url=unescape(match[1])
        check(status==200 and 'paidRecoveryLink' in body and 'PRIVATE SYNTHETIC SOURCE CONTEXT' not in body,'Private recovery workspace unavailable')
        check('no-store' in page_headers.get('Cache-Control','') and page_headers.get('Referrer-Policy')=='no-referrer','Recovery page is cacheable or leaks referrers')
        check('Synthetic &lt;script&gt;claim&lt;/script&gt;' in body,'Recovered claim was not escaped')
    check(snapshot(private)==before,'Read-only handoff recovery changed state, permissions or files')
    wrong=urllib.parse.urlencode({'state':state('checkout',expiry),'token':'WRONG123'})
    check(request(base,'/truth/paypal-return.php?'+wrong)[0]>=400,'Wrong PayPal order recovered another case')
    check(request(base,'/truth/paid-continue.php?'+urllib.parse.urlencode(fields),origin='https://evil.example',site='cross-site')[0]==403,'Cross-site private navigation accepted')
    check(request(base,'/truth/paid-continue.php?'+urllib.parse.urlencode(fields),origin=None,site='none')[0]==200,'Direct private bookmark cannot recover')
    check(request(base,'/truth/paid-status.php',method='GET')[0]==405,'Status allowed GET')
    for origin in ['https://evil.example','https://bobsome1.com:444','https://x@bobsome1.com','https://bobsome1.com/path']:
        rejected=request(base,'/truth/paid-status.php',fields,origin)
        check(rejected[0]==403,f'Status origin rejection failed: {origin} {rejected[:2]}')
    check(request(base,'/truth/paid-status.php',{'case_id':CASE,'recovery_token':'x'*13000})[0]==413,'Status accepted oversized receipt')
    invalid=[{'case_id':CASE,'recovery_token':token+'x'},{'case_id':OTHER,'recovery_token':token},{'case_id':CASE,'recovery_token':receipt(expiry,order_id='WRONG123')},{'case_id':CASE,'recovery_token':receipt(expiry,capture_id='WRONGCAPTURE')},{'case_id':CASE,'recovery_token':receipt(now-1)}]
    for bad in invalid:check(request(base,'/truth/paid-status.php',bad)[0]==403,'Invalid, expired, wrong-case/order/capture receipt accepted')
    for current in ['paid','resume_authorized','finishing','completed','needs_review']:
        record['state']=current;record['resume_consumed_at']=None if current=='paid' else now-10
        if current=='completed':record['result']=finding
        else:record.pop('result',None)
        save();before=snapshot(private)
        for _ in range(2):
            status,body,headers=request(base,'/truth/paid-status.php',fields);data=json.loads(body)
            check(status==200 and data['state']==current and data['case_id']==CASE and data['order_id']==ORDER,'Saved paid state could not be recovered')
            check('PRIVATE SYNTHETIC SOURCE CONTEXT' not in body and 'capture_id' not in data and 'payload' not in data,'Status leaked unneeded source/payment state')
            check(headers.get('Cache-Control','').startswith('private, no-store'),'Private status cache policy missing')
            if current=='completed':check(data.get('result')==finding,'Saved completed result lost after simulated stream interruption')
            if current=='paid':check(bool(data.get('resume_token')),'Paid recovery did not restore unused authorization')
        status,body,_=request(base,recovered_url)
        check(status==200,'Refresh of consumed/pending/completed investigation was rejected')
        if current!='paid':check(re.search(r'id="paidResumeButton"\s+disabled',body),'Consumed or finished case offered a new run')
        if current=='completed':
            check(request(base,'/truth/paid-resume.php',dict(fields,resume_token=state('resume',expiry)))[0]==403,'Completed case could run again')
        check(snapshot(private)==before,'Recovery modified saved payment/research state or secret storage')
    record['expires_at']=now-1;save()
    check(request(base,'/truth/paid-status.php',fields)[0]==403,'Expired case remained recoverable')
    check(request(base,'/truth/paypal-return.php?'+urllib.parse.urlencode({'state':state('checkout',expiry),'token':ORDER}))[0]>=400,'Expired case obtained renewed recovery')
print(f'Paid recovery HTTP checks passed: {CHECKS}')
