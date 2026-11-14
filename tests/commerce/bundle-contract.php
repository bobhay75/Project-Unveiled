<?php
declare(strict_types=1);
/* Included by backend.php. Every archive, approval, and credential below is a
 * synthetic fixture. No configuration is activated and no provider is called.
 * A tiny ZIP writer permits duplicate/path/link attacks libzip won't create.
 */
function test_zip_bytes(array $entries): string
{
    $bytes = ''; $central = '';
    foreach ($entries as $entry) {
        $name = $entry['name']; $data = $entry['data']; $length = strlen($data); $crc = crc32($data);
        $expandedSize = $entry['declared_size'] ?? $length;
        $flags = $entry['flags'] ?? 0; $attributes = $entry['attributes'] ?? (0100600 << 16);
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, (($entry['opsys'] ?? 3) << 8) | 20, 20, $flags, 0, 0, 0,
            $crc, $length, $expandedSize, strlen($name), 0, 0, 0, 0, $attributes, strlen($bytes)) . $name;
        $bytes .= pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, 0, 0, 0, $crc, $length, $expandedSize, strlen($name), 0) . $name . $data;
    }
    $offset = strlen($bytes);
    return $bytes . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $offset, 0);
}
function test_bundle_fixture(string $directory, ?callable $mutate = null): array
{
    $entries = []; $components = [];
    foreach (BC_BUNDLE_COMPONENTS as $id) {
        $name = $id . ($id === 'timeline' ? '/index.html' : '/edition.pdf');
        $data = $id === 'timeline' ? '<!doctype html><title>Offline synthetic timeline</title><p>' . str_repeat('test ', 30) . '</p>'
            : "%PDF-1.7\n" . str_repeat('offline synthetic fixture ', 10);
        $entries[] = ['name' => $name, 'data' => $data];
        $components[] = ['id' => $id, 'entrypoint' => $name,
            'files' => [['path' => $name, 'bytes' => strlen($data), 'sha256' => hash('sha256', $data)]]];
    }
    $manifest = ['schema' => 1, 'product' => BC_SKU, 'release_id' => 'synthetic-owner-review-v1', 'components' => $components];
    if ($mutate !== null) $mutate($manifest, $entries);
    $entries[] = ['name' => BC_BUNDLE_MANIFEST, 'data' => json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)];
    return test_bundle_config($directory, test_zip_bytes($entries));
}
function test_bundle_config(string $directory, string $bytes): array
{
    $path = $directory . '/synthetic-bundle.zip';
    file_put_contents($path, $bytes); chmod($path, 0600);
    return ['enabled' => true, 'mode' => 'sandbox', 'client_id' => 'offline-test-client',
        'client_secret' => 'offline-test-secret', 'merchant_id' => 'MERCHANTTEST1',
        'private_dir' => $directory, 'asset_path' => $path, 'asset_sha256' => hash('sha256', $bytes),
        'owner_approved_sha256' => hash('sha256', $bytes), 'owner_approved_at' => '2026-01-01T00:00:00Z'];
}
check(class_exists('ZipArchive'), 'ZIP extension is required; missing extension is not a test pass');
$bundleConfig = test_bundle_fixture($temp);
$checkedBundle = bc_validate_config($bundleConfig, '/var/www/public');
check($checkedBundle['bundle']['release_id'] === 'synthetic-owner-review-v1', 'Complete four-component synthetic archive accepted');
check($checkedBundle['bundle']['content_type'] === 'application/zip', 'Bundle MIME type is ZIP');
check($checkedBundle['bundle']['download_name'] === 'Project-Unveiled-Complete-Study-Bundle.zip', 'Fixed safe ZIP download name');
check(bc_download_headers($checkedBundle) === ['Content-Type: application/zip',
    'Content-Disposition: attachment; filename="Project-Unveiled-Complete-Study-Bundle.zip"',
    'Content-Length: ' . (string)filesize($bundleConfig['asset_path'])], 'Download uses ZIP headers and validated length');
