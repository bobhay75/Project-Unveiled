<?php
declare(strict_types=1);
require_once __DIR__ . '/../../truth/lib/trust-worthy-ai.php';

$checks = 0;
function verify(bool $condition, string $label): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($label);
}

$accountCodes = [
    'billing_hard_limit_reached', 'insufficient_quota', 'credit_balance_exhausted',
    'organization_spend_limit_exceeded', 'project_spend_limit_exceeded',
    'organization_usage_limit_exceeded',
];
$raw = 'private question, provider account details, and a secret must never be reflected';
foreach ($accountCodes as $code) {
    verify(tw_ai_safe_diagnostic_identifier($code, 'code') === $code, 'Actionable account code was discarded: ' . $code);
    $result = tw_ai_provider_failure(429, ['error' => ['code' => $code, 'type' => 'insufficient_quota', 'message' => $raw]]);
    verify(str_contains($result['message'], 'account access') && !str_contains($result['message'], $raw), 'Account failure guidance was wrong or leaked provider text');
    verify($result['ok'] === false && $result['quota_consumed'] === true, 'Provider denial changed the existing allowance boundary');
}
foreach (['rate_limit_exceeded', 'slow_down', 'server_is_overloaded'] as $code) {
    verify(tw_ai_safe_diagnostic_identifier($code, 'code') === $code, 'Temporary code was discarded');
    $result = tw_ai_provider_failure($code === 'server_is_overloaded' ? 503 : 429, ['error' => ['code' => $code, 'message' => $raw]]);
    verify(str_contains($result['message'], 'busy') && !str_contains($result['message'], 'account access'), 'Temporary error was misclassified as an account problem');
}
foreach (['insufficient_quota', 'service_unavailable_error'] as $type) {
    verify(tw_ai_safe_diagnostic_identifier($type, 'type') === $type, 'Safe provider error type was discarded');
}
verify(str_contains(tw_ai_provider_failure(429, ['error' => ['type' => 'insufficient_quota']])['message'], 'account access'), 'Type-only quota response was lost');
verify(str_contains(tw_ai_provider_failure(429, ['error' => ['code' => 'slow_down', 'type' => 'insufficient_quota']])['message'], 'account access'), 'Account denial did not take precedence over retry guidance');
verify(str_contains(tw_ai_provider_failure(429, ['error' => ['type' => 'rate_limit_error']])['message'], 'busy'), 'Type-only rate limit response was lost');
foreach ([[], ['error' => null], ['error' => 'bad'], ['error' => ['code' => $raw, 'type' => $raw, 'message' => $raw]], ['error' => ['code' => [], 'type' => 42]]] as $data) {
    $result = tw_ai_provider_failure(429, $data);
    verify(!str_contains($result['message'], 'account access') && !str_contains($result['message'], 'busy'), 'Unknown HTTP 429 was given an invented cause');
    verify(!str_contains($result['message'], $raw), 'Raw provider text leaked');
}
foreach ([401, 403] as $status) {
    verify(str_contains(tw_ai_provider_failure($status, [])['message'], 'configuration'), 'Authentication failure did not request operator action');
}
verify(tw_ai_safe_diagnostic_identifier($raw, 'code') === 'other', 'Untrusted diagnostic code escaped allowlist');
verify(tw_ai_safe_diagnostic_identifier($raw, 'type') === 'other', 'Untrusted diagnostic type escaped allowlist');
$record = tw_ai_redact_diagnostic_record([
    'error_type' => 'insufficient_quota',
    'error_code' => 'credit_balance_exhausted',
    'error_message' => $raw,
    'nested' => ['error_code' => 'project_spend_limit_exceeded', 'question' => $raw],
], str_repeat('a', 64));
verify($record['error_code'] === 'credit_balance_exhausted' && $record['nested']['error_code'] === 'project_spend_limit_exceeded', 'Private log discarded the actionable cause');
verify(!str_contains(json_encode($record, JSON_THROW_ON_ERROR), $raw), 'Diagnostic log retained private provider text');
echo "Provider error checks passed: {$checks}\n";
