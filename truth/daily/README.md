# Trust-Worthy Daily Truth Trial Engine

## What it does

1. `refresh.php` reads current private user submissions and combines them with the maintained Meat Desk and Traditions of Men question libraries.
2. The queue puts current user questions first, then rotates consequential evergreen questions. It does not fetch RSS feeds or place routine news headlines in candidate slots.
3. `desk.php` is a private editor queue protected by a short-lived server session.
4. Clicking **Put This Question on Trial** makes one on-demand OpenAI Responses API request with the `web_search` tool.
5. The generated report is stored privately and marked **human review required**.
6. The editor can revise the case and click **Approve & Publish Today's Trial**.
7. `../today.php` renders the reviewed case publicly and closes with **YOU BE THE JUDGE.**

## First run after deployment

```bash
/usr/local/bin/php /home/bobsome1/public_html/truth/daily/refresh.php
```

The refresh command never prints an authentication secret. Generate a one-use editor sign-in separately:

```bash
/usr/local/bin/php /home/bobsome1/public_html/truth/daily/admin-url.php
```

The URL carries its one-use code in the browser fragment, so the code is not sent in the HTTP URL, access logs, redirects, or referrer headers. It expires after 10 minutes and is consumed at sign-in. Do not post or share it.

## Private queue refresh cron

Refreshing the private question/library queue performs no outbound network request and does not call the AI. A practical cPanel cron is every four hours:

```cron
17 */4 * * * /bin/mkdir -p /home/bobsome1/site-private/trust-worthy && /usr/local/bin/php /home/bobsome1/public_html/truth/daily/refresh.php >> /home/bobsome1/site-private/trust-worthy/daily-refresh.log 2>&1
```

The AI/web-search research call occurs only when an authenticated editor clicks **Put This Question on Trial**. Current reporting is gathered then as evidence for that selected investigation, not as a routine-news editorial feed. The cron log does not contain an editor credential.

## API configuration

The engine checks API keys in this order:

1. `TRUST_WORTHY_OPENAI_API_KEY`
2. `OPENAI_API_KEY`
3. `INSIDE_OF_ME_OPENAI_API_KEY`
4. Owner-only `/home/bobsome1/site-private/trust-worthy/openai-key.txt` fallback

If the fallback path exists, it must still pass regular-file, non-symlink, size, identity, and `0600` checks even when an environment key takes precedence.

Optional model override:

```text
TRUST_WORTHY_OPENAI_MODEL
```

Default model: `gpt-5.6-luna` to keep routine trial drafting cost-sensitive.

Daily Desk calls fail closed behind an atomic budget reservation. Defaults are one concurrent investigation, two starts per hour, six starts per UTC day, 4,000 output tokens, and two web-search tool calls. Failed provider attempts stay charged against the allowance because upstream work may still be billable. Optional overrides are bounded:

```text
TRUST_WORTHY_DAILY_MAX_CALLS_PER_HOUR
TRUST_WORTHY_DAILY_MAX_CALLS_PER_DAY
TRUST_WORTHY_DAILY_MAX_CONCURRENT
TRUST_WORTHY_DAILY_MAX_OUTPUT_TOKENS
TRUST_WORTHY_DAILY_MAX_WEB_SEARCH_CALLS
```

## Storage

Private data is stored outside `public_html` in:

```text
/home/bobsome1/site-private/trust-worthy/
```

That includes the candidate queue, one-use sign-in hashes, sessions, budget ledger, individually identified research drafts, and fixed-code owner-safe error state. Provider exception text is never persisted.

Authentication material and private Daily Desk JSON are created with owner-only `0600` permissions before content is written. The OpenAI key file must also be `0600`; see `KEY-FILE-NOTE.md`.

Release 16 retires the obsolete reusable `daily-admin-token.txt` and raw `daily-last-error.json` exact files. A structurally valid, current `daily-draft.json` is first preserved under its deterministic ID in owner-only `daily-drafts/`, but remains `legacy-unverified-draft` and cannot be published because older research was not bound to provider-returned evidence. An expired private-question draft is retired; a corrupt, unsupported, symlinked, or otherwise unsafe legacy draft stops migration and remains untouched for owner review. The deployment command performs backward-compatible private-storage checks before public sync, but exact destructive Daily retirement runs only after Release 16 is live. `refresh.php` repeats the narrow retirement so a rolling deployment cannot leave obsolete sensitive state behind.

A reviewed publication is copied to:

```text
/home/bobsome1/public_html/truth/daily-data/latest.json
```

## Publication rule

No model-generated investigation is automatically published. A human must review/edit the draft and explicitly approve publication. Publication is bound to the exact private draft ID and digest that the editor reviewed.

Questions submitted through the public private-question form remain private. They can be investigated inside the desk, but the publish action is not rendered for those drafts and the server rejects any attempted publication. Public JSON uses an explicit field allow-list and never contains candidate details, question context, provider identifiers, usage, model names, or draft identifiers.

`TW_INTAKE_RETENTION_DAYS` also governs legacy private-question copies used by the Daily Desk (default 180 days, bounded to 30–180). Refresh carries the effective deletion deadline into the private candidate cache and refuses malformed or future-dated source records. Deployment, refresh, authenticated desk access, and investigation prune expired or invalid private candidate derivatives from that cache under a lock; corrupt cache structure fails closed without replacement. Expired private draft derivatives are removed, and investigation rechecks expiry before reserving any provider work. Existing question storage is forced to owner-only `0600` before it is read.

The provider response must report `completed`, stay within the configured web-search count, and return at least two auditable source URLs. Every source in the model's JSON must match a URL in provider-returned search evidence; fabricated, credentialed, local, private-address, or otherwise unsafe URLs stop the draft. Private investigations request `store: false`.

Provider-supplied error text is never copied into editor flashes or persistent Daily Desk error state, because an upstream error could echo private prompt content. Failures expose only fixed owner-safe categories/codes and HTTP status where applicable.

Publication uses an owner-only private transaction journal. The archive and `latest.json` are atomic writes; if the private draft-state update fails afterward, the same transaction is replayed rather than creating a second or mismatched publication.
