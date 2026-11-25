#!/usr/bin/env python3
"""Prepare a private plain-text weekly newsletter draft from Bob's own posts.

For a Friday cPanel cron job, run this script with the absolute path to a
private directory outside public_html. Put teachings.json there as an array
of records with date (YYYY-MM-DD, Chicago time), url (canonical public HTTPS
post), text (verbatim), theme, and permission ("author-owned"). Use --ending
YYYY-MM-DD to reproduce an issue. Drafts never email or publish themselves.
"""
import argparse
import json
import os
from datetime import date, datetime, timedelta
from pathlib import Path
from tempfile import NamedTemporaryFile
from urllib.parse import urlsplit
from zoneinfo import ZoneInfo


def draft(folder, ending):
    source = folder / "teachings.json"
    records = json.loads(source.read_text(encoding="utf-8"))
    if not isinstance(records, list):
        raise ValueError("teachings.json must contain an array")
    posts = {}
    for record in records:
        day = date.fromisoformat(record["date"])
        if not ending - timedelta(days=6) <= day <= ending:
            continue
        if record.get("permission") != "author-owned":
            continue
        url = record["url"]
        parts = urlsplit(url)
        if parts.scheme != "https" or not parts.hostname or any(c.isspace() for c in url):
            raise ValueError("Each teaching needs a canonical public HTTPS link")
        text, theme = record["text"], record["theme"]
        if not text.strip() or not theme.strip():
            raise ValueError("Teaching text and theme are required")
        item = (day, theme, text)
        if url in posts and posts[url] != item:
            raise ValueError("Conflicting source records for " + url)
        posts[url] = item
    if not posts:
        return "No eligible teachings for this week."
    sections = ["Project Unveiled | Weekly teachings", "Week ending " + ending.isoformat()]
    for url, (day, theme, text) in sorted(posts.items(), key=lambda row: (row[1][0], row[0])):
        sections.extend([f"{theme} | {day}", text, url])
    sections.append("Read Project Unveiled at https://bobsome1.com")
    body = "\n\n".join(sections) + "\n"
    target = folder / f"draft-{ending}.txt"
    with NamedTemporaryFile(mode="w", encoding="utf-8", dir=folder, delete=False) as temp:
        temp_path = Path(temp.name)
        try:
            temp.write(body)
            temp.flush()
            os.fsync(temp.fileno())
            try:
                os.link(temp_path, target)
            except FileExistsError:
                if target.read_text(encoding="utf-8") != body:
                    raise ValueError("Existing draft differs. Review it before regenerating.")
        finally:
            temp_path.unlink(missing_ok=True)
    return str(target)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("private_directory", type=Path)
    parser.add_argument("--ending", type=date.fromisoformat)
    args = parser.parse_args()
    folder = args.private_directory.resolve()
    if not folder.is_dir() or "public_html" in folder.parts or Path(__file__).resolve().parents[1] in (folder, *folder.parents):
        parser.error("Choose an existing private directory outside the website and repository")
    try:
        print(draft(folder, args.ending or datetime.now(ZoneInfo("America/Chicago")).date()))
    except (KeyError, TypeError, ValueError, OSError) as error:
        parser.exit(1, f"Draft failed: {error}\n")
