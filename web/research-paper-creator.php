<?php
require_once __DIR__ . '/app/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Research Paper Creator | ZeroThink</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" defer></script>
    <style>
        :root { --bg:#050607; --panel:#101319; --line:#25303a; --text:#f1f5f8; --muted:#a3afba; --accent:#7bb7ff; --green:#52d6b2; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif; line-height:1.65; }
        .shell { max-width:1160px; margin:0 auto; padding:34px 24px 70px; }
        a { color:var(--green); }
        .top { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:34px; flex-wrap:wrap; }
        .top-links { display:flex; gap:10px; flex-wrap:wrap; }
        .top a { text-decoration:none; border:1px solid var(--line); border-radius:8px; padding:10px 14px; color:var(--text); background:#090c11; }
        .hero { padding:44px; border:1px solid var(--line); border-radius:8px; background:linear-gradient(135deg, rgba(123,183,255,.12), rgba(82,214,178,.06)); }
        .eyebrow { color:var(--green); font-weight:800; letter-spacing:.14em; text-transform:uppercase; font-size:.78rem; }
        h1 { font-size:clamp(2.2rem,4vw,3.8rem); line-height:1.05; margin:10px 0 18px; letter-spacing:0; }
        h2 { margin:0 0 12px; font-size:1.4rem; }
        h3 { margin:0 0 8px; font-size:1.05rem; }
        p { color:var(--muted); margin:0 0 18px; }
        .lead { max-width:880px; color:#dce9f3; font-size:1.12rem; }
        .workflow-strip { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:8px; margin-top:24px; }
        .workflow-strip span { border:1px solid rgba(82,214,178,.25); background:rgba(5,10,14,.72); border-radius:8px; padding:10px; color:#cfece4; font-size:.78rem; font-weight:800; text-align:center; }
        section { margin-top:22px; border:1px solid var(--line); border-radius:8px; padding:24px; background:var(--panel); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(235px,1fr)); gap:16px; }
        .card { border:1px solid var(--line); border-radius:8px; padding:18px; background:#090c11; }
        .creator { display:grid; grid-template-columns:minmax(300px,.92fr) minmax(0,1.08fr); gap:18px; align-items:start; }
        label { display:block; color:#dce9f3; font-size:.78rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; margin:14px 0 7px; }
        input, textarea, select { width:100%; border:1px solid var(--line); border-radius:8px; background:#070a0f; color:var(--text); padding:12px; font:inherit; }
        textarea { min-height:110px; resize:vertical; }
        .form-row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        .form-row.three { grid-template-columns:repeat(3,minmax(0,1fr)); }
        .hint { color:var(--muted); font-size:.86rem; margin-top:8px; }
        .mode-note { border:1px solid rgba(123,183,255,.28); border-radius:8px; background:rgba(123,183,255,.07); padding:12px; color:#dce9f3; font-size:.9rem; }
        .upload-zone { margin:16px 0 4px; border:1px solid rgba(82,214,178,.24); border-radius:8px; background:linear-gradient(135deg,rgba(82,214,178,.08),rgba(123,183,255,.05)); padding:16px; }
        .upload-zone input[type=file] { margin-top:10px; border-style:dashed; background:#05080d; }
        .source-list { display:grid; gap:8px; margin-top:12px; }
        .source-empty, .source-item { border:1px solid var(--line); border-radius:8px; background:#070a0f; padding:10px 12px; color:#cbd8e3; font-size:.88rem; }
        .source-item { display:grid; grid-template-columns:1fr auto; gap:10px; align-items:start; }
        .source-meta { color:var(--muted); font-size:.78rem; margin-top:2px; }
        .source-status { color:#8de7ce; font-weight:850; text-transform:uppercase; letter-spacing:.08em; font-size:.68rem; }
        .source-warning { color:#f7dfa3; }
        .source-remove { padding:7px 9px; font-size:.75rem; background:#151b24; color:#f1f5f8; border:1px solid var(--line); }
        .privacy-note { color:#f1d590; font-size:.84rem; margin:10px 0 0; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; }
        button { border:0; border-radius:8px; padding:12px 15px; cursor:pointer; font-weight:850; color:#06100d; background:var(--green); }
        button.secondary { color:var(--text); background:#111720; border:1px solid var(--line); }
        button:disabled { opacity:.58; cursor:wait; }
        .output-panel { position:sticky; top:20px; }
        .output-head { display:flex; justify-content:space-between; gap:12px; align-items:center; margin-bottom:10px; }
        .output-status { color:#8de7ce; border:1px solid rgba(82,214,178,.3); background:rgba(82,214,178,.07); border-radius:999px; padding:6px 10px; font-size:.72rem; font-weight:850; text-transform:uppercase; letter-spacing:.08em; }
        .output { min-height:680px; max-height:calc(100vh - 150px); white-space:pre-wrap; overflow:auto; background:#070a0f; border:1px solid var(--line); border-radius:8px; padding:18px; color:#dce9f3; }
        .checks { display:grid; gap:8px; margin-top:8px; color:var(--muted); }
        .checks label { display:flex; align-items:center; gap:8px; margin:0; text-transform:none; letter-spacing:0; font-weight:600; font-size:.9rem; }
        .checks input { width:auto; }
        ul { color:var(--muted); margin:0; padding-left:20px; }
        li { margin:8px 0; }
        .stage-list { columns:2; column-gap:36px; }
        .boundary { border-color:rgba(242,207,125,.28); background:rgba(242,207,125,.045); }
        .boundary strong { color:#f7dfa3; }
        @media (max-width:860px) { .creator, .form-row, .form-row.three { grid-template-columns:1fr; } .output-panel { position:static; } .output { min-height:520px; max-height:none; } }
        @media (max-width:740px) { .hero { padding:28px; } .stage-list { columns:1; } .workflow-strip { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    </style>
</head>
<body>
    <main class="shell">
        <div class="top">
            <a href="/studio">Return to Studio</a>
            <div class="top-links">
                <a href="/nottingham-research">Literature Research</a>
                <a href="/docs/custom-research-processes">Process Templates</a>
            </div>
        </div>

        <article class="hero">
            <div class="eyebrow">Evidence-first research writing</div>
            <h1>Research Paper Creator</h1>
            <p class="lead">Move from a research question to a search protocol, evidence ledger, defensible outline, full paper draft, reviewer critique, and revision plan without pretending unverified sources are facts.</p>
            <p class="lead">Planning stages are separate from writing stages. Use the full-paper buttons when you want an actual article instead of protocols and approval questions.</p>
            <div class="workflow-strip" aria-label="Research paper workflow">
                <span>01 Scope</span><span>02 Search</span><span>03 Evidence</span><span>04 Outline</span><span>05 Draft</span><span>06 Review</span>
            </div>
        </article>

        <section class="creator" id="creator">
            <div>
                <h2>Define The Research Project</h2>
                <p>Build one stage at a time. Project fields and the latest output are saved in this browser so an interrupted session can be recovered.</p>
                <div class="mode-note">Research integrity rule: citations, quotations, statistics, DOIs, and URLs must be traceable. Search snippets are discovery leads, not proof that a full paper was read.</div>
                <div class="mode-note" style="margin-top:10px;">Key privacy rule: browser keys can be saved locally, but a web request still sends the selected provider key to the ZeroThink server when the server must call Groq, OpenAI, Gemini, xAI, NVIDIA, Serper, IonQ, or IBM on your behalf. Use the CLI/local route for work where the server must never receive the key.</div>
                <div class="mode-note" style="margin-top:10px;">To create a full research paper: fill in the topic, attach sources if available, choose paper type and length, then click <strong>Create Full Paper Draft</strong> or <strong>Create Survey Article</strong>. Earlier buttons create planning material only.</div>
                <div class="mode-note" style="margin-top:10px;">Live generation needs an active ZeroThink session or a supported key route. If a student sees an auth message, they should sign in, then add their own provider key in Studio -> Neural Vault or use an approved server key lane.</div>
                <div class="mode-note" style="margin-top:10px;">Survey expansion route: attach a few seed articles or paper notes, run Plan the Literature Search, Decide What Evidence Is Needed, Evidence Graph, List and Check Sources, Build Paper Structure, then Create Full Paper Draft. The graph lane is scoped to this creator and the Nottingham literature workspace; it is not the default ZeroThink chat mode.</div>

                <div class="upload-zone">
                    <div class="eyebrow">Optional source upload</div>
                    <h3>Attach files for this paper pass</h3>
                    <p class="hint">Upload article notes, research text, abstracts, supervisor comments, source exports, or draft files. TXT, MD, CSV, JSON, HTML, RTF, DOCX, and legacy DOC are handled in-browser where possible.</p>
                    <input id="source-files" type="file" multiple accept=".txt,.md,.csv,.tsv,.json,.xml,.html,.htm,.rtf,.doc,.docx,.log,.bib">
                    <p class="privacy-note">Files are read by this browser and attached only to the current generation request. They are not saved as uploaded research-library files. Do not attach confidential material unless you intend it to be processed by the selected AI route.</p>
                    <div class="actions" style="margin-top:10px;">
                        <button type="button" class="secondary" onclick="clearSources()">Clear sources</button>
                    </div>
                    <div class="source-list" id="source-list"><div class="source-empty">No files attached yet.</div></div>
                </div>
                <label for="topic">Topic</label>
                <input id="topic" value="AI co-scientist literature discovery for survey extension">

                <label for="research-question">Research Question / Hypothesis</label>
                <textarea id="research-question" placeholder="Optional: paste the exact research question, hypothesis, or supervisor-approved aim."></textarea>

                <label for="paper-type">Paper Type</label>
                <select id="paper-type">
                    <option>Full research paper</option>
                    <option>Survey article</option>
                    <option>Literature review article</option>
                    <option>Conceptual research paper</option>
                    <option>Technical white paper</option>
                    <option>Systematic review extension</option>
                    <option>Survey expansion with provenance evidence graph</option>
                    <option>Narrative literature review</option>
                    <option>Research proposal</option>
                    <option>Position paper</option>
                    <option>Methodology paper</option>
                    <option>Conference short paper</option>
                    <option>Grant / defence research brief</option>
                </select>

                <label for="discipline">Discipline / Research Area</label>
                <input id="discipline" placeholder="Example: computer science, HCI, quantum information, education, public policy">

                <label for="methodology">Method / Study Design</label>
                <input id="methodology" placeholder="Example: PRISMA-style review, mixed methods, experiment plan, survey extension, case study">

                <label for="audience">Audience / Venue</label>
                <input id="audience" value="Academic or professional research audience">

                <div class="form-row three">
                    <div>
                        <label for="citation-style">Citation Style</label>
                        <select id="citation-style">
                            <option>Harvard</option>
                            <option>APA 7</option>
                            <option>IEEE</option>
                            <option>MLA</option>
                            <option>Vancouver</option>
                            <option>Chicago</option>
                        </select>
                    </div>
                    <div>
                        <label for="length-target">Length Target</label>
                        <select id="length-target">
                            <option>Short plan / 1,000 words</option>
                            <option>Conference short paper / 2,500 words</option>
                            <option>Journal-style draft / 5,000 words</option>
                            <option>Thesis chapter / 8,000 words</option>
                            <option>Custom from notes</option>
                        </select>
                    </div>
                    <div>
                        <label for="language">Output Language</label>
                        <input id="language" value="English (UK)">
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label for="date-from">Evidence From</label>
                        <input id="date-from" placeholder="Example: 2018">
                    </div>
                    <div>
                        <label for="date-to">Evidence To</label>
                        <input id="date-to" placeholder="Example: present">
                    </div>
                </div>

                <label for="source-policy">Source Policy</label>
                <select id="source-policy">
                    <option>Verified sources only; placeholders for anything not confirmed</option>
                    <option>Use supplied sources only</option>
                    <option>Use live web search plus supplied sources</option>
                    <option>Build search strategy only, no invented source list</option>
                </select>
                <p class="hint">For OpenAI, Gemini, xAI, and Quantum lanes, the account must be Pro or the API will fall back according to the ZeroThink server rules.</p>

                <label for="databases">Target Databases / Collections</label>
                <input id="databases" placeholder="Example: Crossref, OpenAlex, PubMed, arXiv, IEEE Xplore, ACM DL">

                <label for="inclusion">Inclusion Criteria</label>
                <textarea id="inclusion" placeholder="Population, methods, publication types, date range, languages, study quality, or topic boundaries."></textarea>

                <label for="exclusion">Exclusion Criteria</label>
                <textarea id="exclusion" placeholder="Out-of-scope methods, duplicate reports, unsupported claims, inaccessible evidence, or excluded publication types."></textarea>

                <label for="notes">Known Sources, Constraints, Or Notes</label>
                <textarea id="notes" placeholder="Paste paper titles, DOI links, supervisor instructions, module requirements, methods, or constraints here."></textarea>

                <label>Options</label>
                <div class="checks">
                    <label><input id="use-web" type="checkbox" checked> Use web search / Serper evidence when configured</label>
                    <label><input id="critique" type="checkbox" checked> Include reviewer-style critique and revision plan</label>
                    <label><input id="strict-citations" type="checkbox" checked> Require DOI/URL/status labels for every citation claim</label>
                    <label><input id="ethics" type="checkbox" checked> Include ethics, reproducibility, and limitations</label>
                    <label><input id="claim-matrix" type="checkbox" checked> Include claim-to-evidence matrix</label>
                    <label><input id="evidence-graph" type="checkbox" checked> Build claim/evidence/provenance graph</label>
                    <label><input id="optimizer-tests" type="checkbox" checked> Add classical baseline plus quantum/simulator optimisation test plan</label>
                    <label><input id="integrity-check" type="checkbox" checked> Flag unsupported quotations, statistics, causal claims, and inaccessible full text</label>
                    <label><input id="clear-english" type="checkbox" checked> Easy to understand: clear UK English, explained terms, short paragraphs, plain-language summary before abstract</label>
                    <label><input id="use-prior-output" type="checkbox" checked> Use the current output as context for the next workflow stage</label>
                </div>

                <label for="model">Model Lane</label>
                <select id="model">
                    <optgroup label="OpenAI">
                        <option value="openai:gpt-5.5">OpenAI GPT-5.5</option>
                        <option value="openai:gpt-5.4">OpenAI GPT-5.4</option>
                        <option value="openai:gpt-5.4-mini">OpenAI GPT-5.4 Mini</option>
                        <option value="openai:gpt-5.4-nano">OpenAI GPT-5.4 Nano</option>
                        <option value="openai:gpt-5-pro">OpenAI GPT-5 Pro</option>
                    </optgroup>
                    <optgroup label="Gemini">
                        <option value="gemini:gemini-3.5-flash">Gemini 3.5 Flash</option>
                        <option value="gemini:gemini-3.1-pro">Gemini 3.1 Pro</option>
                        <option value="gemini:gemini-3-flash">Gemini 3 Flash</option>
                        <option value="gemini:gemini-3.1-flash-lite">Gemini 3.1 Flash-Lite</option>
                        <option value="gemini:gemini-2.5-pro">Gemini 2.5 Pro</option>
                        <option value="gemini:gemini-2.5-flash">Gemini 2.5 Flash</option>
                    </optgroup>
                    <optgroup label="xAI">
                        <option value="xai:grok-4.3">Grok 4.3</option>
                        <option value="xai:grok-4.3-latest">Grok 4.3 Latest</option>
                        <option value="xai:grok-build-0.1">Grok Build 0.1</option>
                        <option value="xai:grok-4-fast-reasoning">Grok 4 Fast Reasoning</option>
                    </optgroup>
                    <optgroup label="Groq">
                        <option value="groq:openai/gpt-oss-120b" selected>Groq GPT-OSS 120B</option>
                        <option value="groq:llama-3.3-70b-versatile">Groq Llama 3.3 70B</option>
                    </optgroup>
                    <optgroup label="NVIDIA">
                        <option value="nvidia:qwen/qwen3-coder-480b-a35b-instruct">NVIDIA Qwen 3 Coder 480B</option>
                        <option value="nvidia:deepseek-ai/deepseek-v3.2">NVIDIA DeepSeek V3.2</option>
                        <option value="nvidia:mistralai/mistral-large-3-675b-instruct-2512">NVIDIA Mistral Large 3</option>
                    </optgroup>
                    <optgroup label="QuantumZero">
                        <option value="quantum:quantum/gpt-oss-120b-ionq">QuantumZero: Groq GPT-OSS 120B + IonQ evidence lane</option>
                    </optgroup>
                </select>

                <div class="actions">
                    <button type="button" data-run onclick="runCreator('search_protocol')">Plan the Literature Search</button>
                    <button type="button" data-run onclick="runCreator('evidence_plan')">Decide What Evidence Is Needed</button>
                    <button type="button" data-run onclick="runCreator('evidence_graph')">Evidence Graph</button>
                    <button type="button" data-run onclick="runCreator('source_ledger')">List and Check Sources</button>
                    <button type="button" data-run onclick="runCreator('optimisation_test')">Optimisation Test</button>
                    <button type="button" data-run onclick="runCreator('paper_outline')">Build Paper Structure</button>
                    <button type="button" data-run onclick="runCreator('full_research_paper')">Create Full Paper Draft</button>
                    <button type="button" data-run onclick="runCreator('survey_article')">Create Survey Article</button>
                    <button type="button" data-run onclick="runCreator('reviewer_critique')">Critique the Draft</button>
                    <button type="button" data-run onclick="runCreator('revision_plan')">Plan Improvements</button>
                    <button type="button" class="secondary" onclick="copyOutput()">Copy</button>
                    <button type="button" class="secondary" onclick="downloadOutput()">Download Markdown</button>
                    <button type="button" class="secondary" onclick="downloadPdfOutput()">Download PDF</button>
                    <button type="button" class="secondary" onclick="downloadProject()">Export Project JSON</button>
                    <button type="button" class="secondary" onclick="resetWorkspace()">Reset</button>
                </div>
            </div>
            <div class="output-panel">
                <div class="output-head">
                    <h2>Research Output</h2>
                    <span class="output-status" id="output-status">Ready</span>
                </div>
                <pre class="output" id="output" aria-live="polite">Ready. Use the planning buttons for protocols, source checks, and outlines. Use Create Full Paper Draft or Create Survey Article when you want the article itself.</pre>
            </div>
        </section>

        <section>
            <h2>How Source Ledger Entries Are Labelled</h2>
            <div class="grid">
                <div class="card"><h3>Student Survey Expansion Route</h3><p>Attach two to five seed articles, abstracts, BibTeX entries, or notes. Run Plan the Literature Search, Decide What Evidence Is Needed, Evidence Graph, List and Check Sources, Build Paper Structure, Create Full Paper Draft, Critique the Draft, and Plan Improvements. Treat every new source as unverified until a DOI, URL, uploaded text, or database result confirms it.</p></div>
                <div class="card"><h3>Not A Generated Bibliography</h3><p>The source ledger is not meant to present AI-invented papers as real sources. Each row must show where it came from: an uploaded file, researcher notes, a retrieved search result, supplied full text, a candidate lead, or an item that still needs verification.</p></div>
                <div class="card"><h3>Uploaded Source</h3><p>Text supplied by the researcher through the upload box or notes field. The system can quote or summarize it, but the researcher still checks the original file before submission.</p></div>
                <div class="card"><h3>Retrieved Search Result</h3><p>Live web or Serper search context when enabled and available. This is a discovery result unless the output clearly states full-text access was provided.</p></div>
                <div class="card"><h3>Candidate Lead</h3><p>A model-suggested title, author, query, or likely paper to search for. Candidate leads are not treated as verified citations until a DOI, URL, database result, or supplied file confirms them.</p></div>
                <div class="card"><h3>Needs Verification</h3><p>Anything missing a reliable source trail must be marked before drafting claims around it.</p></div>
            </div>
        </section>

        <section>
            <h2>Core Workflow</h2>
            <ul class="stage-list">
                <li>Topic intake</li>
                <li>Research question refinement</li>
                <li>Search protocol and database selection</li>
                <li>Literature search</li>
                <li>Duplicate removal and screening</li>
                <li>Paper retrieval</li>
                <li>Full-text access-status check</li>
                <li>Source ledger</li>
                <li>DOI / URL verification status</li>
                <li>Evidence map</li>
                <li>Claim ledger</li>
                <li>Claim-to-evidence matrix</li>
                <li>Claim/evidence/provenance graph</li>
                <li>Classical baseline and constrained optimisation test plan</li>
                <li>Provenance and citation tracking</li>
                <li>Outline generation</li>
                <li>Argument and counterargument pass</li>
                <li>Methodology helper</li>
                <li>Related work builder</li>
                <li>Gap analysis</li>
                <li>Ethics and reproducibility notes</li>
                <li>Draft generation</li>
                <li>Reviewer-style critique</li>
                <li>Revision planning</li>
                <li>Markdown and project JSON export</li>
            </ul>
        </section>

        <section>
            <h2>Why This Is Research-Facing</h2>
            <div class="grid">
                <div class="card"><h3>Deeper Provenance</h3><p>Every source, claim, and synthesis step is tied back to a ledger where possible.</p></div>
                <div class="card"><h3>Researcher-in-the-loop Control</h3><p>The researcher can steer, correct, validate, and approve before final synthesis.</p></div>
                <div class="card"><h3>Evidence Graph + Optimisation</h3><p>The Nottingham and Paper Creator lanes can build a claim/evidence/provenance graph, then propose constrained optimisation tests for evidence-path selection, clustering, routing, prioritisation, and auditability. Quantum or simulator methods must be compared with classical baselines and must not be described as understanding documents.</p></div>
                <div class="card"><h3>Evidence-first Drafting</h3><p>The workflow starts with literature and claims, not one-shot generation.</p></div>
                <div class="card"><h3>Multi-model Lanes</h3><p>OpenAI, Gemini, xAI, Groq, NVIDIA, and QuantumZero can be selected for different research passes.</p></div>
                <div class="card"><h3>Structured Critique</h3><p>Arguments, counterarguments, gaps, and reviewer-style critique happen before the final draft.</p></div>
            </div>
        </section>

        <section>
            <h2>Research Collaboration Boundary</h2>
            <p>A literature-research plugin, repository, invitation, or team project can supply sources and workflows, but it remains separate from the ZeroThink agent until an integration is deliberately implemented and verified.</p>
            <p>This workspace does not claim university endorsement, institutional adoption, contributor availability, repository access, or formal project membership. Researchers remain responsible for source access, authorship, disclosure, ethics approval, and submission decisions.</p>
        </section>

        <section class="boundary">
            <h2>Before Submission</h2>
            <p><strong>Human verification is required.</strong> Check every citation against the original source, confirm quotation wording and page numbers, review statistical claims, disclose AI assistance where required, and follow the target venue's authorship and research-integrity rules.</p>
        </section>
    </main>
    <script>
        const ZT_SESSION_CSRF_TOKEN = <?= json_encode(
            zt_csrf_token(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
        const output = document.getElementById('output');
        const outputStatus = document.getElementById('output-status');
        const storageKey = 'zerothink-paper-creator-v3';
        const fieldIds = ['topic','research-question','paper-type','discipline','methodology','audience','citation-style','length-target','language','date-from','date-to','source-policy','databases','inclusion','exclusion','notes','use-web','critique','strict-citations','ethics','claim-matrix','evidence-graph','optimizer-tests','integrity-check','clear-english','use-prior-output','model'];
        const sourceList = document.getElementById('source-list');
        const maxUploadedChars = 180000;
        const maxSingleSourceChars = 45000;
        let uploadedSources = [];
        let saveTimer = null;
        let lastRunState = null;
        const actionEnum = {
            protocol: 'search_protocol',
            plan: 'evidence_plan',
            graph: 'evidence_graph',
            ledger: 'source_ledger',
            optimise: 'optimisation_test',
            outline: 'paper_outline',
            draft: 'draft_paper',
            critique: 'reviewer_critique',
            revision: 'revision_plan',
            search_protocol: 'search_protocol',
            evidence_plan: 'evidence_plan',
            evidence_graph: 'evidence_graph',
            source_ledger: 'source_ledger',
            optimisation_test: 'optimisation_test',
            paper_outline: 'paper_outline',
            draft_paper: 'draft_paper',
            full_research_paper: 'full_research_paper',
            survey_article: 'survey_article',
            reviewer_critique: 'reviewer_critique',
            revision_plan: 'revision_plan'
        };
        const actionLabels = {
            search_protocol: 'Plan the Literature Search',
            evidence_plan: 'Decide What Evidence Is Needed',
            evidence_graph: 'Evidence Graph',
            source_ledger: 'List and Check Sources',
            optimisation_test: 'Optimisation Test',
            paper_outline: 'Build Paper Structure',
            draft_paper: 'Create Full Paper Draft',
            full_research_paper: 'Create Full Paper Draft',
            survey_article: 'Create Survey Article',
            reviewer_critique: 'Critique the Draft',
            revision_plan: 'Plan Improvements'
        };
        function val(id) { const el = document.getElementById(id); return el ? String(el.value || '').trim() : ''; }
        function checked(id) { const el = document.getElementById(id); return Boolean(el && el.checked); }
        function setBusy(isBusy) { document.querySelectorAll('[data-run]').forEach(btn => btn.disabled = isBusy); }
        function setStatus(text) { outputStatus.textContent = text; }
        function providerFromModel(model) {
            const parts = String(model || '').split(':');
            return parts.length > 1 ? parts[0] : 'groq';
        }
        function encodePayload(payload) {
            const bytes = new TextEncoder().encode(JSON.stringify(payload));
            let binary = '';
            for (let i = 0; i < bytes.length; i += 0x8000) {
                binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
            }
            return btoa(binary);
        }
        function decodeApiResponse(raw) {
            const text = String(raw || '').trim();
            if (!text) return {};
            try { return JSON.parse(text); } catch (e) {}
            try {
                const binary = atob(text);
                const bytes = Uint8Array.from(binary, char => char.charCodeAt(0));
                return JSON.parse(new TextDecoder().decode(bytes));
            } catch (e) {}
            throw new Error('API returned unreadable JSON.');
        }
        function fileExt(name) {
            const match = String(name || '').toLowerCase().match(/\.([a-z0-9]+)$/);
            return match ? match[1] : '';
        }
        function humanSize(bytes) {
            const value = Number(bytes || 0);
            if (value >= 1024 * 1024) return (value / (1024 * 1024)).toFixed(1) + ' MB';
            if (value >= 1024) return (value / 1024).toFixed(1) + ' KB';
            return value + ' B';
        }
        function cleanExtractedText(text) {
            return String(text || '')
                .replace(/\r/g, '\n')
                .replace(/\u0000/g, ' ')
                .replace(/[ \t]+\n/g, '\n')
                .replace(/\n{4,}/g, '\n\n\n')
                .trim();
        }
        function stripHtml(text) {
            const doc = new DOMParser().parseFromString(String(text || ''), 'text/html');
            return cleanExtractedText(doc.body ? doc.body.innerText : text);
        }
        function stripRtf(text) {
            return cleanExtractedText(String(text || '')
                .replace(/\\'[0-9a-f]{2}/gi, ' ')
                .replace(/\\[a-z]+\d* ?/gi, ' ')
                .replace(/[{}]/g, ' ')
                .replace(/\s+/g, ' '));
        }
        async function extractDocx(file) {
            if (typeof JSZip === 'undefined') {
                throw new Error('DOCX reader still loading. Try again in a few seconds or save as TXT.');
            }
            const zip = await JSZip.loadAsync(await file.arrayBuffer());
            const main = zip.file('word/document.xml');
            if (!main) throw new Error('DOCX document.xml not found.');
            const xml = await main.async('text');
            return cleanExtractedText(xml
                .replace(/<w:tab\/>/g, '\t')
                .replace(/<\/w:p>/g, '\n')
                .replace(/<[^>]+>/g, ' ')
                .replace(/&amp;/g, '&')
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>')
                .replace(/&quot;/g, '"')
                .replace(/&apos;/g, "'"));
        }
        async function extractLegacyDoc(file) {
            const bytes = await file.arrayBuffer();
            const decoded = new TextDecoder('windows-1252', { fatal:false }).decode(bytes);
            return cleanExtractedText(decoded
                .replace(/\u0000/g, ' ')
                .replace(/[^\x09\x0A\x0D\x20-\x7E\u00A0-\u024F]+/g, ' ')
                .replace(/\s+/g, ' '));
        }
        async function readSourceFile(file) {
            const ext = fileExt(file.name);
            let text = '';
            let status = 'uploaded source';
            let warning = '';
            if (ext === 'docx') {
                text = await extractDocx(file);
            } else if (ext === 'doc') {
                text = await extractLegacyDoc(file);
                status = 'legacy doc best effort';
                warning = 'Legacy .doc extraction is imperfect. Confirm the original file.';
            } else if (ext === 'rtf') {
                text = stripRtf(await file.text());
            } else if (['html','htm','xml'].includes(ext)) {
                text = stripHtml(await file.text());
            } else if (['txt','md','csv','tsv','json','log','bib'].includes(ext)) {
                text = cleanExtractedText(await file.text());
            } else {
                text = cleanExtractedText(await file.text());
                status = 'best effort text';
                warning = 'Unknown extension. Verify extraction.';
            }
            if (!text) {
                warning = warning || 'No readable text extracted.';
            }
            if (text.length > maxSingleSourceChars) {
                text = text.slice(0, maxSingleSourceChars) + '\n\n[TRUNCATED BY BROWSER UPLOAD LIMIT]';
                warning = (warning ? warning + ' ' : '') + 'Long file truncated for model context.';
            }
            return { id: crypto.randomUUID ? crypto.randomUUID() : String(Date.now() + Math.random()), name:file.name, size:file.size, type:file.type || ext || 'file', status, warning, text };
        }
        function renderSourceList() {
            if (!sourceList) return;
            if (!uploadedSources.length) {
                sourceList.innerHTML = '<div class="source-empty">No files attached yet.</div>';
                return;
            }
            sourceList.innerHTML = uploadedSources.map(source => `
                <div class="source-item">
                    <div>
                        <strong>${escapeHtml(source.name)}</strong>
                        <div class="source-meta"><span class="source-status">${escapeHtml(source.status)}</span> · ${humanSize(source.size)} · ${source.text.length.toLocaleString()} chars</div>
                        ${source.warning ? `<div class="source-meta source-warning">${escapeHtml(source.warning)}</div>` : ''}
                    </div>
                    <button type="button" class="source-remove" onclick="removeSource('${source.id}')">Remove</button>
                </div>
            `).join('');
        }
        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
        }
        async function handleSourceFiles(event) {
            const files = Array.from(event.target.files || []);
            if (!files.length) return;
            setStatus('Reading sources');
            for (const file of files.slice(0, 12)) {
                try {
                    uploadedSources.push(await readSourceFile(file));
                } catch (err) {
                    uploadedSources.push({ id: String(Date.now() + Math.random()), name:file.name, size:file.size, type:file.type || fileExt(file.name), status:'not extracted', warning:err.message || 'Could not read this file.', text:'' });
                }
            }
            renderSourceList();
            setStatus('Sources ready');
            event.target.value = '';
        }
        function removeSource(id) {
            uploadedSources = uploadedSources.filter(source => source.id !== id);
            renderSourceList();
            setStatus('Source removed');
        }
        function clearSources() {
            uploadedSources = [];
            renderSourceList();
            setStatus('Sources cleared');
        }
        function uploadedSourceInventory() {
            if (!uploadedSources.length) return 'No uploaded source files attached.';
            return uploadedSources.map((source, index) => `${index + 1}. ${source.name} | ${source.status} | ${humanSize(source.size)} | ${source.text ? source.text.length.toLocaleString() + ' readable chars' : 'no readable text'}${source.warning ? ' | note: ' + source.warning : ''}`).join('\n');
        }
        function uploadedSourceContext() {
            if (!uploadedSources.length) return '';
            let total = 0;
            const chunks = [];
            for (const source of uploadedSources) {
                if (!source.text) {
                    chunks.push(`--- SOURCE: ${source.name} ---\nStatus: ${source.status}\nReadable text: none\nNote: ${source.warning || 'Could not extract readable text.'}`);
                    continue;
                }
                const remaining = Math.max(0, maxUploadedChars - total);
                if (remaining <= 0) break;
                const slice = source.text.slice(0, remaining);
                total += slice.length;
                chunks.push(`--- SOURCE: ${source.name} ---\nStatus: ${source.status}\nSize: ${humanSize(source.size)}\nNote: ${source.warning || 'none'}\n\n${slice}`);
            }
            return chunks.join('\n\n');
        }
        function collectProject() {
            const project = {};
            fieldIds.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                project[id] = el.type === 'checkbox' ? el.checked : el.value;
            });
            return project;
        }
        function saveWorkspace() {
            try {
                localStorage.setItem(storageKey, JSON.stringify({
                    version: 3,
                    savedAt: new Date().toISOString(),
                    project: collectProject(),
                    lastRunState,
                    output: output.textContent || ''
                }));
                setStatus('Saved locally');
            } catch (e) {
                setStatus('Local save unavailable');
            }
        }
        function scheduleSave() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(saveWorkspace, 350);
        }
        function restoreWorkspace() {
            try {
                const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
                if (!saved || !saved.project) return;
                Object.entries(saved.project).forEach(([id, value]) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    if (el.type === 'checkbox') el.checked = Boolean(value);
                    else el.value = String(value ?? '');
                });
                if (saved.output) output.textContent = saved.output;
                if (saved.lastRunState) lastRunState = saved.lastRunState;
                setStatus('Recovered locally');
            } catch (e) {}
        }
        function priorStageContext() {
            if (!checked('use-prior-output')) return '(not included)';
            const text = String(output.textContent || '').trim();
            if (!text || /^(Ready\.|Research Paper Creator running|Error:|Workspace cleared\.)/.test(text)) return '(no approved prior-stage output)';
            return text.length > 18000 ? '[Earlier content omitted]\n' + text.slice(-18000) : text;
        }
        function buildWebQuery() {
            return [
                val('topic'),
                val('research-question'),
                val('discipline'),
                val('methodology'),
                'research papers',
                val('date-from'),
                val('date-to')
            ].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim().slice(0, 420);
        }
        function normalizeAction(mode) {
            return actionEnum[String(mode || '').trim()] || 'evidence_plan';
        }
        function isFullPaperMode(action) {
            return ['draft_paper', 'full_research_paper', 'survey_article'].includes(normalizeAction(action));
        }
        function lengthInstruction(lengthTarget, fullMode) {
            const text = String(lengthTarget || '').toLowerCase();
            if (!fullMode && text.includes('1,000')) return 'Planning mode: keep this concise and practical.';
            if (text.includes('1,000')) return 'Write a short but complete article draft of roughly 1,000 to 1,500 words. Do not collapse it into a checklist.';
            if (text.includes('2,500')) return 'Write a compressed conference-style paper of roughly 2,000 to 3,000 words with all required sections.';
            if (text.includes('5,000')) return 'Write a journal-style full article draft of roughly 4,000 to 6,000 words where the model context allows. Prefer depth over filler.';
            if (text.includes('8,000')) return 'Write a long thesis-chapter style draft with section depth, tables, and appendix notes. Continue as far as the model context allows.';
            if (text.includes('custom')) return 'Follow the custom length and structure instructions from the notes field; if unclear, produce a complete journal-style draft.';
            return fullMode ? 'Write a complete article draft with all required sections.' : 'Use the selected length target to control the depth of the planning output.';
        }
        function paperStructure(type, action) {
            const lower = String(type || '').toLowerCase();
            if (action === 'survey_article' || lower.includes('survey')) {
                return [
                    'Title',
                    'Plain-language summary',
                    'Abstract',
                    'Keywords',
                    'Introduction',
                    'Survey scope',
                    'Method for source discovery',
                    'Taxonomy / classification of systems',
                    'Literature synthesis by theme',
                    'Comparison table of key sources',
                    'Gaps and open problems',
                    'Implications for ZeroThink / zero-boundary algebra if supplied by the user',
                    'Ethics, limitations, and reproducibility',
                    'Conclusion',
                    'References',
                    'Appendix: source ledger / verification notes'
                ];
            }
            if (lower.includes('white paper')) {
                return [
                    'Title',
                    'Plain-language summary',
                    'Executive abstract',
                    'Problem statement',
                    'Background and related work',
                    'Proposed technical approach',
                    'Evidence base and source-selection explanation',
                    'Architecture or workflow',
                    'Comparison table of key sources',
                    'Risks, ethics, limitations, and reproducibility',
                    'Implementation roadmap',
                    'Conclusion',
                    'References',
                    'Appendix: source ledger / verification notes'
                ];
            }
            if (lower.includes('conceptual')) {
                return [
                    'Title',
                    'Plain-language summary',
                    'Abstract',
                    'Keywords',
                    'Introduction',
                    'Research question / thesis',
                    'Background / related work',
                    'Conceptual framework',
                    'Evidence base / source-selection explanation',
                    'Main analysis / synthesis',
                    'Comparison table of key sources',
                    'Discussion',
                    'Ethics, limitations, and reproducibility',
                    'Future work',
                    'Conclusion',
                    'References',
                    'Appendix: source ledger / verification notes'
                ];
            }
            return [
                'Title',
                'Plain-language summary',
                'Abstract',
                'Keywords',
                'Introduction',
                'Research question / thesis',
                'Background / related work',
                'Method or literature-search method',
                'Evidence base / source-selection explanation',
                'Main analysis / synthesis',
                'Comparison table of key sources',
                'Discussion',
                'ZeroThink / zero-boundary algebra conceptual contribution, if supplied by user',
                'Ethics, limitations, and reproducibility',
                'Future work',
                'Conclusion',
                'References',
                'Appendix: source ledger / verification notes'
            ];
        }
        function knownSourceCorrections() {
            return `Known source corrections for the AI co-scientist survey test case:
- Google Research blog: "Accelerating scientific breakthroughs with an AI co-scientist"; 19 February 2025; authors Juraj Gottweis and Vivek Natarajan; use as an official blog/system description.
- Nature article: "Accelerating scientific discovery with Co-Scientist"; published 19 May 2026; DOI 10.1038/s41586-026-10644-y; authors begin Juraj Gottweis, Wei-Hung Weng, Alexander Daryin, Tao Tu, et al.; use as primary peer-reviewed/open-access article when accessible; do not label it "Nature Editorial".
- arXiv 2502.18864: "Accelerating scientific discovery with Co-Scientist"; submitted 26 February 2025; last revised 29 June 2026; related DOI 10.1038/s41586-026-10644-y.
- arXiv 2510.23045: "A Survey of AI Scientists"; authors Guiyao Tie, Pan Zhou, Lichao Sun; submitted 27 October 2025; last revised 17 January 2026; DOI 10.48550/arXiv.2510.23045; do not call it "Smith J. et al."
- DeepMind blog: "Co-Scientist: A multi-agent AI partner to accelerate research"; 19 May 2026; author Co-Scientist team; use as official blog/system announcement.
- Sapio blog: "5 Ways AI Co-Scientists Will Transform Your Research Workflow"; author Luke Davies Cooke; 8 July 2025; use as industry commentary only, low evidence weight.
- Moonlight pages are AI-generated/secondary summaries. Do not use them as primary evidence if arXiv/Nature are available.`;
        }
        function planningContract(action) {
            const byAction = {
                search_protocol: 'Output only a reproducible literature-search protocol. Include search strings, databases, date range, inclusion/exclusion criteria, screening steps, source-status rules, and next verification tasks. Do not draft the paper.',
                evidence_plan: 'Output an evidence plan. Decide what evidence is needed, what can be cited, what remains only a candidate lead, and how claims will be checked. Do not draft the paper.',
                evidence_graph: 'Output a provenance-aware claim/evidence graph and survey-expansion map only. Include nodes, edges, contradictions, gaps, confidence, and audit notes. Do not draft the paper.',
                source_ledger: 'Output a source ledger and claim-to-evidence matrix only. Mark uploaded source, retrieved search result, full text supplied, model candidate lead, and needs verification. Do not draft the paper.',
                optimisation_test: 'Output a constrained optimisation test plan for evidence-path selection, clustering, routing, prioritisation, and auditability only. Compare classical baselines with quantum/simulator candidate methods. Do not claim quantum computers understand documents. Do not draft the paper.',
                paper_outline: 'Output a detailed paper outline tied to verified evidence and visible gaps. Include the target sections and where each evidence source would be used. Do not draft full prose.',
                reviewer_critique: 'Act as two strict academic reviewers. Critique the current draft or notes, identify missing evidence, unsupported claims, citation risks, structure problems, and revision steps.',
                revision_plan: 'Output a prioritized revision plan from the available project details and current draft or notes. Include exact edits, source checks, and structure fixes.'
            };
            return byAction[action] || byAction.evidence_plan;
        }
        function fullPaperContract(action, type, lengthTarget) {
            const sections = paperStructure(type, action).map((item, index) => `${index + 1}. ${item}`).join('\n');
            return `You are writing a complete research paper / survey article, not a protocol, not a plan, and not a list of tasks.
Use the supplied sources, verified web evidence, and prior stage output only as material for a full article.
Do not end with human approval questions. Do not say the protocol needs to be finalised. Do not output "Stage 1 Planning".
The output must begin with a title and contain Abstract, Introduction, Method or Methodology, Literature Review or Related Work or Background, Analysis, Discussion, Limitations, Conclusion, and References.
Use clear UK English. Make it understandable to a smart non-specialist while keeping academic strength.
Mark unverified sources clearly and do not treat search snippets as full-text evidence.
If sources are verified, cite and use them in the paper. If sources are only search leads, put them in "Candidate sources not used as evidence" in the appendix.
If full text is unavailable, write a cautious draft using verified abstracts/metadata and clearly mark limits.
Never invent DOI, authors, venue, page numbers, statistics, quotations, or source claims.
${lengthInstruction(lengthTarget, true)}

Required structure:
${sections}`;
        }
        function buildPrompt(mode, sourceInventory) {
            const action = normalizeAction(mode);
            const topic = val('topic');
            const researchQuestion = val('research-question') || '(refine from topic and notes)';
            const type = val('paper-type');
            const discipline = val('discipline') || '(infer cautiously from the topic)';
            const methodology = val('methodology') || '(recommend a defensible method if not supplied)';
            const notes = val('notes');
            const audience = val('audience');
            const citationStyle = val('citation-style');
            const lengthTarget = val('length-target');
            const language = val('language');
            const dateRange = `${val('date-from') || 'not specified'} to ${val('date-to') || 'present'}`;
            const sourcePolicy = val('source-policy');
            const databases = val('databases') || '(recommend suitable databases and collections)';
            const inclusion = val('inclusion') || '(propose explicit inclusion criteria for researcher approval)';
            const exclusion = val('exclusion') || '(propose explicit exclusion criteria for researcher approval)';
            const web = checked('use-web');
            const critique = checked('critique');
            const strictCitations = checked('strict-citations');
            const ethics = checked('ethics');
            const claimMatrix = checked('claim-matrix');
            const evidenceGraph = checked('evidence-graph');
            const optimizerTests = checked('optimizer-tests');
            const integrityCheck = checked('integrity-check');
            const clearEnglish = checked('clear-english');
            const fullMode = isFullPaperMode(action);
            const target = fullMode ? fullPaperContract(action, type, lengthTarget) : planningContract(action);
            const planningEnding = fullMode
                ? 'Do not end with next-source tasks or human approval questions. End with a concise conclusion, references, and appendix verification notes.'
                : 'End with exact next source tasks and human approval questions.';
            return `${target}

Action enum: ${action}
Action label: ${actionLabels[action] || action}
Topic: ${topic}
Research question or hypothesis: ${researchQuestion}
Paper type: ${type}
Discipline or research area: ${discipline}
Method or study design: ${methodology}
Audience or venue: ${audience}
Citation style: ${citationStyle}
Length target: ${lengthTarget}
Output language: ${language}
Evidence date range: ${dateRange}
Source policy: ${sourcePolicy}
Target databases or collections: ${databases}
Inclusion criteria: ${inclusion}
Exclusion criteria: ${exclusion}
Length instruction: ${lengthInstruction(lengthTarget, fullMode)}
Uploaded source inventory:
${sourceInventory || 'No uploaded source files attached.'}
Use web search: ${web ? 'yes, search for recent sources and keep provenance' : 'no, use only supplied notes'}
Reviewer critique: ${critique ? 'include critique and revision plan' : 'skip critique unless necessary'}
Strict citation mode: ${strictCitations ? 'yes, every citation/claim needs DOI, URL, source status, or [needs verification]' : 'normal'}
Ethics/reproducibility/limitations: ${ethics ? 'include explicitly' : 'brief only'}
Claim-to-evidence matrix: ${claimMatrix ? 'include a table mapping each major claim to evidence and confidence' : 'optional'}
Claim/evidence/provenance graph: ${evidenceGraph ? 'include nodes, edges, provenance labels, contradictions, gaps, and audit notes' : 'optional'}
Quantum/simulator optimisation test plan: ${optimizerTests ? 'include candidate constrained optimisation tasks only: evidence-path selection, clustering, routing, prioritisation, and auditability scoring; compare with a classical baseline' : 'skip unless needed'}
Integrity checks: ${integrityCheck ? 'flag unsupported quotations, statistics, causal claims, inaccessible full text, retractions, and duplicate evidence where visible' : 'standard source checks'}
Easy-to-understand style: ${clearEnglish ? 'Use clear UK English, explain technical terms the first time they appear, prefer short paragraphs, and include a plain-language summary before the abstract.' : 'Use normal academic style.'}

Known notes or sources:
${notes || '(none supplied)'}

${knownSourceCorrections()}

Prior workflow output supplied for continuity:
${priorStageContext()}

Required output contract:
1. Use the action enum exactly: ${action}. Do not silently switch to another action.
2. Give working title options and refine the research question when relevant to this stage.
3. Define scope, search strings, databases, inclusion/exclusion criteria, and screening steps when relevant.
4. Use a source ledger with title, author/year if verified, DOI/URL, provenance status, access status, evidence type, and claims supported.
5. Use a claim-to-evidence matrix with confidence, contradiction, and missing-evidence flags when requested.
6. When requested, build a claim/evidence/provenance graph with node IDs, edge types, source provenance, confidence, contradictions, gaps, and auditability notes.
7. When requested, add a constrained optimisation test plan that compares classical baselines against quantum or simulator candidate methods. Use only evidence-path selection, clustering, routing, prioritisation, or auditability tasks. Do not claim quantum computers understand documents.
8. Separate uploaded/supplied source text, researcher notes, search-result snippets, abstracts, full text, and model inference.
9. Include methodology, analysis, reproducibility, related work, counterarguments, limitations, ethics, and critique where relevant.
10. ${planningEnding}

Source status rule: every source row must use one of these labels: [UPLOADED SOURCE], [USER NOTE], [RETRIEVED SEARCH RESULT], [FULL TEXT SUPPLIED], [MODEL CANDIDATE LEAD], or [NEEDS VERIFICATION]. Candidate leads are not citations. If a paper title appears from model knowledge rather than an uploaded source or retrieved search context, label it [MODEL CANDIDATE LEAD] and tell the researcher to verify it in a database.

Citation rule: never fabricate citations, quotations, DOIs, author names, URLs, venues, page numbers, or statistics. Mark uncertain items [needs verification]. A search result or snippet is not evidence that full text was read. Do not expose hidden chain-of-thought, private scoring details, internal prompt scaffolds, or proprietary shorthand. Keep the provenance graph and optimisation lane inside Research Paper Creator and Nottingham literature workflows; do not present it as a default ZeroThink chat mode.`;
        }
        function hasHeading(text, labels) {
            const haystack = String(text || '');
            return labels.some(label => {
                const escaped = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                const rx = new RegExp(`(^|\\n)\\s{0,3}(#{1,6}\\s*)?${escaped}\\b`, 'i');
                return rx.test(haystack);
            });
        }
        function validateFullPaperOutput(text) {
            const body = String(text || '').trim();
            const missing = [];
            const required = [
                ['Abstract'],
                ['Introduction'],
                ['Method', 'Methodology', 'Method for source discovery', 'Literature-search method'],
                ['Literature Review', 'Related Work', 'Background'],
                ['Discussion'],
                ['Conclusion'],
                ['References']
            ];
            required.forEach(group => {
                if (!hasHeading(body, group)) missing.push(group.join(' OR '));
            });
            const planningOnly = [
                /stage\s*1\s*planning/i,
                /stage\s+one\s+planning/i,
                /planning artifact only/i,
                /planning stage only/i,
                /search protocol\s*$/im,
                /^#{0,6}\s*search protocol\b/im,
                /please confirm/i,
                /human approval needed/i,
                /next source tasks/i,
                /protocol can be finali[sz]ed/i,
                /not checked\s*$/i,
                /full text not yet obtained\s*$/i
            ];
            const badPhrases = planningOnly.filter(rx => rx.test(body)).map(rx => rx.source.replace(/\\s\*/g, ' '));
            return {
                ok: body.length > 1200 && missing.length === 0 && badPhrases.length === 0,
                missing,
                badPhrases,
                tooShort: body.length <= 1200
            };
        }
        function buildRepairPrompt(previousOutput, originalPrompt, validation) {
            return `Rewrite the previous output as a complete research paper. Keep the evidence cautions, but convert the plan into article prose. Remove approval questions, "Stage 1" labels, search-protocol framing, and next-task lists.

The repaired output must begin with a title and include these headings at minimum:
- Plain-language summary
- Abstract
- Keywords
- Introduction
- Method or Methodology
- Literature Review or Related Work or Background
- Analysis or Synthesis
- Discussion
- Ethics, limitations, and reproducibility
- Conclusion
- References
- Appendix: source ledger / verification notes

Validation problems detected:
- Missing headings: ${(validation.missing || []).join(', ') || 'none'}
- Planning-only phrases: ${(validation.badPhrases || []).join(', ') || 'none'}
- Too short: ${validation.tooShort ? 'yes' : 'no'}

Do not invent citations, DOIs, page numbers, quotations, statistics, or source metadata. Mark unverified sources clearly. Candidate search leads belong in an appendix, not in the main evidence base.

Original user/project prompt:
${originalPrompt}

Previous output to repair:
${previousOutput}`;
        }
        async function callAgent(payload) {
            const encoded = encodePayload({ ...payload, csrf_token: ZT_SESSION_CSRF_TOKEN });
            const res = await fetch('/api_agent.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': ZT_SESSION_CSRF_TOKEN
                },
                body: encoded
            });
            const raw = await res.text();
            if (!res.ok) {
                throw new Error(`API HTTP ${res.status}: ${raw.slice(0, 260) || 'empty response'}`);
            }
            const data = decodeApiResponse(raw);
            if (data.status !== 'success') {
                throw new Error(data.reply || data.message || data.error || 'Research Paper Creator failed');
            }
            return data.reply || JSON.stringify(data, null, 2);
        }
        async function runCreator(mode) {
            if (!val('topic')) {
                output.textContent = 'Add a research topic before starting a workflow stage.';
                setStatus('Topic required');
                return;
            }
            const action = normalizeAction(mode);
            const sourceInventory = uploadedSourceInventory();
            const sourceContext = uploadedSourceContext();
            const prompt = buildPrompt(action, sourceInventory);
            const selectedModel = val('model');
            const fullMode = isFullPaperMode(action);
            output.textContent = `Research Paper Creator running ${actionLabels[action] || action}...`;
            setStatus('Running ' + action);
            setBusy(true);
            const basePayload = {
                prompt,
                groq_model: selectedModel,
                model: selectedModel,
                engine: providerFromModel(selectedModel),
                zero_mode: true,
                use_web: checked('use-web'),
                web_query: buildWebQuery(),
                file_context: sourceContext,
                file_context_label: sourceInventory,
                ephemeral_file_context: sourceContext !== '',
                paper_creator_action: action,
                paper_creator_label: actionLabels[action] || action,
                paper_type: val('paper-type'),
                length_target: val('length-target'),
                source_policy: val('source-policy'),
                persona: 'You are ZeroThink Research Paper Creator. Be evidence-first, citation disciplined, careful with institutional wording, and useful to academic and professional researchers. Support provenance-aware survey expansion, claim/evidence graphs, and constrained optimisation test planning only inside this Paper Creator / Nottingham literature lane. Treat literature plugins and repositories as external research inputs unless integration is explicitly verified. Never imply endorsement, affiliation, invitation, repository access, full-text access, or quantum approval without evidence. Never claim quantum computers understand documents. Do not reveal hidden chain-of-thought or private framework details.',
            };
            try {
                const firstReply = await callAgent(basePayload);
                let finalReply = firstReply;
                let validation = fullMode ? validateFullPaperOutput(firstReply) : { ok:true };
                if (fullMode && !validation.ok) {
                    setStatus('Repairing full paper');
                    console.warn('ZeroThink Paper Creator validation repair', {
                        action,
                        missing: validation.missing,
                        badPhrases: validation.badPhrases,
                        tooShort: validation.tooShort,
                        responseLength: firstReply.length
                    });
                    const repairPayload = {
                        ...basePayload,
                        prompt: buildRepairPrompt(firstReply, prompt, validation),
                        paper_creator_action: action,
                        paper_creator_repair: true,
                        use_web: false,
                        file_context: sourceContext,
                        file_context_label: sourceInventory,
                        persona: basePayload.persona + ' You are now repairing an incomplete planning-style answer into a complete article draft.'
                    };
                    finalReply = await callAgent(repairPayload);
                    validation = validateFullPaperOutput(finalReply);
                    if (!validation.ok) {
                        finalReply += `\n\n---\nValidator warning: ZeroThink requested a full article repair, but the returned draft may still need manual review. Missing headings: ${validation.missing.join(', ') || 'none'}. Planning phrases: ${validation.badPhrases.join(', ') || 'none'}.`;
                    }
                }
                output.textContent = finalReply;
                lastRunState = {
                    action,
                    actionLabel: actionLabels[action] || action,
                    model: selectedModel,
                    sourcePolicy: val('source-policy'),
                    lengthTarget: val('length-target'),
                    responseLength: finalReply.length,
                    validation: fullMode ? validation : null,
                    completedAt: new Date().toISOString()
                };
                console.info('ZeroThink Paper Creator complete', lastRunState);
                setStatus(fullMode && validation && !validation.ok ? 'Complete with warning' : 'Complete');
                saveWorkspace();
            } catch (err) {
                console.error('ZeroThink Paper Creator error', {
                    action,
                    model: selectedModel,
                    sourcePolicy: val('source-policy'),
                    errorType: err && err.name ? err.name : 'Error',
                    message: err && err.message ? err.message : String(err)
                });
                const message = err && err.message ? err.message : String(err);
                const authHelp = /403|Auth or supported direct key mode required/i.test(message)
                    ? "\n\nThis page reached the ZeroThink API, but generation is locked until the browser is signed in or an approved direct-key route is available. Sign in to ZeroThink, then open Studio -> Neural Vault and save the provider key you want to use, or switch to an approved server key lane."
                    : "\n\nNo empty or silent fallback was used. If this says a key is missing, open Studio -> Neural Vault and save the needed OpenAI, Gemini, xAI, Groq, NVIDIA, Serper, or IonQ key. If a model lane is down, choose another model lane and run the same action again.";
                output.textContent = 'Error: ' + message + authHelp;
                setStatus('Error');
            } finally {
                setBusy(false);
            }
        }
        async function copyOutput() {
            try {
                await navigator.clipboard.writeText(output.textContent || '');
                setStatus('Copied');
            } catch (e) {
                setStatus('Copy blocked');
            }
        }
        function safeSlug(value) {
            return String(value || 'research-paper').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 64) || 'research-paper';
        }
        function downloadOutput() {
            const header = `# ${val('topic') || 'ZeroThink Research Paper'}\n\nGenerated: ${new Date().toISOString()}\n\n`;
            const blob = new Blob([header + (output.textContent || '')], { type: 'text/markdown' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = safeSlug(val('topic')) + '.md';
            a.click();
            URL.revokeObjectURL(a.href);
            setStatus('Markdown exported');
        }
        async function downloadPdfOutput() {
            const text = String(output.textContent || '').trim();
            if (!text || /^(Ready\.|Research Paper Creator running|Error:|Workspace cleared\.)/.test(text)) {
                setStatus('No approved output');
                return;
            }
            const title = val('topic') || 'ZeroThink Research Paper';
            const header = `# ${title}\n\nGenerated: ${new Date().toISOString()}\n\n`;
            setStatus('PDF building');
            try {
                const res = await fetch('/api_pdf_creator.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/pdf, application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        title,
                        subtitle: 'Research Paper Creator export',
                        filename: safeSlug(title) + '.pdf',
                        body: header + text
                    })
                });
                if (!res.ok) {
                    let msg = 'PDF export failed.';
                    try { msg = (await res.json()).error || msg; } catch (e) {}
                    throw new Error(msg);
                }
                const blob = await res.blob();
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = safeSlug(title) + '.pdf';
                a.click();
                URL.revokeObjectURL(a.href);
                setStatus('PDF exported');
            } catch (err) {
                setStatus('PDF blocked');
                alert(err.message + "\n\nOpen Studio first if the export says sign-in is required, then return to the Paper Creator.");
            }
        }
        function downloadProject() {
            const blob = new Blob([JSON.stringify({
                format: 'zerothink-research-project',
                version: 3,
                exportedAt: new Date().toISOString(),
                project: collectProject(),
                latestAction: lastRunState,
                uploadedSourceInventory: uploadedSourceInventory(),
                uploadPrivacy: 'Uploaded source text is not persisted in browser project storage by ZeroThink Paper Creator. Reattach files for a future generation pass.',
                output: output.textContent || ''
            }, null, 2)], { type: 'application/json' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = safeSlug(val('topic')) + '.zerothink-research.json';
            a.click();
            URL.revokeObjectURL(a.href);
            setStatus('Project exported');
        }
        function resetWorkspace() {
            if (!window.confirm('Clear the saved research project and current output in this browser?')) return;
            localStorage.removeItem(storageKey);
            document.querySelectorAll('input, textarea').forEach(el => {
                if (el.type === 'checkbox') el.checked = ['use-web','critique','strict-citations','ethics','claim-matrix','evidence-graph','optimizer-tests','integrity-check','clear-english','use-prior-output'].includes(el.id);
                else el.value = '';
            });
            document.getElementById('audience').value = 'Academic or professional research audience';
            document.getElementById('language').value = 'English (UK)';
            clearSources();
            lastRunState = null;
            output.textContent = 'Workspace cleared. Add a topic and begin with a planning stage, or click Create Full Paper Draft when you are ready for article prose.';
            setStatus('Reset');
        }
        fieldIds.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener(el.type === 'checkbox' || el.tagName === 'SELECT' ? 'change' : 'input', scheduleSave);
        });
        const sourceInput = document.getElementById('source-files');
        if (sourceInput) sourceInput.addEventListener('change', handleSourceFiles);
        renderSourceList();
        restoreWorkspace();
    </script>
</body>
</html>
