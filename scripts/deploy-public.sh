#!/usr/bin/env bash
set -euo pipefail
export LC_ALL=C

fail() {
  echo "Public deployment blocked: $*" >&2
  exit 1
}

if [[ "$#" -ne 1 ]]; then
  fail "usage: $0 DEPLOY_ROOT"
fi

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
deploy_root="${1%/}"
expected_root="/home/bobsome1/public_html"
public_manifest="$repo_root/deployment/public-files.txt"

is_public_path() {
  local candidate_lower="${1,,}"
  case "$candidate_lower" in
    .git|.git/*|.github|.github/*|deployment|deployment/*|docs|docs/*|scripts|scripts/*|tests|tests/*|__pycache__|__pycache__/*|*/__pycache__|*/__pycache__/*)
      return 1
      ;;
    site-private|site-private/*|*/site-private|*/site-private/*)
      return 1
      ;;
    .cpanel.yml|*/.cpanel.yml|.gitignore|*/.gitignore|.gitattributes|*/.gitattributes|.gitmodules|*/.gitmodules)
      return 1
      ;;
    .env|.env.*|*/.env|*/.env.*|readme*|*/readme*|error_log|*/error_log|*.ds_store|thumbs.db|*/thumbs.db)
      return 1
      ;;
    credentials.json|*/credentials.json|service-account.json|*/service-account.json|id_rsa|*/id_rsa|id_dsa|*/id_dsa|id_ed25519|*/id_ed25519)
      return 1
      ;;
    *.md|*.py|*.pyc|*.pyo|*.yml|*.yaml|*.pem|*.key|*.p12|*.pfx|*.zip|*.sql|*.log|*.bak|*.old|*backup*.html)
      return 1
      ;;
  esac
  return 0
}

