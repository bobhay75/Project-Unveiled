"use strict";

(() => {
  const RELEASE = "trust-worthy-observer-v7";
  const STORAGE_KEY = "trust-worthy-observer:latest-v7";
  const PUBLIC_RELEASE = 17;
  const MANIFEST_SCHEMA = "trust-worthy-release-manifest-v1";
  const DELIVERY_POLICY = "exact-owned-static-v1";
  const DAY_MS = 24 * 60 * 60 * 1000;
  const PNG_SIGNATURE = [137, 80, 78, 71, 13, 10, 26, 10];
  let running = false;

  const byId = id => document.getElementById(id);

  function check(name, passed, detail, metadata = {}) {
    return { name, passed: Boolean(passed), detail: String(detail || ""), ...metadata };
  }

  async function sha256Hex(value) {
    const bytes = value instanceof Uint8Array ? value : new Uint8Array(value);
    const digest = new Uint8Array(await crypto.subtle.digest("SHA-256", bytes));
    return [...digest].map(byte => byte.toString(16).padStart(2, "0")).join("");
  }

  function canonicalAssetRootInput(files) {
    const rows = [...files]
      .sort((left, right) => String(left.path).localeCompare(String(right.path)))
      .map(file => [
        file.path,
        file.route,
        file.expected_status,
        file.sha256,
        file.bytes,
        file.content_type,
        file.delivery?.mode,
        file.delivery?.policy || ""
      ]);
    return `${JSON.stringify(rows)}\n`;
  }

  async function buildRootDigest(files) {
    return sha256Hex(new TextEncoder().encode(canonicalAssetRootInput(files)));
  }

  function updateReleaseProof(passed, root = "", transform = "") {
    const proof = byId("release-proof");
    const state = byId("release-proof-state");
    const rootNode = byId("release-root");
    const transformNode = byId("release-transform-state");
    if (proof) proof.dataset.state = passed ? "pass" : "fail";
    if (state) state.textContent = passed ? "VERIFIED LOCALLY" : "REVIEW";
    if (rootNode) rootNode.textContent = root ? `${root.slice(0, 16)}…${root.slice(-8)}` : "Verification failed";
    if (transformNode) transformNode.textContent = transform || "Owned bytes served exactly";
  }

  function readStoredReceipt() {
    try {
      const value = localStorage.getItem(STORAGE_KEY);
      if (!value) return null;
      const receipt = JSON.parse(value);
      return receipt?.schema === RELEASE && Number.isFinite(receipt?.ranAt) ? receipt : null;
    } catch {
      return null;
    }
  }

  function storeReceipt(receipt) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(receipt));
      return true;
    } catch {
      return false;
    }
  }

  function pageStructureCheck() {
    const ids = [...document.querySelectorAll("[id]")].map(node => node.id);
    const oneHeading = document.querySelectorAll("h1").length === 1;
    const uniqueIds = ids.length === new Set(ids).size;
    const languageSet = document.documentElement.lang === "en";
    return check(
      "Page structure",
      oneHeading && uniqueIds && languageSet,
      oneHeading && uniqueIds && languageSet
        ? "The page has one primary heading, a declared language, and unique control identifiers."
        : "A document-structure regression needs review."
    );
  }

  function localFormBoundaryCheck() {
    const forms = [...document.querySelectorAll("form")];
    const localOnly = forms.length > 0 && forms.every(form => form.hasAttribute("data-local-only"));
    const noWriteMethod = forms.every(form => !["post", "put", "patch", "delete"].includes(String(form.method || "get").toLowerCase()));
    const unnamedPrivateInputs = forms.every(form =>
      [...form.querySelectorAll("input, textarea, select")].every(control => !control.hasAttribute("name"))
    );
    return check(
      "Local-only form boundary",
      localOnly && noWriteMethod && unnamedPrivateInputs,
      localOnly && noWriteMethod && unnamedPrivateInputs
        ? "Claim and evidence controls have no network-write method or native submission names."
        : "A form could cross the local-only boundary and needs review."
    );
  }

  function contentSecurityCheck() {
    const policy = document.querySelector('meta[http-equiv="Content-Security-Policy"]')?.content || "";
    const required = ["default-src 'self'", "object-src 'none'", "frame-src 'none'", "form-action 'none'", "script-src 'self'"];
    const referrer = document.querySelector('meta[name="referrer"]')?.content || "";
    const passed = required.every(rule => policy.includes(rule)) && referrer === "no-referrer";
    return check(
      "Browser security boundary",
      passed,
      passed
        ? "Scripts, frames, objects, forms, and referrers remain constrained by the published policy."
        : "A browser security-policy regression needs review."
    );
  }

  function linkBoundaryCheck() {
    const anchors = [...document.querySelectorAll("a[href]")];
    const safeSchemes = anchors.every(anchor => {
      const raw = anchor.getAttribute("href") || "";
      if (raw.startsWith("#") || raw.startsWith("/")) return true;
      try {
        return ["http:", "https:", "mailto:", "tel:"].includes(new URL(raw, location.href).protocol);
      } catch {
        return false;
      }
    });
    const isolatedTabs = anchors
      .filter(anchor => anchor.target === "_blank")
      .every(anchor => {
        const rel = new Set(String(anchor.rel || "").toLowerCase().split(/\s+/).filter(Boolean));
        return rel.has("noopener") && rel.has("noreferrer");
      });
    return check(
      "Link and handoff policy",
      safeSchemes && isolatedTabs,
      safeSchemes && isolatedTabs
        ? "Published links use allowed protocols and isolate new browsing contexts."
        : "A link or external handoff needs review."
    );
  }

  async function coreFunctionCheck() {
    const api = window.TrustWorthy;
    if (!api) return check("Core function smoke test", false, "The claim-analysis functions did not initialize.");
    try {
      const map = api.buildClaimMap("A documented observation occurred during 2026.");
      const safeUrl = api.safeHttpUrl("https://example.com/evidence") === "https://example.com/evidence";
      const unsafeUrl = api.safeHttpUrl("javascript:alert(1)") === "" && api.safeHttpUrl("http://127.0.0.1/private") === "";
      const completeMap = Array.isArray(map?.hypotheses) && map.hypotheses.length >= 2 && Array.isArray(map?.gaps);
      const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(RELEASE));
      const digestReady = digest.byteLength === 32;
      return check(
        "Core function smoke test",
        safeUrl && unsafeUrl && completeMap && digestReady,
        safeUrl && unsafeUrl && completeMap && digestReady
          ? "Claim mapping, URL filtering, uncertainty structure, and receipt hashing responded correctly."
          : "A core local function returned an unexpected result."
      );
    } catch {
      return check("Core function smoke test", false, "A core local function failed during observation.");
    }
  }

  async function fetchAsset(path, expectedType, verifyPng = false) {
    const response = await fetch(new URL(path, location.href), {
      method: "GET",
      cache: "no-store",
      credentials: "omit",
      redirect: "error"
    });
    if (!response.ok) return false;
    const contentType = response.headers.get("content-type") || "";
    if (expectedType && !contentType.toLowerCase().includes(expectedType)) return false;
    if (!verifyPng) return true;
    const bytes = new Uint8Array(await response.arrayBuffer());
    return PNG_SIGNATURE.every((value, index) => bytes[index] === value);
  }

  async function assetAndMediaCheck() {
    const assets = [
      ["./styles.css", "text/css", false],
      ["./research-sweep.js", "javascript", false],
      ["./app.js", "javascript", false],
      ["./observer.js", "javascript", false],
      ["/assets/js/cinematic-effects.js", "javascript", false],
      ["./manifest.webmanifest", "json", false],
      ["./status.json", "json", false],
      ["./release-manifest.json", "json", false],
      ["/.well-known/security.txt", "text/plain", false],
      ["./app-icon-192.png", "image/png", true],
      ["./app-icon-512.png", "image/png", true],
      ["./share-card.png", "image/png", true]
    ];
    try {
      const results = await Promise.all(assets.map(args => fetchAsset(...args)));
      const passed = results.every(Boolean);
      return check(
        "Asset and media integrity",
        passed,
        passed
          ? "Critical scripts, styles, metadata, security contact, icons, and share media are reachable with expected types."
          : "A critical asset or media signature needs review."
      );
    } catch {
      return check("Asset and media integrity", false, "A critical asset check could not complete.");
    }
  }

  async function releaseProvenanceCheck() {
    try {
      const manifestResponse = await fetch(new URL("./release-manifest.json", location.href), {
        method: "GET",
        cache: "no-store",
        credentials: "omit",
        redirect: "error"
      });
      if (!manifestResponse.ok || !(manifestResponse.headers.get("content-type") || "").toLowerCase().includes("json")) {
        throw new Error("The public release manifest is unavailable.");
      }
      const manifest = await manifestResponse.json();
      const files = manifest?.scope?.files;
      const structureMatches = manifest?.schema === MANIFEST_SCHEMA &&
        manifest?.release === PUBLIC_RELEASE &&
        manifest?.observer === RELEASE &&
        Array.isArray(files) && files.length === manifest?.scope?.owned_asset_count && files.length > 0 &&
        manifest?.delivery_boundary?.policy === DELIVERY_POLICY &&
        manifest?.delivery_boundary?.fail_closed === true &&
        files.every(file => file?.delivery?.mode === "exact");
      if (!structureMatches) throw new Error("The release manifest contract does not match this observer.");
      if (await buildRootDigest(files) !== manifest?.digest?.root) throw new Error("The manifest root does not match its asset records.");

      const results = await Promise.all(files.map(async file => {
        const route = String(file?.route || "");
        const routeAllowed = route.startsWith("/truth/lab/") ||
          route === "/.well-known/security.txt" ||
          route === "/assets/js/cinematic-effects.js";
        if (!routeAllowed || route.includes("..")) throw new Error("An asset route escaped the public release boundary.");
        const target = new URL(route, location.href);
        if (target.origin !== location.origin) throw new Error("An asset route escaped the public origin.");
        const response = await fetch(target, {
          method: "GET",
          cache: "no-store",
          credentials: "omit",
          redirect: "error"
        });
        const expectedStatus = Number(file.expected_status || 200);
        if (response.status !== expectedStatus) throw new Error(`A manifest asset returned an unexpected status: ${route}`);
        const observedType = (response.headers.get("content-type") || "").toLowerCase();
        if (!observedType.includes(String(file.content_type || "").toLowerCase())) throw new Error(`An asset content type changed: ${route}`);
        const bytes = new Uint8Array(await response.arrayBuffer());
        if (await sha256Hex(bytes) !== file.sha256 || bytes.byteLength !== file.bytes) {
          throw new Error(`An owned asset fingerprint changed: ${route}`);
        }
        return route;
      }));

      const transform = results.length === files.length ? "Owned bytes served exactly" : "Verification incomplete";
      updateReleaseProof(true, manifest.digest.root, transform);
      return check(
        "Release provenance",
        true,
        `${files.length} owned assets match the release manifest; the host delivery layer was tested separately.`,
        { root: manifest.digest.root, transform }
      );
    } catch {
      updateReleaseProof(false);
      return check("Release provenance", false, "The release manifest, an owned asset fingerprint, or the delivery-layer boundary needs review.");
    }
  }

  async function routePolicyCheck() {
    try {
      const missing = new URL(`./__trust_observer_missing_${Date.now()}`, location.href);
      const deniedMethods = ["OPTIONS", "POST", "PUT", "PATCH", "DELETE"];
      const [unknownGet, unknownHead, statusResponse, rootHead, manifestHead, ...deniedResponses] = await Promise.all([
        fetch(missing, { method: "GET", cache: "no-store", credentials: "omit", redirect: "error" }),
        fetch(missing, { method: "HEAD", cache: "no-store", credentials: "omit", redirect: "error" }),
        fetch(new URL("./status.json", location.href), { method: "GET", cache: "no-store", credentials: "omit", redirect: "error" }),
        fetch(new URL("./", location.href), { method: "HEAD", cache: "no-store", credentials: "omit", redirect: "error" }),
        fetch(new URL("./release-manifest.json", location.href), { method: "HEAD", cache: "no-store", credentials: "omit", redirect: "error" }),
        ...deniedMethods.map(method => fetch(new URL("./", location.href), { method, cache: "no-store", credentials: "omit", redirect: "error" }))
      ]);
      const status = statusResponse.ok ? await statusResponse.json() : null;
      const policyMatches = status?.release === PUBLIC_RELEASE &&
        status?.observer === RELEASE &&
        status?.release_manifest === "/truth/lab/release-manifest.json" &&
        status?.unknown_route_status === 404 &&
        deniedMethods.every(method => status?.expected_denials?.[method] === 405);
      const passed = unknownGet.status === 404 && unknownHead.status === 404 &&
        statusResponse.status === 200 && rootHead.status === 200 && manifestHead.status === 200 &&
        deniedResponses.every(response => response.status === 405) && policyMatches;
      return check(
        "HTTP route policy",
        passed,
        passed
          ? "Documented GET/HEAD reads succeed, unknown GET/HEAD paths return 404, and all five unsupported methods remain denied with 405."
          : "A route is returning a misleading or unexpected status."
      );
    } catch {
      return check("HTTP route policy", false, "The live route-policy check could not complete.");
    }
  }

  function render(receipt) {
    const status = byId("observer-status");
    const state = byId("observer-state");
    const passes = byId("observer-pass-count");
    const failures = byId("observer-failure-count");
    const lastRun = byId("observer-last-run");
    const results = byId("observer-results");
    if (!status || !state || !passes || !failures || !lastRun || !results) return;

    const total = receipt.checks.length;
    const passed = receipt.checks.filter(item => item.passed).length;
    const failed = total - passed;
    status.dataset.state = failed ? "fail" : "pass";
    state.textContent = failed ? "REVIEW" : "PASS";
    passes.textContent = `${passed}/${total}`;
    failures.textContent = String(failed);
    lastRun.textContent = `Last local observation: ${new Date(receipt.ranAt).toLocaleString()}`;
    results.replaceChildren();
    receipt.checks.forEach(item => {
      const node = document.createElement("li");
      node.className = item.passed ? "is-pass" : "is-fail";
      node.textContent = `${item.name}: ${item.detail}`;
      results.append(node);
    });
    const provenance = receipt.checks.find(item => item.name === "Release provenance");
    if (provenance) updateReleaseProof(provenance.passed, provenance.root, provenance.transform);
  }

  async function runObserver(force = false) {
    if (running) return null;
    const stored = readStoredReceipt();
    if (!force && stored && Date.now() - stored.ranAt < DAY_MS) {
      render(stored);
      return stored;
    }

    running = true;
    const status = byId("observer-status");
    const state = byId("observer-state");
    const button = byId("run-observer");
    if (status) status.dataset.state = "idle";
    if (state) state.textContent = "CHECKING";
    if (button) button.disabled = true;

    const localChecks = [pageStructureCheck(), localFormBoundaryCheck(), contentSecurityCheck(), linkBoundaryCheck()];
    const remoteChecks = await Promise.all([coreFunctionCheck(), assetAndMediaCheck(), releaseProvenanceCheck(), routePolicyCheck()]);
    const receipt = {
      schema: RELEASE,
      ranAt: Date.now(),
      origin: location.origin,
      checks: [...localChecks, ...remoteChecks]
    };
    storeReceipt(receipt);
    render(receipt);
    if (button) button.disabled = false;
    running = false;
    return receipt;
  }

  function initialize() {
    byId("run-observer")?.addEventListener("click", () => { void runObserver(true); });
    const receipt = readStoredReceipt();
    if (receipt) render(receipt);
    void runObserver(false);
  }

  window.TrustObserver = {
    RELEASE,
    pageStructureCheck,
    localFormBoundaryCheck,
    contentSecurityCheck,
    linkBoundaryCheck,
    coreFunctionCheck,
    assetAndMediaCheck,
    releaseProvenanceCheck,
    routePolicyCheck,
    buildRootDigest,
    runObserver
  };

  initialize();
})();
