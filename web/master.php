<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/app/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$db = db();
$me = function_exists('current_user_record') ? current_user_record(true) : null;

if (!function_exists('is_architect_user') || !is_architect_user($me)) {
    header('Location: /studio');
    exit;
}

ensure_zerothink_api_tables();

function master_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function master_flash_set($type, $message, $extra = []) {
    $_SESSION['master_flash'] = array_merge([
        'type' => $type,
        'message' => $message,
    ], is_array($extra) ? $extra : []);
}

function master_require_csrf() {
    $expected = (string) ($_SESSION['_csrf'] ?? '');
    $sent = (string) ($_POST['csrf'] ?? '');
    if ($expected === '' || $sent === '' || !hash_equals($expected, $sent)) {
        master_flash_set('error', 'Security token expired. Refresh /master and try again.');
        header('Location: /master');
        exit;
    }
}

function master_redirect() {
    header('Location: /master');
    exit;
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

$csrf = csrf_token();
$flash = $_SESSION['master_flash'] ?? null;
unset($_SESSION['master_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    master_require_csrf();
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));
    $admin_id = (int) ($me['id'] ?? ($_SESSION['user_id'] ?? 0));

    if ($action === 'ingest_data') {
        $lines = explode("\n", (string) ($_POST['ingest_data'] ?? ''));
        $count = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) > 5) {
                $db->prepare("INSERT INTO cortex (user_id, content) VALUES (?, ?)")->execute([$admin_id, $line]);
                $count++;
            }
        }
        master_flash_set('success', "Ingested {$count} knowledge fragments into Cortex.");
        master_redirect();
    }

    if ($action === 'enable_zero' || $action === 'revoke_zero') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            if ($action === 'enable_zero') {
                $db->prepare("UPDATE users SET plan = 'pro', is_pro = 1 WHERE id = ? AND COALESCE(plan, 'free') <> 'god_mode' LIMIT 1")->execute([$user_id]);
                master_flash_set('success', 'Pro access enabled.');
            } else {
                $db->prepare("UPDATE users SET plan = 'free', is_pro = 0 WHERE id = ? AND COALESCE(plan, 'free') <> 'god_mode' LIMIT 1")->execute([$user_id]);
                zerothink_api_revoke_user_tokens($user_id, $admin_id, 'pro revoked from master');
                master_flash_set('success', 'Pro access revoked and active ZeroThink API keys were removed.');
            }
        }
        master_redirect();
    }

    if ($action === 'delete_user') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if ($user_id > 0 && $user_id !== $admin_id && !in_array($user_id, [1, 4], true)) {
            $db->prepare("DELETE FROM users WHERE id = ? AND COALESCE(plan, 'free') <> 'god_mode' LIMIT 1")->execute([$user_id]);
            master_flash_set('success', 'User deleted.');
        } else {
            master_flash_set('error', 'Protected admin users cannot be deleted here.');
        }
        master_redirect();
    }

    if ($action === 'create_zt_api') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($user_id <= 0 && $email !== '') {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user_id = (int) $stmt->fetchColumn();
        }

        if ($user_id <= 0) {
            master_flash_set('error', 'Could not find that user account.');
            master_redirect();
        }

        $user_stmt = $db->prepare("SELECT id, email, plan FROM users WHERE id = ? LIMIT 1");
        $user_stmt->execute([$user_id]);
        $target = $user_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            master_flash_set('error', 'Could not find that user account.');
            master_redirect();
        }

        if (strtolower((string) ($target['plan'] ?? '')) !== 'god_mode') {
            $db->prepare("UPDATE users SET plan = 'pro', is_pro = 1 WHERE id = ? LIMIT 1")->execute([$user_id]);
        }

        $label = trim((string) ($_POST['label'] ?? '')) ?: 'ZeroThink API';
        $issued = zerothink_api_issue_token($user_id, $label, $admin_id, 365);
        if (!empty($issued['ok'])) {
            master_flash_set('success', 'ZeroThink API key created. Copy it now; it will not be shown again.', [
                'token' => $issued['token'],
                'hint' => $issued['hint'],
                'expires_at' => $issued['expires_at'],
                'email' => (string) ($target['email'] ?? ''),
            ]);
        } else {
            master_flash_set('error', 'Could not create the ZeroThink API key.');
        }
        master_redirect();
    }

    if ($action === 'revoke_zt_api_user') {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $count = zerothink_api_revoke_user_tokens($user_id, $admin_id, 'revoked from master');
        master_flash_set('success', "Revoked {$count} active ZeroThink API key(s).");
        master_redirect();
    }
}

