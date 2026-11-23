#!/usr/bin/env python3
"""Exercise real checkout routes with synthetic private data and no network provider.

Only a temporary copy's HTTP transport is replaced. Production has no test flag,
mock URL, or authentication bypass. curl_exec is disabled in the test process.
"""
import contextlib
import hashlib
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
import zipfile

ROOT = Path(__file__).resolve().parents[2]
PHP = os.environ.get("PHP_BIN") or shutil.which("php")
CHECKS = 0


def check(condition, label):
    global CHECKS
    CHECKS += 1
    if not condition:
        raise AssertionError(label)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def bundle(path):
    files = {
        "illustrated-ebook/edition.pdf": b"%PDF-1.4\n" + b"Synthetic test ebook. " * 12,
        "deep-study-guide/guide.pdf": b"%PDF-1.4\n" + b"Synthetic guide. " * 12,
        "no-more-milk/study.pdf": b"%PDF-1.4\n" + b"Synthetic study. " * 12,
        "timeline/index.html": b"<!doctype html><html lang='en'><title>Synthetic timeline</title><p>" + b"Test event. " * 12 + b"</p></html>",
    }
    components = []
    for name, content in files.items():
        components.append({"id": name.split("/")[0], "entrypoint": name, "files": [
            {"path": name, "bytes": len(content), "sha256": hashlib.sha256(content).hexdigest()}
        ]})
    manifest = {"schema": 1, "product": "project-unveiled-digital-edition",
                "release_id": "synthetic-recovery-test", "components": components}
    with zipfile.ZipFile(path, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        archive.writestr("bundle-manifest.json", json.dumps(manifest))
        for name, content in files.items():
            archive.writestr(name, content)
    path.chmod(0o600)
    return hashlib.sha256(path.read_bytes()).hexdigest()


@contextlib.contextmanager
def server():
    with tempfile.TemporaryDirectory(prefix="pu-bundle-recovery-") as name:
        root = Path(name)
        public = root / "public"
        private = root / "private"
        private.mkdir(mode=0o700)
        checkout = public / "store" / "checkout"
        checkout.parent.mkdir(parents=True)
        shutil.copytree(ROOT / "store" / "checkout", checkout)
        source = (checkout / "lib.php").read_text()
        check(source.count("function bc_http(") == 1, "Production transport seam is unambiguous")
        source = source.replace("function bc_http(", "function bc_disabled_http(", 1)
        source += r'''
function bc_http(string $url, array $options): array {
    $private = (string)getenv('BC_TEST_PRIVATE');
    $path = parse_url($url, PHP_URL_PATH);
    $method = $options[CURLOPT_CUSTOMREQUEST] ?? (($options[CURLOPT_POST] ?? false) ? 'POST' : 'GET');
    file_put_contents($private.'/provider-calls.jsonl', json_encode([$method,$path])."\n", FILE_APPEND);
    if ($path === '/v1/oauth2/token' && $method === 'POST') return ['access_token'=>'SYNTHETIC_TOKEN'];
    if ($method !== 'GET' || !in_array($path, ['/v2/checkout/orders/ORDERRECOVERY123', '/v2/payments/captures/CAPTURETEST12345'], true)) throw new RuntimeException('Unexpected provider operation');
    $response = json_decode(file_get_contents($private.'/response.json'), true, 32, JSON_THROW_ON_ERROR);
    if (isset($response['test_failure'])) throw new BcException('Synthetic provider unavailable');
    if (str_starts_with($path, '/v2/payments/captures/')) {
        $capture = $response['purchase_units'][0]['payments']['captures'][0];
        if (!empty($response['test_capture_refund'])) $capture['status'] = 'REFUNDED';
        return $capture;
    }
    return $response;
}
'''
        (checkout / "lib.php").write_text(source)
        digest = bundle(private / "bundle.zip")
        config = {
            "enabled": True, "mode": "sandbox", "client_id": "SYNTHETIC_CLIENT_ID",
            "client_secret": "SYNTHETIC_CLIENT_SECRET", "merchant_id": "MERCHANTTEST1",
            "private_dir": str(private), "asset_path": str(private / "bundle.zip"),
            "asset_sha256": digest, "owner_approved_sha256": digest,
            "owner_approved_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        }
        config_path = private / "config.php"
        config_path.write_text("<?php return json_decode(" + json.dumps(json.dumps(config)) + ", true);")
        config_path.chmod(0o600)
        router = root / "router.php"
        router.write_text("<?php\n$_SERVER['DOCUMENT_ROOT']=" + json.dumps(str(public))
                          + ";$_SERVER['HTTP_HOST']='bobsome1.com';$_SERVER['HTTPS']='on';$base="
                          + json.dumps(str(checkout)) + ";" + r'''
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path === '/fixture') {
    require $base.'/lib.php';$config=bc_boot('GET');
    $id=str_repeat('a',32);$token=str_repeat('b',64);$now=time();
    $expired=($_GET['expired']??'0')==='1';
    $order=bc_new_order($id,$token,$now-($expired?BC_CHECKOUT_TTL+30:30),$config);
    $order['state']='created';$order['paypal_order_id']='ORDERRECOVERY123';
    $order['approval_url']='https://www.sandbox.paypal.com/checkoutnow?token=ORDERRECOVERY123';
    $order['capture_attempted_at']=($_GET['attempted']??'0')==='1'?$now-10:null;
    bc_store($config['private_dir'],static function(array &$ledger)use($order,$id):void{$ledger['orders']=[$id=>$order];});
    $_SESSION['order_id']=$id;$_SESSION['download_token']=$token;unset($_SESSION['last_check_at']);
    $response=['id'=>$order['paypal_order_id'],'status'=>'COMPLETED','purchase_units'=>[[
        'custom_id'=>$id,'reference_id'=>BC_SKU,'payee'=>['merchant_id'=>'MERCHANTTEST1'],
        'amount'=>['value'=>'7.00','currency_code'=>'USD'],'payments'=>['captures'=>[[
        'id'=>'CAPTURETEST12345','status'=>'COMPLETED','amount'=>['value'=>'7.00','currency_code'=>'USD']]]]]]];
    $mode=$_GET['mode']??'paid';
    if($mode==='unpaid')$response['status']='APPROVED';
    if($mode==='refund')$response['purchase_units'][0]['payments']['captures'][0]['status']='REFUNDED';
    if($mode==='timeout')$response=['test_failure'=>true];
    if($mode==='capture_refund')$response['test_capture_refund']=true;
    file_put_contents($config['private_dir'].'/response.json',json_encode($response));
    file_put_contents($config['private_dir'].'/provider-calls.jsonl','');
    header('Content-Type:application/json');echo json_encode(['csrf'=>$_SESSION['csrf']]);exit;
}
if($path==='/store/checkout/')$path='/store/checkout/index.php';
if(!is_string($path)||!preg_match('#^/store/checkout/(?:index|return|check|capture|start|download)\.php$#D',$path)){http_response_code(404);exit;}
require $base.'/'.basename($path);
''')
        with socket.socket() as listener:
            listener.bind(("127.0.0.1", 0))
            port = listener.getsockname()[1]
        env = dict(os.environ)
        env["BOBSOME1_COMMERCE_CONFIG"] = str(config_path)
        env["BC_TEST_PRIVATE"] = str(private)
        with (root / "server.log").open("wb") as log:
            process = subprocess.Popen([PHP, "-d", "disable_functions=curl_exec", "-S", f"127.0.0.1:{port}", str(router)],
                                       cwd=public, env=env, stdout=log, stderr=log)
            try:
                for _ in range(100):
                    if process.poll() is not None:
                        raise RuntimeError("PHP test server stopped")
                    try:
                        with socket.create_connection(("127.0.0.1", port), timeout=0.2):
                            break
                    except OSError:
                        time.sleep(0.05)
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


def request(base, path, cookie="", fields=None, origin="https://bobsome1.com"):
    headers = {"Host": "bobsome1.com", "Sec-Fetch-Site": "same-origin"}
    if cookie:
        headers["Cookie"] = cookie
    if origin:
        headers["Origin"] = origin
    body = None
    if fields is not None:
        headers["Content-Type"] = "application/x-www-form-urlencoded"
        body = urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(base + path, data=body, headers=headers)
    try:
        response = urllib.request.build_opener(NoRedirect).open(req, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read().decode(), dict(response.headers)


if not PHP:
    raise SystemExit("PHP with curl and ZIP is required")
with server() as (base, private):
    def seed(query=""):
        status, body, headers = request(base, "/fixture" + query)
        check(status == 200, "Synthetic private fixture initializes")
        cookie = headers["Set-Cookie"].split(";", 1)[0]
        check("secure" in headers["Set-Cookie"].lower() and "httponly" in headers["Set-Cookie"].lower(), "Checkout session has secure cookie attributes")
        return cookie, json.loads(body)["csrf"]

    for expired in [False, True]:
        cookie, csrf = seed("?attempted=1&expired=" + str(int(expired)))
        status, body, headers = request(base, "/store/checkout/", cookie)
        check(status == 200 and 'action="check.php"' in body, "Reload exposes safe recovery for the saved order")
        check('action="start.php"' not in body, "Uncertain payment is not replaced by a new checkout")
        if expired:
            check('action="capture.php"' not in body, "Expired checkout cannot charge")
        else:
            check("Retry the same $7 payment confirmation" in body, "Unexpired interrupted capture has an explicit same-request retry")
        check((private / "provider-calls.jsonl").read_text() == "", "Reload makes no provider request")
        status, returned, _ = request(base, "/store/checkout/return.php?token=ORDERRECOVERY123", cookie)
        check(status == 200 and 'action="check.php"' in returned and 'action="capture.php"' not in returned,
              "Returning after an uncertain or expired payment offers a read-only check")
        check((private / "provider-calls.jsonl").read_text() == "", "PayPal return page makes no provider request")
        status, body, headers = request(base, "/store/checkout/check.php", cookie, {"csrf": csrf})
        check(status == 200 and "Your complete study bundle is ready" in body, "Lost capture result is recovered by real route")
        check('action="download.php"' in body, "Verified recovery offers the original bundle")
        calls = [json.loads(line) for line in (private / "provider-calls.jsonl").read_text().splitlines()]
        check(calls == [["POST", "/v1/oauth2/token"], ["GET", "/v2/checkout/orders/ORDERRECOVERY123"], ["GET", "/v2/payments/captures/CAPTURETEST12345"]], "Recovery authenticates then only reads the saved payment and current capture")
        check("no-store" in headers.get("Cache-Control", "") and headers.get("Referrer-Policy") == "no-referrer", "Recovery page is private and does not leak a referrer")
        ledger = json.loads((private / "orders.json").read_text())
        check(len(ledger["orders"]) == 1 and ledger["orders"]["a" * 32]["state"] == "paid", "Exactly one original order is fulfilled")
        check(request(base, "/store/checkout/check.php", cookie, {"csrf": csrf})[0] == 429, "Rapid provider rechecks are limited")

    for mode, expected in [("unpaid", 409), ("refund", 409), ("capture_refund", 409), ("timeout", 503)]:
        cookie, csrf = seed("?attempted=1&mode=" + mode)
        before = (private / "orders.json").read_bytes()
        status, body, _ = request(base, "/store/checkout/check.php", cookie, {"csrf": csrf})
        check(status == expected and "Return to your existing checkout" in body, "Failed verification offers a safe retry path: " + mode)
        check("Download my complete study bundle" not in body, "Failed verification cannot release content: " + mode)
        check((private / "orders.json").read_bytes() == before, "Failed verification preserves payment state: " + mode)

    cookie, csrf = seed()
    status, returned, _ = request(base, "/store/checkout/return.php?token=ORDERRECOVERY123", cookie)
    check(status == 200 and 'action="capture.php"' in returned and "Pay $7 USD and unlock my study bundle" in returned,
          "First approval return clearly requests payment confirmation")
    check(request(base, "/store/checkout/check.php", cookie)[0] == 405, "Recovery rejects GET")
    check(request(base, "/store/checkout/check.php", cookie, {"csrf": "wrong"})[0] == 403, "Recovery rejects invalid CSRF")
    check(request(base, "/store/checkout/check.php", cookie, {"csrf": csrf}, "https://evil.example")[0] == 403, "Recovery rejects foreign origins")
    check((private / "provider-calls.jsonl").read_text() == "", "Rejected requests cannot reach PayPal")
    status, body, _ = request(base, "/store/checkout/return.php?token=OTHERORDER12345", cookie)
    check(status == 400 and "Download my complete study bundle" not in body, "Wrong return token cannot recover another order")
    check(request(base, "/store/checkout/check.php", "", {"csrf": csrf})[0] == 403, "Another browser session cannot reuse the recovery form")
print(f"Bundle payment recovery HTTP: {CHECKS} checks passed (synthetic transport; no PayPal calls).")
