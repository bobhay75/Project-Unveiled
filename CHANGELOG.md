# Evidence Phase 2 Changelog

## 2026-08-22 — Trust-Worthy AI Phase 1

- Added the Trust-Worthy AI v0.1 investigation protocol and machine-readable Truth Case schema.
- Added `/trust-worthy/` landing page, public method page, JSON-backed Truth Trial renderer, and structured challenge intake.
- Added `TW-CASE-000001-v1`, testing whether Jesus taught a specific prescribed salvation prayer or formula.
- Added immutable case-version presentation and a challenge workflow designed to keep pending submissions private and out of the published record until independently reviewed.
- No production deployment included.

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
