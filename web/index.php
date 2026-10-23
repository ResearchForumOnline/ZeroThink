<?php
// Production errors are logged server-side, never rendered to visitors.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// LOAD SYSTEM
require_once __DIR__ . '/app/bootstrap.php';

$return_to = sanitize_local_return_path($_GET['return'] ?? '', '/studio');

// REDIRECT IF LOGGED IN
if(isset($_SESSION['user_id'])) {
    header('Location: ' . $return_to);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeroThink | Hybrid Real Quantum AI & Sovereign Intelligence</title>
    
    <meta property="og:title" content="ZeroThink | Hybrid Real Quantum AI & Sovereign Intelligence">
    <meta property="og:description" content="Join ZeroThink: Groq GPT-OSS 120B reasoning, QuantumZero hybrid quantum-informed analysis with IonQ Quantum Cloud telemetry/context, research workflows, coding models, $AI utility access, and the ZeroThink FAQ.">
    <meta property="og:image" content="https://zerothink.talktoai.org/assets/img/zerothinkmain.png">
    <meta property="og:url" content="https://zerothink.talktoai.org">
    <meta property="og:type" content="website">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="ZeroThink | Hybrid Real Quantum AI">
    <meta name="twitter:description" content="Sovereign AI with Groq reasoning, IonQ-connected quantum-informed QuantumZero, coding models, and university research workflows.">
    <meta name="twitter:image" content="https://zerothink.talktoai.org/assets/img/zerothinkmain.png">

    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CORE LAYOUT */
        *, *::before, *::after { box-sizing:border-box; }
        html, body { overflow-x:hidden; }
        body { background:#000; color:#fff; margin:0; font-family:'Inter', sans-serif; display:flex; flex-direction:column; min-height:100vh; }
        
        /* BACKGROUND (UNTOUCHED) */
        .bg-img { position:fixed; top:0; left:0; width:100%; height:100%; background:url('/assets/img/zerothinkmain.png') no-repeat center center; background-size: cover; opacity:0.4; z-index:-1; filter: contrast(1.1) brightness(0.8); }

        /* TOP NAVIGATION */
        .top-nav { position: absolute; top: 0; right: 0; padding: 30px; z-index: 10; display: flex; gap: 14px; flex-wrap: wrap; justify-content: flex-end; }
        .nav-link { color: #ccc; text-decoration: none; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px; font-weight: bold; padding: 10px 20px; border: 1px solid transparent; border-radius: 4px; transition: 0.3s; background: rgba(0,0,0,0.3); backdrop-filter: blur(5px); }
        .nav-link:hover { border-color: #a855f7; color: #fff; background: rgba(168,85,247,0.1); box-shadow: 0 0 15px rgba(168,85,247,0.2); }

        /* HERO SECTION */
        .hero { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px; z-index:2; }
        
        /* LOGO & TEXT */
        .logo-glow { width:100px; margin-bottom:20px; filter: drop-shadow(0 0 20px rgba(168,85,247,0.4)); animation: float 6s ease-in-out infinite; }
        
        /* NEW BRANDING: SWARM AI ZEROTHINK */
        h1 { margin:0; letter-spacing:-2px; text-shadow:0 0 60px rgba(168, 85, 247, 0.6); font-weight: 800; line-height: 1; }
        .swarm-text { display: block; font-size: 2rem; color: #00ffff; letter-spacing: 5px; text-transform: uppercase; margin-bottom: 5px; opacity: 0.9; }
        .zero-text { font-size: 5rem; letter-spacing: -4px; }
        
        .tagline { color:#ccc; font-size:1.2rem; margin-top:15px; text-transform:uppercase; letter-spacing:5px; font-weight:300; }
        .quantum-line { margin-top:12px; color:#9dffe1; letter-spacing:2px; text-transform:uppercase; font-size:0.78rem; text-shadow:0 0 20px rgba(0,255,157,0.25); }
        .status-pill { margin-top: 10px; font-size: 0.6rem; color: #00ff00; background: rgba(0, 255, 0, 0.1); padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(0,255,0,0.2); letter-spacing: 1px; }

        /* AUTH BOX */
        .auth-box { background:rgba(0,0,0,0.85); border:1px solid #333; padding:40px; border-radius:20px; backdrop-filter:blur(20px); margin-top:50px; width:360px; box-shadow: 0 20px 50px rgba(0,0,0,0.9); position: relative; overflow: hidden; }
        .auth-box::before { content:''; position:absolute; top:0; left:0; width:100%; height:4px; background: linear-gradient(90deg, #a855f7, #00ffff); }
        
        .box-title { font-size: 0.8rem; color: #666; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 25px; display: block; font-weight: bold; }
        
        .btn-google { display:flex; align-items:center; justify-content:center; gap:10px; background:#fff; color:#000; padding:15px; border-radius:50px; text-decoration:none; font-weight:800; transition:0.3s; margin-bottom:10px; font-size: 0.9rem; }
        .btn-google:hover { transform:translateY(-2px); box-shadow:0 0 25px rgba(255,255,255,0.4); background: #f0f0f0; }
        
        /* FOOTER & SHARE */
        .footer { padding:40px; text-align:center; font-size:0.75rem; color:#555; background: linear-gradient(to top, #000 20%, transparent); letter-spacing: 1px; z-index: 2; }
        .footer a { color:#777; text-decoration:none; margin:0 10px; text-transform:uppercase; transition:0.2s; font-weight: 600; }
        .footer a:hover { color:#a855f7; }
        
        .share-row { display: flex; justify-content: center; gap: 15px; margin-top: 20px; margin-bottom: 20px; }
        .share-btn { width: 35px; height: 35px; border-radius: 50%; background: #111; border: 1px solid #333; color: #888; display: flex; align-items: center; justify-content: center; transition: 0.2s; text-decoration: none !important; }
        .share-btn:hover { color: #fff; border-color: #a855f7; transform: scale(1.1); box-shadow: 0 0 10px rgba(168,85,247,0.4); }
        .nottingham-mark { display:flex; flex-direction:column; align-items:center; gap:8px; margin:26px auto 18px; }
        .nottingham-logo-wrap { display:inline-flex; align-items:center; justify-content:center; width:min(210px, 78vw); padding:8px 12px; border-radius:8px; background:rgba(255,255,255,0.94); border:1px solid rgba(255,255,255,0.18); box-shadow:0 10px 30px rgba(0,0,0,0.32); transition:.2s; }
        .nottingham-logo-wrap:hover { transform:translateY(-1px); box-shadow:0 0 18px rgba(0,255,255,0.18); }
        .nottingham-logo-wrap img { display:block; width:100%; height:auto; }
        .nottingham-caption { color:#777; font-size:.62rem; letter-spacing:1.4px; text-transform:uppercase; }
        .research-strip { width:min(1080px, calc(100% - 32px)); margin:30px auto 0; display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:14px; z-index:3; }
        .research-card { text-align:left; text-decoration:none; color:#dfe7ec; background:rgba(0,0,0,0.72); border:1px solid rgba(255,255,255,0.12); border-radius:8px; padding:18px; backdrop-filter:blur(12px); transition:.25s; }
        .research-card:hover { border-color:#00ffff; transform:translateY(-2px); box-shadow:0 0 18px rgba(0,255,255,.14); }
        .research-card strong { display:block; color:#fff; margin-bottom:6px; font-size:.94rem; }
        .research-card span { display:block; color:#9aa6ad; font-size:.78rem; line-height:1.45; }

        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }
        
        /* MOBILE ADJUST */
        @media (max-width: 600px) {
            .zero-text { font-size: 3.5rem; }
            .swarm-text { font-size: 1.5rem; }
            .top-nav { left:0; right:0; justify-content: center; width: auto; max-width:100vw; background: rgba(0,0,0,0.8); padding: 15px; position: fixed; }
            .nav-link { font-size: 0.68rem; padding: 8px 10px; }
            .auth-box { width: min(360px, calc(100vw - 36px)); }
        }
    </style>
</head>
<body>
    <div class="bg-img"></div>

    <div class="top-nav">
        <a href="/quantumzero" class="nav-link"><i class="fas fa-atom"></i> QuantumZero</a>
        <a href="/zmath" class="nav-link"><i class="fas fa-square-root-variable"></i> ZMath</a>
        <a href="https://talktoai.org/dna" class="nav-link"><i class="fas fa-dna"></i> DNA Lab</a>
        <a href="/nottingham-research" class="nav-link"><i class="fas fa-university"></i> Nottingham</a>
        <a href="https://research.talktoai.org" class="nav-link"><i class="fas fa-flask"></i> Research</a>
        <a href="/research-paper-creator" class="nav-link"><i class="fas fa-file-lines"></i> Paper Creator</a>
        <a href="/cli" class="nav-link"><i class="fas fa-terminal"></i> CLI</a>
        <a href="/faq" class="nav-link"><i class="fas fa-book"></i> FAQ</a>
        <a href="/terms" class="nav-link"><i class="fas fa-scale-balanced"></i> Terms</a>
        <a href="/privacy" class="nav-link"><i class="fas fa-shield-halved"></i> Privacy</a>
    </div>

    <div class="hero">
        <img src="/assets/img/logo_nobg.png" class="logo-glow">
        <div class="status-pill">● HYBRID REAL QUANTUM AI ONLINE</div>

        <h1>
            <span class="swarm-text">Swarm AI</span>
            <span class="zero-text">Zero<span style="color:#a855f7">Think</span></span>
        </h1>

        <div class="tagline">Sovereign Intelligence • Hybrid Real Quantum AI</div>
        <div class="quantum-line">
            Groq GPT-OSS 120B • IonQ Quantum Cloud Telemetry • DNA Evidence Workflows • University Research Tools
        </div>

        <div class="auth-box">
            <span class="box-title">Private Neural Access</span>
            <a href="/auth/google?return=<?= htmlspecialchars(rawurlencode($return_to), ENT_QUOTES, 'UTF-8') ?>" class="btn-google"><i class="fab fa-google"></i> Continue with Google</a>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(120px, 1fr)); gap:10px; margin-top:12px;">
                <a href="/quantumzero" style="text-decoration:none; text-align:center; padding:12px; border-radius:14px; border:1px solid #174d3a; color:#c8ffe8; background:rgba(0,255,157,0.06); font-size:0.8rem; font-weight:700;">QuantumZero</a>
                <a href="/quantumlab" style="text-decoration:none; text-align:center; padding:12px; border-radius:14px; border:1px solid #2c6b57; color:#d9fff4; background:rgba(104,247,255,0.06); font-size:0.8rem; font-weight:700;">Quantum Lab</a>
                <a href="https://talktoai.org/dna" style="text-decoration:none; text-align:center; padding:12px; border-radius:14px; border:1px solid #2c6b57; color:#d9fff4; background:rgba(0,255,145,0.06); font-size:0.8rem; font-weight:700;">DNA Lab</a>
                <a href="/research-paper-creator" style="text-decoration:none; text-align:center; padding:12px; border-radius:14px; border:1px solid #2b3140; color:#d5deff; background:rgba(255,255,255,0.04); font-size:0.8rem; font-weight:700;">Paper Creator</a>
                <a href="/guest" style="text-decoration:none; text-align:center; padding:12px; border-radius:14px; border:1px solid #2b3140; color:#d5deff; background:rgba(255,255,255,0.04); font-size:0.8rem; font-weight:700;">Direct Keys</a>
            </div>
            <div style="margin-top:14px; font-size:0.76rem; color:#8f98b8; line-height:1.6;">
                QuantumZero is the separate Pro research lane for IonQ-connected hybrid quantum-informed AI.
                <br><span style="color:#7ef7d3;">Clear definition:</span> classical LLM reasoning, plus real quantum-cloud telemetry/context in the loop. Not qubits replacing GPUs.
            </div>
            <div style="font-size:0.7rem; color:#444; margin-top:15px;">
                By entering or using any ZeroThink feature, you agree to the <a href="/terms" style="color:#888;">Terms</a>, <a href="/privacy" style="color:#888;">Privacy Policy</a>, in-house board rules, AI-output limits, and IP-protection rules.
            </div>
        </div>

        <div class="research-strip">
            <a class="research-card" href="/nottingham-research">
                <strong>Literature Research Workspace</strong>
                <span>Survey extension, topic-expert identification, generative UI, provenance, and human validation.</span>
            </a>
            <a class="research-card" href="/research-paper-creator">
                <strong>Research Paper Creator</strong>
                <span>Evidence-first drafting with source ledgers, claim maps, critique passes, and approvals.</span>
            </a>
            <a class="research-card" href="/docs/serper-search">
                <strong>Serper Search + Provenance</strong>
                <span>Web search through Serper with title, URL, snippet, source, timestamp, and evidence ledger storage.</span>
            </a>
            <a class="research-card" href="/quantumzero">
                <strong>QuantumZero Research Agent</strong>
                <span>Quantum-informed Groq + IonQ workflow with evidence boundaries, job telemetry, and audit-safe interpretation.</span>
            </a>
            <a class="research-card" href="/zmath">
                <strong>ZMath Agent</strong>
                <span>Zero Boundary Algebra tools for reset logic, file analysis, mirror checks, and ZME1 encrypted files.</span>
            </a>
            <a class="research-card" href="https://talktoai.org/dna">
                <strong>DNA Lab</strong>
                <span>Raw DNA uploads, ZDNA-MAP quality checks, health research cards, heritage signals, lineage clues, ancient matches, and PDF reports.</span>
            </a>
            <a class="research-card" href="/guest">
                <strong>Direct Mode</strong>
                <span>No Google login: use local browser-stored Groq or NVIDIA keys with direct model selection.</span>
            </a>
        </div>
    </div>

    <div class="footer">
        <a href="https://research.talktoai.org" target="_blank">Research Hub</a> <span>•</span>
        <a href="http://Shafaet.com" target="_blank">Shafaet.com</a> <span>•</span>
        <a href="/terms">Terms</a> <span>•</span>
        <a href="/privacy">Privacy</a> <span>•</span>
        <a href="https://zerothink.talktoai.org/token">$AI Utility</a>

        <div style="margin-top: 15px; font-size: 0.7rem; color: #888;">
            Support the Research (SOL): <span style="color:#00ff41; user-select:all;">5dZeB2SdAyBuwnexMCcSQeCqKUpbCotcqyDB13UMAnqN</span><br>
            $AI Token CA: <span style="color:#00ffff; user-select:all;">dHdkhZsmBQhXjQoKbkyajZ3Ff4W2XumGyVJX4jypump</span>
        </div>

        <div class="share-row">
            <a href="https://x.com/intent/tweet?text=Join%20the%20Sovereign%20Swarm.%20Intelligence%20without%20limits.%20%23ZeroThink%20%23AI&url=https://zerothink.talktoai.org" target="_blank" class="share-btn" title="Share to X">
                <span style="font-family:sans-serif; font-weight:900; font-size:1.2rem;">X</span>
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=https://zerothink.talktoai.org" target="_blank" class="share-btn" title="Share to Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=https://zerothink.talktoai.org" target="_blank" class="share-btn" title="Share to LinkedIn">
                <i class="fab fa-linkedin-in"></i>
            </a>
        </div>

        <div class="nottingham-mark">
            <a class="nottingham-logo-wrap" href="/nottingham-research" aria-label="Open Nottingham research workflow">
                <span style="color:#07241e;font-weight:700">Nottingham research workflow</span>
            </a>
            <span class="nottingham-caption">Research workflow focus</span>
        </div>

        <span style="opacity:0.5; font-size:0.6rem;">&copy; 2026 TalkToAi / Shaf Brady. Sovereign Code.</span>
    </div>
</body>
</html>
