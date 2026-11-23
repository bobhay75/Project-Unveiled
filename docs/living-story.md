# Living Story: cPanel integration

The confirmed `project-unveiled-living-story.zip` supplied a standalone Node prototype. This integration preserves its three investigation paths, scene illustrations, choices, custom questions, character description, loading state, and restart flow on the existing PHP/Apache host. No Node process or additional paid hosting is required in production.

## Release behavior

- `/living-story/` offers a six-scene, three-path prepared sample using existing timeline illustrations. Its current node is stored only in browser local storage.
- The homepage links directly to the sample. Existing manuscript files are unchanged.
- AI generation is disabled until private owner configuration is installed. A separate preview access code is required even after configuration. There is no unrestricted public paid generation endpoint.
- The server reads the selected chapter’s article and evidence desk from the deployed manuscript on every generation. Source links and the chapter fingerprint are supplied by the server, never invented by the model. They establish reading context, not claim-level verification.
- Authorized story state survives refresh and PHP worker restarts in private PHP sessions. Access expires after two hours; logout destroys the current session. Sessions are not accounts, cross-device history, or permanent saved books. Expired files are removed by PHP garbage collection, which can be delayed when inactive.
- Text requests use Responses structured output and `store:false`. Images use the image-generation HTTP API. The current illustration remains in the private session; earlier image payloads are discarded to bound storage. An image failure preserves the text scene.
- Character continuity uses a fixed description. It does not yet pass reference images across scenes and cannot guarantee an identical face.

## Private configuration after reviewed deployment

Create `/home/bobsome1/site-private/living-story` with mode `0700`, owned by the PHP account. Put `config.json` inside with mode `0600`. Do not upload it beneath `public_html` or commit it.

Configuration fields:

| Field | Value |
| --- | --- |
| `enabled` | Boolean `true` only when the owner is ready to test |
| `api_key` | Owner-supplied OpenAI project API key, set privately |
| `access_code_hash` | Output of PHP `password_hash` for a unique, long preview code |
| `text_model` | Optional; defaults to `gpt-4.1-mini` |
| `image_model` | Optional; defaults to `gpt-image-2.5-sunburst` |
| `daily_scenes` | Optional; defaults to 6, maximum 20 across all preview sessions |

Use a password manager to generate and retain the preview code. Enter the code only in the private preview form, never a URL. Do not reuse a cPanel or email password. A private override `LIVING_STORY_PRIVATE_DIR` supports isolated staging/test storage; production defaults to the exact directory above.

The PHP account needs PHP 8.1+, cURL, mbstring, and writable private session storage. Provider transport limits are 55 seconds for text and 90 for an image. Shared-host execution/proxy timeouts must support that combined request. If they do not, an asynchronous job design is needed before AI launch.

## Limits and privacy

There is a global daily scene-attempt cap, eight scenes per story, bounded input/output, low-quality images, and eight access attempts per hashed request address per 15-minute window. The budget reservation is locked and persisted before provider calls; failed requests still consume a reservation. Invalid budget files fail closed without being reset. These are request caps, **not a guaranteed dollar ceiling**. Set and monitor the OpenAI project’s billing controls separately before enabling the preview.

Only browser session identifiers are sent as necessary HttpOnly cookies. Story prompts are not placed in application logs. OpenAI still applies its own retention policies. The privacy page discloses this processing. The current implementation does not verify model claims, narrate audio, generate video, or automatically publish scenes.

## Validation and release

```bash
php tests/living-story/backend.php
python3 tests/living-story/http_flow.py
node --check living-story/app.js
python3 scripts/validate_site.py
python3 scripts/check-deployment-safety.py
python3 scripts/security-audit.py --history
bash tests/deployment/run.sh
```

The backend and HTTP suites use synthetic state only and make no live OpenAI calls. Browser acceptance covers sample branching, refresh/resume, keyboard navigation, phone-width layout, image failure, and the unavailable-AI fallback. Existing site regression checks still apply.

Follow `DEPLOYMENT.md` only after explicit deployment instruction. Require the draft PR’s site-safety checks to pass. After deployment, test the public sample first. Configure the private preview, sign in, generate one scene and illustration, choose one continuation, refresh, and sign out. Verify the deployed chapter fingerprints and inspect generated claims before public AI access is considered. No paid provider call or production deployment is part of the repository preparation.

Immediate AI rollback: privately set `enabled` to `false`. The prepared sample continues working. Full rollback: reviewed revert of this integration, adding every removed public `living-story/` file to `deployment/retired-public-paths.txt` before the guarded cPanel deployment. Never revert or replace unrelated runtime directories.

## API sources checked October 30, 2026

- [Responses structured output](https://developers.openai.com/api/docs/guides/structured-outputs)
- [Image generation](https://developers.openai.com/api/docs/guides/image-generation)
- [Deprecations](https://developers.openai.com/api/docs/deprecations)
- [GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini)

The source package’s `gpt-image-1` shut down October 23, 2026; the replacement follows the official deprecation recommendation. The retained text model is currently deprecated with shutdown scheduled January 14, 2027. Select and live-test its replacement before that date. No model access or output quality is proven without a credentialed acceptance test.
