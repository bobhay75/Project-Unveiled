<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");

// Set by Apache only after the existing /owner/.htaccess authentication.
// PHP_AUTH_USER and forwarded headers are deliberately not accepted as proof.
if (!is_string($_SERVER['REMOTE_USER'] ?? null) || trim($_SERVER['REMOTE_USER']) === '') {
    http_response_code(403);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Owner sign-in required</title><h1>Owner sign-in required</h1><p>Open this inbox through the protected owner workspace.</p></html>';
    exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

require_once __DIR__ . '/../truth/lib/intake.php';
function lead_h(mixed $value): string {
    return htmlspecialchars(is_scalar($value) ? (string)$value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$rows = [];
$error = '';
try {
    $dir = tw_intake_private_dir();
    if (file_exists($dir) || is_link($dir)) {
        tw_intake_prepare_private_dir();
        $rows = tw_intake_read_records($dir . '/leads.json', tw_intake_config()['max_file_bytes']);
    }
    $now = time();
    $rows = array_values(array_filter($rows, static function (array $row) use ($now): bool {
        $submitted = tw_intake_parse_utc_timestamp($row['submitted_at_utc'] ?? null);
        $expires = tw_intake_parse_utc_timestamp($row['delete_after_utc'] ?? null);
        if ($submitted === null || $expires === null || $expires <= $submitted) {
            throw new TwIntakeException('Invalid lead retention date.');
        }
        return min($expires, $submitted + tw_intake_config()['retention_days'] * 86400) > $now;
    }));
    usort($rows, static fn(array $a, array $b): int => strcmp($b['submitted_at_utc'], $a['submitted_at_utc']));
} catch (Throwable $exception) {
    $rows = [];
    $error = 'The inbox could not be read safely. No submissions were changed. Check private intake storage before accepting more briefs.';
    http_response_code(503);
}
$total = count($rows);
$pages = max(1, (int)ceil($total / 20));
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = min($pages, max(1, is_int($requestedPage) ? $requestedPage : 1));
$rows = array_slice($rows, ($page - 1) * 20, 20);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Problem briefs | Bobsome1 Owner</title><link rel="stylesheet" href="/services/services.css?v=3"></head>
<body><main class="lead-inbox shell">
<a href="/owner/">← Owner workspace</a>
<p class="eyebrow">Private follow-up</p><h1>Problem briefs</h1>
<p><?= $total ?> retained brief<?= $total === 1 ? '' : 's' ?> · Newest first · Dates shown in UTC</p>
<p>Review the details and reply personally. This inbox sends no automatic messages and does not subscribe anyone to marketing.</p>
<?php if ($error !== ''): ?><p role="alert"><?= lead_h($error) ?></p>
<?php elseif ($rows === []): ?><section class="lead-card"><h2>No briefs yet</h2><p>New submissions from the <a href="/services/#contact">contact form</a> will appear here.</p></section><?php endif; ?>
<?php foreach ($rows as $row): ?>
<article class="lead-card">
<p class="eyebrow"><?= lead_h($row['submitted_at_utc']) ?></p>
<h2><?= lead_h($row['business'] ?? '') ?></h2>
<p><strong><?= lead_h($row['name'] ?? '') ?></strong> · <?= lead_h($row['email'] ?? '') ?></p>
<?php if (($row['offer'] ?? '') === 'visibility-starter'): ?><p><strong>Requested offer:</strong> $250 Visibility Starter — scope request only, not payment or work approval.</p><?php endif; ?>
<dl><?php foreach (['problem' => 'What is not working', 'cost' => 'Current cost', 'win' => 'Desired result', 'deadline' => 'Deadline', 'website_url' => 'Public page'] as $key => $label): ?>
<dt><?= lead_h($label) ?></dt><dd><?= lead_h(($row[$key] ?? '') === '' ? 'Not supplied' : $row[$key]) ?></dd>
<?php endforeach; ?></dl>
</article>
<?php endforeach; ?>
<?php if ($pages > 1): ?><nav aria-label="Inbox pages">
<?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">← Newer briefs</a><?php endif; ?>
<span>Page <?= $page ?> of <?= $pages ?></span>
<?php if ($page < $pages): ?><a href="?page=<?= $page + 1 ?>">Older briefs →</a><?php endif; ?>
</nav><?php endif; ?>
</main></body></html>
