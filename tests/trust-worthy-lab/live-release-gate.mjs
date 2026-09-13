import assert from "node:assert/strict";
import fs from "node:fs";
import {
  LAB_PATH,
  MANIFEST_PATH,
  MANIFEST_SCHEMA,
  PRODUCTION_ORIGIN,
  PUBLIC_RELEASE,
  buildManifest,
  buildRootDigest,
  sha256Hex
} from "./release-integrity.mjs";

const base = new URL(process.argv[2] || process.env.TRUST_WORTHY_BASE_URL || `${PRODUCTION_ORIGIN}${LAB_PATH}`);
const expectedManifest = buildManifest();
const localManifestBytes = fs.readFileSync(MANIFEST_PATH);
assert.deepEqual(JSON.parse(localManifestBytes), expectedManifest, "local release manifest is stale");

const wait = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

async function request(route, options = {}, expectedOrigin = base.origin) {
  const target = new URL(route, base);
  assert.equal(target.origin, expectedOrigin, `route escaped the expected origin: ${route}`);
  let lastError;
  for (let attempt = 0; attempt < 3; attempt += 1) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 20_000);
    try {
      const response = await fetch(target, {
        method: options.method || "GET",
        body: options.body,
        cache: "no-store",
        credentials: "omit",
        redirect: options.redirect || "error",
        signal: controller.signal
      });
      clearTimeout(timer);
      if (response.status < 500 || attempt === 2) return response;
      lastError = new Error(`${target.pathname} returned ${response.status}`);
    } catch (error) {
      clearTimeout(timer);
      lastError = error;
      if (attempt === 2) throw error;
    }
    await wait(350 * (attempt + 1));
  }
  throw lastError;
}

function contentTypeMatches(response, expected, route) {
  const observed = String(response.headers.get("content-type") || "").toLowerCase();
  assert.ok(observed.includes(expected.toLowerCase()), `${route} content type ${observed || "<missing>"} does not include ${expected}`);
}

async function verifyOwnedAsset(file) {
  const [getResponse, headResponse] = await Promise.all([
    request(file.route),
    request(file.route, { method: "HEAD" })
  ]);
  const expectedStatus = Number(file.expected_status || 200);
  assert.equal(getResponse.status, expectedStatus, `GET ${file.route} must return ${expectedStatus}`);
  assert.equal(headResponse.status, expectedStatus, `HEAD ${file.route} must return ${expectedStatus}`);
  contentTypeMatches(getResponse, file.content_type, file.route);
  contentTypeMatches(headResponse, file.content_type, file.route);

  const bytes = Buffer.from(await getResponse.arrayBuffer());
  assert.equal(sha256Hex(bytes), file.sha256, `${file.route} does not match its owned SHA-256 digest`);
  assert.equal(bytes.byteLength, file.bytes, `${file.route} does not match its owned byte count`);
  return file.route;
}

function assertSecurityHeaders(response) {
  const get = name => String(response.headers.get(name) || "").toLowerCase();
  const csp = get("content-security-policy");
  for (const directive of ["default-src 'self'", "object-src 'none'", "frame-src 'none'", "form-action 'none'", "script-src 'self'"]) {
    assert.ok(csp.includes(directive), `CSP is missing ${directive}`);
  }
  assert.equal(get("x-content-type-options"), "nosniff");
  assert.equal(get("referrer-policy"), "no-referrer");
  assert.equal(get("x-frame-options"), "deny");
  assert.ok(get("permissions-policy").includes("payment=()"));
}

const manifest = JSON.parse(localManifestBytes);
assert.equal(manifest.schema, MANIFEST_SCHEMA);
assert.equal(manifest.release, PUBLIC_RELEASE);
assert.equal(buildRootDigest(manifest.scope.files), manifest.digest.root);

const assetResults = await Promise.all(manifest.scope.files.map(verifyOwnedAsset));

const manifestRoute = "/truth/lab/release-manifest.json";
const [manifestGet, manifestHead, indexAliasGet, indexAliasHead, labGet] = await Promise.all([
  request(manifestRoute),
  request(manifestRoute, { method: "HEAD" }),
  request("/truth/lab/index.html", { redirect: "manual" }),
  request("/truth/lab/index.html", { method: "HEAD", redirect: "manual" }),
  request(LAB_PATH)
]);
for (const [label, response] of [["GET manifest", manifestGet], ["HEAD manifest", manifestHead]]) {
  assert.equal(response.status, 200, `${label} must return 200`);
}
contentTypeMatches(manifestGet, "application/json", manifestRoute);
contentTypeMatches(manifestHead, "application/json", manifestRoute);
assert.equal(sha256Hex(Buffer.from(await manifestGet.arrayBuffer())), sha256Hex(localManifestBytes), "live manifest bytes differ from source");
for (const [label, response] of [["GET", indexAliasGet], ["HEAD", indexAliasHead]]) {
  assert.ok([301, 302, 307, 308].includes(response.status), `${label} /truth/lab/index.html must redirect to the canonical lab path`);
  const destination = new URL(response.headers.get("location") || "", base);
  assert.equal(destination.href, new URL(LAB_PATH, base).href, `${label} index alias redirected outside the canonical lab path`);
}
assertSecurityHeaders(labGet);

