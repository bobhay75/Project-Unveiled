# Trust-Worthy AI / Prosper authorization

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

The transport explicitly bypasses host proxy settings, follows no redirects,
accepts at most 8 KiB while reading, and stops after two seconds. Invalid
credentials, malformed JSON and non-200 responses fail closed. The guard is
disabled unless the private gateway URL is configured.

## Verification — November 23, 2026

The local PHP-to-Python integration passed 23 checks against the recovered
Prosper `gateway.py` from commit `eaa41bae`. Real loopback requests verified
permission grant/revocation, key rotation, identity deletion and the retained
audit chain. Tests used temporary SQLite storage and generated credentials;
no production data, AI provider or payment was involved.

The repeatable HTTP boundary suite covers oversized and invalid responses,
redirect refusal, timeouts, fixed outbound metadata and proxy isolation:

```bash
python3 tests/trust-worthy-lab/prosper-authorization-http.py
python3 tests/trust-worthy-lab/prosper-authorization-http.py \
  --prosper-source /absolute/path/to/prosper/gateway.py
```

This remains a preflight permission check on a trusted local host. It is not
atomic with the later AI execution, and direct internal investigation calls
are outside this endpoint guard. The production host must have a compatible,
protected local gateway before enabling the private configuration. Existing
Namecheap shared hosting has not been verified to run that Python service.
Keep the optional guard unconfigured there until that host requirement is met.
