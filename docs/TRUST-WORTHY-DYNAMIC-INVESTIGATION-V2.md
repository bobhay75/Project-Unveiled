# Trust-Worthy Dynamic Investigation V2

## Product correction

Trust-Worthy must not present a short web-assisted synthesis as a deep investigation. The investigation experience and the engine must use the same receipts.

## Entry experience

The public entry screen asks one primary question: **What do you want to know is true?** A visitor can provide a claim, question, URL, post text, or supporting context. Pricing is not a prerequisite to discovering value.

## Guided investigation

### Stage 1 — Understand
The engine decomposes the submission into independently testable assertions and shows them to the user. The user can add, remove, or refine assertions before research proceeds.

### Stage 2 — Direct
The user can select one or more goals: verify the event, test misleading framing, trace origin, find primary evidence, find counterevidence, or investigate everything.

### Stage 3 — Research
A deep investigation is multi-pass. It must separately perform and record:

1. claim decomposition;
2. origin/earliest-source search;
3. primary-record search;
4. independent corroboration search;
5. explicit counterevidence/adversarial search;
6. chronology and context reconstruction;
7. source-dependency/echo detection;
8. synthesis and unresolved-evidence analysis.

A stage may display complete only when a backend receipt exists for it.

### Stage 4 — Dynamic workspace
The result is not a static article. Evidence creates the interface dynamically. Supported objects include claim nodes, source nodes, people/entities, timeline events, supporting relationships, contradictory relationships, source-copy/echo relationships, and unresolved evidence gaps.

Users can branch from any supported object with actions such as: Dig deeper here; Challenge this finding; Find the original source; Show primary evidence; Find missing evidence; Argue the opposite conclusion; Follow the money; Build the timeline; Compare framing; Investigate this person; Investigate this organization.

## Probability

A Trust-Worthy probability is an experimental evidence-conditioned estimate, not a mathematical measurement of objective truth. The score must not be emitted when the evidence floor is unmet. Score changes must be attributable to evidence events and expose a human-readable explanation.

Possible terminal states include SUPPORTED, LIKELY, MIXED, UNLIKELY, CONTRADICTED, and **INSUFFICIENT EVIDENCE — NO VERDICT**. Missing evidence must never be treated as evidence of falsity.

## Evidence receipts

Every displayed metric must derive from stored investigation state: candidate sources, independently corroborating sources, primary records, counterevidence items, duplicate/echo sources, unresolved material claims, completed passes, and timestamps. The UI must never fabricate progress or source counts.

## Quick versus Deep

Quick Check is a clearly labeled preliminary synthesis optimized for speed and low cost. Deep Investigation is a separate multi-pass engine. A single low-reasoning request with one web-search call does not qualify as Deep Investigation.

## Commercial model

Do not interrupt the initial investigation with arbitrary pricing tiers. Demonstrate value first. Paid continuation can be offered when the completed public investigation identifies material unresolved work that requires substantially more research, specialist records, archival retrieval, or a documented deliverable.

## Homepage

Reduce `/truth` to four principal surfaces: entry/investigation box, recent investigations, methodology/integrity explanation, and a final call to action. Move the dimensional evidence graph, timeline, provenance network, probability history, and investigation controls into the investigation workspace where they represent actual evidence.

## Release gates

- Deep mode cannot call the existing `tw_short_investigation()` path and label the result deep.
- No verdict when the minimum evidence floor is not met.
- No probability without evidence receipts.
- No completed stage without a backend receipt.
- No source count based only on UI state.
- Counterevidence search is mandatory for a verdict.
- Primary-source search is mandatory when the claim is reasonably capable of primary verification.
- Source URLs and provenance must remain inspectable.
- Existing fail-closed intake, origin, privacy, rate-limit, and deployment protections remain intact.
