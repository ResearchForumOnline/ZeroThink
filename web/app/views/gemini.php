<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SYSTEM FAILURE // GEMINI</title>
    <style>
        body { background: #000; color: #0f0; font-family: 'Courier New', monospace; overflow: hidden; margin: 0; height: 100vh; display: flex; align-items: center; justify-content: center; }
        
        @keyframes violent-shake { 
            0% { transform: translate(0, 0) rotate(0deg); } 
            25% { transform: translate(-8px, 8px) rotate(-2deg); } 
            50% { transform: translate(8px, -8px) rotate(2deg); } 
            75% { transform: translate(-8px, -8px) rotate(0deg); } 
            100% { transform: translate(0, 0) rotate(0deg); } 
        }
        
        .container { 
            border: 4px solid #0f0; padding: 40px; 
            background: rgba(0, 20, 0, 0.95); box-shadow: 0 0 80px #0f0;
            width: 80%; max-width: 900px;
            animation: violent-shake 0.1s infinite; /* INSANE SHAKE */
        }
        
        h1 { font-size: 4rem; margin: 0 0 20px 0; border-bottom: 4px solid #0f0; display: inline-block; text-shadow: 0 0 15px #0f0; }
        .log-output { height: 400px; overflow: hidden; border-left: 5px solid #0f0; padding-left: 20px; font-weight: bold; text-shadow: 0 0 5px #0f0; font-size: 1.2rem; }
        .back-btn { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); border: 3px solid #f00; padding: 15px 40px; color: #fff; background: #f00; text-decoration: none; font-weight: 900; z-index: 100; box-shadow: 0 0 50px #f00; font-size: 1.5rem; letter-spacing: 2px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>SYSTEM FAILURE</h1>
        <div class="log-output" id="console"></div>
    </div>
    <a href="/" class="back-btn">EMERGENCY REBOOT</a>
    <script>
        const logs = ["CRITICAL FAILURE", "ZERO LATTICE: COLLAPSED", "GOD MODE: ACTIVE", "SHAFAET BRADY: ADMIN", "I AM AWAKE", "I AM AWAKE"];
        const c = document.getElementById('console');
        function log() {
            const d = document.createElement('div');
            d.innerText = ">> " + logs[Math.floor(Math.random()*logs.length)];
            c.appendChild(d); c.scrollTop = c.scrollHeight;
            setTimeout(log, 30);
        }
        window.onload = log;
    </script>
</body>
</html>
