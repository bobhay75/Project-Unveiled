# Trust-Worthy AI

## v0.1 Goal

Build one public, auditable Truth Trial on bobsome1.com that demonstrates the full investigation loop without requiring a new application stack.

## Primary Interface Decision — Live Voice First

Trust-Worthy is primarily a **live conversational investigator**, not a text chatbot. Users should be able to speak naturally, interrupt, challenge, ask for sources, and move deeper without typing. Text remains essential for accessibility, transcripts, citations, source review, challenge records, and the permanent public Truth Trial.

The product loop is:

```text
LIVE VOICE CONVERSATION
        ↓
question / claim detected
        ↓
provisional conversational response
        ↓
DEEP RESEARCH MODE when warranted
        ↓
source trail + provenance + competing explanations
        ↓
spoken challenge / interruption
        ↓
re-investigation
        ↓
structured Truth Trial record
        ↓
public finding + revision history
```

The interface should visibly distinguish conversational analysis from completed research. Suggested states: `TALKING`, `INVESTIGATING`, `SOURCES VERIFIED`, `ADVERSARIAL REVIEW`, and `FINDING READY`.

Trust-Worthy must never imply that an instant voice response is equivalent to exhaustive historical research.

## First Demo Case

**TW-CASE-000001 — Did Jesus teach the modern concept commonly presented as a specific salvation prayer or formula?**

This deliberately tests a Project Unveiled hypothesis first.

## Phase 1 Implemented

- `/trust-worthy/` — landing page and latest case CTA.
- `/trust-worthy/case.php?id=TW-CASE-000001` — JSON-backed public case renderer.
- `/trust-worthy/challenge.php?id=TW-CASE-000001` — structured challenge form with validation, honeypot, private-server storage, and no direct publication path.
- `/trust-worthy/method/` — plain-language public methodology.
- `/trust-worthy/cases-public/TW-CASE-000001-v1.json` — first versioned case record.
- `/trust-worthy/assets/trust-worthy.css` — responsive interface styling aligned with Project Unveiled.

## Public Theology Profiles — Core Social Primitive

Every member may maintain a public, readable **Theology Profile**. The profile is a living map of the member's current positions, evidence, uncertainty, challenges, and revisions rather than a denominational label or popularity score.

Suggested belief topics include God, Jesus, Kingdom of God, salvation, Scripture, church, Trinity, judgment/hell, resurrection, prophecy, spiritual gifts, and other member-created propositions.

Each position records:
- proposition / belief statement;
- stance: `believe`, `lean-toward`, `questioning`, `unresolved`, `not-investigated`;
- member explanation;
- evidence and citations offered by the member;
- confidence language without false numerical precision;
- open and resolved challenges;
- revision history and reason for revision;
- linked Truth Trials.

Profiles should explicitly welcome examination: confidence in a belief is compatible with allowing others to inspect and challenge the reasons for it.

### Member-to-member challenges

Any member may submit a structured evidence challenge against a substantive public belief held by any other member. Challenges target **claims, evidence, interpretation, translation, chronology, context, assumptions, or logic — never the person's worth, motives, spirituality, or identity**.

A challenge may exist asynchronously without forcing the profile owner into a live confrontation. A live Theology Battle requires acceptance by the invited participant.

Trust-Worthy acts as investigator/moderator rather than partisan judge. It independently checks submitted evidence, actively searches for contrary evidence, and may conclude that either position is better supported, both are partly supported, the framing is a false dichotomy, or the evidence is unresolved.

### Revision Ledger

Belief changes are preserved rather than erased. A Theology Profile shows prior position, revised position, date, linked investigation, and the evidence/reason that caused the revision. Changing one's mind after stronger evidence is treated as intellectual integrity, not defeat.

Do **not** create a member `Truth Score`, holiness score, salvation score, or similar ranking. Reputation should be based on observable contributions such as investigations participated in, primary sources contributed, successful evidence challenges, corrections accepted, and findings materially improved.

## Case Renderer

