<?php
// ZERO HIVE CENTRAL - PHP ADAPTER
require_once __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json');

$db = db();
$in = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

// --- IDENTITY VERIFICATION (RSA) ---
function verify_signature($data, $signature, $public_key) {
    if (empty($signature) || empty($public_key)) return false;
    $msg = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pub = openssl_get_publickey($public_key);
    return openssl_verify($msg, hex2bin($signature), $pub, OPENSSL_ALGO_SHA256) === 1;
}

try {
    if ($action === 'register') {
        $node_id = $in['id'] ?? '';
        $pub_key = $in['public_key'] ?? '';
        $fee = $in['fee_zero_coins'] ?? 0;
        
        if (!$node_id || !$pub_key) throw new Exception("Missing Node Identity");

        // Use a DB table to track active nodes
        $db->exec("CREATE TABLE IF NOT EXISTS hive_nodes (
            node_id VARCHAR(50) PRIMARY KEY,
            public_key TEXT,
            fee FLOAT,
            last_seen DATETIME,
            status VARCHAR(20) DEFAULT 'online'
        )");

        $stmt = $db->prepare("INSERT INTO hive_nodes (node_id, public_key, fee, last_seen) 
                             VALUES (?, ?, ?, NOW()) 
                             ON DUPLICATE KEY UPDATE fee=?, last_seen=NOW(), status='online'");
        $stmt->execute([$node_id, $pub_key, $fee, $fee]);
        
        echo json_encode(['status' => 'success', 'message' => 'Lattice Link Established']);
    } 
    elseif ($action === 'contribute') {
        $node_id = $in['node_id'] ?? '';
        $payload = $in['data'] ?? [];
        $sig = $in['signature'] ?? '';
        
        // Fetch Public Key from DB
        $s = $db->prepare("SELECT public_key FROM hive_nodes WHERE node_id=?");
        $s->execute([$node_id]);
        $pub = $s->fetchColumn();
        
        if (!$pub) throw new Exception("Unknown Node");
        
        // Verify Signature
        if (!verify_signature($payload, $sig, $pub)) {
            throw new Exception("Signature Mismatch - Sovereignty Denied");
        }

        // Store shared knowledge
        $db->exec("CREATE TABLE IF NOT EXISTS hive_knowledge (
            id INT AUTO_INCREMENT PRIMARY KEY,
            node_id VARCHAR(50),
            hash VARCHAR(64),
            prompt TEXT,
            answer TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(hash)
        )");

        $stmt = $db->prepare("INSERT INTO hive_knowledge (node_id, hash, prompt, answer) VALUES (?, ?, ?, ?)");
        $stmt->execute([$node_id, $payload['hash'], $payload['prompt'], $payload['answer']]);

        echo json_encode(['status' => 'success', 'message' => 'Knowledge Synced']);
    }
    elseif ($action === 'search') {
        $q = $in['query'] ?? '';
        $hash = md5($q);
        
        $stmt = $db->prepare("SELECT answer FROM hive_knowledge WHERE hash=? ORDER BY timestamp DESC LIMIT 1");
        $stmt->execute([$hash]);
        $ans = $stmt->fetchColumn();
        
        echo json_encode(['status' => 'success', 'data' => $ans]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
