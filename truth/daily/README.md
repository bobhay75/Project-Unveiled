# Trust-Worthy Daily Truth Trial Engine

## What it does

1. `refresh.php` pulls configured RSS feeds without AI cost.
2. Headlines are normalized, deduplicated, compared across outlets, and ranked for freshness, public impact, and testable-claim signals.
3. `desk.php` is a private editor queue protected by a server-generated token.
4. Clicking **Investigate This Claim** makes one on-demand OpenAI Responses API request with the `web_search` tool.
5. The generated report is stored privately and marked **human review required**.
6. The editor can revise the case and click **Approve & Publish Today's Trial**.
7. `../today.php` renders the reviewed case publicly and closes with **YOU BE THE JUDGE.**

## First run after deployment

```bash
/usr/local/bin/php /home/bobsome1/public_html/truth/daily/refresh.php
```

The command prints the private editor URL. To print it again later:

```bash
/usr/local/bin/php /home/bobsome1/public_html/truth/daily/admin-url.php
```

Do not post or share the private editor URL.

## Free feed refresh cron

Refreshing feeds does not call the AI. A practical cPanel cron is every four hours:

```cron
17 */4 * * * /bin/mkdir -p /home/bobsome1/site-private/trust-worthy && /usr/local/bin/php /home/bobsome1/public_html/truth/daily/refresh.php >> /home/bobsome1/site-private/trust-worthy/daily-refresh.log 2>&1
```

The AI/web-search research call occurs only when an editor clicks **Investigate This Claim**.

## API configuration

The engine checks API keys in this order:

1. `TRUST_WORTHY_OPENAI_API_KEY`
2. `OPENAI_API_KEY`
3. `INSIDE_OF_ME_OPENAI_API_KEY`

Optional model override:

```text
TRUST_WORTHY_OPENAI_MODEL
```

Default model: `gpt-5.6-luna` to keep routine trial drafting cost-sensitive.

## Storage

Private data is stored outside `public_html` in:

```text
/home/bobsome1/site-private/trust-worthy/
```

That includes the candidate queue, editor token, current research draft, and errors.

A reviewed publication is copied to:

```text
/home/bobsome1/public_html/truth/daily-data/latest.json
```

## Publication rule

No model-generated investigation is automatically published. A human must review/edit the draft and explicitly approve publication.
