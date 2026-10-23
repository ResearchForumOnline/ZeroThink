<?php
require_once __DIR__ . '/app/bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location:/');
    exit;
}
$csrfToken = function_exists('zt_csrf_token') ? zt_csrf_token() : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Zero Library | ZeroThink</title>
<meta name="description" content="Zero Library privately retrieves ordinary notes and code without model tokens. Sensitive DNA and genomic files stay vault-only and outside search or model context.">
<link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--bg:#020504;--panel:rgba(5,18,15,.86);--panel2:rgba(7,28,23,.84);--line:rgba(103,255,203,.2);--text:#edfff8;--muted:#9fb8af;--green:#00ff91;--cyan:#70f7ff;--gold:#ffd36f;--red:#ff6d7b}
*{box-sizing:border-box}body{margin:0;min-height:100vh;color:var(--text);font-family:Inter,Segoe UI,system-ui,sans-serif;background:radial-gradient(circle at 8% 0%,rgba(0,255,145,.18),transparent 32rem),radial-gradient(circle at 92% 10%,rgba(112,247,255,.14),transparent 26rem),linear-gradient(180deg,#020504,#071310 58%,#020504)}body:before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(112,247,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(112,247,255,.035) 1px,transparent 1px);background-size:36px 36px}
.shell{position:relative;z-index:1;width:min(1180px,calc(100% - 28px));margin:0 auto;padding:24px 0 60px}.top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:28px}.brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--text)}.brand img{width:44px;filter:drop-shadow(0 0 16px rgba(0,255,145,.45))}.brand strong{letter-spacing:.1em;text-transform:uppercase}.nav{display:flex;gap:10px;flex-wrap:wrap}.btn{border:1px solid var(--line);border-radius:999px;background:rgba(2,10,8,.72);color:var(--text);padding:10px 14px;font-weight:850;text-decoration:none;cursor:pointer}.btn.primary{background:linear-gradient(135deg,var(--green),var(--cyan));color:#00110b;border:0}.hero,.panel{border:1px solid var(--line);background:var(--panel);border-radius:28px;box-shadow:0 28px 90px rgba(0,0,0,.45);backdrop-filter:blur(18px)}.hero{padding:34px;margin-bottom:18px}.eyebrow{color:var(--green);letter-spacing:.22em;text-transform:uppercase;font-size:.78rem;font-weight:900}.hero h1{font-size:clamp(2.6rem,8vw,6.5rem);line-height:.9;margin:14px 0;background:linear-gradient(90deg,#dffff4,var(--green),var(--cyan));-webkit-background-clip:text;color:transparent}.hero p{max-width:820px;color:#c6d9d1;line-height:1.75;font-size:1.08rem}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.panel{padding:22px}.panel h2{margin:0 0 12px}.drop{border:1px dashed rgba(112,247,255,.45);border-radius:20px;padding:20px;background:rgba(0,0,0,.22)}input,textarea{width:100%;border:1px solid var(--line);background:#020807;color:var(--text);border-radius:16px;padding:13px 14px}textarea{min-height:150px;font-family:ui-monospace,Consolas,monospace}.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.muted{color:var(--muted);line-height:1.65}.hits{display:grid;gap:12px;margin-top:14px}.hit{border:1px solid rgba(112,247,255,.14);border-radius:18px;padding:14px;background:rgba(0,0,0,.24)}pre{white-space:pre-wrap;word-break:break-word;background:#010403;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:14px;max-height:420px;overflow:auto;color:#dfffee}.statbar{display:flex;gap:10px;flex-wrap:wrap}.stat{border:1px solid rgba(0,255,145,.18);border-radius:16px;padding:12px 14px;background:rgba(0,255,145,.06);min-width:140px}.stat b{display:block;font-size:1.35rem;color:var(--green)}@media(max-width:860px){.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.hero{padding:24px}.btn{width:100%;justify-content:center}.nav{width:100%}}
</style>
</head>
<body>
<div class="shell">
  <div class="top">
    <a class="brand" href="/studio"><img src="/assets/img/logo_nobg.png" alt=""><div><strong>Zero Library</strong><div class="muted">no-token retrieval layer</div></div></a>
    <nav class="nav">
      <a class="btn" href="/studio">Studio</a>
      <a class="btn" href="/dna">DNA Lab</a>
      <a class="btn" href="/media">Media Lab</a>
      <a class="btn" href="/quantumzero">QuantumZero</a>
      <a class="btn" href="/zmath">ZMath</a>
      <a class="btn" href="/nottingham-research">Nottingham Research</a>
      <button class="btn primary" onclick="reindex()">Rebuild Index</button>
    </nav>
  </div>
  <section class="hero">
    <div class="eyebrow">Private deterministic memory</div>
    <h1>Zero Library</h1>
    <p>Upload code, notes, markdown, logs, configs, and research text for deterministic private retrieval. Ordinary reference text is searchable without an LLM. Recognized sensitive DNA/genomic files and configs containing detected credentials use a separate vault lane: Zero Library catalogues only their metadata and never puts their contents into search, RAG evidence packs, or model context.</p>
    <p class="muted"><b>Account-private by design:</b> files are stored in your own server-side, user-scoped library directory and searches only read your account index. The public web cannot browse the storage folder. Use <a href="/dna" style="color:var(--cyan)">DNA Lab</a> when you intentionally want a private DNA analysis.</p>
    <div class="statbar" id="stats"><div class="stat"><b>--</b> files catalogued</div><div class="stat"><b>--</b> searchable</div><div class="stat"><b>--</b> vault-only</div><div class="stat"><b>Private</b> account scope</div></div>
  </section>
  <div class="grid">
    <section class="panel">
      <h2><i class="fa-solid fa-cloud-arrow-up"></i> Add Files</h2>
      <p class="muted">Use this for persistent private reference context. The search layer runs locally on the server before any optional model call. Saved files are capped at <b>5 MB each</b> to keep the service fast for everyone.</p>
      <div class="drop">
        <input type="file" id="file" multiple>
        <div class="row" style="margin-top:12px"><button class="btn primary" onclick="uploadFiles()">Upload / Vault Files</button><span id="uploadState" class="muted" aria-live="polite"></span></div>
      </div>
      <p class="muted"><b>Searchable:</b> ordinary code, text, markdown, JSON, CSV, TSV, logs, and non-secret configs. <b>Vault-only:</b> recognized FASTA/FASTQ/GenBank, VCF, raw genotype exports, other detected genomic sequence content, and files containing detected credentials/private keys. Vault-only contents cannot be found with this search box or sent through <b>@library</b>.</p>
    </section>
    <section class="panel">
      <h2><i class="fa-solid fa-magnifying-glass"></i> Search Without Tokens</h2>
      <input id="query" placeholder="Search non-sensitive references, e.g. payment callback, qwen model, BRCA research notes">
      <div class="row" style="margin-top:12px">
        <button class="btn primary" onclick="searchLibrary()">Search</button>
        <button class="btn" onclick="contextPack()">Build Evidence Pack</button>
      </div>
      <div id="results" class="hits"></div>
    </section>
  </div>
  <section class="panel" style="margin-top:18px">
    <h2><i class="fa-solid fa-brain"></i> How To Use In Chat</h2>
    <p class="muted">In Studio, type prompts like: <b>@library find the PayPal subscription activation code and check for bugs</b> or <b>@library summarize my non-sensitive genomics research notes using only supported snippets</b>. Zero pulls a compact pack from searchable reference text only; raw DNA and genomic sequences remain vault-only.</p>
    <pre id="contextOut">Evidence packs appear here when generated.</pre>
  </section>
</div>
<script>
const MAX_LIBRARY_FILE_BYTES = 5 * 1024 * 1024;
const MAX_LIBRARY_FILE_LABEL = '5 MB';
const CSRF_TOKEN = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
async function api(payload){const r=await fetch('/api_library.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});const j=await r.json();if(j.status!=='success')throw new Error(j.message||'Library error');return j;}
async function refresh(){try{const j=await api({action:'status'});const lib=j.library||{};const catalogued=Number(lib.catalogued_count??lib.count??0)||0;const searchable=Number(lib.searchable_count??catalogued)||0;const vaultOnly=Number(lib.vault_only_count??Math.max(0,catalogued-searchable))||0;document.getElementById('stats').innerHTML=`<div class="stat"><b>${catalogued}</b> files catalogued</div><div class="stat"><b>${searchable}</b> searchable</div><div class="stat"><b>${vaultOnly}</b> vault-only</div><div class="stat"><b>0</b> token calls for search</div><div class="stat"><b>${esc((j.scope&&j.scope.mode)||'private')}</b> account scope</div><div class="stat"><b>${esc((j.scope&&j.scope.max_saved_file_mb)||5)} MB</b> max file save</div>`;}catch(e){document.getElementById('stats').innerHTML=`<div class="stat"><b>!</b> ${esc(e.message)}</div>`;}}
async function reindex(){await api({action:'index',csrf_token:CSRF_TOKEN});await refresh();alert('Zero Library catalogue and searchable index rebuilt. Vault-only contents stayed excluded.');}
async function uploadFiles(){const files=[...document.getElementById('file').files];if(!files.length)return alert('Choose files first.');const tooLarge=files.find(f=>f.size>MAX_LIBRARY_FILE_BYTES);if(tooLarge)return alert(`${tooLarge.name} is too large to save. Zero Library saves are limited to ${MAX_LIBRARY_FILE_LABEL} per file.`);const state=document.getElementById('uploadState');let searchable=0;let vaultOnly=0;try{for(const f of files){state.textContent='Uploading '+f.name;const fd=new FormData();fd.append('file',f);fd.append('csrf_token',CSRF_TOKEN);const r=await fetch('/api_library.php',{method:'POST',body:fd});const j=await r.json();if(j.status!=='success')throw new Error(j.message||'Upload failed');if(j.file&&j.file.indexable===false)vaultOnly++;else searchable++;}state.textContent=`Catalogued ${files.length} file(s): ${searchable} searchable, ${vaultOnly} vault-only.`;await refresh();}catch(e){state.textContent='Upload stopped: '+e.message;}}
function esc(s){return String(s||'').replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));}
async function searchLibrary(){const q=document.getElementById('query').value.trim();const box=document.getElementById('results');box.innerHTML='Searching...';const j=await api({action:'search',query:q,limit:8});box.innerHTML=(j.hits||[]).map(h=>`<div class="hit"><b>${esc(h.path)}</b><div class="muted">score ${h.score} · ${h.lines} lines · sha256 ${esc((h.sha256||'').slice(0,16))}</div><pre>${esc(h.snippet)}</pre></div>`).join('')||'<p class="muted">No matching library snippets yet.</p>';}
async function contextPack(){const q=document.getElementById('query').value.trim();const j=await api({action:'context',query:q,max_chars:18000});document.getElementById('contextOut').textContent=j.context||'No evidence pack generated.';}
refresh();
</script>
</body>
</html>
