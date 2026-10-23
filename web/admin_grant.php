<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/app/bootstrap.php';

$user = function_exists('request_user_record') ? request_user_record() : (function_exists('current_user_record') ? current_user_record() : null);
if (function_exists('is_architect_user') && is_architect_user($user)) {
    header('Location: /master');
    exit;
}

http_response_code(404);
echo 'Not found.';
