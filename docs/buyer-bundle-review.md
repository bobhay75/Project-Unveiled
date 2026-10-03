# Project Unveiled buyer bundle — owner review candidate

Status: **OWNER REVIEW PENDING. Automatic checkout remains disabled.**

The October 2 product scope contains four components. This October 3 candidate
aligns draft PR #43 with that scope; it does not approve content, enable payments,
merge, or deploy. Price remains $7 USD; no pricing decision was introduced.

## Candidate delivered privately to Robert

Release ID: `2026-10-03-owner-review-v2`

Archive: `Project_Unveiled_Complete_Study_Bundle_Review_v2.zip`

- Size: 33,690,194 bytes.
- SHA-256: `d61bea6ee8c3a8e131ff26b2ed179df45d03c997987870a6459e44ec0faf847b`.
- Manifest SHA-256: `504376ada0f28e84ec24d214a40aca4d75f8f921de089b2251cc8eb059d2d5dc`.
- These identify a **review candidate**, not an approved sale asset.
- No paid ZIP, paid companion content, private configuration, or customer data is committed.

| Component | Included form | Provenance / review state |
|---|---|---|
| Illustrated ebook | 197-page PDF; existing reflowable EPUB also included | Preserves all 183 pages of recovered `Project_Unveiled_Complete_Ebook_Polished.pdf` and adds one edition note and 13 chapter art pages from the online reader. Extracted original page text/order verified unchanged. Original printed page numbers retained; PDF bookmarks target chapters. |
| Illustrated timeline | Offline interactive HTML, 10 local WebP images, 11-page PDF | Uses all ten original event records from `book/timeline.html` at `ea1a95e5d6acfa91ae1250a2117eb49fe12e987a`; local image paths adapted, no external scripts or background requests. |
| Deep Study Guide | 12-page PDF | Newly written eight-week companion, all thirteen chapters, Scripture, exercises, source checks, facilitator guidance and worked examples. |
| No More Milk | 8-page PDF | Newly written six-part advanced kit beginning “In the beginning…” and “The Way Before Christianity Became an Institution”; close reading, canon, Sabbath/Sunday, source criticism, fruit and evidence dossier. |

The two companions are new drafts, not recovered owner-approved manuscripts.
The October 2 chapter title/direction was recovered; its exact earlier wording
was not. Historical art is a generated reconstruction or symbolic illustration,
not documentary evidence. The guides distinguish Scripture, the author's
argument, historical sources, interpretation and disputed claims. They do not
silently rewrite the source manuscript. This is not a full historical fact-check
of every manuscript claim.

## Release contract

See `verified-book-checkout-release.md` for the schema and host contract. Delivery
still uses one private `asset_path` and `asset_sha256`, now for the complete ZIP.
It additionally requires explicit `owner_approved_sha256` and
`owner_approved_at`, all blank in the disabled example configuration. The archive
must contain exactly four manifest components and only enumerated regular files.
Every byte count and SHA-256 is checked; missing, altered, unsafe, unsupported or
unapproved content fails before provider access. New orders bind archive hash,
manifest hash and release ID; old orders need manual reconciliation. Downloads
stream an owner-private, unlinked verified snapshot to avoid in-place upload races.

PHP ZipArchive is now required in addition to existing extensions. Namecheap
availability remains a release gate. The application never extracts buyer ZIPs
on the server. Buyers extract the ZIP on their device and open the PDFs or
`timeline/index.html`; no local server, account or installation is required.

## Rebuild and verify (offline)

Keep paid component input outside Git/public_html. Expected entrypoints:

```
illustrated-ebook/Project_Unveiled_Illustrated_Ebook.pdf
timeline/index.html
deep-study-guide/Deep_Study_Guide.pdf
no-more-milk/No_More_Milk.pdf
```

```
python3 scripts/build-buyer-bundle.py \
  --source /PRIVATE/component-directory \
  --output /PRIVATE/Project_Unveiled_Complete_Study_Bundle_Review_v2.zip \
  --release-id 2026-10-03-owner-review-v2
sha256sum -c /PRIVATE/Project_Unveiled_Complete_Study_Bundle_Review_v2.sha256
python3 tests/commerce/bundle-build.py
php tests/commerce/backend.php
```

The builder refuses to overwrite an existing candidate and emits the archive,
manifest, SHA-256 sidecar and a review record with `enabled: false`, empty approval
fields and `owner_review_status: pending`. Equal input bytes produce equal ZIP
bytes. Editing or re-rendering a PDF may change its hash; every changed candidate
requires fresh review. Never copy the candidate hash into approval fields until
Robert explicitly approves that exact candidate.

## Validation and outstanding gates

- Full local Site safety workflow passed on this change set: security/history,
  deployment/rollback, PHP/JS syntax, site links, service/inbox controls,
  conversion/smart-store regression and Trust-Worthy release tests.
- Final checkout suite: **200 offline checks**. Builder suite: **15 tests**,
  including builder-output acceptance by the PHP validator. No PayPal calls.
- Actual 33.7 MB candidate passed the PHP archive validator using **synthetic
  approval in memory solely for local testing**. This did not write an approval
  record, enable checkout, or validate a merchant/host.
- PDFs rendered and visually inspected; original 183 manuscript pages verified
  text-preserved. Timeline passed desktop/mobile/no-JS checks with no network requests.
- Store browser acceptance passed at 320, 360 and 1440px: all four intents,
  eight walkthroughs, keyboard focus/Back/Next/Escape, service failure retention,
  disabled/manual checkout and signed-out owner denial. External requests were
  blocked; no live payment or host/TLS checks were performed.
- GitHub exact-head CI must pass for the eventual new commit; prior green run
  `37150239011` applies only to the starting head `ea1a95e`.

Still required: Robert's content/illustration approval of the exact bundle,
private merchant and host checks (including ZIP and snapshot disk space), separately
authorized sandbox create/approve/capture/download and interrupted/refunded flows,
configured-payment mobile/keyboard acceptance, and explicit deployment approval.
Keeping this PR draft and the example disabled does not change production.
Rollback and old-order handling are documented in the checkout release guide.
