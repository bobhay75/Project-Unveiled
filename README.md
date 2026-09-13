# Project Unveiled

The source for [bobsome1.com](https://bobsome1.com), including the complete public reader, evidence pages, historical timeline, privacy-first analytics, reader signup, and protected owner tools.

## Public entry points

- `/` — Project homepage
- `/book/read/` — Complete reader and table of contents
- `/book/read/chapter-01.html` — Start reading
- `/book/timeline.html` — Interactive historical timeline
- `/book/research.html` — Research standards and corrections process
- `/questions/` — Focused public evidence files
- `/truth/` — Published Truth Trials and question intake
- `/truth/lab/` — Local-first Trust-Worthy claim-mapping and Source Sweep workbench
- `/services/` — Bobsome1 Media + IT services and selected work
- `/store/` — Digital edition, fixed-scope service checkout, and partner paths

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

The current public-site audit covers local links, linked files, URL fragments, duplicate IDs, H1/title counts, and JSON-LD syntax. Run `bash tests/trust-worthy-lab/run_all.sh` for the Trust-Worthy release, research-engine, provenance, hostile-input, privacy, and rollback contract.

Before changing the cPanel deployment policy or removing a public file, also run:

```bash
python3 scripts/check-deployment-safety.py --write-manifest
python3 scripts/check-deployment-safety.py
python3 scripts/security-audit.py --history
bash tests/deployment/run.sh
```

Deployment copies only the reproducible allowlist in `deployment/public-files.txt`. Public-file removal is explicit rather than recursive: register an old path in `deployment/retired-public-paths.txt`. This prevents stale endpoints without risking separately managed files or runtime data under `public_html`.
