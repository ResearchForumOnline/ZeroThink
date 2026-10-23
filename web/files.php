<?php
require_once __DIR__ . '/app/bootstrap.php';
if (!isset($_SESSION['user_id'])) {
    header('Location:/');
    exit;
}
$user = function_exists('current_user_record') ? current_user_record() : null;
if (!function_exists('is_architect_user') || !is_architect_user($user)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeroThink | Private Files</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #06070b;
            --panel: rgba(11, 15, 20, 0.92);
            --line: rgba(255,255,255,0.09);
            --gold: #eab86b;
            --green: #63d38d;
            --cyan: #7ce8ff;
            --text: #edf3fa;
            --muted: #94a4b7;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, system-ui, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top right, rgba(99, 211, 141, 0.1), transparent 28%),
                radial-gradient(circle at top left, rgba(234, 184, 107, 0.1), transparent 30%),
                linear-gradient(180deg, #06070b 0%, #0a1118 100%);
        }
        .shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px 18px 40px;
        }
        .topbar, .hero, .upload-row, .file-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar {
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .brand, .link-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text);
        }
        .brand img { width: 42px; }
        .link-btn {
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 10px 16px;
            background: rgba(255,255,255,0.03);
        }
        .hero-card, .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 22px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.3);
            backdrop-filter: blur(16px);
        }
        .hero {
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .hero h1 {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.5rem);
            line-height: 0.96;
            letter-spacing: -0.04em;
        }
        .muted { color: var(--muted); }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.04);
            color: var(--text);
            font-size: 0.82rem;
        }
        .grid {
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
            gap: 18px;
        }
        .upload-row {
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .btn {
            border: none;
            border-radius: 18px;
            padding: 12px 16px;
            cursor: pointer;
            font-weight: 800;
        }
        .btn-primary { background: linear-gradient(135deg, var(--gold), #ffd79a); color: #15120c; }
        .btn-secondary { background: rgba(255,255,255,0.05); color: var(--text); border: 1px solid var(--line); }
        .status {
            margin-top: 14px;
            min-height: 22px;
            color: var(--muted);
            font-size: 0.92rem;
        }
        .file-list {
            display: grid;
            gap: 12px;
            margin-top: 12px;
        }
        .file-row {
            justify-content: space-between;
            flex-wrap: wrap;
            padding: 14px;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03);
        }
        .file-meta { display: grid; gap: 4px; }
        .file-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .empty {
            padding: 18px;
            border-radius: 18px;
            border: 1px dashed rgba(255,255,255,0.12);
            color: var(--muted);
        }
        @media (max-width: 920px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <a href="/studio" class="brand">
                <img src="/assets/img/logo_nobg.png" alt="ZeroThink">
                <div>
                    <div style="font-weight:900; letter-spacing:0.16em; text-transform:uppercase;">ZeroThink</div>
                    <div class="muted" style="font-size:0.78rem; letter-spacing:0.18em; text-transform:uppercase;">Private Architect Vault</div>
                </div>
            </a>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="/studio" class="link-btn"><i class="fas fa-chevron-left"></i> Studio</a>
                <a href="/master" class="link-btn"><i class="fas fa-user-shield"></i> Master</a>
            </div>
        </div>

        <div class="hero-card hero">
            <div>
                <div class="pill"><i class="fas fa-lock"></i> Architect only</div>
                <h1>Private files without reopening the old public attack surface.</h1>
                <p class="muted" style="max-width:760px;">This vault is stored under the private app tree, not the public web root. No archive extraction, no executable uploads, no end-user access, and no world-writable folders.</p>
            </div>
            <div style="display:grid; gap:10px;">
                <div class="pill"><i class="fas fa-shield-halved"></i> Safe upload only</div>
                <div class="pill"><i class="fas fa-file-zipper"></i> ZIPs stored, not extracted</div>
                <div class="pill"><i class="fas fa-ban"></i> PHP / shell payloads blocked</div>
            </div>
        </div>

        <div class="grid">
            <div class="panel">
                <div style="font-size:0.78rem; letter-spacing:0.18em; color:var(--cyan); text-transform:uppercase;">Upload</div>
                <h2 style="margin:10px 0 8px;">Drop admin files into your private vault</h2>
                <div class="muted">Use this for notes, backups, docs, exports, images, archives, and inert assets. Use SSH/SFTP for executable scripts or server-side code changes.</div>
                <div class="upload-row">
                    <input id="file-input" type="file">
                    <button class="btn btn-primary" id="upload-btn" type="button"><i class="fas fa-upload"></i> Upload</button>
                    <button class="btn btn-secondary" id="refresh-btn" type="button"><i class="fas fa-rotate"></i> Refresh</button>
                </div>
                <div class="status" id="status-box">Private vault ready.</div>
            </div>

            <div class="panel">
                <div style="font-size:0.78rem; letter-spacing:0.18em; color:var(--cyan); text-transform:uppercase;">Files</div>
                <h2 style="margin:10px 0 8px;">Current vault contents</h2>
                <div class="file-list" id="file-list">
                    <div class="empty">Loading…</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const FILES_CSRF_TOKEN = <?= json_encode(
            zt_csrf_token(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;
        const statusBox = document.getElementById('status-box');
        const fileList = document.getElementById('file-list');
        const fileInput = document.getElementById('file-input');

        function setStatus(message, good = true) {
            statusBox.textContent = message;
            statusBox.style.color = good ? '#9fe7be' : '#ffb4b4';
        }

        function renderFiles(files) {
            if (!Array.isArray(files) || files.length === 0) {
                fileList.innerHTML = '<div class="empty">No files stored yet.</div>';
                return;
            }

            fileList.innerHTML = files.map(file => `
                <div class="file-row">
                    <div class="file-meta">
                        <div style="font-weight:800; color:#fff;">${file.name}</div>
                        <div class="muted">${file.size} • ${new Date(file.modified).toLocaleString()}</div>
                    </div>
                    <div class="file-actions">
                        <a class="btn btn-secondary" href="/api_files.php?action=download&name=${encodeURIComponent(file.name)}"><i class="fas fa-download"></i> Download</a>
                        <button class="btn btn-secondary" type="button" onclick="deleteFile('${encodeURIComponent(file.name)}')"><i class="fas fa-trash"></i> Delete</button>
                    </div>
                </div>
            `).join('');
        }

        async function refreshFiles() {
            try {
                const res = await fetch('/api_files.php?action=list', { credentials: 'same-origin' });
                const data = await res.json();
                if (data.status !== 'success') {
                    throw new Error(data.message || 'List failed.');
                }
                renderFiles(data.files || []);
                setStatus('Vault synced.');
            } catch (err) {
                fileList.innerHTML = '<div class="empty">Could not load files.</div>';
                setStatus(err.message || 'Could not load files.', false);
            }
        }

        async function uploadFile() {
            if (!fileInput.files || !fileInput.files[0]) {
                setStatus('Choose a file first.', false);
                return;
            }

            const fd = new FormData();
            fd.append('action', 'upload');
            fd.append('file', fileInput.files[0]);
            fd.append('csrf_token', FILES_CSRF_TOKEN);
            setStatus('Uploading ' + fileInput.files[0].name + '...');

            try {
                const res = await fetch('/api_files.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': FILES_CSRF_TOKEN }
                });
                const data = await res.json();
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Upload failed.');
                }
                fileInput.value = '';
                setStatus(data.message || 'Upload complete.');
                await refreshFiles();
            } catch (err) {
                setStatus(err.message || 'Upload failed.', false);
            }
        }

        async function deleteFile(encodedName) {
            if (!confirm('Delete this file from the private vault?')) {
                return;
            }

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('name', decodeURIComponent(encodedName));
            fd.append('csrf_token', FILES_CSRF_TOKEN);

            try {
                const res = await fetch('/api_files.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': FILES_CSRF_TOKEN }
                });
                const data = await res.json();
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Delete failed.');
                }
                setStatus(data.message || 'Deleted.');
                await refreshFiles();
            } catch (err) {
                setStatus(err.message || 'Delete failed.', false);
            }
        }

        document.getElementById('upload-btn').addEventListener('click', uploadFile);
        document.getElementById('refresh-btn').addEventListener('click', refreshFiles);
        refreshFiles();
    </script>
</body>
</html>
