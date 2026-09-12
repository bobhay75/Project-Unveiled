import assert from "node:assert/strict";
import fs from "node:fs";
import vm from "node:vm";

const context = vm.createContext({
  console,
  URL,
  Date,
  AbortController,
  window: { setTimeout, clearTimeout }
});
const source = fs.readFileSync(new URL("../../truth/lab/research-sweep.js", import.meta.url), "utf8");
vm.runInContext(source, context, { filename: "research-sweep.js" });
const api = context.window.TrustResearch;

const claim = "Did crews collect lunar samples on the Moon?";
const plan = api.buildResearchPlan(claim);
assert.equal(plan.length, 6);
assert.deepEqual([...new Set(plan.map(item => item.stance))].sort(), ["challenge", "neutral", "support"]);
assert.deepEqual(
  [...new Set(plan.map(item => new URL(item.url).hostname))].sort(),
  ["api.crossref.org", "api.openalex.org", "archive.org", "www.ebi.ac.uk", "www.federalregister.gov"].sort()
);
assert.ok(plan.every(item => item.query.toLowerCase().includes("lunar")), "every lane must derive from the claim");
assert.ok(plan.every(item => new URL(item.url).protocol === "https:"), "provider requests must use HTTPS");

assert.equal(api.safeHttpUrl("javascript:alert(1)"), "");
assert.equal(api.safeHttpUrl("https://user@example.com/record"), "");
assert.equal(api.safeHttpUrl("http://127.0.0.1/record"), "");
assert.equal(api.safeHttpUrl("http://100.64.0.1/record"), "");
assert.equal(api.safeHttpUrl("https://[fd00::1]/record"), "");
assert.equal(api.safeHttpUrl("https://example.com/record#fragment"), "https://example.com/record");
assert.equal(api.cleanText("&lt;img src=x onerror=alert(1)&gt; Visible\u202E title"), "Visible title");
assert.doesNotThrow(() => api.mergeEvidenceFamilies([
  { title: "Malformed DOI A", url: "https://doi.org/10.1000/%ZZ", foundBy: ["A"], queryIds: ["a"], queryStances: ["neutral"], stance: "neutral" },
  { title: "Malformed DOI B", url: "https://doi.org/10.1000/%ZZ", foundBy: ["B"], queryIds: ["b"], queryStances: ["challenge"], stance: "challenge" }
]));

const sameTitleDistinctArtifacts = api.mergeEvidenceFamilies([
  {
    id: "alpha-result",
    providerId: "alpha",
    provider: "Alpha Catalog",
    stance: "support",
    queryId: "alpha-support",
    queryIds: ["alpha-support"],
    queryStances: ["support"],
    foundBy: ["Alpha Catalog"],
    title: "Proceedings of the Test Commission",
    url: "https://alpha.example/records/volume-one",
    externalId: "record-7",
    author: "Alice Author",
    snippet: "First artifact metadata."
  },
  {
    id: "beta-result",
    providerId: "beta",
    provider: "Beta Catalog",
    stance: "challenge",
    queryId: "beta-challenge",
    queryIds: ["beta-challenge"],
    queryStances: ["challenge"],
    foundBy: ["Beta Catalog"],
    title: "Proceedings of the Test Commission!",
    url: "https://beta.example/archive/another-work",
    externalId: "record-7",
    author: "Beatrice Author",
    snippet: "Second, distinct artifact metadata."
  }
]);
assert.equal(sameTitleDistinctArtifacts.length, 2, "title equality and cross-provider ID text must never establish identity");
assert.ok(sameTitleDistinctArtifacts.every(item => item.familyRelationship === "single-record"));
assert.ok(sameTitleDistinctArtifacts.every(item => item.variants.length === 1), "each title-only variant must remain inspectable");
assert.deepEqual(
  [...new Set(sameTitleDistinctArtifacts.map(item => item.url))].sort(),
  ["https://alpha.example/records/volume-one", "https://beta.example/archive/another-work"].sort()
);
assert.deepEqual(
  [...sameTitleDistinctArtifacts[0].possibleDuplicateCluster.memberFamilyIds].sort(),
  [...sameTitleDistinctArtifacts[1].possibleDuplicateCluster.memberFamilyIds].sort()
);
assert.equal(sameTitleDistinctArtifacts[0].possibleDuplicateCluster.decision, "retained-separately");
assert.match(sameTitleDistinctArtifacts[0].possibleDuplicateCluster.reason, /no shared stable identifier or canonical URL/i);

