<?php
require_once __DIR__ . '/../bootstrap.php';

$user = function_exists('request_user_record') ? request_user_record() : (function_exists('current_user_record') ? current_user_record() : null);
if (!function_exists('is_architect_user') || !is_architect_user($user)) {
    http_response_code(403);
    echo 'Forbidden.';
    exit;
}

header('Location: /master');
exit;
