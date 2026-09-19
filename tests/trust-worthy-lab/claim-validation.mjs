import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

import { DELIVERIES, PROOF_STATES, evaluateClaim } from "./claim-validator.mjs";

const fixtureUrl = new URL("./fixtures/claim-validation-cases.json", import.meta.url);
const corpus = JSON.parse(readFileSync(fileURLToPath(fixtureUrl), "utf8"));
const expectedCategories = [
  "supported",
  "partially_supported",
  "contradicted",
  "duplicate_origin",
  "citation_laundering",
];

assert.equal(corpus.schema_version, 1, "corpus schema_version must be 1");
assert.ok(Array.isArray(corpus.cases), "corpus cases must be an array");
assert.equal(corpus.cases.length, 25, "corpus must contain exactly 25 cases");
assert.equal(new Set(corpus.cases.map((testCase) => testCase.id)).size, 25, "case ids must be unique");

const categoryCounts = Object.fromEntries(expectedCategories.map((category) => [category, 0]));

for (const testCase of corpus.cases) {
  assert.ok(
    Object.hasOwn(categoryCounts, testCase.category),
    `${testCase.id} has an unexpected category`,
  );
  categoryCounts[testCase.category] += 1;

  const result = evaluateClaim(testCase);
  assert.equal(result.proof_state, testCase.expected.proof_state, `${testCase.id} proof state`);
  assert.equal(result.assessment, testCase.expected.assessment, `${testCase.id} assessment`);
  assert.equal(result.delivery, testCase.expected.delivery, `${testCase.id} delivery`);
  assert.equal(result.publish_allowed, false, `${testCase.id} must never auto-publish`);
  assert.equal(result.human_review_required, true, `${testCase.id} must require human review`);

  if (testCase.category === "supported") {
    assert.equal(result.delivery, DELIVERIES.REVIEW, `${testCase.id} should enter review`);
  } else {
    assert.equal(result.delivery, DELIVERIES.BLOCK, `${testCase.id} should fail closed`);
  }
}

for (const category of expectedCategories) {
  assert.equal(categoryCounts[category], 5, `${category} must contain exactly five cases`);
}

const supportedCases = corpus.cases.filter((testCase) => testCase.category === "supported");
for (const testCase of supportedCases) {
  const oneCitation = structuredClone(testCase);
  oneCitation.citations = [testCase.citations[0]];
  oneCitation.driver_source_ids = [testCase.citations[0]];
  const result = evaluateClaim(oneCitation);
  assert.equal(result.proof_state, PROOF_STATES.QUESTIONABLE, `${testCase.id} must lose verification`);
  assert.equal(result.assessment, "insufficient_independence", `${testCase.id} must detect one origin`);
  assert.equal(result.delivery, DELIVERIES.BLOCK, `${testCase.id} must block after citation removal`);
}

const unknownCitation = structuredClone(supportedCases[0]);
unknownCitation.citations.push("source-that-does-not-exist");
assert.deepEqual(
  evaluateClaim(unknownCitation),
  {
    case_id: unknownCitation.id,
    proof_state: PROOF_STATES.UNKNOWN,
    assessment: "invalid_input",
    delivery: DELIVERIES.BLOCK,
    human_review_required: true,
    publish_allowed: false,
    supported_atoms: [],
    missing_atoms: [],
    contradicted_atoms: [],
    independent_origin_count: 0,
    reason_codes: ["invalid_input"],
    validation_errors: ["citation references unknown source source-that-does-not-exist"],
  },
  "unknown citations must fail closed",
);

const duplicateSource = structuredClone(supportedCases[1]);
duplicateSource.sources.push(structuredClone(duplicateSource.sources[0]));
const duplicateResult = evaluateClaim(duplicateSource);
assert.equal(duplicateResult.proof_state, PROOF_STATES.UNKNOWN, "duplicate source ids must be unknown");
assert.equal(duplicateResult.delivery, DELIVERIES.BLOCK, "duplicate source ids must block");

const uncitedDriver = structuredClone(supportedCases[2]);
uncitedDriver.sources.push({
  id: "shadow-driver",
  origin_id: "shadow-origin",
  supports: [...uncitedDriver.claim.required_atoms],
  contradicts: [],
});
uncitedDriver.driver_source_ids.push("shadow-driver");
const launderingResult = evaluateClaim(uncitedDriver);
assert.equal(launderingResult.proof_state, PROOF_STATES.MANIPULATED, "uncited drivers must be manipulated");
assert.equal(launderingResult.assessment, "provenance_mismatch", "uncited drivers must expose provenance mismatch");
assert.equal(launderingResult.delivery, DELIVERIES.BLOCK, "uncited drivers must block");

const paddedCitation = structuredClone(supportedCases[3]);
paddedCitation.citations = [paddedCitation.citations[0], "unrelated-citation"];
paddedCitation.driver_source_ids = [...paddedCitation.citations];
paddedCitation.sources.push({
  id: "unrelated-citation",
  origin_id: "unrelated-origin",
  supports: [],
  contradicts: [],
});
const paddingResult = evaluateClaim(paddedCitation);
assert.equal(paddingResult.proof_state, PROOF_STATES.QUESTIONABLE, "citation padding must not verify");
assert.equal(paddingResult.assessment, "insufficient_independence", "irrelevant origins must not corroborate");
assert.equal(paddingResult.independent_origin_count, 1, "only supporting origins may count");
assert.equal(paddingResult.delivery, DELIVERIES.BLOCK, "citation padding must block");

for (const testCase of corpus.cases) {
  const reordered = structuredClone(testCase);
  reordered.claim.required_atoms.reverse();
  reordered.citations.reverse();
  reordered.driver_source_ids.reverse();
  reordered.sources.reverse();
  reordered.sources.forEach((source) => {
    source.supports.reverse();
    source.contradicts.reverse();
  });
  assert.deepEqual(evaluateClaim(reordered), evaluateClaim(testCase), `${testCase.id} must be order-invariant`);
}

console.log(
  `Claim-validation gate passed: ${corpus.cases.length} cases ` +
    `(${expectedCategories.map((category) => `${category}=${categoryCounts[category]}`).join(", ")}); ` +
    "fail-closed mutations and human-review boundary passed.",
);