$handle = bc_open_delivery_file($checkedBundle);
check(hash('sha256', stream_get_contents($handle)) === $checkedBundle['asset_sha256'], 'Streaming handle delivers the exact verified bytes');
fclose($handle);
$handle = bc_open_delivery_file($checkedBundle);
$approvedBytes = file_get_contents($checkedBundle['asset_path']);
file_put_contents($checkedBundle['asset_path'], str_repeat('x', strlen($approvedBytes)));
check(hash('sha256', stream_get_contents($handle)) === $checkedBundle['asset_sha256'], 'In-place archive mutation cannot alter the verified delivery snapshot');
check((glob($temp . '/.delivery-*.tmp') ?: []) === [], 'Delivery snapshot has no remaining filesystem name');
fclose($handle);
file_put_contents($checkedBundle['asset_path'], $approvedBytes);
$boundOrder = bc_new_order(str_repeat('9', 32), $token, time(), $checkedBundle);
bc_require_order_asset($checkedBundle, $boundOrder); check(true, 'New order binds exact bundle and manifest identity');
check($boundOrder['asset_sha256'] === $checkedBundle['asset_sha256'], 'Archive hash stored in order');
check(!isset($boundOrder['asset_path']) && !isset($boundOrder['client_secret']), 'Order has no path or payment secret');
$changed = $boundOrder; $changed['asset_sha256'] = str_repeat('0', 64);
rejects(fn() => bc_require_order_asset($checkedBundle, $changed), 'Different release cannot fulfill old order', 409);
$changed = $boundOrder; $changed['bundle_manifest_sha256'] = str_repeat('0', 64);
rejects(fn() => bc_require_order_asset($checkedBundle, $changed), 'Different manifest cannot fulfill order', 409);
$changed = $boundOrder; unset($changed['asset_sha256']);
rejects(fn() => bc_require_order_asset($checkedBundle, $changed), 'Legacy PDF order requires manual reconciliation', 409);
rejects(fn() => bc_create_payload($checkedBundle, $changed), 'Legacy or mismatched order rejected before provider payload', 409);
$changed = $checkedBundle; $changed['asset_sha256'] = str_repeat('0', 64);
rejects(fn() => bc_open_delivery_file($changed), 'Changed bytes at delivery fail closed', 503);
$changed = $checkedBundle; $changed['bundle']['bytes']++;
rejects(fn() => bc_open_delivery_file($changed), 'Changed size at delivery fails closed', 503);
foreach (['owner_approved_sha256', 'owner_approved_at'] as $field) {
    $changed = $bundleConfig; unset($changed[$field]);
    rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'Missing ' . $field . ' fails before provider', 503);
    $changed[$field] = '';
    rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'Blank ' . $field . ' fails before provider', 503);
}
$changed = $bundleConfig; $changed['owner_approved_sha256'] = str_repeat('0', 64);
rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'Stale owner approval cannot approve new hash', 503);
foreach (['2026-02-30T00:00:00Z', '2026-01-01', 'tomorrow', gmdate('Y-m-d\TH:i:s\Z', time() + 3600)] as $date) {
    $changed = $bundleConfig; $changed['owner_approved_at'] = $date;
    rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'Malformed or future approval time rejected', 503);
}
$changed = $bundleConfig; $changed['asset_sha256'] = str_repeat('0', 64); $changed['owner_approved_sha256'] = $changed['asset_sha256'];
rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'Archive digest mismatch rejected', 503);
$changed = $bundleConfig; $changed['asset_path'] = $temp . '/missing.zip';
rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'Missing archive rejected', 503);
foreach (['', str_repeat('not an archive ', 30), test_zip_bytes([]), '%PDF-1.7' . str_repeat('legacy PDF fixture ', 30)] as $bytes) {
    $changed = test_bundle_config($temp, $bytes);
    rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'Empty malformed or old PDF asset rejected', 503);
}
$attacks = [
    'Missing component' => static function (&$m, &$e): void { array_pop($m['components']); array_pop($e); },
    'Duplicated component' => static function (&$m, &$e): void { $m['components'][3] = $m['components'][2]; },
    'Wrong product' => static function (&$m, &$e): void { $m['product'] = 'another-product'; },
    'Unsupported schema' => static function (&$m, &$e): void { $m['schema'] = 2; },
    'Unknown manifest metadata' => static function (&$m, &$e): void { $m['approved'] = true; },
    'Absent entrypoint' => static function (&$m, &$e): void { $m['components'][0]['entrypoint'] = 'illustrated-ebook/missing.pdf'; },
    'Wrong timeline entrypoint' => static function (&$m, &$e): void { $m['components'][1]['entrypoint'] = 'timeline/other.html'; },
    'Missing listed file' => static function (&$m, &$e): void { array_pop($e); },
    'Empty component' => static function (&$m, &$e): void { $m['components'][0]['files'] = []; },
    'Unlisted file' => static function (&$m, &$e): void { $e[] = ['name' => 'timeline/extra.txt', 'data' => 'unlisted']; },
    'Duplicate archive entry' => static function (&$m, &$e): void { $e[] = $e[0]; },
    'Case-fold duplicate' => static function (&$m, &$e): void { $e[] = ['name' => strtoupper($e[0]['name']), 'data' => $e[0]['data']]; },
    'Mismatched file digest' => static function (&$m, &$e): void { $m['components'][0]['files'][0]['sha256'] = str_repeat('0', 64); },
    'Mismatched file size' => static function (&$m, &$e): void { $m['components'][0]['files'][0]['bytes']++; },
    'Cross-component path' => static function (&$m, &$e): void { $m['components'][0]['files'][0] = $m['components'][1]['files'][0]; },
    'Symbolic link entry' => static function (&$m, &$e): void { $e[0]['attributes'] = 0120600 << 16; },
    'Foreign-OS link entry' => static function (&$m, &$e): void { $e[0]['opsys'] = 19; $e[0]['attributes'] = 0120600 << 16; },
    'Special device entry' => static function (&$m, &$e): void { $e[0]['attributes'] = 0020600 << 16; },
    'Directory entry' => static function (&$m, &$e): void { $e[] = ['name' => 'timeline/', 'data' => 'x', 'attributes' => (0040700 << 16) | 0x10]; },
    'Encrypted entry' => static function (&$m, &$e): void { $e[0]['flags'] = 1; },
    'Oversized expanded file' => static function (&$m, &$e): void { $e[0]['declared_size'] = BC_BUNDLE_MAX_BYTES + 1; },
    'Excessive expanded total' => static function (&$m, &$e): void { foreach ($e as &$entry) $entry['declared_size'] = 40000000; },
    'Oversized manifest' => static function (&$m, &$e): void { $m['padding'] = str_repeat('x', 65536); },
    'Non-PDF entrypoint' => static function (&$m, &$e): void {
        $e[0]['data'] = str_repeat('plain text ', 30); $m['components'][0]['files'][0]['bytes'] = strlen($e[0]['data']);
        $m['components'][0]['files'][0]['sha256'] = hash('sha256', $e[0]['data']);
    },
];
foreach ($attacks as $label => $mutate) {
    $changed = test_bundle_fixture($temp, $mutate);
    rejects(fn() => bc_validate_config($changed, '/var/www/public'), $label . ' rejected before provider', 503);
}
foreach ([false, true] as $reverse) {
    $changed = test_bundle_fixture($temp, static function (&$m, &$e) use ($reverse): void {
        $name = 'timeline/INDEX.HTML/extra.txt'; $data = 'ancestor conflict';
        $m['components'][1]['files'][] = ['path' => $name, 'bytes' => strlen($data), 'sha256' => hash('sha256', $data)];
        $entry = ['name' => $name, 'data' => $data];
        if ($reverse) array_unshift($e, $entry); else $e[] = $entry;
    });
    rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'File ancestor collision rejected in either ordering', 503);
}
foreach (['../escape.txt', '/absolute.txt', 'timeline/../../escape.txt', 'timeline\\escape.txt',
    'timeline//empty.txt', 'timeline/%2e%2e.txt', 'timeline/CON.txt', 'timeline/a./index.html',
    'timeline/evil.php', 'timeline/evil.exe', 'timeline/name:stream.txt', 'C:/windows.txt'] as $unsafe) {
    check(!bc_bundle_path($unsafe), 'Unsafe path not accepted: ' . $unsafe);
    $changed = test_bundle_fixture($temp, static function (&$m, &$e) use ($unsafe): void { $e[] = ['name' => $unsafe, 'data' => 'x']; });
    rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'Archive path attack rejected: ' . $unsafe, 503);
}
$changed = test_bundle_fixture($temp, static function (&$m, &$e): void {
    for ($i = 0; $i < BC_BUNDLE_MAX_ENTRIES; $i++) $e[] = ['name' => 'timeline/extra-' . $i . '.txt', 'data' => 'x'];
});
rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'Excessive entry count rejected', 503);
$manifestEntries = [['name' => BC_BUNDLE_MANIFEST, 'data' => '{malformed']];
foreach (BC_BUNDLE_COMPONENTS as $id) $manifestEntries[] = ['name' => $id . '/file.txt', 'data' => 'x'];
$changed = test_bundle_config($temp, test_zip_bytes($manifestEntries));
rejects(fn() => bc_validate_config($changed, '/var/www/public'), 'Malformed JSON manifest rejected', 503);
$example = require dirname(__DIR__, 2) . '/store/checkout/config.example.php';
check($example['enabled'] === false && $example['asset_path'] === '' && $example['asset_sha256'] === ''
    && $example['owner_approved_sha256'] === '' && $example['owner_approved_at'] === '', 'Shipped template remains disabled and unapproved');
$disabledPath = $temp . '/disabled.php'; file_put_contents($disabledPath, '<?php return ' . var_export($example, true) . ';'); chmod($disabledPath, 0600);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2); putenv('BOBSOME1_COMMERCE_CONFIG=' . $disabledPath);
rejects(fn() => bc_config(), 'Disabled configuration cannot activate checkout', 503);
putenv('BOBSOME1_COMMERCE_CONFIG');
