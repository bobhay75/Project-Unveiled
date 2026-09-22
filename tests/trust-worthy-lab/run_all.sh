#!/usr/bin/env bash
set -euo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repo_dir"

node --check truth/lab/research-sweep.js
node --check truth/lab/app.js
node --check truth/lab/observer.js
node --check truth/entry-v2.js
node --check truth/investigation-ui.js
node --check tests/trust-worthy-lab/release-integrity.mjs
node --check tests/trust-worthy-lab/release-manifest.mjs
node --check tests/trust-worthy-lab/live-release-gate.mjs
node --check tests/trust-worthy-lab/claim-validator.mjs
node --check tests/trust-worthy-lab/claim-validation.mjs
bash tests/trust-worthy-lab/investigate-method-gate.sh
bash tests/trust-worthy-lab/health-method-gate.sh
if command -v php >/dev/null 2>&1; then
  php -r 'if (PHP_VERSION_ID < 80100) { fwrite(STDERR, "PHP 8.1 or newer is required.\n"); exit(1); }'
  php -l truth/lib/trust-worthy-deep.php >/dev/null
  php -l truth/deep-stream.php >/dev/null
  php -l truth/investigation.php >/dev/null
  php tests/trust-worthy-public/deep-v2-contract.php
  php tests/trust-worthy-public/backend-hardening.php
  php tests/trust-worthy-intake/intake-storage.php
  bash tests/trust-worthy-intake/request-gate.sh
  php tests/trust-worthy-daily/hardening.php
  php tests/project-unveiled-analytics/hardening.php
else
  echo "PHP behavior checks skipped: PHP is unavailable in this environment."
fi
node tests/trust-worthy-lab/release-manifest.mjs --check
python3 scripts/validate_site.py
python3 tests/trust-worthy-lab/static_checks.py
python3 tests/trust-worthy-intake/static_checks.py
python3 tests/trust-worthy-daily/static_checks.py
python3 tests/project-unveiled-analytics/static_checks.py
node tests/trust-worthy-lab/claim-validation.mjs
node tests/trust-worthy-lab/research-sweep.mjs
node tests/trust-worthy-lab/app-smoke.mjs
node tests/trust-worthy-lab/observer.mjs

if grep -RIni --exclude='*.png' -- "kcmc" truth/lab; then
  echo "Blocked: KCMC must remain separate from Trust-Worthy." >&2
  exit 1
else
  grep_status=$?
  if [[ "$grep_status" -ne 1 ]]; then
    echo "Blocked: KCMC separation scan could not run." >&2
    exit "$grep_status"
  fi
fi

echo "Release gate passed: syntax, dynamic deep-investigation receipts, reproducible manifest, structure, safety copy, claim-level fail-closed validation, research engine, adversarial workflow, provenance negatives, and KCMC separation."
