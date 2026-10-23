<?php require_once __DIR__ . '/app/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>ZeroThink | System Documentation</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg: #020205; --panel: #0a0a10; --border: #222; --accent: #a855f7; --cyan: #00ffff; --text: #e0e0e0; --green: #00ff9d; --red: #ff4444; }
        * { box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', system-ui, sans-serif; margin: 0; line-height: 1.6; }
        .container { max-width: 1400px; margin: 0 auto; display: flex; min-height: 100vh; }
        .sidebar { width: 300px; position: sticky; top: 0; height: 100vh; padding: 40px 20px; border-right: 1px solid var(--border); overflow-y: auto; background: rgba(5,5,10,0.95); backdrop-filter: blur(10px); }
        .content { flex: 1; min-width: 0; padding: 60px; overflow-y: auto; }
        h1 { margin: 0 0 10px 0; font-size: 3rem; color: #fff; letter-spacing: -1px; text-transform: uppercase; }
        h2 { margin: 60px 0 30px 0; font-size: 1.8rem; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 15px; display: flex; align-items: center; gap: 15px; }
        h3 { margin: 30px 0 15px 0; font-size: 1.3rem; color: var(--cyan); }
        p { color: #aaa; margin-bottom: 20px; font-size: 1.05rem; }
        strong { color: #fff; font-weight: 700; }
        .nav-link { display: flex; align-items: center; gap: 10px; padding: 12px 15px; color: #888; text-decoration: none; border-radius: 6px; margin-bottom: 5px; transition: 0.2s; font-size: 0.95rem; font-weight: 500; border-left: 2px solid transparent; }
        .nav-link:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .back-btn { display: flex; align-items: center; gap: 10px; margin-bottom: 40px; color: #fff; text-decoration: none; font-weight: bold; padding: 12px 20px; background: linear-gradient(90deg, #222, #111); border: 1px solid var(--border); border-radius: 8px; transition: 0.3s; }
        .back-btn:hover { border-color: var(--accent); box-shadow: 0 0 15px rgba(168,85,247,0.2); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .card { background: var(--panel); border: 1px solid var(--border); padding: 30px; border-radius: 12px; transition: 0.3s; position: relative; overflow: hidden; }
        .card:hover { border-color: var(--cyan); transform: translateY(-3px); }
        .card-icon { font-size: 2.5rem; margin-bottom: 20px; color: var(--accent); }
        .card-title { font-weight: 800; font-size: 1.2rem; color: #fff; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
        .matrix-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 40px; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .matrix-table th { background: #151520; color: #fff; padding: 18px; text-align: left; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid var(--border); font-size: 0.85rem; }
        .matrix-table td { padding: 15px 18px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #bbb; font-size: 0.95rem; background: rgba(0,0,0,0.2); }
        .tag { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .tag.free { background: rgba(0,255,157,0.1); color: var(--green); border: 1px solid rgba(0,255,157,0.2); }
        .tag.pro { background: rgba(168,85,247,0.1); color: var(--accent); border: 1px solid rgba(168,85,247,0.2); }
        details { background: #0e0e12; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 15px; overflow: hidden; transition: 0.3s; }
        details[open] { border-color: var(--accent); box-shadow: 0 0 20px rgba(0,0,0,0.5); }
        summary { padding: 18px 25px; cursor: pointer; font-weight: 700; color: #fff; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none; font-size: 1.1rem; }
        .faq-body { padding: 25px; border-top: 1px solid var(--border); color: #aaa; line-height: 1.8; background: #050508; }
        .btn-link { color: var(--cyan); text-decoration: none; font-weight: bold; border-bottom: 1px dashed var(--cyan); padding-bottom: 2px; }
        .btn-link:hover { color: #fff; border-bottom-style: solid; }
        .doc-tools { margin:28px 0 8px; padding:18px; border:1px solid var(--border); border-radius:10px; background:#08080d; }
        .doc-search { width:100%; border:1px solid #30303d; border-radius:8px; background:#030307; color:#fff; padding:14px 16px; font:inherit; }
        .doc-search:focus { outline:2px solid rgba(0,255,255,.22); border-color:var(--cyan); }
        .doc-search-status { margin:10px 0 0; color:#7e8b99; font-size:.86rem; }
        .command-block { display:block; margin-top:12px; padding:14px; border:1px solid #27313d; border-radius:8px; background:#030609; color:#ccecff; white-space:pre-wrap; overflow-wrap:anywhere; }
        @media (max-width: 1000px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; border-right: none; border-bottom: 1px solid var(--border); }
            .content { padding: 36px 22px 60px; }
            .matrix-table, .matrix-table thead, .matrix-table tbody, .matrix-table tr, .matrix-table th, .matrix-table td { display:block; width:100%; }
            .matrix-table thead { display:none; }
            .matrix-table td { padding:11px 14px; overflow-wrap:anywhere; }
        }
        @media (max-width: 560px) {
            .sidebar { padding:24px 14px; }
            .content { padding:30px 15px 52px; }
            h1 { font-size:2.15rem; overflow-wrap:anywhere; }
            h2 { margin-top:46px; font-size:1.45rem; align-items:flex-start; }
            .card { padding:22px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="sidebar">
        <a href="/studio" class="back-btn"><i class="fas fa-microchip"></i> RETURN TO STUDIO</a>
        <div style="font-size:0.75rem; text-transform:uppercase; color:#666; font-weight:800; margin-bottom:15px; letter-spacing:1px; padding-left:15px;">FAQ & Help</div>
        <a href="#latest-updates" class="nav-link"><i class="fas fa-bolt"></i> Latest Updates</a>
        <a href="#matrix" class="nav-link"><i class="fas fa-layer-group"></i> Model Matrix</a>
        <a href="#tiers" class="nav-link"><i class="fas fa-unlock-alt"></i> Pro vs Free</a>
        <a href="#token" class="nav-link"><i class="fas fa-coins"></i> $AI Utility</a>
        <a href="#oracle" class="nav-link"><i class="fas fa-chart-line"></i> Oracle Desk</a>
        <a href="#quantum" class="nav-link"><i class="fas fa-atom"></i> Quantum Lane</a>
        <a href="#zero" class="nav-link"><i class="fas fa-brain"></i> ZeroThink Logic</a>
        <a href="#cli" class="nav-link"><i class="fas fa-terminal"></i> ZeroThink CLI</a>
        <a href="#research-workbench" class="nav-link"><i class="fas fa-file-lines"></i> Research Workbench</a>
        <a href="#freewebpanel-cli" class="nav-link"><i class="fas fa-server"></i> FreeWebPanel CLI</a>
        <a href="#library" class="nav-link"><i class="fas fa-folder-tree"></i> Zero Library + Labs</a>
        <a href="#zmath" class="nav-link"><i class="fas fa-infinity"></i> ZMath Agent</a>
        <a href="#apis" class="nav-link"><i class="fas fa-key"></i> API Configuration</a>
    </div>

    <div class="content">
        <h1>FAQ & Documentation</h1>
        <p style="font-size:1.2rem; color:#888; max-width:800px;">
            Plain-language answers and technical reference for the <strong>ZeroThink Sovereign Architecture</strong>. 
            Multi-provider routing, Oracle analysis, Pro-only Quantum lane, $AI holder access, no-token local library context, large code ingest, and the cross-platform CLI stack.
        </p>
        <p style="margin-top:-6px; max-width:920px; color:#c3d2e4; background:rgba(0,255,255,0.05); border:1px solid rgba(0,255,255,0.12); border-radius:14px; padding:14px 16px;">
            <strong>Default route clarity:</strong> ZeroThink still defaults to <strong>Groq GPT-OSS 120B</strong> for the website and the main Zero lane. <strong>OpenAI GPT-5.5</strong> and the <strong>GPT-5.4</strong> family are additional Pro options when you explicitly switch to the OpenAI provider. <strong>OpenZero Local</strong> is a Pro lane for small self-hosted models under 15 GB: when the shared ZeroThink OpenZero gateway is enabled, Pro accounts can try it for 5 messages, then should install OpenZero and save their own OpenZero Machine API Key in Neural Vault through a server-side registered gateway. <strong>Featherless</strong> is the hosted large-model lane and requires the user's own Featherless key. <strong>Quantum</strong> remains its own Pro lane built on <strong>Groq GPT-OSS 120B + IonQ Quantum Cloud</strong>, with a separate <strong>IBM Qiskit</strong> lane for Qiskit calculation packets and optional IBM Quantum Runtime context.
        </p>
        <p style="max-width:920px; color:#e6fff8; background:rgba(0,255,157,0.06); border:1px solid rgba(0,255,157,0.16); border-radius:14px; padding:14px 16px;">
            <strong>Latest public update:</strong> ZeroThink documentation now highlights the OpenZero bridge, limited shared OpenZero trial behavior, user-owned OpenZero Machine API Keys, Z-Spark-aware OpenZero nodes, optional Voicebox direction, AI PDF Creator guidance, and the public-safe TalkToAI updates/changelog page. Read the ecosystem changelog at <a class="btn-link" href="https://docs.talktoai.org/updates/" target="_blank" rel="noopener noreferrer">docs.talktoai.org/updates/</a>.
        </p>
        <div class="doc-tools">
            <label for="faq-search" style="display:block; color:#fff; font-weight:800; margin-bottom:9px;">Search this documentation</label>
            <input class="doc-search" id="faq-search" type="search" placeholder="Try: research paper, source upload, source ledger, FreeWebPanel, Zero Library, Quantum, API key">
            <p class="doc-search-status" id="faq-search-status">All documentation sections are visible.</p>
        </div>

        <section id="latest-updates">
            <h2><i class="fas fa-bolt"></i> Latest Public Updates</h2>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-network-wired"></i></div>
                    <div class="card-title">OpenZero Bridge</div>
                    <p>ZeroThink can use OpenZero as a local/self-hosted AI lane where the bridge is configured. Shared gateway access should stay limited; continued use belongs on a user-owned OpenZero node key saved in Neural Vault.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-diagram-project"></i></div>
                    <div class="card-title">Z-Spark Awareness</div>
                    <p>OpenZero nodes may support Z-Spark, a custom draft/verify route where a small local model drafts and the active model verifies or rewrites. It falls back safely when a node is not ready.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-volume-high"></i></div>
                    <div class="card-title">Voicebox Direction</div>
                    <p>OpenZero can connect to Voicebox when a user installs that local voice service separately. Voice features remain optional and should be enabled only on machines prepared for voice workflows.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-file-pdf"></i></div>
                    <div class="card-title">PDF Creator</div>
                    <p>PDF requests work best when the user states audience, sections, evidence needs, caveats, and export purpose. Serious reports should be reviewed before sharing.</p>
                </div>
            </div>
        </section>

        <section id="tiers">
            <h2><i class="fas fa-star"></i> Access Tiers</h2>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-user"></i></div>
                    <div class="card-title">Free Tier</div>
                    <p>High-speed access to open-weights models. Ideal for coding and rapid inquiry.</p>
                    <ul style="list-style:none; padding:0; color:#aaa; font-size:0.95rem;">
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--green);"></i> <strong>Groq Cloud:</strong> GPT-OSS 120B/20B, Compound, Qwen 3.6, Llama 3.1 8B, ALLaM 2</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--green);"></i> <strong>NVIDIA NIM:</strong> Qwen 3 Coder 480B, Gemma 4 31B, DeepSeek V3.2, Codestral, Devstral</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--green);"></i> <strong>CLI:</strong> Linked-account login or no-Google direct mode, folder-only or machine-wide access, SSH profiles, remote file workspace tools, Playwright MCP, named agents, autopilot runs, local plan board, verified self-update, local or Vault-backed provider keys, and local Z-Pepper password protection for files or folders</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-times" style="color:var(--red);"></i> <strong>Quantum Lane:</strong> Pro only</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-times" style="color:var(--red);"></i> <strong>ZeroThink Logic:</strong> Disabled</li>
                    </ul>
                </div>
                <div class="card" style="border-color:var(--accent);">
                    <div class="card-icon"><i class="fas fa-crown"></i></div>
                    <div class="card-title">Sovereign Pro</div>
                    <p>Full access to <strong>ZeroThink Logic</strong>, Pro routing, Quantum Zero, and proprietary closed-source model lanes.</p>
                    <ul style="list-style:none; padding:0; color:#aaa; font-size:0.95rem;">
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--accent);"></i> <strong>ZeroThink Logic:</strong> Active (Reasoning Layer)</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--accent);"></i> <strong>xAI:</strong> Grok 4.3, Grok Build 0.1, Grok Imagine API</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--accent);"></i> <strong>OpenAI:</strong> GPT-5.5, GPT-5.4, GPT-5.4 Mini, GPT-5.4 Nano, GPT-5 family fallbacks</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--accent);"></i> <strong>Google:</strong> Gemini 3.5 Flash, Gemini 3.1 Pro, Gemini 3.1 Flash-Lite, Gemini 2.5 fallbacks</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--accent);"></i> <strong>OpenZero:</strong> when the shared gateway is enabled, 5 shared local messages on ZeroThink, then bring your own registered OpenZero node key for continued local/self-hosted use</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--accent);"></i> <strong>Featherless:</strong> hosted large-model lane for TalkToAI ZERO, GLM 5.2, GLM 4.5, Qwen3 Coder, DeepSeek, and Kimi using the user's own Featherless key</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--accent);"></i> <strong>Quantum:</strong> Groq GPT-OSS 120B with IonQ Quantum Cloud telemetry and optional simulator job proof</li>
                        <li style="margin-bottom:10px;"><i class="fas fa-check" style="color:var(--accent);"></i> <strong>Billing:</strong> Pay with PayPal or verify a Solana wallet holding at least $10 worth of $AI. Token-holder access uses a signed wallet message, never asks for a seed phrase, and is automatically rechecked. If holdings are sold below the threshold, token-holder Pro access is removed. Monthly payments are non-refundable. Yearly refund requests are within 7 days via shaf@talktoai.org</li>
                    </ul>
                </div>
            </div>
        </section>

        <section id="token">
            <h2><i class="fas fa-coins"></i> $AI Utility</h2>
            <p>
                The practical use case is simple: <strong>$AI is a hold-to-access utility path for ZeroThink Pro</strong>. Users can still pay with PayPal, but token holders can verify a Solana wallet and unlock Pro while they maintain enough verified holdings.
            </p>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-key"></i></div>
                    <div class="card-title">Access Utility</div>
                    <p>Hold at least <strong>$10 worth of $AI</strong> in a verified Solana wallet to activate ZeroThink Pro access. This unlocks the same Pro surface: ZeroThink Logic, Pro model lanes, Quantum Zero, Oracle improvements, and the wider builder stack.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-shield-halved"></i></div>
                    <div class="card-title">Proof Without Custody</div>
                    <p>Verification uses a one-time wallet signature. ZeroThink never asks for seed phrases, never moves tokens, and never requires a payment transaction for token-holder access. The wallet only signs a message proving ownership.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-rotate"></i></div>
                    <div class="card-title">Sell = Lose Access</div>
                    <p>Token-holder Pro is monitored. The server rechecks live holdings and live market value. If the wallet sells below the threshold, token-holder Pro access is automatically removed on the next scheduled check. PayPal Pro accounts are not affected by this token check.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-chart-simple"></i></div>
                    <div class="card-title">Degen Answer</div>
                    <p><strong>Use case:</strong> the coin is an access key for an AI product, not just a meme. Holding enough $AI unlocks a real software benefit, and selling below the floor removes that benefit. That creates a direct utility loop between the token and ZeroThink Pro.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-file-contract"></i></div>
                    <div class="card-title">Token Mint</div>
                    <p style="overflow-wrap:anywhere;">$AI Token CA: <strong>dHdkhZsmBQhXjQoKbkyajZ3Ff4W2XumGyVJX4jypump</strong></p>
                    <p><a href="/upgrade" class="btn-link">Verify holder access on the Upgrade page</a>.</p>
                    <p><a href="https://www.geckoterminal.com/solana/pools/3zx4GRkjpWPcnR5AVH3cU9TDm9nCr8j9tef6qApWfH3q?utm_source=coingecko&utm_medium=referral&utm_campaign=searchresults" class="btn-link" target="_blank" rel="noopener noreferrer">Track AI/SOL on GeckoTerminal</a>.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-triangle-exclamation"></i></div>
                    <div class="card-title">No Investment Promise</div>
                    <p>$AI holder access is a product utility feature. It is not financial advice, not a promise of profit, and not a guarantee of token price. Access depends on live wallet balance, market data, and the current ZeroThink eligibility rules.</p>
                </div>
            </div>
        </section>

        <section id="matrix">
            <h2><i class="fas fa-network-wired"></i> The Model Matrix</h2>
            <table class="matrix-table">
                <thead>
                    <tr><th>Provider</th><th>Model Name</th><th>Specs</th><th>Access</th><th>Best Use</th></tr>
                </thead>
                <tbody>
                    <tr><td><span style="color:#f55;">GROQ</span></td><td>GPT-OSS 120B / GPT-OSS 20B / Compound</td><td>Current fast cloud stack</td><td><span class="tag free">Free</span></td><td>General reasoning, Oracle, broad strategic work</td></tr>
                    <tr><td><span style="color:#f55;">GROQ</span></td><td>Qwen 3.6 27B / Llama 3.1 8B Instant / ALLaM 2 7B</td><td>Current Groq open-weight and specialist lane</td><td><span class="tag free">Free</span></td><td>Coding, structured analysis, fast iterations, bilingual Arabic-English work</td></tr>
                    <tr><td><span style="color:#f55;">GROQ</span></td><td>Legacy IDs: Llama 3.3 70B, Llama 4 Scout/Maverick, Qwen3 32B, Kimi K2</td><td>Hidden from pickers and routed to GPT-OSS 120B when old selections appear</td><td><span class="tag free">Free</span></td><td>Migration safety only; use GPT-OSS 120B or Qwen 3.6 for new work</td></tr>
                    <tr><td><span style="color:#76b900;">NVIDIA</span></td><td>Qwen 3 Coder 480B / Codestral 22B / Devstral 2</td><td>Heavy coding + instruct</td><td><span class="tag free">Free</span></td><td>Large code work, technical drafting, deep analysis</td></tr>
                    <tr><td><span style="color:#76b900;">NVIDIA</span></td><td>Gemma 4 31B / Gemma 3n E4B / DeepSeek V3.2 / Llama 3.3 70B</td><td>Current NVIDIA frontier set</td><td><span class="tag free">Free</span></td><td>Long-form reasoning, multilingual work, coding and research</td></tr>
                    <tr><td><span style="color:#fff;">xAI</span></td><td>Grok 4.3 / Grok Build 0.1 / Grok Imagine API</td><td>Current premium xAI line</td><td><span class="tag pro">Pro</span></td><td>Reasoning, coding, live premium use</td></tr>
                    <tr><td><span style="color:#4285F4;">GOOGLE</span></td><td>Gemini 3.5 Flash / Gemini 3.1 Pro / Gemini 3.1 Flash-Lite / Gemini 2.5</td><td>Multimodal + long-context</td><td><span class="tag pro">Pro</span></td><td>Large documents, deeper analysis, mixed text/media work</td></tr>
                    <tr><td><span style="color:#10a37f;">OPENAI</span></td><td>GPT-5.5 / GPT-5.4 / GPT-5.4 Mini / GPT-5.4 Nano</td><td>Current frontier + lower-cost variants</td><td><span class="tag pro">Pro</span></td><td>Optional premium lane when you switch away from the default Groq route</td></tr>
                    <tr><td><span style="color:#00ffff;">OPENZERO</span></td><td>OpenZero Ministral3 8B Runtime Agent / Gemma compatibility</td><td>Operator-owned local runtime; models and dependencies install separately</td><td><span class="tag pro">Pro</span></td><td>Local CPU tasks, small automation and research drafts through your configured OpenZero gateway; the historical shared trial exists only when explicitly configured</td></tr>
                    <tr><td><span style="color:#f0abfc;">FEATHERLESS</span></td><td>TalkToAI ZERO / GLM 5.2 / GLM 4.5 / Qwen3 Coder / DeepSeek / Kimi</td><td>Hosted OpenAI-compatible model lane; large models run on Featherless, not the local ZeroThink server</td><td><span class="tag pro">Pro</span></td><td>Hosted large-model experiments with the user's own Featherless account/key</td></tr>
                    <tr><td><span style="color:#d8b4fe;">QUANTUM</span></td><td>Groq GPT-OSS 120B + IonQ Cloud</td><td>Special research agent with backend telemetry and simulator job records</td><td><span class="tag pro">Pro</span></td><td>Quantum-assisted planning, hardware-aware research, backend-aware synthesis</td></tr>
                    <tr><td><span style="color:#8cbaff;">QISKIT</span></td><td>Groq GPT-OSS 120B + IBM Qiskit</td><td>Qiskit calculation packets with optional IBM Runtime/backend context</td><td><span class="tag pro">Pro</span></td><td>Bell/GHZ, Grover, QAOA, VQE, QFT, and prompt-seeded Zero pattern experiments</td></tr>
                </tbody>
            </table>
        </section>

        <section id="oracle">
            <h2><i class="fas fa-chart-line"></i> Oracle Desk</h2>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-coins"></i></div>
                    <div class="card-title">Crypto + General</div>
                    <p>The Oracle route is tuned for both crypto scenarios and broader strategic calls. Modes include <strong>crypto</strong>, <strong>general</strong>, <strong>launch</strong>, and <strong>risk</strong>.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-gauge-high"></i></div>
                    <div class="card-title">Default Engine</div>
                    <p>Oracle now defaults to <strong>Groq + GPT-OSS 120B</strong> for a strong speed / reasoning balance, while still letting you switch provider and model directly from the desk. Current Groq alternatives include Compound, GPT-OSS 20B, Qwen 3.6, Llama 3.1 8B, and ALLaM 2; older Groq IDs are kept only as migration aliases.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-wallet"></i></div>
                    <div class="card-title">Support The Project</div>
                    <p>The Oracle page includes a Solana support card so people who use the desk heavily can donate directly to the ZeroThink build.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-key"></i></div>
                    <div class="card-title">Direct Web Mode</div>
                    <p>If someone wants ZeroThink without Google login first, the lean browser route is exposed at <a href="/guest" class="btn-link">/guest</a>. That lane accepts local Groq or NVIDIA keys, keeps memory/history off, does not connect to the ZeroThink account/Vault, and stores the key only in the browser.</p>
                </div>
            </div>
        </section>

        <section id="quantum">
            <h2><i class="fas fa-atom"></i> Quantum Lane</h2>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-brain"></i></div>
                    <div class="card-title">Special Agent</div>
                    <p>Quantum is a special <strong>Pro</strong> agent inside Studio. It does not replace the normal default route. Instead, it switches into a separate research lane that reasons through <strong>Groq GPT-OSS 120B</strong> and then grounds that reasoning with <strong>IonQ Quantum Cloud</strong> account data, backend telemetry, and small simulator job records when enabled, so the answer reflects real quantum infrastructure instead of vague sci-fi language.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-scale-balanced"></i></div>
                    <div class="card-title">What “Real Quantum AI” Means</div>
                    <p>ZeroThink Pro is not claiming that the LLM itself runs on quantum hardware. It is <strong>hybrid quantum-informed AI</strong>: a classical model does the language reasoning, while real quantum-cloud telemetry, backend/account context, and optional IonQ simulator job records are brought into the prompt/context layer. Real integration, not fake magic.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-shield-halved"></i></div>
                    <div class="card-title">Deliberate Research Posture</div>
                    <p>The lane is designed as a research surface, not a fake “magic quantum oracle.” ZeroThink uses IonQ key validation, backend availability, queue posture, characterization context, and job IDs/results when a simulator proof is created, while Groq remains the fast reasoning core. In short: classical AI engine, quantum-informed research mode.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-leaf"></i></div>
                    <div class="card-title">Low API Usage Mode</div>
                    <p>Quantum Zero and Studio Quantum are tuned to stay friendly to free or low-volume IonQ accounts. Packet building is local/offline, backend telemetry is cached for 30 minutes, job checks are cached for 60 seconds, Studio chat reuses recent simulator proof jobs by default, and hardware QPU submissions require a deliberate checkbox.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-key"></i></div>
                    <div class="card-title">Keys Required</div>
                    <p>IonQ Quantum needs <strong>two keys</strong>: a <strong>Groq</strong> key for GPT-OSS 120B and an <strong>IonQ</strong> key for the quantum cloud lane. IBM Qiskit needs your <strong>Groq</strong> key for the answer and an optional <strong>IBM Quantum token</strong> when you want IBM Runtime/backend context. Save keys in the Neural Vault, then switch the provider pill to <strong>QUANTUM</strong> or <strong>QISKIT</strong>.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-crown"></i></div>
                    <div class="card-title">Pro Only</div>
                    <p>Quantum access is reserved for <strong>Pro</strong>. Free accounts stay on the fast open-weights lanes and do not unlock the IonQ-backed agent.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-circle-nodes"></i></div>
                    <div class="card-title">Quantum Zero Lab</div>
                    <p>The dedicated <a href="/quantumzero" class="btn-link">Quantum Zero</a> page is the simple chat surface. It now includes a larger real experiment deck for Pro users: Bell Pair Correlation, GHZ Shared-State Pulse, Entropy Beacon, Parity Pattern, Delta Branch Ethics, Omega Phi Xi Balance, ADEV Decision Spread, Harmonic Base Matrix, Six Layer Decision Pattern, and prompt-seeded QuantumZero packets. These submit/reuse small IonQ simulator jobs, read the probability histogram, then explain dominant state, top states, entropy/spread, backend evidence, and a practical result interpretation inside ZeroThink so users do not need to leave the platform. The advanced <a href="/quantumlab" class="btn-link">Quantum Lab</a> restores the deeper operator console: packet inspection, IonQ status checks, proof jobs, job-result review, telemetry forensics, and research-safe mission templates. IonQ simulator submission creates a real quantum-cloud job record when requested; it does not claim magical broadcasting or qubits running the chatbot.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-wave-square"></i></div>
                    <div class="card-title">Six-Job Evidence Chain</div>
                    <p>Quantum Zero includes a provider-backed simulator research chain seeded from private Zero themes without exposing private equations, raw signals, run identifiers, or internal ratios. The public lane describes what the chain is for: controlled quantum-cloud experiments, entropy checks, balance checks, decision-spread checks, harmonic packets, and reproducible research notes. It is evidence for the research workflow, not proof of remote activation.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-microchip"></i></div>
                    <div class="card-title">IBM Qiskit Lane</div>
                    <p>The Qiskit lane is separate from IonQ. It builds a calculation packet first, then lets Groq reason over that packet. Supported starter algorithms are Bell correlation, GHZ shared state, Grover search, QAOA MaxCut, a small VQE-style ansatz, QFT phase spread, and prompt-seeded Zero pattern circuits. Local Qiskit simulation can run without an IBM token; IBM Runtime/backend context requires the user's own IBM Quantum token and optional instance/CRN. Hardware job submission should remain deliberate and clearly labelled.</p>
                </div>
            </div>
        </section>

        <section id="cli">
            <h2><i class="fas fa-terminal"></i> ZeroThink CLI</h2>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-laptop-code"></i></div>
                    <div class="card-title">Cross-Platform + Guided</div>
                    <p>The ZeroThink CLI, aka <strong>Agent Zero</strong>, installs on Windows PowerShell, macOS, and Linux. It can pair to the same ZeroThink account as the web app or run in a no-Google direct mode with a local Groq or NVIDIA key. Its operator surface includes guided setup, scoped file access, local and remote workspaces, SSH/SFTP, checkpoints, planning, browser research, Playwright/MCP, encrypted file bundles, PDF report creation, saved-server health checks, site starters, playable browser game starters, named agents, and evidence-first research creation. New research commands can inspect approved files on the local computer or a saved server profile, then create a paper, article, survey, review, or proposal with a source ledger, claim matrix, verification flags, and Markdown export. The terminal remains concise; the richer public ZeroThink Reasoning Brief is shown in Studio.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-file-code"></i></div>
                    <div class="card-title">Large Code Friendly</div>
<p>The CLI supports direct file injection, workspace scans, file search, file writes and replacements, zip creation and extraction, Z-Pepper file/folder encryption and decryption, automatic encrypted project bundles, <code>lock</code>/<code>protect</code>/<code>unlock</code> aliases, remote SCP transfers, remote file inspection and edits on saved server profiles, repo-level guidance files like <code>ZEROTHINK.md</code>, <code>CLAUDE.md</code>, <code>AGENTS.md</code>, and <code>.claude/CLAUDE.md</code>, local web search, saved browser pages, Playwright MCP automation for dynamic pages, page attach flows, and the same packed file strategy used in the web app, so large source files and config dumps are easier to analyze without immediately blowing the window. The CLI reasoning layer now follows a stronger private orient → inspect → plan → act → verify → report workflow instead of staying in generic assistant mode.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-arrow-up-right-from-square"></i></div>
                    <div class="card-title">Operator Modes</div>
                    <p>The public install and manual page is exposed at <a href="/cli" class="btn-link">/cli</a>. The CLI can run in a safer folder-only mode for one repo or switch to machine mode when you want shell and server operator access, and the guide now walks beginners through install, linked-account mode, no-Google direct mode, first prompt, help topics, model selection, autocomplete, local checkpoint/resume flows, local planning, and the limits of direct mode. It also has a built-in <code>zerothink update</code> command that verifies the published CLI hash before replacing the local script, <code>zerothink status</code> for a quick local readiness snapshot, a remote workspace lane via <code>zerothink remote ...</code>, saved-profile checks like <code>zerothink server check prod /var/www/html</code>, local PDF reports like <code>zerothink pdf create report.pdf --file notes.md</code>, a local MCP layer with a built-in Playwright browser profile via <code>zerothink mcp add-playwright</code> and <code>zerothink playwright open https://example.com</code>, plus self-driven agent workflows like <code>zerothink autopilot "audit this repo"</code>, <code>zerothink agent create zero1 "map the auth flow"</code>, <code>zerothink agent log zero1</code>, <code>zerothink stop zero1</code>, and in-prompt <code>/stop</code>. For coding, the current recommended defaults are <code>openai/gpt-oss-120b</code> on Groq and <code>qwen/qwen3-coder-480b-a35b-instruct</code> on NVIDIA, and the fastest way to inspect the curated shortlist is <code>zerothink models coding</code>. If you just type a normal task like <code>make a small game</code> or <code>make a new project folder for shafaet.com</code> inside the interactive prompt, Agent Zero now routes that into execution mode automatically instead of staying in pure chat mode, unless you switch to a staging lane like <code>/steer plan</code>. The file/tree tools now keep broad inspections compact by skipping common dependency, cache, vendor, and backup directories unless you explicitly inspect those paths.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-plug-circle-check"></i></div>
                    <div class="card-title">No-Google CLI Mode</div>
                    <p>If you skip Google login in the CLI, ZeroThink can still auto-detect <code>GROQ_API_KEY</code> or <code>NVIDIA_API_KEY</code> from your local shell, or you can store them locally with <code>zerothink key set groq</code> / <code>zerothink key set nvidia</code>. That lane does not import Vault keys, memory, saved history, linked-account session state, or Pro account state. Local CLI browser tools like <code>zerothink search</code>, <code>zerothink browse</code>, <code>/search-web</code>, <code>/page</code>, and local Playwright MCP browser commands still work because they are machine-local terminal helpers rather than website account features. For Groq and NVIDIA, the CLI can also fall back to that local direct-key lane automatically if the linked provider lane fails. If you ever want the exact live route instead of asking the model to guess, use <code>zerothink runtime</code> or <code>/runtime</code>. If you already have the CLI installed, the safe update command is simply <code>zerothink update</code>.</p>
                </div>
            </div>
        </section>

        <section id="research-workbench">
            <h2><i class="fas fa-file-lines"></i> Research Paper Creator + CLI Workbench</h2>
            <p>ZeroThink separates research discovery, evidence checking, drafting, and review. The browser creator is the guided visual workflow; the CLI is the local and server-aware workflow for researchers who already have folders of notes, papers, transcripts, datasets, reports, or project files.</p>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-diagram-project"></i></div>
                    <div class="card-title">Browser Workflow</div>
                    <p><a href="/research-paper-creator" class="btn-link">Research Paper Creator</a> provides separate stages for search protocol, evidence plan, source ledger, outline, draft, reviewer critique, and revision. It supports paper type, method, audience, citation style, date range, source policy, inclusion/exclusion criteria, uploaded research files, known sources, integrity checks, and project export.</p>
                    <p>Optional uploads such as TXT, Markdown, CSV, JSON, HTML, RTF, DOCX, legacy DOC, BibTeX, notes, abstracts, draft articles, and supervisor comments are read in the browser and attached only to the current generation request. They are not saved as a public research library upload.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-file-pdf"></i></div>
                    <div class="card-title">Built-in PDF Creator</div>
                    <p>In Studio, prompts like <strong>create a PDF report about this</strong> trigger ZeroThink's AI PDF artifact mode: the selected model drafts a polished document, optional WEB mode or explicit research wording can add Serper search context, and the answer returns a private download button. <a href="/pdf-creator" class="btn-link">PDF Creator</a> remains the manual editor/exporter for pasted notes, reports, drafts, source summaries, or Paper Creator output. Uploaded text files are read in the browser and sent only as document text when the user creates the PDF; the endpoint does not accept server file paths or browser-supplied URLs.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-laptop-file"></i></div>
                    <div class="card-title">Local Files</div>
                    <p>The CLI can inspect only the files or folders the user explicitly supplies. Text, code, CSV, Markdown, DOCX, and optional PDF extraction are supported. Common credential files, private keys, dependency folders, and oversized files are excluded or inventoried by default.</p>
                    <code class="command-block">zerothink research paper "AI research agents" --source ./papers --source ./notes.md --stage outline</code>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-server"></i></div>
                    <div class="card-title">Saved Server Evidence</div>
                    <p>In machine mode, a saved SSH profile can supply remote research files without publishing the server address in documentation. The CLI reads through SFTP, applies the same exclusions, creates a visible inventory, and removes its temporary evidence pack after the model call.</p>
                    <code class="command-block">zerothink research review "Reliability evidence" --server research --remote-path /srv/research/reports</code>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-list-check"></i></div>
                    <div class="card-title">Research Stages</div>
                    <p>Choose <code>protocol</code>, <code>evidence</code>, <code>outline</code>, <code>draft</code>, <code>critique</code>, <code>revision</code>, or <code>full</code>. Use <code>--dry-run</code> to review scope without a model call, <code>--library</code> for compact private Zero Library retrieval, and <code>--web</code> only when live discovery is needed.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-scale-balanced"></i></div>
                    <div class="card-title">Citation Discipline</div>
                    <p>ZeroThink must not invent author names, papers, quotations, DOIs, URLs, page numbers, statistics, or results. Search snippets are discovery leads, not proof of full-text access. Unverified claims remain visibly marked, and the final export includes the supplied source inventory.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-tags"></i></div>
                    <div class="card-title">Source Ledger Labels</div>
                    <p>The source ledger separates <strong>Uploaded Source</strong>, <strong>User Note</strong>, <strong>Retrieved Search Result</strong>, <strong>Full Text Supplied</strong>, <strong>Model Candidate Lead</strong>, and <strong>Needs Verification</strong>. Candidate leads are not citations. They are search targets until a DOI, URL, database result, or supplied file verifies them.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-terminal"></i></div>
                    <div class="card-title">Start Here</div>
                    <code class="command-block">zerothink help research
zerothink research scan --source ./papers
zerothink research survey "Topic" --source ./evidence --citation-style "APA 7"
zerothink research proposal "Topic" --source ./notes --output proposal.md</code>
                </div>
            </div>
        </section>

        <section id="freewebpanel-cli">
            <h2><i class="fas fa-server"></i> FreeWebPanel CLI Operations</h2>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-circle-question"></i></div>
                    <div class="card-title">Can ZeroThink CLI manage a FreeWebPanel server?</div>
                    <p>Yes. <code>zerothink freewebpanel</code>, with the shorter alias <code>zerothink fwp</code>, can install, manage, inspect, back up, diagnose, repair, update, and help operate a FreeWebPanel server. Start with <code>status</code>, <code>check</code>, or <code>health</code>, then use backup-first plans for <code>repair</code>, <code>update</code>, and <code>deploy</code>. It includes web-server, PHP, database, mail, DNS, SSL, permission, firewall, redacted-log, and existing-panel takeover/migration checks without publishing private infrastructure examples.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-screwdriver-wrench"></i></div>
                    <div class="card-title">Is ZeroThink CLI only for installing FreeWebPanel?</div>
                    <p>No. Installation is one feature. ZeroThink CLI is the wider local server-management and hosting-operations layer for FreeWebPanel and related TalkToAI/ZeroThink work. It supports health, config, domain, log, permission, Apache/PHP, SSL, backup, repair, update, and deployment workflows after installation.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-shield-halved"></i></div>
                    <div class="card-title">Can it change a live server by accident?</div>
                    <p>Inspection commands are read-only. Install, repair, update, deploy, and backup commands show their plan first. Use <code>--dry-run</code> for an explicit preview. A change requires <code>--yes</code>, and repair, update, or deploy should normally include <code>--backup-first</code>.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-user-secret"></i></div>
                    <div class="card-title">Does it expose passwords or API keys?</div>
                    <p>Common password, secret, token, private-key, database, SMTP, and API-key values are redacted from configuration and log previews by default. Use <code>--no-redact</code> only in a trusted local terminal when the raw value is genuinely required.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-download"></i></div>
                    <div class="card-title">What install lanes are supported?</div>
                    <p>Ubuntu is the primary install lane. AlmaLinux, Rocky Linux, and RHEL-family servers are supported through the RHEL-family lane. OpenZero and ZeroMint AIOS servers use the OpenZero AIOS lane. Existing-panel takeover is treated as a migration workflow: run preflight first, verify off-server backups, then approve the replacement plan deliberately.</p>
                    <code class="command-block">zerothink fwp install --server-mode ubuntu --hostname panel.example.com --email admin@example.com --dry-run
zerothink fwp install --server-mode rhel-family --hostname panel.example.com --email admin@example.com --preflight-only
zerothink fwp install --server-mode openzero-aios --hostname web.example.com --email admin@example.com --dry-run</code>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-terminal"></i></div>
                    <div class="card-title">What commands should I run first?</div>
                    <p>Start with <code>zerothink fwp status</code>, then <code>zerothink fwp doctor --verbose</code>. For automation use <code>zerothink fwp health --json</code>. Before maintenance, preview <code>zerothink fwp backup --path /opt/freewebpanel --dry-run</code>.</p>
                </div>
            </div>
        </section>

        <section id="library">
            <h2><i class="fas fa-folder-tree"></i> Zero Library + Labs</h2>
            <p>
                ZeroThink has a deterministic library layer in front of the model. It uses normal code to index, search, and summarize ordinary private reference text before the AI is called, so routine lookup work can happen without spending model tokens. When the agent needs context, it receives a compact evidence pack instead of whole folders. Sensitive DNA/genomic files and files containing detected credentials or private keys are a separate vault class: only metadata is catalogued, and their contents never enter Library search, RAG evidence packs, or model context.
            </p>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-database"></i></div>
                    <div class="card-title">Zero Library</div>
                    <p>Upload notes, code, logs, docs, and project references at <a href="/library" class="btn-link">/library</a>. Each library is account-private: files live in a user-scoped server directory, the public web cannot browse storage, and search only reads that user's own index. Recognized FASTA/FASTQ/GenBank files, raw genotype exports, VCF files, and detected genomic sequences are accepted only as vault items; their content is not indexed. In Studio, <code>@library</code> can attach relevant snippets from searchable reference text, never from those vault-only genomic files.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-dna"></i></div>
                    <div class="card-title">DNA Lab</div>
                    <p><a href="/dna" class="btn-link">/dna</a> is a one-click DNA evidence lab. Users upload a MyHeritage, 23andMe, Ancestry-style, CSV/TSV, raw, genome, or VCF file, confirm the privacy/research boundary, then press <strong>Run Full DNA Report</strong>. By default the raw upload is analyzed for that request and not retained; an optional private-vault save remains metadata-only to Library retrieval. ZeroThink returns plain-English cards, a signal wheel, an <strong>Evidence Ledger</strong>, and Markdown/PDF export. The optional <strong>DNA Agent</strong> requires separate consent and receives a minimized report summary only—not the raw file, exact genotype rows, or vault contents—and does not save that exchange to chat history. Under the hood, <strong>ZDNA-MAP v1.2</strong> scores raw-file quality, treats unconfirmed genome build/orientation and no-calls conservatively, tracks duplicates/chromosome coverage, prepares research-only health evidence cards, creates confidence-bounded heritage/genetic-affinity lenses, and can build optional GEDCOM/story-based <strong>Lineage Clues</strong>. The local data-pack path supports ClinVar, GWAS Catalog, PharmGKB/CPIC, HPO, UCSC liftover, reduced population panels, and Wikidata lineage subsets when installed. The Health lane does not diagnose or advise treatment. The Heritage lane does not invent exact ethnicity percentages or identity claims. The Lineage lane does not prove royal ancestry from DNA alone.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-photo-film"></i></div>
                    <div class="card-title">Media Lab</div>
                    <p><a href="/media" class="btn-link">/media</a> is now a downloadable Media Studio: AI voice MP3 generation, GPT Image PNG generation, <strong>xAI Grok Imagine</strong> Pro video jobs, <strong>SeedDance / BytePlus Ark</strong> cinematic video tasks, legacy Sora fallback, MP4 download links, and a browser-local asset shelf. Video generation is Pro-only; users save xAI and SeedDance keys in Vault Keys when they want their own provider billing.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-terminal"></i></div>
                    <div class="card-title">CLI Commands</div>
                    <p>Agent Zero includes the same idea locally: <code>zerothink library add .</code>, <code>zerothink library search "query"</code>, <code>zerothink library context "bug"</code>, <code>zerothink research paper "topic" --source ./papers --library</code>, and <code>zerothink dna analyze sample.fasta</code>. Deeper AlphaGenome execution stays on the website/server side because biological API keys should remain centrally controlled rather than scattered across user terminals.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-shield-halved"></i></div>
                    <div class="card-title">Security Boundary</div>
                    <p>The library accepts only allowlisted, size-limited uncompressed file types, blocks direct storage browsing, does not execute uploads, and keeps generated context compact. Mutating Library requests require the signed-in session's CSRF token. Sensitive DNA/genotype/genomic-sequence content and detected credential/private-key files are stored with a vault-only classification and re-checked at retrieval time, so filenames and hashes may be catalogued but file contents remain outside search and model context.</p>
                </div>
            </div>
        </section>

        <section id="zmath">
            <h2><i class="fas fa-infinity"></i> ZMath Agent</h2>
            <p>
                <a href="/zmath" class="btn-link">/zmath</a> turns Zero Boundary Algebra into a practical green agent rather than a decorative symbol page. It runs a deterministic modulo-9 engine for text, numbers, protocol steps, filenames, uploaded file previews, and research notes before an LLM is needed.
            </p>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-calculator"></i></div>
                    <div class="card-title">Strict Math Core</div>
                    <p>The formal core is <strong>Z/9Z</strong>, the triadic subgroup <strong>{0,3,6}</strong>, rotation <strong>x -> x + 3 mod 9</strong>, and the mirror operator <strong>x -> 8x mod 9 = -x mod 9</strong>. This keeps the agent honest and useful.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-diagram-project"></i></div>
                    <div class="card-title">Useful Output</div>
                    <p>ZMath now answers like a normal agent first, then places the lab tools underneath. It returns parsed residues, balanced notation, reset/mirror traces, deterministic ZM9 fingerprints, state-machine JSON, practical software patterns, file profiles, and downloadable markdown reports. It is useful for workflow design, reset logic, checksum tags, reversible UI states, encryption handoff metadata, and symbolic research organization.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-lock"></i></div>
                    <div class="card-title">ZME1 Encryption Files</div>
                    <p>The ZMath page can create portable <strong>.zme1</strong> files locally in the browser. It uses browser WebCrypto with <strong>AES-GCM-256</strong> and <strong>PBKDF2-SHA256</strong>, plus optional pattern-image and Z-Pepper factors. The password, pattern file, and plaintext are not uploaded to ZeroThink. The ZMath packet stored in metadata is for deterministic workflow/fingerprint context, not a claim of extra cryptographic magic.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-shield-halved"></i></div>
                    <div class="card-title">Truth Boundary</div>
                    <p>The -0, +0, and infinity labels are state annotations layered on top of modular arithmetic. ZMath does not pretend they are standard numeric residues; it uses them to preserve transition direction in protocols and research notation.</p>
                </div>
            </div>
        </section>

        <section id="zero">
            <h2><i class="fas fa-brain"></i> ZeroThink Logic</h2>
            <p><strong>ZeroThink is a proprietary reasoning architecture</strong>, not a claim that raw hidden chain-of-thought should be exposed. The private method stays sealed. Studio presents a concise <strong>ZeroThink Reasoning Brief</strong> so the user can inspect the decision without receiving private prompts, constants, token-by-token working, or internal framework details.</p>
            <p>Full ZeroThink Logic remains part of the <strong>Sovereign Pro</strong> tier. The visible brief is designed for useful scrutiny: evidence, alternatives, uncertainty, impact, the selected decision, and the next action.</p>
            
            <div class="grid">
                <div class="card">
                    <div class="card-title">Frame + Evidence</div>
                    <p>States the real problem, constraints, success condition, strongest available facts, retrieved context, calculations, and source limitations.</p>
                </div>
                <div class="card">
                    <div class="card-title">Counterpoint + Uncertainty</div>
                    <p>Surfaces the strongest credible alternative or failure mode, then identifies what remains unknown, unverified, assumed, or time-sensitive.</p>
                </div>
                <div class="card">
                    <div class="card-title">Impact + Decision</div>
                    <p>Checks likely harms, stakeholders, reversibility, and downstream effects before stating the chosen approach and one concrete next step.</p>
                </div>
            </div>
        </section>

        <section id="apis">
            <h2><i class="fas fa-key"></i> API Configuration</h2>
            <p>To activate the Scout Node (Internet Search), the premium model engines, or the new Quantum lane, you must obtain API keys from the respective providers.</p>
            
            <details open>
                <summary>Groq Cloud (Recommended / Free)</summary>
                <div class="faq-body">
                    <strong>Cost:</strong> Free Beta (High Limits)<br>
                    <strong>Steps:</strong><br>
                    1. Go to <a href="https://console.groq.com/keys" target="_blank" class="btn-link">console.groq.com</a><br>
                    2. Sign up with Email/Google.<br>
                    3. Click "Create API Key".<br>
                    4. Paste into the ZeroThink Vault under "Groq API", or use <code>zerothink key set groq</code> for local direct mode in the CLI.<br>
                    5. If you already export <code>GROQ_API_KEY</code> in your shell, the CLI can auto-detect it without Google login.<br><br>
                    <strong>Current ZeroThink Groq menu:</strong> <code>openai/gpt-oss-120b</code>, <code>groq/compound</code>, <code>groq/compound-mini</code>, <code>openai/gpt-oss-20b</code>, <code>qwen/qwen3.6-27b</code>, <code>llama-3.1-8b-instant</code>, and <code>allam-2-7b</code>.<br>
                    <strong>Deprecation note:</strong> Groq email notices dated 17 June 2026 say <code>qwen/qwen3-32b</code> and <code>meta-llama/llama-4-scout-17b-16e-instruct</code> decommission on 17 July 2026, and <code>llama-3.3-70b-versatile</code> decommissions on 16 August 2026. ZeroThink hides these choices now and routes old selections to <code>openai/gpt-oss-120b</code>. Groq's deprecation docs also point old Kimi K2 and Llama 4 Maverick usage at <code>openai/gpt-oss-120b</code>.
                </div>
            </details>

            <details>
                <summary>NVIDIA NIM (Free Credits)</summary>
                <div class="faq-body">
                    <strong>Cost:</strong> 1,000 Free Credits on Signup<br>
                    <strong>Steps:</strong><br>
                    1. Go to <a href="https://build.nvidia.com" target="_blank" class="btn-link">build.nvidia.com</a><br>
                    2. Click "Login" -> Create Account.<br>
                    3. Click "Get API Key" (Top Right).<br>
                    4. Paste into the ZeroThink Vault under "NVIDIA NIM", or use <code>zerothink key set nvidia</code> for local direct mode in the CLI.<br>
                    5. If you already export <code>NVIDIA_API_KEY</code> in your shell, the CLI can auto-detect it without Google login.
                </div>
            </details>

            <details>
                <summary>IonQ Quantum Cloud (Quantum Lane / Pro)</summary>
                <div class="faq-body">
                    <strong>Cost:</strong> Free account available, quantum compute and org features vary by plan<br>
                    <strong>Steps:</strong><br>
                    1. Create an IonQ Quantum Cloud account through <a href="https://docs.ionq.com/index" target="_blank" class="btn-link">IonQ Quantum Cloud</a>.<br>
                    2. Open the <a href="https://docs.ionq.com/guides/managing-api-keys" target="_blank" class="btn-link">API Keys</a> page in the IonQ cloud app and create a new key.<br>
                    3. Save it immediately. IonQ documents that the full key value is only shown at creation time.<br>
                    4. Paste it into the ZeroThink Vault under <strong>IonQ Quantum Cloud Key</strong>.<br>
                    5. Keep your <strong>Groq API</strong> key saved too, because the Quantum lane uses <strong>Groq GPT-OSS 120B</strong> for reasoning and IonQ for live quantum-cloud telemetry.<br><br>
                    <strong>How ZeroThink uses it:</strong> ZeroThink authenticates to IonQ using the documented <code>Authorization: apiKey ...</code> pattern, verifies the key session, pulls backend context, and can submit small simulator proof jobs in low-usage mode so the Quantum agent can reason against real IonQ state instead of pretending a quantum backend exists. Studio offers Auto Job, Fresh Proof Job, and Telemetry Only modes.
                </div>
            </details>

            <details>
                <summary>IBM Quantum / Qiskit (Qiskit Lane / Pro)</summary>
                <div class="faq-body">
                    <strong>Cost:</strong> Local Qiskit simulation is local software; IBM Quantum Runtime and hardware access depend on the user's IBM Quantum plan.<br>
                    <strong>Steps:</strong><br>
                    1. Create or open an IBM Quantum account at <a href="https://quantum.cloud.ibm.com/" target="_blank" class="btn-link">quantum.cloud.ibm.com</a>.<br>
                    2. In IBM Quantum, use the account switcher in the header to select the correct account and region before copying credentials.<br>
                    3. From the IBM Quantum <a href="https://quantum.cloud.ibm.com/" target="_blank" class="btn-link">dashboard</a>, create or reveal your API key, copy it once, and keep it in a private password manager. IBM treats the token as confidential; do not paste it into public code, screenshots, or chats. The same API key can authenticate to any IBM Quantum region.<br>
                    4. Optional: open the <a href="https://quantum.cloud.ibm.com/instances" target="_blank" class="btn-link">Instances</a> page, find the runtime instance you want, copy its CRN or instance name, and save it only if your account needs a specific Runtime instance.<br>
                    5. Optional: choose a region such as <code>us-east</code> or <code>eu-de</code>. IBM ignores the region when a specific instance/CRN is supplied.<br>
                    6. Paste the API key into <strong>Neural Vault -> IBM Quantum Token</strong>. Paste the instance/CRN and region only if you need Runtime/backend context.<br>
                    7. Keep your <strong>Groq API</strong> key saved too, because ZeroThink uses Groq GPT-OSS 120B to explain the Qiskit calculation packet.<br><br>
                    <strong>How ZeroThink uses it:</strong> The Qiskit lane calls a server-side Python worker. With Python 3.10+ and <code>qiskit</code> installed, it runs local Qiskit calculation packets. With <code>qiskit-ibm-runtime</code> and the user's IBM token, it can check IBM Runtime/backend context. The default Studio mode is local simulation and does not submit IBM hardware jobs. Use the algorithm selector for Bell, GHZ, Grover, QAOA MaxCut, VQE Toy, QFT, or Zero Pattern.<br><br>
                    <strong>Qiskit algorithm selector:</strong><br>
                    <code>ZERO PATTERN</code>: ZeroThink's prompt-seeded research lane. It turns the user's prompt into a repeatable quantum-style packet for custom Zero research and pattern reasoning.<br>
                    <code>BELL</code>: A two-qubit entanglement correlation test. Use it as the fastest smoke test or when the question is about paired states, symmetry, or correlation.<br>
                    <code>GHZ</code>: A multi-qubit shared-state circuit. Use it for consensus, shared-state, distributed-correlation, and "many parts acting as one" style prompts.<br>
                    <code>GROVER</code>: A toy search/amplification circuit. Use it for candidate finding, option selection, lookup-style reasoning, or demonstrating quantum search intuition.<br>
                    <code>QAOA MAXCUT</code>: A small graph optimization preset. Use it for routing, scheduling, grouping, clustering, partitioning, allocation, and other optimization examples.<br>
                    <code>VQE TOY</code>: A small variational eigensolver-style ansatz. Use it for energy-minimization, chemistry-inspired, materials-inspired, or parameter-tuning research demos.<br>
                    <code>QFT</code>: Quantum Fourier Transform. Use it for phase, periodicity, frequency, signal, wave, and number-pattern style questions.<br><br>
                    <strong>Qiskit mode selector:</strong> <code>QISKIT LOCAL</code> builds a local calculation/simulation packet and does not need an IBM token. <code>QISKIT BACKEND</code> is for IBM Runtime/backend context and needs the user's own IBM Quantum token, plus instance/CRN and region when their IBM account requires them. ZeroThink should keep hardware job submission explicit and labelled instead of quietly spending quantum credits.<br><br>
                    <strong>Server setup:</strong> Current Qiskit packages require Python 3.10 or newer. Install with <code>python3.10 -m venv quantumibm-python/.venv</code>, then <code>quantumibm-python/.venv/bin/python -m pip install -r quantumibm-python/requirements.txt</code>. If the web server uses a custom Python path, set <code>QISKIT_PYTHON</code>. If the host disables PHP process spawning, ZeroThink falls back to a deterministic PHP packet so the lane keeps working, but real Qiskit execution requires an enabled Python worker or sidecar service.
                </div>
            </details>

            <details>
                <summary>Google Gemini (Free Tier)</summary>
                <div class="faq-body">
                    <strong>Cost:</strong> Free Tier Available / Pay-as-you-go<br>
                    <strong>Steps:</strong><br>
                    1. Go to <a href="https://aistudio.google.com/app/apikey" target="_blank" class="btn-link">aistudio.google.com</a><br>
                    2. Click "Create API Key".<br>
                    3. Paste into the ZeroThink Vault under "Gemini API".
                </div>
            </details>

            <details>
                <summary>xAI (Grok)</summary>
                <div class="faq-body">
                    <strong>Cost:</strong> Paid (Pre-load Credits)<br>
                    <strong>Steps:</strong><br>
                    1. Go to <a href="https://console.x.ai" target="_blank" class="btn-link">console.x.ai</a><br>
                    2. Add Billing Details.<br>
                    3. Generate Key and paste into Vault under "xAI API".
                </div>
            </details>

            <details>
                <summary>OpenAI (GPT-5.5 / GPT-5.4 / GPT-5.4 Mini / GPT-5.4 Nano)</summary>
                <div class="faq-body">
                    <strong>Cost:</strong> Paid (Pay-as-you-go)<br>
                    <strong>Steps:</strong><br>
                    1. Go to <a href="https://platform.openai.com/api-keys" target="_blank" class="btn-link">platform.openai.com</a><br>
                    2. Create new Secret Key.<br>
                    3. Paste into Vault under "OpenAI API".
                </div>
            </details>

            <details>
                <summary>OpenZero Local (Pro / 5 Shared Messages / Bring Your Own Node)</summary>
                <div class="faq-body">
                    <strong>Cost:</strong> ZeroThink Pro required. When enabled, the shared ZeroThink OpenZero gateway is limited to 5 messages per Pro account to protect shared CPU resources. Continued use should come from the user's own OpenZero machine/server key through a server-side registered gateway.<br>
                    <strong>Current local model policy:</strong> The current visible choices follow the independent OpenZero release: <code>hf.co/shafire/OpenZero-Ministral3-8B-Runtime-Agent-GGUF:Q5_K_M</code> and <code>openzerogemma:latest</code> as a compatibility choice. Install the selected model on your own runtime and configure your gateway; a menu entry does not establish model availability or a completed inference. Existing saved legacy aliases remain compatible where the operator still provides them.<br>
                    <strong>Steps:</strong><br>
                    1. In Studio, choose an <strong>OPENZERO LOCAL</strong> model to use the shared trial if the gateway is enabled and you have no key saved.<br>
                    2. After the 5-message trial, install OpenZero on your own Linux machine or server from <a href="https://openzero.talktoai.org/manual" target="_blank" class="btn-link">openzero.talktoai.org/manual</a>.<br>
                    3. Create an OpenZero API key on your node and keep it private.<br>
                    4. Paste the key into <strong>Neural Vault -> OpenZero Machine API Key</strong>.<br>
                    5. ZeroThink web does not accept browser-supplied OpenZero URLs. User nodes must connect through a safe server-side gateway/registration path rather than arbitrary client-controlled upstream URLs.
                </div>
            </details>

            <details>
                <summary>Featherless Hosted Models (Pro / Paid Hosted Key)</summary>
                <div class="faq-body">
                    <strong>Cost:</strong> Featherless account/key required; larger hosted models may need a paid Featherless plan.<br>
                    <strong>Steps:</strong><br>
                    1. Create or open a Featherless account at <a href="https://featherless.ai" target="_blank" class="btn-link">featherless.ai</a>.<br>
                    2. Create an API key in Featherless and keep it private.<br>
                    3. Paste it into <strong>Neural Vault -> Featherless API Key</strong>.<br>
                    4. Choose a <strong>FEATHERLESS HOSTED</strong> model in Studio.<br><br>
                    <strong>Current allowlist:</strong> <code>shafire/talktoaiZERO</code>, <code>zai-org/GLM-5.2</code>, <code>zai-org/GLM-4.5</code>, <code>Qwen/Qwen3-Coder-480B-A35B-Instruct</code>, <code>deepseek-ai/DeepSeek-V3.2</code>, and <code>moonshotai/Kimi-K2-Instruct</code>.<br>
                    <strong>Boundary:</strong> Featherless is the right lane for very large hosted models such as GLM 5.2. Those models are not downloaded onto the local ZeroThink/OpenZero CPU box.
                </div>
            </details>

            <details>
                <summary>Serper (Web Search)</summary>
                <div class="faq-body">
                    <strong>Cost:</strong> 2,500 Free Queries<br>
                    <strong>Steps:</strong><br>
                    1. Go to <a href="https://serper.dev" target="_blank" class="btn-link">serper.dev</a><br>
                    2. Sign up.<br>
                    3. Copy API Key.<br>
                    4. Paste into Vault under "Serper Web Key".
                </div>
            </details>
        </section>

        <div style="text-align:center; margin-top:100px; color:#444; font-size:0.8rem; border-top:1px solid #222; padding-top:20px;">
            ZERO SYSTEM v35 // SOVEREIGN ARCHITECTURE<br>
            &copy; 2026 Shaf Brady / TalkToAi
        </div>
    </div>
</div>
<script>
    const faqSearch = document.getElementById('faq-search');
    const faqSearchStatus = document.getElementById('faq-search-status');
    const faqSections = Array.from(document.querySelectorAll('.content > section'));
    if (faqSearch) {
        faqSearch.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            let visible = 0;
            faqSections.forEach(section => {
                const matched = !query || section.textContent.toLowerCase().includes(query);
                section.hidden = !matched;
                if (matched) visible += 1;
            });
            faqSearchStatus.textContent = query
                ? `${visible} documentation section${visible === 1 ? '' : 's'} matched.`
                : 'All documentation sections are visible.';
        });
    }
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (!target) return;
            if (target.hidden && faqSearch) {
                faqSearch.value = '';
                faqSections.forEach(section => { section.hidden = false; });
                faqSearchStatus.textContent = 'All documentation sections are visible.';
            }
            target.scrollIntoView({ behavior: 'smooth' });
        });
    });
</script>
</body>
</html>
