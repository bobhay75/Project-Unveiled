# Trust-Worthy AI / Prosper authorization (draft)

**This integration belongs to Trust-Worthy AI in `Project-Unveiled`.**
It has no relationship to the separate `Trust` or `trust-ai-core` repositories.

The optional guard is evaluated after existing input/origin checks and before
private storage, rate-limit reservation, and the paid AI investigation request.

Configure **only on a trusted host** where Prosper runs on loopback:
```sh
TW_PROSPER_GATEWAY_URL=http://127.0.0.1:8080
TW_PROSPER_AGENT_KEY=<private-agent-key>
```

Register `trust-worthy` with permission `investigation.run` in Prosper.
The code sends no question, user address, research content, or source data
to Prosper; only an agent ID and action name. The API key must remain outside
public web roots and source control.

Without `TW_PROSPER_GATEWAY_URL`, the existing site operates unchanged.
When configured, any denial, timeout, invalid response, or invalid URL blocks
the investigation with a generic HTTP 503 page.

**Not production-ready.** This prototype uses local HTTP, assumes a matching
Prosper `/authorize` contract, and must undergo end-to-end testing. Its
authorization check is not atomic with execution. Direct calls to internal
investigation functions are not covered. Do not enable in production until
the gateway and deployment architecture are verified.
