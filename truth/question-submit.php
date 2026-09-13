<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/intake.php';

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function question_out(bool $ok, string $message, int $code = 200, ?int $retryAfter = null): never
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

function question_received(): never
{
    header('Location: /truth/question-received.php', true, 303);
    exit;
}

try {
    tw_intake_enforce_post_request(65536);

    // Do not disclose the spam screen to automated submitters.
    if (trim(tw_intake_post_scalar('website')) !== '') {
        question_out(true, 'Thank you.');
    }

    $openedAt = tw_intake_opened_at();
    $age = time() - $openedAt;
    if ($age < 4 || $age > tw_intake_config()['form_ttl_seconds']) {
        throw new TwIntakeException('This form expired or was submitted too quickly. Reload it and try again.', 422);
    }

    $topic = tw_intake_clean_text(tw_intake_post_scalar('topic', true), 1, 64, true);
    $question = tw_intake_clean_text(tw_intake_post_scalar('question', true), 20, 3000);
    $context = tw_intake_clean_text(tw_intake_post_scalar('context'), 0, 2000);
    $name = tw_intake_clean_text(tw_intake_post_scalar('name'), 0, 100, true);
    $email = tw_intake_clean_text(tw_intake_post_scalar('email'), 0, 190, true);

    $topics = ['news', 'propaganda', 'jesus', 'doctrine', 'history', 'science', 'current-events', 'other'];
    if (!in_array($topic, $topics, true)) {
        throw new TwIntakeException('Choose a valid topic.', 422);
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new TwIntakeException('Enter a valid email address or leave it blank.', 422);
    }

    $submittedTimestamp = time();
    $submittedAt = gmdate('c', $submittedTimestamp);
    tw_intake_append_record('question', [
        'schema_version' => 2,
        'id' => bin2hex(random_bytes(16)),
        'topic' => $topic,
        'question' => $question,
        'context' => $context,
        'name' => $name,
        'email' => $email,
        'status' => 'submitted',
        'submitted_at_utc' => $submittedAt,
        'delete_after_utc' => tw_intake_record_expiry($submittedTimestamp),
        'ip_hash' => tw_intake_client_ip_hash('question'),
    ]);
    question_received();
} catch (TwIntakeException $error) {
    question_out(false, $error->getMessage(), $error->httpStatus, $error->retryAfter);
} catch (Throwable $error) {
    error_log('Trust-Worthy question intake failed safely: ' . get_class($error));
    question_out(false, 'Your question could not be saved. Please try again later.', 500);
}
