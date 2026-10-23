<?php
// zt_pills.php  (UI-only; safe in production)
$u = function_exists('current_user') ? current_user() : null;
$isPro = function_exists('is_pro') ? (is_pro($u) ? 1 : 0) : 0;
?>
<!-- ZT_PILLS_V4 -->
<style>
  /* Pills row (Agentz-like, but keep your purple theme) */
  .zt-pillbar{
    display:flex; gap:10px; align-items:center; flex-wrap:wrap;
    padding:10px 12px; margin-top:10px;
    border-radius:16px;
    background: rgba(15, 14, 30, 0.35);
    border: 1px solid rgba(255,255,255,0.08);
    backdrop-filter: blur(10px);
  }
  .zt-pill{
    user-select:none; cursor:pointer;
    padding:8px 14px;
    border-radius:999px;
    font-weight:600; letter-spacing:0.2px;
    border:1px solid rgba(255,255,255,0.12);
    background: rgba(0,0,0,0.18);
    color: rgba(240,240,255,0.92);
    transition: transform .08s ease, background .15s ease, border-color .15s ease;
    display:inline-flex; gap:8px; align-items:center;
  }
  .zt-pill:hover{ transform: translateY(-1px); border-color: rgba(255,255,255,0.20); }
  .zt-pill[data-provider="zerothink"]{ border-color: rgba(176,90,255,0.55); }
  .zt-pill.is-active{
    background: rgba(176,90,255,0.22);
    border-color: rgba(176,90,255,0.75);
    box-shadow: 0 0 0 2px rgba(176,90,255,0.10) inset;
  }
  .zt-dot{
    width:8px; height:8px; border-radius:999px;
    background: rgba(255,255,255,0.35);
  }
  .zt-pill.is-active .zt-dot{ background: rgba(176,90,255,0.95); }

  /* Fix ugly white native selects (Provider dropdown) */
  select, .select, .select select{
    background: rgba(15, 14, 30, 0.35) !important;
    color: rgba(240,240,255,0.92) !important;
    border: 1px solid rgba(255,255,255,0.10) !important;
    border-radius: 12px !important;
    outline: none !important;
  }
  select option{
    background: #0e1020 !important;
    color: rgba(240,240,255,0.95) !important;
  }

  /* Upgrade modal */
  .zt-modal{
    position:fixed; inset:0; z-index:9999;
    display:none; align-items:center; justify-content:center;
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(6px);
  }
  .zt-modal .box{
    width:min(520px, 92vw);
    border-radius:18px;
    background: rgba(20,18,40,0.96);
    border:1px solid rgba(255,255,255,0.10);
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    padding:18px;
    color: rgba(240,240,255,0.94);
  }
  .zt-modal h3{ margin:0 0 8px; }
  .zt-modal p{ margin:0 0 14px; opacity:0.9; }
  .zt-modal .row{ display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; }
  .zt-btn{
    padding:10px 14px; border-radius:12px; font-weight:700;
    border:1px solid rgba(255,255,255,0.12);
    background: rgba(0,0,0,0.20);
    color: rgba(240,240,255,0.94);
    cursor:pointer;
  }
  .zt-btn.primary{
    background: rgba(176,90,255,0.25);
    border-color: rgba(176,90,255,0.60);
  }
</style>

<div class="zt-pillbar" id="zt-pillbar">
  <div class="zt-pill" data-provider="zerothink"><span class="zt-dot"></span>ZeroThink</div>
  <div class="zt-pill" data-provider="groq"><span class="zt-dot"></span>Groq</div>
  <div class="zt-pill" data-provider="gemini"><span class="zt-dot"></span>Gemini</div>
  <div class="zt-pill" data-provider="openai"><span class="zt-dot"></span>OpenAI</div>
  <div class="zt-pill" data-provider="anthropic"><span class="zt-dot"></span>Claude</div>
</div>

