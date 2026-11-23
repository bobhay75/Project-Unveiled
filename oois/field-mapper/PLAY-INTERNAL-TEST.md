# OOIS Play internal-test release

Decision recorded September 19, 2026: use a dedicated Firebase project, keep `bobsome1@bobsome1.com` as the private primary owner, and start Android distribution through Google Play internal testing. Do not merge, deploy Firebase resources, create an upload key, or upload to Play without the owner's explicit approval at that gate.

## Completed in source

- Production package ID is fixed at `com.bobsome1.oois`; the debug preview remains isolated as `com.bobsome1.oois.preview`.
- Target and compile SDK are API 36, and the minimum SDK is 24.
- The maintained `@google/genai` SDK replaces the deprecated Gemini JavaScript library.
- Firestore enforces the private schema and sequential cloud revisions; Storage rejects overwrites, non-JPEG content, oversized files and paths outside the signed-in owner's namespace.
- Release configuration and live owner/denial/CORS/Gemini verification scripts fail closed.
- The manual **OOIS Signed Internal Bundle** workflow builds a signed AAB but never uploads it to Google Play.
- Unused Camera, App and Network Capacitor plugins were removed. The app requests foreground coarse/fine location only when the user taps **Capture GPS**. Photo selection uses the Android system picker.

## Owner-controlled Firebase setup

1. Create a dedicated Firebase project named **OOIS Field Mapper**. Suggested immutable project ID: `oois-field-mapper-bobsome1` if it is available. Record the actual ID; do not silently reuse another project.
2. Before creating Firestore or Storage, have the owner confirm their irreversible locations. Recommended: `us-central1` for this Missouri-centered notebook, to colocate the private data and use an eligible Cloud Storage no-cost region. Do not click the final resource-creation control until this choice is approved; locations cannot be changed later.
3. Cloud Storage for Firebase and deployed Cloud Functions require the pay-as-you-go Blaze plan. Link the owner's billing account and create low budget alerts before provisioning. A budget alert is not a hard spending cap. The function is separately limited to ten requests per owner per UTC day and two instances.
4. Register one Firebase **Web app** for the bundled JavaScript application. Copy only its public web configuration into the repository/environment variables; never put the Gemini key, service-account JSON or signing material in a `VITE_` variable.
5. Enable Email/Password Authentication. Create the primary account `bobsome1@bobsome1.com` with a unique password entered only in Firebase Console. Create `owners/<PRIMARY_UID>` as an empty Firestore document through trusted administrator access.
6. Create a separate non-owner denial-test account. Do not create an `owners/<UID>` document for it. Before Play review, create a separate authorized reviewer account and empty owner document; per-UID rules keep its synthetic records isolated from the primary owner.
7. Copy `.firebaserc.example` to the ignored `.firebaserc`, replace the placeholder with the dedicated project ID, authenticate Firebase CLI, and deploy only after explicit approval:

   ```bash
   firebase deploy --only firestore:rules,storage
   gsutil cors set storage.cors.json gs://PROJECT_ID.firebasestorage.app
   firebase functions:secrets:set GEMINI_API_KEY
   firebase deploy --only functions:analyzeSite
   ```

8. Put the public Firebase Web App values in an ignored `.env`, set `VITE_ENABLE_FIREBASE=true`, and run `npm run release:check`.
9. Put owner and denial-test passwords only in temporary environment variables, set `OOIS_VERIFY_GEMINI=true` and `OOIS_VERIFY_DENIAL=true`, then run `npm run release:verify-live`. The test uses one synthetic white JPEG, verifies owner access and non-owner denial, performs one real Gemini request, writes a sanitized receipt, and removes its temporary Firestore record and photo.

## Owner-controlled signing setup

Use Google Play App Signing and keep a separate upload key. Generate it once on a trusted device, store two encrypted backups in separate owner-controlled locations, and never commit or send the keystore or passwords through chat.

Configure the GitHub `oois-internal` environment with these repository variables:

- `OOIS_FIREBASE_API_KEY`
- `OOIS_FIREBASE_AUTH_DOMAIN`
- `OOIS_FIREBASE_PROJECT_ID`
- `OOIS_FIREBASE_STORAGE_BUCKET`
- `OOIS_FIREBASE_APP_ID`

Configure these encrypted secrets:

- `OOIS_UPLOAD_KEYSTORE_B64`
- `OOIS_UPLOAD_STORE_PASSWORD`
- `OOIS_UPLOAD_KEY_ALIAS`
- `OOIS_UPLOAD_KEY_PASSWORD`

Run **OOIS Signed Internal Bundle** manually with version code `1` and version name `1.0.0`. Download the resulting AAB and confirm its SHA-256 checksum. The workflow does not publish it.

## Play Console internal-test gate

1. Verify the owner's Play Console identity and create the app as **OOIS Field Mapper**, default language English (United States), app (not game), free, and package `com.bobsome1.oois`. The package becomes fixed after the first AAB upload.
2. Enroll in Play App Signing and upload the signed AAB only after owner approval.
3. Add the Galaxy A17 owner's Google account to an internal tester list. Confirm the existing public support/privacy address `thebobsomest1@gmail.com` for the Play listing, or replace it consistently in the policy and release check before building; do not expose the private Firebase sign-in password.
4. Provide a public privacy-policy URL at `https://bobsome1.com/oois/app/privacy-policy.html` after the approved web build is live.
5. Declare foreground precise/approximate location, user-selected photos, field notes and optional account email accurately in Data safety. They are used for app functionality, encrypted in transit when cloud features are chosen, not sold, and not used for advertising. Firebase/Google Cloud and Gemini act as service providers for the optional cloud paths.
6. In **App access**, explain that the notebook works without sign-in. Supply the isolated reviewer account through Play Console—not in source, chat or store text—so reviewers can exercise private cloud and AI features without seeing primary-owner records.
7. Complete content rating, target audience (not designed for children), ads declaration (no ads), store listing, app icon, phone screenshots and feature graphic.
8. Install from the Play internal-test link on the Galaxy A17. Complete every item in `ANDROID-TESTING.md`, then export and preserve the evidence receipt and notebook backup.

## Release blockers that remain owner-only

- Actual Firebase project ID and irreversible Firestore/Storage locations.
- Blaze billing link and budget alerts.
- Primary, denial-test and isolated Play-reviewer accounts.
- Gemini secret entry and the real authorized image receipt.
- Upload-key creation, encrypted backup and GitHub secret entry.
- Play Console app creation, declarations, listing assets and AAB upload.
- Galaxy A17 physical test and Play pre-launch report review.

The PR must remain draft until the live verification receipt and Galaxy field-test evidence pass. Production publication still requires a later, separate approval after internal testing.