const missingRoute = `/truth/lab/__trust_release_missing_${Date.now()}_${Math.random().toString(16).slice(2)}`;
const [missingGet, missingHead] = await Promise.all([
  request(missingRoute),
  request(missingRoute, { method: "HEAD" })
]);
assert.equal(missingGet.status, 404, "a unique unknown GET route must return a true 404");
assert.equal(missingHead.status, 404, "a unique unknown HEAD route must return a true 404");

const deniedMethods = ["OPTIONS", "POST", "PUT", "PATCH", "DELETE"];
const deniedResponses = await Promise.all(deniedMethods.map(method => request(LAB_PATH, {
  method,
  body: method === "OPTIONS" ? undefined : "synthetic-release-gate"
})));
deniedResponses.forEach((response, index) => {
  assert.equal(response.status, 405, `${deniedMethods[index]} ${LAB_PATH} must remain denied with 405`);
  const allowed = String(response.headers.get("allow") || "").toUpperCase();
  assert.ok(allowed.includes("GET") && allowed.includes("HEAD"), `${deniedMethods[index]} ${LAB_PATH} must advertise Allow: GET, HEAD`);
});

const truthRoutes = [
  ["/truth/", "text/html"],
  ["/truth/case-000001.php", "text/html"],
  ["/truth/case-000002.php", "text/html"],
  ["/truth/case-000003.php", "text/html"],
  ["/truth/case-000004.php", "text/html"],
  ["/book/research.html", "text/html"],
  ["/privacy.html", "text/html"]
];
const truthResponses = await Promise.all(truthRoutes.flatMap(([route]) => [
  request(route),
  request(route, { method: "HEAD" })
]));
truthResponses.forEach((response, index) => {
  const [route, type] = truthRoutes[Math.floor(index / 2)];
  assert.equal(response.status, 200, `${index % 2 ? "HEAD" : "GET"} ${route} must return 200`);
  contentTypeMatches(response, type, route);
});

const [ownerBoundary, publicInvestigationGet, publicInvestigationHead, questionGet, questionHead, challengeGet, challengeHead] = await Promise.all([
  request("/owner/", { redirect: "manual" }),
  request("/truth/investigate.php", { redirect: "manual" }),
  request("/truth/investigate.php", { method: "HEAD", redirect: "manual" }),
  request("/truth/question-submit.php", { redirect: "manual" }),
  request("/truth/question-submit.php", { method: "HEAD", redirect: "manual" }),
  request("/truth/challenge-submit.php", { redirect: "manual" }),
  request("/truth/challenge-submit.php", { method: "HEAD", redirect: "manual" })
]);
assert.ok([401, 403].includes(ownerBoundary.status), "the owner route must remain authentication-protected");
const postOnlyResponses = [publicInvestigationGet, publicInvestigationHead, questionGet, questionHead, challengeGet, challengeHead];
postOnlyResponses.forEach(response => {
  assert.equal(response.status, 405, "public POST-only endpoints must deny GET and HEAD");
  assert.ok(String(response.headers.get("allow") || "").toUpperCase().includes("POST"), "public POST-only 405 responses must advertise Allow: POST");
});

const internalRoutes = [
  "/scripts/validate_site.py",
  "/docs/TRUST-WORTHY-TRUTH-TRIAL-PROTOCOL.md",
  "/truth/EDITORIAL-STANDARD.md",
  "/campaigns/7-day-unveiled-launch.md",
  "/truth/daily/KEY-FILE-NOTE.md"
];
const internalResponses = await Promise.all(internalRoutes.map(route => request(route)));
internalResponses.forEach((response, index) => {
  assert.ok([403, 404].includes(response.status), `${internalRoutes[index]} must not be publicly downloadable`);
});

const handoffs = [
  "https://bobsome1-revenue-engine.thebobsomest1.chatgpt.site/",
  "https://bobsome1-media-it-preview.thebobsomest1.chatgpt.site/"
];
const handoffResponses = await Promise.all(handoffs.flatMap(url => [
  request(url, {}, new URL(url).origin),
  request(url, { method: "HEAD" }, new URL(url).origin)
]));
handoffResponses.forEach((response, index) => {
  assert.equal(response.status, 200, `${index % 2 ? "HEAD" : "GET"} ${handoffs[Math.floor(index / 2)]} must return 200`);
});

console.log(JSON.stringify({
  gate: "trust-worthy-live-release-v2",
  release: manifest.release,
  public_url: new URL(LAB_PATH, base).href,
  owned_assets_verified: assetResults.length,
  documented_get_head_routes_verified: assetResults.length + 1 + truthRoutes.length,
  true_404_verified: true,
  denied_methods_verified: deniedMethods,
  security_headers_verified: true,
  truth_trials_preserved: 4,
  owner_boundary_verified: true,
  public_post_only_boundaries_verified: 3,
  internal_artifacts_denied: internalRoutes.length,
  handoffs_verified: handoffs.length,
  build_root_sha256: manifest.digest.root,
  result: "pass"
}, null, 2));
