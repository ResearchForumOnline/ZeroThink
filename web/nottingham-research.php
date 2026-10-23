<?php
require_once __DIR__ . '/app/bootstrap.php';
$back_link = isset($_SESSION['user_id']) ? '/studio' : '/';
$back_text = isset($_SESSION['user_id']) ? 'Return to Studio' : 'Return to Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Literature Research Collaboration Workspace | ZeroThink</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg:#050607; --panel:#0f1216; --line:#26303a; --text:#edf3f7; --muted:#9aa8b5; --accent:#52d6b2; --blue:#7bb7ff; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif; line-height:1.65; }
        .shell { max-width:1180px; margin:0 auto; padding:34px 24px 70px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:18px; margin-bottom:36px; }
        .top a { color:var(--text); text-decoration:none; border:1px solid var(--line); border-radius:8px; padding:10px 14px; background:#0a0c10; }
        .brand { display:flex; align-items:center; gap:10px; color:var(--muted); font-size:.85rem; text-transform:uppercase; letter-spacing:.12em; }
        .brand img { width:30px; height:30px; }
        .hero { border:1px solid var(--line); background:linear-gradient(135deg, rgba(82,214,178,.11), rgba(123,183,255,.07)); border-radius:8px; padding:46px; }
        .eyebrow { color:var(--accent); text-transform:uppercase; letter-spacing:.14em; font-size:.78rem; font-weight:800; }
        h1 { margin:12px 0 18px; font-size:clamp(2.2rem, 4vw, 4rem); line-height:1.05; letter-spacing:0; }
        h2 { margin:0 0 14px; font-size:1.45rem; letter-spacing:0; }
        h3 { margin:0 0 8px; font-size:1.05rem; }
        p { color:var(--muted); margin:0 0 18px; }
        .lead { max-width:880px; color:#d7e5ee; font-size:1.12rem; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:18px; margin-top:28px; }
        .card, section { border:1px solid var(--line); background:var(--panel); border-radius:8px; padding:24px; }
        section { margin-top:22px; }
        ul { margin:0; padding-left:20px; color:var(--muted); }
        li { margin:8px 0; }
        .pill-row { display:flex; flex-wrap:wrap; gap:10px; margin-top:22px; }
        .pill { border:1px solid rgba(82,214,178,.35); color:#d8fff4; background:rgba(82,214,178,.08); border-radius:999px; padding:7px 11px; font-size:.86rem; }
        .flow { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:12px; counter-reset:step; }
        .flow div { border:1px solid var(--line); border-radius:8px; padding:16px; background:#090b0f; color:var(--muted); }
        .flow div:before { counter-increment:step; content:counter(step, decimal-leading-zero); display:block; color:var(--accent); font-weight:800; margin-bottom:8px; }
        .actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:24px; }
        .actions a { color:#06100d; background:var(--accent); text-decoration:none; font-weight:800; border-radius:8px; padding:12px 16px; }
        .actions a.secondary { color:var(--text); background:#111720; border:1px solid var(--line); }
        @media (max-width:720px) { .hero { padding:28px; } .top { align-items:flex-start; flex-direction:column; } }
    </style>
</head>
<body>
    <main class="shell">
        <div class="top">
            <div class="brand"><img src="/assets/img/logo_nobg.png" alt=""> ZeroThink Research</div>
            <a href="<?php echo htmlspecialchars($back_link); ?>"><i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($back_text); ?></a>
        </div>

        <article class="hero">
            <div class="eyebrow">Academic literature research workflow</div>
            <h1>Literature Research Collaboration Workspace</h1>
            <p class="lead">ZeroThink supports literature discovery, survey extension, topic-expert identification, provenance tracking, evidence mapping, and researcher-controlled synthesis.</p>
            <p class="lead">A separate literature-research plugin or repository can support team research activity. It is not presented here as an embedded ZeroThink agent capability, a project invitation, an institutional partnership, or an endorsement unless a formal integration is completed and verified.</p>
            <div class="pill-row">
                <span class="pill">Survey Extension</span>
                <span class="pill">Claim/Evidence Graph</span>
                <span class="pill">Quantum/Simulator Test Lane</span>
                <span class="pill">Topic Expert Identification</span>
                <span class="pill">Generative UI</span>
                <span class="pill">Researcher-in-the-loop validation</span>
            </div>
            <div class="actions">
                <a href="/research-paper-creator">Open Research Paper Creator</a>
                <a href="/docs/custom-research-processes" class="secondary">Custom Research Processes</a>
                <a href="/docs/serper-search" class="secondary">Serper Search Docs</a>
            </div>
        </article>

        <section>
            <h2>Integration Status</h2>
            <div class="grid">
                <div class="card">
                    <h3>Literature Plugin / Repository</h3>
                    <p>A separate team research tool for literature work. Treat its access, invitations, and outputs as external until an integration is documented.</p>
                </div>
                <div class="card">
                    <h3>ZeroThink Paper Creator</h3>
                    <p>A live staged workspace for search protocols, evidence ledgers, claim/evidence/provenance graphs, constrained optimisation test plans, outlines, drafts, critique, revisions, and local project recovery.</p>
                </div>
                <div class="card">
                    <h3>Agent Integration</h3>
                    <p>Not assumed. Agent access should only be stated after authentication, data flow, permissions, provenance, and failure handling are implemented and tested.</p>
                </div>
            </div>
        </section>

        <section>
            <h2>Current Research Direction</h2>
            <div class="grid">
                <div class="card">
                    <h3>Survey Extension</h3>
                    <p>Extend existing surveys with newly published papers while reusing the original review structure where possible.</p>
                </div>
                <div class="card">
                    <h3>Topic Expert Identification</h3>
                    <p>Identify authors, labs, reviewers, and potential supervisors from literature evidence without overclaiming formal status.</p>
                </div>
                <div class="card">
                    <h3>Generative UI</h3>
                    <p>Generate adaptive research interfaces from evidence structures so students and researchers can inspect, steer, and validate work.</p>
                </div>
                <div class="card">
                    <h3>Evidence Graph + Optimisation</h3>
                    <p>Build a claim/evidence/provenance graph, then test constrained optimisation tasks such as evidence-path selection, clustering, routing, prioritisation, and auditability against classical baselines and quantum or simulator candidates.</p>
                </div>
            </div>
        </section>

        <section>
            <h2>Student Route: Survey Expansion In Paper Creator</h2>
            <div class="flow">
                <div>Collect two to five seed articles, abstracts, BibTeX records, DOI links, or paper notes.</div>
                <div>Open Research Paper Creator and choose "Survey expansion with provenance evidence graph".</div>
                <div>Attach the seed files or paste the article details into Known Sources, Constraints, Or Notes.</div>
                <div>Run Search Protocol, then Evidence Plan, then Evidence Graph before drafting.</div>
                <div>Use Source Ledger to mark uploaded sources, retrieved search results, candidate leads, and gaps.</div>
                <div>Run Optimisation Test only as a PoC plan: evidence-path selection, clustering, routing, prioritisation, and auditability.</div>
                <div>Generate the outline and draft only after the student has checked the ledger and approved the evidence map.</div>
                <div>Export Markdown or PDF and disclose AI assistance according to course or venue rules.</div>
            </div>
        </section>

        <section>
            <h2>Researcher-Controlled Workflow</h2>
            <div class="flow">
                <div>Topic intake and research question refinement.</div>
                <div>Search, retrieve papers, and process full text where available.</div>
                <div>Map claims, sources, gaps, contradictions, and expert/topic links.</div>
                <div>Expose provenance so every synthesis step can be inspected.</div>
                <div>Let humans steer, correct, validate, and approve before final writing.</div>
                <div>Build foundations for future AI co-scientist workflows after the evidence layer is stronger.</div>
            </div>
        </section>

        <section>
            <h2>Shared Technical Foundations</h2>
            <ul>
                <li>Search agents and Serper-backed web discovery.</li>
                <li>Paper retrieval, metadata capture, and full-text processing where possible.</li>
                <li>Evidence mapping, claim ledgers, and literature landscape analysis.</li>
                <li>Provenance tracking from source search to final synthesis.</li>
                <li>Claim/evidence/provenance graph creation for survey expansion and audit review.</li>
                <li>Classical baseline plus quantum or simulator candidate tests for constrained optimisation tasks, not document understanding.</li>
                <li>Human-agent validation gates before outputs become drafts or recommendations.</li>
            </ul>
        </section>

        <section>
            <h2>Suggested Workstreams</h2>
            <div class="grid">
                <div class="card">
                    <h3>Team A: Survey Extension</h3>
                    <p>Focus on existing review structures, new-paper search, section mapping, gap detection, source-ledger validation, and claim/evidence graph checks.</p>
                </div>
                <div class="card">
                    <h3>Team B: Topic Experts + Generative UI</h3>
                    <p>Focus on topic-expert identification, supervisor finder, reviewer finder, and adaptive interfaces from evidence maps.</p>
                </div>
                <div class="card">
                    <h3>Shared Infrastructure</h3>
                    <p>Search, retrieval, full-text processing, provenance, GitHub version control, possible Anytype structures, Miro planning, and auditable graph exports.</p>
                </div>
                <div class="card">
                    <h3>Quantum/Simulator PoC</h3>
                    <p>Keep this optional and measured. Test optimisation candidates on non-sensitive graphs, compare against classical baselines, and clearly state that no university, funder, government body, or quantum provider endorsement is implied.</p>
                </div>
            </div>
        </section>

        <section>
            <h2>Collaboration Boundary</h2>
            <p>This workspace can be used in academic discussions and team projects, but the interface does not claim that any university, laboratory, government body, researcher, repository, or plugin owner has approved, adopted, invited, or endorsed ZeroThink. Named affiliations should only appear when they are documented and current.</p>
        </section>

        <section>
            <h2>Next Steps</h2>
            <ul>
                <li>Agree which repository, plugin, or project owns each research task before integration.</li>
                <li>Support team allocation around survey extension and topic expert/generative UI work.</li>
                <li>Prepare an Anytype-compatible research structure where useful.</li>
                <li>Prepare demo mode for the next meeting.</li>
                <li>Enable students to choose project interests and workflow templates.</li>
                <li>Keep AI co-scientist as the long-term direction that emerges after search, retrieval, evidence mapping, provenance, and validation are stronger.</li>
            </ul>
        </section>
    </main>
</body>
</html>
