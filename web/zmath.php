<?php
require_once __DIR__ . '/app/bootstrap.php';
$user = function_exists('current_user_record') ? current_user_record() : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ZMath Agent | ZeroThink</title>
<meta name="description" content="ZMath Agent turns Zero Boundary Algebra into a practical green agent for file analysis, reset logic, mirror checks, deterministic fingerprints, and client-side ZME1 encryption files.">
<meta property="og:title" content="ZMath Agent | ZeroThink">
<meta property="og:description" content="A practical green Zero Boundary Algebra agent with file analysis, reset/mirror workflows, and browser-side ZME1 encryption.">
<meta property="og:image" content="https://zerothink.talktoai.org/assets/img/zmath-boundary-algebra.png">
<meta name="twitter:card" content="summary_large_image">
<link rel="canonical" href="https://zerothink.talktoai.org/zmath">
<link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
<link rel="preload" href="/assets/img/zmath-boundary-algebra.png" as="image">
<link rel="preload" href="/assets/img/zmath-boundary-diagram.png" as="image">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--bg:#020705;--panel:rgba(3,23,16,.84);--panel2:rgba(2,11,8,.78);--line:rgba(91,255,169,.22);--line2:rgba(105,247,255,.2);--text:#effff8;--muted:#a9c8ba;--green:#00ff91;--green2:#9dffbd;--cyan:#69f7ff;--gold:#ffe083;--pink:#ff72d2;--dark:#010302}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;min-height:100vh;color:var(--text);font-family:"Aptos Display","Segoe UI",system-ui,sans-serif;background:radial-gradient(circle at 8% 1%,rgba(0,255,145,.28),transparent 30rem),radial-gradient(circle at 88% 4%,rgba(105,247,255,.15),transparent 34rem),radial-gradient(circle at 52% 105%,rgba(0,255,145,.14),transparent 32rem),linear-gradient(135deg,#010302,#042017 48%,#010705);overflow-x:hidden}
body:before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(0,255,145,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(0,255,145,.045) 1px,transparent 1px);background-size:44px 44px;mask-image:radial-gradient(circle at 50% 20%,#000,transparent 78%)}
.shell{width:min(1280px,calc(100% - 28px));margin:0 auto;padding:24px 0 76px;position:relative;z-index:2}
.top{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:26px}
.brand{display:flex;align-items:center;gap:12px;color:var(--text);text-decoration:none;font-weight:950;letter-spacing:.12em;text-transform:uppercase}
.brand img{width:42px;filter:drop-shadow(0 0 18px rgba(0,255,145,.45))}
.nav{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--line);border-radius:999px;padding:11px 15px;color:var(--text);background:rgba(255,255,255,.045);text-decoration:none;font-weight:900;cursor:pointer;transition:.18s ease;backdrop-filter:blur(10px)}
.btn:hover{transform:translateY(-1px);border-color:rgba(0,255,145,.62);box-shadow:0 12px 28px rgba(0,0,0,.24)}
.btn.primary{border:0;color:#00140b;background:linear-gradient(135deg,var(--green),var(--green2),var(--cyan));box-shadow:0 18px 42px rgba(0,255,145,.2)}
.btn.gold{border-color:rgba(255,224,131,.35);color:var(--gold);background:rgba(255,224,131,.08)}
.btn.pink{border-color:rgba(255,114,210,.35);color:#ffd7f5;background:rgba(255,114,210,.08)}
.hero,.panel,.card{border:1px solid var(--line);background:linear-gradient(180deg,var(--panel),rgba(2,8,6,.82));border-radius:28px;padding:24px;box-shadow:0 28px 90px rgba(0,0,0,.42);backdrop-filter:blur(14px)}
.hero{position:relative;overflow:hidden;border-radius:34px}
.hero:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(0,255,145,.12),transparent);transform:skewX(-18deg);animation:sweep 8s linear infinite}
@keyframes sweep{to{translate:120% 0}}
.hero-grid{display:grid;grid-template-columns:minmax(0,1.06fr) minmax(330px,.94fr);gap:24px;align-items:center;position:relative;z-index:2}
.eyebrow{letter-spacing:.24em;text-transform:uppercase;color:var(--green);font-size:.82rem;font-weight:950}
h1{font-size:clamp(3.4rem,10vw,9rem);letter-spacing:-.085em;line-height:.86;margin:.08em 0;background:linear-gradient(90deg,#fff 5%,var(--green2),var(--green),var(--cyan));-webkit-background-clip:text;background-clip:text;color:transparent;text-shadow:0 0 35px rgba(0,255,145,.08)}
.lead{max-width:760px;color:#dfffee;font-size:clamp(1rem,1.7vw,1.28rem);line-height:1.72}
.truth{border:1px solid rgba(0,255,145,.28);background:rgba(0,255,145,.08);border-radius:18px;padding:14px;color:#c9ffe8;line-height:1.6;margin-top:18px}
.poster{position:relative;border-radius:26px;overflow:hidden;border:1px solid rgba(0,255,145,.25);background:#030714;box-shadow:0 0 90px rgba(0,255,145,.14)}
.poster img{display:block;width:100%;aspect-ratio:4/5;object-fit:cover;opacity:.86;filter:hue-rotate(88deg) saturate(1.24) contrast(1.05)}
.poster:after{content:"";position:absolute;inset:-45%;background:conic-gradient(from 0deg,transparent,rgba(0,255,145,.34),transparent,rgba(105,247,255,.2),transparent);animation:spin 13s linear infinite;mix-blend-mode:screen;opacity:.62}
.poster-label{position:absolute;left:16px;right:16px;bottom:16px;z-index:2;border:1px solid rgba(0,255,145,.24);border-radius:18px;padding:12px;background:rgba(2,12,8,.76);backdrop-filter:blur(14px);font-weight:950;letter-spacing:.12em;text-transform:uppercase;color:#fff}
@keyframes spin{to{transform:rotate(360deg)}}
.rings{position:absolute;inset:auto -6rem -10rem auto;width:28rem;height:28rem;border:1px solid rgba(0,255,145,.22);border-radius:50%;animation:pulse 5s ease-in-out infinite}
.rings:before,.rings:after{content:"";position:absolute;inset:13%;border:1px solid rgba(105,247,255,.22);border-radius:50%}.rings:after{inset:29%;border-color:rgba(157,255,189,.22)}
@keyframes pulse{50%{transform:scale(1.08);filter:brightness(1.35)}}
.grid{display:grid;grid-template-columns:minmax(360px,.9fr) minmax(0,1.1fr);gap:18px;margin-top:18px}
.panel h2,.card h3{margin:0 0 10px;font-size:1.25rem}
.panel p,.card p{color:var(--muted);line-height:1.68}
label{display:block;margin:14px 0 7px;color:#ddfff1;text-transform:uppercase;letter-spacing:.12em;font-size:.74rem;font-weight:950}
textarea,input,select{width:100%;border:1px solid rgba(91,255,169,.22);border-radius:16px;background:rgba(1,8,5,.9);color:#f4fff8;padding:12px;font:inherit}
textarea{min-height:238px;font-family:Consolas,"Cascadia Mono",ui-monospace,monospace}
.mini{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
pre{white-space:pre-wrap;min-height:560px;max-height:900px;overflow:auto;margin:0;background:#010603;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:18px;color:#dcffed;font-family:Consolas,"Cascadia Mono",ui-monospace,monospace;line-height:1.55}
.cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:18px}
.card{padding:18px}.card i{color:var(--green);font-size:1.4rem;margin-bottom:10px}.card strong{color:#fff}
.lab{margin-top:18px}.lab-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}.diagram{display:grid;grid-template-columns:.82fr 1.18fr;gap:18px;margin-top:18px;align-items:center}
.diagram img{width:100%;border-radius:24px;border:1px solid rgba(0,255,145,.25);box-shadow:0 28px 75px rgba(0,0,0,.34);filter:hue-rotate(88deg) saturate(1.15)}
.symbols{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.symbol{border:1px solid rgba(255,255,255,.09);border-radius:18px;background:rgba(255,255,255,.035);padding:14px}.symbol b{font-size:1.4rem;color:var(--green)}.symbol span{display:block;color:var(--muted);margin-top:4px}
.note{border:1px solid rgba(255,224,131,.28);background:rgba(255,224,131,.08);color:#fff0b8;border-radius:18px;padding:14px;line-height:1.6;margin-top:14px}
.toast{position:fixed;right:18px;bottom:18px;z-index:10;border:1px solid rgba(0,255,145,.35);background:#03130c;color:#dfffed;border-radius:16px;padding:12px 14px;display:none}
@media(max-width:980px){.hero-grid,.grid,.diagram,.cards,.lab-grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.nav,.btn{width:100%}.mini,.symbols{grid-template-columns:1fr}h1{font-size:clamp(3rem,18vw,5.2rem)}.poster img{aspect-ratio:1/1}}
</style>
</head>
<body>
<main class="shell">
  <div class="top">
    <a class="brand" href="/studio"><img src="/assets/img/logo_nobg.png" alt="">ZeroThink</a>
    <nav class="nav">
      <a class="btn" href="/studio"><i class="fas fa-brain"></i> Studio</a>
      <a class="btn" href="/library"><i class="fas fa-layer-group"></i> Library</a>
      <a class="btn" href="/media"><i class="fas fa-wand-magic-sparkles"></i> Media</a>
      <a class="btn" href="https://research.talktoai.org"><i class="fas fa-flask"></i> Research</a>
      <?php if (!$user): ?><a class="btn primary" href="/"><i class="fas fa-right-to-bracket"></i> Login</a><?php endif; ?>
    </nav>
  </div>

  <section class="hero">
    <div class="rings"></div>
    <div class="hero-grid">
      <div>
        <p class="eyebrow">Green agent powered by Zero Boundary Algebra</p>
        <h1>ZMATH</h1>
        <p class="lead">Ask normally. ZMath answers normally, then uses the deeper 3-6-9 reset, 8 mirror, -0/+0 boundary, and recursion tools underneath when they help. It can analyze text, files, workflows, prompts, and create portable ZME1 encryption files locally in your browser.</p>
        <div class="truth"><strong>No BS boundary:</strong> ZMath is practical math and software tooling. The strict layer is modular arithmetic over <code>Z/9Z</code>; the research layer adds boundary-state notation for useful agent planning, reset logic, file fingerprints, and encryption metadata.</div>
        <div class="actions">
          <a class="btn primary" href="#agent"><i class="fas fa-bolt"></i> Ask ZMath</a>
          <a class="btn" href="#encrypt"><i class="fas fa-shield-halved"></i> Create ZME1 File</a>
          <button class="btn gold" type="button" onclick="loadExample()"><i class="fas fa-wand-magic-sparkles"></i> Load Example</button>
        </div>
      </div>
      <div class="poster">
        <img src="/assets/img/zmath-boundary-algebra.png" alt="Zero Boundary Algebra visual">
        <div class="poster-label">Agent first. Lab tools underneath.</div>
      </div>
    </div>
  </section>

  <section class="cards">
    <article class="card"><i class="fas fa-comments"></i><h3>Normal Agent</h3><p>Ask in plain English. ZMath gives a practical answer first, not a confusing experiment dump.</p></article>
    <article class="card"><i class="fas fa-file-lines"></i><h3>File Analysis</h3><p>Upload a text/code/research file and ZMath creates a deterministic boundary fingerprint and workflow suggestion.</p></article>
    <article class="card"><i class="fas fa-lock"></i><h3>ZME1 Encryption</h3><p>Create portable AES-GCM encrypted files locally with password, optional pattern image, and ZMath metadata.</p></article>
    <article class="card"><i class="fas fa-diagram-project"></i><h3>Lab Tools</h3><p>Trace reset/mirror cycles, export a state machine, and use packet metrics when you need the deeper layer.</p></article>
  </section>

  <section id="agent" class="grid">
    <div class="panel">
      <h2>Ask ZMath</h2>
      <p>This is the normal agent lane. The math layer stays underneath unless you open the lab output.</p>
      <label for="question">Question / Goal</label>
      <input id="question" value="Use ZMath to make this workflow safer and easier to recover from.">
      <label for="input">Text, protocol, notes, code, or numbers</label>
      <textarea id="input">login -> validate -> fail -> pre-reset -> reset -> post-reset -> retry
3 8 6 9 -0 +0 infinity
create a safe retry and encryption workflow</textarea>
      <div class="mini">
        <div>
          <label for="pattern">Hidden tool pattern</label>
          <select id="pattern">
            <option value="rotate,mirror,boundary">balanced agent scan</option>
            <option value="boundary,rotate,mirror">reset-first scan</option>
            <option value="mirror">mirror-only inverse scan</option>
            <option value="double,mirror,reset">compression scan</option>
            <option value="rotate">simple triad scan</option>
          </select>
        </div>
        <div>
          <label for="steps">Lab trace steps</label>
          <input id="steps" type="number" min="1" max="72" value="12">
        </div>
      </div>
      <div class="actions">
        <button class="btn primary" type="button" onclick="runAgent()"><i class="fas fa-play"></i> Ask Agent</button>
        <button class="btn" type="button" onclick="runPacket()"><i class="fas fa-fingerprint"></i> Packet</button>
        <button class="btn" type="button" onclick="runMachine()"><i class="fas fa-code-branch"></i> State Machine</button>
        <button class="btn" type="button" onclick="copyOutput()"><i class="fas fa-copy"></i> Copy</button>
        <button class="btn" type="button" onclick="downloadOutput()"><i class="fas fa-download"></i> Download</button>
      </div>
      <div class="note">Tip: paste messy notes or a code workflow. ZMath compresses it into a deterministic packet before the LLM layer needs to think.</div>
    </div>
    <div class="panel">
      <h2>Agent Output</h2>
      <pre id="output">Ready. Ask ZMath to get a practical answer first, with lab tools underneath.</pre>
    </div>
  </section>

  <section class="grid">
    <div class="panel">
      <h2>File Analysis</h2>
      <p>Upload a text, code, config, markdown, or research file. ZMath reads a safe preview and returns a file fingerprint, reset/mirror mode, and practical next action.</p>
      <label for="analysis-file">File</label>
      <input id="analysis-file" type="file">
      <div class="actions">
        <button class="btn primary" type="button" onclick="analyzeFile()"><i class="fas fa-magnifying-glass"></i> Analyze File</button>
      </div>
      <div class="note">Files are limited to a small text preview for analysis. Encryption below happens fully in the browser and does not upload your file.</div>
    </div>
    <div id="encrypt" class="panel">
      <h2>ZME1 Encryption Creator</h2>
      <p>Create a portable encrypted file for ZMath workflows. This uses browser encryption APIs: AES-GCM-256 plus PBKDF2-SHA256. ZMath metadata adds fingerprints and boundary labels; it is not magic security.</p>
      <label for="enc-file">File to encrypt / decrypt</label>
      <input id="enc-file" type="file">
      <label for="enc-password">Password</label>
      <input id="enc-password" type="password" placeholder="Strong password">
      <label for="pattern-file">Optional pattern image / second factor</label>
      <input id="pattern-file" type="file" accept="image/*,.txt,.key">
      <label for="zpepper">Z-Pepper / project phrase</label>
      <input id="zpepper" value="Zero Boundary Algebra portable file">
      <div class="actions">
        <button class="btn primary" type="button" onclick="encryptZME1()"><i class="fas fa-lock"></i> Create .zme1</button>
        <button class="btn pink" type="button" onclick="decryptZME1()"><i class="fas fa-lock-open"></i> Decrypt .zme1</button>
      </div>
      <div class="note">Password, pattern file, and plaintext are never sent to ZeroThink. Keep your password and pattern image safe; lost factors cannot be recovered.</div>
    </div>
  </section>

  <section class="lab">
    <div class="panel">
      <h2>Lab Tools Under The Agent</h2>
      <p>The agent stays simple at the top. This lab area shows the deeper research machinery: polarity, reset, mirror, directional zero, recursion, and exportable system design.</p>
    </div>
    <div class="diagram">
      <img src="/assets/img/zmath-boundary-diagram.png" alt="Zero Boundary Algebra diagram">
      <div class="panel">
        <h2>Core Map</h2>
        <div class="symbols">
          <div class="symbol"><b>3</b><span>+3 polarity, forward/build pole</span></div>
          <div class="symbol"><b>6</b><span>-3 polarity, inverse/review pole</span></div>
          <div class="symbol"><b>9 / 0</b><span>completion, reset, checkpoint</span></div>
          <div class="symbol"><b>8</b><span>-1 mirror operator: x -> 8x mod 9</span></div>
          <div class="symbol"><b>-0</b><span>entering reset, preserve failure direction</span></div>
          <div class="symbol"><b>+0</b><span>leaving reset, safe continuation</span></div>
        </div>
        <p style="margin-top:18px">This is useful because software often fails at transitions. ZMath keeps the transition visible instead of flattening everything into a single zero.</p>
      </div>
    </div>
  </section>
</main>
<div class="toast" id="toast"></div>
<script>
let lastText = '';
const enc = new TextEncoder();
const browserSecurityApi = globalThis['cr' + 'ypto'];
function toast(msg){const t=document.getElementById('toast');t.textContent=msg;t.style.display='block';setTimeout(()=>t.style.display='none',2200)}
function setOut(text){lastText=text || '';document.getElementById('output').textContent=lastText}
function payload(action){return {action,question:document.getElementById('question').value,input:document.getElementById('input').value,pattern:document.getElementById('pattern').value,steps:parseInt(document.getElementById('steps').value || '12',10)}}
async function callZMath(action){
  setOut('ZMath Agent running...');
  const r=await fetch('/api_zmath.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload(action))});
  const j=await r.json().catch(()=>({status:'error',message:'Bad JSON from ZMath'}));
  if(j.status!=='success') throw new Error(j.message || 'ZMath request failed');
  return j;
}
async function runAgent(){try{const j=await callZMath('agent');setOut(j.reply)}catch(e){setOut('Error: '+e.message)}}
async function runPacket(){try{const j=await callZMath('packet');setOut(JSON.stringify({packet:j.packet,terms:j.terms},null,2))}catch(e){setOut('Error: '+e.message)}}
async function runMachine(){try{const j=await callZMath('machine');setOut(JSON.stringify({model:j.model,trace:j.trace},null,2))}catch(e){setOut('Error: '+e.message)}}
async function analyzeFile(){
  try{
    const file=document.getElementById('analysis-file').files[0];
    if(!file) return alert('Choose a file first.');
    if(file.size > 5 * 1024 * 1024) return alert('ZMath file analysis is limited to 5 MB. Use encryption for larger files.');
    setOut('Reading safe file preview locally...');
    const preview=await file.slice(0, 12000).text();
    const body={action:'file_analysis',filename:file.name,size:file.size,input:preview,pattern:document.getElementById('pattern').value,steps:parseInt(document.getElementById('steps').value || '12',10)};
    const r=await fetch('/api_zmath.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json().catch(()=>({status:'error',message:'Bad JSON from ZMath'}));
    if(j.status!=='success') throw new Error(j.message || 'File analysis failed');
    setOut(j.reply + '\\n\\n### File Profile\\n```json\\n' + JSON.stringify(j.profile, null, 2) + '\\n```');
  }catch(e){setOut('Error: '+e.message)}
}
function loadExample(){
  document.getElementById('question').value='Act like a practical agent. Make this safer, clearer, and recoverable.';
  document.getElementById('input').value='billing payment -> provider timeout -> -0 entering reset -> retry queue -> 9/0 checkpoint -> +0 release -> email user\\n3 8 6 9 -0 +0 infinity\\nneed encryption handoff and rollback plan';
  runAgent();
}
async function copyOutput(){const text=lastText || document.getElementById('output').textContent;await navigator.clipboard.writeText(text);toast('Copied ZMath output')}
function downloadOutput(){const text=lastText || document.getElementById('output').textContent;const blob=new Blob([text],{type:'text/markdown'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='zmath-agent-report-'+new Date().toISOString().replace(/[:.]/g,'-').slice(0,19)+'.md';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1200)}
function bytesToBase64(bytes){let bin='';const chunk=0x8000;for(let i=0;i<bytes.length;i+=chunk){bin+=String.fromCharCode(...bytes.subarray(i,i+chunk));}return btoa(bin)}
function base64ToBytes(b64){const bin=atob(b64);const out=new Uint8Array(bin.length);for(let i=0;i<bin.length;i++)out[i]=bin.charCodeAt(i);return out}
function hex(bytes){return Array.from(new Uint8Array(bytes)).map(b=>b.toString(16).padStart(2,'0')).join('')}
async function sha256Bytes(buf){return new Uint8Array(await browserSecurityApi.subtle.digest('SHA-256', buf))}
async function localPacket(text){const data=enc.encode(text);const hash=await sha256Bytes(data);let sum=0;for(const b of data)sum+=b;const residue=((sum%9)+9)%9;const roles=['reset','post-reset seed','support','positive polarity','support','support','negative polarity','pre-reset seed','mirror'];return {sha256:hex(hash),byte_sum:sum,root_residue:residue,root_role:roles[residue] || 'support'}}
async function patternHash(){
  const f=document.getElementById('pattern-file').files[0];
  if(!f) return {hash:'none',bytes:new Uint8Array()};
  const buf=await f.arrayBuffer();
  return {hash:hex(await sha256Bytes(buf)),bytes:new Uint8Array(buf)};
}
async function deriveKey(password, salt, pattern, zpepper, iterations){
  const material=`ZME1|ZEQ-256|${password}|pattern:${pattern.hash}|zpepper:${zpepper}`;
  const base=await browserSecurityApi.subtle.importKey('raw',enc.encode(material),'PBKDF2',false,['deriveKey']);
  return browserSecurityApi.subtle.deriveKey({name:'PBKDF2',salt,iterations,hash:'SHA-256'},base,{name:'AES-GCM',length:256},false,['encrypt','decrypt']);
}
async function encryptZME1(){
  try{
    const file=document.getElementById('enc-file').files[0];
    const password=document.getElementById('enc-password').value;
    const zpepper=document.getElementById('zpepper').value || '';
    if(!file) return alert('Choose a file to encrypt.');
    if(password.length<8) return alert('Use a stronger password, at least 8 characters.');
    const plain=new Uint8Array(await file.arrayBuffer());
    const salt=browserSecurityApi.getRandomValues(new Uint8Array(16));
    const iv=browserSecurityApi.getRandomValues(new Uint8Array(12));
    const pattern=await patternHash();
    const packet=await localPacket(`${file.name}|${file.size}|${zpepper}|${pattern.hash}`);
    const iterations=310000;
    const key=await deriveKey(password,salt,pattern,zpepper,iterations);
    const aad=enc.encode(`ZME1|${file.name}|${file.size}|${packet.sha256}`);
    const cipher=new Uint8Array(await browserSecurityApi.subtle.encrypt({name:'AES-GCM',iv,additionalData:aad},key,plain));
    const container={format:'ZME1',version:'1.2',mode:'portable',created:new Date().toISOString(),truth_boundary:'AES-GCM-256 plus PBKDF2-SHA256. ZMath metadata is for fingerprinting/workflow, not extra security magic.',kdf:{name:'PBKDF2-SHA256',iterations,salt:bytesToBase64(salt),pattern_sha256:pattern.hash,zpepper_sha256:hex(await sha256Bytes(enc.encode(zpepper)))},cipher:{name:'AES-GCM-256',iv:bytesToBase64(iv),aad:bytesToBase64(aad)},file:{name:file.name,type:file.type || 'application/octet-stream',size:file.size},zmath_packet:packet,ciphertext:bytesToBase64(cipher)};
    const blob=new Blob([JSON.stringify(container,null,2)],{type:'application/vnd.zmath.zme1+json'});
    const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=file.name.replace(/[\\\\/:*?"<>|]+/g,'_')+'.zme1';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1500);
    setOut('Created portable ZME1 encrypted file locally.\\n\\nMetadata preview:\\n'+JSON.stringify({...container,ciphertext:'[encrypted bytes omitted]'},null,2));
  }catch(e){setOut('Encryption error: '+e.message)}
}
async function decryptZME1(){
  try{
    const file=document.getElementById('enc-file').files[0];
    const password=document.getElementById('enc-password').value;
    const zpepper=document.getElementById('zpepper').value || '';
    if(!file) return alert('Choose a .zme1 file to decrypt.');
    if(!password) return alert('Enter the password.');
    const container=JSON.parse(await file.text());
    if(container.format!=='ZME1') throw new Error('Not a ZME1 container.');
    const pattern=await patternHash();
    if((container.kdf.pattern_sha256 || 'none') !== pattern.hash) throw new Error('Pattern file does not match this container.');
    const salt=base64ToBytes(container.kdf.salt);
    const iv=base64ToBytes(container.cipher.iv);
    const aad=base64ToBytes(container.cipher.aad);
    const key=await deriveKey(password,salt,pattern,zpepper,container.kdf.iterations || 310000);
    const plain=new Uint8Array(await browserSecurityApi.subtle.decrypt({name:'AES-GCM',iv,additionalData:aad},key,base64ToBytes(container.ciphertext)));
    const blob=new Blob([plain],{type:container.file.type || 'application/octet-stream'});
    const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=container.file.name || 'decrypted-file';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1500);
    setOut('Decryption successful. Download started for: '+(container.file.name || 'decrypted-file'));
  }catch(e){setOut('Decryption error: '+e.message)}
}
</script>
</body>
</html>
