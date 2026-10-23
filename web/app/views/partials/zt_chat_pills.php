<?php /* ZT_CHAT_PILLS_V3 */
  $isPro = false;
  if (function_exists('is_pro')) { $isPro = (bool)@is_pro(); }
  elseif (function_exists('current_user')) {
    $u = @current_user();
    $isPro = !empty($u) && (int)($u['is_pro'] ?? 0) === 1;
  }
?>
<style>
  /* ZT_CHAT_PILLS_V3 styles (scoped-ish) */
  .zt-pillbar-wrap{ margin: 10px 0 6px 0; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  .zt-pillbar{
    display:flex; gap:10px; flex-wrap:wrap;
    padding:10px 12px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.10);
    background: rgba(10,10,16,.35);
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
  }
  .zt-pill{
    appearance:none; border:1px solid rgba(255,255,255,.12);
    background: rgba(255,255,255,.05);
    color: rgba(255,255,255,.82);
    padding:7px 12px;
    border-radius:999px;
    font-weight:600;
    letter-spacing:.2px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:8px;
    transition: transform .08s ease, background .15s ease, border-color .15s ease, color .15s ease;
    user-select:none;
  }
  .zt-pill:active{ transform: translateY(1px); }
  .zt-pill:hover{ border-color: rgba(255,255,255,.22); background: rgba(255,255,255,.08); }
  .zt-pill .dot{
    width:8px; height:8px; border-radius:50%;
    background: rgba(255,255,255,.35);
    box-shadow: 0 0 0 2px rgba(255,255,255,.08);
  }
  .zt-pill.active{
    color: #fff;
    border-color: rgba(164, 92, 255, .45);
    background: linear-gradient(135deg, rgba(140,70,255,.35), rgba(60,220,255,.12));
  }
  .zt-pill.active .dot{ background: rgba(164,92,255,.95); box-shadow: 0 0 0 2px rgba(164,92,255,.20); }

  .zt-upgrade-modal{
    position:fixed; inset:0; z-index:9999;
    display:none; align-items:center; justify-content:center;
    background: rgba(0,0,0,.55);
    backdrop-filter: blur(6px);
    padding: 24px;
  }
  .zt-upgrade-card{
    width:min(560px, 100%);
    border-radius: 18px;
    border: 1px solid rgba(255,255,255,.12);
    background: rgba(16,16,24,.88);
    box-shadow: 0 30px 90px rgba(0,0,0,.45);
    padding: 18px;
    color: rgba(255,255,255,.9);
  }
  .zt-upgrade-card h3{ margin:0 0 8px 0; font-size: 18px; }
  .zt-upgrade-card p{ margin:0 0 14px 0; color: rgba(255,255,255,.72); line-height: 1.45; }
  .zt-upgrade-actions{ display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; }
  .zt-btn{
    display:inline-flex; align-items:center; justify-content:center;
    border-radius: 12px; padding: 10px 12px;
    border:1px solid rgba(255,255,255,.14);
    background: rgba(255,255,255,.06);
    color:#fff; text-decoration:none; font-weight:700;
  }
  .zt-btn:hover{ background: rgba(255,255,255,.10); }
  .zt-btn.primary{
    border-color: rgba(164,92,255,.45);
    background: linear-gradient(135deg, rgba(164,92,255,.45), rgba(60,220,255,.14));
  }
</style>

<div class="zt-pillbar-wrap" id="zt-pillbar-wrap">
  <div class="zt-pillbar" id="zt-pillbar" aria-label="Provider selection">
