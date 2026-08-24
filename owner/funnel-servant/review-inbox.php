<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");

require __DIR__ . '/bootstrap.php';

try {
    $config = fs_config();
} catch (Throwable $e) {
    http_response_code(503);
    echo '<!doctype html><html><body><h1>' . fs_h($e->getMessage()) . '</h1></body></html>';
    exit;
}

session_name('pu_private_funnel_servant');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/owner/funnel-servant/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

function ri_redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function ri_authenticated(): bool {
    if (empty($_SESSION['authenticated'])) return false;
    $last = (int)($_SESSION['last_seen'] ?? 0);
    if (!$last || time() - $last > 14400) {
        $_SESSION = [];
        session_destroy();
        return false;
    }
    $_SESSION['last_seen'] = time();
    return true;
}

function ri_csrf(): string {
    if (empty($_SESSION['review_inbox_csrf'])) {
        $_SESSION['review_inbox_csrf'] = bin2hex(random_bytes(24));
    }
    return (string)$_SESSION['review_inbox_csrf'];
}

$loginError = '';
if (!ri_authenticated()) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['login_password'])) {
        if (password_verify((string)$_POST['login_password'], (string)$config['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['last_seen'] = time();
            ri_redirect('/owner/funnel-servant/review-inbox.php');
        }
        $loginError = 'Incorrect owner password.';
    }

    ?><!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Project Unveiled Review Inbox</title>
        <link rel="stylesheet" href="app.css">
    </head>
    <body>
    <main class="login-wrap">
        <section class="login-panel">
            <div class="eyebrow">PRIVATE OWNER ACCESS</div>
            <h1>Review<br><span>Inbox</span></h1>
            <p>Approve or reject Project Unveiled reader reviews without digging through the full dashboard.</p>
            <?php if ($loginError !== ''): ?><div class="flash bad"><?=fs_h($loginError)?></div><?php endif; ?>
            <form method="post" class="stack">
                <label>Owner password<input type="password" name="login_password" autocomplete="current-password" required autofocus></label>
                <button type="submit">Open Review Inbox</button>
            </form>
        </section>
    </main>
    </body>
    </html><?php
    exit;
}

$settings = fs_settings();
$csrf = ri_csrf();
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['operation'])) {
    $posted = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $posted)) {
        $flash = ['type' => 'bad', 'message' => 'Security token expired. Reload and try again.'];
    } else {
        $id = fs_clean($_POST['id'] ?? '', 90);
        $operation = fs_clean($_POST['operation'] ?? '', 30);
        $allowed = ['approve', 'reject', 'hide', 'pending', 'feature', 'unfeature', 'verify', 'unverify'];
        if ($id === '' || !in_array($operation, $allowed, true)) {
            $flash = ['type' => 'bad', 'message' => 'Invalid review action.'];
        } else {
            [$ok, $message] = fs_community_moderate($id, $operation);
            fs_log('review_inbox', $message, ['id' => $id, 'operation' => $operation]);
            $flash = ['type' => $ok ? 'ok' : 'bad', 'message' => $ok && $operation === 'approve' ? 'Review approved and is now eligible to appear publicly.' : $message];
        }
    }
}

$pending = fs_community_filtered('pending', 'review', 100);
$approved = fs_community_filtered('approved', 'review', 25);
$timezone = (string)($settings['timezone'] ?? 'America/Chicago');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Project Unveiled Review Inbox</title>
    <link rel="stylesheet" href="app.css">
</head>
<body>
<header class="top">
    <div>
        <div class="eyebrow">PROJECT UNVEILED · PRIVATE OWNER TOOL</div>
        <h1>Review <span>Inbox</span></h1>
    </div>
    <div class="status-pill on"><?=count($pending)?> pending</div>
</header>
<nav class="nav">
    <a class="active" href="/owner/funnel-servant/review-inbox.php">Review Inbox</a>
    <a href="/owner/funnel-servant/?tab=community">Full Reader Dashboard</a>
    <a href="/book/" target="_blank" rel="noopener">Public Book Page</a>
