import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import vm from "node:vm";

class StubNode {
  constructor(id = "") {
    this.id = id;
    this.textContent = "";
    this.className = "";
    this.dataset = {};
    this.disabled = false;
    this.children = [];
    this.listeners = new Map();
  }
  addEventListener(name, handler) { this.listeners.set(name, handler); }
  replaceChildren(...children) { this.children = children; }
  append(...children) { this.children.push(...children); }
}

const ids = [
  "run-observer", "observer-status", "observer-state", "observer-pass-count",
  "observer-failure-count", "observer-last-run", "observer-results", "release-proof",
  "release-proof-state", "release-root", "release-transform-state"
];
const nodes = new Map(ids.map(id => [id, new StubNode(id)]));
const controls = [{ hasAttribute: () => false }];
const forms = ["claim", "coverage", "evidence"].map(() => ({
  method: "get",
  hasAttribute: name => name === "data-local-only",
  querySelectorAll: () => controls
}));
const anchors = [
  { target: "", rel: "", getAttribute: () => "#observer" },
  { target: "_blank", rel: "noopener noreferrer", getAttribute: () => "https://example.com/" },
  { target: "", rel: "", getAttribute: () => "mailto:test@example.com" }
];
const policy = "default-src 'self'; object-src 'none'; frame-src 'none'; form-action 'none'; script-src 'self'";
const documentStub = {
  documentElement: { lang: "en" },
  getElementById(id) { return nodes.get(id) || null; },
  querySelectorAll(selector) {
    if (selector === "[id]") return [...nodes.values()];
    if (selector === "h1") return [new StubNode("primary-heading")];
    if (selector === "form") return forms;
    if (selector === "a[href]") return anchors;
    return [];
  },
  querySelector(selector) {
    if (selector.includes("Content-Security-Policy")) return { content: policy };
    if (selector.includes('meta[name="referrer"]')) return { content: "no-referrer" };
    return null;
  },
  createElement() { return new StubNode(); }
};

const storage = new Map();
const localStorageStub = {
  getItem(key) { return storage.get(key) ?? null; },
  setItem(key, value) { storage.set(key, String(value)); }
};

const dist = fileURLToPath(new URL("../../truth/lab/", import.meta.url));
const manifest = JSON.parse(fs.readFileSync(path.join(dist, "release-manifest.json"), "utf8"));
const recordByRoute = new Map(manifest.scope.files.map(file => [file.route, file]));
const typeByPath = new Map(manifest.scope.files.map(file => [file.route, file.content_type]));
typeByPath.set("/truth/lab/release-manifest.json", "application/json");

let soft404 = false;
let tamperPath = "";
function bodyForPath(pathname) {
  const relative = pathname === "/truth/lab/release-manifest.json"
    ? "release-manifest.json"
    : recordByRoute.get(pathname)?.path;
  if (!relative) return Buffer.alloc(0);
  let body = fs.readFileSync(path.join(dist, relative));
  if (relative === tamperPath) body = Buffer.concat([body, Buffer.from("\nsynthetic-tamper")]);
  return body;
}

function response(status, contentType, body = Buffer.alloc(0)) {
  const bytes = Buffer.isBuffer(body) ? body : Buffer.from(String(body));
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: { get(name) { return name.toLowerCase() === "content-type" ? contentType : null; } },
    async arrayBuffer() { return bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength); },
    async json() { return JSON.parse(bytes.toString("utf8")); },
    async text() { return bytes.toString("utf8"); }
  };
}

async function fetchStub(input, options = {}) {
  const url = new URL(String(input));
  const method = String(options.method || "GET").toUpperCase();
  if (["OPTIONS", "POST", "PUT", "PATCH", "DELETE"].includes(method)) return response(405, "text/plain");
  if (url.pathname.includes("__trust_observer_missing_")) return response(soft404 ? 200 : 404, "text/html");
  const contentType = typeByPath.get(url.pathname);
  if (!contentType) return response(404, "text/html");
  const body = method === "HEAD" ? Buffer.alloc(0) : bodyForPath(url.pathname);
  return response(recordByRoute.get(url.pathname)?.expected_status || 200, contentType, body);
}

const locationStub = {
  href: "https://bobsome1.com/truth/lab/",
  origin: "https://bobsome1.com"
};
const windowStub = {
  TrustWorthy: {
    buildClaimMap() { return { hypotheses: [{}, {}], gaps: [] }; },
    safeHttpUrl(value) { return value.startsWith("https://example.com") ? value : ""; }
  }
};
const context = vm.createContext({
  console,
  URL,
  Date,
  TextEncoder,
  TextDecoder,
  document: documentStub,
  localStorage: localStorageStub,
  location: locationStub,
  window: windowStub,
  fetch: fetchStub,
  crypto: {
    subtle: {
      async digest(_algorithm, bytes) {
        const value = createHash("sha256").update(Buffer.from(bytes)).digest();
        return value.buffer.slice(value.byteOffset, value.byteOffset + value.byteLength);
      }
    }
  }
});

const source = fs.readFileSync(path.join(dist, "observer.js"), "utf8");
vm.runInContext(source, context, { filename: "observer.js" });
for (let index = 0; index < 40 && nodes.get("run-observer").disabled; index += 1) {
  await new Promise(resolve => setImmediate(resolve));
}

const api = context.window.TrustObserver;
assert.equal(api.RELEASE, "trust-worthy-observer-v5");
const receipt = await api.runObserver(true);
assert.equal(receipt.checks.length, 8);
assert.ok(receipt.checks.every(item => item.passed), "the complete observer fixture should pass");
assert.equal(nodes.get("observer-state").textContent, "PASS");
assert.equal(nodes.get("observer-pass-count").textContent, "8/8");
assert.equal(nodes.get("observer-failure-count").textContent, "0");
assert.equal(nodes.get("observer-results").children.length, 8);
assert.equal(nodes.get("release-proof-state").textContent, "VERIFIED LOCALLY");
assert.equal(nodes.get("release-transform-state").textContent, "Owned bytes served exactly");
assert.ok(storage.has("trust-worthy-observer:latest-v5"), "the latest local receipt should be retained under the release-15 observer key");

soft404 = true;
const routeFailure = await api.routePolicyCheck();
assert.equal(routeFailure.passed, false, "a homepage response for an unknown path must be rejected as a soft 404");
soft404 = false;

tamperPath = "app.js";
const assetFailure = await api.releaseProvenanceCheck();
assert.equal(assetFailure.passed, false, "a changed owned asset must fail provenance verification");
tamperPath = "";

const exactPass = await api.releaseProvenanceCheck();
assert.equal(exactPass.passed, true, "exact owned bytes must pass after the tamper is removed");

console.log("Observer checks passed: eight-check receipt, all denied methods, true 404, exact manifest hashes, and negative tamper cases");
