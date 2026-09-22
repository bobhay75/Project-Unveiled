<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$deep = file_get_contents($root . '/truth/lib/trust-worthy-deep.php');
$stream = file_get_contents($root . '/truth/deep-stream.php');
$entry = file_get_contents($root . '/truth/index.php');
$workspace = file_get_contents($root . '/truth/investigation.php');
$ui = file_get_contents($root . '/truth/investigation-ui.js');

foreach (compact('deep','stream','entry','workspace','ui') as $name => $text) {
    if (!is_string($text) || $text === '') throw new RuntimeException("missing V2 source: {$name}");
}

$mustContain = static function(string $haystack, string $needle, string $message): void {
    if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
};
$mustNotContain = static function(string $haystack, string $needle, string $message): void {
    if (str_contains($haystack, $needle)) throw new RuntimeException($message);
};

$mustContain($deep, "'origin' =>", 'deep engine missing origin pass');
$mustContain($deep, "'primary' =>", 'deep engine missing primary-source pass');
$mustContain($deep, "'corroboration' =>", 'deep engine missing independent corroboration pass');
$mustContain($deep, "'counter' =>", 'deep engine missing counterevidence pass');
$mustContain($deep, "'context' =>", 'deep engine missing context/chronology pass');
$mustContain($deep, 'evidenceFloorMet', 'deep engine missing evidence floor');
$mustContain($deep, 'INSUFFICIENT EVIDENCE — NO VERDICT', 'deep engine missing no-verdict terminal state');
$mustContain($deep, "'probability'=>null", 'deep engine must be able to suppress probability');
$mustNotContain($deep, 'tw_short_investigation(', 'deep engine must never route through the preliminary engine');

$mustContain($stream, 'tw_deep_run(', 'stream endpoint does not invoke deep engine');
$mustContain($stream, "'type'=>'receipt'", 'stream endpoint does not emit evidence receipts');
$mustContain($stream, 'tw_rate_limit(', 'deep endpoint bypasses public research rate limiting');
$mustContain($stream, 'Request origin was not accepted.', 'deep endpoint lacks same-origin enforcement');

$mustContain($entry, 'START GUIDED DEEP INVESTIGATION', 'entry page does not lead with guided investigation');
$mustContain($entry, 'QUICK PRELIMINARY CHECK', 'entry page does not distinguish preliminary mode');
$mustContain($entry, 'action="/truth/investigation.php"', 'deep entry does not route to workspace');
$mustContain($workspace, 'Choose what matters', 'workspace does not guide the user through investigation intent');
$mustContain($workspace, 'LIVE RECEIPTS', 'workspace does not expose actual investigation receipts');
$mustContain($ui, "event.type==='receipt'", 'workspace UI does not consume receipt events');
$mustContain($ui, 'Evidence floor not met', 'workspace UI does not explain blocked probability');

echo "Deep Investigation V2 contract passed.\n";
