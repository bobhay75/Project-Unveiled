# Trust-Worthy provider recovery

## Verified production symptom — 2026-11-23

The public form accepted one synthetic, non-sensitive research request and
returned HTTP 503 after its provider returned HTTP 429. Its old diagnostic
allowlist reported `other`, so this result does **not** establish whether the
provider rejected temporary traffic, exhausted credits, or an account limit.
The public health endpoint still reported `ready`: it checks local configuration
and protected storage, not a completed provider-backed research request.

## Operator action

After deploying the current source, inspect the next failed request's sanitized
`http_status`, `error_type`, and `error_code` in the private
`/home/bobsome1/site-private/trust-worthy/openai-errors.jsonl` log. Do not print or
share the API key or private research inputs. Previously rewritten `other`
classifications cannot be reconstructed from the log's keyed message digest.

Match the API organization and project to the key configured on this server.

| Code | Required action |
| --- | --- |
| `credit_balance_exhausted` | Review the credit balance in API billing and add credit if appropriate. |
| `organization_spend_limit_exceeded` | Review the configured organization spend limit. |
| `project_spend_limit_exceeded` | Review the configured project spend limit. |
| `organization_usage_limit_exceeded` | Review the provider-assigned usage limit and available increase. |
| `insufficient_quota` / `billing_hard_limit_reached` | Review billing and limits; the broad code alone does not identify the exact control. |
| `rate_limit_exceeded` / `slow_down` | Reduce bursts and respect the provider's retry delay. |
| `server_is_overloaded` | Defer the request until capacity recovers. |
| `invalid_api_key`, 401, or 403 | Verify the private key and its project permissions. |
| Unrecognized 429 | Inspect the provider account's diagnostic information before changing billing or retrying. |

Useful operator pages:

- [API billing](https://platform.openai.com/settings/organization/billing/overview)
- [Organization limits](https://platform.openai.com/settings/organization/limits)
- [Project settings](https://platform.openai.com/settings/organization/projects)
- [Official 429 troubleshooting](https://help.openai.com/en/articles/5955604-troubleshooting-api-rate-limits-and-429-errors)
- [Official API error codes](https://developers.openai.com/api/docs/guides/error-codes)

The application does not automatically retry provider failures, change billing,
or call a substitute provider. It preserves conservative local allowance
accounting. Reader-facing errors use bounded plain-language messages; private
logs preserve only allowlisted diagnostic identifiers and protected digests.

## Validation and release boundary

Run `php tests/trust-worthy-public/provider-errors.php` and the full
`bash tests/trust-worthy-lab/run_all.sh` with PHP 8.1 or newer. The focused check
covers specific and type-only account failures, temporary limits, malformed
provider payloads, unknown HTTP 429 responses, authentication, privacy, and
existing allowance behavior.

After resolving the provider account condition, send one ordinary non-sensitive
request through the real public form and verify a completed result with a source
trail. A passing local test or health endpoint does not establish that live
research is restored. Rollback is a normal source revert; this change adds no
private data migration or configuration requirement.
