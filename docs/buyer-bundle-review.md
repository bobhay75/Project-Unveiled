# Project Unveiled buyer bundle — owner-approved content candidate

Status: **CONTENT APPROVED FOR THE EXACT V3 ARCHIVE. Automatic checkout remains disabled.**

Robert approved the exact corrected v3 content candidate on October 4, 2026.
This approval covers content only. It does not enable payments, authorize merchant
configuration, merge, or production deployment. Price remains $7 USD; no pricing
decision was introduced.

## Exact owner-approved candidate

Release ID: `2026-10-04-owner-review-v3`

Archive: `Project_Unveiled_Complete_Study_Bundle_Review_v3.zip`

- Size: 39,706,890 bytes.
- SHA-256: `ce4dda8bbe1cb8874229737ba5293e62f6c0346cc116b759b9832a4fca51a9a6`.
- Manifest SHA-256: `3945ae9fd64af475ddee214e960e847e026201c1c1cee666463eb2ef3336dcd3`.
- Owner approval applies only to these exact archive bytes/hash.
- Any changed archive requires a new complete review and approval.
- No paid ZIP, private merchant configuration, credentials, or customer data is committed to Git.

| Component | Included form | Approved v3 scope |
|---|---|---|
| Illustrated ebook | 197-page PDF; revised reflowable EPUB | 183 manuscript pages plus edition note and 13 illustrations. |
| Illustrated timeline | Offline ten-event HTML; 11-page printable illustrated overview | 10 events, 40 key points and 10 reflections; printable PDF is explicitly an overview. |
| Deep Study Guide | 11-page PDF | All eight weeks and all thirteen manuscript chapters retained; previously orphaned introductory page eliminated. |
| No More Milk | 8-page PDF | Concise six-session theology/evidence workshop with labs, worked answers and final assessment. |

The corrected v3 historical/context changes include the 311 Galerius toleration
before the 313 policy, the instructional-reading category from Athanasius Festal
Letter 39 section 7, explicit labels for the author's imperial-capture/approved-
shelf interpretation, exact primary-text loci, and identification of modernized
Acts wording as paraphrase. Artwork scarcity/collectible labels were removed and
symbolic or imagined scenes were labeled as such. Buyer-facing internal review
wording was removed and START-HERE instructions were added.

Only 11 of the 183 manuscript pages were changed for the requested corrections.
The remaining 172 pages matched the original in extracted text and rendered
pixels. Page order, printed numbering, thirteen chapters, five appendices and the
author's theological argument remain.

## Verification of the approved bytes

Full component generation was repeated with identical bytes. Two independently
assembled ZIPs matched exactly. The final archive passed CRC, all 19 payload file
sizes and hashes, safe-path/regular-file checks, and complete manifest coverage.
Expanded size is 48,276,054 bytes and remains within the existing delivery limits.

Changed manuscript pages, all fourteen edition/illustration pages, all nineteen
companion pages and all eleven timeline pages were visually checked. No clipping,
missing glyphs, missing sections or broken file paths were found. Unchanged
manuscript pages passed automated text/raster comparison.

EPUB ZIP integrity, 31 XML documents, spine/resource references and internal
fragments passed. HTML local links and assets resolve; timeline JavaScript syntax
passed. The ten events, forty key points, ten reflections and ten timeline
illustrations remain. Core reading files have no remote asset dependency; external
historical-source links require internet access.

## Release contract

See `verified-book-checkout-release.md` for the schema and host contract. Delivery
uses one private `asset_path` and `asset_sha256` for the complete ZIP. Runtime
activation additionally requires `owner_approved_sha256` to equal the exact v3
archive digest and `owner_approved_at` to record the actual UTC approval time in
the private configuration.

The checked-in `store/checkout/config.example.php` intentionally remains disabled
with blank approval fields. Approval evidence belongs in the owner-controlled
private operational configuration, not in Git. Do not autofill the public template.

The archive must contain exactly four manifest components and only enumerated
regular files. Every byte count and SHA-256 is checked; missing, altered, unsafe,
unsupported or unapproved content fails before provider access. New orders bind
archive hash, manifest hash and release ID; old orders require manual
reconciliation. Downloads stream an owner-private, unlinked verified snapshot.

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
  --output /PRIVATE/Project_Unveiled_Complete_Study_Bundle_Review_v3.zip \
  --release-id 2026-10-04-owner-review-v3
sha256sum -c /PRIVATE/Project_Unveiled_Complete_Study_Bundle_Review_v3.sha256
python3 tests/commerce/bundle-build.py
php tests/commerce/backend.php
```

The builder refuses to overwrite an existing candidate and emits the archive,
manifest, SHA-256 sidecar and review record. Equal input bytes produce equal ZIP
bytes. Editing or re-rendering any component changes the archive hash and requires
fresh owner review.

## Remaining activation gates

Content approval for the exact v3 archive is complete. The following remain
separate and are **not** authorized by content approval:

1. Exact-head GitHub Site safety / repository checks after this documentation update.
2. Private PayPal merchant configuration and matching merchant identity.
3. Namecheap host verification for PHP 8.1+, cURL, ZipArchive, private writable
   storage, secure sessions, Apache protections, and snapshot disk capacity.
4. Separately authorized PayPal sandbox create/approve/capture/download tests,
   including interrupted/cancelled/refunded and provider/file failure paths.
5. Configured-payment mobile/keyboard acceptance on the actual target devices/browser.
6. Explicit production deployment approval.

Native EPUB/Kindle rendering, real Chromebook/Android behavior, live browser
interaction and PHP checkout execution were not recertified by the private v3
editorial environment. Keep PR #43 draft and checkout disabled until the remaining
activation gates pass.
