# Trust-Worthy guided investigation release

## Release state — November 23, 2026

The guided workspace is opt-in. With `TW_GUIDED_RESEARCH_ENABLED` unset, empty, or any value other than the exact string `1`, `/truth/` serves the existing public homepage and all new guided/payment routes return 404 before creating private state. Merging or deploying this guarded source does not activate new research charges or PayPal checkout.

The repaired source is ready for automated release review. Live activation remains blocked on a successful provider run from the real host, measured hosting/cost behavior, and PayPal sandbox acceptance. The live preliminary endpoint returned HTTP429 from its provider on November23; local health checks did not prove provider readiness. No live guided research or charge is claimed verified.

## Entry and research

The guided entry asks what the visitor wants to establish, accepts a claim and supporting context, and distinguishes the existing Quick Preliminary Check from Guided Deep Investigation. The Deep submit button posts to `/truth/investigation.php`; the preliminary action remains `/truth/investigate.php`.

The user reviews and edits a claim map before research starts. The map endpoint charges a bounded research allowance and issues a one-use authorization bound to the claim, context and requesting address. The workspace shows only backend completion receipts for decomposition, origin, primary evidence, corroboration, source dependency, counterevidence, context and synthesis.

The full profile performs six web-assisted evidence passes plus synthesis after claim-map review. Each web pass allows at most two search calls. The paid reuse profile preserves origin, primary and corroboration receipts in private storage; its paid portion analyzes dependency and chronology from those receipts, performs a counterevidence web pass, then synthesizes. It does not claim that every stage browses.

## Evidence and findings

A scored finding requires at least four distinct URLs across at least three domain families, a counterevidence source/family, and corroboration across at least two families. These are minimum diversity checks. Different domains may share the same original record, wire story, study or claim; domain counts do not prove independence.

Provider responses must be complete; required search calls must themselves be complete and within the configured count. Malformed or unfinished results stop the stage. Citation URLs are normalized and filtered before counts; provider-supplied family labels cannot inflate diversity.

The final response must begin with exactly one valid verdict and probability pair. Allowed scored verdicts are SUPPORTED, LIKELY, MIXED, UNLIKELY and CONTRADICTED with an integer from0 through100. Without the evidence floor, only INSUFFICIENT EVIDENCE — NO VERDICT with PROBABILITY: NONE is accepted. A conservative no-verdict response is also allowed when the numerical minimum is met. Contradictory, duplicate or malformed headers stop publication of that finding; raw model text cannot retain a contrary scored headline.

The probability remains an experimental, evidence-conditioned model estimate. It is not a calibrated measurement of objective truth.

## Functional follow-ups and export

Completed findings can be downloaded as JSON with the analysis, evidence links, metrics and investigation identifier. The export contains no payment authorization or provider key.

Follow-up controls open a new investigation in another tab, carrying the original claim, a bounded excerpt and source leads. They explicitly label the earlier model answer as unverified context. The original result stays available, and the new claim map needs review before provider work. Follow-ups use a fresh allowance and, if configured, a separate optional checkout. This implementation does not silently extend a durable multi-investigation evidence graph or pretend queued work has executed.

Both ordinary and paid streams validate their event sequence, completed receipts and final finding. They reject malformed, duplicate, contradictory, oversized or truncated output, including a stream that ends without a terminal result. A final line without a newline is accepted. Repeated button presses cannot start overlapping requests in the same workspace.

## Payment and recovery

PayPal Orders credentials remain outside `public_html`; `TW_PAYPAL_ENABLED=1` and the guided flag are both required. Price is fixed server-side at USD2.99 for the same investigation. Signed checkout/resume state, exact capture validation, idempotent provider request IDs, locked case transitions and one-use resume authorization prevent client price changes and duplicate return replay from restarting research.

The HTTP client restricts payment requests to the fixed PayPal API origins over HTTPS, with no redirects or proxy inheritance, bounded response size and a deadline. Approval links must match the configured sandbox or live PayPal origin.

