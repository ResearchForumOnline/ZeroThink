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

$contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (strpos($contentType, 'application/json') !== 0) {
    http_response_code(415);
    echo json_encode(['status' => 'error', 'message' => 'JSON request required.']);
    exit;
}

$raw = (string) file_get_contents('php://input');
if (strlen($raw) > 4096) {
    http_response_code(413);
    echo json_encode(['status' => 'error', 'message' => 'Request is too large.']);
    exit;
}
$input = json_decode($raw, true);
if (!is_array($input) || !zt_csrf_validate($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Security token expired.']);
    exit;
}

$user = current_user_record(true);
if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Login required.']);
    exit;
}

$result = cancel_pro_subscription((int) $user['id'], ['cancel_remote' => true]);

if (empty($result['ok'])) {
    $message_map = [
        'not_logged_in' => 'Login required.',
        'db_unavailable' => 'Billing storage is unavailable right now.',
        'user_not_found' => 'Account not found.',
        'db_update_failed' => 'Could not cancel the subscription on this account. Please contact shaf@talktoai.org.',
    ];

    $status_code = ($result['error'] ?? '') === 'not_logged_in' ? 401 : 500;
    http_response_code($status_code);
    echo json_encode([
        'status' => 'error',
        'code' => $result['error'] ?? 'unknown_error',
        'message' => $message_map[$result['error'] ?? ''] ?? 'Cancellation failed.',
        'support_email' => 'shaf@talktoai.org',
    ]);
    exit;
}

$manual_paypal_cancel_required = !empty($result['manual_paypal_cancel_required']);
$message = $manual_paypal_cancel_required
    ? 'ZeroThink access is cancelled on this account. To stop future billing, cancel the recurring payment in PayPal too.'
    : 'ZeroThink access is cancelled, and the PayPal subscription has also been cancelled.';

$warning = $manual_paypal_cancel_required
    ? 'Monthly payments are non-refundable. Yearly plans may request a refund within 7 days by emailing shaf@talktoai.org.'
    : null;

echo json_encode([
    'status' => 'success',
    'message' => $message,
    'warning' => $warning,
    'manual_paypal_cancel_required' => $manual_paypal_cancel_required,
    'paypal_manage_url' => $result['paypal_manage_url'] ?? paypal_manage_url(),
    'support_email' => $result['support_email'] ?? 'shaf@talktoai.org',
    'remote_cancelled' => (bool) ($result['remote_cancelled'] ?? false),
    'remote_reason' => $result['remote_reason'] ?? null,
]);
