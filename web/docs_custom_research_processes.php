<?php require_once __DIR__ . '/app/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Research Processes | ZeroThink</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <style>
        body { margin:0; background:#050607; color:#eef5f6; font-family:Inter,system-ui,sans-serif; line-height:1.7; }
        main { max-width:1120px; margin:0 auto; padding:42px 24px 72px; }
        a { color:#52d6b2; }
        h1 { font-size:clamp(2rem,4vw,3.4rem); line-height:1.08; margin:14px 0; letter-spacing:0; }
        h2 { margin-top:0; color:#7bb7ff; }
        p, li, label { color:#a8b3bd; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin-top:22px; }
        .card, .panel { border:1px solid #26313a; border-radius:8px; background:#0d1117; padding:20px; }
        .card h3 { margin:0 0 8px; color:#fff; }
        .back { display:inline-block; margin-bottom:18px; text-decoration:none; border:1px solid #26313a; border-radius:8px; padding:9px 12px; color:#eef5f6; }
        input, textarea { width:100%; background:#06090d; color:#fff; border:1px solid #26313a; border-radius:8px; padding:10px; margin:6px 0 14px; font:inherit; }
        textarea { min-height:92px; }
        button { background:#52d6b2; color:#05100d; border:0; border-radius:8px; padding:12px 16px; font-weight:800; cursor:pointer; }
        .note { color:#52d6b2; font-weight:700; }
    </style>
</head>
<body>
    <main>
        <a href="/nottingham-research" class="back">Back to Literature Research</a>
        <p style="text-transform:uppercase; letter-spacing:.14em; color:#52d6b2; font-weight:800;">User-facing scaffolds</p>
        <h1>Custom Research Process Templates</h1>
        <p>ZeroThink supports custom thinking-process options as visible research scaffolds, checklists, stages, rubrics, and workflow templates. These templates do not expose or store hidden chain-of-thought.</p>

        <section class="panel">
            <h2>Default Templates</h2>
            <div id="template-list" class="grid"></div>
        </section>

        <section class="panel" style="margin-top:20px;">
            <h2>Add Custom User Template</h2>
            <form id="template-form">
                <label>Name</label>
                <input name="name" value="Literature Survey Extension">
                <label>Description</label>
                <textarea name="description">Extend an existing literature survey using new papers while preserving the original review structure.</textarea>
                <label>Stages, one per line</label>
                <textarea name="stages">Define survey scope
Import existing survey structure
Search new papers
Cluster papers by section
Map claims to evidence
Identify gaps and contradictions
Human validation
Generate updated review draft</textarea>
                <label>Required sources, one per line</label>
                <textarea name="required_sources">Existing survey
Serper search results
Full-text papers where available</textarea>
                <label>Validation checks, one per line</label>
                <textarea name="validation_checks">Every claim maps to evidence
Contradictions are logged
Human approval before synthesis</textarea>
                <label>Output format</label>
                <input name="output_format" value="Evidence map plus updated review draft">
                <label>Provenance requirements</label>
                <input name="provenance_requirements" value="Source ledger with URL, title, snippet, source, and retrieval timestamp">
                <label>Export option</label>
                <input name="export_option" value="Markdown, DOCX, PDF, or LaTeX where supported">
                <button type="submit">Save Template</button>
            </form>
            <p id="save-status" class="note"></p>
        </section>
    </main>
    <script>
        const csrfToken = <?= json_encode(zt_csrf_token(), JSON_UNESCAPED_SLASHES) ?>;
        function lines(value) {
            return value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
        }
        async function loadTemplates() {
            const res = await fetch('/api_research_templates.php');
            const data = await res.json();
            const list = document.getElementById('template-list');
            list.innerHTML = '';
            (data.templates || []).forEach((template) => {
                const card = document.createElement('article');
                card.className = 'card';
                const title = document.createElement('h3');
                title.textContent = template.name || 'Untitled template';
                const description = document.createElement('p');
                description.textContent = template.description || '';
                const stages = document.createElement('p');
                const stagesLabel = document.createElement('strong');
                stagesLabel.textContent = 'Stages: ';
                stages.appendChild(stagesLabel);
                const stageList = Array.isArray(template.stages) ? template.stages : [];
                stages.appendChild(document.createTextNode(stageList.slice(0, 4).join(', ') + (stageList.length > 4 ? '...' : '')));
                card.appendChild(title);
                card.appendChild(description);
                card.appendChild(stages);
                list.appendChild(card);
            });
        }
        document.getElementById('template-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = new FormData(event.currentTarget);
            const payload = {
                name: form.get('name'),
                description: form.get('description'),
                stages: lines(form.get('stages') || ''),
                required_sources: lines(form.get('required_sources') || ''),
                validation_checks: lines(form.get('validation_checks') || ''),
                output_format: form.get('output_format'),
                provenance_requirements: form.get('provenance_requirements'),
                export_option: form.get('export_option'),
                csrf_token: csrfToken,
                provenance_required: true,
                human_approval_required: true
            };
            const res = await fetch('/api_research_templates.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            let data = {};
            try {
                data = await res.json();
            } catch (error) {
                data = {status: 'error', message: 'The server did not return a valid template response.'};
            }
            document.getElementById('save-status').textContent = res.ok && data.status === 'success'
                ? 'Template saved as a visible research scaffold.'
                : (data.message || 'Could not save template.');
            await loadTemplates();
        });
        loadTemplates();
    </script>
</body>
</html>
