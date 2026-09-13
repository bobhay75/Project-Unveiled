import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import fs from "node:fs";
import vm from "node:vm";

class ClassList {
  constructor() { this.values = new Set(); }
  add(value) { this.values.add(value); }
  remove(value) { this.values.delete(value); }
  toggle(value, force) {
    if (force === true) this.values.add(value);
    else if (force === false) this.values.delete(value);
    else if (this.values.has(value)) this.values.delete(value);
    else this.values.add(value);
  }
}

class StubNode {
  constructor(id = "", tagName = "div") {
    this.id = id;
    this.tagName = tagName.toUpperCase();
    this.textContent = "";
    this.value = "";
    this.defaultValue = "";
    this.hidden = false;
    this.disabled = false;
    this.href = "";
    this.dataset = {};
    this.children = [];
    this.className = "";
    this.classList = new ClassList();
    this.listeners = new Map();
    this.innerHTML = "";
    this.attributes = new Map();
    this.resetTargets = [];
  }
  addEventListener(name, handler) { this.listeners.set(name, handler); }
  replaceChildren(...children) { this.children = children; }
  append(...children) { this.children.push(...children); }
  focus() { documentStub.activeElement = this; }
  scrollIntoView() {}
  contains(target) { return target === this || this.descendants().includes(target); }
  reset() { this.resetTargets.forEach(node => { node.value = node.defaultValue; }); }
  closest() { return null; }
  descendants() {
    return this.children.flatMap(child => child instanceof StubNode ? [child, ...child.descendants()] : []);
  }
  querySelectorAll(selector) {
    const selectors = selector.split(",").map(value => value.trim());
    return this.descendants().filter(node => selectors.some(part => {
      if (part === "button") return node.tagName === "BUTTON";
      if (["input", "textarea", "select"].includes(part)) return node.tagName === part.toUpperCase();
      const data = part.match(/^\[data-([a-z-]+)\]$/);
      if (data) {
        const key = data[1].replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
        return Object.hasOwn(node.dataset, key);
      }
      if (part.startsWith(".")) return node.className.split(/\s+/).includes(part.slice(1));
      return node.tagName === part.toUpperCase();
    }));
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  setAttribute(name, value) { this.attributes.set(name, String(value)); }
  removeAttribute(name) { this.attributes.delete(name); }
  getAttribute(name) { return this.attributes.get(name) ?? null; }
}

const ids = [
  "claim-form", "claim-input", "char-count", "claim-error", "results", "toast",
  "evidence-form", "evidence-list", "saved-cases", "docket-count", "report-claim",
  "report-classification", "report-wording", "report-clarify", "report-support",
  "report-counter", "report-sources", "report-hypotheses", "report-incentives",
  "report-forensics", "report-gaps", "report-coverage", "result-kicker", "results-title", "finding-label",
  "finding-summary", "finding-band", "evidence-count", "readiness-score", "readiness-label",
  "verified-text", "inference-text", "unknown-text", "case-status", "triage-link", "deep-link", "hyper-link",
  "source-title", "source-url", "source-role", "source-class", "source-author", "source-date",
  "source-target", "source-origin", "source-independence", "source-incentives", "source-custody", "source-falsifier",
  "source-notes", "evidence-error", "coverage-form", "coverage-scope", "coverage-cutoff",
  "coverage-stop", "coverage-gaps", "coverage-error", "save-case", "copy-report", "share-report",
  "print-report", "new-case", "load-moon-case", "open-proof-case", "open-atmosphere-case",
  "evidence-title", "boundary-title", "clear-cases", "receipt-hash", "print-record-meta", "copy-receipt", "copy-receipt-json", "facebook-share", "claim-sync-warning",
  "archived-receipt", "archived-receipt-hash", "archived-receipt-status", "copy-archived-hash", "copy-archived-json",
  "research-sweep", "research-results", "research-query-list", "research-status", "research-query-count",
  "research-result-count", "research-failure-count", "run-research", "cancel-research", "copy-search-receipt"
];
const nodes = new Map(ids.map(id => [id, new StubNode(id)]));
for (const id of ["results", "claim-sync-warning", "archived-receipt", "cancel-research"]) nodes.get(id).hidden = true;
const stageNodes = ["frame", "search", "test", "judge"].map(stage => {
  const node = new StubNode(`stage-${stage}`, "button");
  node.dataset.stage = stage;
  node.classList.add("stage");
  if (stage === "frame") {
    node.classList.add("is-active");
    node.setAttribute("aria-current", "step");
  }
  return node;
});
const safeguardKeys = ["primary", "independent", "counter", "provenance", "incentives", "custody", "falsifier", "coverage"];
const readinessCards = new Map(safeguardKeys.map(key => {
  const card = new StubNode(key);
  card.strong = new StubNode(`${key}-strong`);
  card.querySelector = () => card.strong;
  return [key, card];
}));

const researchStatusCode = new StubNode("research-status-code", "strong");
const researchStatusText = new StubNode("research-status-text", "span");
nodes.get("research-status").append(researchStatusCode, researchStatusText);

const evidenceControlIds = [
  "source-title", "source-url", "source-role", "source-class", "source-author", "source-date", "source-target", "source-origin",
  "source-independence", "source-incentives", "source-custody", "source-falsifier", "source-notes"
];
const coverageControlIds = ["coverage-scope", "coverage-cutoff", "coverage-stop", "coverage-gaps"];
nodes.get("source-role").defaultValue = "support";
nodes.get("source-role").value = "support";
nodes.get("source-class").defaultValue = "primary";
nodes.get("source-class").value = "primary";
nodes.get("evidence-form").resetTargets = evidenceControlIds.map(id => nodes.get(id));
nodes.get("coverage-form").resetTargets = coverageControlIds.map(id => nodes.get(id));
nodes.get("evidence-form").children = [...nodes.get("evidence-form").resetTargets];
nodes.get("coverage-form").children = [...nodes.get("coverage-form").resetTargets];

const storage = new Map();
const STORAGE_KEY = "trust-worthy-evidence-lab:v2";
const storageControl = { failWrites: false };
const clipboardWrites = [];
const digestGates = [];
function armDigestGate() {
  let release;
  let markEntered;
  const wait = new Promise(resolve => { release = resolve; });
  const entered = new Promise(resolve => { markEntered = resolve; });
  const gate = { wait, entered, release };
  digestGates.push({ wait, markEntered });
  return gate;
}
async function sha256Digest(_algorithm, bytes) {
  const gate = digestGates.shift();
  if (gate) {
    gate.markEntered();
    await gate.wait;
  }
  const buffer = createHash("sha256").update(Buffer.from(bytes)).digest();
  return buffer.buffer.slice(buffer.byteOffset, buffer.byteOffset + buffer.byteLength);
}
const windowListeners = new Map();
const documentStub = {
  activeElement: null,
  getElementById(id) {
    assert.ok(nodes.has(id), `JavaScript referenced undeclared test DOM id: ${id}`);
    return nodes.get(id);
  },
  querySelectorAll(selector) {
    if (selector === ".stage") return stageNodes;
    return [];
  },
  querySelector(selector) {
    const match = selector.match(/data-check="([^"]+)"/);
    return match ? readinessCards.get(match[1]) : null;
  },
  createElement(tagName) { return new StubNode("", tagName); }
};
const context = vm.createContext({
  console,
  URL,
  Date,
  TextEncoder,
  crypto: {
    randomUUID() { return "00000000-0000-4000-8000-000000000001"; },
    subtle: { digest: sha256Digest }
  },
  localStorage: {
    getItem(key) { return storage.has(key) ? storage.get(key) : null; },
    setItem(key, value) {
      if (storageControl.failWrites) throw new Error("simulated quota/privacy failure");
      storage.set(key, String(value));
    }
  },
  document: documentStub,
  navigator: { clipboard: { writeText: async value => { clipboardWrites.push(String(value)); } } },
  window: {
    setTimeout() { return 1; },
    clearTimeout() {},
    print() {},
    confirm() { return true; },
    addEventListener(name, handler) {
      const listeners = windowListeners.get(name) || [];
      listeners.push(handler);
      windowListeners.set(name, listeners);
    },
    dispatchEvent(event) {
      (windowListeners.get(event.type) || []).forEach(handler => handler(event));
    },
    TrustWorthy: null
  }
});

