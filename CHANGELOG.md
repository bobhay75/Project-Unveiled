# Evidence Phase 2 Changelog

## Trust-Worthy Release 16.1 — September 14, 2026

- Delegated unsupported Evidence Lab methods to its child Apache policy so live `405 Method Not Allowed` responses retain the required `Allow: GET, HEAD` header and route-specific Content Security Policy.
- Added a static regression check that prevents the public-root method rules from intercepting Evidence Lab denials before its child headers run.

## Trust-Worthy Release 16 — September 13, 2026

- Replaced the Daily Desk's reusable URL token with one-use, ten-minute fragment sign-in codes, short-lived secure sessions, same-origin checks, and CSRF protection; credentials no longer enter HTTP URLs, referrers, redirects, or cron logs.
- Bound every Daily Desk source to provider-returned web evidence, required complete bounded provider responses, added atomic hourly/daily/concurrent cost reservations, and made publication digest-bound, human-reviewed, private-question-proof, and recoverable after partial writes.
- Rebuilt public question and challenge intake around bounded scalar requests, real-case validation, HMAC rate limits, atomic fail-closed queues, owner-only secrets, record/file ceilings, and 180-day retention including legacy records.
- Made free Truth Trials reserve quota atomically, charge provider attempts safely, reject malformed or unverifiable output, append only provider-bound source trails, send `store: false` so responses are not kept as retrievable provider responses, and remove raw questions from operational logs.
- Reduced the public health response to a GET/HEAD-only ready/unavailable contract; added canonical-host, read-only-method, include-only-module, CSP, referrer, framing, and implementation-header protections.
- Corrected protected-app password-file paths for the active cPanel account, pinned deployment collation for deterministic manifest checks, and advanced the shared analytics cache token after Store tracking changed.
- Hardened first-party analytics with strict same-origin JSON intake, actual-byte and Apache body caps, a complete event allowlist, atomic per-address abuse controls, owner-only bounded retention, privacy-safe referrers/search/targets, locked atomic migration of legacy private records, content-bound metadata, bounded dashboard reads, formula-safe CSV, rate-limited login, and CSRF-protected logout.
- Replaced broad cPanel copying with a reproducible exact public-file allowlist and stale-file retirement registry, preserving unrelated runtime/application data while preventing repository internals, credentials, archives, and tests from entering `public_html`.
- Added reachable-history secret scanning, fail-closed deployment tests, a pinned read-only GitHub Actions workflow, and weekly GitHub Actions dependency updates.
- Enforced the canonical `https://bobsome1.com` origin inside the Evidence Lab child rewrite context and added live HTTP, `www`, path, and query-preservation checks.
- Replaced over-constrained archive queries with bounded alternative term groups, removed weak connective terms, normalized Unicode, and added topic-anchor screening to reduce loosely related discovery leads without deleting lower-overlap audit records.
- Preserved keyboard focus across Source Sweep start, progress, completion, and result rerenders; field errors now identify and focus the exact invalid control.
- Exposed exact provider endpoints and UTC request/completion times in visible and printed search receipts, and moved commercial navigation from hosted previews to owned Bobsome1 routes.
- Advanced the exact-byte manifest and local observer to release 16 / observer v6, retaining release 15 commit `aea90c55a31e7d158f1d092e2509fcc3b9147a34` as provenance and adding a guarded, Evidence-Lab-only rollback to the verified lab bytes in pre-release main commit `9c4faa71e9c4955c1e597b2b3f9c8a861cac3bf1`.

## Revenue Funnel Foundation — September 13, 2026

- Added `/store/` as the clear revenue hub for the $7 Project Unveiled digital edition, the exact $250 Visibility Starter checkout, qualified service inquiries, and partner/sponsor proposals.
- Added owned conversion events for store, checkout, lead and partner actions while preserving DNT/GPC behavior.
- Added a capped $35 Meta campaign brief with a required checkout and analytics preflight.
- Added a ranked partner/sponsor pipeline with value-first outreach and disclosure guardrails.
- Added Store discovery to the homepage, services page and shared reader navigation.

## Trust-Worthy Release 15 — September 13, 2026

