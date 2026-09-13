<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$testRoot = sys_get_temp_dir() . '/tw-daily-hardening-' . bin2hex(random_bytes(8));
$privateDir = $testRoot . '/private';
$publicDir = $testRoot . '/public';
if (!mkdir($privateDir, 0700, true) || !mkdir($publicDir, 0755, true)) {
    throw new RuntimeException('Unable to create Daily Desk test directories.');
}
define('TW_DAILY_TEST_PRIVATE_DIR', $privateDir);
define('TW_DAILY_TEST_PUBLIC_DIR', $publicDir);

function remove_test_tree(string $path, string $root): void {
    if (!str_starts_with($path, $root) || (!file_exists($path) && !is_link($path))) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            remove_test_tree($path . '/' . $name, $root);
        }
        rmdir($path);
    } else {
        unlink($path);
    }
}
register_shutdown_function(static function () use ($testRoot): void {
    remove_test_tree($testRoot, $testRoot);
});

require $root . '/truth/daily/compat.php';
require $root . '/truth/daily/lib.php';
require $root . '/truth/daily/legacy-migration.php';
require $root . '/truth/daily/meat-desk.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function expect_failure(callable $callback, string $contains): void {
    try {
        $callback();
    } catch (Throwable $error) {
        check(str_contains($error->getMessage(), $contains), 'Unexpected failure: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected failure containing: ' . $contains);
}

$_POST['array-shaped'] = ['not', 'scalar'];
check(tw_post_scalar('array-shaped', 100) === null, 'Array-shaped POST value was treated as scalar.');
unset($_POST['array-shaped']);

$savedServer = $_SERVER;
$_SERVER = [
    'HTTP_HOST' => 'bobsome1.com',
    'HTTP_ORIGIN' => 'https://bobsome1.com',
    'HTTP_SEC_FETCH_SITE' => 'same-origin',
];
check(tw_daily_request_origin_is_allowed(), 'Exact apex same-origin request was rejected.');
$_SERVER['HTTP_HOST'] = 'bobsome1.com:443';
$_SERVER['HTTP_ORIGIN'] = 'https://bobsome1.com:443/';
check(tw_daily_request_origin_is_allowed(), 'Explicit default HTTPS port was rejected.');
$_SERVER['HTTP_HOST'] = 'bobsome1.com:444';
check(!tw_daily_request_origin_is_allowed(), 'Non-default request Host port was accepted.');
$_SERVER['HTTP_HOST'] = 'bobsome1.com';
$_SERVER['HTTP_ORIGIN'] = 'https://www.bobsome1.com';
check(!tw_daily_request_origin_is_allowed(), 'Cross-host apex/www Origin was accepted as same-origin.');
$_SERVER['HTTP_ORIGIN'] = 'https://bobsome1.com:444';
check(!tw_daily_request_origin_is_allowed(), 'Non-default Origin port was accepted.');
$_SERVER['HTTP_ORIGIN'] = 'https://user:pass@bobsome1.com';
check(!tw_daily_request_origin_is_allowed(), 'Credentialed Origin was accepted.');
$_SERVER['HTTP_ORIGIN'] = 'https://bobsome1.com/?query=not-origin';
check(!tw_daily_request_origin_is_allowed(), 'Origin with query data was accepted.');
unset($_SERVER['HTTP_ORIGIN']);
$_SERVER['HTTP_REFERER'] = 'https://bobsome1.com/truth/daily/desk.php?view=owner';
check(tw_daily_request_origin_is_allowed(), 'Same-origin owner Referer was rejected.');
$_SERVER['HTTP_REFERER'] = 'https://www.bobsome1.com/truth/daily/desk.php';
check(!tw_daily_request_origin_is_allowed(), 'Cross-host apex/www Referer was accepted.');
$_SERVER = $savedServer;

$bootstrap = tw_admin_bootstrap_create(600);
check((bool)preg_match('/^[a-f0-9]{64}$/', $bootstrap), 'Bootstrap format was not owner-generated.');
check(tw_admin_bootstrap_consume($bootstrap), 'Fresh bootstrap was not accepted.');
check(!tw_admin_bootstrap_consume($bootstrap), 'Bootstrap was reusable.');
check((fileperms($privateDir . '/daily-admin-bootstraps.json') & 0777) === 0600, 'Bootstrap ledger is not owner-only.');
$bootstrapLedgerPath = $privateDir . '/daily-admin-bootstraps.json';
tw_json_write($bootstrapLedgerPath, [
    'bootstraps' => [
        str_repeat('a', 64) => [
            'created_at_utc' => gmdate('c'),
            'expires_at' => 'not-an-integer',
        ],
    ],
]);
$corruptBootstrapBytes = file_get_contents($bootstrapLedgerPath);
expect_failure(static fn() => tw_admin_bootstrap_create(), 'corrupted');
check(file_get_contents($bootstrapLedgerPath) === $corruptBootstrapBytes, 'Bootstrap creation overwrote a corrupt ledger.');
expect_failure(static fn() => tw_admin_bootstrap_consume(str_repeat('b', 64)), 'corrupted');
check(file_get_contents($bootstrapLedgerPath) === $corruptBootstrapBytes, 'Bootstrap consumption overwrote a corrupt ledger.');
tw_json_write($bootstrapLedgerPath, ['bootstraps' => []]);

