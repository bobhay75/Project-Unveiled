<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    tw_admin_private_headers();
    http_response_code(405);
    header('Allow: GET, POST');
    exit('GET or POST required.');
}

$nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
tw_admin_private_headers($nonce);

if ($method === 'POST') {
    tw_require_same_origin_form_post(4096);
    $bootstrapValue = tw_post_scalar('bootstrap', 64);
    if ($bootstrapValue === null) {
        http_response_code(422);
        $loginError = 'A valid one-use sign-in code is required.';
        $bootstrap = '';
    } else {
        $bootstrap = trim($bootstrapValue);
    }
    try {
        if ($bootstrap === '' || !tw_admin_bootstrap_consume($bootstrap)) {
            http_response_code(403);
            $loginError = 'That one-use sign-in has expired or was already used. Run admin-url.php again.';
        } else {
            tw_admin_login();
            header('Location: /truth/daily/desk.php', true, 303);
            exit;
        }
    } catch (Throwable $e) {
        http_response_code(503);
        $loginError = 'The private editor session could not be started. Try a newly generated sign-in.';
    }
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer"><title>Private Daily Desk Sign-In</title><link rel="stylesheet" href="/truth/truth-worthy.css"></head><body>
<main><section class="hero"><div class="wrap"><div class="eyebrow">Private Editor Access</div><h1>DAILY DESK SIGN-IN</h1>
<?php if (isset($loginError)): ?><div class="card"><strong>Sign-in unavailable:</strong> <?=h($loginError)?></div><?php endif; ?>
<p>Generate a one-use sign-in on the server, then open the complete URL. The code expires after ten minutes and is never sent in the URL request.</p>
<form id="bootstrap-form" class="challenge" method="post" action="/truth/daily/login.php" autocomplete="off">
<label for="bootstrap">One-use sign-in code</label><input id="bootstrap" name="bootstrap" type="password" required minlength="64" maxlength="64" pattern="[a-f0-9]{64}" autocomplete="one-time-code">
<button class="button" type="submit">Open Private Desk</button>
</form></div></section></main>
<script nonce="<?=h($nonce)?>">(()=>{const p=new URLSearchParams(location.hash.slice(1));const code=p.get('bootstrap');if(!code)return;history.replaceState(null,'',location.pathname);const input=document.getElementById('bootstrap');const form=document.getElementById('bootstrap-form');input.value=code;form.requestSubmit();})();</script>
</body></html>