const settle = async () => {
  for (let index = 0; index < 4; index += 1) await Promise.resolve();
  await new Promise(resolve => setImmediate(resolve));
  for (let index = 0; index < 4; index += 1) await Promise.resolve();
};
const click = async id => nodes.get(id).listeners.get("click")?.({ preventDefault() {} });
const dispatchStorage = () => context.window.dispatchEvent({ type: "storage", key: STORAGE_KEY });
const savedAction = (kind, key) => nodes.get("saved-cases").listeners.get("click")({
  target: {
    closest(selector) {
      if (kind === "open" && selector === "[data-open-case]") return { dataset: { openCase: key } };
      if (kind === "delete" && selector === "[data-delete-case]") return { dataset: { deleteCase: key } };
      return null;
    }
  }
});
const snapshotFixture = ({
  caseId,
  claim,
  savedAt,
  evidence = [],
  coverage = {},
  coverageOrigin = "none",
  research = {},
  curated = false,
  recordVersion = null,
  reviewedAt = null,
  parent = null,
  canonicalReceipt = "",
  canonicalReceiptHash = ""
}) => ({
  schema: "trust-worthy-local-snapshot-v5",
  caseId,
  claim,
  evidence,
  coverage,
  coverageOrigin,
  research,
  curated,
  recordVersion,
  reviewedAt,
  parent,
  canonicalReceipt,
  canonicalReceiptHash,
  savedAt
});
function fillEvidenceForm(overrides = {}) {
  const fields = {
    "source-title": "Exact source under adversarial test",
    "source-url": "https://example.com/adversarial-source",
    "source-role": "counter",
    "source-class": "independent",
    "source-author": "Independent test custodian",
    "source-date": "September 8, 2026",
    "source-target": "H2 strongest competing explanation",
    "source-origin": "Original public record produced by an identified independent test custodian.",
    "source-independence": "Separate institution and dataset; no shared upstream assertion is currently known.",
    "source-incentives": "Publication and reputation incentives exist and must be checked against the underlying record.",
    "source-custody": "Publisher retains the original; this browser copy has not been independently preserved and hashed.",
    "source-falsifier": "A custody break, contradictory original, or failed reproduction would materially weaken this source.",
    "source-notes": "This record addresses the named hypothesis, while its provenance and full contents still require inspection."
  };
  Object.entries({ ...fields, ...overrides }).forEach(([id, value]) => { nodes.get(id).value = value; });
}
function fillCoverageForm(overrides = {}) {
  const fields = {
    "coverage-scope": "National, state, and local public archives plus supportive, challenging, and neutral query families were searched.",
    "coverage-cutoff": "September 8, 2026",
    "coverage-stop": "Stopped after every hypothesis had a distinct test and no new evidence family appeared.",
    "coverage-gaps": "Private, deleted, sealed, untranslated, and undiscovered records remain outside this bounded search."
  };
  Object.entries({ ...fields, ...overrides }).forEach(([id, value]) => { nodes.get(id).value = value; });
}

const source = fs.readFileSync(new URL("../../truth/lab/app.js", import.meta.url), "utf8");
const researchSource = fs.readFileSync(new URL("../../truth/lab/research-sweep.js", import.meta.url), "utf8");
assert.doesNotMatch(source, /open\.innerHTML/, "saved-case rendering must not inject localStorage data through innerHTML");
vm.runInContext(researchSource, context, { filename: "research-sweep.js" });
vm.runInContext(source, context, { filename: "app.js" });
const api = context.window.TrustWorthy;
const digest = value => createHash("sha256").update(value).digest("hex");

assert.equal(api.isMoonClaim("Did man land on the moon?"), true);
assert.equal(api.isMoonClaim("People never landed on the Moon."), true);
assert.equal(api.isMoonClaim("Was the Moon landing a hoax?"), true);
assert.equal(api.isMoonClaim("Apollo was staged in a studio"), true);
assert.equal(api.isAtmosphereClaim("Are persistent high-altitude aircraft trails evidence of a secret atmospheric spraying program?"), true);
assert.equal(api.isAtmosphereClaim("Are chemtrails real?"), true);
assert.equal(api.isAtmosphereClaim("Is there covert aerosol spraying?"), true);
assert.equal(api.stageTargetId("search"), "research-sweep");
assert.equal(api.stageTargetId("test"), "evidence-title");
assert.equal(api.stageTargetId("judge"), "boundary-title");
assert.equal(api.stageTargetId("unknown"), null);
assert.equal(api.safeHttpUrl("javascript:alert(1)"), "");
assert.equal(api.safeHttpUrl("https://user@example.com/source"), "");
assert.equal(api.safeHttpUrl("http://127.0.0.1/source"), "");
assert.equal(api.dedupeUrl("https://EXAMPLE.com/CaseSensitive/?utm_source=x&id=7#part"), "https://example.com/CaseSensitive/?id=7");
const firstLocalId = api.createLocalCaseId([]);
const secondLocalId = api.createLocalCaseId([{ caseId: firstLocalId }]);
assert.notEqual(firstLocalId, secondLocalId);
assert.equal(secondLocalId, `${firstLocalId}-02`);

