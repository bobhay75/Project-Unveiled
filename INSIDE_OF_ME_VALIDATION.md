# Inside of Me — Private MVP v0.1.0

## Scope

Private founder build for `https://bobsome1.com/unveiltheinsideofme/`.

## Working MVP

- Voice/text life-story capture.
- Browser-local save by default; no story database in v0.1.0.
- Conservative timeline extraction with manual add/remove/reorder.
- Evidence-backed local reflection across belonging/rejection, safety/vigilance, control/unpredictability, early responsibility/self-reliance, shame/self-worth, grief/loss, meaning/faith/purpose, and persistence/rebuilding.
- User can dismiss interpretations that do not fit.
- Reflection language is explicitly non-diagnostic and probabilistic.
- Strengths and possible present-day costs are presented together.
- `Once Upon a Life` story arc and scene-by-scene storyboard.
- Truth status and director notes discourage fabricated details and exploitative depictions.
- JSON project export and readable story-brief export.
- Optional server-side Deep AI bridge; fails closed until environment variables are configured.

## Privacy/security

- Reuses the existing cPanel `/owner/` Basic Auth credential file.
- `noindex`, `nofollow`, `noarchive`.
- `Cache-Control: no-store`.
- Scoped microphone permission for voice capture.
- CSP limited to same-origin resources/connections.
- No API key is committed to GitHub.
- Deep AI environment variables: `INSIDE_OF_ME_OPENAI_API_KEY` and `INSIDE_OF_ME_OPENAI_MODEL`.

## Tests completed before publication

- PHP syntax checks for page and API endpoints: pass.
- JavaScript syntax check: pass on local source.
- Local reflection feature assertions: pass.
- Timeline correction controls: pass static assertion.
- Pattern dismissal: pass static assertion.
- Voice-recognition capability detection: pass static assertion.
- Export features: pass static assertion.
- API health endpoint: HTTP 200.
- Short-story API validation: HTTP 422.
- Deep AI missing-configuration fallback: HTTP 503 with safe local-reflection message.
- Security header/auth configuration assertions: pass.

## Production acceptance checks after cPanel deploy

1. `/unveiltheinsideofme/` must request credentials.
2. Successful login must load the app over HTTPS.
3. Page source/headers must remain noindex/noarchive/no-store.
4. Voice dictation must either work or clearly report browser unavailability.
5. Founder demo must create timeline and pattern cards.
6. Manual timeline reorder/remove/add must work on mobile.
7. Dismissing a pattern must remove it and persist locally.
8. Film step must create a story arc and storyboard.
9. JSON/text exports must download without sending the story to the server.
10. Deep AI button must fail safely until server variables are configured.
11. Existing home page, reader, chapters, and `/owner/` must remain unchanged.

## Known v0.1.0 boundaries

This is a story-reflection and storyboard MVP, not a clinical tool and not yet a full generative-video renderer. Video generation, account sync, encrypted cloud vault, family collaboration, billing, and production telemetry belong to later phases after founder testing proves retention and emotional value.
