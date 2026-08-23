# Trust-Worthy AI Architecture

## Product decision

Trust-Worthy should become its own evidence system, but it should not attempt to train a frontier model in its first releases. Its defensible value is the investigation protocol, provenance records, source-lineage graph, adversarial workflow, version history, challenge corpus, and evaluation harness.

External language models may serve as replaceable research and reasoning workers. No model answer becomes evidence merely because a model generated it, and no provider becomes the authority.

## Phase 1 — Public evidence ledger

The production surface remains compatible with Namecheap shared hosting:

- Apache;
- PHP;
- static HTML/CSS/JavaScript;
- JSON case records;
- private storage outside the document root;
- no client-side API secrets;
- no required Node.js, Docker, or persistent application server.

The canonical product route is `/truth/`. Existing public case URLs remain stable while thin PHP wrappers use one JSON-backed renderer.

## Core layers

1. **Question and claim normalizer** — preserves the original wording and produces one primary `P` / `not-P` pair.
2. **Research planner** — creates fresh research tracks for each case or material challenge.
3. **Source acquisition** — locates primary evidence and serious contextual scholarship.
4. **Provenance engine** — records attribution, dating, transmission, disputes, custody, purpose, and limitations.
5. **Lineage engine** — detects derivative claims and prevents dependent sources from masquerading as independent corroboration.
6. **Context and language analysis** — restores literary, historical, social, and linguistic context when material.
7. **Evidence map** — separates support for P, support for not-P, contextual evidence, and counterevidence.
8. **Adversarial reviewer** — independently tries to defeat the leading assessment.
9. **Logic auditor** — tests assumptions, contradictions, causation, anachronism, source laundering, and explanatory fit.
10. **Christ-consistency analyst** — optional theology-only interpretive layer, never a substitute for evidence.
11. **Synthesizer** — produces a scoped epistemic assessment with uncertainty and change conditions.
12. **Public ledger** — publishes canonical IDs, evidence records, challenges, and immutable revisions.

## Model independence

Every model or search call should sit behind a server-side adapter. Preserve privately where permitted:

- provider and model version;
- original question;
- normalized proposition;
- research plan;
- source set;
- provenance and lineage output;
- adversarial findings;
- synthesis rationale;
- timestamps and case version.

The same case should eventually be runnable through multiple models to expose disagreement. Model consensus is diagnostic, not evidence.

## Voice and assisted research — Phase 2

Voice is a future interface over the evidence system, not a substitute for it. A responsible prototype needs:

1. explicit microphone consent;
2. streaming transcription;
3. visible transcript correction;
4. interruption and barge-in;
5. clear separation between provisional conversation and researched findings;
6. source cards during the conversation;
7. an explicit Deep Dive transition;
8. server-side orchestration;
9. private transcripts by default;
10. explicit publication into a versioned Truth Trial.

Suggested visible states are `TALKING`, `INVESTIGATING`, `SOURCES CHECKED`, `ADVERSARIAL REVIEW`, and `FINDING READY`.

## Community products — Phase 3

Accounts, Theology Profiles, member challenges, consensual live Battles, personal books, achievements, and physical editions should be separate releases. They require identity, consent, moderation, abuse controls, privacy rules, and durable storage.

No Truth Score, holiness score, or popularity ranking should be created. Reputation may recognize observable contributions such as primary-source discoveries, successful corrections, improved provenance, and willingness to revise one's own position.

Payment may fund research compute, publishing, archival services, or physical production. It may never purchase a finding, successful challenge, case destination, or evidentiary score.

## Evidence graph and external API — Phase 4

Later infrastructure may expose:

- `claim_lookup`
- `case_get`
- `evidence_search`
- `source_lineage`
- `challenge_submit`
- `case_changes`

Responses should return the case ID/version, primary proposition, evidence in both directions, provenance relationships, unresolved disputes, alternatives, and source trail—not merely a verdict.

## Path toward greater ownership

1. **Orchestrated system:** own the protocol, case schema, storage, UI, evaluation, and public records while using replaceable external models.
2. **Owned retrieval and evidence graph:** index permitted primary sources and structured provenance metadata.
3. **Specialized local models:** use smaller open models for claim decomposition, source classification, lineage detection, contradiction analysis, and validation.
4. **Trust-Worthy reasoning model:** train or fine-tune on audited cases, successful challenges, revisions, and adversarial examples when the corpus is large enough.
5. **Independent verification infrastructure:** publish stable APIs and benchmarks that other systems can audit or use.

## Success measures

Trust-Worthy succeeds when it accurately preserves the proposition, finds direct evidence, detects dependent sourcing, presents serious counterevidence, distinguishes fact from interpretation, says “insufficient” when warranted, changes when better evidence wins, and leaves a reproducible trail another investigator can inspect.

The long-term moat is not the largest model. It is the most rigorous, auditable, challenge-tested evidence record.
