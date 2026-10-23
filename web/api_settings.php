<?php
require_once __DIR__ . '/app/bootstrap.php';
if (!isset($_SESSION['user_id'])) exit;
$in = json_decode(file_get_contents('php://input'), true);

if (isset($in['keys'])) {
    $db = db();
    // Save all keys including NVIDIA and Serper
    $sql = "UPDATE users SET api_key_groq=?, api_key_nvidia=?, api_key_openai=?, api_key_serper=?, system_prompt=? WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $in['keys']['groq'],
        $in['keys']['nvidia'],
        $in['keys']['openai'],
        $in['keys']['serper'],
        $in['keys']['persona'],
        $_SESSION['user_id']
    ]);
    echo json_encode(["status"=>"success"]);
} else {
    $u = current_user();
    echo json_encode([
        "groq" => $u['api_key_groq'] ?? '',
        "nvidia" => $u['api_key_nvidia'] ?? '',
        "openai" => $u['api_key_openai'] ?? '',
        "serper" => $u['api_key_serper'] ?? '',
        "persona" => $u['system_prompt'] ?? ''
    ]);
}
?>
