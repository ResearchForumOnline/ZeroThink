<?php
require_once "app/bootstrap.php";
header('Content-Type: application/json');

// 1. Receive & Decode Input
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// 64-bit Firewall Bypass (Base64 Decode)
$raw_prompt = $data['prompt'] ?? $data['message'] ?? '';
$prompt = (function_exists('neural_decode')) ? neural_decode($raw_prompt) : base64_decode($raw_prompt);

// 2. Determine Provider & Mode
$provider = $data['provider'] ?? 'openai';
$is_image_request = (strpos(strtolower($prompt), 'image') !== false || strpos(strtolower($prompt), 'generate') !== false);

// 3. Fetch API Key (Support for xAI added)
$apiKey = user_key($provider);

// 4. STOP THE LOOP: If key is missing, return SUCCESS with a warning message.
if (empty($apiKey) || $apiKey === 'sk-test-key') {
    $error_msg = "⚠️ **NEURAL LINK STANDBY**\n\n";
    $error_msg .= "The **" . strtoupper($provider) . "** frequency is silent.\n";
    $error_msg .= "Please add your API Key in Settings to activate this channel.";
    
    echo json_encode([
        'status' => 'success', // This stops the JS retry loop
        'response' => $error_msg,
        'data' => base64_encode($error_msg) // For legacy JS compatibility
    ]);
    exit;
}

// 5. xAI / Image Gen Routing Placeholder
// In a full build, this is where we curl the xAI/OpenAI endpoints.
$response_text = "Neural Link Active [$provider]. Processing: " . substr($prompt, 0, 30) . "...";

echo json_encode([
    'status' => 'success',
    'response' => $response_text,
    'data' => base64_encode($response_text)
]);
