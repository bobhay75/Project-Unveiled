<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');

function pu_analytics_json_response(bool $ok, int $status, ?int $retryAfter = null): never
{
    http_response_code($status);
    if ($retryAfter !== null) {
        header('Retry-After: ' . max(1, $retryAfter));
    }
    echo json_encode(['ok' => $ok], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ((string)($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        throw new PuAnalyticsException('POST required.', 405);
    }
    if (!pu_analytics_request_origin_is_allowed(true)) {
        throw new PuAnalyticsException('The request origin was not accepted.', 403);
    }
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
    if ($contentType !== 'application/json') {
        throw new PuAnalyticsException('JSON is required.', 415);
    }

    $config = pu_analytics_config();
    $raw = pu_analytics_read_request_body((int)$config['max_body_bytes']);
    $payload = pu_analytics_parse_payload($raw);
    $privateRoot = pu_analytics_private_root();

    // REMOTE_ADDR is used only to derive a secret-protected digest in private
    // rate state. The address itself never enters analytics event records.
    pu_analytics_consume_rate(
        $privateRoot,
        'collector',
        (int)$config['collector_hour_limit'],
        (int)$config['collector_day_limit'],
        (int)$config['max_rate_entries']
    );
    pu_analytics_append_event($privateRoot, $payload, null, $config);
    pu_analytics_json_response(true, 200);
} catch (PuAnalyticsException $error) {
    pu_analytics_json_response(false, $error->httpStatus, $error->retryAfter);
} catch (Throwable $error) {
    pu_analytics_json_response(false, 500);
}
