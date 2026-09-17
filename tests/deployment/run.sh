#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
cd "$repo_root"
python3 scripts/check-deployment-safety.py
grep -Fxq 'export LC_ALL=C' scripts/deploy-public.sh
grep -Fq 'diff-index --quiet HEAD --' scripts/deploy-public.sh
grep -Fq 'ls-files --others --exclude-standard' scripts/deploy-public.sh

tmp_root="$(mktemp -d)"
trap '/bin/rm -rf -- "$tmp_root"' EXIT
deploy_root="$tmp_root/public_html"
/bin/mkdir -p "$deploy_root/unmanaged-app"
/bin/mkdir -p "$deploy_root/unveiltheinsideofme"

printf 'old archive\n' > "$deploy_root/Project-Unveiled-repository-ready.zip"
printf 'old archive\n' > "$deploy_root/Project-Unveiled-repository-ready (2).zip"
printf 'old private-app stylesheet\n' > "$deploy_root/unveiltheinsideofme/visual-approval.css"
printf 'old private-app script\n' > "$deploy_root/unveiltheinsideofme/visual-approval.js"
printf 'must survive\n' > "$deploy_root/unmanaged-app/keep.txt"

PROJECT_UNVEILED_DEPLOY_TEST_MODE=1 \
  /bin/bash scripts/deploy-public.sh "$deploy_root"

test -f "$deploy_root/index.html"
test -f "$deploy_root/truth/lab/index.html"
test -f "$deploy_root/unmanaged-app/keep.txt"
test ! -e "$deploy_root/Project-Unveiled-repository-ready.zip"
test ! -e "$deploy_root/Project-Unveiled-repository-ready (2).zip"
test ! -e "$deploy_root/unveiltheinsideofme/visual-approval.css"
test ! -e "$deploy_root/unveiltheinsideofme/visual-approval.js"
test ! -e "$deploy_root/.git"
test ! -e "$deploy_root/.github"
test ! -e "$deploy_root/.cpanel.yml"
test ! -e "$deploy_root/deployment"
test ! -e "$deploy_root/docs"
test ! -e "$deploy_root/scripts"
test ! -e "$deploy_root/tests"

# A second run is idempotent and still preserves files outside this repository.
PROJECT_UNVEILED_DEPLOY_TEST_MODE=1 \
  /bin/bash scripts/deploy-public.sh "$deploy_root"
test -f "$deploy_root/unmanaged-app/keep.txt"

# Traversal and attempts to retire a current repository file must fail closed.
outside="$tmp_root/outside.txt"
bad_registry="$tmp_root/bad-retired-paths.txt"
printf 'outside\n' > "$outside"
printf '../outside.txt\n' > "$bad_registry"
if /bin/bash scripts/prune-retired-public-paths.sh "$deploy_root" "$bad_registry"; then
  echo "Traversal registry unexpectedly passed." >&2
  exit 1
fi
test -f "$outside"

printf 'index.html\n' > "$bad_registry"
if /bin/bash scripts/prune-retired-public-paths.sh "$deploy_root" "$bad_registry"; then
  echo "Tracked-file retirement unexpectedly passed." >&2
  exit 1
fi
test -f "$deploy_root/index.html"

/bin/mkdir -p "$tmp_root/outside-directory"
/bin/ln -s "$tmp_root/outside-directory" "$deploy_root/linked-directory"
printf 'linked-directory/outside.txt\n' > "$bad_registry"
if /bin/bash scripts/prune-retired-public-paths.sh "$deploy_root" "$bad_registry"; then
  echo "Symlinked parent directory unexpectedly passed." >&2
  exit 1
fi

# rsync's --safe-links does not protect against symlinked parents already in
# the destination. The manifest preflight must block before writing outside.
symlink_deploy_root="$tmp_root/symlink-case/public_html"
symlink_outside="$tmp_root/symlink-case/outside"
/bin/mkdir -p "$symlink_deploy_root" "$symlink_outside"
/bin/ln -s "$symlink_outside" "$symlink_deploy_root/truth"
if PROJECT_UNVEILED_DEPLOY_TEST_MODE=1 /bin/bash scripts/deploy-public.sh "$symlink_deploy_root"; then
  echo "Manifest sync through a symlinked destination parent unexpectedly passed." >&2
  exit 1
fi
test -L "$symlink_deploy_root/truth"
test ! -e "$symlink_outside/lab/index.html"

if PROJECT_UNVEILED_DEPLOY_TEST_MODE=1 /bin/bash scripts/deploy-public.sh "$tmp_root"; then
  echo "Broad deployment root unexpectedly passed." >&2
  exit 1
fi

