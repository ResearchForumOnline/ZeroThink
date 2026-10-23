<?php
require_once __DIR__ . '/app/bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location:/');
    exit;
}

$current = current_user();
if (!$current || (($current['role'] ?? '') !== 'admin' && empty($current['is_pro']))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$db = db();

// Fetch Nodes
$nodes = $db->query("SELECT * FROM hive_nodes ORDER BY last_seen DESC")->fetchAll(PDO::FETCH_ASSOC);
// Fetch Recent Knowledge
$knowledge = $db->query("SELECT * FROM hive_knowledge ORDER BY timestamp DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Zero Lattice // Hive Status</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #050505; color: #00ff41; font-family: monospace; padding: 40px; }
        h1, h2 { color: #fff; border-bottom: 1px solid #333; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; background: rgba(0,255,65,0.02); }
        th, td { border: 1px solid #222; padding: 12px; text-align: left; }
        th { background: #111; color: #d4af37; }
        tr:hover { background: rgba(0,255,65,0.05); }
        .status-online { color: #00ff41; font-weight: bold; }
        .status-offline { color: #ff3333; }
        .sig { font-size: 0.7em; color: #666; word-break: break-all; }
    </style>
</head>
<body>
    <h1><i class="fas fa-hive"></i> SOVEREIGN HIVE MONITOR</h1>
    
    <h2>ACTIVE LATTICE NODES</h2>
    <table>
        <tr>
            <th>Node ID</th>
            <th>Capability</th>
            <th>Fee ($AI)</th>
            <th>Last Seen</th>
            <th>Status</th>
        </tr>
        <?php foreach ($nodes as $n): ?>
        <tr>
            <td><code><?php echo htmlspecialchars($n['node_id']); ?></code></td>
            <td><?php echo strtoupper($n['compute'] ?? 'CPU'); ?></td>
            <td><?php echo number_format($n['fee'], 2); ?></td>
            <td><?php echo $n['last_seen']; ?></td>
            <td class="status-online">ONLINE</td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>DISTRIBUTED KNOWLEDGE BASE</h2>
    <table>
        <tr>
            <th>Timestamp</th>
            <th>Node</th>
            <th>Prompt Snippet</th>
            <th>Answer Hash</th>
        </tr>
        <?php foreach ($knowledge as $k): ?>
        <tr>
            <td><?php echo $k['timestamp']; ?></td>
            <td><code><?php echo htmlspecialchars($k['node_id']); ?></code></td>
            <td><?php echo htmlspecialchars(substr($k['prompt'], 0, 100)); ?>...</td>
            <td class="sig"><?php echo $k['hash']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