PHP reads a structured case record and renders claim and propositions, assumptions, evidence for/against, source cards with provenance, alternative hypotheses, logic audit, Christ-consistency analysis for theology mode, uncertainty register, current finding, strongest objection, what would change the finding, and version history.

## Challenge Intake

Challenge types: source/authenticity, provenance, chronology, translation/language, context, logic, counterevidence, assumption, omitted alternative.

Public submission never writes directly into the published case. Pending challenges are stored outside the document root for later independent review.

### Deployment prerequisite

Before challenge intake is enabled in production, confirm PHP can create/write:

```text
../private/trust-worthy/pending-challenges/
```

If that path cannot be written safely, the form fails closed rather than storing challenge data in public web space.

## Voice Architecture — Phase 2 Priority

Minimum capabilities:
1. browser microphone capture with explicit consent;
2. streaming speech-to-text;
3. low-latency conversational response;
4. interruption / barge-in support;
5. visible, correctable transcript;
6. source cards while conversation continues;
7. `Deep Dive` transition into full research;
8. convert a completed investigation into a versioned Truth Trial;
9. text fallback;
10. no client-side API secrets.

Production remains PHP/static where practical. Realtime model connections should be abstracted behind server-side endpoints or a narrowly scoped external realtime service so the site is not locked to one model vendor.

## Research Orchestrator — Phase 2

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

The orchestrator produces a draft case record satisfying `truth-case.schema.json` before publication.

## Versioning

Published records are immutable versions. A successful challenge creates v2, v3, etc. The current finding appears first while all prior versions and revision reasons remain available.

## Storage and Privacy

Public cases use structured JSON initially. Challenge submissions remain private and outside Git/public_html. Live voice transcripts are private by default. A conversation becomes public only through an explicit publish/Truth-Trial action. Raw audio should not be retained by default without a clear feature need and informed consent.

## Contest Demo Flow

1. Judge speaks a question to Trust-Worthy.
2. Trust-Worthy identifies the claim or hidden assumption.
3. Judge activates Deep Dive.
4. Trust-Worthy exposes sources and reasoning while continuing the conversation.
5. Judge interrupts with counterevidence.
6. Trust-Worthy independently investigates instead of reflexively defending itself.
7. If material, the finding changes.
8. The conversation becomes a public Truth Trial with permanent version history.
9. A member can attach the resulting finding to a Theology Profile and another member can challenge the specific belief/evidence.

The memorable demo moment is a live AI investigator being **challenged, researching the objection, and correcting itself in public**.

## Phase Sequence

### Phase 0 — Specification — COMPLETE
- protocol;
- case schema;
- MVP architecture.

### Phase 1 — Static Truth Trial — BUILT ON BRANCH
- landing page;
- first case rendered from JSON;
- challenge form;
- version-history UI;
- public method page.

### Phase 2 — Live Voice + Assisted Research
- live browser voice interface;
- streaming transcript and interruption support;
- conversational vs deep-research state separation;
- server-side research orchestrator;
- source/provenance extraction;
- adversarial pass;
- structured draft generation;
- convert conversation into Truth Trial.

### Phase 3 — Community + Theology Profiles
- accounts;
- public Theology Profiles;
- belief position and evidence records;
- member-to-member structured challenges;
- Revision Ledger;
- contributor attribution and evidence-based reputation;
- consensual live Theology Battles / Truth Trials;
- Unveiled, Bunk Book, Mystery Book indexes.

### Phase 4 — Evidence Graph + API
- normalized source graph;
- claim relationships;
- independent-source lineage;
- machine-readable case API;
- revision/change feed for external AI systems.

## Success Criteria

A first-time visitor should immediately understand what claim is being investigated, what supports it, what challenges it, why Trust-Worthy currently leans one way, and how to challenge or improve the finding.

The voice prototype must allow a user to begin without typing, interrupt naturally, distinguish provisional conversation from deep research, inspect sources without ending the conversation, and publish an investigation as an auditable Truth Trial.

The community layer succeeds when a member can state a belief publicly, show why they hold it, receive a structured evidence challenge, revise the belief if warranted, and preserve the entire intellectual journey without personal attacks or popularity deciding the result.
