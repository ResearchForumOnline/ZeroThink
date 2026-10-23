<?php 
require_once __DIR__ . '/app/bootstrap.php'; 
require_once __DIR__ . '/app/core/model_catalog.php';
if(!isset($_SESSION['user_id'])) { header('Location:/'); exit; } 
$u_id = $_SESSION['user_id'];
$user = function_exists('current_user_record') ? current_user_record() : null;
$is_architect = function_exists('is_architect_user') ? is_architect_user($user) : in_array((int) $u_id, [1, 4], true);
$is_pro = $is_architect; 
if (!$is_pro && function_exists('db')) {
    $db = db();
    if($db) {
        $s = $db->prepare("SELECT role, plan, is_pro, subscription_status FROM users WHERE id=?"); $s->execute([$u_id]);
        $proRow = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        $is_pro = !empty($proRow['is_pro'])
            || strtolower((string) ($proRow['role'] ?? '')) === 'admin'
            || in_array(strtolower((string) ($proRow['plan'] ?? '')), ['pro', 'god_mode'], true)
            || in_array(strtolower((string) ($proRow['subscription_status'] ?? '')), ['active', 'pro'], true);
    }
}
$free_attr = ''; 
$pro_attr = $is_pro ? '' : 'disabled style="background:#220000; color:#666;"';
$pro_key_attr = $is_pro ? '' : 'disabled aria-disabled="true"';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ZeroThink | Sovereign</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        /* CORE LAYOUT */
        * { box-sizing: border-box; outline: none; -webkit-tap-highlight-color: transparent; }
        body { margin:0; background:#020208; color:#e0e0e0; font-family:'Inter', sans-serif; height:100vh; width:100vw; overflow:hidden; font-size: 15px; }
        #matrix-canvas { position:absolute; top:0; left:0; width:100%; height:100%; z-index:0; opacity:0.15; pointer-events:none; }
        .app-container { display: flex; width: 100%; height: 100%; position: relative; z-index: 10; }

        /* SCROLLBARS */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #00ffff; }

        /* SIDEBAR */
        .sidebar { width:260px; background:rgba(5,5,10,0.95); border-right:1px solid #222; display:flex; flex-direction:column; padding:20px; transition:0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index:100; backdrop-filter:blur(20px); flex-shrink: 0; }
        .brand { font-size:1.3rem; font-weight:800; color:#fff; margin-bottom:25px; display:flex; align-items:center; gap:12px; cursor:pointer; letter-spacing: 1px; }
        
        .swarm-panel { background:rgba(255,255,255,0.03); border:1px solid #333; border-radius:8px; padding:15px; margin-bottom:25px; font-size:0.75rem; box-shadow:0 4px 15px rgba(0,0,0,0.3); }
        .swarm-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; color:#888; font-weight:700; letter-spacing: 0.5px; }
        
        .led { width:8px; height:8px; border-radius:50%; background:#222; box-shadow:inset 0 0 2px #000; transition:0.3s; }
        .led.active-orch { background:#00ffff; box-shadow:0 0 8px #00ffff; } 
        .led.active-res { background:#00ff00; box-shadow:0 0 8px #00ff00; }  
        .led.active-lat { background:#a855f7; box-shadow:0 0 8px #a855f7; }  
        
        .nav-item { display:flex; align-items:center; gap:12px; padding:12px 15px; color:#999; border-radius:8px; margin-bottom:4px; font-size:0.9rem; font-weight:600; transition:0.2s; cursor:pointer; text-decoration:none; }
        .nav-item:hover { background:rgba(255,255,255,0.08); color:#fff; transform: translateX(3px); }

        /* MAIN STAGE */
        .main-stage { flex: 1; display: flex; flex-direction: column; background: transparent; position: relative; min-width: 0; height: 100%; }
        #chat-history { flex: 1; overflow-y: auto; padding: 40px; display: flex; flex-direction: column; gap: 25px; scroll-behavior: smooth; }
        #starter-grid { text-align: center; margin-top: 15vh; animation: fadeIn 1s ease; }
        #starter-grid h1 { font-size: 3rem; margin: 15px 0; text-shadow: 0 0 20px rgba(0,255,255,0.3); }
        .starter-research-grid { width:min(940px,100%); margin:30px auto 0; display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
        .starter-card { text-align:left; background:rgba(8,12,18,.82); border:1px solid #29313c; border-radius:10px; padding:16px; cursor:pointer; transition:.2s; }
        .starter-card:hover { border-color:#00ffff; transform:translateY(-2px); box-shadow:0 0 16px rgba(0,255,255,.14); }
        .starter-card i { color:#00ffff; margin-right:8px; }
        .starter-card strong { display:block; color:#fff; margin-bottom:5px; }
        .starter-card span { color:#99a5ae; font-size:.82rem; line-height:1.45; display:block; }
        .starter-card .prompt-text { display:none; }
        .lane-label { width:min(940px,100%); margin:28px auto 10px; color:#00ffff; font-size:.72rem; font-weight:900; letter-spacing:1.8px; text-transform:uppercase; text-align:left; opacity:.82; }
        .starter-card.link-card { border-color:rgba(168,85,247,.45); }
        .starter-card.link-card i { color:#a855f7; }
        
        /* MESSAGES */
        .user-message { align-self: flex-end; background: #1a1a2e; border: 1px solid #334; color: #fff; padding: 14px 20px; border-radius: 18px 18px 2px 18px; max-width: 80%; word-wrap: break-word; box-shadow: 0 4px 10px rgba(0,0,0,0.2); font-size: 1rem; line-height: 1.5; }
        .zt-message { max-width: 900px; margin: 0 auto; line-height: 1.7; width: 100%; font-size: 1rem; color: #d0d0d0; }
        .zt-message p { margin-bottom: 1em; }
        .zt-message strong { color: #fff; }
        .zt-message code { background: rgba(255,255,255,0.1); padding: 2px 5px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 0.9em; color: #ff79c6; }
        .zt-message pre { background: #0d0d15; padding: 15px; border-radius: 8px; border: 1px solid #333; overflow-x: auto; }

        /* THINKING BOX */
        .thinking-wrapper { margin: 20px 0; }
        .think-toggle { color: #a855f7; cursor: pointer; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border: 1px solid rgba(168, 85, 247, 0.4); border-radius: 20px; background: rgba(168, 85, 247, 0.05); transition: 0.2s; font-weight: 600; letter-spacing: 0.5px; }
        .think-toggle:hover { background: rgba(168, 85, 247, 0.15); box-shadow: 0 0 10px rgba(168, 85, 247, 0.2); }
        .thinking-box { display: none; background: rgba(10, 10, 15, 0.95); padding: 20px; border: 1px solid #333; border-left: 3px solid #a855f7; color: #ccc; font-family: 'Courier New', monospace; margin-top: 12px; white-space: pre-wrap; font-size: 0.85rem; border-radius: 0 8px 8px 0; box-shadow: 0 10px 30px rgba(0,0,0,0.5); backdrop-filter: blur(10px); }
        .thinking-box.open { display: block; animation: slideDown 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); }

        /* INPUT DOCK */
        .input-dock { background: rgba(10,10,15,0.95); border-top: 1px solid #333; padding: 20px; backdrop-filter: blur(20px); flex-shrink: 0; position: relative; }
        .controls-bar { display:flex; align-items:center; gap:12px; margin-bottom:15px; flex-wrap:wrap; }
        .zt-logo-btn { width:42px; height:42px; border-radius:50%; background:url('/assets/img/logo_nobg.png') center/70% no-repeat; border:2px solid #333; cursor:pointer; transition:0.3s; filter:grayscale(100%) opacity(0.5); }
        .zt-logo-btn.active { filter:none; opacity:1; border-color: #a855f7; box-shadow:0 0 15px rgba(168, 85, 247, 0.4); } 
        .zt-state-label { padding:6px 10px; border-radius:999px; border:1px solid #333; color:#777; font-size:0.72rem; font-weight:800; letter-spacing:0.5px; text-transform:uppercase; white-space:nowrap; }
        .zt-state-label.active { color:#d8b4fe; border-color:rgba(168,85,247,0.45); background:rgba(168,85,247,0.12); box-shadow:0 0 10px rgba(168,85,247,0.18); }
        
        .model-select { background:#080810; border:1px solid #444; color:#fff; padding:8px 12px; border-radius:6px; font-size:0.85rem; min-width:220px; cursor: pointer; transition: 0.2s; }
        .model-select:hover { border-color: #00ffff; }
        
        .model-pill { padding:6px 14px; border:1px solid #333; border-radius:20px; font-size:0.75rem; color:#888; cursor:pointer; transition: 0.2s; font-weight: 600; }
        .model-pill:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .model-pill.active { background:rgba(0,255,255,0.1); color:#00ffff; border-color:#00ffff; box-shadow: 0 0 10px rgba(0,255,255,0.1); }
        .model-pill#btn-search.active { background:rgba(0,255,0,0.1); color:#00ff00; border-color:#00ff00; box-shadow: 0 0 10px rgba(0,255,0,0.2); }
        .quantum-job-select { background:#080810; border:1px solid rgba(216,180,254,0.35); color:#e9d5ff; padding:7px 10px; border-radius:999px; font-size:0.72rem; font-weight:800; letter-spacing:0.4px; text-transform:uppercase; cursor:pointer; max-width:170px; }
        .quantum-job-select:focus { outline:none; border-color:#d8b4fe; box-shadow:0 0 12px rgba(168,85,247,0.18); }
        .quantum-proof-card { margin:0 0 12px 0; padding:12px 14px; border:1px solid rgba(157,255,225,0.25); border-left:3px solid #9dffe1; background:linear-gradient(135deg, rgba(0,255,170,0.09), rgba(168,85,247,0.08)); color:#cffff3; border-radius:12px; font-size:0.82rem; box-shadow:0 14px 35px rgba(0,0,0,0.22); }
        .quantum-proof-card code { color:#9dffe1; background:rgba(0,0,0,0.35); }
        .quantum-proof-meta { margin-top:6px; color:#b6ffe9; }
        .quantum-proof-results { margin-top:7px; color:#e9d5ff; }
        .copy-chip { display:inline-flex; align-items:center; gap:6px; margin-left:8px; padding:3px 8px; border-radius:999px; border:1px solid rgba(157,255,225,0.28); color:#9dffe1; cursor:pointer; font-size:0.72rem; background:rgba(0,0,0,0.18); }
        .copy-chip:hover { background:rgba(157,255,225,0.12); color:#fff; }
        .pdf-artifact-card { margin:0 0 12px 0; padding:14px; border:1px solid rgba(0,240,255,0.28); border-left:3px solid #00f0ff; background:linear-gradient(135deg, rgba(0,240,255,0.09), rgba(82,214,178,0.08)); color:#dffcff; border-radius:12px; font-size:0.86rem; line-height:1.5; }
        .pdf-artifact-card strong { color:#fff; }
        .pdf-artifact-meta { color:#aee7ef; margin:5px 0 10px; font-size:0.78rem; }
        .pdf-artifact-actions { display:flex; flex-wrap:wrap; gap:8px; }
        .pdf-artifact-actions a { display:inline-flex; align-items:center; gap:7px; border:1px solid rgba(0,240,255,0.34); background:rgba(0,0,0,0.2); color:#dffcff; text-decoration:none; border-radius:8px; padding:8px 10px; font-weight:850; }
        .pdf-artifact-actions a:hover { border-color:#52d6b2; color:#fff; }

        .input-glass { background:#050508; border:1px solid #333; border-radius:14px; padding:10px; display:flex; gap:10px; align-items:flex-end; transition: 0.2s; }
        .input-glass:focus-within { border-color: #00ffff; box-shadow: 0 0 15px rgba(0,255,255,0.1); }
        #zero-input { flex:1; background:transparent; border:none; color:#fff; font-size:1rem; resize:none; max-height:200px; min-height: 24px; font-family: inherit; line-height: 1.5; }
        
        .send-btn { background:#fff; color:#000; border:none; padding:10px 20px; border-radius:8px; font-weight:800; cursor:pointer; transition: 0.2s; }
        .send-btn:hover { background: #00ffff; box-shadow: 0 0 15px #00ffff; }
        .tool-btn { color:#666; cursor:pointer; padding:8px; transition:0.2s; font-size: 1.1rem; }
        .tool-btn:hover { color:#00ffff; transform: scale(1.1); }

        /* MOBILE */
        .hamburger { display: none; position:absolute; top:15px; left:15px; font-size:1.5rem; color:#fff; cursor:pointer; z-index:200; background:rgba(0,0,0,0.5); padding:8px 12px; border-radius:8px; border: 1px solid #333; }
        @media (max-width: 768px) { 
            .hamburger { display: block; } 
            .sidebar { position:fixed; left:-280px; height:100vh; top:0; box-shadow: 10px 0 50px rgba(0,0,0,0.9); width: 280px; } 
            .sidebar.open { left:0; } 
            #starter-grid { margin-top: 20px; padding: 0 10px; }
            #starter-grid h1 { font-size: 2rem; }
            .user-message { max-width: 90%; font-size: 0.95rem; }
            #chat-history { padding: 15px; padding-bottom: 20px; } 
            .input-dock { padding: 10px; position: sticky; bottom: 0; border-top: 1px solid #222; }
            .controls-bar { gap: 8px; justify-content: space-between; margin-bottom: 10px; }
            .model-select { min-width: 0; flex: 1; font-size: 0.8rem; }
            .model-pill { display: none; } 
            #btn-search { display: flex !important; }
            .quantum-job-select { display:block; width:100%; max-width:none; flex:0 0 100%; }
            .input-glass { padding: 8px; }
            .send-btn { padding: 8px 14px; }
        }

        /* MODALS */
        #vault-modal { display:none; position:fixed; z-index:999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; backdrop-filter: blur(8px); }
        .modal-box { background:#111; padding:30px; width:450px; max-width:90%; border:1px solid #333; border-radius:16px; text-align:center; box-shadow: 0 0 50px rgba(0,0,0,0.8); }
        .modal-box input { width:100%; padding:12px; margin-top:8px; background:#050505; border:1px solid #333; color:#fff; border-radius: 6px; }
        #vault-modal .modal-box { text-align:left; max-height:calc(100vh - 42px); overflow-y:auto; }
        #vault-modal h3 { text-align:center; }
        .vault-field { margin-top:11px; }
        .vault-field label { display:flex; justify-content:space-between; gap:10px; align-items:center; margin-bottom:5px; color:#d9e6ef; font-size:0.72rem; line-height:1.25; font-weight:800; letter-spacing:0.45px; text-transform:uppercase; }
        .vault-field input, .vault-field textarea { width:100%; margin-top:0; padding:12px; background:#050505; border:1px solid #333; color:#fff; border-radius:6px; }
        .vault-tag { color:#00ffff; font-size:0.67rem; white-space:nowrap; }
        .vault-note { margin-top:6px; color:#8d97a3; font-size:0.72rem; line-height:1.45; }
        .vault-note a { color:#8cbaff; text-decoration:none; }
        .vault-section-title { margin:16px 0 2px; color:#00ffff; font-size:0.74rem; font-weight:900; letter-spacing:1px; text-transform:uppercase; }
        .vault-actions { display:flex; gap:10px; margin-top:20px; }
        
        @keyframes pulse { 0% { opacity:1; box-shadow: 0 0 0 rgba(255,68,68,0); } 50% { opacity:0.8; box-shadow: 0 0 15px rgba(255,68,68,0.5); } 100% { opacity:1; box-shadow: 0 0 0 rgba(255,68,68,0); } }
        @keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeIn { from { opacity:0; transform: scale(0.95); } to { opacity:1; transform: scale(1); } }
    </style>
</head>
<body>
    <canvas id="matrix-canvas"></canvas>

    <div id="vault-modal">
        <div class="modal-box">
            <h3><i class="fas fa-microchip"></i> NEURAL VAULT</h3>
            <div class="vault-field">
                <label for="k-persona">System Override <span class="vault-tag">Optional</span></label>
                <textarea id="k-persona" rows="5" placeholder="System Override..." style="resize:vertical;"></textarea>
            </div>

            <div class="vault-section-title">Core Reasoning</div>
            <div class="vault-field">
                <label for="k-groq">Groq API Key <span class="vault-tag">Recommended</span></label>
                <input id="k-groq" type="password" placeholder="Paste Groq API key here" autocomplete="off">
            </div>
            <div class="vault-field">
                <label for="k-nvidia">NVIDIA NIM Key <span class="vault-tag">Ultra Models</span></label>
                <input id="k-nvidia" type="password" placeholder="Paste NVIDIA NIM key here" autocomplete="off">
            </div>

            <div class="vault-section-title">Quantum Lanes</div>
            <div class="vault-field">
                <label for="k-quantum">IonQ Quantum Cloud Key <span class="vault-tag">Quantum</span></label>
                <input id="k-quantum" type="password" placeholder="Paste IonQ key here" autocomplete="off">
            </div>
            <div class="vault-field">
                <label for="k-ibm-quantum">IBM Quantum API Key / Token <span class="vault-tag">Qiskit</span></label>
                <input id="k-ibm-quantum" type="password" placeholder="Paste IBM Quantum API key here" autocomplete="off">
                <div class="vault-note">This is the IBM key from the IBM Quantum dashboard. It is only needed for QISKIT BACKEND/Runtime; QISKIT LOCAL can run without it.</div>
            </div>
            <div class="vault-field">
                <label for="k-ibm-instance">IBM Instance / CRN <span class="vault-tag">Optional</span></label>
                <input id="k-ibm-instance" type="text" placeholder="Paste IBM instance name or CRN if required" autocomplete="off">
            </div>
            <div class="vault-field">
                <label for="k-ibm-region">IBM Region <span class="vault-tag">Optional</span></label>
                <input id="k-ibm-region" type="text" placeholder="us-east or eu-de" autocomplete="off">
                <div class="vault-note">Leave instance and region blank unless your IBM account or Runtime setup asks for them.</div>
            </div>

            <div class="vault-section-title">Other Providers</div>
            <div class="vault-field">
                <label for="k-featherless">Featherless API Key <span class="vault-tag">Pro / Paid Hosted</span></label>
                <input id="k-featherless" type="password" placeholder="<?= $is_pro ? 'Paste Featherless API key here' : 'Pro required for Featherless keys' ?>" autocomplete="off" <?=$pro_key_attr?>>
                <div class="vault-note">Featherless is a hosted model provider. A Featherless account/key is required; larger hosted models may need a paid Featherless plan.</div>
            </div>
            <div class="vault-field">
                <label for="k-zerothink_api">OpenZero Machine API Key <span class="vault-tag">Pro / Local API</span></label>
                <input id="k-zerothink_api" type="password" placeholder="<?= $is_pro ? 'Paste your OpenZero machine key here' : 'Pro required for OpenZero machine keys' ?>" autocomplete="off" <?=$pro_key_attr?>>
                <div class="vault-note">Use your own OpenZero node key when a server-side gateway is configured for that node. If the shared ZeroThink OpenZero gateway is enabled, Pro accounts get 5 trial messages before they must install OpenZero and add their own key. ZeroThink does not accept browser-supplied upstream URLs.</div>
            </div>
            <div class="vault-field">
                <label for="k-openai">OpenAI API Key <span class="vault-tag">Pro Options</span></label>
                <input id="k-openai" type="password" placeholder="Paste OpenAI API key here" autocomplete="off">
            </div>
            <div class="vault-field">
                <label for="k-gemini">Gemini API Key <span class="vault-tag">Google</span></label>
                <input id="k-gemini" type="password" placeholder="Paste Gemini API key here" autocomplete="off">
            </div>
            <div class="vault-field">
                <label for="k-xai">xAI API Key <span class="vault-tag">Grok</span></label>
                <input id="k-xai" type="password" placeholder="Paste xAI API key here" autocomplete="off">
            </div>
            <div class="vault-field">
                <label for="k-serper">Serper Web Key <span class="vault-tag">Web</span></label>
                <input id="k-serper" type="password" placeholder="Paste Serper web key here" autocomplete="off">
            </div>
            <div class="vault-actions">
                <button onclick="window.Zero.saveVault()" style="flex:2; padding:12px; background:#00ffff; color:#000; border:none; border-radius:6px; font-weight:800; cursor:pointer;">SAVE SYSTEM</button>
                <button onclick="window.Zero.wipeVault()" style="flex:1; padding:12px; background:#220000; color:#f44; border:1px solid #f44; border-radius:6px; font-weight:bold; cursor:pointer;">WIPE</button>
            </div>
            <div onclick="document.getElementById('vault-modal').style.display='none'" style="margin-top:15px; color:#666; cursor:pointer; font-size:0.8rem; text-decoration:underline; text-align:center;">CLOSE CONSOLE</div>
        </div>
    </div>

    <div id="feedback-modal" style="display:none; position:fixed; z-index:999; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; backdrop-filter: blur(8px);">
        <div class="modal-box">
            <h3><i class="fas fa-comment-dots"></i> SYSTEM FEEDBACK</h3>
            <p style="font-size:0.8rem; color:#888; margin-bottom:15px;">Report bugs or suggest neural optimizations.</p>
            <textarea id="f-message" rows="5" placeholder="Your message to the Architect..." style="width:100%; padding:10px; background:#050505; border:1px solid #333; color:#fff; margin-bottom:10px; resize:vertical; font-family:inherit;"></textarea>
            <div style="display:flex; gap:10px; margin-top:10px;">
                <button onclick="window.Zero.submitFeedback()" style="flex:1; padding:12px; background:#a855f7; color:#fff; border:none; border-radius:6px; font-weight:800; cursor:pointer;">SUBMIT</button>
                <button onclick="document.getElementById('feedback-modal').style.display='none'" style="flex:1; padding:12px; background:#222; color:#fff; border:none; border-radius:6px; cursor:pointer;">CANCEL</button>
            </div>
        </div>
    </div>

    <div class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></div>

    <div class="app-container">
        <aside class="sidebar" id="main-sidebar">
            <div class="brand" onclick="window.location.reload()">
                <img src="/assets/img/logo_nobg.png" style="width:35px;"> 
                <span>ZeroThink</span>
            </div>
            
            <div class="swarm-panel">
                <div class="swarm-row"><span>ORCHESTRATOR</span><div class="led active active-orch" id="led-orch"></div></div>
                <div class="swarm-row"><span>RESEARCHER</span><div class="led" id="led-res"></div></div>
                <div class="swarm-row"><span>LATTICE NODE</span><div class="led" id="led-lat"></div></div>
            </div>
            
            <a href="#" onclick="location.reload()" class="nav-item"><i class="fas fa-plus"></i> New Pulse</a>
            <a href="/history" class="nav-item"><i class="fas fa-history"></i> Memory Stream</a>
            <div class="nav-item" onclick="document.getElementById('vault-modal').style.display='flex'; window.Zero.loadVault();"><i class="fas fa-cog"></i> Neural Vault</div>
            <?php if($is_architect): ?><a href="/master" class="nav-item" style="color:#d8b4fe;"><i class="fas fa-user-shield"></i> Master</a><?php endif; ?>
            <a href="/library" class="nav-item"><i class="fas fa-layer-group"></i> Zero Library</a>
            <a href="/dna" class="nav-item"><i class="fas fa-dna"></i> DNA Lab</a>
            <a href="/media" class="nav-item"><i class="fas fa-wand-magic-sparkles"></i> Media Lab</a>
            <a href="/research" class="nav-item"><i class="fas fa-flask"></i> Research Lab</a>
            <a href="/quantumzero" class="nav-item"><i class="fas fa-atom"></i> QuantumZero</a>
            <a href="/quantumlab" class="nav-item"><i class="fas fa-wave-square"></i> Quantum Lab</a>
            <a href="/zmath" class="nav-item"><i class="fas fa-square-root-variable"></i> ZMath Agent</a>
            <a href="/guest" class="nav-item"><i class="fas fa-plug"></i> Direct Mode</a>
            <a href="/nottingham-research" class="nav-item"><i class="fas fa-book-open"></i> Literature Research</a>
            <a href="/research-paper-creator" class="nav-item"><i class="fas fa-file-lines"></i> Paper Creator</a>
            <a href="/pdf-creator" class="nav-item"><i class="fas fa-file-pdf"></i> PDF Creator</a>
            <a href="/oracle" class="nav-item"><i class="fas fa-eye"></i> Oracle Vision</a>
            <a href="/faq" class="nav-item"><i class="fas fa-book"></i> FAQ</a>
            
            <div class="nav-item" onclick="document.getElementById('feedback-modal').style.display='flex'" style="cursor:pointer; color:#a855f7; font-weight:bold; border-left:3px solid #a855f7; padding-left:15px; margin-top:20px;">
                <i class="fas fa-comment-dots"></i> FEEDBACK
            </div>
            <a href="/upgrade" class="nav-item" style="color:#ffd700;"><i class="fas fa-bolt"></i> Upgrade Gold</a>
            <div style="margin-top:auto;"><a href="/logout" class="nav-item" style="justify-content:center; color:#f44;"><i class="fas fa-power-off"></i> LOGOUT</a></div>
        </aside>

        <main class="main-stage">
            <div id="chat-history">
                <div id="starter-grid">
                    <img src="/assets/img/logo_nobg.png" style="width:120px; filter: drop-shadow(0 0 20px rgba(0,255,255,0.4)); animation: pulse 3s infinite;">
                    <h1>ZeroThink</h1>
                    <p style="color:#00ffff; font-family:'Courier New'; letter-spacing:4px; font-size: 0.9rem;">SOVEREIGN INTELLIGENCE ARCHITECTURE</p>
                    <div style="max-width:900px; margin:22px auto 0; padding:18px 20px; border:1px solid rgba(0,255,255,0.18); border-radius:18px; background:linear-gradient(135deg, rgba(6,14,26,0.92), rgba(8,6,18,0.92)); box-shadow:0 24px 70px rgba(0,0,0,0.35); text-align:left;">
                        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-bottom:10px;">
                            <span style="display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px; background:rgba(0,255,255,0.12); border:1px solid rgba(0,255,255,0.22); color:#9cefff; font-size:0.72rem; font-weight:800; letter-spacing:1px; text-transform:uppercase;"><i class="fas fa-bolt"></i> Default // Groq GPT-OSS 120B</span>
                            <span style="display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px; background:rgba(168,85,247,0.14); border:1px solid rgba(168,85,247,0.32); color:#d8b4fe; font-size:0.72rem; font-weight:800; letter-spacing:1px; text-transform:uppercase;"><i class="fas fa-atom"></i> Quantum // Pro</span>
                            <span style="color:#8cbaff; font-size:0.8rem; letter-spacing:0.7px; text-transform:uppercase;">Groq Reasoning + IonQ or IBM Qiskit Evidence</span>
                        </div>
                        <p style="margin:0; color:#aeb6c3; line-height:1.7;">The main chat includes separate Quantum lanes. IonQ uses Groq GPT-OSS 120B with IonQ telemetry and optional simulator-job evidence. IBM Qiskit uses Groq GPT-OSS 120B with Qiskit calculation packets for Bell/GHZ, Grover, QAOA, VQE, QFT, and Zero lattice experiments. Zero Library, DNA Lab, Media Lab, ZMath, and research tools remain separate specialist workspaces. This is hybrid quantum-informed AI: classical language reasoning with quantum-computing evidence in the loop, with clear boundaries that the LLM itself is not running on qubits.</p>
                    </div>
                    <div class="lane-label">Core Workbench</div>
                    <div class="starter-research-grid">
                        <div class="starter-card" onclick="window.Zero.setMode('quantum', document.getElementById('btn-quantum')); window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-atom"></i>Studio Quantum Lane</strong>
                            <span>Run this chat through Groq GPT-OSS 120B with IonQ telemetry and optional simulator-job evidence.</span>
                            <div class="prompt-text">Use the Studio Quantum lane. Analyze this with real IonQ evidence boundaries, separate physics fact from metaphor/speculation, cite any job id if one is created, and give a practical next experiment: [INSERT QUESTION]</div>
                        </div>
                        <div class="starter-card link-card" onclick="window.location.href='/quantumzero'">
                            <strong><i class="fas fa-atom"></i>QuantumZero</strong>
                            <span>Quantum-informed research agent with IonQ evidence boundaries and audit-safe output.</span>
                            <div class="prompt-text">Open QuantumZero for a quantum-informed research check with clear REAL QUANTUM EVIDENCE, INTERPRETATION, LIMITS, and PRACTICAL NEXT STEP sections.</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.setMode('quantum_ibm', document.getElementById('btn-qiskit')); window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-microchip"></i>IBM Qiskit Lane</strong>
                            <span>Run Bell/GHZ, Grover, QAOA, VQE, QFT, or Zero lattice calculation packets before the answer.</span>
                            <div class="prompt-text">Use the IBM Qiskit lane. Run a small Qiskit calculation packet, explain the counts, entropy, dominant state, limitations, and next experiment for: [INSERT QUESTION]</div>
                        </div>
                        <div class="starter-card link-card" onclick="window.location.href='/zmath'">
                            <strong><i class="fas fa-square-root-variable"></i>ZMath Agent</strong>
                            <span>Zero Boundary Algebra, file analysis, reset logic, mirror checks, and ZME1 encryption.</span>
                            <div class="prompt-text">Use ZMath to analyze this workflow and identify reset points, mirror checks, failure boundaries, and recovery steps: [INSERT WORKFLOW]</div>
                        </div>
                        <div class="starter-card link-card" onclick="window.location.href='/dna'">
                            <strong><i class="fas fa-dna"></i>DNA Lab</strong>
                            <span>Raw DNA upload reports with ZDNA-MAP quality, health research cards, heritage signals, lineage clues, ancient matches, and PDF export.</span>
                            <div class="prompt-text">Open DNA Lab to run a local-first raw DNA evidence report with clear research boundaries and privacy controls.</div>
                        </div>
                        <div class="starter-card link-card" onclick="window.location.href='/library'">
                            <strong><i class="fas fa-layer-group"></i>Zero Library</strong>
                            <span>Private deterministic context for code, research notes, DNA files, and local evidence packs.</span>
                            <div class="prompt-text">Open Zero Library to manage private files and deterministic context packs before model synthesis.</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-brain"></i>Lattice Reasoning</strong>
                            <span>Deconstruct a complex premise using the ZeroThink framework.</span>
                            <div class="prompt-text">Apply Lattice Logic to deconstruct this premise: [INSERT IDEA HERE]. Include structure, counterpoint, risk, evidence needs, and practical next steps.</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-crystal-ball"></i>Future Prediction</strong>
                            <span>Theoretical event analysis with grounded caveats and alternatives.</span>
                            <div class="prompt-text">Predict the future of this event theoretically. Be logical, plausible, skeptical, and clear about uncertainty. Event: [INSERT EVENT]</div>
                        </div>
                    </div>

                    <div class="lane-label">Academic Research Workflows</div>
                    <div class="starter-research-grid">
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-book-open"></i>Literature Survey Extension</strong>
                            <span>Extend an existing review with new papers, evidence mapping, provenance, and researcher validation.</span>
                            <div class="prompt-text">Use the Literature Survey Extension scaffold. Topic: [INSERT TOPIC]. Help me extend an existing survey by searching for newly published papers, mapping them to the original review structure, logging provenance, identifying gaps and contradictions, and asking for researcher approval before synthesis.</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-users"></i>Topic Expert Finder</strong>
                            <span>Find literature-backed authors, reviewers, supervisors, labs, and topic communities.</span>
                            <div class="prompt-text">Use the Topic Expert Finder scaffold for: [INSERT TOPIC]. Identify authors, labs, potential reviewers, or supervisor-fit topic areas from literature evidence. Keep wording careful and do not claim formal endorsement, affiliation, invitation, or availability.</div>
                        </div>
                        <div class="starter-card link-card" onclick="window.location.href='/research-paper-creator'">
                            <strong><i class="fas fa-file-signature"></i>Research Paper Creator</strong>
                            <span>Open the staged paper workspace for scope, search protocol, evidence, drafting, critique, and revision.</span>
                            <div class="prompt-text">Open the Research Paper Creator workspace.</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-file-pdf"></i>AI PDF Report</strong>
                            <span>Ask Zero to research, draft, structure, and return a downloadable PDF artifact from the answer.</span>
                            <div class="prompt-text">Create a PDF report about [INSERT TOPIC]. Use web search if needed, include sources and limitations, and make it polished enough to share.</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-user-graduate"></i>Supervisor Finder</strong>
                            <span>Find supervisor-fit areas and labs from literature evidence.</span>
                            <div class="prompt-text">Use the Supervisor Finder scaffold for: [INSERT TOPIC]. Identify possible supervisor-fit topic areas, labs, and author clusters from evidence. Keep wording careful and do not claim availability, endorsement, or formal relationship.</div>
                        </div>
                    </div>

                    <div class="lane-label">Code, Security, and Search</div>
                    <div class="starter-research-grid">
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-globe"></i>Search Web</strong>
                            <span>Natural search prompt; Serper runs automatically when configured.</span>
                            <div class="prompt-text">search [INSERT TOPIC OR QUESTION]</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-code"></i>Python Script</strong>
                            <span>Generate optimized code with error handling.</span>
                            <div class="prompt-text">Write a Python script to [INSERT TASK HERE]. Use best practices, clear error handling, and explain how to test it.</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-database"></i>SQL Optimization</strong>
                            <span>Refine queries and spot bottlenecks.</span>
                            <div class="prompt-text">Analyze this SQL query logic for performance bottlenecks and correctness issues: [PASTE QUERY HERE]</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-shield-alt"></i>Security Audit</strong>
                            <span>Scan code for vulnerabilities and patch ideas.</span>
                            <div class="prompt-text">Audit this code snippet for security vulnerabilities and suggest concrete patches: [PASTE CODE HERE]</div>
                        </div>
                    </div>

                    <div class="lane-label">Abstract / Theoretical</div>
                    <div class="starter-research-grid">
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-atom"></i>Quantum Theory</strong>
                            <span>Explain quantum ideas without removing the truth boundary.</span>
                            <div class="prompt-text">Explain Quantum Entanglement using the metaphor of a digital neural network, and clearly separate physics fact, metaphor, and speculation.</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-infinity"></i>Theory of Everything</strong>
                            <span>Physics, consciousness, and ZeroThink framing.</span>
                            <div class="prompt-text">Propose a theoretical connection between physics and consciousness using the ZeroThink framework. Keep it speculative, clearly labelled, and grounded in what is known.</div>
                        </div>
                        <div class="starter-card" onclick="window.Zero.quickPrompt(this)">
                            <strong><i class="fas fa-user-astronaut"></i>Deep Self-Analysis</strong>
                            <span>Structured personal reflection report.</span>
                            <div class="prompt-text">I am going to tell you about myself. Create a deep analysis report to help me understand myself better. Ask clarifying questions first if needed.</div>
                        </div>
                        <div class="starter-card link-card" onclick="window.location.href='/guest'">
                            <strong><i class="fas fa-plug"></i>Direct Mode</strong>
                            <span>No Google login; local Groq/NVIDIA keys in the browser.</span>
                            <div class="prompt-text">Open Direct Mode for local Groq or NVIDIA key use.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="input-dock">
                <div class="controls-bar">
                    <div class="zt-logo-btn active" id="btn-zero" onclick="window.Zero.toggleZeroThink(this)" title="Toggle ZeroThink Protocol"></div>
                    <span class="zt-state-label active" id="zero-state-label">ZeroThink ON</span>
                    <div style="width:1px; height:25px; background:#333; margin:0 10px;"></div>
                    
                    <select id="groq-model" class="model-select" onchange="const opt=this.options[this.selectedIndex]; const m=opt.dataset.provider || (this.value.includes(':') ? this.value.split(':')[0] : 'groq'); window.Zero.setMode(m, document.querySelector(`.model-pill[onclick*='${m}']`));"> 

                        <?= zt_model_options_html(null, $is_pro) ?>
                    </select>
                    
                    <div class="model-pill model active" onclick="window.Zero.setMode('groq', this)">GROQ</div>
                    <div class="model-pill model" onclick="window.Zero.setMode('nvidia', this)">NVIDIA</div>
                    <div class="model-pill model" onclick="window.Zero.setMode('xai', this)">GROK</div>
                    <div class="model-pill model" onclick="window.Zero.setMode('openai', this)">OPENAI</div>
                    <div class="model-pill model" onclick="window.Zero.setMode('gemini', this)">GEMINI</div>
                    <div class="model-pill model" onclick="window.Zero.setMode('featherless', this)">FEATHERLESS</div>
                    <div class="model-pill model" onclick="window.Zero.setMode('zerothink', this)">OPENZERO</div>
                    <div class="model-pill model" id="btn-quantum" onclick="window.Zero.setMode('quantum', this)">QUANTUM</div>
                    <div class="model-pill model" id="btn-qiskit" onclick="window.Zero.setMode('quantum_ibm', this)">QISKIT</div>
                    <select id="quantum-job-mode" class="quantum-job-select" title="Quantum job mode" onchange="window.Zero.setQuantumJobMode(this.value)">
                        <option value="auto">IonQ Auto Job</option>
                        <option value="force">Fresh Proof Job</option>
                        <option value="telemetry">Telemetry Only</option>
                    </select>
                    <select id="qiskit-mode" class="quantum-job-select" title="IBM Qiskit mode" onchange="window.Zero.setQiskitMode(this.value)">
                        <option value="local">Qiskit Local</option>
                        <option value="backend">IBM Backend Check</option>
                    </select>
                    <select id="qiskit-algorithm" class="quantum-job-select" title="IBM Qiskit algorithm" onchange="window.Zero.setQiskitAlgorithm(this.value)">
                        <option value="zero_lattice">Zero Lattice</option>
                        <option value="bell">Bell</option>
                        <option value="ghz">GHZ</option>
                        <option value="grover">Grover</option>
                        <option value="qaoa_maxcut">QAOA MaxCut</option>
                        <option value="vqe_toy">VQE Toy</option>
                        <option value="qft">QFT</option>
                    </select>
                    
                    <div style="flex:1;"></div>
                    <div class="model-pill" id="btn-search" onclick="window.Zero.toggleSearch(this)"><i class="fas fa-globe"></i> WEB</div>
                </div>

                <div class="input-glass">
                    <i class="fas fa-file-download tool-btn" onclick="window.Zero.downloadLog()" title="Download Log" style="margin-right:5px;"></i>
                    <i class="fas fa-paperclip tool-btn" onclick="document.getElementById('f-up').click()" title="Upload File"></i>
                    <input type="file" id="f-up" style="display:none" onchange="window.Zero.handleUpload(this)">
                    
                    <textarea id="zero-input" placeholder="Enter command..." rows="1"></textarea>
                    <button class="send-btn" onclick="window.Zero.send()"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </main>
    </div>

    <script src="/assets/js/app.js?v=20260714"></script>
    <script>
        const ZT_SESSION_CSRF_TOKEN = <?= json_encode(
            zt_csrf_token(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
        const ZT_VAULT_CSRF_TOKEN = ZT_SESSION_CSRF_TOKEN;
        window.ZT_SESSION_CSRF_TOKEN = ZT_SESSION_CSRF_TOKEN;

        async function ztVaultRequest(payload) {
            const body = Object.assign({}, payload || {}, { csrf_token: ZT_VAULT_CSRF_TOKEN });
            const res = await fetch('/api_vault.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': ZT_VAULT_CSRF_TOKEN
                },
                body: JSON.stringify(body)
            });
            const text = await res.text();
            let data = null;
            try { data = JSON.parse(text); } catch (e) {}
            if (!res.ok || !data || data.status !== 'success') {
                throw new Error((data && data.message) || 'The vault request failed. Refresh Studio and try again.');
            }
            return data;
        }

        window.Zero = {
            config: { engine: 'groq', task: 'chat', model: 'groq:openai/gpt-oss-120b', zero_mode: true, use_web: false, quantum_job_mode: 'auto', qiskit_mode: 'local', qiskit_algorithm: 'zero_lattice' },
            fileContext: "", imageBase64: "",

            decodeApiResponse: function(raw) {
                try { return JSON.parse(raw); } catch (e) {}
                try {
                    const binary = atob(raw);
                    const bytes = Uint8Array.from(binary, ch => ch.charCodeAt(0));
                    const text = new TextDecoder().decode(bytes);
                    return JSON.parse(text);
                } catch (e) {}
                return { status: 'error', reply: "Response Error: " + raw.substring(0, 140) };
            },

            escapeHtml: function(value) {
                return String(value || '').replace(/[&<>"']/g, ch => ({
                    '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
                }[ch]));
            },

            copyText: async function(value, label) {
                try {
                    await navigator.clipboard.writeText(String(value || ''));
                    if (label) label.innerHTML = '<i class="fas fa-check"></i> Copied';
                    setTimeout(() => { if (label) label.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 1300);
                } catch (e) {
                    alert('Copy failed. Select the text manually.');
                }
            },

            renderPdfArtifact: function(pdf, error) {
                if (error) {
                    return `<div class="pdf-artifact-card" style="border-left-color:#ffb84d;"><strong><i class="fas fa-triangle-exclamation"></i> PDF artifact not created</strong><div class="pdf-artifact-meta">${this.escapeHtml(error)}</div><div class="pdf-artifact-actions"><a href="/pdf-creator"><i class="fas fa-file-pdf"></i> Open PDF Creator</a></div></div>`;
                }
                if (!pdf || !pdf.url) return '';
                const title = this.escapeHtml(pdf.title || 'ZeroThink AI Report');
                const filename = this.escapeHtml(pdf.filename || 'zerothink-ai-report.pdf');
                const expires = pdf.expires_at ? ` &middot; Expires ${this.escapeHtml(pdf.expires_at)}` : '';
                const size = Number(pdf.size || 0);
                const sizeText = size > 0 ? `${Math.max(1, Math.round(size / 1024))} KB` : 'ready';
                const url = this.escapeHtml(pdf.url);
                return `<div class="pdf-artifact-card"><strong><i class="fas fa-file-pdf"></i> PDF ready: ${title}</strong><div class="pdf-artifact-meta">${filename} &middot; ${sizeText}${expires}</div><div class="pdf-artifact-actions"><a href="${url}" download="${filename}"><i class="fas fa-download"></i> Download PDF</a><a href="/pdf-creator"><i class="fas fa-pen-to-square"></i> Edit in PDF Creator</a></div></div>`;
            },

            formatQuantumProbabilities: function(raw) {
                const source = raw && raw.probabilities ? raw.probabilities : raw;
                if (!source || typeof source !== 'object') return '';
                return Object.entries(source)
                    .slice(0, 6)
                    .map(([state, value]) => {
                        const numeric = Number(value);
                        const pct = Number.isFinite(numeric) ? (numeric * 100).toFixed(numeric >= 0.1 ? 1 : 2) + '%' : this.escapeHtml(value);
                        return `${this.escapeHtml(state)} ~= ${pct}`;
                    })
                    .join(' / ');
            },

            renderQuantumProof: function(q) {
                q = q || {};
                if (q.job_id) {
                    const mode = q.job_mode ? `Mode: ${this.escapeHtml(q.job_mode)}` : 'Mode: auto';
                    const reused = q.job_new ? 'new simulator job' : `reused recent job${q.job_cache_age_seconds ? ' (' + q.job_cache_age_seconds + 's old)' : ''}`;
                    const throttled = q.forced_throttled ? '<br><span style="color:#ffd166;">Fresh proof was throttled to protect API limits, so a recent job was reused.</span>' : '';
                    const results = this.formatQuantumProbabilities(q.probabilities);
                    const id = this.escapeHtml(q.job_id);
                    return `<div class="quantum-proof-card"><strong><i class="fas fa-atom"></i> IonQ job-backed</strong><span class="copy-chip" data-copy="${id}" onclick="window.Zero.copyText(this.dataset.copy, this)"><i class="fas fa-copy"></i> Copy</span><br>ID: <code>${id}</code><div class="quantum-proof-meta">Status: ${this.escapeHtml(q.job_status || 'submitted')} &middot; Backend: ${this.escapeHtml(q.job_backend || 'simulator')} &middot; ${this.escapeHtml(reused)} &middot; ${mode}</div>${results ? `<div class="quantum-proof-results">Results: ${results}</div>` : ''}${throttled}</div>`;
                }
                if (q.job_mode === 'telemetry') {
                    return `<div class="quantum-proof-card" style="border-left-color:#d8b4fe;"><strong><i class="fas fa-satellite-dish"></i> IonQ telemetry-only</strong><br>No simulator job was submitted for this message. This keeps API usage lower while still using cached backend context.</div>`;
                }
                if (q.job_error) {
                    return `<div style="margin:0 0 12px 0; padding:10px 12px; border:1px solid rgba(255,196,87,0.25); border-left:3px solid #ffc457; background:rgba(255,196,87,0.08); color:#ffe8b0; border-radius:10px; font-size:0.82rem;"><strong><i class="fas fa-triangle-exclamation"></i> IonQ job not created</strong><br>${this.escapeHtml(q.job_error)}</div>`;
                }
                return '';
            },

            renderQiskitProof: function(q) {
                q = q || {};
                const algorithm = this.escapeHtml(q.algorithm || 'qiskit');
                const execution = this.escapeHtml(q.execution || 'pending');
                const dominant = this.escapeHtml(q.dominant_state || '');
                const probability = Number(q.dominant_probability || 0);
                const entropy = Number(q.entropy_bits || 0);
                const qiskit = q.qiskit_available ? 'Qiskit ' + this.escapeHtml(q.qiskit_version || 'available') : 'fallback';
                const states = Array.isArray(q.top_states) ? q.top_states.slice(0, 5).map(s => {
                    const pct = (Number(s.probability || 0) * 100).toFixed(1) + '%';
                    return `${this.escapeHtml(s.state)} ${pct}`;
                }).join(' / ') : '';
                const runtime = q.runtime_message ? `<div class="quantum-proof-results">IBM: ${this.escapeHtml(q.runtime_message)}</div>` : '';
                return `<div class="quantum-proof-card" style="border-left-color:#8cbaff;"><strong><i class="fas fa-microchip"></i> IBM Qiskit calculation</strong><br>Algorithm: <code>${algorithm}</code><div class="quantum-proof-meta">Execution: ${execution} &middot; ${qiskit} &middot; Dominant: ${dominant || 'n/a'} ${probability ? '(' + (probability * 100).toFixed(1) + '%)' : ''} &middot; Entropy: ${Number.isFinite(entropy) ? entropy.toFixed(3) : 'n/a'} bits</div>${states ? `<div class="quantum-proof-results">Top states: ${states}</div>` : ''}${runtime}</div>`;
            },

            setMode: function(e, el) { 
                this.config.engine = e; 
                document.querySelectorAll('.model-pill.model').forEach(b => b.classList.remove('active')); 
                if(el) el.classList.add('active'); 
                const select = document.getElementById('groq-model');
                if(!select) return;
                const current = select.options[select.selectedIndex];
                const currentProvider = (current && current.dataset && current.dataset.provider) ? current.dataset.provider : 'groq';
                if (currentProvider !== e) {
                    const match = Array.from(select.options).find(opt => ((opt.dataset && opt.dataset.provider) ? opt.dataset.provider : 'groq') === e);
                    if (match) select.value = match.value;
                }
            },
            setQuantumJobMode: function(mode) {
                this.config.quantum_job_mode = ['auto', 'force', 'telemetry'].includes(mode) ? mode : 'auto';
            },
            setQiskitMode: function(mode) {
                this.config.qiskit_mode = ['local', 'backend', 'runtime', 'ibm'].includes(mode) ? mode : 'local';
            },
            setQiskitAlgorithm: function(algorithm) {
                this.config.qiskit_algorithm = ['zero_lattice', 'bell', 'ghz', 'grover', 'qaoa_maxcut', 'vqe_toy', 'qft'].includes(algorithm) ? algorithm : 'zero_lattice';
            },
            syncZeroThinkState: function() {
                const btn = document.getElementById('btn-zero');
                const led = document.getElementById('led-lat');
                const label = document.getElementById('zero-state-label');
                const active = !!this.config.zero_mode;
                if (btn) {
                    btn.classList.toggle('active', active);
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                    btn.title = active ? 'ZeroThink Logic ON. Click to disable reasoning mode.' : 'ZeroThink Logic OFF. Click to enable reasoning mode.';
                }
                if (led) led.classList.toggle('active-lat', active);
                if (label) {
                    label.classList.toggle('active', active);
                    label.innerText = active ? 'ZeroThink ON' : 'ZeroThink OFF';
                }
            },
            toggleZeroThink: function(el) { 
                this.config.zero_mode = !this.config.zero_mode; 
                this.syncZeroThinkState();
            },
            toggleSearch: function(el) { 
                this.config.use_web = !this.config.use_web; 
                el.classList.toggle('active'); 
                const btn = document.getElementById('btn-search');
                btn.style.boxShadow = this.config.use_web ? "0 0 10px #00ff00" : "none"; 
                btn.style.color = this.config.use_web ? "#00ff00" : "#888";
                const led = document.getElementById('led-res');
                if (this.config.use_web) led.classList.add('active-res');
                else led.classList.remove('active-res');
            },

            downloadLog: function() {
                const historyDiv = document.getElementById('chat-history');
                if (!historyDiv) return;
                let logText = "ZERO SOVEREIGN LOG - " + new Date().toLocaleString() + "\n\n";
                const messages = historyDiv.querySelectorAll('.user-message, .zt-message');
                if(messages.length === 0) return alert("No chat data.");
                messages.forEach(msg => {
                    const type = msg.classList.contains('user-message') ? "USER" : "ZERO";
                    let text = msg.innerText.replace("ðŸŸ£ ZeroThink Process", "[LOGIC HIDDEN]");
                    logText += `[${type}]:\n${text}\n\n----------------------------------\n\n`;
                });
                const blob = new Blob([logText], { type: 'text/plain' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'ZeroThink_Log_' + Date.now() + '.txt';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
            },

            quickPrompt: function(card) {
                const text = card.querySelector('.prompt-text');
                if (!text) return;
                const input = document.getElementById('zero-input');
                input.value = text.innerText.trim();
                input.focus();
            },

            handleUpload: async function(input) {
                if (!input.files[0]) return;
                const fd = new FormData(); fd.append('file', input.files[0]);
                const hist = document.getElementById('chat-history');
                hist.innerHTML += `<div class="zt-message" style="color:#00ffff"><i class="fas fa-spinner fa-spin"></i> Uploading...</div>`;
                try {
                    const res = await fetch('/api_upload.php', { method:'POST', body:fd });
                    const d = await res.json();
                    hist.lastElementChild.remove();
                    if(d.status==='success') {
                        if(d.type==='text') { 
                            this.fileContext=d.content; 
                            hist.innerHTML += `<div class="zt-message">âœ… <strong>System:</strong> Analyzed ${d.name}</div>`; 
                        } else { 
                            this.imageBase64=d.url; 
                            hist.innerHTML += `<div class="zt-message"><img src="${d.url}" style="max-width:300px; border-radius:8px; border:1px solid #333;"></div>`; 
                        }
                    } else { alert("Upload Failed: " + d.message); }
                } catch(e) { alert("Upload Error"); }
            },

            send: async function() {
                const input = document.getElementById('zero-input');
                const prompt = input.value.trim();
                if (!prompt && !this.fileContext && !this.imageBase64) return;

                const history = document.getElementById('chat-history');
                const uDiv = document.createElement('div');
                uDiv.className = 'user-message';
                uDiv.innerText = prompt || '[Attached File]';
                history.appendChild(uDiv);
                
                input.value = '';
                history.scrollTop = history.scrollHeight;

                const aiDiv = document.createElement('div');
                aiDiv.className = 'zt-message';
                aiDiv.innerHTML = '<i class="fas fa-circle-notch fa-spin" style="color:#00ffff"></i> ZeroThink\'ing...';
                history.appendChild(aiDiv);

                const keys = {
                    groq: document.getElementById('k-groq').value,
                    openai: document.getElementById('k-openai').value,
                    xai: document.getElementById('k-xai').value,
                    gemini: document.getElementById('k-gemini').value,
                    nvidia: document.getElementById('k-nvidia').value,
                    featherless: document.getElementById('k-featherless').value,
                    zerothink: document.getElementById('k-zerothink_api').value,
                    quantum: document.getElementById('k-quantum').value,
                    ibm_quantum: document.getElementById('k-ibm-quantum').value,
                    ibm_instance: document.getElementById('k-ibm-instance').value,
                    ibm_region: document.getElementById('k-ibm-region').value,
                    serper: document.getElementById('k-serper').value
                };

                const payload = {
                    prompt: prompt,
                    file_context: this.fileContext,
                    image: this.imageBase64,
                    engine: this.config.engine,
                    groq_model: document.getElementById('groq-model').value,
                    zero_mode: this.config.zero_mode,
                    use_web: this.config.use_web,
                    key: keys[this.config.engine] || '',
                    quantum_groq_key: keys.groq,
                    quantum_ionq_key: keys.quantum,
                    quantum_job_mode: this.config.quantum_job_mode,
                    quantum_ibm_key: keys.ibm_quantum,
                    quantum_ibm_instance: keys.ibm_instance,
                    quantum_ibm_region: keys.ibm_region,
                    qiskit_mode: this.config.qiskit_mode,
                    qiskit_algorithm: this.config.qiskit_algorithm,
                    qiskit_shots: 256,
                    serper: keys.serper,
                    persona: document.getElementById('k-persona').value,
                    csrf_token: ZT_SESSION_CSRF_TOKEN
                };

                try {
                    const encodedPayload = btoa(unescape(encodeURIComponent(JSON.stringify(payload))));
                    const res = await fetch('/api_agent.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-Token': ZT_SESSION_CSRF_TOKEN
                        },
                        body: encodedPayload
                    });
                    const raw = await res.text();
                    const data = this.decodeApiResponse(raw);
                    
                    let html = "";
                    if (data.thought) {
                        const uid = 'think-' + Date.now();
                        let fancyThought = this.escapeHtml(String(data.thought))
                            .replace(/\n/g, '<br>')
                            .replace(/\[(FRAME|EVIDENCE|LATTICE|COUNTERPOINT|UNCERTAINTY|IMPACT|DECISION|CHOICE|NEXT STEP)\]/g, '<strong style="color:#7ee7d2;">[$1]</strong>')
                            .replace(/\[(SYNTHESIS|CHOICE)\]/g, '<strong style="color:#a855f7;">[$1]</strong>');
                        html += `<div class="thinking-wrapper"><div class="think-toggle" onclick="document.getElementById('${uid}').classList.toggle('open')"><i class="fas fa-list-check"></i> ZeroThink Reasoning Brief</div><div id="${uid}" class="thinking-box">${fancyThought}</div></div>`;
                    }
                    if (data.quantum) {
                        html += this.renderQuantumProof(data.quantum);
                    }
                    if (data.qiskit) {
                        html += this.renderQiskitProof(data.qiskit);
                    }
                    if (data.openzero_trial) {
                        const t = data.openzero_trial;
                        const remaining = t.remaining === null || typeof t.remaining === 'undefined' ? 'BYO key' : `${t.remaining}/${t.limit} shared trial messages left`;
                        html += `<div style="border-left:3px solid #00ffff; background:rgba(0,255,255,0.06); color:#b8f7ff; padding:10px 12px; margin-bottom:12px; font-size:0.86rem; line-height:1.45;"><strong>OpenZero:</strong> ${this.escapeHtml(remaining)}<br>${this.escapeHtml(t.message || '')}</div>`;
                    }
                    html += this.renderPdfArtifact(data.pdf_artifact, data.pdf_artifact_error);
                    if (data.status === 'error') {
                        html += `<div style="color:#ff7b7b; border-left:3px solid #ff4d4f; padding-left:12px;">${data.reply || 'Request failed.'}</div>`;
                    } else {
                        html += marked.parse(data.reply || "No response.");
                    }
                    aiDiv.innerHTML = html;
                    
                    this.fileContext = ""; this.imageBase64 = "";

                } catch (e) { aiDiv.innerHTML = `<span style="color:red">CONNECTION ERROR: ${e.message}</span>`; }
                
                history.scrollTop = history.scrollHeight;
                const starter = document.getElementById('starter-grid');
                if(starter) starter.style.display = 'none';
            },
            
            init: function() {
                const q = localStorage.getItem('zt_restore_q');
                const r = localStorage.getItem('zt_restore_r');
                if(q && r) {
                    const hist = document.getElementById('chat-history');
                    document.getElementById('starter-grid').style.display = 'none';
                    hist.innerHTML += `<div class="user-message">${q}</div>`;
                    const aiDiv = document.createElement('div');
                    aiDiv.className = 'zt-message';
                    aiDiv.innerHTML = marked.parse(r);
                    hist.appendChild(aiDiv);
                    localStorage.removeItem('zt_restore_q');
                    localStorage.removeItem('zt_restore_r');
                    setTimeout(() => hist.scrollTop = hist.scrollHeight, 100);
                }
                if (window.location.hash === '#neural-vault') {
                    this.loadVault();
                }
            },

            loadVault: async function() { 
                document.getElementById('vault-modal').style.display='flex';
                try {
                    const d = await ztVaultRequest({ action: 'load' });
                    if(d.data) {
                        const vaultMap = {
                            groq: 'api_key_groq',
                            openai: 'api_key_openai',
                            gemini: 'api_key_gemini',
                            xai: 'api_key_xai',
                            nvidia: 'api_key_nvidia',
                            quantum: 'api_key_quantum',
                            featherless: 'api_key_featherless',
                            zerothink_api: 'api_key_zerothink'
                        };
                        Object.keys(vaultMap).forEach(k => {
                            const el = document.getElementById('k-'+k);
                            if(el && d.data[vaultMap[k] + '_saved']) {
                                el.value = '';
                                el.placeholder = 'Saved securely. Enter a new key to replace it.';
                            }
                        });
                        if(d.data.api_key_ibm_quantum_saved) {
                            document.getElementById('k-ibm-quantum').value = '';
                            document.getElementById('k-ibm-quantum').placeholder = 'Saved securely. Enter a new key to replace it.';
                        }
                        if(d.data.ibm_quantum_instance) document.getElementById('k-ibm-instance').value = d.data.ibm_quantum_instance;
                        if(d.data.ibm_quantum_region) document.getElementById('k-ibm-region').value = d.data.ibm_quantum_region;
                        if(d.data.serper_api_key_saved) {
                            document.getElementById('k-serper').value = '';
                            document.getElementById('k-serper').placeholder = 'Saved securely. Enter a new key to replace it.';
                        }
                        if(d.data.system_prompt) document.getElementById('k-persona').value = d.data.system_prompt;
                    }
                } catch(e){}
            },
            saveVault: async function() {
                const payload = { action:'save', persona:document.getElementById('k-persona').value };
                ['groq','openai','gemini','xai','nvidia','quantum','featherless','zerothink_api'].forEach(k => {
                    const el = document.getElementById('k-'+k);
                    if(el && !el.disabled && el.value.trim() !== '') payload[k] = el.value;
                });
                const serperEl = document.getElementById('k-serper');
                if(serperEl && serperEl.value.trim() !== '') payload.serper = serperEl.value;
                const ibmQuantumEl = document.getElementById('k-ibm-quantum');
                if(ibmQuantumEl && ibmQuantumEl.value.trim() !== '') payload.ibm_quantum = ibmQuantumEl.value;
                payload.ibm_instance = document.getElementById('k-ibm-instance').value;
                payload.ibm_region = document.getElementById('k-ibm-region').value;
                try {
                    await ztVaultRequest(payload);
                    ['groq','openai','gemini','xai','nvidia','quantum','featherless','zerothink_api','ibm-quantum','serper'].forEach(k => {
                        const el = document.getElementById('k-'+k);
                        if(el && el.type === 'password') el.value = '';
                    });
                    alert("Saved"); document.getElementById('vault-modal').style.display='none';
                } catch (e) {
                    alert('Vault save failed: ' + e.message);
                }
            },
            wipeVault: async function() {
                if(!confirm("Delete all keys?")) return;
                try {
                    await ztVaultRequest({ action: 'wipe' });
                    alert("Wiped");
                } catch (e) {
                    alert('Vault wipe failed: ' + e.message);
                }
            },
            submitFeedback: async function() {
                const msg = document.getElementById('f-message').value.trim();
                if(!msg) return alert("Please enter a message.");
                try {
                    const res = await fetch('/api_feedback.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': ZT_SESSION_CSRF_TOKEN
                        },
                        body: JSON.stringify({ message: msg, csrf_token: ZT_SESSION_CSRF_TOKEN })
                    });
                    const d = await res.json();
                    if(d.status === 'success') {
                        alert("Feedback received. The Architect has been notified.");
                        document.getElementById('f-message').value = '';
                        document.getElementById('feedback-modal').style.display = 'none';
                    } else {
                        alert("Error: " + d.message);
                    }
                } catch(e) { alert("Submission failed."); }
            }
        };
        
        document.getElementById('zero-input').addEventListener('keypress', (e)=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault(); window.Zero.send();}});
        
        window.Zero.init();
        window.Zero.syncZeroThinkState();
        
        const canvas = document.getElementById('matrix-canvas');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        const drops = Array(Math.floor(canvas.width/14)).fill(1);
        function draw() {
            ctx.fillStyle = 'rgba(2, 2, 8, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#0033cc';
            ctx.font = '14px monospace';
            drops.forEach((y, i) => {
                const text = Math.random() > 0.5 ? '0' : '1';
                ctx.fillText(text, i*14, y*14);
                if(y*14 > canvas.height && Math.random() > 0.975) drops[i] = 0;
                drops[i]++;
            });
        }
        setInterval(draw, 50);
        window.onresize = () => { canvas.width=window.innerWidth; canvas.height=window.innerHeight; };
    </script>
</body>
</html>

