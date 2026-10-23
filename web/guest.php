<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
require_once __DIR__ . '/app/core/model_catalog.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZeroThink Direct | No-Google Key Mode</title>
<meta property="og:title" content="ZeroThink Direct">
<meta property="og:description" content="Use ZeroThink without Google login. Bring your own Groq or NVIDIA key, choose a model, and work directly in the browser.">
<meta property="og:image" content="https://zerothink.talktoai.org/assets/img/zerothinkmain.png">
<meta property="og:url" content="https://zerothink.talktoai.org/guest">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="ZeroThink Direct">
<meta name="twitter:description" content="No Google required. Local browser-stored Groq or NVIDIA keys, direct model selection, and large code/text support.">
<meta name="twitter:image" content="https://zerothink.talktoai.org/assets/img/zerothinkmain.png">
<link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--bg:#030408;--panel:#090c14;--panel2:#121827;--border:#263045;--text:#edf1fb;--muted:#98a3bf;--purple:#a855f7;--cyan:#00d7ff;--green:#00ff9d;--gold:#f8d47e;--red:#ff6c6c}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:radial-gradient(circle at top right,rgba(168,85,247,.14),transparent 22%),radial-gradient(circle at top left,rgba(0,215,255,.12),transparent 18%),linear-gradient(180deg,#030408 0%,#050815 100%);color:var(--text);font-family:Inter,system-ui,sans-serif;line-height:1.6}
.shell{max-width:1480px;margin:0 auto;padding:28px 18px 70px}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.back,.toplink,.chip,.stat{display:inline-flex;align-items:center}.back{gap:10px;padding:12px 16px;border-radius:14px;text-decoration:none;color:#fff;font-weight:800;background:rgba(255,255,255,.05);border:1px solid var(--border)}.back:hover{border-color:rgba(168,85,247,.45);box-shadow:0 0 18px rgba(168,85,247,.16)}
.toplinks{display:flex;gap:10px;flex-wrap:wrap}.toplink{gap:8px;padding:10px 14px;border-radius:12px;text-decoration:none;color:#cfd6ea;border:1px solid var(--border);background:rgba(255,255,255,.03);font-size:.88rem;font-weight:700}.toplink:hover{border-color:rgba(0,215,255,.4);color:#fff}
.hero{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(360px,.95fr);gap:22px;align-items:stretch}
.panel,.terminal,.callout,.output,.file-list{background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(255,255,255,.015));border:1px solid var(--border);border-radius:22px}
.panel{padding:26px}
.hero h1{margin:0;font-size:clamp(2.4rem,5vw,4.2rem);line-height:.95;letter-spacing:-.05em;text-transform:uppercase}
.hero p{margin:14px 0 0;color:var(--muted);font-size:1.02rem;max-width:860px}
.chip-row{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0 0}.chip{gap:8px;padding:8px 12px;border-radius:999px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);font-size:.76rem;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.chip.purple{color:var(--purple)}.chip.cyan{color:var(--cyan)}.chip.green{color:var(--green)}.chip.gold{color:var(--gold)}
.terminal{padding:22px;background:radial-gradient(circle at top left,rgba(0,255,157,.08),transparent 25%),#050912;font-family:"Cascadia Code","JetBrains Mono",monospace;white-space:pre-wrap;color:var(--green);font-size:.92rem}
.grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:18px;margin-top:22px}
.form-panel{grid-column:span 7;padding:24px}
.side-panel{grid-column:span 5;padding:24px}
.section-label{display:block;margin:0 0 10px;color:#6f7c9e;font-size:.76rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.field{margin-bottom:18px}
.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
label{display:block;margin-bottom:8px;color:#dce4fb;font-size:.9rem;font-weight:800}
input,select,textarea,button{font:inherit}
input[type="text"],input[type="password"],select,textarea{width:100%;border-radius:16px;border:1px solid #2a3550;background:#0a0f1a;color:#eef3ff;padding:14px 15px;outline:none}
input[type="text"]:focus,input[type="password"]:focus,select:focus,textarea:focus{border-color:rgba(0,215,255,.42);box-shadow:0 0 0 3px rgba(0,215,255,.08)}
textarea{min-height:220px;resize:vertical}
.hint{font-size:.84rem;color:var(--muted);margin-top:8px}
.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:10px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:13px 18px;border-radius:14px;border:1px solid var(--border);background:rgba(255,255,255,.04);color:#fff;text-decoration:none;font-weight:900;cursor:pointer}
.btn.primary{background:linear-gradient(90deg,#0f4d66,#11604e);border-color:rgba(0,255,157,.28);color:#eafdf5}
.btn.secondary{background:linear-gradient(90deg,rgba(168,85,247,.16),rgba(0,215,255,.08));border-color:rgba(168,85,247,.28)}
.btn.ghost{background:rgba(255,255,255,.025);color:#cfd7ee}
.btn:disabled{opacity:.5;cursor:not-allowed}
.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:18px}
.stat{flex-direction:column;align-items:flex-start;padding:14px;border-radius:16px;border:1px solid rgba(255,255,255,.05);background:rgba(255,255,255,.025)}
.stat .label{font-size:.72rem;color:#6d7998;text-transform:uppercase;font-weight:900;letter-spacing:.08em}
.stat .value{font-size:1rem;font-weight:900;color:#fff;margin-top:6px}
.callout{padding:18px;margin-bottom:16px}
.callout strong{color:#fff}
.callout.warn{border-color:rgba(248,212,126,.28);background:rgba(248,212,126,.07);color:#decf9d}
.callout.safe{border-color:rgba(0,255,157,.2);background:rgba(0,255,157,.05);color:#bbefd9}
.callout.info{border-color:rgba(0,215,255,.22);background:rgba(0,215,255,.05);color:#c8edf5}
.file-list,.output{padding:18px}
.file-list{margin-top:14px;min-height:90px}
.file-item{padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:.88rem;color:#d8def1}.file-item:last-child{border-bottom:none}
.output-header{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
.status{font-size:.82rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#93f2c2}
.status.error{color:#ff8b8b}
.response{white-space:pre-wrap;overflow-wrap:anywhere;color:#eef3ff;background:#050913;border:1px solid rgba(255,255,255,.06);border-radius:16px;padding:18px;min-height:240px}
.mini-links{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.mini-links a{color:var(--cyan);text-decoration:none;font-weight:800}
.foot{margin-top:26px;color:#72809f;font-size:.82rem;text-align:center}
@media (max-width:1180px){.hero{grid-template-columns:1fr}.form-panel,.side-panel{grid-column:span 12}}
@media (max-width:760px){.shell{padding:18px 12px 42px}.panel,.form-panel,.side-panel{padding:18px}.field-grid,.stats{grid-template-columns:1fr}.hero p{font-size:.96rem}.response{min-height:180px}.topbar{align-items:stretch}}
</style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <a href="/" class="back"><i class="fas fa-chevron-left"></i> Back To ZeroThink</a>
        <div class="toplinks">
            <a href="/cli" class="toplink"><i class="fas fa-terminal"></i> CLI</a>
            <a href="/faq" class="toplink"><i class="fas fa-book"></i> FAQ</a>
            <a href="/oracle" class="toplink"><i class="fas fa-eye"></i> Oracle</a>
        </div>
    </div>

    <section class="hero">
        <div class="panel">
            <h1>ZeroThink Direct</h1>
            <p>Use ZeroThink without a Google account. Bring your own <strong>Groq</strong> or <strong>NVIDIA</strong> API key, keep it stored only in your browser on this machine, choose a model, and send large prompts or code/text files straight into the direct lane.</p>
            <div class="chip-row">
                <span class="chip purple"><i class="fas fa-key"></i> No Google Required</span>
                <span class="chip cyan"><i class="fas fa-laptop"></i> Keys Stay In Your Browser</span>
                <span class="chip green"><i class="fas fa-file-code"></i> Large Code Friendly</span>
                <span class="chip gold"><i class="fas fa-shield-halved"></i> No History Or Memory</span>
            </div>
        </div>
        <div class="terminal">ZERO DIRECT // BYOK MODE

1. Pick Groq or NVIDIA.
2. Paste your key once.
3. Choose a model.
4. Drop in a prompt and optional code/text files.
5. Run without login, vault, memory, or history.</div>
    </section>

    <section class="grid">
        <div class="panel form-panel">
            <span class="section-label">Direct Session</span>
            <form id="direct-form">
                <div class="field-grid">
                    <div class="field">
                        <label for="provider">Provider</label>
                        <select id="provider" name="provider"></select>
                        <div class="hint">Direct mode supports Groq and NVIDIA only.</div>
                    </div>
                    <div class="field">
                        <label for="model">Model</label>
                        <select id="model" name="model"></select>
                        <div class="hint">The list stays current to the ZeroThink curated fast/coding lanes.</div>
                    </div>
                </div>

                <div class="field">
                    <label for="api-key">API Key</label>
                    <input id="api-key" name="api-key" type="password" placeholder="Paste your Groq or NVIDIA API key">
                    <div class="hint">Stored in <strong>localStorage</strong> on this browser only. Not saved to your ZeroThink account or server Vault.</div>
                </div>

                <div class="field">
                    <label for="prompt">Prompt</label>
                    <textarea id="prompt" name="prompt" placeholder="Example: review these PHP files, explain the architecture, and propose a safer refactor without changing behavior."></textarea>
                    <div class="hint">Large prompts are okay. ZeroThink will clamp oversized context to fit the selected model window.</div>
                </div>

                <div class="field">
                    <label for="files">Attach Code / Text Files</label>
                    <input id="files" name="files" type="file" multiple>
                    <div class="hint">Best for <code>.php</code>, <code>.py</code>, <code>.js</code>, <code>.ts</code>, <code>.json</code>, <code>.md</code>, configs, logs, and other text/code files. Binary archives are better handled in the CLI.</div>
                    <div id="file-list" class="file-list">No files loaded yet.</div>
                </div>

                <div class="actions">
                    <button class="btn primary" id="run-btn" type="submit"><i class="fas fa-bolt"></i> Run ZeroThink Direct</button>
                    <button class="btn secondary" id="clear-local" type="button"><i class="fas fa-eraser"></i> Clear Local Keys</button>
                    <button class="btn ghost" id="reset-form" type="button"><i class="fas fa-rotate-left"></i> Reset Form</button>
                </div>
            </form>
        </div>

        <aside class="panel side-panel">
            <span class="section-label">Mode Notes</span>
            <div class="stats">
                <div class="stat"><span class="label">Login</span><span class="value">Not Needed</span></div>
                <div class="stat"><span class="label">Memory</span><span class="value">Off</span></div>
                <div class="stat"><span class="label">History</span><span class="value">Off</span></div>
            </div>

            <div class="callout safe"><strong>What this is for:</strong> quick direct use, local keys, coding help, large code/text inspection, and testing Groq or NVIDIA models without linking a Google account first.</div>
            <div class="callout warn"><strong>What this is not:</strong> this lane does not use ZeroThink account memory, saved history, Pro-only web search, or vault-backed billing features. It is intentionally lean and private.</div>
            <div class="callout info"><strong>Best coding lane:</strong> NVIDIA with <code>qwen/qwen3-coder-480b-a35b-instruct</code>. Best fast lane: Groq with <code>openai/gpt-oss-120b</code> or <code>groq/compound</code>.</div>

            <div class="output">
                <div class="output-header">
                    <strong>Direct Output</strong>
                    <span id="status" class="status">READY</span>
                </div>
                <div id="meta" class="hint" style="margin-bottom:14px;">Waiting for a direct prompt.</div>
                <div id="response" class="response">The reply will appear here.</div>
            </div>

            <div class="mini-links">
                <a href="/cli">Need zip handling and terminal workflows?</a>
                <a href="/auth/google">Want full account mode instead?</a>
            </div>
        </aside>
    </section>

    <div class="foot">ZERO DIRECT MODE // GROQ + NVIDIA // LOCAL BROWSER KEY STORAGE ONLY</div>
</div>

<script>
const PROVIDERS = <?= json_encode([
  'groq' => zt_model_ids('groq'),
  'nvidia' => zt_model_ids('nvidia'),
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const TEXTISH_EXTENSIONS = [
  ".txt",".md",".php",".phtml",".py",".js",".jsx",".ts",".tsx",".json",".css",".scss",".html",".xml",".yml",".yaml",".toml",".ini",".env",".sh",".bash",".zsh",".ps1",".sql",".log",".java",".c",".cc",".cpp",".h",".hpp",".cs",".go",".rs",".rb",".swift",".kt",".kts",".scala",".conf",".cfg",".mjs",".cjs",".mts",".cts",".proto"
];

const providerEl = document.getElementById("provider");
const modelEl = document.getElementById("model");
const keyEl = document.getElementById("api-key");
const promptEl = document.getElementById("prompt");
const filesEl = document.getElementById("files");
const fileListEl = document.getElementById("file-list");
const statusEl = document.getElementById("status");
const metaEl = document.getElementById("meta");
const responseEl = document.getElementById("response");
const runBtn = document.getElementById("run-btn");

function storageKey(name) {
  return "zt_direct_" + name;
}

function getCurrentProvider() {
  return providerEl.value || "groq";
}

function maskKey(value) {
  if (!value) return "(empty)";
  if (value.length <= 8) return "********";
  return value.slice(0, 4) + "..." + value.slice(-4);
}

function escapeHtml(value) {
  return String(value || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll("\"", "&quot;")
    .replaceAll("'", "&#39;");
}

function saveState() {
  localStorage.setItem(storageKey("provider"), getCurrentProvider());
  localStorage.setItem(storageKey("model_" + getCurrentProvider()), modelEl.value || "");
  localStorage.setItem(storageKey("key_" + getCurrentProvider()), keyEl.value || "");
  localStorage.setItem(storageKey("prompt"), promptEl.value || "");
}

function populateProviders() {
  providerEl.innerHTML = "";
  Object.keys(PROVIDERS).forEach((name) => {
    const option = document.createElement("option");
    option.value = name;
    option.textContent = name === "groq" ? "Groq" : "NVIDIA";
    providerEl.appendChild(option);
  });
}

function populateModels() {
  const provider = getCurrentProvider();
  const models = PROVIDERS[provider] || [];
  const preferred = localStorage.getItem(storageKey("model_" + provider)) || models[0] || "";
  modelEl.innerHTML = "";
  models.forEach((model) => {
    const option = document.createElement("option");
    option.value = model;
    option.textContent = model;
    modelEl.appendChild(option);
  });
  modelEl.value = models.includes(preferred) ? preferred : (models[0] || "");
  keyEl.value = localStorage.getItem(storageKey("key_" + provider)) || "";
  saveState();
}

function extensionOf(fileName) {
  const lower = String(fileName || "").toLowerCase();
  const index = lower.lastIndexOf(".");
  return index >= 0 ? lower.slice(index) : "";
}

function isTextish(file) {
  const ext = extensionOf(file.name);
  return TEXTISH_EXTENSIONS.includes(ext) || String(file.type || "").startsWith("text/");
}

function classifySelectedFiles() {
  const selected = Array.from(filesEl.files || []);
  const readable = [];
  const skipped = [];
  selected.forEach((file) => {
    if (isTextish(file)) readable.push(file);
    else skipped.push(file);
  });
  return { readable, skipped };
}

function renderFiles(files, skipped = []) {
  if (!files.length && !skipped.length) {
    fileListEl.textContent = "No files loaded yet.";
    return;
  }
  const html = [];
  files.forEach((file) => {
    html.push(`<div class="file-item"><strong>${escapeHtml(file.name)}</strong> // ${(file.size / 1024).toFixed(1)} KB // code/text ready</div>`);
  });
  skipped.forEach((file) => {
    html.push(`<div class="file-item" style="color:#d8ba8a;"><strong>${escapeHtml(file.name)}</strong> // skipped here (binary/archive). Use the CLI for zip-heavy workflows.</div>`);
  });
  fileListEl.innerHTML = html.join("");
}

async function buildFileContext() {
  const { readable, skipped } = classifySelectedFiles();
  renderFiles(readable, skipped);

  if (!readable.length) {
    return "";
  }

  const sections = [];
  let totalChars = 0;
  const maxChars = 1200000;

  for (const file of readable) {
    const text = await file.text();
    const safeText = String(text || "");
    const section = `[WEB FILE]\nNAME: ${file.name}\nSIZE: ${file.size} bytes\nCONTENT:\n${safeText}\n`;
    if ((totalChars + section.length) > maxChars) {
      sections.push(`[WEB FILE]\nNAME: ${file.name}\nNOTE: skipped because the browser-side file pack is already large.\n`);
      continue;
    }
    sections.push(section);
    totalChars += section.length;
  }

  return sections.join("\n");
}

function setBusy(busy, message, error = false) {
  runBtn.disabled = busy;
  statusEl.textContent = message;
  statusEl.className = "status" + (error ? " error" : "");
}

async function runDirect(event) {
  event.preventDefault();
  saveState();

  const provider = getCurrentProvider();
  const model = modelEl.value || "";
  const key = keyEl.value.trim();
  const prompt = promptEl.value.trim();

  if (!key) {
    setBusy(false, "KEY REQUIRED", true);
    responseEl.textContent = "Paste a " + (provider === "groq" ? "Groq" : "NVIDIA") + " key first.";
    return;
  }
  if (!prompt) {
    setBusy(false, "PROMPT REQUIRED", true);
    responseEl.textContent = "Write a prompt before running ZeroThink Direct.";
    return;
  }

  setBusy(true, "RUNNING");
  metaEl.textContent = "Reading files and sending a direct " + provider.toUpperCase() + " request...";
  responseEl.textContent = "Working...";

  try {
    const fileContext = await buildFileContext();
    const payload = {
      prompt,
      engine: provider,
      model,
      key,
      direct_mode: true,
      zero_mode: false,
      use_web: false
    };
    if (fileContext) {
      payload.file_context = fileContext;
    }

    const res = await fetch("/api_agent.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    let data;
    try {
      data = await res.json();
    } catch (err) {
      throw new Error("The server returned a non-JSON response.");
    }

    if (!res.ok || data.status !== "success") {
      throw new Error(data.reply || data.message || ("HTTP " + res.status));
    }

    responseEl.textContent = String(data.reply || "").trim() || "(No reply returned.)";
    metaEl.textContent = "Provider: " + String(data.provider || provider).toUpperCase() + " // Model: " + String(data.model || model);
    setBusy(false, "COMPLETE");
  } catch (error) {
    responseEl.textContent = String(error && error.message ? error.message : error);
    metaEl.textContent = "The direct request failed. Check the key, provider, model access, or browser network panel.";
    setBusy(false, "FAILED", true);
  }
}

populateProviders();
providerEl.value = localStorage.getItem(storageKey("provider")) || "groq";
populateModels();
promptEl.value = localStorage.getItem(storageKey("prompt")) || "";
renderFiles([]);

providerEl.addEventListener("change", populateModels);
modelEl.addEventListener("change", saveState);
keyEl.addEventListener("input", saveState);
promptEl.addEventListener("input", saveState);
filesEl.addEventListener("change", () => {
  const { readable, skipped } = classifySelectedFiles();
  renderFiles(readable, skipped);
});
document.getElementById("direct-form").addEventListener("submit", runDirect);

document.getElementById("clear-local").addEventListener("click", () => {
  Object.keys(PROVIDERS).forEach((provider) => {
    localStorage.removeItem(storageKey("key_" + provider));
    localStorage.removeItem(storageKey("model_" + provider));
  });
  keyEl.value = "";
  populateModels();
  metaEl.textContent = "Local browser-stored direct keys cleared.";
  responseEl.textContent = "Your local direct keys were removed from this browser.";
  setBusy(false, "CLEARED");
});

document.getElementById("reset-form").addEventListener("click", () => {
  promptEl.value = "";
  filesEl.value = "";
  renderFiles([]);
  localStorage.removeItem(storageKey("prompt"));
  responseEl.textContent = "Form reset. Keys are still stored locally unless you clear them.";
  metaEl.textContent = "Waiting for a direct prompt.";
  setBusy(false, "READY");
});
</script>
</body>
</html>