</nav>
<main class="main">
    <?php if ($flash): ?><div class="flash <?=fs_h($flash['type'])?>"><?=fs_h($flash['message'])?></div><?php endif; ?>

    <section class="section-head">
        <div>
            <div class="eyebrow">AWAITING YOUR DECISION</div>
            <h2>Pending book reviews</h2>
            <p>Nothing becomes public until you approve it.</p>
        </div>
    </section>

    <?php if (!$pending): ?>
        <section class="panel"><h3>You're caught up.</h3><p>No book reviews are waiting for approval.</p></section>
    <?php endif; ?>

    <section class="community-list-owner">
    <?php foreach ($pending as $row): if (!is_array($row)) continue; $id = (string)($row['id'] ?? ''); ?>
        <article class="panel community-card status-pending" id="<?=fs_h($id)?>">
            <div class="community-card-head">
                <div>
                    <div class="eyebrow">BOOK REVIEW · PENDING</div>
                    <h3><?=fs_h($row['name'] ?? 'Reader')?><?php if (!empty($row['title'])): ?> — <?=fs_h($row['title'])?><?php endif; ?></h3>
                </div>
                <div class="community-badges-owner"><span class="status-badge status-pending"><?=fs_h((int)($row['rating'] ?? 0))?> / 5 STARS</span></div>
            </div>
            <div class="community-meta">
                <span><b>Submitted</b><?=fs_h(fs_social_local_time((string)($row['created_at_utc'] ?? ''), $timezone))?></span>
                <span><b>Page</b><?=fs_h($row['page_title'] ?? 'Reader Reviews')?></span>
                <span><b>Spam score</b><?=fs_h((int)($row['spam_score'] ?? 0))?></span>
            </div>
            <div class="community-submission-body"><?=nl2br(fs_h($row['body'] ?? ''))?></div>
            <?php if (!empty($row['reader_completed'])): ?><p class="muted">Reader checked: “I have read Project Unveiled.”</p><?php endif; ?>

            <div class="community-actions-owner">
                <form method="post">
                    <input type="hidden" name="csrf" value="<?=fs_h($csrf)?>">
                    <input type="hidden" name="id" value="<?=fs_h($id)?>">
                    <input type="hidden" name="operation" value="approve">
                    <button class="confirm-posted" type="submit">APPROVE &amp; PUBLISH REVIEW</button>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?=fs_h($csrf)?>">
                    <input type="hidden" name="id" value="<?=fs_h($id)?>">
                    <input type="hidden" name="operation" value="reject">
                    <button class="secondary" type="submit">Reject</button>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?=fs_h($csrf)?>">
                    <input type="hidden" name="id" value="<?=fs_h($id)?>">
                    <input type="hidden" name="operation" value="hide">
                    <button class="secondary" type="submit">Hide</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
    </section>

    <?php if ($approved): ?>
    <section class="section-head"><div><div class="eyebrow">RECENT HISTORY</div><h2>Recently approved</h2></div></section>
    <section class="community-list-owner">
    <?php foreach (array_slice($approved, 0, 10) as $row): if (!is_array($row)) continue; ?>
        <article class="panel community-card status-approved">
            <div class="community-card-head"><div><div class="eyebrow">APPROVED REVIEW</div><h3><?=fs_h($row['name'] ?? 'Reader')?></h3></div><div class="community-badges-owner"><span class="status-badge status-approved">APPROVED ✓</span><span><?=fs_h((int)($row['rating'] ?? 0))?> / 5</span></div></div>
            <div class="community-submission-body"><?=nl2br(fs_h($row['body'] ?? ''))?></div>
        </article>
    <?php endforeach; ?>
    </section>
    <?php endif; ?>
</main>
<footer class="footer">Project Unveiled · Private Review Inbox · Human approval required</footer>
</body>
</html>
