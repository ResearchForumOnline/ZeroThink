<?php require_once __DIR__ . '/app/bootstrap.php'; if(!isset($_SESSION['user_id'])) { header('Location:/'); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeroThink | Oracle Desk</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #04060a;
            --panel: rgba(10, 16, 22, 0.9);
            --line: rgba(255, 255, 255, 0.1);
            --gold: #f6c86f;
            --green: #56d39b;
            --cyan: #78e2ff;
            --text: #eef4ff;
            --muted: #9aa9bb;
            --warn: #ffb454;
            --danger: #ff7f8f;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, system-ui, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top right, rgba(86, 211, 155, 0.12), transparent 30%),
                radial-gradient(circle at top left, rgba(246, 200, 111, 0.12), transparent 32%),
                linear-gradient(180deg, #04060a 0%, #081018 100%);
            overflow-x: hidden;
        }
        .grid {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.12;
            background-image:
                linear-gradient(rgba(120, 226, 255, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(120, 226, 255, 0.08) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .shell {
            position: relative;
            z-index: 2;
            max-width: 1320px;
            margin: 0 auto;
            padding: 26px 18px 40px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: var(--text);
        }
        .brand img {
            width: 42px;
            filter: drop-shadow(0 0 12px rgba(120, 226, 255, 0.3));
        }
        .brand-title {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }
        .brand-sub {
            font-size: 0.75rem;
            color: var(--muted);
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }
        .top-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .link-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 10px 16px;
            background: rgba(255,255,255,0.03);
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
        }
        .hero {
            display: grid;
            grid-template-columns: 1.25fr 0.95fr;
            gap: 18px;
            align-items: stretch;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 24px;
            backdrop-filter: blur(18px);
            box-shadow: 0 30px 70px rgba(0,0,0,0.32);
        }
        .hero h1 {
            margin: 0;
            font-size: clamp(2.4rem, 5vw, 4.7rem);
            line-height: 0.95;
            letter-spacing: -0.05em;
        }
        .hero p {
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.7;
        }
        .hero-badges, .mode-row, .meta-grid, .mini-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .badge, .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.04);
            color: var(--text);
            font-size: 0.82rem;
        }
        .badge strong { color: var(--gold); }
        .hero-side {
            display: grid;
            gap: 14px;
        }
        .donate-card {
            border-color: rgba(246, 200, 111, 0.26);
            background: linear-gradient(180deg, rgba(246, 200, 111, 0.08), rgba(255,255,255,0.03));
        }
        .donate-address {
            margin-top: 12px;
            padding: 12px;
            border-radius: 16px;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255,255,255,0.08);
            font-family: ui-monospace, SFMono-Regular, monospace;
            font-size: 0.86rem;
            line-height: 1.5;
            word-break: break-all;
            color: #fff3d7;
        }
        .panel-label {
            font-size: 0.74rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--cyan);
            margin-bottom: 10px;
        }
        .desk {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 18px;
            margin-top: 18px;
        }
        textarea, select {
            width: 100%;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 18px;
            background: rgba(4, 8, 14, 0.88);
            color: var(--text);
            padding: 14px 16px;
            font: inherit;
        }
        textarea {
            min-height: 180px;
            resize: vertical;
            line-height: 1.55;
        }
        .controls {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
        }
        .mode-row { margin: 14px 0 16px; }
        .mode-btn {
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.03);
            color: var(--text);
            border-radius: 999px;
            padding: 10px 14px;
            cursor: pointer;
            font-weight: 700;
        }
        .mode-btn.active {
            border-color: rgba(246, 200, 111, 0.45);
            background: rgba(246, 200, 111, 0.12);
            color: #fff5dd;
            box-shadow: 0 0 0 1px rgba(246,200,111,0.12) inset;
        }
        .run-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
        }
        .run-btn, .ghost-btn {
            border: none;
            border-radius: 18px;
            padding: 14px 18px;
            font-weight: 800;
            cursor: pointer;
        }
        .run-btn {
            background: linear-gradient(135deg, var(--gold), #ffd99e);
            color: #13110c;
            min-width: 210px;
        }
        .ghost-btn {
            background: rgba(255,255,255,0.05);
            color: var(--text);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .hint {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .result-wrap {
            display: grid;
            gap: 14px;
        }
        .signal-orb {
            width: 168px;
            height: 168px;
            border-radius: 50%;
            margin: 0 auto 12px;
            display: grid;
            place-items: center;
            font-size: 2.6rem;
            font-weight: 900;
            border: 10px solid rgba(255,255,255,0.08);
            box-shadow: inset 0 0 50px rgba(0,0,0,0.25), 0 0 40px rgba(120,226,255,0.08);
        }
        .result-title {
            text-align: center;
            font-size: 1.65rem;
            font-weight: 900;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
        }
        .result-sub {
            text-align: center;
            color: var(--muted);
            line-height: 1.6;
        }
        .mini-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .mini-card {
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            background: rgba(255,255,255,0.03);
            padding: 14px;
        }
        .mini-card h3 {
            margin: 0 0 8px;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: var(--gold);
        }
        ul.clean {
            margin: 0;
            padding-left: 18px;
            color: #dce6f4;
            line-height: 1.7;
        }
        .loading-note {
            display: none;
            color: var(--cyan);
            font-weight: 700;
        }
        .error-box {
            display: none;
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255, 127, 143, 0.3);
            background: rgba(255, 127, 143, 0.08);
            color: #ffd3d9;
        }
        .footer-note {
            margin-top: 16px;
            color: var(--muted);
            font-size: 0.85rem;
            line-height: 1.7;
        }
        @media (max-width: 980px) {
            .hero, .desk { grid-template-columns: 1fr; }
            .controls, .mini-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 700px) {
            .shell { padding: 16px 12px 28px; }
            .topbar {
                flex-direction: column;
                align-items: stretch;
            }
            .top-actions {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
            .link-btn {
                justify-content: center;
                width: 100%;
            }
            .card {
                padding: 18px;
                border-radius: 22px;
            }
            .hero h1 {
                font-size: clamp(1.9rem, 10vw, 3rem);
            }
            textarea {
                min-height: 160px;
            }
            .mode-row {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .mode-btn {
                justify-content: center;
                text-align: center;
            }
            .run-row {
                display: grid;
                grid-template-columns: 1fr;
            }
            .run-btn, .ghost-btn {
                width: 100%;
            }
            .signal-orb {
                width: 136px;
                height: 136px;
                font-size: 2rem;
                border-width: 8px;
            }
        }
        @media (max-width: 460px) {
            .top-actions {
                grid-template-columns: 1fr;
            }
            .hero-badges {
                display: grid;
                grid-template-columns: 1fr;
            }
            .badge, .pill {
                width: 100%;
                justify-content: center;
            }
            .result-title {
                font-size: 1.35rem;
            }
        }
    </style>
</head>
<body>
    <div class="grid"></div>
    <div class="shell">
        <div class="topbar">
            <a class="brand" href="/studio">
                <img src="/assets/img/logo_nobg.png" alt="ZeroThink">
                <div>
                    <div class="brand-title">Oracle Desk</div>
                    <div class="brand-sub">Market + General Signal Analysis</div>
                </div>
            </a>
            <div class="top-actions">
                <a class="link-btn" href="/studio"><i class="fas fa-arrow-left"></i> Studio</a>
                <a class="link-btn" href="/cli"><i class="fas fa-terminal"></i> CLI</a>
                <a class="link-btn" href="/faq"><i class="fas fa-book"></i> FAQ</a>
            </div>
        </div>

        <section class="hero">
            <div class="card">
                <div class="panel-label">Signal Core</div>
                <h1>Ask the Oracle what the field really looks like.</h1>
                <p>Built for launch teams, founders, operators, researchers, and general scenario testing. Pick a provider, choose a stronger model, toggle live web if you have search enabled, and get a clean signal with drivers, risks, and next moves.</p>
                <div class="hero-badges">
                    <div class="badge"><i class="fas fa-bolt"></i> Default: <strong>Groq + GPT-OSS 120B</strong></div>
                    <div class="badge"><i class="fas fa-globe"></i> Optional live web context</div>
                    <div class="badge"><i class="fas fa-chart-line"></i> Market + general scenarios</div>
                </div>
            </div>
            <div class="hero-side">
                <div class="card donate-card">
                    <div class="panel-label">Support ZeroThink</div>
                    <div style="font-size:1.2rem; font-weight:800;">Need implementation help?</div>
                    <div class="donate-address" id="support-address">shaf@talktoai.org</div>
                    <div class="run-row" style="margin-top:14px;">
                        <button class="ghost-btn" type="button" onclick="copySupportAddress()"><i class="fas fa-copy"></i> Copy email</button>
                        <a class="ghost-btn" href="mailto:shaf@talktoai.org"><i class="fas fa-paper-plane"></i> Contact support</a>
                    </div>
                </div>
                <div class="card">
                    <div class="panel-label">Vault Status</div>
                    <div id="vault-status" class="hint">Loading provider readiness from your saved ZeroThink Vault...</div>
                </div>
            </div>
        </section>

        <section class="desk">
            <div class="card">
                <div class="panel-label">Oracle Input</div>
                <div class="mode-row" id="mode-row">
                    <button class="mode-btn active" data-mode="general" type="button">General</button>
                    <button class="mode-btn" data-mode="launch" type="button">Launch</button>
                    <button class="mode-btn" data-mode="risk" type="button">Risk</button>
                </div>

                <textarea id="oracle-query" maxlength="12000" placeholder="Examples:
- What is the probability this market opens up this quarter?
- How strong is the chance this launch keeps traction after day 3?
- Should I launch this SaaS next week or wait?
- What are the main failure risks for this plan?"></textarea>

                <div class="controls">
                    <div>
                        <div class="panel-label">Provider</div>
                        <select id="oracle-provider"></select>
                    </div>
                    <div>
                        <div class="panel-label">Model</div>
                        <select id="oracle-model"></select>
                    </div>
                    <div>
                        <div class="panel-label">Horizon</div>
                        <select id="oracle-horizon">
                            <option value="24h">24 Hours</option>
                            <option value="7d">7 Days</option>
                            <option value="30d" selected>30 Days</option>
                            <option value="90d">90 Days</option>
                            <option value="1y">1 Year</option>
                        </select>
                    </div>
                    <div>
                        <div class="panel-label">Live Web</div>
                        <select id="oracle-web">
                            <option value="0" selected>Off</option>
                            <option value="1">On if Serper key exists</option>
                        </select>
                    </div>
                </div>

                <div class="run-row">
                    <button class="run-btn" type="button" id="oracle-run"><i class="fas fa-satellite-dish"></i> Run Oracle</button>
                    <button class="ghost-btn" type="button" onclick="document.getElementById('oracle-query').value=''"><i class="fas fa-rotate-left"></i> Clear</button>
                    <div class="loading-note" id="oracle-loading"><i class="fas fa-spinner fa-spin"></i> Running live probability pass...</div>
                </div>
                <div class="error-box" id="oracle-error"></div>
                <div class="footer-note">
                    Oracle is for analysis and decision support, not guaranteed truth. Use it as a structured signal layer, not as a substitute for professional judgement.
                </div>
            </div>

            <div class="result-wrap">
                <div class="card">
                    <div class="panel-label">Signal Readout</div>
                    <div class="signal-orb" id="probability-orb">--%</div>
                    <div class="result-title" id="oracle-verdict">Awaiting input</div>
                    <div class="result-sub" id="oracle-reasoning">The Oracle will return a probability score, confidence, horizon, risks, and next actions.</div>
                    <div class="meta-grid" style="margin-top:18px;">
                        <div class="pill"><i class="fas fa-bullseye"></i> Confidence: <strong id="oracle-confidence" style="color:var(--gold);">--</strong></div>
                        <div class="pill"><i class="fas fa-wave-square"></i> Signal: <strong id="oracle-signal" style="color:var(--green);">--</strong></div>
                        <div class="pill"><i class="fas fa-clock"></i> Horizon: <strong id="oracle-horizon-out" style="color:var(--cyan);">--</strong></div>
                        <div class="pill"><i class="fas fa-microchip"></i> Model: <strong id="oracle-model-out" style="color:var(--gold);">--</strong></div>
                    </div>
                </div>

                <div class="mini-grid">
                    <div class="mini-card">
                        <h3>Drivers</h3>
                        <ul class="clean" id="oracle-drivers"><li>Run a scenario to populate this panel.</li></ul>
                    </div>
                    <div class="mini-card">
                        <h3>Risks</h3>
                        <ul class="clean" id="oracle-risks"><li>The Oracle will surface the biggest blockers here.</li></ul>
                    </div>
                    <div class="mini-card" style="grid-column: 1 / -1;">
                        <h3>Next Steps</h3>
                        <ul class="clean" id="oracle-next"><li>Action steps appear here after analysis.</li></ul>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        const ZT_VAULT_CSRF_TOKEN = <?= json_encode(
            zt_csrf_token(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;

        const ORACLE_MODELS = {
            groq: [
                ['openai/gpt-oss-120b', 'Groq • GPT-OSS 120B'],
                ['groq/compound', 'Groq • Compound'],
                ['groq/compound-mini', 'Groq • Compound Mini'],
                ['deepseek-r1-distill-llama-70b', 'Groq • DeepSeek R1 Distill Llama 70B'],
                ['deepseek-r1-distill-qwen-32b', 'Groq • DeepSeek R1 Distill Qwen 32B'],
                ['llama-3.3-70b-versatile', 'Groq • Llama 3.3 70B'],
                ['qwen/qwen3-32b', 'Groq • Qwen 3 32B'],
                ['qwen-qwq-32b', 'Groq • QwQ 32B'],
                ['moonshotai/kimi-k2-instruct-0905', 'Groq • Kimi K2']
            ],
            nvidia: [
                ['qwen/qwen3-coder-480b-a35b-instruct', 'NVIDIA • Qwen 3 Coder 480B'],
                ['qwen/qwen2.5-coder-32b-instruct', 'NVIDIA • Qwen 2.5 Coder 32B'],
                ['qwen/qwen3-next-80b-a3b-instruct', 'NVIDIA • Qwen 3 Next 80B'],
                ['qwen/qwen3-5-122b-a10b', 'NVIDIA • Qwen 3.5 122B'],
                ['meta/llama-3.3-70b-instruct', 'NVIDIA • Llama 3.3 70B'],
                ['meta/llama-4-maverick-17b-128e-instruct', 'NVIDIA • Llama 4 Maverick'],
                ['google/gemma-4-31b-it', 'NVIDIA • Gemma 4 31B'],
                ['google/gemma-3-27b-it', 'NVIDIA • Gemma 3 27B'],
                ['google/gemma-3n-e4b-it', 'NVIDIA • Gemma 3n E4B'],
                ['mistralai/mistral-2-large-instruct', 'NVIDIA • Mistral 2 Large'],
                ['mistralai/mistral-nemotron', 'NVIDIA • Mistral Nemotron'],
                ['mistralai/devstral-2-123b-instruct-2512', 'NVIDIA • Devstral 2 123B'],
                ['mistralai/mistral-small-24b-instruct', 'NVIDIA • Mistral Small 24B'],
                ['deepseek-ai/deepseek-v3.1', 'NVIDIA • DeepSeek V3.1'],
                ['deepseek-ai/deepseek-v3.2', 'NVIDIA • DeepSeek V3.2'],
                ['deepseek-ai/deepseek-r1', 'NVIDIA • DeepSeek R1'],
                ['deepseek-ai/deepseek-r1-distill-qwen-32b', 'NVIDIA • DeepSeek R1 Distill Qwen 32B'],
                ['nvidia/nemotron-3-super-120b-a12b', 'NVIDIA • Nemotron 3 Super 120B']
            ],
            xai: [
                ['grok-4-latest', 'xAI • Grok 4 Latest'],
                ['grok-4-fast-reasoning', 'xAI • Grok 4 Fast Reasoning'],
                ['grok-code-fast-1', 'xAI • Grok Code Fast 1'],
                ['grok-3', 'xAI • Grok 3']
            ],
            openai: [
                ['gpt-5.2', 'OpenAI • GPT-5.2'],
                ['gpt-5.2-pro', 'OpenAI • GPT-5.2 Pro'],
                ['gpt-5-pro', 'OpenAI • GPT-5 Pro'],
                ['gpt-5.1', 'OpenAI • GPT-5.1'],
                ['gpt-5', 'OpenAI • GPT-5'],
                ['gpt-5-mini', 'OpenAI • GPT-5 Mini']
            ],
            gemini: [
                ['gemini-2.5-pro', 'Gemini • 2.5 Pro'],
                ['gemini-2.5-flash', 'Gemini • 2.5 Flash'],
                ['gemini-2.5-flash-lite', 'Gemini • 2.5 Flash-Lite'],
                ['gemini-3-pro-preview', 'Gemini • 3 Pro Preview'],
                ['gemini-3-flash-preview', 'Gemini • 3 Flash Preview']
            ]
        };

        const DEFAULT_PROVIDER = 'groq';
        const DEFAULT_MODEL = 'openai/gpt-oss-120b';
        let currentMode = 'general';

        function copySupportAddress() {
            const value = document.getElementById('support-address').innerText.trim();
            navigator.clipboard.writeText(value).then(() => alert('Support email copied.'));
        }

        function renderProviders() {
            const provider = document.getElementById('oracle-provider');
            provider.innerHTML = Object.keys(ORACLE_MODELS).map((key) => {
                const label = key === 'xai' ? 'xAI' : key.charAt(0).toUpperCase() + key.slice(1);
                return `<option value="${key}">${label}</option>`;
            }).join('');
            const saved = localStorage.getItem('zt_oracle_provider');
            provider.value = saved && Object.prototype.hasOwnProperty.call(ORACLE_MODELS, saved)
                ? saved
                : DEFAULT_PROVIDER;
        }

        function renderModels() {
            const provider = document.getElementById('oracle-provider').value;
            const modelSelect = document.getElementById('oracle-model');
            const items = ORACLE_MODELS[provider] || ORACLE_MODELS.groq;
            modelSelect.innerHTML = items.map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
            const saved = localStorage.getItem('zt_oracle_model');
            const candidate = items.some(([value]) => value === saved) ? saved : (provider === DEFAULT_PROVIDER ? DEFAULT_MODEL : items[0][0]);
            modelSelect.value = candidate;
        }

        function setMode(mode) {
            currentMode = mode;
            document.querySelectorAll('.mode-btn').forEach((btn) => btn.classList.toggle('active', btn.dataset.mode === mode));
        }

        function setOrb(probability) {
            const orb = document.getElementById('probability-orb');
            const value = Math.max(0, Math.min(100, Number(probability) || 0));
            orb.innerText = `${value}%`;
            if (value >= 70) {
                orb.style.borderColor = '#56d39b';
                orb.style.color = '#56d39b';
            } else if (value >= 45) {
                orb.style.borderColor = '#f6c86f';
                orb.style.color = '#f6c86f';
            } else {
                orb.style.borderColor = '#ff7f8f';
                orb.style.color = '#ff7f8f';
            }
        }

        function fillList(id, items, fallback) {
            const node = document.getElementById(id);
            const safe = Array.isArray(items) && items.length ? items : [fallback];
            node.replaceChildren(...safe.map((item) => {
                const li = document.createElement('li');
                li.textContent = String(item);
                return li;
            }));
        }

        async function hydrateVaultStatus() {
            const status = document.getElementById('vault-status');
            try {
                const res = await fetch('/api_vault.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': ZT_VAULT_CSRF_TOKEN
                    },
                    body: JSON.stringify({ action: 'load', csrf_token: ZT_VAULT_CSRF_TOKEN })
                });
                const data = await res.json();
                if (!data.data) throw new Error('No vault payload');

                const providers = ['groq','openai','gemini','xai','nvidia'];
                const badges = providers.map((key) => {
                    const field = 'api_key_' + key;
                    const ok = !!data.data[field + '_saved'] || !!data.data[field];
                    return `<span class="pill"><i class="fas fa-${ok ? 'circle-check' : 'circle-xmark'}" style="color:${ok ? '#56d39b' : '#ff7f8f'};"></i>${key.toUpperCase()}</span>`;
                }).join(' ');

                status.innerHTML = `<div style="display:flex; flex-wrap:wrap; gap:8px;">${badges}</div><div style="margin-top:10px;">Oracle uses your saved ZeroThink Vault keys automatically. Groq is the default landing stack.</div>`;
            } catch (e) {
                status.innerHTML = 'Vault status could not be loaded right now. Oracle will still try your saved session values.';
            }
        }

        async function runOracle() {
            const query = document.getElementById('oracle-query').value.trim();
            const errorBox = document.getElementById('oracle-error');
            errorBox.style.display = 'none';
            errorBox.innerText = '';

            if (!query) {
                errorBox.style.display = 'block';
                errorBox.innerText = 'Enter a scenario, market setup, launch question, or risk case first.';
                return;
            }

            const provider = document.getElementById('oracle-provider').value;
            const model = document.getElementById('oracle-model').value;
            const horizon = document.getElementById('oracle-horizon').value;
            const useWeb = document.getElementById('oracle-web').value === '1';

            localStorage.setItem('zt_oracle_provider', provider);
            localStorage.setItem('zt_oracle_model', model);

            document.getElementById('oracle-loading').style.display = 'inline-flex';
            const runButton = document.getElementById('oracle-run');
            runButton.disabled = true;

            try {
                const res = await fetch('/api_oracle.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': ZT_VAULT_CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        query,
                        provider,
                        model,
                        mode: currentMode,
                        horizon,
                        use_web: useWeb,
                        csrf_token: ZT_VAULT_CSRF_TOKEN
                    })
                });
                const data = await res.json().catch(() => ({ status: 'error', message: 'Oracle returned an invalid response.' }));
                if (!res.ok || data.status === 'error') {
                    const reference = res.status >= 500 && data.request_id
                        ? ` Reference: ${data.request_id}.`
                        : '';
                    throw new Error((data.message || data.reasoning || 'Oracle failed.') + reference);
                }

                setOrb(data.probability);
                document.getElementById('oracle-verdict').innerText = data.verdict || 'Signal Ready';
                document.getElementById('oracle-reasoning').innerText = data.reasoning || 'No reasoning returned.';
                document.getElementById('oracle-confidence').innerText = `${data.confidence || '--'}%`;
                document.getElementById('oracle-signal').innerText = data.signal || '--';
                document.getElementById('oracle-horizon-out').innerText = data.horizon || horizon;
                document.getElementById('oracle-model-out').innerText = data.model || model;
                fillList('oracle-drivers', data.drivers, 'No main drivers returned.');
                fillList('oracle-risks', data.risks, 'No major risks returned.');
                fillList('oracle-next', data.next_steps, 'No next actions returned.');
            } catch (e) {
                errorBox.style.display = 'block';
                errorBox.innerText = e.message;
            } finally {
                document.getElementById('oracle-loading').style.display = 'none';
                runButton.disabled = false;
            }
        }

        document.getElementById('oracle-provider').addEventListener('change', renderModels);
        document.getElementById('oracle-run').addEventListener('click', runOracle);
        document.getElementById('oracle-query').addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') runOracle();
        });
        document.querySelectorAll('.mode-btn').forEach((btn) => btn.addEventListener('click', () => setMode(btn.dataset.mode)));

        renderProviders();
        renderModels();
        hydrateVaultStatus();
    </script>
</body>
</html>
