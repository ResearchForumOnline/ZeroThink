<?php
require_once __DIR__ . '/app/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

// ACCESS DENIED
$adminUser = function_exists('current_user_record') ? current_user_record() : null;
if (!function_exists('is_architect_user') || !is_architect_user($adminUser)) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}
$csrfToken = zt_csrf_token();

// DELETE HANDLER
if (isset($_POST['delete_id'])) {
    if (!zt_csrf_validate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Security token expired.');
    }
    $stmt = db()->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->execute([$_POST['delete_id']]);
    header("Location: /admin_feedback");
    exit;
}

$rows = db()->query("SELECT f.*, u.email FROM feedback f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Zero Command | Feedback</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #050505; color: #e0e0e0; font-family: 'Courier New', monospace; padding: 0; margin: 0; }
        .header { background: #111; padding: 20px; border-bottom: 2px solid #a855f7; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 40px auto; padding: 20px; display: grid; gap: 30px; }
        .card { background: #0a0a0a; border: 1px solid #222; border-left: 5px solid #a855f7; padding: 20px; position: relative; }
        .meta { color: #00ffff; font-weight: bold; margin-bottom: 10px; display: block; }
        .msg { font-size: 1.2rem; color: #fff; background: #151515; padding: 20px; border-radius: 5px; white-space: pre-wrap; line-height: 1.6; }
        .del-btn { position: absolute; top: 20px; right: 20px; background: #300; color: #f55; border: 1px solid #500; padding: 5px 15px; cursor: pointer; }
        .back-btn { background: #333; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin:0; font-size:1.5rem;">FEEDBACK LOG [<?php echo count($rows); ?>]</h1>
        <a href="/studio" class="back-btn">EXIT</a>
    </div>
    <div class="container">
        <?php foreach($rows as $r): ?>
            <div class="card">
                <form method="POST" onsubmit="return confirm('Delete?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="delete_id" value="<?php echo $r['id']; ?>">
                    <button class="del-btn">DELETE</button>
                </form>
                <span class="meta"><?php echo htmlspecialchars($r['email']); ?> | <?php echo $r['created_at']; ?></span>
                <div class="msg"><?php echo htmlspecialchars($r['message']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
