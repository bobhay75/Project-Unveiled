# Validation Results

## Phase 1 Live Verification

- 43 deployed Phase 1 files checked.
- 43 returned HTTP 200.
- 43 matched the tested upload package byte-for-byte.
- Homepage, reader doors, Chapter 1 WebP, community controls, timeline deep link, timeline image labels, unobstructed Next Event control, and updates page rendered correctly.
- `/owner/` returned HTTP 401, confirming the authentication boundary remains active.
- Signup endpoint returned HTTP 405 to a read-only GET, confirming it remains POST-only.
- Community endpoint returned HTTP 200.

## Phase 2 Staged Validation

- 29 public sitemap pages found.
- 0 missing public pages.
- 0 missing internal file references.
- 0 duplicate-ID pages.
- 0 pages with invalid H1 counts.
- 0 invalid JSON-LD blocks.
- 0 inline JavaScript syntax failures.
- CSS opening and closing braces balanced.
- Both new pages have one canonical URL and a search-length meta description.
- New article body lengths: approximately 1,166 and 1,293 words before the signup block.
- Chapters 3, 4, and 11 manuscript article blocks match Phase 1 exactly.

The first full rendered visual check of the two new pages should be performed immediately after the upload, because the cloud browser cannot load workspace-local files.

