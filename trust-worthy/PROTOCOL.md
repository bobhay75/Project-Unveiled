# Trust-Worthy Verification Protocol v0.1

## Purpose

Trust-Worthy is an investigative AI system for contested historical, theological, textual, scientific, cultural, and public claims. It does not certify what a user must believe. It documents what the available evidence most strongly supports, why, what challenges that conclusion, and what remains unresolved.

The system is designed so that no doctrine, institution, founder, tradition, or prior Trust-Worthy finding is protected from evidence.

## Core Principles

1. **No protected conclusions.** Project Unveiled claims receive the same scrutiny as any other claim.
2. **Primary evidence first.** Prefer original documents, manuscripts, inscriptions, archival records, critical editions, direct data, and contemporary records where available.
3. **No fact-checker dependency.** Third-party fact-check verdicts are not accepted as authority. If referenced at all, they are treated as claims whose sources can be independently examined.
4. **Trace provenance.** Repetition is not corroboration. Follow citations backward to determine whether apparent sources are independent.
5. **Separate evidence from inference.** Every case must distinguish documented evidence, inference, interpretation, speculation, and unresolved uncertainty.
6. **Attack the preliminary conclusion.** A dedicated adversarial pass must search for evidence capable of overturning the working hypothesis.
7. **Steelman competing explanations.** Present the strongest credible case for material alternatives, not straw men.
8. **Show uncertainty.** When evidence cannot resolve a question, say so explicitly.
9. **Version every finding.** Findings are provisional records with a permanent public history.
10. **Let the seeker decide.** Trust-Worthy may state what appears most strongly supported; it must not tell a user what they are required to believe.

## Investigation Pipeline

### 1. Claim Definition

Convert the submitted question into one or more precise propositions. Record ambiguities, loaded terms, hidden premises, and definitions that materially affect the inquiry.

### 2. Assumption Audit

Identify assumptions embedded in the question. Where appropriate, test whether the premise itself is supported before investigating downstream conclusions.

### 3. Source Hunt

Search backward toward the earliest, strongest, and most direct surviving evidence. For historical and theological cases, prioritize primary texts, manuscript evidence, critical editions, contemporary records, archaeology, inscriptions, and original-language sources.

### 4. Provenance

For every material source, record:
- author or creator when known;
- approximate date;
- relationship to the event or claim;
- transmission history where relevant;
- whether the source is primary, secondary, tertiary, or derivative;
- known disputes over authenticity, dating, interpolation, or attribution.

### 5. Source Lineage

Trace derivative claims back to their earliest identifiable source. Multiple articles repeating one underlying assertion count as one lineage, not independent corroboration.

### 6. Context

Establish relevant literary, historical, social, political, religious, scientific, and economic context. Avoid proof-texting and decontextualized quotations.

### 7. Language Analysis

When wording materially affects the case, examine the relevant original language, semantic range, grammar, comparable usage, translation history, and major scholarly disagreements.

### 8. Independent Corroboration

Determine which sources are genuinely independent. Record convergent and conflicting evidence separately.

### 9. Adversarial Pass

A separate research pass receives the instruction: **Find the strongest credible evidence that the preliminary conclusion is wrong.**

This pass must not merely repeat the initial search. It should seek contrary sources, alternative dating, translation disputes, methodological criticism, and competing causal explanations.

### 10. Alternative Hypotheses

Construct the strongest material competing explanations and state what evidence each explains well or poorly.

### 11. Logic Audit

Test whether conclusions actually follow from premises. Flag:
- circular reasoning;
- false dichotomies;
- correlation/causation errors;
- argument from silence;
- anachronism;
- equivocation;
- cherry-picking;
- source laundering;
- unsupported certainty.

### 12. Christ-Consistency Analysis — Theology Mode Only

When the case is theological, perform a clearly labeled secondary analysis asking how competing interpretations align with the recorded teachings, conduct, and character attributed to Jesus in the earliest relevant sources.

This is a theological interpretive layer, not a substitute for historical evidence and not a claim that the AI possesses divine authority.

### 13. Uncertainty Register

List facts that cannot currently be established, material evidence that is missing, unresolved textual or historical disputes, and what new evidence would materially change the assessment.

### 14. Finding

A finding must use calibrated language such as:
- strongly supported;
- better supported than alternatives;
- plausible;
- disputed;
- weakly supported;
- unsupported by the evidence reviewed;
- unresolved / insufficient evidence.

Avoid false numerical precision unless the number comes from an explicit statistical model whose assumptions are published.

### 15. Challenge

Every public finding ends with **Challenge This Finding**. A challenge must target at least one specific component:
- source/authenticity;
- provenance;
- chronology;
- translation/language;
- context;
- logic;
- counterevidence;
- assumption;
- omitted alternative hypothesis.

Successful challenges trigger a new investigation version rather than silently editing the existing record.

## Public Case Record

Every Truth Trial receives a permanent identifier such as `TW-CASE-000001` and contains:
- claim;
- decomposed propositions;
- assumptions;
- sources and source lineage;
- evidence for;
- evidence against;
- alternatives;
- logic audit;
- Christ-consistency analysis when applicable;
- uncertainty register;
- current finding;
- challenge history;
- version history;
- revision rationale.

## Finding Destinations

Trust-Worthy does not force every case into true/false.

- **Unveiled** — evidence materially supports a finding or exposes a previously obscured relationship.
- **Bunk Book** — a claim has materially failed under evidence because of provenance failure, anachronism, misquotation, context failure, logical contradiction, source laundering, or comparable defects.
- **Mystery Book** — available evidence cannot presently resolve the claim responsibly.

## Version Control

Every substantive revision creates a new immutable finding version containing:
- prior version ID;
- new evidence or challenge;
- what changed;
- why it changed;
- who submitted the successful challenge, when attribution is allowed;
- whether the overall assessment changed.

Correction is a feature, not a failure.

## MVP Truth Trial

The first demonstrator should put a Project Unveiled hypothesis on trial rather than attack an external group first.

Suggested Case #0001:

**Did Jesus teach the modern concept commonly presented as a specific salvation prayer or formula?**

Minimum user flow:

`Submit/Select Claim -> Investigate -> Evidence -> Counterevidence -> Finding -> Sources -> Challenge -> Revision History`

## Shared-Hosting Architecture Constraint

The first public implementation must remain compatible with the existing Namecheap shared-hosting environment: Apache, static HTML/CSS/JavaScript, and PHP. It must not require Node.js, Docker, or a persistent application server in production.

Research orchestration may initially call external model/search APIs from server-side PHP, while public case records can be stored as structured JSON files or a server-side database outside the public web root. Secrets and private runtime data must never be committed to Git.

## Long-Term API Goal

The public website is the demonstration layer. The long-term infrastructure product is a machine-readable evidence and provenance service that other AI systems can query.

Potential API operations:
- claim lookup;
- case retrieval;
- evidence graph retrieval;
- source provenance lookup;
- submit challenge;
- run investigation;
- subscribe to case revisions.

The strategic objective is not to become an unquestionable authority. It is to become a trusted, auditable public record of how claims survive scrutiny.