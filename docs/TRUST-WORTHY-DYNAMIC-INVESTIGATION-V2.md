# Trust-Worthy Dynamic Investigation V2

## Product correction

Trust-Worthy must not present a short web-assisted synthesis as a deep investigation. The investigation experience and the engine must use the same receipts.

## Entry experience

The public entry screen asks one primary question: **What do you want to know is true?** A visitor can provide a claim, question, URL, post text, or supporting context. Pricing is not a prerequisite to discovering value.

Implemented in this V2 branch: the oversized command-center landing page is replaced by a focused claim entry, an explicit Guided Deep Investigation route, and a separately labeled Quick Preliminary Check.

## Guided investigation

### Stage 1 — Understand
The engine decomposes the submission into independently testable assertions and identifies literal claims, implied claims, framing, entities, dates, and assumptions.

### Stage 2 — Direct
Before Deep research begins, the workspace lets the user select what matters: verify the event, test misleading framing, trace origin, prioritize primary evidence, seek counterevidence, and/or build chronology.

### Stage 3 — Research
A deep investigation is multi-pass. The current V2 engine separately performs and records:

1. claim decomposition;
2. origin/earliest-source search;
3. primary-record search;
4. independent corroboration search;
5. explicit counterevidence/adversarial search;
6. chronology and context reconstruction;
7. final synthesis with an evidence floor.

Source-dependency/echo analysis is explicitly requested during origin/corroboration passes; a richer graph-level dependency detector remains follow-up work.

### Stage 4 — Dynamic workspace
The result is not a static article. The Deep endpoint streams newline-delimited receipt events. The browser only marks a stage complete after the backend emits its completed receipt. Source metrics and counterevidence counts are derived from returned research data.

The workspace exposes follow-on branch controls for: Challenge this finding; Dig deeper here; Primary evidence only; Argue the opposite; Follow the money; Investigate an entity. In the current slice these controls are visibly marked as queued for the next branch-engine layer rather than pretending additional research has already occurred.

## Probability

A Trust-Worthy probability is an experimental evidence-conditioned estimate, not a mathematical measurement of objective truth. The current engine blocks probability unless the evidence floor is met.

Current minimum evidence floor:
- at least 4 unique source URLs across research passes;
- at least 1 counterevidence source;
- at least 1 independent-corroboration source.

Possible terminal states include SUPPORTED, LIKELY, MIXED, UNLIKELY, CONTRADICTED, and **INSUFFICIENT EVIDENCE — NO VERDICT**. Missing evidence must never be treated as evidence of falsity.

## Evidence receipts

Every displayed progress stage must derive from a backend receipt. Current receipts record stage, label, completion state, source count, source list, pass summary, and UTC completion time. Final synthesis receipts additionally record the evidence-floor state, verdict, and probability when permitted.

## Quick versus Deep

Quick Check remains the existing preliminary synthesis path. Deep Investigation is now a separate engine and does not call `tw_short_investigation()`.

## Commercial model

Do not interrupt the initial investigation with arbitrary pricing tiers. Demonstrate value first. Paid continuation can be offered later when a completed public investigation identifies material unresolved work that requires substantially more research, specialist records, archival retrieval, or a documented deliverable.

## Homepage

V2 reduces `/truth` to four principal surfaces: entry/investigation box, guided-method explanation, published cases, and integrity rule. The dimensional evidence/progress interface lives inside the investigation workspace where it represents actual research.

## Security and release gates

- Deep mode cannot call the existing `tw_short_investigation()` path.
- No verdict when the minimum evidence floor is not met.
- No probability without the evidence floor.
- No completed stage without a backend receipt.
- No source count based only on UI state.
- Counterevidence search is mandatory for a scored verdict.
- Independent corroboration is mandatory for a scored verdict.
- Primary-source search is a dedicated mandatory pass.
- Source URLs remain inspectable.
- Same-origin request enforcement and public research rate limiting remain in the Deep endpoint.
- New PHP and JS syntax plus the V2 contract are added to the existing release gate.
- New public files are registered in the sorted fail-closed deployment manifest.

## Validation boundary

The source-level V2 implementation is complete enough for review, but it is intentionally **not release-approved yet**. No claim is made that the new provider path has passed a real production-style OpenAI request from the shared host. That must be demonstrated before merge/deploy.

## Still required before merge/deploy

1. Run the full repository release gate in a PHP/Node environment.
2. Run controlled provider tests against benign claims and inspect every streamed receipt.
3. Verify timeout/cost behavior for seven provider passes under shared-hosting limits.
4. Verify duplicate-source handling and strengthen independence/echo classification.
5. Add persistent investigation state before enabling follow-on branch actions.
6. Perform desktop/mobile browser inspection.
7. Keep the PR draft until these gates pass.
