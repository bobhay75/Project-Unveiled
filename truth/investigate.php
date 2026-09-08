<?php
declare(strict_types=1);
/* Compatibility endpoint: public requests are queue-only and never invoke a model. */
$_POST['topic'] = $_POST['topic'] ?? 'other';
require __DIR__ . '/question-submit.php';
