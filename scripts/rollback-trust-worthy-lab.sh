#!/usr/bin/env bash
set -euo pipefail
export LC_ALL=C

fail() {
  echo "Evidence Lab rollback blocked: $*" >&2
  exit 1
}

if [[ "$#" -ne 1 ]]; then
  fail "usage: $0 /home/bobsome1/public_html/truth/lab"
fi

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
target="${1%/}"
expected_target="/home/bobsome1/public_html/truth/lab"
rollback_commit="9c4faa71e9c4955c1e597b2b3f9c8a861cac3bf1"

[[ -n "$target" && "$target" == /* ]] || fail "target must be an absolute path"
[[ -d "$target" && ! -L "$target" ]] || fail "target must be an existing, real directory"
target="$(cd "$target" && pwd -P)"

if [[ "${PROJECT_UNVEILED_ROLLBACK_TEST_MODE:-0}" == "1" ]]; then
  [[ "$target" == */public_html/truth/lab ]] || fail "test target must end in public_html/truth/lab"
elif [[ "$target" != "$expected_target" ]]; then
  fail "expected $expected_target, received $target"
fi

git_bin="$(command -v git || true)"
[[ -n "$git_bin" && -x "$git_bin" ]] || fail "git is unavailable"
[[ -x /usr/bin/rsync ]] || fail "/usr/bin/rsync is unavailable"
[[ -x /bin/tar ]] || fail "/bin/tar is unavailable"

resolved_commit="$($git_bin -C "$repo_root" rev-parse --verify "$rollback_commit^{commit}" 2>/dev/null || true)"
[[ "$resolved_commit" == "$rollback_commit" ]] || fail "verified rollback commit is unavailable"

temporary_root="$(mktemp -d /tmp/project-unveiled-lab-rollback.XXXXXX)"
cleanup() {
  /bin/rm -rf -- "$temporary_root"
}
trap cleanup EXIT

rollback_paths="$temporary_root/rollback-paths.txt"
current_paths="$temporary_root/current-paths.txt"
target_paths="$temporary_root/target-paths.txt"

"$git_bin" -C "$repo_root" ls-tree -r --name-only "$rollback_commit" -- truth/lab \
  | sed 's#^truth/lab/##' \
  | LC_ALL=C sort > "$rollback_paths"
"$git_bin" -C "$repo_root" ls-files -- truth/lab \
  | sed 's#^truth/lab/##' \
  | LC_ALL=C sort > "$current_paths"

[[ -s "$rollback_paths" ]] || fail "rollback file set is empty"
cmp -s "$rollback_paths" "$current_paths" || \
  fail "current checkout and verified rollback release do not have the same Evidence Lab file set"

while IFS= read -r path || [[ -n "$path" ]]; do
  [[ -n "$path" && "$path" != /* && "$path" != */ && "$path" != *'//'* && "$path" != *\\* ]] || \
    fail "unsafe rollback path: $path"
  IFS='/' read -r -a parts <<< "$path"
  for part in "${parts[@]}"; do
    [[ -n "$part" && "$part" != "." && "$part" != ".." ]] || fail "unsafe rollback path: $path"
  done
done < "$rollback_paths"

if [[ -n "$(find "$target" -type l -print -quit)" ]]; then
  fail "target contains a symlink"
fi
if [[ -n "$(find "$target" ! -type d ! -type f -print -quit)" ]]; then
  fail "target contains a non-regular filesystem entry"
fi
find "$target" -type f -printf '%P\n' | LC_ALL=C sort > "$target_paths"
cmp -s "$target_paths" "$current_paths" || \
  fail "target file set does not exactly match the current Evidence Lab release"

"$git_bin" -C "$repo_root" archive --format=tar "$rollback_commit" -- truth/lab \
  | /bin/tar -xf - -C "$temporary_root"
source_root="$temporary_root/truth/lab"
[[ -d "$source_root" && ! -L "$source_root" ]] || fail "rollback archive is invalid"
if [[ -n "$(find "$source_root" -type l -print -quit)" ]]; then
  fail "rollback archive contains a symlink"
fi

while IFS= read -r path || [[ -n "$path" ]]; do
  [[ -f "$source_root/$path" && ! -L "$source_root/$path" ]] || fail "rollback source is not a regular file: $path"

  cursor="$target"
  IFS='/' read -r -a parts <<< "$path"
  for ((index = 0; index < ${#parts[@]} - 1; index++)); do
    cursor="$cursor/${parts[$index]}"
    [[ ! -L "$cursor" ]] || fail "rollback destination crosses a symlink: $path"
    if [[ -e "$cursor" && ! -d "$cursor" ]]; then
      fail "rollback destination crosses a non-directory: $path"
    fi
  done

  destination="$target/$path"
  [[ ! -L "$destination" ]] || fail "rollback destination is a symlink: $path"
  if [[ -e "$destination" && ! -f "$destination" ]]; then
    fail "rollback destination is not a regular file: $path"
  fi
done < "$rollback_paths"

/usr/bin/rsync \
  -rlt \
  --checksum \
  --files-from="$rollback_paths" \
  --no-implied-dirs \
  --prune-empty-dirs \
  --safe-links \
  --chmod=D755,F644 \
  "$source_root/" \
  "$target/"

while IFS= read -r path || [[ -n "$path" ]]; do
  cmp -s "$source_root/$path" "$target/$path" || fail "post-rollback verification failed: $path"
done < "$rollback_paths"

echo "Evidence Lab restored exactly to release 15 from $rollback_commit."
