<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
if (PHP_SAPI !== 'cli') { tw_admin_private_headers(); http_response_code(404); exit; }
$bootstrap = tw_admin_bootstrap_create();
echo "One-use Daily Desk sign-in (expires in 10 minutes):\n";
echo "https://bobsome1.com/truth/daily/login.php#bootstrap=" . rawurlencode($bootstrap) . PHP_EOL;
unset($bootstrap);
