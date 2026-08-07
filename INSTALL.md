# Project Unveiled — Evidence Phase 2

Built: August 6, 2026

This package installs on top of the verified Phase 1 live site.

## Install in cPanel

1. Open Namecheap cPanel → File Manager.
2. Open the existing `public_html` directory.
3. Upload **only** `UPLOAD_TO_PUBLIC_HTML.zip`.
4. Extract it directly inside `public_html`.
5. Allow matching files to be overwritten.
6. Delete the uploaded ZIP after extraction.

Do not upload this outer delivery folder and do not create a second `public_html` folder.

## Test After Installation

1. Homepage: confirm the new **Open the Evidence Files** section appears.
2. Open `/questions/what-did-nicaea-decide.html`.
3. Open `/questions/who-decided-bible-canon.html`.
4. Open Chapters 3, 4, and 11 and scroll below the manuscript to the Evidence Desk.
5. Confirm the original chapter prose still reads normally.
6. Open `/book/research.html` and confirm the Public Question Files section.
7. Open `/book/updates.html` and confirm milestone 07.
8. Test the signup form with an email address you control.
9. Confirm `/owner/` still requests authentication.

## Roll Back

Upload `ROLLBACK_TO_PHASE_1.zip` inside `public_html`, extract it, and allow overwrite.

That restores every Phase 1 file that Phase 2 replaced. To remove the two new, now-orphaned question pages completely, delete:

- `public_html/questions/`
- `public_html/book/evidence-panels.css`

Leaving those orphaned files in place is harmless after rollback because the Phase 1 homepage, research page, chapters, and sitemap no longer link to them.

## Intentionally Untouched

- Chapter manuscript prose
- PHP signup, comment, review, and analytics endpoints
- Subscriber, comment, review, and analytics records
- Owner authentication and private owner tools
- PayPal configuration
- Databases and campaign tools
- Root `.htaccess` and hosting configuration
- Existing original PNG/JPEG artwork

