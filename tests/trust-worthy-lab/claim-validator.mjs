const PROOF_STATES = Object.freeze({
  VERIFIED: "verified",
  QUESTIONABLE: "questionable",
  MANIPULATED: "manipulated",
  UNKNOWN: "unknown",
});

const DELIVERIES = Object.freeze({
  REVIEW: "candidate_for_human_review",
  BLOCK: "block_and_escalate",
});

function sorted(values) {
  return [...new Set(values)].sort();
}

function isNonEmptyString(value) {
  return typeof value === "string" && value.trim() === value && value.length > 0;
}

function validateStringArray(value, path, errors, { allowEmpty = true } = {}) {
  if (!Array.isArray(value)) {
    errors.push(`${path} must be an array`);
    return [];
  }

  if (!allowEmpty && value.length === 0) {
    errors.push(`${path} must not be empty`);
  }

  const validValues = [];
  for (const [index, item] of value.entries()) {
    if (!isNonEmptyString(item)) {
      errors.push(`${path}[${index}] must be a non-empty, trimmed string`);
      continue;
    }
    validValues.push(item);
  }

  if (new Set(validValues).size !== validValues.length) {
    errors.push(`${path} must not contain duplicates`);
  }

  return validValues;
}

function invalidResult(caseId, errors) {
  return Object.freeze({
    case_id: isNonEmptyString(caseId) ? caseId : null,
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
    validation_errors: sorted(errors),
  });
}

function validateInput(testCase) {
  const errors = [];
  if (!testCase || typeof testCase !== "object" || Array.isArray(testCase)) {
    return { errors: ["case must be an object"] };
  }

  if (!isNonEmptyString(testCase.id)) {
    errors.push("id must be a non-empty, trimmed string");
  }

  if (!testCase.claim || typeof testCase.claim !== "object" || Array.isArray(testCase.claim)) {
    errors.push("claim must be an object");
  }

  const claim = testCase.claim && typeof testCase.claim === "object" ? testCase.claim : {};
  if (!isNonEmptyString(claim.text)) {
    errors.push("claim.text must be a non-empty, trimmed string");
  }
  const requiredAtoms = validateStringArray(
    claim.required_atoms,
    "claim.required_atoms",
    errors,
    { allowEmpty: false },
  );
  if (!Number.isInteger(claim.minimum_independent_origins) || claim.minimum_independent_origins < 1) {
    errors.push("claim.minimum_independent_origins must be an integer of at least 1");
  }

  const citations = validateStringArray(testCase.citations, "citations", errors, { allowEmpty: false });
  const driverSourceIds = validateStringArray(
    testCase.driver_source_ids,
    "driver_source_ids",
    errors,
    { allowEmpty: false },
  );

  if (!Array.isArray(testCase.sources) || testCase.sources.length === 0) {
    errors.push("sources must be a non-empty array");
  }

  const sources = [];
  const sourceIds = [];
  for (const [index, source] of (Array.isArray(testCase.sources) ? testCase.sources : []).entries()) {
    const path = `sources[${index}]`;
    if (!source || typeof source !== "object" || Array.isArray(source)) {
      errors.push(`${path} must be an object`);
      continue;
    }
    if (!isNonEmptyString(source.id)) {
      errors.push(`${path}.id must be a non-empty, trimmed string`);
    } else {
      sourceIds.push(source.id);
    }
    if (!isNonEmptyString(source.origin_id)) {
      errors.push(`${path}.origin_id must be a non-empty, trimmed string`);
    }
    const supports = validateStringArray(source.supports, `${path}.supports`, errors);
    const contradicts = validateStringArray(source.contradicts, `${path}.contradicts`, errors);
    const overlaps = supports.filter((atom) => contradicts.includes(atom));
    if (overlaps.length > 0) {
      errors.push(`${path} cannot support and contradict the same atom`);
    }
    sources.push({
      id: source.id,
      origin_id: source.origin_id,
      supports,
      contradicts,
    });
  }

  if (new Set(sourceIds).size !== sourceIds.length) {
    errors.push("sources must have unique ids");
  }

  const requiredAtomSet = new Set(requiredAtoms);
  for (const source of sources) {
    for (const atom of [...source.supports, ...source.contradicts]) {
      if (!requiredAtomSet.has(atom)) {
        errors.push(`source ${source.id || "<unknown>"} references undeclared atom ${atom}`);
      }
    }
  }

  const sourceIdSet = new Set(sourceIds);
  for (const citation of citations) {
    if (!sourceIdSet.has(citation)) {
      errors.push(`citation references unknown source ${citation}`);
    }
  }
  for (const driverId of driverSourceIds) {
    if (!sourceIdSet.has(driverId)) {
      errors.push(`driver_source_ids references unknown source ${driverId}`);
    }
  }

  return {
    errors,
    claim,
    requiredAtoms,
    citations,
    driverSourceIds,
    sources,
  };
}

