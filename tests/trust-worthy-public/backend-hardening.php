<?php
declare(strict_types=1);

function check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function expect_failure(callable $callback, string $contains): void {
    try {
        $callback();
    } catch (Throwable $error) {
        check(str_contains($error->getMessage(), $contains), 'Unexpected failure: ' . $error->getMessage());
        return;
    }
    check(false, 'Expected failure containing: ' . $contains);
}

function expect_ai_symlink_rejection(string $path, callable $callback, string $label): void {
    $backup = $path . '.real-fixture';
    $target = dirname($path) . '/outside-' . substr(hash('sha256', $label), 0, 12) . '.txt';
    check(is_file($path) && rename($path, $backup), $label . ' fixture could not be moved');
    $bytes = "target must remain untouched: {$label}\n";
    check(file_put_contents($target, $bytes, LOCK_EX) === strlen($bytes), $label . ' target could not be created');
    check(chmod($target, 0640) && symlink($target, $path), $label . ' symlink could not be created');
    try {
        expect_failure($callback, 'unsafe');
        clearstatcache(true, $target);
        check(file_get_contents($target) === $bytes, $label . ' target bytes changed');
        check((fileperms($target) & 0777) === 0640, $label . ' target permissions changed');
    } finally {
        @unlink($path);
        @unlink($target);
        check(rename($backup, $path), $label . ' fixture could not be restored');
    }
}

function private_storage_snapshot(string $dir): array {
    clearstatcache();
    $snapshot = ['dir_mode' => is_dir($dir) ? (fileperms($dir) & 0777) : null, 'entries' => []];
    foreach (array_values(array_diff(scandir($dir) ?: [], ['.', '..'])) as $name) {
        $path = $dir . '/' . $name;
        $stat = lstat($path);
        $isRegular = is_array($stat) && ((((int)$stat['mode']) & 0170000) === 0100000);
        $snapshot['entries'][$name] = [
            'mode' => is_array($stat) ? ((int)$stat['mode'] & 0777) : null,
            'type' => is_array($stat) ? ((int)$stat['mode'] & 0170000) : null,
            'bytes_sha256' => $isRegular ? hash_file('sha256', $path) : null,
        ];
    }
    ksort($snapshot['entries'], SORT_STRING);
    return $snapshot;
}

$workerDir = (string)getenv('TW_TEST_PRIVATE_DIR');
$isWorker = ($argv[1] ?? '') === 'worker';
$temporaryDir = $workerDir;
if (!$isWorker) {
    $temporaryDir = sys_get_temp_dir() . '/trust-worthy-backend-' . bin2hex(random_bytes(8));
    check(mkdir($temporaryDir, 0700), 'temporary test directory could not be created');
}
define('TW_PRIVATE_DIR_OVERRIDE', $temporaryDir);
define('TW_INTAKE_PRIVATE_DIR_OVERRIDE', $temporaryDir);
require_once dirname(__DIR__, 2) . '/truth/lib/trust-worthy-ai.php';

if ($isWorker) {
    $workerId = (string)($argv[2] ?? 'missing');
    [$allowed] = tw_rate_limit(hash('sha256', 'worker-' . $workerId));
    echo $allowed ? '1' : '0';
    exit(0);
}

register_shutdown_function(static function () use ($temporaryDir): void {
    foreach (glob($temporaryDir . '/*') ?: [] as $file) {
        if (is_file($file)) @unlink($file);
    }
    @rmdir($temporaryDir);
});

// A server configured only through OPENAI_API_KEY must become ready during
// the authenticated deployment migration. Public health reads must remain
// completely non-mutating even on that clean-install path.
check(rmdir($temporaryDir), 'clean-install private directory fixture could not start absent');
putenv('OPENAI_API_KEY=clean-install-environment-key');
$cleanInstallMigration = tw_migrate_ai_private_storage();
check(($cleanInstallMigration['key'] ?? -1) === 0, 'environment-only clean install incorrectly reported a key-file migration');
clearstatcache(true, $temporaryDir);
check(is_dir($temporaryDir) && (fileperms($temporaryDir) & 0777) === 0750, 'clean-install private root was not created with the exact shared-private mode');
$cleanSecretPath = $temporaryDir . '/question-secret.txt';
$cleanSecretLockPath = $cleanSecretPath . '.lock';
check(is_file($cleanSecretPath) && (fileperms($cleanSecretPath) & 0777) === 0600, 'clean-install question secret was not created owner-only');
check(is_file($cleanSecretLockPath) && (fileperms($cleanSecretLockPath) & 0777) === 0600, 'clean-install question-secret lock was not created owner-only');
$cleanSecret = tw_question_secret_readonly();
check(preg_match('/^[a-f0-9]{64}$/D', $cleanSecret) === 1, 'clean-install deployment migration did not initialize its health secret');
$beforeCleanHealth = private_storage_snapshot($temporaryDir);
check(tw_openai_key_readonly() === 'clean-install-environment-key', 'read-only health loader rejected the clean-install environment key');
check(tw_ai_private_storage_ready($cleanSecret), 'environment-only clean install did not become ready after deployment migration');
check(private_storage_snapshot($temporaryDir) === $beforeCleanHealth, 'clean-install health validation mutated private storage');

