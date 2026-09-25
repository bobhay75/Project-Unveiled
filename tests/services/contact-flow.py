#!/usr/bin/env python3
"""Exercise the real PHP intake and inbox using synthetic, temporary data only."""
import hashlib
import http.client
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
from urllib.parse import urlencode

ROOT = Path(__file__).resolve().parents[2]
assert shutil.which('php'), 'PHP is required: this check must not silently skip.'

with tempfile.TemporaryDirectory(prefix='bobsome1-contact-') as temporary:
    tmp = Path(temporary)
    private = tmp / 'private'
    prepend = tmp / 'prepend.php'
    # Only this isolated test server accepts the fixture identity header. The
    # shipped endpoint accepts Apache REMOTE_USER and has no test mode.
    prepend.write_text("<?php\ndefine('TW_INTAKE_PRIVATE_DIR_OVERRIDE', "
                       + repr(str(private)) + ");\n"
                       "if (($_SERVER['HTTP_X_FIXTURE_OWNER'] ?? '') === 'fixture') "
                       "$_SERVER['REMOTE_USER'] = 'fixture-owner';\n")
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    log = (tmp / 'server.log').open('w')
    server = subprocess.Popen(['php', '-d', f'auto_prepend_file={prepend}',
                               '-S', f'127.0.0.1:{port}', '-t', str(ROOT)],
                              stdout=log, stderr=log)
    def request(path='/services/contact-submit.php', method='POST', data=None, headers=None):
        defaults = {'Host': 'bobsome1.com', 'Origin': 'https://bobsome1.com',
                    'Sec-Fetch-Site': 'same-origin', 'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded'}
        defaults.update(headers or {})
        payload = urlencode(data) if isinstance(data, dict) else data
        connection = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
        connection.request(method, path, body=payload, headers=defaults)
        response = connection.getresponse()
        result = response.status, dict(response.getheaders()), response.read().decode()
        connection.close()
        return result
    def brief(**changes):
        data = {'name': 'Test Visitor', 'email': 'visitor@example.invalid',
                'business': 'Synthetic test project', 'problem': 'The inquiry button does not work.',
                'win': 'A working inquiry path.', 'opened_at': str(int(time.time()) - 10),
                'website_url': 'https://example.invalid/', 'fax': ''}
        data.update(changes)
        return data
    try:
        for attempt in range(50):
            try:
                request('/privacy.html', 'GET')
                break
            except (ConnectionError, OSError):
                time.sleep(.1)
        else:
            raise AssertionError('PHP server did not start')
        assert request(method='GET')[0] == 405
        assert request(data=brief(), headers={'Origin': 'https://other.invalid'})[0] == 403
        assert request(data='{}', headers={'Content-Type': 'application/json'})[0] == 415
        assert request(data='problem=' + 'x' * 33000)[0] == 413
        assert request(data=brief(opened_at='0'))[0] == 422
        assert request(data=brief(email='invalid'))[0] == 422
        assert request(data=brief(website_url='javascript:alert(1)'))[0] == 422
        assert request(data=brief(website_url='ftp://example.invalid/file'))[0] == 422
        assert request(data=brief(website_url='https://user:password@example.invalid'))[0] == 422
        data = brief(); data.pop('name'); data['name[]'] = 'unexpected array'
        assert request(data=data)[0] == 422
        status, _, body = request(data=brief(fax='spam'))
        assert status == 200 and json.loads(body)['ok'] is True
        assert not (private / 'leads.json').exists(), 'Honeypot stored a lead'

        status, headers, body = request(data=brief(problem='<script>alert(1)</script> is the supplied problem.'))
        assert status == 200 and json.loads(body) == {'ok': True}
        assert headers['Cache-Control'].startswith('no-store')
        queue = private / 'leads.json'
        rows = json.loads(queue.read_text())
        assert len(rows) == 1 and rows[0]['email'] == 'visitor@example.invalid'
        assert '127.0.0.1' not in queue.read_text()
        assert rows[0]['delete_after_utc'] > rows[0]['submitted_at_utc']
        assert (queue.stat().st_mode & 0o777) == 0o600
        assert (private.stat().st_mode & 0o777) == 0o750
        status, headers, _ = request(data=brief(), headers={'Accept': 'text/html'})
        assert status == 303 and headers['Location'] == '/services/contact-received.html'

        status, _, body = request('/owner/leads.php', 'GET')
        assert status == 403 and 'visitor@example.invalid' not in body
        assert request('/owner/leads.php', 'GET', headers={'Authorization': 'Basic ZmFrZTpmYWtl',
                       'X-Remote-User': 'owner', 'Remote-User': 'owner'})[0] == 403
        auth = {'X-Fixture-Owner': 'fixture'}
        status, headers, body = request('/owner/leads.php', 'GET', headers=auth)
        assert status == 200 and 'visitor@example.invalid' in body
        assert '<script>alert(1)</script>' not in body and '&lt;script&gt;alert(1)&lt;/script&gt;' in body
        assert headers['Cache-Control'].startswith('no-store')
        assert headers['X-Robots-Tag'] == 'noindex, nofollow, noarchive'
        assert request('/owner/leads.php', 'POST', headers=auth)[0] == 405

        for _ in range(3):
            assert request(data=brief())[0] == 200
        before = queue.read_bytes()
        status, headers, body = request(data=brief())
        assert status == 429 and headers['Retry-After'] == '3600'
        assert queue.read_bytes() == before, 'Rate limit changed stored data'

        rows = json.loads(before)
        rows[0]['submitted_at_utc'] = '2020-01-01T00:00:00+00:00'
        rows[0]['delete_after_utc'] = '2020-02-01T00:00:00+00:00'
        rows[0]['business'] = 'EXPIRED FIXTURE'
        queue.write_text(json.dumps(rows))
        assert 'EXPIRED FIXTURE' not in request('/owner/leads.php', 'GET', headers=auth)[2]
        assert request(data=brief())[0] == 200
        assert 'EXPIRED FIXTURE' not in queue.read_text(), 'Expired brief not pruned on accepted intake'

        valid_queue = queue.read_bytes()
        queue.write_text('{broken fixture')
        assert request('/owner/leads.php', 'GET', headers=auth)[0] == 503
        assert request(data=brief())[0] == 500
        assert queue.read_text() == '{broken fixture', 'Corrupt storage overwritten'

        queue.unlink()
        target = tmp / 'external.json'; target.write_bytes(valid_queue)
        queue.symlink_to(target)
        digest = hashlib.sha256(target.read_bytes()).hexdigest()
        assert request(data=brief())[0] == 500
        assert request('/owner/leads.php', 'GET', headers=auth)[0] == 503
        assert hashlib.sha256(target.read_bytes()).hexdigest() == digest
        print('Service contact flow passed: save/redirect, JSON errors, origin/method/body/URL guards, '
              'rate limits, private permissions, owner auth, escaped output, retention, corrupt/symlink storage.')
    finally:
        server.terminate()
        server.wait(timeout=5)
        log.close()
