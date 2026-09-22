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

$mustContain($deep, "'origin' =>", 'deep engine missing origin pass');
$mustContain($deep, "'primary' =>", 'deep engine missing primary-source pass');
$mustContain($deep, "'corroboration' =>", 'deep engine missing independent corroboration pass');
$mustContain($deep, "'counter' =>", 'deep engine missing counterevidence pass');
$mustContain($deep, "'context' =>", 'deep engine missing context/chronology pass');
$mustContain($deep, 'evidenceFloorMet', 'deep engine missing evidence floor');
$mustContain($deep, 'INSUFFICIENT EVIDENCE — NO VERDICT', 'deep engine missing no-verdict terminal state');
$mustContain($deep, '$probability = null;', 'deep engine must be able to suppress probability');
$mustContain($deep, "'minimum_source_families' => 3", 'deep engine does not gate on source-family diversity');
$mustContain($deep, "'minimum_corroboration_families' => 2", 'deep engine does not require corroboration-family diversity');
$mustContain($deep, 'tw_deep_source_family', 'deep engine lacks deterministic source-family classification');
$mustContain($deep, 'domain_family_heuristic_only', 'deep engine overstates source-family diversity as independence');
$mustContain($deep, 'Different domains can still repeat the same wire story', 'synthesis does not warn that domain diversity is not proof of independence');
$mustContain($deep, 'tw_deep_issue_authorization', 'deep engine missing one-time authorization issuer');
$mustContain($deep, 'tw_deep_consume_authorization', 'deep engine missing one-time authorization consumer');
$mustContain($deep, 'Claim map confirmed', 'deep engine does not distinguish user-confirmed claim maps');
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
$mustContain($ui, "fetch('/truth/claim-map.php'", 'workspace UI does not request a pre-research claim map');
$mustContain($ui, "data.set('confirmed_map',map)", 'workspace UI does not submit the edited claim map');
$mustContain($ui, "event.type==='receipt'", 'workspace UI does not consume receipt events');
$mustContain($ui, 'Evidence floor not met', 'workspace UI does not explain blocked probability');

echo "Deep Investigation V2 contract passed.\n";
