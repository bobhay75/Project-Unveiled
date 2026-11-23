<?php
declare(strict_types=1);

/** The guided release stays off until its provider and payment acceptance pass. */
function tw_guided_enabled(): bool {
    return getenv('TW_GUIDED_RESEARCH_ENABLED') === '1';
}

function tw_guided_require_enabled(): void {
    if (tw_guided_enabled()) return;
    http_response_code(404);
    header('Cache-Control: no-store, max-age=0');
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    exit('This investigation route is not available.');
}

function tw_guided_valid_origin(array $server): bool {
    $hostValue = strtolower(rtrim((string)($server['HTTP_HOST'] ?? ''), '.'));
    $host = preg_match('/^((?:www\.)?bobsome1\.com)(?::443)?$/D', $hostValue, $matches) === 1 ? $matches[1] : '';
    $parts = parse_url(trim((string)($server['HTTP_ORIGIN'] ?? '')));
    $site = strtolower(trim((string)($server['HTTP_SEC_FETCH_SITE'] ?? '')));
    return $host !== '' && is_array($parts)
        && strtolower((string)($parts['scheme'] ?? '')) === 'https'
        && strtolower((string)($parts['host'] ?? '')) === $host
        && (!isset($parts['port']) || $parts['port'] === 443)
        && !isset($parts['user']) && !isset($parts['pass'])
        && !isset($parts['query']) && !isset($parts['fragment'])
        && in_array((string)($parts['path'] ?? ''), ['', '/'], true)
        && ($site === '' || $site === 'same-origin');
}

function tw_guided_post_gate(int $maximumBytes): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405); header('Allow: POST'); exit('POST required.');
    }
    $type = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
    if ($type !== 'application/x-www-form-urlencoded') {
        http_response_code(415); exit('Form encoding required.');
    }
    $length = (string)($_SERVER['CONTENT_LENGTH'] ?? '');
    if ($length !== '' && (!ctype_digit($length) || (int)$length > $maximumBytes)) {
        http_response_code(413); exit('Submission too large.');
    }
    require_once __DIR__ . '/intake.php';
    try { tw_intake_enforce_raw_body_limit($maximumBytes); }
    catch (TwIntakeException $error) { http_response_code($error->httpStatus); exit($error->getMessage()); }
    if (!tw_guided_valid_origin($_SERVER)) {
        http_response_code(403); exit('Request origin was not accepted.');
    }
}

function tw_guided_require_provider_access(): void {
    require_once __DIR__ . '/prosper-authorization.php';
    if (tw_prosper_authorized()) return;
    http_response_code(503);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'message' => 'Investigation authorization is temporarily unavailable. Please try again later.']);
    exit;
}
