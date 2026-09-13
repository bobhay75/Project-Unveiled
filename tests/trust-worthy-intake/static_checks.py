#!/usr/bin/env python3
"""Source-level intake contract checks that run even when PHP CLI is absent."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
HELPER = (ROOT / "truth/lib/intake.php").read_text(encoding="utf-8")
QUESTION = (ROOT / "truth/question-submit.php").read_text(encoding="utf-8")
CHALLENGE = (ROOT / "truth/challenge-submit.php").read_text(encoding="utf-8")
CASE = (ROOT / "truth/case-000002.php").read_text(encoding="utf-8")
QUESTION_RECEIPT = (ROOT / "truth/question-received.php").read_text(encoding="utf-8")
CHALLENGE_RECEIPT = (ROOT / "truth/challenge-received.php").read_text(encoding="utf-8")
PRIVACY = (ROOT / "privacy.html").read_text(encoding="utf-8")
TRUTH_INDEX = (ROOT / "truth/index.php").read_text(encoding="utf-8")
DEPLOY = (ROOT / "scripts/deploy-public.sh").read_text(encoding="utf-8")
MIGRATION = (ROOT / "scripts/migrate-intake-permissions.php").read_text(encoding="utf-8")
HTACCESS = (ROOT / ".htaccess").read_text(encoding="utf-8")

for required in (
    "flock($lock, LOCK_EX)",
    "json_decode($raw, true, 64, JSON_THROW_ON_ERROR)",
    "Private intake storage is corrupted; no data was overwritten.",
    "max_records",
    "max_file_bytes",
    "hour_limit",
    "day_limit",
    "retention_days",
    "$retentionSeconds = $config['retention_days'] * 86400",
    "tw_intake_env_int('TW_INTAKE_RETENTION_DAYS', 180, 30, 180)",
    "$effectiveDeleteAfter = min($storedDeleteAfter, $submittedAt + $retentionSeconds)",
    "delete_after_utc",
    "HTTP_ORIGIN",
    "HTTP_REFERER",
    "HTTP_SEC_FETCH_SITE",
    "application/x-www-form-urlencoded",
    "php://input",
    "File uploads are not accepted.",
    "tw_intake_migrate_private_permissions()",
    "Private intake permission migration rejected a symlink.",
    "Private intake permission migration found a non-file path.",
    "tw_intake_assert_absent_or_regular($migrationLockPath, 'Private intake permission lock')",
    "tw_intake_is_list",
    "tw_intake_secure_open_file($lock, $lockPath, 0600, 'Private intake secret lock')",
    "tw_intake_require_file_mode($tmp, 0600, 'Private intake temporary file')",
    "tw_intake_secure_open_file($lock, $lockPath, 0600, 'Private intake queue lock')",
):
    assert required in HELPER, f"intake safeguard missing: {required}"

assert HELPER.index(
    "tw_intake_secure_open_file($created, $path, 0600, 'Private intake secret')",
) < HELPER.index(
    "tw_intake_write_all($created, bin2hex(random_bytes(32))",
), "secret bytes are written before owner-only permissions are verified"
assert HELPER.index(
    "tw_intake_secure_open_file($handle, $tmp, 0600, 'Private intake temporary file')",
) < HELPER.index(
    "tw_intake_write_all($handle, $json)",
), "private queue data is written before owner-only permissions are verified"
assert "$contentType !== 'application/x-www-form-urlencoded'" in HELPER, "multipart input is not denied"
assert "['application/x-www-form-urlencoded', 'multipart/form-data']" not in HELPER, "multipart input remains allowed"

for exact_name in (
    "question-secret.txt",
    "question-secret.txt.lock",
    "challenge-secret.txt",
    "challenge-secret.txt.lock",
    "questions.json",
    "questions.json.lock",
    "challenges.json",
    "challenges.json.lock",
):
    assert f"'{exact_name}'" in HELPER, f"permission migration omits {exact_name}"

production_migration = 'migration_php="/usr/local/bin/php"'
pre_migration = '"$migration_php" -q "$repo_root/scripts/migrate-intake-permissions.php" --pre-sync'
post_migration = '"$migration_php" -q "$repo_root/scripts/migrate-intake-permissions.php" --post-sync'
assert production_migration in DEPLOY, "real deployment does not select documented cPanel PHP"
assert "PHP_VERSION_ID < 80100" in DEPLOY, "real deployment does not require PHP 8.1 or newer"
assert '"ctype_digit"' in DEPLOY, "real deployment does not require the ctype request-validation function"
assert pre_migration in DEPLOY, "real deployment does not invoke pre-sync permission migration"
assert post_migration in DEPLOY, "real deployment does not invoke post-sync private migration"
assert DEPLOY.index(pre_migration) < DEPLOY.rindex("/usr/bin/rsync"), "pre-sync permission migration runs after public sync"
assert DEPLOY.rindex(post_migration) > DEPLOY.rindex("/usr/bin/rsync"), "post-sync sensitive-state migration is missing"
assert "'/home/bobsome1/site-private/trust-worthy'" in MIGRATION, "migration lacks deterministic production target"
assert "PROJECT_UNVEILED_DEPLOY_TEST_MODE" in MIGRATION, "migration lacks isolated CI mode"

for endpoint in (QUESTION, CHALLENGE):
    assert "tw_intake_enforce_post_request(" in endpoint, "request/body guard is not enforced"
    assert "tw_intake_append_record(" in endpoint, "locked append is not used"
    assert "tw_intake_post_scalar(" in endpoint, "scalar-only validation is not used"
    assert "delete_after_utc" in endpoint, "retention date is not recorded"
    assert "Allow: POST" in endpoint, "405 response omits Allow: POST"
    assert "json_decode((string)file_get_contents" not in endpoint, "legacy fail-open queue read remains"
    assert "file_put_contents($tmp" not in endpoint, "legacy endpoint-local replacement remains"

assert "tw_intake_assert_known_case($caseId)" in CHALLENGE, "nonexistent case IDs are not rejected"
assert "'opinion'" in CHALLENGE, "Bobinated Opinion form value is not accepted"
assert '<option value="opinion">Bobinated Opinion</option>' in CASE, "opinion challenge option disappeared"
assert "tw_intake_enforce_post_request(65536)" in QUESTION, "question body cap changed unexpectedly"
assert "tw_intake_enforce_post_request(114688)" in CHALLENGE, "challenge body cap changed unexpectedly"
for route, limit in (("question-submit.php", "65536"), ("challenge-submit.php", "114688")):
    stanza = f'<Files "{route}">\n  LimitRequestBody {limit}\n</Files>'
    assert stanza in HTACCESS, f"{route} lacks a pre-PHP Apache request-body cap"

privacy_anchor = '/privacy.html#truth-intake-privacy'
assert privacy_anchor in CASE, "challenge form does not link to the intake privacy notice"
assert privacy_anchor in QUESTION_RECEIPT, "question receipt does not link to the intake privacy notice"
assert privacy_anchor in CHALLENGE_RECEIPT, "challenge receipt does not link to the intake privacy notice"
for disclosure in (
    'id="truth-intake-privacy"',
    "optional name and email address",
    "secret-protected digest derived from the request address",
    "never more than 180 days",
    "removed lazily when a relevant intake or private Daily Desk maintenance or access operation next runs",
    "not displayed publicly",
    "or sold",
    "request early deletion",
    "owner chooses to investigate",
    "configured OpenAI Responses API",
    "asking the API not to store the response as a retrievable provider response",
    "remain in private owner-only storage under the same question-retention deadline",
    "blocks that private-question draft from publication",
    "separately authored nonprivate work",
):
    assert disclosure in PRIVACY, f"public intake privacy disclosure is incomplete: {disclosure}"

free_trial_anchor = '/privacy.html#free-truth-trial-privacy'
assert free_trial_anchor in TRUTH_INDEX, "Free Truth Trial form does not link to its privacy notice"
for disclosure in (
    'id="free-truth-trial-privacy"',
    "OpenAI Responses API",
    "store: false",
    "not to store the response as a retrievable provider response",
    "does not keep the raw question or context",
    "secret-protected request-address and question digests",
    "failure-diagnostic record",
    "not the raw messages",
    "retained for up to 180 days",
):
    assert disclosure in PRIVACY, f"Free Truth Trial privacy disclosure is incomplete: {disclosure}"

print("Trust-Worthy intake static checks passed: locked fail-closed storage, bounded requests, real cases, origin guard, and retention.")
