#!/usr/bin/env bash
set -euo pipefail

if ! command -v php >/dev/null 2>&1; then
  echo "Intake HTTP request checks skipped: PHP is unavailable in this environment."
  exit 0
fi

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd -P)"
port=18716
response_body="$(mktemp)"
response_headers="$(mktemp)"
server_log="$(mktemp)"
oversized_question="$(mktemp)"
oversized_challenge="$(mktemp)"
test_root="$(mktemp -d)"
server_pid=""

cleanup() {
  if [[ -n "$server_pid" ]]; then
    kill "$server_pid" >/dev/null 2>&1 || true
    wait "$server_pid" 2>/dev/null || true
  fi
  /bin/rm -f -- "$response_body" "$response_headers" "$server_log" "$oversized_question" "$oversized_challenge"
  /bin/rm -rf -- "$test_root"
}
trap cleanup EXIT

php -S "127.0.0.1:${port}" -t "$repo_dir" >"$server_log" 2>&1 &
server_pid=$!

ready=0
for _ in {1..40}; do
  if curl -fsS --max-time 2 "http://127.0.0.1:${port}/privacy.html" >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 0.1
done
if [[ "$ready" != "1" ]]; then
  sed -n '1,120p' "$server_log" >&2
  exit 1
fi

request_status() {
  curl -sS --max-time 10 -H 'Host: bobsome1.com' -H 'Origin: https://bobsome1.com' \
    -H 'Sec-Fetch-Site: same-origin' -D "$response_headers" -o "$response_body" -w '%{http_code}' "$@"
}

status="$(request_status -F 'question=This multipart request must never enter private intake.' -F 'opened_at=1' \
  'http://127.0.0.1:18716/truth/question-submit.php')"
[[ "$status" == "415" ]]
grep -q 'URL-encoded form data is required' "$response_body"

# The browser-default encoding used by the live forms must pass the transport
# boundary. An intentionally expired timestamp then proves endpoint validation
# (not the request gate) produced this 422 response.
status="$(request_status -X POST -H 'Content-Type: application/x-www-form-urlencoded' \
  --data 'opened_at=0&website=' \
  'http://127.0.0.1:18716/truth/question-submit.php')"
[[ "$status" == "422" ]]
grep -q 'This form expired or was submitted too quickly' "$response_body"

printf 'private upload fixture\n' > "$test_root/upload.txt"
status="$(request_status -F 'evidence=@'"$test_root/upload.txt" \
  'http://127.0.0.1:18716/truth/challenge-submit.php')"
[[ "$status" == "422" ]]
grep -q 'File uploads are not accepted' "$response_body"

python3 -c 'import sys; sys.stdout.write("question=" + "q" * 70000)' > "$oversized_question"
status="$(request_status --http1.1 -X POST -H 'Content-Type: application/x-www-form-urlencoded' \
  -H 'Transfer-Encoding: chunked' -H 'Content-Length:' -H 'Expect:' --data-binary @"$oversized_question" \
  'http://127.0.0.1:18716/truth/question-submit.php')"
[[ "$status" == "413" ]]
grep -q 'The submission is too large' "$response_body"

python3 -c 'import sys; sys.stdout.write("argument=" + "a" * 120000)' > "$oversized_challenge"
status="$(request_status --http1.1 -X POST -H 'Content-Type: application/x-www-form-urlencoded' \
  -H 'Transfer-Encoding: chunked' -H 'Content-Length:' -H 'Expect:' --data-binary @"$oversized_challenge" \
  'http://127.0.0.1:18716/truth/challenge-submit.php')"
[[ "$status" == "413" ]]
grep -q 'The submission is too large' "$response_body"

migration_dir="$test_root/private-runtime"
/bin/mkdir -p "$migration_dir"
printf '[{"private":"question"}]\n' > "$migration_dir/questions.json"
printf '[{"private":"challenge"}]\n' > "$migration_dir/challenges.json"
printf '%064d\n' 0 > "$migration_dir/question-secret.txt"
printf '%064d\n' 1 > "$migration_dir/challenge-secret.txt"
printf 'release-15-token-must-survive-pre-sync\n' > "$migration_dir/daily-admin-token.txt"
printf '%s\n' '{"headline":"Legacy headline","claim":"Legacy claim","summary":"Legacy summary","sources":[],"_meta":{"candidate":{"candidate_type":"meat-desk","source":"Trust-Worthy Meat Desk"}}}' > "$migration_dir/daily-draft.json"
/bin/chmod 0644 "$migration_dir/questions.json" "$migration_dir/challenges.json" \
  "$migration_dir/question-secret.txt" "$migration_dir/challenge-secret.txt" \
  "$migration_dir/daily-admin-token.txt" "$migration_dir/daily-draft.json"
before="$(sha256sum "$migration_dir/questions.json" "$migration_dir/challenges.json" \
  "$migration_dir/question-secret.txt" "$migration_dir/challenge-secret.txt")"
PROJECT_UNVEILED_DEPLOY_TEST_MODE=1 TW_INTAKE_MIGRATION_TEST_DIR="$migration_dir" \
  php "$repo_dir/scripts/migrate-intake-permissions.php" --pre-sync > "$response_body"
after="$(sha256sum "$migration_dir/questions.json" "$migration_dir/challenges.json" \
  "$migration_dir/question-secret.txt" "$migration_dir/challenge-secret.txt")"
[[ "$after" == "$before" ]]
[[ "$(cat "$migration_dir/daily-admin-token.txt")" == "release-15-token-must-survive-pre-sync" ]]
test -f "$migration_dir/daily-draft.json"
for private_file in questions.json challenges.json question-secret.txt challenge-secret.txt intake-permissions.lock; do
  [[ "$(stat -c '%a' "$migration_dir/$private_file")" == "600" ]]
done
PROJECT_UNVEILED_DEPLOY_TEST_MODE=1 TW_INTAKE_MIGRATION_TEST_DIR="$migration_dir" \
  php "$repo_dir/scripts/migrate-intake-permissions.php" --post-sync > "$response_body"
test ! -e "$migration_dir/daily-admin-token.txt"
test ! -e "$migration_dir/daily-draft.json"

absent_dir="$test_root/absent-runtime"
PROJECT_UNVEILED_DEPLOY_TEST_MODE=1 TW_INTAKE_MIGRATION_TEST_DIR="$absent_dir" \
  php "$repo_dir/scripts/migrate-intake-permissions.php" --pre-sync > "$response_body"
test ! -e "$absent_dir"
PROJECT_UNVEILED_DEPLOY_TEST_MODE=1 TW_INTAKE_MIGRATION_TEST_DIR="$absent_dir" \
  php "$repo_dir/scripts/migrate-intake-permissions.php" --post-sync > "$response_body"
test ! -e "$absent_dir"

echo "Intake HTTP checks passed: URL-encoded-only forms, no uploads, chunked raw-body caps, and content-preserving permission migration."
