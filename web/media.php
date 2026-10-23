<?php
require_once __DIR__ . '/app/bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location:/');
    exit;
}
$media_csrf_token = zt_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Media Studio | ZeroThink</title>
<meta name="description" content="ZeroThink Media Studio creates downloadable AI voice, image, and video assets from authenticated user vault keys or Pro access.">
<link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--bg:#04050b;--panel:rgba(9,13,24,.82);--panel2:rgba(12,21,34,.78);--line:rgba(125,210,255,.22);--text:#f3fbff;--muted:#aab9c9;--blue:#6aa7ff;--cyan:#70f7ff;--green:#00ff91;--pink:#ff76d9;--gold:#ffd76f;--red:#ff657a}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;min-height:100vh;color:var(--text);font-family:Inter,Segoe UI,system-ui,sans-serif;background:radial-gradient(circle at 12% 0%,rgba(255,118,217,.17),transparent 31rem),radial-gradient(circle at 86% 6%,rgba(106,167,255,.24),transparent 31rem),radial-gradient(circle at 58% 96%,rgba(0,255,145,.1),transparent 28rem),linear-gradient(180deg,#03040a,#08101d 56%,#03050a)}
body:before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(112,247,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(112,247,255,.035) 1px,transparent 1px);background-size:44px 44px;mask-image:linear-gradient(180deg,#000,transparent 90%)}
.shell{position:relative;z-index:1;width:min(1260px,calc(100% - 28px));margin:0 auto;padding:24px 0 72px}
.top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px}
.brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--text)}
.brand img{width:46px;filter:drop-shadow(0 0 18px rgba(112,247,255,.45))}
.brand b{letter-spacing:.12em;text-transform:uppercase}
.nav{display:flex;gap:10px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--line);border-radius:999px;background:rgba(0,0,0,.24);color:var(--text);padding:11px 15px;text-decoration:none;font-weight:900;cursor:pointer;transition:.18s ease}
.btn:hover{transform:translateY(-1px);border-color:rgba(112,247,255,.45);box-shadow:0 10px 28px rgba(0,0,0,.24)}
.btn.primary{border:0;color:#00111b;background:linear-gradient(135deg,var(--cyan),var(--blue))}
.btn.green{border:0;color:#00120b;background:linear-gradient(135deg,var(--green),var(--cyan))}
.btn.gold{border-color:rgba(255,215,111,.36);color:var(--gold);background:rgba(255,215,111,.08)}
.btn.danger{border-color:rgba(255,101,122,.35);color:#ffc1ca;background:rgba(255,101,122,.08)}
.hero,.panel,.asset{border:1px solid var(--line);background:linear-gradient(180deg,var(--panel),rgba(4,7,14,.88));border-radius:30px;box-shadow:0 30px 90px rgba(0,0,0,.45);backdrop-filter:blur(18px)}
.hero{padding:34px;margin-bottom:18px;overflow:hidden;position:relative}
.hero:after{content:"";position:absolute;inset:-40% -20%;background:conic-gradient(from 90deg,transparent,rgba(112,247,255,.14),transparent,rgba(255,118,217,.12),transparent);animation:spin 18s linear infinite;opacity:.62;z-index:-1}
@keyframes spin{to{transform:rotate(1turn)}}
.eyebrow{letter-spacing:.22em;text-transform:uppercase;color:var(--cyan);font-weight:950;font-size:.8rem}
.hero h1{font-size:clamp(2.8rem,8vw,7.2rem);line-height:.88;margin:12px 0;background:linear-gradient(90deg,#fff,var(--cyan),var(--pink));-webkit-background-clip:text;background-clip:text;color:transparent;letter-spacing:-.07em}
.hero p{max-width:870px;color:#cad9e6;line-height:1.78;font-size:1.08rem}
.status-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:18px}
.status-card{border:1px solid rgba(255,255,255,.09);border-radius:18px;padding:14px;background:rgba(0,0,0,.22)}
.status-card span{display:block;color:var(--muted);font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;font-weight:900}
.status-card b{display:block;margin-top:6px;font-size:1.14rem;color:#fff}
.workspace{display:grid;grid-template-columns:minmax(330px,.82fr) minmax(0,1.18fr);gap:18px}
.panel{padding:22px}
.panel h2{margin:0 0 8px;font-size:1.24rem}
.muted{color:var(--muted);line-height:1.65}
.tabs{display:grid;gap:10px}
.tab{border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:14px;background:rgba(0,0,0,.22);cursor:pointer;color:var(--text);text-align:left;font:inherit}
.tab strong{display:block;font-size:1rem}
.tab span{display:block;color:var(--muted);font-size:.9rem;margin-top:3px}
.tab.active{border-color:rgba(112,247,255,.55);background:linear-gradient(135deg,rgba(112,247,255,.13),rgba(255,118,217,.08));box-shadow:0 0 0 1px rgba(112,247,255,.12) inset}
.tool{display:none}
.tool.active{display:block}
textarea,input,select{width:100%;border:1px solid var(--line);background:#030714;color:var(--text);border-radius:17px;padding:13px 14px;font:inherit}
textarea{min-height:178px;resize:vertical}
label{display:block;color:#dffaff;font-size:.78rem;font-weight:950;letter-spacing:.1em;text-transform:uppercase;margin:14px 0 7px}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.output{border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:16px;background:rgba(0,0,0,.26);min-height:220px;overflow:auto}
.preview{width:100%;border:1px solid rgba(255,255,255,.1);border-radius:20px;background:#02040a;overflow:hidden}
.preview img,.preview video{display:block;width:100%;height:auto}
.asset-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.asset{padding:14px;border-radius:22px}
.asset h3{margin:8px 0 6px;font-size:1rem}
.asset small{color:var(--muted)}
.asset .thumb{height:150px;border-radius:16px;background:rgba(0,0,0,.28);display:grid;place-items:center;overflow:hidden;border:1px solid rgba(255,255,255,.08)}
.asset .thumb img,.asset .thumb video{width:100%;height:100%;object-fit:cover}
.asset .thumb i{font-size:2.2rem;color:var(--cyan)}
.preset-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}
.preset{border:1px solid rgba(255,255,255,.09);background:rgba(0,0,0,.22);border-radius:16px;padding:12px;color:#dff;cursor:pointer;text-align:left}
.preset:hover{border-color:rgba(112,247,255,.45)}
pre{white-space:pre-wrap;word-break:break-word;color:#dff;background:#02050b;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:14px}
.notice{border:1px solid rgba(255,215,111,.22);background:rgba(255,215,111,.08);color:#ffe5a3;border-radius:18px;padding:13px;line-height:1.6}
@media(max-width:1040px){.workspace,.status-grid{grid-template-columns:1fr}.asset-grid,.preset-grid{grid-template-columns:1fr 1fr}}
@media(max-width:720px){.top{align-items:flex-start;flex-direction:column}.nav{width:100%}.btn{width:100%}.form-grid,.asset-grid,.preset-grid{grid-template-columns:1fr}.hero{padding:24px}.panel{padding:18px}}
</style>
</head>
<body>
<main class="shell">
  <div class="top">
    <a class="brand" href="/studio"><img src="/assets/img/logo_nobg.png" alt=""><div><b>ZeroThink Media</b><div class="muted">downloadable voice, image, video assets</div></div></a>
    <nav class="nav">
      <a class="btn" href="/studio"><i class="fa-solid fa-brain"></i> Studio</a>
      <a class="btn" href="/library"><i class="fa-solid fa-book"></i> Library</a>
      <a class="btn" href="/dna"><i class="fa-solid fa-dna"></i> DNA</a>
      <a class="btn" href="/settings"><i class="fa-solid fa-key"></i> Vault Keys</a>
    </nav>
  </div>

  <section class="hero">
    <div class="eyebrow">Creative output layer</div>
    <h1>Media Studio</h1>
    <p>Generate polished voiceovers, images, and Pro video jobs through your saved vault keys or Pro service lane. xAI Grok Imagine is the recommended video default, SeedDance / BytePlus Ark is the cinematic advanced lane, and Sora remains only as a legacy fallback.</p>
    <div class="status-grid" id="statusGrid">
      <div class="status-card"><span>Status</span><b>Checking...</b></div>
      <div class="status-card"><span>Voice</span><b>gpt-4o-mini-tts</b></div>
      <div class="status-card"><span>Image</span><b>gpt-image-2</b></div>
      <div class="status-card"><span>Video</span><b>grok-imagine-video</b></div>
    </div>
  </section>

  <section class="workspace">
    <aside class="panel">
      <h2>Choose A Lane</h2>
      <p class="muted">Keep prompts specific. ZeroThink stores generated previews in this browser only; download the files you want to keep.</p>
      <div class="tabs">
        <button class="tab active" data-tab="voice" onclick="switchTool('voice')"><strong><i class="fa-solid fa-volume-high"></i> Voice Studio</strong><span>AI narration, intros, explainer audio, character voice direction.</span></button>
        <button class="tab" data-tab="image" onclick="switchTool('image')"><strong><i class="fa-solid fa-image"></i> Image Studio</strong><span>Product visuals, thumbnails, research diagrams, hero art.</span></button>
        <button class="tab" data-tab="video" onclick="switchTool('video')"><strong><i class="fa-solid fa-video"></i> Pro Video Studio</strong><span>xAI Grok Imagine, SeedDance, legacy Sora fallback, MP4 downloads.</span></button>
        <button class="tab" data-tab="assets" onclick="switchTool('assets')"><strong><i class="fa-solid fa-folder-open"></i> Downloads Shelf</strong><span>Recent browser-local assets with download buttons.</span></button>
      </div>
      <div class="notice" style="margin-top:14px"><b>Disclosure:</b> voice output is AI-generated. Users should not present AI voice as a human recording.</div>
    </aside>

    <div class="panel">
      <section id="tool-voice" class="tool active">
        <h2><i class="fa-solid fa-wave-square"></i> Voice Studio</h2>
        <p class="muted">Create MP3 voice assets that users can play and download immediately.</p>
        <label for="voiceText">Script</label>
        <textarea id="voiceText">Welcome to ZeroThink. I am Agent Zero, ready to help you build, research, and create.</textarea>
        <div class="form-grid">
          <div><label for="voice">Voice</label><select id="voice"><option>marin</option><option>cedar</option><option>coral</option><option>alloy</option><option>ash</option><option>ballad</option><option>nova</option><option>verse</option><option>sage</option><option>shimmer</option><option>onyx</option><option>echo</option><option>fable</option></select></div>
          <div><label for="voiceStyle">Direction</label><input id="voiceStyle" value="Confident, warm, futuristic, clear product narrator."></div>
        </div>
        <div class="actions"><button class="btn primary" onclick="speech()"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate MP3</button><button class="btn" onclick="loadVoicePreset()">Use Sales Intro</button></div>
        <div id="voiceOut" class="output" style="margin-top:14px"><p class="muted">Your audio player and download button will appear here.</p></div>
      </section>

      <section id="tool-image" class="tool">
        <h2><i class="fa-solid fa-palette"></i> Image Studio</h2>
        <p class="muted">Generate downloadable PNG visuals. Use this for hero images, thumbnails, product cards, concept art, and social previews.</p>
        <label for="imagePrompt">Image Prompt</label>
        <textarea id="imagePrompt">A cinematic ZeroThink quantum research console, green and cyan glow, premium product shot, high detail, clean composition.</textarea>
        <div class="form-grid">
          <div><label for="imageModel">Model</label><select id="imageModel"><option>gpt-image-2</option><option>gpt-image-1.5</option><option>gpt-image-1</option><option>gpt-image-1-mini</option></select></div>
          <div><label for="imageSize">Size</label><select id="imageSize"><option>1024x1024</option><option>1024x1536</option><option>1536x1024</option></select></div>
        </div>
        <div class="form-grid">
          <div><label for="imageQuality">Quality</label><select id="imageQuality"><option value="">Model default</option><option>low</option><option>medium</option><option>high</option></select></div>
          <div><label for="imageName">File Name</label><input id="imageName" value="zerothink-image.png"></div>
        </div>
        <div class="preset-grid">
          <button class="preset" onclick="setImagePreset(0)">Premium AI website hero</button>
          <button class="preset" onclick="setImagePreset(1)">Crypto utility card</button>
          <button class="preset" onclick="setImagePreset(2)">DNA research visual</button>
        </div>
        <div class="actions"><button class="btn primary" onclick="image()"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate PNG</button></div>
        <div id="imageOut" class="output" style="margin-top:14px"><p class="muted">Generated images and download buttons will appear here.</p></div>
      </section>

      <section id="tool-video" class="tool">
        <h2><i class="fa-solid fa-film"></i> Pro Video Studio</h2>
        <p class="muted">Start a provider job, check status, then download the MP4 when the provider marks it complete. Pro service-lane jobs are private to the account that started them; pasting a manual provider ID requires your own saved provider key. Recommended: xAI Grok Imagine. SeedDance is the advanced cinematic BytePlus lane. Sora is kept only as legacy fallback.</p>
        <label for="videoPrompt">Video Prompt</label>
        <textarea id="videoPrompt">A silent futuristic AI command center with quantum lattice particles forming the ZERO logo, premium cinematic lighting, no text, smooth camera motion.</textarea>
        <div class="form-grid">
          <div><label for="videoProvider">Provider</label><select id="videoProvider" onchange="syncVideoModels()"><option value="xai">xAI Grok Imagine</option><option value="seedance">SeedDance / BytePlus Ark</option><option value="openai">OpenAI Sora Legacy</option></select></div>
          <div><label for="videoModel">Model</label><select id="videoModel"></select></div>
        </div>
        <div class="form-grid">
          <div><label for="videoRatio">Aspect Ratio</label><select id="videoRatio"><option>16:9</option><option>9:16</option><option>1:1</option><option>4:3</option><option>3:4</option><option>21:9</option><option>adaptive</option></select></div>
          <div><label for="videoResolution">Resolution</label><select id="videoResolution"><option>720p</option><option>480p</option><option>1080p</option></select></div>
        </div>
        <div class="form-grid">
          <div><label for="videoSeconds">Seconds</label><select id="videoSeconds"><option>5</option><option>8</option><option>10</option><option>12</option><option>15</option></select></div>
          <div><label for="videoId">Video Job ID</label><input id="videoId" placeholder="video_... appears here"></div>
        </div>
        <div class="actions"><label style="display:flex;align-items:center;gap:8px;margin:0;color:var(--muted);font-size:.92rem;text-transform:none;letter-spacing:0"><input id="videoAudio" type="checkbox" style="width:auto"> SeedDance audio</label><label style="display:flex;align-items:center;gap:8px;margin:0;color:var(--muted);font-size:.92rem;text-transform:none;letter-spacing:0"><input id="videoDraft" type="checkbox" style="width:auto"> SeedDance cheap draft</label></div>
        <div class="actions"><button class="btn primary" onclick="video()"><i class="fa-solid fa-play"></i> Start Pro Job</button><button class="btn" onclick="videoStatus()"><i class="fa-solid fa-rotate"></i> Check Status</button><button class="btn green" onclick="downloadVideo()"><i class="fa-solid fa-download"></i> Download MP4</button><button class="btn" onclick="loadVideoPreset()"><i class="fa-solid fa-wand-magic-sparkles"></i> Premium Prompt</button></div>
        <div id="videoOut" class="output" style="margin-top:14px"><pre>Video jobs can take time. Start a job, keep the id and provider selected, then check status until completed. Pro-only.</pre></div>
      </section>

      <section id="tool-assets" class="tool">
        <h2><i class="fa-solid fa-box-archive"></i> Downloads Shelf</h2>
        <p class="muted">This shelf is local to this browser. Download important assets because browser storage can be cleared.</p>
        <div class="actions"><button class="btn" onclick="renderAssets()"><i class="fa-solid fa-rotate"></i> Refresh</button><button class="btn danger" onclick="clearAssets()"><i class="fa-solid fa-trash"></i> Clear Shelf</button></div>
        <div id="assetShelf" class="asset-grid" style="margin-top:14px"></div>
      </section>
    </div>
  </section>
</main>
<script>
const ASSET_KEY='zt_media_assets_v2';
const MEDIA_CSRF=<?= json_encode($media_csrf_token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const VIDEO_MODELS={
  xai:['grok-imagine-video'],
  seedance:['seedance-1-5-pro-251215','dreamina-seedance-2-0-fast-260128','dreamina-seedance-2-0-260128','seedance-1-0-pro-fast-251015','seedance-1-0-pro-250528'],
  openai:['sora-2','sora-2-pro']
};
function $(id){return document.getElementById(id);}
function esc(s){return String(s ?? '').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function slug(s){return String(s||'zerothink-asset').toLowerCase().replace(/[^a-z0-9._-]+/g,'-').replace(/^-+|-+$/g,'').slice(0,80)||'zerothink-asset';}
function stamp(){return new Date().toISOString().replace(/[:.]/g,'-').slice(0,19);}
function switchTool(name){document.querySelectorAll('.tool').forEach(el=>el.classList.toggle('active',el.id==='tool-'+name));document.querySelectorAll('.tab').forEach(el=>el.classList.toggle('active',el.dataset.tab===name));if(name==='assets')renderAssets();}
async function api(payload){const r=await fetch('/api_media.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-ZeroThink-CSRF':MEDIA_CSRF},body:JSON.stringify(payload)});const j=await r.json().catch(()=>({status:'error',message:'Media API returned an unreadable response.'}));if(j.status!=='success'){const ref=j.request_id&&!String(j.message||'').includes(j.request_id)?' Reference ID: '+j.request_id:'';throw new Error((j.message||'Media API error')+ref);}return j;}
function assets(){try{return JSON.parse(localStorage.getItem(ASSET_KEY)||'[]')}catch(e){return []}}
function saveAsset(asset){const list=assets();list.unshift({...asset,created_at:new Date().toISOString()});localStorage.setItem(ASSET_KEY,JSON.stringify(list.slice(0,18)));renderAssets();}
function dataDownload(dataUrl,name,label='Download'){return `<a class="btn green" download="${esc(name)}" href="${esc(dataUrl)}"><i class="fa-solid fa-download"></i> ${esc(label)}</a>`;}
function renderAssets(){const box=$('assetShelf');if(!box)return;const list=assets();box.innerHTML=list.map((a,i)=>{let thumb='<i class="fa-solid fa-file-arrow-down"></i>';if(a.kind==='image')thumb=`<img src="${esc(a.url)}" alt="">`;if(a.kind==='video')thumb=`<video src="${esc(a.url)}" muted playsinline></video>`;return `<article class="asset"><div class="thumb">${thumb}</div><h3>${esc(a.name)}</h3><small>${esc(a.kind)} · ${new Date(a.created_at).toLocaleString()}</small><div class="actions">${a.url?dataDownload(a.url,a.name,'Download'):''}<button class="btn danger" onclick="removeAsset(${i})">Remove</button></div></article>`}).join('')||'<p class="muted">No assets yet. Generate voice or images first.</p>';}
function removeAsset(i){const list=assets();list.splice(i,1);localStorage.setItem(ASSET_KEY,JSON.stringify(list));renderAssets();}
function clearAssets(){if(confirm('Clear this browser asset shelf?')){localStorage.removeItem(ASSET_KEY);renderAssets();}}
async function refreshStatus(){try{const j=await api({action:'status'});$('statusGrid').innerHTML=`<div class="status-card"><span>Access</span><b>${j.is_pro?'Pro video unlocked':'Pro required for video'}</b></div><div class="status-card"><span>xAI Video</span><b>${j.has_xai_key?'Key ready':'Add key'}</b></div><div class="status-card"><span>SeedDance</span><b>${j.has_seedance_key?'Key ready':'Add Ark key'}</b></div><div class="status-card"><span>OpenAI Media</span><b>${j.has_openai_key?'Voice/Image ready':'Add key'}</b></div>`;}catch(e){$('statusGrid').innerHTML=`<div class="status-card"><span>Status</span><b>${esc(e.message)}</b></div>`;}}
function loadVoicePreset(){$('voiceText').value='ZeroThink turns your AI account into a full creative, coding, research, and automation workspace. Ask, build, generate, and download your result without leaving the platform.';$('voiceStyle').value='Premium SaaS launch narrator. Clear, confident, energetic, no hype.';}
function setImagePreset(i){const presets=['A premium AI SaaS homepage hero for ZeroThink, luminous green and cyan interface, glass panels, agent workspace, cinematic product render, no unreadable text','A premium Pro access dashboard for an AI research platform, private account status, clean verified access motif, no fake logos','A DNA research lab visual with glowing helix, secure private library nodes, clinical clean interface, cyan and emerald lighting, research-only feel'];$('imagePrompt').value=presets[i]||presets[0];}
async function speech(){const out=$('voiceOut');out.innerHTML='<p class="muted">Generating speech...</p>';try{const j=await api({action:'speech',text:$('voiceText').value,voice:$('voice').value,instructions:$('voiceStyle').value});const name='zerothink-voice-'+stamp()+'.mp3';out.innerHTML=`<div class="preview" style="padding:16px"><audio controls src="${esc(j.data_url)}" style="width:100%"></audio></div><div class="actions">${dataDownload(j.data_url,name,'Download MP3')}</div>`;saveAsset({kind:'voice',name,url:j.data_url});}catch(e){out.innerHTML='<pre>Error: '+esc(e.message)+'</pre>';}}
async function image(){const out=$('imageOut');out.innerHTML='<p class="muted">Generating image...</p>';try{const payload={action:'image',prompt:$('imagePrompt').value,model:$('imageModel').value,size:$('imageSize').value};if($('imageQuality').value)payload.quality=$('imageQuality').value;const j=await api(payload);const images=j.images||[];if(!images.length){out.innerHTML='<pre>No image returned.</pre>';return;}out.innerHTML=images.map((src,i)=>{const name=slug(($('imageName').value||'zerothink-image.png').replace(/\.png$/i,''))+'-'+stamp()+(i?'-'+(i+1):'')+'.png';saveAsset({kind:'image',name,url:src});return `<div class="preview"><img src="${esc(src)}" alt="Generated image"></div><div class="actions">${dataDownload(src,name,'Download PNG')}</div>`;}).join('');}catch(e){out.innerHTML='<pre>Error: '+esc(e.message)+'</pre>';}}
function syncVideoModels(){const provider=$('videoProvider').value;const models=VIDEO_MODELS[provider]||VIDEO_MODELS.xai;$('videoModel').innerHTML=models.map(m=>`<option>${esc(m)}</option>`).join('');$('videoAudio').disabled=provider!=='seedance';$('videoDraft').disabled=provider!=='seedance';if(provider==='xai'){$('videoRatio').value='16:9';$('videoResolution').value='720p';$('videoSeconds').value='10';}if(provider==='seedance'){$('videoRatio').value='16:9';$('videoResolution').value='720p';$('videoSeconds').value='5';}if(provider==='openai'){$('videoRatio').value='9:16';$('videoResolution').value='720p';$('videoSeconds').value='4';}}
function loadVideoPreset(){$('videoPrompt').value='A cinematic product-launch video for ZeroThink: luminous AI command workspace, clean emerald and cyan interface, subtle quantum lattice particles, human-friendly technology, premium camera movement, no readable text, no copyrighted characters, no logos except abstract ZERO geometry.';}
function videoReady(provider,status){status=String(status||'').toLowerCase();return (provider==='xai'&&status==='done')||(provider==='seedance'&&status==='succeeded')||(provider==='openai'&&status==='completed');}
async function video(){const out=$('videoOut');out.innerHTML='<p class="muted">Starting Pro video job...</p>';try{const provider=$('videoProvider').value;const j=await api({action:'video',provider,prompt:$('videoPrompt').value,model:$('videoModel').value,aspect_ratio:$('videoRatio').value,ratio:$('videoRatio').value,resolution:$('videoResolution').value,seconds:$('videoSeconds').value,generate_audio:$('videoAudio').checked,draft:$('videoDraft').checked});$('videoId').value=(j.job&&j.job.id)||'';out.innerHTML='<pre>'+esc(JSON.stringify(j.job,null,2))+'</pre><div class="notice">Provider: '+esc(provider)+'. Keep this job id and check status until complete.</div>';}catch(e){out.innerHTML='<pre>Error: '+esc(e.message)+'</pre>';}}
async function videoStatus(){const id=$('videoId').value.trim();if(!id)return alert('Paste a video job id first.');const provider=$('videoProvider').value;const out=$('videoOut');out.innerHTML='<p class="muted">Checking video status...</p>';try{const j=await api({action:'video_status',provider,id});const status=(j.job&&j.job.status)||'';const ready=videoReady(provider,status);let media='';if(j.video_url){media=`<div class="preview"><video src="${esc(j.video_url)}" controls playsinline></video></div>`;saveAsset({kind:'video',name:'zerothink-'+provider+'-'+stamp()+'.mp4',url:j.video_url});}out.innerHTML=media+'<pre>'+esc(JSON.stringify(j.job,null,2))+'</pre>'+(ready&&j.download_url?`<div class="actions"><a class="btn green" href="${esc(j.download_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-download"></i> Download MP4</a>${j.last_frame_url?`<a class="btn" href="${esc(j.last_frame_url)}" target="_blank" rel="noopener"><i class="fa-solid fa-image"></i> Last Frame</a>`:''}${j.thumbnail_url?`<a class="btn" href="${esc(j.thumbnail_url)}" download><i class="fa-solid fa-image"></i> Thumbnail</a>`:''}</div>`:'<p class="muted">Not completed yet. Check again in a little while.</p>');}catch(e){out.innerHTML='<pre>Error: '+esc(e.message)+'</pre>';}}
function downloadVideo(variant='video'){const id=$('videoId').value.trim();if(!id)return alert('Paste a completed video job id first.');const provider=$('videoProvider').value;const url='/api_media.php?action=video_download&provider='+encodeURIComponent(provider)+'&id='+encodeURIComponent(id)+'&variant='+encodeURIComponent(variant);window.open(url,'_blank','noopener');}
syncVideoModels();refreshStatus();renderAssets();
</script>
</body>
</html>
