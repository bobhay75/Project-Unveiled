<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

tw_admin_private_headers();
tw_require_same_origin_form_post(2048);
tw_require_admin();
tw_require_admin_csrf();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => (bool)$params['secure'],
        'httponly' => (bool)$params['httponly'],
        'samesite' => 'Strict',
    ]);
}
session_destroy();
header('Location: /truth/daily/login.php', true, 303);
exit;
