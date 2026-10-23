<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function history_api_reply($payload, $status = 200) {
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    history_api_reply(['status' => 'error', 'message' => 'POST required.'], 405);
}

$user = function_exists('current_user_record') ? current_user_record() : null;
$uid = (int) ($user['id'] ?? 0);
if ($uid <= 0) {
    history_api_reply(['status' => 'error', 'message' => 'Not logged in.'], 403);
}

$content_type = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (strpos($content_type, 'application/json') !== 0) {
    history_api_reply(['status' => 'error', 'message' => 'JSON request required.'], 415);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    history_api_reply(['status' => 'error', 'message' => 'Invalid JSON request.'], 400);
}

$csrf_token = trim((string) ($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($csrf_token)) {
    history_api_reply([
        'status' => 'error',
        'message' => 'Your secure session expired. Refresh Memory Stream and try again.',
    ], 403);
}

$action = strtolower(trim((string) ($input['action'] ?? '')));
if (!in_array($action, ['delete', 'rename', 'nuke', 'wipe_memory'], true)) {
    history_api_reply(['status' => 'error', 'message' => 'Unknown history action.'], 400);
}

try {
    $db = db();

    if ($action === 'delete') {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
            history_api_reply(['status' => 'error', 'message' => 'Valid history id required.'], 422);
        }
        $stmt = $db->prepare('DELETE FROM chat_history WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        if ($stmt->rowCount() !== 1) {
            history_api_reply(['status' => 'error', 'message' => 'History item not found.'], 404);
        }
        history_api_reply(['status' => 'success']);
    }

    if ($action === 'rename') {
        $id = (int) ($input['id'] ?? 0);
        $title = trim((string) ($input['title'] ?? ''));
        $title = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $title));
        if ($id <= 0 || $title === '') {
            history_api_reply(['status' => 'error', 'message' => 'Valid history id and title required.'], 422);
        }
        if (strlen($title) > 160) {
            $title = substr($title, 0, 160);
        }
        $stmt = $db->prepare('UPDATE chat_history SET title = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$title, $id, $uid]);
        if ($stmt->rowCount() > 1) {
            throw new RuntimeException('History rename affected an unexpected number of rows.');
        }
        history_api_reply(['status' => 'success']);
    }

    if ($action === 'nuke') {
        $stmt = $db->prepare('DELETE FROM chat_history WHERE user_id = ?');
        $stmt->execute([$uid]);
        history_api_reply(['status' => 'success', 'deleted' => (int) $stmt->rowCount()]);
    }

    $memory_file = dirname(__DIR__) . '/.zerothink_memories/mem_' . $uid . '.txt';
    if (is_file($memory_file) && !unlink($memory_file)) {
        throw new RuntimeException('Memory file could not be removed.');
    }
    history_api_reply(['status' => 'success']);
} catch (Throwable $e) {
    try {
        $request_id = bin2hex(random_bytes(6));
    } catch (Throwable $id_error) {
        $request_id = substr(hash('sha256', microtime(true) . ':' . getmypid()), 0, 12);
    }
    error_log('ZeroThink history API error [' . $request_id . ']: ' . $e->getMessage());
    history_api_reply([
        'status' => 'error',
        'message' => 'The history request could not be completed safely. Try again or contact support with reference ' . $request_id . '.',
        'request_id' => $request_id,
    ], 500);
}
