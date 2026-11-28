#!/usr/bin/env python3
"""Compare public production pages to the currently checked out Git commit."""

import argparse
import subprocess
from concurrent.futures import ThreadPoolExecutor
from http.client import HTTPException
from pathlib import Path
from urllib.error import HTTPError
from urllib.parse import urlsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener


ROOT = Path(__file__).resolve().parents[1]
PAGES = {
    "/": "index.html",
    "/services/": "services/index.html",
    "/store/": "store/index.html",
    "/book/": "book/index.html",
    "/book/read/": "book/read/index.html",
    "/book/read/chapter-01.html": "book/read/chapter-01.html",
    "/book/timeline.html": "book/timeline.html",
    "/book/research.html": "book/research.html",
    "/unveiled/": "unveiled/index.html",
    "/privacy.html": "privacy.html",
}


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, new_url):
        return None


def verify_page(origin, route, expected):
    request = Request(origin + route, headers={"Cache-Control": "no-cache"})
    try:
        with build_opener(NoRedirect()).open(request, timeout=15) as response:
            if response.status != 200:
                return f"HTTP {response.status}"
            if response.headers.get_content_type() != "text/html":
                return "expected text/html"
            if response.read(len(expected) + 1) != expected:
                return "content differs from the selected release"
    except HTTPError as error:
        return f"HTTP {error.code}"
    except (OSError, HTTPException) as error:
        return str(error)
    return ""


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--origin", default="https://bobsome1.com")
    args = parser.parse_args()
    origin = urlsplit(args.origin)
    if (
        origin.scheme != "https" or not origin.hostname or origin.username is not None
        or origin.path not in ("", "/") or origin.query or origin.fragment
    ):
        parser.error("--origin must be an HTTPS origin without credentials, a path, or a query")

    commit = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
    base = f"{origin.scheme}://{origin.netloc}"
    print(f"Comparing {base} with release {commit}")
    expected = [
        subprocess.check_output(["git", "show", f"{commit}:{path}"], cwd=ROOT)
        for path in PAGES.values()
    ]
    with ThreadPoolExecutor(max_workers=4) as pool:
        results = list(pool.map(verify_page, [base] * len(PAGES), PAGES, expected))
    for route, result in zip(PAGES, results):
        print(f"{route}  {result or 'matches'}")
    return int(any(results))


if __name__ == "__main__":
    raise SystemExit(main())
