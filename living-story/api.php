<?php
declare(strict_types=1);
define('LIVING_STORY_ENTRY', true);
require __DIR__ . '/lib.php';
umask(0077);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function ls_reply(array $value, int $status = 200): never {
    http_response_code($status);
    echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'GET' && ($_GET['action'] ?? '') === 'status') {
        try { $c = ls_config(); } catch (Throwable $error) { ls_reply(['available' => false, 'authorized' => false]); }
        if (isset($_COOKIE['LIVING_STORY_PREVIEW'])) { ls_session($c); }
        $authorized = ls_authorized();
        ls_reply(['available' => true, 'authorized' => $authorized, 'csrf' => $authorized ? $_SESSION['csrf'] : null,
            'story' => $authorized && isset($_SESSION['story']) ? ['page' => end($_SESSION['story']['pages']), 'revision' => count($_SESSION['story']['pages'])] : null]);
    }
    if ($method !== 'POST') { header('Allow: GET, POST'); ls_fail('Method not allowed.', 405); }
    if (($_SERVER['HTTP_ORIGIN'] ?? '') !== ls_origin() || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin') === 'cross-site') { ls_fail('Open this story on bobsome1.com and try again.', 403); }
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') { ls_fail('JSON required.', 415); }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) { ls_fail('Request is too large.', 413); }
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if ($raw === false || strlen($raw) > 8192) { ls_fail('Request is too large.', 413); }
    try { $data = json_decode($raw, true, 12, JSON_THROW_ON_ERROR); } catch (JsonException $e) { ls_fail('Invalid request.', 400); }
    if (!is_array($data)) { ls_fail('Invalid request.', 400); }
    $action = $data['action'] ?? '';
    if (!in_array($action, ['login', 'start', 'continue', 'logout'], true)) { ls_fail('Unknown story action.', 400); }
    $c = ls_config();
    if ($action === 'login') {
        ls_budget($c, true);
        $code = ls_text($data['code'] ?? '', 200);
        if (!password_verify($code, $c['access_code_hash'])) { ls_fail('That preview code was not accepted.', 401); }
        ls_session($c); session_regenerate_id(true); $_SESSION = ['authorized' => true, 'expires' => time() + 7200, 'csrf' => bin2hex(random_bytes(32))];
        ls_reply(['authorized' => true, 'csrf' => $_SESSION['csrf']]);
    }
    ls_session($c);
    if (!ls_authorized()) { ls_fail('Your private preview has expired. Sign in again.', 401); }
    if (!is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) || !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'])) { ls_fail('Refresh the page and try again.', 403); }
    if ($action === 'logout') { $_SESSION = []; session_destroy(); setcookie('LIVING_STORY_PREVIEW', '', ['expires' => time()-3600, 'path' => '/living-story/', 'secure' => ls_origin() === 'https://bobsome1.com', 'httponly' => true, 'samesite' => 'Strict']); ls_reply(['authorized' => false]); }
    $requestId = ls_text($data['request_id'] ?? '', 64);
    if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $requestId)) { ls_fail('Invalid request identifier.', 422); }
    if (($_SESSION['last_request'] ?? '') === $requestId && isset($_SESSION['story'])) {
        ls_reply(['page' => end($_SESSION['story']['pages']), 'revision' => count($_SESSION['story']['pages'])]);
    }
    if ($action === 'start') {
        $path = ls_text($data['path'] ?? '', 30); ls_context($path);
        $character = ls_text($data['character'] ?? '', 500);
        if ($character === '') { ls_fail('Describe your investigator.', 422); }
        $story = ['path' => $path, 'character' => $character, 'appearance' => '', 'pages' => []];
        $choice = 'Begin the investigation with an honest question.';
    } else {
        $story = $_SESSION['story'] ?? null;
        if (!$story) { ls_fail('Begin a story first.', 409); }
        if (($data['revision'] ?? null) !== count($story['pages'])) { ls_fail('This story changed in another tab. Refresh to continue.', 409); }
        if (count($story['pages']) >= 8) { ls_fail('This preview story has reached eight scenes. You can return to the book or begin again.', 422); }
        $choice = ls_text($data['choice'] ?? '', 700);
        if ($choice === '') { ls_fail('Choose a path or enter a question.', 422); }
    }
    ls_budget($c, false);
    $page = ls_generate($c, $story, $choice);
    if ($story['pages']) { $story['pages'][count($story['pages'])-1]['action'] = $choice; }
    foreach ($story['pages'] as &$previous) { $previous['image'] = null; } unset($previous);
    $story['appearance'] = $page['character']; $story['pages'][] = $page;
    $_SESSION['story'] = $story; $_SESSION['last_request'] = $requestId;
    if (!session_write_close()) { ls_fail('The scene could not be saved. Please refresh.', 503); }
    ls_reply(['page' => $page, 'revision' => count($story['pages'])]);
} catch (Throwable $error) {
    $status = $error instanceof RuntimeException && in_array($error->getCode(), [400,401,403,405,409,413,415,422,429,502,503], true) ? $error->getCode() : 503;
    ls_reply(['error' => $error instanceof RuntimeException ? $error->getMessage() : 'The preview is temporarily unavailable. The sample story is still available.'], $status);
}