<div class="zt-modal" id="zt-upgrade-modal" aria-hidden="true">
  <div class="box">
    <h3>ZeroThink is a Pro feature</h3>
    <p>Upgrade to unlock ZeroThink mode.</p>
    <div class="row">
<button class="zt-btn" type="button" id="zt-upgrade-cancel">Cancel</button>
      <a class="zt-btn primary" href="/upgrade">Upgrade</a>
    </div>
  </div>
</div>

<script>
(function(){
  const isPro = <?php echo (int)$isPro; ?>;
  const bar = document.getElementById('zt-pillbar');
  const modal = document.getElementById('zt-upgrade-modal');
  const cancel = document.getElementById('zt-upgrade-cancel');

  function openModal(){ modal.style.display='flex'; modal.setAttribute('aria-hidden','false'); }
  function closeModal(){ modal.style.display='none'; modal.setAttribute('aria-hidden','true'); }
  cancel && cancel.addEventListener('click', closeModal);
  modal && modal.addEventListener('click', (e)=>{ if(e.target === modal) closeModal(); });

  // Find provider select (top bar) OR ensure a hidden provider field exists in the message form.
  function findProviderControl(){
    let sel = document.querySelector('select[name="provider"], #provider');
    if(sel) return {type:'select', el: sel};

    // fallback: attach hidden input to the first form that contains the main textarea
    const ta = document.querySelector('textarea');
    if(!ta) return null;
    const form = ta.closest('form') || document.querySelector('form');
    if(!form) return null;

    let hid = form.querySelector('input[name="provider"]');
    if(!hid){
      hid = document.createElement('input');
      hid.type = 'hidden';
      hid.name = 'provider';
      form.appendChild(hid);
    }
    return {type:'hidden', el: hid, form};
  }

  const ctl = findProviderControl();

  // If provider <select> exists but has no options, populate it.
  function ensureSelectOptions(sel){
    if(!sel) return;
    if(sel.options && sel.options.length > 0) return;

    const opts = [
      ['auto','Auto'],
      ['zerothink','ZeroThink'],
      ['groq','Groq'],
      ['gemini','Gemini'],
      ['openai','OpenAI (GPT-4)'],
      ['anthropic','Anthropic (Claude)'],
      ['xai','xAI']
    ];
    opts.forEach(([v,t])=>{
      const o=document.createElement('option');
      o.value=v; o.textContent=t;
      sel.appendChild(o);
    });
  }

  // Map pill -> backend provider value
  const MAP = {
    zerothink: 'zerothink',
    groq: 'groq',
    gemini: 'gemini',
    openai: 'openai',
    anthropic: 'anthropic'
  };

  function setActive(p){
    const v = MAP[p] || p;
    localStorage.setItem('zt_provider', p);

    // UI active state
    bar.querySelectorAll('.zt-pill').forEach(x=>x.classList.toggle('is-active', x.dataset.provider===p));

    // sync to backend control
    if(!ctl) return;
    if(ctl.type === 'select'){
      ensureSelectOptions(ctl.el);
      // if select doesn't contain value, add it
      if(!Array.from(ctl.el.options).some(o=>o.value===v)){
        const o=document.createElement('option'); o.value=v; o.textContent=v;
        ctl.el.appendChild(o);
      }
      ctl.el.value = v;
      ctl.el.dispatchEvent(new Event('change', {bubbles:true}));
    } else {
      ctl.el.value = v;
    }
  }

  if(ctl && ctl.type==='select') ensureSelectOptions(ctl.el);

  bar.addEventListener('click', (e)=>{
    const btn = e.target.closest('.zt-pill');
    if(!btn) return;
    const p = btn.dataset.provider;
    if(p === 'zerothink' && !isPro){ openModal(); return; }
    setActive(p);
  });

  // restore
  const saved = localStorage.getItem('zt_provider') || 'groq';
  setActive(saved);
})();
</script>
