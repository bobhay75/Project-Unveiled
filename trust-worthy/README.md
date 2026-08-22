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

The interface should visibly distinguish conversational analysis from completed research. Suggested states:

- `TALKING` — natural live conversation;
- `INVESTIGATING` — deeper source research is running;
- `SOURCES VERIFIED` — cited material has been checked for provenance/context;
- `ADVERSARIAL REVIEW` — the preliminary finding is being attacked;
- `FINDING READY` — a structured case record can be published.

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

## Case Renderer

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

## Challenge Intake

Structured form fields:
- case ID;
- challenge type;
- proposition or finding component challenged;
- challenger argument;
- source URL/citation;
- why the source materially changes the case;
- optional public display name/email handled privately.

Challenge types: source/authenticity, provenance, chronology, translation/language, context, logic, counterevidence, assumption, omitted alternative.

Public submission never writes directly into the published case. Pending challenges are stored outside the document root for later independent review.

### Deployment prerequisite

Before challenge intake is enabled in production, confirm PHP can create/write this private path relative to the hosting document root:

```text
../private/trust-worthy/pending-challenges/
```

If the directory cannot be created or written safely, the form fails closed with a 503-style message rather than storing challenge data in public web space.

## Voice Architecture — Phase 2 Priority

Phase 2 should prioritize a provider-agnostic live-voice layer instead of building the product around typed chat.

Minimum capabilities:

1. browser microphone capture with explicit consent;
2. streaming speech-to-text;
3. low-latency conversational response;
4. interruption / barge-in support;
5. text transcript kept visible and correctable;
6. source cards surfaced on screen while the conversation continues;
7. a clear `Deep Dive` action that moves from conversational analysis into the full research protocol;
8. ability to turn a completed deep-dive conversation into a versioned Truth Trial;
9. text fallback for accessibility and unsupported browsers;
10. no API secrets exposed client-side.

Because production is Namecheap shared hosting, the web application should remain PHP/static where practical. Real-time model connections should be abstracted behind server-side endpoints or a narrowly scoped external realtime service so the public site is not locked to one model vendor and can migrate later.

## Research Orchestrator — Phase 2

The research engine executes distinct passes without exposing API keys client-side:

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

The orchestrator should produce a draft case record that satisfies `truth-case.schema.json` before publication.

## Versioning

Published records are immutable versions. A successful challenge creates v2, v3, etc. The page shows the current finding first while preserving all prior versions and revision reasons.

## Storage

For v0.1, public cases are structured JSON records. Challenge submissions stay private and outside Git/public_html. If scale or concurrent editing becomes material, migrate case/challenge storage to SQLite or MySQL without changing the public schema.

Live voice transcripts must be treated as private by default. A conversation becomes public only through an explicit publish/Truth-Trial action. Sensitive raw audio should not be retained by default unless a later feature has a clear reason and informed user consent.

## Contest Demo Flow

1. Judge speaks a provocative but precise question to Trust-Worthy.
2. Trust-Worthy converses naturally and identifies the claim or hidden assumption.
3. Judge says **Go deeper** / activates Deep Dive.
4. Trust-Worthy exposes the source trail and reasoning while continuing the conversation.
5. Judge interrupts with counterevidence or a challenge.
6. Trust-Worthy independently investigates the challenge instead of defending itself reflexively.
7. If material, the finding changes.
8. The conversation becomes a public Truth Trial with a permanent version history.

The memorable demo moment is not the AI sounding smart. It is a live AI investigator being **challenged, researching the objection, and correcting itself in public**.

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
- convert a conversation into a Truth Trial.

### Phase 3 — Community
- accounts;
- contributor attribution;
- challenge reputation;
- live theology battles / Truth Trials;
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

The voice-first prototype adds five more tests:
1. Can a user begin without typing?
2. Can the user interrupt Trust-Worthy naturally?
3. Can Trust-Worthy clearly signal when it is doing deeper research rather than giving a provisional response?
4. Can the user inspect sources without ending the conversation?
5. Can the resulting investigation become a permanent, auditable Truth Trial?

If those are obvious, the core product is working.
