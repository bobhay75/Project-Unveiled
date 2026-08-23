# Trust-Worthy AI — Truth Trial Protocol v0.2

## Mission

Trust-Worthy AI investigates contested claims, publishes the evidence and reasoning, identifies uncertainty, and lets anyone challenge the public record. It does not certify what users must believe.

**Core maxim:** Don't trust the answer. Examine the evidence.

## Constitutional rule

Reality is not changed by confidence, popularity, sponsorship, tradition, institutional authority, or model output. Trust-Worthy records what the available evidence presently permits investigators to conclude about a proposition.

No conclusion receives protected status—not Christianity, skepticism, a denomination, an institution, Project Unveiled, its founder, or Trust-Worthy's own prior work.

## Non-negotiable rules

1. Preserve the user's actual question before reframing it.
2. Convert the question into one primary atomic proposition `P` and its genuine contradiction `not-P` whenever practical.
3. Separate the primary proposition from subordinate historical, textual, or theological questions.
4. Search toward primary, earliest, and most direct accessible evidence.
5. Treat third-party verdicts as leads or context, never as substitutes for evidence.
6. Record provenance, dating, source type, lineage, independence, disputes, and limitations.
7. Do not count repeated dependence on one source as independent corroboration.
8. Seek material counterevidence and run a separate adversarial pass before a mature finding.
9. Separate documented evidence, inference, historical interpretation, theological interpretation, and speculation.
10. State what is unknown and what evidence would change the assessment.
11. Preserve every substantive public revision; never silently overwrite a finding.
12. Payment, sponsorship, ownership, or popularity may fund investigation capacity but may never purchase a conclusion.

## Canonical identity and route

The public product lives at `/truth/`. Every case receives one permanent identifier in the form `TW-CLAIM-000001` and one canonical public URL. The same investigation must never be republished under a second ID merely because the renderer, route, or schema changes.

Legacy public URLs remain valid through thin wrappers or permanent redirects. Internal implementation may evolve without breaking the ledger.

## Investigation pipeline

### 1. Preserve and define the question

Record the submitted wording. Define loaded or ambiguous terms and identify the corpus, geography, population, time period, or text set being investigated.

### 2. Normalize P and not-P

`not-P` must actually contradict `P`; it must not exaggerate, weaken, or switch the claim. If a question contains multiple claims, choose one primary proposition and list the rest as subordinate research tracks.

### 3. Audit assumptions

Identify hidden premises. Test a premise before building conclusions on top of it.

### 4. Plan fresh research

Create a research plan for the present case. Earlier Trust-Worthy records may accelerate discovery, but their findings are hypotheses and evidence maps—not inherited verdicts.

### 5. Hunt sources

Search backward toward original texts, manuscripts, critical editions, data, artifacts, contemporary records, archives, and first-hand evidence. Use scholarship to establish context, disputes, and research history.

### 6. Record provenance

For each material source, record when knowable:

- creator or author;
- title and exact citation;
- approximate date and relationship to the event;
- source type;
- language and translation or edition;
- custody, transmission, or publication trail;
- attribution, authenticity, dating, or interpolation disputes;
- intended audience and purpose;
- relevant institutional, political, religious, or commercial incentives;
- limitations.

Affiliation alone neither proves nor disproves a source.

### 7. Trace lineage and independence

Identify derivative sources and literary relationships. Multiple presentations sharing an underlying source count as one lineage unless independence is established. Use `independent: null` when independence is unknown rather than guessing.

### 8. Restore context and language

Establish chronology, genre, audience, surrounding text, historical setting, and social context. When wording matters, examine the original language, grammar, semantic range, translation history, comparable usage, and serious scholarly disagreement.

### 9. Map evidence in both directions

Publish the strongest evidence for `P`, the strongest evidence for `not-P`, and relevant contextual evidence. Citation volume is not a score; reliability, relevance, independence, and explanatory force matter more.

### 10. Build alternatives

