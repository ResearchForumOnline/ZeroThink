<?php
require_once __DIR__ . '/app/bootstrap.php';
$user = current_user_record();
if (!$user) {
    header('Location: /');
    exit;
}
$user_email = htmlspecialchars((string) (isset($user['email']) ? $user['email'] : ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Creator | ZeroThink</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg:#05070b; --panel:#0b1119; --panel2:#101923; --line:#203141; --cyan:#00f0ff; --green:#52d6b2; --text:#edf6ff; --muted:#93a4b7; --warn:#fbbf24; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:radial-gradient(circle at 20% 0%, rgba(0,240,255,.12), transparent 30%), linear-gradient(135deg,#05070b,#07101a 52%,#05070b); color:var(--text); font-family:Inter,Segoe UI,Arial,sans-serif; }
        a { color:inherit; }
        .topbar { min-height:66px; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 24px; border-bottom:1px solid rgba(0,240,255,.14); background:rgba(5,8,12,.86); position:sticky; top:0; z-index:5; backdrop-filter:blur(14px); }
        .brand { display:flex; align-items:center; gap:10px; font-weight:900; letter-spacing:.02em; }
        .brand img { width:34px; height:34px; }
        .nav { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:flex-end; }
        .nav a, button { border:1px solid var(--line); background:#0b121b; color:var(--text); border-radius:8px; padding:10px 13px; font-weight:850; text-decoration:none; cursor:pointer; transition:.18s; }
        .nav a:hover, button:hover { border-color:var(--cyan); transform:translateY(-1px); }
        button.primary { background:linear-gradient(135deg,#00d9ff,#52d6b2); color:#001016; border-color:transparent; }
        button.secondary { color:#cfe8f3; }
        .shell { width:min(1260px,100%); margin:0 auto; padding:24px; display:grid; grid-template-columns:minmax(260px,360px) minmax(0,1fr); gap:18px; }
        .panel { background:rgba(9,15,22,.88); border:1px solid rgba(80,110,130,.42); border-radius:8px; padding:18px; box-shadow:0 28px 90px rgba(0,0,0,.28); }
        .panel h1, .panel h2 { margin:0 0 10px; line-height:1.1; }
        .panel h1 { font-size:clamp(2rem,5vw,4.1rem); letter-spacing:0; }
        .panel h2 { font-size:1.05rem; color:#dcecff; }
        .muted { color:var(--muted); line-height:1.6; font-size:.92rem; }
        .status { display:inline-flex; align-items:center; gap:8px; border:1px solid rgba(82,214,178,.32); background:rgba(82,214,178,.08); color:#bff7e8; border-radius:999px; padding:7px 11px; font-size:.76rem; font-weight:900; text-transform:uppercase; margin-top:12px; }
        .field-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
        label { display:block; color:#dce9f3; font-size:.82rem; font-weight:900; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; }
        input, textarea { width:100%; border:1px solid var(--line); background:#060a10; color:var(--text); border-radius:8px; padding:12px; font:inherit; outline:none; }
        input[type="checkbox"] { width:auto; margin-right:8px; vertical-align:middle; }
        input:focus, textarea:focus { border-color:var(--cyan); box-shadow:0 0 0 3px rgba(0,240,255,.08); }
        textarea { min-height:640px; resize:vertical; line-height:1.55; white-space:pre-wrap; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
        .privacy { border-left:3px solid var(--cyan); padding-left:12px; color:#b7c8d8; font-size:.86rem; line-height:1.55; margin-top:18px; }
        .file-box { margin-top:16px; padding:14px; border:1px dashed rgba(0,240,255,.28); border-radius:8px; background:rgba(0,240,255,.04); }
        .file-list { margin:10px 0 0; padding:0; list-style:none; color:#9fb1c1; font-size:.82rem; line-height:1.55; }
        .ai-prompt { min-height:112px; max-height:180px; }
        .artifact-panel { display:none; margin-top:14px; padding:12px; border:1px solid rgba(82,214,178,.28); border-left:3px solid var(--green); border-radius:8px; background:rgba(82,214,178,.07); color:#dffcf4; font-size:.86rem; line-height:1.5; }
        .artifact-panel a { display:inline-flex; align-items:center; gap:7px; margin-top:8px; border:1px solid rgba(82,214,178,.35); border-radius:8px; padding:8px 10px; color:#eafff9; text-decoration:none; font-weight:850; }
        @media (max-width:900px) { .shell { grid-template-columns:1fr; padding:16px; } .topbar { align-items:flex-start; flex-direction:column; } textarea { min-height:520px; } }
        @media (max-width:620px) { .field-grid { grid-template-columns:1fr; } .nav a, button { width:100%; text-align:center; } .actions button { flex:1 1 100%; } }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="/studio">
            <img src="/assets/img/logo_nobg.png" alt="">
            <span>ZeroThink PDF Creator</span>
        </a>
        <nav class="nav" aria-label="PDF Creator navigation">
            <a href="/studio"><i class="fas fa-comments"></i> Studio</a>
            <a href="/research-paper-creator"><i class="fas fa-file-signature"></i> Paper Creator</a>
            <a href="/faq"><i class="fas fa-book"></i> FAQ</a>
        </nav>
    </header>

    <main class="shell">
        <aside class="panel">
            <h1>PDF Creator</h1>
            <p class="muted">Create a clean ZeroThink PDF from notes, reports, drafts, source summaries, or Paper Creator output.</p>
            <span class="status" id="status"><i class="fas fa-circle-check"></i> Ready</span>

            <div class="file-box">
                <label for="source-files">Add Text Files</label>
                <input id="source-files" type="file" multiple accept=".txt,.md,.csv,.tsv,.json,.xml,.html,.htm,.log,.bib,.rtf">
                <ul class="file-list" id="file-list"></ul>
            </div>

            <div class="file-box">
                <label for="ai-prompt">AI Draft Prompt</label>
                <textarea id="ai-prompt" class="ai-prompt" placeholder="Create a PDF report about..."></textarea>
                <label style="margin-top:10px; text-transform:none; letter-spacing:0;"><input id="ai-web" type="checkbox"> Use WEB search context when available</label>
                <button class="primary" style="margin-top:10px;" type="button" onclick="generateAiDraft()"><i class="fas fa-wand-magic-sparkles"></i> Generate AI PDF Draft</button>
                <div class="artifact-panel" id="artifact-panel"></div>
            </div>

            <div class="actions">
                <button class="primary" type="button" onclick="createPdf()"><i class="fas fa-file-pdf"></i> Create PDF</button>
                <button class="secondary" type="button" onclick="copyBody()"><i class="fas fa-copy"></i> Copy</button>
                <button class="secondary" type="button" onclick="clearWorkspace()"><i class="fas fa-rotate-left"></i> Clear</button>
            </div>

            <p class="privacy">Signed in as <?php echo $user_email ?: 'ZeroThink user'; ?>. Files are read in this browser and sent to ZeroThink only when the PDF is created. The server never accepts local paths or browser-supplied URLs.</p>
        </aside>

        <section class="panel">
            <div class="field-grid">
                <div>
                    <label for="title">Title</label>
                    <input id="title" maxlength="180" placeholder="ZeroThink Report">
                </div>
                <div>
                    <label for="filename">Filename</label>
                    <input id="filename" maxlength="96" placeholder="zerothink-report.pdf">
                </div>
                <div>
                    <label for="subtitle">Subtitle</label>
                    <input id="subtitle" maxlength="240" placeholder="Optional context">
                </div>
                <div>
                    <label for="author">Author</label>
                    <input id="author" maxlength="160" value="<?php echo $user_email; ?>">
                </div>
            </div>

            <div style="margin-top:14px;">
                <label for="body">Document Body</label>
                <textarea id="body" maxlength="120000" placeholder="Paste or draft the document text here."></textarea>
            </div>
        </section>
    </main>

    <script>
        const ZT_SESSION_CSRF_TOKEN = <?= json_encode(
            zt_csrf_token(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
        const storageKey = 'zerothink-pdf-creator-v1';
        const ids = ['title','filename','subtitle','author','body'];
        const statusEl = document.getElementById('status');
        const fileList = document.getElementById('file-list');
        function el(id) { return document.getElementById(id); }
        function setStatus(text) { statusEl.innerHTML = '<i class="fas fa-circle-check"></i> ' + text; }
        function safeSlug(value) {
            return String(value || 'zerothink-document').toLowerCase().replace(/[^a-z0-9._-]+/g, '-').replace(/^-|-$/g, '').slice(0, 80) || 'zerothink-document';
        }
        function decodeApiResponse(raw) {
            try { return JSON.parse(raw); } catch (e) {}
            try {
                const binary = atob(raw);
                const bytes = Uint8Array.from(binary, ch => ch.charCodeAt(0));
                return JSON.parse(new TextDecoder().decode(bytes));
            } catch (e) {}
            return { status: 'error', reply: raw.slice(0, 180) || 'API response could not be decoded.' };
        }
        function collect() {
            return Object.fromEntries(ids.map(id => [id, el(id).value || '']));
        }
        function save() {
            try { localStorage.setItem(storageKey, JSON.stringify(collect())); } catch (e) {}
        }
        function restore() {
            try {
                const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
                if (!saved) return;
                ids.forEach(id => { if (saved[id] !== undefined && el(id)) el(id).value = saved[id]; });
            } catch (e) {}
        }
        async function addFiles(event) {
            const files = Array.from(event.target.files || []);
            if (!files.length) return;
            const names = [];
            for (const file of files) {
                if (file.size > 600000) {
                    names.push(file.name + ' skipped: too large');
                    continue;
                }
                const text = await file.text();
                el('body').value += "\n\n--- Source: " + file.name + " ---\n" + text.trim() + "\n";
                names.push(file.name + ' added');
            }
            fileList.innerHTML = names.map(name => '<li>' + name.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</li>').join('');
            setStatus('Files added');
            save();
        }
        async function createPdf() {
            const payload = collect();
            if (!payload.body.trim()) {
                setStatus('Add text first');
                return;
            }
            payload.title = payload.title.trim() || 'ZeroThink PDF';
            payload.filename = payload.filename.trim() || safeSlug(payload.title) + '.pdf';
            setStatus('Creating PDF');
            try {
                const res = await fetch('/api_pdf_creator.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/pdf, application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
                if (!res.ok) {
                    let msg = 'PDF request failed.';
                    try { msg = (await res.json()).error || msg; } catch (e) {}
                    throw new Error(msg);
                }
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = safeSlug(payload.filename).replace(/\.pdf$/i, '') + '.pdf';
                a.click();
                URL.revokeObjectURL(url);
                setStatus('PDF downloaded');
            } catch (err) {
                setStatus('Export blocked');
                alert(err.message);
            }
        }
        function renderArtifact(pdf) {
            const panel = el('artifact-panel');
            if (!pdf || !pdf.url) {
                panel.style.display = 'none';
                panel.innerHTML = '';
                return;
            }
            const title = String(pdf.title || 'ZeroThink AI Report').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));
            const filename = String(pdf.filename || 'zerothink-ai-report.pdf').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));
            const url = String(pdf.url || '#').replace(/"/g, '&quot;');
            panel.innerHTML = `<strong><i class="fas fa-file-pdf"></i> AI PDF ready: ${title}</strong><br>${filename}<br><a href="${url}" download="${filename}"><i class="fas fa-download"></i> Download AI PDF</a>`;
            panel.style.display = 'block';
        }
        async function generateAiDraft() {
            const promptText = el('ai-prompt').value.trim();
            const sourceText = el('body').value.trim();
            if (!promptText && !sourceText) {
                setStatus('Add prompt or data');
                return;
            }
            const title = el('title').value.trim() || 'ZeroThink AI Report';
            const prompt = promptText || `Create a PDF report titled "${title}" from the supplied source data.`;
            setStatus('AI drafting');
            try {
                const encodedPayload = btoa(unescape(encodeURIComponent(JSON.stringify({
                    prompt: /\bpdf\b/i.test(prompt) ? prompt : `Create a PDF report. ${prompt}`,
                    file_context: sourceText,
                    ephemeral_file_context: !!sourceText,
                    file_context_label: 'PDF Creator source text used for one AI draft request.',
                    engine: 'groq',
                    groq_model: 'groq:openai/gpt-oss-120b',
                    zero_mode: true,
                    use_web: el('ai-web').checked,
                    csrf_token: ZT_SESSION_CSRF_TOKEN
                }))));
                const res = await fetch('/api_agent.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': ZT_SESSION_CSRF_TOKEN
                    },
                    body: encodedPayload
                });
                const raw = await res.text();
                const data = decodeApiResponse(raw);
                if (data.status === 'error') throw new Error(data.reply || 'AI draft failed.');
                el('body').value = data.reply || '';
                const h1 = (el('body').value.match(/^#\\s+(.+)$/m) || [])[1];
                if (h1 && !el('title').value.trim()) el('title').value = h1.slice(0, 160);
                renderArtifact(data.pdf_artifact);
                if (data.pdf_artifact_error) alert(data.pdf_artifact_error);
                save();
                setStatus(data.pdf_artifact ? 'AI PDF ready' : 'AI draft ready');
            } catch (err) {
                setStatus('AI draft blocked');
                alert(err.message);
            }
        }
        async function copyBody() {
            try {
                await navigator.clipboard.writeText(el('body').value || '');
                setStatus('Copied');
            } catch (e) {
                setStatus('Copy blocked');
            }
        }
        function clearWorkspace() {
            if (!window.confirm('Clear this PDF workspace?')) return;
            ids.forEach(id => { if (id !== 'author') el(id).value = ''; });
            fileList.innerHTML = '';
            localStorage.removeItem(storageKey);
            setStatus('Cleared');
        }
        ids.forEach(id => el(id).addEventListener('input', save));
        document.getElementById('source-files').addEventListener('change', addFiles);
        restore();
    </script>
</body>
</html>
