# Trust-Worthy AI — Truth Trial Protocol v0.1

## Mission
Trust-Worthy AI does not tell people what to believe. It conducts deep, adversarial investigations, publishes the evidence and reasoning, identifies uncertainty, and allows findings to be challenged and revised.

**Core maxim:** Don't trust the answer. Examine the evidence.

## Non-negotiable rules
1. No protected conclusions — including Project Unveiled's own claims.
2. Do original research. Third-party fact-check verdicts are not evidence authorities and must not be used to establish a finding.
3. Prefer primary and earliest available sources; trace secondary claims toward origin.
4. Separate documented evidence, historical context, inference, theological interpretation, and unresolved questions.
5. Search deliberately for credible counterevidence before publishing.
6. Steelman competing explanations rather than attacking weak versions.
7. Never disguise uncertainty as certainty.
8. Publish corrections and preserve the full revision history.
9. A challenger wins by improving the evidence record, not by rhetoric or popularity.
10. For Christian/theological cases, Christ-Consistency Analysis is a separately labeled interpretive layer, not a substitute for historical evidence.

## Truth Trial lifecycle
`QUESTION -> ATOMIC CLAIMS -> SOURCE HUNT -> PROVENANCE -> CONTEXT -> SUPPORTING EVIDENCE -> COUNTEREVIDENCE -> COMPETING HYPOTHESES -> ADVERSARIAL REVIEW -> FINDING -> PUBLICATION -> CHALLENGE -> REVISION`

Every case receives a permanent ID such as `TW-CLAIM-000001` and a version number.

## Evidence record
Each material source should record where possible:
- author/creator
- title or artifact
- date and estimated date range
- source type (manuscript, inscription, dataset, book, article, etc.)
- primary/secondary/tertiary classification
- language and translation used
- provenance/custody information
- temporal proximity to the event or claim
- independence from other cited sources
- exact location supporting the proposition
- evidence direction: supports / challenges / contextual / neutral
- authenticity or dating disputes
- limitations

Repeated claims are not independent corroboration. Trust-Worthy should trace citation lineage and collapse duplicated dependence where possible.

## Investigation agents
The implementation may use specialized agents/processes:
- **Archivist:** locate earliest and primary evidence.
- **Historian:** chronology, genre, audience and historical context.
- **Linguist:** original-language and translation disputes.
- **Advocate A:** strongest case supporting the proposition.
- **Advocate B:** strongest credible case challenging it.
- **Source Auditor:** provenance, independence, circular citation and source laundering.
- **Logician:** assumptions, contradictions, causal errors and inferential validity.
- **Christ-Consistency Analyst:** for theological cases only; compares an interpretation with the recorded words, actions and character attributed to Jesus, with sources and competing interpretations disclosed.
- **Synthesizer:** integrates the record without being instructed which position must win.

## Findings
Avoid binary TRUE/FALSE when the evidence does not justify it. Findings may include:
- Strongly supported
- Better supported than alternatives
- Partially supported
- Disputed
- Poorly supported
- Substantially contradicted
- Insufficient evidence
- False dichotomy
- Unresolved

No numerical confidence score should imply precision the evidence cannot support.

Every finding must include:
1. What appears most likely.
2. Why.
3. Strongest supporting evidence.
4. Strongest counterevidence.
5. Plausible alternative explanations.
6. What remains unknown.
7. What evidence could change the finding.
8. Complete source trail.
9. Current case version and revision history.

## Truth Challenges
Any public finding may be challenged.

A challenge must identify at least one target:
- SOURCE — authenticity/reliability problem
- DATE — chronology error
- TRANSLATION — linguistic error
- CONTEXT — context changes the meaning
- LOGIC — conclusion does not follow
- COUNTEREVIDENCE — omitted evidence materially changes the case
- PROVENANCE — source lineage or independence problem
- ASSUMPTION — unsupported premise
- ALTERNATIVE — stronger competing explanation

Trust-Worthy investigates the challenge independently. It does not defend its prior answer merely because it produced it.

Possible challenge outcomes:
- finding upheld
- finding strengthened
- finding weakened
- finding revised
- finding overturned
- unresolved pending additional evidence

Successful challengers receive permanent attribution in the case history.

## Public case history / version control
Truth has a revision history. Never silently overwrite a finding.

Example:
`v1 -> challenge -> new evidence -> v2 -> translation challenge -> reanalysis -> v3/overturned`

Users and AI systems must be able to inspect what changed and why.

## Public collections
- **The Unveiled:** findings strongly supported by the investigation.
- **The Bunk Book:** claims substantially defeated by evidence, with the failure mode explicitly documented.
- **The Mystery Book:** questions the available evidence cannot presently resolve.

Cases can be reopened whenever material new evidence appears.

## Theology Battles
Two or more positions may enter a structured Truth Trial. Trust-Worthy investigates rather than acting as a partisan debater. Popular votes do not determine findings.

The system should permit outcomes where neither side wins, including false dichotomy and insufficient evidence.

## Christ-Consistency Analysis
For theological questions, Trust-Worthy may separately ask:

> Which interpretation appears most consistent with the recorded words, conduct and character attributed to Jesus in the relevant early sources?

The analysis must disclose textual, translation, dating and interpretive disputes. It must never claim privileged access to God's mind.

## Machine-readable infrastructure
Long-term objective: make the evidence graph usable by other AI systems.

Proposed API concepts:
- `claim_lookup`
- `case_get`
- `evidence_search`
- `source_lineage`
- `challenge_submit`
- `investigation_request`
- `case_changes`

A machine response should return the case ID/version, current assessment, supporting and challenging evidence, provenance relationships, unresolved disputes, alternatives and source references — not merely a verdict.

## Trust-Worthy Verification Protocol (TWVP)
Every investigation should explicitly evaluate:
- **P — Provenance**
- **T — Temporal proximity**
- **I — Independence**
- **C — Corroboration**
- **X — Contradiction/counterevidence**
- **L — Logical validity**
- **A — Alternative explanations**
- **U — Uncertainty**

These dimensions are evidence descriptors, not an arbitrary truth score.

## Social mechanics
The platform should reward epistemic contribution rather than popularity. Core actions:

`INVESTIGATE · SUPPORT WITH EVIDENCE · CHALLENGE · SOURCE · REOPEN`

Reputation can recognize primary-source discoveries, successful corrections, translation corrections, provenance discoveries, finding overturns, and users who revise their own positions when stronger evidence emerges.

## Independence principle
Sponsors, donors, founders, denominations, institutions and users may fund questions or research capacity. They may never purchase a conclusion.

## API vision
Trust-Worthy should become useful even to competing AI systems: instead of rebuilding a mature investigation, an external model can query whether a claim has already undergone a public adversarial Truth Trial and retrieve the evidence graph and current version.

The goal is not to become an authority that commands belief. The goal is to become a trusted, auditable public record of **how claims have survived evidence**.
