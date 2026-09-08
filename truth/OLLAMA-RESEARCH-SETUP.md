# Trust-Worthy Ollama research pilot

This is an admin-only, queue-first shadow workflow. A public submission writes a private queue item and performs no provider request. An authenticated owner may run one bounded draft, inspect its evidence ledger, and record a human verdict. Nothing in this workflow publishes content.

## Private configuration

Create these files outside `public_html`, in `site-private/trust-worthy/`:

- `ollama-api-key.txt` — the Ollama Cloud API key, mode `0640`.
- `research-enabled.txt` — exactly `enabled` to permit runs. Any other value fails closed.
- `ollama-config.json` — optional overrides for the allowlisted settings below.

Example configuration:

```json
{
  "planner_model": "deepseek-v4-flash:0731",
  "synthesis_model": "deepseek-v4-pro:0813",
  "daily_runs": 3,
  "max_searches_per_run": 8,
  "max_results_per_search": 3,
  "max_input_tokens_per_run": 300000,
  "max_output_tokens_per_run": 20000,
  "daily_cost_usd": 0.50,
  "run_cost_usd": 0.20,
  "timeout_seconds": 45
}
```

The cost values are conservative internal reservations, not a reproduction of Ollama billing. Each attempted run reserves `run_cost_usd` before the first API call. Once reserved, it is not refunded automatically after a failure. This intentionally favors stopping early over squeezing every cent from a daily allowance. Provider billing and search quotas still need review in the Ollama account.

## Safety properties

- `/truth/investigate.php` is a compatibility queue endpoint; it cannot call a model.
- Provider code is reachable only from `/owner/trust-worthy/`, which inherits owner Basic Auth.
- The server checks `research-enabled.txt` before each run. The owner dashboard can disable, but cannot enable, research.
- Daily run and cost-reservation caps are checked before a run.
- Searches, results, generation sizes, and timeouts are bounded.
- The provider response is treated as untrusted input. JSON is parsed and schema-checked locally because Ollama Cloud does not guarantee structured output for this model.
- Every factual, inferential, disputed, and counterevidence claim must cite source IDs from the captured ledger. Invalid outputs fail closed and are not saved as drafts.
- Human review is blank by default. Recording approval does not publish.

## Rollout

1. Merge only after code review and CI.
2. Deploy with no key and no enable file; verify public submissions only queue.
3. Add the key, leave research disabled, and verify the owner dashboard.
4. Set the enable file manually and run one low-risk test question.
5. Verify the provider bill/usage page against the private daily ledger.
6. Keep the pilot private until source quality and actual costs are acceptable over several cases.

To stop immediately, use **Disable research now** in the owner dashboard or replace the enable file content with `disabled`. Removing the API key is the second independent stop.
