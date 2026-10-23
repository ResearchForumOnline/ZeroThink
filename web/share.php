<?php
require_once __DIR__ . '/app/bootstrap.php';
$token = $_GET['id'] ?? '';
if(!$token) { header("Location: /"); exit; }
$db = db();
$c = $db->query("SELECT * FROM chat_history WHERE share_token = '$token'")->fetch();
if(!$c) die("<h1 style='color:#fff;text-align:center;background:#000;padding:50px;'>LINK EXPIRED</h1>");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>ZeroThink Shared</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        body { background:#050505; color:#ccc; font-family:'Inter',sans-serif; padding:40px; margin:0; }
        .container { max-width:800px; margin:0 auto; }
        .header { border-bottom:1px solid #333; padding-bottom:20px; margin-bottom:40px; display:flex; justify-content:space-between; }
        .u-msg { background:#111; padding:20px; border-radius:8px; color:#fff; margin-bottom:20px; }
        .a-msg { border-left:3px solid #00ffff; padding:10px 20px; color:#ddd; }
        .btn { background:#00ffff; color:#000; padding:10px 20px; text-decoration:none; font-weight:bold; border-radius:4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span style="font-weight:bold; color:#fff;">ZeroThink Shared</span>
            <a href="/" class="btn">TRY ZERO</a>
        </div>
        <h2 style="color:#fff;"><?= htmlspecialchars($c['title']) ?></h2>
        <div class="u-msg"><?= nl2br(htmlspecialchars($c['query'])) ?></div>
        <div class="a-msg" id="resp"></div>
    </div>
    <script>
        const r = `<?= str_replace('`', '\`', str_replace('\\', '\\\\', $c['response'])) ?>`;
        document.getElementById('resp').innerHTML = marked.parse(r);
    </script>
</body>
</html>
