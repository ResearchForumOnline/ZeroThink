<?php require_once __DIR__ . '/app/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docs | ZeroThink</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg:#050607; --panel:#101319; --line:#26303a; --text:#f1f5f8; --muted:#a5b0ba; --accent:#52d6b2; --blue:#7bb7ff; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif; line-height:1.65; }
        .shell { max-width:1180px; margin:0 auto; padding:34px 24px 70px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:18px; margin-bottom:32px; }
        .top a { color:var(--text); text-decoration:none; border:1px solid var(--line); border-radius:8px; padding:10px 14px; background:#0a0c10; }
        .hero { border:1px solid var(--line); background:linear-gradient(135deg, rgba(82,214,178,.11), rgba(123,183,255,.07)); border-radius:8px; padding:42px; }
        .eyebrow { color:var(--accent); text-transform:uppercase; letter-spacing:.14em; font-size:.78rem; font-weight:800; }
        h1 { margin:12px 0 18px; font-size:clamp(2.2rem,4vw,3.8rem); line-height:1.05; letter-spacing:0; }
        h2 { margin:0 0 14px; font-size:1.45rem; }
        h3 { margin:0 0 8px; font-size:1.05rem; }
        p { color:var(--muted); margin:0 0 18px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:18px; margin-top:22px; }
        .card, section { border:1px solid var(--line); background:var(--panel); border-radius:8px; padding:22px; }
        section { margin-top:22px; }
        a { color:var(--accent); }
        code { background:#080b0f; border:1px solid var(--line); border-radius:6px; padding:2px 6px; color:#eafdf8; }
        ul { margin:0; padding-left:20px; color:var(--muted); }
        li { margin:8px 0; }
    </style>
</head>
<body>
    <main class="shell">
        <div class="top">
            <a href="/"><img src="/assets/img/logo_nobg.png" alt="" style="width:26px;vertical-align:middle;margin-right:8px;"> ZeroThink</a>
            <a href="/studio"><i class="fas fa-microchip"></i> Studio</a>
        </div>

        <article class="hero">
            <div class="eyebrow">Documentation hub</div>
            <h1>ZeroThink Docs</h1>
            <p>Practical reference for the ZeroThink research workflow layer, Serper web search, CLI usage, Nottingham-facing research workflows, and custom process templates.</p>
        </article>

        <section>
            <h2>Research Workflows</h2>
            <div class="grid">
                <div class="card"><h3>Nottingham University Research Workflow</h3><p>Survey extension, topic-expert identification, generative UI, provenance tracking, and human validation.</p><a href="/nottingham-research">Open workflow</a></div>
                <div class="card"><h3>Research Paper Creator</h3><p>Evidence-first drafting with source ledgers, claim maps, critique passes, and approval before synthesis.</p><a href="/research-paper-creator">Open paper creator</a></div>
                <div class="card"><h3>Custom Research Processes</h3><p>Visible scaffolds for stages, required sources, validation checks, provenance requirements, output format, and export.</p><a href="/docs/custom-research-processes">Open custom templates</a></div>
                <div class="card"><h3>QuantumZero</h3><p>Quantum-informed research agent with Groq reasoning, IonQ telemetry or simulator evidence, and clear truth boundaries.</p><a href="/quantumzero">Open QuantumZero</a></div>
                <div class="card"><h3>ZMath Agent</h3><p>Zero Boundary Algebra tools for reset logic, file analysis, mirror checks, state traces, and ZME1 encrypted files.</p><a href="/zmath">Open ZMath</a></div>
                <div class="card"><h3>Direct Mode</h3><p>No Google login lane for local browser-stored Groq or NVIDIA keys and direct model testing.</p><a href="/guest">Open Direct Mode</a></div>
            </div>
        </section>

        <section id="quantum">
            <h2>QuantumZero Routes</h2>
            <div class="grid">
                <div class="card"><h3>QuantumZero Chat</h3><p>Dedicated quantum-informed chat with evidence, interpretation, limits, and next-step sections.</p><a href="/quantumzero">Open chat</a></div>
                <div class="card"><h3>Quantum Lab</h3><p>Mission-style lab interface for quantum evidence packets and IonQ-backed experiments.</p><a href="/quantumlab">Open lab</a></div>
                <div class="card"><h3>Python Agent</h3><p>Standalone companion for local setup, IonQ status checks, mission runs, and optional voice output.</p><a href="/quantumzero-python/">Open Python agent</a></div>
            </div>
        </section>

        <section>
            <h2>Serper + CLI</h2>
            <p>ZeroThink CLI supports web search through Serper. Search output is normalized into title, URL, snippet, source, and timestamp if available, then stored in the private server-side evidence ledger for audit review.</p>
            <div class="grid">
                <div class="card"><h3>CLI</h3><p><code>zerothink search "generative UI topic expert literature review"</code></p><a href="/cli">Open CLI docs</a></div>
                <div class="card"><h3>Serper Search</h3><p>Add <code>SERPER_API_KEY=your_key_here</code> to <code>.env</code> or use the Studio Vault.</p><a href="/docs/serper-search">Open Serper docs</a></div>
            </div>
        </section>

        <section>
            <h2>Collaboration-Facing Positioning</h2>
            <ul>
                <li>Immediate focus: University of Nottingham research collaboration direction.</li>
                <li>Wider compatibility: Melbourne, Singapore Management University, Chicago, and MOD-related research conversations.</li>
                <li>AI co-scientist is positioned as a long-term goal after stronger search, retrieval, evidence mapping, provenance, and validation.</li>
                <li>Collaboration wording remains careful and does not claim official institutional endorsement.</li>
            </ul>
        </section>
    </main>
</body>
</html>