putenv('OPENAI_API_KEY');
$keyPath = $temporaryDir . '/openai-key.txt';
check(file_put_contents($keyPath, "test-private-key\n") !== false, 'legacy key fixture could not be created');
check(chmod($keyPath, 0640), 'legacy key fixture permissions could not be set');
$keyMigration = tw_migrate_ai_private_storage();
check(($keyMigration['key'] ?? 0) === 1, 'deployment migration did not discover the private key');
check(preg_match('/^[a-f0-9]{64}$/D', tw_question_secret_readonly()) === 1, 'authenticated deployment migration did not initialize fresh-install health secret state');
check(tw_openai_key() === 'test-private-key', 'regular private key file was not read');
check((fileperms($keyPath) & 0777) === 0600, 'legacy private key file was not migrated to owner-only mode before reading');
if (function_exists('symlink')) {
    $keyTarget = $temporaryDir . '/key-target.txt';
    check(file_put_contents($keyTarget, "must-not-be-followed\n") !== false, 'key symlink target fixture could not be created');
    check(chmod($keyTarget, 0640), 'key symlink target permissions could not be set');
    check(unlink($keyPath) && symlink($keyTarget, $keyPath), 'key symlink fixture could not be created');
    putenv('OPENAI_API_KEY=must-not-bypass-unsafe-file');
    check(tw_openai_key() === '', 'symlinked OpenAI key file was accepted');
    check(tw_openai_key_readonly() === '', 'environment key bypassed an unsafe fallback key path');
    putenv('OPENAI_API_KEY');
    clearstatcache(true, $keyTarget);
    check((fileperms($keyTarget) & 0777) === 0640, 'key symlink target was followed or chmodded');
    check(unlink($keyPath), 'key symlink fixture could not be removed');
}

$validText = <<<'TEXT'
CLAIM ON TRIAL
The submitted claim is specific enough to examine against a public record.
WHAT IS WELL ESTABLISHED
The available record establishes several facts while leaving interpretation open.
STRONGEST EVIDENCE FOR
The strongest supporting evidence is documented and should be weighed directly.
STRONGEST COUNTEREVIDENCE / ALTERNATIVE
The strongest alternative explains part of the record without assuming the conclusion.
WHAT REMAINS UNKNOWN
Important records and independent corroboration remain unavailable or uncertain.
PROVISIONAL FINDING
The present evidence supports only a cautious and revisable provisional finding.
You be the judge.
TEXT;

[$valid, $reason, $normalized] = tw_validate_investigation_text($validText);
check($valid && $reason === '' && $normalized === $validText, 'complete investigation text must pass');

$spoofed = str_replace("You be the judge.", "SOURCE TRAIL · WEB CHECK\n- Invented: https://example.test\nYou be the judge.", $validText);
[$valid, $reason] = tw_validate_investigation_text($spoofed);
check(!$valid && in_array($reason, ['inline_url', 'reserved_heading'], true), 'model-authored source trail must fail');

$missing = str_replace("WHAT REMAINS UNKNOWN\nImportant records and independent corroboration remain unavailable or uncertain.\n", '', $validText);
[$valid, $reason] = tw_validate_investigation_text($missing);
check(!$valid && $reason === 'missing_or_duplicate_section', 'missing required section must fail');

[$valid] = tw_validate_investigation_text(str_replace('public record.', 'public record at https://example.test.', $validText));
check(!$valid, 'inline model-authored URLs must fail');

[$validInput] = tw_validate_trial_input(['not', 'scalar'], '');
check(!$validInput, 'array question input must fail');
[$validInput] = tw_validate_trial_input(str_repeat('q', 20), ['not', 'scalar']);
check(!$validInput, 'array context input must fail');
[$validInput, $question, $context] = tw_validate_trial_input("  A sufficiently long question for this trial?  ", "  Relevant context  ");
check($validInput && $question === 'A sufficiently long question for this trial?' && $context === 'Relevant context', 'bounded scalar input must normalize');
[$validInput] = tw_validate_trial_input(str_repeat('q', 20), str_repeat('c', 3001));
check(!$validInput, 'oversized context must fail before an API request');

