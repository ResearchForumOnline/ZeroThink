<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/core/research_tools.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

$user = current_user_record();
$userId = (int) ($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $templates = zt_default_research_templates();
    if ($userId > 0) {
        $templates = array_merge($templates, zt_load_user_research_templates($userId));
    }

    echo json_encode([
        'status' => 'success',
        'templates' => $templates,
        'note' => 'Templates are user-facing reasoning scaffolds, not hidden chain-of-thought.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Sign in before saving custom research templates.']);
        exit;
    }

    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (strpos($contentType, 'application/json') !== 0) {
        http_response_code(415);
        echo json_encode(['status' => 'error', 'message' => 'JSON request required.']);
        exit;
    }

    $raw = (string)file_get_contents('php://input');
    if (strlen($raw) > 65536) {
        http_response_code(413);
        echo json_encode(['status' => 'error', 'message' => 'Template payload is too large.']);
        exit;
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid template payload.']);
        exit;
    }
    if (!zt_csrf_validate($payload['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Security token expired.']);
        exit;
    }
    unset($payload['csrf_token']);

    try {
        $template = zt_save_user_research_template($payload, $userId);
    } catch (Throwable $e) {
        error_log('Research template save failed: ' . $e->getMessage());
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Could not save that template. Check the fields and try again.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'template' => $template,
        'note' => 'Saved as a visible research process template. Hidden chain-of-thought is not stored.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
