<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function chat_manager_reply($payload, $status = 200) {
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$user = function_exists('current_user_record') ? current_user_record() : null;
$user_id = (int) ($user['id'] ?? 0);
if ($user_id <= 0) {
    chat_manager_reply(['status' => 'error', 'message' => 'Auth required.'], 403);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    chat_manager_reply(['status' => 'error', 'message' => 'Method not allowed.'], 405);
}

$db = null;
try {
    $db = db();

    // GET is intentionally read-only. Mutating actions are never accepted from
    // query parameters, links, images, or top-level browser navigation.
    if ($method === 'GET') {
        $action = strtolower(trim((string) ($_GET['action'] ?? 'list')));
        if ($action === 'list') {
            $stmt = $db->prepare('SELECT id, title FROM chats WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
            $stmt->execute([$user_id]);
            chat_manager_reply($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        if ($action === 'get') {
            $chat_id = (int) ($_GET['id'] ?? 0);
            if ($chat_id <= 0) {
                chat_manager_reply(['status' => 'error', 'message' => 'Valid chat id required.'], 422);
            }
            $stmt = $db->prepare(
                'SELECT m.role, m.content
                 FROM messages m
                 INNER JOIN chats c ON c.id = m.chat_id
                 WHERE m.chat_id = ? AND c.user_id = ?
                 ORDER BY m.id ASC'
            );
            $stmt->execute([$chat_id, $user_id]);
            chat_manager_reply($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        chat_manager_reply(['status' => 'error', 'message' => 'Unknown read action.'], 400);
    }

    $content_type = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (strpos($content_type, 'application/json') !== 0) {
        chat_manager_reply(['status' => 'error', 'message' => 'JSON request required.'], 415);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        chat_manager_reply(['status' => 'error', 'message' => 'Invalid JSON request.'], 400);
    }

    $csrf_token = trim((string) ($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($csrf_token)) {
        chat_manager_reply([
            'status' => 'error',
            'message' => 'Your secure session expired. Refresh Studio and try again.',
        ], 403);
    }

    $action = strtolower(trim((string) ($input['action'] ?? '')));
    if ($action === 'create') {
        $title = trim((string) ($input['title'] ?? ''));
        $title = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $title));
        if ($title === '') {
            $title = 'New chat';
        }
        if (strlen($title) > 160) {
            $title = substr($title, 0, 160);
        }

        $stmt = $db->prepare('INSERT INTO chats (user_id, title) VALUES (?, ?)');
        $stmt->execute([$user_id, $title]);
        chat_manager_reply([
            'status' => 'success',
            'id' => (int) $db->lastInsertId(),
        ]);
    }

    if ($action === 'delete') {
        $chat_id = (int) ($input['id'] ?? 0);
        if ($chat_id <= 0) {
            chat_manager_reply(['status' => 'error', 'message' => 'Valid chat id required.'], 422);
        }

        $db->beginTransaction();
        $owned = $db->prepare('SELECT id FROM chats WHERE id = ? AND user_id = ? FOR UPDATE');
        $owned->execute([$chat_id, $user_id]);
        if (!$owned->fetchColumn()) {
            $db->rollBack();
            chat_manager_reply(['status' => 'error', 'message' => 'Chat not found.'], 404);
        }

        // Ownership is re-stated in the destructive SQL as defense in depth.
        $delete_messages = $db->prepare(
            'DELETE m FROM messages m
             INNER JOIN chats c ON c.id = m.chat_id
             WHERE m.chat_id = ? AND c.user_id = ?'
        );
        $delete_messages->execute([$chat_id, $user_id]);

        $delete_chat = $db->prepare('DELETE FROM chats WHERE id = ? AND user_id = ?');
        $delete_chat->execute([$chat_id, $user_id]);
        if ($delete_chat->rowCount() !== 1) {
            throw new RuntimeException('Owned chat delete affected an unexpected number of rows.');
        }

        $db->commit();
        chat_manager_reply(['status' => 'success', 'message' => 'Chat deleted.']);
    }

    chat_manager_reply(['status' => 'error', 'message' => 'Unknown write action.'], 400);
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    try {
        $request_id = bin2hex(random_bytes(6));
    } catch (Throwable $id_error) {
        $request_id = substr(hash('sha256', microtime(true) . ':' . getmypid()), 0, 12);
    }
    error_log('ZeroThink chat manager error [' . $request_id . ']: ' . $e->getMessage());
    chat_manager_reply([
        'status' => 'error',
        'message' => 'The chat request could not be completed safely. Try again or contact support with reference ' . $request_id . '.',
        'request_id' => $request_id,
    ], 500);
}
