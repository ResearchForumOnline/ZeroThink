<?php
require_once __DIR__ . '/app/bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: /?return=%2Fdna', true, 302);
    exit;
}
$csrfToken = zt_csrf_token();
header('Cache-Control: private, no-store, max-age=0, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DNA Lab | ZeroThink</title>
<meta name="description" content="Upload raw DNA to explore reference-model ancestry estimates, modern place context, ancient DNA, everyday traits and DNA Sound. Read clear reports with source evidence.">
<meta property="og:title" content="ZeroThink DNA Lab">
<meta property="og:description" content="Explore reference-model ancestry percentages, the modern places behind them, ancient DNA, everyday traits and DNA Sound, with readable reports and source evidence.">
<meta property="og:image" content="https://zerothink.talktoai.org/assets/img/logo_nobg.png">
<link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
<?php if (!defined('ZEROTHINK_LOCAL_LAB')): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php endif; ?>
<style>
:root{--bg:#030605;--panel:rgba(5,18,17,.88);--panel2:rgba(2,10,10,.78);--line:rgba(121,255,226,.22);--line2:rgba(255,255,255,.09);--text:#efffff;--muted:#a9c4bd;--cyan:#69f7ff;--green:#00ff91;--gold:#ffd76f;--pink:#ff6edb;--red:#ff7b7b}
*{box-sizing:border-box}
html{scroll-behavior:smooth;background:#020505;color-scheme:dark}
body{margin:0;min-height:100vh;background:radial-gradient(circle at 7% 0%,rgba(0,255,145,.2),transparent 28rem),radial-gradient(circle at 88% 5%,rgba(105,247,255,.18),transparent 32rem),radial-gradient(circle at 50% 96%,rgba(255,110,219,.13),transparent 28rem),linear-gradient(180deg,#010403,#071113 62%,#020303);color:var(--text);font-family:Inter,Segoe UI,system-ui,sans-serif;overflow-x:hidden}
body:before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(105,247,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(105,247,255,.035) 1px,transparent 1px);background-size:42px 42px;mask-image:radial-gradient(circle at 50% 10%,#000,transparent 82%)}
.skip-link{position:fixed;left:14px;top:10px;z-index:3000;translate:0 -150%;border-radius:12px;padding:10px 14px;background:#fff;color:#00120c;font-weight:950;text-decoration:none;transition:translate .16s ease}
.skip-link:focus{translate:0 0}
:where(a,button,input,select,textarea,summary,[tabindex]):focus-visible{outline:3px solid var(--gold);outline-offset:3px}
.shell{width:min(1320px,calc(100% - 28px));margin:0 auto;padding:24px 0 72px;position:relative;z-index:2}
.top{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:24px}
.brand{display:flex;gap:12px;align-items:center;color:var(--text);text-decoration:none;font-weight:950;letter-spacing:.12em;text-transform:uppercase}
.brand img{width:42px;filter:drop-shadow(0 0 18px rgba(0,255,145,.38))}
.nav{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--line);border-radius:999px;padding:10px 14px;color:var(--text);background:rgba(0,0,0,.28);text-decoration:none;font-weight:900;cursor:pointer;transition:.18s ease;white-space:nowrap}
.btn:hover{transform:translateY(-1px);border-color:rgba(105,247,255,.55);box-shadow:0 14px 34px rgba(0,0,0,.22)}
.btn.primary{border:0;color:#00120c;background:linear-gradient(135deg,var(--green),var(--cyan))}
.btn.gold{border-color:rgba(255,215,111,.34);color:var(--gold);background:rgba(255,215,111,.08)}
.btn.pink{border-color:rgba(255,110,219,.34);color:#ffd5f5;background:rgba(255,110,219,.08)}
.hero,.panel,.card{border:1px solid var(--line);background:linear-gradient(180deg,var(--panel),rgba(2,8,8,.84));border-radius:28px;padding:24px;box-shadow:0 28px 80px rgba(0,0,0,.42);backdrop-filter:blur(14px)}
.hero{position:relative;overflow:hidden}
.hero:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(105,247,255,.09),transparent);transform:skewX(-18deg);animation:sweep 8s linear infinite}
@keyframes sweep{to{translate:120% 0}}
.eyebrow{letter-spacing:.22em;text-transform:uppercase;color:var(--cyan);font-weight:950}
h1{font-size:clamp(3rem,8vw,7.2rem);line-height:.9;margin:.06em 0;background:linear-gradient(90deg,#fff,var(--green),var(--cyan),var(--pink));-webkit-background-clip:text;background-clip:text;color:transparent;letter-spacing:-.08em}
h2{margin:0 0 12px;font-size:1.35rem}
h3{margin:0 0 10px}
.muted{color:var(--muted);line-height:1.72}
.hero-grid{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(330px,.92fr);gap:24px;align-items:center;position:relative;z-index:2}
.helix-stage{position:relative;min-height:360px;border-radius:30px;border:1px solid rgba(255,255,255,.08);background:radial-gradient(circle at 50% 50%,rgba(105,247,255,.14),transparent 56%),rgba(0,0,0,.2);overflow:hidden}
.helix{position:absolute;left:50%;top:50%;width:220px;height:320px;transform:translate(-50%,-50%);transform-style:preserve-3d;animation:floatHelix 7s ease-in-out infinite}
.base{position:absolute;left:50%;top:50%;width:210px;height:3px;transform-origin:center;transform:translate(-50%,-50%) rotateZ(calc(var(--i)*15deg)) translateY(calc((var(--i) - 12)*12px));background:linear-gradient(90deg,var(--green),rgba(105,247,255,.25),var(--pink));box-shadow:0 0 18px rgba(105,247,255,.3)}
.base:before,.base:after{content:"";position:absolute;top:50%;width:14px;height:14px;border-radius:50%;transform:translateY(-50%);background:var(--cyan);box-shadow:0 0 18px var(--cyan)}
.base:before{left:-4px}.base:after{right:-4px;background:var(--pink);box-shadow:0 0 18px var(--pink)}
@keyframes floatHelix{50%{transform:translate(-50%,-53%) rotateY(28deg) rotateZ(3deg);filter:brightness(1.25)}}
.scanline{position:absolute;left:8%;right:8%;top:12%;height:2px;background:linear-gradient(90deg,transparent,var(--green),var(--cyan),transparent);box-shadow:0 0 24px var(--cyan);animation:scan 4.2s ease-in-out infinite}
@keyframes scan{50%{top:86%}}
.hero-stat{position:absolute;left:18px;right:18px;bottom:18px;display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.hero-stat div{border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:10px;background:rgba(0,0,0,.28);font-size:.8rem;color:#cffff3}.hero-stat b{display:block;color:#fff;font-size:1rem}
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.tab{border:1px solid rgba(255,255,255,.1);border-radius:999px;padding:8px 11px;background:rgba(255,255,255,.04);color:#cffff3;font-weight:900;font-size:.78rem}
.note{border:1px solid rgba(255,215,111,.22);background:rgba(255,215,111,.08);color:#ffe7a9;border-radius:18px;padding:14px;line-height:1.65;margin-top:14px}
.danger{border-color:rgba(255,123,123,.28);background:rgba(255,123,123,.08);color:#ffdede}
.upload-card{display:grid;grid-template-columns:minmax(280px,1fr) auto;gap:18px;align-items:center;margin-top:18px}
.drop-zone{position:relative;border:1px dashed rgba(105,247,255,.48);border-radius:24px;background:linear-gradient(135deg,rgba(0,255,145,.08),rgba(105,247,255,.05),rgba(255,110,219,.05));padding:20px;transition:.18s ease}
.drop-zone.drag{border-color:var(--green);box-shadow:0 0 36px rgba(0,255,145,.2);transform:translateY(-1px)}
.drop-zone .drop-title{display:flex;align-items:center;gap:10px;font-weight:950;color:#fff;font-size:1.05rem}
.drop-zone .drop-title i{color:var(--green);filter:drop-shadow(0 0 12px rgba(0,255,145,.5))}
.file-status{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:12px}
.file-status div{border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(0,0,0,.24);padding:10px;color:#bff5e9;font-size:.83rem}
.file-status b{display:block;color:#fff;font-size:1rem;margin-top:2px}
.compat-details{margin-top:12px;border:1px solid rgba(105,247,255,.18);border-radius:16px;background:rgba(0,0,0,.2);overflow:hidden}
.compat-details summary{cursor:pointer;padding:11px 13px;color:#dffff7;font-weight:900}
.compat-details div{padding:0 13px 13px;color:var(--muted);font-size:.88rem;line-height:1.6}
.safety-controls{margin:14px 0 0;padding:14px;border:1px solid rgba(105,247,255,.22);border-radius:20px;background:rgba(0,0,0,.22)}
.safety-controls legend{padding:0 8px;color:#dffff7;font-weight:950;letter-spacing:.08em;text-transform:uppercase;font-size:.78rem}
.control-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.control-grid label{margin-top:0}
.check-control{display:grid;grid-template-columns:auto minmax(0,1fr);gap:10px;align-items:start;margin:12px 0 0;color:#d8eee9;font-size:.9rem;font-weight:700;letter-spacing:0;line-height:1.55;text-transform:none}
.check-control input{width:auto;margin-top:.22rem;accent-color:var(--green)}
.check-control strong{color:#fff}
.safety-hint{margin:10px 0 0;color:#a9c4bd;font-size:.84rem;line-height:1.55}
.safety-hint a,.agent-disclosure a,.lab-footer a{color:#9fffe4}
.one-click{display:grid;gap:10px;min-width:min(360px,100%)}
.btn.mega{font-size:1.05rem;padding:16px 20px;border-radius:20px;box-shadow:0 18px 50px rgba(0,255,145,.16)}
.btn[disabled]{opacity:.45;cursor:not-allowed;transform:none!important;box-shadow:none!important}
.secondary-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.experimental-tag{border:1px solid rgba(255,215,111,.3);border-radius:999px;padding:2px 6px;color:var(--gold);font-size:.62rem;letter-spacing:.06em;text-transform:uppercase}
.sonic-panel{grid-column:1/-1;border:1px solid rgba(105,247,255,.22);border-radius:24px;background:linear-gradient(135deg,rgba(0,255,145,.08),rgba(0,0,0,.28),rgba(255,110,219,.06));padding:16px;display:none}
.sonic-grid{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:14px;align-items:center}
.sonic-title{font-weight:950;letter-spacing:.12em;text-transform:uppercase;color:#dffff7}
.sonic-title i{color:var(--green);filter:drop-shadow(0 0 12px rgba(0,255,145,.5))}
#dna-wave{width:100%;height:120px;border:1px solid rgba(255,255,255,.08);border-radius:18px;background:#020505}
.sonic-controls{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.lineage-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.lineage-orbit{position:relative;min-height:138px;border:1px solid rgba(255,215,111,.2);border-radius:22px;margin-bottom:14px;background:radial-gradient(circle at 50% 50%,rgba(255,215,111,.12),transparent 42%),radial-gradient(circle at 18% 18%,rgba(0,255,145,.13),transparent 24%),rgba(0,0,0,.22);overflow:hidden}
.lineage-orbit:before,.lineage-orbit:after{content:"";position:absolute;left:50%;top:50%;border:1px solid rgba(105,247,255,.24);border-radius:50%;transform:translate(-50%,-50%) rotate(0deg);animation:lineageSpin 14s linear infinite}
.lineage-orbit:before{width:260px;height:82px}.lineage-orbit:after{width:160px;height:116px;animation-duration:20s;animation-direction:reverse}
.lineage-node{position:absolute;width:12px;height:12px;border-radius:50%;background:var(--gold);box-shadow:0 0 22px var(--gold);animation:lineagePulse 2.4s ease-in-out infinite}
.lineage-node:nth-child(1){left:14%;top:34%}.lineage-node:nth-child(2){left:49%;top:17%;background:var(--green);box-shadow:0 0 22px var(--green);animation-delay:.35s}.lineage-node:nth-child(3){right:15%;top:38%;background:var(--cyan);box-shadow:0 0 22px var(--cyan);animation-delay:.7s}.lineage-node:nth-child(4){left:46%;bottom:16%;background:var(--pink);box-shadow:0 0 22px var(--pink);animation-delay:1.05s}
.lineage-orbit-label{position:absolute;inset:auto 16px 14px 16px;color:#fff;font-weight:950;text-align:center;letter-spacing:.08em;text-transform:uppercase;font-size:.8rem}
@keyframes lineageSpin{to{transform:translate(-50%,-50%) rotate(360deg)}}@keyframes lineagePulse{50%{transform:scale(1.55);filter:brightness(1.35)}}
.source-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:12px}
.source-strip div{border:1px solid rgba(255,255,255,.08);border-radius:16px;background:rgba(0,0,0,.18);padding:10px;color:#dff;font-size:.85rem}
.evidence-pulse{position:relative;overflow:hidden}
.evidence-pulse:after{content:"";position:absolute;inset:-2px;background:linear-gradient(120deg,transparent,rgba(0,255,145,.18),transparent);translate:-120% 0;animation:evidencePulse 5.2s ease-in-out infinite}
@keyframes evidencePulse{55%,100%{translate:120% 0}}
input[type=file]{width:100%;border:1px dashed rgba(105,247,255,.42);border-radius:20px;background:rgba(0,0,0,.32);color:#eaffff;padding:18px;font:inherit}
textarea,input,select{width:100%;border:1px solid var(--line);border-radius:16px;background:#020505;color:#eaffff;padding:12px;font:inherit}
textarea{min-height:160px;font-family:ui-monospace,Consolas,monospace}
label{display:block;color:#d9fff8;font-size:.78rem;font-weight:950;letter-spacing:.1em;text-transform:uppercase;margin:12px 0 6px}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
.grid{display:grid;grid-template-columns:minmax(360px,.88fr) minmax(0,1.12fr);gap:18px;margin-top:18px}
.result-grid{display:grid;grid-template-columns:340px minmax(0,1fr);gap:18px;align-items:start}
.chart-card{border:1px solid var(--line2);background:rgba(0,0,0,.26);border-radius:24px;padding:18px}
canvas{max-width:100%}
.legend{display:grid;gap:8px;margin-top:12px}
.legend-row{display:grid;grid-template-columns:16px 1fr auto;gap:8px;align-items:center;color:#cffff3;font-size:.9rem}
.dot{width:13px;height:13px;border-radius:50%;box-shadow:0 0 14px currentColor}
.map-card{display:none;margin-top:14px;border:1px solid rgba(105,247,255,.2);background:radial-gradient(circle at 72% 20%,rgba(105,247,255,.14),transparent 36%),radial-gradient(circle at 18% 78%,rgba(0,255,145,.12),transparent 32%),rgba(0,0,0,.24);border-radius:24px;padding:14px;overflow:hidden}
.map-card.active{display:block}
.map-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px}
.map-head strong{display:block;color:#fff;letter-spacing:.08em;text-transform:uppercase}.map-head span{display:block;color:#a9c4bd;font-size:.82rem;line-height:1.45}
.dna-map-wrap{position:relative;border:1px solid rgba(255,255,255,.08);border-radius:20px;overflow:hidden;background:radial-gradient(circle at 55% 42%,rgba(0,255,145,.12),transparent 34%),linear-gradient(180deg,rgba(105,247,255,.08),rgba(0,0,0,.14));cursor:zoom-in}
.dna-map-wrap:after{content:"Tap map to expand";position:absolute;right:12px;bottom:12px;border:1px solid rgba(255,255,255,.12);border-radius:999px;padding:7px 10px;color:#00120c;background:linear-gradient(135deg,var(--green),var(--cyan));font-weight:950;font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;box-shadow:0 10px 30px rgba(0,255,145,.18)}
.dna-map{display:block;width:100%;height:auto;min-height:220px}
.map-country{fill:rgba(106,255,208,.105);stroke:rgba(184,255,236,.22);stroke-width:.45;vector-effect:non-scaling-stroke;transition:.16s ease}
.map-country:nth-child(3n){fill:rgba(105,247,255,.095)}
.map-country:nth-child(5n){fill:rgba(255,215,111,.075)}
.map-country:hover{fill:rgba(0,255,145,.24);stroke:rgba(255,255,255,.58)}
.map-land{fill:rgba(139,255,220,.13);stroke:rgba(169,255,233,.18);stroke-width:.8}
.map-grid{stroke:rgba(255,255,255,.07);stroke-width:.5}
.map-region-halo{mix-blend-mode:screen;opacity:.4;pointer-events:none}
.map-point{cursor:pointer;filter:drop-shadow(0 0 10px currentColor);transition:.16s ease;transform-box:fill-box;transform-origin:center}
.map-point:hover,.map-point.active{filter:drop-shadow(0 0 18px currentColor);transform:scale(1.22)}
.map-ripple{fill:none;stroke:currentColor;stroke-width:1.4;opacity:.42;animation:mapPulse 2.7s ease-out infinite}
@keyframes mapPulse{0%{r:4;opacity:.55}100%{r:32;opacity:0}}
.map-info{margin-top:12px;border:1px solid rgba(255,255,255,.1);border-radius:18px;background:rgba(0,0,0,.28);padding:12px;color:#dff}
.map-info b{display:block;color:#fff;font-size:1.05rem}.map-info small{display:block;color:#9fffe4;font-weight:900;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px}.map-info p{margin:.45rem 0 0;color:#cfeee8;line-height:1.55}
.map-buttons{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.map-chip{border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.22);color:#eaffff;border-radius:999px;padding:7px 10px;font-weight:900;font-size:.78rem;cursor:pointer}
.map-chip.active{border-color:var(--green);box-shadow:0 0 18px rgba(0,255,145,.18);color:#00120c;background:linear-gradient(135deg,var(--green),var(--cyan))}
.map-expand{padding:8px 11px;font-size:.78rem}
.map-modal{position:fixed;inset:0;z-index:2000;display:none;background:radial-gradient(circle at 16% 12%,rgba(0,255,145,.18),transparent 28rem),radial-gradient(circle at 88% 20%,rgba(105,247,255,.16),transparent 30rem),rgba(0,4,4,.96);backdrop-filter:blur(18px);padding:18px}
.map-modal.active{display:grid;grid-template-rows:auto minmax(0,1fr) auto;gap:12px}
body.map-modal-open{overflow:hidden}
.map-modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid rgba(105,247,255,.22);border-radius:22px;padding:12px 14px;background:rgba(0,0,0,.34)}
.map-modal-title strong{display:block;font-size:1.08rem;letter-spacing:.12em;text-transform:uppercase}.map-modal-title span{display:block;color:#a9c4bd;font-size:.82rem;margin-top:3px}
.map-modal-tools{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.map-tool{border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.32);color:#eaffff;border-radius:999px;padding:9px 12px;font-weight:950;cursor:pointer}
.map-tool.primary{color:#00120c;background:linear-gradient(135deg,var(--green),var(--cyan));border:0}
.map-modal-stage{position:relative;border:1px solid rgba(105,247,255,.24);border-radius:26px;overflow:hidden;background:radial-gradient(circle at 50% 45%,rgba(0,255,145,.13),transparent 38%),linear-gradient(180deg,rgba(105,247,255,.08),rgba(0,0,0,.34));min-height:0}
.map-modal-stage:before{content:"";position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(rgba(105,247,255,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(105,247,255,.045) 1px,transparent 1px);background-size:54px 54px}
.map-modal-stage .dna-map{height:100%;min-height:min(68vh,720px);touch-action:none;cursor:grab;position:relative;z-index:1}
.map-modal-stage .dna-map:active{cursor:grabbing}
.map-modal-foot{display:grid;grid-template-columns:minmax(0,1fr) minmax(260px,.52fr);gap:12px;align-items:start}
.map-modal-foot .map-buttons{margin-top:0;max-height:140px;overflow:auto;border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:10px;background:rgba(0,0,0,.25)}
.map-modal-foot .map-info{margin-top:0}
@media(max-width:760px){.map-modal{padding:10px}.map-modal.active{grid-template-rows:auto minmax(0,1fr) auto}.map-modal-head,.map-modal-foot{grid-template-columns:1fr;display:grid}.map-modal-tools{justify-content:stretch}.map-tool{flex:1}.map-modal-stage .dna-map{min-height:58vh}.map-modal-foot .map-buttons{max-height:110px}.dna-map-wrap:after{font-size:.66rem;padding:6px 8px}}
.summary-card{border:1px solid var(--line2);background:rgba(0,0,0,.23);border-radius:20px;padding:16px;margin-bottom:12px}
.summary-card strong{color:#fff}.summary-card p{color:#cfeee8;line-height:1.62;margin:.4rem 0 0}
.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin:12px 0}
.metric{border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:12px;background:rgba(0,0,0,.22);color:#cffff3}.metric b{display:block;color:#fff;font-size:1.1rem}
.report-body{border:1px solid var(--line2);background:#020505;border-radius:20px;padding:16px;max-height:620px;overflow:auto;white-space:pre-wrap;font-family:ui-monospace,Consolas,monospace;color:#dff;line-height:1.55}
.agent-box{border:1px solid rgba(0,255,145,.25);background:linear-gradient(180deg,rgba(0,255,145,.08),rgba(0,0,0,.18));border-radius:24px;padding:16px;margin-top:18px}
.agent-log{border:1px solid var(--line2);background:#020505;border-radius:18px;padding:14px;min-height:140px;white-space:pre-wrap;line-height:1.6;color:#eaffff}
.agent-disclosure{border:1px solid rgba(105,247,255,.2);border-radius:16px;background:rgba(0,0,0,.24);padding:12px;color:#cfeee8;font-size:.88rem;line-height:1.55}
.pill-row{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}
.prompt-pill{border:1px solid rgba(105,247,255,.22);background:rgba(105,247,255,.07);color:#dff;border-radius:999px;padding:8px 10px;font-size:.82rem;cursor:pointer}
.status{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:12px 0}
.status div{border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:10px;color:#bfeee4;background:rgba(0,0,0,.2);font-size:.84rem}
details.advanced{margin-top:18px;border:1px solid rgba(255,255,255,.1);border-radius:20px;background:rgba(0,0,0,.22);overflow:hidden}
details.advanced summary{cursor:pointer;padding:14px 16px;font-weight:950;color:#dff;text-transform:uppercase;letter-spacing:.08em}
details.advanced .advanced-body{border-top:1px solid rgba(255,255,255,.08);padding:16px}
.mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.lab-footer{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:18px;padding:16px 4px;color:#8fb2aa;font-size:.85rem}
.lab-footer nav{display:flex;gap:14px;flex-wrap:wrap}
.print-only{display:none}
@media(max-width:1060px){.hero-grid,.grid,.result-grid,.upload-card,.sonic-grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.nav{width:100%}.btn{width:100%;justify-content:center;text-align:center}.mini-grid,.status,.hero-stat{grid-template-columns:1fr}.helix-stage{min-height:300px}.sonic-controls{justify-content:stretch}}
@media(max-width:620px){.control-grid{grid-template-columns:1fr}.secondary-actions{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important}.hero:before,.scanline{display:none}}
@media print{body{background:#fff;color:#111}.top,.hero,.grid>.panel:first-child,.nav,.agent-box,.actions,details.advanced{display:none!important}.shell{width:100%;padding:0}.panel,.card,.chart-card,.summary-card{box-shadow:none;border-color:#ddd;background:#fff;color:#111}.report-body{max-height:none;overflow:visible;color:#111;background:#fff}.print-only{display:block}}

/* DNA-only evidence workspace: measured data, calm motion, readable results. */
[hidden]{display:none!important}
.hero-intro{font-size:1.12rem;max-width:58ch}
.hero:before,.evidence-pulse:after{pointer-events:none}
.lab-steps{list-style:none;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;padding:0;margin:22px 0}
.lab-steps li{border-top:2px solid var(--line2);padding-top:12px;display:grid;gap:5px;font-size:.83rem;color:var(--muted)}
.lab-steps li>span{font-size:.74rem;letter-spacing:.1em;color:var(--cyan)}
.lab-steps b{color:var(--text)}.lab-steps small{line-height:1.5}.lab-steps .current,.lab-steps .done{border-color:var(--green)}
.one-click{align-self:start;padding-top:14px;min-width:0}.report-options{margin-top:0!important}.report-options summary{font-size:.8rem}
.upload-card{grid-template-columns:minmax(0,1.5fr) minmax(270px,1fr);align-items:start}
.secondary-actions{grid-template-columns:1fr}.secondary-actions .btn{white-space:normal;min-height:44px}
.run-status{display:grid;grid-template-columns:24px minmax(0,1fr);gap:12px;border:1px solid var(--line);border-radius:18px;padding:16px;background:rgba(105,247,255,.06);overflow-wrap:anywhere}
.run-status p{margin:5px 0 0;color:var(--muted);font-size:.88rem;line-height:1.6}.run-status.error{border-color:var(--red)}
.run-orbit{width:22px;height:22px;border:2px solid rgba(105,247,255,.18);border-top-color:var(--cyan);border-radius:50%}
.run-status.busy .run-orbit{animation:labOrbit 1.3s linear infinite}.run-status.error .run-orbit{border-color:var(--red)}
.run-status.complete .run-orbit{border-color:var(--green);background:rgba(0,255,145,.2)}
#dna-upload-progress{grid-column:1/-1;width:100%;accent-color:var(--green);height:8px}
.report-workspace{grid-template-columns:minmax(0,1fr)}#report-panel{grid-row:1;min-width:0;scroll-margin-top:16px}
.advanced-console{grid-row:2}.advanced-console>summary{cursor:pointer;font-weight:900;padding:0;list-style-position:inside}.advanced-console>summary span{color:var(--muted);font-weight:400;margin-left:12px;font-size:.88rem}.console-body{padding-top:20px}
.report-heading{display:flex;align-items:center;justify-content:space-between;gap:16px}.report-heading .eyebrow{font-size:.72rem;margin:0 0 8px}.report-heading h2{font-size:1.8rem;margin-bottom:0}
.report-badge{border:1px solid var(--line);border-radius:999px;padding:8px 12px;color:var(--cyan);font-size:.8rem;max-width:52%;overflow-wrap:anywhere}
.report-nav{display:flex;flex-wrap:wrap;gap:8px;padding:14px 0 20px}.report-nav a{padding:9px 13px;border:1px solid var(--line);border-radius:999px;color:#dffff7;text-decoration:none;font-weight:750;font-size:.85rem;min-height:40px}.report-nav a:hover{background:rgba(0,255,145,.1)}
.evidence-workspace{margin-top:24px;padding-top:24px;border-top:1px solid var(--line);scroll-margin-top:20px}.evidence-workspace .eyebrow{font-size:.7rem}.evidence-workspace>.muted{font-size:.9rem;max-width:85ch}
.coverage-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.coverage-card{padding:18px;border:1px solid var(--line2);border-radius:20px;background:rgba(0,0,0,.24);overflow-wrap:anywhere}.coverage-card>span{display:block;color:var(--muted);font-size:.8rem}.coverage-card>b{display:block;font-size:1.45rem;margin:10px 0;color:#fff}.coverage-card small{display:block;line-height:1.55;color:var(--muted)}
.coverage-meter{margin-top:14px;height:5px;border-radius:9px;background:rgba(255,255,255,.09);overflow:hidden}.coverage-meter i{display:block;height:100%;background:linear-gradient(90deg,var(--green),var(--cyan));transform-origin:left;animation:coverageReveal .8s ease-out both}
.chromosome-strip{display:flex;gap:8px;overflow-x:auto;padding:8px 0 12px}.chromosome-bar{flex:1;min-width:58px;text-align:center}.chromosome-bar span{display:flex;align-items:flex-end;justify-content:center;height:88px}.chromosome-bar i{display:block;width:16px;min-height:2px;border-radius:12px;background:linear-gradient(0deg,rgba(0,255,145,.15),var(--cyan));transform-origin:bottom;animation:coverageReveal .85s ease-out both}.chromosome-bar b,.chromosome-bar small{display:block;margin-top:8px;font-size:.77rem}.chromosome-bar small{color:var(--muted);font-size:.68rem}
.source-table-wrap{overflow-x:auto;border:1px solid var(--line2);border-radius:16px;margin-top:12px}.source-table{border-collapse:collapse;width:100%;min-width:580px;text-align:left;font-size:.85rem}.source-table th,.source-table td{padding:13px 15px;vertical-align:top;border-bottom:1px solid var(--line2);line-height:1.6;overflow-wrap:anywhere}.source-table th{color:var(--cyan);background:rgba(105,247,255,.05)}.source-table tr:last-child td{border-bottom:0}.source-table td{color:#d0e9e2}
.source-detail{border:1px solid var(--line2);border-radius:16px;padding:14px 16px;margin-top:10px;background:rgba(0,0,0,.15)}.source-detail summary{cursor:pointer;font-weight:800;line-height:1.6}.source-detail p{font-size:.88rem;line-height:1.7;color:var(--muted)}.source-detail .evidence-label{color:var(--cyan);font-size:.75rem;display:block;font-weight:500}
.affinity-row{padding:13px 0;border-bottom:1px solid var(--line2)}.affinity-row>div:first-child{display:flex;gap:12px;justify-content:space-between;font-size:.85rem}.affinity-row small{display:block;color:var(--muted);margin-top:8px;font-size:.76rem;line-height:1.5}.affinity-row .coverage-meter{margin-top:9px}.source-table a,.source-detail a{color:var(--cyan)}
.file-status b,.metric b,.summary-card,.report-body{overflow-wrap:anywhere}.hero-stat{pointer-events:none}.btn{min-height:42px}
@keyframes labOrbit{to{transform:rotate(360deg)}}@keyframes coverageReveal{from{scale:0.05 1;opacity:.35}to{scale:1 1;opacity:1}}
html.motion-paused *,html.motion-paused *::before,html.motion-paused *::after{animation-play-state:paused!important;transition:none!important}html.motion-paused{scroll-behavior:auto}
@media(max-width:1060px){.upload-card{grid-template-columns:1fr}.one-click{max-width:none}.report-heading .btn,.report-nav .btn{width:auto}.hero-stat{grid-template-columns:repeat(3,1fr)}.result-grid{grid-template-columns:minmax(0,1fr)}.chart-card{min-width:0}.coverage-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.nav .btn{width:auto;flex:1}.one-click .secondary-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:620px){.hero,.panel{padding:18px}.lab-steps{gap:12px}.lab-steps small{display:none}.report-heading{align-items:flex-start;flex-direction:column}.report-badge{max-width:100%}.coverage-grid{grid-template-columns:1fr 1fr;gap:8px}.coverage-card{padding:13px}.coverage-card>b{font-size:1.17rem}.one-click .secondary-actions{grid-template-columns:1fr}.advanced-console>summary span{display:block;margin:8px 0 0}.hero-stat{left:12px;right:12px;gap:6px}.hero-stat div{padding:9px 7px;font-size:.7rem}.hero-stat b{font-size:.84rem}.lab-steps b{font-size:.8rem}.hero-grid{grid-template-columns:minmax(0,1fr)}.helix-stage{min-height:270px}.helix{opacity:.8}}
@media(max-width:620px){.upload-card,.one-click .secondary-actions{grid-template-columns:minmax(0,1fr)}.one-click,.one-click>*,.drop-zone,.safety-controls{min-width:0}.one-click .btn{white-space:normal;padding:12px;max-width:100%;overflow-wrap:anywhere}.report-options summary{overflow-wrap:anywhere;padding:13px 12px}.drop-zone{padding:15px}input[type=file]{min-width:0;padding:12px;font-size:.86rem}.btn.mega{font-size:1rem}.coverage-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(prefers-reduced-motion:reduce){.run-orbit,.coverage-meter i,.chromosome-bar i{animation:none!important}}
@media print{.advanced-console,.report-nav,.report-heading+.compat-details{display:none!important}.report-workspace{display:block}.evidence-workspace{break-inside:avoid}.coverage-card,.source-detail,.source-table th,.source-table td{background:#fff;color:#111}.coverage-card b,.coverage-card small,.coverage-card span{color:#111}}


/* Origins journey: illustration, measured comparisons and progressive disclosure. */
:root{--origin-amber:#e7b47a;--origin-violet:#b1a4ff;--origin-sea:#79ded3}
.origin-hero-line{font-size:clamp(1.45rem,3.2vw,2.6rem);line-height:1.2;letter-spacing:-.045em;font-weight:750;margin:20px 0 12px;color:#e6f4ee}
.hero-intro{font-size:1rem;max-width:56ch}.hero-grid{grid-template-columns:minmax(0,1fr) minmax(0,1.08fr);gap:30px}.hero h1{font-size:clamp(3.5rem,7vw,6.5rem)}
.origin-hero-art{min-height:480px;align-self:stretch;isolation:isolate;background:#101c1a;border-color:rgba(231,180,122,.18)}
.origin-landscape{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:34% 50%;opacity:.88;animation:originLandscape 32s ease-in-out infinite alternate;z-index:-3}
.origin-hero-art:before{content:"";position:absolute;inset:0;background:linear-gradient(0deg,rgba(2,12,11,.98),rgba(2,12,11,.12) 55%,rgba(3,12,13,.07));z-index:-1}
.origin-hero-art .helix{left:auto;right:-100px;top:60px;opacity:.17;scale:.63;mix-blend-mode:screen;pointer-events:none;z-index:0}.origin-hero-art .base{box-shadow:none}
.origin-art-caption{position:absolute;left:26px;right:24px;bottom:28px}.origin-art-caption>span{display:block;text-transform:uppercase;letter-spacing:.18em;color:var(--origin-amber);font-size:.64rem;font-weight:800;margin-bottom:15px}.origin-art-caption>b{font-size:clamp(1.5rem,2.6vw,2.3rem);line-height:1.18;letter-spacing:-.04em;color:#fff}.origin-art-caption>small{display:block;font-size:.64rem;color:#bdc3b4;line-height:1.5;margin-top:19px}
.origin-aurora{position:absolute;inset:0;background:radial-gradient(ellipse at 20% 90%,rgba(222,156,79,.12),transparent 55%),radial-gradient(ellipse at 80% 10%,rgba(72,172,146,.1),transparent 50%);animation:originAurora 9s ease-in-out infinite;pointer-events:none;z-index:-2}
.origin-discovery-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:24px 0 6px}.origin-discovery-strip button{display:flex;gap:12px;align-items:center;justify-content:flex-start;min-width:0;text-align:left;border:1px solid rgba(160,210,190,.16);border-radius:18px;padding:16px;background:rgba(5,15,14,.65);color:#edf7f0;cursor:pointer;transition:background .2s,border-color .2s,transform .2s}.origin-discovery-strip button:hover{background:rgba(25,56,45,.6);border-color:rgba(121,222,211,.45);transform:translateY(-2px)}.origin-discovery-strip button>span:last-child{margin-left:auto;color:var(--origin-amber)}.origin-discovery-strip b,.origin-discovery-strip small{display:block}.origin-discovery-strip b{font-size:.9rem}.origin-discovery-strip small{font-size:.75rem;color:var(--muted);margin-top:5px}.origin-topic-icon{display:flex;flex:0 0 38px;height:38px;align-items:center;justify-content:center;border:1px solid rgba(231,180,122,.24);border-radius:12px;color:var(--origin-amber);font-size:1.5rem}
.origin-empty{position:relative;display:grid;grid-template-columns:minmax(0,1fr) 240px;align-items:center;gap:28px;border:1px solid var(--line2);border-radius:24px;padding:30px;background:radial-gradient(circle at 85% 30%,rgba(92,140,115,.13),transparent 40%),rgba(0,0,0,.18);margin-top:16px;overflow:hidden}.origin-empty h3{font-size:1.8rem;letter-spacing:-.04em}.origin-empty p{max-width:66ch;color:var(--muted);line-height:1.7}.origin-empty .eyebrow{font-size:.68rem;color:var(--origin-amber)}.origin-empty .btn{margin-top:9px}
.origin-empty-orbit{position:relative;width:220px;height:210px;display:flex;align-items:center;justify-content:center;color:var(--origin-sea);font-size:.8rem;font-weight:800;letter-spacing:.15em}.origin-empty-orbit i{position:absolute;inset:30px 0;border:1px solid rgba(121,222,211,.32);border-radius:50%;rotate:30deg;animation:originOrbit 18s linear infinite}.origin-empty-orbit i:nth-child(2){rotate:90deg;animation-duration:25s;border-color:rgba(231,180,122,.27)}.origin-empty-orbit i:nth-child(3){rotate:150deg;animation-duration:30s}
.origins-report{margin-top:12px;scroll-margin-top:18px}.origins-intro{display:flex;align-items:center;gap:22px;justify-content:space-between;padding:20px 0}.origins-intro .eyebrow{font-size:.66rem;color:var(--origin-amber);margin:0 0 10px}.origins-intro h3{font-size:clamp(1.7rem,3.5vw,2.6rem);line-height:1.14;letter-spacing:-.045em;margin:0 0 12px;max-width:30ch}.origins-intro p:last-child{max-width:83ch;margin:0;color:var(--muted);font-size:.96rem;line-height:1.75}.origins-intro>.btn{flex-shrink:0}
.origins-report.educational .origins-intro{border-left:3px solid var(--origin-amber);padding-left:18px}.origins-report.educational #origins-mode{color:var(--origin-amber)}
.origin-highlights{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:12px 0 25px}.origin-highlight{padding:20px;border:1px solid var(--line2);border-radius:20px;position:relative;overflow:hidden;background:linear-gradient(145deg,rgba(73,119,104,.13),rgba(0,0,0,.15));text-align:left;color:var(--text);cursor:pointer}.origin-highlight:nth-child(2){background:linear-gradient(145deg,rgba(138,101,59,.14),rgba(0,0,0,.15))}.origin-highlight:nth-child(3){background:linear-gradient(145deg,rgba(83,67,124,.19),rgba(0,0,0,.15))}.origin-highlight:after{content:"↗";position:absolute;right:17px;top:17px;color:var(--muted)}.origin-highlight>span{display:block;font-size:.65rem;text-transform:uppercase;letter-spacing:.13em;color:var(--muted);padding-right:15px}.origin-highlight>b{display:block;font-size:1.38rem;letter-spacing:-.035em;line-height:1.3;margin:12px 0 10px;overflow-wrap:anywhere}.origin-highlight>small{font-size:.79rem;line-height:1.6;color:#a9c4bd;display:block}.origin-highlight:hover{border-color:rgba(121,222,211,.4)}
.origin-tabs{display:flex;gap:8px;border-bottom:1px solid rgba(145,185,165,.2);margin-bottom:24px;padding-bottom:10px;overflow-x:auto}.origin-tabs button{display:flex;align-items:center;gap:10px;flex:1;white-space:nowrap;justify-content:center;min-height:54px;border:1px solid transparent;border-radius:14px;background:transparent;color:#a9c4bd;padding:12px 16px;cursor:pointer;font-weight:800;font-size:.9rem}.origin-tabs button>span{font-size:.68rem;font-weight:500;color:#7d9e92}.origin-tabs button[aria-selected=true]{background:rgba(121,222,211,.09);border-color:rgba(121,222,211,.22);color:#e6fff5}.origin-tabs button[aria-selected=true]>span{color:var(--cyan)}
[id^=origins-panel-]:not([hidden]){animation:originReveal .35s ease both}
.origins-modern-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(260px,.85fr);gap:18px;align-items:start}.origin-map-card{border:1px solid rgba(121,222,211,.19);border-radius:22px;background:radial-gradient(ellipse at 60% 15%,rgba(121,222,211,.075),transparent 70%),#03100f;overflow:hidden;min-width:0}.origin-map-title{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px 22px 10px}.origin-map-title strong{font-size:.9rem}.origin-map-title span{font-size:.67rem;color:var(--muted);border:1px solid var(--line2);border-radius:30px;padding:6px 9px;max-width:55%;text-align:center}.origin-world-map{width:100%;height:auto;min-height:230px;display:block}.origin-world-map .map-country{stroke:rgba(164,201,178,.24);fill:rgba(105,151,133,.18)}.origin-world-map .map-country:nth-child(3n){fill:rgba(94,139,123,.14)}.origin-world-map .map-country:nth-child(5n){fill:rgba(167,146,100,.13)}.origin-map-point{cursor:pointer}.origin-map-point .point-core{fill:var(--origin-sea);stroke:#00130c;stroke-width:1.5}.origin-map-point.active .point-core{fill:var(--origin-amber);stroke:#fff;stroke-width:1}.origin-map-point .point-ring{fill:none;stroke:rgba(121,222,211,.6);stroke-width:1;animation:originPoint 4s ease-out infinite}.origin-map-point text{fill:#d8ede3;font-size:10px;font-weight:750;paint-order:stroke;stroke:#05110c;stroke-width:2.5px;stroke-linejoin:round;pointer-events:none}.origin-map-caption{font-size:.73rem!important;color:#8dafa2!important;margin:0!important;padding:0 22px 19px;line-height:1.65!important}.origin-location-details{padding:21px;border-top:1px solid var(--line2);background:rgba(0,0,0,.14)}.origin-location-details .eyebrow{font-size:.59rem;margin:0 0 8px;color:var(--origin-amber)}.origin-location-details h4{margin:0 0 9px;font-size:1.38rem;letter-spacing:-.035em}.origin-location-details p{margin:7px 0;color:var(--muted);font-size:.85rem;line-height:1.65}.origin-location-stats{display:flex;gap:20px;flex-wrap:wrap;margin-top:16px}.origin-location-stats b{font-size:1.2rem;display:block;color:#f6fff9}.origin-location-stats span{display:block;font-size:.64rem;color:#9cbcaf;margin-top:5px}
.origin-region-list{display:grid;gap:8px}.origin-section-kicker{margin:0 0 14px;color:var(--muted);font-size:.68rem;text-transform:uppercase;letter-spacing:.12em}.origin-region-button{width:100%;text-align:left;border:1px solid var(--line2);border-radius:16px;padding:15px;background:rgba(0,0,0,.16);color:var(--text);cursor:pointer;min-width:0}.origin-region-button.active{border-color:rgba(121,222,211,.54);background:rgba(83,144,113,.1)}.origin-region-line{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}.origin-region-line b{font-size:.9rem;line-height:1.45}.origin-region-line>span{font-size:.72rem;color:var(--origin-sea);white-space:nowrap}.origin-region-button small{display:block;margin-top:8px;font-size:.7rem;color:var(--muted);line-height:1.5}.origin-region-button .coverage-meter{height:3px;margin-top:12px}.origin-region-button .coverage-meter i{background:var(--origin-sea)}.origin-subsection{margin-top:25px}.origin-population-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.origin-population{border:1px solid var(--line2);border-radius:16px;padding:17px;background:rgba(0,0,0,.13)}.origin-population h4{font-size:.95rem;margin:0 0 10px;line-height:1.45}.origin-population p{font-size:.77rem;line-height:1.7;color:var(--muted);margin:5px 0}.origin-population .origin-affinity{font-size:1.28rem;color:var(--origin-sea);font-weight:800}.origin-population small{color:var(--muted);font-size:.7rem;line-height:1.6;display:block;margin-top:12px}
.origin-context-banner{position:relative;isolation:isolate;overflow:hidden;min-height:200px;border:1px solid rgba(231,180,122,.2);border-radius:22px;padding:28px;display:flex;align-items:flex-end;margin-bottom:22px;background:#18241c}.origin-context-banner:before{content:"";position:absolute;inset:0;z-index:-2;background-image:url('/assets/dna-origins/ancient-journey-v1.png');background-size:cover;background-position:50% 44%;opacity:.8}.origin-context-banner:after{content:"";position:absolute;inset:0;z-index:-1;background:linear-gradient(90deg,rgba(2,12,8,.92),rgba(2,12,8,.3))}.origin-context-banner h4{font-size:1.7rem;letter-spacing:-.04em;margin:0 0 10px}.origin-context-banner p{margin:0;max-width:70ch;color:#cfdbc8;font-size:.88rem;line-height:1.65}.origin-context-banner .eyebrow{font-size:.6rem;color:var(--origin-amber);margin-bottom:10px}
.origin-timeline{position:relative;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;padding-top:23px}.origin-timeline:before{content:"";position:absolute;left:8%;right:8%;top:7px;height:1px;background:linear-gradient(90deg,rgba(231,180,122,.1),rgba(231,180,122,.65),rgba(121,222,211,.2))}.origin-chapter{position:relative;border:1px solid rgba(231,180,122,.16);border-radius:20px;overflow:visible;background:rgba(0,0,0,.2);min-width:0}.origin-chapter:before{content:"";position:absolute;left:50%;top:-20px;width:8px;height:8px;border-radius:50%;background:var(--origin-amber);box-shadow:0 0 15px rgba(231,180,122,.5)}.origin-chapter-art{height:126px;border-radius:20px 20px 0 0;position:relative;overflow:hidden;background-image:linear-gradient(0deg,rgba(3,12,8,.3),rgba(3,12,8,.05)),url('/assets/dna-origins/ancient-journey-v1.png');background-size:auto 170%;background-position:14% 52%}.origin-chapter:nth-child(2) .origin-chapter-art{background-position:57% 55%}.origin-chapter:nth-child(3) .origin-chapter-art{background-position:95% 58%}.origin-chapter-art>span{position:absolute;left:15px;bottom:14px;padding:5px 8px;font-size:.58rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;background:rgba(1,10,8,.7);border:1px solid rgba(231,180,122,.28);border-radius:20px;color:#f0d2a8}.origin-chapter-body{padding:19px}.origin-chapter h4{font-size:1.1rem;letter-spacing:-.025em;margin:0 0 9px;line-height:1.3}.origin-chapter p{font-size:.8rem;line-height:1.7;color:var(--muted);margin:0 0 14px}.origin-chapter-stat{border-top:1px solid var(--line2);padding-top:15px;margin-top:14px}.origin-chapter-stat strong{font-size:1.3rem;color:var(--origin-amber);display:block}.origin-chapter-stat small{display:block;color:var(--muted);font-size:.69rem;line-height:1.6;margin-top:6px}.origin-chapter details{margin-top:15px;font-size:.74rem}.origin-chapter details summary{cursor:pointer;color:#d4e2d4;line-height:1.6}.origin-chapter details p{margin-top:10px;font-size:.73rem}.origin-era{color:var(--origin-amber)!important;font-size:.69rem!important;font-weight:700;margin-bottom:9px!important}
.origin-archaic-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.origin-archaic-card{border:1px solid rgba(177,164,255,.2);border-radius:24px;overflow:hidden;background:linear-gradient(160deg,rgba(40,36,55,.65),rgba(2,9,9,.6));min-width:0}.origin-archaic-art{height:200px;position:relative;isolation:isolate;background:#111b1c}.origin-archaic-art:before{content:"";position:absolute;inset:0;background-image:url('/assets/dna-origins/archaic-world-v1.png');background-size:cover;background-position:22% 55%;opacity:.85;z-index:-2}.origin-archaic-card:nth-child(2) .origin-archaic-art:before{background-position:86% 55%}.origin-archaic-art:after{content:"";position:absolute;inset:0;background:linear-gradient(0deg,#131a1e,transparent 100%);z-index:-1}.origin-archaic-art h4{position:absolute;bottom:20px;left:24px;right:24px;margin:0;color:#fff;font-size:1.85rem;letter-spacing:-.05em}.origin-archaic-body{padding:0 24px 24px}.origin-archaic-body>p{font-size:.87rem;line-height:1.75;color:var(--muted);margin-top:0}.origin-tag-result{display:flex;align-items:baseline;gap:10px;margin:20px 0 8px}.origin-tag-result b{font-size:3rem;line-height:1;letter-spacing:-.06em;color:#d7d0ff}.origin-tag-result span{font-size:.86rem;color:#b4c8c3;line-height:1.55}.origin-tag-meter{display:grid;grid-template-columns:repeat(28,minmax(0,1fr));gap:4px;margin:20px 0 12px;height:25px}.origin-tag-meter i{border-radius:3px;background:rgba(177,164,255,.12)}.origin-tag-meter i.covered{background:rgba(177,164,255,.46)}.origin-tag-meter i.observed{background:var(--origin-violet);box-shadow:0 0 10px rgba(177,164,255,.18)}.origin-archaic-body small{display:block;color:var(--muted);font-size:.73rem;line-height:1.7}.origin-archaic-facts{display:flex;gap:16px;flex-wrap:wrap;margin:18px 0;padding:15px 0;border-top:1px solid var(--line2);border-bottom:1px solid var(--line2)}.origin-archaic-facts b{display:block;color:#dfe8ed;font-size:1.06rem}.origin-archaic-facts span{display:block;font-size:.64rem;color:var(--muted);margin-top:6px}
.origin-explainer{padding:19px 22px;border-left:2px solid rgba(121,222,211,.4);background:rgba(103,176,142,.045);border-radius:0 16px 16px 0;margin-top:20px;color:#b7d0c4;font-size:.84rem;line-height:1.75}.origin-explainer strong{color:#e3f4e7}.origin-availability{font-size:.8rem;color:var(--muted);line-height:1.7;margin:14px 0 0}.origin-pill{display:inline-block;padding:6px 9px;font-size:.62rem;letter-spacing:.03em;border:1px solid rgba(231,180,122,.25);border-radius:99px;color:var(--origin-amber);line-height:1.4;margin:0 0 10px}.origin-methods,.origin-depth{border:1px solid var(--line2);border-radius:18px;padding:17px 19px;margin-top:24px;background:rgba(0,0,0,.14)}.origin-methods>summary,.origin-depth>summary{font-weight:800;color:#d6e8dd;cursor:pointer;font-size:.88rem;line-height:1.6}.origin-methods p{font-size:.83rem;line-height:1.8;color:var(--muted)}.origin-depth>summary>span{display:block;color:var(--muted);font-size:.74rem;font-weight:400;margin-top:4px}.origin-depth[open]>.result-grid{margin-top:20px}.origin-depth[hidden]{display:none!important}.origin-source-links{display:flex;flex-wrap:wrap;gap:10px;font-size:.75rem;line-height:1.6;margin-top:12px}.origin-source-links a{color:var(--origin-sea)}
@keyframes originLandscape{from{scale:1;translate:0 0}to{scale:1.075;translate:-1% 0}}@keyframes originAurora{50%{opacity:.3}}@keyframes originOrbit{to{transform:rotate(360deg)}}@keyframes originPoint{0%{r:5;opacity:.7}85%,100%{r:15;opacity:0}}@keyframes originReveal{from{opacity:.45;translate:0 7px}to{opacity:1;translate:0 0}}
@media(max-width:1060px){.hero-grid{grid-template-columns:1fr}.origin-hero-art{min-height:320px}.origin-landscape{object-position:50% 50%}.origins-modern-layout{grid-template-columns:1fr}.origin-region-list{grid-template-columns:repeat(2,minmax(0,1fr))}.origin-world-map{min-height:260px}.origin-population-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.origin-timeline{gap:10px}.origin-chapter-body{padding:16px}.origin-discovery-strip{gap:8px}.origin-discovery-strip button{padding:14px 11px;gap:8px}.origin-discovery-strip b{font-size:.79rem}.origin-discovery-strip small{font-size:.67rem}.origin-topic-icon{flex-basis:31px;height:31px;font-size:1.25rem}.origin-highlights{gap:9px}.origin-highlight{padding:17px}.origin-highlight>b{font-size:1.17rem}.origins-intro>.btn{width:auto}.origin-tabs button{font-size:.8rem;padding:12px;gap:7px}}
@media(max-width:700px){.origin-discovery-strip{grid-template-columns:minmax(0,1fr)}.origin-discovery-strip button{padding:14px}.origin-discovery-strip b{font-size:.88rem}.origin-discovery-strip small{font-size:.74rem}.origin-topic-icon{flex-basis:36px;height:36px}.origin-discovery-strip button>span:nth-child(2){display:flex;align-items:baseline;gap:8px;flex-wrap:wrap}.origin-discovery-strip small{margin:0}.origin-empty{grid-template-columns:minmax(0,1fr);padding:24px}.origin-empty-orbit{display:none}.origin-highlights{grid-template-columns:minmax(0,1fr)}.origin-highlight{padding:17px 19px}.origin-highlight>b{margin:8px 0 6px;font-size:1.3rem}.origin-highlight>small{font-size:.76rem}.origin-tabs{gap:5px}.origin-tabs button{min-width:142px;min-height:49px;flex:1 0 auto;font-size:.77rem}.origin-tabs button>span{font-size:.6rem}.origin-archaic-grid{grid-template-columns:minmax(0,1fr)}.origin-timeline{grid-template-columns:minmax(0,1fr);padding:0 0 0 20px;gap:18px}.origin-timeline:before{left:3px;top:15px;bottom:15px;width:1px;height:auto;right:auto;background:linear-gradient(0deg,rgba(121,222,211,.2),rgba(231,180,122,.65),rgba(231,180,122,.1))}.origin-chapter:before{left:-22px;top:28px}.origin-chapter-art{height:135px;background-size:cover}.origin-chapter:nth-child(1) .origin-chapter-art{background-position:15% 45%}.origin-chapter:nth-child(2) .origin-chapter-art{background-position:60% 52%}.origin-chapter:nth-child(3) .origin-chapter-art{background-position:95% 52%}.origin-chapter-body{padding:20px}.origin-population-grid{grid-template-columns:minmax(0,1fr)}.origin-context-banner{padding:23px;min-height:200px}.origin-context-banner h4{font-size:1.5rem}.origin-world-map{min-height:180px}.origin-map-title{padding:18px 17px 10px}.origin-location-details{padding:19px}.origin-map-caption{padding:0 17px 16px}.origin-region-list{grid-template-columns:minmax(0,1fr)}.origins-intro{align-items:flex-start;flex-direction:column;gap:15px}.origin-hero-art{min-height:300px}.origin-art-caption{left:22px;right:20px;bottom:22px}.origin-hero-art .helix{display:none}.origin-methods,.origin-depth{padding:15px}.origin-archaic-body{padding:0 20px 22px}.origin-archaic-art h4{left:20px;right:20px}.origin-tag-meter{gap:3px}.origin-hero-line{margin-top:15px}}
@media(prefers-reduced-motion:reduce){.origin-landscape,.origin-aurora,.origin-empty-orbit i,.origin-map-point .point-ring,[id^=origins-panel-]{animation:none!important}}
@media print{.origin-discovery-strip,.origin-empty,.origin-tabs,.origin-landscape,.origin-methods{display:none!important}.origins-report{display:block!important}.origin-highlights,.origins-modern-layout,.origin-archaic-grid{display:block}.origin-highlight,.origin-archaic-card,.origin-population,.origin-chapter{background:#fff;color:#111;box-shadow:none;break-inside:avoid}.origin-highlight small,.origin-chapter p,.origin-archaic-body p{color:#333}.origins-report [role=tabpanel]{display:block!important}.origin-context-banner:before,.origin-archaic-art:before{display:none}.origin-archaic-art{height:auto}.origin-archaic-art h4{position:static;padding:12px;color:#111}}

.origin-inventory{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding:17px 0 2px}.origin-inventory>div{padding:10px 14px;border-left:1px solid rgba(121,222,211,.25)}.origin-inventory b{font-size:1.15rem;color:var(--origin-sea);display:block;font-weight:750}.origin-inventory span{display:block;color:var(--muted);font-size:.69rem;line-height:1.55;margin-top:5px}.origin-inventory>small{grid-column:1/-1;font-size:.65rem;color:#8da99b;padding:0 14px;line-height:1.55}@media(max-width:620px){.origin-inventory{gap:4px}.origin-inventory>div{padding:9px}.origin-inventory b{font-size:1rem}.origin-inventory span{font-size:.64rem}}
</style>
<link rel="stylesheet" href="/assets/dna-discovery/discovery.css?v=20260905-1">
<link rel="stylesheet" href="/assets/dna-ancestry-story/ancestry-story.css?v=20260905-1">
<link rel="stylesheet" href="/assets/dna-customer/customer.css?v=20260905-1">
<link rel="stylesheet" href="/assets/dna-country/country.css?v=20260905-2">
</head>
<body>
<?php if (defined('ZEROTHINK_LOCAL_LAB') && ZEROTHINK_LOCAL_LAB === true): ?>
<aside style="padding:12px 20px;background:#05221b;border-bottom:1px solid #00ff91;color:#efffff;text-align:center" role="status">
<strong>Local DNA Lab</strong> · Runs on this computer without an account or MySQL.
Reference packs install separately; unavailable models stay unavailable. External research and DNA Agent calls are disabled in this mode.
</aside>
<?php endif; ?>
<a class="skip-link" href="#dna-main">Skip to DNA Lab</a>
<main class="shell" id="dna-main">
  <div class="top">
    <a class="brand" href="/studio"><img src="/assets/img/logo_nobg.png" alt="ZeroThink logo">ZeroThink DNA</a>
    <nav class="nav" aria-label="DNA Lab navigation">
      <button class="btn" id="motion-toggle" type="button" aria-pressed="false" onclick="toggleDnaMotion()">Pause animations</button>
      <a class="btn" href="/library">Zero Library</a>
      <a class="btn" href="/studio#neural-vault">Neural Vault</a>
      <a class="btn" href="/faq#library">DNA FAQ</a>
      <a class="btn" href="/privacy">Privacy</a>
      <a class="btn primary" href="/studio">Studio</a>
    </nav>
  </div>

  <section class="hero">
    <div class="hero-grid">
      <div>
        <p class="eyebrow">Deep roots. New discoveries.</p>
        <h1>DNA Lab</h1><p class="origin-hero-line">Explore your human story.</p>
        <p class="muted hero-intro">Explore your ancestry percentage estimates across published reference groups, understand the modern places behind them, and discover ancient DNA, everyday traits and DNA Sound. Estimates depend on the markers your file can compare.</p>
        <div class="tabs">
          <span class="tab">Regional origins</span><span class="tab">Ancient populations</span>
          <span class="tab">Neanderthal &amp; Denisovan</span><span class="tab">Health evidence</span>
        </div>
        <ol class="lab-steps" aria-label="DNA report workflow">
          <li id="step-file" class="current"><span>01</span><b>Choose a file</b><small>Original raw export</small></li>
          <li id="step-consent"><span>02</span><b>Review privacy</b><small>You control processing</small></li>
          <li id="step-report"><span>03</span><b>Explore evidence</b><small>Quality, signals, sources</small></li>
        </ol>
        <div class="note danger">
          <strong>Truth boundary:</strong> this is research support. Health output is not diagnosis or treatment advice. Heritage can show country/region-style DNA signals with confidence, but culture, nationality, religion, tribe, identity, and lineage proof stay separate.
        </div>
      </div>
      <div class="helix-stage origin-hero-art">
        <img class="origin-landscape" src="/assets/dna-origins/ancient-journey-v1.png" width="2172" height="724" alt="Artistic ancient landscape, from an Ice Age camp to farming and open grasslands" decoding="async">
        <div class="origin-aurora" aria-hidden="true"></div>
        <div class="helix" id="helix" aria-hidden="true"></div>
        <div class="origin-art-caption"><span>One species. Countless journeys.</span><b>From ancient worlds<br>to your next discovery.</b><small>Original illustration · not a reconstruction of your ancestors</small></div>
      </div>
    </div>

    <nav class="ancestry-tool-shortcuts" aria-label="More DNA discoveries"><span>More ways to explore</span><button type="button" onclick="DnaDiscovery.open('sound')">♫ DNA Sound</button><button type="button" onclick="DnaDiscovery.open('portrait')">◌ Portrait Studio</button><button type="button" onclick="DnaDiscovery.open('ancient')">⌁ Ancient Explorer</button></nav>
    <div class="origin-inventory" id="origins-catalog-badges" hidden aria-label="Installed origins source library"></div>
    <div class="upload-card" id="dna-upload">
      <div class="drop-zone" id="drop-zone">
        <div class="drop-title"><i class="fas fa-cloud-arrow-up"></i> Upload raw DNA file</div>
        <p class="muted" id="dna-file-help" style="margin:.45rem 0 0">Choose an original, uncompressed raw-genotype export up to 30 MB. Supported inputs are 23andMe/Ancestry-style TXT, MyHeritage-style CSV, delimited TXT/CSV/TSV with marker, chromosome, position and genotype columns, and single-sample VCF with GT calls.</p>
        <input id="raw-dna-file" type="file" accept=".txt,.csv,.tsv,.vcf,.dna,.genome,.raw" aria-describedby="dna-file-help dna-compat-help">
        <details class="compat-details" id="dna-compat-help">
          <summary>Exact compatibility and privacy details</summary>
          <div>
            Accepted: <b>.txt, .csv, .tsv, .vcf, .dna, .genome, .raw</b> text files. ZIP/GZIP archives, VCF.GZ, BAM, CRAM, PDFs, images, executables and HTML are not accepted. Export or decompress the original vendor file first. The server parses the upload for this report and discards the raw file by default; optional private-vault saving is separate and capped at 5 MB.
          </div>
        </details>
        <fieldset class="safety-controls">
          <legend>Analysis and privacy controls</legend>
          <div class="control-grid">
            <div>
              <label for="declared-build">Reference genome build</label>
              <select id="declared-build">
                <option value="auto">Auto-detect from file</option>
                <option value="grch37">GRCh37 / hg19 — confirmed</option>
                <option value="grch38">GRCh38 / hg38 — confirmed</option>
              </select>
            </div>
            <div>
              <label for="reported-orientation">Reported strand orientation</label>
              <select id="reported-orientation">
                <option value="auto">Unknown / detect where possible</option>
                <option value="forward">Reference-forward — confirmed</option>
              </select>
            </div>
          </div>
          <p class="safety-hint">If you are unsure, leave either control on auto. Personalized health interpretation is withheld when build or strand confidence is insufficient; ZeroThink does not impute or silently flip alleles.</p>
          <label class="check-control" for="dna-consent">
            <input id="dna-consent" type="checkbox">
            <span><strong>I consent to the selected DNA and optional lineage files being processed for the report I request.</strong> I understand genetic data is highly sensitive, the output is research support rather than diagnosis, and I am authorised to process every file I attach. See the <a href="/privacy">Privacy Policy</a> and <a href="/terms">Terms</a>.</span>
          </label>
          <label class="check-control" for="include-sensitive-health">
            <input id="include-sensitive-health" type="checkbox">
            <span><strong>Include sensitive health research cards</strong>, such as APOE-related context, when the evidence gate permits. Off by default; important findings require certified confirmation and qualified clinical advice.</span>
          </label>
          <label class="check-control" for="save-raw-library">
            <input id="save-raw-library" type="checkbox">
            <span><strong>Save a private raw copy in my DNA vault</strong> (off by default, max 5 MB). Sensitive genotype files are metadata-only in Zero Library: never searchable and never added to AI context.</span>
          </label>
        </fieldset>
        <div class="file-status evidence-pulse" id="file-status" role="status" aria-live="polite">
          <div>File detected<b>None yet</b></div>
          <div>Quality<b>Pending</b></div>
          <div>Privacy<b>Discard raw by default</b></div>
          <div>Engine<b>DNA Lab v1.7.1</b></div>
        </div>
      </div>
      <div class="one-click">
        <p class="eyebrow" style="margin:0">Start here</p>
        <p class="muted" id="dna-run-hint" style="margin:0">Choose a raw DNA file, then review the processing consent to enable your report.</p>
        <button class="btn primary mega dna-action" type="button" onclick="runFullDnaReport()" disabled><i class="fas fa-dna"></i> Discover My DNA Story</button>
        <details class="advanced report-options"><summary>Choose a focused report</summary><div class="advanced-body secondary-actions">
          <button class="btn dna-action" type="button" onclick="runFileReport('dna_heritage_file')" disabled><i class="fas fa-earth-europe"></i> Heritage Signals</button>
          <button class="btn pink dna-action" type="button" onclick="runFileReport('dna_health_file')" disabled><i class="fas fa-heart-pulse"></i> Health Research</button>
          <button class="btn gold dna-action" type="button" onclick="runLineageReport()" disabled><i class="fas fa-crown"></i> Lineage Clues</button>
          <button class="btn gold dna-action" type="button" onclick="runAncientGraveReport()" disabled><i class="fas fa-landmark"></i> Ancient comparisons</button>
          <button class="btn dna-action" type="button" onclick="runRawSummary()" disabled><i class="fas fa-microscope"></i> Raw Quality</button>
          <button class="btn gold dna-action" type="button" onclick="runDnaSound()" disabled><i class="fas fa-wave-square"></i> DNA Sound <span class="experimental-tag">Experimental</span></button>
          <button class="btn pink dna-action" type="button" onclick="runTheoreticalReport()" disabled><i class="fas fa-wand-magic-sparkles"></i> Theoretical Analysis <span class="experimental-tag">Experimental</span></button>
        </div></details>
        <button class="btn" id="clear-dna-file" type="button" onclick="clearDnaFile()"><i class="fas fa-trash"></i> Clear file</button>
        <div class="run-status" id="dna-run-status" role="status" aria-live="polite" hidden>
          <span class="run-orbit" aria-hidden="true"></span><div><b id="dna-run-title">Ready</b><p id="dna-run-detail"></p></div>
          <progress id="dna-upload-progress" max="100" aria-label="File upload progress" hidden></progress>
        </div>
      </div>

    </div>

    <details class="advanced" style="position:relative;z-index:2;margin-top:16px">
      <summary><i class="fas fa-crown"></i> Optional Royal / Lineage Clues wizard</summary>
      <div class="advanced-body">
        <div class="lineage-orbit" aria-hidden="true">
          <span class="lineage-node"></span><span class="lineage-node"></span><span class="lineage-node"></span><span class="lineage-node"></span>
          <div class="lineage-orbit-label">DNA + records + places + time = lineage evidence</div>
        </div>
        <div class="note">This does not prove royal lineage. It builds a claim ledger from tree/story evidence. DNA is only supporting context unless you have direct Y-DNA, mtDNA, or segment/match evidence.</div>
        <div class="note" style="border-color:rgba(105,247,255,.22);background:rgba(105,247,255,.07);color:#dffff8">No family tree yet? You can still click Lineage Clues. ZeroThink will build a DNA-only starter showing which research lanes are possible and exactly what to add next.</div>
        <div class="lineage-grid">
          <div>
            <label for="family-side">Family side</label>
            <select id="family-side"><option value="unknown">Unknown</option><option value="mother">Mother</option><option value="father">Father</option><option value="both">Both / mixed</option></select>
          </div>
          <div>
            <label for="gedcom-file">GEDCOM family tree</label>
            <input id="gedcom-file" type="file" accept=".ged,.gedcom,.txt">
          </div>
          <div>
            <label for="relative-dna-files">Relative DNA files</label>
            <input id="relative-dna-files" type="file" multiple accept=".txt,.csv,.tsv,.vcf,.dna,.genome,.raw">
          </div>
        </div>
        <div class="lineage-grid">
          <div>
            <label for="mother-dna-file">Mother-side DNA file</label>
            <input id="mother-dna-file" type="file" accept=".txt,.csv,.tsv,.vcf,.dna,.genome,.raw">
          </div>
          <div>
            <label for="father-dna-file">Father-side DNA file</label>
            <input id="father-dna-file" type="file" accept=".txt,.csv,.tsv,.vcf,.dna,.genome,.raw">
          </div>
        </div>
        <div class="lineage-grid">
          <div><label for="lineage-surnames">Known surnames</label><input id="lineage-surnames" placeholder="Optional: family names, comma separated"></div>
          <div><label for="lineage-places">Known places</label><input id="lineage-places" placeholder="Optional: towns, regions, countries"></div>
        </div>
        <label for="lineage-dates">Approximate date ranges</label>
        <input id="lineage-dates" placeholder="Optional: 1750-1900, maternal side, Bengal / London etc">
        <label for="lineage-story">Family story notes</label>
        <textarea id="lineage-story" placeholder="Optional: paste the family story. The report will treat it as a clue, not proof."></textarea>
      </div>
    </details>

    <details class="advanced" id="reference-catalog" style="position:relative;z-index:2;margin-top:16px">
      <summary><i class="fas fa-layer-group"></i> Explore the reference panels</summary>
      <div class="advanced-body"><p class="muted">Browse the populations and marker coverage present in the reference catalog. The report chooses the applicable comparisons automatically.</p><label for="reference-search">Find a reference population</label><input id="reference-search" type="search" placeholder="Search a group name, region or code…" oninput="renderDnaCatalog(dnaReferenceCatalog)"><div id="reference-catalog-results" aria-live="polite">Loading reference catalog…</div><div id="origins-catalog-detail"></div></div>
    </details>
    <details class="advanced" style="position:relative;z-index:2;margin-top:16px">
      <summary><i class="fas fa-scale-balanced"></i> How ZeroThink judges results</summary>
      <div class="advanced-body">
        <div class="source-strip">
          <div><b>Raw QC</b><br>marker count, calls, no-calls, chromosomes, duplicates, build guess</div>
          <div><b>Health</b><br>ClinVar/GWAS/PharmGKB-ready research cards, not diagnosis or treatment advice</div>
          <div><b>Heritage</b><br>country and region signals with confidence, not identity proof</div>
          <div><b>Lineage</b><br>GEDCOM/story graph clues, source gaps, not royal proof</div>
        </div>
        <p class="muted">Black-box DNA reports often hide how sure they are. ZeroThink shows a clear Evidence Ledger so users can see what matched, what did not, and what still needs proof.</p>
      </div>
    </details>
  </section>

  <section class="panel discovery-studio" id="dna-portrait-studio" hidden aria-labelledby="portrait-title">
    <div class="discovery-heading"><div><p class="eyebrow">Your DNA, expressed in code</p><h2 id="portrait-title">DNA Portrait Studio</h2><p>Art from your DNA; facial features are stylized. Build a living portrait, explore the trait evidence, and make it your own.</p></div><button class="btn" type="button" onclick="DnaDiscovery.close('portrait')">Close studio</button></div>
    <div class="portrait-layout">
      <div class="portrait-stage"><canvas id="dna-portrait-canvas" width="960" height="1080" role="img" aria-label="Animated stylized portrait with fixed neutral facial geometry and an artistic DNA aura"></canvas><div class="portrait-stage-label"><span id="portrait-mode">Neutral artwork</span><b>LIVE · PURE CODE</b></div><p>DNA-inspired art · not an appearance prediction</p></div>
      <div class="portrait-controls">
        <div class="portrait-control-card"><span class="discovery-mini-badge">YOUR ART CHOICES</span><h3>Find your visual signature</h3><p>The palette and eye glow are your choices. DNA never determines the face shape, age, sex or ethnicity in this illustration.</p><label for="portrait-palette">Art palette</label><select id="portrait-palette" onchange="DnaDiscovery.configure('palette',this.value)"><option value="aurora">Aurora · jade and ice</option><option value="ember">Ember · copper and gold</option><option value="nebula">Nebula · violet and pearl</option><option value="silver">Silver · monochrome</option></select>
          <label for="portrait-eye-glow">Eye glow · chosen by you</label><select id="portrait-eye-glow" onchange="DnaDiscovery.configure('eye',this.value)"><option value="neutral">Neutral silver</option><option value="teal">Artistic teal</option><option value="amber">Artistic amber</option><option value="violet">Artistic violet</option></select>
          <label for="portrait-energy">Aura intensity</label><input type="range" id="portrait-energy" min="0" max="100" value="60" oninput="DnaDiscovery.configure('energy',this.value)">
          <label for="portrait-style">Portrait treatment</label><select id="portrait-style" onchange="DnaDiscovery.configure('style',this.value)"><option value="constellation">Constellation</option><option value="wireframe">Luminous wireframe</option><option value="minimal">Quiet silhouette</option></select>
          <div class="actions"><button class="btn" id="portrait-motion" type="button" onclick="DnaDiscovery.toggleMotion()">Pause portrait</button><button class="btn" type="button" onclick="DnaDiscovery.shuffle()">Shuffle art pattern</button></div><p class="portrait-footnote" id="portrait-pattern-note">Neutral illustration. Run a full report to let summary-level evidence shape the surrounding aura.</p>
        </div>
        <div class="portrait-control-card"><h3>Make it yours</h3><div class="portrait-export"><button class="btn primary" type="button" onclick="DnaDiscovery.exportPng()">Save PNG</button><button class="btn" type="button" onclick="DnaDiscovery.exportSvg()">Save SVG</button><button class="btn" id="portrait-record" type="button" onclick="DnaDiscovery.exportAnimation()">Save animated WebM</button></div><p class="portrait-footnote" id="portrait-export-note" role="status">Exports stay in your browser and retain the artistic label.</p></div>
        <div class="portrait-control-card portrait-evidence"><span class="discovery-mini-badge">WHAT YOUR FILE SUPPORTS</span><h3>Appearance evidence</h3><div id="portrait-trait-evidence"><p>No DNA-based appearance prediction is shown. Upload your raw file and run a full report to inspect the source-marker evidence.</p></div></div>
      </div>
    </div>
  </section>
  <section class="panel discovery-studio" id="dna-chromosome-studio" hidden aria-labelledby="chromosome-title">
    <div class="discovery-heading"><div><p class="eyebrow">Ancient evidence, in higher resolution</p><h2 id="chromosome-title">Chromosome Explorer</h2><p>Compare the same usable markers across ancient reference groups, one chromosome at a time.</p></div><button class="btn" type="button" onclick="DnaDiscovery.close('ancient')">Close explorer</button></div>
    <div id="discovery-chromosome-content"></div>
    <div class="discovery-explainer"><strong>Similarity is not an inherited percentage.</strong> The colors show relative reference comparisons on tested markers. Chromosome coverage and inherited ancestry are different measurements.</div>
  </section>

  <section class="grid report-workspace">
    <details class="panel advanced-console"><summary>Advanced research console <span>Sequence &amp; public variant tools</span></summary><div class="console-body">
      <h2>DNA Console</h2>
      <p class="muted">Most users should only upload a file and press one report button. The sequence box and variant lookup tools are for advanced research.</p>
      <label for="seq">DNA / RNA / FASTA Sequence</label>
      <textarea id="seq" placeholder="Optional advanced sequence input. Raw DNA users can ignore this."></textarea>
      <div class="actions">
        <button class="btn primary" type="button" onclick="analyzeLocal()">Analyze Sequence</button>
        <button class="btn" type="button" onclick="buildAlphaGenome()">AlphaGenome Plan</button>
        <button class="btn" type="button" onclick="loadExample()">Load Example</button>
        <button class="btn" type="button" onclick="document.getElementById('seq').value=''">Clear</button>
      </div>

      <details class="advanced">
        <summary><i class="fas fa-flask"></i> Advanced public variant lookup</summary>
        <div class="advanced-body">
          <div class="note">Only use this if you already know a variant ID, genomic region, or source search term. Beginners should use the report buttons above.</div>
          <label for="clinvar-rsids">ClinVar RSID Lookup</label>
          <input id="clinvar-rsids" value="" placeholder="Optional: paste RSIDs from your report">
          <div class="actions"><button class="btn pink" type="button" onclick="runClinVar()">Run ClinVar Lookup</button></div>

          <div class="mini-grid">
            <div>
              <label for="region">Ensembl Region</label>
              <input id="region" value="" placeholder="Optional: chr:start-end:strand">
            </div>
            <div>
              <label for="allele">Allele</label>
              <input id="allele" value="" placeholder="Optional: A, C, G, T, N, or -">
            </div>
          </div>
          <div class="actions"><button class="btn gold" type="button" onclick="runVep()">Run Ensembl VEP</button></div>

          <label for="ncbi-query">NCBI Search</label>
          <div class="mini-grid">
            <input id="ncbi-query" value="" placeholder="Optional: gene, SNP, paper, or trait search">
            <select id="ncbi-db"><option value="gene">Gene</option><option value="snp">SNP</option><option value="pubmed">PubMed</option><option value="nuccore">Nucleotide</option><option value="protein">Protein</option></select>
          </div>
          <div class="actions"><button class="btn gold" type="button" onclick="runNcbi()">Search NCBI</button></div>
        </div>
      </details>
    </div></details>

    <div class="panel" id="report-panel" tabindex="-1">
      <div class="report-heading"><div><p class="eyebrow">Your discovery workspace</p><h2>Your DNA story</h2></div><span class="report-badge" id="report-badge">Awaiting your file</span></div>
      <details class="compat-details"><summary>Engine connection status</summary><div class="status" id="status">Checking availability…</div></details>
      <div class="actions" style="margin-bottom:12px">
        <button class="btn" type="button" data-report-export disabled onclick="copyReport()">Copy Report</button>
        <button class="btn" type="button" data-report-export disabled onclick="downloadMarkdown()">Download Markdown</button>
        <button class="btn primary" type="button" data-report-export disabled onclick="window.DnaCustomer?.downloadReport()">Download readable report</button>
        <button class="btn" type="button" data-report-export disabled onclick="downloadPdf()">Print / save PDF</button>
      </div>
      <div id="empty-state" class="origin-empty">
        <div><p class="eyebrow">Ready when you are</p><h3>Where will your DNA take you?</h3><p>Upload a raw DNA file and select <b>Discover My DNA Story</b>. Your regional references, ancient comparisons and archaic-marker results will appear here.</p><button class="btn primary" type="button" onclick="showOriginsGuide('modern')">Explore the educational guide <span aria-hidden="true">→</span></button></div>
        <div class="origin-empty-orbit" aria-hidden="true"><i></i><i></i><i></i><span>A · C · G · T</span></div>
      </div>
      <div id="customer-workspace" hidden>
        <div id="customer-stale" class="customer-stale" role="status" hidden></div>
        <nav id="customer-nav" class="customer-nav" aria-label="Your report chapters"></nav>
        <section id="country-hub" class="country-hub" hidden aria-labelledby="country-title"></section>
        <section id="customer-overview" class="customer-overview" aria-labelledby="customer-title"></section>
        <section id="customer-classification" class="customer-classification" hidden aria-label="Published population model result"></section>
        <section id="customer-traits" class="customer-traits" hidden aria-labelledby="customer-traits-title"></section>
        <section id="customer-health" class="customer-health" hidden aria-labelledby="customer-health-title"></section>
        <section id="customer-input-help" class="customer-input-help" hidden aria-labelledby="customer-input-title"></section>
        <p id="customer-export-status" class="customer-export-status" role="status" aria-live="polite"></p>
      </div>
      <nav class="report-nav" id="report-nav" aria-label="Report support and evidence" hidden>
        <a id="origins-overview-link" href="#visual-report">Overview</a><a href="#dna-quality-panel" onclick="DnaAncestryStory.reveal('dna-quality-panel')">Quality &amp; coverage</a><a href="#dna-sources-panel" onclick="DnaAncestryStory.reveal('dna-sources-panel')">Sources</a><a href="#dna-agent-details" onclick="DnaAncestryStory.reveal('dna-agent-details')">Ask DNA Agent</a><a href="#dna-evidence-heading" onclick="DnaAncestryStory.reveal('dna-full-evidence')">Full evidence</a>
      </nav>
      <section class="origins-report" id="origins-report" hidden aria-labelledby="origins-title">
        <div class="origins-intro"><div><p class="eyebrow" id="origins-mode">Your origins journey</p><h3 id="origins-title">A deeper view of your origins</h3><p id="origins-summary"></p></div><button class="btn" type="button" id="origins-return" hidden onclick="restoreOriginsReport()">Return to my results</button></div>
        <div class="origin-tabs" role="tablist" aria-label="Explore DNA origins" onkeydown="originsTabKey(event)">
          <button type="button" role="tab" id="origins-tab-modern" aria-controls="origins-panel-modern" aria-selected="true" onclick="activateOriginsTab('modern')"><span>01</span> Regional origins</button>
          <button type="button" role="tab" id="origins-tab-ancient" aria-controls="origins-panel-ancient" aria-selected="false" tabindex="-1" onclick="activateOriginsTab('ancient')"><span>02</span> Ancient world</button>
          <button type="button" role="tab" id="origins-tab-archaic" aria-controls="origins-panel-archaic" aria-selected="false" tabindex="-1" onclick="activateOriginsTab('archaic')"><span>03</span> Archaic connections</button>
        </div>
        <div role="tabpanel" id="origins-panel-modern" aria-labelledby="origins-tab-modern"><div id="origins-modern-content"></div></div>
        <div role="tabpanel" id="origins-panel-ancient" aria-labelledby="origins-tab-ancient" hidden><div id="origins-ancient-content"></div></div>
        <div role="tabpanel" id="origins-panel-archaic" aria-labelledby="origins-tab-archaic" hidden><div id="origins-archaic-content"></div></div>
        <div class="origin-highlights" id="origins-highlights"></div>
        <details class="origin-methods"><summary>How to read your origins journey</summary><p>Regional affinities compare your observed calls with reference datasets. Ancient comparisons measure overlap with public ancient samples. Archaic marker screens count observed research tags in the portion of a panel your file can test. These measurements answer different questions; none should be converted into a percentage of your entire genome or a statement of ethnicity, nationality or identity.</p><div id="origins-evidence-cards"></div></details>
      </section>
      <details class="origin-depth" id="origins-report-depth" open hidden><summary>Reference scores, report cards &amp; detailed analysis <span>Explore the measurements behind your results</span></summary>
      <div class="result-grid" id="visual-report" style="display:none">
        <div class="chart-card">
          <h3 id="chart-title">Signal Pie Chart</h3>
          <div id="dna-reference-bars" hidden></div>
          <canvas id="dna-chart" width="300" height="300" aria-label="DNA report pie chart"></canvas>
          <div class="legend" id="chart-legend"></div>
          <p class="muted" id="chart-note"></p>
          <div class="map-card" id="dna-map-card">
            <div class="map-head">
              <div><strong>Living Heritage Map</strong><span>Tap a glowing region for details, or expand the map to explore your DNA signals full-screen.</span></div>
              <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end"><span id="map-count">0 regions</span><button class="btn map-expand" type="button" onclick="openDnaMapModal();event.stopPropagation();"><i class="fas fa-expand"></i> Expand</button></div>
            </div>
            <div class="dna-map-wrap" role="button" tabindex="0" aria-label="Expand the interactive DNA world map" onclick="openDnaMapModal()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openDnaMapModal();}">
              <svg class="dna-map" id="dna-world-map" viewBox="0 0 720 360" role="img" aria-label="Interactive DNA world map"></svg>
            </div>
            <div class="map-info" id="dna-map-info"><small>Map ready</small><b>Select a region</b><p>The selected region explanation appears here.</p></div>
            <div class="map-buttons" id="dna-map-buttons"></div>
          </div>
        </div>
        <div>
          <div class="metric-grid" id="metrics"></div>
          <div id="plain-cards"></div>
        </div>
      </div>
      </details>
      <details class="evidence-workspace ancestry-raw-details" id="dna-quality-panel" hidden><summary>Quality, marker coverage &amp; checks <span>Inspect the evidence supporting this result</span></summary>
        <div class="evidence-heading"><div><p class="eyebrow">Start with what was measured</p><h3 id="dna-quality-heading">Quality &amp; reference coverage</h3></div></div>
        <p class="muted">File quality describes the usable input. It is separate from the accuracy of any heritage or health interpretation.</p>
        <div class="coverage-grid" id="dna-coverage"></div>
        <div id="dna-chromosomes"></div>
        <div class="note" id="dna-quality-notes" hidden></div>
      </details>
      <details class="evidence-workspace ancestry-raw-details" id="dna-sources-panel" hidden><summary>Reference sources &amp; methods <span>Study datasets, releases and limitations</span></summary>
        <p class="eyebrow">Trace the result</p><h3 id="dna-sources-heading">Reference sources &amp; evidence</h3>
        <p class="muted">Installed data and source overlap are shown separately. A larger database alone does not establish a more accurate result.</p>
        <div id="dna-source-ledger"></div>
      </details>
      <details class="ancestry-raw-details ancestry-agent-details" id="dna-agent-details" hidden><summary>Ask DNA Agent about this report <span>Optional · review the privacy controls before sending</span></summary>
      <div class="agent-box" id="agent-box" style="display:none">
        <h3><i class="fas fa-brain"></i> DNA Agent</h3>
        <p class="muted">Ask follow-up questions about the generated report. This is a separate, optional AI step; report generation itself does not require the DNA Agent.</p>
        <div class="agent-disclosure" id="dna-agent-disclosure">
          If you continue, your question and a minimized derived summary are sent to the configured Groq model. The summary excludes raw rows, file name, file hash, exact genotypes, RSIDs and family-tree text. ZeroThink runs this request without its saved chats, Zero Library, account memory or web retrieval and does not add the exchange to ZeroThink chat history or memory. Groq processes the submitted data under its own terms. Review <a href="/privacy">Privacy</a> before opting in.
        </div>
        <label class="check-control" for="dna-agent-consent">
          <input id="dna-agent-consent" type="checkbox">
          <span><strong>I consent to sending this minimized summary and my question to Groq for this report.</strong></span>
        </label>
        <div class="pill-row" id="agent-prompts"></div>
        <label for="agent-question">Question for DNA Agent</label>
        <textarea id="agent-question" aria-describedby="dna-agent-disclosure" placeholder="Ask: What does this mean in plain English? Which regions should I explore? What should I verify?"></textarea>
        <div class="actions"><button class="btn primary" id="dna-agent-submit" type="button" onclick="askDnaAgent()" disabled>Ask DNA Agent</button></div>
        <div class="agent-log" id="agent-log" role="status" aria-live="polite">Agent answer appears here.</div>
      </div>
      </details>
      <details class="ancestry-raw-details" id="dna-full-evidence" hidden><summary id="dna-evidence-heading">Full technical report <span>All measurements and source notes · exports remain available above</span></summary><div class="report-body" id="out" tabindex="0" aria-label="Full DNA evidence report">Results appear here.</div></details>
      <section class="ancestry-more-tools" aria-label="Creative DNA discoveries"><div class="ancestry-tools-heading"><p class="eyebrow">Keep exploring</p><h3>Bring your DNA story to life</h3></div>    <div class="discovery-launch" aria-label="Creative DNA discoveries">
      <button type="button" onclick="DnaDiscovery.open('portrait')"><span class="discovery-card-art portrait-symbol" aria-hidden="true"><i></i><i></i><i></i></span><span><small>01 · CREATE</small><b>DNA Portrait Studio</b><em>A live portrait drawn entirely in code</em></span><strong aria-hidden="true">↗</strong></button>
      <button type="button" onclick="DnaDiscovery.open('sound')"><span class="discovery-card-art sound-symbol" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span><span><small>02 · LISTEN</small><b>DNA Sound</b><em>Play your composition · save a WAV</em></span><strong aria-hidden="true">↗</strong></button>
      <button type="button" onclick="DnaDiscovery.open('ancient')"><span class="discovery-card-art chromosome-symbol" aria-hidden="true"><i></i><i></i><i></i><i></i></span><span><small>03 · EXPLORE</small><b>Ancient Chromosome Explorer</b><em>See the evidence, chromosome by chromosome</em></span><strong aria-hidden="true">↗</strong></button>
    </div>
      <div class="sonic-panel" id="dna-sound-panel">
        <div class="sonic-grid">
          <div>
            <div class="sonic-title"><i class="fas fa-music"></i> Your DNA, composed</div><span class="discovery-mini-badge">DNA Sound · ZDNA-SONIC</span>
            <p class="muted" id="dna-sound-note" style="margin:.45rem 0 0">Generate a privacy-safe ambience score from the uploaded markers, then listen or download a WAV made in your browser.</p>
            <canvas id="dna-wave" width="820" height="160" aria-label="DNA sound composition waveform"></canvas>
            <div class="sound-transport"><span id="discovery-sound-time">0:00 / 0:00</span><label for="discovery-sound-seek" class="sr-only">Seek in DNA sound</label><input id="discovery-sound-seek" type="range" min="0" max="1000" value="0" disabled oninput="DnaDiscovery.seekSound(this.value)"><span id="discovery-sound-state" role="status">Ready after analysis</span></div>
          </div>
          <div class="sonic-controls">
            <button class="btn primary" id="discovery-sound-play" type="button" onclick="playDnaSound()" disabled><i class="fas fa-play"></i> Play DNA Sound</button>
            <button class="btn" type="button" onclick="stopDnaSound()"><i class="fas fa-stop"></i> Stop</button>
            <button class="btn gold" id="discovery-sound-download" type="button" onclick="downloadDnaSound()" disabled><i class="fas fa-download"></i> Download WAV</button>
            <button class="btn dna-action" id="discovery-sound-generate" type="button" onclick="runDnaSound()" disabled>Generate DNA Sound</button>
            <div class="sound-volume"><label for="discovery-sound-volume">Volume</label><input id="discovery-sound-volume" type="range" min="0" max="100" value="55" oninput="DnaDiscovery.volume(this.value)"></div>
            <button class="btn" type="button" onclick="DnaDiscovery.open('portrait')">Pair with your portrait ↗</button>
          </div>
        </div>
      </div></section>
    </div>
  </section>
  <footer class="lab-footer">
    <span>ZeroThink DNA Lab · research support with explicit privacy controls</span>
    <nav aria-label="Legal links"><a href="/privacy">Privacy</a><a href="/terms">Terms</a><a href="/faq#library">DNA FAQ</a></nav>
  </footer>
</main>
<div class="map-modal" id="dna-map-modal" aria-hidden="true">
  <div class="map-modal-head">
    <div class="map-modal-title">
      <strong>Living Heritage Map</strong>
      <span>Drag to move. Use zoom to inspect your country and region signals.</span>
    </div>
    <div class="map-modal-tools">
      <button class="map-tool" type="button" onclick="zoomDnaMap(1.25)"><i class="fas fa-plus"></i> Zoom</button>
      <button class="map-tool" type="button" onclick="zoomDnaMap(0.8)"><i class="fas fa-minus"></i> Out</button>
      <button class="map-tool" type="button" onclick="resetDnaMapView()"><i class="fas fa-crosshairs"></i> Reset</button>
      <button class="map-tool primary" type="button" onclick="closeDnaMapModal()"><i class="fas fa-xmark"></i> Close</button>
    </div>
  </div>
  <div class="map-modal-stage">
    <svg class="dna-map" id="dna-world-map-full" viewBox="0 0 720 360" role="img" aria-label="Expanded interactive DNA world map"></svg>
  </div>
  <div class="map-modal-foot">
    <div class="map-buttons" id="dna-map-full-buttons"></div>
    <div class="map-info" id="dna-map-full-info"><small>Map ready</small><b>Select a region</b><p>The selected region explanation appears here.</p></div>
  </div>
</div>
<script>
const DNA_CSRF_TOKEN = <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const helix = document.getElementById('helix');
for (let i = 0; i < 25; i++) {
  const b = document.createElement('span');
  b.className = 'base';
  b.style.setProperty('--i', i);
  helix.appendChild(b);
}

let lastReport = '';
let lastReportData = null;
let lastReportKind = '';
let lastAgentContext = null;
let selectedDnaFile = null;
let currentDnaSummary = null;
let dnaRequestBusy = false;
let dnaDisabledControls = new Map();
const dnaReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
let dnaMotionPaused = dnaReducedMotion.matches;
let lastDnaSoundProfile = null;
let lastDnaAudioBuffer = null;
let dnaAncestryCatalog = null;
let dnaAudioCtx = null;
let dnaAudioSource = null;
const ZERO_LIBRARY_MAX_SAVE_BYTES = 5 * 1024 * 1024;
const ZERO_DNA_MAX_RAW_UPLOAD_BYTES = 30 * 1024 * 1024;

function esc(v){return String(v ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));}
function setOut(text){document.getElementById('out').textContent = text; const detail=document.getElementById('dna-full-evidence');if(detail) detail.hidden=!text || text==='Results appear here.';}
function updateDnaAgentControls(){
  const consent = !!document.getElementById('dna-agent-consent')?.checked;
  const submit = document.getElementById('dna-agent-submit');
  if(submit) submit.disabled = !consent || !lastAgentContext;
}
function rememberReport(text, data, kind, agentContext = null){
  lastReport = text || '';
  lastReportData = data || null;
  lastReportKind = kind || '';
  lastAgentContext = agentContext && typeof agentContext === 'object' ? agentContext : null;
  const agentConsent = document.getElementById('dna-agent-consent');
  if(agentConsent) agentConsent.checked = false;
  updateDnaAgentControls();
  setOut(lastReport);
  renderVisualReport(data, kind);
  renderDnaReferenceBars(data, kind);
  renderDnaEvidence(data, kind);
  renderDnaOrigins(data, kind);
  window.DnaDiscovery?.setReport(data, kind);
  window.DnaAncestryStory?.reportChanged(data, kind);
  document.getElementById('origins-report-depth').hidden = !data;
  document.getElementById('origins-report-depth').open = !getDnaOrigins(data);
  document.querySelectorAll('[data-report-export]').forEach(button => button.disabled = !lastReport);
  document.getElementById('report-nav').hidden = !data;
  document.getElementById('report-badge').textContent = kind.replaceAll('_',' ') + ' · report ready';
  window.DnaCustomer?.setReport(data, kind);
}

function formatBytes(bytes){
  bytes = Number(bytes || 0);
  if(bytes >= 1024*1024) return (bytes/(1024*1024)).toFixed(1) + ' MB';
  if(bytes >= 1024) return (bytes/1024).toFixed(1) + ' KB';
  return bytes + ' B';
}
function guessVendorFromName(file){
  const name = String(file?.name || '').toLowerCase();
  if(name.includes('myheritage')) return 'MyHeritage-style';
  if(name.includes('23andme') || name.includes('genome')) return '23andMe-style';
  if(name.includes('ancestry')) return 'Ancestry-style';
  if(name.endsWith('.vcf')) return 'VCF';
  return 'Raw genotype file';
}
function updateDnaFileStatus(file, serverSummary = null){
  const nextFile = file || document.getElementById('raw-dna-file').files?.[0] || null;
  if(nextFile !== selectedDnaFile){
    stopDnaSound(); lastDnaSoundProfile = null; lastDnaAudioBuffer = null;
    window.DnaDiscovery?.invalidate('File changed. Run a report to update your creative results.');
    window.DnaCustomer?.markStale('You selected a different file. These results belong to the previous analysis. Run Discover My DNA Story to update them.');
    currentDnaSummary = null;
    document.getElementById('dna-consent').checked = false;
    document.getElementById('dna-run-status').hidden = true;
    if(lastReport) document.getElementById('report-badge').textContent = 'Previous file report · run again for this file';
  }
  selectedDnaFile = nextFile;
  const hasFile = !!selectedDnaFile;
  const hasConsent = !!document.getElementById('dna-consent')?.checked;
  const overRawUploadLimit = hasFile && Number(selectedDnaFile.size || 0) > ZERO_DNA_MAX_RAW_UPLOAD_BYTES;
  const fileIssue = validateDnaFile(selectedDnaFile);
  const canAnalyze = hasFile && hasConsent && !fileIssue && !dnaRequestBusy;
  const hint = document.getElementById('dna-run-hint');
  hint.textContent = dnaRequestBusy ? 'Your report is running. Results will appear below when the server completes the analysis.' : (fileIssue || (!hasFile ? 'Choose a raw DNA file, then review the processing consent to enable your report.' : !hasConsent ? 'File selected. Review the privacy controls and give consent to enable analysis.' : 'Ready. Run the full report, or choose a focused report below.'));
  document.getElementById('step-file').className = hasFile ? 'done' : 'current';
  document.getElementById('step-consent').className = hasConsent ? 'done' : (hasFile ? 'current' : '');
  document.getElementById('step-report').className = lastReport ? 'done' : (canAnalyze || dnaRequestBusy ? 'current' : '');
  document.querySelectorAll('.dna-action').forEach(btn => btn.disabled = !canAnalyze);
  const s = serverSummary || currentDnaSummary || null;
  const wantsSave = document.getElementById('save-raw-library').checked;
  const overLibrarySaveLimit = hasFile && Number(selectedDnaFile.size || 0) > ZERO_LIBRARY_MAX_SAVE_BYTES;
  const privacyLabel = !hasConsent ? 'Consent required' : (wantsSave ? (overLibrarySaveLimit ? 'Analyze only; over 5 MB save limit' : 'Private vault save on') : 'Discard raw by default');
  const sizeLabel = hasFile
    ? (overRawUploadLimit ? `${formatBytes(selectedDnaFile.size)} (over 30 MB limit)` : formatBytes(selectedDnaFile.size))
    : 'No file';
  document.getElementById('file-status').innerHTML = hasFile ? [
    `<div>File detected<b>${esc(s?.file_name || selectedDnaFile.name)}</b></div>`,
    `<div>${s?.parsed_variants ? 'Markers found' : 'Size'}<b>${s?.parsed_variants ? Number(s.parsed_variants || 0).toLocaleString() : esc(sizeLabel)}</b></div>`,
    `<div>${s?.quality_band ? 'File quality' : 'Filename hint'}<b>${esc(s?.quality_band ? `${s.quality_band} (${s.quality_score ?? '?'}/100)` : guessVendorFromName(selectedDnaFile))}</b></div>`,
    `<div>${s?.genome_build_guess ? 'Build guess' : 'Privacy'}<b>${esc(s?.genome_build_guess || privacyLabel)}</b></div>`
  ].join('') : [
    '<div>File detected<b>None yet</b></div>',
    '<div>Quality<b>Pending</b></div>',
    '<div>Privacy<b>Discard raw by default</b></div>',
    '<div>Engine<b>DNA Lab v1.7.1</b></div>'
  ].join('');
}
function clearDnaFile(){
  if(dnaRequestBusy) return;
  currentDnaSummary = null;
  ['raw-dna-file', 'gedcom-file', 'relative-dna-files', 'mother-dna-file', 'father-dna-file'].forEach(id => {
    const input = document.getElementById(id);
    if(input) input.value = '';
  });
  ['dna-consent', 'include-sensitive-health', 'save-raw-library', 'dna-agent-consent'].forEach(id => {
    const input = document.getElementById(id);
    if(input) input.checked = false;
  });
  const build = document.getElementById('declared-build');
  const orientation = document.getElementById('reported-orientation');
  const familySide = document.getElementById('family-side');
  if(build) build.value = 'auto';
  if(orientation) orientation.value = 'auto';
  if(familySide) familySide.value = 'unknown';
  ['lineage-surnames', 'lineage-places', 'lineage-dates', 'lineage-story'].forEach(id => {
    const input = document.getElementById(id);
    if(input) input.value = '';
  });
  selectedDnaFile = null;
  lastReport = '';
  lastReportData = null;
  lastReportKind = '';
  lastAgentContext = null;
  lastDnaSoundProfile = null;
  lastDnaAudioBuffer = null;
  stopDnaSound();
  document.getElementById('dna-sound-panel').style.display = 'block';
  window.DnaDiscovery?.invalidate('File cleared. The portrait is neutral artwork.');
  window.DnaAncestryStory?.reportChanged(null, '');
  document.getElementById('agent-question').value = '';
  document.getElementById('agent-log').textContent = 'Agent answer appears here.';
  updateDnaAgentControls();
  updateDnaFileStatus(null);
  renderVisualReport(null, '');
  setOut('Results appear here.');
  renderDnaEvidence(null, '');
  renderDnaOrigins(null, '');
  document.getElementById('origins-report-depth').hidden = true;
  document.getElementById('dna-run-status').hidden = true;
  document.getElementById('report-nav').hidden = true;
  document.getElementById('report-badge').textContent = 'Awaiting your file';
  document.querySelectorAll('[data-report-export]').forEach(button => button.disabled = true);
  window.DnaCustomer?.setReport(null, '');
}
async function parseJsonResponse(r) {
  const text = await r.text();
  let j = null;
  try {
    j = JSON.parse(text);
  } catch(e) {
    throw new Error(r.status === 401 || r.status === 403 || /<html|<!doctype/i.test(text) ? 'Your session may have expired or access was blocked. Sign in again in another tab, then retry.' : 'The server returned an unreadable response (HTTP ' + r.status + '). Please try again later.');
  }
  if(!j || typeof j !== 'object' || Array.isArray(j)) throw new Error('The server did not return a valid report response. Please try again later.');
  if (!r.ok || j.status === 'error') {
    throw new Error(j.message || j.reply || ('Request failed with HTTP ' + r.status));
  }
  return j;
}
async function post(action, payload = {}) {
  const r = await fetch('/api_library.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action, ...payload, csrf_token:DNA_CSRF_TOKEN})});
  return parseJsonResponse(r);
}
function loadExample(){document.getElementById('seq').value='>Zero DNA sample\\nATGGCCATTGTAATGGGCCGCTGAAAGGGTGCCCGATAG';}

async function refreshStatus(){
  try {
    const j = await post('dna_status');
    const providers = j.providers || {};
    renderDnaCatalog(j.reference_catalog || null);
    renderOriginsCatalog(j.origins_catalog || null);
    dnaAncestryCatalog = j.ancestry_story_catalog || null;
    window.DnaAncestryStory?.setCatalog(dnaAncestryCatalog);
    document.getElementById('status').innerHTML = Object.keys(providers).slice(0,4).map(k => `<div><b>${esc(k.replaceAll('_',' '))}</b><br>${esc(providers[k].status || providers[k].mode || 'ready')}</div>`).join('');
  } catch(e) {
    document.getElementById('status').innerHTML = '<div>Status unavailable</div>';
    renderDnaCatalog(null);
    renderOriginsCatalog(null);
  }
}

function getDnaFile(){
  const file = selectedDnaFile || document.getElementById('raw-dna-file').files[0];
  if(!file) {
    alert('Choose your raw DNA file first.');
    return null;
  }
  if(!document.getElementById('dna-consent')?.checked) {
    alert('Confirm the DNA privacy and research-use notice before running a report.');
    return null;
  }
  const issue = validateDnaFile(file);
  if(issue){showDnaRunState('error', 'Check your file', issue); return null;}
  return file;
}

function appendDnaSafetyFields(fd){
  fd.set('csrf_token', DNA_CSRF_TOKEN);
  fd.set('dna_consent', document.getElementById('dna-consent')?.checked ? '1' : '0');
  fd.set('declared_build', document.getElementById('declared-build')?.value || 'auto');
  fd.set('reported_orientation', document.getElementById('reported-orientation')?.value || 'auto');
  fd.set('include_sensitive_health', document.getElementById('include-sensitive-health')?.checked ? '1' : '0');
  return fd;
}

function appendLineageFields(fd){
  fd.append('family_side', document.getElementById('family-side').value || 'unknown');
  fd.append('surnames', document.getElementById('lineage-surnames').value || '');
  fd.append('places', document.getElementById('lineage-places').value || '');
  fd.append('date_ranges', document.getElementById('lineage-dates').value || '');
  fd.append('story_notes', document.getElementById('lineage-story').value || '');
  const ged = document.getElementById('gedcom-file').files[0];
  if(ged) fd.append('gedcom_file', ged);
  const relatives = document.getElementById('relative-dna-files')?.files || [];
  Array.from(relatives).slice(0, 3).forEach(file => fd.append('relative_dna_files[]', file));
  const mother = document.getElementById('mother-dna-file')?.files?.[0];
  const father = document.getElementById('father-dna-file')?.files?.[0];
  if(mother) fd.append('mother_dna_file', mother);
  if(father) fd.append('father_dna_file', father);
}

async function runDnaUpload(action, kind, label, lineage = false){
  if(dnaRequestBusy) return;
  const file = getDnaFile();
  if(!file) return;
  const fd = new FormData();
  fd.append('action', action);
  fd.append('dna_file', file);
  appendDnaSafetyFields(fd);
  if(lineage) appendLineageFields(fd);
  if(['raw','full'].includes(kind) && document.getElementById('save-raw-library').checked) fd.append('save_to_library','1');
  setDnaBusy(true);
  showDnaRunState('busy', 'Uploading · ' + label, 'Transferring the selected file to your ZeroThink server. Keep this page open.');
  try {
    const j = await uploadDnaForm(fd, label);
    let markdown = j.markdown || '';
    if(j.library_file) markdown += '\n\nPrivate Library copy: ' + j.library_file.path + ' (' + j.library_file.size + ' bytes)';
    if(j.library_notice) markdown += '\n\nZero Library note: ' + j.library_notice;
    const data = kind === 'raw' ? {summary:j.analysis, chart:qualityChart(j.analysis), insights:{headline:'Raw file quality summary', plain_summary:'Measured input quality is separate from the accuracy of a genetic interpretation.', cards:[]}} : j.report;
    if(!data || !markdown) throw new Error('The server did not return a complete report. Your previous report is preserved.');
    currentDnaSummary = data.raw || data.summary || null;
    rememberReport(markdown, data, kind, j.agent_context);
    if(kind === 'health' && Array.isArray(data.clinvar_rsids)) document.getElementById('clinvar-rsids').value = data.clinvar_rsids.join(' ');
    if(kind === 'sound'){
      lastDnaSoundProfile = j.profile || data.profile || null;
      lastDnaAudioBuffer = null;
      renderDnaSound(lastDnaSoundProfile);
    }
    showDnaRunState('complete', label + ' ready', 'Review measured quality, reference coverage and the evidence below. Your report is ready to copy or export.');
    document.getElementById(kind === 'sound' ? 'dna-sound-panel' : 'report-panel').scrollIntoView({behavior:dnaMotionAllowed() ? 'smooth' : 'instant', block:'start'});
  } catch(e) {
    showDnaRunState('error', 'Report could not complete', (e.message || 'Connection failed.') + (lastReport ? ' Your previous report remains available below.' : ' Check the file and try again when the connection is available.'));
  } finally {
    setDnaBusy(false);
    updateDnaFileStatus(file, currentDnaSummary);
  }
}
function runRawSummary(){return runDnaUpload('dna_raw_file','raw','Raw quality');}
function runFileReport(action){return runDnaUpload(action,action === 'dna_health_file' ? 'health':'heritage',action === 'dna_health_file' ? 'Health research':'Heritage signals');}
function runDnaSound(){
  if(lastDnaSoundProfile && window.DnaDiscovery){window.DnaDiscovery.open('sound');return;}
  return runDnaUpload('dna_sound_file','sound','DNA sound');
}
function runFullDnaReport(){return runDnaUpload('dna_full_file','full','Full DNA report',true);}
function runLineageReport(){return runDnaUpload('dna_lineage_file','lineage','Lineage clues',true);}
function runAncientGraveReport(){return runDnaUpload('dna_ancient_file','ancient','Ancient affinity');}
function runTheoreticalReport(){return runDnaUpload('dna_theory_file','theory','Theoretical research',true);}

function qualityChart(a){
  const parsed = Number(a?.parsed_variants || 0);
  const called = Number(a?.called_variants || 0);
  const noCalls = Number(a?.no_calls || 0);
  const complex = Number(a?.indel_or_complex || 0);
  return [
    {label:'Called variants', value:Math.max(0, called - complex), percent:parsed ? Math.max(0, Math.round(((called-complex)/parsed)*1000)/10) : 0, color:'#00ff91'},
    {label:'Indel/complex rows', value:Math.max(0, complex), percent:parsed ? Math.round((complex/parsed)*1000)/10 : 0, color:'#69f7ff'},
    {label:'No calls', value:Math.max(0, noCalls), percent:parsed ? Math.round((noCalls/parsed)*1000)/10 : 0, color:'#ff7b7b'}
  ];
}

function renderVisualReport(report, kind){
  const empty = document.getElementById('empty-state');
  const visual = document.getElementById('visual-report');
  const agent = document.getElementById('agent-box');
  if(!report){
    empty.style.display = 'block';
    visual.style.display = 'none';
    agent.style.display = 'none';
    return;
  }
  empty.style.display = 'none';
  visual.style.display = 'grid';
  agent.style.display = ['health','heritage','raw','full','lineage','sound','theory','ancient'].includes(kind) ? 'block' : 'none';
  const insights = report.insights || {};
  const summary = {...(report.summary || {}), ...(report.raw || {})};
  const quality = insights.quality || report.quality || {};
  const chart = report.chart || insights.chart || report.chart_data || [];
  const parentAware = report.parent_aware_estimate && report.parent_aware_estimate.available;
  let chartTitle = 'Raw Quality Pie Chart';
  let chartNote = 'File quality proportions from the local parser.';
  if(parentAware){
    chartTitle = 'Parent-Aware DNA Split';
    chartNote = 'A parent DNA file was supplied, so the chart shows the known parent side and the estimated remaining side.';
  } else if(kind === 'full'){
    chartTitle = 'Full DNA Reference Snapshot';
    chartNote = 'Relative reference signals from this analysis. Chart shares are not validated ancestry percentages or probabilities. Check measured overlap and sources below.';
  } else if(kind === 'ancient'){
    chartTitle = 'Ancient Region & Grave Context';
    chartNote = 'Wheel uses broader country/region context. Exact grave/site matches are separate nearest-sample clues, not ancestry percentages.';
  } else if(kind === 'lineage'){
    chartTitle = 'Lineage Heritage & Ancient Context';
    chartNote = 'Plain country and ancient-context leads first. The proof ledger sits underneath so users know what is evidence, theory, or missing.';
  } else if(kind === 'heritage'){
    chartTitle = 'Heritage Signal Wheel';
    chartNote = 'Relative reference signals only. Chart shares are not validated ancestry percentages or probabilities; interpretation depends on panel coverage and validation.';
  } else if(kind === 'health'){
    chartTitle = 'Health Research Pie Chart';
    chartNote = 'Research marker categories only. This is not a medical risk score.';
  } else if(kind === 'sound'){
    chartTitle = 'DNA Sound Tone Mix';
    chartNote = 'Base-tone mix from the derived sonification profile, with an optional ambient harmonic layer. Experimental ambience only.';
  } else if(kind === 'theory'){
    chartTitle = 'Theoretical Hypothesis Wheel';
    chartNote = 'Hypothesis mode: research leads, not proof of ethnicity, religion, royalty, or famous relatives.';
  }
  document.getElementById('chart-title').textContent = chartTitle;
  document.getElementById('chart-note').textContent = chartNote;
  drawPie(chart);
  renderDnaWorldMap(report, kind);
  const metrics = [
    ['File', summary.file_name || report.variant_index?.file_name || 'DNA file'],
    ['Parsed', Number(summary.parsed_variants || summary.marker_count || 0).toLocaleString()],
    ['Called', dnaNumber(summary.called_variants)],
    ['No-call', summary.no_call_percent == null ? 'Not reported' : fmtPct(summary.no_call_percent, 3) + '%'],
    ['Heterozygosity', summary.heterozygosity_percent == null ? 'Not reported' : fmtPct(summary.heterozygosity_percent, 3) + '%'],
    ['Engine', getDnaOrigins(report) ? 'DNA Lab v1.7.1' : (insights.math_system ? 'ZDNA-MAP v1' : (summary.engine_version || 'local parser'))],
    ['Input quality', quality.score !== undefined ? `${quality.score}/100` : (summary.quality_score !== undefined ? `${summary.quality_score}/100` : 'research only')],
    ['Quality band', summary.quality_band || 'Not reported']
  ];
  document.getElementById('metrics').innerHTML = metrics.map(([k,v]) => `<div class="metric"><span>${esc(k)}</span><b>${esc(v)}</b></div>`).join('');
  const cards = [];
  if(insights.headline){
    cards.push(`<div class="summary-card"><strong>${esc(insights.headline)}</strong><p>${esc(insights.plain_summary || '')}</p></div>`);
  }
  if(insights.math_system){
    const q = quality.score !== undefined ? `Quality ${quality.score}/100 · ${quality.confidence || 'checked'}` : 'Quality checked';
    const extra = insights.uncertainty !== undefined ? ` · Uncertainty ${insights.uncertainty}/100` : '';
    cards.push(`<div class="summary-card"><strong>${esc(insights.math_system)}</strong><p>${esc(q + extra)}<br>${esc(insights.math_summary || '')}</p></div>`);
  }
  if(kind === 'health' && insights.domain_scores && typeof insights.domain_scores === 'object'){
    const domains = Object.entries(insights.domain_scores).slice(0, 5).map(([k,v]) => `${esc(k)}: ${esc(v)}`).join('<br>');
    if(domains) cards.push(`<div class="summary-card"><strong>Evidence domains</strong><p>${domains}</p></div>`);
  }
  if(kind === 'health' && insights.blood_type_research?.available !== false && insights.blood_type_research && typeof insights.blood_type_research === 'object'){
    const b = insights.blood_type_research;
    cards.push(`<div class="summary-card"><strong>${esc(b.label || 'Blood-type clue')}</strong><p>${esc(b.boundary || 'Research-only clue.')}<br>${(Array.isArray(b.markers_seen) && b.markers_seen.length) ? b.markers_seen.map(esc).join('<br>') : 'No watched ABO markers present.'}</p></div>`);
  }
  if(kind === 'sound' && report.profile){
    const p = report.profile;
    const layerNote = p.harmonic_layer?.enabled ? `<br>${esc(p.harmonic_layer.public_note || 'Ambient harmonic layer active.')}` : '';
    cards.push(`<div class="summary-card"><strong>${esc(p.engine || 'DNA sound profile')}</strong><p>Mode: ${esc(p.mode || 'ambient')} · ${esc(p.bpm || '?')} BPM · ${esc(p.duration_seconds || '?')} seconds<br>${esc(p.truth_boundary || '')}${layerNote}</p></div>`);
  }
  if(kind === 'ancient'){
    const pack = report.ancient_pack || {};
    const packStatus = pack.installed ? `Active · ${Number(pack.matched_marker_count || 0).toLocaleString()} ancient markers matched` : 'No active ancient source pack reported';
    cards.push(`<div class="summary-card"><strong>Individual ancient sample matching (legacy)</strong><p>${esc(packStatus)}<br>${esc(pack.truth_boundary || report.disclaimer || 'Ancient affinity only, not descent proof.')}</p></div>`);
    if(report.cohen_priestly && Number(report.cohen_priestly.classic_markers_present || 0) > 0){
      const c = report.cohen_priestly;
      const rows = Array.isArray(c.classic_marker_table) ? c.classic_marker_table.slice(0, 6).map(r => {
        const observed = Array.isArray(r.observed) && r.observed.length ? r.observed.join('/') : 'missing';
        return `${esc(r.marker || 'DYS?')}: expected ${esc(r.expected || '?')}, observed ${esc(observed)} · ${esc(r.status || '')}`;
      }).join('<br>') : '';
      cards.push(`<div class="summary-card"><strong>Cohen / Kohen Y-DNA clue</strong><p><b>${esc(c.status || 'not testable')}</b><br>${esc(c.plain || '')}<br><br>Classic CMH markers: ${Number(c.classic_markers_present || 0).toLocaleString()}/${Number(c.classic_markers_total || 6).toLocaleString()} · exact ${Number(c.classic_exact_matches || 0).toLocaleString()} · ${esc(c.confidence_band || 'not-testable')} (${Number(c.confidence_score || 0).toLocaleString()}/100)<br>${rows}<br><br>${esc(c.truth_boundary || 'Paternal-line research clue only.')}</p></div>`);
    }
    if(Array.isArray(report.ancient_spotlight) && report.ancient_spotlight.length){
      const lanes = report.ancient_spotlight.slice(0, 6).map(l => `${esc(l.label || 'Ancient lead')}: ${esc(l.status || 'context')} · ${esc(fmtPct(l.strength || 0))}%`).join('<br>');
      cards.push(`<div class="summary-card"><strong>Ancient spotlight</strong><p>${lanes}</p></div>`);
    }
    if(Array.isArray(report.sample_matches) && report.sample_matches.length){
      const leads = report.sample_matches.slice(0, 10).map(m => {
        const place = [m.site_name, m.modern_country].filter(Boolean).join(', ');
        return `${esc(m.public_label || m.sample_id || 'Ancient sample')}: <b>${esc(place || m.culture || 'ancient sample')}</b><br>${esc(m.culture || 'unknown culture')} · ${esc(m.date_label || 'unknown date')} · ${esc(m.confidence_band || 'low')} (${esc(m.confidence_score || 0)}/100) · ${Number(m.overlap_marker_count || 0).toLocaleString()} markers`;
      }).join('<br><br>');
      cards.push(`<div class="summary-card"><strong>Closest ancient sample leads</strong><p>${leads}<br><br>These are similarity leads only, not proof of descent from a grave or named person.</p></div>`);
    }
    if(Array.isArray(report.site_scores) && report.site_scores.length){
      const sites = report.site_scores.slice(0, 8).map(s => `${esc([s.site_name, s.modern_country].filter(Boolean).join(', ') || 'Ancient site')}: <b>${esc(s.culture || 'unknown culture')}</b> · ${esc(s.confidence_band || 'low')} · ${Number(s.sample_count || 0).toLocaleString()} samples`).join('<br>');
      cards.push(`<div class="summary-card"><strong>Grave site / culture clusters</strong><p>${sites}</p></div>`);
    }
    if(Array.isArray(report.context_lanes) && report.context_lanes.length){
      const lanes = report.context_lanes.slice(0, 8).map(l => {
        const pct = Number(l.proxy_percent || 0) > 0 ? ` · ${esc(fmtPct(l.proxy_percent))}% context proxy` : '';
        return `${esc(l.label || 'Ancient context')}: <b>${esc(l.status || 'context')}</b>${pct}<br>${esc(l.modern_countries || '')}<br>${esc(l.plain || '')}`;
      }).join('<br><br>');
      cards.push(`<div class="summary-card"><strong>Ancient context lanes</strong><p>${lanes}</p></div>`);
    }
    if(Array.isArray(report.evidence_cards)){
      cards.push(`<div class="summary-card"><strong>Ancient evidence ledger</strong><p>${report.evidence_cards.slice(0, 8).map(c => `${esc(c.title || 'Evidence')}: <b>${esc(c.claim_label || '')}</b> · ${esc(c.confidence_band || '')}`).join('<br>')}</p></div>`);
    }
  }
  if(kind === 'heritage' && Array.isArray(insights.confidence_bands) && insights.confidence_bands.length){
    const bands = insights.confidence_bands.slice(0, 5).map(b => `${esc(b.label || 'Signal')}: ${esc(b.confidence_band || 'uncertain')}`).join('<br>');
    cards.push(`<div class="summary-card"><strong>Confidence-bounded heritage lenses</strong><p>${bands}<br>This is not an ethnicity percentage.</p></div>`);
  }
  if(kind === 'heritage' && insights.plain_ancestry_summary){
    const p = insights.plain_ancestry_summary;
    const snap = p.consumer_snapshot || {};
    if(snap.headline || snap.plain){
      cards.push(`<div class="summary-card"><strong>Plain result first</strong><p><b>${esc(snap.headline || 'Country-style reference leads ready.')}</b><br>${esc(snap.plain || '')}<br><br><b>Boundary:</b> ${esc(snap.theory_boundary || p.truth_boundary || 'Reference signal only, not identity proof.')}</p></div>`);
    }
    if(p.consumer_estimate?.available !== false && p.consumer_estimate?.segments?.length){
      const ce = p.consumer_estimate;
      const comp = ce.segments.slice(0, 10).map(s => `${esc(s.label || 'Projection')}: <b>${esc(s.percent ?? s.value ?? 0)}%</b>${s.note ? `<br><span>${esc(s.note)}</span>` : ''}`).join('<br><br>');
      const traces = Array.isArray(ce.trace_lanes) && ce.trace_lanes.length
        ? '<br><br><b>Trace lanes:</b><br>' + ce.trace_lanes.slice(0, 4).map(t => `${esc(t.label || 'Trace')}: ${esc(t.answer || 'research lead')}${Number(t.proxy_percent || 0) > 0 ? ` · ${esc(t.proxy_percent)}%` : ''}`).join('<br>')
        : '';
      cards.push(`<div class="summary-card"><strong>${esc(ce.headline || 'Consumer-style projection')}</strong><p>${comp}${traces}<br><br><b>Engine:</b> ${esc(ce.engine || 'ZDNA-CONSUMER')}<br><b>Boundary:</b> ${esc(ce.boundary || 'Probabilistic projection only.')}</p></div>`);
      const jewishTrace = Array.isArray(ce.trace_lanes) ? ce.trace_lanes.find(t => String(t.label || '').toLowerCase().includes('ashkenazi') || String(t.label || '').toLowerCase().includes('jewish')) : null;
      if(jewishTrace){
        const pct = Number(jewishTrace.proxy_percent || 0) > 0 ? ` · ${esc(jewishTrace.proxy_percent)}% trace proxy` : '';
        cards.push(`<div class="summary-card"><strong>Jewish diaspora trace lane</strong><p><b>${esc(jewishTrace.answer || 'research clue')}</b>${pct}<br>${esc(jewishTrace.plain || '')}<br><br><b>Important:</b> ${esc(jewishTrace.boundary || 'Genetic affinity is not religion, identity, community membership, or belonging.')}</p></div>`);
      }
    }
    if(Array.isArray(p.plain_country_cards) && p.plain_country_cards.length){
      const country = p.plain_country_cards.slice(0, 10).map(c => {
        const pct = Number(c.proxy_percent || 0) > 0 ? ` · ${esc(c.proxy_percent)}% starter proxy` : '';
        return `${esc(c.label || 'Country/region lane')}: <b>${esc(c.answer || 'unknown')}</b>${pct}<br><span>${esc(c.plain || '')}</span>`;
      }).join('<br><br>');
      cards.push(`<div class="summary-card"><strong>Country-style named leads</strong><p>${country}</p></div>`);
    }
    if(Array.isArray(p.theoretical_lanes) && p.theoretical_lanes.length){
      const lanes = p.theoretical_lanes.slice(0, 8).map(c => {
        const pct = Number(c.proxy_percent || 0) > 0 ? ` · ${esc(c.proxy_percent)}% proxy` : '';
        return `${esc(c.label || 'Theoretical lane')}: <b>${esc(c.answer || 'research lead')}</b>${pct}<br>${esc(c.plain || '')}`;
      }).join('<br><br>');
      cards.push(`<div class="summary-card"><strong>Special heritage questions</strong><p>${lanes}<br><br>Use these as research directions, not final identity claims.</p></div>`);
    }
    if(Array.isArray(p.broad_reference_mix) && p.broad_reference_mix.length){
    const mix = Array.isArray(p.broad_reference_mix) && p.broad_reference_mix.length
      ? p.broad_reference_mix.slice(0, 5).map(r => `${esc(r.proxy_percent || 0)}% ${esc(r.label || 'reference signal')}`).join('<br>')
      : '';
    cards.push(`<div class="summary-card"><strong>Detailed reference mix</strong><p>${esc(p.headline || 'Simple ancestry answer')}<br>${esc(p.plain_summary || '')}<br><br>${mix}<br><b>Boundary:</b> ${esc(p.truth_boundary || 'Reference signal only, not identity.')}</p></div>`);
    }
    if(Array.isArray(p.cards) && p.cards.length && !Array.isArray(p.reference_affinities)){
      const q = p.cards.slice(0, 8).map(c => `${esc(c.label || 'Question')}: <b>${esc(c.answer || 'unknown')}</b>${Number(c.proxy_percent || 0) > 0 ? ` · ${esc(c.proxy_percent)}% proxy` : ''}`).join('<br>');
      cards.push(`<div class="summary-card"><strong>Yes / no / possible answers</strong><p>${q}</p></div>`);
    }
  }
  if(kind === 'heritage' && Array.isArray(insights.question_signals) && insights.question_signals.length){
    const signals = insights.question_signals.slice(0, 6).map(s => `${esc(s.label || 'Question')}: ${esc(s.status || 'uncertain')} · ${esc(s.confidence_band || 'low')}`).join('<br>');
    cards.push(`<div class="summary-card"><strong>Ancestry Question Lab</strong><p>${signals}<br>ZeroThink separates strong country/region clues from weaker research leads.</p></div>`);
  }
  if(kind === 'heritage' && report.local_reference_pack){
    const pack = report.local_reference_pack || {};
    const status = pack.installed ? (pack.interpretation_ready === false ? 'Reference pack installed; comparison withheld until file validation.' : 'Reference panel scanned. Review overlap and validation limits below.') : 'No active reference panel reported for this result.';
    const pops = Array.isArray(pack.population_scores) && pack.population_scores.length
      ? pack.population_scores.slice(0, 6).map(p => `${esc(p.modern_label || p.population_code || 'Reference group')}: ${esc(p.confidence_band || 'uncertain')} · ${esc(p.matched_marker_count || 0)} markers`).join('<br>')
      : 'No population score rows returned. A preview is not a calibrated ancestry estimate.';
    cards.push(`<div class="summary-card"><strong>Country and region signal scan</strong><p>${esc(status)}<br>${pops}<br>These are DNA similarity signals, not identity labels.</p></div>`);
  }
  if(kind === 'heritage' && insights.modern_region_glossary && typeof insights.modern_region_glossary === 'object'){
    const glossary = Object.entries(insights.modern_region_glossary).slice(0, 5).map(([k,v]) => `${esc(k)}: ${esc(v)}`).join('<br>');
    if(glossary) cards.push(`<div class="summary-card"><strong>Plain-language region names</strong><p>${glossary}</p></div>`);
  }
  if(kind === 'heritage' && Array.isArray(insights.region_research_prompts) && insights.region_research_prompts.length){
    cards.push(`<div class="summary-card"><strong>Regions to explore next</strong><p>${insights.region_research_prompts.map(esc).join('<br>')}</p></div>`);
  }
  if(kind === 'full' && report.heritage?.insights?.consumer_estimate?.available !== false && report.heritage?.insights?.consumer_estimate?.segments?.length){
    const ce = report.heritage.insights.consumer_estimate;
    const comp = Array.isArray(ce.segments) ? ce.segments.slice(0, 10).map(s => `${esc(s.label || 'Projection')}: <b>${esc(s.percent ?? s.value ?? 0)}%</b>`).join('<br>') : '';
    const suppressed = Array.isArray(ce.suppressed_noise) && ce.suppressed_noise.length ? '<br><br>' + ce.suppressed_noise.map(n => `<b>Suppressed:</b> ${esc(n)}`).join('<br>') : '';
    cards.push(`<div class="summary-card"><strong>${esc(ce.headline || 'Plain heritage projection')}</strong><p>${comp}${suppressed}<br><br><b>Engine:</b> ${esc(ce.engine || 'ZDNA-CONSUMER')}<br><b>Boundary:</b> ${esc(ce.boundary || 'Probabilistic projection only.')}</p></div>`);
  }
  if(kind === 'full' && Number(report.ancient_graves?.cohen_priestly?.classic_markers_present || 0) > 0){
    const c = report.ancient_graves.cohen_priestly;
    cards.push(`<div class="summary-card"><strong>Cohen / Kohen Y-DNA clue</strong><p><b>${esc(c.status || 'not testable')}</b><br>${esc(c.plain || '')}<br>Classic CMH markers: ${Number(c.classic_markers_present || 0).toLocaleString()}/${Number(c.classic_markers_total || 6).toLocaleString()} · exact ${Number(c.classic_exact_matches || 0).toLocaleString()} · ${esc(c.confidence_band || 'not-testable')} (${Number(c.confidence_score || 0).toLocaleString()}/100)<br>${esc(c.truth_boundary || 'Paternal-line research clue only.')}</p></div>`);
  }
  if(kind === 'full' && Array.isArray(report.evidence_cards)){
    cards.push(`<div class="summary-card"><strong>Evidence Ledger</strong><p>${report.evidence_cards.slice(0, 6).map(c => `${esc(c.title || 'Evidence')}: ${esc(c.claim_label || '')} · ${esc(c.confidence_band || '')}`).join('<br>')}</p></div>`);
  }
  if(kind === 'lineage' && report.plain_lineage){
    const p = report.plain_lineage;
    const comp = Array.isArray(p.segments) && p.segments.length
      ? p.segments.slice(0, 10).map(s => `${esc(s.label || 'Lineage projection')}: <b>${esc(s.percent ?? s.value ?? 0)}%</b>${s.note ? `<br><span>${esc(s.note)}</span>` : ''}`).join('<br><br>')
      : 'Country/heritage projection unavailable for this upload.';
    cards.push(`<div class="summary-card"><strong>${esc(p.headline || 'Plain lineage result')}</strong><p>${esc(p.plain_summary || '')}<br><br>${comp}<br><br><b>Boundary:</b> ${esc(p.truth_boundary || 'Research context only.')}</p></div>`);
    if(Array.isArray(p.ancient_lanes) && p.ancient_lanes.length){
      const lanes = p.ancient_lanes.slice(0, 10).map(l => {
        const pct = Number(l.proxy_percent || 0) > 0 ? ` · ${esc(l.proxy_percent)}% proxy` : '';
        const places = l.modern_countries ? `<br><span>Modern map names: ${esc(l.modern_countries)}</span>` : '';
        return `${esc(l.label || 'Ancient lane')}: <b>${esc(l.status || 'research lead')}</b>${pct}<br>${esc(l.plain || '')}${places}`;
      }).join('<br><br>');
      cards.push(`<div class="summary-card"><strong>Ancient / regional lineage lanes</strong><p>${lanes}<br><br>These are useful leads, not proof of royalty, religion, tribe, or a named ancestor.</p></div>`);
    }
    if(Array.isArray(p.direct_line_cards) && p.direct_line_cards.length){
      const direct = p.direct_line_cards.map(c => `${esc(c.label || 'Direct line')}: <b>${esc(c.status || 'context')}</b><br>${esc(c.plain || '')}`).join('<br><br>');
      cards.push(`<div class="summary-card"><strong>Paternal / maternal clue lanes</strong><p>${direct}</p></div>`);
    }
  }
  if(kind === 'lineage' && Array.isArray(report.evidence_cards)){
    cards.push(`<div class="summary-card"><strong>${esc(report.summary?.conclusion_band || 'Lineage clues')}</strong><p>${report.evidence_cards.slice(0, 8).map(c => `${esc(c.title || 'Evidence')}: <b>${esc(c.claim_label || '')}</b><br>${esc(c.explanation || c.limitations || '')}`).join('<br><br>')}</p></div>`);
  }
  if(kind === 'lineage' && report.public_graph){
    const g = report.public_graph;
    cards.push(`<div class="summary-card"><strong>Lineage search graph</strong><p>${g.installed ? 'Ready' : 'Needs expansion'}<br>${Number(g.people_count || 0).toLocaleString()} public people · ${Number(g.edge_count || 0).toLocaleString()} family links<br>${esc(g.next_step || '')}</p></div>`);
  }
  if(kind === 'lineage' && Array.isArray(report.route_lenses) && report.route_lenses.length){
    const lenses = report.route_lenses.map(l => `${esc(l.title || 'Route')}: <b>${esc(l.status || 'context')}</b><br>${esc(l.plain || '')}`).join('<br><br>');
    cards.push(`<div class="summary-card"><strong>Lineage route lenses</strong><p>${lenses}</p></div>`);
  }
  if(kind === 'lineage' && Array.isArray(report.public_graph_candidates) && report.public_graph_candidates.length){
    const leads = report.public_graph_candidates.map(l => `${esc(l.name || 'Public person')} via "${esc(l.anchor || '')}" · ${esc(l.confidence_band || 'low')}<br>${esc(l.title_or_role || '')}${l.url ? `<br>${esc(l.url)}` : ''}`).join('<br><br>');
    cards.push(`<div class="summary-card"><strong>Public graph leads</strong><p>${leads}<br>These are name/graph leads only, not proof of descent.</p></div>`);
  }
  if(kind === 'lineage' && report.lineage_context){
    const ctx = report.lineage_context;
    const m = ctx.marker_summary || {};
    cards.push(`<div class="summary-card"><strong>DNA lineage starter</strong><p>Readiness ${esc(ctx.readiness_score || 0)}/100 · ${esc(ctx.readiness_band || 'low')}<br>Autosomal ${Number(m.autosomal_markers || 0).toLocaleString()} · Y ${Number(m.y_markers || 0).toLocaleString()} · mtDNA ${Number(m.mt_markers || 0).toLocaleString()}<br>${esc(ctx.truth_boundary || '')}</p></div>`);
  }
  if(kind === 'lineage' && Array.isArray(report.research_tasks) && report.research_tasks.length){
    cards.push(`<div class="summary-card"><strong>Next evidence to add</strong><p>${report.research_tasks.slice(0, 6).map(esc).join('<br>')}</p></div>`);
  }
  if(kind === 'lineage' && Array.isArray(report.relative_dna_context) && report.relative_dna_context.length){
    const rel = report.relative_dna_context.map(r => `${esc(r.relative_file || 'relative DNA')}: ${esc(r.claim_label || 'comparison')}<br>Overlap ${Number(r.overlap_markers || 0).toLocaleString()} · exact ${esc(r.exact_match_percent || 0)}% · shared allele ${esc(r.allele_share_percent || 0)}%`).join('<br><br>');
    cards.push(`<div class="summary-card"><strong>Relative DNA overlap</strong><p>${rel}<br>Private overlap only, not legal or medical relationship proof.</p></div>`);
  }
  if(kind === 'theory' && Array.isArray(report.theory_cards)){
    const theory = report.theory_cards.slice(0, 12).map(c => `${esc(c.title || 'Hypothesis')}: <b>${esc(c.answer || 'unknown')}</b> · ${esc(c.confidence_band || 'low')}<br>${esc(c.plain || '')}`).join('<br><br>');
    cards.push(`<div class="summary-card"><strong>Theoretical hypothesis ledger</strong><p>${theory}<br><br><b>Boundary:</b> ${esc(report.disclaimer || 'Theoretical only.')}</p></div>`);
  }
  if((kind === 'theory' || kind === 'full') && (report.mother_side || report.father_side)){
    const sideBlock = ['mother_side','father_side'].filter(k => report[k]).map(k => {
      const s = report[k];
      const mix = Array.isArray(s.top_mix) ? s.top_mix.slice(0,3).map(m => `${esc(m.proxy_percent || 0)}% ${esc(m.label || 'reference')}`).join('<br>') : 'No mix available.';
      const rel = s.relatedness ? `${esc(s.relatedness.claim_label || 'comparison')} · shared allele ${esc(s.relatedness.allele_share_percent || 0)}%` : 'No relation screen.';
      const proj = s.other_parent_projection && s.other_parent_projection.available
        ? `<br><br><b>Estimated remaining side:</b> ${esc(s.other_parent_projection.headline || 'parent-side projection')} · ${esc(s.other_parent_projection.confidence_band || 'medium')}<br>${esc(s.other_parent_projection.plain_summary || '')}`
        : '';
      return `<b>${esc(k.replace('_side',''))}</b>: ${esc(rel)}<br>${mix}${proj}`;
    }).join('<br><br>');
    cards.push(`<div class="summary-card"><strong>Mother / father side clues</strong><p>${sideBlock}<br>Side assignment needs parent files or phased segments.</p></div>`);
  }
  const insightCards = Array.isArray(insights.cards) ? insights.cards.slice(0, 9) : [];
  insightCards.forEach(card => {
    const tag = card.priority === 'attention' ? 'Needs care' : (card.lane || card.category || 'Context');
    cards.push(`<div class="summary-card"><strong>${esc(card.title || 'Research card')}</strong><p><b>${esc(tag)}</b><br>${esc(card.plain || '')}</p></div>`);
  });
  document.getElementById('plain-cards').innerHTML = cards.join('') || '<div class="summary-card"><strong>Report ready</strong><p>Open the evidence report below for full details.</p></div>';
  const prompts = insights.agent_prompts || ['Explain this report in plain English.', 'What should I verify next?', 'What should I not overclaim?'];
  const promptHost = document.getElementById('agent-prompts');
  promptHost.innerHTML = prompts.map((p, index) => `<button class="prompt-pill" type="button" data-prompt-index="${index}">${esc(p)}</button>`).join('');
  promptHost.querySelectorAll('[data-prompt-index]').forEach(button => {
    button.addEventListener('click', () => {
      const index = Number(button.dataset.promptIndex || 0);
      setAgentQuestion(String(prompts[index] ?? ''));
    });
  });
}

function setAgentQuestion(q){document.getElementById('agent-question').value=q;}
function fmtPct(v, decimals = 1){
  const n = Number(v ?? 0);
  if(!Number.isFinite(n)) return '0';
  return n.toLocaleString(undefined, {maximumFractionDigits: decimals});
}

function drawPie(segments){
  const canvas = document.getElementById('dna-chart');
  const ctx = canvas.getContext('2d');
  const w = canvas.width, h = canvas.height, cx = w/2, cy = h/2, r = Math.min(w,h)*0.38;
  ctx.clearRect(0,0,w,h);
  const clean = Array.isArray(segments) ? segments.filter(seg => Number.isFinite(Number(seg.value)) && Number(seg.value) > 0) : [];
  if(!clean.length){
    ctx.strokeStyle = 'rgba(105,247,255,.18)';
    ctx.lineWidth = 16;
    ctx.beginPath(); ctx.arc(cx,cy,r,0,Math.PI*2); ctx.stroke();
    ctx.textAlign = 'center'; ctx.fillStyle = '#a9c4bd'; ctx.font = '700 15px Segoe UI, sans-serif';
    ctx.fillText('No chart available',cx,cy);
    document.getElementById('chart-legend').innerHTML = '';
    return;
  }
  const total = clean.reduce((a,s)=>a+Math.max(0,Number(s.value || 0)),0) || 1;
  let start = -Math.PI/2;
  clean.forEach(seg => {
    const value = Math.max(0, Number(seg.value || 0));
    const end = start + (value / total) * Math.PI * 2;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, r, start, end);
    ctx.closePath();
    ctx.fillStyle = seg.color || '#69f7ff';
    ctx.shadowColor = seg.color || '#69f7ff';
    ctx.shadowBlur = 18;
    ctx.fill();
    start = end;
  });
  ctx.shadowBlur = 0;
  ctx.beginPath();
  ctx.arc(cx, cy, r*0.52, 0, Math.PI*2);
  ctx.fillStyle = '#03100d';
  ctx.fill();
  ctx.fillStyle = '#eaffff';
  ctx.font = '900 20px Segoe UI, sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText('DNA', cx, cy - 2);
  ctx.fillStyle = '#9fffe4';
  ctx.font = '700 12px Segoe UI, sans-serif';
  ctx.fillText('REPORT', cx, cy + 18);
  document.getElementById('chart-legend').innerHTML = clean.map(seg => {
    const pct = seg.percent ?? Math.round((Number(seg.value || 0) / total) * 1000) / 10;
    return `<div class="legend-row"><span class="dot" style="background:${esc(seg.color || '#69f7ff')};color:${esc(seg.color || '#69f7ff')}"></span><span>${esc(seg.label)}</span><b>${esc(fmtPct(pct))}%</b></div>`;
  }).join('');
}

let currentDnaMapPoints = [];
let dnaWorldMapSvgCache = '';
let dnaWorldMapPromise = null;
let currentDnaMapIndex = 0;
let dnaMapZoom = 1;
let dnaMapPanX = 0;
let dnaMapPanY = 0;
let dnaMapDragging = null;
function estimateFromReport(report, kind){
  const insights = report?.insights || {};
  if(kind === 'full'){
    if(report?.parent_aware_estimate?.available) return report.parent_aware_estimate;
    return report?.heritage?.insights?.consumer_estimate || report?.heritage?.insights?.plain_ancestry_summary?.consumer_estimate || null;
  }
  if(kind === 'heritage'){
    return insights.consumer_estimate || insights.plain_ancestry_summary?.consumer_estimate || null;
  }
  if(kind === 'lineage'){
    return report?.plain_lineage?.consumer_estimate || report?.plain_lineage || null;
  }
  if(kind === 'theory'){
    if(report?.parent_aware_estimate?.available) return report.parent_aware_estimate;
    return report?.heritage?.insights?.consumer_estimate || null;
  }
  return null;
}
function ancientPointFromMatch(match, index){
  const lat = Number(match?.latitude);
  const lon = Number(match?.longitude);
  if(!Number.isFinite(lat) || !Number.isFinite(lon)) return null;
  const label = String(match?.public_label || match?.sample_id || 'Ancient sample');
  const place = [match?.site_name, match?.modern_country].filter(Boolean).join(', ');
  return {
    label,
    country_name: place || label,
    modern_region: [match?.culture, match?.date_label].filter(Boolean).join(' · '),
    percent: Math.max(1, Number(match?.confidence_score || match?.similarity_score || 1)),
    color: ['#d8b4fe','#8df7d2','#ffd76f','#69f7ff','#ff9fcf','#b6ff7a'][index % 6],
    type: index > 5 ? 'trace' : 'main',
    x: ((lon + 180) / 360) * 100,
    y: ((90 - lat) / 180) * 100,
    info: `Similarity ${match?.similarity_score || 0}/100 · overlap ${Number(match?.overlap_marker_count || 0).toLocaleString()} markers. Ancient affinity only, not descent proof.`
  };
}
function ancientPointsFromReport(report){
  const direct = Array.isArray(report?.sample_matches) ? report.sample_matches.map(ancientPointFromMatch).filter(Boolean) : [];
  const lanes = Array.isArray(report?.context_lanes) ? report.context_lanes : [];
  const context = lanes
    .filter(lane => Number(lane?.proxy_percent || 0) > 0)
    .slice(0, 10)
    .map(lane => pointFromSegment({
      label: lane.label,
      percent: lane.proxy_percent,
      value: lane.proxy_percent,
      color: '#8df7d2',
      note: `${lane.modern_countries || 'Ancient context lane'} · ${lane.boundary || 'Context only.'}`
    }));
  const graveLeads = direct.slice(0, 8).map(point => ({
    ...point,
    type: 'trace',
    label: 'Grave lead: ' + point.label,
    percent: Math.round(Math.min(8, Math.max(1, Number(point.percent || 1) / 12)) * 10) / 10,
    info: point.info + ' Nearest-sample clue only; the main ancient wheel uses broader region context.'
  }));
  if(context.length) return context.concat(graveLeads).slice(0, 18);
  if(direct.length) return direct.slice(0, 14);
  return [];
}
function fallbackMapMeta(label){
  const l = String(label || '').toLowerCase();
  const defs = [
    ['pakistani', 'Pakistan / Punjab', 'Lahore, Punjab, Pakistan plus neighbouring Punjab/Afghanistan/India context', 31.6, 73.0],
    ['irish', 'Ireland', 'Ireland / Celtic Atlantic fringe', 53.3, -8.2],
    ['ireland', 'Ireland', 'Ireland / Celtic Atlantic fringe', 53.3, -8.2],
    ['scottish', 'Scotland and Wales', 'British Isles: Scotland, Wales, and nearby Celtic Britain', 55.5, -4.4],
    ['english', 'England', 'England / United Kingdom', 52.4, -1.6],
    ['early britain', 'England / Early Britain', 'England, Wales, Scotland, and early Britain context', 52.4, -1.6],
    ['breton', 'Brittany / Breton Celtic fringe', 'Brittany, northwest France, Celtic Atlantic fringe', 48.2, -3.5],
    ['khazar', 'Khazar / Steppe-Caucasus', 'Steppe-Caucasus historical hypothesis lane', 45.2, 41.7],
    ['steppe', 'Steppe / Central Asia', 'Pontic-Caspian steppe, Central Asia, and Turkic-Caucasus bridge context', 46.5, 63.5],
    ['central asian', 'Central Asia', 'Kazakhstan, Uzbekistan, Kyrgyzstan, Tajikistan, Turkmenistan, Afghanistan-adjacent context', 44.5, 67.0],
    ['israel', 'Israel / Levant', 'Israel, Palestine, Lebanon, Jordan, Syria, Cyprus, and Eastern Mediterranean context', 31.8, 35.0],
    ['levant', 'Israel / Levant', 'Israel, Palestine, Lebanon, Jordan, Syria, Cyprus, and Eastern Mediterranean context', 31.8, 35.0],
    ['egypt', 'Egypt / Nile Valley', 'Egypt, Nile Valley, Nubia, and North Africa context', 26.8, 30.8],
    ['north africa', 'Egypt / North Africa', 'Egypt, Maghreb, Nile Valley, Nubia, and North Africa context', 28.0, 14.0],
    ['iran', 'Iran / Persia', 'Iran/Persia, Caucasus, Central Asia, Afghanistan, and West-Central Asia context', 32.4, 53.7],
    ['persia', 'Iran / Persia', 'Iran/Persia, Caucasus, Central Asia, Afghanistan, and West-Central Asia context', 32.4, 53.7],
    ['mediterranean', 'Mediterranean', 'Italy, Greece, Spain, Cyprus, Malta, and coastal Mediterranean Europe', 39.0, 14.0],
    ['general south asian', 'South Asia', 'India, Pakistan, Bangladesh, Sri Lanka, Nepal, and nearby contexts', 22.5, 78.8],
    ['finnish', 'Finland / Nordic trace', 'Finland and nearby Nordic/Baltic reference context', 63.2, 25.7],
    ['ashkenazi', 'Ashkenazi Jewish diaspora trace', 'Central/Eastern European Jewish diaspora context', 50.1, 19.5]
  ];
  const hit = defs.find(d => l.includes(d[0])) || [label, label, 'Reference-panel region', 20, 0];
  return {country_name: hit[1], modern_region: hit[2], lat: hit[3], lon: hit[4]};
}
function pointFromSegment(segment){
  const label = String(segment?.label || 'Projection lane');
  const meta = fallbackMapMeta(label);
  const lon = Number(meta.lon || 0);
  const lat = Number(meta.lat || 0);
  return {
    label,
    country_name: meta.country_name,
    modern_region: meta.modern_region,
    percent: Number(segment?.percent ?? segment?.value ?? 0),
    color: segment?.color || '#69f7ff',
    type: 'main',
    x: ((lon + 180) / 360) * 100,
    y: ((90 - lat) / 180) * 100,
    info: segment?.note || 'Projection lane generated from this DNA report.'
  };
}
function projectDnaLonLat(lon, lat){
  return [
    ((Number(lon || 0) + 180) / 360) * 720,
    ((90 - Number(lat || 0)) / 180) * 360
  ];
}
function decodeTopoArcs(topology){
  const scale = topology?.transform?.scale || [1,1];
  const translate = topology?.transform?.translate || [0,0];
  return (topology?.arcs || []).map(arc => {
    let x = 0, y = 0;
    return arc.map(pair => {
      x += Number(pair[0] || 0);
      y += Number(pair[1] || 0);
      return [x * scale[0] + translate[0], y * scale[1] + translate[1]];
    });
  });
}
function topoRingPath(ring, decodedArcs){
  const pts = [];
  (ring || []).forEach((arcIndex, index) => {
    const raw = decodedArcs[arcIndex < 0 ? ~arcIndex : arcIndex] || [];
    const arc = arcIndex < 0 ? raw.slice().reverse() : raw;
    const trimmed = index > 0 ? arc.slice(1) : arc;
    trimmed.forEach(pt => pts.push(pt));
  });
  if(!pts.length) return '';
  return pts.map((pt, index) => {
    const [x,y] = projectDnaLonLat(pt[0], pt[1]);
    return `${index === 0 ? 'M' : 'L'}${Math.round(x*10)/10},${Math.round(y*10)/10}`;
  }).join('') + 'Z';
}
function topoGeometryPath(geometry, decodedArcs){
  if(!geometry || !geometry.arcs) return '';
  if(geometry.type === 'Polygon'){
    return geometry.arcs.map(ring => topoRingPath(ring, decodedArcs)).join('');
  }
  if(geometry.type === 'MultiPolygon'){
    return geometry.arcs.flatMap(poly => poly.map(ring => topoRingPath(ring, decodedArcs))).join('');
  }
  return '';
}
function buildDnaWorldMapSvg(topology){
  const decoded = decodeTopoArcs(topology);
  const countries = topology?.objects?.countries?.geometries || [];
  return countries.map((geometry, index) => {
    const d = topoGeometryPath(geometry, decoded);
    if(!d) return '';
    const name = geometry?.properties?.name || `Country ${index + 1}`;
    return `<path class="map-country" d="${d}"><title>${esc(name)}</title></path>`;
  }).join('');
}
function loadDnaWorldMapSvg(){
  if(dnaWorldMapSvgCache) return Promise.resolve(dnaWorldMapSvgCache);
  if(!dnaWorldMapPromise){
    dnaWorldMapPromise = fetch('/assets/maps/world-countries-110m.json', {cache:'force-cache'})
      .then(r => {
        if(!r.ok) throw new Error('world map asset unavailable');
        return r.json();
      })
      .then(topology => {
        dnaWorldMapSvgCache = buildDnaWorldMapSvg(topology);
        return dnaWorldMapSvgCache;
      })
      .catch(() => {
        dnaWorldMapSvgCache = `
          <path class="map-land" d="M82 84 C40 118 36 179 86 216 C124 244 166 229 188 194 C218 148 187 99 142 82 C120 74 99 74 82 84 Z"/>
          <path class="map-land" d="M165 219 C134 258 149 323 199 341 C248 357 279 315 262 267 C249 231 205 201 165 219 Z"/>
          <path class="map-land" d="M300 89 C276 100 262 126 266 154 C271 189 311 206 344 196 C379 185 398 147 378 116 C362 91 329 77 300 89 Z"/>
          <path class="map-land" d="M330 178 C302 211 302 265 341 296 C385 332 458 312 488 261 C517 211 482 162 429 154 C389 148 355 151 330 178 Z"/>
          <path class="map-land" d="M408 98 C461 62 558 68 632 108 C684 136 690 194 645 222 C596 253 526 221 475 208 C434 197 394 185 383 150 C377 129 389 111 408 98 Z"/>`;
        return dnaWorldMapSvgCache;
      });
  }
  return dnaWorldMapPromise;
}
function dnaMapGridSvg(){
  const grid = Array.from({length: 13}, (_, i) => `<line class="map-grid" x1="${i*60}" y1="0" x2="${i*60}" y2="360"></line>`).join('') +
    Array.from({length: 7}, (_, i) => `<line class="map-grid" x1="0" y1="${i*60}" x2="720" y2="${i*60}"></line>`).join('');
  return grid;
}
function buildDnaMapScene(points, prefix){
  const grid = dnaMapGridSvg();
  const idPrefix = prefix || 'map';
  if(!dnaWorldMapSvgCache){
    return `<rect width="720" height="360" fill="rgba(2,12,15,.62)"></rect>${grid}<text x="360" y="184" fill="#9fffe4" font-size="18" font-weight="900" text-anchor="middle">Loading heritage map...</text>`;
  }
  const halos = points.map((p) => {
    const x = Math.max(18, Math.min(702, Number(p.x ?? 50) / 100 * 720));
    const y = Math.max(18, Math.min(342, Number(p.y ?? 50) / 100 * 360));
    const pct = Math.max(1, Number(p.percent || 0));
    const rx = Math.max(15, Math.min(74, 16 + Math.sqrt(pct) * 8));
    const ry = Math.max(9, Math.min(42, 8 + Math.sqrt(pct) * 4.6));
    const color = esc(p.color || '#69f7ff');
    return `<ellipse class="map-region-halo" cx="${x}" cy="${y}" rx="${rx}" ry="${ry}" fill="${color}"></ellipse>`;
  }).join('');
  const pointSvg = points.map((p, i) => {
    const x = Math.max(18, Math.min(702, Number(p.x ?? 50) / 100 * 720));
    const y = Math.max(18, Math.min(342, Number(p.y ?? 50) / 100 * 360));
    const pct = Math.max(1, Number(p.percent || 0));
    const r = Math.max(5, Math.min(21, 5 + Math.sqrt(pct) * (p.type === 'trace' ? 0.85 : 1.45)));
    const color = esc(p.color || (p.type === 'trace' ? '#ffd76f' : '#69f7ff'));
    const label = pct >= 2.5 ? `<text x="${x + r + 4}" y="${y - r - 2}" fill="#eaffff" font-size="10" font-weight="800">${esc(p.country_name || p.label || '')}</text>` : '';
    return `<g class="map-point" id="${idPrefix}-point-${i}" style="color:${color}" onclick="selectDnaMapPoint(${i});event.stopPropagation();">
      <circle class="map-ripple" cx="${x}" cy="${y}" r="${r + 5}"></circle>
      <circle cx="${x}" cy="${y}" r="${r}" fill="${color}" fill-opacity="${p.type === 'trace' ? '.6' : '.82'}" stroke="#fff" stroke-opacity=".65"></circle>
      <circle cx="${x}" cy="${y}" r="${Math.max(2, r*.35)}" fill="#00120c" fill-opacity=".68"></circle>
      ${label}
    </g>`;
  }).join('');
  return `<rect width="720" height="360" fill="rgba(2,12,15,.62)"></rect>${grid}${dnaWorldMapSvgCache}${halos}${pointSvg}`;
}
function renderDnaWorldMap(report, kind){
  const card = document.getElementById('dna-map-card');
  const svg = document.getElementById('dna-world-map');
  const buttons = document.getElementById('dna-map-buttons');
  const count = document.getElementById('map-count');
  if(!card || !svg || !buttons) return;
  const estimate = estimateFromReport(report, kind);
  let points = kind === 'ancient' ? ancientPointsFromReport(report) : (Array.isArray(estimate?.map_points) ? estimate.map_points : []);
  if(!points.length && Array.isArray(estimate?.segments)) points = estimate.segments.map(pointFromSegment);
  points = points.filter(p => Number(p.percent || 0) > 0).slice(0, 14);
  if(!points.length || !['full','heritage','lineage','theory','ancient'].includes(kind)){
    card.classList.remove('active');
    currentDnaMapPoints = [];
    svg.innerHTML = '';
    buttons.innerHTML = '';
    closeDnaMapModal();
    return;
  }
  currentDnaMapPoints = points;
  currentDnaMapIndex = 0;
  card.classList.add('active');
  count.textContent = `${points.length} region${points.length === 1 ? '' : 's'}`;
  svg.innerHTML = buildDnaMapScene(points, 'map');
  if(!dnaWorldMapSvgCache){
    loadDnaWorldMapSvg().then(() => renderDnaWorldMap(report, kind));
    return;
  }
  buttons.innerHTML = points.map((p, i) => `<button class="map-chip" id="map-chip-${i}" type="button" onclick="selectDnaMapPoint(${i})">${esc(p.country_name || p.label)} · ${esc(fmtPct(p.percent))}%</button>`).join('');
  selectDnaMapPoint(0);
}
function setDnaMapInfo(info, point){
  if(!info || !point) return;
  const type = point.type === 'trace' ? 'Trace lane' : 'Main signal';
  info.innerHTML = `<small>${esc(type)} · ${esc(fmtPct(point.percent))}%</small><b>${esc(point.country_name || point.label || 'Region')}</b><p>${esc(point.modern_region || '')}</p><p>${esc(point.info || 'Generated from this DNA report projection.')}</p>`;
}
function selectDnaMapPoint(index){
  const point = currentDnaMapPoints[index];
  if(!point) return;
  currentDnaMapIndex = index;
  document.querySelectorAll('.map-point,.map-chip').forEach(el => el.classList.remove('active'));
  document.getElementById(`map-point-${index}`)?.classList.add('active');
  document.getElementById(`full-map-point-${index}`)?.classList.add('active');
  document.getElementById(`map-chip-${index}`)?.classList.add('active');
  document.getElementById(`full-map-chip-${index}`)?.classList.add('active');
  setDnaMapInfo(document.getElementById('dna-map-info'), point);
  setDnaMapInfo(document.getElementById('dna-map-full-info'), point);
}
function applyDnaMapTransform(){
  const layer = document.getElementById('dna-map-full-layer');
  if(layer) layer.setAttribute('transform', `translate(${dnaMapPanX} ${dnaMapPanY}) scale(${dnaMapZoom})`);
}
function renderDnaMapModal(){
  const svg = document.getElementById('dna-world-map-full');
  const buttons = document.getElementById('dna-map-full-buttons');
  if(!svg || !buttons || !currentDnaMapPoints.length) return;
  const scene = buildDnaMapScene(currentDnaMapPoints, 'full-map');
  svg.innerHTML = `<g id="dna-map-full-layer" transform="translate(${dnaMapPanX} ${dnaMapPanY}) scale(${dnaMapZoom})">${scene}</g>`;
  buttons.innerHTML = currentDnaMapPoints.map((p, i) => `<button class="map-chip" id="full-map-chip-${i}" type="button" onclick="selectDnaMapPoint(${i})">${esc(p.country_name || p.label)} · ${esc(fmtPct(p.percent))}%</button>`).join('');
  selectDnaMapPoint(currentDnaMapIndex || 0);
}
function openDnaMapModal(){
  if(!currentDnaMapPoints.length) return;
  const modal = document.getElementById('dna-map-modal');
  if(!modal) return;
  modal.classList.add('active');
  modal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('map-modal-open');
  if(!dnaWorldMapSvgCache){
    loadDnaWorldMapSvg().then(renderDnaMapModal);
  } else {
    renderDnaMapModal();
  }
}
function closeDnaMapModal(){
  const modal = document.getElementById('dna-map-modal');
  if(!modal) return;
  modal.classList.remove('active');
  modal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('map-modal-open');
  dnaMapDragging = null;
}
function zoomDnaMap(multiplier){
  dnaMapZoom = Math.max(1, Math.min(5.5, dnaMapZoom * Number(multiplier || 1)));
  applyDnaMapTransform();
}
function resetDnaMapView(){
  dnaMapZoom = 1;
  dnaMapPanX = 0;
  dnaMapPanY = 0;
  applyDnaMapTransform();
}
function initDnaMapModal(){
  const modal = document.getElementById('dna-map-modal');
  const svg = document.getElementById('dna-world-map-full');
  if(!modal || !svg) return;
  modal.addEventListener('click', e => {
    if(e.target === modal) closeDnaMapModal();
  });
  svg.addEventListener('wheel', e => {
    e.preventDefault();
    zoomDnaMap(e.deltaY < 0 ? 1.14 : 0.88);
  }, {passive:false});
  svg.addEventListener('pointerdown', e => {
    dnaMapDragging = {id:e.pointerId, x:e.clientX, y:e.clientY, panX:dnaMapPanX, panY:dnaMapPanY};
    svg.setPointerCapture?.(e.pointerId);
  });
  svg.addEventListener('pointermove', e => {
    if(!dnaMapDragging || dnaMapDragging.id !== e.pointerId) return;
    const dx = (e.clientX - dnaMapDragging.x) / dnaMapZoom;
    const dy = (e.clientY - dnaMapDragging.y) / dnaMapZoom;
    dnaMapPanX = Math.max(-520, Math.min(520, dnaMapDragging.panX + dx));
    dnaMapPanY = Math.max(-260, Math.min(260, dnaMapDragging.panY + dy));
    applyDnaMapTransform();
  });
  ['pointerup','pointercancel','pointerleave'].forEach(evt => svg.addEventListener(evt, () => { dnaMapDragging = null; }));
  document.addEventListener('keydown', e => {
    if(e.key === 'Escape') closeDnaMapModal();
  });
}

function renderDnaSound(profile){
  const panel = document.getElementById('dna-sound-panel');
  const note = document.getElementById('dna-sound-note');
  panel.style.display = 'block';
  window.DnaDiscovery?.soundReady(profile);
  if(!profile) return;
  const layerNote = profile.harmonic_layer?.enabled ? ' · harmonic ambience layer active' : '';
  note.textContent = `${profile.engine || 'ZDNA-SONIC'} · ${profile.mode || 'ambient'} · ${profile.bpm || '?'} BPM · ${profile.duration_seconds || '?'}s${layerNote}. Experimental ambience only, not therapy or diagnosis.`;
  drawDnaWave(profile);
}

function drawDnaWave(profile){
  const canvas = document.getElementById('dna-wave');
  const ctx = canvas.getContext('2d');
  const w = canvas.width, h = canvas.height;
  ctx.clearRect(0,0,w,h);
  const grad = ctx.createLinearGradient(0,0,w,0);
  grad.addColorStop(0,'rgba(0,255,145,.08)');
  grad.addColorStop(.5,'rgba(105,247,255,.12)');
  grad.addColorStop(1,'rgba(255,110,219,.08)');
  ctx.fillStyle = grad;
  ctx.fillRect(0,0,w,h);
  ctx.strokeStyle = 'rgba(105,247,255,.16)';
  ctx.lineWidth = 1;
  for(let x=0;x<w;x+=32){ctx.beginPath();ctx.moveTo(x,0);ctx.lineTo(x,h);ctx.stroke();}
  for(let y=0;y<h;y+=32){ctx.beginPath();ctx.moveTo(0,y);ctx.lineTo(w,y);ctx.stroke();}
  const events = Array.isArray(profile.events) ? profile.events : [];
  const duration = Math.max(1, Number(profile.duration_seconds || 60));
  ctx.beginPath();
  events.forEach((ev, i) => {
    const x = Math.max(0, Math.min(w, (Number(ev.time || 0) / duration) * w));
    const freq = Number(ev.frequency || 220);
    const y = h/2 + Math.sin(i * .73 + freq / 88) * (h * .35) * Number(ev.gain || .05) * 18;
    if(i === 0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
  });
  ctx.strokeStyle = '#69f7ff';
  ctx.lineWidth = 2;
  ctx.shadowColor = '#00ff91';
  ctx.shadowBlur = 18;
  ctx.stroke();
  ctx.shadowBlur = 0;
  const harmonic = profile.harmonic_layer || {};
  if(harmonic.enabled){
    const phase = Number(harmonic.phase || 0);
    ctx.beginPath();
    for(let x=0;x<w;x++){
      const t = (x / Math.max(1, w)) * duration;
      const y = h/2 + (
        Math.sin(Math.PI * 2 * 11 * t + phase) * .34 +
        Math.sin(Math.PI * 2 * 5 * t + phase * 1.7) * .33 +
        Math.sin(Math.PI * 2 * 7 * t + phase * 2.3) * .33
      ) * h * .11;
      if(x === 0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
    }
    ctx.strokeStyle = 'rgba(255,215,111,.62)';
    ctx.lineWidth = 1.35;
    ctx.shadowColor = 'rgba(255,215,111,.72)';
    ctx.shadowBlur = 12;
    ctx.stroke();
    ctx.shadowBlur = 0;
  }
  events.slice(0, 120).forEach(ev => {
    const x = Math.max(0, Math.min(w, (Number(ev.time || 0) / duration) * w));
    const f = Number(ev.frequency || 220);
    const y = h - ((Math.log2(f / 55) % 4) / 4) * h;
    ctx.fillStyle = Number(ev.pan || 0) >= 0 ? '#00ff91' : '#ff6edb';
    ctx.beginPath();
    ctx.arc(x, y, 2.4, 0, Math.PI*2);
    ctx.fill();
  });
}

function ensureAudioContext(){
  const Ctx = window.AudioContext || window.webkitAudioContext;
  if(!Ctx) throw new Error('This browser does not support Web Audio.');
  if(!dnaAudioCtx) dnaAudioCtx = new Ctx();
  return dnaAudioCtx;
}

async function buildDnaAudioBuffer(){
  if(lastDnaAudioBuffer) return lastDnaAudioBuffer;
  if(!lastDnaSoundProfile) throw new Error('Generate DNA Sound first.');
  const ctx = ensureAudioContext();
  if(ctx.state === 'suspended') await ctx.resume();
  const profile = lastDnaSoundProfile;
  const sr = 44100;
  const duration = Math.min(120, Math.max(12, Number(profile.duration_seconds || 60)));
  const frames = Math.floor(duration * sr);
  const buffer = ctx.createBuffer(2, frames, sr);
  const left = buffer.getChannelData(0);
  const right = buffer.getChannelData(1);
  const drone = profile.drone || {};
  const droneFreq = Number(drone.frequency || 55);
  const droneGain = Number(drone.gain || .012);
  const harmonic = profile.harmonic_layer || {};
  const harmonicGain = harmonic.enabled ? Math.max(0, Math.min(.024, Number(harmonic.gain || .006))) : 0;
  const harmonicPhase = Number(harmonic.phase || 0);
  const harmonicCarrier = Math.max(44, Math.min(240, Number(harmonic.carrier_frequency || droneFreq * 2)));
  for(let i=0;i<frames;i++){
    const t = i / sr;
    const d = Math.sin(Math.PI * 2 * droneFreq * t) * droneGain;
    const shimmer = Math.sin(Math.PI * 2 * droneFreq * 2.01 * t) * droneGain * .42;
    left[i] += d + shimmer;
    right[i] += d - shimmer * .7;
    if(harmonicGain > 0){
      const hA = Math.sin(Math.PI * 2 * 11 * t + harmonicPhase);
      const hB = Math.sin(Math.PI * 2 * 5 * t + harmonicPhase * 1.7);
      const hC = Math.sin(Math.PI * 2 * 7 * t + harmonicPhase * 2.3);
      const lfo = hA * .36 + hB * .31 + hC * .33;
      const bloom = .72 + .28 * Math.sin(Math.PI * Math.min(1, t / duration));
      const layer = Math.sin(Math.PI * 2 * harmonicCarrier * t + lfo * .22) * harmonicGain * bloom;
      left[i] += layer * (1 + hB * .08);
      right[i] += layer * (.92 + hC * .08);
    }
  }
  const events = Array.isArray(profile.events) ? profile.events : [];
  events.forEach(ev => {
    const start = Math.max(0, Math.floor(Number(ev.time || 0) * sr));
    const dur = Math.max(.06, Math.min(1.2, Number(ev.duration || .25)));
    const end = Math.min(frames, start + Math.floor(dur * sr));
    const freq = Math.max(30, Math.min(5000, Number(ev.frequency || 220)));
    const gain = Math.max(0, Math.min(.14, Number(ev.gain || .04)));
    const pan = Math.max(-1, Math.min(1, Number(ev.pan || 0)));
    const lGain = gain * Math.cos((pan + 1) * Math.PI / 4);
    const rGain = gain * Math.sin((pan + 1) * Math.PI / 4);
    for(let i=start;i<end;i++){
      const local = (i - start) / Math.max(1, end - start);
      const env = Math.sin(Math.PI * local);
      const t = i / sr;
      let wave = Math.sin(Math.PI * 2 * freq * t);
      if(ev.wave === 'triangle') wave = (2 / Math.PI) * Math.asin(wave);
      const overtone = Math.sin(Math.PI * 2 * freq * 1.5 * t) * .18;
      const harmonicLift = harmonicGain > 0
        ? 1 + (
          Math.sin(Math.PI * 2 * 11 * t + harmonicPhase) * .024 +
          Math.sin(Math.PI * 2 * 5 * t + harmonicPhase * 1.7) * .018 +
          Math.sin(Math.PI * 2 * 7 * t + harmonicPhase * 2.3) * .021
        )
        : 1;
      left[i] += (wave + overtone) * env * lGain * harmonicLift;
      right[i] += (wave - overtone) * env * rGain * harmonicLift;
    }
  });
  for(let i=0;i<frames;i++){
    left[i] = Math.max(-.95, Math.min(.95, left[i]));
    right[i] = Math.max(-.95, Math.min(.95, right[i]));
  }
  lastDnaAudioBuffer = buffer;
  return buffer;
}

async function playDnaSound(){
  if(window.DnaDiscovery) return window.DnaDiscovery.playSound();
  try {
    const ctx = ensureAudioContext();
    if(ctx.state === 'suspended') await ctx.resume();
    stopDnaSound();
    const buffer = await buildDnaAudioBuffer();
    const source = ctx.createBufferSource();
    const gain = ctx.createGain();
    gain.gain.value = .88;
    source.buffer = buffer;
    source.connect(gain).connect(ctx.destination);
    source.start();
    dnaAudioSource = source;
  } catch(e) {
    alert(e.message);
  }
}

function stopDnaSound(){
  window.DnaDiscovery?.stopSound();
  if(dnaAudioSource){
    try { dnaAudioSource.stop(); } catch(e) {}
    dnaAudioSource = null;
  }
}

function audioBufferToWav(buffer){
  const channels = buffer.numberOfChannels;
  const sampleRate = buffer.sampleRate;
  const length = buffer.length * channels * 2 + 44;
  const ab = new ArrayBuffer(length);
  const view = new DataView(ab);
  const write = (offset, str) => { for(let i=0;i<str.length;i++) view.setUint8(offset+i, str.charCodeAt(i)); };
  write(0,'RIFF'); view.setUint32(4, length - 8, true); write(8,'WAVE');
  write(12,'fmt '); view.setUint32(16,16,true); view.setUint16(20,1,true); view.setUint16(22,channels,true);
  view.setUint32(24,sampleRate,true); view.setUint32(28,sampleRate*channels*2,true); view.setUint16(32,channels*2,true); view.setUint16(34,16,true);
  write(36,'data'); view.setUint32(40, length - 44, true);
  let offset = 44;
  const data = [];
  for(let c=0;c<channels;c++) data.push(buffer.getChannelData(c));
  for(let i=0;i<buffer.length;i++){
    for(let c=0;c<channels;c++){
      const s = Math.max(-1, Math.min(1, data[c][i]));
      view.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7fff, true);
      offset += 2;
    }
  }
  return new Blob([ab], {type:'audio/wav'});
}

async function downloadDnaSound(){
  if(window.DnaDiscovery) return window.DnaDiscovery.downloadSound();
  try {
    const buffer = await buildDnaAudioBuffer();
    const blob = audioBufferToWav(buffer);
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'zerothink-dna-sound-' + new Date().toISOString().replace(/[:.]/g,'-').slice(0,19) + '.wav';
    a.click();
    setTimeout(()=>URL.revokeObjectURL(a.href),1500);
  } catch(e) {
    alert(e.message);
  }
}

async function analyzeLocal(){
  try {
    const sequence=document.getElementById('seq').value.trim();
    if(!sequence) return alert('Paste a sequence first.');
    setOut('Running local Zero Library DNA analysis...');
    const j=await post('dna',{sequence});
    rememberReport(j.markdown, {summary:j.analysis, chart:qualityChart({parsed_variants:j.analysis.length, called_variants:j.analysis.length, no_calls:j.analysis.counts?.N || 0, indel_or_complex:0}), insights:{headline:'Sequence analysis complete', plain_summary:'Local sequence metrics are ready.', cards:[]}}, 'sequence');
  } catch(e) { setOut('Error: ' + e.message); }
}
async function buildAlphaGenome(){
  try {
    setOut('Building AlphaGenome request manifest...');
    const j=await post('dna_alphagenome_manifest',{sequence:document.getElementById('seq').value});
    rememberReport(j.markdown, null, 'alphagenome');
  } catch(e) { setOut('Error: ' + e.message); }
}
async function runClinVar(){
  try {
    const rsids=document.getElementById('clinvar-rsids').value.trim();
    if(!rsids) return alert('Enter one or more RSIDs from a generated report.');
    setOut('Calling NCBI ClinVar through E-utilities...');
    const j=await post('dna_clinvar_lookup',{rsids});
    rememberReport(j.markdown + '\\n\\nRaw JSON preview:\\n' + JSON.stringify(j.items || [], null, 2).slice(0, 10000), null, 'clinvar');
  } catch(e) { setOut('Error: ' + e.message); }
}
async function runVep(){
  try {
    if(!document.getElementById('region').value.trim() || !document.getElementById('allele').value.trim()) return alert('Enter an Ensembl region and allele first, or use the normal upload buttons above.');
    setOut('Calling Ensembl VEP...');
    const j=await post('dna_ensembl_vep',{species:'human',region:document.getElementById('region').value,allele:document.getElementById('allele').value});
    rememberReport(j.markdown + '\\n\\nRaw JSON preview:\\n' + JSON.stringify(j.payload, null, 2).slice(0, 8000), null, 'vep');
  } catch(e) { setOut('Error: ' + e.message); }
}
async function runNcbi(){
  try {
    if(!document.getElementById('ncbi-query').value.trim()) return alert('Enter an NCBI search term first, or use the normal upload buttons above.');
    setOut('Calling NCBI Entrez...');
    const j=await post('dna_ncbi_search',{query:document.getElementById('ncbi-query').value,db:document.getElementById('ncbi-db').value});
    rememberReport(j.markdown + '\\n\\nSummary preview:\\n' + JSON.stringify(j.summary || {}, null, 2).slice(0, 8000), null, 'ncbi');
  } catch(e) { setOut('Error: ' + e.message); }
}

async function askDnaAgent(){
  const q = document.getElementById('agent-question').value.trim();
  if(!q) return alert('Ask the DNA Agent a question first.');
  if(!lastAgentContext) return alert('Generate a new DNA report with a privacy-safe agent summary first.');
  if(!document.getElementById('dna-agent-consent')?.checked) return alert('Confirm the separate DNA Agent consent before sending anything to Groq.');
  const log = document.getElementById('agent-log');
  const submit = document.getElementById('dna-agent-submit');
  if(submit) submit.disabled = true;
  log.textContent = 'DNA Agent is reading the minimized, no-history report summary...';
  const prompt = [
    'You are ZeroThink DNA Agent. Answer in plain English for a normal user.',
    'Use the supplied minimized DNA report context only. Do not claim you read the full report or raw DNA file.',
    'This is a private no-history request. Do not use or request account memory, saved chats, Zero Library, web retrieval, raw marker rows, exact genotypes, RSIDs, file names, hashes, or family-tree text.',
    'If the report includes ZDNA-MAP, explain the evidence math simply: quality score, confidence bands, evidence index, and uncertainty boundary.',
    'Full report rules: explain the Evidence Ledger, matched marker counts, confidence, and limitations before any conclusion.',
    'Health rules: no diagnosis, treatment, medication, prevention, or personal disease risk score. Recommend certified lab confirmation and clinician/genetic counsellor for important findings.',
    'Heritage rules: explain country/region proxy percentages as DNA similarity estimates. Do not turn them into culture, nationality, religion, tribe, identity, lineage, or medical claims.',
    'Lineage rules: never say the user is royal or noble. Raw DNA alone cannot prove lineage. Talk about clues, document gaps, GEDCOM paths, and supporting evidence only.',
    'Ancient grave rules: explain closest public ancient sample/site leads as genetic affinity only. Never say the user descends from a grave, king, soldier, tribe, religion, culture, or named ancient person.',
    'DNA Sound rules: explain ZDNA-SONIC as experimental sonification/ambience only. Do not claim therapy, diagnosis, healing, or proven biological effects.',
    'Theoretical mode rules: explain hypotheses boldly but label them as hypotheses. Never turn Jewish, African, royal, famous-relative, tribe, religion, or ancient-lineage leads into proof.',
    '',
    'User question: ' + q,
    '',
    '[MINIMIZED DNA REPORT CONTEXT]',
    JSON.stringify(lastAgentContext, null, 2).slice(0, 12000)
  ].join('\\n');
  try {
    const res = await fetch('/api_agent.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({prompt, engine:'groq', groq_model:'openai/gpt-oss-120b', model:'openai/gpt-oss-120b', zero_mode:false, use_web:false, sensitive_no_history:true, csrf_token:DNA_CSRF_TOKEN})
    });
    const data = await parseJsonResponse(res);
    if(data.status === 'error') throw new Error(data.reply || data.message || 'Agent request failed.');
    if(data.history_saved === true) throw new Error('The no-history privacy boundary was not confirmed.');
    log.textContent = data.reply || 'No answer returned.';
  } catch(e) {
    log.textContent = 'DNA Agent error: ' + e.message + '\n\nThe report is still available above. Check that a Groq key is saved in Studio → Neural Vault if this account uses BYOK.';
  } finally {
    updateDnaAgentControls();
  }
}

async function copyReport(){
  const text=lastReport;
  if(!text.trim()) return alert('No report to copy yet.');
  try { await navigator.clipboard.writeText(text); showDnaRunState('complete','Report copied','The generated report is on your clipboard.'); } catch(e) { showDnaRunState('error','Copy unavailable','Your browser blocked clipboard access. Use Download Markdown instead.'); }
}
function downloadMarkdown(){
  const text=lastReport;
  if(!text.trim()) return alert('No report to download yet.');
  const blob=new Blob([text],{type:'text/markdown'});
  const a=document.createElement('a');
  a.href=URL.createObjectURL(blob);
  a.download='zerothink-dna-report-'+new Date().toISOString().replace(/[:.]/g,'-').slice(0,19)+'.md';
  a.click();
  setTimeout(()=>URL.revokeObjectURL(a.href),1500);
}
function downloadPdf(){
  if(window.DnaCustomer) return window.DnaCustomer.printReport();
  alert('The readable report is still loading. Try again, or use Download Markdown.');
}

const rawDnaInput = document.getElementById('raw-dna-file');
const dropZone = document.getElementById('drop-zone');
const saveRawToggle = document.getElementById('save-raw-library');
const dnaConsentToggle = document.getElementById('dna-consent');
const dnaAgentConsentToggle = document.getElementById('dna-agent-consent');
if(rawDnaInput){
  rawDnaInput.addEventListener('change', () => updateDnaFileStatus(rawDnaInput.files?.[0] || null));
}
if(saveRawToggle){
  saveRawToggle.addEventListener('change', () => updateDnaFileStatus(selectedDnaFile));
}
if(dnaConsentToggle){
  dnaConsentToggle.addEventListener('change', () => updateDnaFileStatus(selectedDnaFile));
}
if(dnaAgentConsentToggle){
  dnaAgentConsentToggle.addEventListener('change', updateDnaAgentControls);
}
if(dropZone){
  ['dragenter','dragover'].forEach(evt => dropZone.addEventListener(evt, e => {
    e.preventDefault();
    dropZone.classList.add('drag');
  }));
  ['dragleave','drop'].forEach(evt => dropZone.addEventListener(evt, e => {
    e.preventDefault();
    if(evt === 'drop' && !dnaRequestBusy && e.dataTransfer?.files?.length){
      const droppedFile = e.dataTransfer.files[0];
      rawDnaInput.value = '';
      updateDnaFileStatus(droppedFile);
    }
    dropZone.classList.remove('drag');
  }));
}

let currentOriginsData = null;
let originsGuideMode = false;
let originsRegionIndex = 0;
let originsMapBase = '';
let originsMapRequested = false;
let originsActiveTab = 'modern';
let originsLiveCatalog = null;

function getDnaOrigins(report){return report?.origins || report?.heritage?.origins || null;}
function originFinite(value){return value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value));}
function originValue(row, ...keys){for(const key of keys){if(row?.[key] !== null && row?.[key] !== undefined) return row[key];}return null;}
function originLabel(row, fallback = 'Reference group'){
  const names = {EUR:'Europe',SAS:'South Asia',EAS:'East Asia',AFR:'Africa & diaspora',AMR:'The Americas',NEANDERTHAL:'Neanderthal',DENISOVAN:'Denisovan'};
  return typeof row === 'string' ? row : (names[row?.code] || row?.label || row?.population_label || row?.modern_label || row?.code || fallback);
}
function originMeasured(row){return originFinite(originValue(row,'matched_markers','matched_marker_count')) && Number(originValue(row,'matched_markers','matched_marker_count')) > 0;}
function originSources(sources){
  if(!Array.isArray(sources) || !sources.length) return '';
  return '<div class="origin-source-links">' + sources.map(source => typeof source === 'string' ? esc(source) : dnaSafeLink(source.url || source.source_url || '', source.label || source.name || source.source_name || 'Research source') + (source.version || source.source_version ? ' · ' + esc(source.version || source.source_version) : '')).join(' <span aria-hidden="true">·</span> ') + '</div>';
}
function originsGuideData(){
  const guide = {
    headline:'Explore the human journey',
    summary:'An educational tour of the questions your DNA report can explore. This guide contains no personal DNA results or invented example percentages.',
    modern:{headline:'A world of reference populations',plain:'Genetic ancestry research compares observed DNA with groups represented in reference datasets. Explore the map to see how a reference location differs from a personal birthplace, nationality or migration route.',broad_regions:[
      {code:'EUR',label:'Europe',plain:'A broad reference region. Individual study groups provide more specific sampling context when your file is compared.',map_location:{lat:50,lon:15,label:'Broad European reference context'}},
      {code:'AFR',label:'Africa',plain:'African reference populations span many distinct histories. A broad regional label cannot capture that diversity.',map_location:{lat:4,lon:20,label:'Broad African reference context'}},
      {code:'SAS',label:'South Asia',plain:'Reference sampling can include several South Asian study groups. Compare their evidence within the same source panel.',map_location:{lat:24,lon:78,label:'Broad South Asian reference context'}},
      {code:'EAS',label:'East Asia',plain:'Study populations are samples used in research; their labels do not assign someone a nationality or cultural identity.',map_location:{lat:35,lon:110,label:'Broad East Asian reference context'}},
      {code:'AMR',label:'The Americas',plain:'American reference groups can reflect complex mixed histories. Sampling location is not the same as a genetic origin.',map_location:{lat:8,lon:-78,label:'Broad American reference context'}}
    ],study_populations:[],boundary:'Educational reference locations only; no DNA has been compared in this guide.'},
    ancient:{headline:'Meet the worlds of the ancient DNA record',plain:'Ancient genomes offer glimpses of sampled communities from the past. Your report compares usable markers where a source panel and your file overlap.',groups:[
      {code:'guide_hg',label:'Hunter-gatherer communities',era:'Before and alongside early farming',region:'Several ancient reference regions',short_plain:'Foraging communities had diverse histories. Ancient reference groups let researchers compare sampled people across places and periods.'},
      {code:'guide_farmer',label:'Early farming communities',era:'The spread of farming',region:'Ancient farming reference contexts',short_plain:'Ancient DNA helps researchers investigate how farming communities moved, mixed and interacted with neighbouring populations.'},
      {code:'guide_steppe',label:'Steppe-associated populations',era:'Later prehistoric transitions',region:'Eurasian Steppe reference contexts',short_plain:'Steppe-associated samples provide one window into later prehistoric population history. Similarity to sampled groups is not proof of a named ancestor.'}
    ],boundary:'These chapters are historical reference contexts, not a reconstructed family tree.'},
    archaic:{headline:'A deeper chapter of human history',plain:'Archaic DNA research uses defined source variants to investigate connections with Neanderthal and Denisovan genomes.',categories:[{code:'neanderthal',label:'Neanderthal',plain:'A source-tag screen asks which directly observed variants in your file match a published research tag set. It does not measure a whole-genome ancestry percentage.'},{code:'denisovan',label:'Denisovan',plain:'A Denisovan research-tag result depends on the available source panel, marker coverage and exact allele alignment. Missing tags cannot establish absence of ancestry.'}],boundary:'No personal archaic result is shown in this educational guide.'},evidence_cards:[]
  };
  if(originsLiveCatalog?.ancient?.installed && originsLiveCatalog.ancient.groups?.length){
    guide.ancient.groups = originsLiveCatalog.ancient.groups.map(group => ({...group,short_plain:group.description,panel_marker_count:group.marker_count}));
    guide.ancient.plain = 'Explore the ancient reference groups currently installed in the source library. These catalog entries describe the reference samples; they are not personal DNA matches.';
  }
  if(originsLiveCatalog?.archaic?.installed){
    guide.archaic.categories = guide.archaic.categories.map(row => {
      const source = originsLiveCatalog.archaic.groups?.find(group => group.code.toLowerCase() === row.code);
      return source ? {...row,panel_tag_count:source.marker_count,sources:[source]} : row;
    });
  }
  return guide;
}
function showOriginsGuide(tab = 'modern'){
  window.DnaCustomer?.guideShown();
  renderDnaOrigins({origins:originsGuideData()},'guide');
  activateOriginsTab(tab);
  document.querySelectorAll('[data-report-export]').forEach(button => button.disabled = true);
  document.getElementById('origins-report').scrollIntoView({behavior:dnaMotionAllowed() ? 'smooth':'instant',block:'start'});
}
function restoreOriginsReport(){
  renderDnaOrigins(lastReportData,lastReportKind);
  document.querySelectorAll('[data-report-export]').forEach(button => button.disabled = !lastReport);
  window.DnaCustomer?.restoreResults();
}
function renderDnaOrigins(report, kind){
  const origins = getDnaOrigins(report);
  const container = document.getElementById('origins-report');
  container.hidden = !origins;
  currentOriginsData = origins;
  originsGuideMode = kind === 'guide';
  originsRegionIndex = 0;
  document.getElementById('origins-overview-link').href = origins ? '#origins-report' : '#visual-report';
  if(!origins) return;
  container.classList.toggle('educational',originsGuideMode);
  document.getElementById('origins-mode').textContent = originsGuideMode ? 'Educational guide · not your DNA results' : 'Your origins journey · measured from this file';
  document.getElementById('origins-title').textContent = origins.headline || 'Your origins at a glance';
  document.getElementById('origins-summary').textContent = origins.summary || 'Explore your regional references, ancient comparisons and archaic research markers.';
  document.getElementById('origins-return').hidden = !originsGuideMode || !getDnaOrigins(lastReportData);
  const modern = origins.modern || {};
  const broad = Array.isArray(modern.broad_regions) ? modern.broad_regions : [];
  const closest = Object.prototype.hasOwnProperty.call(modern,'closest_region') ? modern.closest_region : broad.find(originMeasured);
  const modernTies = Array.isArray(modern.tied_top_regions) ? modern.tied_top_regions.length : 0;
  const ancient = origins.ancient || {};
  const ancientGroups = Array.isArray(ancient.groups) ? ancient.groups : [];
  const firstAncient = ancientGroups.find(originMeasured);
  const commonAncientMarkers = Number(ancient.common_marker_count ?? originValue(firstAncient,'matched_markers','matched_marker_count') ?? 0);
  const ancientLead = Object.prototype.hasOwnProperty.call(ancient,'closest_group') ? ancient.closest_group : (!ancient.comparison_tied && ancient.status !== 'sparse_reference_overlap' && commonAncientMarkers >= 50 ? firstAncient : null);
  const similarAncient = Number(ancient.nearby_group_count || ancient.tied_top_groups?.length || ancient.tied_top_labels?.length || 0) || (firstAncient && originFinite(firstAncient.similarity_score) ? ancientGroups.filter(row => originMeasured(row) && originFinite(row.similarity_score) && Math.abs(Number(row.similarity_score)-Number(firstAncient.similarity_score)) <= 0.1).length : 0);
  const archaic = origins.archaic || {};
  const archaicRows = Array.isArray(archaic.categories) ? archaic.categories : [];
  const covered = archaicRows.filter(row => Number(row.callable_tag_count || 0) > 0);
  const leadingArchaic = covered.find(row => String(row.code || row.label).toLowerCase().includes('neanderthal')) || covered[0];
  const archaicName = leadingArchaic ? (String(leadingArchaic.code || leadingArchaic.label).toLowerCase().includes('neanderthal') ? 'Neanderthal' : 'Denisovan') : '';
  const highlight = (tab, label, title, note) => '<button class="origin-highlight" type="button" onclick="activateOriginsTab(\'' + tab + '\',true)"><span>' + esc(label) + '</span><b>' + esc(title) + '</b><small>' + esc(note) + '</small></button>';
  document.getElementById('origins-highlights').innerHTML = [
    highlight('modern','Broad reference findings',originsGuideMode ? 'Explore the world map' : modern.comparison_tied || modernTies > 1 ? (modernTies > 1 ? modernTies + ' regional references overlap' : 'Comparable regional references') : closest ? originLabel(closest) : 'Regional evidence check',originsGuideMode ? 'Meet the reference populations behind the analysis.' : closest && originMeasured(closest) ? dnaNumber(originValue(closest,'matched_markers','matched_marker_count')) + ' compared markers · explore the group comparisons.' : modern.plain || 'Open your reference comparison and file-validation details.'),
    highlight('ancient','Ancient population context',originsGuideMode ? 'Hunters, farmers & Steppe' : similarAncient > 1 && commonAncientMarkers >= 50 ? similarAncient + ' ancient references overlap' : ancientLead ? originLabel(ancientLead) : 'Explore the ancient record',originsGuideMode ? 'Discover the sampled communities of the ancient DNA record.' : commonAncientMarkers >= 50 ? dnaNumber(commonAncientMarkers) + ' common markers' + (similarAncient > 1 ? ' · similar sharing scores across these groups.' : ' in this reference comparison.') : ancient.plain || 'Open the ancient chapters for source coverage and historical context.'),
    highlight('archaic','Archaic research markers',originsGuideMode ? 'Neanderthal & Denisovan' : leadingArchaic ? dnaNumber(leadingArchaic.observed_tag_count) + ' ' + archaicName + '-associated markers' : 'Your archaic-marker screen',originsGuideMode ? 'Learn what a marker screen can tell you.' : leadingArchaic ? 'Found among ' + dnaNumber(leadingArchaic.callable_tag_count) + ' markers your file could test. Explore both research screens.' : archaic.plain || 'See which published research tags your file can test.')
  ].join('');
  renderOriginsModern();
  renderOriginsAncient();
  renderOriginsArchaic();
  const cards = Array.isArray(origins.evidence_cards) ? origins.evidence_cards : [];
  document.getElementById('origins-evidence-cards').innerHTML = cards.slice(0,20).map(card => '<details class="source-detail"><summary>' + esc(card.title || 'Source evidence') + '</summary><p>' + esc(card.explanation || card.plain || '') + '</p><p><b>Source:</b> ' + esc(card.source_name || 'Not supplied') + ' · ' + esc(card.source_version || 'Exact release not supplied') + '<br><b>Confidence:</b> ' + esc(card.confidence_band || 'Not established') + '<br><b>Limitations:</b> ' + esc(card.limitations || 'Research context only; see the full report.') + '</p></details>').join('');
  activateOriginsTab(kind === 'ancient' ? 'ancient' : 'modern');
  window.DnaCustomer?.originsRendered();
}
function activateOriginsTab(tab, scroll = false){
  if(!['modern','ancient','archaic'].includes(tab)) return;
  originsActiveTab = tab;
  ['modern','ancient','archaic'].forEach(name => {
    const selected = name === tab;
    const button = document.getElementById('origins-tab-' + name);
    button.setAttribute('aria-selected',String(selected));
    button.tabIndex = selected ? 0 : -1;
    document.getElementById('origins-panel-' + name).hidden = !selected;
  });
  if(scroll) document.getElementById('origins-tab-' + tab).scrollIntoView({behavior:dnaMotionAllowed() ? 'smooth':'instant',block:'nearest'});
  window.DnaAncestryStory?.tabChanged(tab);
}
function originsTabKey(event){
  const names = ['modern','ancient','archaic'];
  const index = names.indexOf(originsActiveTab);
  const next = event.key === 'ArrowRight' ? (index+1)%3 : event.key === 'ArrowLeft' ? (index+2)%3 : event.key === 'Home' ? 0 : event.key === 'End' ? 2 : -1;
  if(next < 0) return;
  event.preventDefault();activateOriginsTab(names[next]);document.getElementById('origins-tab-' + names[next]).focus();
}
function originCoordinates(row){
  const point = row?.map_location || row?.location || {};
  const lat = originValue(point,'lat','latitude') ?? originValue(row,'lat','latitude');
  const lon = originValue(point,'lon','longitude') ?? originValue(row,'lon','longitude');
  return originFinite(lat) && originFinite(lon) && Math.abs(Number(lat)) <= 90 && Math.abs(Number(lon)) <= 180 ? {lat:Number(lat),lon:Number(lon),label:point.label || row.label || 'Reference location'} : null;
}
function renderOriginsModern(){
  if(window.DnaAncestryStory) return window.DnaAncestryStory.render();
  const modern = currentOriginsData?.modern || {};
  const regions = Array.isArray(modern.broad_regions) ? modern.broad_regions : [];
  const populations = Array.isArray(modern.study_populations) ? modern.study_populations : [];
  const rows = regions.map((row,index) => {
    const score = originValue(row,'affinity_score','similarity_score');
    return '<button type="button" class="origin-region-button' + (index === originsRegionIndex ? ' active' : '') + '" aria-pressed="' + (index === originsRegionIndex) + '" onclick="selectOriginsRegion(' + index + ')"><span class="origin-region-line"><b>' + esc(originLabel(row)) + '</b><span>' + (originsGuideMode || !originFinite(score) ? 'Explore →' : esc(fmtPct(score)) + '/100') + '</span></span>' + (!originsGuideMode && originFinite(score) ? '<div class="coverage-meter" aria-hidden="true"><i style="width:' + Math.max(0,Math.min(100,Number(score))) + '%"></i></div>' : '') + '<small>' + (originsGuideMode ? 'Educational reference location' : dnaNumber(originValue(row,'matched_markers','matched_marker_count')) + ' aligned markers · ' + esc(row.confidence_band || 'uncalibrated')) + '</small></button>';
  }).join('');
  let html = '<div class="origins-modern-layout"><div class="origin-map-card"><div class="origin-map-title"><strong>Regional reference map</strong><span>' + (originsGuideMode ? 'Educational reference locations' : 'Study reference locations') + '</span></div><svg id="origins-world-map" class="origin-world-map" viewBox="0 0 720 360" role="group" aria-label="World map of reference sampling locations"></svg><p class="origin-map-caption">Points locate broad study-reference contexts. They do not map your birthplace, personal migration or an area of genetic ownership. Every point uses the same size.</p><div class="origin-location-details" id="origins-location-details"></div></div><div><p class="origin-section-kicker">' + (originsGuideMode ? 'Choose a reference region' : 'Your broad reference comparisons') + '</p><div class="origin-region-list">' + (rows || '<div class="origin-explainer"><strong>Start with the file evidence.</strong><br>' + esc(modern.plain || 'No comparable regional reference rows were returned. Your quality and source details explain the next step.') + '</div>') + '</div><p class="origin-availability">' + esc(modern.boundary || 'Affinity is a comparison score, not an ancestry percentage. Different source panels are not directly comparable.') + '</p></div></div>';
  if(populations.length){
    html += '<div class="origin-subsection"><p class="eyebrow" style="font-size:.64rem;color:var(--origin-amber)">A closer view</p><h4>Study populations worth exploring</h4><p class="origin-availability" style="margin-bottom:15px">Reference-group names describe the sampled study populations. They are clues for research, not an assignment of ethnicity or identity.</p><div class="origin-population-grid">' + populations.slice(0,9).map(row => '<article class="origin-population"><h4>' + esc(originLabel(row)) + '</h4><div class="origin-affinity">' + (originFinite(row.affinity_score) ? esc(fmtPct(row.affinity_score)) + '<span style="font-size:.7rem;font-weight:500"> /100 affinity</span>' : 'Reference context') + '</div><p>' + dnaNumber(originValue(row,'matched_markers','matched_marker_count')) + ' aligned markers' + (originFinite(row.chromosome_count) ? ' · ' + dnaNumber(row.chromosome_count) + ' chromosomes' : '') + '</p><small>' + esc(row.plain || row.confidence_band || 'Research reference comparison') + (row.source_version ? '<br>' + esc(row.source_version) : '') + '</small></article>').join('') + '</div></div>';
  }
  document.getElementById('origins-modern-content').innerHTML = html;
  renderOriginsMap();renderOriginsLocation();
}
function renderOriginsMap(){
  const svg = document.getElementById('origins-world-map');
  if(!svg || !currentOriginsData) return;
  const rows = currentOriginsData.modern?.broad_regions || [];
  const points = rows.map((row,index) => {
    const point = originCoordinates(row);if(!point) return '';
    const [x,y] = projectDnaLonLat(point.lon,point.lat);
    const active = index === originsRegionIndex;
    return '<g class="origin-map-point' + (active ? ' active' : '') + '" role="button" tabindex="0" aria-label="Explore ' + esc(originLabel(row)) + ' reference context" onclick="selectOriginsRegion(' + index + ')" onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();selectOriginsRegion(' + index + ');}"><circle class="point-ring" cx="' + x.toFixed(2) + '" cy="' + y.toFixed(2) + '" r="7"></circle><circle class="point-core" cx="' + x.toFixed(2) + '" cy="' + y.toFixed(2) + '" r="5"></circle>' + (active ? '<text x="' + x.toFixed(2) + '" y="' + (y-13).toFixed(2) + '" text-anchor="middle">' + esc(originLabel(row)) + '</text>' : '') + '</g>';
  }).join('');
  svg.innerHTML = '<g aria-hidden="true"><rect width="720" height="360" fill="transparent"></rect>' + dnaMapGridSvg() + originsMapBase + '</g>' + points;
  if(!originsMapRequested){
    originsMapRequested = true;
    fetch('/assets/maps/world-countries-110m.json',{cache:'force-cache'}).then(response => {if(!response.ok) throw new Error('Map unavailable');return response.json();}).then(topology => {originsMapBase = buildDnaWorldMapSvg(topology);renderOriginsMap();}).catch(() => {
      const caption = document.querySelector('#origins-modern-content .origin-map-caption');
      if(caption) caption.textContent = 'The land-outline asset is unavailable. Reference points retain their geographic positions on the latitude/longitude grid; they are not personal migration routes.';
    });
  }
}
function selectOriginsRegion(index){
  const rows = currentOriginsData?.modern?.broad_regions || [];
  if(!Number.isInteger(index) || index < 0 || index >= rows.length) return;
  originsRegionIndex = index;
  document.querySelectorAll('.origin-region-button').forEach((button,i) => {button.classList.toggle('active',i === index);button.setAttribute('aria-pressed',String(i === index));});
  renderOriginsMap();renderOriginsLocation();
}
function renderOriginsLocation(){
  if(window.DnaAncestryStory) return window.DnaAncestryStory.renderLocation();
  const row = currentOriginsData?.modern?.broad_regions?.[originsRegionIndex];
  const target = document.getElementById('origins-location-details');if(!target) return;
  if(!row){target.innerHTML = '<p>Your regional story begins with an aligned reference comparison. You can inspect your file quality and source coverage in the detailed report below.</p>';return;}
  const markers = originValue(row,'matched_markers','matched_marker_count');
  const score = originValue(row,'affinity_score','similarity_score');
  const point = originCoordinates(row);
  const stats = !originsGuideMode ? '<div class="origin-location-stats">' + (originFinite(score) ? '<div><b>' + esc(fmtPct(score)) + '/100</b><span>Reference affinity</span></div>' : '') + (originFinite(markers) ? '<div><b>' + dnaNumber(markers) + '</b><span>Aligned markers</span></div>' : '') + (originFinite(row.chromosome_count) ? '<div><b>' + dnaNumber(row.chromosome_count) + '</b><span>Chromosomes compared</span></div>' : '') + '</div>' : '';
  target.innerHTML = '<p class="eyebrow">' + (originsGuideMode ? 'Explore a reference context' : 'Selected reference comparison') + '</p><h4>' + esc(originLabel(row)) + '</h4><p>' + esc(row.plain || row.short_plain || (originsGuideMode ? 'This is a reference location used to explain the map.' : 'This score reflects directly compared markers in the selected reference panel. It does not assign a nationality or an ancestry percentage.')) + '</p>' + (point ? '<p style="font-size:.73rem">Map context: ' + esc(point.label) + '</p>' : '') + stats + (!originsGuideMode ? '<p style="font-size:.7rem;margin-top:14px">Confidence: ' + esc(row.confidence_band || 'not established') + (row.source_version ? ' · ' + esc(row.source_version) : '') + '</p>' : '');
}
function originAncientContext(group){
  const label = (group.code || '') + ' ' + (group.label || '');
  if(/hunter|gather|WHG|EHG|CHG/i.test(label)) return 'Ancient hunter-gatherer reference samples represent distinct foraging communities. Similarity is measured only where usable source and file markers overlap.';
  if(/farm|neolithic|ANF|EEF/i.test(label)) return 'Ancient farming reference samples help investigate population history during the spread of agriculture. They are sampled historical contexts, not complete ancestor lists.';
  if(/steppe|yamnaya|bronze/i.test(label)) return 'Steppe-associated genomes provide a reference for prehistoric Eurasian population history. A shared allele is not evidence of descent from a particular person.';
  return 'This group represents sampled ancient individuals with a documented place and period. Your comparison depends on usable marker overlap and source quality.';
}
function renderOriginsAncient(){
  const ancient = currentOriginsData?.ancient || {};
  const measuredGroups = Array.isArray(ancient.groups) ? ancient.groups : [];
  const placeContext = !originsGuideMode && Array.isArray(lastReportData?.country_context?.ancient) ? lastReportData.country_context.ancient : [];
  const groups = [...(measuredGroups.length ? measuredGroups : originsGuideData().ancient.groups)].map(group => {
    const place = placeContext.find(row => row.code === group.code);
    return place ? {...group,source_group_label:group.label,label:place.label || group.label,short_plain:place.plain || group.short_plain,region:(place.places || []).map(row => row.label + (row.country ? ' (' + row.country + ')' : '')).join(' · '),boundary:place.boundary || group.boundary} : group;
  }).sort((a,b) => {
    const left = originValue(a,'time_start_bp','era_years_bp');
    const right = originValue(b,'time_start_bp','era_years_bp');
    return originFinite(left) && originFinite(right) ? Number(right)-Number(left) : 0;
  });
  const contextualOnly = originsGuideMode || !measuredGroups.length;
  const cards = groups.slice(0,12).map(group => {
    const markers = originValue(group,'matched_markers','matched_marker_count');
    const compared = originValue(group,'compared_allele_count','compared_alleles');
    const shared = originValue(group,'shared_allele_equivalents','shared_allele_count','shared_alleles');
    const sharingIsExpected = group.shared_allele_equivalents != null;
    const sampleCount = originValue(group,'sample_count','matched_sample_count');
    const measured = !contextualOnly && originMeasured(group);
    return '<article class="origin-chapter"><div class="origin-chapter-art" role="img" aria-label="Artistic ancient landscape illustration"><span>' + (measured ? 'Compared with your file' : 'Historical reference context') + '</span></div><div class="origin-chapter-body">' + (group.era ? '<p class="origin-era">' + esc(group.era) + '</p>' : '') + '<h4>' + esc(originLabel(group,'Ancient population')) + '</h4>' + (group.source_group_label ? '<small class="country-place-gloss">Study label: ' + esc(group.source_group_label) + '</small>' : '') + '<p>' + esc(group.short_plain || group.plain || originAncientContext(group)) + '</p>' + (group.region ? '<p style="font-size:.71rem">Reference region: ' + esc(group.region) + '</p>' : '') + '<div class="origin-chapter-stat">' + (measured ? '<strong>' + dnaNumber(markers) + ' markers</strong><small>Compared in this group' + (originFinite(sampleCount) ? ' · ' + dnaNumber(sampleCount) + ' sampled individuals' : '') + '</small>' + (originFinite(shared) && originFinite(compared) ? '<small>' + (sharingIsExpected ? fmtPct(shared,1) + ' expected shared allele equivalents / ' : dnaNumber(shared) + ' shared allele observations / ') + dnaNumber(compared) + ' compared alleles</small>' : '') + (originFinite(group.similarity_score) ? '<small>Allele-sharing score: ' + esc(fmtPct(group.similarity_score)) + '/100. Not ancestry composition.</small>' : '') : '<strong style="font-size:.96rem">' + (originsGuideMode ? 'A chapter to explore' : 'Research context') + '</strong><small>' + esc(group.reason || (originsGuideMode ? 'Your own result will show the measured marker overlap here.' : 'No supported personalized comparison was returned for this chapter.')) + '</small>') + '</div><details><summary>Source &amp; interpretation</summary><p>Confidence: ' + esc(group.confidence_band || (contextualOnly ? 'Educational context only' : 'Not established')) + '<br>Source: ' + esc(group.source_version || group.source_name || 'See the full report source ledger') + '</p><p>' + esc(group.boundary || group.limitations || ancient.boundary || 'Ancient sample affinity does not establish a named ancestor or personal ancestry percentage.') + '</p>' + originSources(group.sources) + '</details></div></article>';
  }).join('');
  document.getElementById('origins-ancient-content').innerHTML = '<div class="origin-context-banner"><div><p class="eyebrow">Ancient DNA · sampled windows into the past</p><h4>' + esc(placeContext.length ? 'Ancient communities, placed on today’s map' : ancient.headline || 'The ancient chapters of population history') + '</h4><p>' + esc(placeContext.length ? 'Start with the modern locations of the sampled remains, then explore each archaeological chapter and the evidence your file could compare.' : ancient.plain || 'Explore the reference communities behind ancient DNA comparisons.') + '</p></div></div><div class="origin-timeline">' + cards + '</div><div class="origin-explainer"><strong>Read this as a comparison with sampled ancient groups.</strong> ' + esc(ancient.boundary || 'Shared markers do not measure how much of your genome came from an ancient group. Source groups can overlap, and sample dates do not form your personal family timeline.') + '<br><small>Landscape images are artistic illustrations, not portraits or reconstructions of your ancestors.</small></div>';
}
function renderOriginsArchaic(){
  const archaic = currentOriginsData?.archaic || {};
  const categories = Array.isArray(archaic.categories) ? archaic.categories : [];
  const guideRows = originsGuideData().archaic.categories;
  const rows = ['neanderthal','denisovan'].map(code => categories.find(row => String(row.code || row.label || '').toLowerCase().includes(code)) || guideRows.find(row => row.code === code));
  const cards = rows.map(row => {
    const observed = row.observed_tag_count;
    const callable = row.callable_tag_count;
    const total = row.panel_tag_count;
    const hasData = !originsGuideMode && originFinite(callable) && Number(callable) > 0 && originFinite(observed);
    const coverage = originFinite(total) && Number(total) > 0 && originFinite(callable) ? Math.max(0,Math.min(100,Number(callable)/Number(total)*100)) : null;
    const intro = row.plain || row.short_plain || (String(row.code).includes('neanderthal') ? 'A research-tag screen explores directly observed variants linked to Neanderthal studies.' : 'A research-tag screen explores directly observed variants linked to Denisovan studies.');
    let result = '';
    if(hasData){
      result = '<div class="origin-tag-result"><b>' + dnaNumber(observed) + '</b><span>associated markers found<br>among ' + dnaNumber(callable) + ' markers your file could test</span></div><small>These are associated markers in the tested research panel, not a percentage of your DNA. Definitions and source limitations are below.</small><div class="origin-archaic-facts"><div><b>' + dnaNumber(callable) + '</b><span>Markers tested</span></div>' + (originFinite(total) ? '<div><b>' + dnaNumber(total) + '</b><span>Markers in source panel</span></div>' : '') + (coverage !== null ? '<div><b>' + esc(fmtPct(coverage,2)) + '%</b><span>Reference coverage</span></div>' : '') + '</div>' + (coverage !== null ? '<div class="coverage-meter" role="meter" aria-label="Source panel coverage" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + coverage.toFixed(2) + '" style="height:7px;margin-bottom:13px"><i style="width:' + coverage + '%;background:var(--origin-violet)"></i></div>' : '');
    } else {
      result = '<div class="origin-tag-result"><b style="font-size:1.45rem;line-height:1.3">' + (originsGuideMode ? 'Explore the research' : 'Check the source coverage') + '</b></div><small>' + esc(row.reason || row.coverage_note || (originsGuideMode ? 'After analysis, this space shows observed source tags and the number of panel tags your file could test.' : 'No supported callable-tag result was returned for this category. This does not establish absence of archaic ancestry.')) + '</small>' + (!originsGuideMode && originFinite(total) ? '<div class="origin-archaic-facts"><div><b>' + dnaNumber(total) + '</b><span>Source-panel tags</span></div><div><b>' + dnaNumber(callable ?? 0) + '</b><span>Callable tags in this file</span></div></div>' : '');
    }
    return '<article class="origin-archaic-card"><div class="origin-archaic-art"><h4>' + esc(originLabel(row)) + '</h4></div><div class="origin-archaic-body"><span class="origin-pill">' + (originsGuideMode ? 'Educational research context' : hasData ? 'Measured research-tag screen' : 'Source &amp; coverage review') + '</span><p>' + esc(intro) + '</p>' + result + '<details class="source-detail"><summary>What this result means</summary><p>' + esc(row.boundary || row.limitations || archaic.boundary || 'These research markers are a limited panel. They do not determine your total archaic ancestry, traits or identity.') + '</p><p>Confidence: ' + esc(row.confidence_band || (originsGuideMode ? 'Educational context' : 'Not established')) + '</p>' + originSources(row.sources) + '</details></div></article>';
  }).join('');
  document.getElementById('origins-archaic-content').innerHTML = '<div style="margin-bottom:20px"><p class="eyebrow" style="font-size:.64rem;color:var(--origin-violet)">Shared human history</p><h4 style="font-size:1.6rem;letter-spacing:-.035em;margin:0 0 10px">' + esc(archaic.headline || 'Archaic connections, one tested marker at a time') + '</h4><p class="origin-availability">' + esc(archaic.plain || 'See what the available source tags and your raw file can measure.') + '</p></div><div class="origin-archaic-grid">' + cards + '</div><div class="origin-explainer"><strong>Panel coverage is not genome coverage.</strong> A raw SNP file tests selected positions. Untested tags remain unknown, and an observed research tag is not a diagnosis, trait prediction or measure of your entire Neanderthal or Denisovan ancestry. The two screens may include overlapping loci.</div>';
}


function renderOriginsCatalog(catalog){
  originsLiveCatalog = catalog || null;
  const badges = document.getElementById('origins-catalog-badges');
  const detail = document.getElementById('origins-catalog-detail');
  badges.hidden = !catalog || (!catalog.ancient?.installed && !catalog.archaic?.installed);
  if(!catalog){detail.innerHTML = '';return;}
  const ancient = catalog.ancient || {};
  const archaic = catalog.archaic || {};
  const badge = (count,label) => '<div><b>' + dnaNumber(count) + '</b><span>' + esc(label) + '</span></div>';
  let summary = ancient.installed ? badge(ancient.group_count ?? ancient.groups?.length,'ancient reference groups') : '';
  if(archaic.installed) summary += (archaic.groups || []).filter(group => originFinite(group.marker_count)).map(group => badge(group.marker_count,originLabel(group) + ' source markers')).join('');
  badges.innerHTML = summary + '<small>Installed source library · your file’s usable overlap is measured after upload.</small>';
  let html = '<h4 style="margin:24px 0 12px">Ancient &amp; archaic source library</h4>';
  for(const [kind,pack] of [['ancient',ancient],['archaic',archaic]]){
    const title = kind === 'ancient' ? 'Ancient population references' : 'Neanderthal & Denisovan research markers';
    const groups = Array.isArray(pack.groups) ? pack.groups : [];
    html += '<details class="source-detail"><summary>' + esc(title) + '<span class="evidence-label">' + (pack.installed ? 'Installed · ' + groups.length + ' source groups' : 'No active source pack reported') + '</span></summary>';
    if(groups.length) html += '<div class="source-table-wrap"><table class="source-table"><thead><tr><th scope="col">Source group</th><th scope="col">Source coverage</th><th scope="col">Version &amp; provenance</th></tr></thead><tbody>' + groups.map(group => '<tr><td><b>' + esc(group.label || group.code) + '</b>' + (group.era ? '<br>' + esc(group.era) : '') + (group.region ? '<br>' + esc(group.region) : '') + '</td><td>' + dnaNumber(group.marker_count) + ' source markers' + (originFinite(group.sample_count) ? '<br>' + dnaNumber(group.sample_count) + ' reference individuals' : '') + '</td><td>' + esc(group.source_version || pack.metadata?.source_version || 'Exact release not supplied') + (group.source_url ? '<br>' + dnaSafeLink(group.source_url,'Original source') : '') + '</td></tr>').join('') + '</tbody></table></div>';
    if(pack.metadata?.license || pack.metadata?.license_note) html += '<p>' + esc(pack.metadata.license || pack.metadata.license_note) + '</p>';
    html += '</details>';
  }
  detail.innerHTML = html + '<p class="origin-availability">' + esc(catalog.boundary || 'Catalog counts describe installed reference sources, not personal DNA findings.') + '</p>';
}
function dnaMotionAllowed(){return !dnaMotionPaused && !dnaReducedMotion.matches;}
function applyDnaMotion(){
  document.documentElement.classList.toggle('motion-paused', dnaMotionPaused || dnaReducedMotion.matches);
  const button = document.getElementById('motion-toggle');
  button.textContent = dnaReducedMotion.matches ? 'Reduced motion enabled' : (dnaMotionPaused ? 'Resume animations' : 'Pause animations');
  button.setAttribute('aria-pressed', String(dnaMotionPaused || dnaReducedMotion.matches));
  button.disabled = dnaReducedMotion.matches;
  window.DnaDiscovery?.motionChanged();
}
function toggleDnaMotion(){dnaMotionPaused = !dnaMotionPaused; applyDnaMotion();}
function dnaNumber(value){return value == null || value === '' || !Number.isFinite(Number(value)) ? 'Not reported' : Number(value).toLocaleString();}
function validateDnaFile(file){
  if(!file) return '';
  if(!/\.(txt|csv|tsv|vcf|dna|genome|raw)$/i.test(file.name || '')) return 'Choose an uncompressed TXT, CSV, TSV, VCF, DNA, GENOME or RAW file. ZIP/GZIP files need to be decompressed first.';
  if(!Number(file.size)) return 'This file is empty. Choose the original raw DNA export.';
  if(Number(file.size) > ZERO_DNA_MAX_RAW_UPLOAD_BYTES) return 'This file exceeds the 30 MB upload limit. Choose a supported raw genotype export up to 30 MB.';
  return '';
}
function showDnaRunState(state, title, detail){
  const box = document.getElementById('dna-run-status');
  box.hidden = false;
  box.className = 'run-status ' + state;
  document.getElementById('dna-run-title').textContent = title;
  document.getElementById('dna-run-detail').textContent = detail;
  if(state !== 'busy') document.getElementById('dna-upload-progress').hidden = true;
}
function setDnaBusy(busy){
  dnaRequestBusy = busy;
  document.getElementById('report-panel').setAttribute('aria-busy', String(busy));
  if(busy){
    dnaDisabledControls = new Map();
    document.querySelectorAll('.dna-action, #drop-zone input, #drop-zone select, .advanced input, .advanced textarea, .advanced select, .advanced button, .advanced-console button, .advanced-console textarea, #clear-dna-file, #dna-agent-submit').forEach(control => {
      dnaDisabledControls.set(control, control.disabled);
      control.disabled = true;
    });
  } else {
    dnaDisabledControls.forEach((disabled, control) => control.disabled = disabled);
    dnaDisabledControls.clear();
    updateDnaAgentControls();
  }
}
function uploadDnaForm(fd, label){
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    const progress = document.getElementById('dna-upload-progress');
    xhr.open('POST','/api_library.php');
    xhr.timeout = 20 * 60 * 1000;
    xhr.upload.addEventListener('progress', e => {
      if(e.lengthComputable){
        const percent = Math.min(100, Math.floor(e.loaded / e.total * 100));
        progress.hidden = false;
        progress.value = percent;
        document.getElementById('dna-run-detail').textContent = 'Upload ' + percent + '% · ' + formatBytes(e.loaded) + ' of ' + formatBytes(e.total) + '. This measures transfer only, not analysis progress.';
      }
    });
    xhr.upload.addEventListener('load', () => {
      progress.hidden = true;
      showDnaRunState('busy', 'Analyzing · ' + label, 'File transfer finished. The server is processing the selected report. No analysis percentage is available; results appear when processing completes.');
    });
    xhr.addEventListener('load', async () => {
      try { resolve(await parseJsonResponse({ok:xhr.status >= 200 && xhr.status < 300, status:xhr.status, text:async () => xhr.responseText})); }
      catch(e){reject(e);}
    });
    xhr.addEventListener('error', () => reject(new Error('The connection to ZeroThink was interrupted. Processing may still be running on the server.')));
    xhr.addEventListener('timeout', () => reject(new Error('The request timed out. The server may still be processing; wait before sending the file again.')));
    xhr.send(fd);
  });
}
function dnaSafeLink(url, title){
  try {const parsed = new URL(url); if(parsed.protocol === 'https:' || parsed.protocol === 'http:') return '<a href="' + esc(parsed.href) + '" target="_blank" rel="noopener noreferrer">' + esc(title) + '</a>';}
  catch(e){}
  return esc(title);
}
function renderDnaEvidence(report, kind){
  document.getElementById('dna-quality-panel').hidden = !report;
  document.getElementById('dna-sources-panel').hidden = !report;
  if(!report) return;
  const raw = report.raw || report.summary || {};
  const heritage = kind === 'heritage' ? report : (report.heritage || {});
  const pack = heritage.local_reference_pack || {};
  const ancient = kind === 'ancient' ? report.ancient_pack : report.ancient_graves?.ancient_pack;
  const ancientOrigins = getDnaOrigins(report)?.ancient || null;
  const parsed = Number(raw.parsed_variants ?? raw.marker_count ?? 0);
  const hasCallRate = parsed > 0 && raw.called_variants != null;
  const rate = hasCallRate ? Math.max(0, Math.min(100, Number(raw.called_variants) / parsed * 100)) : null;
  const metric = (title, value, note, percent = null) => '<div class="coverage-card"><span>' + esc(title) + '</span><b>' + esc(value) + '</b><small>' + esc(note) + '</small>' + (percent !== null && Number.isFinite(Number(percent)) ? '<div class="coverage-meter" aria-hidden="true"><i style="width:' + Math.max(0,Math.min(100,Number(percent))) + '%"></i></div>' : '') + '</div>';
  document.getElementById('dna-coverage').innerHTML = [
    metric('Called marker rate', rate === null ? 'Not reported' : fmtPct(rate, 2) + '%', rate === null ? 'Requires called and parsed counts.' : dnaNumber(raw.called_variants) + ' called / ' + dnaNumber(parsed) + ' parsed markers.', rate),
    metric('Input quality score', raw.quality_score == null ? 'Not reported' : dnaNumber(raw.quality_score) + '/100', (raw.quality_band || 'No quality band reported') + ' · parser heuristic; not a clinical accuracy score.', raw.quality_score ?? null),
    metric('Reference overlap', pack.matched_marker_count == null ? 'Not reported' : dnaNumber(pack.matched_marker_count), pack.installed ? 'Markers matched to the active heritage reference panel. This is not independent validation.' : 'No active heritage reference match was reported.'),
    metric('Genome build', raw.genome_build_guess || 'Not reported', 'Build confidence: ' + (raw.genome_build_confidence || 'not reported') + ' · strand: ' + (raw.reported_orientation || 'not reported'))
  ].join('');
  const chroms = Object.entries(raw.top_chromosomes || {}).filter(([chrom,count]) => /^([1-9]|1[0-9]|2[0-2]|X|Y|MT|M)$/i.test(chrom) && Number.isFinite(Number(count)) && Number(count) >= 0).sort((a,b) => a[0].localeCompare(b[0], undefined, {numeric:true}));
  const max = Math.max(1,...chroms.map(row => Number(row[1])));
  document.getElementById('dna-chromosomes').innerHTML = chroms.length ? '<h4>Chromosome coverage</h4><p class="muted" style="font-size:.85rem">Largest chromosome groups returned by the parser (up to 12). Bar height shows marker count, not genetic ancestry. Unlisted chromosomes are not necessarily absent.</p><div class="chromosome-strip" role="list">' + chroms.map(([chrom,count]) => '<div class="chromosome-bar" role="listitem" aria-label="Chromosome ' + esc(chrom) + ': ' + esc(dnaNumber(count)) + ' markers"><span aria-hidden="true"><i style="height:' + Math.max(2,Number(count)/max*88).toFixed(1) + 'px"></i></span><b>' + esc(chrom) + '</b><small>' + esc(dnaNumber(count)) + '</small></div>').join('') + '</div>' : '';
  const notes = [];
  if(raw.truncated) notes.push('The parser reached its row limit. This report covers a partial scan, so input and reference coverage are incomplete.');
  if(Number(raw.duplicate_rsid_count) > 0) notes.push(dnaNumber(raw.duplicate_rsid_count) + ' duplicate marker IDs were reported. Check duplicate and conflicting-call handling in the full evidence report.');
  if(!raw.genome_build_guess || /unknown|undetermined/i.test(raw.genome_build_guess)) notes.push('Genome build is not established. Confirm the vendor documentation before choosing a build; do not guess.');
  if(rate !== null && rate < 95) notes.push('Missing calls reduce the usable evidence. A missing or untested variant is not a negative finding.');
  const gates = [pack.analysis_gate, kind === 'health' ? report.insights?.analysis_gate : report.health?.insights?.analysis_gate].filter(Boolean);
  gates.forEach(gate => {
    const reason = typeof gate === 'string' ? gate : (Array.isArray(gate.reasons) && gate.reasons.length ? gate.reasons.join('; ') : gate.reason);
    if(reason && !notes.includes(reason)) notes.push(reason.replaceAll('_',' '));
  });
  if(raw.genome_build_conflict) notes.push('The declared build conflicts with the file metadata. Resolve this conflict before interpreting variant matches.');
  const noteBox = document.getElementById('dna-quality-notes');
  noteBox.hidden = notes.length === 0;
  noteBox.innerHTML = notes.map(esc).join('<br><br>');

  const sources = Array.isArray(report.source_versions) ? report.source_versions : [];
  const packs = Array.isArray(report.data_pack_status) ? report.data_pack_status : [];
  const meta = pack.pack_meta || {};
  let html = '';
  if(pack.installed || Object.keys(meta).length){
    const version = meta.source_version || meta.pack_version || meta.version || meta.source_release || 'Exact release not supplied in this report';
    html += '<div class="summary-card"><strong>Heritage reference panel</strong><p>' + esc(pack.reason || 'Local reference panel') + '<br><b>Matched markers:</b> ' + dnaNumber(pack.matched_marker_count) + '<br><b>Panel chromosome scope:</b> ' + esc(pack.chromosomes || meta.chromosomes || 'Not reported') + '<br><b>Source release:</b> ' + esc(version) + '<br>' + esc(pack.truth_boundary || 'Reference affinity is not ancestry proportion or proof of identity.') + '</p></div>';
  }
  if(ancientOrigins){
    const groups = Array.isArray(ancientOrigins.groups) ? ancientOrigins.groups : [];
    const version = ancientOrigins.pack_meta?.source_version || groups[0]?.source_version || 'Exact release not supplied';
    html += '<div class="summary-card"><strong>Ancient population reference coverage</strong><p>' + dnaNumber(ancientOrigins.common_marker_count) + ' common compared markers across ' + dnaNumber(ancientOrigins.chromosome_count) + ' autosomes · ' + groups.length + ' reference groups.<br>' + esc(ancientOrigins.headline || 'Ancient reference comparison') + '<br><b>Source:</b> ' + esc(version) + '<br>' + esc(ancientOrigins.boundary || 'Sampled ancient groups do not establish a personal ancestry percentage.') + '</p></div>';
  } else if(ancient) html += '<details class="source-detail"><summary>Individual ancient sample matching (legacy)</summary><p>' + esc(ancient.installed ? 'Active legacy pack' : 'No active legacy pack reported') + ' · ' + dnaNumber(ancient.matched_marker_count) + ' matched markers.<br>' + esc(ancient.truth_boundary || 'Public sample affinity does not establish descent.') + '</p></details>';
  if(packs.length) html += '<details class="source-detail"><summary>Installed data packs <span class="evidence-label">' + packs.filter(p => p.installed).length + ' active of ' + packs.length + ' listed; installation does not mean every record matched</span></summary><div class="source-table-wrap"><table class="source-table"><thead><tr><th scope="col">Pack</th><th scope="col">Availability</th><th scope="col">Version / update</th></tr></thead><tbody>' + packs.map(p => '<tr><td>' + esc(p.display_name || p.label || p.pack || 'Source pack') + '</td><td>' + esc(p.installed ? 'Installed' : 'Not installed / unavailable') + '</td><td>' + esc(p.pack_version || 'Release not supplied') + '<br>' + esc(p.built_at || p.updated_at || 'Update not supplied') + '</td></tr>').join('') + '</tbody></table></div></details>';
  if(sources.length) html += '<details class="source-detail"><summary>Source version ledger <span class="evidence-label">' + sources.length + ' source entries returned by this report</span></summary><div class="source-table-wrap"><table class="source-table"><thead><tr><th scope="col">Source</th><th scope="col">Version / description as reported</th><th scope="col">Status</th></tr></thead><tbody>' + sources.map(source => '<tr><td>' + (source.source_url ? dnaSafeLink(source.source_url, source.source_name || 'Source') : esc(source.source_name || 'Source')) + '</td><td>' + esc(source.source_version || 'Exact release not supplied') + '</td><td>' + esc(source.status || 'Not reported') + '</td></tr>').join('') + '</tbody></table></div><p>A descriptive source label is not an exact release identifier. Missing release information remains a provenance limitation.</p></details>';
  const cards = Array.isArray(report.evidence_cards) ? report.evidence_cards : (Array.isArray(report.insights?.cards) ? report.insights.cards : []);
  if(cards.length) html += '<h4>Inspect individual evidence cards</h4>' + cards.slice(0,40).map(card => '<details class="source-detail"><summary>' + esc(card.title || card.label || 'Research evidence') + '<span class="evidence-label">' + esc(card.claim_label || card.evidence_level || 'Research support') + ' · confidence: ' + esc(card.confidence_band || 'not reported') + '</span></summary><p><b>Source:</b> ' + esc(card.source_name || card.source || 'Not supplied') + '<br><b>Version:</b> ' + esc(card.source_version || 'Not supplied') + '<br><b>Evidence level:</b> ' + esc(card.evidence_level || 'Not supplied') + '<br><b>Matched markers:</b> ' + dnaNumber(card.matched_marker_count) + '</p><p>' + esc(card.explanation || card.plain || '') + '</p><p><b>Limitations:</b> ' + esc(card.limitations || 'See the full evidence report for this card’s limitations.') + '</p><p><b>Next step:</b> ' + esc(card.next_steps || card.next_step || 'Verify important findings with appropriate primary evidence.') + '</p></details>').join('');
  document.getElementById('dna-source-ledger').innerHTML = html || '<div class="summary-card"><strong>No source version ledger returned for this report type.</strong><p>Use the full report for broader source context. Missing provenance must not be treated as verified evidence.</p></div>';
}
function renderDnaReferenceBars(report, kind, chosenPanel = ''){
  if(kind === 'health' && !report?.chart?.length){
    document.getElementById('dna-reference-bars').hidden = false;
    document.getElementById('dna-chart').hidden = true;
    document.getElementById('chart-legend').hidden = true;
    document.getElementById('chart-title').textContent = 'Health evidence overview';
    document.getElementById('chart-note').textContent = 'No numerical health risk is calculated. A missing or untested variant is not a negative finding.';
    document.getElementById('dna-reference-bars').innerHTML = '<div class="note">Review the health evidence cards and exact match status in the report. Build, strand and allele validation determine which interpretations can be shown.</div>';
    return;
  }
  const heritage = kind === 'heritage' ? report : report?.heritage;
  const pack = heritage?.local_reference_pack || {};
  const estimate = heritage?.insights?.consumer_estimate || heritage?.insights?.plain_ancestry_summary?.consumer_estimate || {};
  const rows = Array.isArray(pack.population_scores) ? pack.population_scores : [];
  const withheld = !!heritage && (estimate.available === false || !Array.isArray(heritage.chart) || !heritage.chart.length);
  const box = document.getElementById('dna-reference-bars');
  box.hidden = !withheld;
  document.getElementById('dna-chart').hidden = withheld;
  document.getElementById('chart-legend').hidden = withheld;
  if(!withheld) return;
  document.getElementById('chart-title').textContent = 'Reference affinity comparison';
  document.getElementById('chart-note').textContent = 'Compare scores within the selected panel. Panels and marker sets are not directly comparable. Scores do not sum to 100 and are not ancestry percentages, probabilities or identity labels.';
  const groups = new Map();
  rows.forEach(row => {const name = row.panel_name || 'Reference panel'; if(!groups.has(name)) groups.set(name, []); groups.get(name).push(row);});
  const names = [...groups.keys()].sort((a,b) => groups.get(b).length - groups.get(a).length);
  const selected = groups.has(chosenPanel) ? chosenPanel : names[0];
  const panelRows = groups.get(selected) || [];
  const panelLabel = name => dnaReferenceCatalog?.panels?.find(panel => panel.panel_name === name)?.display_name || name.replaceAll('_',' ');
  const drawRow = row => {
    const score = Number(row.affinity_score);
    const value = Number.isFinite(score) ? Math.max(0,Math.min(100,score)) : null;
    return '<div class="affinity-row"><div><b>' + esc(row.population_label || row.modern_label || row.population_code || 'Reference group') + '</b><span>' + (value === null ? 'Not reported' : esc(fmtPct(value)) + '/100') + '</span></div>' + (value === null ? '' : '<div class="coverage-meter" aria-hidden="true"><i style="width:' + value + '%"></i></div>') + '<small>' + dnaNumber(row.matched_marker_count) + ' overlapping markers' + (row.matched_chromosome_count == null ? '' : ' across ' + dnaNumber(row.matched_chromosome_count) + ' chromosomes') + ' · ' + esc(row.confidence_band || 'uncertain') + ' confidence</small></div>';
  };
  const selector = names.length > 1 ? '<label for="dna-affinity-panel">Compare within a reference panel</label><select id="dna-affinity-panel" onchange="renderDnaReferenceBars(lastReportData,lastReportKind,this.value)">' + names.map(name => '<option value="' + esc(name) + '"' + (name === selected ? ' selected' : '') + '>' + esc(panelLabel(name)) + '</option>').join('') + '</select>' : '';
  box.innerHTML = '<p class="muted" style="font-size:.88rem">' + esc(estimate.plain_summary || estimate.plain || estimate.boundary || 'Ancestry composition is withheld without a validated admixture model. Observed reference comparisons remain available below.') + '</p>' + selector + (panelRows.length ? panelRows.slice(0,8).map(drawRow).join('') + (panelRows.length > 8 ? '<details class="source-detail"><summary>Show ' + (panelRows.length - 8) + ' more reference comparisons</summary>' + panelRows.slice(8).map(drawRow).join('') + '</details>' : '') : '<div class="note">No population comparisons passed the current evidence gate. See quality and reference coverage before interpreting this file.</div>');
}
let dnaReferenceCatalog = null;
function renderDnaCatalog(catalog){
  dnaReferenceCatalog = catalog || null;
  const target = document.getElementById('reference-catalog-results');
  const search = document.getElementById('reference-search');
  if(!catalog || !Array.isArray(catalog.panels)){
    target.innerHTML = '<p class="muted">The reference catalog is currently unavailable. Completed reports still show the source information returned by their analysis.</p>';
    return;
  }
  const query = search.value.trim().toLowerCase();
  const panels = catalog.panels.map(panel => ({...panel, filtered:(Array.isArray(panel.populations) ? panel.populations : []).filter(pop => [pop.code,pop.label,panel.display_name,panel.panel_name].join(' ').toLowerCase().includes(query))})).filter(panel => !query || panel.filtered.length);
  let html = '<p class="muted" style="font-size:.85rem">Catalog audit: ' + esc(catalog.audited_at || 'Not supplied') + ' · assembly: ' + esc(catalog.assembly || 'Not supplied') + ' · ' + esc((catalog.integrity_status || 'unavailable').replaceAll('_',' ')) + '. Reference groups may overlap across panels; group counts must not be added as independent populations.</p>';
  if(catalog.integrity_status !== 'verified_snapshot') html += '<div class="note">This catalog snapshot is unavailable or does not match the current pack. Rely on a newly generated report for active matching evidence.</div>';
  if(catalog.reference_evaluation) html += '<p class="muted">Expanded population panel: ' + dnaNumber(catalog.reference_evaluation.training_samples) + ' training reference samples, with ' + dnaNumber(catalog.reference_evaluation.heldout_samples) + ' separate public samples used for internal evaluation. These checks do not establish consumer ancestry accuracy or calibrated composition percentages.</p>';
  html += panels.map(panel => '<details class="source-detail"' + (query ? ' open' : '') + '><summary>' + esc(panel.display_name || panel.panel_name || 'Reference panel') + '<span class="evidence-label">' + dnaNumber(panel.population_count) + ' reference groups · ' + dnaNumber(panel.marker_count) + ' markers in this panel</span></summary><p>' + (panel.source_url ? dnaSafeLink(panel.source_url, 'Original source') : 'Source URL not supplied') + ' · ' + esc(panel.source_version || 'Exact release not supplied') + '</p><div class="source-table-wrap"><table class="source-table"><thead><tr><th scope="col">Code</th><th scope="col">Reference group</th><th scope="col">Markers</th></tr></thead><tbody>' + panel.filtered.map(pop => '<tr><td>' + esc(pop.code || '') + '</td><td>' + esc(pop.label || 'Reference group') + '</td><td>' + dnaNumber(pop.marker_count) + '</td></tr>').join('') + '</tbody></table></div></details>').join('');
  if(!panels.length) html += '<p class="muted">No reference groups match this search. Try a population code or broader region.</p>';
  if(Array.isArray(catalog.limitations) && catalog.limitations.length) html += '<p class="muted" style="font-size:.85rem">' + catalog.limitations.map(esc).join('<br>') + '</p>';
  target.innerHTML = html;
}

applyDnaMotion();
dnaReducedMotion.addEventListener("change", () => {dnaMotionPaused = dnaReducedMotion.matches; applyDnaMotion();});
updateDnaFileStatus(null);
initDnaMapModal();
refreshStatus();
</script>
<script src="/assets/dna-discovery/discovery.js?v=20260905-1"></script>
<script src="/assets/dna-ancestry-story/ancestry-story.js?v=20260905-1"></script>
<script src="/assets/dna-customer/customer.js?v=20260905-2"></script>
<script src="/assets/dna-country/country.js?v=20260905-2"></script>
</body>
</html>
