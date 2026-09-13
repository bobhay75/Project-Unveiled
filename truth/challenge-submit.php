<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/intake.php';

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function challenge_out(bool $ok, string $message, int $code = 200, ?int $retryAfter = null): never
{
    http_response_code($code);
    if ($code === 405) {
        header('Allow: POST');
    }
    if ($retryAfter !== null) {
        header('Retry-After: ' . $retryAfter);
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function challenge_received(string $caseId): never
{
    header('Location: /truth/challenge-received.php?case=' . rawurlencode($caseId), true, 303);
    exit;
}

try {
    tw_intake_enforce_post_request(114688);

    // Do not disclose the spam screen to automated submitters.
    if (trim(tw_intake_post_scalar('website')) !== '') {
        challenge_out(true, 'Thank you.');
    }

    $caseId = tw_intake_clean_text(tw_intake_post_scalar('case_id', true), 1, 64, true);
    tw_intake_assert_known_case($caseId);
    $openedAt = tw_intake_opened_at();
    $age = time() - $openedAt;
    if ($age < 4 || $age > tw_intake_config()['form_ttl_seconds']) {
        throw new TwIntakeException('This form expired or was submitted too quickly. Reload it and try again.', 422);
    }

    $type = tw_intake_clean_text(tw_intake_post_scalar('challenge_type', true), 1, 64, true);
    $name = tw_intake_clean_text(tw_intake_post_scalar('name'), 0, 100, true);
    $email = tw_intake_clean_text(tw_intake_post_scalar('email'), 0, 190, true);
    $argument = tw_intake_clean_text(tw_intake_post_scalar('argument', true), 20, 6000);
    $source = tw_intake_clean_text(tw_intake_post_scalar('source'), 0, 2000);

    $allowed = ['source', 'date', 'translation', 'context', 'logic', 'counterevidence', 'assumption', 'opinion', 'other'];
    if (!in_array($type, $allowed, true)) {
        throw new TwIntakeException('Choose a valid challenge type.', 422);
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new TwIntakeException('Enter a valid email address or leave it blank.', 422);
    }

    $submittedTimestamp = time();
    $submittedAt = gmdate('c', $submittedTimestamp);
    tw_intake_append_record('challenge', [
        'schema_version' => 2,
        'id' => bin2hex(random_bytes(16)),
        'case_id' => $caseId,
        'challenge_type' => $type,
        'name' => $name,
        'email' => $email,
        'argument' => $argument,
        'source' => $source,
        'status' => 'pending',
        'submitted_at_utc' => $submittedAt,
        'delete_after_utc' => tw_intake_record_expiry($submittedTimestamp),
        'ip_hash' => tw_intake_client_ip_hash('challenge'),
    ]);
    challenge_received($caseId);
} catch (TwIntakeException $error) {
    challenge_out(false, $error->getMessage(), $error->httpStatus, $error->retryAfter);
} catch (Throwable $error) {
    error_log('Trust-Worthy challenge intake failed safely: ' . get_class($error));
    challenge_out(false, 'Your challenge could not be saved. Please try again later.', 500);
}
