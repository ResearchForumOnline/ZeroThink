<?php
require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$user = current_user_record(true);
if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Login required.']);
    exit;
}

$contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (strpos($contentType, 'application/json') !== 0) {
    http_response_code(415);
    echo json_encode(['status' => 'error', 'message' => 'JSON request required.']);
    exit;
}
$raw = (string) file_get_contents('php://input');
if (strlen($raw) > 16384) {
    http_response_code(413);
    echo json_encode(['status' => 'error', 'message' => 'Request is too large.']);
    exit;
}
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request payload.']);
    exit;
}
if (!zt_csrf_validate($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Security token expired.']);
    exit;
}

$result = activate_pro_subscription(
    (int) $user['id'],
    $input['sub_id'] ?? '',
    $input['plan'] ?? '',
    ['verify' => true]
);

if (empty($result['ok'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'code' => $result['error'] ?? 'unknown_error',
        'message' => 'Upgrade confirmation failed.',
    ]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Upgrade confirmed.',
    'plan' => $result['plan'] ?? null,
    'verified' => (bool) ($result['verified'] ?? false),
]);