$keyEnvironmentNames = ['TRUST_WORTHY_OPENAI_API_KEY', 'OPENAI_API_KEY', 'INSIDE_OF_ME_OPENAI_API_KEY'];
$savedKeyEnvironment = [];
foreach ($keyEnvironmentNames as $name) {
    $savedKeyEnvironment[$name] = getenv($name);
    putenv($name);
}
$outsideKeyPath = $testRoot . '/outside-openai-key.txt';
check(file_put_contents($outsideKeyPath, 'outside-test-credential') !== false, 'Unable to prepare key symlink fixture.');
check(symlink($outsideKeyPath, $privateDir . '/openai-key.txt'), 'Unable to create key symlink fixture.');
putenv('TRUST_WORTHY_OPENAI_API_KEY=environment-test-credential');
expect_failure(static fn() => tw_openai_key(), 'secure regular file');
check(unlink($privateDir . '/openai-key.txt'), 'Unable to clear key symlink fixture.');
check(file_put_contents($privateDir . '/openai-key.txt', 'fallback-test-credential') !== false, 'Unable to prepare key file fixture.');
chmod($privateDir . '/openai-key.txt', 0644);
check(tw_openai_key() === 'environment-test-credential', 'Documented environment key did not take precedence.');
check((fileperms($privateDir . '/openai-key.txt') & 0777) === 0600, 'Fallback key file was not secured before environment-key use.');
putenv('TRUST_WORTHY_OPENAI_API_KEY');
check(tw_openai_key() === 'fallback-test-credential', 'Safe private key fallback was not loaded.');
check(file_put_contents($privateDir . '/openai-key.txt', str_repeat('x', 8193)) !== false, 'Unable to prepare oversized key fixture.');
expect_failure(static fn() => tw_openai_key(), 'invalid size');
check(unlink($privateDir . '/openai-key.txt'), 'Unable to clear key file fixture.');
foreach ($savedKeyEnvironment as $name => $value) {
    if ($value === false) {
        putenv($name);
    } else {
        putenv($name . '=' . $value);
    }
}

$outsideJsonPath = $testRoot . '/outside-private-json.json';
check(file_put_contents($outsideJsonPath, "{\"outside\":true}\n") !== false, 'Unable to prepare JSON symlink fixture.');
chmod($outsideJsonPath, 0644);
check(symlink($outsideJsonPath, $privateDir . '/unsafe-private.json'), 'Unable to create JSON symlink fixture.');
expect_failure(
    static fn() => tw_private_json_read_strict($privateDir . '/unsafe-private.json'),
    'symlink'
);
check(file_get_contents($outsideJsonPath) === "{\"outside\":true}\n", 'Safe JSON rejection changed the outside file.');
check((fileperms($outsideJsonPath) & 0777) === 0644, 'Safe JSON rejection changed outside permissions.');
check(unlink($privateDir . '/unsafe-private.json'), 'Unable to clear JSON symlink fixture.');

$outsideLockPath = $testRoot . '/outside-lock.txt';
check(file_put_contents($outsideLockPath, 'outside-lock-sentinel') !== false, 'Unable to prepare lock symlink fixture.');
chmod($outsideLockPath, 0644);
$locksDir = $privateDir . '/locks';
check(is_dir($locksDir), 'Bootstrap flow did not create private lock storage.');
check(symlink($outsideLockPath, $locksDir . '/unsafe-lock.lock'), 'Unable to create lock symlink fixture.');
expect_failure(
    static fn() => tw_with_private_lock('unsafe-lock', static fn(): bool => true),
    'symlink'
);
check(file_get_contents($outsideLockPath) === 'outside-lock-sentinel', 'Unsafe lock rejection changed the outside file.');
check((fileperms($outsideLockPath) & 0777) === 0644, 'Unsafe lock rejection changed outside permissions.');
check(unlink($locksDir . '/unsafe-lock.lock'), 'Unable to clear lock symlink fixture.');

