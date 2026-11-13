<?php
declare(strict_types=1);

/**
 * Optional Prosper authorization at the Trust-Worthy investigation boundary.
 * This is a local-only prototype; do not expose the gateway over public HTTP.
 * Return true if not configured; fail closed if configured but unhealthy.
 */
function tw_prosper_authorized(): bool {
    $url = trim((string)(getenv('TW_PROSPER_GATEWAY_URL') ?: ''));
    if ($url === '') return true;
    $parts = parse_url($url);
    if (!is_array($parts)
        || ($parts['scheme'] ?? '') !== 'http'
        || ($parts['host'] ?? '') !== '127.0.0.1'
        || !isset($parts['port'])
        || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])
        || !in_array($parts['path'] ?? '', ['', '/'], true)
    ) return false;
    $key = (string)(getenv('TW_PROSPER_AGENT_KEY') ?: '');
    if ($key === '' || preg_match('/[\r\n]/', $key)) return false;
    if (!function_exists('curl_init')) return false;
    $payload = json_encode(['agent_id'=>'trust-worthy','action'=>'investigation.run']);
    if (!is_string($payload)) return false;
    $ch = curl_init('http://127.0.0.1:' . (int)$parts['port'] . '/authorize');
    if ($ch === false) return false;
    try {
        if (!curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Agent-Key: ' . $key],
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
        ])) return false;
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!is_string($body) || $status !== 200 || strlen($body) > 8192) return false;
        $response = json_decode($body, true);
        return is_array($response) && ($response['allowed'] ?? null) === true;
    } catch (Throwable $e) {
        return false;
    } finally {
        curl_close($ch);
    }
}
