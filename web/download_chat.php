<?php
require_once __DIR__ . '/app/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Not logged in.');
}

$chat_id = (int) ($_GET['chat_id'] ?? 0);
if ($chat_id <= 0) {
    http_response_code(400);
    exit('Missing chat_id.');
}

$db = db();
$select_model = has_chat_history_model_column() ? 'model' : 'NULL AS model';
$stmt = $db->prepare("SELECT id, title, query, response, {$select_model}, timestamp FROM chat_history WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$chat_id, (int) $_SESSION['user_id']]);
$chat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chat) {
    http_response_code(404);
    exit('Chat not found.');
}

$title = trim((string) ($chat['title'] ?: 'ZeroThink_Chat_' . $chat['id']));
$safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $title);
$safe = trim($safe, '_');
if ($safe === '') {
    $safe = 'ZeroThink_Chat_' . $chat['id'];
}

$body = [];
$body[] = 'ZeroThink Conversation Export';
$body[] = 'ID: ' . $chat['id'];
$body[] = 'Title: ' . $title;
$body[] = 'Timestamp: ' . ($chat['timestamp'] ?? '');
$body[] = 'Model: ' . ($chat['model'] ?? '');
$body[] = str_repeat('-', 72);
$body[] = 'USER';
$body[] = (string) $chat['query'];
$body[] = str_repeat('-', 72);
$body[] = 'ZEROTHINK';
$body[] = (string) $chat['response'];
$body[] = '';

$content = implode(PHP_EOL, $body);

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $safe . '.txt"');
header('Content-Length: ' . strlen($content));
echo $content;
?>
