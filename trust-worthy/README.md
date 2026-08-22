# Trust-Worthy AI

## v0.1 Goal

Build one public, auditable Truth Trial on bobsome1.com that demonstrates the full investigation loop without requiring a new application stack.

## First Demo Case

**TW-CASE-000001 — Did Jesus teach the modern concept commonly presented as a specific salvation prayer or formula?**

This deliberately tests a Project Unveiled hypothesis first.

## MVP Pages

- `/trust-worthy/` — landing page, mission, latest case, submit-a-question CTA.
- `/trust-worthy/case.php?id=TW-CASE-000001` — public case record.
- `/trust-worthy/challenge.php?id=TW-CASE-000001` — structured challenge form.
- `/trust-worthy/method/` — plain-language protocol and research standards.

## MVP Components

### 1. Case Renderer

PHP reads a structured case record and renders:
- claim and propositions;
- assumptions;
- evidence for / against;
- source cards with provenance;
- alternative hypotheses;
- logic audit;
- Christ-consistency analysis for theology mode;
- uncertainty register;
- current finding;
- strongest objection;
- what would change the finding;
- version history.

### 2. Challenge Intake

Structured form fields:
- case ID;
- challenge type;
- proposition or finding component challenged;
- challenger argument;
- source URL/citation;
- why the source materially changes the case;
- optional public display name/email handled privately.

Challenge types: source/authenticity, provenance, chronology, translation/language, context, logic, counterevidence, assumption, omitted alternative.

Public submission must not write directly into the published case. Store pending challenges privately for review/research.

### 3. Research Orchestrator

Initial implementation can be manual/semi-automated: a server-side script receives a case question and executes distinct research passes. Do not expose API keys client-side.

Logical agents/passes:
1. Claim Decomposer
2. Archivist / Source Hunter
3. Provenance Auditor
4. Historian / Context Analyst
5. Linguist when needed
6. Advocate A
7. Advocate B / Adversary
8. Logic Auditor
9. Christ-Consistency Analyst in theology mode
10. Synthesizer

The orchestrator produces a draft case record that must satisfy `truth-case.schema.json` before publication.

### 4. Versioning

Published records are immutable versions. A successful challenge creates v2, v3, etc. The page shows the current finding first while preserving all prior versions and revision reasons.

## Storage

For v0.1, prefer simple structured JSON records for public case data and private server-side storage for challenge submissions. If scale or concurrent editing becomes material, migrate case/challenge storage to SQLite or MySQL without changing the public schema.

Suggested private/public split:

```text
public_html/
  trust-worthy/
    index.php
    case.php
    challenge.php
    method/
    assets/
    cases-public/
      TW-CASE-000001-v1.json

/home/.../private/
  trust-worthy/
    config.php
    pending-challenges/
    research-drafts/
    logs/
```

Never commit the private directory or secrets.

## Contest Demo Flow

1. Judge sees a provocative but precise claim.
2. Trust-Worthy exposes the source trail and reasoning, not just an answer.
3. Judge clicks **Challenge This Finding**.
4. New evidence is submitted.
5. Trust-Worthy independently investigates the challenge.
6. If material, the finding changes and a new version is published.
7. The public history shows exactly why Trust-Worthy changed its mind.

The memorable demo moment is not the AI being right. It is the AI being **correctable in public**.

## Phase Sequence

### Phase 0 — Specification
- protocol;
- case schema;
- MVP architecture.

### Phase 1 — Static Truth Trial
- landing page;
- one hand-researched case rendered from JSON;
- challenge form;
- version-history UI.

### Phase 2 — Assisted Research
- server-side research orchestrator;
- source/provenance extraction;
- adversarial pass;
- structured draft generation.

### Phase 3 — Community
- accounts;
- contributor attribution;
- challenge reputation;
- theology battles / Truth Trials;
- Unveiled, Bunk Book, Mystery Book indexes.

### Phase 4 — Evidence Graph + API
- normalized source graph;
- claim relationships;
- independent-source lineage;
- machine-readable case API;
- revision/change feed for external AI systems.

## Success Criteria for v0.1

The MVP succeeds if a first-time visitor can answer all five questions without explanation:
1. What claim is being investigated?
2. What evidence supports it?
3. What evidence challenges it?
4. Why does Trust-Worthy currently lean one way?
5. How can I challenge or improve the finding?

If those are obvious, the core product is working.