$outsideDirectory = $testRoot . '/outside-private-directory';
check(mkdir($outsideDirectory, 0700), 'Unable to prepare private-directory symlink fixture.');
check(symlink($outsideDirectory, $testRoot . '/private-directory-link'), 'Unable to create private-directory symlink fixture.');
expect_failure(
    static fn() => tw_daily_prepare_directory($testRoot . '/private-directory-link', 0700, 'Test private directory'),
    'symlink'
);

$providerResponse = [
    'status' => 'completed',
    'output' => [
        [
            'type' => 'web_search_call',
            'action' => [
                'type' => 'search',
                'sources' => [
                    ['type' => 'url', 'url' => 'https://www.nasa.gov/one?utm_source=test', 'title' => 'Provider One'],
                    ['type' => 'url', 'url' => 'https://www.archives.gov/two', 'title' => 'Provider Two'],
                ],
            ],
        ],
        [
            'type' => 'message',
            'content' => [[
                'type' => 'output_text',
                'annotations' => [[
                    'type' => 'url_citation',
                    'url' => 'https://www.nasa.gov/one',
                    'title' => 'Cited One',
                ]],
            ]],
        ],
    ],
];
$providerSources = tw_provider_evidence_sources($providerResponse);
check(count($providerSources) === 2, 'Provider evidence sources were not normalized and deduplicated.');
$providerContract = tw_validate_provider_response_contract($providerResponse, ['max_web_search_calls' => 1]);
check($providerContract['web_search_calls'] === 1, 'Completed provider contract did not count its search.');
$safeProviderMetadata = tw_daily_provider_private_metadata([
    'id' => 'resp_safe-Provider_123',
    'usage' => ['input_tokens' => 41, 'output_tokens' => 59, 'total_tokens' => 100],
]);
check(($safeProviderMetadata['provider_response_id_sha256'] ?? '') === hash('sha256', 'resp_safe-Provider_123'), 'Provider response ID fingerprint was not stored safely.');
check(($safeProviderMetadata['provider_response_id_bytes'] ?? null) === strlen('resp_safe-Provider_123'), 'Provider response ID byte count was not bounded metadata.');
check($safeProviderMetadata['provider_usage'] === ['input_tokens' => 41, 'output_tokens' => 59, 'total_tokens' => 100], 'Safe provider usage summary changed valid counters.');
$providerPromptEcho = 'private question and context must never survive here';
$unsafeProviderMetadata = tw_daily_provider_private_metadata([
    'id' => "resp_valid-prefix\n{$providerPromptEcho}",
    'usage' => [
        'input_tokens' => $providerPromptEcho,
        'output_tokens' => -1,
        'total_tokens' => 7,
        'input_tokens_details' => ['echo' => $providerPromptEcho],
        'arbitrary' => $providerPromptEcho,
    ],
]);
$unsafeProviderMetadataJson = json_encode($unsafeProviderMetadata, JSON_THROW_ON_ERROR);
check(!isset($unsafeProviderMetadata['provider_response_id']), 'Raw prompt-like provider response ID survived metadata reduction.');
check($unsafeProviderMetadata['provider_usage'] === ['total_tokens' => 7], 'Provider usage metadata was not reduced to bounded nonnegative integer counters.');
check(!str_contains($unsafeProviderMetadataJson, $providerPromptEcho), 'Prompt-like provider metadata survived into draft state.');
$capturedProviderBody = '123';
$providerBodyOverflow = false;
check(tw_daily_capture_provider_chunk($capturedProviderBody, $providerBodyOverflow, '45', 5) === 2, 'Bounded provider response capture rejected an in-limit chunk.');
check($capturedProviderBody === '12345' && !$providerBodyOverflow, 'Bounded provider response capture corrupted an in-limit body.');
check(tw_daily_capture_provider_chunk($capturedProviderBody, $providerBodyOverflow, '6', 5) === 0, 'Oversized provider response chunk was accepted.');
check($capturedProviderBody === '12345' && $providerBodyOverflow, 'Oversized provider response changed the retained bounded body.');
$incompleteResponse = $providerResponse;
$incompleteResponse['status'] = 'incomplete';
expect_failure(
    static fn() => tw_validate_provider_response_contract($incompleteResponse, ['max_web_search_calls' => 1]),
    'did not complete'
);
$tooManySearches = $providerResponse;
$tooManySearches['output'][] = $providerResponse['output'][0];
expect_failure(
    static fn() => tw_validate_provider_response_contract($tooManySearches, ['max_web_search_calls' => 1]),
    'exceeded'
);
check(tw_provider_url_key('https://user:pass@www.nasa.gov/private') === null, 'Credentialed provider URL was accepted.');
check(tw_provider_url_key('http://127.0.0.1/private') === null, 'Loopback provider URL was accepted.');
check(tw_provider_url_key('http://10.0.0.1/private') === null, 'Private-address provider URL was accepted.');

