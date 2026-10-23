<?php
// ZT_MODEBAR_TITAN_V5 (Corrected Labels & Models)
$__zt_is_pro = 0;
if (function_exists('is_pro')) { try { $__zt_is_pro = is_pro() ? 1 : 0; } catch (Throwable $e) { $__zt_is_pro = 0; } }
?>
<style>
/* TITAN GLASS CSS */
.zt-modebar-wrap{ margin: 15px 0; }
.zt-modebar{
  display:flex; gap:12px; flex-wrap:wrap; align-items:center;
  padding:12px 18px;
  border:1px solid rgba(255,255,255,.10);
  border-radius:24px;
  background: rgba(10,10,18,.55);
  backdrop-filter: blur(14px);
  box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}

/* MODEL DROPDOWN */
.zt-select {
  appearance: none;
  background: rgba(0,0,0,0.45);
  border: 1px solid rgba(255,255,255,.12);
  color: #fff;
  padding: 8px 14px;
  border-radius: 12px;
  font-family: inherit; font-size: 0.9rem; font-weight: 500;
  outline: none; cursor: pointer;
  min-width: 220px;
  transition: 0.2s;
}
.zt-select:hover { border-color: #a855f7; background: rgba(0,0,0,0.7); }
.zt-select option { background: #111; color: #ddd; padding: 10px; }

/* PILLS */
.zt-pill{
  appearance:none; border:1px solid rgba(255,255,255,.14);
  background: rgba(255,255,255,.06);
  color: rgba(255,255,255,.88);
  padding:8px 16px;
  border-radius:999px;
  font-weight:600; font-size:13px;
  cursor:pointer; transition: 0.2s;
}
.zt-pill:hover{ background: rgba(255,255,255,.10); border-color: rgba(255,255,255,.30); color: #fff; }
.zt-pill.is-active{ background: #fff; color: #000; box-shadow: 0 0 15px rgba(255,255,255,0.25); border-color:#fff; }

/* SEPARATOR */
.zt-sep { width:1px; height:24px; background:rgba(255,255,255,0.15); margin:0 8px; }

/* MODAL */
#zt-upgrade-modal{ position:fixed; inset:0; display:none; align-items:center; justify-content:center; background: rgba(0,0,0,.7); z-index:9999; backdrop-filter: blur(8px); }
#zt-upgrade-modal .box{ width:min(450px, 90%); border-radius: 20px; border:1px solid rgba(255,255,255,.15); background: #0a0a0a; padding:35px; box-shadow: 0 30px 60px rgba(0,0,0,.9); text-align:center; }
#zt-upgrade-modal .btn{ padding:10px 24px; border-radius:12px; font-weight:700; cursor:pointer; border:1px solid #333; background:#222; color:#fff; margin:5px; }
#zt-upgrade-modal .btn.primary{ background:#a855f7; border-color:#a855f7; }
</style>

<div class="zt-modebar-wrap" data-zt-is-pro="<?= (int)$__zt_is_pro ?>">
  <div class="zt-modebar" id="zt-modebar">
  

    <div class="zt-sep"></div>

