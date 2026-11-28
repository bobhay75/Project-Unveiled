#!/usr/bin/env python3
"""Verify the public case API over HTTP with the deploy allowlist and no private data."""

import http.client
import json
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
from urllib.parse import urlencode, urlsplit


ROOT = Path(__file__).resolve().parents[2]
CLAIMS = "/truth/api/claims.php"
CASE = "/truth/api/case.php"
OPENAPI = "/truth/api/openapi.json"


def request(port, path, method="GET"):
    connection = http.client.HTTPConnection("127.0.0.1", port, timeout=3)
    try:
        connection.request(method, path, headers={"Origin": "https://reader.example"})
        response = connection.getresponse()
        assert response.headers.get_content_type() == "application/json", path
        body = response.read()
        return response.status, json.loads(body) if body else None, response.headers
    finally:
        connection.close()


with tempfile.TemporaryDirectory(prefix="tw-public-cases-") as tmp:
    temporary = Path(tmp)
    public = temporary / "public"
    public.mkdir()
    manifest = set((ROOT / "deployment/public-files.txt").read_text().splitlines())
    required = {CLAIMS[1:], CASE[1:], OPENAPI[1:], "truth/lib/public-cases.php", "llms.txt"}
    assert required <= manifest, "The API and its discovery file must be deployed."

    published = {}
    for src in sorted((ROOT / "truth/cases").glob("TW-CLAIM-*.json")):
        path = src.relative_to(ROOT).as_posix()
        assert path in manifest, f"The public API must use deployed records: {path}"
        published[src.stem] = json.loads(src.read_text())
        required.add(path)
    assert published, "The catalog fixture must contain published records."
    missing_id = next(f"TW-CLAIM-{number:06d}" for number in range(999999, 0, -1)
                      if f"TW-CLAIM-{number:06d}" not in published)

    for path in required:
        target = public / path
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / path, target)

    private = temporary / "private-case.json"
    private.write_text('{"private_test_marker":true}')
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        port = listener.getsockname()[1]
    with (temporary / "php.log").open("w+") as log:
        args = ["php", "-d", "allow_url_fopen=0", "-d", "display_errors=1", "-S", f"127.0.0.1:{port}", "-t", str(public)]
        process = subprocess.Popen(args, stdout=log, stderr=log)
        try:
            for _ in range(60):
                try:
                    with socket.create_connection(("127.0.0.1", port), timeout=.2):
                        break
                except OSError:
                    if process.poll() is not None:
                        log.seek(0)
                        raise RuntimeError(log.read())
                    time.sleep(.05)
            else:
                raise RuntimeError("Public case server did not start.")

            def get(path, status=200, method="GET"):
                actual, body, headers = request(port, path, method)
                assert actual == status, (path, actual, body)
                return body, headers

            spec, _ = get(OPENAPI)
            assert spec["servers"][0]["url"] == "https://bobsome1.com/truth/api"
            assert {item["get"]["operationId"] for item in spec["paths"].values()} == {"claim_lookup", "case_get"}
            assert all(set(route) == {"get"} for route in spec["paths"].values())
            assert all(path.removeprefix("/truth/api") in spec["paths"] for path in [CLAIMS, CASE])
            discovery = (public / "llms.txt").read_text()
            assert f"https://bobsome1.com{OPENAPI}" in discovery

            catalog, headers = get(CLAIMS)
            assert headers["Access-Control-Allow-Origin"] == "*" and headers["Set-Cookie"] is None
            assert f"<{OPENAPI}>" in headers["Link"] and "public" in headers["Cache-Control"]
            records = catalog["cases"][:]
            while catalog["next_after"] is not None:
                after = catalog["next_after"]
                catalog, _ = get(CLAIMS + "?" + urlencode({"after": after}))
                assert catalog["cases"] and catalog["cases"][0]["case_id"] > after
                records.extend(catalog["cases"])
            assert list(published) == [item["case_id"] for item in records]
            for item in records:
                url = urlsplit(item["record_url"])
                assert (url.scheme, url.netloc, url.path) == ("https", "bobsome1.com", CASE)
                record, _ = get(url.path + "?" + url.query)
                assert record == published[item["case_id"]], "Retrieval changed the evidence record."
                assert all(item[key] == record[key] for key in ("case_id", "version", "status", "title", "claim_under_test", "public_url"))

            first, _ = get(CLAIMS + "?limit=1")
            assert first["cases"] == records[:1]
            if len(records) > 1:
                assert first["next_after"] == records[0]["case_id"]
                second, _ = get(CLAIMS + "?" + urlencode({"limit": 1, "after": first["next_after"]}))
                assert second["cases"] == records[1:2]
            filtered, headers = get(CLAIMS + "?q=bODILY+resurrection")
            assert "TW-CLAIM-000005" in [item["case_id"] for item in filtered["cases"]]
            assert headers["Cache-Control"] == "no-store"
            empty, _ = get(CLAIMS + "?q=bodily+zzznaonlymissing")
            assert empty == {"cases": [], "next_after": None}

            for invalid in ["q=", "q=%FF", "q%5B%5D=claim", "q=" + "a" * 241, "limit=0", "limit=51", "after=../private"]:
                assert get(CLAIMS + "?" + invalid, 400)[0] == {"error": "invalid_query"}
            for invalid in ["", "case_id[]=TW-CLAIM-000001", "case_id=..%2Fprivate-case", "case_id=TW-CLAIM-000001%00"]:
                assert get(CASE + "?" + invalid, 400)[0] == {"error": "invalid_case_id"}
            assert get(CASE + "?case_id=" + missing_id, 404)[0] == {"error": "case_not_found"}
            for path in [CLAIMS, CASE]:
                body, headers = get(path, 405, "POST")
                assert body == {"error": "method_not_allowed"} and headers["Allow"] == "GET, HEAD"
            assert get(CLAIMS, method="HEAD")[0] is None
            assert get(CASE + "?case_id=TW-CLAIM-000001", method="HEAD")[0] is None

            unexpected = public / f"truth/cases/{missing_id}.json"
            unexpected.symlink_to(private)
            invalid_record_url = CASE + "?case_id=" + missing_id
            assert get(invalid_record_url, 503)[0] == {"error": "catalog_unavailable"}
            assert get(CLAIMS + "?after=" + records[-1]["case_id"], 503)[0] == {"error": "catalog_unavailable"}
            unexpected.unlink()
            for contents in ["{", "false", "x" * 262145]:
                unexpected.write_text(contents)
                assert get(invalid_record_url, 503)[0] == {"error": "catalog_unavailable"}
            assert private.read_text() == '{"private_test_marker":true}'
        finally:
            process.terminate()
            process.wait(timeout=5)

print("Trust-Worthy public case API checks passed: published data parity, discovery, search, pagination, and HTTP boundaries.")
