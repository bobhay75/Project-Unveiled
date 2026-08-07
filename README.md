# Project Unveiled

The source for [bobsome1.com](https://bobsome1.com), including the complete public reader, evidence pages, historical timeline, privacy-first analytics, reader signup, and protected owner tools.

## Public entry points

- `/` — Project homepage
- `/book/read/` — Complete reader and table of contents
- `/book/read/chapter-01.html` — Start reading
- `/book/timeline.html` — Interactive historical timeline
- `/book/research.html` — Research standards and corrections process
- `/questions/` — Focused public evidence files

## Hosting

The production site targets Apache with PHP on Namecheap shared hosting. Private configuration and operating data live outside `public_html` and must never be committed.

## Safety rules

- Never commit `site-private`, subscriber records, analytics records, passwords, secrets, server logs, SQL exports, or hosting backups.
- Test internal links and JavaScript before deployment.
- Back up production before replacing files.
- Review changes before merging or uploading them.

## Verification

Run the repository audit from its parent workspace:

```bash
python3 tools/audit_site.py worksite
node --check worksite/book/signup-widget.js
node --check worksite/book/reader-community.js
node --check worksite/project-unveiled-analytics/tracker.js
```

The current public-site audit covers local links, linked files, URL fragments, duplicate IDs, H1/title counts, and JSON-LD syntax.
