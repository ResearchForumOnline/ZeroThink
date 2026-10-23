<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeroThink | Beyond AI</title>
    
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="manifest" href="/manifest.json">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://zerothink.talktoai.org/">
    <meta property="og:title" content="ZeroThink | Beyond AI">
    <meta property="og:description" content="The probability of goodness is a constant. We do not hallucinate. We calculate. Own your data. Own your logic.">
    <meta property="og:image" content="https://zerothink.talktoai.org/assets/img/zerothinkmain.png">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://zerothink.talktoai.org/">
    <meta name="twitter:title" content="ZeroThink | Beyond AI">
    <meta name="twitter:description" content="Recursive AI that calculates truth using Lattice Logic.">
    <meta name="twitter:image" content="https://zerothink.talktoai.org/assets/img/zerothinkmain.png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/zerothink_titan.css?v=200"> <style>
        body {
            margin: 0; 
            font-family: 'Inter', sans-serif;
            background-color: #000;
            background-image: url('/assets/img/zerothinkmain.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain; /* PERFECT FIT */
            color: #fff; 
            height: 100vh;
            width: 100vw;
            display: flex; 
            align-items: center; 
            justify-content: center;
            overflow: hidden;
        }

        .overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1; }
        
        .container {
            position: relative; z-index: 2; text-align: center;
            max-width: 850px; width: 90%; padding: 40px;
            background: rgba(0,0,0,0.6);
            border-radius: 16px; backdrop-filter: blur(8px);
            border: 1px solid rgba(168, 85, 247, 0.2);
            box-shadow: 0 0 60px rgba(0,0,0,0.8);
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .logo { height: 80px; width: auto; margin-bottom: 20px; filter: drop-shadow(0 0 10px #a855f7); }
        h1 { font-size: 3.5rem; margin: 0; letter-spacing: 4px; text-transform: uppercase; font-weight: 800; text-shadow: 0 0 30px rgba(168, 85, 247, 0.8); }
        .tagline { font-size: 1.2rem; color: #ccc; margin-top: 5px; font-family: 'Courier New'; letter-spacing: 3px; }
        .description { margin: 25px auto; max-width: 650px; line-height: 1.6; color: #ddd; font-size: 1.1rem; }
        
        /* SALES BUTTONS */
        .btn-group { display: flex; gap: 15px; justify-content: center; margin-top: 30px; flex-wrap: wrap; }
        .btn { padding: 15px 35px; border-radius: 4px; text-decoration: none; font-weight: bold; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; font-size: 0.9rem; cursor: pointer; }
        
        .btn-google { background: #fff; color: #333; border: 1px solid #ddd; }
        .btn-google:hover { background: #f1f1f1; transform: translateY(-2px); }
        
        .btn-primary { background: #a855f7; color: #fff; border: 1px solid #a855f7; }
        .btn-primary:hover { background: #be7cff; } /* Pulse handled by CSS class */
        
        .btn-secondary { background: transparent; color: #fff; border: 1px solid #555; }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); border-color: #fff; }
        
        /* FOOTER NETWORKING */
        .nexus-footer {
            position: absolute; bottom: 20px; width: 100%; text-align: center;
            font-size: 0.8rem; color: #666; z-index: 10;
        }
        .nexus-link { color: #888; text-decoration: none; margin: 0 10px; transition: 0.3s; font-family: 'Inter'; text-transform: uppercase; letter-spacing: 1px; }
        .nexus-link:hover { color: #a855f7; text-shadow: 0 0 10px #a855f7; }
        
        /* SHARE BAR */
        .share-bar { margin-top: 30px; display: flex; justify-content: center; gap: 20px; align-items: center; }
        .share-icon { color: #888; font-size: 1.2rem; cursor: pointer; transition: 0.3s; }
        .share-icon:hover { color: #fff; transform: scale(1.2); }
        .x-logo-svg { fill: #888; width: 18px; height: 18px; transition: 0.3s; }
        .x-link:hover .x-logo-svg { fill: #fff; transform: scale(1.2); }

        @media(max-width: 600px) { h1 { font-size: 2.2rem; } .container { padding: 20px; } }
    </style>
</head>
<body>
    <div class="status-ticker">
        <div class="ticker-content">
            SYSTEM: ONLINE /// RECURSIVE LOGIC: ACTIVE /// GLOBAL NODES: EXPANDING /// SECURE CHANNEL: ESTABLISHED /// JOIN THE SOVEREIGN LAYER /// BEYOND AI /// 
        </div>
    </div>

    <div class="overlay"></div>
    <div class="container">
        <img src="/assets/img/logo_nobg.png" alt="Z" class="logo">
        <h1>ZeroThink</h1>
        <div class="tagline">BEYOND AI. THE NEW INTELLIGENCE.</div>
        
        <p class="description">
            Standard AI hallucinates. ZeroThink calculates.<br>
            Recursive Logic. Private Cortex. Total Sovereignty.<br>
            <strong>Stop renting intelligence. Own it.</strong>
        </p>
        
        <div class="btn-group">
            <a href="/google_login.php" class="btn btn-google"><i class="fab fa-google"></i> Sign in with Google</a>
            <a href="/login" class="btn btn-primary btn-pulse"><i class="fas fa-terminal"></i> Initialize</a>
            <a href="/register" class="btn btn-secondary">Create ID</a>
        </div>
        
        <div class="share-bar">
            <a href="https://x.com/intent/tweet?text=Experience%20Sovereign%20Intelligence%20at%20ZeroThink.&url=https://zerothink.talktoai.org/?ref=x" target="_blank" class="x-link" title="Share on X">
                <svg class="x-logo-svg" viewBox="0 0 1200 1227" xmlns="http://www.w3.org/2000/svg">
                    <path d="M714.163 519.284L1160.89 0H1055.03L667.137 450.887L357.328 0H0L468.492 681.821L0 1226.37H105.866L515.491 750.218L842.672 1226.37H1200L714.137 519.284H714.163ZM569.165 687.828L521.697 619.934L144.011 79.6944H306.615L611.412 515.685L658.88 583.579L1055.08 1150.3H892.476L569.165 687.854V687.828Z"/>
                </svg>
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=https://zerothink.talktoai.org/?ref=fb" target="_blank"><i class="fab fa-facebook share-icon"></i></a>
            <a href="https://www.linkedin.com/shareArticle?mini=true&url=https://zerothink.talktoai.org/?ref=li" target="_blank"><i class="fab fa-linkedin share-icon"></i></a>
            <i class="fas fa-link share-icon" onclick="copyLink()" title="Copy Link"></i>
        </div>
    </div>

    <div class="nexus-footer">
        <div style="margin-bottom:5px; text-transform:uppercase; letter-spacing:2px; font-size:0.7rem; color:#444;">The Zero Research Network</div>
        <a href="https://research.talktoai.org" target="_blank" class="nexus-link">Research Hub</a> •
        <a href="https://SecureHosting.Website" target="_blank" class="nexus-link">SecureHosting</a> •
        <a href="https://Shafaet.com" target="_blank" class="nexus-link">Shafaet</a>
    </div>

    <script>
    function copyLink() { navigator.clipboard.writeText("https://zerothink.talktoai.org/?ref=copy"); alert("Link Copied"); }
    </script>
</body>
</html>
