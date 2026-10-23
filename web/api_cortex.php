<?php
require_once __DIR__ . '/app/bootstrap.php';
$input = json_decode(file_get_contents('php://input'), true);
if (!empty($input['data'])) {
    // Append to system prompt or a vector log
    // For now, we append to a knowledge file that the Agent can read
    file_put_contents(__DIR__ . '/data/neural_core.json', json_encode(['timestamp'=>time(), 'content'=>$input['data']]) . "\n", FILE_APPEND);
    echo json_encode(['status'=>'success']);
}