const generic = api.buildClaimMap("A testable record exists during 2026.");
assert.equal(generic.hypotheses.length, 3);
assert.ok(generic.incentives.length >= 4);
assert.ok(generic.forensics.length >= 3);
assert.ok(generic.gaps.length >= 3);

for (const reviewed of [api.moonCase, api.atmosphereCase]) {
  assert.ok(reviewed.evidence.length >= 8);
  assert.ok(reviewed.evidence.every(item => api.safeHttpUrl(item.url)));
  assert.ok(reviewed.evidence.every(item =>
    ["origin", "notes", "independence", "incentives", "custody", "falsifier"].every(field => String(item[field] || "").length >= 30)
  ));
  assert.deepEqual(JSON.parse(JSON.stringify(api.sourceMetrics(reviewed.evidence, reviewed.coverage))), {
    primary: true,
    independent: true,
    counter: true,
    provenance: true,
    incentives: true,
    custody: true,
    falsifier: true,
    coverage: true
  });
}

vm.runInContext('analyze("Did man land on the moon?")', context);
assert.equal(nodes.get("finding-label").textContent, "ADVERSARIAL RECORD OPEN");
assert.match(nodes.get("result-kicker").textContent, /TW-MOON-001/);
assert.equal(nodes.get("readiness-score").textContent, "8/8");
for (const id of ["report-hypotheses", "report-incentives", "report-forensics", "report-gaps"]) {
  assert.ok(nodes.get(id).children.length > 0, `${id} should render content`);
}
const moonPayload = api.receiptPayload();
const moonReceipt = JSON.parse(moonPayload);
assert.equal(moonReceipt.schema, "trust-worthy-adversarial-record-v5");
assert.equal(moonReceipt.case_id, "TW-MOON-001");
assert.equal(moonReceipt.version, "2.0");
assert.equal(moonReceipt.reasoning.hypotheses.length, 4);
assert.ok(moonReceipt.search_coverage.scope.length >= 60);
assert.ok(moonReceipt.evidence[0].independence.length >= 30);
assert.equal(moonReceipt.evidence[0].recorded, "URL recorded September 8, 2026");

await click("save-case");
const savedMoonSnapshot = JSON.parse(storage.values().next().value)[0];
assert.equal(savedMoonSnapshot.schema, "trust-worthy-local-snapshot-v5");
assert.equal(savedMoonSnapshot.recordVersion, "2.0");
assert.equal(savedMoonSnapshot.canonicalReceipt, moonPayload);
assert.equal(savedMoonSnapshot.map, undefined, "local snapshots must not persist a self-asserted reviewed finding");
const forgedMoonSnapshot = {
  ...savedMoonSnapshot,
  claim: "FORGED CLAIM THAT MUST NOT REPLACE THE REGISTRY RECORD",
  map: { summary: "FORGED REVIEWED FINDING" },
  evidence: [{ title: "Forged source", url: "javascript:alert(1)" }],
  canonicalReceipt: '{"curated":true,"finding_summary":"FORGED REVIEWED FINDING"}'
};
storage.set("trust-worthy-evidence-lab:v2", JSON.stringify([forgedMoonSnapshot]));
savedAction("open", api.localRecordKey(forgedMoonSnapshot));
const trustedReload = JSON.parse(api.receiptPayload());
assert.equal(trustedReload.curated, true, "a matching built-in registry record may retain reviewed status");
assert.equal(trustedReload.case_id, "TW-MOON-001");
assert.equal(trustedReload.claim, api.moonCase.claim, "saved text must not replace an immutable reviewed registry record");
assert.equal(trustedReload.finding_summary, api.moonCase.summary, "saved findings must not replace an immutable reviewed registry record");
assert.equal(trustedReload.evidence.length, api.moonCase.evidence.length, "saved evidence must not replace an immutable reviewed registry record");

const forgedUnknownSnapshot = {
  schema: "trust-worthy-local-snapshot-v5",
  caseId: "TW-FORGED-999",
  claim: "A local attacker claims this record was professionally reviewed.",
  evidence: [],
  coverage: {},
  research: {},
  curated: true,
  recordVersion: "99.0",
  reviewedAt: "2099-01-01",
  parent: null,
  savedAt: "2099-01-01"
};
storage.set("trust-worthy-evidence-lab:v2", JSON.stringify([forgedUnknownSnapshot]));
savedAction("open", api.localRecordKey(forgedUnknownSnapshot));
const untrustedReload = JSON.parse(api.receiptPayload());
assert.equal(untrustedReload.curated, false, "unknown local review claims must be downgraded");
assert.equal(untrustedReload.case_id, null);
assert.equal(untrustedReload.parent_record.caseId, "TW-FORGED-999");
assert.equal(untrustedReload.finding, "PRE-RESEARCH");

const originalGap = api.moonCase.coverage.gaps;
api.moonCase.coverage.gaps = `${originalGap} Material change.`;
vm.runInContext('loadMoonCase()', context);
assert.notEqual(digest(api.receiptPayload()), digest(moonPayload), "coverage changes must alter the fingerprint payload");
api.moonCase.coverage.gaps = originalGap;

const originalCustody = api.moonCase.evidence[0].custody;
api.moonCase.evidence[0].custody = `${originalCustody} Material change.`;
vm.runInContext('loadMoonCase()', context);
assert.notEqual(digest(api.receiptPayload()), digest(moonPayload), "custody changes must alter the fingerprint payload");
api.moonCase.evidence[0].custody = originalCustody;

vm.runInContext('analyze("Are persistent high-altitude aircraft trails evidence of a secret atmospheric spraying program?")', context);
assert.match(nodes.get("result-kicker").textContent, /TW-ATMOS-001/);
assert.equal(nodes.get("readiness-score").textContent, "8/8");
assert.match(nodes.get("finding-label").textContent, /NOT ESTABLISHED IN THIS DOSSIER/);
assert.ok(api.atmosphereCase.evidence.some(item => item.className === "lead" && /Geoengineering Watch/.test(item.title)));
assert.ok(api.atmosphereCase.evidence.some(item => /patent/i.test(item.title)));

