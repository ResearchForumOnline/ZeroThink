<?php
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function knowledge_api_reply($payload, $status = 200) {
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$user = function_exists('current_user_record') ? current_user_record() : null;
$is_architect = $user && function_exists('is_architect_user') && is_architect_user($user);
$has_god_plan = strtolower((string) ($user['plan'] ?? '')) === 'god_mode';
if (!$user || (!$is_architect && !$has_god_plan)) {
    knowledge_api_reply(['status' => 'error', 'message' => 'Access denied.'], 403);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    knowledge_api_reply(['status' => 'error', 'message' => 'Method not allowed.'], 405);
}

$directory = __DIR__ . '/app/data';
$file = $directory . '/neural_core.json';

if ($method === 'GET') {
    if (!is_readable($file)) {
        knowledge_api_reply(['content' => '']);
    }
    $saved = json_decode((string) file_get_contents($file), true);
    knowledge_api_reply(['content' => is_array($saved) ? (string) ($saved['content'] ?? '') : '']);
}

$content_type = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (strpos($content_type, 'application/json') !== 0) {
    knowledge_api_reply(['status' => 'error', 'message' => 'JSON request required.'], 415);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    knowledge_api_reply(['status' => 'error', 'message' => 'Invalid JSON request.'], 400);
}

$csrf_token = trim((string) ($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($csrf_token)) {
    knowledge_api_reply([
        'status' => 'error',
        'message' => 'Your secure session expired. Refresh Knowledge Core and try again.',
    ], 403);
}

try {
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Knowledge data directory could not be created.');
    }

    $encoded = json_encode(
        ['content' => (string) ($input['content'] ?? '')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if ($encoded === false) {
        throw new RuntimeException('Knowledge content could not be encoded.');
    }

    $temporary = $file . '.tmp.' . bin2hex(random_bytes(6));
    $written = file_put_contents($temporary, $encoded, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException('Knowledge temporary file could not be written.');
    }
    @chmod($temporary, 0600);
    if (!rename($temporary, $file)) {
        @unlink($temporary);
        throw new RuntimeException('Knowledge file could not be activated.');
    }
    @chmod($file, 0600);
    knowledge_api_reply(['status' => 'success']);
} catch (Throwable $e) {
    try {
        $request_id = bin2hex(random_bytes(6));
    } catch (Throwable $id_error) {
        $request_id = substr(hash('sha256', microtime(true) . ':' . getmypid()), 0, 12);
    }
    error_log('ZeroThink knowledge API error [' . $request_id . ']: ' . $e->getMessage());
    knowledge_api_reply([
        'status' => 'error',
        'message' => 'Knowledge Core could not be updated safely. The previous data remains in place. Contact support with reference ' . $request_id . '.',
        'request_id' => $request_id,
    ], 500);
}
