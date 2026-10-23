<?php
declare(strict_types=1);

// This legacy standalone administrator surface previously embedded database
// credentials and mutated subscriptions through GET requests. The supported
// administrator interface is the authenticated application view/API.
require_once __DIR__ . '/app/bootstrap.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
http_response_code(404);
echo 'Not found';

