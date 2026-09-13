#!/usr/bin/env bash
set -euo pipefail

fail() {
  echo "Retired-path cleanup blocked: $*" >&2
  exit 1
}

if [[ "$#" -ne 2 ]]; then
  fail "usage: $0 DEPLOY_ROOT RETIRED_PATHS_FILE"
fi

deploy_root="${1%/}"
retired_file="$2"
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"

[[ -n "$deploy_root" && "$deploy_root" == /* ]] || fail "deployment root must be an absolute path"
[[ "$deploy_root" != "/" && "$deploy_root" != "/home" ]] || fail "deployment root is too broad"
[[ -d "$deploy_root" && ! -L "$deploy_root" ]] || fail "deployment root must be an existing, real directory"
deploy_root="$(cd "$deploy_root" && pwd -P)"
[[ -f "$retired_file" && ! -L "$retired_file" ]] || fail "retired-path registry is missing or is a symlink"

declare -a retired_paths=()
declare -A seen_paths=()
line_number=0

while IFS= read -r raw_path || [[ -n "$raw_path" ]]; do
  line_number=$((line_number + 1))
  path="${raw_path%$'\r'}"

  [[ -z "$path" || "$path" == \#* ]] && continue
  [[ "$path" != /* ]] || fail "line $line_number is absolute: $path"
  [[ "$path" != *\\* ]] || fail "line $line_number contains a backslash: $path"
  [[ "$path" != *'*'* && "$path" != *'?'* && "$path" != *'['* && "$path" != *']'* ]] || \
    fail "line $line_number contains a glob: $path"
  [[ "$path" != *'//'* && "$path" != */ ]] || fail "line $line_number is not a normalized file path: $path"

  IFS='/' read -r -a parts <<< "$path"
  for part in "${parts[@]}"; do
    [[ -n "$part" && "$part" != "." && "$part" != ".." ]] || \
      fail "line $line_number contains an unsafe path component: $path"
  done

  [[ -z "${seen_paths[$path]+present}" ]] || fail "line $line_number duplicates: $path"
  seen_paths["$path"]=1

  if [[ -e "$repo_root/$path" || -L "$repo_root/$path" ]]; then
    fail "refusing to retire a path that still exists in the repository: $path"
  fi

  cursor="$deploy_root"
  for ((index = 0; index < ${#parts[@]} - 1; index++)); do
    cursor="$cursor/${parts[$index]}"
    [[ ! -L "$cursor" ]] || fail "line $line_number crosses a symlinked directory: $path"
    if [[ -e "$cursor" && ! -d "$cursor" ]]; then
      fail "line $line_number crosses a non-directory path: $path"
    fi
  done

  target="$deploy_root/$path"
  [[ "$target" == "$deploy_root/"* ]] || fail "line $line_number escapes the deployment root: $path"
  if [[ -d "$target" && ! -L "$target" ]]; then
    fail "directories cannot be retired recursively; list their files instead: $path"
  fi

  retired_paths+=("$path")
done < "$retired_file"

removed=0
already_absent=0
for path in "${retired_paths[@]}"; do
  target="$deploy_root/$path"
  if [[ -e "$target" || -L "$target" ]]; then
    /bin/rm -f -- "$target"
    removed=$((removed + 1))
    echo "Retired public file removed: $path"
  else
    already_absent=$((already_absent + 1))
  fi
done

echo "Retired-path cleanup passed: $removed removed, $already_absent already absent."
