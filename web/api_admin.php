<?php
require_once __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// SECURITY CHECK: MUST BE ADMIN
$adminUser = function_exists('current_user_record') ? current_user_record() : null;
$adminAllowed = function_exists('is_architect_user') && is_architect_user($adminUser);
if (!$adminAllowed) { http_response_code(403); die(json_encode(["error"=>"Forbidden"])); }

// LIST USERS
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->query("SELECT id, email, role, subscription_status FROM users ORDER BY id DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    die(json_encode(['error' => 'Method not allowed']));
}

// UPDATE USER
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || !zt_csrf_validate($in['csrf_token'] ?? '')) {
    http_response_code(403);
    die(json_encode(['error' => 'Security token expired']));
}
$id = (int) ($in['id'] ?? 0);
$status = strtolower(trim((string) ($in['status'] ?? '')));
if ($id > 0 && in_array($status, ['active', 'free'], true)) {
    $stmt = db()->prepare("UPDATE users SET subscription_status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    echo json_encode(["status"=>"success"]);
    exit;
}
http_response_code(422);
echo json_encode(['error' => 'Invalid user or status']);
