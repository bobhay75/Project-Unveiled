# Conversion paths and verified complete study-bundle checkout

## Scope and current state

The October 2 buyer-package decision supersedes the earlier single-PDF
contract. Automatic delivery now requires one private ZIP containing all four
components: the illustrated ebook, timeline, Deep Study Guide, and No More Milk
advanced theology kit. Packaging and automated verification do not record
Robert's approval. The candidate must reach Robert for review first; the
checked-in template has `enabled=false` and both approval fields blank.

This change includes the service-intake work from PR #41. PR #43 now integrates
main at `9847c761646e123d8f7977b0c5f0492c4b38474d`, including the merged homepage
restoration and PR #45 smart store. The restored homepage is unchanged from
that main commit. The four intent routes, eight walkthroughs and
`store/store.js` public-manifest entry are retained. Book walkthrough actions
use guarded checkout; service and partnership actions use the protected intake
path. Automatic checkout remains disabled by default.
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
- Checkout is disabled unless the private configuration, complete bundle, and
  approval bound to the exact archive hash are valid. The disabled page retains the existing clearly labelled manual
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
3. Deliver the complete four-part candidate and SHA-256 evidence to Robert
   for editorial and device review before activation. His instruction to build
   this candidate permits assembly; it is not approval to sell these exact
   bytes. Record explicit approval of the archive hash and its actual UTC time
   only after that review. Correcting even one byte requires a new hash, new
   complete-package review, and updated approval. Never infer approval from a
   passing build, test, PR, or an earlier single-PDF review.
4. Configure an owner-authorized PayPal REST application and its matching
   merchant account privately. Never put credentials in HTML, JavaScript, Git,
   PR comments or a chat message. The old PayPal.me/hosted-button payments do
   not automatically become orders in this application.
5. Exercise the full sandbox flow with seller and buyer sandbox accounts.
   Verify exact amount/currency/merchant checks, capture, same-browser delivery,
   retries after interruption and refused downloads after refunds. Test an
   unavailable PayPal API and missing file: both must fail closed.
