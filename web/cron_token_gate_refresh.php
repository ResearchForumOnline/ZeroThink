<?php
/**
 * ZeroThink token-holder Pro monitor.
 *
 * Runs from cron. It refreshes wallets that previously proved ownership and
 * removes token-holder Pro when the verified wallet falls below the USD floor.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('TOKEN_GATE_LIBRARY_MODE', true);
$_SERVER['REQUEST_METHOD'] = 'CLI';
require_once __DIR__ . '/api_token_gate.php';

set_time_limit(300);

$lock = fopen(sys_get_temp_dir() . '/zerothink-token-gate-refresh.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo json_encode([
        'status' => 'skipped',
        'reason' => 'already_running',
        'checked_at' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

tg_ensure_tables();

$db = db();
$mint = zerothink_token_gate_mint();
$prefix = zerothink_token_gate_subscription_prefix();
$limit = max(1, min(250, (int) (getenv('TOKEN_GATE_CRON_LIMIT') ?: 80)));
$summary = [
    'status' => 'success',
    'checked_at' => gmdate('c'),
    'checked' => 0,
    'kept' => 0,
    'revoked' => 0,
    'errors' => 0,
    'expired_leases' => 0,
    'wallets' => [],
];

try {
    $expire = $db->prepare("
        UPDATE users
        SET plan = 'free',
            is_pro = 0,
            subscription_status = 'cancelled',
            subscription_id = NULL,
            subscription_end = NULL
        WHERE subscription_id LIKE ?
          AND plan <> 'god_mode'
          AND (subscription_end IS NULL OR subscription_end < NOW())
    ");
    $expire->execute([$prefix . '%']);
    $summary['expired_leases'] = (int) $expire->rowCount();

    $mark_expired = $db->prepare("
        UPDATE token_gate_wallets
        SET status = 'expired',
            updated_at = NOW()
        WHERE token_mint = ?
          AND status = 'active'
          AND access_expires_at IS NOT NULL
          AND access_expires_at < NOW()
    ");
    $mark_expired->execute([$mint]);

    $cleanup = [
        'old_challenges' => "DELETE FROM token_gate_challenges WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)",
        'old_cache' => "DELETE FROM token_gate_cache WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)",
        'old_audit' => "DELETE FROM token_gate_audit WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)",
    ];
    $summary['cleaned'] = [];
    foreach ($cleanup as $label => $sql) {
        try {
            $deleted = $db->exec($sql);
            $summary['cleaned'][$label] = (int) $deleted;
        } catch (Throwable $e) {
            $summary['cleaned'][$label] = 0;
        }
    }

    $stmt = $db->prepare("
        SELECT w.*, u.subscription_id, u.subscription_end
        FROM token_gate_wallets w
        INNER JOIN users u ON u.id = w.user_id
        WHERE w.token_mint = ?
          AND u.subscription_id = CONCAT(?, w.wallet)
          AND u.plan <> 'god_mode'
          AND (
              w.last_checked_at IS NULL
              OR w.last_checked_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
          )
        ORDER BY COALESCE(w.last_checked_at, '1970-01-01') ASC
        LIMIT ?
    ");
    $stmt->bindValue(1, $mint, PDO::PARAM_STR);
    $stmt->bindValue(2, $prefix, PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($wallets as $row) {
        $user_id = (int) ($row['user_id'] ?? 0);
        $wallet = (string) ($row['wallet'] ?? '');
        if ($user_id <= 0 || !tg_valid_solana_pubkey($wallet)) {
            continue;
        }

        $summary['checked']++;
        $before = [
            'status' => (string) ($row['status'] ?? ''),
            'value_usd' => (float) ($row['last_value_usd'] ?? 0),
            'access_expires_at' => (string) ($row['access_expires_at'] ?? ''),
        ];

        $assessment = tg_assess_wallet($wallet);
        if (empty($assessment['ok'])) {
            $summary['errors']++;
            $reason = (string) ($assessment['reason'] ?? 'refresh_failed');
            $err = $db->prepare("UPDATE token_gate_wallets SET last_error = ?, last_checked_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1");
            $err->execute([substr($reason, 0, 240), (int) $row['id']]);
            tg_audit($user_id, $wallet, 'cron_refresh_error', [
                'reason' => $reason,
                'before' => $before,
            ]);
            $summary['wallets'][] = [
                'wallet' => substr($wallet, 0, 4) . '...' . substr($wallet, -4),
                'result' => 'error',
                'reason' => $reason,
            ];
            continue;
        }

        $grant = tg_grant_or_revoke($user_id, $wallet, $assessment);
        $eligible = !empty($assessment['eligible']);
        if ($eligible) {
            $summary['kept']++;
            $event = 'cron_access_kept';
            $result = 'kept';
        } else {
            $summary['revoked']++;
            $event = 'cron_access_revoked';
            $result = 'revoked';
        }

        tg_audit($user_id, $wallet, $event, [
            'before' => $before,
            'balance' => (float) ($assessment['balance'] ?? 0),
            'price_usd' => (float) ($assessment['price_usd'] ?? 0),
            'liquidity_usd' => (float) ($assessment['liquidity_usd'] ?? 0),
            'volume_24h_usd' => (float) ($assessment['volume_24h_usd'] ?? 0),
            'value_usd' => (float) ($assessment['value_usd'] ?? 0),
            'min_usd' => zerothink_token_gate_min_usd(),
            'grant' => $grant,
        ]);

        $summary['wallets'][] = [
            'wallet' => substr($wallet, 0, 4) . '...' . substr($wallet, -4),
            'result' => $result,
            'value_usd' => round((float) ($assessment['value_usd'] ?? 0), 4),
            'expires_at' => $grant['expires_at'] ?? null,
        ];
    }
} catch (Throwable $e) {
    $summary['status'] = 'error';
    $summary['message'] = $e->getMessage();
}

echo json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
?>
