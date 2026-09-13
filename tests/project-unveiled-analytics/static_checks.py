#!/usr/bin/env python3

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LIB = (ROOT / "project-unveiled-analytics/lib.php").read_text(encoding="utf-8")
COLLECTOR = (ROOT / "project-unveiled-analytics/collect.php").read_text(encoding="utf-8")
DASHBOARD = (ROOT / "project-unveiled-analytics/index.php").read_text(encoding="utf-8")
TRACKER = (ROOT / "project-unveiled-analytics/tracker.js").read_text(encoding="utf-8")
HTACCESS = (ROOT / "project-unveiled-analytics/.htaccess").read_text(encoding="utf-8")
ROOT_HTACCESS = (ROOT / ".htaccess").read_text(encoding="utf-8")
PRIVACY = (ROOT / "privacy.html").read_text(encoding="utf-8")
MIGRATION = (ROOT / "scripts/migrate-intake-permissions.php").read_text(encoding="utf-8")
DEPLOY = (ROOT / "scripts/deploy-public.sh").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise SystemExit(f"Analytics static check failed: {message}")


allowlist_match = re.search(
    r"function pu_analytics_allowed_events\(\): array\s*\{\s*return \[(.*?)\];\s*\}",
    LIB,
    re.S,
)
require(allowlist_match is not None, "collector event allowlist was not found")
allowed_events = set(re.findall(r"'([a-z0-9_]+)'", allowlist_match.group(1)))

emitted_events = set()
tracker_references = []
for path in ROOT.rglob("*"):
    if not path.is_file() or path.suffix.lower() not in {".html", ".php", ".js"}:
        continue
    text = path.read_text(encoding="utf-8")
    emitted_events.update(re.findall(r'data-pu-event=["\']([a-z0-9_]+)["\']', text))
    tracker_references.extend(re.findall(r'/project-unveiled-analytics/tracker\.js\?v=([^"\' ]+)', text))

emitted_events.update(re.findall(r"send\('([a-z0-9_]+)'", TRACKER))
emitted_events.update(re.findall(r"eventName = '([a-z0-9_]+)'", TRACKER))
emitted_events.update(re.findall(r"setAttribute\('data-pu-event', '([a-z0-9_]+)'\)", TRACKER))
require("send(`scroll_${mark}`)" in TRACKER, "scroll-depth event emission changed without updating the check")
emitted_events.update({"scroll_25", "scroll_50", "scroll_75", "scroll_90"})

require(emitted_events <= allowed_events, f"emitted events missing from collector allowlist: {sorted(emitted_events - allowed_events)}")
require(allowed_events <= emitted_events, f"stale collector events are not emitted anywhere: {sorted(allowed_events - emitted_events)}")
require(tracker_references and set(tracker_references) == {"5"}, "public pages do not all use the hardened tracker cache token")

for token in [
    "pu_analytics_request_origin_is_allowed(true)",
    "pu_analytics_read_request_body",
    "pu_analytics_parse_payload",
    "pu_analytics_consume_rate",
    "pu_analytics_append_event",
    "CONTENT_TYPE",
]:
    require(token in COLLECTOR, f"collector no longer invokes {token}")

for token in [
    "HTTP_SEC_FETCH_SITE",
    "HTTP_SEC_FETCH_DEST",
    "CONTENT_LENGTH",
    "stream_get_contents($stream, $maxBytes + 1)",
    "JSON_THROW_ON_ERROR",
    "max_daily_events",
    "max_daily_bytes",
    "retention_days",
    "pu_analytics_assert_regular_or_absent",
    "pu_analytics_assert_open_regular",
    "pu_analytics_event_file_digest",
    "pu_analytics_metadata_integrity",
    "pu_analytics_migrate_private_storage",
    "pu_analytics_sanitize_retained_event_files",
    "migration_max_bytes",
    "parse_str($raw, $parsed)",
    "HTTP_TRANSFER_ENCODING",
    "Search text is not accepted by analytics.",
    "referrer must contain only a website name",
    "str_contains($target, '?')",
    "rate-limits.json",
    "hash_hmac('sha256'",
    "REMOTE_ADDR",
]:
    require(token in LIB, f"shared hardening control is missing: {token}")

require("['t' => gmdate('c', $now)] + $payload" in LIB, "collector record path no longer uses the validated payload")
require("'ip' =>" not in LIB and "'ip_address' =>" not in LIB, "raw IP field appears in stored analytics")
require('<Files "collect.php">' in HTACCESS and "LimitRequestBody 32768" in HTACCESS, "collector Apache hard body cap is missing")
require('<Files "index.php">' in HTACCESS and "LimitRequestBody 2048" in HTACCESS, "dashboard Apache form body cap is missing")
require("pu_analytics_env_int('PU_ANALYTICS_MAX_BODY_BYTES', 8192, 1024, 32768)" in LIB, "collector app default/hard body limits drifted from Apache")
require('Files "lib.php"' in HTACCESS and "Require all denied" in HTACCESS, "analytics helper is directly accessible")
require("project-unveiled-analytics/lib\\.php" in ROOT_HTACCESS, "root defense-in-depth denial for analytics helper is missing")