vm.runInContext('analyze("A testable record exists during 2026.")', context);
const originalRunFederatedSearch = context.window.TrustResearch.runFederatedSearch;
let fixtureResearchRun;
context.window.TrustResearch.runFederatedSearch = async (claim, options = {}) => {
  const completedAt = "2026-09-08T12:00:00.000Z";
  const queries = context.window.TrustResearch.buildResearchPlan(claim).map(item => ({
    ...item,
    requestedAt: completedAt,
    completedAt,
    status: "complete",
    resultCount: item.id === "crossref-neutral" ? 2 : 0,
    retainedResultCount: item.id === "crossref-neutral" ? 1 : 0,
    lowOverlapResultCount: item.id === "crossref-neutral" ? 1 : 0,
    error: ""
  }));
  queries.forEach(item => options.onProgress?.(item));
  fixtureResearchRun = {
    schema: "trust-worthy-source-sweep-v2",
    startedAt: completedAt,
    completedAt,
    claim,
    queries,
    results: [{
      id: "crossref-neutral-1",
      providerId: "crossref",
      provider: "Crossref",
      repository: "Scholarly DOI metadata",
      stance: "neutral",
      queryId: "crossref-neutral",
      query: claim,
      title: "Automated discovery fixture",
      url: "https://doi.org/10.1000/integration-fixture",
      author: "Test Author",
      date: "2026",
      publisher: "Test Publisher",
      kind: "journal-article",
      snippet: "Returned metadata is a discovery lead, not inspected evidence.",
      externalId: "10.1000/integration-fixture",
      foundBy: ["Crossref"],
      queryIds: ["crossref-neutral"],
      queryStances: ["neutral"],
      screeningExcerpt: "A testable record exists and is described in the provider metadata.",
      relevance: {
        status: "retained",
        score: 2,
        ratio: 0.5,
        requiredMatches: 2,
        matchedTerms: ["testable", "record"],
        claimTerms: ["testable", "record", "exists", "during", "2026"],
        reason: "Shown because 2 of 5 claim terms matched the title or provider description."
      },
      metadataOnly: true
    }],
    lowOverlapResults: [{
      id: "crossref-neutral-2",
      familyId: "evidence-family-2",
      familyRelationship: "single-record",
      providerId: "crossref",
      provider: "Crossref",
      repository: "Scholarly DOI metadata",
      stance: "neutral",
      queryId: "crossref-neutral",
      query: claim,
      title: "To Which Race Did Jesus Belong?",
      url: "https://doi.org/10.1000/integration-noise",
      author: "Noise Fixture Author",
      date: "2025",
      publisher: "Noise Fixture Publisher",
      kind: "journal-article",
      snippet: "Unrelated provider metadata.",
      externalId: "10.1000/integration-noise",
      foundBy: ["Crossref"],
      queryIds: ["crossref-neutral"],
      queryStances: ["neutral"],
      relevance: {
        status: "low-overlap",
        score: 0,
        ratio: 0,
        requiredMatches: 2,
        matchedTerms: [],
        claimTerms: ["testable", "record", "exists", "during", "2026"],
        reason: "Moved to the low-overlap audit because 0 of 5 claim terms matched the title or provider description; 2 were required."
      },
      metadataOnly: true
    }],
    screening: {
      policy: "claim-term-overlap-v2",
      mode: "applied",
      requiredMatchCount: 2,
      requiredAnchorMatchCount: 2,
      titleCharacterLimit: 240,
      descriptionCharacterLimit: 1000,
      returnedFamilyCount: 2,
      retainedFamilyCount: 1,
      screenedOutFamilyCount: 1
    }
  };
  return fixtureResearchRun;
};
nodes.get("run-research").focus();
const runningSweep = nodes.get("run-research").listeners.get("click")();
assert.equal(documentStub.activeElement, nodes.get("cancel-research"), "starting a sweep must move focus from the disabled Run control to Stop");
assert.equal(nodes.get("research-sweep").getAttribute("aria-busy"), "true");
await runningSweep;
assert.equal(documentStub.activeElement, nodes.get("run-research"), "finishing a sweep must return focus when Stop held focus");
assert.equal(nodes.get("research-sweep").getAttribute("aria-busy"), "false");
const sweepReceipt = JSON.parse(api.receiptPayload());
assert.equal(sweepReceipt.automated_source_sweep.status, "complete");
assert.equal(sweepReceipt.automated_source_sweep.queries.length, 6);
assert.equal(sweepReceipt.automated_source_sweep.discovered_evidence_families.length, 1);
assert.equal(sweepReceipt.automated_source_sweep.discovered_evidence_families[0].status, "DISCOVERY LEAD · NOT INSPECTED EVIDENCE");
assert.equal(sweepReceipt.automated_source_sweep.schema, "trust-worthy-source-sweep-v2");
assert.equal(sweepReceipt.automated_source_sweep.screening.policy, "claim-term-overlap-v2");
assert.equal(sweepReceipt.automated_source_sweep.screening.mode, "applied");
assert.equal(sweepReceipt.automated_source_sweep.screening.required_topic_anchor_match_count, 2);
assert.equal(sweepReceipt.automated_source_sweep.screening.provider_description_character_limit, 1000);
assert.equal(sweepReceipt.automated_source_sweep.screening.screened_out_family_count, 1);
assert.equal(sweepReceipt.automated_source_sweep.low_overlap_metadata_families.length, 1);
assert.equal(sweepReceipt.automated_source_sweep.low_overlap_metadata_families[0].status, "LOWER-OVERLAP METADATA FAMILY · NOT INSPECTED EVIDENCE");
assert.match(researchStatusText.textContent, /1 lower-overlap family remains/);
let relevanceAudit = nodes.get("research-results").children.find(node => node.tagName === "DETAILS");
assert.ok(relevanceAudit, "lower-overlap provider returns must remain inspectable in the audit drawer");
assert.match(relevanceAudit.children[0].textContent, /1 lower-overlap metadata family preserved for audit/);
let lowerOverlapLink = relevanceAudit.descendants().find(node => node.tagName === "A");
assert.match(lowerOverlapLink.getAttribute("aria-label"), /Crossref: To Which Race Did Jesus Belong/);
const firstQueryBlock = nodes.get("research-query-list").children[0].children[1];
assert.match(firstQueryBlock.children[1].textContent, /Requested UTC: 2026-09-08T12:00:00.000Z/);
assert.equal(firstQueryBlock.children[3].textContent, fixtureResearchRun.queries[0].url, "the visible query receipt must expose the exact endpoint");
const primaryAttach = nodes.get("research-results").descendants().find(node => node.dataset.researchFocusKey?.startsWith("attach:"));
primaryAttach.focus();
vm.runInContext("renderResearch();", context);
const restoredAttach = nodes.get("research-results").descendants().find(node => node.dataset.researchFocusKey === primaryAttach.dataset.researchFocusKey);
assert.equal(documentStub.activeElement, restoredAttach, "progress rerenders must preserve focus on a surviving Attach control");
relevanceAudit = nodes.get("research-results").children.find(node => node.tagName === "DETAILS");
relevanceAudit.open = true;
lowerOverlapLink = relevanceAudit.descendants().find(node => node.tagName === "A");
lowerOverlapLink.focus();
vm.runInContext("renderResearch();", context);
const restoredLowerLink = nodes.get("research-results").descendants().find(node => node.dataset.researchFocusKey === lowerOverlapLink.dataset.researchFocusKey);
assert.equal(documentStub.activeElement, restoredLowerLink, "progress rerenders must preserve focus on a surviving lower-overlap source link");
relevanceAudit = nodes.get("research-results").children.find(node => node.tagName === "DETAILS");
relevanceAudit.open = true;
relevanceAudit.children[0].focus();
const preMutationReceipt = api.receiptPayload();
fixtureResearchRun.lowOverlapResults[0].author = "Changed Lower-Overlap Author";
assert.notEqual(digest(api.receiptPayload()), digest(preMutationReceipt), "every preserved lower-overlap bibliographic field must affect the canonical fingerprint payload");
fixtureResearchRun.lowOverlapResults[0].author = "Noise Fixture Author";
assert.equal(api.receiptPayload(), preMutationReceipt, "restoring lower-overlap metadata must restore the canonical payload exactly");
vm.runInContext(`
  globalThis.__appliedResearch = state.research;
  globalThis.__appliedCoverage = state.coverage;
  state.research = {
    ...state.research,
    results: [...state.research.results, ...state.research.lowOverlapResults],
    lowOverlapResults: [],
    queries: state.research.queries.map(item => ({ ...item, retainedResultCount: item.resultCount, lowOverlapResultCount: 0 })),
    screening: {
      ...state.research.screening,
      mode: "bypassed-no-usable-terms",
      requiredMatchCount: 0,
      retainedFamilyCount: 2,
      screenedOutFamilyCount: 0
    }
  };
  globalThis.__bypassCoverage = coverageFromResearch(state.research);
  state.coverage = globalThis.__bypassCoverage;
  globalThis.__bypassReport = buildPlainReport();
  renderResearch();
`, context);
const bypassQueryState = nodes.get("research-query-list").children[0].children[2].textContent;
assert.match(bypassQueryState, /unscreened/);
assert.doesNotMatch(bypassQueryState, /higher|lower/);
assert.match(context.__bypassCoverage.scope, /metadata was left unscreened/);
assert.doesNotMatch(context.__bypassCoverage.scope, /higher-overlap counts|lower-overlap counts/);
assert.match(context.__bypassCoverage.gaps, /No higher\/lower split was made/);
assert.match(context.__bypassReport, /Display screen: bypassed/);
assert.doesNotMatch(context.__bypassReport, /0 distinct claim terms required|higher[- ]overlap|lower[- ]overlap/);
vm.runInContext("state.research = globalThis.__appliedResearch; state.coverage = globalThis.__appliedCoverage; renderResearch();", context);
const restoredAppliedAudit = nodes.get("research-results").children.find(node => node.tagName === "DETAILS");
restoredAppliedAudit.open = true;
restoredAppliedAudit.children[0].focus();
assert.match(sweepReceipt.search_coverage.scope, /Automatic Source Sweep execution receipt/);
const dynamicSweepGaps = sweepReceipt.reasoning.missing_evidence;
nodes.get("research-results").listeners.get("click")({
  target: { closest(selector) { return selector === "[data-attach-research]" ? { dataset: { attachResearch: "crossref-neutral-1" } } : null; } }
});
const attachedSweepReceipt = JSON.parse(api.receiptPayload());
const rerenderedAudit = nodes.get("research-results").children.find(node => node.tagName === "DETAILS");
assert.equal(rerenderedAudit.open, true, "the audit drawer must stay open across evidence-workbench rerenders");
assert.equal(documentStub.activeElement, rerenderedAudit.children[0], "audit focus must return to the recreated summary");
const attachedSweepLead = attachedSweepReceipt.evidence[0];
assert.equal(attachedSweepLead.className, "lead");
assert.match(attachedSweepLead.origin, /underlying artifact has not been opened or authenticated/);
assert.match(attachedSweepLead.notes, /establishes no part of the claim/);
assert.deepEqual(attachedSweepReceipt.reasoning.missing_evidence, dynamicSweepGaps, "attaching a discovery lead must retain the dynamic sweep gaps");
nodes.get("evidence-list").listeners.get("click")({
  target: { closest(selector) { return selector === "[data-remove-source]" ? { dataset: { removeSource: "0" } } : null; } }
});
const removedSweepReceipt = JSON.parse(api.receiptPayload());
assert.equal(removedSweepReceipt.evidence.length, 0);
assert.deepEqual(removedSweepReceipt.reasoning.missing_evidence, dynamicSweepGaps, "removing a discovery lead must retain the dynamic sweep gaps");
context.window.TrustResearch.runFederatedSearch = originalRunFederatedSearch;

