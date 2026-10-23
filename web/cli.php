<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZeroThink CLI aka Agent Zero | Research Workbench & Server Operator</title>
<meta property="og:title" content="ZeroThink CLI aka Agent Zero">
<meta property="og:description" content="Install ZeroThink CLI for local projects, saved servers, PDF reports, evidence-first research papers, SSH/SFTP, diagnostics, deployment planning, agents, and FreeWebPanel operations.">
<meta property="og:image" content="https://zerothink.talktoai.org/assets/img/zerothinkmain.png">
<meta property="og:url" content="https://zerothink.talktoai.org/cli">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="ZeroThink CLI aka Agent Zero">
<meta name="twitter:description" content="Cross-platform Agent Zero terminal with local and remote files, PDF reports, playable game starters, server checks, research workflows, agents, web research, Playwright MCP, encryption, SSH/SFTP, and FreeWebPanel operations.">
<meta name="twitter:image" content="https://zerothink.talktoai.org/assets/img/zerothinkmain.png">
<link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--bg:#020205;--panel:#090912;--panel2:#0f1018;--border:#222430;--text:#ececf3;--muted:#9ea3b5;--accent:#a855f7;--cyan:#00e7ff;--green:#00ff9d;--gold:#f6cf7a;--red:#ff6161}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;color:var(--text);background:radial-gradient(circle at top right,rgba(168,85,247,.1),transparent 26%),radial-gradient(circle at top left,rgba(0,231,255,.08),transparent 22%),linear-gradient(180deg,#020205 0%,#04050b 100%);font-family:Inter,system-ui,sans-serif;line-height:1.65}
.container{max-width:1480px;margin:0 auto;display:flex;min-height:100vh}.sidebar{width:320px;position:sticky;top:0;height:100vh;padding:34px 20px;overflow-y:auto;border-right:1px solid var(--border);background:rgba(5,6,10,.92);backdrop-filter:blur(12px)}.content{flex:1;padding:52px 42px 84px;overflow-y:auto}
.back-btn,.nav-link,.tag,.pill{display:inline-flex;align-items:center}.back-btn{gap:10px;margin-bottom:30px;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:800;letter-spacing:.03em;color:#fff;background:linear-gradient(90deg,rgba(255,255,255,.05),rgba(255,255,255,.02));border:1px solid var(--border)}.back-btn:hover{border-color:rgba(168,85,247,.35);box-shadow:0 0 20px rgba(168,85,247,.14)}
.nav-label{font-size:.72rem;text-transform:uppercase;color:#6c7081;font-weight:900;letter-spacing:.12em;margin:0 0 14px 14px}.nav-link{gap:10px;width:100%;margin-bottom:6px;padding:12px 14px;color:#8d92a5;text-decoration:none;font-size:.94rem;border-radius:10px;border-left:2px solid transparent;transition:.18s ease}.nav-link:hover{color:#fff;background:rgba(255,255,255,.04);border-left-color:var(--accent)}
h1{margin:0 0 12px;font-size:clamp(2.55rem,5vw,4.3rem);line-height:.98;letter-spacing:-.05em;text-transform:uppercase}h2{margin:62px 0 24px;padding-bottom:14px;border-bottom:1px solid var(--border);color:var(--accent);font-size:1.72rem;display:flex;align-items:center;gap:14px}h3{margin:0 0 12px;color:var(--cyan);font-size:1rem;text-transform:uppercase;letter-spacing:.08em}p{margin:0 0 16px;color:var(--muted);font-size:1rem}strong{color:#fff}
.brand-stack{display:flex;flex-wrap:wrap;align-items:center;gap:14px 16px;margin:0 0 12px}.zero-mark{display:inline-flex;align-items:center;justify-content:center;padding:12px 18px;border-radius:18px;border:1px solid rgba(0,255,157,.32);background:linear-gradient(135deg,rgba(0,255,157,.18),rgba(0,255,157,.05));box-shadow:0 0 0 1px rgba(0,255,157,.08) inset,0 0 30px rgba(0,255,157,.08);color:var(--green);font-weight:1000;font-size:clamp(1.6rem,3vw,2.5rem);letter-spacing:.16em;text-transform:uppercase}.brand-title{display:flex;flex-direction:column;gap:6px}.brand-title h1{margin:0}.brand-sub{display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap;color:#cbeedc;font-size:.92rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.brand-sub .aka{padding:6px 10px;border-radius:999px;border:1px solid rgba(0,255,157,.2);background:rgba(0,255,157,.06);color:var(--green)}
.hero-copy{max-width:960px;font-size:1.14rem}.tag-row{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 26px}.tag{gap:8px;padding:7px 12px;border-radius:999px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.tag.green{color:var(--green)}.tag.cyan{color:var(--cyan)}.tag.gold{color:var(--gold)}.tag.purple{color:var(--accent)}
.hero-grid,.grid,.steps,.faq-grid{display:grid;gap:22px}.hero-grid{grid-template-columns:minmax(0,1.3fr) minmax(320px,.95fr)}.grid,.faq-grid{grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}.steps{grid-template-columns:repeat(auto-fit,minmax(210px,1fr))}
.card,.terminal-card,.step-card{background:linear-gradient(180deg,rgba(255,255,255,.025),rgba(255,255,255,.01));border:1px solid var(--border);border-radius:18px;padding:24px;position:relative;overflow:hidden}.card:hover,.step-card:hover{border-color:rgba(0,231,255,.24);transform:translateY(-2px)}.card-icon{font-size:2rem;color:var(--accent);margin-bottom:16px}.card-title{color:#fff;font-weight:900;letter-spacing:.08em;font-size:1rem;margin-bottom:10px;text-transform:uppercase}.terminal-card{background:radial-gradient(circle at top left,rgba(0,255,157,.08),transparent 24%),#05070b;box-shadow:inset 0 0 26px rgba(0,0,0,.34)}
.hero-terminal,.cmd{display:block;border-radius:14px;border:1px solid rgba(255,255,255,.06);background:rgba(0,0,0,.36);font-family:"Cascadia Code","JetBrains Mono",monospace;font-size:.92rem;line-height:1.58;overflow-wrap:anywhere;white-space:pre-wrap}.hero-terminal{padding:18px;margin-top:14px;color:var(--green)}.cmd{padding:14px 16px;margin-top:14px;color:#eef2c6}
.callout{padding:16px 18px;border-radius:14px;border:1px solid rgba(246,207,122,.18);background:rgba(246,207,122,.05);color:#d9d0b3;margin-top:18px}.callout code,.matrix-table code,.step-card code{color:var(--gold);font-family:"Cascadia Code","JetBrains Mono",monospace}.step-num{width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:rgba(168,85,247,.12);color:var(--accent);font-weight:900;margin-bottom:14px}
.matrix-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:32px}.matrix-table th{padding:18px;background:#141724;color:#fff;text-align:left;font-size:.82rem;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid var(--border)}.matrix-table td{padding:16px 18px;color:var(--muted);background:rgba(255,255,255,.015);border-bottom:1px solid rgba(255,255,255,.05);vertical-align:top}.matrix-table tr:last-child td{border-bottom:none}
.pill{padding:5px 10px;border-radius:999px;font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.06em}.pill.safe{background:rgba(0,255,157,.1);border:1px solid rgba(0,255,157,.18);color:var(--green)}.pill.full{background:rgba(168,85,247,.1);border:1px solid rgba(168,85,247,.18);color:var(--accent)}.pill.remote{background:rgba(0,231,255,.09);border:1px solid rgba(0,231,255,.18);color:var(--cyan)}
.foot{margin-top:76px;padding-top:24px;border-top:1px solid var(--border);color:#5f6477;text-align:center;font-size:.82rem}
@media (max-width:1120px){.container{flex-direction:column}.sidebar{width:100%;height:auto;position:relative;border-right:none;border-bottom:1px solid var(--border)}.content{padding:34px 22px 58px}.hero-grid{grid-template-columns:1fr}}
@media (max-width:760px){h2{margin-top:52px;font-size:1.48rem}.content{padding:28px 16px 48px}.card,.terminal-card,.step-card{padding:20px}.steps,.grid,.faq-grid{grid-template-columns:1fr}.matrix-table,.matrix-table thead,.matrix-table tbody,.matrix-table tr,.matrix-table th,.matrix-table td{display:block;width:100%}.matrix-table thead{display:none}.matrix-table td{padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.06)}}
</style>
</head>
<body>
<div class="container">
<div class="sidebar">
<a href="/studio" class="back-btn"><i class="fas fa-microchip"></i> RETURN TO STUDIO</a>
<div class="nav-label">ZeroThink CLI Guide</div>
<a href="#install" class="nav-link"><i class="fas fa-download"></i> Install</a>
<a href="#update" class="nav-link"><i class="fas fa-arrow-rotate-right"></i> Update</a>
<a href="#quickstart" class="nav-link"><i class="fas fa-play"></i> Quickstart</a>
<a href="#identity" class="nav-link"><i class="fas fa-id-badge"></i> Identity</a>
<a href="#direct-mode" class="nav-link"><i class="fas fa-key"></i> Direct Mode</a>
<a href="#first-session" class="nav-link"><i class="fas fa-terminal"></i> First Session</a>
<a href="#help-inside-cli" class="nav-link"><i class="fas fa-circle-question"></i> Help Inside CLI</a>
<a href="#browser" class="nav-link"><i class="fas fa-globe"></i> Browser Lane</a>
<a href="#extensions" class="nav-link"><i class="fas fa-plug-circle-plus"></i> Extensions</a>
<a href="#agents" class="nav-link"><i class="fas fa-robot"></i> Agent Autopilot</a>
<a href="#access" class="nav-link"><i class="fas fa-lock"></i> Access Modes</a>
<a href="#files" class="nav-link"><i class="fas fa-folder-tree"></i> Files + Zips</a>
<a href="#research" class="nav-link"><i class="fas fa-file-lines"></i> Research Workbench</a>
<a href="#crypto" class="nav-link"><i class="fas fa-shield-halved"></i> Z-Pepper Crypto</a>
<a href="#remote" class="nav-link"><i class="fas fa-server"></i> Server Profiles</a>
<a href="#freewebpanel" class="nav-link"><i class="fas fa-server"></i> FreeWebPanel Mode</a>
<a href="#models" class="nav-link"><i class="fas fa-network-wired"></i> Model Routing</a>
<a href="#commands" class="nav-link"><i class="fas fa-list"></i> Core Commands</a>
<a href="#troubleshoot" class="nav-link"><i class="fas fa-wrench"></i> Troubleshooting</a>
</div>

<div class="content">
<div class="brand-stack">
<div class="zero-mark">ZERO</div>
<div class="brand-title">
<h1>ZeroThink CLI</h1>
<div class="brand-sub"><span>Terminal Node</span><span class="aka">aka Agent Zero</span></div>
</div>
</div>
<p class="hero-copy">ZeroThink in the terminal, built for people who may know <strong>nothing yet</strong>. Install it, then either link the same account you use on the website or stay outside Google with a local Groq or NVIDIA key. Agent Zero can operate on an approved repo or the wider machine, work with saved SSH targets, manage files and encrypted bundles, create PDF reports, search and browse, run bounded agents, create playable browser games, run saved-server checks, and build evidence-first research papers, articles, surveys, reviews, and proposals from local or saved-server source files. Plain-English build and fix requests route into the real tool stack, while <code>/steer plan</code> and <code>/steer confirm</code> keep larger tasks staged for approval. The terminal remains concise; Studio provides the richer public ZeroThink Reasoning Brief.</p>
<div class="tag-row">
<span class="tag purple"><i class="fas fa-user-lock"></i> Account + Direct Mode</span>
<span class="tag green"><i class="fas fa-folder-tree"></i> Repo-safe folder mode</span>
<span class="tag cyan"><i class="fas fa-server"></i> SSH + remote files</span>
<span class="tag cyan"><i class="fas fa-stethoscope"></i> Server checks</span>
<span class="tag green"><i class="fas fa-server"></i> FreeWebPanel operations</span>
<span class="tag gold"><i class="fas fa-file-zipper"></i> Zip + archive workflows</span>
<span class="tag gold"><i class="fas fa-file-pdf"></i> PDF reports</span>
<span class="tag gold"><i class="fas fa-shield-halved"></i> Z-Pepper file crypto</span>
<span class="tag purple"><i class="fas fa-robot"></i> Autopilot + subagents</span>
<span class="tag green"><i class="fas fa-gamepad"></i> Game starter</span>
<span class="tag cyan"><i class="fas fa-book-bookmark"></i> repo guidance context</span>
<span class="tag gold"><i class="fas fa-globe"></i> URL fetch</span>
<span class="tag cyan"><i class="fas fa-magnifying-glass"></i> Local web search</span>
<span class="tag green"><i class="fas fa-file-lines"></i> Research paper workflows</span>
<span class="tag purple"><i class="fas fa-window-restore"></i> Playwright MCP</span>
<span class="tag purple"><i class="fas fa-plug-circle-plus"></i> Custom commands</span>
<span class="tag green"><i class="fas fa-list-check"></i> Local plan board</span>
<span class="tag purple"><i class="fas fa-circle-question"></i> Built-in help topics</span>
</div>

<div class="hero-grid">
<div class="card">
<h3>No Guesswork</h3>
<p>The fast path is simple: install, run <strong><code>zerothink</code></strong> or <strong><code>zerothink tutorial</code></strong>, then either link your account or set a local Groq/NVIDIA key, pick <strong>folder mode</strong> if you only want one repo, and then start with a plain-English prompt. For action requests like creating a project, fixing a config, building a small game, or scaffolding files, Agent Zero now executes through the real CLI tool stack by default. The reasoning layer is stronger now too: it works in a private orient → inspect → plan → act → verify → report loop, with compact scans instead of noisy dependency or backup-folder dumps. The CLI includes its own guide, examples, command map, local checkpoints, project context support, and an explicit autopilot layer for multi-step work inside the terminal.</p>
<div class="callout"><strong>Best beginner flow:</strong> install the CLI, run <code>zerothink</code> for the first-run guide, then either <code>zerothink login</code> or <code>zerothink key set groq</code>, then <code>zerothink access folder .</code>, then optionally add a repo guidance file like <code>ZEROTHINK.md</code>, <code>CLAUDE.md</code>, <code>AGENTS.md</code>, or <code>.claude/CLAUDE.md</code> so the CLI keeps project-specific rules in mind automatically. After that, you can type normal requests like <code>make a small game</code> or <code>make a new project folder for shafaet.com</code> and Agent Zero will execute the work instead of only suggesting commands. If you want a safer review step first, switch to <code>/steer plan</code>, let the CLI stage the task, then use <code>/go</code> to execute or <code>/stop</code> to discard it. When you want it to self-drive a bounded task more explicitly, use <code>zerothink autopilot "map this repo and tell me what to fix first"</code>. The terminal path is intentionally cleaner than Studio: no automatic Zero trace dump in normal replies.</div>
</div>
<div class="terminal-card">
<h3>What First Run Looks Like</h3>
<div class="hero-terminal">███████╗███████╗██████╗  ██████╗
╚══███╔╝██╔════╝██╔══██╗██╔═══██╗
  ███╔╝ █████╗  ██████╔╝██║   ██║
 ███╔╝  ██╔══╝  ██╔══██╗██║   ██║
███████╗███████╗██║  ██║╚██████╔╝
╚══════╝╚══════╝╚═╝  ╚═╝ ╚═════╝

ZERO THINK CLI // AGENT ZERO // TERMINAL NODE
New here? Type /about, /tutorial, /guide, or /help quickstart.
Need examples? Type /help examples.

ZERO&gt; /about
ZERO&gt; /capabilities
ZERO&gt; /help quickstart
ZERO&gt; /key status
ZERO&gt; /context show
ZERO&gt; /attach-url https://example.com
ZERO&gt; /search-web nginx php-fpm tuning
ZERO&gt; /page 1
ZERO&gt; /attach-page 1
ZERO&gt; /runtime
ZERO&gt; /provider groq
ZERO&gt; /model openai/gpt-oss-120b
ZERO&gt; /plan add map the repo and auth flow
ZERO&gt; /autopilot map this repo and list the highest-risk auth bugs
ZERO&gt; /checkpoint repo-audit
ZERO&gt; review this repo and tell me what to fix first</div>
</div>
</div>

<section id="identity">
<h2><i class="fas fa-id-badge"></i> Identity</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-fingerprint"></i></div><div class="card-title">Who It Is</div><p>The CLI now answers as <strong>ZeroThink CLI aka Agent Zero</strong>, not as a generic AI assistant. If someone asks who it is or what it can do, it answers from the local terminal runtime and current route instead of letting the model improvise.</p><code class="cmd">zerothink about
zerothink capabilities
/about
/capabilities</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-route"></i></div><div class="card-title">What Route It Is Using</div><p>When people want to know whether they are on Groq, NVIDIA, linked-account mode, or direct-key mode, use the runtime commands instead of asking the model to guess.</p><code class="cmd">zerothink runtime
zerothink status
/runtime
/status</code></div>
</div>
</section>

<section id="install">
<h2><i class="fas fa-download"></i> Install</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fab fa-windows"></i></div><div class="card-title">Windows PowerShell</div><p>Installs the Python client, creates launchers, adds the user bin path if needed, and then prints the beginner quickstart commands.</p><code class="cmd">irm https://zerothink.talktoai.org/cli-assets/install.ps1 | iex</code></div>
<div class="card"><div class="card-icon"><i class="fab fa-linux"></i></div><div class="card-title">Linux / macOS</div><p>Downloads the CLI into <code>~/.zerothink</code>, creates a launcher in <code>~/.local/bin</code>, and prints the same beginner next steps.</p><code class="cmd">curl -fsSL https://zerothink.talktoai.org/cli-assets/install.sh | bash</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-book-open"></i></div><div class="card-title">Read Help First</div><p>If you know nothing yet, start the guided setup before trying random commands or switching providers.</p><code class="cmd">zerothink
zerothink tutorial
zerothink help quickstart
zerothink help sessions
zerothink help fetch
zerothink help custom
zerothink context show
zerothink help examples
zerothink --help</code></div>
</div>
<div class="callout"><strong>Two ways in:</strong> linked-account mode uses the same ZeroThink account and Vault keys as the website, while direct-key mode lets you stay outside Google by storing a local Groq or NVIDIA key on the machine itself or auto-detecting <code>GROQ_API_KEY</code> / <code>NVIDIA_API_KEY</code> from your shell.</div>
</section>

<section id="update">
<h2><i class="fas fa-arrow-rotate-right"></i> Update Existing Installs</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-wand-magic-sparkles"></i></div><div class="card-title">Best Update Command</div><p>If ZeroThink CLI is already installed, this is the command to tell people to run. It downloads the latest published CLI, verifies the SHA-256 hash, then replaces the local script safely.</p><code class="cmd">zerothink update</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-terminal"></i></div><div class="card-title">After Updating</div><p>Close the old terminal session and reopen it so the new script is actually loaded into memory.</p><code class="cmd">zerothink update
exit
zerothink</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-circle-info"></i></div><div class="card-title">Check The Live Route</div><p>If anyone is confused about whether they are on Groq, NVIDIA, linked-account mode, or local direct-key mode, use the built-in runtime inspector instead of asking the model to guess.</p><code class="cmd">zerothink runtime
zerothink status
/runtime</code></div>
</div>
</section>

<section id="quickstart">
<h2><i class="fas fa-play"></i> Five-Minute Quickstart</h2>
<div class="steps">
<div class="step-card"><div class="step-num">1</div><h3>Install It</h3><p>Run one install command for your machine.</p><code class="cmd">irm https://zerothink.talktoai.org/cli-assets/install.ps1 | iex</code></div>
<div class="step-card"><div class="step-num">2</div><h3>Run The Guided Setup</h3><p>The CLI explains itself now instead of assuming you know the flow already.</p><code class="cmd">zerothink
or
zerothink tutorial</code></div>
<div class="step-card"><div class="step-num">3</div><h3>Choose A Login Lane</h3><p>Either link the normal ZeroThink account or set a local Groq/NVIDIA key for no-Google direct mode.</p><code class="cmd">zerothink login
or
zerothink key set groq</code></div>
<div class="step-card"><div class="step-num">4</div><h3>Choose A Safe Mode</h3><p>Folder mode is best if you only want one repo available.</p><code class="cmd">zerothink access folder .</code></div>
<div class="step-card"><div class="step-num">5</div><h3>Open The Prompt</h3><p>Then ask for help in plain English.</p><code class="cmd">zerothink
/guide
review this codebase and explain what it does</code></div>
<div class="step-card"><div class="step-num">6</div><h3>Stage Big Tasks First</h3><p>If you do not want Agent Zero to run immediately, switch steering mode and stage the task before you approve it.</p><code class="cmd">/steer plan
make a new project folder for shafaet.com
/go
/stop</code></div>
</div>
</section>

<section id="direct-mode">
<h2><i class="fas fa-key"></i> No-Google Direct Mode</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-bolt"></i></div><div class="card-title">What It Does</div><p>If you do not want to link a Google account, the CLI can still run with a local <strong>Groq</strong> or <strong>NVIDIA</strong> key. The key stays in <code>~/.zerothink/config.json</code> on that machine, not in the ZeroThink Vault. If your shell already has <code>GROQ_API_KEY</code> or <code>NVIDIA_API_KEY</code>, the CLI can auto-detect that too.</p></div>
<div class="card"><div class="card-icon"><i class="fas fa-terminal"></i></div><div class="card-title">Best Commands</div><code class="cmd">zerothink key set groq
zerothink key status
zerothink status
zerothink runtime
zerothink config provider groq
zerothink</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-circle-info"></i></div><div class="card-title">Important Limits</div><p>Direct mode is intentionally lean: no account memory, no saved ZeroThink history, no ZeroThink Vault key import, no linked-account session, and no Pro-only account features. Local CLI browser tools like <code>zerothink search</code>, <code>zerothink browse</code>, <code>/search-web</code>, and <code>/page</code> still work, but they are local terminal research helpers, not website account memory or account web-search state.</p></div>
</div>
</section>

<section id="first-session">
<h2><i class="fas fa-terminal"></i> Your First Session</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-user-check"></i></div><div class="card-title">If You Just Want Repo Help</div><p>Use folder mode, then ask normal questions like you would in chat.</p><code class="cmd">cd your-project
zerothink access folder .
zerothink
/guide
scan this repo and tell me the most important files first</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-code"></i></div><div class="card-title">If You Want Coding Models</div><p>Pick Groq for the fastest practical coding default with <code>openai/gpt-oss-120b</code>, or switch to NVIDIA for the heavier coding lane like Qwen 480B Coder. You can also ask Agent Zero for the curated coding shortlist directly.</p><code class="cmd">/models coding
/provider groq
/model openai/gpt-oss-120b

/provider nvidia
/model qwen/qwen3-coder-480b-a35b-instruct
read src/main.py and propose a safer refactor</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-file-circle-plus"></i></div><div class="card-title">If You Have Big Files</div><p>Attach a file or zip instead of pasting a giant wall of code into the terminal. If the repo contains a guidance file like <code>ZEROTHINK.md</code>, <code>CLAUDE.md</code>, <code>AGENTS.md</code>, or <code>.claude/CLAUDE.md</code>, the CLI also folds that guidance into local prompts automatically.</p><code class="cmd">zerothink zip create audit.zip src package.json
zerothink chat --file audit.zip "review this project"</code></div>
</div>
</section>

<section id="help-inside-cli">
<h2><i class="fas fa-circle-question"></i> Help Inside The CLI</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-compass"></i></div><div class="card-title">Built-In Topics</div><p>The CLI now ships with a real first-run guide plus beginner help topics so people do not have to memorize the command tree first.</p><code class="cmd">zerothink about
zerothink capabilities
zerothink tutorial
zerothink help quickstart
zerothink help sessions
zerothink context show
zerothink help access
zerothink help files
zerothink help remote
zerothink help examples</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-comments"></i></div><div class="card-title">Inside The REPL</div><p>Once you are inside the live prompt, the same guidance is available as slash commands, and unique short prefixes like <code>/he</code> can resolve to the right command. Steering and stop controls are built in now too.</p><code class="cmd">/about
/capabilities
/tutorial
/guide
/help
/steer plan
/go
/stop
/runtime
/context
/plan
/checkpoint first-pass
/attach-url https://example.com
/custom show
/help commands
/help examples
/tips</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-life-ring"></i></div><div class="card-title">If You Are Stuck</div><p>The safest recovery pattern is to check identity, access, provider, and direct-key readiness before assuming the model is broken.</p><code class="cmd">zerothink me
zerothink status
zerothink runtime
zerothink key status
zerothink access status
zerothink doctor</code></div>
</div>
<table class="matrix-table">
<thead><tr><th>Help Topic</th><th>What It Explains</th><th>Command</th></tr></thead>
<tbody>
<tr><td>Quickstart</td><td>Install, login, access mode, first prompt, and the safest beginner path.</td><td><code>zerothink help quickstart</code></td></tr>
<tr><td>Access</td><td>The difference between folder mode and machine mode.</td><td><code>zerothink help access</code></td></tr>
<tr><td>Files</td><td>Workspace reads, searches, writes, and zip/archive workflows.</td><td><code>zerothink help files</code></td></tr>
<tr><td>Sessions</td><td>Local checkpointing, resume, export, and history workflows.</td><td><code>zerothink help sessions</code></td></tr>
<tr><td>Plan</td><td>A local task board inside the REPL for open vs done work.</td><td><code>zerothink help plan</code></td></tr>
<tr><td>Context</td><td>How repo-level <code>ZEROTHINK.md</code>, <code>CLAUDE.md</code>, and <code>AGENTS.md</code> files steer the CLI locally.</td><td><code>zerothink help context</code></td></tr>
<tr><td>Fetch</td><td>How local URL fetching and URL attachments work.</td><td><code>zerothink help fetch</code></td></tr>
<tr><td>Browser</td><td>How local web search, page open, page attach, and browser session state work.</td><td><code>zerothink help browser</code></td></tr>
<tr><td>Custom</td><td>How local JSON command packs extend the CLI.</td><td><code>zerothink help custom</code></td></tr>
<tr><td>Remote</td><td>Saved SSH targets, remote execution, SCP, and remote file read/write/replace workflows.</td><td><code>zerothink help remote</code></td></tr>
<tr><td>Models</td><td>When to use Groq, NVIDIA, Gemini, OpenAI, or xAI, plus which providers support no-Google direct mode.</td><td><code>zerothink help models</code></td></tr>
<tr><td>Examples</td><td>Real prompts and operator workflows for repo work, audits, and remote ops.</td><td><code>zerothink help examples</code></td></tr>
</tbody>
</table>
</section>

<section id="browser">
<h2><i class="fas fa-globe"></i> Browser Research Lane</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-magnifying-glass"></i></div><div class="card-title">Search The Web Locally</div><p>Run a live web search from the terminal, keep the numbered results in the current session, and reopen them later without leaving the CLI flow.</p><code class="cmd">zerothink search "nginx php-fpm tuning"
/search-web latest ollama gemma docs
/pages</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-language"></i></div><div class="card-title">Natural Language Search</div><p>Inside the live prompt, users do not need to remember slash commands. Agent Zero now recognizes plain phrases and routes them into the local search or research lane; bare words like <code>search</code> or <code>research</code> ask for the missing topic instead of failing.</p><code class="cmd">search the web for latest OpenAI model docs
web search UK quantum computing grants
do a web search for Groq GPT-OSS 120B
research DNA evidence workflow tools
literature search quantum-informed AI</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-book-open-reader"></i></div><div class="card-title">Open A Result And Read It</div><p>Open a saved result into the local browser state and preview the cleaned page text right inside the terminal.</p><code class="cmd">/page 1
/browse https://nginx.org/en/docs/
zerothink browse https://example.com</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-link"></i></div><div class="card-title">Attach A Page To The Prompt</div><p>Once a page matters, attach it into the current session so the next prompt can reason over that page alongside files and repo context.</p><code class="cmd">/attach-page 1
/files
/clear-pages</code></div>
</div>
<div class="callout"><strong>Important:</strong> this is a local CLI browser lane. It is useful for docs, changelogs, incident notes, and research, but it is not pretending to be a full graphical browser.</div>
</section>

<section id="extensions">
<h2><i class="fas fa-plug-circle-plus"></i> Extension Layers</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-globe"></i></div><div class="card-title">URL Fetch + Browser Attach</div><p>You can fetch a live URL locally, search for a result, open a page into the browser state, and attach that page to the current session as prompt context.</p><code class="cmd">zerothink fetch https://example.com
zerothink search "nginx hardening"
zerothink chat --url https://example.com "summarize this"
/search-web ollama gemma docs
/page 1
/attach-page 1
/attach-url https://example.com
/detach-url all</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-window-restore"></i></div><div class="card-title">Playwright MCP Browser</div><p>For dynamic pages or real browser automation, ZeroThink CLI can register the official Playwright MCP server locally and drive it through a first-class wrapper instead of raw tool names. The built-in profile now uses headless isolated mode so separate runs do not fight over the same browser profile.</p><code class="cmd">zerothink mcp add-playwright
zerothink mcp tools playwright
zerothink playwright open https://example.com
zerothink playwright call browser_snapshot
/playwright open https://example.com
/playwright tools</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-terminal"></i></div><div class="card-title">Custom Command Packs</div><p>Drop a <code>ZEROTHINK.commands.json</code> file into a repo to add reusable prompt commands or machine-mode shell commands without editing the CLI itself.</p><code class="cmd">zerothink custom
zerothink custom show
/custom show</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-list-check"></i></div><div class="card-title">Local Plan Board</div><p>Keep a clean step list inside the live session, checkpoint it with the rest of the conversation, and reopen the exact same working state later.</p><code class="cmd">/plan add audit auth flow
/plan
/plan done 1
/plan clear</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-shield-halved"></i></div><div class="card-title">Findings-First Review Lane</div><p>There is now a dedicated review wrapper for code-review style output instead of relying only on free-form prompting.</p><code class="cmd">zerothink review src/app.py
/review src/app.py security only</code></div>
</div>
</section>

<section id="agents">
<h2><i class="fas fa-robot"></i> Agent Autopilot</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-wand-magic-sparkles"></i></div><div class="card-title">Autopilot Runs</div><p>Use autopilot when you want the CLI to drive a multi-step task by itself instead of stopping after one reply. The controller chooses one next action at a time, saves state locally, and can finish with a proper summary.</p><code class="cmd">zerothink autopilot "audit this repo and tell me what to fix first"
zerothink autopilot "inspect the nginx config and summarize the risks" --steps 8</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-users-gear"></i></div><div class="card-title">Named Workers</div><p>Create named workers like <code>zero1</code> and rerun them later. This is useful when you want a durable saved task with logs rather than a one-shot run.</p><code class="cmd">zerothink agent create zero1 "map the repo and find auth risks"
zerothink agent run zero1 --steps 8
zerothink agent show zero1
zerothink agent log zero1</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-diagram-project"></i></div><div class="card-title">Subagents</div><p>The controller can spawn child workers like <code>zero2</code> and <code>zero3</code> for side tasks when that helps the main job. Those child runs are logged back into the parent agent state.</p><code class="cmd">zerothink autopilot "review this app, inspect the billing code, and split side tasks when useful"
/agent list
/agent log zero2</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-window-restore"></i></div><div class="card-title">Tool Surface</div><p>Agent mode can inspect local files, run shell commands, read and edit remote files on saved server profiles, search the web, and now call Playwright/MCP tools directly when machine mode allows it.</p><code class="cmd">zerothink mcp add-playwright
zerothink autopilot "open the docs site with Playwright, inspect the page, and summarize the important controls"
zerothink remote read prod /etc/nginx/nginx.conf</code></div>
</div>
<div class="callout"><strong>Good default:</strong> use <code>folder mode</code> for repo work and switch to <code>machine mode</code> only when you want shell, SSH, remote files, or Playwright/MCP automation available to the agent.</div>
</section>

<section id="access">
<h2><i class="fas fa-lock"></i> Access Modes</h2>
<table class="matrix-table">
<thead><tr><th>Mode</th><th>What It Allows</th><th>Best Use</th><th>Command</th></tr></thead>
<tbody>
<tr><td><span class="pill safe">Folder Only</span></td><td>Reads, writes, scans, search, zips, attachments, and project work only inside one chosen workspace root.</td><td>Beginners, coding sessions, contractor use, or any time you want repo help without exposing the whole machine.</td><td><code>zerothink access folder .</code></td></tr>
<tr><td><span class="pill full">Machine</span></td><td>Full local shell execution, wider filesystem access, SSH commands, SCP transfers, and remote file read/write/replace workflows.</td><td>Senior operator work, machine admin tasks, server work, and broad local automation.</td><td><code>zerothink access machine</code></td></tr>
<tr><td><span class="pill remote">Workspace Root</span></td><td>Stores the repo root used by folder mode so you can move between codebases more cleanly.</td><td>Switching projects without re-linking your account or changing machine policy.</td><td><code>zerothink workspace C:\path\to\repo</code></td></tr>
</tbody>
</table>
</section>

<section id="files">
<h2><i class="fas fa-folder-tree"></i> Files, Uploads, And Zip Workflows</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-magnifying-glass"></i></div><div class="card-title">Read And Search</div><p>Read a file, search for symbols or TODOs, or scan a project with compact defaults that skip dependency, cache, vendor, and backup folders.</p><code class="cmd">zerothink fs scan .
zerothink fs read src/app.ts --start 1 --lines 120
zerothink fs search "TODO|FIXME|hack" .</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-pen-to-square"></i></div><div class="card-title">Write And Replace</div><p>Create files, append notes, or do basic replacements without leaving the terminal.</p><code class="cmd">zerothink fs write notes.txt --text "audit summary"
zerothink fs append notes.txt --text "next step"
zerothink fs replace config.yml "debug: true" "debug: false"</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-file-zipper"></i></div><div class="card-title">Attach Zips Instead Of Pasting</div><p>Package a repo slice into a zip and attach that to a prompt when the project is too big to paste manually.</p><code class="cmd">zerothink zip create review-pack.zip src package.json README.md
zerothink chat --file review-pack.zip "explain this codebase and find risks"</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-wand-magic-sparkles"></i></div><div class="card-title">Website Starter</div><p>Generate a responsive static website starter when you want Agent Zero to begin from a clean, polished base instead of a blank folder.</p><code class="cmd">zerothink site new launch-page --theme zero
zerothink site new portfolio --theme luxury --title "Shaf Portfolio"</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-gamepad"></i></div><div class="card-title">Playable Game Starter</div><p>Create a runnable HTML, CSS, and JavaScript browser game directly from the CLI, or just ask Agent Zero to make a small game in the interactive prompt.</p><code class="cmd">zerothink game new mini-runner --title "Mini Runner"
ZERO&gt; make a small game</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-file-pdf"></i></div><div class="card-title">PDF Reports</div><p>Create simple local PDF reports from text or an approved source file without installing extra packages or uploading the content.</p><code class="cmd">zerothink pdf create report.pdf --title "Server Notes" --text "Checked logs"
zerothink pdf create report.pdf --title "Deploy Notes" --file notes.md</code></div>
</div>
</section>

<section id="research">
<h2><i class="fas fa-file-lines"></i> Evidence-First Research Workbench</h2>
<p>Agent Zero can turn explicitly approved local folders or saved-server paths into a source inventory, evidence ledger, paper outline, draft, critique, or revision plan. It does not silently scan the entire computer, and it does not treat filenames or search snippets as verified academic evidence.</p>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-laptop-file"></i></div><div class="card-title">Local Papers + Notes</div><p>Supply files or folders with <code>--source</code>. The CLI reads supported text, code, CSV, Markdown, DOCX, and optional PDF text, while excluding common credentials, private keys, dependency folders, and oversized material by default.</p><code class="cmd">zerothink research scan --source ./papers
zerothink research paper "AI research agents" --source ./papers --source ./notes.md --stage outline</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-server"></i></div><div class="card-title">Saved Server Sources</div><p>Machine mode can use a saved SSH profile and SFTP to inspect research material on a server. Documentation uses neutral examples so private infrastructure is never published as a tutorial.</p><code class="cmd">zerothink server add research server.example.com --user researcher --port 22 --cwd /srv/research
zerothink research review "Reliability evidence" --server research --remote-path /srv/research/reports</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-list-check"></i></div><div class="card-title">Choose The Stage</div><p>Run only what is needed: <code>protocol</code>, <code>evidence</code>, <code>outline</code>, <code>draft</code>, <code>critique</code>, <code>revision</code>, or <code>full</code>. Use <code>--dry-run</code> to inspect the source plan without calling a model.</p><code class="cmd">zerothink research survey "Topic" --source ./evidence --stage evidence --citation-style "APA 7"
zerothink research proposal "Topic" --source ./notes --dry-run</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-scale-balanced"></i></div><div class="card-title">Integrity + Export</div><p>The research contract requires visible source status, verification gaps, contradiction handling, a claim-to-evidence matrix, and no invented authors, citations, quotations, DOIs, URLs, page numbers, statistics, or findings. Results are exported as private Markdown by default.</p><code class="cmd">zerothink help research
zerothink research article "Topic" --source ./sources --library --web --output article.md</code></div>
</div>
<div class="callout"><strong>PDF note:</strong> PDFs are inventoried automatically. Install the optional Python package <code>pypdf</code> when local PDF text extraction is required. Always compare important claims against the original document.</div>
</section>

<section id="crypto">
<h2><i class="fas fa-shield-halved"></i> Z-Pepper File Crypto</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-lock"></i></div><div class="card-title">Password-Protect Files Or Folders</div><p>Encrypt local files or folders without uploading the content to ZeroThink. A folder is automatically packed into an encrypted <code>.ztz</code> bundle. The CLI combines a passphrase, optional saved Z-Pepper pattern, PBKDF2-HMAC-SHA256, compression, and an authenticated envelope.</p><code class="cmd">zerothink crypto zpepper set
zerothink crypto encrypt secrets.txt
zerothink crypto encrypt ./private-folder
zerothink crypto lock secrets.txt</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-unlock-keyhole"></i></div><div class="card-title">Unlock Safely</div><p>Decrypt verifies the authentication tag before writing output. If the passphrase, Z-Pepper, or file bytes are wrong, Agent Zero refuses to create a bad plaintext file. Encrypted folder bundles are detected and unpacked automatically.</p><code class="cmd">zerothink crypto unlock secrets.txt.ztz
zerothink crypto unlock private-folder.ztz ./restored
/unlock secrets.txt.ztz</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-box-archive"></i></div><div class="card-title">Encrypted Bundles</div><p>Use <code>crypto encrypt ./folder</code> for the easiest path, or pack multiple paths into one encrypted <code>.ztz</code> bundle for private project handoff, backup, or moving code between machines.</p><code class="cmd">zerothink crypto pack project.ztz ./project ./notes.txt
zerothink crypto unpack project.ztz ./restored</code></div>
</div>
<div class="callout"><strong>Important:</strong> losing the file passphrase or the Z-Pepper pattern means the encrypted file cannot be recovered. For automation, prefer <code>--pass-env</code> over typing secrets directly into shell history.</div>
</section>

<section id="remote">
<h2><i class="fas fa-server"></i> Saved Server Profiles</h2>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-id-badge"></i></div><div class="card-title">Store A Target Once</div><p>Save a neutral profile name, hostname, SSH user, port, and working directory so you do not have to retype them. Keep real infrastructure details in the private local CLI configuration, not public documentation or screenshots.</p><code class="cmd">zerothink server add research server.example.com --user researcher --port 22 --cwd /srv/research
zerothink server add research server.example.com --user researcher --port 22 --ask-pass
zerothink server list</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-terminal"></i></div><div class="card-title">Run Remote Commands</div><p>Useful for status checks, logs, permissions, and quick repair work. Machine mode only.</p><code class="cmd">zerothink ssh prod "pwd && ls -la"
zerothink ssh prod "systemctl status nginx"</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-stethoscope"></i></div><div class="card-title">Check Server + Files</div><p>Run a compact saved-profile health check that logs into the server, shows identity, uptime, disk, memory, target directory listing, recent files, git status, and failed services.</p><code class="cmd">zerothink server check prod /var/www/html
zerothink remote doctor prod /var/www/html
ZERO&gt; check server prod files</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-arrow-right-arrow-left"></i></div><div class="card-title">Push And Pull Files</div><p>Move notes, datasets, build artifacts, or backups between your local machine and a saved target.</p><code class="cmd">zerothink scp put research notes.md /srv/research/notes.md
zerothink scp get research /srv/research/results.csv ./results.csv</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-file-pen"></i></div><div class="card-title">Edit Remote Files</div><p>The CLI can inspect and change text files on a saved server profile directly. Keep privileged configuration changes scoped, reviewed, and backed up.</p><code class="cmd">zerothink remote read research /srv/research/notes.md --lines 40
zerothink remote write research /srv/research/status.txt --text "reviewed"
zerothink remote replace research /srv/research/notes.md old new</code></div>
</div>
</section>

<section id="freewebpanel">
<h2><i class="fas fa-server"></i> FreeWebPanel Server Mode</h2>
<p>FreeWebPanel is now a first-class ZeroThink CLI command group. Operators can inspect a server, check services and disk space, find panel configuration and logs, review domain and SSL state, scan permissions, plan backups, and stage repair, update, install, or deployment work without memorising a loose collection of shell commands.</p>
<div class="grid">
<div class="card"><div class="card-icon"><i class="fas fa-stethoscope"></i></div><div class="card-title">Status + Doctor</div><p>Read-only checks report the detected FreeWebPanel root, operating system, disk capacity, web/PHP/database tools, service state, configuration paths, log paths, and actionable warnings.</p><code class="cmd">zerothink freewebpanel status
zerothink fwp health --json
zerothink fwp doctor --verbose</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-box-archive"></i></div><div class="card-title">Backup First</div><p>Preview the exact backup paths before an archive is written. Real archives require explicit approval, and database credentials are never printed or guessed.</p><code class="cmd">zerothink fwp backup --path /opt/freewebpanel --dry-run
zerothink fwp backup --path /opt/freewebpanel --include-config --yes</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-screwdriver-wrench"></i></div><div class="card-title">Safe Change Plans</div><p>Install, repair, update, and deploy actions show a numbered plan first. Ubuntu is the primary lane; AlmaLinux/Rocky/RHEL-family servers and OpenZero/ZeroMint AIOS servers have their own supported lanes. Existing-panel takeover is treated as a migration workflow: verify off-server backups, inspect the preflight, then approve deliberately.</p><code class="cmd">zerothink fwp install --server-mode ubuntu --hostname panel.example.com --email admin@example.com --dry-run
zerothink fwp install --server-mode rhel-family --hostname panel.example.com --email admin@example.com --preflight-only
zerothink fwp install --server-mode openzero-aios --hostname web.example.com --email admin@example.com --dry-run
zerothink fwp repair --backup-first --dry-run
zerothink fwp update --backup-first --dry-run</code></div>
<div class="card"><div class="card-icon"><i class="fas fa-magnifying-glass"></i></div><div class="card-title">Focused Diagnostics</div><p>Use the smaller commands when you need domain, Apache/nginx, PHP-FPM, SSL, permissions, configuration, or redacted log evidence without a full report.</p><code class="cmd">zerothink fwp domains --domain example.com
zerothink fwp php
zerothink fwp ssl --domain example.com
zerothink fwp logs --lines 100 --redact</code></div>
</div>
<div class="callout"><strong>Alias:</strong> <code>zerothink fwp ...</code> is identical to <code>zerothink freewebpanel ...</code>. Inspection is read-only, JSON output is available for automation, and common password/token values are redacted from previews by default.</div>
</section>

<section id="models">
<h2><i class="fas fa-network-wired"></i> Model Routing</h2>
<table class="matrix-table">
<thead><tr><th>Provider</th><th>Default</th><th>Notable Models</th><th>Best Use</th></tr></thead>
<tbody>
<tr><td><strong style="color:#f55;">Groq</strong></td><td><code>openai/gpt-oss-120b</code></td><td>GPT-OSS 120B, Llama 3.3 70B, Kimi K2, Compound, GPT-OSS 20B, Qwen 3 32B</td><td>Fastest practical coding lane, strong reasoning, and the default CLI coding route.</td></tr>
<tr><td><strong style="color:#76b900;">NVIDIA</strong></td><td><code>qwen/qwen3-coder-480b-a35b-instruct</code></td><td>Qwen 480B Coder, GPT-OSS 120B, Qwen 2.5 Coder 32B, Gemma 4 31B, Codestral, Llama 3.3 70B</td><td>Heavy coding, architecture work, bigger code generation, and deeper technical drafting.</td></tr>
<tr><td><strong style="color:#4285F4;">Gemini</strong></td><td><code>gemini-3.5-flash</code></td><td>Gemini 3.5 Flash, Gemini 3.1 Pro, Gemini 3 Flash, Gemini 3.1 Flash-Lite</td><td>Long-context analysis, large documents, multimodal research, and fast paper workflows.</td></tr>
<tr><td><strong style="color:#10a37f;">OpenAI</strong></td><td><code>gpt-5.5</code></td><td>GPT-5.5, GPT-5.4, GPT-5.4 Mini, GPT-5.4 Nano, GPT-5 Pro</td><td>Premium reasoning, writing, coding, and research through the same linked ZeroThink account.</td></tr>
<tr><td><strong style="color:#fff;">xAI</strong></td><td><code>grok-4.3</code></td><td>Grok 4.3, Grok Build 0.1, Grok 4 Fast, Grok Code Fast 1</td><td>Premium Grok reasoning and coding when that lane suits the task better.</td></tr>
</tbody>
</table>
<div class="callout"><strong>NVIDIA note:</strong> if you want models like <code>qwen/qwen3-coder-480b-a35b-instruct</code>, you can either save your NVIDIA key in the ZeroThink Vault through linked-account mode or use no-Google direct mode with <code>zerothink key set nvidia</code>.</div>
</section>

<section id="commands">
<h2><i class="fas fa-list"></i> Core Commands</h2>
<div class="grid">
<div class="card"><div class="card-title">Identity + Startup</div><code class="cmd">zerothink --help
zerothink tutorial
zerothink help quickstart
zerothink help browser
zerothink login
zerothink status
zerothink key status
zerothink key set groq
zerothink me
zerothink doctor</code></div>
<div class="card"><div class="card-title">Access + Workspace</div><code class="cmd">zerothink access status
zerothink access folder .
zerothink access machine
zerothink workspace .
zerothink workspace C:\code\project</code></div>
<div class="card"><div class="card-title">Files + Search</div><code class="cmd">zerothink fs ls .
zerothink fs tree . --depth 2
zerothink fs scan .
zerothink fs read src/app.ts --start 1 --lines 80
zerothink fs search "auth|token|session" .</code></div>
<div class="card"><div class="card-title">Write + Archive</div><code class="cmd">zerothink fs write notes.txt --text "hello"
zerothink fs append notes.txt --text "more"
zerothink zip create release.zip src public
zerothink zip extract release.zip ./unpacked
zerothink site new launch-page --theme cyber
zerothink game new mini-runner --title "Mini Runner"
zerothink pdf create report.pdf --title "Report" --text "hello"</code></div>
<div class="card"><div class="card-title">Z-Pepper Crypto</div><code class="cmd">zerothink help crypto
zerothink crypto status
zerothink crypto zpepper set
zerothink crypto encrypt secrets.txt
zerothink crypto encrypt ./private-folder
zerothink crypto lock secrets.txt
zerothink crypto unlock private-folder.ztz</code></div>
<div class="card"><div class="card-title">Autopilot + Agents</div><code class="cmd">zerothink help agents
zerothink autopilot "review this repo and summarize the top fixes"
zerothink agent create zero1 "map the auth flow"
zerothink agent run zero1 --steps 8
zerothink agent log zero1</code></div>
<div class="card"><div class="card-title">Local Session Memory</div><code class="cmd">zerothink session list
zerothink session show repo-audit
zerothink session export repo-audit
/checkpoint repo-audit
/resume repo-audit</code></div>
<div class="card"><div class="card-title">URL + Custom</div><code class="cmd">zerothink fetch https://example.com
zerothink search "php-fpm tuning"
zerothink browse https://nginx.org/en/docs/
zerothink playwright open https://example.com
zerothink custom show
/attach-url https://example.com
/custom show</code></div>
<div class="card"><div class="card-title">Research Workbench</div><code class="cmd">zerothink help research
zerothink research scan --source ./papers
zerothink research paper "Topic" --source ./papers --stage outline
zerothink research survey "Topic" --source ./evidence --citation-style "APA 7"
zerothink research review "Topic" --server research --remote-path /srv/research</code></div>
<div class="card"><div class="card-title">Remote</div><code class="cmd">zerothink server add research server.example.com --user researcher --port 22 --ask-pass
zerothink server list
zerothink server check research /srv/research
zerothink remote doctor research /srv/research
zerothink ssh research "uptime"
zerothink remote read research /srv/research/notes.md
zerothink scp get research /srv/research/results.csv ./results.csv</code></div>
<div class="card"><div class="card-title">FreeWebPanel</div><code class="cmd">zerothink freewebpanel help
zerothink fwp status
zerothink fwp doctor --verbose
zerothink fwp logs --lines 80
zerothink fwp backup --path /opt/freewebpanel --dry-run
zerothink fwp update --backup-first --dry-run</code></div>
<div class="card"><div class="card-title">Inside The REPL</div><code class="cmd">/guide
/help commands
/he&lt;TAB&gt;
/search-web gemma ollama docs
/page 1
/attach-page 1
/playwright open https://example.com
/context show
/checkpoint repo-audit
/provider nvidia
/model qwen/qwen3-coder-480b-a35b-instruct
/attach review-pack.zip</code></div>
</div>
</section>

<section id="troubleshoot">
<h2><i class="fas fa-wrench"></i> Beginner Troubleshooting</h2>
<div class="faq-grid">
<div class="card"><div class="card-title">Command Not Found</div><p>Open a new terminal window first. On Linux or macOS make sure <code>~/.local/bin</code> is on your <code>PATH</code>. On Windows, reopen PowerShell after install. If interactive completion feels missing, run <code>zerothink update</code> or reinstall so <code>prompt_toolkit</code> gets installed too.</p></div>
<div class="card"><div class="card-title">Not Logged In</div><p>Run <code>zerothink login</code>. If you are unsure whether the machine is linked, run <code>zerothink me</code>. Slash commands like <code>/help</code> only work <strong>inside</strong> the interactive <code>ZERO&gt;</code> prompt, not in plain PowerShell.</p></div>
<div class="card"><div class="card-title">Provider Feels Missing</div><p>Make sure the relevant provider key exists in the right place. Linked-account mode uses the ZeroThink Vault. Direct mode uses <code>zerothink key status</code> and only supports Groq or NVIDIA local keys. If the linked Groq or NVIDIA lane fails but you already have a local key saved, the CLI now falls back automatically for those providers.</p></div>
<div class="card"><div class="card-title">Too Much Access</div><p>Switch back to repo-safe mode with <code>zerothink access folder .</code> and reopen the CLI inside the project you actually want to work on. If you want project-specific guidance without pasting it each time, add a repo guidance file such as <code>ZEROTHINK.md</code>, <code>CLAUDE.md</code>, or <code>AGENTS.md</code> and inspect it with <code>zerothink context show</code>.</p></div>
<div class="card"><div class="card-title">Agent Seems Stuck</div><p>Check the saved worker state with <code>zerothink agent show zero1</code> and <code>zerothink agent log zero1</code>. If the task needs more room, rerun it with a higher step limit using <code>zerothink agent run zero1 --steps 12</code> or start a fresh controller with <code>zerothink autopilot "..."</code>.</p></div>
</div>
</section>

<div class="foot">ZERO THINK CLI aka AGENT ZERO // v3.7.0 // RESEARCH WORKBENCH + FREEWEBPANEL SERVER MODE<br>&copy; 2026 Shaf Brady / TalkToAi</div>
</div>
</div>
</body>
</html>
