<?php
declare(strict_types=1);

$temporaryDir = sys_get_temp_dir() . '/trust-worthy-deep-auth-' . bin2hex(random_bytes(6));
define('TW_PRIVATE_DIR_OVERRIDE', $temporaryDir);
require_once dirname(__DIR__, 2) . '/truth/lib/trust-worthy-deep.php';

function deep_auth_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

register_shutdown_function(static function() use ($temporaryDir): void {
    foreach (glob($temporaryDir . '/*') ?: [] as $path) @unlink($path);
    @rmdir($temporaryDir);
});

$secret = hash('sha256', 'deep-auth-test-secret');
$ipHash = hash('sha256', '127.0.0.1');
$question = 'A sufficiently long test claim for one-time authorization behavior.';
$context = 'Test context.';

$token = tw_deep_issue_authorization($ipHash, $question, $context, $secret);
deep_auth_check($token !== '', 'authorization token was not issued');
deep_auth_check(tw_deep_consume_authorization($token, $ipHash, $question, $context, $secret), 'first token consumption failed');
deep_auth_check(!tw_deep_consume_authorization($token, $ipHash, $question, $context, $secret), 'authorization token replay was accepted');

$second = tw_deep_issue_authorization($ipHash, $question, $context, $secret);
deep_auth_check($second !== '', 'second authorization token was not issued');
deep_auth_check(!tw_deep_consume_authorization($second, hash('sha256','different-ip'), $question, $context, $secret), 'authorization accepted from a different IP hash');
deep_auth_check(!tw_deep_consume_authorization($second, $ipHash, $question . ' changed', $context, $secret), 'authorization accepted for a different question');
deep_auth_check(tw_deep_consume_authorization($second, $ipHash, $question, $context, $secret), 'valid token was damaged by rejected mismatch attempts');

echo "Deep one-time authorization test passed.\n";
