<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/app/bootstrap.php';

if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'message' => 'POST required.']);
    exit;
}

$token = request_bearer_token();
$user = $token !== '' ? cli_authenticate_token_user($token) : null;
if (!$user || empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired desktop link.']);
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['email'] = (string) (isset($user['email']) ? $user['email'] : '');
$_SESSION['role'] = (string) (isset($user['role']) ? $user['role'] : 'user');
$_SESSION['plan'] = (string) (isset($user['plan']) ? $user['plan'] : 'free');
$_SESSION['is_pro'] = (int) (isset($user['is_pro']) ? $user['is_pro'] : 0);
if (!empty($user['avatar'])) $_SESSION['avatar'] = (string) $user['avatar'];

echo json_encode([
    'status' => 'success',
    'user' => ['email' => $_SESSION['email'], 'plan' => $_SESSION['plan']],
], JSON_UNESCAPED_SLASHES);
