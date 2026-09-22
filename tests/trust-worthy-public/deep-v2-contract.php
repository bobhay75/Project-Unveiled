<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$deep = file_get_contents($root . '/truth/lib/trust-worthy-deep.php');
$claimMap = file_get_contents($root . '/truth/claim-map.php');
$stream = file_get_contents($root . '/truth/deep-stream.php');
$entry = file_get_contents($root . '/truth/index.php');
$workspace = file_get_contents($root . '/truth/investigation.php');
$ui = file_get_contents($root . '/truth/investigation-ui.js');

foreach (compact('deep','claimMap','stream','entry','workspace','ui') as $name => $text) {
    if (!is_string($text) || $text === '') throw new RuntimeException("missing V2 source: {$name}");
}

$mustContain = static function(string $haystack, string $needle, string $message): void {
    if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
};
$mustMatch = static function(string $haystack, string $pattern, string $message): void {
    if (preg_match($pattern, $haystack) !== 1) throw new RuntimeException($message);
};
$mustNotContain = static function(string $haystack, string $needle, string $message): void {
    if (str_contains($haystack, $needle)) throw new RuntimeException($message);
};
$mustNotCall = static function(string $php, string $functionName, string $message): void {
    $tokens = token_get_all($php);
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING || strcasecmp($token[1], $functionName) !== 0) continue;
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $j++;
        if (($tokens[$j] ?? null) === '(') throw new RuntimeException($message);
    }
};

foreach (['origin','primary','corroboration','dependency','counter','context'] as $stage) {
    $mustMatch($deep, "/'" . preg_quote($stage, '/') . "'\\s*=>/", "deep engine missing {$stage} pass");
}
$mustContain($deep, 'shared upstream', 'dependency pass does not audit shared upstream evidence');
$mustContain($deep, 'tw_deep_usage_total', 'deep engine lacks total token accounting');
$mustContain($deep, "'total_tokens'", 'deep engine does not expose total token usage');
$mustMatch($deep, '/\$evidenceFloorMet\s*=/', 'deep engine missing evidence floor');
$mustContain($deep, 'INSUFFICIENT EVIDENCE — NO VERDICT', 'deep engine missing no-verdict terminal state');
$mustMatch($deep, '/\$probability\s*=\s*null\s*;/', 'deep engine must be able to suppress probability');
$mustMatch($deep, "/'minimum_source_families'\\s*=>\\s*3/", 'deep engine does not gate on source-family diversity');
$mustMatch($deep, "/'minimum_corroboration_families'\\s*=>\\s*2/", 'deep engine does not require corroboration-family diversity');
$mustContain($deep, 'tw_deep_source_family', 'deep engine lacks deterministic source-family classification');
$mustContain($deep, 'dependency_audit_plus_domain_family_heuristic', 'deep engine overstates family diversity as proven independence');
$mustContain($deep, 'Different domains can still repeat', 'synthesis does not warn that domain diversity is not proof of independence');
$mustContain($deep, 'tw_deep_issue_authorization', 'deep engine missing one-time authorization issuer');
$mustContain($deep, 'tw_deep_consume_authorization', 'deep engine missing one-time authorization consumer');
$mustContain($deep, 'Claim map confirmed', 'deep engine does not distinguish user-confirmed claim maps');
$mustContain($deep, 'relentless evidence investigator', 'deep engine missing adversarial evidence-investigation doctrine');
$mustContain($deep, 'fact-checks', 'deep engine does not explicitly audit fact-checker claims');
$mustContain($deep, 'underlying proposition', 'deep engine does not separate attribution from underlying proposition');
$mustContain($deep, 'strongest good-faith competing explanation', 'deep engine does not steelman competing explanations');
$mustNotCall($deep, 'tw_short_investigation', 'deep engine must never route through the preliminary engine');

$mustContain($claimMap, 'tw_deep_claim_map(', 'claim-map endpoint does not build a claim map');
$mustContain($claimMap, 'tw_rate_limit(', 'claim-map endpoint bypasses public research allowance');
$mustContain($claimMap, 'tw_deep_issue_authorization(', 'claim-map endpoint does not issue deep authorization');
$mustContain($claimMap, 'Request origin was not accepted.', 'claim-map endpoint lacks same-origin enforcement');

$mustContain($stream, 'tw_deep_run(', 'stream endpoint does not invoke deep engine');
$mustContain($stream, "'type'=>'receipt'", 'stream endpoint does not emit evidence receipts');
$mustContain($stream, 'tw_deep_consume_authorization(', 'deep endpoint does not consume one-time authorization');
$mustContain($stream, '$confirmedMap=$_POST[\'confirmed_map\']??\'\';', 'deep endpoint does not require the user-confirmed map');
$mustContain($stream, 'Request origin was not accepted.', 'deep endpoint lacks same-origin enforcement');
$mustNotContain($stream, 'tw_rate_limit(', 'deep research should use the already charged one-time claim-map authorization');

$mustContain($entry, 'START GUIDED DEEP INVESTIGATION', 'entry page does not lead with guided investigation');
$mustContain($entry, 'QUICK PRELIMINARY CHECK', 'entry page does not distinguish preliminary mode');
$mustContain($entry, 'action="/truth/investigate.php"', 'deep entry does not route to workspace');
$mustContain($workspace, 'Choose what matters', 'workspace does not guide the user through investigation intent');
$mustContain($workspace, 'REVIEW THE CLAIM MAP', 'workspace does not stop for claim-map review');
$mustContain($workspace, 'YOUR APPROVAL REQUIRED', 'workspace does not make user confirmation explicit');
$mustContain($workspace, 'LIVE RECEIPTS', 'workspace does not expose actual investigation receipts');
$mustContain($workspace, 'Trace source dependencies + echo chains', 'workspace hides the dependency audit stage');
$mustContain($workspace, 'UNVEILING THE TRUTH', 'workspace is missing the paid continuation identity');
$mustContain($workspace, '$2.99', 'workspace is missing the proposed one-time continuation price');
$mustContain($workspace, 'id="unveilGate" hidden data-checkout-state="owner-link-required"', 'paid continuation gate must remain hidden until checkout is verified');
$mustContain($workspace, 'id="unveilCheckoutButton" disabled', 'paid continuation button must remain disabled until checkout is verified');
$mustContain($workspace, 'No charge can occur from this button yet.', 'draft checkout state is not disclosed clearly');
$mustContain($workspace, 'Trust-Worthy never invents findings to create urgency.', 'conversion gate lacks evidence-integrity protection');
$mustContain($ui, "fetch('/truth/claim-map.php'", 'workspace UI does not request a pre-research claim map');
$mustContain($ui, "data.set('confirmed_map',map)", 'workspace UI does not submit the edited claim map');
$mustContain($ui, "event.type==='receipt'", 'workspace UI does not consume receipt events');
$mustContain($ui, "'dependency'", 'workspace UI does not advance through dependency receipts');
$mustContain($ui, 'total_tokens', 'workspace UI does not disclose Deep token usage');
$mustContain($ui, 'Evidence floor not met', 'workspace UI does not explain blocked probability');

echo "Deep Investigation V2 contract passed.\n";
