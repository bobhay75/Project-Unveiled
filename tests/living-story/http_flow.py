#!/usr/bin/env python3
"""Isolated HTTP flow tests. No provider requests and no production state."""
import http.cookiejar
import json
import os
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='living-story-http-') as temp:
    private = Path(temp)
    os.chmod(private, 0o700)
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    origin = f'http://127.0.0.1:{port}'
    env = dict(os.environ, LIVING_STORY_PRIVATE_DIR=temp, LIVING_STORY_TEST_ORIGIN=origin)
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(ROOT)], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def call(payload=None, *, headers=None, path='/living-story/api.php', method=None):
        default = {'Origin': origin, 'Content-Type': 'application/json'}
        default.update(headers or {})
        req = urllib.request.Request(origin + path, data=json.dumps(payload).encode() if payload is not None else None, headers=default, method=method)
        try:
            res = opener.open(req, timeout=5)
        except urllib.error.HTTPError as error:
            res = error
        body = res.read()
        return res.status, json.loads(body) if 'application/json' in res.headers.get('Content-Type', '') else body, res.headers
    try:
        for _ in range(60):
            try:
                status, data, _ = call(path='/living-story/api.php?action=status')
                break
            except OSError:
                time.sleep(.05)
        assert status == 200 and data['available'] is False
        assert call({'action':'start'})[0] == 503
        assert call({'action':'login'}, headers={'Origin':'https://example.invalid'})[0] == 403
        assert call({'action':'login'}, headers={'Content-Type':'text/plain'})[0] == 415
        assert call({'action':'login','code':'x'*9000})[0] == 413
        assert call(method='PUT')[0] == 405
        password_hash = subprocess.check_output(['php','-r',"echo password_hash('synthetic-preview-code', PASSWORD_DEFAULT);"]).decode()
        conf = private/'config.json'
        conf.write_text(json.dumps({'enabled':True,'api_key':'synthetic-test-credential-only','access_code_hash':password_hash,'daily_scenes':2}))
        os.chmod(conf,0o600)
        assert call({'action':'login','code':'incorrect'})[0] == 401
        status, auth, headers = call({'action':'login','code':'synthetic-preview-code'})
        assert status == 200 and auth['authorized'] is True
        assert 'HttpOnly' in headers.get('Set-Cookie','') and 'SameSite=Strict' in headers.get('Set-Cookie','')
        assert call({'action':'start'})[0] == 403
        csrf = {'X-CSRF-Token':auth['csrf']}
        assert call({'action':'continue','request_id':'synthetic-identifier-0001','choice':'Inspect','revision':0},headers=csrf)[0] == 409
        assert call({'action':'start','request_id':'synthetic-identifier-0001','path':'../privacy','character':'Reader'},headers=csrf)[0] == 422
        assert call(path='/living-story/api.php?action=status')[1]['authorized'] is True
        assert call({'action':'logout'},headers=csrf)[0] == 200
        assert call(path='/living-story/api.php?action=status')[1]['authorized'] is False
        assert call(path='/living-story/lib.php')[0] == 404
        for path in ['/','/book/read/','/book/read/chapter-01.html','/book/timeline.html','/book/research.html','/unveiled/','/privacy.html','/living-story/','/living-story/demo.json']:
            assert call(path=path)[0] == 200, path
        assert not (ROOT/'living-story/config.json').exists()
        print('Living Story HTTP tests passed: disabled mode, origins, CSRF, authentication, request limits, logout, direct library denial, and public route smoke checks.')
    finally:
        server.terminate()
        server.wait(timeout=5)