vm.runInContext('analyze("A testable record exists during 2026.")', context);
nodes.get("coverage-scope").value = "National, state, and local public archives plus supportive, challenging, and neutral query families were searched.";
nodes.get("coverage-cutoff").value = "September 8, 2026";
nodes.get("coverage-stop").value = "Stopped after each hypothesis had a distinct test and no new evidence family appeared.";
nodes.get("coverage-gaps").value = "Private, deleted, sealed, untranslated, and undiscovered records remain outside this search.";
nodes.get("coverage-form").listeners.get("submit")({ preventDefault() {} });
assert.equal(JSON.parse(api.receiptPayload()).search_coverage.cutoff, "September 8, 2026");

nodes.get("source-title").value = "Case-sensitive source";
nodes.get("source-url").value = "https://example.com/CaseSensitive/record?id=1#page-2";
nodes.get("source-role").value = "counter";
nodes.get("source-class").value = "independent";
nodes.get("source-author").value = "Example author";
nodes.get("source-date").value = "September 8, 2026";
nodes.get("source-origin").value = "Original public record with a stated publication chain";
nodes.get("source-target").value = "H2 strongest competing explanation";
nodes.get("source-independence").value = "Separate institution and dataset; no shared upstream source is currently known.";
nodes.get("source-incentives").value = "Publication and reputation incentives exist, but no direct financial conflict is known.";
nodes.get("source-custody").value = "Publisher retains the digital original; this public copy has not been independently hashed.";
nodes.get("source-falsifier").value = "A custody break or failure to reproduce the result would materially weaken this source.";
nodes.get("source-notes").value = "This is a sufficiently complete evidence note with one explicit limitation.";
nodes.get("evidence-form").listeners.get("submit")({ preventDefault() {} });
const userReceipt = JSON.parse(api.receiptPayload());
assert.equal(userReceipt.evidence.length, 1);
assert.match(userReceipt.evidence[0].custody, /Publisher retains/);
assert.equal(api.sourceMetrics(userReceipt.evidence, userReceipt.search_coverage).coverage, true);

