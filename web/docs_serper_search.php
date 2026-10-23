<?php require_once __DIR__ . '/app/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serper Search Docs | ZeroThink</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <style>
        body { margin:0; background:#050607; color:#eef5f6; font-family:Inter,system-ui,sans-serif; line-height:1.7; }
        main { max-width:980px; margin:0 auto; padding:42px 24px 72px; }
        a { color:#52d6b2; }
        h1 { font-size:clamp(2rem,4vw,3.4rem); line-height:1.08; margin:14px 0; letter-spacing:0; }
        h2 { margin-top:36px; color:#7bb7ff; }
        p, li { color:#a8b3bd; }
        code, pre { background:#0e1217; border:1px solid #26313a; border-radius:8px; color:#eafdf8; }
        code { padding:2px 6px; }
        pre { padding:18px; overflow:auto; }
        .panel { border:1px solid #26313a; border-radius:8px; background:#0d1117; padding:22px; margin-top:20px; }
        .back { display:inline-block; margin-bottom:18px; text-decoration:none; border:1px solid #26313a; border-radius:8px; padding:9px 12px; color:#eef5f6; }
    </style>
</head>
<body>
    <main>
        <a href="/docs" class="back">Back to Docs</a>
        <p style="text-transform:uppercase; letter-spacing:.14em; color:#52d6b2; font-weight:800;">Web search and provenance</p>
        <h1>Serper Search for ZeroThink</h1>
        <p>ZeroThink uses Serper as a web-search route for literature discovery, topic-expert identification, and staged research plans. Search results are normalized into title, URL, snippet, source, and timestamp fields where available, then stored in the provenance evidence ledger.</p>

        <section class="panel">
            <h2>Enable Serper</h2>
            <p>Add a key to the ZeroThink root <code>.env</code> file:</p>
            <pre>SERPER_API_KEY=your_key_here</pre>
            <p>Studio users can also paste a key into the Vault under <strong>Serper Web Key</strong>. If the key is missing, ZeroThink shows a clear setup message rather than crashing.</p>
        </section>

        <section class="panel">
            <h2>CLI Examples</h2>
            <pre>zerothink search "generative UI topic expert literature review"
zerothink search the web for "generative UI topic experts"
zerothink do a web search for "AI co-scientist literature"
zerothink research --topic "AI co-scientist literature discovery" --web serper
zerothink research "AI co-scientist literature discovery" with web search
zerothink nottingham --topic "survey extension generative UI" --serper</pre>
            <p>On this server checkout, the Windows wrapper lives at <code>tools\zerothink.cmd</code> and the PHP command lives at <code>tools/zerothink.php</code>.</p>
        </section>

        <section class="panel">
            <h2>Evidence Ledger</h2>
            <p>Serper output is written to the private server-side evidence ledger. Each record includes the query, workflow context, plan stage, collection timestamp, and result entries.</p>
        </section>

        <section class="panel">
            <h2>Research Plan Use</h2>
            <p>Inside staged research plans, Serper should be used before synthesis. The recommended order is search, retrieve papers, process full text where possible, map evidence, validate with a human, then draft.</p>
        </section>
    </main>
</body>
</html>
