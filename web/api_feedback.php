<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function feedback_api_reply($payload, $status = 200) {
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    feedback_api_reply(['status' => 'error', 'message' => 'POST required.'], 405);
}

$user = function_exists('current_user_record') ? current_user_record() : null;
$uid = (int) ($user['id'] ?? 0);
if ($uid <= 0) {
    feedback_api_reply(['status' => 'error', 'message' => 'Login required.'], 403);
}

$content_type = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (strpos($content_type, 'application/json') !== 0) {
    feedback_api_reply(['status' => 'error', 'message' => 'JSON request required.'], 415);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    feedback_api_reply(['status' => 'error', 'message' => 'Invalid JSON request.'], 400);
}

$csrf_token = trim((string) ($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($csrf_token)) {
    feedback_api_reply([
        'status' => 'error',
        'message' => 'Your secure session expired. Refresh Studio and try again.',
    ], 403);
}

$message = trim((string) ($input['message'] ?? ''));
$message = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $message));
if ($message === '') {
    feedback_api_reply(['status' => 'error', 'message' => 'Empty message.'], 422);
}
if (strlen($message) > 10000) {
    feedback_api_reply(['status' => 'error', 'message' => 'Feedback is too long (maximum 10,000 characters).'], 422);
}

try {
    $stmt = db()->prepare('INSERT INTO feedback (user_id, message) VALUES (?, ?)');
    $stmt->execute([$uid, $message]);
    feedback_api_reply(['status' => 'success']);
} catch (Throwable $e) {
    try {
        $request_id = bin2hex(random_bytes(6));
    } catch (Throwable $id_error) {
        $request_id = substr(hash('sha256', microtime(true) . ':' . getmypid()), 0, 12);
    }
    error_log('ZeroThink feedback API error [' . $request_id . ']: ' . $e->getMessage());
    feedback_api_reply([
        'status' => 'error',
        'message' => 'Feedback could not be saved safely. Try again or contact support with reference ' . $request_id . '.',
        'request_id' => $request_id,
    ], 500);
}