$report = [
    'headline' => 'Test headline',
    'claim' => 'Test claim',
    'summary' => 'Test summary',
    'proven' => ['One supported point.'],
    'strongly_indicated' => ['One indication.'],
    'contradictions_missing_pieces' => ['One missing piece.'],
    'motives_incentives_who_benefits' => ['One incentive.'],
    'logic_common_sense' => ['One logic check.'],
    'counterevidence_alternatives' => ['One counterpoint.'],
    'unknowns' => ['One unknown.'],
    'bobinated_opinion' => ['opinion' => 'Explicit opinion.', 'reasoning' => ['One reason.']],
    'sources' => [
        ['title' => 'Model One', 'url' => 'https://www.nasa.gov/one', 'role' => 'Primary record.'],
        ['title' => 'Model Two', 'url' => 'https://www.archives.gov/two', 'role' => 'Independent context.'],
    ],
    'what_would_change_the_finding' => ['A stronger primary record.'],
    'confidence' => 'medium',
];
$validated = tw_validate_daily_report($report, $providerSources, 1);
check($validated['sources'][0]['title'] === 'Cited One', 'Provider citation title did not override model metadata.');

$fabricated = $report;
$fabricated['sources'][1]['url'] = 'https://www.loc.gov/not-returned';
expect_failure(
    static fn() => tw_validate_daily_report($fabricated, $providerSources, 1),
    'not returned by provider evidence'
);

$validated['_meta'] = [
    'candidate' => ['candidate_type' => 'meat-desk', 'source' => 'Trust-Worthy Meat Desk'],
    'provider_sources' => array_values($providerSources),
    'provider_response_id' => 'private-provider-id',
    'provider_usage' => ['total_tokens' => 123],
    'model' => 'private-model-name',
];

$legacyDraft = $validated;
$legacyDraft['_meta'] = [
    'candidate' => [
        'candidate_type' => 'meat-desk',
        'source' => 'Trust-Worthy Meat Desk',
        'title' => 'Preserve this owner draft without making it publishable.',
        'timestamp' => time(),
    ],
    'model' => 'legacy-private-model',
    'provider_response_id' => 'resp_' . $providerPromptEcho,
    'provider_usage' => ['nested' => ['echo' => $providerPromptEcho]],
];
$legacyDraftRaw = json_encode($legacyDraft, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$legacyDraftId = substr(hash('sha256', "release-16-daily-draft\0" . $legacyDraftRaw), 0, 32);
$legacyErrorSecret = 'raw-provider-echo-must-disappear';
$legacyTokenSecret = 'reusable-admin-token-must-disappear';
check(file_put_contents($privateDir . '/daily-last-error.json', json_encode(['message' => $legacyErrorSecret]) . "\n") !== false, 'Unable to prepare legacy error fixture.');
check(file_put_contents($privateDir . '/daily-draft.json', $legacyDraftRaw) !== false, 'Unable to prepare legacy draft fixture.');
check(file_put_contents($privateDir . '/daily-admin-token.txt', $legacyTokenSecret) !== false, 'Unable to prepare legacy token fixture.');
chmod($privateDir . '/daily-last-error.json', 0644);
chmod($privateDir . '/daily-draft.json', 0644);
chmod($privateDir . '/daily-admin-token.txt', 0644);
$legacyResult = tw_daily_retire_legacy_private_artifacts($privateDir);
check($legacyResult['retired'] === 3, 'Legacy migration did not retire every exact sensitive artifact.');
check($legacyResult['migrated_drafts'] === 1, 'Legacy migration did not preserve the valid owner draft.');
foreach (['daily-last-error.json', 'daily-draft.json', 'daily-admin-token.txt'] as $legacyName) {
    check(!tw_daily_legacy_path_exists($privateDir . '/' . $legacyName), 'Legacy exact path survived migration: ' . $legacyName);
}
$preservedLegacyPath = $privateDir . '/daily-drafts/' . $legacyDraftId . '.json';
check(is_file($preservedLegacyPath), 'Preserved legacy draft is missing from identified owner-only storage.');
$preservedLegacy = tw_private_json_read_strict($preservedLegacyPath, [], 2097152);
check(($preservedLegacy['_meta']['publication_status'] ?? '') === 'legacy-unverified-draft', 'Legacy provider-unbound draft became publishable.');
check(!isset($preservedLegacy['_meta']['provider_response_id'], $preservedLegacy['_meta']['provider_usage']), 'Raw legacy provider metadata survived draft migration.');
check((fileperms($preservedLegacyPath) & 0777) === 0600, 'Preserved legacy draft is not owner-only.');
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($privateDir, FilesystemIterator::SKIP_DOTS)) as $privateFile) {
    if (!$privateFile->isFile() || $privateFile->isLink()) continue;
    $privateBytes = file_get_contents($privateFile->getPathname());
    check(is_string($privateBytes), 'Unable to inspect migrated private fixture.');
    check(!str_contains($privateBytes, $legacyErrorSecret), 'Raw provider error survived legacy retirement.');
    check(!str_contains($privateBytes, $legacyTokenSecret), 'Reusable admin credential survived legacy retirement.');
}

