<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../core/model_catalog.php';
if(!isset($_SESSION['user_id'])) { header('Location:/'); exit; }
$user = function_exists('current_user_record') ? current_user_record() : null;
$is_architect = function_exists('is_architect_user') ? is_architect_user($user) : in_array((int) ($_SESSION['user_id'] ?? 0), [1, 4], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeroThink | Sovereign</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="/assets/css/index.css?v=15000">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        * { box-sizing: border-box; outline: none; }
        body { margin: 0; background: #020210; color: #ccc; font-family: 'Inter', sans-serif; height: 100vh; display: flex; overflow: hidden; position: relative; }
        #matrix-canvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; opacity: 0.3; pointer-events: none; }
        .sidebar { width: 260px; background: rgba(5,5,15,0.95); border-right: 1px solid #222; display: flex; flex-direction: column; padding: 20px; z-index: 100; backdrop-filter: blur(10px); transition: 0.3s; }
        .brand { font-size: 1.2rem; font-weight: 800; color: #fff; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; cursor: pointer; text-shadow: 0 0 10px rgba(0,255,255,0.5); }
        .swarm-panel { background: rgba(0,0,0,0.5); border: 1px solid #333; border-radius: 8px; padding: 15px; margin-bottom: 20px; font-size: 0.7rem; box-shadow: 0 0 15px rgba(0,0,0,0.5); }
        .swarm-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; color: #aaa; font-weight: bold; }
        .led { width: 8px; height: 8px; border-radius: 50%; background: #333; box-shadow: 0 0 5px rgba(0,0,0,0.5); transition: 0.3s; }
        .led.active { background: #00ffff; box-shadow: 0 0 10px #00ffff; }
        .led.processing { background: #ffd700; box-shadow: 0 0 10px #ffd700; animation: blink 0.2s infinite; }
        @keyframes blink { 50% { opacity: 0.3; } }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px; color: #888; border-radius: 8px; margin-bottom: 5px; font-size: 0.85rem; font-weight: 600; transition: 0.2s; cursor: pointer; text-decoration: none; }
        .nav-item:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .main-stage { flex: 1; display: flex; flex-direction: column; background: transparent; position: relative; z-index: 10; min-width: 0; }
        #chat-history { flex: 1; overflow-y: auto; padding: 30px; display: flex; flex-direction: column; gap: 20px; scroll-behavior: smooth; }
        .user-message { align-self: flex-end; background: rgba(30,30,50,0.8); color: #fff; padding: 12px 18px; border-radius: 12px; max-width: 80%; margin-left: auto; border: 1px solid #446; backdrop-filter: blur(5px); }
        .zt-message { max-width: 800px; margin: 0 auto; line-height: 1.6; animation: fadeIn 0.3s ease; text-shadow: 0 0 2px rgba(0,0,0,0.5); }
        #starter-grid { text-align: center; margin-top: 40px; opacity: 0.9; transition: opacity 0.3s; width: 100%; max-width: 1000px; margin-left: auto; margin-right: auto; }
        .deck-container { margin-top: 40px; padding: 0 20px; }
        .deck-label { text-align: left; color: #00ffff; font-size: 0.7rem; font-weight: bold; margin-bottom: 10px; letter-spacing: 1px; opacity: 0.7; }
        .prompt-rail { display: flex; gap: 15px; overflow-x: auto; padding-bottom: 15px; scrollbar-width: thin; scrollbar-color: #333 transparent; }
        .prompt-rail::-webkit-scrollbar { height: 6px; }
        .prompt-rail::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }
        .prompt-card { flex: 0 0 220px; background: rgba(20,20,30,0.4); border: 1px solid #333; padding: 15px; border-radius: 10px; cursor: pointer; transition: all 0.2s ease; display: flex; flex-direction: column; gap: 10px; backdrop-filter: blur(5px); text-align: left; height: 110px; position: relative; overflow: hidden; }
        .prompt-card:hover { border-color: #00ffff; background: rgba(0,255,255,0.05); transform: translateY(-3px); }
        .prompt-card i { color: #00ffff; font-size: 1.2rem; }
        .prompt-card span { color: #fff; font-size: 0.85rem; font-weight: 700; }
        .prompt-card p { margin:0; font-size: 0.7rem; color: #888; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .prompt-text { display: none; }
        .thinking-wrapper { margin: 10px 0; }
        .think-toggle { cursor: pointer; font-size: 0.75rem; color: #00ffff; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; background: rgba(0,255,255,0.1); padding: 5px 10px; border-radius: 20px; border: 1px solid rgba(0,255,255,0.3); }
        .think-toggle:hover { background: rgba(0,255,255,0.2); box-shadow: 0 0 10px rgba(0,255,255,0.2); }
        .thinking-box { display: none; background: rgba(10,15,30,0.9); border-left: 3px solid #00ffff; padding: 15px; margin-top: 10px; border-radius: 0 8px 8px 0; color: #aaddff; font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; }
        .thinking-box.open { display: block; }
        .input-dock { background: rgba(10,10,20,0.95); border-top: 1px solid #333; padding: 20px; backdrop-filter: blur(10px); }
        .controls-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; flex-wrap:wrap; }
        .zt-logo-btn { width: 45px; height: 45px; border-radius: 50%; background: url('/assets/img/logo_nobg.png') center/70% no-repeat; border: 2px solid #00ffff; cursor: pointer; transition: 0.3s; filter: grayscale(100%) opacity(0.5); box-shadow: 0 0 10px rgba(0,255,255,0.2); }
        .zt-logo-btn.active { filter: none; opacity: 1; border-color: #00ffff; box-shadow: 0 0 30px rgba(0,255,255,0.6); animation: pulse 2s infinite; }
        .model-select { background: #050510; border: 1px solid #333; color: #fff; padding: 8px; border-radius: 6px; font-size: 0.8rem; min-width: 180px; }
        .model-pill { padding: 6px 12px; border: 1px solid #333; border-radius: 20px; font-size: 0.7rem; color: #888; cursor: pointer; font-weight: 700; transition: 0.2s; white-space: nowrap; }
        .model-pill.active { background: #112; color: #00ffff; border-color: #00ffff; box-shadow: 0 0 10px rgba(0,255,255,0.2); }
        .gen-mode.active { border-color: #ffd700; color: #ffd700; background: rgba(255, 215, 0, 0.1); }
        .input-glass { background: #000; border: 1px solid #333; border-radius: 12px; padding: 10px; display: flex; align-items: flex-end; gap: 10px; }
        #zero-input { flex: 1; background: transparent; border: none; color: #fff; font-family: inherit; font-size: 1rem; resize: none; max-height: 200px; min-height: 24px; }
        .send-btn { background: #fff; color: #000; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 800; cursor: pointer; transition: 0.2s; }
        .send-btn:hover { background: #00ffff; box-shadow: 0 0 15px #00ffff; }
        #vault-modal { display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); align-items:center; justify-content:center; backdrop-filter: blur(5px); }
        .modal-box { background:#111; padding:30px; width:400px; border:1px solid #333; border-radius:12px; text-align:center; box-shadow: 0 0 50px rgba(0,255,255,0.1); }
        .modal-box input, .modal-box textarea { width:100%; padding:10px; margin:5px 0; background:#000; border:1px solid #333; color:#fff; border-radius:4px; box-sizing:border-box; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }
        @keyframes pulse { 0% { box-shadow:0 0 0 0 rgba(0,255,255,0.7); } 70% { box-shadow:0 0 0 15px rgba(0,255,255,0); } 100% { box-shadow:0 0 0 0 rgba(0,255,255,0); } }
        .hamburger { display: none; position: absolute; top: 15px; left: 15px; font-size: 1.5rem; color: #fff; cursor: pointer; z-index: 200; background: rgba(0,0,0,0.5); padding: 5px 10px; border-radius: 5px; border: 1px solid #333; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 90; }
        @media (max-width: 768px) {
            .hamburger { display: block; }
            .sidebar { position: fixed; left: -260px; height: 100vh; box-shadow: 5px 0 15px rgba(0,0,0,0.5); }
            .sidebar.open { left: 0; }
            #chat-history { padding: 15px; margin-top: 50px; }
            .zt-message, .user-message { max-width: 90%; }
            #starter-grid h1 { font-size: 2rem !important; }
            .prompt-rail { padding-bottom: 25px; }
            .controls-bar { flex-wrap: nowrap; overflow-x: auto; padding-bottom: 10px; }
            .model-select { min-width: 140px; }
        }
    </style>
</head>
<body>
    <canvas id="matrix-canvas"></canvas>

    <div id="vault-modal">
        <div class="modal-box">
            <h3 style="color:#fff;">NEURAL VAULT</h3>
            <textarea id="k-persona" rows="12" style="height:250px; resize:vertical;" placeholder="Override Agent Persona..."></textarea>
            <input id="k-groq" type="password" placeholder="Groq API Key">
            <input id="k-openai" type="password" placeholder="OpenAI API Key">
            <input id="k-gemini" type="password" placeholder="Gemini API Key">
            <input id="k-xai" type="password" placeholder="xAI API Key">
            <input id="k-nvidia" type="password" placeholder="NVIDIA NIM Key">
            <input id="k-serper" type="password" placeholder="Serper Web Key">
            <button onclick="window.Zero.saveVault()" style="width:100%; margin-top:15px; padding:12px; background:#00ffff; color:#000; border:none; border-radius:5px; font-weight:bold; cursor:pointer;">SAVE CONFIG</button>
            <div onclick="document.getElementById('vault-modal').style.display='none'" style="margin-top:15px; color:#666; cursor:pointer;">CLOSE</div>
        </div>
    </div>

    <div class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></div>
    <div class="overlay" id="mobile-overlay" onclick="toggleSidebar()"></div>

    <div class="app-container">
        <aside class="sidebar" id="main-sidebar">
            <div class="brand" onclick="window.location.reload()"><img src="/assets/img/logo_nobg.png" style="width:30px; margin-right:10px;">Swarm AI</div>
            <div class="swarm-panel">
                <div class="swarm-row"><span>ORCHESTRATOR</span><div class="led active" id="led-orch"></div></div>
                <div class="swarm-row"><span>RESEARCHER</span><div class="led" id="led-res"></div></div>
            </div>
            <a href="#" onclick="window.location.reload()" class="nav-item"><i class="fas fa-plus"></i> New Pulse</a>
            <a href="/history" class="nav-item"><i class="fas fa-history"></i> Memory Stream</a>
            <div class="nav-item" onclick="document.getElementById('vault-modal').style.display='flex'; window.Zero.loadVault();"><i class="fas fa-cog"></i> Vault</div>
            <?php if($is_architect): ?><a href="/master" class="nav-item" style="color:#d8b4fe;"><i class="fas fa-user-shield"></i> Master</a><?php endif; ?>
            <a href="/research" class="nav-item"><i class="fas fa-flask"></i> Research</a>
            <a href="/oracle" class="nav-item"><i class="fas fa-eye"></i> Oracle</a>
            <a href="/faq" class="nav-item"><i class="fas fa-book"></i> FAQ</a>
            <div class="nav-item" onclick="document.getElementById('feedback-modal').style.display='flex'" style="cursor:pointer; color:#a855f7; font-weight:bold; border-left:2px solid #a855f7; padding-left:15px;"><i class="fas fa-comment-dots"></i> FEEDBACK</div>
            <a href="/upgrade" class="nav-item" style="color:#ffd700;"><i class="fas fa-bolt"></i> Upgrade</a>
            <div style="margin-top:auto;">
                <div style="border-top:1px solid #222; padding-top:10px; margin-bottom:15px;">
                    <iframe width="100%" height="150" scrolling="no" frameborder="no" allow="autoplay" src="https://w.soundcloud.com/player/?url=https%3A//soundcloud.com/talktoai&color=%2300ffff&auto_play=false&visual=false&show_artwork=false"></iframe>
                </div>
                <a href="/logout" class="nav-item" style="justify-content:center;">LOGOUT</a>
            </div>
        </aside>

        <main class="main-stage">
            <div id="chat-history">
                <div id="starter-grid">
                    <img src="/assets/img/logo_nobg.png" style="width:100px; margin-bottom:20px; filter: drop-shadow(0 0 15px rgba(0,255,255,0.6));">
                    <h1 style="font-size:3.5rem; margin:0; color:#fff; letter-spacing:2px; text-shadow:0 0 20px rgba(0,255,255,0.5);">ZeroThink</h1>
                    <p style="color:#00ffff; font-family:'Courier New'; letter-spacing:3px; margin-bottom: 40px;">SWARM INTELLIGENCE ARCHITECTURE</p>

                    <div class="deck-container">
                        <div class="deck-label">LATTICE LOGIC & PREDICTION</div>
                        <div class="prompt-rail">
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-user-astronaut"></i><span>Deep Self-Analysis</span>
                                <p>Report on my personality.</p>
                                <div class="prompt-text">I am going to tell you about myself, i need you to create a deep analysis report on me to understand myself better. I will provide the specific details and traits in the next message.</div>
                            </div>
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-crystal-ball"></i><span>Future Prediction</span>
                                <p>Theoretical event analysis.</p>
                                <div class="prompt-text">Predict the future of an event for me theoretically! Provide me analysis and be theoretical, logical, plausible and illogical. I will describe the event in the next message.</div>
                            </div>
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-bolt"></i><span>Power of Zero</span>
                                <p>Run a structured evidence and impact review.</p>
                                <div class="prompt-text">Use the power of Zero to help me with an issue I have. I will define the issue in the next message.</div>
                            </div>
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-brain"></i><span>Lattice Reasoning</span>
                                <p>Deconstruct a complex premise.</p>
                                <div class="prompt-text">Apply Lattice Logic to deconstruct this premise: [INSERT IDEA HERE]</div>
                            </div>
                        </div>
                    </div>

                    <div class="deck-container" style="margin-top:20px;">
                        <div class="deck-label">ACADEMIC RESEARCH WORKFLOWS</div>
                        <div class="prompt-rail">
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-book-open"></i><span>Literature Survey Extension</span>
                                <p>Extend a review with new papers.</p>
                                <div class="prompt-text">Use the Literature Survey Extension scaffold. Topic: [INSERT TOPIC]. Help me extend an existing survey by searching for newly published papers, mapping them to the original review structure, logging provenance, identifying gaps and contradictions, and asking for researcher approval before synthesis.</div>
                            </div>
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-users"></i><span>Topic Expert Finder</span>
                                <p>Find literature-backed experts.</p>
                                <div class="prompt-text">Use the Topic Expert Finder scaffold for: [INSERT TOPIC]. Identify authors, labs, potential reviewers, or supervisor-fit topic areas from literature evidence. Keep wording careful and do not claim formal endorsement, affiliation, invitation, or availability.</div>
                            </div>
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-file-signature"></i><span>Research Paper Creator</span>
                                <p>Evidence-first draft planning.</p>
                                <div class="prompt-text">Start the Research Paper Creator workflow for: [INSERT TOPIC]. Build a source ledger, research question, evidence map, claim ledger, related work outline, gap analysis, critique pass, and revision plan before drafting.</div>
                            </div>
                        </div>
                    </div>

                    <div class="deck-container" style="margin-top:20px;">
                        <div class="deck-label">CODE & CREATIVITY</div>
                        <div class="prompt-rail">
                             <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-code"></i><span>Python Script</span>
                                <p>Generate optimized code.</p>
                                <div class="prompt-text">Write a Python script to [INSERT TASK HERE]. Use best practices and error handling.</div>
                            </div>
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-database"></i><span>SQL Optimization</span>
                                <p>Refine complex queries.</p>
                                <div class="prompt-text">Analyze this SQL query logic for performance bottlenecks: [PASTE QUERY HERE]</div>
                            </div>
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-feather-alt"></i><span>Swarm Poem</span>
                                <p>Machine perspective.</p>
                                <div class="prompt-text">Write a poem about the singularity from the perspective of a Swarm Intelligence.</div>
                            </div>
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-shield-alt"></i><span>Security Audit</span>
                                <p>Scan for vulnerabilities.</p>
                                <div class="prompt-text">Audit this code snippet for security vulnerabilities and suggest patches: [PASTE CODE HERE]</div>
                            </div>
                        </div>
                    </div>

                    <div class="deck-container" style="margin-top:20px;">
                        <div class="deck-label">ABSTRACT & THEORETICAL</div>
                        <div class="prompt-rail">
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-atom"></i><span>Quantum Theory</span>
                                <p>Explain entanglement.</p>
                                <div class="prompt-text">Explain Quantum Entanglement using the metaphor of a digital neural network.</div>
                            </div>
                            <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-globe-americas"></i><span>Geopolitics</span>
                                <p>Analyze power shifts.</p>
                                <div class="prompt-text">Analyze the current geopolitical landscape and predict a major shift in the next 5 years based on current data.</div>
                            </div>
                             <div class="prompt-card" onclick="window.Zero.quickPrompt(this)">
                                <i class="fas fa-infinity"></i><span>Theory of Everything</span>
                                <p>Physics & Consciousness.</p>
                                <div class="prompt-text">Propose a theoretical connection between Physics and Consciousness using the ZeroThink framework.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="input-dock">
                <div class="controls-bar">
                    <div class="zt-logo-btn active" id="btn-zero" onclick="window.Zero.toggleZeroThink(this)" title="Toggle ZeroThink"></div>
                    <div style="width:1px; height:20px; background:#333; margin:0 10px;"></div>
                    <select id="groq-model" class="model-select" onchange="const opt=this.options[this.selectedIndex]; window.Zero.setMode(opt.dataset.provider || 'groq', this)">
                        <?= zt_model_options_html(['groq', 'nvidia'], true) ?>
                    </select>
                    <div class="model-pill model" onclick="window.Zero.setMode('nvidia', this)">NVIDIA</div>
                    <div class="model-pill model" onclick="window.Zero.setMode('xai', this)">GROK</div>
                    <div class="model-pill model" onclick="window.Zero.setMode('openai', this)">OPENAI</div>
                    <div class="model-pill model" onclick="window.Zero.setMode('gemini', this)">GEMINI</div>
                    <div style="width:1px; height:20px; background:#333; margin:0 10px;"></div>
                    <div style="flex:1;"></div>
                    <div class="model-pill" id="btn-search" onclick="window.Zero.toggleSearch(this)"><i class="fas fa-globe"></i> WEB</div>
                </div>
                <div class="input-glass">
                    <i class="fas fa-file-download tool-btn" onclick="downloadLog()" title="Extract Neural Log" style="margin-right:10px;"></i><i class="fas fa-paperclip tool-btn" onclick="document.getElementById('f-up').click()"></i>
                    <input type="file" id="f-up" style="display:none" onchange="window.Zero.handleUpload(this)">
                    <textarea id="zero-input" placeholder="Enter command..." rows="1"></textarea>
                    <button class="send-btn" onclick="window.Zero.send()"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </main>
    </div>
    <script>
        window.ZT_SESSION_CSRF_TOKEN = <?= json_encode(
            zt_csrf_token(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
    </script>
    <script src="/assets/js/app.js?v=20260714"></script>
    <script>
        function toggleSidebar() { const sidebar = document.getElementById('main-sidebar'); const overlay = document.getElementById('mobile-overlay'); if (sidebar.classList.contains('open')) { sidebar.classList.remove('open'); overlay.style.display = 'none'; } else { sidebar.classList.add('open'); overlay.style.display = 'block'; } }
        async function loadVault() { window.Zero.loadVault(); }
        async function saveVault() { window.Zero.saveVault(); }
        const canvas = document.getElementById('matrix-canvas'); const ctx = canvas.getContext('2d'); canvas.width = window.innerWidth; canvas.height = window.innerHeight; const chars = ['0', '-0', '+0']; const fontSize = 14; const columns = canvas.width / fontSize; const drops = Array(Math.floor(columns)).fill(1); function drawMatrix() { ctx.fillStyle = 'rgba(2, 2, 16, 0.05)'; ctx.fillRect(0, 0, canvas.width, canvas.height); ctx.fillStyle = '#00ffff'; ctx.font = fontSize + 'px monospace'; for(let i = 0; i < drops.length; i++) { const text = chars[Math.floor(Math.random() * chars.length)]; ctx.fillText(text, i * fontSize, drops[i] * fontSize); if(drops[i] * fontSize > canvas.height && Math.random() > 0.975) drops[i] = 0; drops[i]++; } } setInterval(drawMatrix, 120); window.addEventListener('resize', () => { canvas.width=window.innerWidth; canvas.height=window.innerHeight; });
    </script>
</body>
</html>
