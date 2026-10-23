<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function share_api_reply($payload, $status = 200) {
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    share_api_reply(['status' => 'error', 'message' => 'POST required.'], 405);
}

$user = function_exists('current_user_record') ? current_user_record() : null;
$uid = (int) ($user['id'] ?? 0);
if ($uid <= 0) {
    share_api_reply(['status' => 'error', 'message' => 'Not logged in.'], 403);
}

$content_type = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (strpos($content_type, 'application/json') !== 0) {
    share_api_reply(['status' => 'error', 'message' => 'JSON request required.'], 415);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    share_api_reply(['status' => 'error', 'message' => 'Invalid JSON request.'], 400);
}

$csrf_token = trim((string) ($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($csrf_token)) {
    share_api_reply([
        'status' => 'error',
        'message' => 'Your secure session expired. Refresh Memory Stream and try again.',
    ], 403);
}

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
    share_api_reply(['status' => 'error', 'message' => 'Valid history id required.'], 422);
}

$db = null;
try {
    $db = db();
    $db->beginTransaction();

    $check = $db->prepare('SELECT share_token FROM chat_history WHERE id = ? AND user_id = ? FOR UPDATE');
    $check->execute([$id, $uid]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $db->rollBack();
        share_api_reply(['status' => 'error', 'message' => 'History item not found.'], 404);
    }

    $token = trim((string) ($row['share_token'] ?? ''));
    if ($token === '') {
        $token = bin2hex(random_bytes(16));
        $update = $db->prepare('UPDATE chat_history SET share_token = ? WHERE id = ? AND user_id = ?');
        $update->execute([$token, $id, $uid]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Share token update affected an unexpected number of rows.');
        }
    }

    $db->commit();
    share_api_reply([
        'status' => 'success',
        'url' => 'https://zerothink.talktoai.org/share.php?id=' . rawurlencode($token),
    ]);
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    try {
        $request_id = bin2hex(random_bytes(6));
    } catch (Throwable $id_error) {
        $request_id = substr(hash('sha256', microtime(true) . ':' . getmypid()), 0, 12);
    }
    error_log('ZeroThink share API error [' . $request_id . ']: ' . $e->getMessage());
    share_api_reply([
        'status' => 'error',
        'message' => 'The share request could not be completed safely. Try again or contact support with reference ' . $request_id . '.',
        'request_id' => $request_id,
    ], 500);
}