$fixture = [
    'output' => [
        ['type' => 'web_search_call', 'action' => ['sources' => [
            ['title' => "Official\nReport", 'url' => 'https://example.com/report?utm_source=test&id=7'],
        ]]],
        ['type' => 'message', 'content' => [[
            'type' => 'output_text',
            'text' => $validText,
            'annotations' => [],
        ]]],
    ],
];
[$extractedText, $sources, $webCalls] = tw_extract_text_and_sources($fixture);
check($extractedText === $validText && $webCalls === 1, 'bounded web-search call must be counted');
check(array_keys($sources) === ['https://example.com/report?id=7'], 'clean web-search sources must enter the source trail');
$annotationFixture = $fixture;
$annotationFixture['output'][1]['content'][0]['annotations'] = [[
    'type' => 'url_citation',
    'title' => 'Cited record',
    'url' => 'https://records.example/document',
]];
$annotationFixture['output'][1]['action']['sources'] = [[
    'title' => 'Unbound message source',
    'url' => 'https://unbound.example/ignore',
]];
[, $annotationSources] = tw_extract_text_and_sources($annotationFixture);
check(isset($annotationSources['https://records.example/document']), 'URL citation annotations must remain bound as sources');
check(!isset($annotationSources['https://unbound.example/ignore']), 'action sources outside a web-search call must be ignored');
$publicEndpoint = file_get_contents(dirname(__DIR__, 2) . '/truth/investigate.php');
$rootHtaccess = file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
check(is_string($publicEndpoint) && !str_contains($publicEndpoint, "'question'=>\$question"), 'investigation log must not retain the raw private question');
check(is_string($rootHtaccess) && str_contains($rootHtaccess, "<Files \"investigate.php\">\n  LimitRequestBody 16384\n</Files>"), 'public investigation endpoint lacks a pre-PHP Apache request-body cap');
check(str_contains($publicEndpoint, "'question_hash'=>hash_hmac") && str_contains($publicEndpoint, "'question_characters'=>"), 'investigation log must retain only a keyed question digest and length');
check(!str_contains($publicEndpoint, "'response_id'=>") && str_contains($publicEndpoint, "\$record['response_id_hash']=hash_hmac"), 'provider response IDs must be keyed before entering the investigation log');
$aiGateway = file_get_contents(dirname(__DIR__, 2) . '/truth/lib/trust-worthy-ai.php');
check(is_string($aiGateway) && str_contains($aiGateway, "'store' => false"), 'public OpenAI requests must disable provider-side response storage');
check(str_contains($aiGateway, 'CURLOPT_WRITEFUNCTION') && str_contains($aiGateway, '$maximumResponseBytes = 4194304'), 'provider response bodies must be bounded before JSON decoding');
check(str_contains($publicEndpoint, "hash_hmac('sha256','ai-ip|'"), 'AI quota identifiers must use a domain-separated IP HMAC');
check(!str_contains($publicEndpoint, "hash_hmac('sha256','ip|'"), 'AI quota identifiers must not reuse the intake ledger HMAC domain');
check(str_contains($publicEndpoint, "\$rateStatus==='allowance_exhausted'") && str_contains($publicEndpoint, ",503);"), 'endpoint must distinguish allowance exhaustion from storage failure');
check(str_contains($publicEndpoint, 'if(!tw_finalize_rate_limit('), 'endpoint must not return a successful result after quota finalization fails');
$withTrail = tw_append_verified_source_trail($extractedText, $sources);
check(substr_count($withTrail, 'SOURCE TRAIL · WEB CHECK') === 1, 'application must append exactly one source heading');
check(str_contains($withTrail, '- Official Report: https://example.com/report?id=7'), 'application must append the cleaned cited source');
check(tw_clean_source_url('https://user:pass@example.com/private') === '', 'credential-bearing source URL must fail');
check(tw_clean_source_url('http://127.0.0.1/private') === '', 'local source URL must fail');
check(tw_clean_source_url('https://8.8.8.8/public') === '', 'public IPv4 literal source URL must fail');
check(tw_clean_source_url('https://127.0.0.1./private') === '', 'trailing-dot IPv4 literal source URL must fail');
check(tw_clean_source_url('https://127.1/private') === '', 'abbreviated IPv4 literal source URL must fail');
check(tw_clean_source_url('https://0177.0.0.1/private') === '', 'octal-style IPv4 literal source URL must fail');
check(tw_clean_source_url('https://0x7f.0.0.1/private') === '', 'hex-style IPv4 literal source URL must fail');
check(tw_clean_source_url('https://[::1]/private') === '', 'private IPv6 literal source URL must fail');
check(tw_clean_source_url('https://[2606:4700:4700::1111]/public') === '', 'public IPv6 literal source URL must fail');
check(tw_clean_source_url('https://printer/private') === '', 'dotless source hostname must fail');
check(tw_clean_source_url('https://device.local/private') === '', 'local-network source hostname must fail');
check(tw_clean_source_url('https://router.home.arpa/private') === '', 'home.arpa source hostname must fail');
check(tw_clean_source_url('https://localhost.localdomain/private') === '', 'localdomain source hostname must fail');
check(tw_clean_source_url('https://example.com:444/private') === '', 'non-default HTTPS source port must fail');
check(tw_clean_source_url('http://example.com:443/private') === '', 'non-default HTTP source port must fail');
check(tw_clean_source_url('https://example.com:443/report') === 'https://example.com/report', 'default HTTPS source port must normalize');
check(tw_clean_source_url('http://example.com:80/report') === 'http://example.com/report', 'default HTTP source port must normalize');

