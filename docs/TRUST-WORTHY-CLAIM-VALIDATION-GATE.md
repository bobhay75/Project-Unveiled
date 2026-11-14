# Trust-Worthy Offline Claim-Validation Gate

Status: internal test harness; not a production truth detector or publication system.

This gate turns five evidence-policy boundaries into deterministic regression tests:

- every required claim atom must be supported;
- a cited contradiction blocks delivery;
- repeated copies of one origin do not count as independent corroboration;
- every source that causally informed an answer must appear in its citations;
- passing cases remain candidates for human review and never publish automatically.

## Corpus

`tests/trust-worthy-lab/fixtures/claim-validation-cases.json` contains 25 synthetic cases, five each for fully supported, partially supported, contradicted, duplicate-origin, and citation-laundering conditions. Synthetic names and facts keep the test focused on policy behavior rather than asserting facts about real people or institutions.

Each case declares atomic claim requirements, a minimum independent-origin count, cited source identifiers, causal driver identifiers, and structured source support or contradiction. The evaluator returns one of four proof states:

Only cited origins that support at least one declared claim atom contribute to the independence count. An unrelated citation cannot be used as padding to satisfy the corroboration threshold.

| State | Meaning | Gate action |
| --- | --- | --- |
| `verified` | Every declared atom is supported, the independence threshold is met, and causal sources match citations. | Candidate for human review; publishing remains disabled. |
| `questionable` | Support is partial, cited evidence conflicts, or apparent corroboration collapses to too few origins. | Block and escalate. |
| `manipulated` | A source that causally informed the answer is absent from its citations. | Block and escalate. |
| `unknown` | The input is missing, malformed, duplicated, or references an unknown source. | Fail closed and escalate. |

Run the focused check with:

```sh
node tests/trust-worthy-lab/claim-validation.mjs
```

The repository release gate runs it automatically through `tests/trust-worthy-lab/run_all.sh`.

## Safety boundary

This is a structured policy oracle, not a semantic fact checker. It does not authenticate source contents, infer whether prose entails a claim, determine whether two differently named origins are actually independent, or prove that recorded causal traces are complete. Causal-driver checking depends on retrieval and generation instrumentation supplying an honest trace.

The test module and fixtures are internal repository files excluded from the public deployment manifest. Connecting these rules to live evidence collection or changing a publication path requires separate design review, adversarial evaluation, privacy review, and human approval.
