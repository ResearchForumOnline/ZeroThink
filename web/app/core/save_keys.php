<?php
// ZT_SAVE_KEYS_V35
// Handles API Key Updates safely.

function handle_save_keys() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: /settings"); exit;
    }

    $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) { header("Location: /login"); exit; }

    try {
        $db = db();
        foreach (['api_key_groq', 'api_key_google', 'api_key_gemini', 'api_key_openai', 'api_key_xai', 'api_key_seedance', 'api_key_byteplus', 'api_key_anthropic', 'api_key_alphagenome', 'api_key_ncbi'] as $col) {
            try {
                $db->query("SELECT {$col} FROM users LIMIT 1");
            } catch (Exception $e) {
                $db->exec("ALTER TABLE users ADD COLUMN {$col} TEXT NULL");
            }
        }
        
        // Prepare Data
        $groq = trim($_POST['groq'] ?? '');
        $gemini = trim($_POST['gemini'] ?? '');
        $openai = trim($_POST['openai'] ?? '');
        $xai = trim($_POST['xai'] ?? '');
        $seedance = trim($_POST['seedance'] ?? '');
        $claude = trim($_POST['claude'] ?? '');
        $alphagenome = trim($_POST['alphagenome'] ?? '');
        $ncbi = trim($_POST['ncbi'] ?? '');
        
        // Update Query
        $sql = "UPDATE users SET 
                api_key_groq = ?, 
                api_key_google = ?, 
                api_key_gemini = ?, 
                api_key_openai = ?, 
                api_key_xai = ?,
                api_key_seedance = ?,
                api_key_byteplus = ?,
                api_key_anthropic = ?,
                api_key_alphagenome = ?,
                api_key_ncbi = ?
                WHERE id = ?";
                
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([$groq, $gemini, $gemini, $openai, $xai, $seedance, $seedance, $claude, $alphagenome, $ncbi, $userId]);

        if ($result) {
            header("Location: /settings?msg=saved");
        } else {
            header("Location: /settings?error=db_fail");
        }
        exit;

    } catch (Exception $e) {
        // Log error but don't show blank page
        error_log("Save Keys Error: " . $e->getMessage());
        header("Location: /settings?error=system_error");
        exit;
    }
}
