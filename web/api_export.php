<?php
require_once __DIR__ . '/app/bootstrap.php';
if (!isset($_SESSION['user_id'])) exit;
$db = db();
$select_model = has_chat_history_model_column() ? 'model' : 'NULL AS model';
$stmt = $db->prepare("SELECT query, response, timestamp, {$select_model} FROM chat_history WHERE user_id = ? ORDER BY id ASC");
$stmt->execute([$_SESSION['user_id']]);
$chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$content = "ZeroThink: The Sovereign Reasoning Layer\nExport Date: " . date('Y-m-d H:i:s') . "\n" . str_repeat("=", 40) . "\n\n";
foreach($chats as $c) { 
    $content .= "[USER] (" . ($c['timestamp'] ?? '') . ")\n" . ($c['query'] ?? '') . "\n\n";
    $content .= "[ZEROTHINK" . (!empty($c['model']) ? " · " . $c['model'] : "") . "]\n" . ($c['response'] ?? '') . "\n\n";
}

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="ZeroThink_Memory_'.date('Ymd').'.txt"');
echo $content;