$legacyOutside = $testRoot . '/outside-legacy-token.txt';
check(file_put_contents($legacyOutside, 'outside-legacy-sentinel') !== false, 'Unable to prepare legacy symlink target.');
chmod($legacyOutside, 0644);
$legacyPreservedError = "{\"message\":\"must-remain-on-preflight-failure\"}\n";
check(file_put_contents($privateDir . '/daily-last-error.json', $legacyPreservedError) !== false, 'Unable to prepare legacy rollback fixture.');
check(symlink($legacyOutside, $privateDir . '/daily-admin-token.txt'), 'Unable to prepare unsafe legacy credential fixture.');
expect_failure(
    static fn() => tw_daily_retire_legacy_private_artifacts(TW_DAILY_TEST_PRIVATE_DIR),
    'symlink'
);
check(file_get_contents($privateDir . '/daily-last-error.json') === $legacyPreservedError, 'Legacy preflight failure changed a valid original artifact.');
check(file_get_contents($legacyOutside) === 'outside-legacy-sentinel', 'Legacy preflight followed and changed an outside file.');
check((fileperms($legacyOutside) & 0777) === 0644, 'Legacy preflight changed outside-file permissions.');
check(unlink($privateDir . '/daily-last-error.json'), 'Unable to clear legacy rollback fixture.');
check(unlink($privateDir . '/daily-admin-token.txt'), 'Unable to clear legacy credential symlink fixture.');

$invalidLegacyRaw = "{not-json\n";
check(file_put_contents($privateDir . '/daily-draft.json', $invalidLegacyRaw) !== false, 'Unable to prepare corrupt legacy draft fixture.');
expect_failure(
    static fn() => tw_daily_retire_legacy_private_artifacts(TW_DAILY_TEST_PRIVATE_DIR),
    'corrupted'
);
check(file_get_contents($privateDir . '/daily-draft.json') === $invalidLegacyRaw, 'Corrupt legacy draft was erased instead of preserved.');
check(unlink($privateDir . '/daily-draft.json'), 'Unable to clear corrupt legacy draft fixture.');

