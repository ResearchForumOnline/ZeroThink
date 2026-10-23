<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cortex | ZeroThink</title>
    <link rel="stylesheet" href="/assets/css/zerothink_titan.css?v=6000">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <canvas id="matrix-canvas"></canvas>
    <div class="app-container">
        <div class="sidebar">
            <div class="brand"><img src="/assets/img/logo_nobg.png"> ZeroThink</div>
            <nav>
                <a href="/studio"><i class="fas fa-terminal"></i> Studio</a>
                <a href="/cortex" style="color:#fff; background:rgba(168,85,247,0.1);"><i class="fas fa-brain"></i> Cortex</a>
                <a href="/faq"><i class="fas fa-book-open"></i> Guide</a>
                <a href="/logout" style="margin-top:20px; color:#f55;"><i class="fas fa-power-off"></i> LOGOUT</a>
            </nav>
        </div>
        <div class="main-content" style="padding:40px;">
            <div class="input-container" style="max-width:800px; margin:0 auto; height:80vh; display:flex; flex-direction:column;">
                <h2 style="color:#fff; margin-top:0; font-family:'JetBrains Mono';">CORTEX INGESTION</h2>
                <p style="color:#888; font-size:0.9rem;">Paste datasets here to expand ZeroThink's long-term memory.</p>
                <textarea id="cortex-data" placeholder="Paste knowledge base here..." style="flex-grow:1; background:#111; border:1px solid #333; padding:15px; color:#0f0; font-family:monospace; margin-bottom:15px; resize:none;"></textarea>
                <button class="btn-zero" onclick="ingestData()" style="width:100%; justify-content:center;">INGEST DATASET</button>
            </div>
        </div>
    </div>
    <script>
        // Matrix BG
        const c=document.getElementById('matrix-canvas'),x=c.getContext('2d');
        c.width=window.innerWidth;c.height=window.innerHeight;
        const s=14,cl=c.width/s,d=Array(Math.floor(cl)).fill(1);
        setInterval(()=>{
            x.fillStyle='rgba(0,0,0,0.05)';x.fillRect(0,0,c.width,c.height);
            x.fillStyle='#00f';x.font=s+'px monospace';
            d.forEach((y,i)=>{x.fillText('0-0+0'[Math.floor(Math.random()*5)],i*s,y*s);if(y*s>c.height&&Math.random()>.975)d[i]=0;d[i]++});
        },50);

        async function ingestData() {
            const txt = document.getElementById('cortex-data').value;
            if(!txt) return alert("Cortex empty.");
            const btn = document.querySelector('.btn-zero');
            btn.innerHTML = "PROCESSING...";
            await fetch('/api_cortex.php', { method:'POST', body:JSON.stringify({data:txt}) });
            btn.innerHTML = "INGEST DATASET";
            alert("Neural Pathways Updated.");
            document.getElementById('cortex-data').value = "";
        }
    </script>
</body>
</html>
