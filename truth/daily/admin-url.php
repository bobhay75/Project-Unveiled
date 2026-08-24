<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
echo "https://bobsome1.com/truth/daily/desk.php?key=" . tw_admin_token() . PHP_EOL;