preflight_destination_path() {
  local path="$1"
  local cursor="$deploy_root"
  local target
  local index
  local -a parts=()

  IFS='/' read -r -a parts <<< "$path"
  for ((index = 0; index < ${#parts[@]} - 1; index++)); do
    cursor="$cursor/${parts[$index]}"
    [[ ! -L "$cursor" ]] || fail "public destination crosses a symlinked directory: $path"
    if [[ -e "$cursor" && ! -d "$cursor" ]]; then
      fail "public destination crosses a non-directory path: $path"
    fi
  done

  target="$deploy_root/$path"
  [[ ! -L "$target" ]] || fail "public destination is a symlink: $path"
  if [[ -e "$target" && ! -f "$target" ]]; then
    fail "public destination is not a regular file: $path"
  fi
}

[[ -n "$deploy_root" && "$deploy_root" == /* ]] || fail "deployment root must be an absolute path"
[[ -d "$deploy_root" && ! -L "$deploy_root" ]] || fail "deployment root must be an existing, real directory"
deploy_root="$(cd "$deploy_root" && pwd -P)"

if [[ "${PROJECT_UNVEILED_DEPLOY_TEST_MODE:-0}" == "1" ]]; then
  [[ "${deploy_root##*/}" == "public_html" ]] || fail "test deployment root must end in public_html"
elif [[ "$deploy_root" != "$expected_root" ]]; then
  fail "expected $expected_root, received $deploy_root"
fi

[[ -x /usr/bin/rsync ]] || fail "/usr/bin/rsync is unavailable"
git_bin="$(command -v git || true)"
[[ -n "$git_bin" && -x "$git_bin" ]] || fail "git is unavailable"
[[ -f "$public_manifest" && ! -L "$public_manifest" ]] || fail "public-file manifest is missing or is a symlink"
[[ -f "$repo_root/deployment/retired-public-paths.txt" ]] || fail "retired-path registry is missing"

# cPanel reports the deployed HEAD SHA, so real production bytes must come from
# that exact commit rather than from tracked edits or untracked workspace files.
# Test mode intentionally exercises the current shared checkout, including new
# files that have not been committed yet.
if [[ "${PROJECT_UNVEILED_DEPLOY_TEST_MODE:-0}" != "1" ]]; then
  "$git_bin" -C "$repo_root" diff-index --quiet HEAD -- || \
    fail "tracked checkout differs from HEAD; commit or discard local changes before deployment"
  if ! untracked_paths="$("$git_bin" -C "$repo_root" ls-files --others --exclude-standard)"; then
    fail "untracked-file check could not be completed"
  fi
  [[ -z "$untracked_paths" ]] || \
    fail "untracked files are present; clean the checkout before deployment"
fi

declare -A expected_public=()
declare -A manifested_public=()
if [[ "${PROJECT_UNVEILED_DEPLOY_TEST_MODE:-0}" == "1" ]]; then
  git_list_args=(ls-files --cached --others --exclude-standard -z)
else
  git_list_args=(ls-files -z)
fi

while IFS= read -r -d '' path; do
  if is_public_path "$path"; then
    [[ -f "$repo_root/$path" && ! -L "$repo_root/$path" ]] || \
      fail "public Git path is not a regular file: $path"
    expected_public["$path"]=1
  fi
done < <("$git_bin" -C "$repo_root" "${git_list_args[@]}")

previous_path=""
line_number=0
while IFS= read -r path || [[ -n "$path" ]]; do
  line_number=$((line_number + 1))
  [[ -n "$path" && "$path" != /* && "$path" != */ && "$path" != *'//'* && "$path" != *\\* ]] || \
    fail "invalid public-file manifest entry on line $line_number"
  [[ "$path" != *'*'* && "$path" != *'?'* && "$path" != *'['* && "$path" != *']'* ]] || \
    fail "public-file manifest entry contains a glob on line $line_number: $path"
  IFS='/' read -r -a parts <<< "$path"
  for part in "${parts[@]}"; do
    [[ -n "$part" && "$part" != "." && "$part" != ".." ]] || \
      fail "public-file manifest entry contains an unsafe component on line $line_number: $path"
  done
  is_public_path "$path" || fail "internal/private path appears in public-file manifest: $path"
  [[ -n "${expected_public[$path]+present}" ]] || fail "untracked or missing path appears in public-file manifest: $path"
  [[ -z "${manifested_public[$path]+present}" ]] || fail "duplicate public-file manifest entry: $path"
  if [[ -n "$previous_path" && "$path" < "$previous_path" ]]; then
    fail "public-file manifest is not byte-order sorted: $path"
  fi
  manifested_public["$path"]=1
  previous_path="$path"
done < "$public_manifest"

for path in "${!expected_public[@]}"; do
  [[ -n "${manifested_public[$path]+present}" ]] || fail "public Git path is missing from manifest: $path"
done

[[ "${#manifested_public[@]}" -gt 0 ]] || fail "public-file manifest is empty"

# --safe-links protects only symlinks supplied by the rsync source. Reject
# destination symlinks and non-directory parents before rsync can follow them
# outside the intended public root.
while IFS= read -r path || [[ -n "$path" ]]; do
  preflight_destination_path "$path"
done < "$public_manifest"

if [[ "${PROJECT_UNVEILED_DEPLOY_TEST_MODE:-0}" != "1" ]]; then
  migration_php="/usr/local/bin/php"
  [[ -x "$migration_php" ]] || fail "documented cPanel PHP is unavailable for the private runtime migration"
  "$migration_php" -r 'if (PHP_VERSION_ID < 80100) { fwrite(STDERR, "PHP 8.1 or newer is required.\n"); exit(1); } foreach (["ctype_digit", "curl_init", "mb_check_encoding", "mb_strlen", "mb_substr"] as $function) { if (!function_exists($function)) { fwrite(STDERR, "Required PHP function unavailable: {$function}\n"); exit(1); } }' || \
    fail "required cPanel PHP 8.1 runtime or extensions are unavailable"
  "$migration_php" -q "$repo_root/scripts/migrate-intake-permissions.php" --pre-sync || \
    fail "private runtime migration failed"
fi

"$repo_root/scripts/prune-retired-public-paths.sh" \
  "$deploy_root" \
  "$repo_root/deployment/retired-public-paths.txt"

/usr/bin/rsync \
  -rlt \
  --files-from="$public_manifest" \
  --no-implied-dirs \
  --prune-empty-dirs \
  --safe-links \
  --chmod=D755,F644 \
  "$repo_root/" \
  "$deploy_root/"

if [[ "${PROJECT_UNVEILED_DEPLOY_TEST_MODE:-0}" != "1" ]]; then
  # Repeat after the new fail-closed endpoints are live so an append from the
  # pre-deploy PHP generation cannot leave a raw legacy log behind.
  "$migration_php" -q "$repo_root/scripts/migrate-intake-permissions.php" --post-sync || \
    fail "post-sync private runtime migration failed"
fi

echo "Tracked public-file sync completed without broad deletion."