export function evaluateClaim(testCase) {
  const validated = validateInput(testCase);
  if (validated.errors.length > 0) {
    return invalidResult(testCase && testCase.id, validated.errors);
  }

  const { claim, requiredAtoms, citations, driverSourceIds, sources } = validated;
  const sourceById = new Map(sources.map((source) => [source.id, source]));
  const citedSources = citations.map((id) => sourceById.get(id));
  const citationSet = new Set(citations);
  const citedOrigins = new Set(citedSources.map((source) => source.origin_id));
  const requiredAtomSet = new Set(requiredAtoms);
  const supportingOrigins = new Set(
    citedSources
      .filter((source) => source.supports.some((atom) => requiredAtomSet.has(atom)))
      .map((source) => source.origin_id),
  );

  const supported = new Set();
  const contradicted = new Set();
  for (const source of citedSources) {
    source.supports.forEach((atom) => supported.add(atom));
    source.contradicts.forEach((atom) => contradicted.add(atom));
  }

  const supportedAtoms = sorted(requiredAtoms.filter((atom) => supported.has(atom)));
  const contradictedAtoms = sorted(requiredAtoms.filter((atom) => contradicted.has(atom)));
  const missingAtoms = sorted(
    requiredAtoms.filter((atom) => !supported.has(atom) && !contradicted.has(atom)),
  );
  const uncitedDrivers = driverSourceIds.filter((id) => !citationSet.has(id));
  const unrepresentedDriverOrigins = driverSourceIds
    .map((id) => sourceById.get(id).origin_id)
    .filter((originId) => !citedOrigins.has(originId));

  const base = {
    case_id: testCase.id,
    human_review_required: true,
    publish_allowed: false,
    supported_atoms: supportedAtoms,
    missing_atoms: missingAtoms,
    contradicted_atoms: contradictedAtoms,
    independent_origin_count: supportingOrigins.size,
    validation_errors: [],
  };

  if (uncitedDrivers.length > 0 || unrepresentedDriverOrigins.length > 0) {
    return Object.freeze({
      ...base,
      proof_state: PROOF_STATES.MANIPULATED,
      assessment: "provenance_mismatch",
      delivery: DELIVERIES.BLOCK,
      reason_codes: ["causal_source_not_cited"],
    });
  }

  if (contradictedAtoms.length > 0) {
    return Object.freeze({
      ...base,
      proof_state: PROOF_STATES.QUESTIONABLE,
      assessment: "contradicted",
      delivery: DELIVERIES.BLOCK,
      reason_codes: ["cited_source_contradicts_claim"],
    });
  }

  if (supportedAtoms.length < requiredAtoms.length) {
    return Object.freeze({
      ...base,
      proof_state: PROOF_STATES.QUESTIONABLE,
      assessment: "partially_supported",
      delivery: DELIVERIES.BLOCK,
      reason_codes: ["required_claim_atom_unsubstantiated"],
    });
  }

  if (supportingOrigins.size < claim.minimum_independent_origins) {
    return Object.freeze({
      ...base,
      proof_state: PROOF_STATES.QUESTIONABLE,
      assessment: "insufficient_independence",
      delivery: DELIVERIES.BLOCK,
      reason_codes: ["duplicate_origin_does_not_corroborate"],
    });
  }

  return Object.freeze({
    ...base,
    proof_state: PROOF_STATES.VERIFIED,
    assessment: "fully_supported",
    delivery: DELIVERIES.REVIEW,
    reason_codes: ["structured_evidence_checks_passed"],
  });
}

export { DELIVERIES, PROOF_STATES };
