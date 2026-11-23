<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/truth/lib/prosper-authorization.php';

function expect_result(bool $actual, bool $expected, string $label): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
}
putenv('TW_PROSPER_GATEWAY_URL');
putenv('TW_PROSPER_AGENT_KEY');
expect_result(tw_prosper_authorized(), true, 'opt-out preserves existing behavior');
putenv('TW_PROSPER_GATEWAY_URL=http://example.com:8080');
putenv('TW_PROSPER_AGENT_KEY=test-key');
expect_result(tw_prosper_authorized(), false, 'external HTTP host rejected');
putenv('TW_PROSPER_GATEWAY_URL=http://127.0.0.1:8080');
putenv('TW_PROSPER_AGENT_KEY');
expect_result(tw_prosper_authorized(), false, 'missing credential denied');
putenv('TW_PROSPER_AGENT_KEY=test-key');
putenv('TW_PROSPER_GATEWAY_URL=http://user@127.0.0.1:8080');
expect_result(tw_prosper_authorized(), false, 'userinfo rejected');
putenv('TW_PROSPER_GATEWAY_URL=http://127.0.0.1:1');
expect_result(tw_prosper_authorized(), false, 'unreachable gateway denied');
echo "Prosper authorization configuration tests passed\n";
