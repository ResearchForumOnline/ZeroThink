<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sovereign Command | ZeroThink</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <link rel="stylesheet" href="/assets/css/zerothink_titan.css?v=9">
    <style>
        body { overflow-y: auto; background: #050505; }
        .admin-layout { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: #111; border: 1px solid #333; padding: 25px; border-radius: 8px; position: relative; overflow: hidden; }
        .stat-card h3 { margin: 0; color: #666; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .stat-card .value { font-size: 2.5rem; color: #fff; font-weight: 800; margin-top: 10px; }
        .stat-card.purple { border-bottom: 3px solid #a855f7; }
        .stat-card.green { border-bottom: 3px solid #0f0; }
        .stat-card.red { border-bottom: 3px solid #f00; }

        /* Table */
        .table-container { background: #111; border: 1px solid #333; border-radius: 8px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; color: #ddd; }
        th { background: #1a1a1a; padding: 15px; text-align: left; color: #a855f7; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid #333; }
        td { padding: 15px; border-bottom: 1px solid #222; font-size: 0.9rem; }
        tr:hover { background: #161616; }
        
        /* Badges */
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; text-transform: uppercase; }
        .badge-pro { background: rgba(168, 85, 247, 0.2); color: #a855f7; border: 1px solid #a855f7; }
        .badge-free { background: rgba(255, 255, 255, 0.1); color: #888; border: 1px solid #444; }
        .badge-admin { background: rgba(0, 255, 0, 0.2); color: #0f0; border: 1px solid #0f0; }

        /* Buttons */
        .btn-action { cursor: pointer; border: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 0.7rem; transition: 0.2s; text-transform: uppercase; }
        .btn-grant { background: #2a0a3d; color: #fff; border: 1px solid #a855f7; }
        .btn-grant:hover { background: #a855f7; color: #000; }
        .btn-revoke { background: #300; color: #f88; border: 1px solid #800; }
        .btn-revoke:hover { background: #f00; color: #fff; }

        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .back-btn { color: #888; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .back-btn:hover { color: #fff; }
    </style>
</head>
<body>

    <div class="admin-layout">
        <div class="top-nav">
            <a href="/studio" class="back-btn">← Return to Neural Link</a>
            <div class="zt-logo"><img src="/assets/img/logo_nobg.png" alt="Z" style="height:25px;"> COMMAND <span class="pro-badge" style="background:#0f0; color:#000; border-color:#0f0;">GOD MODE</span></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card purple">
                <h3>Total Users</h3>
                <div class="value" id="stat-users">0</div>
            </div>
            <div class="stat-card green">
                <h3>Active Sovereigns</h3>
                <div class="value" id="stat-pro">0</div>
            </div>
            <div class="stat-card red">
                <h3>System Load</h3>
                <div class="value">NOMINAL</div>
            </div>
        </div>

        <h2 style="color:#fff; margin-bottom:20px; font-size:1.2rem;">USER DATABASE</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Identity (Email)</th>
                        <th>Role</th>
                        <th>Access Level</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="userList">
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#666;">Scanning User Database...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    const ADMIN_CSRF_TOKEN = <?= json_encode(zt_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    async function loadData() {
        try {
            const res = await fetch('/api_admin.php?action=list');
            if(res.status === 403) { window.location.href = '/login'; return; }
            
            const users = await res.json();
            
            // Calc Stats
            document.getElementById('stat-users').innerText = users.length;
            document.getElementById('stat-pro').innerText = users.filter(u => u.subscription_status === 'active').length;

            // Render Table
            const tbody = document.getElementById('userList');
            tbody.innerHTML = users.map(u => {
                const isPro = u.subscription_status === 'active';
                const isAdmin = u.role === 'admin';
                let badge = isPro ? '<span class="badge badge-pro">SOVEREIGN</span>' : '<span class="badge badge-free">OPERATOR</span>';
                if(isAdmin) badge = '<span class="badge badge-admin">ADMIN</span>';

                return `
                <tr>
                    <td>#${u.id}</td>
                    <td style="color:#fff;">${u.email}</td>
                    <td>${u.role.toUpperCase()}</td>
                    <td>${badge}</td>
                    <td>
                        ${!isAdmin ? `
                            ${!isPro ? 
                                `<button class="btn-action btn-grant" onclick="setStatus(${u.id}, 'active')">GRANT PRO</button>` : 
                                `<button class="btn-action btn-revoke" onclick="setStatus(${u.id}, 'free')">REVOKE</button>`
                            }
                        ` : '<span style="color:#444;">--</span>'}
                    </td>
                </tr>
                `;
            }).join('');
        } catch(e) {
            console.error(e);
        }
    }

    async function setStatus(id, status) {
        if(!confirm("Change user status to " + status.toUpperCase() + "?")) return;
        await fetch('/api_admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id, status: status, csrf_token: ADMIN_CSRF_TOKEN })
        });
        loadData();
    }

    loadData();
    </script>

</body>
</html>
