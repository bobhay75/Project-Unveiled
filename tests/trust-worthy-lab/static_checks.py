#!/usr/bin/env python3
"""Small dependency-free checks for the public static build."""

from html.parser import HTMLParser
import json
from pathlib import Path
from typing import List, Optional, Tuple
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parents[2]
DIST = ROOT / "truth" / "lab"


class PageAudit(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.ids: List[str] = []
        self.references: List[str] = []
        self.h1_count = 0
        self.title_count = 0

    def handle_starttag(self, tag: str, attrs: List[Tuple[str, Optional[str]]]) -> None:
        attributes = dict(attrs)
        if attributes.get("id"):
            self.ids.append(str(attributes["id"]))
        if attributes.get("href"):
            self.references.append(str(attributes["href"]))
        if attributes.get("src"):
            self.references.append(str(attributes["src"]))
        if tag == "h1":
            self.h1_count += 1
        if tag == "title":
            self.title_count += 1


page = DIST / "index.html"
assert page.is_file(), "dist/index.html is missing"
assert (DIST / "styles.css").is_file(), "styles.css is missing"
assert (DIST / "app.js").is_file(), "app.js is missing"
assert (DIST / "research-sweep.js").is_file(), "research-sweep.js is missing"
assert (DIST / "observer.js").is_file(), "observer.js is missing"
assert (DIST / "release-manifest.json").is_file(), "release manifest is missing"
assert (DIST / "404.html").is_file(), "custom 404 page is missing"
assert (DIST / ".well-known" / "security.txt").is_file(), "security contact is missing"
status = json.loads((DIST / "status.json").read_text(encoding="utf-8"))
assert status["release"] == 16, "public status release is incorrect"
assert status["observer"] == "trust-worthy-observer-v6", "public observer release is incorrect"
assert status["release_manifest"] == "/truth/lab/release-manifest.json", "public release-manifest route is incorrect"
assert status["rollback_release"] == 15, "last-known-good rollback release is incorrect"
assert status["rollback_source_commit"] == "9c4faa71e9c4955c1e597b2b3f9c8a861cac3bf1", "release-16 rollback commit is incorrect"
assert status["unknown_route_status"] == 404, "unknown routes must be declared as 404"
assert status["expected_denials"]["OPTIONS"] == 405, "unsupported methods must remain denied"
robots = DIST / "robots.txt"
sitemap = DIST / "sitemap.xml"
assert robots.is_file() and "Sitemap: https://bobsome1.com/truth/lab/sitemap.xml" in robots.read_text(encoding="utf-8"), "robots.txt is missing or points at the wrong sitemap"
assert sitemap.is_file() and "https://bobsome1.com/truth/lab/" in sitemap.read_text(encoding="utf-8"), "sitemap.xml is missing the canonical public URL"
share_card = DIST / "share-card.png"
assert share_card.is_file(), "Facebook share card is missing"
assert share_card.read_bytes().startswith(b"\x89PNG\r\n\x1a\n"), "share card is not a PNG"
manifest = json.loads((DIST / "manifest.webmanifest").read_text(encoding="utf-8"))
assert manifest["name"].startswith("Project Unveiled"), "manifest brand is incorrect"
for icon in manifest["icons"]:
    assert icon["src"].startswith("/truth/lab/"), f"manifest icon escaped the lab scope: {icon['src']}"
    assert (DIST / icon["src"][len("/truth/lab/"):]).is_file(), f"manifest icon is missing: {icon['src']}"

audit = PageAudit()
audit.feed(page.read_text(encoding="utf-8"))
assert audit.h1_count == 1, f"expected one h1, found {audit.h1_count}"
assert audit.title_count == 1, f"expected one title, found {audit.title_count}"
assert len(audit.ids) == len(set(audit.ids)), "duplicate HTML ids found"

for raw in audit.references:
    parsed = urlsplit(raw)
    if raw.startswith("#"):
        continue
    if parsed.scheme:
        assert parsed.scheme in {"http", "https", "mailto", "tel"}, f"unsafe external scheme: {raw}"
        if parsed.scheme in {"http", "https"}:
            assert parsed.netloc, f"external HTTP(S) reference has no host: {raw}"
        continue
    if parsed.netloc:
        raise AssertionError(f"scheme-relative external reference is not allowed: {raw}")
    if parsed.path.startswith("/truth/lab/"):
        target = DIST / parsed.path[len("/truth/lab/"):]
    elif parsed.path.startswith("/"):
        target = ROOT / parsed.path.lstrip("/")
    else:
        local_path = parsed.path[2:] if parsed.path.startswith("./") else parsed.path
        target = DIST / local_path
    assert target.exists(), f"broken local reference: {raw}"

text = page.read_text(encoding="utf-8")
assert 'http-equiv="Content-Security-Policy"' in text, "content security policy is missing"
assert "form-action 'none'" in text, "native form submission must remain blocked"
assert 'name="referrer" content="no-referrer"' in text, "referrer privacy policy is missing"
for required in (
    "pre-research map",
    "Evidence bench",
    "Completeness is not a truth percentage or source authentication",
    "Watch-Dawg adversarial hygiene",
    "Open Moon adversarial file",
    "Open aircraft-trails file",
    "Documented in this dossier",
    "Copy canonical JSON",
    "Potentially independent — explain",
    "PROJECT UNVEILED",
    "Known record stays free",
    "Claim Triage",
    "Deep Investigation",
    "Hyper-Deep Case File",
    "Commission the test—not the verdict",
    "Have a disputed claim?",
    "Share public lab on Facebook",
    "No guaranteed finding",
    "A request is not a purchase or reservation",
    "private PayPal link for that written order",
    "private PayPal payment link matching that written order",
    "written start notice receives a full refund",
    "No external search runs until you press “Run Source Sweep.”",
    "When the claim has usable terms, higher-overlap matches appear first.",
    "Lower-overlap metadata remains inspectable in the audit drawer instead of disappearing.",
    "Namecheap shared hosting",
    "Search hits remain unreviewed leads until the underlying record is opened and tested",
    "Starts external transmission",
    "A saved case stays on this device unless",
    "title-only similarities remain separate possible duplicates",
    "A title, snippet, index ranking, patent, archive upload, or repeated URL is not proof",
    "sends only the public lab URL—not the draft case",
    "Bobsome1 does not sell intake data",
    "ordinary email, not an encrypted or legally privileged channel",
    "A customer may request access or deletion by email",
    "Local receipts are not a trust authority",
    "Release 16 provenance",
    "Release 15 · tested known-good",
    "Open machine-readable manifest",
    "fails closed on any difference",
    "not a digital signature, proof of authorship, or external timestamp",
    "provider identities and queries are rebuilt from the fixed plan",
    "private submitted material require written permission",
    "complete evidence entries",
    "You be the judge",
    "912-701-4008",
    "Serving Branson and the Table Rock Lake area",
    "Bobsome1 home",
    "Media + IT services",
):
    assert required in text, f"required safety or conversion text missing: {required}"
assert "chatgpt.site" not in text, "public lab navigation must remain on the owned bobsome1.com surface"

for element_id in (
    "coverage-form", "coverage-scope", "coverage-cutoff", "coverage-stop", "coverage-gaps",
    "source-target", "source-independence", "source-incentives", "source-custody", "source-falsifier",
    "report-hypotheses", "report-incentives", "report-forensics", "report-gaps",
    "open-atmosphere-case", "copy-receipt-json",
    "research-sweep", "run-research", "cancel-research", "research-status",
    "research-query-count", "research-result-count", "research-failure-count",
    "research-query-list", "copy-search-receipt", "research-results",
    "claim-sync-warning", "print-record-meta", "archived-receipt", "archived-receipt-hash",
    "archived-receipt-status", "copy-archived-hash", "copy-archived-json", "sweep-transmission",
    "observer", "observer-title", "run-observer", "observer-last-run", "observer-status",
    "observer-state", "observer-pass-count", "observer-failure-count", "observer-results",
    "release-proof", "release-proof-state", "release-root", "release-transform-state",
):
    assert f'id="{element_id}"' in text, f"missing adversarial workflow control: {element_id}"

for safeguard in ("primary", "independent", "counter", "provenance", "incentives", "custody", "falsifier", "coverage"):
    assert text.count(f'data-check="{safeguard}"') == 1, f"missing or duplicate safeguard: {safeguard}"

# The results workbench is intentionally hidden until a claim is analyzed.
# Paid offers must remain outside it so they are visible on the first visit.
results_start = text.index('<section class="result-shell" id="results"')
results_end = text.index('<section class="proof-desk"', results_start)
hidden_results_markup = text[results_start:results_end]
assert text.index('id="investigation-offers"') < results_start, "paid offers are not visible before analysis"
for offer_id in ("triage-link", "deep-link", "hyper-link"):
    assert f'id="{offer_id}"' not in hidden_results_markup, f"{offer_id} is trapped in the hidden results area"

for forbidden in (
    "payment integration pending",
    "guaranteed conclusion",
    "automated verdict",
    "we prove every claim",
    "paypal.me",
    "supported, contradicted, mixed",
    "Verified record",
    "delivered through ChatGPT Sites",
):
    assert forbidden.lower() not in text.lower(), f"forbidden product claim found: {forbidden}"

scripts = [reference for reference in audit.references if reference.endswith(".js")]
assert scripts[-3:] == ["./research-sweep.js", "./app.js", "./observer.js"], "observer must load after the application"

for form_id in ("claim-form", "coverage-form", "evidence-form"):
    form_tag = next(tag for tag in text.split(">") if f'id="{form_id}"' in tag)
    assert "data-local-only" in form_tag, f"{form_id} is missing its local-only boundary"

claim_tag = next(tag for tag in text.split(">") if 'id="claim-input"' in tag)
assert ' name=' not in claim_tag.lower(), "claim textarea must not natively submit private text if JavaScript fails"

app_source = (DIST / "app.js").read_text(encoding="utf-8")
research_source = (DIST / "research-sweep.js").read_text(encoding="utf-8")
observer_source = (DIST / "observer.js").read_text(encoding="utf-8")
lab_htaccess = (DIST / ".htaccess").read_text(encoding="utf-8")
assert "trustedRegistryRecordForSaved" in app_source, "reviewed local records lack an immutable registry gate"
assert "Local review claims are never trusted" in app_source, "forged review downgrade is missing"
assert "DISCOVERY LEAD · NOT INSPECTED EVIDENCE" in app_source, "automated leads are not explicitly bounded"
assert "low_overlap_metadata_families" in app_source, "lower-overlap families are missing from the canonical audit receipt"
assert "first 1,000 cleaned characters of its provider description" in app_source, "the visible screen omits its provider-description limit"
assert "assessResultRelevance" in research_source, "Source Sweep lacks its deterministic display screen"
assert "claim-term-overlap-v2" in research_source, "Source Sweep screening policy is not named"
assert "requiredAnchorMatches" in research_source, "Source Sweep lacks its topic-anchor precision gate"
assert '.normalize("NFKC")' in research_source, "Source Sweep lacks canonical Unicode normalization"
assert "screeningExcerpt" in research_source, "the bounded screening input is not retained for audit"
assert "RewriteCond %{HTTPS} !=on [OR]" in lab_htaccess, "lab child rewrites do not enforce HTTPS"
assert "RewriteCond %{HTTP_HOST} !^bobsome1\\.com$ [NC]" in lab_htaccess, "lab child rewrites do not enforce the apex host"
assert "RewriteRule ^ https://bobsome1.com%{REQUEST_URI} [R=301,L,NE]" in lab_htaccess, "lab canonical redirect must use a fixed owned host"
assert 'behavior: "smooth"' not in app_source, "scripted smooth scrolling must respect the reduced-motion baseline"
assert "open.innerHTML" not in app_source, "saved-case content must not be injected as HTML"
assert "eval(" not in app_source and "eval(" not in research_source and "eval(" not in observer_source, "dynamic code execution is forbidden"
assert "credentials: \"omit\"" in observer_source, "observer requests must omit credentials"
assert "unknownGet.status === 404" in observer_source and "unknownHead.status === 404" in observer_source, "observer must detect soft 404 responses for GET and HEAD"
assert 'const deniedMethods = ["OPTIONS", "POST", "PUT", "PATCH", "DELETE"]' in observer_source, "observer must test every unsupported method"
assert "releaseProvenanceCheck" in observer_source, "observer must verify the public release manifest"
assert "exact-owned-static-v1" in observer_source, "observer must bind the exact delivery policy"
assert "files.every(file => file?.delivery?.mode === \"exact\")" in observer_source, "observer must reject non-exact asset delivery modes"
assert "normalizeKnownDeliveryText" not in observer_source, "the canonical host must not normalize unexpected delivery changes"
for forbidden_observer_action in ("reverse lookup", "whois", "geolocat", "counter-hack", "hack back"):
    assert forbidden_observer_action not in observer_source.lower(), f"unsafe observer action found: {forbidden_observer_action}"
for host in (
    "api.crossref.org", "api.openalex.org", "www.ebi.ac.uk",
    "archive.org", "www.federalregister.gov",
):
    assert host in research_source, f"declared source provider missing: {host}"

styles = (DIST / "styles.css").read_text(encoding="utf-8")
assert ".result-shell[hidden]" in styles, "print styles may expose a hidden stale result"
assert "attr(href)" in styles, "printed reports must expose their source URLs"
assert ".query-receipt:not([open])" in styles, "printed reports must expose the exact query receipt"
assert ".relevance-audit:not([open])" in styles, "printed reports must expose lower-overlap metadata"

truth_home = (ROOT / "truth" / "index.php").read_text(encoding="utf-8")
assert 'href="/truth/lab/">Evidence Lab</a>' in truth_home, "Truth on Trial navigation does not expose the Evidence Lab"
assert 'id="lab"' in truth_home and "LIVE EVIDENCE WORKBENCH · RELEASE 16" in truth_home, "Truth on Trial lacks the integrated release card"
assert 'action="/truth/investigate.php" method="post"' in truth_home, "the existing Truth Trial submission contract changed"
assert all(f"/truth/case-00000{number}.php" in truth_home for number in range(1, 5)), "an existing Truth Trial route disappeared"

root_sitemap = (ROOT / "sitemap.xml").read_text(encoding="utf-8")
assert "https://bobsome1.com/truth/lab/" in root_sitemap, "the canonical sitemap omits the Evidence Lab"
assert "https://bobsome1.com/truth/lab/</loc><lastmod>2026-09-13</lastmod>" in root_sitemap, "the canonical sitemap has a stale Evidence Lab date"
assert "https://bobsome1.com/truth/</loc><lastmod>2026-09-13</lastmod>" in root_sitemap, "the Truth on Trial sitemap date is stale"

lab_htaccess = (DIST / ".htaccess").read_text(encoding="utf-8")
for required in ("ErrorDocument 404 /truth/lab/404.html", "R=405", "Content-Security-Policy", "X-Frame-Options \"DENY\""):
    assert required in lab_htaccess, f"lab hosting contract is missing: {required}"

root_htaccess = (ROOT / ".htaccess").read_text(encoding="utf-8")
assert "md|py|ya?ml" in root_htaccess, "internal source extensions are not denied"
assert "(?:docs|scripts|tests)" in root_htaccess, "internal source directories are not denied"
assert "RewriteCond %{HTTP_HOST} !^bobsome1\\.com$ [NC]" in root_htaccess, "root canonical redirect accepts an alternate Host"
assert "RewriteRule ^ https://bobsome1.com%{REQUEST_URI} [R=301,L,NE]" in root_htaccess, "root canonical redirect must use the fixed owned host"
for required in (
    "Content-Security-Policy",
    "object-src 'none'",
    "frame-ancestors 'self'",
    "form-action 'self'",
    "Header always unset X-Powered-By",
    "X-Permitted-Cross-Domain-Policies",
    "PU_LAB_SCOPE",
    "PU_READ_ONLY_METHOD_DENIED",
    "RewriteCond %{REQUEST_FILENAME} -f",
    "RewriteCond %{REQUEST_FILENAME} -d",
    "RewriteRule ^truth/lib(?:/|$) - [F,L,NC]",
):
    assert required in root_htaccess, f"root hosting hardening is missing: {required}"
assert root_htaccess.count("RewriteCond %{REQUEST_URI} !^/(?:owner|unveiltheinsideofme)(?:/|$) [NC]") == 2, "read-only method rules must preserve authenticated application routing"
assert root_htaccess.count("RewriteCond %{REQUEST_URI} !^/truth/lab(?:/|$) [NC]") == 2, "root method rules must delegate Evidence Lab denials to its child header policy"

for protected_route in ("owner", "unveiltheinsideofme"):
    protected_htaccess = (ROOT / protected_route / ".htaccess").read_text(encoding="utf-8")
    expected_password_file = "/home/bobsome1/.htpasswds/public_html/{}/passwd".format(protected_route)
    assert expected_password_file in protected_htaccess, "{} uses the wrong cPanel password-file path".format(protected_route)
    assert "/home/bobsocdw/" not in protected_htaccess, "{} retains the retired cPanel account path".format(protected_route)

daily_htaccess = (ROOT / "truth" / "daily" / ".htaccess").read_text(encoding="utf-8")
for helper in ("compat", "config", "lib", "meat-desk", "queue-rotation", "traditions-of-men", "trial-filter"):
    assert helper in daily_htaccess, f"daily include-only module remains publicly routable: {helper}.php"
assert "Require all denied" in daily_htaccess, "daily include-only modules are not denied"

root_home = (ROOT / "index.html").read_text(encoding="utf-8")
assert 'href="/truth/today.php">Today\'s Trial</a>' in root_home, "Today's Truth Trial has no public incoming link"

health_source = (ROOT / "truth" / "health.php").read_text(encoding="utf-8")
for required in ("['GET', 'HEAD']", "http_response_code(405)", "Allow: GET, HEAD", "method_not_allowed", "truth-on-trial", "PHP_VERSION_ID >= 80100", "ctype_digit", "mb_check_encoding", "mb_strlen", "mb_substr", "tw_openai_key_readonly()", "tw_question_secret_readonly()", "tw_ai_private_storage_ready"):
    assert required in health_source, f"health method/privacy contract is missing: {required}"
for mutating_health_call in ("tw_openai_key()", "tw_question_secret()", "tw_migrate_ai_private_storage()"):
    assert mutating_health_call not in health_source, f"unauthenticated health check invokes mutating storage helper: {mutating_health_call}"
for forbidden in ("curl_enabled", "private_storage_exists", "openai_key_configured", "reasoning_effort", "daily_request_cap", "per_ip_daily_cap", "max_output_tokens", "max_web_search_calls"):
    assert forbidden not in health_source, f"health endpoint exposes implementation detail: {forbidden}"

cia_case = (ROOT / "truth" / "case-000004.php").read_text(encoding="utf-8")
for stale in (
    "https://www.cia.gov/readingroom/document/cia-rdp82b00421r000100020039-7",
    "https://www.cia.gov/readingroom/docs/DOC_0001262737.pdf",
):
    assert stale not in cia_case, f"stale CIA citation remains: {stale}"
assert "web.archive.org/web/20250305233503id_" in cia_case, "archived CIA policy record is missing"
assert "archive.org/details/CIA-Family-Jewels/page/n20/mode/2up" in cia_case, "archived Project MOCKINGBIRD record is missing"

root_security = (ROOT / ".well-known" / "security.txt").read_bytes()
assert root_security == (DIST / ".well-known" / "security.txt").read_bytes(), "root and release security contacts differ"

deployment = (ROOT / ".cpanel.yml").read_text(encoding="utf-8")
deploy_script = (ROOT / "scripts" / "deploy-public.sh").read_text(encoding="utf-8")
public_manifest = (ROOT / "deployment" / "public-files.txt").read_text(encoding="utf-8").splitlines()
retired_paths = (ROOT / "deployment" / "retired-public-paths.txt").read_text(encoding="utf-8").splitlines()
assert "./scripts/deploy-public.sh" in deployment, "cPanel deployment does not use the guarded public sync"
assert "/usr/bin/rsync" not in deployment, "cPanel configuration must not bypass the guarded deployment script"
for required in ('deployment/public-files.txt', '--files-from="$public_manifest"', "--no-implied-dirs", "--prune-empty-dirs"):
    assert required in deploy_script, f"guarded public sync is missing: {required}"
assert "--delete" not in deploy_script, "guarded public sync must not broadly delete public_html"
assert public_manifest == sorted(set(public_manifest), key=lambda item: item.encode("utf-8")), "public deployment manifest is not unique and byte-order sorted"
assert "index.html" in public_manifest and "truth/lab/index.html" in public_manifest, "public deployment manifest lacks required entry points"
for retired_asset in ("unveiltheinsideofme/visual-approval.css", "unveiltheinsideofme/visual-approval.js"):
    assert retired_asset in retired_paths, f"historically removed public asset is not retired: {retired_asset}"
tracker_pages = []
for path in public_manifest:
    assert not path.startswith((".github/", "deployment/", "docs/", "scripts/", "tests/")), f"internal directory entered public deployment manifest: {path}"
    assert not path.lower().endswith((".md", ".py", ".yml", ".yaml", ".zip", ".sql", ".log")), f"internal file entered public deployment manifest: {path}"
    if path.lower().endswith((".html", ".php")):
        source = (ROOT / path).read_text(encoding="utf-8")
        if "/project-unveiled-analytics/tracker.js?v=" in source:
            tracker_pages.append(path)
            assert "/project-unveiled-analytics/tracker.js?v=5" in source, f"stale analytics cache token remains in {path}"
assert tracker_pages, "no public analytics tracker references were verified"

owner_route_contracts = {
    "owner/funnel-servant/command-center.php": "'/owner/funnel-servant/?tab=community'",
    "owner/funnel-servant/campaign-studio.php": 'href="/owner/funnel-servant/?tab=activity"',
    "owner/funnel-servant/search.php": 'href="/owner/funnel-servant/?tab=drafts"',
}
for path, expected_route in owner_route_contracts.items():
    assert expected_route in (ROOT / path).read_text(encoding="utf-8"), f"owner route is not canonical in {path}"

for file_name in ("index.html", "app.js", "observer.js", "status.json", "robots.txt", "sitemap.xml"):
    public_text = (DIST / file_name).read_text(encoding="utf-8")
    assert "trust-worthy-public-lab.thebobsomest1.chatgpt.site" not in public_text, f"stale hosted origin remains in {file_name}"

for path in DIST.rglob("*"):
    if path.is_file() and path.suffix.lower() not in {".png"}:
        assert "kcmc" not in path.read_text(encoding="utf-8", errors="ignore").lower(), f"KCMC crossed into Trust-Worthy: {path.name}"

print(f"Static checks passed: {len(audit.ids)} unique ids, {len(audit.references)} references")
