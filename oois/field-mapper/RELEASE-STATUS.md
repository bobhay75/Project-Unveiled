# OOIS Release Status

Updated: 2026-11-23

## Device-only release candidate

The canonical implementation is `oois/field-mapper` in [PR #35](https://github.com/bobhay75/Project-Unveiled/pull/35). The web notebook and separate Android preview can operate without Firebase, Gemini, a paid backend or production credentials. Neither a successful build nor this document establishes a completed live deployment or store release.

The latest source fixes exports to read records and routes directly from one IndexedDB transaction, so an open tab cannot back up an older in-memory copy after another tab saves. The offline worker isolates cache names and reads to the installed origin/path and current build. It excludes private responses, token-bearing URLs and non-shell requests; missing scripts do not receive an HTML fallback. A patched transitive gRPC dependency removes the four high advisory findings in the previous web lockfile.

Local verification on this date:

- All 15 data/geography and worker tests passed.
- Production Vite build and device-only release configuration check passed.
- Web and function runtime dependency audits reported zero vulnerabilities.
- Function JavaScript syntax and Git whitespace checks passed.

The **OOIS Web Build** pull-request workflow runs the browser acceptance suite, Firestore/Storage isolation tests, Android preview APK compilation/lint, and unsigned production AAB compilation/lint. Use the checks for the exact current PR head as the evidence; older successful runs do not verify newer source changes.

## Deployable artifacts

- **OOIS-Web-Preview:** the contents of `dist/`, including `.htaccess`, retained for 14 days. Deploy only these files to `/home/bobsome1/public_html/oois/app/`, then verify `https://bobsome1.com/oois/app/` and its headers. Preserve the previous build for rollback.
- **OOIS-Android-Preview:** separate `com.bobsome1.oois.preview` debug APK, installation guide and checksum, retained for seven days. It is suitable for device testing, not Google Play production signing.

Keep the source, dependencies, signing keys, environment files and notebook backups outside the public deployment. Exact locations and media remain on the device unless the owner exports or explicitly uploads them. JSON backups are private plaintext data; use private storage. Rolling back a web build must not erase browser records or backups.

## Remaining account and device requirements

The static device-only notebook can be deployed as soon as hosting access is available. These additional requirements apply to the optional cloud and Play features:

1. Confirm the dedicated Firebase project, Firestore/Storage regions and any billing before provisioning paid resources. No billing activation is part of the default device-only build.
2. Configure owner/reviewer accounts, rules, CORS, the callable function and Gemini secret. Run the live verification harness and preserve its sanitized receipt.
3. Create and back up the owner's Play upload key, configure the protected GitHub environment, and run the signed internal bundle workflow.
4. Complete the real Play Console app identity, Data safety, App access, privacy and support details. No invented declarations or review credentials.
5. Install the preview on the target Galaxy A17 and verify GPS, camera/media import, native backup sharing, airplane-mode use and recovery. For Play, upload to internal testing first and review its pre-launch report.

External map tiles still require connectivity. The saved device notebook supports offline records and routes, but downloaded offline topo maps, terrain/history overlays, GPS track/pace recording and satellite-change analysis are not implemented. The separate God's Eye encrypted-registry prototype is not silently included or represented as satellite analysis.
