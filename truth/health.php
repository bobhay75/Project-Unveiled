<?php
declare(strict_types=1);
header('Cache-Control: no-store, max-age=0');
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
echo json_encode(['service'=>'trust-worthy-queue','public_model_execution'=>false,'status'=>'available'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
