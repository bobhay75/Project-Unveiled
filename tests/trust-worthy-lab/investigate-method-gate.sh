#!/usr/bin/env bash
set -euo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

grep -Fq 'Header always set Allow "GET, HEAD"' "$repo_dir/truth/lab/.htaccess"
for endpoint in truth/question-submit.php truth/challenge-submit.php truth/daily/investigate.php truth/daily/publish.php; do
  grep -Fq "header('Allow: POST')" "$repo_dir/$endpoint"
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

status="$(curl -sS --max-time 5 -X POST -H 'Host: bobsome1.com' -D "$header_file" -o "$body_file" -w '%{http_code}' "http://127.0.0.1:${port}/truth/investigate.php")"
[[ "$status" == "200" ]]
grep -q 'Please take a few seconds to review your question' "$body_file"

echo "HTTP method gate passed: protected GET, HEAD, OPTIONS, PUT, PATCH, and DELETE requests return 405 with accurate Allow headers; bounded POST reaches the form guard."
