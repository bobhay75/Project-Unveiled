#!/usr/bin/env python3
"""Exercise real PHP routes with synthetic inputs and no provider credentials."""
import contextlib
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which("php")
CHECKS = 0


def check(ok, label):
    global CHECKS
    CHECKS += 1
    if not ok:
        raise AssertionError(label)


@contextlib.contextmanager
def server(enabled):
    with tempfile.TemporaryDirectory(prefix="tw-guided-http-") as directory:
        directory = Path(directory)
        private = directory / "private"
        router = directory / "router.php"
        router.write_text("<?php\ndeclare(strict_types=1);\n"
            + "define('TW_PRIVATE_DIR_OVERRIDE'," + json.dumps(str(private)) + ");\n"
            + "$root=" + json.dumps(str(ROOT)) + ";\n"
            + "$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);\n"
            + "if($path==='/truth/')$path='/truth/index.php';\n"
            + "if(!is_string($path)||!preg_match('#^/truth/[a-z-]+\\.php$#D',$path)){http_response_code(404);exit;}\n"
            + "require $root.$path;\n")
        with socket.socket() as listener:
            listener.bind(("127.0.0.1", 0))
            port = listener.getsockname()[1]
        env = dict(os.environ)
        for name in list(env):
            if name.startswith(("TW_", "OPENAI_", "PROSPER_")):
                env.pop(name)
        env["TW_GUIDED_RESEARCH_ENABLED"] = "1" if enabled else "0"
        with (directory / "server.log").open("wb") as log:
            process = subprocess.Popen([PHP, "-S", f"127.0.0.1:{port}", str(router)], cwd=ROOT,
                                       env=env, stdout=log, stderr=log)
            try:
                for _ in range(60):
                    if process.poll() is not None:
                        raise RuntimeError("PHP test server exited")
                    try:
                        with socket.create_connection(("127.0.0.1", port), timeout=.2):
                            break
                    except OSError:
                        time.sleep(.05)
                else:
                    raise RuntimeError("PHP test server did not start")
                yield f"http://127.0.0.1:{port}", private
            finally:
                process.terminate()
                try:
                    process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait(timeout=5)


def request(base, path, fields=None, origin="https://bobsome1.com", method=None):
    headers = {"Host":"bobsome1.com", "Origin":origin, "Sec-Fetch-Site":"same-origin"}
    data = None
    if fields is not None:
        data = urllib.parse.urlencode(fields).encode()
        headers["Content-Type"] = "application/x-www-form-urlencoded"
    req = urllib.request.Request(base + path, data=data, headers=headers, method=method)
    try:
        response = urllib.request.urlopen(req, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read().decode(), dict(response.headers)


if not PHP:
    raise SystemExit("PHP is required")
routes = ["guided", "claim-map", "deep-stream", "investigation", "paypal-start",
          "paypal-return", "paid-continue", "paid-resume", "paid-status"]
with server(False) as (base, private):
    status, body, _ = request(base, "/truth/")
    check(status == 200 and "START GUIDED DEEP INVESTIGATION" not in body,
          "Default homepage changed into the unreleased guided engine")
    for route in routes:
        status, _, headers = request(base, f"/truth/{route}.php", {"question":"Synthetic test claim"})
        check(status == 404 and "no-store" in headers.get("Cache-Control", ""), "Disabled route exposed")
    check(not private.exists(), "Disabled routes touched private state")

with server(True) as (base, private):
    status, body, _ = request(base, "/truth/")
    check(status == 200 and 'formaction="/truth/investigation.php"' in body,
          "Guided entry did not target its actual workspace")
    fields = {"question":"Synthetic <script>alert(1)</script> question for local validation",
              "context":"Synthetic test only", "opened_at":str(int(time.time())-6), "website":""}
    status, body, headers = request(base, "/truth/investigation.php", fields)
    check(status == 200 and "Choose what matters" in body and "&lt;script&gt;" in body,
          "Valid workspace did not render escaped input")
    check("no-store" in headers.get("Cache-Control", ""), "Workspace is cacheable")
    for route in ["investigation", "paypal-start", "paid-continue", "paid-resume"]:
        for origin in ["https://evil.example", "https://bobsome1.com:444", "https://x@bobsome1.com", "https://bobsome1.com/path"]:
            status, _, _ = request(base, f"/truth/{route}.php", fields, origin)
            check(status == 403, "Unsafe real HTTP origin accepted")
        status, _, _ = request(base, f"/truth/{route}.php", {"question":"x"*31000})
        check(status == 413, "Oversized real HTTP body accepted")
    status, body, _ = request(base, "/truth/claim-map.php", fields)
    check(status == 503 and "configuration is unavailable" in body,
          "Missing provider configuration did not stop before an external request")
print(f"Guided PHP HTTP checks passed: {CHECKS}")