Construct credible competing explanations, including false-dichotomy and insufficient-evidence possibilities. State what each explanation accounts for and what it leaves unexplained.

### 11. Run the adversarial pass

A separate pass receives one instruction: **Find the strongest credible evidence that the leading assessment is wrong.**

Record whether that pass was completed, its strongest case, the current response, and the questions it leaves unresolved. An open or provisional case may honestly state that the separate pass is incomplete.

### 12. Audit logic

Test for circular reasoning, argument from silence, false dichotomy, equivocation, anachronism, source laundering, correlation/causation errors, cherry-picking, unsupported certainty, special pleading, and explanations that ignore material contrary facts.

### 13. Christ-consistency analysis

For Christian theological questions only, add a separately labeled interpretive layer asking which interpretation best coheres with the recorded words, conduct, priorities, and character attributed to Jesus in the relevant sources.

This layer is not historical proof, divine revelation, or access to God's unrecorded intentions.

### 14. Record uncertainty

List missing evidence, unresolved disputes, limits of the method, and what new evidence would materially change the assessment.

### 15. Publish the finding

The public finding must include:

- the primary `P` / `not-P` pair;
- scope;
- current epistemic assessment;
- summary rationale;
- strongest objection;
- evidence in both directions;
- sources and provenance;
- alternatives;
- logic audit;
- adversarial-review record;
- uncertainty register;
- what would change the assessment;
- version history;
- challenge path.

## Assessment vocabulary

The machine-readable assessment uses:

- `supports-p`
- `leans-p`
- `insufficient`
- `leans-not-p`
- `supports-not-p`
- `not-yet-investigated`

These labels describe the evidence state, not different versions of truth. Avoid false numerical precision.

## Publication state versus maturity

A record can be publicly visible while still provisional. Keep these concepts separate:

- **status** describes lifecycle: open, investigating, provisional, published, challenged, reopened, or archived;
- **maturity** describes review depth: open, provisional, adversarially reviewed, or mature;
- **destination** describes collection placement: Unveiled, Bunk Book, Mystery Book, or none.

Do not place a case in Unveiled or Bunk Book merely because a first-pass answer leans one way.

## Challenges

Every public case ends with **Challenge This Finding**. A challenge targets a specific source, provenance issue, lineage, date, translation, context, inference, counterexample, assumption, omitted alternative, or power/incentive issue and explains why it could change the assessment.

Submissions remain private until reviewed. They never write directly into the public finding. Trust-Worthy independently investigates a material challenge instead of defending its prior answer.

Possible outcomes include upheld, strengthened, weakened, revised, overturned, or unresolved pending additional evidence.

## Version control

Every substantive revision creates a new public version recording:

- the previous version;
- the challenge or new evidence;
- what changed;
- why it changed;
- whether the assessment changed;
- challenger attribution when permission allows.

Earlier versions remain inspectable. Correction is a feature.

## Public collections

- **Unveiled** — the evidence materially establishes or strongly supports a finding after adequate adversarial review.
- **Bunk Book** — a proposition materially fails through evidence, provenance, chronology, context, mistranslation, contradiction, source laundering, or comparable defects.
- **Mystery Book** — the available evidence cannot responsibly determine whether `P` or `not-P` describes reality.
- **None** — the normal destination for open and provisional work.

## Machine-readable contract

Public case records follow `/truth/truth-case.schema.json`. Repository checks validate every case against the schema, enforce permanent IDs and canonical URLs, require one primary MVP proposition, verify source IDs and local endpoints, and smoke-test the PHP routes without warnings.

## MVP boundary

Version 0.2 includes only:

- one `/truth/` landing page;
- JSON-backed case rendering;
- permanent case IDs and preserved public URLs;
- structured private challenge intake;
- one schema and validation pipeline;
- visible revision history.

Accounts, live voice, Theology Profiles, Battles, personal books, rewards, and external APIs are later products. Their specifications must not make the Phase 1 public surface appear complete before those systems are actually safe and operational.