nodes.get("source-title").value = "Offline physical artifact";
nodes.get("source-url").value = "";
nodes.get("source-role").value = "context";
nodes.get("source-class").value = "lead";
nodes.get("source-author").value = "Custodian not yet authenticated";
nodes.get("source-date").value = "Date not yet authenticated";
nodes.get("source-target").value = "H2 strongest competing explanation";
nodes.get("source-origin").value = "Physical item supplied for inspection; upstream origin remains under review.";
nodes.get("source-independence").value = "No public link exists; ownership, source lineage, and independence remain unverified.";
nodes.get("source-incentives").value = "The supplier may have financial or reputational interests that require investigation.";
nodes.get("source-custody").value = "The current holder reports physical custody, but prior possession and transformations remain unverified.";
nodes.get("source-falsifier").value = "A custody break, mismatch, or failed authentication would disqualify the artifact.";
nodes.get("source-notes").value = "Offline lead retained for later authentication; it does not yet establish the claim.";
nodes.get("evidence-form").listeners.get("submit")({ preventDefault() {} });
assert.equal(JSON.parse(api.receiptPayload()).evidence.length, 2);
await click("save-case");
const saved = JSON.parse(storage.values().next().value);
assert.equal(saved[0].coverage.cutoff, "September 8, 2026");
assert.equal(saved[0].evidence[0].falsifier.length >= 30, true);
assert.equal(saved[0].evidence[1].url, "");
assert.equal(saved[0].map, undefined, "untrusted reasoning is rebuilt instead of accepted from storage");
assert.equal(JSON.parse(saved[0].canonicalReceipt).reasoning.hypotheses.length, 3);
assert.match(saved[0].canonicalReceipt, /trust-worthy-adversarial-record-v5/);
savedAction("open", api.localRecordKey(saved[0]));
const reopenedUserCase = JSON.parse(api.receiptPayload());
assert.equal(reopenedUserCase.curated, false);
assert.equal(reopenedUserCase.evidence.length, 2, "offline evidence must survive a local save/reopen cycle");
assert.equal(reopenedUserCase.evidence[1].url, "");

const fullDocket = Array.from({ length: 20 }, (_, index) => ({
  schema: "trust-worthy-local-snapshot-v5",
  caseId: `TW-LOCAL-FULL-${String(index + 1).padStart(2, "0")}`,
  claim: `A complete saved test claim number ${index + 1}.`,
  evidence: [],
  coverage: {},
  research: {},
  curated: false,
  savedAt: "2026-09-08T00:00:00.000Z"
}));
storage.set("trust-worthy-evidence-lab:v2", JSON.stringify(fullDocket));
vm.runInContext('analyze("A twenty-first local case must never evict an older case silently.")', context);
await click("save-case");
assert.equal(JSON.parse(storage.get("trust-worthy-evidence-lab:v2")).length, 20);
assert.match(nodes.get("toast").textContent, /docket is full/i);

// Stable saved-record keys must survive reorder, open the intended record, and delete only the intended record.
const stableAlpha = snapshotFixture({
  caseId: "TW-LOCAL-STABLE-A",
  claim: "Stable record alpha remains addressable after docket reorder.",
  savedAt: "2026-09-08T10:00:00.000Z"
});
const stableBeta = snapshotFixture({
  caseId: "TW-LOCAL-STABLE-B",
  claim: "Stable record beta remains addressable after docket reorder.",
  savedAt: "2026-09-08T11:00:00.000Z"
});
const alphaKey = api.localRecordKey(stableAlpha);
const betaKey = api.localRecordKey(stableBeta);
storage.set(STORAGE_KEY, JSON.stringify([stableAlpha, stableBeta]));
dispatchStorage();
storage.set(STORAGE_KEY, JSON.stringify([stableBeta, stableAlpha]));
dispatchStorage();
savedAction("open", alphaKey);
assert.equal(JSON.parse(api.receiptPayload()).claim, stableAlpha.claim, "reordering the docket must not make an old control open a different record");
await settle();

storageControl.failWrites = true;
const docketBeforeFailedDelete = storage.get(STORAGE_KEY);
savedAction("delete", betaKey);
assert.equal(storage.get(STORAGE_KEY), docketBeforeFailedDelete, "a failed delete write must leave the stored docket intact");
assert.match(nodes.get("toast").textContent, /saving is blocked/i);
assert.doesNotMatch(nodes.get("toast").textContent, /deleted/i, "a failed delete must never announce success");
storageControl.failWrites = false;
savedAction("delete", betaKey);
const docketAfterDelete = JSON.parse(storage.get(STORAGE_KEY));
assert.deepEqual(docketAfterDelete.map(item => item.claim), [stableAlpha.claim], "stable-key deletion must remove only the selected record");

storageControl.failWrites = true;
const docketBeforeFailedClear = storage.get(STORAGE_KEY);
await click("clear-cases");
assert.equal(storage.get(STORAGE_KEY), docketBeforeFailedClear, "a failed clear-all write must leave the stored docket intact");
assert.match(nodes.get("toast").textContent, /saving is blocked/i);
assert.doesNotMatch(nodes.get("toast").textContent, /cleared/i, "a failed clear-all must never announce success");
storageControl.failWrites = false;
storage.set(STORAGE_KEY, "{malformed local docket");
dispatchStorage();
await click("clear-cases");
assert.deepEqual(JSON.parse(storage.get(STORAGE_KEY)), [], "Clear All must overwrite a corrupt raw storage blob even when no parsed cases can render");
assert.match(nodes.get("toast").textContent, /all local cases cleared/i);

// Save failures must remain truthful and must not create a phantom local snapshot.
vm.runInContext('analyze("A blocked browser write must not be reported as a saved case.")', context);
await settle();
storageControl.failWrites = true;
await click("save-case");
assert.deepEqual(JSON.parse(storage.get(STORAGE_KEY)), []);
assert.match(nodes.get("toast").textContent, /saving is blocked/i);
assert.doesNotMatch(nodes.get("toast").textContent, /case saved/i);
storageControl.failWrites = false;

// A delayed SHA must not let Save overwrite an edited claim.
vm.runInContext('analyze("The original claim must remain distinct during a delayed save.")', context);
await settle();
const editRaceGate = armDigestGate();
const editRaceSave = click("save-case");
await editRaceGate.entered;
nodes.get("claim-input").value = "The editor now contains a materially different claim during hashing.";
nodes.get("claim-input").listeners.get("input")();
editRaceGate.release();
await editRaceSave;
await settle();
assert.deepEqual(JSON.parse(storage.get(STORAGE_KEY)), [], "editing during SHA generation must abort rather than save the stale claim");
assert.equal(nodes.get("claim-input").value, "The editor now contains a materially different claim during hashing.");
assert.equal(JSON.parse(api.receiptPayload()).claim, "The original claim must remain distinct during a delayed save.");
assert.match(nodes.get("toast").textContent, /case changed while saving/i);