const sharedDoiDifferentUrls = api.mergeEvidenceFamilies([
  {
    id: "doi-left",
    providerId: "crossref",
    provider: "Crossref",
    stance: "neutral",
    queryIds: ["crossref-neutral"],
    queryStances: ["neutral"],
    foundBy: ["Crossref"],
    title: "Repository title variant A",
    url: "https://publisher.example/article/landing",
    externalId: "10.1234/Case-Sensitive-Suffix"
  },
  {
    id: "doi-right",
    providerId: "openalex",
    provider: "OpenAlex",
    stance: "challenge",
    queryIds: ["openalex-challenge"],
    queryStances: ["challenge"],
    foundBy: ["OpenAlex"],
    title: "Repository title variant B",
    url: "https://openalex.org/W123456",
    externalId: "https://doi.org/10.1234/case-sensitive-suffix"
  }
]);
assert.equal(sharedDoiDifferentUrls.length, 1, "a shared DOI may merge variants even when repository URLs and titles differ");
assert.equal(sharedDoiDifferentUrls[0].variants.length, 2);
assert.ok(sharedDoiDifferentUrls[0].mergeDecisions.some(decision =>
  decision.reason === "shared-stable-identifier" && decision.match === "doi:10.1234/case-sensitive-suffix"
));
assert.deepEqual([...sharedDoiDifferentUrls[0].foundBy].sort(), ["Crossref", "OpenAlex"]);
assert.deepEqual([...sharedDoiDifferentUrls[0].queryStances].sort(), ["challenge", "neutral"]);
assert.deepEqual(
  [...new Set(sharedDoiDifferentUrls[0].variants.map(item => item.url))].sort(),
  ["https://openalex.org/W123456", "https://publisher.example/article/landing"].sort(),
  "merged families must retain every provider URL"
);

const sharedCanonicalUrl = api.mergeEvidenceFamilies([
  {
    id: "url-left",
    providerId: "one",
    provider: "Catalog One",
    title: "Catalog wording one",
    url: "https://records.example:443/item/42#summary",
    foundBy: ["Catalog One"],
    queryIds: ["one-neutral"],
    queryStances: ["neutral"],
    stance: "neutral"
  },
  {
    id: "url-right",
    providerId: "two",
    provider: "Catalog Two",
    title: "Catalog wording two",
    url: "https://records.example/item/42",
    foundBy: ["Catalog Two"],
    queryIds: ["two-support"],
    queryStances: ["support"],
    stance: "support"
  }
]);
assert.equal(sharedCanonicalUrl.length, 1, "safe URL normalization may establish an identity relationship");
assert.ok(sharedCanonicalUrl[0].mergeDecisions.some(decision => decision.reason === "shared-normalized-canonical-url"));

const identityBridge = api.mergeEvidenceFamilies([
  {
    id: "bridge-a",
    providerId: "catalog",
    provider: "Catalog",
    title: "Bridge A",
    url: "https://records.example/a",
    externalId: "A-1",
    foundBy: ["Catalog"],
    queryIds: ["q-a"],
    queryStances: ["neutral"]
  },
  {
    id: "bridge-b",
    providerId: "catalog",
    provider: "Catalog",
    title: "Bridge B",
    url: "https://records.example/b",
    externalId: "B-1",
    foundBy: ["Catalog"],
    queryIds: ["q-b"],
    queryStances: ["support"]
  },
  {
    id: "bridge-link",
    providerId: "catalog",
    provider: "Catalog",
    title: "Bridge link",
    url: "https://records.example/b",
    externalId: "A-1",
    foundBy: ["Catalog"],
    queryIds: ["q-link"],
    queryStances: ["challenge"]
  }
]);
assert.equal(identityBridge.length, 1, "identity relationships must merge transitively without relying on titles");
assert.equal(identityBridge[0].variants.length, 3);
assert.deepEqual([...identityBridge[0].queryIds].sort(), ["q-a", "q-b", "q-link"]);
assert.ok(identityBridge[0].mergeDecisions.length >= 2, "the returned family must record an auditable identity path");

