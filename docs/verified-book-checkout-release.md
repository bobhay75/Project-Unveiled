# Conversion paths and verified digital-book checkout

## Scope and current state

This change builds on the service-intake work in PR #41. It does not merge,
replace or alter the separate homepage-restoration proposal in PR #42.
No production deployment, PayPal transaction, customer email or advertising
spend is performed by this change.

- The existing $7 digital book leads to a delivery-aware checkout page.
- The existing $250 Visibility Starter leads to a written scope request, not
  immediate payment. Prices, scope-approval boundaries and existing deliverables
  are preserved.
- The service brief presents five required fields, with optional detail in a
  keyboard-operable disclosure. Only the published Visibility Starter query
  value can prefill intent; arbitrary URL text is never inserted.
- PayPal order creation and capture happen on the server. Browser redirects,
  approval, screenshots and analytics clicks do not authorize delivery.
- Checkout is disabled unless the private configuration and approved paid file
  are valid. The disabled page retains the existing clearly labelled manual
  purchase/delivery path and the free public reader.

Service inquiries still require Robert's review in `/owner/leads.php`.
The site does not automatically approve work, promise results, send marketing,
subscribe leads, agree to terms or change a prospect's payment structure.

## Release gates

1. Complete review and pass Site safety checks on the exact candidate commit.
   The checkout tests use synthetic responses only and must never call live
   PayPal or charge a real account.
2. Inspect the changed store, service form and checkout at narrow mobile and
   desktop widths. Tab through required fields, optional details, errors and
   payment confirmation. Test cancellation, refresh, back navigation and
   expiration. Static checks are not a substitute for these browser checks.
3. Robert approves the downloadable edition and its displayed format. Do not
   compile private source material or make a new edition without approval.
4. Configure an owner-authorized PayPal REST application and its matching
   merchant account privately. Never put credentials in HTML, JavaScript, Git,
   PR comments or a chat message. The old PayPal.me/hosted-button payments do
   not automatically become orders in this application.
5. Exercise the full sandbox flow with seller and buyer sandbox accounts.
   Verify exact amount/currency/merchant checks, capture, same-browser delivery,
   retries after interruption and refused downloads after refunds. Test an
   unavailable PayPal API and missing file: both must fail closed.
6. Confirm the host supports the checkout's PHP/cURL requirements, private
   writable storage, secure session cookies and its Apache protections.
7. Obtain Robert's explicit production-deployment approval. Approval to edit
   the site is not approval to publish an untested payment path.
8. Switch to the authorized live configuration only after all sandbox and host
   gates pass. A live-money smoke purchase/refund needs separate explicit
   transaction authorization; never run it automatically.

## Verification commands

From the repository root:

```bash
python3 scripts/security-audit.py --history
python3 scripts/check-deployment-safety.py
bash tests/deployment/run.sh
python3 scripts/validate_site.py
node --check services/contact.js
node tests/services/conversion-flow.mjs
python3 tests/services/contact-flow.py
php tests/commerce/backend.php
bash tests/trust-worthy-lab/run_all.sh
```

A pre-existing PHP 8.3 CLI can check syntax and offline commerce logic, but it
does not provide the cURL and mbstring modules required for full runtime tests.
No additional runtime was installed after environment permissions blocked
installation. Full PHP behavior checks must pass in CI; a locally skipped test
is not a pass. The cloud preview does not provide a verified local
mobile/keyboard result, so browser acceptance remains a release gate.

## Private configuration contract

Use `store/checkout/config.example.php` as a template only. The public URL is
denied; never edit this repository copy to contain credentials. Create the
actual PHP-array configuration outside both the public document root and the
repository, with owner-only file access (`0600`). Point the server-side
`BOBSOME1_COMMERCE_CONFIG` environment variable at that absolute private file.
Verify environment-variable support with the hosting provider; do not expose
a configuration-dump or `phpinfo()` endpoint to debug it.

Required keys are `enabled` (boolean), `mode` (`sandbox` or `live`),
`client_id`, `client_secret`, `merchant_id`, `private_dir`, `asset_path`, and
`asset_sha256`. Use the merchant ID, not an email address. `private_dir` must
already exist with owner-only directory access (`0700`). The approved PDF
must be outside the document root and repository, have `0600` access, be
100 bytes–50 MiB, and match the SHA-256 digest in the configuration. File
validation is not editorial approval: Robert must approve the edition.

The product and price are fixed server-side at $7.00 USD; no browser field or
configuration option can choose a different amount. The session cookie is
Secure, HttpOnly and SameSite=Lax. The private ledger is capped at 5,000
records and 10 MiB and uses an exclusive lock plus atomic replacement.
Unfinished order records expire after seven days, paid records after 180 days;
cleanup runs when storage is next accessed. Plan owner-controlled private
cleanup if the site is inactive. Download grants last 24 hours and allow at
most 10 attempts in the originating browser session.

Do not repoint a live checkout configuration at a different PayPal merchant
or sandbox while unresolved orders exist. Disable new purchases, reconcile
those orders in the original PayPal account and preserve private records
before making a reviewed account or environment change.

## Publishing and rollback

After review, all gates and deployment approval, publish through the existing
guarded cPanel process documented in `docs/bobsome1-contact-release.md`.
Use the exact approved commit and `deployment/public-files.txt`. Keep runtime
orders, session files, the paid edition and credentials outside `public_html`
and outside Git. Do not deploy internal docs or tests.

To stop new automatic purchases, disable the private checkout configuration.
Retain private order records and reconcile any interrupted payment in PayPal
before asking a customer to try a new purchase. A site rollback cannot undo a
payment or retrieve an already downloaded file. Preserve manual order support.

For code rollback, revert the reviewed conversion/checkout commit and redeploy
through the guarded process. Register each removed public checkout file in
`deployment/retired-public-paths.txt`; do not delete a broad directory or restore
an old whole-site snapshot. Preserve the service-intake work and private leads.

## Known limits

This version is a same-browser digital-book checkout, not a customer account
system, email fulfillment service, subscription platform or automated service
salesperson. It does not introduce a webhook receiver. Remote payment checks
before download do not provide a background dispute/refund dashboard. Use
PayPal as the authoritative payment record and handle disputes, tax obligations,
refund policy and exceptional delivery requests through owner review.
