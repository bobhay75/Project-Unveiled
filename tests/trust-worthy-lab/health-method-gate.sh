#!/usr/bin/env bash
set -euo pipefail

if ! command -v php >/dev/null 2>&1; then
  echo "Health method gate skipped: PHP is unavailable in this environment."
  exit 0
fi

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
port=18716
scratch_dir="$(mktemp -d)"
private_dir="$scratch_dir/private"
body_file="$scratch_dir/body"
header_file="$scratch_dir/headers"
server_log="$scratch_dir/server.log"
server_pid=""

cleanup() {
  if [[ -n "$server_pid" ]]; then
    kill "$server_pid" >/dev/null 2>&1 || true
    wait "$server_pid" 2>/dev/null || true
  fi
  rm -rf "$scratch_dir"
}
trap cleanup EXIT

OPENAI_API_KEY="health-gate-placeholder" \
TW_PRIVATE_DIR_OVERRIDE="$private_dir" \
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

status="$(curl -sS --max-time 5 -D "$header_file" -o "$body_file" -w '%{http_code}' "http://127.0.0.1:${port}/truth/health.php")"
[[ "$status" == "503" ]]
test ! -e "$private_dir"
grep -qi '^Cache-Control:.*no-store' "$header_file"
grep -qi '^X-Robots-Tag:.*noindex' "$header_file"
grep -qi '^Content-Security-Policy:.*default-src.*none' "$header_file"
if grep -qi '^X-Powered-By:' "$header_file"; then
  echo "Health endpoint exposed X-Powered-By." >&2
  exit 1
fi
grep -q '"service": "truth-on-trial"' "$body_file"
grep -Eq '"status": "(ready|unavailable)"' "$body_file"
if grep -Eqi 'php|curl_enabled|private_storage|openai_key|model|reasoning_effort|request_cap|max_output_tokens|max_web_search' "$body_file"; then
  echo "Health endpoint exposed internal diagnostics." >&2
  exit 1
fi

status="$(curl -sS --max-time 5 -I -o "$header_file" -w '%{http_code}' "http://127.0.0.1:${port}/truth/health.php")"
[[ "$status" == "503" ]]
test ! -e "$private_dir"

for method in OPTIONS POST PUT PATCH DELETE; do
  status="$(curl -sS --max-time 5 -X "$method" -D "$header_file" -o "$body_file" -w '%{http_code}' "http://127.0.0.1:${port}/truth/health.php")"
  [[ "$status" == "405" ]]
  grep -qi '^Allow: GET, HEAD' "$header_file"
  grep -q '"error":"method_not_allowed"' "$body_file"
  test ! -e "$private_dir"
done

echo "Health method gate passed: minimal readiness output, no implementation details, and unsupported methods return 405."
