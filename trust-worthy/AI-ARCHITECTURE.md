# Trust-Worthy AI Architecture

## Decision

Trust-Worthy should become its own AI system, but v0.x should not attempt to train a frontier foundation model from scratch. The product moat is the investigation protocol, evidence graph, provenance engine, adversarial research workflow, theology profiles, battle corpus, revision history, and source-lineage data.

Initial releases may use one or more external language models as interchangeable reasoning workers. Trust-Worthy owns the orchestration, prompts/policies, retrieval, evidence storage, scoring, validation, public records, and evaluation harness. No single model vendor is the authority.

## Layers

1. **Voice Interface** — speech in/out, interruption, transcript correction.
2. **Claim Normalizer** — converts speech into atomic P / not-P propositions without silently changing meaning.
3. **Research Planner** — creates fresh search plans for each case, challenge, and battle turn.
4. **Source Acquisition** — locates primary/early evidence, original texts, archives, archaeology/data, and useful secondary scholarship for context.
5. **Provenance Engine** — authorship, dating, transmission, lineage, independence, authenticity disputes.
6. **Power & Incentive Audit** — patronage, funding, institutional/imperial ties, censorship, intended audience, incentives; never assumes affiliation alone proves truth or falsity.
7. **Language Engine** — original-language terms, grammar, semantic range, translation comparison when material.
8. **Evidence Graph** — normalized claims, sources, relationships, contradictions, lineage, challenges, revisions.
9. **Adversarial Engine** — independently tries to defeat the current leading explanation.
10. **Logic / Explanatory Coherence Engine** — tests contradictions, assumptions, causal logic, parsimony, and total-fact fit.
11. **Christ-Consistency Engine** — theology-only secondary analysis against the recorded teaching/conduct of Jesus; never presented as divine revelation.
12. **Synthesizer** — produces the epistemic assessment and cites the evidence trail.
13. **Public Ledger** — immutable Truth Trial versions, Theology Profiles, Battle turns, corrections, Bunk/Mystery/Unveiled destinations.
14. **External Verification API** — eventually lets other AI systems query cases, provenance, evidence graphs, and revision feeds.

## Model Independence

External LLMs are workers, not Trust-Worthy itself. Every model call must be replaceable behind an adapter. The same case should be capable of being run through multiple models for disagreement testing.

Trust-Worthy should preserve:
- the original question;
- the normalized propositions;
- research plan;
- source set;
- provenance data;
- adversarial findings;
- logic findings;
- final rationale;
- model/provider/version metadata privately for reproducibility where permitted.

No model answer becomes evidence merely because the model generated it.

## Path Toward More Ownership

### Stage 1 — Orchestrated system
Use external models/search services where needed; own protocol, storage, UI, evaluation, and evidence records.

### Stage 2 — Owned retrieval and evidence graph
Build/crawl/licence an index of public-domain and permitted primary sources, Scripture/text corpora, historical records, and structured provenance metadata. Reduce dependence on external search.

### Stage 3 — Specialized local models
Fine-tune or train smaller open models for claim decomposition, source classification, lineage detection, contradiction analysis, translation assistance, and scoring. These can run on owned infrastructure when economical.

### Stage 4 — Trust-Worthy reasoning model
Train/fine-tune a dedicated reasoning model using the accumulated audited case corpus, successful challenges, revisions, adversarial examples, and battle turns. The objective is methodological fidelity, not reproducing founder conclusions.

### Stage 5 — Independent verification infrastructure
Expose stable APIs and benchmark suites so outside AI systems can use Trust-Worthy's evidence/provenance layer even if they use different base models.

## Non-Negotiable Research Rule

Every substantive case and battle turn requires fresh independent research. Prior cases may accelerate discovery, but their findings are hypotheses/research assets, not inherited verdicts. When a prior case is reused, Trust-Worthy must re-check the material sources relevant to the new context and actively look for changed or omitted evidence.

## Evaluation

Trust-Worthy should be tested on whether it:
- preserves the user's proposition accurately;
- finds earliest/primary evidence when available;
- detects common-source repetition;
- identifies material conflicts of interest without assuming guilt by association;
- finds strong counterevidence;
- distinguishes fact from inference;
- changes its assessment when better evidence warrants it;
- says `insufficient` when the record cannot decide;
- produces reproducible source trails;
- treats founder-preferred claims no differently from hostile claims.

The long-term competitive advantage is not owning the largest language model. It is owning the most rigorous, auditable, adversarially tested evidence system and the corpus created by people continuously challenging it.