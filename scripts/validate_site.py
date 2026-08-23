#!/usr/bin/env python3
"""Validate public links and reject private or deployment artifacts."""

from __future__ import annotations

import os
import sys
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parents[1]
FORBIDDEN_SUFFIXES = {".bak", ".key", ".log", ".old", ".pem", ".sql", ".zip"}
FORBIDDEN_NAMES = {".env", "error_log", "thumbs.db", ".ds_store"}
SKIP_DIRS = {".git", ".idea", ".vscode", "site-private"}
SKIP_LINK_AUDIT_DIRS = {"owner"}


class References(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.items: list[tuple[int, str]] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        for name, value in attrs:
            if value and name in {"href", "src", "action"}:
                self.items.append((self.getpos()[0], value.strip()))


def public_files() -> list[Path]:
    return [
        path
        for path in ROOT.rglob("*")
        if path.is_file() and not any(part.lower() in SKIP_DIRS for part in path.parts)
    ]


def resolve_reference(source: Path, raw: str) -> Path | None:
    if not raw or raw.startswith(("#", "//")) or "<?" in raw:
        return None
    parsed = urlsplit(raw)
    if parsed.scheme or parsed.netloc:
        return None
    target = unquote(parsed.path)
    if not target or target.startswith(("data:", "javascript:")):
        return None
    candidate = ROOT / target.lstrip("/") if target.startswith("/") else source.parent / target
    candidate = Path(os.path.normpath(candidate))
    if candidate.is_dir():
        for index_name in ("index.html", "index.htm", "index.php"):
            index_path = candidate / index_name
            if index_path.is_file():
                return index_path
    return candidate


def main() -> int:
    errors: list[str] = []
    files = public_files()

    for path in files:
        lower = path.name.lower()
        if lower in FORBIDDEN_NAMES or path.suffix.lower() in FORBIDDEN_SUFFIXES:
            errors.append(f"forbidden repository artifact: {path.relative_to(ROOT)}")

    for required in ("index.html", "privacy.html", "robots.txt", "sitemap.xml", ".htaccess"):
        if not (ROOT / required).is_file():
            errors.append(f"missing required file: {required}")

    for source in (
        path
        for path in files
        if path.suffix.lower() in {".html", ".htm", ".php"}
        and not any(part.lower() in SKIP_LINK_AUDIT_DIRS for part in path.relative_to(ROOT).parts)
    ):
        parser = References()
        try:
            parser.feed(source.read_text(encoding="utf-8"))
        except (OSError, UnicodeError) as exc:
            errors.append(f"cannot parse {source.relative_to(ROOT)}: {exc}")
            continue
        for line, raw in parser.items:
            target = resolve_reference(source, raw)
            if target is not None and not target.exists():
                errors.append(f"broken local reference: {source.relative_to(ROOT)}:{line} -> {raw}")

    if errors:
        print("Site validation failed:")
        print("\n".join(f"- {item}" for item in errors))
        return 1

    print(f"Site validation passed: {len(files)} files checked.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