require("navigator.doNotTrack === '1'" in TRACKER, "Do Not Track opt-out was removed")
require("navigator.globalPrivacyControl === true" in TRACKER, "Global Privacy Control opt-out was removed")
require("return clean(url.hostname, 240);" in TRACKER, "referrer is not restricted to hostname")
require("url.hostname + url.pathname" not in TRACKER, "referrer path is still collected")
require("send('search_use');" in TRACKER, "search-use event is missing")
require("send('search_use'," not in TRACKER, "free-text search input is still sent to analytics")
require("utm_term" not in TRACKER, "UTM search terms are still collected")
require("privacySafeTarget" in TRACKER, "click targets are not passed through privacy normalization")
require("url.search" not in TRACKER and "url.hash" not in TRACKER, "target normalization retains URL query or fragment data")
require("Search text and <code>utm_term</code> values are not collected." in PRIVACY, "public privacy notice does not disclose search-text minimization")
require("30-second engagement" in PRIVACY and "scroll-depth thresholds" in PRIVACY, "public privacy notice omits engagement measurements")
require("whether chapter search was used (not the search text)" in PRIVACY, "public privacy notice omits minimized search-use measurement")
require("filter_var($payload['referrer'], FILTER_VALIDATE_IP)" in LIB, "collector does not reject IP-literal referrers")
require("IP-literal referrers and external link destinations are rejected" in PRIVACY, "public privacy notice misstates IP-literal analytics handling")
require("Files older than 100 days are retired" in PRIVACY, "public privacy notice lacks the bounded retention period")

for token in [
    "pu_analytics_load_dashboard_config($configFile)",
    "pu_analytics_dashboard_post(2048)",
    "pu_analytics_consume_rate(",
    "pu_analytics_select_event_files(",
    "pu_analytics_visit_event_files(",
    "pu_analytics_prune_event_files($dataDir, time()",
    "pu_analytics_sanitize_retained_event_files($dataDir, $limits)",
    "pu_analytics_csv_cell",
    "name=\"action\" value=\"logout\"",
    "hash_equals",
]:
    require(token in DASHBOARD, f"dashboard hardening control is missing: {token}")

require("?logout=1" not in DASHBOARD, "dashboard logout remains a CSRF-able GET")
require("session.use_only_cookies" in DASHBOARD and "session.use_trans_sid" in DASHBOARD, "dashboard session URL transport is not disabled")
require("if (!@session_start())" in DASHBOARD, "dashboard does not fail closed when session storage fails")
require("glob($dataDir" not in DASHBOARD, "dashboard returned to an unbounded glob/read path")
require("file_get_contents($file)" not in DASHBOARD, "dashboard returned to whole-file event reads")

require("'/home/bobsome1/site-private/project-unveiled-analytics'" in MIGRATION, "deployment migration lacks the exact private analytics root")
require("PU_ANALYTICS_MIGRATION_TEST_DIR" in MIGRATION, "analytics migration lacks an isolated behavior-test override")
require("pu_analytics_migrate_private_storage($analyticsDir)" in MIGRATION, "deployment CLI does not migrate persisted analytics privacy fields")
pre_migration = '"$migration_php" -q "$repo_root/scripts/migrate-intake-permissions.php" --pre-sync'
post_migration = '"$migration_php" -q "$repo_root/scripts/migrate-intake-permissions.php" --post-sync'
require(pre_migration in DEPLOY and post_migration in DEPLOY, "private analytics migration is not phased around public sync")
require(DEPLOY.index(pre_migration) < DEPLOY.rindex("/usr/bin/rsync"), "analytics pre-sync migration does not run before public sync")
require(DEPLOY.rindex(post_migration) > DEPLOY.rindex("/usr/bin/rsync"), "analytics post-sync migration does not run after public sync")

manifest = (ROOT / "deployment/public-files.txt").read_text(encoding="utf-8").splitlines()
require("project-unveiled-analytics/.htaccess" in manifest, "analytics Apache guard is absent from deployment manifest")
require("project-unveiled-analytics/lib.php" in manifest, "analytics helper is absent from deployment manifest")

print(
    "Project Unveiled analytics static checks passed: "
    f"{len(allowed_events)} collector events, strict request/privacy/storage/dashboard controls."
)
