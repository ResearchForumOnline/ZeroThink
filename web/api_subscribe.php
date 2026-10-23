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

$subscription_id = $input['sub_id'] ?? '';
$plan = $input['plan'] ?? '';
$result = activate_pro_subscription((int) $user['id'], $subscription_id, $plan, ['verify' => true]);

if (empty($result['ok'])) {
    $message_map = [
        'invalid_plan' => 'That plan is not recognized.',
        'invalid_subscription_id' => 'PayPal did not return a valid subscription ID.',
        'subscription_in_use' => 'That PayPal subscription is already linked to another account.',
        'plan_mismatch' => 'The approved PayPal plan did not match the selected tier.',
        'subscription_not_active' => 'PayPal has not marked this subscription active yet.',
        'subscription_user_mismatch' => 'This PayPal approval belongs to a different account.',
        'verification_failed' => 'PayPal verification failed. Please try again in a moment.',
        'db_unavailable' => 'Subscription storage is unavailable right now.',
        'db_update_failed' => 'Could not save Pro access. Please contact support if payment already completed.',
        'not_logged_in' => 'Login required.',
    ];

    $status_code = 400;
    if (($result['error'] ?? '') === 'subscription_in_use') {
        $status_code = 409;
    } elseif (($result['error'] ?? '') === 'verification_failed') {
        $status_code = 502;
    } elseif (($result['error'] ?? '') === 'not_logged_in') {
        $status_code = 401;
    }

    http_response_code($status_code);
    echo json_encode([
        'status' => 'error',
        'code' => $result['error'] ?? 'unknown_error',
        'message' => $message_map[$result['error'] ?? ''] ?? 'Upgrade activation failed.',
    ]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Pro access is now active.',
    'plan' => $result['plan'] ?? null,
    'subscription_id' => $result['subscription_id'] ?? null,
    'verified' => (bool) ($result['verified'] ?? false),
    'verification_mode' => $result['verification_mode'] ?? 'client_callback',
]);
