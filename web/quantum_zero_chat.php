<?php
require_once __DIR__ . '/app/bootstrap.php';

$user = function_exists('current_user_record') ? current_user_record() : null;
$is_architect = function_exists('is_architect_user') ? is_architect_user($user) : ((int) ($_SESSION['user_id'] ?? 0) === 1);
$is_pro = $is_architect
    || !empty($user['is_pro'])
    || in_array(strtolower((string) ($user['plan'] ?? '')), ['pro', 'god_mode'], true)
    || strtolower((string) ($user['subscription_status'] ?? '')) === 'active';
$qz_csrf_token = function_exists('zt_csrf_token') ? zt_csrf_token() : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QuantumZero Chat | ZeroThink</title>
<meta name="description" content="QuantumZero is the ZeroThink Pro quantum-informed chat agent: Groq GPT-OSS 120B reasoning with real IonQ Quantum Cloud telemetry and optional job evidence.">
<link rel="canonical" href="https://zerothink.talktoai.org/quantumzero">
<link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
<link rel="preload" as="image" href="/assets/img/quantumzero-og.png">
<meta name="theme-color" content="#020403">
<meta property="og:type" content="website">
<meta property="og:site_name" content="ZeroThink">
<meta property="og:title" content="QuantumZero - Real Quantum AI Chat">
<meta property="og:description" content="Groq-speed AI reasoning with real IonQ quantum-cloud telemetry and optional job evidence in the loop. Classical LLM, quantum-informed research mode.">
<meta property="og:url" content="https://zerothink.talktoai.org/quantumzero">
<meta property="og:image" content="https://zerothink.talktoai.org/assets/img/quantumzero-og.png">
<meta property="og:image:secure_url" content="https://zerothink.talktoai.org/assets/img/quantumzero-og.png">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1919">
<meta property="og:image:height" content="998">
<meta property="og:image:alt" content="QuantumZero Real Quantum AI chat preview with green quantum interface">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="QuantumZero - Real Quantum AI Chat">
<meta name="twitter:description" content="Groq reasoning plus real IonQ quantum-cloud telemetry and optional proof jobs.">
<meta name="twitter:image" content="https://zerothink.talktoai.org/assets/img/quantumzero-og.png">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "QuantumZero",
  "applicationCategory": "AIApplication",
  "operatingSystem": "Web",
  "url": "https://zerothink.talktoai.org/quantumzero",
  "image": "https://zerothink.talktoai.org/assets/img/quantumzero-og.png",
  "description": "ZeroThink Pro quantum-informed AI chat with Groq reasoning and real IonQ quantum-cloud telemetry or optional job evidence in the loop.",
  "offers": {
    "@type": "Offer",
    "availability": "https://schema.org/OnlineOnly",
    "url": "https://zerothink.talktoai.org/upgrade"
  }
}
</script>
<style>
:root{
  --bg:#020403;
  --panel:rgba(3,13,10,.78);
  --line:rgba(108,255,202,.2);
  --text:#eafff6;
  --muted:#91aaa1;
  --green:#00ff91;
  --cyan:#68f7ff;
  --gold:#ffd36f;
  --red:#ff6d7b;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0;
  min-height:100vh;
  color:var(--text);
  background:
    radial-gradient(circle at 12% 0%,rgba(0,255,145,.19),transparent 34rem),
    radial-gradient(circle at 88% 8%,rgba(104,247,255,.16),transparent 28rem),
    linear-gradient(180deg,#010302,#06110f 58%,#020504);
  font-family:ui-sans-serif,Inter,Segoe UI,Roboto,Arial,sans-serif;
  overflow-x:hidden;
}
body:before{
  content:"";
  position:fixed;
  inset:0;
  pointer-events:none;
  background-image:linear-gradient(rgba(104,247,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(104,247,255,.04) 1px,transparent 1px);
  background-size:42px 42px;
  mask-image:radial-gradient(circle at center,#000 0%,transparent 82%);
}
button,input,textarea,select{font:inherit}
a{color:inherit}
#qz-field{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.7}
.shell{position:relative;z-index:2;width:min(1320px,calc(100% - 28px));margin:0 auto;padding:22px 0 70px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:42px}
.brand{display:flex;align-items:center;gap:13px;text-decoration:none}
.brand img{width:46px;filter:drop-shadow(0 0 18px rgba(0,255,145,.42))}
.brand strong{display:block;letter-spacing:.08em;text-transform:uppercase}
.brand span{display:block;color:var(--muted);font-size:.72rem;letter-spacing:.18em;text-transform:uppercase}
.nav{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  border:1px solid var(--line);
  background:rgba(255,255,255,.045);
  color:var(--text);
  text-decoration:none;
  border-radius:999px;
  padding:12px 15px;
  font-weight:900;
  font-size:.82rem;
  cursor:pointer;
}
.btn.primary{border:0;color:#02100b;background:linear-gradient(90deg,var(--green),var(--cyan));box-shadow:0 0 34px rgba(0,255,145,.2)}
.btn.gold{border-color:rgba(255,211,111,.3);color:#fff0bc;background:rgba(255,211,111,.08)}
.btn.red{border-color:rgba(255,109,123,.34);color:#ffd8dc;background:rgba(255,109,123,.08)}
.hero{text-align:center;margin:0 auto 34px}
.eyebrow{color:var(--green);letter-spacing:.2em;text-transform:uppercase;font-weight:1000;font-size:.76rem}
h1{font-size:clamp(3.4rem,10vw,8.4rem);line-height:.86;letter-spacing:-.1em;text-transform:uppercase;margin:12px 0}
.qword{display:inline-block;background:linear-gradient(90deg,#fff,var(--green),var(--cyan),#fff);background-size:220% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:glowText 7s linear infinite}
@keyframes glowText{to{background-position:220% 0}}
.lead{max-width:870px;margin:0 auto;color:#bdd3cb;font-size:1.08rem;line-height:1.75}
.truth{max-width:820px;margin:18px auto 0;color:#a7fff0;font-size:.92rem;line-height:1.65}
.chat-stage{display:grid;grid-template-columns:minmax(280px,380px) minmax(0,1fr);gap:20px;align-items:stretch}
.panel{border:1px solid var(--line);background:linear-gradient(180deg,rgba(4,16,13,.82),rgba(2,7,6,.74));box-shadow:0 28px 90px rgba(0,0,0,.42);border-radius:30px;backdrop-filter:blur(16px)}
.presence{padding:20px;overflow:hidden}
.agent-stage{position:relative;height:410px;display:grid;place-items:end center;isolation:isolate}
.agent-stage:before{content:"";position:absolute;inset:auto 8% 0 8%;height:34%;border-radius:50%;background:radial-gradient(ellipse at center,rgba(0,255,145,.2),transparent 66%);filter:blur(2px);transform:perspective(400px) rotateX(64deg)}
.agent-avatar{position:relative;z-index:3;width:min(300px,78vw);max-height:390px;object-fit:contain;filter:drop-shadow(0 0 30px rgba(104,247,255,.42)) drop-shadow(0 0 58px rgba(0,255,145,.16));transform-origin:50% 95%;animation:avatarFloat 5.6s ease-in-out infinite}
.agent-video{border-radius:32px;object-fit:contain;background:#000;border:1px solid rgba(104,247,255,.22);box-shadow:0 0 0 1px rgba(0,255,145,.08) inset,0 18px 90px rgba(0,0,0,.44)}
.video-glass{position:absolute;z-index:5;inset:16px 16px 34px 16px;pointer-events:none;border-radius:34px;background:linear-gradient(120deg,rgba(255,255,255,.12),transparent 30%,transparent 68%,rgba(0,255,145,.08));mix-blend-mode:screen}
.video-glass:after{content:"";position:absolute;inset:0;border-radius:34px;background:repeating-linear-gradient(0deg,transparent 0,transparent 6px,rgba(104,247,255,.08) 7px);opacity:.42}
.avatar-halo,.avatar-voice,.avatar-face{position:absolute;pointer-events:none;border-radius:50%}
.avatar-halo{z-index:1;width:250px;height:250px;bottom:38px;border:1px solid rgba(104,247,255,.28);box-shadow:0 0 44px rgba(0,255,145,.16),inset 0 0 36px rgba(104,247,255,.08);animation:spin 13s linear infinite}
.avatar-halo:after{content:"";position:absolute;inset:16%;border-radius:50%;border:1px dashed rgba(0,255,145,.32)}
.avatar-voice{z-index:2;width:92px;height:92px;top:76px;left:50%;transform:translateX(-50%);background:radial-gradient(circle,rgba(104,247,255,.24),rgba(0,255,145,.06) 55%,transparent 72%);opacity:.32}
.avatar-face{z-index:4;width:52px;height:76px;top:72px;left:50%;transform:translateX(-50%) rotate(6deg);border:1px solid rgba(104,247,255,.2);background:linear-gradient(180deg,rgba(104,247,255,.2),rgba(0,255,145,.05));opacity:.26;filter:blur(1px)}
.core-card{display:flex;align-items:center;gap:12px;margin-top:14px;padding:13px;border:1px solid rgba(104,247,255,.16);border-radius:22px;background:rgba(0,0,0,.25)}
.orb{width:74px;min-width:74px;aspect-ratio:1;border-radius:50%;border:1px solid rgba(104,247,255,.35);box-shadow:0 0 38px rgba(0,255,145,.24),inset 0 0 30px rgba(104,247,255,.08);background:radial-gradient(circle at 35% 30%,rgba(255,255,255,.78),transparent 8%),radial-gradient(circle at 50% 55%,rgba(0,255,145,.28),transparent 34%),radial-gradient(circle at 65% 36%,rgba(104,247,255,.2),transparent 24%),#020605;animation:pulseOrb 4.2s ease-in-out infinite}
.orb:before,.orb:after{content:"";position:absolute}
.core-card strong{display:block;text-transform:uppercase;letter-spacing:.11em;font-size:.82rem}
.core-card span{display:block;color:var(--muted);font-size:.79rem;line-height:1.45;margin-top:4px}
.voice-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:12px}
.voice-row .btn{font-size:.76rem;padding:10px}
.status-grid{display:grid;gap:8px;margin-top:12px}
.status-pill{display:flex;justify-content:space-between;gap:10px;border:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.28);border-radius:16px;padding:10px 12px;color:#c5ddd4;font-size:.78rem}
.status-pill strong{color:#fff;text-align:right}
.signal-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:12px}
.signal-mini{min-height:58px;border:1px solid rgba(104,247,255,.16);background:linear-gradient(180deg,rgba(104,247,255,.055),rgba(0,0,0,.18));border-radius:16px;padding:10px}
.signal-mini span{display:block;color:#83aaa0;font-size:.62rem;font-weight:1000;letter-spacing:.12em;text-transform:uppercase}
.signal-mini strong{display:block;margin-top:5px;color:#fff;font-size:.82rem}
.chat{display:flex;flex-direction:column;min-height:660px}
.messages{flex:1;padding:22px;display:flex;flex-direction:column;gap:15px;overflow:auto;max-height:66vh}
.msg{max-width:900px;border:1px solid rgba(255,255,255,.08);border-radius:24px;padding:16px 18px;line-height:1.72;white-space:pre-wrap}
.msg.zero{align-self:flex-start;background:rgba(0,255,145,.055);border-color:rgba(0,255,145,.18)}
.msg.user{align-self:flex-end;background:rgba(104,247,255,.075);border-color:rgba(104,247,255,.2)}
.msg strong{display:block;color:#fff;margin-bottom:6px;text-transform:uppercase;letter-spacing:.1em;font-size:.72rem}
.composer{border-top:1px solid rgba(108,255,202,.14);padding:18px}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.chip{border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:#dffcf4;border-radius:999px;padding:9px 11px;font-weight:850;font-size:.78rem;cursor:pointer}
.mission-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px}
.mission-card{position:relative;overflow:hidden;text-align:left;border:1px solid rgba(108,255,202,.16);background:linear-gradient(155deg,rgba(0,255,145,.09),rgba(104,247,255,.035));color:#eafff6;border-radius:20px;padding:13px;min-height:92px;cursor:pointer}
.mission-card:before{content:"";position:absolute;inset:0;background:linear-gradient(110deg,transparent,rgba(104,247,255,.12),transparent);transform:translateX(-110%);transition:transform .6s ease}
.mission-card:hover:before{transform:translateX(110%)}
.mission-card strong,.mission-card span{position:relative;z-index:1;display:block}
.mission-card strong{font-size:.8rem;letter-spacing:.12em;text-transform:uppercase;color:#fff}
.mission-card span{margin-top:7px;color:#9fc8bd;font-size:.76rem;line-height:1.45}
.micro-copy{margin:0 0 12px;color:#9bc3b9;font-size:.82rem;line-height:1.55}
textarea{width:100%;min-height:130px;resize:vertical;color:var(--text);background:rgba(0,0,0,.35);border:1px solid rgba(108,255,202,.2);border-radius:22px;outline:none;padding:15px;line-height:1.6}
textarea:focus{border-color:rgba(0,255,145,.62);box-shadow:0 0 0 4px rgba(0,255,145,.08)}
.composer-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
.composer-actions .btn{min-width:150px}
details{margin-top:14px;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:12px;background:rgba(255,255,255,.025)}
summary{cursor:pointer;color:#a7fff0;font-weight:950}
.lab-options{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}
label{display:block;color:#d9fff4;font-weight:850;font-size:.78rem;margin-bottom:6px}
select,input{width:100%;border:1px solid rgba(108,255,202,.18);background:rgba(1,6,6,.78);color:var(--text);border-radius:14px;padding:11px;outline:none}
.evidence{margin-top:18px;padding:18px}
.evidence h2{margin:0 0 12px;color:#a8fff0;font-size:.8rem;letter-spacing:.16em;text-transform:uppercase}
.ledger{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.ledger-card{border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.035);border-radius:18px;padding:12px;min-height:80px}
.ledger-card span{display:block;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.12em}
.ledger-card strong{display:block;color:#fff;margin-top:7px;word-break:break-word}
.job-tools{display:grid;grid-template-columns:minmax(180px,1fr) repeat(3,auto);gap:10px;margin-top:14px}
.job-tools input{min-height:44px}
.metric-note{margin-top:12px;color:#9fc8bd;font-size:.82rem;line-height:1.55}
.locked{min-height:72vh;display:grid;place-items:center}.locked .panel{max-width:980px;text-align:center;padding:34px}
.public-preview{margin:22px auto 0;max-width:850px;border:1px solid rgba(104,247,255,.2);border-radius:26px;padding:10px;background:linear-gradient(145deg,rgba(0,255,145,.08),rgba(104,247,255,.05));box-shadow:0 22px 80px rgba(0,0,0,.35),0 0 44px rgba(0,255,145,.08)}
.public-preview img{display:block;width:100%;border-radius:20px;border:1px solid rgba(255,255,255,.08);box-shadow:0 16px 60px rgba(0,0,0,.34)}
.preview-copy{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-top:10px}
.preview-copy span{border:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.24);border-radius:16px;padding:10px;color:#bde7dc;font-size:.76rem;font-weight:900;text-transform:uppercase;letter-spacing:.1em}
.proof-note{margin-top:12px;border:1px solid rgba(0,255,145,.16);background:rgba(0,255,145,.055);border-radius:18px;padding:12px;color:#bde7dc;font-size:.82rem;line-height:1.55;text-align:left}
.proof-note strong{color:#fff}
.proof-chain{display:grid;gap:7px;margin-top:10px}
.proof-chain span{display:flex;justify-content:space-between;gap:10px;border:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.24);border-radius:12px;padding:8px 10px;color:#c8fff2;font-size:.74rem}
.proof-chain code{color:#7fffd4;font-family:"Space Mono",monospace;font-size:.7rem;word-break:break-all}
.proof-chain em{color:#fff;font-style:normal;font-weight:900}
.is-thinking .avatar-halo{animation-duration:3.2s;box-shadow:0 0 68px rgba(255,211,111,.22),inset 0 0 48px rgba(104,247,255,.12)}
.is-thinking .agent-avatar{filter:drop-shadow(0 0 30px rgba(255,211,111,.38)) drop-shadow(0 0 66px rgba(0,255,145,.18))}
.is-speaking .avatar-voice{animation:speakPulse .62s ease-in-out infinite;opacity:.86}
.is-speaking .avatar-face{animation:faceTalk .38s ease-in-out infinite;opacity:.72}
.is-speaking .agent-avatar{animation:avatarSpeak .72s ease-in-out infinite;filter:drop-shadow(0 0 38px rgba(104,247,255,.58)) drop-shadow(0 0 80px rgba(0,255,145,.24))}
@keyframes avatarFloat{50%{transform:translateY(-8px) scale(1.01)}}@keyframes avatarSpeak{50%{transform:translateY(-4px) scale(1.018)}}@keyframes speakPulse{50%{transform:translateX(-50%) scale(1.16);filter:blur(2px)}}@keyframes faceTalk{50%{transform:translateX(-50%) rotate(6deg) scaleY(.84);box-shadow:0 0 28px rgba(104,247,255,.44)}}@keyframes spin{to{rotate:360deg}}@keyframes pulseOrb{50%{transform:scale(1.035);box-shadow:0 0 58px rgba(0,255,145,.34),inset 0 0 40px rgba(104,247,255,.13)}}
@media(max-width:980px){.chat-stage{grid-template-columns:1fr}.messages{max-height:none}.ledger{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:760px){.mission-grid{grid-template-columns:1fr}.job-tools{grid-template-columns:1fr}}
@media(max-width:640px){.shell{width:min(100% - 18px,1320px);padding-top:12px}.topbar,.nav{align-items:stretch;flex-direction:column}.btn{width:100%}h1{font-size:clamp(3rem,18vw,5.2rem)}.presence{padding:14px}.voice-row,.signal-strip,.lab-options,.ledger,.preview-copy{grid-template-columns:1fr}.composer-actions .btn{min-width:0}.msg{max-width:100%}.agent-stage{height:360px}}
</style>
</head>
<body>
<canvas id="qz-field" aria-hidden="true"></canvas>
<div class="shell">
  <header class="topbar">
    <a class="brand" href="/studio">
      <img src="/assets/img/logo_nobg.png" alt="ZeroThink">
      <div><strong>QuantumZero</strong><span>Real Quantum AI Chat</span></div>
    </a>
    <nav class="nav" aria-label="QuantumZero navigation">
      <a class="btn" href="/studio">Studio</a>
      <a class="btn" href="https://research.talktoai.org">Research</a>
      <a class="btn" href="/faq#quantum">Docs</a>
      <a class="btn gold" href="/quantumlab">Quantum Lab</a>
      <a class="btn primary" href="/upgrade">Pro</a>
    </nav>
  </header>

  <?php if (!$is_pro): ?>
  <main class="locked">
    <section class="panel">
      <p class="eyebrow">Sovereign Pro Research Lane</p>
      <h1><span class="qword">Quantum</span>Zero</h1>
      <p class="lead">A simpler front door to the quantum lane: chat with Quantum Zero, then let the server quietly handle Groq reasoning plus IonQ telemetry/job evidence behind the scenes.</p>
      <p class="truth">Real Quantum AI here means classical AI reasoning with real quantum-cloud signal in the loop. It does not mean the LLM itself runs on qubits.</p>
      <div class="public-preview" aria-label="QuantumZero public preview">
        <img src="/assets/img/quantumzero-og.png" alt="QuantumZero Real Quantum AI chat preview" loading="eager" decoding="async">
        <div class="preview-copy">
          <span>Groq reasoning</span>
          <span>IonQ evidence</span>
          <span>Quantum Lab ready</span>
        </div>
        <div class="proof-note">
          <strong>Six-job Zero research proof chain:</strong> Quantum Zero now has six completed IonQ simulator records seeded from the private Zero research themes. The chain moves from an ethics pulse into Delta branching, Omega/Phi/Xi balance, ADEV decision spread, a harmonic base matrix, and a six-layer ethics review. This is real provider-backed circuit evidence, not a claim of remote activation or an LLM running on qubits.
          <div class="proof-chain" aria-label="IonQ evidence chain">
            <span><em>01 Ethics</em><code>019e04e3-ae16-702e-96ee-d16fb54d6886</code></span>
            <span><em>02 Delta</em><code>019e04e9-bf18-77df-b463-4ce4bdec956a</code></span>
            <span><em>03 Balance</em><code>019e04e9-d377-7578-b13c-745490ea8183</code></span>
            <span><em>04 ADEV</em><code>019e04e9-de7d-70ed-ac28-6618fe273567</code></span>
            <span><em>05 Harmonic</em><code>019e04e9-e96f-73f9-9439-7ec2612bf50d</code></span>
            <span><em>06 Lattice</em><code>019e04e9-f44f-743b-a56b-d0a7bbcdfb5d</code></span>
          </div>
        </div>
      </div>
      <div class="composer-actions" style="justify-content:center">
        <a class="btn primary" href="/upgrade">Unlock Pro</a>
        <a class="btn gold" href="/quantumlab">Preview Quantum Lab</a>
        <a class="btn" href="/faq#quantum">Read Quantum Docs</a>
      </div>
    </section>
  </main>
  <?php else: ?>
  <section class="hero">
    <p class="eyebrow">Ask the quantum agent</p>
    <h1><span class="qword">Quantum</span>Zero</h1>
    <p class="lead">No maze of controls. Ask the question, choose a mission if you want, and Quantum Zero will answer with a clean split between real IonQ evidence, research interpretation, and the practical next step.</p>
    <p class="truth">Truth boundary: real quantum-cloud telemetry and optional IonQ simulator jobs can ground the answer. The chatbot itself still runs on classical AI infrastructure.</p>
    <p class="truth">Latest evidence: Quantum Zero now has a six-job IonQ simulator proof chain based on the private Zero research themes, ending with job 019e04e9-f44f-743b-a56b-d0a7bbcdfb5d. Use the expanded experiment deck to create fresh proof pulses inside your own session.</p>
  </section>

  <main class="chat-stage">
    <aside class="panel presence">
      <div class="agent-stage" aria-label="Quantum Zero avatar">
        <div class="avatar-halo" aria-hidden="true"></div>
        <div class="avatar-voice" aria-hidden="true"></div>
        <video class="agent-avatar agent-video" src="/assets/video/quantum_zero_agent.mp4" poster="/assets/img/quantum_zero_agent.png" autoplay muted loop playsinline preload="metadata" disablepictureinpicture aria-label="Quantum Zero silent hologram"></video>
        <div class="video-glass" aria-hidden="true"></div>
        <div class="avatar-face" aria-hidden="true"></div>
      </div>
      <div class="core-card">
        <div class="orb" aria-hidden="true"></div>
        <div><strong>Quantum Core</strong><span>The original orb stays as the evidence core. Agent Zero is the face; the core is the proof lane.</span></div>
      </div>
      <div class="voice-row">
        <button class="btn" id="voice-intro" type="button">Voice Intro</button>
        <button class="btn" id="speak-last" type="button">Speak Answer</button>
        <button class="btn" id="mic-ask" type="button">Mic Prompt</button>
        <button class="btn red" id="silence" type="button">Silence</button>
      </div>
      <div class="status-grid">
        <div class="status-pill"><span>Classical engine</span><strong>Groq GPT-OSS 120B</strong></div>
        <div class="status-pill"><span>Quantum lane</span><strong>IonQ Cloud</strong></div>
        <div class="status-pill"><span>Default</span><strong>Low-usage auto</strong></div>
        <div class="status-pill"><span id="voice-label">Voice</span><strong id="voice-status">Ready</strong></div>
      </div>
      <div class="signal-strip">
        <div class="signal-mini"><span>Visual</span><strong>Live</strong></div>
        <div class="signal-mini"><span>Proof</span><strong>Job-backed</strong></div>
        <div class="signal-mini"><span>Dispatch</span><strong>Ready</strong></div>
      </div>
    </aside>

    <section class="panel chat">
      <div class="messages" id="messages">
        <div class="msg zero"><strong>Quantum Zero</strong>Ask me something difficult. I will use the quantum evidence lane when useful, but I will not pretend the LLM runs on qubits. Start with a mission chip below, or type your own question.</div>
      </div>
      <div class="composer">
        <p class="micro-copy">Simple mode: choose a mission or just type. ZeroThink handles the IonQ backend, result metrics, and honest explanation inside the platform.</p>
        <div class="mission-grid" aria-label="QuantumZero missions">
          <button class="mission-card" data-task="evidence"><strong>Reality Check</strong><span>Separate real IonQ evidence from interpretation and next experiment.</span></button>
          <button class="mission-card" data-task="proofpulse"><strong>Proof Pulse</strong><span>Run or reuse a tiny simulator job, then explain the histogram.</span></button>
          <button class="mission-card" data-task="ab"><strong>A/B Decision</strong><span>Use quantum probabilities as a branch signal, not a magic answer.</span></button>
          <button class="mission-card" data-task="optimizer"><strong>Optimize</strong><span>Turn a system into variables, constraints, and testable changes.</span></button>
          <button class="mission-card" data-task="dispatch"><strong>Dispatch</strong><span>Hash a private message into a real job-backed research packet.</span></button>
          <button class="mission-card" data-task="integrity"><strong>Anti-BS Audit</strong><span>Make the claim powerful, honest, and university-safe.</span></button>
        </div>
        <div class="chips"><button class="chip" data-task="entropy">Entropy seed idea</button><button class="chip" data-task="research">Research paper angle</button></div>
        <textarea id="question" placeholder="Ask Quantum Zero...">How can real quantum computing make my AI system more useful without making fake claims?</textarea>
        <div class="composer-actions">
          <button class="btn primary" id="run">Ask Quantum Zero</button>
          <button class="btn primary" id="run-experiment">Run Real Experiment</button>
          <button class="btn gold" id="dispatch">Send Quantum Dispatch</button>
          <button class="btn" id="packet">Preview Evidence Packet</button>
          <button class="btn red" id="stop">Stop</button>
        </div>
        <details>
          <summary>Advanced options</summary>
          <div class="lab-options">
            <div><label for="experiment">Experiment</label><select id="experiment"><option value="prompt_seed">Prompt-seeded packet</option><option value="bell_pair">Bell pair correlation</option><option value="ghz_lattice">GHZ lattice pulse</option><option value="entropy_beacon">Entropy beacon</option><option value="parity_lattice">Parity lattice</option><option value="delta_branch">Delta branch ethics</option><option value="omega_balance">Omega Phi Xi balance</option><option value="adev_decision">ADEV decision spread</option><option value="harmonic_matrix">Harmonic base matrix</option><option value="six_layer_lattice">Six layer ethics lattice</option></select></div>
            <div><label for="job-mode">Evidence mode</label><select id="job-mode"><option value="auto">Auto proof job</option><option value="telemetry">Telemetry only</option><option value="force">Force fresh proof</option></select></div>
            <div><label for="backend">IonQ backend</label><select id="backend"><option value="simulator">simulator</option><option value="qpu.aria-1">qpu.aria-1</option><option value="qpu.aria-2">qpu.aria-2</option><option value="qpu.forte-1">qpu.forte-1</option><option value="qpu.forte-enterprise-1">qpu.forte-enterprise-1</option></select></div>
            <div><label for="shots">Shots</label><input id="shots" type="number" min="100" max="1024" value="128"></div>
          </div>
          <label style="margin-top:12px"><input id="confirm-qpu" type="checkbox" style="width:auto"> I understand hardware QPU jobs may cost credits or queue.</label>
        </details>
      </div>
    </section>
  </main>

  <section class="panel evidence" id="evidence">
    <h2>Evidence Ledger</h2>
    <div class="ledger">
      <div class="ledger-card"><span>Status</span><strong id="ev-status">Ready</strong></div>
      <div class="ledger-card"><span>Job ID</span><strong id="ev-job">none</strong></div>
      <div class="ledger-card"><span>Mode</span><strong id="ev-mode">auto</strong></div>
      <div class="ledger-card"><span>Dominant State</span><strong id="ev-dominant">waiting</strong></div>
      <div class="ledger-card"><span>Entropy</span><strong id="ev-entropy">waiting</strong></div>
      <div class="ledger-card"><span>Backend</span><strong id="ev-backend">simulator</strong></div>
      <div class="ledger-card"><span>Top States</span><strong id="ev-top">none</strong></div>
      <div class="ledger-card"><span>Truth Boundary</span><strong id="ev-truth">classical LLM + IonQ context</strong></div>
    </div>
    <div class="job-tools">
      <input id="job-id-input" placeholder="Paste IonQ job ID to explain it inside ZeroThink">
      <button class="btn" id="explain-job" type="button">Explain Job</button>
      <button class="btn" id="recent-jobs" type="button">Recent Jobs</button>
      <button class="btn" id="backend-status" type="button">Backend Status</button>
    </div>
    <p class="metric-note">ZeroThink now reads IonQ probability histograms into simple metrics: dominant state, entropy/spread, top states, and the practical interpretation boundary.</p>
  </section>
  <?php endif; ?>
</div>

<script>
(function(){
  const qzCsrfToken = <?= json_encode($qz_csrf_token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const canvas = document.getElementById('qz-field');
  const ctx = canvas.getContext('2d');
  let dots = [];
  function size(){
    canvas.width = window.innerWidth * devicePixelRatio;
    canvas.height = window.innerHeight * devicePixelRatio;
    canvas.style.width = window.innerWidth + 'px';
    canvas.style.height = window.innerHeight + 'px';
    ctx.setTransform(devicePixelRatio,0,0,devicePixelRatio,0,0);
    const count = Math.min(82, Math.max(30, Math.floor(window.innerWidth / 20)));
    dots = Array.from({length:count}, () => ({x:Math.random()*window.innerWidth,y:Math.random()*window.innerHeight,vx:(Math.random()-.5)*.34,vy:(Math.random()-.5)*.34,r:Math.random()*1.7+0.5}));
  }
  function draw(){
    ctx.clearRect(0,0,window.innerWidth,window.innerHeight);
    ctx.fillStyle = 'rgba(104,247,255,.5)';
    for(const d of dots){d.x+=d.vx;d.y+=d.vy;if(d.x<0||d.x>window.innerWidth)d.vx*=-1;if(d.y<0||d.y>window.innerHeight)d.vy*=-1;ctx.beginPath();ctx.arc(d.x,d.y,d.r,0,Math.PI*2);ctx.fill();}
    ctx.strokeStyle = 'rgba(0,255,145,.08)';
    for(let i=0;i<dots.length;i++){for(let j=i+1;j<dots.length;j++){const a=dots[i],b=dots[j],dist=Math.hypot(a.x-b.x,a.y-b.y);if(dist<126){ctx.globalAlpha=(126-dist)/126;ctx.beginPath();ctx.moveTo(a.x,a.y);ctx.lineTo(b.x,b.y);ctx.stroke();ctx.globalAlpha=1;}}}
    requestAnimationFrame(draw);
  }
  size(); draw(); window.addEventListener('resize', size);

  const messages = document.getElementById('messages');
  if(!messages) return;
  document.querySelectorAll('.agent-video').forEach(video => {
    video.muted = true;
    video.defaultMuted = true;
    video.volume = 0;
    video.playsInline = true;
    video.play().catch(() => {});
    video.addEventListener('volumechange', () => {
      if (!video.muted || video.volume !== 0) {
        video.muted = true;
        video.defaultMuted = true;
        video.volume = 0;
      }
    });
  });
  let aborter = null;
  let lastAnswerText = '';
  let activeTask = {profile:'zero-network', jobMode:'auto'};
  let recognition = null;
  let voiceAudio = null;
  const supportsSpeech = 'speechSynthesis' in window;
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  const tasks = {
    evidence:{profile:'signal-integrity',jobMode:'auto',experiment:'prompt_seed',prompt:'Run a Quantum Zero evidence check on this idea. What is real quantum evidence, what is symbolic research framing, and what practical test should I run next?'},
    proofpulse:{profile:'zero-network',jobMode:'force',experiment:'bell_pair',prompt:'Run a compact QuantumZero proof pulse. Submit a tiny IonQ simulator job if allowed, read the probability histogram, and explain the dominant state, entropy, and practical meaning in plain English.'},
    entropy:{profile:'entanglement-probe',jobMode:'force',experiment:'entropy_beacon',prompt:'Design and run a safe Quantum Entropy Beacon experiment using IonQ probabilities as public randomness or inspiration, not as a private security key.'},
    ab:{profile:'entanglement-probe',jobMode:'force',experiment:'bell_pair',prompt:'Compare two paths with a quantum-informed A/B decision read. Use the Bell result as a branch signal, then separate signal, bias, risk, reversibility, and the smallest next test.'},
    optimizer:{profile:'optimization-oracle',jobMode:'auto',experiment:'parity_lattice',prompt:'Optimize a system design. Run a parity-lattice style experiment if useful, then turn the result into variables, constraints, risk gates, and a practical experiment plan.'},
    dispatch:{profile:'zero-network',jobMode:'force',experiment:'prompt_seed',prompt:'Send this as a Quantum Dispatch: encode the message into a private circuit seed, submit a small IonQ simulator job, and return the job ID with a clear explanation of what is real evidence and what is not.'},
    integrity:{profile:'signal-integrity',jobMode:'telemetry',experiment:'prompt_seed',prompt:'Run an anti-BS audit. Identify what is real engineering, what is research interpretation, and what wording avoids deception while still sounding powerful.'},
    research:{profile:'zero-network',jobMode:'telemetry',experiment:'ghz_lattice',prompt:'Help me turn QuantumZero into a serious research-page explanation for universities: clear claim, evidence boundary, experiments, limitations, and future work.'}
  };
  function esc(value){return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));}
  function addMsg(who, text){const div=document.createElement('div');div.className='msg '+(who==='You'?'user':'zero');div.innerHTML='<strong>'+esc(who)+'</strong>'+esc(text);messages.appendChild(div);messages.scrollTop=messages.scrollHeight;}
  function setAgent(state, label){document.body.classList.toggle('is-thinking', state==='thinking');document.body.classList.toggle('is-speaking', state==='speaking');const s=document.getElementById('voice-status');if(s)s.textContent=label||'Ready';}
  function updateEvidence(data, statusText){
    const ionq=data.ionq||{}, job=data.job||{}, packet=data.packet||{};
    const metrics=data.probability_metrics || (data.meta && data.meta.probability_metrics) || {};
    const top=Array.isArray(metrics.top_states)?metrics.top_states:[];
    const dominant=metrics.dominant_state||top[0]||null;
    const backend=(job.backend || (data.meta && data.meta.backend) || (data.job_request_public && data.job_request_public.backend) || document.getElementById('backend').value || 'simulator');
    document.getElementById('ev-status').textContent = statusText || job.status || ionq.status || data.mode || 'Ready';
    document.getElementById('ev-job').textContent = ionq.id || job.id || 'none';
    document.getElementById('ev-mode').textContent = data.mode || document.getElementById('job-mode').value || 'auto';
    document.getElementById('ev-dominant').textContent = dominant ? (dominant.state + ' @ ' + dominant.percent + '%') : 'waiting';
    document.getElementById('ev-entropy').textContent = metrics.available ? (metrics.entropy_bits + ' bits') : 'waiting';
    document.getElementById('ev-backend').textContent = backend;
    document.getElementById('ev-top').textContent = top.length ? top.slice(0,4).map(s => s.state + ':' + s.percent + '%').join(' | ') : 'none';
    document.getElementById('ev-truth').textContent = data.truth_boundary || packet.truth_boundary || 'classical LLM + IonQ context';
    if(ionq.id || job.id){document.getElementById('job-id-input').value = ionq.id || job.id;}
  }
  function readPayload(){
    return {
      profile: activeTask.profile || 'zero-network',
      experiment: document.getElementById('experiment').value || activeTask.experiment || 'prompt_seed',
      question: document.getElementById('question').value || '',
      signal_text: '',
      backend: document.getElementById('backend').value || 'simulator',
      shots: parseInt(document.getElementById('shots').value || '128', 10),
      confirm_qpu: document.getElementById('confirm-qpu').checked,
      job_mode: document.getElementById('job-mode').value || activeTask.jobMode || 'auto'
    };
  }
  async function post(action, payload){
    aborter = new AbortController();
    const res = await fetch('/api_quantum_zero.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':qzCsrfToken},body:JSON.stringify(Object.assign({action}, payload || {})),signal:aborter.signal});
    const data = await res.json().catch(() => ({status:'error',message:'Bad JSON response'}));
    if(!res.ok || data.status === 'error') throw new Error(data.message || 'Request failed');
    return data;
  }
  async function run(action){
    const payload = readPayload();
    if(action === 'dispatch') {
      payload.signal_text = payload.question;
      payload.job_mode = 'force';
    }
    if(action === 'analyze' || action === 'dispatch' || action === 'experiment') addMsg('You', (action === 'dispatch' ? 'Quantum Dispatch: ' : (action === 'experiment' ? 'Quantum Experiment: ' : '')) + payload.question);
    setAgent('thinking', action === 'packet' ? 'Building packet' : (action === 'dispatch' ? 'Dispatching' : (action === 'experiment' ? 'Running experiment' : 'Thinking')));
    updateEvidence({}, action === 'packet' ? 'Building packet' : (action === 'dispatch' ? 'Dispatching' : (action === 'experiment' ? 'Running experiment' : 'Running')));
    try{
      const data = await post(action, payload);
      if(action === 'packet'){
        updateEvidence(data, 'Packet ready');
        addMsg('Quantum Zero', 'Evidence packet preview is ready. No IonQ job was submitted. Ask the full question when you want the Groq + IonQ answer.');
      } else {
        const reply = data.reply || data.answer || 'No answer returned.';
        lastAnswerText = reply;
        updateEvidence(data, action === 'dispatch' ? 'Dispatch sent' : (action === 'experiment' ? 'Experiment complete' : 'Answer ready'));
        addMsg('Quantum Zero', reply);
      }
      setAgent('idle', 'Ready');
    }catch(e){
      if(e.name === 'AbortError'){addMsg('Quantum Zero','Stopped.');updateEvidence({}, 'Stopped');setAgent('idle','Stopped');}
      else{addMsg('Quantum Zero','Error: '+e.message+'. If this says key missing, open Studio -> Neural Vault and add Groq plus IonQ keys.');updateEvidence({}, 'Error');setAgent('idle','Error');}
    }finally{aborter=null;}
  }
  function jobListSummary(payload){
    const jobs = (payload && (payload.jobs || payload.data || payload.items)) || [];
    if(!Array.isArray(jobs) || !jobs.length) return 'No recent IonQ jobs returned for this project/key.';
    return jobs.slice(0,6).map(j => `${j.id || 'job'} | ${j.status || 'unknown'} | ${j.backend || j.target || 'backend?'} | ${j.name || 'unnamed'}`).join('\n');
  }
  async function utility(action){
    setAgent('thinking', action === 'status' ? 'Checking IonQ' : 'Reading jobs');
    try{
      const data = await post(action, action === 'recent' ? {limit:6} : {});
      updateEvidence(data, action === 'status' ? 'Backend status' : 'Recent jobs');
      if(action === 'status'){
        const s=data.backend_summary||{};
        addMsg('Quantum Zero', (s.message||'IonQ backend status returned.') + '\nRecommended backend: ' + (s.recommended_backend||'simulator') + '\nVisible online count: ' + (s.online_count ?? 'unknown'));
      }else{
        addMsg('Quantum Zero', 'Recent IonQ jobs:\n' + jobListSummary(data.ionq));
      }
      setAgent('idle','Ready');
    }catch(e){
      addMsg('Quantum Zero','Utility error: '+e.message);
      setAgent('idle','Error');
    }
  }
  async function explainJob(){
    const id=document.getElementById('job-id-input').value.trim();
    if(!id){addMsg('Quantum Zero','Paste an IonQ job ID first.');return;}
    const payload=readPayload();
    payload.job_id=id;
    payload.question=payload.question || 'Explain this IonQ job result in simple ZeroThink language.';
    addMsg('You','Explain IonQ job '+id);
    setAgent('thinking','Explaining job');
    updateEvidence({ionq:{id}}, 'Explaining job');
    try{
      const data=await post('explain_job', payload);
      lastAnswerText=data.reply||'No explanation returned.';
      updateEvidence(data,'Job explained');
      addMsg('Quantum Zero', lastAnswerText);
      setAgent('idle','Ready');
    }catch(e){
      addMsg('Quantum Zero','Job explanation error: '+e.message);
      updateEvidence({}, 'Error');
      setAgent('idle','Error');
    }
  }
  function speechText(text){return String(text||'').replace(/\[[^\]]+\]/g,' ').replace(/https?:\/\/\S+/g,' link ').replace(/[{}[\]<>]/g,' ').replace(/\s+/g,' ').trim().slice(0,4200);}
  function pickVoice(){if(!supportsSpeech)return null;const voices=speechSynthesis.getVoices();return voices.find(v=>/en-GB|United Kingdom|Ryan|George|Daniel/i.test(v.lang+' '+v.name))||voices.find(v=>/^en/i.test(v.lang))||voices[0]||null;}
  function browserSpeak(clean){if(!supportsSpeech){setAgent('idle','Text mode');return;}window.speechSynthesis.cancel();const u=new SpeechSynthesisUtterance(clean);const v=pickVoice();if(v)u.voice=v;u.rate=.94;u.pitch=.86;u.onstart=()=>setAgent('speaking','Browser voice');u.onend=()=>setAgent('idle','Ready');u.onerror=()=>setAgent('idle','Ready');speechSynthesis.speak(u);}
  async function serverSpeak(clean){
    try{
      setAgent('thinking','Rendering local voice');
      const res=await fetch('/api_quantum_voice.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':qzCsrfToken},body:JSON.stringify({action:'speak',text:clean,csrf_token:qzCsrfToken})});
      const data=await res.json().catch(()=>({status:'fallback'}));
      if(data.status!=='success'||!data.url)return false;
      if(supportsSpeech)window.speechSynthesis.cancel();
      if(voiceAudio){voiceAudio.pause();voiceAudio.removeAttribute('src');voiceAudio.load();}
      voiceAudio=new Audio(data.url);
      voiceAudio.preload='auto';
      voiceAudio.onplay=()=>setAgent('speaking','Local Piper voice');
      voiceAudio.onended=()=>setAgent('idle','Ready');
      voiceAudio.onerror=()=>setAgent('idle','Voice fallback');
      await voiceAudio.play();
      return true;
    }catch(e){
      return false;
    }
  }
  async function speak(text){const clean=speechText(text);if(!clean)return;const ok=await serverSpeak(clean);if(!ok)browserSpeak(clean);}
  function stopAll(){if(aborter)aborter.abort();if(voiceAudio){voiceAudio.pause();voiceAudio.removeAttribute('src');voiceAudio.load();voiceAudio=null;}if(supportsSpeech)speechSynthesis.cancel();if(recognition){try{recognition.stop();}catch(e){}recognition=null;}setAgent('idle','Ready');}
  function mic(){if(!SpeechRecognition){addMsg('Quantum Zero','This browser does not support Web Speech microphone input. You can still type your prompt.');return;}stopAll();recognition=new SpeechRecognition();recognition.lang='en-GB';recognition.interimResults=false;recognition.onstart=()=>setAgent('speaking','Listening');recognition.onerror=()=>setAgent('idle','Mic error');recognition.onend=()=>{recognition=null;setAgent('idle','Ready')};recognition.onresult=e=>{const t=e.results&&e.results[0]&&e.results[0][0]?e.results[0][0].transcript:'';if(t){document.getElementById('question').value=t;addMsg('You', t);addMsg('Quantum Zero','Voice prompt loaded. Press Ask Quantum Zero when ready.');}};recognition.start();}
  document.querySelectorAll('[data-task]').forEach(btn => btn.addEventListener('click', () => {const t=tasks[btn.dataset.task];if(!t)return;activeTask=t;document.getElementById('question').value=t.prompt;document.getElementById('job-mode').value=t.jobMode;document.getElementById('experiment').value=t.experiment||'prompt_seed';addMsg('Quantum Zero','Mission loaded: '+btn.textContent.trim()+'. Edit the prompt if needed, then press Ask Quantum Zero or Run Real Experiment.');}));
  document.getElementById('run').addEventListener('click', () => run('analyze'));
  document.getElementById('run-experiment').addEventListener('click', () => run('experiment'));
  document.getElementById('dispatch').addEventListener('click', () => run('dispatch'));
  document.getElementById('packet').addEventListener('click', () => run('packet'));
  document.getElementById('stop').addEventListener('click', stopAll);
  document.getElementById('voice-intro').addEventListener('click', () => speak('I am Quantum Zero. Ask me a hard research question. I will use Groq reasoning with IonQ quantum cloud evidence when useful, and I will separate real evidence from interpretation.'));
  document.getElementById('speak-last').addEventListener('click', () => speak(lastAnswerText || 'Quantum Zero is ready. Ask a question first.'));
  document.getElementById('mic-ask').addEventListener('click', mic);
  document.getElementById('silence').addEventListener('click', stopAll);
  document.getElementById('explain-job').addEventListener('click', explainJob);
  document.getElementById('recent-jobs').addEventListener('click', () => utility('recent'));
  document.getElementById('backend-status').addEventListener('click', () => utility('status'));
  if(supportsSpeech) window.speechSynthesis.onvoiceschanged = pickVoice;
  fetch('/api_quantum_voice.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':qzCsrfToken},body:JSON.stringify({action:'health',csrf_token:qzCsrfToken})}).then(r=>r.json()).then(data=>{const label=document.getElementById('voice-label');const status=document.getElementById('voice-status');if(label)label.textContent='Voice engine';if(status)status.textContent=data.available?'Local Piper':'Browser fallback';}).catch(()=>{});
})();
</script>
</body>
</html>
