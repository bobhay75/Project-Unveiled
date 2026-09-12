#!/usr/bin/env bash
set -euo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$repo_dir"

node --check truth/lab/research-sweep.js
node --check truth/lab/app.js
node --check truth/lab/observer.js
node --check tests/trust-worthy-lab/release-integrity.mjs
node --check tests/trust-worthy-lab/release-manifest.mjs
node --check tests/trust-worthy-lab/live-release-gate.mjs
bash tests/trust-worthy-lab/investigate-method-gate.sh
node tests/trust-worthy-lab/release-manifest.mjs --check
python3 scripts/validate_site.py
python3 tests/trust-worthy-lab/static_checks.py
node tests/trust-worthy-lab/research-sweep.mjs
node tests/trust-worthy-lab/app-smoke.mjs
node tests/trust-worthy-lab/observer.mjs

if rg -ni "kcmc" truth/lab --glob '!*.png'; then
  echo "Blocked: KCMC must remain separate from Trust-Worthy." >&2
  exit 1
fi

echo "Release gate passed: syntax, reproducible manifest, structure, safety copy, research engine, adversarial workflow, provenance negatives, and KCMC separation."