$expiredLegacy = $legacyDraft;
$expiredLegacy['_meta']['candidate'] = [
    'candidate_type' => 'user-question',
    'source' => 'Private user question',
    'visibility' => 'private',
    'title' => 'Expired private legacy question',
    'summary' => 'Expired private legacy context',
    'timestamp' => time() - (181 * 86400),
];
check(file_put_contents(
    $privateDir . '/daily-draft.json',
    json_encode($expiredLegacy, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
) !== false, 'Unable to prepare expired private legacy draft fixture.');
$expiredLegacyResult = tw_daily_retire_legacy_private_artifacts($privateDir);
check($expiredLegacyResult['expired_or_invalid_drafts'] === 1, 'Expired private legacy draft was not classified for retirement.');
check($expiredLegacyResult['migrated_drafts'] === 0, 'Expired private legacy draft was migrated.');
check(!tw_daily_legacy_path_exists($privateDir . '/daily-draft.json'), 'Expired private legacy draft survived retirement.');

$outsideDraftPath = $testRoot . '/outside-draft.json';
check(file_put_contents($outsideDraftPath, "{\"outside_draft\":true}\n") !== false, 'Unable to prepare draft symlink target.');
chmod($outsideDraftPath, 0644);
$unsafeDraftLink = $privateDir . '/daily-drafts/' . str_repeat('f', 32) . '.json';
check(symlink($outsideDraftPath, $unsafeDraftLink), 'Unable to prepare unsafe private draft fixture.');
expect_failure(static fn() => tw_prune_expired_private_daily_drafts(), 'symlink');
check(file_get_contents($outsideDraftPath) === "{\"outside_draft\":true}\n", 'Draft pruning changed an outside file.');
check((fileperms($outsideDraftPath) & 0777) === 0644, 'Draft pruning changed outside-file permissions.');
check(unlink($unsafeDraftLink), 'Unable to clear unsafe private draft fixture.');

$draft = tw_store_daily_draft($validated);
$draftId = $draft['_meta']['draft_id'];
$draftDigest = tw_daily_draft_digest($draft);
check(!tw_daily_draft_is_private_question($draft), 'Public Meat Desk draft was marked private.');
$privateDraft = $draft;
$privateDraft['_meta']['candidate'] = [
    'candidate_type' => 'user-question',
    'source' => 'Private user question',
    'title' => 'never publish this',
    'summary' => 'private context',
];
check(tw_daily_draft_is_private_question($privateDraft), 'Private question draft was publishable.');

$publicationId = bin2hex(random_bytes(12));
$published = $validated;
unset($published['_meta']);
$published['_meta'] = [
    'publication_id' => $publicationId,
    'published_at_utc' => gmdate('c'),
    'reviewed_by_human' => true,
];
$transaction = [
    'version' => 1,
    'created_at_utc' => gmdate('c'),
    'draft_id' => $draftId,
    'original_draft_digest' => $draftDigest,
    'publication_id' => $publicationId,
    'archive_name' => 'trial-20260913-120000-' . $publicationId . '.json',
    'published' => $published,
];
tw_json_write($privateDir . '/daily-publish-transaction.json', $transaction);

expect_failure(
    static fn() => tw_recover_daily_publication_transaction(
        static function (array $ignoredDraft, string $ignoredPublication): void {
            throw new RuntimeException('injected private marker failure');
        }
    ),
    'injected private marker failure'
);
check(is_file($publicDir . '/latest.json'), 'Atomic latest publication was not fully written before injected marker failure.');
check(is_file($privateDir . '/daily-publish-transaction.json'), 'Recovery journal was lost after injected failure.');
check((tw_load_daily_draft($draftId)['_meta']['publication_status'] ?? '') === 'publishing', 'Draft did not retain recoverable publishing state.');

$recovered = tw_recover_daily_publication_transaction();
check(is_array($recovered), 'Pending publication did not recover.');
check(!is_file($privateDir . '/daily-publish-transaction.json'), 'Completed recovery journal was not cleared.');
check((tw_load_daily_draft($draftId)['_meta']['publication_status'] ?? '') === 'published', 'Recovered draft was not finalized.');
$live = tw_json_read_strict($publicDir . '/latest.json');
check(($live['_meta']['publication_id'] ?? '') === $publicationId, 'Recovered latest publication changed identity.');
check(!isset($live['_meta']['candidate'], $live['_meta']['provider_response_id'], $live['_meta']['provider_response_id_sha256'], $live['_meta']['provider_response_id_bytes'], $live['_meta']['provider_usage'], $live['_meta']['model'], $live['_meta']['draft_id']), 'Private metadata leaked into public JSON.');
check((fileperms($publicDir . '/latest.json') & 0777) === 0644, 'Public latest JSON permissions are not readable.');
check((fileperms(tw_daily_draft_path($draftId)) & 0777) === 0600, 'Private draft permissions are not owner-only.');

$limits = [
    'max_calls_per_hour' => 2,
    'max_calls_per_day' => 3,
    'max_concurrent' => 1,
    'max_output_tokens' => 2000,
    'max_web_search_calls' => 1,
    'lease_seconds' => 240,
];
$reservationOne = tw_daily_ai_reserve($limits);
expect_failure(static fn() => tw_daily_ai_reserve($limits), 'already running');
tw_daily_ai_finish($reservationOne, true, ['total_tokens' => 100]);
$reservationTwo = tw_daily_ai_reserve($limits);
tw_daily_ai_finish($reservationTwo, false, ['total_tokens' => 20]);
expect_failure(static fn() => tw_daily_ai_reserve($limits), 'hourly');
check((fileperms($privateDir . '/daily-ai-budget.json') & 0777) === 0600, 'AI budget ledger is not owner-only.');

$budgetPath = $privateDir . '/daily-ai-budget.json';
$ledgerLimits = [
    'max_calls_per_hour' => 2,
    'max_calls_per_day' => 3,
    'max_concurrent' => 1,
];
$corruptLedgers = [
    [
        'version' => 1,
        'updated_at_utc' => gmdate('c'),
        'limits' => $ledgerLimits,
        'reservations' => 'not-a-map',
    ],
    [
        'version' => 1,
        'updated_at_utc' => gmdate('c'),
        'limits' => ['max_calls_per_hour' => '2'] + array_slice($ledgerLimits, 1, null, true),
        'reservations' => [],
    ],
    [
        'version' => 1,
        'updated_at_utc' => gmdate('c'),
        'limits' => $ledgerLimits,
        'reservations' => [
            str_repeat('a', 32) => [
                'started_at' => time(),
                'started_at_utc' => gmdate('c'),
                'lease_expires_at' => time() + 240,
                'status' => 'active',
            ],
            str_repeat('b', 32) => ['started_at' => time()],
        ],
    ],
];
foreach ($corruptLedgers as $corruptLedger) {
    tw_json_write($budgetPath, $corruptLedger);
    $before = file_get_contents($budgetPath);
    expect_failure(static fn() => tw_daily_ai_reserve($limits), 'corrupted');
    check(file_get_contents($budgetPath) === $before, 'A corrupted AI budget ledger was overwritten during reservation.');
}
$finishCorruptLedger = $corruptLedgers[2];
tw_json_write($budgetPath, $finishCorruptLedger);
$beforeFinish = file_get_contents($budgetPath);
expect_failure(static fn() => tw_daily_ai_finish(str_repeat('a', 32), true), 'corrupted');
check(file_get_contents($budgetPath) === $beforeFinish, 'A corrupted AI budget ledger was overwritten during reconciliation.');

$previousRetentionDays = getenv('TW_INTAKE_RETENTION_DAYS');
putenv('TW_INTAKE_RETENTION_DAYS=30');
$questionNow = time();
tw_json_write($privateDir . '/questions.json', [
    [
        'id' => 'expired-explicit',
        'question' => 'This explicitly expired private question must not enter the queue.',
        'context' => 'expired context',
        'submitted_at_utc' => gmdate('c', $questionNow - 86400),
        'delete_after_utc' => gmdate('c', $questionNow - 60),
    ],
    [
        'id' => 'expired-legacy',
        'question' => 'This legacy expired private question must not enter the queue.',
        'context' => 'legacy expired context',
        'submitted_at_utc' => gmdate('c', $questionNow - (31 * 86400)),
    ],
    [
        'id' => 'invalid-present-expiry',
        'question' => 'A present but invalid retention date must not receive the legacy fallback.',
        'context' => 'invalid-retention context',
        'submitted_at_utc' => gmdate('c', $questionNow - 60),
        'delete_after_utc' => 'not-a-date',
    ],
    [
        'id' => 'future-submission',
        'question' => 'A future-dated submission must not enter the queue.',
        'context' => 'future context',
        'submitted_at_utc' => gmdate('c', $questionNow + 3600),
        'delete_after_utc' => gmdate('c', $questionNow + 7200),
    ],
    [
        'id' => 'invalid-submission',
        'question' => 'A malformed submission timestamp must not enter the queue.',
        'context' => 'invalid timestamp context',
        'submitted_at_utc' => 'yesterday',
        'delete_after_utc' => gmdate('c', $questionNow + 7200),
    ],
    [
        'id' => 'invalid-relationship',
        'question' => 'A retention date before submission must not enter the queue.',
        'context' => 'bad relationship context',
        'submitted_at_utc' => gmdate('c', $questionNow - 60),
        'delete_after_utc' => gmdate('c', $questionNow - 120),
    ],
    [
        'id' => 'expired-config-cap',
        'question' => 'An explicit date must not extend the configured retention cap.',
        'context' => 'overlong context',
        'submitted_at_utc' => gmdate('c', $questionNow - (31 * 86400)),
        'delete_after_utc' => gmdate('c', $questionNow + 90 * 86400),
    ],
    [
        'id' => 'retained',
        'question' => 'This retained private question may enter only the private queue.',
        'context' => 'retained private context',
        'submitted_at_utc' => gmdate('c', $questionNow - 60),
        'delete_after_utc' => gmdate('c', $questionNow + 86400),
    ],
    [
        'id' => 'retained-legacy',
        'question' => 'This recent legacy question uses the configured fallback.',
        'context' => 'retained legacy context',
        'submitted_at_utc' => gmdate('c', $questionNow - (29 * 86400)),
    ],
]);
chmod($privateDir . '/questions.json', 0644);
$questionCandidates = tw_user_question_candidates($privateDir, 6);
check((fileperms($privateDir . '/questions.json') & 0777) === 0600, 'Legacy private question storage was read before owner-only permissions were enforced.');
check(count($questionCandidates) === 2, 'Invalid or expired private questions survived queue retention filtering.');
$questionIds = array_column($questionCandidates, 'id');
sort($questionIds);
check($questionIds === ['user-retained', 'user-retainedlegacy'], 'Configured legacy retention did not preserve exactly the current private questions.');
foreach ($questionCandidates as $candidate) {
    check(is_string($candidate['delete_after_utc'] ?? null), 'Private candidate omitted its effective retention date.');
    check(tw_daily_private_candidate_is_current($candidate, $questionNow), 'Current private candidate failed its investigation-time retention check.');
}

$retainedPrivateCandidate = $questionCandidates[0];
tw_json_write($privateDir . '/daily-candidates.json', [
    'candidate_count' => 4,
    'candidates' => [
        [
            'id' => 'meat-public-control',
            'candidate_type' => 'meat-desk',
            'source' => 'Trust-Worthy Meat Desk',
            'title' => 'Public library control candidate',
        ],
        $retainedPrivateCandidate,
        [
            'id' => 'user-expired-derivative',
            'candidate_type' => 'user-question',
            'source' => 'Private user question',
            'visibility' => 'private',
            'title' => 'Expired derivative title',
            'summary' => 'Expired derivative context',
            'timestamp' => $questionNow - 120,
            'delete_after_utc' => gmdate('c', $questionNow - 60),
        ],
        [
            'id' => 'user-invalid-derivative',
            'candidate_type' => 'user-question',
            'source' => 'Private user question',
            'visibility' => 'private',
            'title' => 'Invalid derivative title',
            'summary' => 'Invalid derivative context',
            'timestamp' => $questionNow + 3600,
            'delete_after_utc' => gmdate('c', $questionNow + 7200),
        ],
    ],
]);
check(tw_daily_prune_expired_candidate_derivatives($privateDir, $questionNow) === 2, 'Expired or invalid private candidate derivatives were not pruned.');
$prunedQueue = tw_private_json_read_strict($privateDir . '/daily-candidates.json', [], 2097152);
check(($prunedQueue['candidate_count'] ?? null) === 2, 'Candidate derivative count was not updated atomically.');
check(array_column($prunedQueue['candidates'], 'id') === ['meat-public-control', $retainedPrivateCandidate['id']], 'Retention pruning did not preserve exactly the public and current private candidates.');
check((fileperms($privateDir . '/daily-candidates.json') & 0777) === 0600, 'Pruned candidate queue is not owner-only.');

$corruptQueueBytes = "{\"candidates\":\"not-a-list\"}\n";
check(file_put_contents($privateDir . '/daily-candidates.json', $corruptQueueBytes) !== false, 'Unable to prepare corrupt candidate queue fixture.');
expect_failure(
    static fn() => tw_daily_prune_expired_candidate_derivatives(TW_DAILY_TEST_PRIVATE_DIR, $questionNow),
    'corrupted'
);
check(file_get_contents($privateDir . '/daily-candidates.json') === $corruptQueueBytes, 'Corrupt candidate queue was reset or overwritten.');
check(unlink($privateDir . '/daily-candidates.json'), 'Unable to clear corrupt candidate fixture.');

$outsideCandidatePath = $testRoot . '/outside-candidates.json';
check(file_put_contents($outsideCandidatePath, "{\"candidates\":[]}\n") !== false, 'Unable to prepare candidate symlink target.');
chmod($outsideCandidatePath, 0644);
check(symlink($outsideCandidatePath, $privateDir . '/daily-candidates.json'), 'Unable to prepare unsafe candidate queue fixture.');
expect_failure(
    static fn() => tw_daily_prune_expired_candidate_derivatives(TW_DAILY_TEST_PRIVATE_DIR, $questionNow),
    'symlink'
);
check(file_get_contents($outsideCandidatePath) === "{\"candidates\":[]}\n", 'Candidate pruning changed an outside file.');
check((fileperms($outsideCandidatePath) & 0777) === 0644, 'Candidate pruning changed outside-file permissions.');
check(unlink($privateDir . '/daily-candidates.json'), 'Unable to clear unsafe candidate queue fixture.');

$expiredDerivative = $validated;
$expiredDerivative['_meta'] = [
    'candidate' => [
        'candidate_type' => 'user-question',
        'source' => 'Private user question',
        'visibility' => 'private',
        'title' => 'Temporary private derivative',
        'summary' => 'Temporary context',
        'timestamp' => $questionNow - 60,
        'delete_after_utc' => gmdate('c', $questionNow + 3600),
    ],
];
$expiredDerivative = tw_store_daily_draft($expiredDerivative);
$expiredDerivativeId = $expiredDerivative['_meta']['draft_id'];
$expiredDerivative['_meta']['candidate']['delete_after_utc'] = gmdate('c', $questionNow - 1);
$expiredDerivative['_meta']['delete_after_utc'] = gmdate('c', $questionNow - 1);
tw_json_write(tw_daily_draft_path($expiredDerivativeId), $expiredDerivative);
check(tw_prune_expired_private_daily_drafts($questionNow) === 1, 'Expired private draft derivative was not pruned.');
check(!is_file(tw_daily_draft_path($expiredDerivativeId)), 'Expired private question/context remained in draft storage.');
check(tw_load_current_daily_draft() === [], 'Expired private draft pointer was not cleared.');
if ($previousRetentionDays === false) {
    putenv('TW_INTAKE_RETENTION_DAYS');
} else {
    putenv('TW_INTAKE_RETENTION_DAYS=' . $previousRetentionDays);
}

echo "Daily Desk hardening checks passed: one-use auth, provider-bound sources, private-data separation, recoverable publication, owner-only storage, and atomic AI budget.\n";
