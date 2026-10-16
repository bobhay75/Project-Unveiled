# OOIS Field Mapper

A working private field notebook under the existing PR #35. The web application builds and runs locally. The selected release route is a dedicated OOIS Firebase project and Google Play internal testing first. It has not been deployed, provisioned in Firebase, production-signed, uploaded to Play, merged, or published.

## What works

- Click/tap a map location, enter coordinates manually, or capture GPS.
- Save, edit, search, filter and delete records with notes, tags, categories and owner-assigned review status.
- Up to five compressed JPEG photo copies per record in IndexedDB. Originals stay separate. Saved records survive reloads; competing edits from another tab are rejected.
- Versioned JSON export/import. Imports merge new IDs and retain existing local records. Up to 1,000 records and an 18 MB notebook ensure full backups fit the 20 MB import limit.
- Offline application shell and record entry after the first online visit. External map tiles require connectivity. No tile prefetch/download feature.
- Nearby records within one mile, measured by straight-line distance. These are comparison candidates, not evidence of a historical relationship.
- A clearly labeled local documentation checklist based on note keywords. It does not analyze photographs or manufacture confidence scores.
- An optional Gemini image-analysis function using the maintained Google GenAI SDK, real inline JPEG inputs, private Secret Manager binding, owner allowlist, daily request limit, schema-constrained output, bounded input/output, and fail-closed errors. The UI asks before sending notes/photos; exact coordinate fields and nearby records are excluded.
- Explicit private cloud save, import and delete actions. No automatic uploads. Firebase uses a provisioned owner email/password account; anonymous sign-in is not used. Photos are read through authenticated storage access rather than public download-token URLs. Cloud writes reject stale revisions.
- Installable web manifest, dark field UI, native dialogs, keyboard coordinate entry, mobile layout and native Android GPS/share integration.

Anyone using the same browser profile can read its notebook. Device records are not password-encrypted. Cloud sign-out does not erase them. Preserve exported backups; browser storage can be cleared. Read `public/privacy-policy.html`.

## Build and test

Requires Node 22.12+ (the CI uses Node 22). No production Node server is required for the built web app.

```bash
cd oois/field-mapper
npm ci
npm test
npm run build
npm run preview
```

For the browser workflow suite, install Playwright in the test environment and Chromium, then run `node tests/browser.cjs` from this directory. `ATLAS_TEST_CHROMIUM` can select an installed Chromium binary. The test creates an isolated local server/browser profile, uses synthetic records and blocks external tiles. It tests map entry, JPEG conversion, persistence, editing, text injection, GPS, search, backup restore, offline reload/save, deletion, unavailable-cloud messaging, mobile layout and dialog dismissal.

For cloud access rules, use Java 21 and Node 22+, run `npm ci --ignore-scripts` then `npm test` in `tests/cloud/`. The isolated test dependencies are locked separately. This starts Firestore/Storage emulators for `demo-oois-tests` only, without a paid project, credentials, production data or real AI calls. It checks owner CRUD, account isolation, strict private-record schema, server-enforced revision increments, owner/quota administration restrictions, private photo access, upload bounds, overwrite denial and access revocation. The pull-request workflow runs the same suite.

## cPanel web deployment

The repository’s public deploy script deliberately excludes `oois/field-mapper/` source, dependencies, secrets scaffolding and native project. It must not upload these files as a live application. The root `.htaccess` blocks source access and permits the built app’s scoped security headers at `/oois/app/`.

After review and explicit deployment approval, place **the contents of `dist/` only** at `/home/bobsome1/public_html/oois/app/`, including the hidden `.htaccess`. The intended address is `https://bobsome1.com/oois/app/`; this is an intended deployment target, not a verified live URL. Keep the previous deployed build for rollback. Removing this folder disables the served build; an installed offline copy may continue on a device. Do not delete local/browser records or backups during rollback.

The downloaded web-build ZIP contains no live credentials and has cloud features disabled. It can be hosted as static files and used immediately for the device notebook. To enable cloud features, configure the project and rebuild.

## Cloud provisioning and release gates

