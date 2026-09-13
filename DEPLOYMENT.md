# Safe Production Deployment

## Deploy from GitHub

Repository: `/home/bobsome1/repositories/Project-Unveiled`

Production site: `/home/bobsome1/public_html`

Runtime requirement: select PHP 8.1 or newer for `bobsome1.com` in cPanel MultiPHP Manager and use the matching PHP 8.1+ CLI for server-side behavior tests. The release gate stops with a clear error if it finds an older PHP CLI.

1. Pull `main` with `git pull --ff-only origin main`.
2. Confirm the working tree is clean with `git status --short`.
3. Use cPanel Git Version Control → **Deploy HEAD Commit**.
4. Confirm the deployment log contains both `Retired-path cleanup passed` and `Tracked public-file sync completed without broad deletion`.
5. Confirm the site remains on HTTPS and test the reader, timeline, research pages, and signup.

## Guarded public-file sync

`.cpanel.yml` delegates to `scripts/deploy-public.sh`. The script accepts only `/home/bobsome1/public_html`, requires a clean production checkout with no tracked differences or untracked files, verifies `deployment/public-files.txt` against the Git-tracked tree, and then copies only that exact public allowlist with fixed directory and file permissions. It rejects an incomplete or extra manifest entry, an internal/private path, an untracked path, a symlinked deployment root, and any other production destination. This binds the copied working-tree bytes to the HEAD commit cPanel reports as deployed.

Broad `rsync --delete` is intentionally disabled because `public_html` can contain runtime data and separately managed applications. Files removed or renamed in Git are instead deleted through the exact allowlist in `deployment/retired-public-paths.txt`:

1. Add the old repository-relative file path to the registry in the same pull request that removes or renames it.
2. List each file separately. Absolute paths, globs, parent traversal, symlinks, duplicate entries, and recursive directory deletion fail closed.
3. Keep the entry permanently so an older stale deployment is cleaned when it next receives current `main`.
4. Do not add a path that still exists in the repository. Both CI and the deployment script reject it.

After adding, removing, or renaming any public file, regenerate the exact deployment allowlist and review its diff:

```bash
python3 scripts/check-deployment-safety.py --write-manifest
git diff -- deployment/public-files.txt deployment/retired-public-paths.txt
```

Before merging a deployment-policy change, run:

```bash
python3 scripts/check-deployment-safety.py
python3 scripts/security-audit.py --history
bash tests/deployment/run.sh
```

The pull-request workflow also compares removed public files with the retirement registry and reproduces the public-file allowlist. Untracked files are never selected for production deployment. Private data, credentials, archives, logs, source notes, tests, and deployment tooling cannot enter the manifest even if accidentally tracked.

## GitHub repository controls

The repository workflow has read-only permissions, does not persist checkout credentials, scans current files and reachable history for high-confidence secret formats, and runs pinned third-party Actions. Dependabot checks the GitHub Actions pin weekly. The project currently has no npm, Composer, or Python package manifest, so there is no application dependency lockfile to audit.

Repository administrators should also require the `Site safety checks` job on `main`, block force pushes and branch deletion, and enable GitHub secret scanning, push protection, Dependabot alerts, and Dependabot security updates where the repository plan exposes them. Those account-level controls cannot be enforced by files in this repository.

## Intake storage limits

Question and evidence-challenge records stay in `/home/bobsome1/site-private/trust-worthy`, outside `public_html` and Git. Queue writes use a stable companion lock and atomic replacement. Invalid, truncated, or oversized JSON fails closed and is not reset or overwritten. Secrets, temporary files, queues, and their lock files must all verify as owner-only `0600` before private data is read or written.

Every real cPanel deployment runs `/usr/local/bin/php -q scripts/migrate-intake-permissions.php --pre-sync` before public-file sync and repeats it with `--post-sync` afterward. The CLI targets the exact private Trust-Worthy and Project Unveiled analytics roots: it secures named intake and AI state and atomically removes retired analytics fields during both compatible phases. Only after Release 16 files are live does it retire or preserve the explicitly named legacy Daily artifacts and prune expired private Daily candidate derivatives under the canonical queue lock, so a failed rsync cannot break the still-live Release 15 desk. Missing analytics and unconfigured Trust-Worthy stores are no-ops. When an absent Trust-Worthy store is configured through `OPENAI_API_KEY`, the authenticated migration creates its private root and HMAC secret so the public health check can remain read-only. Symlinks, non-files, corrupt records, and configured capacity overruns fail the deployment closed.

