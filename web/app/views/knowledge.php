<?php
require_once __DIR__ . '/../bootstrap.php';
$knowledgeUser = function_exists('current_user_record') ? current_user_record() : null;
$knowledgeAllowed = $knowledgeUser
    && ((function_exists('is_architect_user') && is_architect_user($knowledgeUser))
        || strtolower((string) ($knowledgeUser['plan'] ?? '')) === 'god_mode');
if (!$knowledgeAllowed) { header('Location: /studio'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Knowledge Core | ZeroThink</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="/assets/css/zerothink_titan.css?v=KNOWLEDGE">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #050505; color: #fff; padding: 40px; font-family: 'Inter', sans-serif; display: flex; justify-content: center; }
        .container { width: 100%; max-width: 900px; }
        
        h1 { color: #a855f7; border-bottom: 1px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
        .back { color: #888; text-decoration: none; font-weight: bold; display: block; margin-bottom: 20px; } 
        .back:hover { color: #fff; }

        .desc { background: #111; padding: 15px; border-radius: 8px; border: 1px solid #333; color: #aaa; font-size: 0.9rem; margin-bottom: 20px; }
        
        textarea { 
            width: 100%; height: 500px; background: #0a0a0a; border: 1px solid #333; color: #20c997; 
            padding: 20px; font-family: 'Courier New', monospace; font-size: 0.9rem; 
            border-radius: 8px; resize: vertical; outline: none; box-sizing: border-box;
        }
        textarea:focus { border-color: #a855f7; }
        
        .btn { 
            background: #a855f7; color: #fff; border: none; padding: 15px 30px; 
            font-weight: bold; cursor: pointer; border-radius: 6px; margin-top: 20px; 
            float: right; transition: 0.3s;
        }
        .btn:hover { background: #9333ea; box-shadow: 0 0 15px rgba(168,85,247,0.4); }
    </style>
</head>
<body>
    <div class="container">
        <a href="/studio" class="back">&larr; Return to Studio</a>
        <h1><i class="fas fa-brain"></i> Neural Core Ingestor</h1>
        
        <div class="desc">
            <strong><i class="fas fa-lock"></i> SECURE ACCESS.</strong><br>
            Paste raw datasets, Q&A pairs, or facts below. This data is injected directly into ZeroThink's long-term context window.
        </div>
        
        <textarea id="knowledge-input" placeholder="Loading Neural Core..."></textarea>
        <button onclick="saveKnowledge()" class="btn"><i class="fas fa-save"></i> INGEST DATASET</button>
    </div>

    <script>
        const KNOWLEDGE_CSRF_TOKEN = <?= json_encode(
            zt_csrf_token(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
        // Load existing data
        fetch('/api_knowledge.php', { credentials: 'same-origin' }).then(r=>r.json()).then(d => {
            document.getElementById('knowledge-input').value = d.content || "";
        });

        async function saveKnowledge() {
            const data = document.getElementById('knowledge-input').value;
            const btn = document.querySelector('.btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SAVING...';
            
            try {
                const res = await fetch('/api_knowledge.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': KNOWLEDGE_CSRF_TOKEN
                    },
                    body: JSON.stringify({ content: data, csrf_token: KNOWLEDGE_CSRF_TOKEN })
                });
                const j = await res.json();
                if(j.status === 'success') alert("✅ Neural Core Updated.");
                else alert("❌ Save Failed.");
            } catch(e) {
                alert("❌ Network Error.");
            }
            btn.innerHTML = '<i class="fas fa-save"></i> INGEST DATASET';
        }
    </script>
</body>
</html>
