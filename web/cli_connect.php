<?php
require_once __DIR__ . '/app/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

$user_code = cli_normalize_user_code($_GET['user_code'] ?? $_POST['user_code'] ?? '');
$return_to = '/cli/connect?user_code=' . rawurlencode($user_code);
$record = $user_code !== '' ? cli_lookup_device_request($user_code, true) : null;
$auth_user = request_user_record();
$logged_in = (int) ($auth_user['id'] ?? 0) > 0;
$csrf_token = zt_csrf_token();

if (!$logged_in && $user_code !== '') {
    header('Location: /auth/google?return=' . rawurlencode($return_to));
    exit;
}

$flash = '';
$flash_type = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_code !== '' && $logged_in) {
    $intent = strtolower(trim((string) ($_POST['intent'] ?? '')));
    $content_type = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    $valid_form_type = strpos($content_type, 'application/x-www-form-urlencoded') === 0
        || strpos($content_type, 'multipart/form-data') === 0;
    if (!$valid_form_type || !zt_csrf_validate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $flash = 'This pairing form expired or came from an untrusted page. Refresh and try again.';
        $flash_type = 'error';
    } elseif ($intent === 'approve') {
        $approved = cli_approve_device_request($user_code, (int) $auth_user['id']);
        $record = cli_lookup_device_request($user_code, true);
        if (!empty($approved['ok'])) {
            $flash = 'ZeroThink CLI is approved for this account. Return to the terminal and the session will finish pairing automatically.';
            $flash_type = 'success';
        } else {
            $flash = 'Approval failed: ' . str_replace('_', ' ', (string) ($approved['error'] ?? 'unknown_error'));
            $flash_type = 'error';
        }
    } elseif ($intent === 'deny') {
        $denied = cli_deny_device_request($user_code, (int) $auth_user['id']);
        $record = cli_lookup_device_request($user_code, true);
        $flash = !empty($denied['ok'])
            ? 'That CLI request was denied.'
            : 'Unable to deny this request right now.';
        $flash_type = !empty($denied['ok']) ? 'info' : 'error';
    }
}

function status_badge($status) {
    $status = strtolower((string) $status);
    $map = [
        'pending' => ['Pending', '#77e4ff'],
        'approved' => ['Approved', '#74e2a3'],
        'used' => ['Connected', '#f5d37c'],
        'denied' => ['Denied', '#ff8f9d'],
        'expired' => ['Expired', '#ffb36c'],
    ];
    return $map[$status] ?? ['Unknown', '#97a6ba'];
}

[$status_label, $status_color] = status_badge($record['status'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZeroThink CLI Connect</title>
    <link rel="icon" type="image/png" href="/assets/img/logo_nobg.png">
    <style>
        :root {
            --bg: #05070b;
            --panel: rgba(10, 16, 23, 0.9);
            --line: rgba(255,255,255,0.09);
            --text: #ecf5ff;
            --muted: #92a5bb;
            --cyan: #77e4ff;
            --gold: #f4d17f;
            --green: #74e2a3;
            --red: #ff8f9d;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, system-ui, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(119, 228, 255, 0.16), transparent 26%),
                radial-gradient(circle at bottom right, rgba(244, 209, 127, 0.12), transparent 24%),
                linear-gradient(180deg, #04070b 0%, #09111a 100%);
        }
        .shell {
            max-width: 760px;
            margin: 0 auto;
            padding: 28px 16px 44px;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 24px;
            box-shadow: 0 30px 90px rgba(0,0,0,0.3);
            backdrop-filter: blur(20px);
        }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.2em;
            font-size: 0.76rem;
            color: var(--cyan);
            margin-bottom: 10px;
        }
        h1 {
            margin: 0 0 10px;
            font-size: clamp(2rem, 5vw, 3.3rem);
            line-height: 0.95;
            letter-spacing: -0.05em;
        }
        p {
            color: var(--muted);
            line-height: 1.7;
            font-size: 1rem;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
            font-size: 0.92rem;
            margin-bottom: 16px;
        }
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--cyan);
            box-shadow: 0 0 20px currentColor;
        }
        .code {
            font-family: "Cascadia Code", "JetBrains Mono", monospace;
            font-size: 1.2rem;
            letter-spacing: 0.12em;
            color: var(--gold);
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(3, 7, 12, 0.92);
            margin: 16px 0;
        }
        .flash {
            margin: 16px 0;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.05);
            line-height: 1.6;
        }
        .flash.success { border-color: rgba(116, 226, 163, 0.4); color: #d7ffeb; }
        .flash.error { border-color: rgba(255, 143, 157, 0.4); color: #ffe0e5; }
        .meta {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .row span:first-child { color: var(--muted); }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
        }
        button, a.link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
            font: inherit;
        }
        .primary {
            background: linear-gradient(135deg, rgba(116, 226, 163, 0.25), rgba(119, 228, 255, 0.18));
            border-color: rgba(116, 226, 163, 0.3);
        }
        .danger {
            border-color: rgba(255, 143, 157, 0.3);
        }
        @media (max-width: 640px) {
            .card { padding: 18px; border-radius: 22px; }
            .row { flex-direction: column; }
            .actions { flex-direction: column; }
            button, a.link { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="card">
            <div class="eyebrow">CLI Pairing</div>
            <h1>Link ZeroThink CLI to your account.</h1>
            <p>This terminal client uses the same ZeroThink identity and saved provider keys as your web account. Approve once here, then the terminal can use your account without asking for a second login on every command.</p>

            <?php if ($flash !== ''): ?>
                <div class="flash <?= htmlspecialchars($flash_type, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (!$record): ?>
                <div class="flash error">That pairing request was not found. Start <strong>zerothink login</strong> again from the terminal to get a fresh code.</div>
            <?php else: ?>
                <div class="status">
                    <span class="dot" style="background: <?= htmlspecialchars($status_color, ENT_QUOTES, 'UTF-8') ?>; color: <?= htmlspecialchars($status_color, ENT_QUOTES, 'UTF-8') ?>;"></span>
                    <strong><?= htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>

                <div class="code"><?= htmlspecialchars($record['user_code'], ENT_QUOTES, 'UTF-8') ?></div>

                <div class="meta">
                    <div class="row">
                        <span>Account</span>
                        <strong><?= htmlspecialchars((string) ($auth_user['email'] ?? 'Not signed in'), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="row">
                        <span>Request label</span>
                        <strong><?= htmlspecialchars((string) ($record['label'] ?? 'ZeroThink CLI'), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="row">
                        <span>Expires</span>
                        <strong><?= htmlspecialchars((string) ($record['expires_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?> UTC</strong>
                    </div>
                </div>

                <div class="actions">
                    <?php if (($record['status'] ?? '') === 'pending'): ?>
                        <form method="post">
                            <input type="hidden" name="user_code" value="<?= htmlspecialchars($record['user_code'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="intent" value="approve">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="primary" type="submit">Approve CLI Access</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="user_code" value="<?= htmlspecialchars($record['user_code'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="intent" value="deny">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="danger" type="submit">Deny</button>
                        </form>
                    <?php endif; ?>
                    <a class="link" href="/studio">Back to Studio</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
