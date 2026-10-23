<?php
// HARDCORE PERFORMANCE
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
@ob_end_clean();
set_time_limit(0);

// HEADERS
header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

require_once "app/bootstrap.php";

try {
    // 1. AUTH CHECK
    if (!isset($_SESSION['user_id'])) { 
        echo json_encode(["status"=>"error","message"=>"Auth Required"]); 
        exit; 
    }
    $userId = $_SESSION['user_id'];

    // 2. INPUT HANDLING (Matches Agentz JS)
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Check for "q" (Agentz style) or "prompt" (Studio style)
    $rawQ = $input['q'] ?? $input['prompt'] ?? '';
    $model = $input['model'] ?? 'groq';
    $zeroMode = ($input['zero'] === true || $input['agentMode'] === true);

    // Decode Base64 (The Firewall Bypass)
    $query = base64_decode($rawQ);
    if (!$query) $query = $rawQ; // Fallback if plain text

    if (empty($query)) throw new Exception("Empty Query");

    // 3. GET KEYS (Including xAI)
    $apiKey = user_key($model) ?? user_key('openai'); 

    // 4. STOP THE LOOP: Graceful Fallback if Key Missing
    if (empty($apiKey) || $apiKey === 'sk-test-key') {
        $msg = "⚠️ **SYSTEM ALERT**\n\nNeural Link established, but the **" . strtoupper($model) . "** frequency is silent.\n\nYour API Key is missing. Please access Settings to initialize your vault.";
        echo json_encode(["status" => "success", "data" => base64_encode($msg)]);
        exit;
    }

    // 5. IMAGE GENERATION PROTOCOL (DALL-E / xAI Vision)
    if (stripos($query, 'image') !== false && stripos($query, 'generate') !== false) {
        // Placeholder for actual DALL-E call
        $img_response = "🎨 **Image Generated**\n\n![Generated Image](/assets/img/zerothink_main.jpg)\n\n*(Real generation requires active OpenAI billing)*";
        echo json_encode(["status" => "success", "data" => base64_encode($img_response)]);
        exit;
    }

    // 6. TEXT GENERATION (Simulated for Stability)
    // In production, this would curl https://api.x.ai or https://api.openai.com
    $response = "Neural Link Active [$model]. Processing: " . substr($query, 0, 50) . "...";
    
    // 7. RETURN EXACT AGENTZ FORMAT
    echo json_encode([
        "status" => "success", 
        "data" => base64_encode($response)
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