6. Confirm the host supports the checkout's PHP/cURL/ZipArchive requirements, private
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
node tests/services/smart-store.mjs
python3 tests/services/contact-flow.py
php tests/commerce/backend.php
bash tests/trust-worthy-lab/run_all.sh
```

Use PHP 8.1 or newer with cURL, ZIP (`ZipArchive`), and mbstring for the full
runtime suite. The commerce suite includes synthetic ZIP attacks and requires
ZIP support; missing ZIP support is a failed gate, not a skipped success.
All PHP behavior checks must pass; a skipped test is not a pass. The smart-store
regression uses synthetic DOM events and cannot establish browser layout or
keyboard acceptance. Record local browser results separately from configured
PayPal sandbox and production-host acceptance. Passing local checks does not
enable checkout or satisfy the owner-controlled activation gates above.

## Private configuration contract

Use `store/checkout/config.example.php` as a template only. The public URL is
denied; never edit this repository copy to contain credentials. Create the
actual PHP-array configuration outside both the public document root and the
repository, with owner-only file access (`0600`). Point the server-side
`BOBSOME1_COMMERCE_CONFIG` environment variable at that absolute private file.
Verify environment-variable support with the hosting provider; do not expose
a configuration-dump or `phpinfo()` endpoint to debug it.

Required keys are `enabled` (boolean), `mode` (`sandbox` or `live`),
`client_id`, `client_secret`, `merchant_id`, `private_dir`, `asset_path`,
`asset_sha256`, `owner_approved_sha256`, and `owner_approved_at`. Use the
merchant ID, not an email address. `private_dir` must already exist with
owner-only directory access (`0700`). The private ZIP must be outside both the
public root and repository, have `0600` access, be 100 bytes–50 MiB, and match
`asset_sha256`. `owner_approved_sha256` must equal that exact archive digest.
`owner_approved_at` is the actual approval time in `YYYY-MM-DDTHH:MM:SSZ`
(UTC); invalid or future timestamps fail closed. These owner-controlled values
are an operational approval record, not cryptographic proof of who approved.
Keep them blank until Robert expressly approves the reviewed candidate.

The only delivery file remains `asset_path`; this is not a four-file public
URL or four separate payment system. `asset_sha256` covers the ZIP bytes,
including its internal `bundle-manifest.json`. No paid ZIP, customer records,
private credentials, private config, or owner approval belongs in Git or in
the public deployment manifest. An old PDF-only configuration is intentionally
incompatible and fails before any provider request.

### ZIP contract (schema 1)

`bundle-manifest.json` is UTF-8 JSON with exactly these top-level keys:

```json
{
  "schema": 1,
  "product": "project-unveiled-digital-edition",
  "release_id": "2026-10-03-owner-review-v1",
  "components": [
    {
      "id": "illustrated-ebook",
      "entrypoint": "illustrated-ebook/edition.pdf",
      "files": [
        {"path": "illustrated-ebook/edition.pdf", "bytes": 12345, "sha256": "<64 lowercase hexadecimal characters>"}
      ]
    }
  ]
}
```

This abbreviated example is deliberately incomplete and cannot pass checkout.
There must be exactly four component objects, with the unique IDs
`illustrated-ebook`, `timeline`, `deep-study-guide`, and `no-more-milk`.
Each component has exactly `id`, `entrypoint`, and `files`; each nonempty
`files` array contains objects with exactly `path`, `bytes`, and `sha256`.
Paths must begin with their component ID plus `/`. The three book/study
entrypoints must end in `.pdf`, be at least 100 bytes, and begin `%PDF-`.
The timeline entrypoint must be `timeline/index.html` and at least 100 bytes.
Additional PDF, EPUB, local timeline dependencies, and supporting notes can
be listed under their respective component. A PDF magic check establishes
format only; editorial completeness and readable illustrations require
Robert's review and rendered-file QA.

The archive may contain only the manifest and the files listed by it. The
manifest cannot exceed 64 KiB; `release_id` is 1–80 lowercase ASCII letters,
digits, dots, underscores or hyphens and starts with a letter or digit. At
most 512 archive entries and 100 MiB total expanded bytes are allowed, with
50 MiB per file. Every file must have a positive byte count. Build ZIPs with
file entries only; do not add explicit directory entries. Only stored or
deflated, unencrypted regular files with DOS or Unix ZIP origin metadata are
accepted. Names are restricted to
safe ASCII relative paths of at most 200 characters/eight segments; traversal,
absolute paths, backslashes, device names, empty segments, trailing dots,
symlinks, special files, file/ancestor collisions, and duplicate names
(including case collisions) are
rejected. File extensions are restricted to `pdf`, `epub`, `html`, `css`,
`js`, `json`, `jpg`, `jpeg`, `png`, `svg`, `webp`, `gif`, `woff2`, `txt`, and
`md`. The validator reads bounded streams without extracting anything, checks
each component file's exact byte count and digest, and rejects missing,
extra, malformed or altered contents. It does not run offline HTML/JavaScript.
Packaging must separately check local timeline links and owner-device use.

Successful validation happens during bootstrap before order creation, capture,
or download payment checks. Checkout always returns `application/zip` with
the fixed attachment name `Project-Unveiled-Complete-Study-Bundle.zip`.
Every new order stores the archive hash, manifest hash, and release ID. Existing
orders must match all three before provider calls or delivery. A legacy order
without that identity, or an order for an older release, requires owner
reconciliation; it must never silently receive substituted content or a new
payment demand. Preserve the original approved artifact for such support.
Before the payment check, the download is copied in bounded chunks into an
owner-only temporary snapshot inside private storage, hashed and rewound. The
snapshot is immediately unlinked and closed after delivery; it never has a
public URL. This prevents both pathname replacement and in-place uploads from
changing the delivered bytes after verification. The host must support
unlinking an open private temporary file and have room for a second ZIP copy
per simultaneous download (up to 50 MiB each).

The product and price are fixed server-side at $7.00 USD; no browser field or
configuration option can choose a different amount. The session cookie is
Secure, HttpOnly and SameSite=Lax. The private ledger is capped at 5,000
records and 10 MiB and uses an exclusive lock plus atomic replacement.
Unfinished order records expire after seven days, paid records after 180 days;
cleanup runs when storage is next accessed. Plan owner-controlled private
cleanup if the site is inactive. Download grants last 24 hours and allow at
most 10 attempts in the originating browser session.

Do not replace an approved bundle while unresolved orders exist. Disable new
purchases and retain the original ZIP and approval record; reconcile old
orders before changing releases. A corrected package needs a new full review.

Do not repoint a live checkout configuration at a different PayPal merchant
or sandbox while unresolved orders exist. Disable new purchases, reconcile
those orders in the original PayPal account and preserve private records
before making a reviewed account or environment change.

## Publishing and rollback

After review, all gates and deployment approval, publish through the existing
guarded cPanel process documented in `docs/bobsome1-contact-release.md`.
Use the exact approved commit and `deployment/public-files.txt`. Keep runtime
orders, session files, the paid bundle and credentials outside `public_html`
and outside Git. Do not deploy internal docs or tests.

To stop new automatic purchases, disable the private checkout configuration.
Retain private order records and reconcile any interrupted payment in PayPal
before asking a customer to try a new purchase. A site rollback cannot undo a
payment or retrieve an already downloaded file. Preserve manual order support.

For code rollback, prepare a reviewed patch against the deployed commit that
removes only the checkout changes while preserving the merged smart store,
homepage and service intake. Do not revert the main-integration merge as a
whole: that would discard unrelated main work. Redeploy only after approval,
through the guarded process. Register each removed public checkout file in
`deployment/retired-public-paths.txt`; do not delete a broad directory or restore
an old whole-site snapshot. Preserve the service-intake work and private leads.

## Known limits

This version is a same-browser digital-bundle checkout, not a customer account
system, email fulfillment service, subscription platform or automated service
salesperson. It does not introduce a webhook receiver. Remote payment checks
before download do not provide a background dispute/refund dashboard. Use
PayPal as the authoritative payment record and handle disputes, tax obligations,
refund policy and exceptional delivery requests through owner review.