- Added a deterministic claim-term-overlap display screen so Source Sweep presents higher-overlap metadata first while preserving lower-overlap families, every provider variant, exact query counts, and screening details in an audit drawer and canonical receipt.
- Applied screening only after DOI and canonical-URL family merging, preventing a lower-overlap provider variant from erasing the provenance of an identity-linked higher-overlap record.
- Corrected the public hosting disclosure to Namecheap shared hosting and removed the stale ChatGPT Sites delivery claim.
- Advanced the exact-byte release manifest and local observer to release 15 / observer v5, with verified release 14 commit `10c0b4b140264808aebdf0d18cdd0b3632c358f2` as the rollback point.
- Kept the dependency-free release gate compatible with the Python 3.6 and GNU grep tools supplied by the production cPanel shell.

## Trust-Worthy Canonical Evidence Lab — September 12, 2026

- Added the verified Trust-Worthy Evidence Lab at `/truth/lab/` without replacing any published Truth Trial, PHP intake path, private owner route, or payment boundary.
- Moved public sharing, PWA scope, security contact, sitemap, and release provenance to the canonical `https://bobsome1.com/truth/lab/` address.
- Added an exact-byte release manifest, browser-local observer v4, true-404 and deny-by-default method contract, Content Security Policy, and a tested rollback pointer to release 13 and Project-Unveiled commit `7a4345bc5b4268ff4305494cf4019f8bd783029a`.
- Added the full research-engine, hostile-input, privacy, storage-failure, accessibility-structure, and provenance gate to repository CI.
- Blocked internal Markdown, Python, validation, test, and deployment materials from public download and excluded them from future cPanel deployments.

## Bobsome1 Media + IT Landing Page — September 6, 2026

- Added `/services/` as a standalone services and selected-work page for venues, churches, contractors, and local businesses.
- Added a venue-focused engagement path suitable for opportunity-audit outreach without publishing prospect-specific findings.
- Linked only to inspectable live work or clearly labeled public prototypes; no unverifiable testimonials or performance claims were added.
- Added the new route to the homepage navigation, footer, README, and XML sitemap.
- Repaired a pre-existing missing parenthesis in the Trust-Worthy source URL sanitizer so the repository safety workflow can complete.

## New Search Entry Pages

- `questions/what-did-nicaea-decide.html`
  - Answers what the 325 council did and did not decide.
  - Separates documented facts, Project interpretation, and open questions.
  - Corrects the unsupported claim that Nicaea created the Bible.
  - Includes Article and FAQ structured data, primary-source doors, book CTAs, analytics, and reader signup.

- `questions/who-decided-bible-canon.html`
  - Explains canon formation as a process rather than a single vote.
  - Covers circulation, disputed books, Eusebius, Athanasius, African council records, differing traditions, and Nag Hammadi cautions.
  - Includes Article and FAQ structured data, source doors, book CTAs, analytics, and reader signup.

## Claim-Level Evidence Desks

- Chapter 3: Roman toleration, Constantine and Nicaea, the 380 legal shift, interpretation, and open questions.
- Chapter 4: Nicaea versus canon formation, Eusebius, Athanasius, Arius’s writings, interpretation, and open questions.
- Chapter 11: Nag Hammadi manuscript facts, the burial hypothesis, text-specific cautions, and Project method.
- Shared responsive presentation in `book/evidence-panels.css`.
- Original manuscript prose preserved exactly.

## Discovery and Retention

- Added an Evidence Files section to the homepage.
- Added Public Question Files to the Research & Corrections page.
- Added both pages to the XML sitemap, bringing the public sitemap to 29 URLs.
- Updated the August progress page with milestone 07 and Phase 2 completion language.
- Reused the existing privacy-first signup and analytics systems without changing their endpoints.
# Production Hardening — August 6, 2026

- Enforced the canonical HTTPS non-`www` origin.
- Added site-wide browser security and privacy headers.
- Blocked internal delivery notes and server logs from public download.
- Hardened signup host/origin validation and removed reliance on `mbstring`.
- Fixed unsubscribe temporary-file collisions and failed-write handling.
- Reset signup timing correctly after a successful submission.
- Added repository safety rules, documentation, and verification guidance.
