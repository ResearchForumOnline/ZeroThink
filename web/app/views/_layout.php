<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ZeroThink | Sovereign Intelligence</title>
    <link rel="icon" href="/assets/img/logo_nobg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;700&display=swap" rel="stylesheet">
    <style>
        body { background: #050505; color: #e0e0e0; font-family: 'Space Grotesk', sans-serif; margin: 0; }
        .nav { padding: 15px 40px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.8); backdrop-filter: blur(15px); position: sticky; top: 0; z-index: 1000; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.1rem; color: #fff; text-decoration: none; }
        .brand img { height: 28px; width: auto; }
        .links a { margin-left: 20px; font-size: 0.8rem; color: #666; text-decoration: none; font-weight: 600; text-transform: uppercase; transition: 0.3s; }
        .links a:hover { color: #00ff00; }
        .btn-up { background: #00ff00; color: #000 !important; padding: 6px 15px; border-radius: 4px; }
        .footer { text-align: center; padding: 40px; font-size: 0.7rem; color: #222; border-top: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body>
    <nav class="nav">
        <a href="/" class="brand"><img src="/assets/img/logo_nobg.png"> ZeroThink</a>
        <div class="links"><a href="/studio">Studio</a><a href="/quantumzero">QuantumZero</a><a href="/zmath">ZMath</a><a href="/nottingham-research">Research</a><a href="/docs">Docs</a><a href="/guest">Direct</a><a href="/faq">FAQ</a><a href="/login">Login</a><a href="/upgrade" class="btn-up">Upgrade</a></div>
    </nav>
    <div class="main-content"><?php if(isset($view) && file_exists($view)) include $view; ?></div>
    <footer class="footer">
        <div style="margin-bottom: 15px; display: flex; justify-content: center; gap: 20px;">
            <a href="https://x.com/talktoai" target="_blank" style="color:#444">X.COM</a>
            <a href="https://www.youtube.com/@quantumzero101" target="_blank" style="color:#444">YOUTUBE</a>
        </div>
        &copy; 2026 UNIVERSITY OF ZERO · SHAF BRADY · SOVEREIGN LATTICE LOGIC
    </footer>
</body>
</html>