If paid research fails, the case retains its payment receipt and research context in `needs_review`; it is not silently retried or charged again. The UI exposes a case reference and directs the purchaser to Bobsome1 for recovery or refund review. Completed findings are persisted before their final completion receipt is emitted. Shutdown cleanup cannot rewind a completed case. Abrupt host termination still requires operator inspection of old `finishing` cases.

The purchaser receives a private recovery link signed for the exact case, PayPal order and recorded capture. It expires with the case (24 hours after case creation). Anyone holding that link can view the case. Recovery responses use no-store and no-referrer headers and require the signed receipt; the status endpoint also requires a same-origin POST. Reading a result or pending status does not call a provider, capture a payment, consume the resume authorization or write case state. A previously used PayPal return URL can recover only its exact already-recorded payment, even if its original checkout action has expired; an explicit continuation form preserves the same-origin boundary when returning from PayPal.

Refreshing the continuation workspace or losing the final stream recovers a saved completed finding. Pending status polls stop after at most 24 requests or 120 seconds, with bounded request deadlines; the purchaser can check again manually. A still-unused paid case can restore its resume authorization, but the research starts only on an explicit submit. A failed request before consumption permits an explicit retry; an authorized, running, completed or review-needed case cannot be restarted through recovery.

Operator procedure: use the private case record and PayPal activity to reconcile a reported failure, verify the original capture, and deliver the completed finding or review a refund. Do not manually rewind a running case, ask the purchaser to pay again, or regenerate a signed resume token without inspecting the original state. There is no automatic refund endpoint in this release.

## Verification

The repository gate runs PHP syntax/behavior, payment state regressions, 76 provider/origin/replay/paid failure recovery checks, 35 guided PHP HTTP checks, 82 paid-recovery HTTP checks, seven stream/follow-up JavaScript groups, source-family/authorization/evidence reuse checks and the existing preliminary/lab/analytics/private-storage regressions. The paid-recovery HTTP suite uses temporary private records and disables all outbound provider network functions. It verifies exact case/order/capture binding, direct refresh, lost handoff, completed-result retrieval, pending/review states, origin and expiry rejection, no recapture, and unchanged private file contents, names and permissions.

A hosted Chrome gate renders the actual guided entry and workspace at 375px and 1280px, checks overflow and keyboard focus, and saves screenshots. At each width it also exercises nine real DOM assertions: claim-map approval, repeated-click protection, edited-map submission, receipts/results, safe text rendering, six follow-ups, original-result preservation, exported data and truncated-stream handling. It uses synthetic input and no provider key. This validates the client workflow, not real paid delivery or provider quality.

A separate Chrome recovery fixture exercises the actual paid workspace at 375px and 1280px with synthetic responses: completed refresh, a dropped final stream, duplicate clicks, an explicit retry after pre-consumption rejection, safe rendering/export and finite pending polling. It contains no live payment or provider calls. Both browser gates must pass in hosted CI; the current local execution environment blocks Chrome's required sockets.

The existing public lab asset manifest remains intact. New public files are explicitly registered in the sorted deployment allowlist. No private cases, credentials, owner tools or tests are publicly deployed.

## Activation work still required

1. Restore authenticated cPanel access and deploy the tested source to the intended paths.
2. Resolve the actual provider429 using the private sanitized diagnostic and the correct OpenAI project’s limits/billing settings; do not assume every429 is depleted credit.
3. In controlled staging, enable the guided flag and run benign supported, disputed and insufficient-evidence cases. Inspect receipts, citations, failure behavior, output quality and actual token/search cost. Verify shared-host deadlines for the entire stream.
4. Configure PayPal sandbox credentials, then test approval, cancel, exact capture, replay, dropped connection, failed paid synthesis and support/refund reconciliation. Keep live payments disabled until this completes.
5. Verify desktop and actual mobile browser interaction, exports and follow-up navigation. The original report must remain available when a follow-up opens.
6. Enable public guided research only after those checks. Public publication of generated claims still requires the existing evidence and editorial policy; the workspace does not auto-publish investigations.
