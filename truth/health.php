<?php
declare(strict_types=1);
header('Cache-Control: no-store, max-age=0');
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/lib/trust-worthy-ai.php';

$dir = tw_private_dir();
$key = tw_openai_key();
$cfg = tw_ai_config();

$checks = [
  'php' => PHP_VERSION,
  'curl_enabled' => function_exists('curl_init'),
  'private_storage_exists' => is_dir($dir),
  'private_storage_writable' => is_dir($dir) && is_writable($dir),
  'openai_key_configured' => $key !== '',
  'model' => (string)$cfg['model'],
  'reasoning_effort' => (string)$cfg['reasoning_effort'],
  'daily_request_cap' => (int)$cfg['daily_request_cap'],
  'per_ip_daily_cap' => (int)$cfg['per_ip_daily_cap'],
  'max_output_tokens' => (int)$cfg['max_output_tokens'],
  'max_web_search_calls' => (int)$cfg['max_web_search_calls'],
];

$checks['ready'] = $checks['curl_enabled'] && $checks['private_storage_exists'] && $checks['private_storage_writable'] && $checks['openai_key_configured'];

echo json_encode($checks, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