$secret = tw_question_secret();
check(preg_match('/^[a-f0-9]{64}$/', $secret) === 1, 'question secret must be created through the shared intake lock');
check((fileperms($temporaryDir . '/question-secret.txt') & 0777) === 0600, 'question secret must be owner-readable only');
check((fileperms($temporaryDir . '/question-secret.txt.lock') & 0777) === 0600, 'question secret lock must be owner-readable only');

$investigationLog = $temporaryDir . '/ai-investigations.jsonl';
$legacyQuestion = 'A raw legacy question that must be removed without losing its metadata.';
$legacyContext = 'Private legacy context that must also be removed.';
$expiredQuestion = 'An expired legacy question that must leave bounded operational history.';
$legacyRecords = [
    [
        'at_utc' => gmdate('c'),
        'ip_hash' => hash('sha256', 'legacy-reader'),
        'question' => $legacyQuestion,
        'context' => $legacyContext,
        'response_id' => $legacyQuestion,
        'model' => 'legacy-model',
        'input_tokens' => 123,
    ],
    [
        'at_utc' => gmdate('c', time() - (181 * 86400)),
        'ip_hash' => hash('sha256', 'expired-reader'),
        'question' => $expiredQuestion,
        'model' => 'expired-model',
    ],
];
$legacyBytes = implode("\n", array_map(
    static fn(array $record): string => json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    $legacyRecords
)) . "\n";
check(file_put_contents($investigationLog, $legacyBytes) === strlen($legacyBytes), 'legacy investigation log fixture could not be created');
check(chmod($investigationLog, 0640), 'legacy investigation log fixture permissions could not be set');
$investigationMigration = tw_migrate_ai_private_storage();
check(($investigationMigration['investigations'] ?? 0) === 1, 'deployment migration did not process the investigation log');
$migratedBytes = (string)file_get_contents($investigationLog);
check(!str_contains($migratedBytes, $legacyQuestion) && !str_contains($migratedBytes, $legacyContext), 'legacy raw question or context survived redaction');
check(!str_contains($migratedBytes, $expiredQuestion), 'expired investigation metadata survived bounded retention');
$migratedLines = array_values(array_filter(explode("\n", trim($migratedBytes))));
check(count($migratedLines) === 1, 'investigation retention or migration produced the wrong record count');
$migratedRecord = json_decode($migratedLines[0], true, 64, JSON_THROW_ON_ERROR);
check(($migratedRecord['model'] ?? '') === 'legacy-model' && ($migratedRecord['input_tokens'] ?? 0) === 123, 'legacy non-sensitive investigation metadata was not preserved');
check(($migratedRecord['question_hash'] ?? '') === hash_hmac('sha256', 'question|' . $legacyQuestion, $secret), 'legacy question was not replaced with the keyed digest');
check(($migratedRecord['question_characters'] ?? -1) === tw_ai_utf8_length($legacyQuestion), 'legacy question length was not preserved');
check(($migratedRecord['context_hash'] ?? '') === hash_hmac('sha256', 'context|' . $legacyContext, $secret), 'legacy context was not replaced with the keyed digest');
check(($migratedRecord['context_characters'] ?? -1) === tw_ai_utf8_length($legacyContext), 'legacy context length was not preserved');
check(!isset($migratedRecord['response_id']) && ($migratedRecord['response_id_hash'] ?? '') === hash_hmac('sha256', 'response-id|' . $legacyQuestion, $secret), 'legacy provider response ID was not replaced with a keyed digest');
check((fileperms($investigationLog) & 0777) === 0600, 'investigation log was not migrated to owner-only mode');
check((fileperms($investigationLog . '.lock') & 0777) === 0600, 'investigation log lock is not owner-only');
if (function_exists('symlink')) {
    expect_ai_symlink_rejection($investigationLog, static fn() => tw_prepare_ai_investigation_log($secret), 'investigation log');
    expect_ai_symlink_rejection($investigationLog . '.lock', static fn() => tw_prepare_ai_investigation_log($secret), 'investigation log lock');
}

