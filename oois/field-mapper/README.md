# OOIS Field Mapper

A working private field notebook under the existing PR #35. The web application builds and runs locally. It has not been deployed, provisioned in Firebase, signed for Android, or published to Google Play.

## What works

- Click/tap a map location, enter coordinates manually, or capture GPS.
- Save, edit, search, filter and delete records with notes, tags, categories and owner-assigned review status.
- Up to five compressed JPEG photo copies per record in IndexedDB. Originals stay separate. Saved records survive reloads; competing edits from another tab are rejected.
- Versioned JSON export/import. Imports merge new IDs and retain existing local records. Up to 1,000 records and an 18 MB notebook ensure full backups fit the 20 MB import limit.
- Offline application shell and record entry after the first online visit. External map tiles require connectivity. No tile prefetch/download feature.
- Nearby records within one mile, measured by straight-line distance. These are comparison candidates, not evidence of a historical relationship.
- A clearly labeled local documentation checklist based on note keywords. It does not analyze photographs or manufacture confidence scores.
- An optional Gemini image-analysis function using real inline JPEG inputs, private Secret Manager binding, owner allowlist, daily request limit, bounded input/output, and fail-closed errors. The UI asks before sending notes/photos; exact coordinate fields and nearby records are excluded.
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

## cPanel web deployment

The repository’s public deploy script deliberately excludes `oois/field-mapper/` source, dependencies, secrets scaffolding and native project. It must not upload these files as a live application. The root `.htaccess` blocks source access and permits the built app’s scoped security headers at `/oois/app/`.

After review and explicit deployment approval, place **the contents of `dist/` only** at `/home/bobsome1/public_html/oois/app/`, including the hidden `.htaccess`. The intended address is `https://bobsome1.com/oois/app/`; this is an intended deployment target, not a verified live URL. Keep the previous deployed build for rollback. Removing this folder disables the served build; an installed offline copy may continue on a device. Do not delete local/browser records or backups during rollback.

The downloaded web-build ZIP contains no live credentials and has cloud features disabled. It can be hosted as static files and used immediately for the device notebook. To enable cloud features, configure the project and rebuild.

## Cloud provisioning and release gates

1. Provision the intended Firebase project. Do not reuse an unrelated project's credentials silently. Configure Firestore, Storage, billing/budgets where required, and Email/Password Auth.
2. Create the owner account through Firebase Console. Through trusted administrator access, create `owners/<OWNER_UID>` in Firestore (an empty document is sufficient). Client writes to owners and AI-limit documents are denied. Do not enable anonymous access as a workaround.
3. Copy `.env.example` to the ignored `.env`, fill this project's public web configuration, and set `VITE_ENABLE_FIREBASE=true`. These client identifiers are not the Gemini secret. Never add a server key or service account to a `VITE_` variable.
4. From the app directory, deploy the reviewed Firestore and Storage rules. The cross-service Storage/Firestore owner check requires enabling the permissions requested by Firebase. Verify owner access and denial for a second signed-in account with the emulator and the provisioned project before launch.
5. In `functions/`, run `npm ci`. Set the secret with `firebase functions:secrets:set GEMINI_API_KEY`, using the authenticated CLI in this project, then deploy `analyzeSite`. The function binds the secret explicitly. Default model is `gemini-2.5-flash`; configure `GEMINI_MODEL` if the account requires a different supported image model.
6. The function caps requests at 10 per owner per UTC day and two instances. This is not a dollar spending cap. Configure cloud/API budgets and verify one authorized live image request. Provider output quality and production account access were not tested in this environment.
7. Configure Storage CORS for the actual web origin so authenticated browser photo downloads work. Rebuild and deploy the web bundle. Verify cloud login, save/import/delete (including photo deletion), stale revision rejection, exact runtime security headers and GPS permissions on the live domain.

Cloud imports preserve local versions with the same ID. They are not continuous cross-device sync; reconcile divergent copies deliberately using exported backups. Photos belonging to an older cloud revision are removed after a successful replacement; a cleanup failure can leave an orphan in the private account, so production verification includes storage cleanup.

## Android

```bash
npm run android:prepare
npm run android
```

`android:prepare` builds the same web app, creates the Capacitor Android project if necessary, syncs all plugins, adds foreground coarse/fine GPS permissions, and disables automatic OS backups for private records. The generated directory is ignored and reproducible; do not commit signing keys. Android JSON export uses the native share sheet. The target/compile SDK generated by Capacitor is 36.

Android project generation and sync succeeded locally. A native binary was **not** compiled: this environment lacks the Android SDK and has JDK 17 rather than the required JDK 21. A signed AAB, physical-device GPS/photo/share tests, internal testing, privacy/data-safety review, and Play Console submission remain release gates. No Play release was attempted.

## Validation and references

The production web build and five data/geography tests pass. The browser workflow suite passes. Runtime dependencies reported zero known vulnerabilities in `npm audit --omit=dev` during this build. This is not a security certification. Cloud/emulator and physical Android tests remain unverified.

- Leaflet distribution and attribution: https://leafletjs.com/download.html
- USGS topographic service: https://basemap.nationalmap.gov/arcgis/rest/services/USGSTopo/MapServer
- Firebase Storage rule conditions: https://firebase.google.com/docs/storage/security/rules-conditions
- Firebase secrets: https://firebase.google.com/docs/functions/config-env
- Gemini image input: https://ai.google.dev/gemini-api/docs/image-understanding

Earlier cPanel-only exploratory work was not added as a competing app; this PR remains the canonical OOIS implementation. No real findings, sensitive locations, credentials or production runtime records are bundled.
