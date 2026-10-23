<?php
// ERROR REPORTING OFF
ini_set('display_errors', 0);
require_once __DIR__ . '/app/bootstrap.php';
if(!isset($_SESSION['user_id'])) { header('Location:/'); exit; }

$db = db();
$uid = $_SESSION['user_id'];
$select_model = has_chat_history_model_column() ? "model" : "NULL AS model";
$stmt = $db->prepare("SELECT id, title, query, response, {$select_model}, timestamp FROM chat_history WHERE user_id = ? ORDER BY timestamp DESC LIMIT 100");
$stmt->execute([$uid]);
$chats = $stmt->fetchAll();

// --- LOAD MEMORY ---
$mem_file = dirname(__DIR__) . '/.zerothink_memories/mem_' . $uid . '.txt';
$memory = file_exists($mem_file) ? file_get_contents($mem_file) : "";
if(empty($memory)) $memory = "No long-term memories established yet.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Memory Stream</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        body { background:#050505; color:#e0e0e0; font-family:'Inter',sans-serif; padding:40px; margin:0; }
        .container { max-width:1000px; margin:0 auto; padding-bottom: 100px; }
        .header { display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #333; padding-bottom:20px; margin-bottom:30px; }
        h1 { color:#fff; font-size:1.5rem; margin:0; }
        .back-btn { color:#888; text-decoration:none; font-weight:bold; }
        .chat-card { background:#111; border:1px solid #222; padding:20px; border-radius:12px; margin-bottom:15px; }
        .chat-meta { color:#777; font-size:0.8rem; margin-top:8px; display:flex; gap:14px; flex-wrap:wrap; }
        .preview-row { margin-top:14px; display:grid; gap:10px; }
        .preview-block { background:#0a0a0f; border:1px solid #1d1d28; border-radius:10px; padding:12px 14px; }
        .preview-label { color:#a855f7; font-size:0.72rem; font-weight:bold; letter-spacing:1px; margin-bottom:6px; text-transform:uppercase; }
        .preview-text { color:#cfcfcf; font-size:0.92rem; line-height:1.5; }
        .chat-title-input { background:transparent; border:none; color:#fff; font-weight:bold; font-size:1.1rem; width:70%; outline:none; }
        .actions { display:flex; gap:10px; border-top:1px solid #222; padding-top:15px; margin-top:10px; flex-wrap:wrap; }
        .act-btn { cursor:pointer; color:#666; font-size:0.85rem; display:flex; align-items:center; gap:6px; padding: 5px 12px; border-radius: 4px; background: rgba(255,255,255,0.05); }
        .act-btn:hover { color:#fff; }
        .act-btn.share { color: #00ffff; border: 1px solid rgba(0,255,255,0.3); }
        .act-btn.load { color: #a855f7; border: 1px solid rgba(168,85,247,0.3); }
        .act-btn.load:hover { background: rgba(168,85,247,0.2); }
        .nuke-btn { background:#300; color:#f88; padding:10px 20px; border-radius:6px; border:1px solid #500; cursor:pointer; }
        #view-modal { display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.95); }
        .modal-content { width: 800px; max-width: 90%; height: 90vh; margin: 5vh auto; background: #0a0a0a; border: 1px solid #333; padding:40px; overflow-y:auto; }
        .u-bubble { background: #1a1a1a; color: #fff; padding:15px; border-radius:8px; margin-bottom:10px; }
        .a-bubble { border-left: 3px solid #a855f7; color: #ddd; padding:15px; }
        
        /* MEMORY BOX STYLES */
        .memory-box { background:#0a0a10; border:1px solid #a855f7; padding:20px; border-radius:12px; margin-bottom:30px; position:relative; }
        .memory-title { color:#a855f7; font-weight:bold; margin-bottom:10px; display:flex; align-items:center; gap:10px; }
        .memory-content { font-family:'Courier New', monospace; font-size:0.9rem; color:#ccc; white-space:pre-wrap; max-height:150px; overflow-y:auto; background:rgba(0,0,0,0.3); padding:10px; border-radius:6px; }
        .mem-actions { position:absolute; top:20px; right:20px; }
        .mem-btn { background:rgba(168,85,247,0.1); color:#a855f7; border:1px solid #a855f7; padding:5px 10px; border-radius:4px; font-size:0.8rem; cursor:pointer; }
        .mem-btn:hover { background:rgba(168,85,247,0.3); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="/studio" class="back-btn"><i class="fas fa-arrow-left"></i> STUDIO</a>
            <h1>MEMORY STREAM</h1>
            <button class="nuke-btn" onclick="nukeHistory()">WIPE ALL</button>
        </div>

        <!-- MEMORY SECTION -->
        <div class="memory-box">
            <div class="memory-title">
                <i class="fas fa-brain"></i> ZEROTHINK LONG-TERM MEMORY
            </div>
            <div class="mem-actions">
                <button class="mem-btn" onclick="wipeMemory()"><i class="fas fa-eraser"></i> Clear Memory</button>
            </div>
            <div class="memory-content" id="mem-content"><?= htmlspecialchars($memory) ?></div>
        </div>

        <?php if (empty($chats)): ?>
        <div class="chat-card" style="text-align:center; padding:30px;">
            <div style="font-size:1.05rem; color:#fff; margin-bottom:8px;">No conversation history yet.</div>
            <div style="color:#888; font-size:0.92rem;">Your future ZeroThink chats and downloads will appear here once they are saved.</div>
        </div>
        <?php endif; ?>

        <?php foreach($chats as $c): ?>
        <div class="chat-card" id="c-<?= $c['id'] ?>">
            <input type="text" class="chat-title-input" value="<?= htmlspecialchars($c['title'] ?: substr($c['query'],0,30)) ?>" onchange="renameChat(<?= $c['id'] ?>, this.value)">
            <div class="chat-meta">
                <span><i class="fas fa-clock"></i> <?= htmlspecialchars((string)$c['timestamp']) ?></span>
                <span><i class="fas fa-microchip"></i> <?= htmlspecialchars((string)($c['model'] ?: 'unknown-model')) ?></span>
            </div>

            <div class="preview-row">
                <div class="preview-block">
                    <div class="preview-label">Prompt</div>
                    <div class="preview-text"><?= htmlspecialchars(strlen((string)$c['query']) > 220 ? substr((string)$c['query'], 0, 217) . '...' : (string)$c['query']) ?></div>
                </div>
                <div class="preview-block">
                    <div class="preview-label">ZeroThink Reply</div>
                    <div class="preview-text"><?= htmlspecialchars(strlen(strip_tags((string)$c['response'])) > 260 ? substr(strip_tags((string)$c['response']), 0, 257) . '...' : strip_tags((string)$c['response'])) ?></div>
                </div>
            </div>

            <div id="data-q-<?= $c['id'] ?>" style="display:none;"><?= htmlspecialchars($c['query']) ?></div>
            <div id="data-r-<?= $c['id'] ?>" style="display:none;"><?= htmlspecialchars($c['response']) ?></div>

            <div class="actions">
                <span class="act-btn load" onclick="loadToStudio(<?= $c['id'] ?>)"><i class="fas fa-reply-all"></i> CONTINUE</span>
                <span class="act-btn" onclick="viewChat(<?= $c['id'] ?>)"><i class="fas fa-eye"></i> View</span>
                <span class="act-btn" onclick="downloadChat(<?= $c['id'] ?>)"><i class="fas fa-download"></i> Download</span>
                <span class="act-btn share" onclick="shareChat(<?= $c['id'] ?>, this)"><i class="fas fa-share-nodes"></i> Share</span>
                <span class="act-btn" onclick="delChat(<?= $c['id'] ?>)"><i class="fas fa-trash"></i> Delete</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="view-modal">
        <div class="modal-content">
            <h3 style="color:#fff; border-bottom:1px solid #333; padding-bottom:10px;">PLAYBACK <span onclick="document.getElementById('view-modal').style.display='none'" style="float:right; cursor:pointer;">&times;</span></h3>
            <div id="modal-viewer"></div>
        </div>
    </div>

    <script>
        const HISTORY_CSRF_TOKEN = <?= json_encode(
            zt_csrf_token(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;

        async function secureHistoryPost(url, payload) {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': HISTORY_CSRF_TOKEN
                },
                body: JSON.stringify({ ...(payload || {}), csrf_token: HISTORY_CSRF_TOKEN })
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || data.status === 'error') {
                throw new Error((data && data.message) || 'The secure history request failed. Refresh and try again.');
            }
            return data;
        }

        async function renameChat(id, title) {
            try {
                await secureHistoryPost('/api_history.php', { action: 'rename', id, title });
            } catch (error) {
                alert(error.message);
            }
        }
        async function delChat(id) {
            if(!confirm("Delete?")) return;
            try {
                await secureHistoryPost('/api_history.php', { action: 'delete', id });
                document.getElementById('c-' + id)?.remove();
            } catch (error) {
                alert(error.message);
            }
        }
        async function nukeHistory() {
            if(!confirm("WIPE ALL HISTORY?")) return;
            try {
                await secureHistoryPost('/api_history.php', { action: 'nuke' });
                location.reload();
            } catch (error) {
                alert(error.message);
            }
        }

        async function wipeMemory() {
            if(confirm("PERMANENTLY ERASE MEMORY? This cannot be undone.")) {
                await secureHistoryPost('/api_history.php', { action: 'wipe_memory' });
                document.getElementById('mem-content').innerText = "Memory wiped.";
            }
        }

        function viewChat(id) {
            const q = document.getElementById('data-q-'+id).innerText;
            const r = document.getElementById('data-r-'+id).innerText;
            document.getElementById('modal-viewer').innerHTML = `<div class='u-bubble'>${q}</div><div class='a-bubble'>${marked.parse(r)}</div>`;
            document.getElementById('view-modal').style.display = 'block';
        }

        function downloadChat(id) {
            window.location.href = '/download_chat.php?chat_id=' + encodeURIComponent(id) + '&format=txt';
        }

        async function shareChat(id, btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '...';
            try {
                const data = await secureHistoryPost('/api_share.php', { id });
                if(!data.url) throw new Error('Share link was not returned.');
                await navigator.clipboard.writeText(data.url);
                btn.innerHTML = 'Copied!';
                setTimeout(() => btn.innerHTML = orig, 2000);
            } catch (error) {
                alert(error.message);
                btn.innerHTML = orig;
            }
        }

        function loadToStudio(id) {
            const q = document.getElementById('data-q-'+id).innerText;
            const r = document.getElementById('data-r-'+id).innerText;
            localStorage.setItem('zt_restore_q', q);
            localStorage.setItem('zt_restore_r', r);
            window.location.href = '/studio';
        }
    </script>
</body>
</html>