$newQuestionText = 'new normalized question';
tw_append_ai_investigation_record([
    'at_utc' => gmdate('c'),
    'ip_hash' => hash('sha256', 'new-reader'),
    'question_hash' => hash_hmac('sha256', 'question|' . $newQuestionText, $secret),
    'question_characters' => tw_ai_utf8_length($newQuestionText),
    'model' => 'new-model',
    'response_id' => $newQuestionText,
], $secret);
$newInvestigationBytes = (string)file_get_contents($investigationLog);
check(substr_count($newInvestigationBytes, "\n") === 2 && !str_contains($newInvestigationBytes, $newQuestionText), 'new investigation metadata was not appended through the atomic redacting log');
check(str_contains($newInvestigationBytes, hash_hmac('sha256', 'response-id|' . $newQuestionText, $secret)), 'new provider response ID was not keyed before persistence');
check(tw_ai_safe_response_id(['malformed']) === '' && tw_ai_safe_response_id('question echoed by provider') === '', 'malformed provider response IDs must not reach telemetry');
check(tw_ai_bounded_nonnegative_int(['malformed']) === 0 && tw_ai_bounded_nonnegative_int(-1) === 0 && tw_ai_bounded_nonnegative_int(100000001) === 0, 'malformed provider usage fields must normalize without scalar conversion warnings');