1. Provision the dedicated **OOIS Field Mapper** Firebase project. Do not reuse another project's credentials. The suggested project ID is `oois-field-mapper-bobsome1` if available. The owner must confirm the actual project ID and irreversible Firestore/Storage locations before creating those resources; `us-central1` is the documented recommendation for this Missouri-centered notebook. Configure Email/Password Auth and billing/budget alerts. Cloud Storage for Firebase now requires the Blaze pay-as-you-go plan, although eligible usage can remain within no-cost allowances; alerts do not impose a hard cap.
2. Create the primary owner account through Firebase Console for `bobsome1@bobsome1.com`. Through trusted administrator access, create `owners/<OWNER_UID>` in Firestore (an empty document is sufficient). Create a separate non-owner account for denial testing and, before Play review, an isolated authorized reviewer account. Client writes to owners and AI-limit documents are denied. Do not enable anonymous access as a workaround. Passwords stay out of source and chat.
3. Copy `.env.example` to the ignored `.env`, fill this project's public web configuration, and set `VITE_ENABLE_FIREBASE=true`. These client identifiers are not the Gemini secret. Never add a server key or service account to a `VITE_` variable.
4. From the app directory, deploy the reviewed Firestore and Storage rules, then apply `storage.cors.json` to the actual bucket. The cross-service Storage/Firestore owner check requires enabling the permissions requested by Firebase. Verify owner access and denial for a second signed-in account with the emulator and the provisioned project before launch.
5. In `functions/`, run `npm ci`. Set the secret with `firebase functions:secrets:set GEMINI_API_KEY`, using the authenticated CLI in this project, then deploy `analyzeSite`. The function binds the secret explicitly. Default model is `gemini-2.5-flash`; configure `GEMINI_MODEL` if the account requires a different supported image model.
6. The function caps requests at 10 per owner per UTC day and two instances. This is not a dollar spending cap. Configure cloud/API budgets and verify one authorized live image request. Provider output quality and production account access were not tested in this environment.
7. Run `npm run release:check`, followed by `npm run release:verify-live` with temporary owner and denial-test credentials, `OOIS_VERIFY_GEMINI=true`, and `OOIS_VERIFY_DENIAL=true`. This creates only synthetic test data, verifies both approved CORS origins, makes one authorized image request and removes its test record/photo. Preserve the sanitized receipt. Rebuild and deploy the web bundle only after approval, then verify cloud login, save/import/delete, stale revision rejection, exact runtime security headers and GPS permissions on the live domain.

Cloud imports preserve local versions with the same ID. They are not continuous cross-device sync; reconcile divergent copies deliberately using exported backups. Photos belonging to an older cloud revision are removed after a successful replacement; a cleanup failure can leave an orphan in the private account, so production verification includes storage cleanup.

## Android

```bash
npm run android:prepare
npm run android
```

`android:prepare` builds the same web app, creates the Capacitor Android project if necessary, syncs all plugins, adds foreground coarse/fine GPS permissions, and disables automatic OS backups for private records. The generated directory is ignored and reproducible; do not commit signing keys. Android JSON export uses the native share sheet. The target/compile SDK generated by Capacitor is 36.

The pull-request workflow uses JDK 21 and the Android SDK to compile a separate `com.bobsome1.oois.preview` debug APK, runs Android lint, verifies that the production identity compiles as an unsigned release AAB, and saves an `OOIS-Android-Preview` artifact for seven days. It bundles device-only settings and a temporary debug signature, never a production signing key. See `ANDROID-TESTING.md` for installation, backup and device checks. `node scripts/prepare-android.mjs --preview` selects this identity after building; running the normal preparation command restores the production identity.

After the dedicated Firebase project and owner-controlled upload key are configured, the manual **OOIS Signed Internal Bundle** workflow validates the exact project values, injects version metadata, builds and verifies a signed production AAB, and retains it as a private workflow artifact for 14 days. It does not upload to Play. Required GitHub variables, encrypted secrets, Play declarations and approval gates are listed in `PLAY-INTERNAL-TEST.md`.

A successful CI build is not physical-device verification. A signed release AAB, GPS/photo/share tests on the Galaxy A17, internal testing, privacy/data-safety review, and Play Console submission remain release gates. No Play app, upload or release was attempted.

## Validation and references

The production web build and five data/geography tests pass. The browser workflow suite passes. Runtime dependencies reported zero known vulnerabilities in `npm audit --omit=dev` during the web build. This is not a security certification. Consult the current PR checks for emulator/native build results; live cloud/AI and physical Android behavior still require verification.

- Leaflet distribution and attribution: https://leafletjs.com/download.html
- USGS topographic service: https://basemap.nationalmap.gov/arcgis/rest/services/USGSTopo/MapServer
- Firebase Storage rule conditions: https://firebase.google.com/docs/storage/security/rules-conditions
- Firebase secrets: https://firebase.google.com/docs/functions/config-env
- Gemini image input: https://ai.google.dev/gemini-api/docs/image-understanding
- Firebase emulator rule testing: https://firebase.google.com/docs/rules/unit-tests
- Demo projects and emulator isolation: https://firebase.google.com/docs/emulator-suite/connect_firestore
- Capacitor 8 Android build requirements: https://capacitorjs.com/docs/updating/8-0

Earlier cPanel-only exploratory work was not added as a competing app; this PR remains the canonical OOIS implementation. No real findings, sensitive locations, credentials or production runtime records are bundled.
