<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sovereign Access | ZeroThink</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #000; color: #fff; font-family: 'Space Grotesk', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; overflow: hidden; }
        
        /* AUTH BOX CONTAINER */
        .auth-box { width: 100%; max-width: 400px; padding: 60px 40px; background: rgba(5,5,5,0.9); border: 1px solid #1a1a1a; border-radius: 24px; text-align: center; box-shadow: 0 0 50px rgba(0,0,0,0.8); position: relative; z-index: 10; backdrop-filter: blur(10px); }
        
        /* LOGO ANIMATION */
        .auth-logo { height: 60px; margin: 0 auto 30px auto; filter: drop-shadow(0 0 10px rgba(168, 85, 247, 0.5)); animation: float 3s ease-in-out infinite; }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-5px); } 100% { transform: translateY(0px); } }
        
        /* GOOGLE BUTTON (PREMIUM) */
        .google-btn { display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; padding: 16px; background: #fff; color: #000; border-radius: 12px; font-weight: 800; text-decoration: none; margin-bottom: 20px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid #fff; text-transform: uppercase; letter-spacing: 1px; font-size: 0.9rem; }
        .google-btn:hover { background: #000; color: #fff; border-color: #a855f7; box-shadow: 0 0 20px rgba(168, 85, 247, 0.3); }
        
        /* BACKGROUND MATRIX EFFECT (OPTIONAL FLOURISH) */
        .grid-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-image: radial-gradient(#222 1px, transparent 1px); background-size: 40px 40px; opacity: 0.1; z-index: 1; pointer-events: none; }
    </style>
</head>
<body>
    <div class="grid-bg"></div>
    
    <div class="auth-box">
        <img src="/assets/img/logo_nobg.png" class="auth-logo">
        <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 10px; letter-spacing: -1px; color:#fff;">ZERO ACCESS</h2>
        <p style="color: #666; font-size: 0.8rem; margin-bottom: 40px; text-transform: uppercase; letter-spacing: 2px;">Identity Verification</p>
        
        <a href="/auth/google" class="google-btn">
            <img src="https://www.google.com/favicon.ico" style="height:20px;"> 
            Initialize Session
        </a>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #1a1a1a;">
            <p style="color: #444; font-size: 0.7rem; font-family: monospace;">SECURE GATEWAY v9.0</p>
        </div>
    </div>
</body>
</html>
