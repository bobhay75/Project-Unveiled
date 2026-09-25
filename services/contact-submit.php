<?php
declare(strict_types=1);

require_once __DIR__ . '/../truth/lib/intake.php';

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function services_out(string $message, int $code = 400, ?int $retryAfter = null): never
{
    http_response_code($code);
    if ($code === 405) header('Allow: POST');
    if ($retryAfter !== null) header('Retry-After: ' . $retryAfter);
    if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'message' => $message]);
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
    }
    exit;
}

function services_received(): never
{
    if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => true]);
    } else {
        header('Location: /services/contact-received.html', true, 303);
    }
    exit;
}

try {
    tw_intake_enforce_post_request(32768);

    if (trim(tw_intake_post_scalar('fax')) !== '') {
        services_received();
    }

    $openedAt = tw_intake_opened_at();
    $age = time() - $openedAt;
    if ($age < 3 || $age > tw_intake_config()['form_ttl_seconds']) {
        throw new TwIntakeException('This form expired or was submitted too quickly. Reload the services page and try again.', 422);
    }

    $name = tw_intake_clean_text(tw_intake_post_scalar('name', true), 2, 100, true);
    $email = tw_intake_clean_text(tw_intake_post_scalar('email', true), 5, 190, true);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new TwIntakeException('Enter a valid email address.', 422);
    }

    $business = tw_intake_clean_text(tw_intake_post_scalar('business', true), 2, 180, true);
    $problem = tw_intake_clean_text(tw_intake_post_scalar('problem', true), 10, 2000);
    $cost = tw_intake_clean_text(tw_intake_post_scalar('cost'), 0, 1200);
    $win = tw_intake_clean_text(tw_intake_post_scalar('win', true), 5, 1200);
    $deadline = tw_intake_clean_text(tw_intake_post_scalar('deadline'), 0, 100, true);
    $website = tw_intake_clean_text(tw_intake_post_scalar('website_url'), 0, 300, true);
    $websiteParts = $website === '' ? [] : parse_url($website);
    if ($website !== '' && (filter_var($website, FILTER_VALIDATE_URL) === false
        || !is_array($websiteParts)
        || !in_array(strtolower((string)($websiteParts['scheme'] ?? '')), ['http', 'https'], true)
        || isset($websiteParts['user']) || isset($websiteParts['pass']))) {
        throw new TwIntakeException('Enter a valid website URL or leave it blank.', 422);
    }

    $submittedTimestamp = time();
    tw_intake_append_record('lead', [
        'schema_version' => 2,
        'id' => bin2hex(random_bytes(16)),
        'name' => $name,
        'email' => $email,
        'business' => $business,
        'problem' => $problem,
        'cost' => $cost,
        'win' => $win,
        'deadline' => $deadline,
        'website_url' => $website,
        'status' => 'new',
        'submitted_at_utc' => gmdate('c', $submittedTimestamp),
        'delete_after_utc' => tw_intake_record_expiry($submittedTimestamp),
        'ip_hash' => tw_intake_client_ip_hash('lead'),
    ]);

    services_received();
} catch (TwIntakeException $error) {
    services_out($error->getMessage(), $error->httpStatus, $error->retryAfter);
} catch (Throwable $error) {
    error_log('Services lead intake failed safely: ' . get_class($error));
    services_out('Your problem brief could not be saved. Please try again later or email thebobsomest1@gmail.com.', 500);
}
