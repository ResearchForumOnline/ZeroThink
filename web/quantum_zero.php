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
<title>Quantum Lab | ZeroThink</title>
<meta name="description" content="Quantum Lab is the advanced ZeroThink Pro operator console for IonQ job evidence, Groq reasoning, packet inspection, circuit missions, telemetry forensics, and quantum-informed AI experiments.">
<link rel="canonical" href="https://zerothink.talktoai.org/quantumlab">
<link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
<style>
:root{
  --bg:#020403;
  --panel:rgba(3,12,10,.78);
  --panel2:rgba(8,22,20,.82);
  --line:rgba(108,255,202,.22);
  --text:#eafff6;
  --muted:#8da79d;
  --green:#00ff91;
  --cyan:#68f7ff;
  --blue:#6aa7ff;
  --gold:#ffd36f;
  --red:#ff6d7b;
  --shadow:0 28px 100px rgba(0,0,0,.55);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  margin:0;
  min-height:100vh;
  color:var(--text);
  background:
    radial-gradient(circle at 18% 0%,rgba(0,255,145,.22),transparent 34rem),
    radial-gradient(circle at 82% 8%,rgba(104,247,255,.16),transparent 28rem),
    radial-gradient(circle at 50% 100%,rgba(106,167,255,.14),transparent 32rem),
    linear-gradient(180deg,#010302,#06110f 52%,#020504);
  font-family:ui-sans-serif,Inter,Segoe UI,Roboto,Arial,sans-serif;
  overflow-x:hidden;
}
body:before{
  content:"";
  position:fixed;
  inset:0;
  pointer-events:none;
  background-image:
    linear-gradient(rgba(104,247,255,.045) 1px,transparent 1px),
    linear-gradient(90deg,rgba(104,247,255,.045) 1px,transparent 1px);
  background-size:38px 38px;
  mask-image:radial-gradient(circle at center,#000 0%,transparent 82%);
}
body:after{
  content:"";
  position:fixed;
  inset:0;
  pointer-events:none;
  opacity:.08;
  background:repeating-linear-gradient(0deg,transparent 0,transparent 7px,#fff 8px);
  mix-blend-mode:screen;
}
a{color:inherit}
button,input,select,textarea{font:inherit}
#qz-field{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:.7}
.shell{position:relative;z-index:2;width:min(1480px,calc(100% - 30px));margin:0 auto;padding:22px 0 70px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:18px}
.brand{display:flex;align-items:center;gap:14px;text-decoration:none}
.brand img{width:46px;filter:drop-shadow(0 0 18px rgba(0,255,145,.42))}
.brand strong{display:block;letter-spacing:.08em;text-transform:uppercase;font-size:1rem}
.brand span{display:block;color:var(--muted);font-size:.72rem;letter-spacing:.18em;text-transform:uppercase}
.nav{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.btn{
  border:1px solid var(--line);
  background:rgba(2,8,8,.78);
  color:var(--text);
  border-radius:999px;
  padding:11px 15px;
  font-weight:850;
  text-decoration:none;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease,background .18s ease;
}
.btn:hover{transform:translateY(-1px);border-color:rgba(0,255,145,.7);box-shadow:0 0 28px rgba(0,255,145,.13);text-decoration:none}
.btn.primary{background:linear-gradient(135deg,var(--green),#83ffd5);color:#001711;border-color:transparent}
.btn.gold{background:rgba(255,211,111,.12);border-color:rgba(255,211,111,.45);color:#ffe6a4}
.btn.red{background:rgba(255,109,123,.12);border-color:rgba(255,109,123,.45);color:#ffb2bc}
.hero{
  position:relative;
  min-height:360px;
  display:grid;
  grid-template-columns:minmax(0,1fr) 360px;
  gap:20px;
  align-items:stretch;
  margin-bottom:18px;
}
.panel,.card,.console{
  border:1px solid var(--line);
  background:linear-gradient(180deg,var(--panel),rgba(1,5,5,.82));
  border-radius:28px;
  box-shadow:var(--shadow),inset 0 1px 0 rgba(255,255,255,.04);
  backdrop-filter:blur(16px);
}
.hero-main{padding:34px;overflow:hidden}
.eyebrow{color:var(--green);font:900 .78rem/1.2 ui-monospace,Consolas,monospace;letter-spacing:.22em;text-transform:uppercase}
h1{
  margin:14px 0 14px;
  max-width:900px;
  font-size:clamp(3.4rem,9vw,8.2rem);
  line-height:.82;
  letter-spacing:-.09em;
  text-transform:uppercase;
}
.qword{
  display:inline-block;
  background:linear-gradient(90deg,#fff,var(--green),var(--cyan),#fff);
  background-size:220% 100%;
  -webkit-background-clip:text;
  background-clip:text;
  color:transparent;
  animation:glowText 7s linear infinite;
}
@keyframes glowText{to{background-position:220% 0}}
.lead{max-width:910px;color:#b9cdc5;font-size:1.05rem;line-height:1.75;margin:0}
.truth{margin-top:18px;color:#a7fff0;font-size:.9rem;line-height:1.65;border-left:3px solid var(--green);padding-left:14px}
.lab-ribbon{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 18px}
.lab-card{
  position:relative;
  overflow:hidden;
  min-height:150px;
  padding:16px;
  border:1px solid rgba(108,255,202,.16);
  border-radius:24px;
  background:
    radial-gradient(circle at 80% 10%,rgba(104,247,255,.12),transparent 7rem),
    linear-gradient(160deg,rgba(255,255,255,.055),rgba(255,255,255,.018));
}
.lab-card:before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,transparent,rgba(0,255,145,.12),transparent);transform:translateX(-110%);animation:scanCard 8s ease-in-out infinite}
.lab-card strong,.lab-card span,.lab-card em{position:relative;z-index:1}
.lab-card strong{display:block;color:#fff;letter-spacing:.1em;text-transform:uppercase;font-size:.82rem}
.lab-card span{display:block;color:#aec8bf;font-size:.83rem;line-height:1.55;margin-top:8px}
.lab-card em{display:inline-flex;margin-top:13px;color:#71ffd5;font-style:normal;font-size:.68rem;letter-spacing:.16em;text-transform:uppercase}
.pipeline{grid-column:1/-1;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:9px;margin:4px 0 18px}
.stage{
  border:1px solid rgba(255,255,255,.08);
  border-radius:18px;
  padding:12px;
  background:rgba(0,0,0,.28);
  color:#dffcf4;
  min-height:86px;
}
.stage span{display:block;color:var(--green);font:900 .68rem/1.2 ui-monospace,Consolas,monospace;letter-spacing:.14em;text-transform:uppercase}
.stage strong{display:block;margin-top:7px;color:#fff;font-size:.88rem}
.stage small{display:block;margin-top:5px;color:#8ea9a0;line-height:1.4}
@keyframes scanCard{50%{transform:translateX(110%)}100%{transform:translateX(110%)}}
.orb-card{position:relative;overflow:hidden;min-height:510px;display:grid;place-items:center;padding:18px}
.orb{
  width:min(250px,68vw);
  aspect-ratio:1;
  border-radius:50%;
  position:relative;
  border:1px solid rgba(104,247,255,.35);
  box-shadow:0 0 70px rgba(0,255,145,.22),inset 0 0 60px rgba(104,247,255,.08);
  background:
    radial-gradient(circle at 35% 30%,rgba(255,255,255,.78),transparent 8%),
    radial-gradient(circle at 50% 55%,rgba(0,255,145,.28),transparent 34%),
    radial-gradient(circle at 65% 36%,rgba(104,247,255,.2),transparent 24%),
    #020605;
  animation:pulseOrb 4.2s ease-in-out infinite;
}
.orb:before,.orb:after{
  content:"";
  position:absolute;
  inset:10%;
  border-radius:50%;
  border:1px solid rgba(104,247,255,.32);
  transform:rotateX(65deg) rotateZ(20deg);
  animation:spin 8s linear infinite;
}
.orb:after{transform:rotateY(70deg) rotateZ(-40deg);animation-duration:11s;border-color:rgba(0,255,145,.36)}
@keyframes pulseOrb{50%{transform:scale(1.035);box-shadow:0 0 95px rgba(0,255,145,.34),inset 0 0 70px rgba(104,247,255,.13)}}
@keyframes spin{to{rotate:360deg}}
.agent-stage{position:relative;width:min(340px,76vw);height:410px;display:grid;place-items:end center;margin-top:4px;isolation:isolate}
.agent-stage:before{
  content:"";
  position:absolute;
  inset:auto 8% 0 8%;
  height:34%;
  border-radius:50%;
  background:radial-gradient(ellipse at center,rgba(0,255,145,.2),transparent 66%);
  filter:blur(2px);
  transform:perspective(400px) rotateX(64deg);
}
.agent-avatar{
  position:relative;
  z-index:3;
  width:min(310px,78vw);
  max-height:400px;
  object-fit:contain;
  filter:drop-shadow(0 0 28px rgba(104,247,255,.42)) drop-shadow(0 0 56px rgba(0,255,145,.18));
  transform-origin:50% 95%;
  animation:avatarFloat 5.6s ease-in-out infinite;
}
.agent-video{
  border-radius:32px;
  object-fit:contain;
  background:#000;
  border:1px solid rgba(104,247,255,.22);
  box-shadow:0 0 0 1px rgba(0,255,145,.08) inset,0 18px 90px rgba(0,0,0,.44);
}
.video-glass{
  position:absolute;
  z-index:5;
  inset:16px 16px 34px 16px;
  pointer-events:none;
  border-radius:34px;
  background:linear-gradient(120deg,rgba(255,255,255,.12),transparent 30%,transparent 68%,rgba(0,255,145,.08));
  mix-blend-mode:screen;
}
.video-glass:after{
  content:"";
  position:absolute;
  inset:0;
  border-radius:34px;
  background:repeating-linear-gradient(0deg,transparent 0,transparent 6px,rgba(104,247,255,.08) 7px);
  opacity:.42;
}
.avatar-halo,.avatar-voice,.avatar-face{
  position:absolute;
  pointer-events:none;
  border-radius:50%;
}
.avatar-halo{
  z-index:1;
  width:250px;
  height:250px;
  bottom:38px;
  border:1px solid rgba(104,247,255,.28);
  box-shadow:0 0 44px rgba(0,255,145,.16),inset 0 0 36px rgba(104,247,255,.08);
  animation:spin 13s linear infinite;
}
.avatar-halo:after{
  content:"";
  position:absolute;
  inset:16%;
  border-radius:50%;
  border:1px dashed rgba(0,255,145,.32);
}
.avatar-voice{
  z-index:2;
  width:92px;
  height:92px;
  top:76px;
  left:50%;
  transform:translateX(-50%);
  background:radial-gradient(circle,rgba(104,247,255,.24),rgba(0,255,145,.06) 55%,transparent 72%);
  opacity:.32;
  filter:blur(.5px);
}
.avatar-face{
  z-index:4;
  width:52px;
  height:76px;
  top:72px;
  left:50%;
  transform:translateX(-50%) rotate(6deg);
  border:1px solid rgba(104,247,255,.2);
  background:linear-gradient(180deg,rgba(104,247,255,.2),rgba(0,255,145,.05));
  opacity:.26;
  filter:blur(1px);
}
.voice-panel{
  position:relative;
  z-index:5;
  width:100%;
  margin-top:12px;
  padding:13px;
  border:1px solid rgba(108,255,202,.18);
  border-radius:20px;
  background:rgba(0,0,0,.34);
}
.voice-status{display:flex;justify-content:space-between;gap:10px;color:#b6d6cc;font-size:.78rem;margin-bottom:10px}
.voice-status strong{color:#77ffd4;text-align:right}
.voice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.voice-grid .btn{justify-content:center;padding:10px 11px;font-size:.76rem}
.voice-toggle{margin:10px 0 0;font-size:.78rem}
.core-strip{
  position:relative;
  z-index:5;
  width:100%;
  display:flex;
  align-items:center;
  gap:12px;
  margin-top:12px;
  padding:12px;
  border:1px solid rgba(104,247,255,.18);
  border-radius:20px;
  background:linear-gradient(90deg,rgba(104,247,255,.08),rgba(0,255,145,.035));
}
.core-strip .orb{width:72px;min-width:72px;box-shadow:0 0 36px rgba(0,255,145,.22),inset 0 0 28px rgba(104,247,255,.08)}
.core-strip strong{display:block;color:#eafff6;font-size:.86rem;text-transform:uppercase;letter-spacing:.12em}
.core-strip span{display:block;color:#9fb9b0;font-size:.78rem;line-height:1.45;margin-top:4px}
.is-thinking .avatar-halo{animation-duration:3.4s;box-shadow:0 0 68px rgba(255,211,111,.2),inset 0 0 48px rgba(104,247,255,.12)}
.is-thinking .agent-avatar{filter:drop-shadow(0 0 30px rgba(255,211,111,.38)) drop-shadow(0 0 66px rgba(0,255,145,.18))}
.is-speaking .avatar-voice{animation:speakPulse .62s ease-in-out infinite;opacity:.86}
.is-speaking .avatar-face{animation:faceTalk .38s ease-in-out infinite;opacity:.72}
.is-speaking .agent-avatar{animation:avatarSpeak .72s ease-in-out infinite;filter:drop-shadow(0 0 38px rgba(104,247,255,.58)) drop-shadow(0 0 80px rgba(0,255,145,.24))}
@keyframes avatarFloat{50%{transform:translateY(-8px) scale(1.01)}}
@keyframes avatarSpeak{50%{transform:translateY(-4px) scale(1.018)}}
@keyframes speakPulse{50%{transform:translateX(-50%) scale(1.16);filter:blur(2px)}}
@keyframes faceTalk{50%{transform:translateX(-50%) rotate(6deg) scaleY(.84);box-shadow:0 0 28px rgba(104,247,255,.44)}}
.status-pills{width:100%;display:grid;gap:8px;margin-top:12px}
.pill{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;border:1px solid rgba(255,255,255,.08);background:rgba(0,0,0,.38);border-radius:14px;color:#c8ded6;font-size:.78rem}
.pill strong{color:#fff;text-align:right}
.layout{display:grid;grid-template-columns:330px minmax(0,1fr) 380px;gap:16px;align-items:start}
.panel{padding:18px}
h2{margin:0 0 14px;color:#a8fff0;font-size:.78rem;letter-spacing:.18em;text-transform:uppercase}
.modes{display:grid;gap:10px}
.mode{
  width:100%;
  border:1px solid rgba(255,255,255,.08);
  background:rgba(255,255,255,.035);
  color:#d7f8ee;
  border-radius:18px;
  text-align:left;
  padding:14px;
  cursor:pointer;
}
.mode strong{display:block;margin-bottom:4px}
.mode span{color:var(--muted);font-size:.82rem;line-height:1.45}
.mode.active{border-color:rgba(0,255,145,.62);box-shadow:0 0 0 1px rgba(0,255,145,.16) inset,0 0 30px rgba(0,255,145,.1)}
.mission-deck{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:12px 0 18px}
.mission-card{
  position:relative;
  min-height:150px;
  border:1px solid rgba(108,255,202,.16);
  background:
    radial-gradient(circle at 90% 12%,rgba(0,255,145,.13),transparent 7rem),
    linear-gradient(145deg,rgba(255,255,255,.055),rgba(255,255,255,.02));
  color:#dffcf4;
  border-radius:20px;
  padding:15px;
  text-align:left;
  cursor:pointer;
  overflow:hidden;
  transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease;
}
.mission-card:before{
  content:"";
  position:absolute;
  inset:-1px;
  background:linear-gradient(120deg,transparent,rgba(104,247,255,.22),transparent);
  transform:translateX(-120%);
  transition:transform .55s ease;
}
.mission-card:hover{transform:translateY(-2px);border-color:rgba(0,255,145,.48);box-shadow:0 18px 50px rgba(0,0,0,.22),0 0 28px rgba(0,255,145,.08)}
.mission-card:hover:before{transform:translateX(120%)}
.mission-card strong,.mission-card span,.mission-card em{position:relative;z-index:1}
.mission-card strong{display:block;margin-bottom:7px;color:#fff;font-size:.98rem}
.mission-card span{display:block;color:#a9c5bd;font-size:.84rem;line-height:1.5}
.mission-card em{display:inline-flex;margin-top:12px;color:#73ffd0;font-style:normal;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase}
label{display:block;color:#d9fff4;font-weight:850;font-size:.82rem;margin:14px 0 7px}
input,select,textarea{
  width:100%;
  border:1px solid rgba(108,255,202,.18);
  background:rgba(1,6,6,.78);
  color:var(--text);
  border-radius:16px;
  padding:12px 13px;
  outline:none;
}
input:focus,select:focus,textarea:focus{border-color:rgba(0,255,145,.62);box-shadow:0 0 0 4px rgba(0,255,145,.08)}
textarea{min-height:220px;resize:vertical;line-height:1.55}
.help{color:var(--muted);font-size:.82rem;line-height:1.55;margin-top:7px}
.notice{border:1px solid rgba(255,211,111,.25);background:rgba(255,211,111,.08);color:#ffecc3;border-radius:18px;padding:13px;line-height:1.55;font-size:.86rem}
.switch{display:flex;align-items:flex-start;gap:10px;color:#d9fff4;font-weight:800;line-height:1.45}
.switch input{width:auto;margin-top:4px}
.console{padding:0;overflow:hidden}
.console-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid rgba(108,255,202,.16);background:rgba(0,0,0,.22)}
.console-head strong{font:900 .82rem/1.2 ui-monospace,Consolas,monospace;color:#baffed;letter-spacing:.12em;text-transform:uppercase}
.output{min-height:520px;max-height:74vh;overflow:auto;padding:18px;background:linear-gradient(180deg,rgba(0,0,0,.18),rgba(0,0,0,.44))}
.answer{white-space:pre-wrap;line-height:1.72;color:#eafff6}
.ledger{display:grid;gap:10px;margin-bottom:14px}
.ledger-row{display:flex;justify-content:space-between;gap:14px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.035);border-radius:16px;padding:12px;font-size:.84rem;color:#bdd1ca}
.ledger-row strong{color:#fff;text-align:right;word-break:break-word}
pre{
  margin:0;
  white-space:pre-wrap;
  word-break:break-word;
  background:rgba(0,0,0,.34);
  border:1px solid rgba(255,255,255,.08);
  border-radius:18px;
  padding:13px;
  color:#d2fff0;
  font:500 .82rem/1.5 ui-monospace,Consolas,monospace;
}
.locked{min-height:72vh;display:grid;place-items:center}
.locked .panel{max-width:780px;text-align:center}
.locked h1{font-size:clamp(3rem,9vw,7rem)}
.small-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:12px}
.small-actions .btn{flex:1}
@media(max-width:1160px){.hero,.layout,.lab-ribbon{grid-template-columns:1fr}.pipeline{grid-template-columns:1fr}.orb-card{min-height:520px}.output{max-height:none}}
@media(max-width:680px){.shell{width:min(100% - 18px,1480px);padding-top:12px}.topbar,.nav{align-items:stretch;flex-direction:column}.btn{width:100%}.hero-main,.panel{padding:15px}h1{font-size:clamp(3rem,18vw,5.2rem)}.mission-deck{grid-template-columns:1fr}.voice-grid{grid-template-columns:1fr}.console-head{align-items:flex-start;flex-direction:column}.output{min-height:420px}.ledger-row{flex-direction:column}.status-pills{position:static;padding:0}.agent-stage{height:360px}}
</style>
</head>
<body>
<canvas id="qz-field" aria-hidden="true"></canvas>
<div class="shell">
  <header class="topbar">
    <a class="brand" href="/studio">
      <img src="/assets/img/logo_nobg.png" alt="ZeroThink">
      <div><strong>Quantum Lab</strong><span>Advanced Quantum Zero Console</span></div>
    </a>
    <nav class="nav" aria-label="Quantum Lab navigation">
      <a class="btn" href="/studio">Studio</a>
      <a class="btn" href="https://research.talktoai.org">Research</a>
      <a class="btn" href="/quantumzero">QuantumZero Chat</a>
      <a class="btn" href="/faq#quantum">Docs</a>
      <a class="btn gold" href="/upgrade">Pro</a>
    </nav>
  </header>

  <?php if (!$is_pro): ?>
  <main class="locked">
    <section class="panel">
      <p class="eyebrow">Sovereign Pro Research Lane</p>
      <h1><span class="qword">Quantum</span>Lab</h1>
      <p class="lead" style="margin-inline:auto">Quantum Lab is the advanced Quantum Zero console for Groq-speed reasoning, IonQ Quantum Cloud telemetry, proof jobs, packet inspection, and research-safe experiment design. It is real quantum-informed AI, not a claim that the LLM itself runs on qubits.</p>
      <div class="small-actions" style="justify-content:center">
        <a class="btn primary" href="/upgrade">Unlock Pro</a>
        <a class="btn" href="/faq#quantum">Read Quantum Docs</a>
      </div>
    </section>
  </main>
  <?php else: ?>

  <section class="hero">
    <div class="panel hero-main">
      <p class="eyebrow">Advanced IonQ job evidence + Groq reasoning</p>
      <h1><span class="qword">Quantum</span>Lab</h1>
      <p class="lead">The old advanced quantum workspace is back as Quantum Lab: a serious operator console for building private research kernels, submitting small IonQ simulator proof circuits, checking telemetry, reading job results, and asking Groq GPT-OSS 120B to reason over the evidence.</p>
      <p class="truth">Truth boundary: Quantum Lab is real quantum-informed AI because IonQ cloud telemetry and job records enter the evidence loop. It does not mean the language model itself runs on quantum hardware, and the lab keeps that distinction explicit.</p>
    </div>
    <aside class="panel orb-card">
      <div class="agent-stage" id="agent-stage" aria-label="Quantum Zero avatar">
        <div class="avatar-halo" aria-hidden="true"></div>
        <div class="avatar-voice" aria-hidden="true"></div>
        <video class="agent-avatar agent-video" src="/assets/video/quantum_zero_agent.mp4" poster="/assets/img/quantum_zero_agent.png" autoplay muted loop playsinline preload="metadata" disablepictureinpicture aria-label="Quantum Zero silent hologram"></video>
        <div class="video-glass" aria-hidden="true"></div>
        <div class="avatar-face" aria-hidden="true"></div>
      </div>
      <div class="voice-panel">
        <div class="voice-status"><span>Voice layer</span><strong id="voice-status">Ready</strong></div>
        <div class="voice-grid">
          <button class="btn" id="voice-intro" type="button">Voice Intro</button>
          <button class="btn" id="speak-last" type="button">Speak Answer</button>
          <button class="btn" id="mic-ask" type="button">Mic Prompt</button>
          <button class="btn red" id="silence" type="button">Silence</button>
        </div>
        <label class="switch voice-toggle"><input id="auto-voice" type="checkbox"> Auto-speak Quantum Zero answers on this device.</label>
        <p class="help">Voice tries the local open-source Piper engine first, then falls back to your browser voice if the local engine is offline. No paid voice API is used.</p>
      </div>
      <div class="core-strip">
        <div class="orb" aria-hidden="true"></div>
        <div>
          <strong>Quantum Core Orb</strong>
          <span>The original evidence-core stays active beside Agent Zero: visual proof mode, mission focus, and IonQ/Groq telemetry state.</span>
        </div>
      </div>
      <div class="status-pills">
        <div class="pill"><span>Classical engine</span><strong>Groq GPT-OSS 120B</strong></div>
        <div class="pill"><span>Quantum lane</span><strong>IonQ v0.4</strong></div>
        <div class="pill"><span>Default proof</span><strong>Simulator job</strong></div>
        <div class="pill"><span>Lab route</span><strong>/quantumlab</strong></div>
      </div>
    </aside>
  </section>

  <section class="lab-ribbon" aria-label="Quantum Lab protocol stack">
    <article class="lab-card"><strong>Circuit Mission Control</strong><span>Choose simulator or confirmed QPU, set shot limits, create small reproducible proof jobs, and inspect returned IonQ job IDs.</span><em>job-backed</em></article>
    <article class="lab-card"><strong>Private Kernel Builder</strong><span>Question plus optional research packet becomes a server-side kernel. Public output exposes hashes and evidence, not hidden math or private text.</span><em>private by design</em></article>
    <article class="lab-card"><strong>Telemetry Forensics</strong><span>Check IonQ status, recent jobs, individual job records, probability results, backend names, and cache/rate-limit behavior.</span><em>operator tools</em></article>
    <article class="lab-card"><strong>Safety Governor</strong><span>Hardware jobs require explicit confirmation, low usage defaults stay on, and the answer must split evidence from interpretation.</span><em>anti-bs gate</em></article>
    <div class="pipeline">
      <div class="stage"><span>01 kernel</span><strong>Hash + profile</strong><small>Build a private mission seed.</small></div>
      <div class="stage"><span>02 circuit</span><strong>GHZ proof</strong><small>Submit small IonQ-compatible circuits.</small></div>
      <div class="stage"><span>03 telemetry</span><strong>Job ledger</strong><small>Capture ID, backend, status, probabilities.</small></div>
      <div class="stage"><span>04 reasoning</span><strong>Groq synthesis</strong><small>Use evidence as context, not magic.</small></div>
      <div class="stage"><span>05 report</span><strong>Truth split</strong><small>Real, symbolic, risk, next test.</small></div>
    </div>
  </section>

  <main class="layout">
    <aside class="panel">
      <h2>Research Mode</h2>
      <div class="modes" id="modes">
        <button class="mode active" data-profile="zero-network"><strong>Zero Network</strong><span>Coherence, readiness, and installation-state research.</span></button>
        <button class="mode" data-profile="entanglement-probe"><strong>Entanglement Probe</strong><span>Compare two possibilities through a GHZ-style proof circuit.</span></button>
        <button class="mode" data-profile="optimization-oracle"><strong>Optimization Oracle</strong><span>Use quantum-random evidence as a decision-context layer.</span></button>
        <button class="mode" data-profile="signal-integrity"><strong>Signal Integrity</strong><span>Stress-test claims and separate evidence from symbol.</span></button>
      </div>

      <label for="backend">IonQ backend</label>
      <select id="backend">
        <option value="simulator">simulator (safe default)</option>
        <option value="qpu.aria-1">qpu.aria-1</option>
        <option value="qpu.aria-2">qpu.aria-2</option>
        <option value="qpu.forte-1">qpu.forte-1</option>
        <option value="qpu.forte-enterprise-1">qpu.forte-enterprise-1</option>
      </select>

      <label for="shots">Shots</label>
      <input id="shots" type="number" min="100" max="1024" value="128">
      <p class="help">Simulator is capped at 1024 shots. Hardware QPU is capped lower and requires confirmation.</p>

      <label class="switch"><input id="confirm-qpu" type="checkbox"> I understand hardware QPU jobs may cost credits or queue.</label>
      <div class="notice" style="margin-top:14px">Low usage mode is built in: backend status is cached, job checks are cached, proof jobs are rate-limited, and hardware submission is blocked unless you explicitly confirm.</div>

      <div class="small-actions">
        <button class="btn" data-action="status">IonQ Status</button>
        <button class="btn" data-action="diagnose">Diagnose</button>
        <button class="btn" data-action="recent">Recent Jobs</button>
      </div>
    </aside>

    <section class="panel">
      <h2>Quantum Lab Missions</h2>
      <p class="help" style="margin-top:-5px">Advanced Agent Zero tasks. Pick one, then run Quantum Lab when you are ready. Simulator proof is the safe default.</p>
      <div class="mission-deck" id="mission-deck">
        <button class="mission-card" data-task="evidence"><strong>Evidence Check</strong><span>Turn a big claim into a testable quantum-informed research read with a real job ledger.</span><em>Best first run</em></button>
        <button class="mission-card" data-task="entropy"><strong>Quantum Entropy Seed</strong><span>Use IonQ probabilities as a public randomness/creativity signal, not as a secret key.</span><em>Safe experiment</em></button>
        <button class="mission-card" data-task="ab"><strong>Entangled A/B Decision</strong><span>Compare two build paths and ask Quantum Zero to separate signal, bias, risk, and next test.</span><em>Decision support</em></button>
        <button class="mission-card" data-task="optimizer"><strong>Optimization Scout</strong><span>Frame a routing, compute, security, or product problem as a search space with quantum evidence.</span><em>Operator mode</em></button>
        <button class="mission-card" data-task="dispatch"><strong>Quantum Dispatch</strong><span>Send a private-hash message seed to a real IonQ simulator circuit and return the job evidence.</span><em>Send job</em></button>
        <button class="mission-card" data-task="circuit"><strong>Circuit Architect</strong><span>Design the smallest honest circuit experiment for a claim, including shots, backend, expected evidence, and failure modes.</span><em>experiment design</em></button>
        <button class="mission-card" data-task="qpu"><strong>QPU Readiness Gate</strong><span>Decide whether a mission deserves real hardware or should stay on simulator to protect credits and avoid weak evidence.</span><em>credit guard</em></button>
        <button class="mission-card" data-task="forensics"><strong>Telemetry Forensics</strong><span>Audit a job ID or result packet and explain what can be concluded, what cannot, and what to test next.</span><em>lab audit</em></button>
        <button class="mission-card" data-task="agent"><strong>Agent Zero Autopilot Design</strong><span>Design a safe multi-step Agent Zero workflow that uses quantum evidence without giving it blind control.</span><em>Automation</em></button>
        <button class="mission-card" data-task="integrity"><strong>Signal Integrity Audit</strong><span>Stress-test whether an idea is real engineering, symbolic research, or marketing language.</span><em>Anti-BS gate</em></button>
        <button class="mission-card" data-task="report"><strong>Research Report Generator</strong><span>Turn the run into a clear university-style lab note with method, evidence, limitations, and next experiment.</span><em>publish-ready</em></button>
      </div>

      <h2>Quantum Lab Console</h2>
      <label for="question">Question or directive</label>
      <textarea id="question">How can I use a real quantum computer with my AI system in a way that is useful, honest, and stronger than a normal chatbot?</textarea>

      <label for="private-signal">Private research packet (optional)</label>
      <textarea id="private-signal" style="min-height:100px" placeholder="Optional. Raw text stays server-side and is hashed for evidence. Do not paste secrets you do not want processed."></textarea>
      <p class="help">The public ledger only shows hashes and evidence. Private kernel details stay server-side.</p>

      <label for="job-mode">Quantum evidence mode</label>
      <select id="job-mode">
        <option value="auto">Auto proof job (recommended)</option>
        <option value="telemetry">Telemetry only, no new job</option>
        <option value="force">Force fresh proof job</option>
      </select>

      <div class="small-actions">
        <button class="btn primary" id="run">Run Quantum Lab</button>
        <button class="btn gold" id="dispatch">Send Dispatch</button>
        <button class="btn gold" id="packet">Build Public Packet</button>
        <button class="btn red" id="stop">Stop</button>
      </div>
    </section>

    <section class="console">
      <div class="console-head">
        <strong>Quantum Lab Evidence Ledger</strong>
        <div class="small-actions" style="margin:0">
          <button class="btn" id="check-job">Check Job</button>
          <button class="btn" id="get-results">Results</button>
        </div>
      </div>
      <div class="output" id="output">
        <div class="ledger">
          <div class="ledger-row"><span>Status</span><strong>Ready</strong></div>
          <div class="ledger-row"><span>Route</span><strong>Server-side Quantum Lab</strong></div>
        </div>
        <div class="answer">Choose a mode, ask a question, and run Quantum Lab.</div>
      </div>
      <div style="padding:16px 18px;border-top:1px solid rgba(108,255,202,.16)">
        <label for="job-id" style="margin-top:0">IonQ job ID</label>
        <input id="job-id" placeholder="IonQ UUID appears here after a proof job">
      </div>
    </section>
  </main>
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
    const count = Math.min(90, Math.max(34, Math.floor(window.innerWidth / 18)));
    dots = Array.from({length:count}, () => ({
      x:Math.random()*window.innerWidth,
      y:Math.random()*window.innerHeight,
      vx:(Math.random()-.5)*.36,
      vy:(Math.random()-.5)*.36,
      r:Math.random()*1.8+0.5
    }));
  }
  function draw(){
    ctx.clearRect(0,0,window.innerWidth,window.innerHeight);
    ctx.fillStyle = 'rgba(104,247,255,.5)';
    for(const d of dots){
      d.x += d.vx; d.y += d.vy;
      if(d.x < 0 || d.x > window.innerWidth) d.vx *= -1;
      if(d.y < 0 || d.y > window.innerHeight) d.vy *= -1;
      ctx.beginPath(); ctx.arc(d.x,d.y,d.r,0,Math.PI*2); ctx.fill();
    }
    ctx.strokeStyle = 'rgba(0,255,145,.08)';
    for(let i=0;i<dots.length;i++){
      for(let j=i+1;j<dots.length;j++){
        const a=dots[i],b=dots[j],dx=a.x-b.x,dy=a.y-b.y,dist=Math.hypot(dx,dy);
        if(dist<130){ctx.globalAlpha=(130-dist)/130;ctx.beginPath();ctx.moveTo(a.x,a.y);ctx.lineTo(b.x,b.y);ctx.stroke();ctx.globalAlpha=1;}
      }
    }
    requestAnimationFrame(draw);
  }
  size(); draw(); window.addEventListener('resize',size);

  const out = document.getElementById('output');
  if(!out) return;
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
  let profile = 'zero-network';
  let aborter = null;
  let lastAnswerText = '';
  let recognition = null;
  let voiceAudio = null;
  const voiceStatus = document.getElementById('voice-status');
  const supportsSpeech = 'speechSynthesis' in window;
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  function setAgentState(state, message){
    document.body.classList.toggle('is-thinking', state === 'thinking');
    document.body.classList.toggle('is-speaking', state === 'speaking');
    if(voiceStatus) voiceStatus.textContent = message || (state === 'idle' ? 'Ready' : state);
  }
  function speechText(text){
    return String(text || '')
      .replace(/\[[^\]]+\]/g, ' ')
      .replace(/https?:\/\/\S+/g, ' link ')
      .replace(/[{}[\]<>]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .slice(0, 4200);
  }
  function pickVoice(){
    if(!supportsSpeech) return null;
    const voices = window.speechSynthesis.getVoices();
    return voices.find(v => /en-GB|United Kingdom|Ryan|George|Daniel/i.test(v.lang + ' ' + v.name))
      || voices.find(v => /^en/i.test(v.lang))
      || voices[0]
      || null;
  }
  function speak(text){
    const clean = speechText(text);
    if(!clean) return;
    serverSpeak(clean).then(ok => {
      if(!ok) browserSpeak(clean);
    });
  }
  function browserSpeak(clean){
    if(!supportsSpeech){
      setAgentState('idle', 'Speech not supported');
      return;
    }
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(clean);
    const voice = pickVoice();
    if(voice) utterance.voice = voice;
    utterance.rate = 0.96;
    utterance.pitch = 0.82;
    utterance.volume = 1;
    utterance.onstart = () => setAgentState('speaking', 'Speaking');
    utterance.onend = () => setAgentState('idle', 'Ready');
    utterance.onerror = () => setAgentState('idle', 'Speech stopped');
    window.speechSynthesis.speak(utterance);
  }
  async function serverSpeak(clean){
    try{
      setAgentState('thinking', 'Rendering local voice');
      const res = await fetch('/api_quantum_voice.php', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-Token':qzCsrfToken},
        body:JSON.stringify({action:'speak', text:clean, csrf_token:qzCsrfToken})
      });
      const data = await res.json().catch(() => ({status:'fallback'}));
      if(data.status !== 'success' || !data.url) return false;
      if(supportsSpeech) window.speechSynthesis.cancel();
      if(voiceAudio) {
        voiceAudio.pause();
        voiceAudio.removeAttribute('src');
        voiceAudio.load();
      }
      voiceAudio = new Audio(data.url);
      voiceAudio.preload = 'auto';
      voiceAudio.onplay = () => setAgentState('speaking', 'Local Piper voice');
      voiceAudio.onended = () => setAgentState('idle', 'Ready');
      voiceAudio.onerror = () => setAgentState('idle', 'Voice fallback');
      await voiceAudio.play();
      return true;
    } catch(e) {
      return false;
    }
  }
  function stopVoice(){
    if(voiceAudio) {
      voiceAudio.pause();
      voiceAudio.removeAttribute('src');
      voiceAudio.load();
      voiceAudio = null;
    }
    if(supportsSpeech) window.speechSynthesis.cancel();
    if(recognition) {
      try { recognition.stop(); } catch(e) {}
      recognition = null;
    }
    setAgentState('idle', 'Ready');
  }
  function startMic(){
    if(!SpeechRecognition){
      setAgentState('idle', 'Mic unsupported');
      out.innerHTML = '<div class="ledger"><div class="ledger-row"><span>Voice input</span><strong>Unsupported</strong></div></div><div class="answer">This browser does not expose Web Speech microphone recognition. You can still type the prompt and run Quantum Lab.</div>';
      return;
    }
    stopVoice();
    recognition = new SpeechRecognition();
    recognition.lang = 'en-GB';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    recognition.onstart = () => setAgentState('speaking', 'Listening');
    recognition.onerror = () => setAgentState('idle', 'Mic error');
    recognition.onend = () => { if(recognition) setAgentState('idle', 'Ready'); recognition = null; };
    recognition.onresult = event => {
      const text = event.results && event.results[0] && event.results[0][0] ? event.results[0][0].transcript : '';
      if(text) {
        document.getElementById('question').value = text;
        out.innerHTML = `<div class="ledger"><div class="ledger-row"><span>Voice prompt captured</span><strong>${esc(text)}</strong></div></div><div class="answer">Prompt loaded from microphone. Press Run Quantum Lab when ready.</div>`;
      }
    };
    recognition.start();
  }
  const missionPrompts = {
    evidence: {
      profile: 'signal-integrity',
      jobMode: 'auto',
      question: 'Run a Quantum Zero evidence check on this idea: can a real quantum-cloud job make my AI answer stronger? Use IonQ job evidence if available, explain what is physically real, what is only symbolic research framing, and give me the next practical engineering test.'
    },
    entropy: {
      profile: 'entanglement-probe',
      jobMode: 'auto',
      question: 'Create a safe Quantum Entropy Seed experiment. Use the IonQ proof probabilities as a public randomness or creativity signal for brainstorming and optimization, but explain why it must not be treated as a private security key by itself.'
    },
    ab: {
      profile: 'entanglement-probe',
      jobMode: 'auto',
      question: 'Help me compare two possible paths with an entangled A/B decision read. Path A is the obvious safe engineering route. Path B is the ambitious experimental route. Use the quantum evidence ledger as context, then separate signal, bias, risk, reversibility, and the smallest next test.'
    },
    optimizer: {
      profile: 'optimization-oracle',
      jobMode: 'telemetry',
      question: 'Act as an Optimization Scout. Turn my problem into a searchable design space with variables, constraints, risk gates, and test steps. Use IonQ telemetry as the quantum-informed context, conserve API usage, and recommend the strongest next experiment.'
    },
    dispatch: {
      profile: 'zero-network',
      jobMode: 'force',
      question: 'Send this as a Quantum Dispatch: encode the message into a private circuit seed, submit a small IonQ simulator job, and return the IonQ job ID with a clear explanation of what is real evidence and what is not.'
    },
    circuit: {
      profile: 'entanglement-probe',
      jobMode: 'auto',
      question: 'Act as Quantum Lab Circuit Architect. Design the smallest honest IonQ-compatible circuit experiment for my claim. Include goal, circuit shape, backend recommendation, shots, expected evidence, failure modes, what the results can prove, what they cannot prove, and the next engineering step.'
    },
    qpu: {
      profile: 'signal-integrity',
      jobMode: 'telemetry',
      question: 'Run a QPU Readiness Gate. Decide whether this mission deserves real hardware or should remain on simulator. Consider cost, queue time, scientific value, credit protection, evidence quality, and whether simulator proof is enough. Be strict and practical.'
    },
    forensics: {
      profile: 'signal-integrity',
      jobMode: 'telemetry',
      question: 'Run Quantum Lab Telemetry Forensics. Audit the job ledger or result packet, explain what is physically real, what is only context, whether the evidence is strong or weak, and what exact follow-up experiment would improve confidence.'
    },
    agent: {
      profile: 'zero-network',
      jobMode: 'telemetry',
      question: 'Design a safe Agent Zero autopilot workflow that uses Quantum Zero evidence without giving quantum results blind control. Include subagents, stop conditions, logs, rollback, human approval points, and exactly where IonQ jobs should and should not be used.'
    },
    integrity: {
      profile: 'signal-integrity',
      jobMode: 'telemetry',
      question: 'Run a Signal Integrity Audit on Quantum Zero itself. Be strict: identify which parts are real engineering, which parts are symbolic research language, what evidence would strengthen the claim, and what wording avoids deception while still sounding powerful.'
    },
    report: {
      profile: 'zero-network',
      jobMode: 'auto',
      question: 'Generate a Quantum Lab research note for this run. Structure it like: abstract, method, classical AI lane, quantum evidence lane, observed job telemetry, limitations, honest claim wording, next experiment, and public-safe summary. Do not reveal private kernel details.'
    }
  };

  function esc(value){
    return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  }
  function selectProfile(nextProfile){
    profile = nextProfile || 'zero-network';
    document.querySelectorAll('.mode').forEach(x => x.classList.toggle('active', (x.dataset.profile || '') === profile));
  }
  function loadMission(id){
    const task = missionPrompts[id];
    if(!task) return;
    selectProfile(task.profile);
    document.getElementById('question').value = task.question;
    document.getElementById('job-mode').value = task.jobMode || 'auto';
    out.innerHTML = `<div class="ledger"><div class="ledger-row"><span>Mission loaded</span><strong>${esc(id)}</strong></div><div class="ledger-row"><span>Profile</span><strong>${esc(task.profile)}</strong></div><div class="ledger-row"><span>Evidence mode</span><strong>${esc(task.jobMode || 'auto')}</strong></div></div><div class="answer">Mission loaded. Review the prompt, add private context if needed, then press Run Quantum Lab.</div>`;
    document.getElementById('question').focus();
  }
  function read(){
    return {
      profile,
      question: document.getElementById('question').value || '',
      signal_text: document.getElementById('private-signal').value || '',
      backend: document.getElementById('backend').value || 'simulator',
      shots: parseInt(document.getElementById('shots').value || '128', 10),
      confirm_qpu: document.getElementById('confirm-qpu').checked,
      job_mode: document.getElementById('job-mode').value || 'auto'
    };
  }
  function setBusy(text){
    setAgentState('thinking', text);
    out.innerHTML = `<div class="ledger"><div class="ledger-row"><span>Status</span><strong>${esc(text)}</strong></div></div><div class="answer">Working...</div>`;
  }
  function renderLedger(data, answer){
    const ionq = data.ionq || {};
    const job = data.job || {};
    const packet = data.packet || {};
    const rows = [
      ['Agent', 'Quantum Lab / Quantum Zero'],
      ['Model', data.model || 'openai/gpt-oss-120b'],
      ['Mode', data.mode || 'quantum-informed'],
      ['Backend', ionq.backend || job.backend || data.backend || 'simulator'],
      ['Job status', (job.status || ionq.status || data.job_status || 'not submitted')],
      ['Job ID', ionq.id || job.id || 'none'],
      ['Kernel', packet.kernel_id || 'server-side'],
      ['Truth boundary', data.truth_boundary || 'classical LLM + IonQ context; not qubits running the LLM']
    ];
    const htmlRows = rows.map(([k,v]) => `<div class="ledger-row"><span>${esc(k)}</span><strong>${esc(v)}</strong></div>`).join('');
    const packetText = Object.keys(packet).length ? `<h2 style="margin-top:18px">Public Packet</h2><pre>${esc(JSON.stringify(packet,null,2))}</pre>` : '';
    const probs = data.probabilities ? `<h2 style="margin-top:18px">IonQ Probabilities</h2><pre>${esc(JSON.stringify(data.probabilities,null,2))}</pre>` : '';
    const spoken = answer || data.reply || 'No answer returned.';
    lastAnswerText = spoken;
    setAgentState('idle', 'Answer ready');
    out.innerHTML = `<div class="ledger">${htmlRows}</div><div class="answer">${esc(spoken)}</div>${packetText}${probs}`;
    const id = ionq.id || job.id || '';
    if(id) document.getElementById('job-id').value = id;
    if(document.getElementById('auto-voice')?.checked && spoken) speak(spoken);
  }
  async function post(action, payload){
    aborter = new AbortController();
    const res = await fetch('/api_quantum_zero.php', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':qzCsrfToken},
      body:JSON.stringify(Object.assign({action}, payload || {})),
      signal:aborter.signal
    });
    const data = await res.json().catch(() => ({status:'error',message:'Bad JSON response'}));
    if(!res.ok || data.status === 'error') throw new Error(data.message || 'Request failed');
    return data;
  }
  async function run(action){
    try{
      const labels = {analyze:'Running Quantum Lab', packet:'Building public packet', status:'Checking IonQ status', diagnose:'Diagnosing key', recent:'Loading recent jobs', job:'Checking job', results:'Loading results'};
      labels.dispatch = 'Sending Quantum Dispatch';
      setBusy(labels[action] || 'Working');
      let payload = read();
      if(action === 'dispatch') { payload.signal_text = payload.question; payload.job_mode = 'force'; }
      if(action === 'job' || action === 'results') payload = {job_id:document.getElementById('job-id').value.trim()};
      const data = await post(action, payload);
      if(action === 'packet'){
        renderLedger(data, 'Public packet built locally/server-side. No IonQ job was submitted.');
      } else if(action === 'status' || action === 'diagnose' || action === 'recent' || action === 'job' || action === 'results'){
        renderLedger(data, data.message || 'Utility result returned.');
      } else {
        renderLedger(data, data.reply || data.answer);
      }
    }catch(e){
      if(e.name === 'AbortError') {
        setAgentState('idle', 'Stopped');
        out.innerHTML = '<div class="ledger"><div class="ledger-row"><span>Status</span><strong>Stopped</strong></div></div><div class="answer">Request cancelled.</div>';
      } else {
        setAgentState('idle', 'Error');
        out.innerHTML = `<div class="ledger"><div class="ledger-row"><span>Error</span><strong>${esc(e.message)}</strong></div></div><div class="answer">Quantum Lab stopped before completing. If this says key missing, open Studio -> Neural Vault and add Groq plus IonQ keys.</div>`;
      }
    } finally {
      aborter = null;
    }
  }

  document.querySelectorAll('.mode').forEach(btn => btn.addEventListener('click', () => selectProfile(btn.dataset.profile || 'zero-network')));
  document.querySelectorAll('[data-task]').forEach(btn => btn.addEventListener('click', () => loadMission(btn.dataset.task)));
  document.getElementById('run').addEventListener('click', () => run('analyze'));
  document.getElementById('dispatch').addEventListener('click', () => run('dispatch'));
  document.getElementById('packet').addEventListener('click', () => run('packet'));
  document.getElementById('stop').addEventListener('click', () => { if(aborter) aborter.abort(); stopVoice(); });
  document.getElementById('check-job').addEventListener('click', () => run('job'));
  document.getElementById('get-results').addEventListener('click', () => run('results'));
  document.getElementById('voice-intro')?.addEventListener('click', () => speak('Quantum Lab is online. I am the advanced Quantum Zero operator console: Groq reasoning, IonQ telemetry, proof jobs, packet inspection, and a strict evidence boundary. Pick a mission, run a simulator proof when useful, and I will separate physical evidence, symbolic interpretation, risk, and the next experiment.'));
  document.getElementById('speak-last')?.addEventListener('click', () => speak(lastAnswerText || 'Quantum Lab is ready. Run a mission or ask a question first.'));
  document.getElementById('mic-ask')?.addEventListener('click', startMic);
  document.getElementById('silence')?.addEventListener('click', stopVoice);
  document.querySelectorAll('[data-action]').forEach(btn => btn.addEventListener('click', () => run(btn.dataset.action)));
  if(supportsSpeech) window.speechSynthesis.onvoiceschanged = pickVoice;
  fetch('/api_quantum_voice.php', {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-Token':qzCsrfToken},
    body:JSON.stringify({action:'health', csrf_token:qzCsrfToken})
  }).then(r => r.json()).then(data => {
    if(voiceStatus) voiceStatus.textContent = data.available ? 'Local Piper' : (supportsSpeech ? 'Browser fallback' : 'Text mode');
  }).catch(() => {});
  setAgentState('idle', supportsSpeech ? 'Ready' : 'Text mode');
})();
</script>
</body>
</html>
