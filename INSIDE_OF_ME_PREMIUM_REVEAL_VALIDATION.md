# Inside of Me v0.3 — Premium Reveal validation

## Product rule implemented

The founder beta no longer generates imagery for every timeline event. The full storyboard remains visible for truth and chronology, but a Director layer chooses a maximum of three reveal moments for premium pre-purchase imagery.

Flow:

1. Tell story.
2. Correct timeline.
3. Review possible patterns.
4. Build full text storyboard.
5. Director selects three scene-worthy moments.
6. User reviews emotional center and visual direction.
7. User explicitly approves the story beat before an image request is made.
8. Generate one high-quality vertical cinematic keyframe.
9. User approves or regenerates the keyframe.
10. After all reveal frames are approved, play a short in-browser cinematic trailer.
11. Mark the project ready for the paid 4-minute `Once Upon a Life` production workflow.

## Credit discipline

- No image generation occurs during story entry, timeline extraction, local reflection, or Director scoring fallback.
- Deep Director falls back to a browser-local scoring system when the server AI model is not configured or cannot respond.
- Premium imagery defaults to `gpt-image-2`, vertical `1024x1536`, and `high` quality.
- Regeneration requires explicit confirmation and consumes another image request.
- Only selected reveal moments are eligible for premium image generation.

## Truth / safety rules

- Director may select only existing timeline event IDs.
- Director is instructed not to invent, merge, intensify, or rewrite events.
- Image prompt says unknown factual details should remain visually ambiguous instead of fabricated.
- Unknown real-person appearance is not presented as an exact likeness without an approved reference image.
- Abuse, violence, death, incarceration, addiction, and child-distress imagery is directed toward non-graphic perspective, atmosphere, aftermath, separation, consequence, or resilience.
- Reflection remains non-diagnostic.
- Story and approved reveal images remain browser-local by default.
- UI discloses that requested Deep Reflection, Deep Director, and premium generation send only relevant text for that request through the configured server-side AI provider.

## Local technical validation completed

- `premium-reveal.js` source version passed `node --check` before publication.
- `api/director.php` passed PHP syntax validation under PHP 8.4.
- `api/generate-image.php` passed PHP syntax validation under PHP 8.4.
- Deep Director with a valid timeline and no configured API key returns HTTP `503` with a JSON fallback message rather than crashing.
- Premium image endpoint rejects underspecified scenes with HTTP `422`.
- Premium image endpoint with a valid scene and no configured API key returns HTTP `503` JSON rather than exposing an error page.
- Old per-scene visual generation JavaScript and CSS were removed from the feature branch.
- The active index loads `premium-reveal.css`, `premium-reveal.js`, and the timeline editing patch.

## Production acceptance checks after cPanel deploy

1. Visit `/unveiltheinsideofme/` and authenticate.
2. Confirm footer shows `v0.3.0`.
3. Enter or load a story and build at least three timeline events.
4. Correct one event using the Timeline Edit control and confirm the correction survives reload.
5. Open Reveal and confirm the full text storyboard appears.
6. Confirm each text scene shows a `Scene worthiness` badge.
7. Press `Choose my 3 reveal moments` and verify exactly three reveal cards for a story with at least three events.
8. Confirm no image is generated merely by selecting scenes.
9. Confirm each reveal card allows emotional-center and visual-direction edits.
10. Press `Approve moment & reveal it` and verify an unconfigured server fails gracefully, or a configured server returns one high-quality vertical image.
11. Approve each generated frame and verify approval survives reload on the same device/browser.
12. After all reveal frames are approved, confirm `Play my trailer` unlocks.
13. Optionally choose a local audio track and verify it plays only in the browser preview.
14. Confirm `Prepare my 4-minute film` records film-ready status and displays the production contract summary.
15. Confirm the old per-timeline-scene image controls do not appear.
16. Confirm browser/dev tools show no story database write requests.

## Server configuration required for real premium generation

These values must be available to PHP as server environment variables:

- `INSIDE_OF_ME_OPENAI_API_KEY`
- `INSIDE_OF_ME_OPENAI_MODEL` for Deep Reflection / Deep Director
- optional `INSIDE_OF_ME_IMAGE_MODEL` (defaults to `gpt-image-2`)
- optional `INSIDE_OF_ME_IMAGE_QUALITY` (`high` by default; `medium` also permitted)

Never put the API key in JavaScript, HTML, GitHub, or a public cPanel file.

## Known founder-beta limits

- Premium images are stored in IndexedDB on the current browser/device, not synced across devices yet.
- The trailer is an in-browser cinematic preview using approved stills, movement, text overlays, and optional local audio; it is not yet an exported MP4.
- The 4-minute film button records production readiness; payment, queued video generation, final render, and delivery are the next production milestone.
- Real face/age continuity is not solved until approved reference-photo ingestion and identity-continuity generation are added.
