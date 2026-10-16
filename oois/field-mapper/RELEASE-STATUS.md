# OOIS Release Status

Updated: 2026-09-28

## Verified
- PR #35 is open, draft, and mergeable.
- Last verified CI at head `5ad9edef7a11feee03baf6cbdeb1f2abe0918507` passed:
  - web build and tests
  - Firestore/Storage emulator rules
  - Android preview APK build + lint
  - unsigned production-identity AAB build
  - site-safety checks

## Current owner-controlled gates
1. Create/confirm dedicated Firebase project.
2. Confirm Firestore/Storage regions and billing/budget alerts.
3. Configure owner/reviewer accounts, rules, CORS, callable function, and Gemini secret.
4. Run live verification harness and preserve receipt.
5. Create/back up Play upload key, configure protected GitHub environment, and run signed internal bundle workflow.
6. Complete Play Console app, Data safety, App access, privacy/support details.
7. Upload only to internal testing first; install on Galaxy A17 and review pre-launch report.

No merge, production deployment, Play upload, or public release is authorized by this file.