# The pre-sync phase must remain compatible with the still-live Release 15
# desk. If sync fails after this phase, withholding post-sync must leave its
# token, raw error record, and unpublished single-file draft byte-for-byte
# intact. The ordering assertions above cover the real deploy control flow.
if command -v php >/dev/null 2>&1; then
  phase_private="$tmp_root/phased-private"
  /bin/mkdir -m 0700 "$phase_private"
  printf 'release-15-token-bytes\n' > "$phase_private/daily-admin-token.txt"
  printf '%s\n' '{"message":"release-15-error-bytes"}' > "$phase_private/daily-last-error.json"
  printf '%s\n' '{"headline":"Legacy headline","claim":"Legacy claim","summary":"Legacy summary","sources":[],"_meta":{"candidate":{"candidate_type":"meat-desk","source":"Trust-Worthy Meat Desk"}}}' > "$phase_private/daily-draft.json"
  phase_before="$(sha256sum "$phase_private/daily-admin-token.txt" "$phase_private/daily-last-error.json" "$phase_private/daily-draft.json")"
  PROJECT_UNVEILED_DEPLOY_TEST_MODE=1 TW_INTAKE_MIGRATION_TEST_DIR="$phase_private" \
    php scripts/migrate-intake-permissions.php --pre-sync >/dev/null
  phase_after_failed_sync="$(sha256sum "$phase_private/daily-admin-token.txt" "$phase_private/daily-last-error.json" "$phase_private/daily-draft.json")"
  [[ "$phase_after_failed_sync" == "$phase_before" ]]
  grep -Eq '^[a-f0-9]{64}$' "$phase_private/question-secret.txt"
  [[ "$(stat -c '%a' "$phase_private/question-secret.txt")" == "600" ]]
  phase_secret_before="$(sha256sum "$phase_private/question-secret.txt")"
  PROJECT_UNVEILED_DEPLOY_TEST_MODE=1 TW_INTAKE_MIGRATION_TEST_DIR="$phase_private" \
    php scripts/migrate-intake-permissions.php --post-sync >/dev/null
  [[ "$(sha256sum "$phase_private/question-secret.txt")" == "$phase_secret_before" ]]
  test ! -e "$phase_private/daily-admin-token.txt"
  test ! -e "$phase_private/daily-last-error.json"
  test ! -e "$phase_private/daily-draft.json"
fi

# Release rollback is deliberately scoped to the Evidence Lab. It restores the
# verified predecessor bytes without checking out or deploying an older whole
# site, which would leave newer release files behind on cPanel.
rollback_target="$tmp_root/rollback/public_html/truth/lab"
/bin/mkdir -p "$rollback_target"
/usr/bin/rsync -rlt truth/lab/ "$rollback_target/"
cp "$rollback_target/status.json" "$tmp_root/status-before-blocked-rollback.json"
printf 'unmanaged\n' > "$rollback_target/unmanaged.txt"
if PROJECT_UNVEILED_ROLLBACK_TEST_MODE=1 \
  /bin/bash scripts/rollback-trust-worthy-lab.sh "$rollback_target"; then
  echo "Rollback with an unmanaged target file unexpectedly passed." >&2
  exit 1
fi
cmp -s "$tmp_root/status-before-blocked-rollback.json" "$rollback_target/status.json"
/bin/rm -f -- "$rollback_target/unmanaged.txt"

PROJECT_UNVEILED_ROLLBACK_TEST_MODE=1 \
  /bin/bash scripts/rollback-trust-worthy-lab.sh "$rollback_target"
"$(command -v git)" show 37ad89d77205d9479cee96834288d5cbb4b309b0:truth/lab/status.json \
  | cmp -s - "$rollback_target/status.json"
"$(command -v git)" show 37ad89d77205d9479cee96834288d5cbb4b309b0:truth/lab/app.js \
  | cmp -s - "$rollback_target/app.js"

# A destination-file symlink must block before any write can escape the lab.
printf 'outside\n' > "$tmp_root/rollback-outside.txt"
/bin/rm -f -- "$rollback_target/app.js"
/bin/ln -s "$tmp_root/rollback-outside.txt" "$rollback_target/app.js"
if PROJECT_UNVEILED_ROLLBACK_TEST_MODE=1 \
  /bin/bash scripts/rollback-trust-worthy-lab.sh "$rollback_target"; then
  echo "Rollback through a destination symlink unexpectedly passed." >&2
  exit 1
fi
grep -Fxq 'outside' "$tmp_root/rollback-outside.txt"

echo "Deployment tests passed: exact manifest, scoped cleanup and rollback, idempotence, and fail-closed paths."
