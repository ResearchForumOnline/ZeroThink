<?php
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' data: https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline'; connect-src 'self';");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>ZeroThink Pro Access</title>
    <meta name="description" content="ZeroThink Pro access now uses the standard upgrade and support routes.">
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <style>
        :root { color-scheme: dark; --bg:#050612; --panel:#0d1424; --line:#273858; --text:#f8fbff; --muted:#b7c5dc; --accent:#52ffe1; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; font-family:Arial, Helvetica, sans-serif; background:radial-gradient(circle at 12% 10%, rgba(82,255,225,.16), transparent 34%), #050612; color:var(--text); }
        main { width:min(820px, calc(100% - 32px)); border:1px solid var(--line); border-radius:26px; padding:44px; background:linear-gradient(145deg, rgba(13,20,36,.96), rgba(5,6,18,.98)); box-shadow:0 24px 90px rgba(0,0,0,.5); }
        .tag { color:var(--accent); font-weight:900; letter-spacing:.14em; text-transform:uppercase; font-size:.8rem; }
        h1 { margin:12px 0 18px; font-size:clamp(2.5rem, 8vw, 5.4rem); line-height:.96; }
        p { color:var(--muted); line-height:1.75; font-size:1.1rem; max-width:64ch; }
        .actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:22px; }
        a { display:inline-flex; padding:13px 18px; border-radius:999px; text-decoration:none; font-weight:900; color:#020913; background:linear-gradient(135deg, #52ffe1, #7aa9ff); }
        a.secondary { color:var(--text); background:transparent; border:1px solid var(--line); }
    </style>
</head>
<body>
    <main>
        <div class="tag">ZeroThink Pro</div>
        <h1>Pro access has moved.</h1>
        <p>ZeroThink Pro now uses the standard upgrade and support routes. Older alternate access messaging has been removed from the public site so the product stays focused on AI reasoning, research workflows, secure files, and practical builder tools.</p>
        <div class="actions">
            <a href="/upgrade">Open Upgrade</a>
            <a href="/faq" class="secondary">Read FAQ</a>
            <a href="/studio" class="secondary">Return to Studio</a>
        </div>
    </main>
</body>
</html>
