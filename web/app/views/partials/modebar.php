<?php
// ZT_MODEBAR_V3
$u = function_exists('current_user') ? current_user() : null;
$isPro = false;
if (function_exists('is_pro')) {
  try { $isPro = is_pro($u); } catch (\Throwable $e) { $isPro = false; }
} else {
  $isPro = !empty($u) && (int)($u['is_pro'] ?? 0) === 1;
}
?>
<style>
  .zt-modebar-wrap{position:fixed;left:50%;transform:translateX(-50%);bottom:92px;z-index:9999;width:min(920px,calc(100vw - 28px));pointer-events:none}
  .zt-modebar{pointer-events:auto;display:flex;gap:10px;align-items:center;justify-content:flex-start;
    padding:10px 12px;border-radius:16px;
    background:rgba(20,20,24,.62);backdrop-filter:blur(18px);
    border:1px solid rgba(255,255,255,.09);
    box-shadow:0 10px 30px rgba(0,0,0,.35)
  }
  .zt-pill{cursor:pointer;user-select:none;display:inline-flex;align-items:center;gap:8px;
    padding:8px 12px;border-radius:999px;font:600 13px/1.0 system-ui,-apple-system,Segoe UI,Roboto,Arial;
    color:rgba(255,255,255,.78);
    border:1px solid rgba(255,255,255,.10);
    background:rgba(255,255,255,.04);
    transition:transform .12s ease,background .12s ease,border-color .12s ease,color .12s ease
  }
  .zt-pill:hover{transform:translateY(-1px);background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.16);color:#fff}
  .zt-pill.is-active{background:rgba(120,80,255,.18);border-color:rgba(160,120,255,.35);color:#fff}
  .zt-dot{width:8px;height:8px;border-radius:999px;background:rgba(255,255,255,.35)}
  .zt-pill.is-active .zt-dot{background:rgba(170,140,255,.95)}
  .zt-prolock{opacity:.75}
  .zt-prolock .zt-dot{background:rgba(255,200,120,.55)}
  .zt-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:10000;background:rgba(0,0,0,.55)}
  .zt-modal .card{width:min(520px,calc(100vw - 28px));border-radius:18px;padding:18px 18px 14px;
    background:rgba(18,18,22,.92);backdrop-filter:blur(16px);
    border:1px solid rgba(255,255,255,.10);box-shadow:0 18px 50px rgba(0,0,0,.55);color:#fff
  }
  .zt-modal h3{margin:0 0 8px;font:800 18px/1.2 system-ui}
  .zt-modal p{margin:0 0 14px;color:rgba(255,255,255,.80);font:500 14px/1.45 system-ui}
  .zt-actions{display:flex;gap:10px;justify-content:flex-end}
  .zt-btn{cursor:pointer;border-radius:12px;padding:10px 12px;border:1px solid rgba(255,255,255,.14);
    background:rgba(255,255,255,.06);color:#fff;font:700 13px/1 system-ui
  }
  .zt-btn.primary{background:rgba(120,80,255,.28);border-color:rgba(160,120,255,.42)}
</style>

<div class="zt-modebar-wrap">
  <div class="zt-modebar" id="ztModebar"
       data-is-pro="<?= $isPro ? '1' : '0' ?>">
    ZeroThink
    </div>
    <div class="zt-pill" data-provider="groq"><span class="zt-dot"></span> Groq</div>
    <div class="zt-pill" data-provider="gemini"><span class="zt-dot"></span> Gemini</div>
    <div class="zt-pill" data-provider="gpt4"><span class="zt-dot"></span> GPT-4</div>
    <div class="zt-pill" data-provider="claude"><span class="zt-dot"></span> Claude</div>
  </div>
</div>

<div class="zt-modal" id="ztUpgradeModal">
  <div class="card">
    <h3>ZeroThink is a Pro feature</h3>
    <p>Unlock ZeroThink Mode with Pro. Click Upgrade to continue.</p>
    <div class="zt-actions">
<button class="zt-btn" id="ztCloseModal">Cancel</button>
<button class="zt-btn primary" id="ztGoUpgrade">Upgrade</button>
    </div>
  </div>
</div>

<script>
(function(){
  const bar = document.getElementById('ztModebar');
  if(!bar) return;
  const isPro = bar.getAttribute('data-is-pro') === '1';
  const modal = document.getElementById('ztUpgradeModal');
  const close = document.getElementById('ztCloseModal');
  const go = document.getElementById('ztGoUpgrade');

  const KEY = 'zt_provider';
  function setActive(p){
    localStorage.setItem(KEY, p);
    bar.querySelectorAll('.zt-pill').forEach(el=>{
      el.classList.toggle('is-active', el.dataset.provider === p);
    });

    // also try to push into any existing hidden/provider inputs if your UI has them
    const candidates = [
      document.querySelector('input[name="provider"]'),
      document.querySelector('#provider'),
      document.querySelector('select[name="provider"]')
    ].filter(Boolean);
    candidates.forEach(el=>{
      try { el.value = p; el.dispatchEvent(new Event('change', {bubbles:true})); } catch(e){}
    });
  }

  const saved = localStorage.getItem(KEY) || 'groq';
  setActive(saved);

  bar.addEventListener('click', (e)=>{
    const btn = e.target.closest('.zt-pill');
    if(!btn) return;
    const p = btn.dataset.provider;

    if(p === 'zerothink' && !isPro){
      if(modal) modal.style.display = 'flex';
      return;
    }
    setActive(p);
  });

  if(close) close.onclick = ()=> modal.style.display = 'none';
  if(modal) modal.addEventListener('click', (e)=>{ if(e.target === modal) modal.style.display='none'; });

  if(go) go.onclick = ()=>{
    window.location.href = '/upgrade';
  };
})();
</script>
