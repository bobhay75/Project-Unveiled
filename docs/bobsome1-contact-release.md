# Bobsome1 contact completion

## Release scope

Extends PR #41 so a visitor can submit a problem brief without a configured mail
app and Robert can read it at `/owner/leads.php`. Store service and partnership
buttons reach the same form. Failed submissions keep entered text and report
that receipt was not confirmed. No email, marketing enrollment, payment or AI
call happens automatically.

The Project Unveiled brand returns to `/book/`; its navigation distinguishes
the book home from the Bobsome1 parent site. Chapter and appendix prose is
unchanged. Store payment links retain the existing prices and manual delivery.

## Verification

Run from the repository root:

```bash
python3 scripts/security-audit.py --history
python3 scripts/check-deployment-safety.py
bash tests/deployment/run.sh
python3 scripts/validate_site.py
node --check services/contact.js
python3 tests/services/contact-flow.py
bash tests/trust-worthy-lab/run_all.sh
```

The contact test requires PHP with mbstring and exercises a temporary, isolated
queue. It checks successful JSON and redirect responses, input and URL rejection,
origin/method/body limits, honeypot behavior, rate limits, owner authorization,
escaped output, private file modes, expiry, and corrupt/symlink rejection. It
does not call production or send messages. CI must pass on the current PR head.

This workspace has no PHP binary; local checks that skip PHP are not sufficient
evidence. Use the mandatory contact-flow CI step and existing PHP release checks.
The cloud browser rejected local file previews, so mobile visual and keyboard
acceptance of the changed form remains a release gate.

## Approval and deployment

Production publication requires Robert's explicit deployment approval under
`AGENTS.md`. Before deployment, review the form at 390px and desktop widths,
tab through the labelled fields and submit button, verify error focus and
retained input, and inspect the confirmation page. Do not treat a static
confirmation-page visit as evidence that a brief was stored.

After approval and all gates, merge PR #41. In cPanel, use the **Project-Unveiled**
repository at `/home/bobsome1/repositories/Project-Unveiled`, branch `main`.
Update from remote, verify the approved merge SHA, then deploy with the existing
guarded `.cpanel.yml` policy. The deployment target is `/home/bobsome1/public_html`.

Verify on production:

1. `/`, `/book/`, the reader, Chapter 1, timeline, research, Journey signup page,
   privacy page, `/services/`, and `/store/` load with correct navigation.
2. Signed-out `/owner/leads.php` is protected by the existing Apache login.
   PHP also denies access when Apache has not set `REMOTE_USER`; do not weaken
   this check or the existing owner `.htaccess` to make the page open.
3. An owner-controlled synthetic brief produces confirmation and appears in the
   protected inbox. Do not enter a real prospect's data for a release test.
4. Read and reply to genuine leads personally through the protected inbox.
   Queue records are not public and are not automatic email notifications.

## Rollback

Revert this PR's changes in Git and redeploy the resulting reviewed commit with
the guarded deploy script. Register new public paths in
`deployment/retired-public-paths.txt` if the revert removes them, so the allowlist
deployment removes only those exact paths. Do not broadly delete `public_html`.
Preserve the private `site-private/trust-worthy/leads.json` queue and its secrets;
do not copy them into Git, a public backup, or this document.

If only the form is malfunctioning, restore the previous services page first
and keep any saved briefs available privately while repairing the endpoint.
Payment completion and digital delivery remain manually verified; this release
does not introduce merchant webhooks or claim instant fulfillment.