$diagnosticLog = $temporaryDir . '/openai-errors.jsonl';
$legacyDiagnosticMessage = 'Legacy provider message that could reflect private prompt text.';
$legacyCurlMessage = 'Legacy transport detail.';
$diagnosticFixture = json_encode([
    'at_utc' => gmdate('c'),
    'http_status' => 500,
    'error_type' => 'legacy_error',
    'error_message' => $legacyDiagnosticMessage,
    'curl_error' => $legacyCurlMessage,
    'nested' => ['question' => 'nested raw diagnostic question', 'safe' => 'keep-nested-metadata'],
    'legacy_metadata' => 'preserve-me',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
check(file_put_contents($diagnosticLog, $diagnosticFixture) === strlen($diagnosticFixture), 'legacy diagnostic fixture could not be created');
check(chmod($diagnosticLog, 0640), 'legacy diagnostic fixture permissions could not be set');
$diagnosticMigration = tw_migrate_ai_private_storage();
check(($diagnosticMigration['investigations'] ?? 0) === 1 && ($diagnosticMigration['diagnostics'] ?? 0) === 1, 'deployment migration did not process both AI logs');
$rawDiagnosticInput = 'do not retain this private diagnostic text';
check(tw_log_openai_diagnostic(429, ['error' => ['type' => $rawDiagnosticInput, 'code' => $rawDiagnosticInput, 'message' => $rawDiagnosticInput]], $rawDiagnosticInput, 7, $secret), 'secure diagnostic append failed');
$diagnosticBytes = (string)file_get_contents($diagnosticLog);
check(!str_contains($diagnosticBytes, $legacyDiagnosticMessage) && !str_contains($diagnosticBytes, $legacyCurlMessage), 'legacy raw diagnostic text survived migration');
check(!str_contains($diagnosticBytes, $rawDiagnosticInput) && !str_contains($diagnosticBytes, 'nested raw diagnostic question'), 'raw user content entered the operational diagnostic log');
check(str_contains($diagnosticBytes, 'preserve-me') && str_contains($diagnosticBytes, 'keep-nested-metadata'), 'legacy non-sensitive diagnostic metadata was lost');
$diagnosticLines = array_values(array_filter(explode("\n", trim($diagnosticBytes))));
$newDiagnostic = json_decode((string)end($diagnosticLines), true, 64, JSON_THROW_ON_ERROR);
check(($newDiagnostic['error_type'] ?? '') === 'other' && ($newDiagnostic['error_code'] ?? '') === 'other', 'provider diagnostic identifiers were not bounded to safe classifications');
check(($newDiagnostic['curl_error_code'] ?? -1) === 7, 'safe cURL classification was not retained');
check(($newDiagnostic['provider_message_hash'] ?? '') === hash_hmac('sha256', 'error_message|' . $rawDiagnosticInput, $secret), 'provider diagnostic text was not replaced by its keyed digest');
check(($newDiagnostic['curl_error_hash'] ?? '') === hash_hmac('sha256', 'curl_error|' . $rawDiagnosticInput, $secret), 'transport diagnostic text was not replaced by its keyed digest');
check((fileperms($diagnosticLog) & 0777) === 0600, 'diagnostic log is not owner-only');
check((fileperms($diagnosticLog . '.lock') & 0777) === 0600, 'diagnostic log lock is not owner-only');
check(file_put_contents($keyPath, "read-only-health-key\n", LOCK_EX) !== false && chmod($keyPath, 0600), 'read-only health key fixture could not be created');
$beforeReadiness = private_storage_snapshot($temporaryDir);
check(tw_openai_key_readonly() === 'read-only-health-key' && tw_question_secret_readonly() === $secret, 'read-only health secret readers rejected valid owner-only files');
check(tw_ai_private_storage_ready($secret), 'healthy private AI storage did not satisfy readiness validation');
$afterReadiness = private_storage_snapshot($temporaryDir);
check($afterReadiness === $beforeReadiness, 'unauthenticated readiness validation created, chmodded, or rewrote private storage');
check(chmod($keyPath, 0640), 'legacy-mode health key fixture could not be prepared');
$beforeLooseModeRead = private_storage_snapshot($temporaryDir);
check(tw_openai_key_readonly() === '', 'read-only key check accepted a non-owner-only key');
check(private_storage_snapshot($temporaryDir) === $beforeLooseModeRead, 'read-only key check normalized permissions');
check(chmod($keyPath, 0600), 'health key fixture mode could not be restored');
$questionSecretPath = $temporaryDir . '/question-secret.txt';
$questionSecretBackup = $questionSecretPath . '.readiness-fixture';
check(rename($questionSecretPath, $questionSecretBackup), 'question secret readiness fixture could not be moved');
$beforeMissingSecretRead = private_storage_snapshot($temporaryDir);
check(tw_question_secret_readonly() === '', 'missing question secret was treated as ready');
check(private_storage_snapshot($temporaryDir) === $beforeMissingSecretRead, 'read-only question-secret check created private state');
check(rename($questionSecretBackup, $questionSecretPath), 'question secret readiness fixture could not be restored');
if (function_exists('symlink')) {
    expect_ai_symlink_rejection($diagnosticLog, static fn() => tw_prepare_ai_diagnostic_log($secret), 'diagnostic log');
    expect_ai_symlink_rejection($diagnosticLog . '.lock', static fn() => tw_prepare_ai_diagnostic_log($secret), 'diagnostic log lock');
}
$boundedDiagnosticRecords = [];
for ($index = 0; $index < 1100; $index++) {
    $boundedDiagnosticRecords[] = json_encode([
        'at_utc' => gmdate('c'),
        'http_status' => 500,
        'error_type' => 'server_error',
        'error_code' => 'server_error',
        'sequence' => $index,
        'legacy_metadata' => str_repeat('x', 1500),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
$boundedDiagnosticBytes = implode("\n", $boundedDiagnosticRecords) . "\n";
check(file_put_contents($diagnosticLog, $boundedDiagnosticBytes, LOCK_EX) === strlen($boundedDiagnosticBytes), 'bounded diagnostic fixture could not be created');
tw_prepare_ai_diagnostic_log($secret);
$boundedDiagnosticBytes = (string)file_get_contents($diagnosticLog);
$boundedDiagnosticLines = array_values(array_filter(explode("\n", trim($boundedDiagnosticBytes))));
check(strlen($boundedDiagnosticBytes) <= 1048576 && count($boundedDiagnosticLines) <= 1000, 'diagnostic retention exceeded its byte or record cap');
check(str_contains((string)end($boundedDiagnosticLines), '"sequence":1099') && !str_contains($boundedDiagnosticBytes, '"sequence":0,'), 'bounded diagnostic retention did not preserve the newest records');
$corruptDiagnostic = "{broken-diagnostic\n";
check(file_put_contents($diagnosticLog, $corruptDiagnostic, LOCK_EX) === strlen($corruptDiagnostic), 'corrupt diagnostic fixture could not be created');
check(!tw_log_openai_diagnostic(500, [], '', 0, $secret), 'corrupt diagnostic log did not fail closed');
check(file_get_contents($diagnosticLog) === $corruptDiagnostic, 'corrupt diagnostic log was reset or overwritten');
check(!tw_ai_private_storage_ready($secret), 'corrupt private AI storage was reported ready');

$corruptInvestigation = "{broken-json\n";
check(file_put_contents($investigationLog, $corruptInvestigation, LOCK_EX) === strlen($corruptInvestigation), 'corrupt investigation fixture could not be created');
expect_failure(static fn() => tw_prepare_ai_investigation_log($secret), 'corrupted');
check(file_get_contents($investigationLog) === $corruptInvestigation, 'corrupt investigation log was reset or overwritten');
$invalidTimestampInvestigation = json_encode([
    'at_utc' => 'yesterday',
    'question_hash' => hash_hmac('sha256', 'question|legacy', $secret),
    'question_characters' => 6,
], JSON_THROW_ON_ERROR) . "\n";
check(file_put_contents($investigationLog, $invalidTimestampInvestigation, LOCK_EX) === strlen($invalidTimestampInvestigation), 'invalid-timestamp investigation fixture could not be created');
expect_failure(static fn() => tw_prepare_ai_investigation_log($secret), 'invalid timestamp');
check(file_get_contents($investigationLog) === $invalidTimestampInvestigation, 'invalid-timestamp investigation log was reset or overwritten');
$whitespaceInvestigation = " \n\t\n";
check(file_put_contents($investigationLog, $whitespaceInvestigation, LOCK_EX) === strlen($whitespaceInvestigation), 'whitespace investigation fixture could not be created');
expect_failure(static fn() => tw_prepare_ai_investigation_log($secret), 'no valid records');
check(file_get_contents($investigationLog) === $whitespaceInvestigation, 'whitespace-only investigation log was reset or overwritten');

$legacyUsageTemporary = $temporaryDir . '/.ai-usage-ABC123';
$freshLegacyUsageTemporary = $temporaryDir . '/.ai-usage-ZYX987';
$orphanedLogTemporary = $temporaryDir . '/openai-errors.jsonl.tmp-' . str_repeat('a', 24);
$unrelatedUsageBackup = $temporaryDir . '/ai-usage-notes.json.backup';
$expiredUsage = $temporaryDir . '/ai-usage-' . gmdate('Y-m-d', time() - (60 * 86400)) . '.json';
$expiredUsageLock = $expiredUsage . '.lock';
check(file_put_contents($legacyUsageTemporary, 'legacy quota bytes') !== false, 'legacy usage temporary fixture could not be created');
check(file_put_contents($freshLegacyUsageTemporary, 'possible live writer') !== false, 'fresh legacy usage temporary fixture could not be created');
check(file_put_contents($orphanedLogTemporary, 'orphaned atomic bytes') !== false, 'orphaned log temporary fixture could not be created');
check(file_put_contents($unrelatedUsageBackup, 'unrelated file') !== false, 'unrelated usage-named fixture could not be created');
check(chmod($unrelatedUsageBackup, 0644), 'unrelated usage-named fixture mode could not be set');
check(file_put_contents($expiredUsage, "{}\n") !== false && file_put_contents($expiredUsageLock, '') !== false, 'expired usage fixtures could not be created');
check(chmod($legacyUsageTemporary, 0640) && chmod($expiredUsage, 0640) && chmod($expiredUsageLock, 0640), 'legacy usage fixture permissions could not be set');
check(touch($legacyUsageTemporary, time() - 172800), 'legacy usage temporary fixture age could not be set');
check(touch($orphanedLogTemporary, time() - 172800), 'orphaned log temporary fixture age could not be set');
check(tw_maintain_ai_usage_files(), 'usage-ledger retention maintenance failed');
check(!file_exists($legacyUsageTemporary) && !file_exists($orphanedLogTemporary) && !file_exists($expiredUsage) && !file_exists($expiredUsageLock), 'expired or abandoned AI files survived bounded retention');
check(file_exists($freshLegacyUsageTemporary) && (fileperms($freshLegacyUsageTemporary) & 0777) === 0600, 'possible live legacy writer was deleted instead of secured');
check(file_get_contents($unrelatedUsageBackup) === 'unrelated file' && (fileperms($unrelatedUsageBackup) & 0777) === 0644, 'unrecognized usage-named file was mutated or removed');
check(unlink($freshLegacyUsageTemporary), 'fresh legacy usage fixture could not be removed');

$usageFile = tw_usage_file();
$reservationIp = hash('sha256', 'reservation-shape');
$reservationToken = hash('sha256', 'reservation-token');
$validUsageFixture = [
    'version' => 2,
    'total' => 1,
    'ips' => [$reservationIp => 1],
    'reservations' => [$reservationToken => ['ip_hash' => $reservationIp, 'at_utc' => gmdate('c')]],
];
$invalidReservationFixtures = [];
$withExtraField = $validUsageFixture;
$withExtraField['reservations'][$reservationToken]['unexpected'] = true;
$invalidReservationFixtures[] = $withExtraField;
$withRelativeTime = $validUsageFixture;
$withRelativeTime['reservations'][$reservationToken]['at_utc'] = 'yesterday';
$invalidReservationFixtures[] = $withRelativeTime;
$withPriorDay = $validUsageFixture;
$withPriorDay['reservations'][$reservationToken]['at_utc'] = gmdate('c', time() - 86400);
$invalidReservationFixtures[] = $withPriorDay;
$withFutureTime = $validUsageFixture;
$withFutureTime['reservations'][$reservationToken]['at_utc'] = gmdate('c', time() + 600);
$invalidReservationFixtures[] = $withFutureTime;
foreach ($invalidReservationFixtures as $fixture) {
    $bytes = json_encode($fixture, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    check(file_put_contents($usageFile, $bytes, LOCK_EX) === strlen($bytes), 'invalid reservation fixture could not be created');
    check(chmod($usageFile, 0600), 'invalid reservation fixture mode could not be secured');
    [$usageOk] = tw_read_usage_file($usageFile);
    check(!$usageOk, 'malformed or out-of-bounds reservation metadata was accepted');
}
file_put_contents($usageFile, '{broken-json');
[$allowed, , , $rateStatus] = tw_rate_limit(hash('sha256', 'corrupt'));
check(!$allowed && $rateStatus === 'storage_unavailable' && file_get_contents($usageFile) === '{broken-json', 'corrupt quota storage must fail closed as unavailable without being overwritten');
check((fileperms($usageFile) & 0777) === 0600, 'legacy usage ledger was not migrated to owner-only mode before validation');
check((fileperms($usageFile . '.lock') & 0777) === 0600, 'usage lock is not owner-only');
unlink($usageFile);

$ipHash = hash('sha256', 'one-reader');
[$allowed, $message, $reservation] = tw_rate_limit($ipHash);
check($allowed && $message === '' && preg_match('/^[a-f0-9]{64}$/', $reservation) === 1, 'quota reservation must be created');
check((fileperms($usageFile) & 0777) === 0600, 'usage ledger is not owner-only');
check(!tw_release_rate_limit(hash('sha256', 'different-reader'), $reservation), 'another reader must not release a reservation');
[$usageOk, $usage] = tw_read_usage_file($usageFile);
check($usageOk && $usage['total'] === 1 && $usage['ips'][$ipHash] === 1, 'failed foreign refund must leave counters intact');
check(tw_release_rate_limit($ipHash, $reservation), 'matching reservation must be refundable once');
check(!tw_release_rate_limit($ipHash, $reservation), 'reservation refund must not replay');
[$usageOk, $usage] = tw_read_usage_file($usageFile);
check($usageOk && $usage['total'] === 0 && $usage['ips'] === [], 'refund must atomically restore both counters');

[$allowed, , $reservation] = tw_rate_limit($ipHash);
check($allowed, 'successful request reservation must be available for finalization');
$offlineDir = $temporaryDir . '-offline';
check(rename($temporaryDir, $offlineDir), 'test storage could not be moved offline');
check(file_put_contents($temporaryDir, 'blocked') === 7, 'test storage blocker could not be created');
$finalizedWhileOffline = tw_finalize_rate_limit($ipHash, $reservation, true);
unlink($temporaryDir);
check(rename($offlineDir, $temporaryDir), 'test storage could not be restored');
check(!$finalizedWhileOffline, 'finalization must report a storage write failure');
[$usageOk, $usage] = tw_read_usage_file($usageFile);
check($usageOk && $usage['total'] === 1 && isset($usage['reservations'][$reservation]), 'failed finalization must preserve the charged reservation');
check(tw_finalize_rate_limit($ipHash, $reservation, true), 'successful request must consume and close its reservation after storage recovery');
check(!tw_release_rate_limit($ipHash, $reservation), 'committed usage must not be refundable');
[$usageOk, $usage] = tw_read_usage_file($usageFile);
check($usageOk && $usage['total'] === 1 && $usage['reservations'] === [], 'commit must preserve usage while removing pending reservation');

$preflightHash = hash('sha256', 'pre-provider-failure');
[$allowed, , $reservation] = tw_rate_limit($preflightHash);
check($allowed && tw_finalize_rate_limit($preflightHash, $reservation, false), 'pre-provider failure must release its reservation');
$providerHash = hash('sha256', 'provider-contract-failure');
[$allowed, , $reservation] = tw_rate_limit($providerHash);
check($allowed && tw_finalize_rate_limit($providerHash, $reservation, true), 'provider-attempted contract failure must consume its reservation');
[$usageOk, $usage] = tw_read_usage_file($usageFile);
check($usageOk && $usage['total'] === 2 && !isset($usage['ips'][$preflightHash]) && $usage['ips'][$providerHash] === 1, 'only provider-attempted failures may remain charged');
unlink($usageFile);

$quotaIpHash = hash('sha256', 'quota-reader');
for ($index = 0; $index < 3; $index++) {
    [$allowed, , $reservation, $rateStatus] = tw_rate_limit($quotaIpHash);
    check($allowed && $rateStatus === 'reserved' && tw_commit_rate_limit($quotaIpHash, $reservation), 'quota fixture could not reserve and commit usage');
}
[$allowed, $message, $reservation, $rateStatus] = tw_rate_limit($quotaIpHash);
check(!$allowed && $rateStatus === 'allowance_exhausted' && $reservation === '' && str_contains($message, 'allowance'), 'genuine per-IP exhaustion must be distinguishable from storage failure');
unlink($usageFile);

if (function_exists('proc_open')) {
    $processes = [];
    for ($index = 0; $index < 24; $index++) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, __FILE__, 'worker', (string)$index],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['TW_TEST_PRIVATE_DIR' => $temporaryDir]
        );
        check(is_resource($process), 'concurrent quota worker could not start');
        $processes[] = [$process, $pipes];
    }
    $allowedWorkers = 0;
    foreach ($processes as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        check($exit === 0 && $error === '', 'concurrent quota worker failed');
        if ($output === '1') $allowedWorkers++;
    }
    check($allowedWorkers === 20, 'atomic daily quota must admit exactly the configured cap');
    [$usageOk, $usage] = tw_read_usage_file($usageFile);
    check($usageOk && $usage['total'] === 20 && count($usage['ips']) === 20, 'concurrent counters must remain valid and complete');
}

echo "Public Truth Trial backend hardening checks passed.\n";