// A storage event that removes an existing case during SHA must prevent resurrection.
vm.runInContext('analyze("A case deleted in another tab must never be resurrected by a delayed save.")', context);
await settle();
await click("save-case");
await settle();
assert.equal(JSON.parse(storage.get(STORAGE_KEY)).length, 1);
const deletionRaceGate = armDigestGate();
const deletionRaceSave = click("save-case");
await deletionRaceGate.entered;
storage.set(STORAGE_KEY, "[]");
dispatchStorage();
deletionRaceGate.release();
await deletionRaceSave;
await settle();
assert.deepEqual(JSON.parse(storage.get(STORAGE_KEY)), [], "an external deletion during SHA generation must not be overwritten or resurrected");
assert.match(nodes.get("toast").textContent, /docket changed in another tab/i);

// Reset must invalidate an in-flight hash and clear every outward representation of the prior claim.
const resetGate = armDigestGate();
vm.runInContext('analyze("Sensitive reset fixture must disappear from every outward field.")', context);
await resetGate.entered;
assert.match(decodeURIComponent(nodes.get("deep-link").href), /Sensitive reset fixture/);
await click("new-case");
resetGate.release();
await settle();
assert.equal(nodes.get("results").hidden, true);
assert.equal(nodes.get("claim-input").value, "");
assert.equal(nodes.get("receipt-hash").textContent, "Build a case to generate a fingerprint");
assert.equal(nodes.get("print-record-meta").textContent, "");
assert.equal(nodes.get("archived-receipt").hidden, true);
for (const id of ["triage-link", "deep-link", "hyper-link"]) {
  assert.doesNotMatch(decodeURIComponent(nodes.get(id).href), /Sensitive reset fixture/, `${id} must not retain the cleared claim`);
}
assert.equal(stageNodes.find(node => node.dataset.stage === "frame").getAttribute("aria-current"), "step");
assert.ok(stageNodes.filter(node => node.dataset.stage !== "frame").every(node => node.getAttribute("aria-current") === null));

// Manual evidence gets an application timestamp; every bounded text field strips controls and bidi overrides.
vm.runInContext('analyze("A manually entered record needs a trustworthy capture timestamp.")', context);
fillEvidenceForm({
  "source-title": "Man\u202Eual\u0000 evidence record",
  "source-date": "September\u2066 8, 2026",
  "source-notes": "This record addresses the named hypothesis\u0007 while its provenance and limitations remain explicit."
});
nodes.get("evidence-form").listeners.get("submit")({ preventDefault() {} });
const manualReceiptText = api.receiptPayload();
const manualReceipt = JSON.parse(manualReceiptText);
assert.equal(manualReceipt.evidence.length, 1);
assert.match(manualReceipt.evidence[0].recorded, /^URL recorded \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/);
assert.ok(!Number.isNaN(Date.parse(manualReceipt.evidence[0].recorded.replace(/^URL recorded /, ""))));
assert.doesNotMatch(manualReceiptText, /[\u0000-\u001F\u007F\u202A-\u202E\u2066-\u2069]/, "canonical manual evidence must not retain control or bidi formatting characters");

// Restored research must use the fixed plan and visibly remain unverified regardless of local claims.
const restoredClaim = "A restored research receipt cannot authenticate its own provider execution.";
const fixedPlan = context.window.TrustResearch.buildResearchPlan(restoredClaim);
const forgedResearch = {
  schema: "trust-worthy-source-sweep-v1",
  status: "complete",
  startedAt: "2099-01-01T00:00:00.000Z",
  completedAt: "2099-01-01T00:01:00.000Z",
  claim: "FORGED DIFFERENT CLAIM",
  queries: fixedPlan.map(item => ({
    id: item.id,
    provider: "FORGED PROVIDER",
    repository: "FORGED REPOSITORY",
    stance: "support",
    query: "FORGED QUERY",
    url: "https://forged.invalid/endpoint",
    requestedAt: "2099-01-01T00:00:00.000Z",
    completedAt: "2099-01-01T00:01:00.000Z",
    status: "complete",
    resultCount: 1,
    error: ""
  })),
  results: [{
    id: "restored-result-1",
    providerId: "forged-provider",
    provider: "FORGED PROVIDER",
    repository: "FORGED REPOSITORY",
    stance: "support",
    queryId: fixedPlan[0].id,
    queryIds: [fixedPlan[0].id],
    query: "FORGED QUERY",
    title: "Unrelated catalog entry",
    url: "https://example.com/restored-result",
    author: "Catalog author",
    date: "2026",
    publisher: "Catalog publisher",
    kind: "record",
    snippet: "A generic index entry about weather.",
    externalId: "restored-1",
    foundBy: ["FORGED PROVIDER"],
    queryStances: ["support"],
    metadataOnly: false
  }]
};
const restoredSnapshot = snapshotFixture({
  caseId: "TW-LOCAL-RESTORED",
  claim: restoredClaim,
  savedAt: "2026-09-08T12:00:00.000Z",
  coverage: {
    scope: "A forged local description claims all supportive, challenging, and neutral repositories were searched.",
    cutoff: "2099-01-01",
    stop: "A forged local stop rule claims every relevant source was exhausted.",
    gaps: "A forged local gap statement claims no inaccessible evidence remains anywhere."
  },
  coverageOrigin: "automatic",
  research: forgedResearch
});
storage.set(STORAGE_KEY, JSON.stringify([restoredSnapshot]));
dispatchStorage();
savedAction("open", api.localRecordKey(restoredSnapshot));
await settle();
const restoredReceipt = JSON.parse(api.receiptPayload());
const restoredSweep = restoredReceipt.automated_source_sweep;
assert.equal(restoredSweep.status, "restored-unverified");
assert.equal(restoredSweep.schema, "trust-worthy-source-sweep-v2");
assert.equal(restoredSweep.queries.length, fixedPlan.length);
assert.deepEqual(restoredSweep.queries.map(item => item.provider), Array.from(fixedPlan, item => item.provider));
assert.deepEqual(restoredSweep.queries.map(item => item.endpoint), Array.from(fixedPlan, item => item.url));
assert.doesNotMatch(JSON.stringify(restoredSweep), /FORGED PROVIDER|FORGED REPOSITORY|FORGED QUERY|forged\.invalid/);
assert.ok(restoredSweep.discovered_evidence_families.every(item => item.providers.every(provider => fixedPlan.some(lane => lane.provider === provider))));
assert.equal(restoredSweep.discovered_evidence_families.length, 0, "legacy v1 metadata must be re-screened instead of being forced into the primary list");
assert.equal(restoredSweep.low_overlap_metadata_families.length, 1);
assert.equal(restoredSweep.screening.screened_out_family_count, 1);
assert.equal(researchStatusCode.textContent, "RESTORED · EXECUTION UNVERIFIED");
assert.equal(readinessCards.get("coverage").strong.textContent, "Missing", "restored coverage cannot self-award the coverage safeguard");
assert.match(restoredReceipt.reasoning.missing_evidence.join(" "), /reset to the fixed Source Sweep plan/i);

