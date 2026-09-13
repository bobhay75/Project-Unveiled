#!/usr/bin/env python3
"""Fail closed if Daily Desk privacy/authentication invariants regress."""

from pathlib import Path
import re


ROOT = Path(__file__).resolve().parents[2]


def text(relative: str) -> str:
    return (ROOT / relative).read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise SystemExit(f"Daily Desk hardening check failed: {message}")


def main() -> None:
    combined = "\n".join(text(f"truth/daily/{name}") for name in (
        "admin-url.php",
        "desk.php",
        "investigate.php",
        "login.php",
        "logout.php",
        "publish.php",
        "refresh.php",
    ))
    require("?key=" not in combined, "query-string admin credentials remain")
    require('name="key"' not in combined, "admin credential remains in an HTML form")
    require("$_REQUEST" not in combined, "request-wide credential parsing remains")
    require("#bootstrap=" in text("truth/daily/admin-url.php"), "CLI helper does not use a fragment bootstrap")
    require("tw_admin_bootstrap_consume" in text("truth/daily/login.php"), "one-use bootstrap is not consumed")
    require("tw_require_same_origin_form_post" in text("truth/daily/investigate.php"), "investigation lacks form request guard")
    require("tw_require_same_origin_form_post" in text("truth/daily/publish.php"), "publication lacks form request guard")
    require("tw_require_same_origin_form_post" in text("truth/daily/login.php"), "login lacks form request guard")
    require("tw_require_same_origin_form_post" in text("truth/daily/logout.php"), "logout lacks form request guard")
    require("tw_require_admin_csrf" in text("truth/daily/investigate.php"), "investigation lacks CSRF enforcement")
    require("tw_require_admin_csrf" in text("truth/daily/publish.php"), "publication lacks CSRF enforcement")

    publish = text("truth/daily/publish.php")
    require("tw_daily_draft_is_private_question" in publish, "private question publication is not denied")
    require("'origin' =>" not in publish, "candidate origin can enter public JSON")
    require("'model' =>" not in publish, "model metadata can enter public JSON")
    require("'provider_response_id' =>" not in publish, "provider ID can enter public JSON")
    require("tw_recover_daily_publication_transaction" in publish, "publication lacks replay journal")
    require("tw_json_write_public" not in publish, "endpoint bypasses the recoverable publication helper")
    require("daily-last-error.json" not in combined, "obsolete raw-error path is still written by a web endpoint")
    require("TW_DAILY_PUBLICATION_FAILED" in publish, "publication failures lack a stable owner-safe code")
    require("tw_clean_text($e->getMessage()" not in publish, "raw publication exceptions can enter persistent state")

    library = text("truth/daily/lib.php")
    config = text("truth/daily/config.php")
    compatibility = text("truth/daily/compat.php")
    require("'include' => ['web_search_call.action.sources']" in library, "provider source evidence is not requested")
    require("'store' => false" in library, "private Daily Desk prompts may be retained as stored responses")
    require("$configured = curl_setopt_array($ch" in library and "if (!$configured)" in library, "provider cURL setup is not checked")
    require("CURLOPT_WRITEFUNCTION" in library and "tw_daily_capture_provider_chunk" in library, "provider response body is buffered without a hard ceiling")
    require("4194304" in library and "TW_DAILY_PROVIDER_OVERSIZE" in library, "provider response ceiling does not fail closed with a stable code")
    require("CURLOPT_RETURNTRANSFER => false" in library, "provider response bypasses the bounded write callback")
    require("tw_provider_evidence_sources" in library, "provider evidence is not extracted")
    require("not returned by provider evidence" in library, "fabricated source URLs do not fail closed")
    require("tw_daily_ai_reserve" in library and "tw_with_private_lock('daily-ai-budget'" in library, "AI budget is not atomic")
    require(library.count("tw_daily_ai_validate_ledger($state") >= 2, "AI budget ledger is not validated before reservation and reconciliation")
    require("Research budget ledger is corrupted; refusing to replace it." in library, "corrupted AI budget ledgers do not fail closed")
    require("tw_admin_bootstrap_validate_ledger" in library, "bootstrap ledger is rewritten without full schema validation")
    require(library.count("tw_admin_bootstrap_validate_ledger($state, $now)") >= 2, "bootstrap create/consume do not both fail closed on corrupt state")
    require("Daily admin bootstrap ledger is corrupted; original was preserved." in library, "corrupt bootstrap state lacks a stable fail-closed error")
    require("hash_equals($hash, $digest)" in library, "bootstrap digests are not compared in constant time")
    require("tw_daily_request_origin_is_allowed" in library, "Daily forms lack a reusable strict-origin validator")
    require("(?::443)?$/D" in library and "$urlHost !== $host" in library and "$port !== 443" in library, "Daily form gate accepts a cross-host or non-default-port origin")
    require("isset($parts['user'])" in library and "isset($parts['fragment'])" in library, "Daily form gate accepts credentialed or fragment URLs")
    require("tw_daily_measure_raw_body($maxBodyBytes)" in library and "php://input" in library, "Daily form gate trusts declared length without measuring actual bytes")
    require("$actualBytes !== (int)$lengthRaw" in library, "Daily form gate does not reconcile actual and declared body lengths")
    require("tw_json_write_mode($path, $data, 0600)" in library, "private JSON is not owner-only")
    require("function tw_json_read(" not in library, "permissive unbounded JSON reader remains")
    require("tw_daily_open_regular_file" in library, "private reads and locks lack a shared open-handle guard")
    require("stream_get_contents($handle, $maxBytes + 1)" in library, "private JSON reads are not bounded")
    require("tw_daily_stats_match($after, $pathAfter)" in library, "private JSON identity is not checked after reading")
    require("tw_daily_prepare_directory($dir, 0750" in library, "shared private directory mode is not verified")
    require("tw_daily_private_candidate_is_current" in library, "private candidate expiry is not revalidated")
    require("tw_prune_expired_private_daily_drafts" in library, "expired private draft derivatives are not pruned")

    refresh = text("truth/daily/refresh.php")
    require("admin-url.php separately" in refresh, "refresh output may expose a credential to cron logs")
    require("tw_prune_expired_private_daily_drafts()" in refresh, "cron refresh does not prune expired private draft copies")
    meat_desk = text("truth/daily/meat-desk.php")
    retired_feed_surface = "\n".join((library, config, compatibility, meat_desk, refresh))
    for retired_name in (
        "tw_fetch_feed",
        "tw_parse_feed",
        "tw_parse_feed_compat",
        "tw_parse_feed_regex",
        "tw_feed_item",
        "tw_rank_candidates",
        "tw_candidate_score",
        "tw_build_meat_queue",
        "max_items_per_feed",
        "lookback_hours",
    ):
        require(retired_name not in retired_feed_surface, f"retired RSS surface remains: {retired_name}")
    require("CURLOPT_FOLLOWLOCATION" not in retired_feed_surface, "dormant redirect-following feed client remains")
    require("'feeds'" not in config, "retired RSS endpoints remain configured")
    require("tw_build_full_meat_queue($privateDir, 18)" in refresh, "refresh is not built solely from the private/library queue")
    daily_readme = text("truth/daily/README.md")
    require("does not fetch RSS feeds" in daily_readme, "Daily documentation does not clearly exclude routine RSS intake")
    require("private user submissions" in daily_readme and "Meat Desk" in daily_readme, "Daily documentation misstates the queue sources")
    require("Free feed refresh cron" not in daily_readme, "Daily cron documentation still claims to refresh feeds")
    require("array_key_exists('delete_after_utc', $row)" in meat_desk, "invalid explicit retention dates can receive a legacy fallback")
    require("tw_daily_intake_retention_days()" in meat_desk, "Daily Desk does not honor configured intake retention")
    require("tw_daily_parse_utc_timestamp" in meat_desk, "Daily Desk accepts non-UTC or malformed intake timestamps")
    require("180 * 86400" not in meat_desk, "Daily Desk still hardcodes legacy retention")
    require("tw_private_json_read_strict($path, [], 8388608)" in meat_desk, "legacy question file is not secured through the bounded owner-only reader")
    require("file_get_contents($path)" not in meat_desk, "legacy question file bypasses the safe reader")
    require("'delete_after_utc' => gmdate('c', $deleteAt)" in meat_desk, "private candidate derivatives omit their retention deadline")
    require("tw_daily_private_candidate_is_current($candidate)" in text("truth/daily/investigate.php"), "investigation accepts stale private candidate copies")
    investigate = text("truth/daily/investigate.php")
    require("$e->getMessage()" not in investigate, "raw provider exceptions can enter private logs or editor flashes")
    require("TW_DAILY_INVESTIGATION_FAILED" in investigate, "investigation failures lack a stable owner-safe code")
    require("$errorNode['message']" not in library, "provider-supplied error text can escape the research boundary")
    require("TW_DAILY_PROVIDER_FAILED" in library, "provider failures lack a stable safe code")
    require("tw_daily_provider_private_metadata($decoded)" in library, "raw provider metadata can enter private drafts")
    require("provider_response_id_sha256" in library and "hash('sha256', $responseId)" in library, "raw provider response IDs are not reduced to a fingerprint")
    require("provider_response_id_bytes" in library and "strlen($responseId) <= 512" in library, "provider response ID metadata is not bounded")
    require("['input_tokens', 'output_tokens', 'total_tokens']" in library, "provider usage metadata is not reduced to exact token counters")
    require("'provider_response_id' => $decoded" not in library, "raw provider response ID is persisted")
    require("'provider_usage' => is_array($decoded" not in library, "raw provider usage object is persisted")
    require("is_link($privatePath)" in library, "OpenAI fallback accepts a symlink")
    require(library.count("lstat($privatePath)") >= 2, "OpenAI fallback identity is not checked across its read")
    require("fopen($privatePath, 'rb')" in library, "OpenAI fallback is not read through a verified handle")
    require("fstat($handle)" in library and "!chmod($privatePath, 0600)" in library, "OpenAI fallback handle is not secured")
    require("fchmod(" not in library, "Daily storage depends on PHP's nonexistent fchmod function")
    require("file_get_contents($privatePath)" not in library, "OpenAI fallback follows its pathname during the secret read")
    require("$size > 8192" in library and "8193" in library, "OpenAI fallback read is not size bounded")
    require("& 0777) === 0600" in library, "OpenAI fallback mode is not verified")
    key_docs = text("truth/daily/README.md")
    require(
        key_docs.index("TRUST_WORTHY_OPENAI_API_KEY")
        < key_docs.index("OPENAI_API_KEY")
        < key_docs.index("INSIDE_OF_ME_OPENAI_API_KEY")
        < key_docs.index("openai-key.txt"),
        "documented Daily key precedence is inconsistent",
    )

    migration = text("truth/daily/legacy-migration.php")
    require("fchmod(" not in migration, "Daily migration depends on PHP's nonexistent fchmod function")
    migration_cli = text("scripts/migrate-intake-permissions.php")
    daily_htaccess = text("truth/daily/.htaccess")
    require("LimitRequestBody 524288" in daily_htaccess, "Daily requests are not bounded before PHP body parsing")
    require("legacy-migration" in daily_htaccess, "legacy migration include is directly web-accessible")
    for route, body_limit in (
        ("login.php", "4096"),
        ("logout.php", "2048"),
        ("investigate.php", "8192"),
        ("publish.php", "524288"),
    ):
        route_cap = re.search(
            rf'<Files\s+"{re.escape(route)}">\s*LimitRequestBody\s+{body_limit}\s*</Files>',
            daily_htaccess,
            re.MULTILINE,
        )
        require(route_cap is not None, f"{route} Apache body cap is not aligned with its PHP request gate")
    require("tw_daily_retire_legacy_private_artifacts" in migration, "legacy sensitive-state retirement is missing")
    for exact_name in ("daily-last-error.json", "daily-draft.json", "daily-admin-token.txt"):
        require(exact_name in migration, f"legacy retirement omits {exact_name}")
    require("glob(" not in migration, "legacy retirement uses a broad path match")
    require("is_link($path)" in migration and "fstat($handle)" in migration, "legacy retirement can follow an unsafe file")
    require("Legacy Daily draft is corrupted; original was preserved." in migration, "corrupt legacy drafts are erased instead of preserved")
    require("legacy-unverified-draft" in migration, "legacy provider-unbound drafts can become publishable")
    require("unset($draft['_meta']['provider_response_id'], $draft['_meta']['provider_usage'])" in migration, "untrusted legacy provider metadata is preserved verbatim")
    require("ftruncate($handle, 0)" in migration and "fsync($handle)" in migration, "retired sensitive bytes are not erased before unlink")
    require("tw_daily_retire_legacy_private_artifacts($privateDir)" in migration_cli, "deployment CLI does not run Daily legacy retirement")
    require("$phase === '--post-sync'" in migration_cli, "destructive Daily retirement can run before Release 16 is live")
    deploy_script = text("scripts/deploy-public.sh")
    require("--pre-sync" in deploy_script and "--post-sync" in deploy_script, "deployment does not phase private migration around public sync")
    require("tw_daily_retire_legacy_private_artifacts($privateDir)" in refresh, "runtime refresh does not complete Daily legacy retirement")
    require("tw_daily_prune_expired_candidate_derivatives($privateDir)" in migration_cli, "deployment migration does not prune expired private candidate derivatives")
    require("tw_daily_prune_expired_candidate_derivatives($privateDir)" in refresh, "refresh does not prune the prior private candidate cache")
    require("tw_daily_prune_expired_candidate_derivatives($privateDir)" in text("truth/daily/desk.php"), "owner desk does not prune stale private candidate copies")
    require("tw_daily_prune_expired_candidate_derivatives($privateDir)" in text("truth/daily/investigate.php"), "investigation does not prune stale private candidate copies")
    require("daily-candidates.lock" in migration, "candidate derivative pruning does not use the canonical queue lock")
    require("tw_with_private_lock('daily-candidates'" in refresh, "refresh does not install the candidate queue under the canonical queue lock")
    require("Daily candidate queue is corrupted; original was preserved." in migration, "corrupt candidate derivatives can be reset")
    print("Daily Desk static checks passed: private auth, source binding, queue alignment, transaction recovery, and cost guard invariants.")


if __name__ == "__main__":
    main()
