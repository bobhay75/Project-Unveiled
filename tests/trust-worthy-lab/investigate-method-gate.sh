#!/usr/bin/env bash
set -euo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

grep -Fq 'Header always set Allow "GET, HEAD"' "$repo_dir/truth/lab/.htaccess"
for endpoint in truth/question-submit.php truth/challenge-submit.php; do
  grep -Fq "header('Allow: POST')" "$repo_dir/$endpoint"
done
grep -Fq "header('Allow: ' . \$required)" "$repo_dir/truth/daily/lib.php"
for endpoint in truth/daily/investigate.php truth/daily/publish.php; do
  grep -Fq 'tw_require_same_origin_form_post' "$repo_dir/$endpoint"
done

if ! command -v php >/dev/null 2>&1; then
  echo "HTTP method runtime gate skipped: PHP is unavailable; source-level Allow checks passed."
  exit 0
fi

port=18714
body_file="$(mktemp)"
header_file="$(mktemp)"
server_log="$(mktemp)"
server_pid=""

cleanup() {
  if [[ -n "$server_pid" ]]; then
    kill "$server_pid" >/dev/null 2>&1 || true
    wait "$server_pid" 2>/dev/null || true
  fi
  rm -f "$body_file" "$header_file" "$server_log"
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

for endpoint in truth/investigate.php truth/question-submit.php truth/challenge-submit.php; do
  for method in GET HEAD OPTIONS PUT PATCH DELETE; do
    status="$(curl -sS --max-time 5 -X "$method" -D "$header_file" -o "$body_file" -w '%{http_code}' "http://127.0.0.1:${port}/${endpoint}")"
    [[ "$status" == "405" ]]
    grep -qi '^Allow: POST' "$header_file"
  done
done

status="$(curl -sS --max-time 5 -X POST -H 'Host: bobsome1.com' -d 'opened_at=0' -D "$header_file" -o "$body_file" -w '%{http_code}' "http://127.0.0.1:${port}/truth/investigate.php")"
[[ "$status" == "403" ]]
grep -q 'Request origin was not accepted' "$body_file"

status="$(curl -sS --max-time 5 -X POST -H 'Host: bobsome1.com' -H 'Origin: null' -d 'opened_at=0' -D "$header_file" -o "$body_file" -w '%{http_code}' "http://127.0.0.1:${port}/truth/investigate.php")"
[[ "$status" == "403" ]]

status="$(curl -sS --max-time 5 -X POST -H 'Host: bobsome1.com' -H 'Origin: https://bobsome1.com' -H 'Sec-Fetch-Site: cross-site' -d 'opened_at=0' -D "$header_file" -o "$body_file" -w '%{http_code}' "http://127.0.0.1:${port}/truth/investigate.php")"
[[ "$status" == "403" ]]

status="$(curl -sS --max-time 5 -X POST -H 'Host: bobsome1.com' -H 'Origin: https://bobsome1.com' -H 'Sec-Fetch-Site: same-origin' -d 'opened_at=0' -D "$header_file" -o "$body_file" -w '%{http_code}' "http://127.0.0.1:${port}/truth/investigate.php")"
[[ "$status" == "422" ]]
grep -q 'Please reload the page, then take a few seconds' "$body_file"

opened_at="$(( $(date +%s) - 5 ))"
status="$(curl -sS --max-time 5 -X POST -H 'Host: bobsome1.com' -H 'Origin: https://bobsome1.com' --data-urlencode "opened_at=${opened_at}" --data-urlencode 'question[]=array-input' -D "$header_file" -o "$body_file" -w '%{http_code}' "http://127.0.0.1:${port}/truth/investigate.php")"
[[ "$status" == "422" ]]
grep -q 'Form fields must contain plain text' "$body_file"

echo "Investigation method gate passed: POST-only routes return accurate 405 responses, CSRF origin checks reject missing/null/cross-site requests, and malformed arrays stop before research."