// A forged v2 bucket cannot force high-overlap metadata into the lower-overlap drawer.
const forgedV2Research = {
  ...forgedResearch,
  schema: "trust-worthy-source-sweep-v2",
  results: [],
  lowOverlapResults: [{
    ...forgedResearch.results[0],
    id: "forged-low-bucket",
    title: "Provider execution research receipt",
    snippet: "A restored research receipt about provider execution."
  }]
};
const forgedV2Snapshot = snapshotFixture({
  caseId: "TW-LOCAL-FORGED-BUCKET",
  claim: restoredClaim,
  savedAt: "2026-09-08T12:02:00.000Z",
  research: forgedV2Research
});
storage.set(STORAGE_KEY, JSON.stringify([forgedV2Snapshot]));
dispatchStorage();
savedAction("open", api.localRecordKey(forgedV2Snapshot));
await settle();
const reclassifiedSweep = JSON.parse(api.receiptPayload()).automated_source_sweep;
assert.equal(reclassifiedSweep.discovered_evidence_families.length, 1);
assert.equal(reclassifiedSweep.low_overlap_metadata_families.length, 0);
assert.equal(reclassifiedSweep.screening.retained_family_count, 1);

// Coverage and evidence drafts stay isolated: one form's submit preserves the other draft, while case changes clear both.
vm.runInContext('analyze("Draft isolation must preserve work only inside the current case.")', context);
fillCoverageForm();
const pendingCoverageScope = nodes.get("coverage-scope").value;
fillEvidenceForm({ "source-title": "Submitted source while coverage remains a draft" });
nodes.get("evidence-form").listeners.get("submit")({ preventDefault() {} });
assert.equal(nodes.get("coverage-scope").value, pendingCoverageScope, "submitting evidence must not erase the unsubmitted coverage draft");
nodes.get("source-title").value = "Unsubmitted evidence draft survives coverage rendering";
nodes.get("source-notes").value = "Unsubmitted evidence notes must remain scoped to this case until navigation changes the case.";
nodes.get("coverage-form").listeners.get("submit")({ preventDefault() {} });
assert.equal(nodes.get("source-title").value, "Unsubmitted evidence draft survives coverage rendering", "submitting coverage must not erase an evidence draft");
vm.runInContext('analyze("Opening a different case must clear every transient form draft.")', context);
assert.ok(coverageControlIds.every(id => nodes.get(id).value === ""));
assert.ok(evidenceControlIds.filter(id => !["source-role", "source-class"].includes(id)).every(id => nodes.get(id).value === ""));
assert.equal(nodes.get("source-role").value, "support");
assert.equal(nodes.get("source-class").value, "primary");

// Save-time receipts remain immutable, cryptographically verified, and independently copyable after current-state changes and reopen.
storage.set(STORAGE_KEY, "[]");
dispatchStorage();
vm.runInContext('analyze("An archived receipt must remain verifiable after the live case changes.")', context);
fillEvidenceForm({ "source-title": "Archived receipt source fixture" });
nodes.get("evidence-form").listeners.get("submit")({ preventDefault() {} });
await settle();
await click("save-case");
await settle();
const archivedSnapshot = JSON.parse(storage.get(STORAGE_KEY))[0];
assert.ok(archivedSnapshot.canonicalReceipt);
assert.equal(archivedSnapshot.canonicalReceiptHash, digest(archivedSnapshot.canonicalReceipt));
assert.equal(nodes.get("archived-receipt").hidden, false);
assert.equal(nodes.get("archived-receipt-hash").textContent, archivedSnapshot.canonicalReceiptHash);
assert.match(nodes.get("archived-receipt-status").textContent, /^MATCH/);
assert.equal(nodes.get("copy-archived-hash").disabled, false);
assert.equal(nodes.get("copy-archived-json").disabled, false);
await click("copy-archived-hash");
assert.equal(clipboardWrites.at(-1), archivedSnapshot.canonicalReceiptHash);
await click("copy-archived-json");
assert.equal(clipboardWrites.at(-1), archivedSnapshot.canonicalReceipt);
fillCoverageForm();
nodes.get("coverage-form").listeners.get("submit")({ preventDefault() {} });
await settle();
assert.equal(nodes.get("archived-receipt-hash").textContent, archivedSnapshot.canonicalReceiptHash, "live edits must not rewrite the archived save-time fingerprint");
await click("copy-archived-json");
assert.equal(clipboardWrites.at(-1), archivedSnapshot.canonicalReceipt, "live edits must not rewrite the archived save-time JSON");
savedAction("open", api.localRecordKey(archivedSnapshot));
await settle();
assert.match(nodes.get("archived-receipt-status").textContent, /^MATCH/, "reopening a saved case must verify its archived receipt");
await click("copy-archived-hash");
assert.equal(clipboardWrites.at(-1), archivedSnapshot.canonicalReceiptHash);

// Local-only forms must identify and focus the exact field that failed validation.
vm.runInContext('analyze("Accessible local validation must identify the field that needs attention.")', context);
nodes.get("coverage-form").listeners.get("submit")({ preventDefault() {} });
assert.equal(nodes.get("coverage-scope").getAttribute("aria-invalid"), "true");
assert.equal(documentStub.activeElement, nodes.get("coverage-scope"));
nodes.get("coverage-form").listeners.get("input")({ target: nodes.get("coverage-scope") });
assert.equal(nodes.get("coverage-scope").getAttribute("aria-invalid"), null);
assert.equal(nodes.get("coverage-error").textContent, "");
nodes.get("evidence-form").listeners.get("submit")({ preventDefault() {} });
assert.equal(nodes.get("source-title").getAttribute("aria-invalid"), "true");
assert.equal(documentStub.activeElement, nodes.get("source-title"));
vm.runInContext("resetCase();", context);
nodes.get("claim-input").value = "short";
nodes.get("claim-form").listeners.get("submit")({ preventDefault() {} });
assert.equal(nodes.get("claim-input").getAttribute("aria-invalid"), "true");
assert.equal(documentStub.activeElement, nodes.get("claim-input"));

const blankMetrics = api.sourceMetrics([{ url: "", role: "support", className: "primary" }]);
assert.ok(Object.values(blankMetrics).every(value => value === false));
assert.ok(api.sourceFlags({ url: "", role: "support", className: "lead" }, []).length >= 6);

console.log("App smoke checks passed: reviewed dossiers, stable docket identity, truthful storage failure, async race safety, accessible focus lifecycle and validation, reset privacy, sanitization, restored-sweep distrust, draft isolation, and archived receipt verification");