The public intake forms accept only `application/x-www-form-urlencoded` requests and reject all file uploads and multipart bodies. The endpoints enforce both the declared `Content-Length` and bytes read from `php://input`, so missing-length or chunked bodies cannot bypass the 64 KiB question and 112 KiB challenge ceilings.

The shared-hosting defaults are deliberately bounded:

- `TW_INTAKE_MAX_RECORDS=1000`
- `TW_INTAKE_MAX_FILE_BYTES=8388608` (8 MiB per queue)
- `TW_INTAKE_HOURLY_LIMIT=5` submissions per IP hash and queue
- `TW_INTAKE_DAILY_LIMIT=12` submissions per IP hash and queue
- `TW_INTAKE_RETENTION_DAYS=180`
- `TW_INTAKE_FORM_TTL_SECONDS=7200` (two hours)

Each new version-2 record receives a `delete_after_utc` value. Expired records are pruned inside the same exclusive lock during the next successful append. For legacy records without that field, expiry is derived from `submitted_at_utc` plus the configured retention period; invalid timestamps fail closed without overwriting the queue. If a queue reaches its record or byte ceiling, intake returns `503` until the owner validates and archives or removes reviewed records; never replace a damaged queue with an empty array.

## Trust-Worthy release 16 gate

Before deployment, require `bash tests/trust-worthy-lab/run_all.sh` and the GitHub `Site safety checks` workflow to pass. After deployment, run `node tests/trust-worthy-lab/live-release-gate.mjs` from a checkout with Node 20 or newer.

If any release-16 Evidence Lab live check fails, keep the release-16 repository checkout in place and run:

```bash
bash scripts/rollback-trust-worthy-lab.sh /home/bobsome1/public_html/truth/lab
```

The guarded rollback restores only the verified release-15 Evidence Lab bytes from Project-Unveiled commit `9c4faa71e9c4955c1e597b2b3f9c8a861cac3bf1`. It rejects symlinked or unexpected destination files and verifies every copied byte. Do **not** check out or deploy that older commit as a whole site: its legacy broad-copy deployment would not remove files introduced by release 16 and could leave an inconsistent public tree.

## Self-hosted 7-Day Unveiled Journey

No Kit or third-party newsletter service is required. Subscriber data and credentials stay outside `public_html`.

### Private files

Directory:

`/home/bobsome1/site-private/project-unveiled`

Required files:

- `mailing-address.txt` — valid physical mailing address for email compliance; permissions `0640`.
- `smtp.json` — authenticated mailbox settings; permissions `0600`. Never commit this file.

SMTP configuration shape:

```json
{
  "host": "bobsome1.com",
  "port": 465,
  "username": "letter@bobsome1.com",
  "password": "MAILBOX_PASSWORD",
  "from": "letter@bobsome1.com"
}
```

The production mailbox is `letter@bobsome1.com`. SMTP uses implicit TLS on port 465.

### cPanel mail settings

- Email Routing for `bobsome1.com` must be **Local Mail Exchanger**.
- SPF, DKIM, DMARC, and PTR should all show **Valid** in Email Deliverability.
- Do not use PHP `mail()`; Namecheap's local scanning wrapper rejects its default envelope sender.

### Cron

Current production schedule:

```cron
0,30 * * * * /usr/local/bin/php -q /home/bobsome1/public_html/book/unveiled-journey-cron.php >/dev/null 2>&1
```

Manual test:

```bash
/usr/local/bin/php -q /home/bobsome1/public_html/book/unveiled-journey-cron.php
```

### End-to-end test

1. Submit `https://bobsome1.com/unveiled/`.
2. Confirm the email arrives from `letter@bobsome1.com`.
3. Click the private confirmation link.
4. Run the cron manually.
5. Confirm the output reports `1 sent, 0 failed`.
6. Confirm Day 1 arrives and test the unsubscribe link.

The signup endpoint refuses journey signups until the mailing address exists. The cron stops safely when required configuration is missing.

## Files that must not remain publicly downloadable

- Hosting backup ZIPs
- Dated HTML backups
- Installer scripts
- Error logs
- SQL exports
- Subscriber or analytics data
- SMTP credentials
- Deployment notes and checksum files