function fixtureFor(url) {
  const target = new URL(url);
  if (target.hostname === "api.crossref.org") {
    return {
      message: {
        items: [{
          title: ["Independent lunar sample analysis"],
          DOI: "10.1000/shared",
          author: [{ given: "Ada", family: "Researcher" }],
          published: { "date-parts": [[2024, 1, 2]] },
          publisher: "Test Journal",
          type: "journal-article",
          abstract: "&lt;script&gt;alert(1)&lt;/script&gt; Sample evidence abstract."
        }]
      }
    };
  }
  if (target.hostname === "api.openalex.org") {
    return {
      results: [{
        display_name: "Independent lunar sample analysis",
        doi: "https://doi.org/10.1000/shared",
        authorships: [{ author: { display_name: "Ada Researcher" } }],
        publication_date: "2024-01-02",
        primary_location: { source: { display_name: "Test Journal" } },
        type: "article"
      }]
    };
  }
  if (target.hostname === "www.ebi.ac.uk") {
    return {
      resultList: {
        result: [{
          title: "A separate material assay",
          doi: "10.2000/separate",
          authorString: "B. Analyst",
          firstPublicationDate: "2023-04-05",
          journalTitle: "Assay Review",
          pubType: "research article",
          abstractText: "A distinct record family."
        }]
      }
    };
  }
  if (target.hostname === "archive.org") {
    return {
      response: {
        docs: [{
          identifier: "shared-archive-item",
          title: "&lt;img src=x onerror=alert(1)&gt; Archive mission record",
          creator: "Public uploader",
          date: "1970",
          description: "Digitized catalog lead only.",
          mediatype: "texts"
        }]
      }
    };
  }
  if (target.hostname === "www.federalregister.gov") {
    return {
      results: [{
        title: "Federal custody notice",
        html_url: "https://www.federalregister.gov/documents/2025/01/01/example",
        agencies: [{ name: "Records Agency" }],
        publication_date: "2025-01-01",
        type: "Notice",
        abstract: "An indexed government notice.",
        document_number: "2025-00001"
      }]
    };
  }
  throw new Error(`Unexpected provider ${target.hostname}`);
}

const requested = [];
const fetchFixture = async (url, options) => {
  requested.push({ url, options });
  return { ok: true, status: 200, json: async () => fixtureFor(url) };
};
const result = await api.runFederatedSearch(claim, { fetchImpl: fetchFixture, timeoutMs: 1000 });
assert.equal(result.schema, "trust-worthy-source-sweep-v1");
assert.equal(result.queries.length, 6);
assert.ok(result.queries.every(item => item.status === "complete"));
assert.equal(requested.length, 6);
assert.ok(requested.every(item => item.options.credentials === "omit" && item.options.referrerPolicy === "no-referrer"));
assert.equal(result.results.length, 4, "duplicate DOI and archive families must collapse");
assert.ok(result.results.every(item => item.metadataOnly === true));
assert.ok(result.results.every(item => !/[<>\u202E]/.test(item.title + item.snippet)), "hostile markup and bidi controls must be removed");

const doiFamily = result.results.find(item => item.url === "https://doi.org/10.1000/shared");
assert.deepEqual([...doiFamily.foundBy].sort(), ["Crossref", "OpenAlex"]);
assert.equal(doiFamily.queryIds.length, 2);
assert.equal(doiFamily.familyRelationship, "merged-by-identity");
assert.equal(doiFamily.variants.length, 2, "DOI merge must preserve both provider records");
assert.ok(doiFamily.mergeDecisions.some(decision => decision.reason === "shared-stable-identifier"));
const archiveFamily = result.results.find(item => item.url.includes("archive.org/details/shared-archive-item"));
assert.deepEqual([...archiveFamily.queryStances].sort(), ["challenge", "support"]);
assert.equal(archiveFamily.variants.length, 2, "same archive URL in opposing lanes must retain both query variants");
assert.ok(archiveFamily.mergeDecisions.some(decision => decision.reason === "shared-normalized-canonical-url"));

const fetchWithFailure = async url => {
  if (new URL(url).hostname === "api.openalex.org") return { ok: false, status: 429, json: async () => ({}) };
  return { ok: true, status: 200, json: async () => fixtureFor(url) };
};
const partial = await api.runFederatedSearch(claim, { fetchImpl: fetchWithFailure, timeoutMs: 1000 });
const failed = partial.queries.filter(item => item.status === "failed");
assert.equal(failed.length, 1);
assert.equal(failed[0].provider, "OpenAlex");
assert.equal(failed[0].error, "HTTP 429");
assert.ok(partial.results.length > 0, "one provider failure must not discard completed lanes");

const neverResponds = async (url, options) => new Promise((resolve, reject) => {
  options.signal?.addEventListener("abort", () => {
    const error = new Error("aborted");
    error.name = "AbortError";
    reject(error);
  }, { once: true });
});
const timedOut = await api.runFederatedSearch(claim, { fetchImpl: neverResponds, timeoutMs: 5 });
assert.ok(timedOut.queries.every(item => item.status === "failed"), "provider timeouts must be failures, not user cancellations");
assert.ok(timedOut.queries.every(item => /timed out after 5 ms/i.test(item.error)));

console.log("Source Sweep checks passed: fixed endpoints, query diversity, hostile-text cleanup, identity-only family merges, title-only duplicate retention, auditable merge lineage, visible partial failure, and timeout classification");
