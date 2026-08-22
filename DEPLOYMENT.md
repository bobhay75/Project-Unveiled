# Safe Production Deployment

## Deploy from GitHub

Repository: `/home/bobsome1/repositories/Project-Unveiled`

Production site: `/home/bobsome1/public_html`

1. Pull `main` with `git pull --ff-only origin main`.
2. Confirm the working tree is clean with `git status --short`.
3. Use cPanel Git Version Control → **Deploy HEAD Commit**.
4. Confirm the site remains on HTTPS and test the reader, timeline, research pages, and signup.

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
