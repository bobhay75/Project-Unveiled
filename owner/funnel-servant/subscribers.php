<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow,noarchive');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$bootstrap = __DIR__ . '/bootstrap.php';
if (!is_file($bootstrap)) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Reader Signups Diagnostic</title><style>body{background:#080705;color:#f4ead2;font:18px Arial;padding:40px}code{display:block;background:#111;padding:15px;color:#e4b951}</style><h1>Reader Signups diagnostic</h1><p>The owner bootstrap file was not found.</p><code>' . htmlspecialchars($bootstrap, ENT_QUOTES, 'UTF-8') . '</code>';
    exit;
}
require $bootstrap;

/* Use the exact authentication/session pattern used by the working v7 Command Center. */
try {
    $config = fs_config();
    $env = fs_env();
} catch (Throwable $e) {
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>Reader Signups Diagnostic</title><style>body{background:#080705;color:#f4ead2;font:18px Arial;padding:40px}.bad{border-left:4px solid #ff6b6b;padding:15px;background:#240d0d}</style><h1>Reader Signups diagnostic</h1><p class="bad">The Funnel Servant configuration could not be loaded.</p>';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('pu_private_funnel_servant');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/owner/funnel-servant/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ));
    session_start();
}
if (empty($_SESSION['authenticated']) || empty($_SESSION['last_seen']) || time() - (int)$_SESSION['last_seen'] > 14400) {
    header('Location: /owner/funnel-servant/');
    exit;
}
$_SESSION['last_seen'] = time();

function rs_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$file = dirname(__DIR__, 3) . '/site-private/project-unveiled/subscribers.json';
$items = array();
$error = '';
$storageStatus = 'Ready';

if (is_file($file)) {
    if (!is_readable($file)) {
        $error = 'Subscriber data exists but is not readable.';
        $storageStatus = 'Check permissions';
    } else {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            $error = 'Subscriber data exists but could not be read.';
            $storageStatus = 'Read failed';
        } elseif (trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $error = 'Subscriber data is not valid JSON. The file was not changed.';
                $storageStatus = 'Invalid JSON';
            }
        }
    }
} else {
    $parent = dirname($file);
    if (!is_dir($parent)) {
        $storageStatus = 'Created after first signup';
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="project-unveiled-subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array('First name','Email','Status','Created','Updated','Source page','UTM source','UTM medium','UTM campaign','UTM content','Consent version','Consent date'));
    foreach ($items as $row) {
        if (!is_array($row)) continue;
        fputcsv($out, array(
            isset($row['first_name']) ? $row['first_name'] : '',
            isset($row['email']) ? $row['email'] : '',
            isset($row['status']) ? $row['status'] : '',
            isset($row['created_at']) ? $row['created_at'] : '',
            isset($row['updated_at']) ? $row['updated_at'] : '',
            isset($row['source_url']) ? $row['source_url'] : '',
            isset($row['utm_source']) ? $row['utm_source'] : '',
            isset($row['utm_medium']) ? $row['utm_medium'] : '',
            isset($row['utm_campaign']) ? $row['utm_campaign'] : '',
            isset($row['utm_content']) ? $row['utm_content'] : '',
            isset($row['consent_version']) ? $row['consent_version'] : '',
            isset($row['consent_at']) ? $row['consent_at'] : ''
        ));
    }
    fclose($out);
    exit;
}

$activeCount = 0;
foreach ($items as $row) {
    if (is_array($row) && isset($row['status']) && $row['status'] === 'subscribed') $activeCount++;
}
$rows = array_reverse($items);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reader Signups · Project Unveiled OS</title>
<link rel="stylesheet" href="/owner/funnel-servant/v7-foundation.css">
<link rel="stylesheet" href="/owner/funnel-servant/v7-1-shell.css">
<style>
body{margin:0;background:#080705;color:#f4ead2;font:16px/1.5 Arial,sans-serif}main{max-width:1350px;margin:auto;padding:28px}.top{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}.btn{display:inline-block;padding:11px 15px;background:#e4b951;color:#111;text-decoration:none;font-weight:900;border:0}.promise{border-left:4px solid #62df98;padding:14px;background:#0c160f}.error{border-left:4px solid #ff6b6b;padding:14px;background:#240d0d}.cards{display:grid;grid-template-columns:repeat(3,minmax(0,240px));gap:14px;margin:20px 0}.card{border:1px solid #5c4722;padding:16px;background:#11100c}.card strong{display:block;font:700 32px Georgia;color:#e4b951}.wrap{overflow:auto;border:1px solid #4b391b}table{width:100%;border-collapse:collapse;min-width:980px}th,td{padding:11px;border-bottom:1px solid #3e321d;text-align:left;vertical-align:top}th{color:#e4b951;background:#15120c}.muted{color:#bfb49a}code{overflow-wrap:anywhere}.empty{padding:24px;text-align:center;color:#bfb49a}@media(max-width:700px){main{padding:16px}.cards{grid-template-columns:1fr}}
</style></head><body><main>
<div class="top"><div><a href="/owner/funnel-servant/command-center.php">← Command Center</a><h1>Reader Signups</h1></div><a class="btn" href="?export=csv">Download CSV</a></div>
<p class="promise"><strong>Privacy promise:</strong> We do not sell, rent, or trade visitor information. Ever.</p>
<?php if ($error !== ''): ?><p class="error"><strong>Storage warning:</strong> <?php echo rs_h($error); ?><br><small>File: <?php echo rs_h($file); ?></small></p><?php endif; ?>
<div class="cards"><div class="card"><strong><?php echo (int)$activeCount; ?></strong>active subscribers</div><div class="card"><strong><?php echo count($items); ?></strong>total records</div><div class="card"><strong style="font-size:22px"><?php echo rs_h($storageStatus); ?></strong>subscriber storage</div></div>
<div class="wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Source</th><th>Campaign</th><th>Consent</th></tr></thead><tbody>
<?php if (count($rows) === 0): ?><tr><td colspan="7" class="empty">No reader signups yet. Submit a public test signup to verify the full path.</td></tr><?php else: ?>
<?php foreach ($rows as $row): if (!is_array($row)) continue; ?>
<tr><td><?php echo rs_h(isset($row['first_name']) ? $row['first_name'] : ''); ?></td><td><?php echo rs_h(isset($row['email']) ? $row['email'] : ''); ?></td><td><?php echo rs_h(isset($row['status']) ? $row['status'] : ''); ?></td><td><?php echo rs_h(isset($row['created_at']) ? $row['created_at'] : ''); ?></td><td><?php echo rs_h(isset($row['utm_source']) ? $row['utm_source'] : ''); ?><br><small class="muted"><?php echo rs_h(substr(isset($row['source_url']) ? (string)$row['source_url'] : '', 0, 140)); ?></small></td><td><?php echo rs_h(isset($row['utm_campaign']) ? $row['utm_campaign'] : ''); ?></td><td><?php echo rs_h(isset($row['consent_version']) ? $row['consent_version'] : ''); ?><br><small class="muted"><?php echo rs_h(isset($row['consent_at']) ? $row['consent_at'] : ''); ?></small></td></tr>
<?php endforeach; ?><?php endif; ?></tbody></table></div>
<p class="muted">Data location: <code><?php echo rs_h($file); ?></code></p>
</main></body></html>
