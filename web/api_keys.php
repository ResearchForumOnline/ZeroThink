<?php
require_once __DIR__ . '/app/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

// The legacy plaintext key endpoint is intentionally retired.
header('Cache-Control: no-store');
header('Location: /studio', true, 303);
exit;
