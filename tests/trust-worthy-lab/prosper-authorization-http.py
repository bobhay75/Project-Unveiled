#!/usr/bin/env python3
"""Real loopback HTTP checks; no external services, real secrets or AI calls."""
import argparse
import importlib.util
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import tempfile
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')
assert PHP, 'PHP with cURL is required; this check must not silently skip.'
subprocess.run([PHP, '-r', 'exit(function_exists("curl_init") ? 0 : 1);'], check=True)
GUARD = ROOT / 'truth/lib/prosper-authorization.php'
KEY = secrets.token_urlsafe(32)
CHECKS = 0


def check(condition, label):
    global CHECKS
    assert condition, label
    CHECKS += 1


def authorize(port, key=KEY, extra_env=None):
    env = os.environ.copy()
    env.update(TW_PROSPER_GATEWAY_URL=f'http://127.0.0.1:{port}',
               TW_PROSPER_AGENT_KEY=key, TEST_PROSPER_GUARD=str(GUARD))
    env.update(extra_env or {})
    result = subprocess.run(
        [PHP, '-r', 'require getenv("TEST_PROSPER_GUARD"); echo tw_prosper_authorized() ? "allow" : "deny";'],
        env=env, text=True, capture_output=True, timeout=5, check=True)
    assert result.stdout in ('allow', 'deny'), 'Guard emitted unexpected output'
    return result.stdout == 'allow'


class Stub(BaseHTTPRequestHandler):
    scenario = 'allow'
    requests = []

    def log_message(self, *_args):
        pass

    def do_POST(self):
        size = int(self.headers.get('Content-Length', '0'))
        payload = self.rfile.read(size)
        type(self).requests.append((self.path, json.loads(payload), self.headers.get('X-Agent-Key')))
        scenario = type(self).scenario
        status = 200
        headers = {}
        if scenario == 'allow':
            body = b'{"allowed":true}'
        elif scenario == 'deny':
            body = b'{"allowed":false}'
        elif scenario == 'wrong-type':
            body = b'{"allowed":"true"}'
        elif scenario == 'malformed':
            body = b'{bad json'
        elif scenario == 'unavailable':
            status, body = 503, b'{"allowed":true}'
        elif scenario == 'redirect':
            status, body = 302, b'{"allowed":true}'
            headers['Location'] = f'http://127.0.0.1:{self.server.server_port}/redirected'
        elif scenario == 'oversized':
            body = b'{"allowed":true,"padding":"' + b'x' * 131072 + b'"}'
        elif scenario == 'timeout':
            time.sleep(2.8)
            body = b'{"allowed":true}'
        else:
            raise AssertionError(scenario)
        try:
            self.send_response(status)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', str(len(body)))
            for name, value in headers.items():
                self.send_header(name, value)
            self.end_headers()
            self.wfile.write(body)
        except (BrokenPipeError, ConnectionResetError):
            pass


class Proxy(BaseHTTPRequestHandler):
    requests = 0

    def log_message(self, *_args):
        pass

    def do_POST(self):
        type(self).requests += 1
        self.send_error(502)


def start(handler):
    server = ThreadingHTTPServer(('127.0.0.1', 0), handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    return server


def boundary_checks():
    server, proxy = start(Stub), start(Proxy)
    try:
        for scenario, expected in [('allow', True), ('deny', False), ('wrong-type', False),
                                   ('malformed', False), ('unavailable', False),
                                   ('redirect', False), ('oversized', False)]:
            Stub.scenario = scenario
            check(authorize(server.server_port) is expected, f'{scenario} result')
        check(len(Stub.requests) == 7, 'Redirect was not followed')
        check(all(path == '/authorize' and body == {'agent_id': 'trust-worthy', 'action': 'investigation.run'}
                  and key == KEY for path, body, key in Stub.requests), 'Only fixed action metadata sent')
        Stub.scenario = 'allow'
        proxy_url = f'http://127.0.0.1:{proxy.server_port}'
        check(authorize(server.server_port, extra_env={'http_proxy': proxy_url, 'HTTP_PROXY': proxy_url,
              'ALL_PROXY': proxy_url, 'all_proxy': proxy_url, 'NO_PROXY': '', 'no_proxy': ''}),
              'Host proxy settings cannot intercept loopback authorization')
        check(Proxy.requests == 0, 'Agent credential never reached proxy')
        Stub.scenario = 'timeout'
        before = time.monotonic()
        check(not authorize(server.server_port), 'Stalled gateway denied')
        check(time.monotonic() - before < 3.5, 'Stalled gateway bounded to two seconds')
    finally:
        server.shutdown()
        proxy.shutdown()
        server.server_close()
        proxy.server_close()


def gateway_checks(source):
    spec = importlib.util.spec_from_file_location('prosper_contract_gateway', source)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    with tempfile.TemporaryDirectory(prefix='trust-worthy-prosper-') as directory:
        gateway = module.Gateway(str(Path(directory) / 'gateway.sqlite3'))
        status, created = gateway.create_agent('trust-worthy')
        check(status == 201, 'Real gateway provisioned synthetic identity')

        class RealHandler(module.Handler):
            def log_message(self, *_args):
                pass

        RealHandler.gateway = gateway
        RealHandler.admin_key = secrets.token_urlsafe(32)
        server = start(RealHandler)
        try:
            key = created['agent_key']
            check(not authorize(server.server_port, key), 'Real gateway denies absent permission')
            gateway.grant('trust-worthy', 'investigation.run')
            check(authorize(server.server_port, key), 'Real gateway allows granted action')
            check(not authorize(server.server_port, secrets.token_urlsafe(32)), 'Wrong agent key rejected')
            gateway.revoke('trust-worthy', 'investigation.run')
            check(not authorize(server.server_port, key), 'Permission revocation takes effect')
            gateway.grant('trust-worthy', 'investigation.run')
            _, rotated = gateway.rotate_key('trust-worthy')
            check(not authorize(server.server_port, key), 'Old credential rejected after rotation')
            check(authorize(server.server_port, rotated['agent_key']), 'Rotated credential accepted')
            gateway.delete_agent('trust-worthy')
            check(not authorize(server.server_port, rotated['agent_key']), 'Deleted identity denied')
            audit = gateway.audit()
            check(audit['chain_valid'] and len(audit['entries']) == 4, 'Authorization audit chain preserved')
            check(all(row['agent_id'] == 'trust-worthy' and row['action'] == 'investigation.run'
                      for row in audit['entries']), 'Audit contains only synthetic identity/action')
        finally:
            server.shutdown()
            server.server_close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--prosper-source', type=Path, help='Optional exact recovered gateway.py for a two-service check')
    args = parser.parse_args()
    boundary_checks()
    if args.prosper_source:
        gateway_checks(args.prosper_source.resolve())
    print(f'Prosper authorization HTTP: {CHECKS} checks passed (local synthetic data only).')