$users = $db->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$user_ids = [];
foreach ($users as $user_row) {
    $user_ids[] = (int) ($user_row['id'] ?? 0);
}
$api_summary = zerothink_api_token_summary_for_users($user_ids);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master | ZeroThink</title>
    <link rel="stylesheet" href="/assets/css/index.css?v=master_api_keys">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body{ background:#020210; color:#ccc; padding:40px; overflow-y:auto; font-family:Inter,system-ui,sans-serif; }
        .wrap{ max-width:1280px; margin:0 auto; }
        .card{ background:rgba(17,17,17,.86); padding:20px; border:1px solid #333; margin-bottom:24px; border-radius:8px; backdrop-filter:blur(10px); }
        .grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; }
        table{ width:100%; border-collapse:collapse; background:rgba(0,0,0,.5); border:1px solid #333; }
        th,td{ padding:13px; border-bottom:1px solid #222; text-align:left; vertical-align:top; }
        th{ color:#888; text-transform:uppercase; font-size:.68rem; letter-spacing:1px; }
        tr:hover{ background:rgba(168,85,247,.05); }
        input,textarea{ width:100%; background:#050507; border:1px solid #333; color:#dff; padding:10px 12px; border-radius:6px; font:inherit; }
        textarea{ min-height:130px; font-family:monospace; }
        .btn{ display:inline-flex; align-items:center; gap:6px; border:1px solid #333; border-radius:6px; background:#111; color:#eee; padding:8px 12px; text-decoration:none; cursor:pointer; font-weight:700; font-size:.72rem; }
        .btn-green{ color:#8fffc4; border-color:#1f7d56; }
        .btn-warn{ color:#ffd28a; border-color:#87602a; }
        .btn-red{ color:#ff9ba9; border-color:#77313b; }
        .btn-purple{ color:#e3c8ff; border-color:#7c3fb2; }
        .muted{ color:#777; font-size:.78rem; line-height:1.5; }
        .flash{ margin:18px 0; padding:14px 16px; border-radius:8px; border:1px solid #333; background:#09090f; }
        .flash.success{ color:#b9ffd2; border-color:#225e36; }
        .flash.error{ color:#ffc4cb; border-color:#73323d; }
        .token-box{ margin-top:12px; }
        .actions{ display:flex; flex-wrap:wrap; gap:8px; }
        .inline-form{ display:inline-flex; margin:0; }
        .pill{ display:inline-flex; align-items:center; border:1px solid #333; border-radius:999px; padding:4px 9px; color:#ccc; font-size:.7rem; }
    </style>
</head>
<body>
    <div class="wrap">
        <a href="/studio" style="color:#00ffff; text-decoration:none; font-size:.8rem;"><i class="fas fa-arrow-left"></i> RETURN TO STUDIO</a>
        <h1 style="color:#a855f7; letter-spacing:-1px;">MASTER LATTICE</h1>

        <?php if (is_array($flash)): ?>
            <div class="flash <?php echo master_h($flash['type'] ?? 'success'); ?>">
                <strong><?php echo master_h($flash['message'] ?? ''); ?></strong>
                <?php if (!empty($flash['token'])): ?>
                    <div class="muted" style="margin-top:8px;">Account: <?php echo master_h($flash['email'] ?? ''); ?> | Hint: <?php echo master_h($flash['hint'] ?? ''); ?> | Expires: <?php echo master_h($flash['expires_at'] ?? 'never'); ?> UTC</div>
                    <div class="token-box">
                        <input type="text" readonly value="<?php echo master_h($flash['token']); ?>" onclick="this.select();">
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="grid">
            <div class="card">
                <h3 style="margin-top:0; color:#fff;"><i class="fas fa-key"></i> Create ZeroThink API Key</h3>
                <p class="muted">Issues a hashed, Pro-only account API key. The plaintext key is shown once and never stored.</p>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo master_h($csrf); ?>">
                    <input type="hidden" name="action" value="create_zt_api">
                    <label class="muted">User email</label>
                    <input name="email" value="shafaet85@gmail.com" autocomplete="off">
                    <label class="muted" style="display:block; margin-top:10px;">Label</label>
                    <input name="label" value="Shafaet admin ZeroThink API" autocomplete="off">
                    <button class="btn btn-purple" type="submit" style="margin-top:12px;"><i class="fas fa-plus"></i> CREATE FOR EMAIL</button>
                </form>
            </div>

            <div class="card">
                <h3 style="margin-top:0; color:#fff;"><i class="fas fa-brain"></i> Knowledge Injection</h3>
                <p class="muted">Paste one fact per line. It will be indexed into the Cortex Memory.</p>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo master_h($csrf); ?>">
                    <input type="hidden" name="action" value="ingest_data">
                    <textarea name="ingest_data" placeholder="Q: What is Zero? A: Sovereign Logic..."></textarea>
                    <button class="btn btn-green" type="submit" style="margin-top:12px;"><i class="fas fa-database"></i> INGEST DATA</button>
                </form>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Plan</th>
                    <th>API Keys</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                        $uid = (int) ($u['id'] ?? 0);
                        $plan = (string) ($u['plan'] ?? 'free');
                        $summary = $api_summary[$uid] ?? ['active_count' => 0, 'latest_hint' => ''];
                    ?>
                    <tr>
                        <td>#<?php echo $uid; ?></td>
                        <td><?php echo master_h($u['email'] ?? ''); ?></td>
                        <td>
                            <span style="color:<?php echo $plan === 'god_mode' ? '#a855f7' : ($plan === 'pro' ? '#0f0' : '#fff'); ?>; font-weight:bold;">
                                <?php echo master_h(strtoupper($plan ?: 'free')); ?>
                            </span>
                        </td>
                        <td>
                            <span class="pill"><?php echo (int) $summary['active_count']; ?> active</span>
                            <?php if (!empty($summary['latest_hint'])): ?>
                                <div class="muted">Latest: <?php echo master_h($summary['latest_hint']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <form class="inline-form" method="POST">
                                    <input type="hidden" name="csrf" value="<?php echo master_h($csrf); ?>">
                                    <input type="hidden" name="action" value="create_zt_api">
                                    <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                    <input type="hidden" name="label" value="ZeroThink API">
                                    <button class="btn btn-purple" type="submit"><i class="fas fa-key"></i> CREATE API KEY</button>
                                </form>
                                <?php if ((int) $summary['active_count'] > 0): ?>
                                    <form class="inline-form" method="POST" onsubmit="return confirm('Revoke all active ZeroThink API keys for this user?');">
                                        <input type="hidden" name="csrf" value="<?php echo master_h($csrf); ?>">
                                        <input type="hidden" name="action" value="revoke_zt_api_user">
                                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                        <button class="btn btn-red" type="submit"><i class="fas fa-ban"></i> REVOKE API KEYS</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($plan !== 'god_mode'): ?>
                                    <form class="inline-form" method="POST">
                                        <input type="hidden" name="csrf" value="<?php echo master_h($csrf); ?>">
                                        <input type="hidden" name="action" value="<?php echo $plan === 'pro' ? 'revoke_zero' : 'enable_zero'; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                        <button class="btn <?php echo $plan === 'pro' ? 'btn-warn' : 'btn-green'; ?>" type="submit">
                                            <i class="fas fa-bolt"></i> <?php echo $plan === 'pro' ? 'REVOKE PRO' : 'GRANT PRO'; ?>
                                        </button>
                                    </form>
                                    <form class="inline-form" method="POST" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                        <input type="hidden" name="csrf" value="<?php echo master_h($csrf); ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                        <button class="btn btn-red" type="submit"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">SYSTEM ARCHITECT</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
