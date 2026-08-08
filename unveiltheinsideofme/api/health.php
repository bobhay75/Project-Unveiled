<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
echo json_encode(['ok'=>true,'app'=>'inside-of-me','version'=>'0.1.0','deep_ai_configured'=>(bool)(getenv('INSIDE_OF_ME_OPENAI_API_KEY')&&getenv('INSIDE_OF_ME_OPENAI_MODEL'))]);
