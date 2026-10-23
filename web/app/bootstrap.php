<?php
if (session_status() === PHP_SESSION_NONE) {
    $forwarded_proto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $is_https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || $forwarded_proto === 'https';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/subscription_security.php';

function has_chat_history_model_column() {
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    try {
        $stmt = db()->query("SHOW COLUMNS FROM chat_history LIKE 'model'");
        $hasColumn = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $hasColumn = false;
    }

    return $hasColumn;
}

function store_chat_history_entry($userId, $query, $response, $model = '') {
    $userId = (int) $userId;
    $query = trim((string) $query);
    $response = trim((string) $response);
    if ($userId <= 0 || $query === '' || $response === '') {
        return false;
    }

    try {
        if (has_chat_history_model_column()) {
            $stmt = db()->prepare(
                'INSERT INTO chat_history (user_id, query, response, model, timestamp) VALUES (?, ?, ?, ?, NOW())'
            );
            return $stmt->execute([$userId, $query, $response, trim((string) $model)]);
        }

        $stmt = db()->prepare(
            'INSERT INTO chat_history (user_id, query, response, timestamp) VALUES (?, ?, ?, NOW())'
        );
        return $stmt->execute([$userId, $query, $response]);
    } catch (Throwable $e) {
        error_log('ZeroThink chat history write failed.');
        return false;
    }
}

function sanitize_local_return_path($value, $fallback = '/studio') {
    $fallback = is_string($fallback) && preg_match('#^/(?!/)#', $fallback) ? $fallback : '/studio';
    $value = trim((string) $value);
    if ($value === '' || preg_match('/[\r\n\x00-\x1F\x7F]/', $value)) {
        return $fallback;
    }

    $host = 'zerothink.talktoai.org';
    if (preg_match('#^https?://#i', $value)) {
        $parts = parse_url($value);
        if (!is_array($parts) || strcasecmp((string) ($parts['host'] ?? ''), $host) !== 0) {
            return $fallback;
        }
        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $value = $path . $query;
    }

    if (!preg_match('#^/(?!/)#', $value) || strpos($value, '\\') !== false) {
        return $fallback;
    }

    return $value;
}

function zt_csrf_token() {
    $token = (string) ($_SESSION['zt_csrf_token'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['zt_csrf_token'] = $token;
    }
    return $token;
}

function zt_csrf_validate($token) {
    $expected = (string) ($_SESSION['zt_csrf_token'] ?? '');
    $token = trim((string) $token);
    return preg_match('/^[a-f0-9]{64}$/', $expected)
        && preg_match('/^[a-f0-9]{64}$/', $token)
        && hash_equals($expected, $token);
}

function current_user_record($refresh = false) {
    if (defined('ZEROTHINK_LOCAL_LAB') && ZEROTHINK_LOCAL_LAB === true) {
        return ['id'=>1, 'role'=>'user', 'plan'=>'local', 'email'=>'', 'is_pro'=>false];
    }
    static $cache = [];
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return null;
    }

    if (!$refresh && array_key_exists($uid, $cache)) {
        return $cache[$uid];
    }

    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND COALESCE(disabled, 0) = 0 LIMIT 1");
        $stmt->execute([$uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $user = null;
    }

    if ($user) {
        $_SESSION['email'] = $user['email'] ?? ($_SESSION['email'] ?? '');
        $_SESSION['role'] = $user['role'] ?? ($_SESSION['role'] ?? 'user');
        $_SESSION['plan'] = $user['plan'] ?? ($_SESSION['plan'] ?? 'free');
        $_SESSION['is_pro'] = (int) ($user['is_pro'] ?? 0);
    }

    $cache[$uid] = $user;
    return $user;
}

function request_bearer_token() {
    static $resolved = false;
    static $token = '';

    if ($resolved) {
        return $token;
    }
    $resolved = true;

    $header = '';
    foreach ([
        'HTTP_AUTHORIZATION',
        'REDIRECT_HTTP_AUTHORIZATION',
        'Authorization',
        'HTTP_X_ZEROTHINK_TOKEN',
        'HTTP_X_AUTH_TOKEN',
        'X-ZeroThink-Token',
        'X-Auth-Token',
    ] as $key) {
        if (!empty($_SERVER[$key])) {
            $header = (string) $_SERVER[$key];
            break;
        }
    }

    if ($header === '' && function_exists('apache_request_headers')) {
        foreach ((array) apache_request_headers() as $name => $value) {
            $normalized = strtolower((string) $name);
            if ($normalized === 'authorization' || $normalized === 'x-zerothink-token' || $normalized === 'x-auth-token') {
                $header = (string) $value;
                break;
            }
        }
    }

    if (preg_match('/Bearer\s+([A-Za-z0-9._:-]+)/i', $header, $match)) {
        $token = trim((string) $match[1]);
    } elseif (preg_match('/^[A-Za-z0-9._:-]{24,}$/', trim($header))) {
        $token = trim((string) $header);
    }

    return $token;
}

function cli_token_hash($token) {
    return hash('sha256', trim((string) $token));
}

function cli_token_hint($token) {
    $token = trim((string) $token);
    if ($token === '') {
        return '';
    }
    return substr($token, 0, 10) . '...' . substr($token, -6);
}

function cli_generate_secret($prefix = 'ztcli_', $bytes = 24) {
    return $prefix . rtrim(strtr(base64_encode(random_bytes(max(16, (int) $bytes))), '+/', '-_'), '=');
}

function cli_normalize_user_code($raw) {
    $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $raw));
    if ($clean === '') {
        return '';
    }

    return implode('-', str_split(substr($clean, 0, 8), 4));
}

function cli_generate_user_code() {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return cli_normalize_user_code($code);
}

function ensure_cli_tables() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $tables = [
        "CREATE TABLE IF NOT EXISTS cli_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            token_hint VARCHAR(32) NOT NULL,
            label VARCHAR(120) NULL,
            client_meta TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            expires_at DATETIME NULL,
            revoked_at DATETIME NULL,
            UNIQUE KEY uniq_cli_token_hash (token_hash),
            KEY idx_cli_tokens_user (user_id),
            KEY idx_cli_tokens_active (revoked_at, expires_at)
        )",
        "CREATE TABLE IF NOT EXISTS cli_device_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_code VARCHAR(96) NOT NULL,
            user_code VARCHAR(16) NOT NULL,
            user_id INT NULL,
            label VARCHAR(120) NULL,
            client_meta TEXT NULL,
            request_ip_hash CHAR(64) NULL,
            status ENUM('pending','approved','denied','used','expired') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            approved_at DATETIME NULL,
            consumed_at DATETIME NULL,
            token_id INT NULL,
            UNIQUE KEY uniq_cli_device_code (device_code),
            UNIQUE KEY uniq_cli_user_code (user_code),
            KEY idx_cli_device_status (status, expires_at),
            KEY idx_cli_device_user (user_id),
            KEY idx_cli_device_ip_created (request_ip_hash, created_at)
        )",
    ];

    foreach ($tables as $sql) {
        try {
            db()->exec($sql);
        } catch (Throwable $e) {
        }
    }

    // Add the rate-limit key to installations created before this hardening.
    // The migration is additive and leaves all existing device records intact.
    try {
        $column = db()->query("SHOW COLUMNS FROM cli_device_codes LIKE 'request_ip_hash'");
        if (!$column || !$column->fetch(PDO::FETCH_ASSOC)) {
            db()->exec("ALTER TABLE cli_device_codes ADD COLUMN request_ip_hash CHAR(64) NULL AFTER client_meta");
        }
        $index = db()->query("SHOW INDEX FROM cli_device_codes WHERE Key_name = 'idx_cli_device_ip_created'");
        if (!$index || !$index->fetch(PDO::FETCH_ASSOC)) {
            db()->exec("ALTER TABLE cli_device_codes ADD KEY idx_cli_device_ip_created (request_ip_hash, created_at)");
        }
    } catch (Throwable $e) {
        error_log('ZeroThink CLI rate-limit schema migration failed.');
    }
}

function cli_client_ip() {
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!filter_var($remote, FILTER_VALIDATE_IP)) {
        $remote = '';
    }

    // The production Nginx proxy overwrites X-Real-IP. Trust it only when the
    // immediate peer is loopback; direct backend clients cannot spoof it.
    if (in_array($remote, ['127.0.0.1', '::1'], true)) {
        $forwarded = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
            return $forwarded;
        }
    }

    return $remote !== '' ? $remote : 'unknown';
}

function cli_request_meta($fallback = []) {
    $meta = [];
    foreach ((array) $fallback as $key => $value) {
        $safe_key = preg_replace('/[^a-z0-9_.-]/i', '', (string) $key);
        if ($safe_key !== '' && count($meta) < 16) {
            $meta[$safe_key] = substr(trim((string) $value), 0, 512);
        }
    }
    $meta['ip'] = cli_client_ip();
    $meta['ua'] = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
    return json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function cli_named_lock(PDO $db, $name, $timeout_seconds = 2) {
    $stmt = $db->prepare('SELECT GET_LOCK(?, ?)');
    $stmt->execute([substr((string) $name, 0, 64), max(0, (int) $timeout_seconds)]);
    return (int) $stmt->fetchColumn() === 1;
}

function cli_named_unlock(PDO $db, $name) {
    try {
        $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([substr((string) $name, 0, 64)]);
    } catch (Throwable $e) {
    }
}

function cli_start_device_request($label = '', $client_meta = []) {
    ensure_cli_tables();
    $label = substr(trim((string) $label) ?: 'ZeroThink CLI', 0, 120);
    $ip_hash = hash('sha256', cli_client_ip());
    $db = db();
    $global_lock = 'zt_cli_device_start_global';
    $ip_lock = 'zt_cli_device_' . substr($ip_hash, 0, 36);
    $global_locked = false;
    $ip_locked = false;

    try {
        $global_locked = cli_named_lock($db, $global_lock, 2);
        $ip_locked = $global_locked && cli_named_lock($db, $ip_lock, 2);
        if (!$global_locked || !$ip_locked) {
            return ['ok' => false, 'error' => 'rate_limit_unavailable'];
        }

        $global_count = (int) $db->query("SELECT COUNT(*) FROM cli_device_codes WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)")->fetchColumn();
        $ip_minute = $db->prepare("SELECT COUNT(*) FROM cli_device_codes WHERE request_ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $ip_minute->execute([$ip_hash]);
        $ip_window = $db->prepare("SELECT COUNT(*) FROM cli_device_codes WHERE request_ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $ip_window->execute([$ip_hash]);
        if ($global_count >= 300 || (int) $ip_minute->fetchColumn() >= 12 || (int) $ip_window->fetchColumn() >= 50) {
            return ['ok' => false, 'error' => 'rate_limited'];
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $device_code = cli_generate_secret('ztdev_', 24);
            $user_code = cli_generate_user_code();
            $expires_at = gmdate('Y-m-d H:i:s', time() + 900);
            try {
                $stmt = $db->prepare("INSERT INTO cli_device_codes (device_code, user_code, label, client_meta, request_ip_hash, status, expires_at) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
                $stmt->execute([$device_code, $user_code, $label, cli_request_meta($client_meta), $ip_hash, $expires_at]);
                return [
                    'ok' => true,
                    'device_code' => $device_code,
                    'user_code' => $user_code,
                    'expires_at' => $expires_at,
                    'interval' => 3,
                ];
            } catch (Throwable $e) {
            }
        }

        return ['ok' => false, 'error' => 'device_code_generation_failed'];
    } catch (Throwable $e) {
        error_log('ZeroThink CLI device start failed.');
        return ['ok' => false, 'error' => 'device_start_failed'];
    } finally {
        if ($ip_locked) {
            cli_named_unlock($db, $ip_lock);
        }
        if ($global_locked) {
            cli_named_unlock($db, $global_lock);
        }
    }
}

function cli_lookup_device_request($lookup, $by_user_code = false) {
    ensure_cli_tables();
    $lookup = $by_user_code ? cli_normalize_user_code($lookup) : trim((string) $lookup);
    if ($lookup === '') {
        return null;
    }

    try {
        $column = $by_user_code ? 'user_code' : 'device_code';
        $stmt = db()->prepare("SELECT * FROM cli_device_codes WHERE {$column} = ? LIMIT 1");
        $stmt->execute([$lookup]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row && !empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time() && $row['status'] === 'pending') {
            $expire = db()->prepare("UPDATE cli_device_codes SET status = 'expired' WHERE id = ? LIMIT 1");
            $expire->execute([(int) $row['id']]);
            $row['status'] = 'expired';
        }
        return $row;
    } catch (Throwable $e) {
        return null;
    }
}

function cli_issue_access_token($user_id, $label = '', $client_meta = [], $ttl_days = 180) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'user_required'];
    }

    ensure_cli_tables();
    $plain = cli_generate_secret('ztcli_', 36);
    $hash = cli_token_hash($plain);
    $hint = cli_token_hint($plain);
    $expires_at = gmdate('Y-m-d H:i:s', time() + max(30, (int) $ttl_days) * 86400);

    try {
        $stmt = db()->prepare("INSERT INTO cli_tokens (user_id, token_hash, token_hint, label, client_meta, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $hash,
            $hint,
            trim((string) $label) ?: 'ZeroThink CLI',
            cli_request_meta($client_meta),
            $expires_at,
        ]);
        return [
            'ok' => true,
            'token' => $plain,
            'hint' => $hint,
            'expires_at' => $expires_at,
            'token_id' => (int) db()->lastInsertId(),
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'token_insert_failed'];
    }
}

function cli_approve_device_request($user_code, $user_id) {
    ensure_cli_tables();
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'user_required'];
    }

    $user_code = cli_normalize_user_code($user_code);
    if ($user_code === '') {
        return ['ok' => false, 'error' => 'code_not_found'];
    }
    try {
        $stmt = db()->prepare("UPDATE cli_device_codes
            SET user_id = ?, status = 'approved', approved_at = NOW()
            WHERE user_code = ? AND status = 'pending' AND user_id IS NULL
              AND consumed_at IS NULL AND expires_at > NOW()
            LIMIT 1");
        $stmt->execute([$user_id, $user_code]);
        if ($stmt->rowCount() !== 1) {
            $row = cli_lookup_device_request($user_code, true);
            $status = (string) ($row['status'] ?? 'not_found');
            return ['ok' => false, 'error' => $status === 'not_found' ? 'code_not_found' : 'code_' . $status];
        }
        return ['ok' => true, 'row' => cli_lookup_device_request($user_code, true)];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'approval_failed'];
    }
}

function cli_deny_device_request($user_code, $user_id = 0) {
    ensure_cli_tables();
    $user_code = cli_normalize_user_code($user_code);
    if ($user_code === '') {
        return ['ok' => false, 'error' => 'code_not_found'];
    }

    try {
        $stmt = db()->prepare("UPDATE cli_device_codes
            SET user_id = ?, status = 'denied', consumed_at = NOW()
            WHERE user_code = ? AND status = 'pending' AND user_id IS NULL
              AND consumed_at IS NULL AND expires_at > NOW()
            LIMIT 1");
        $stmt->execute([(int) $user_id ?: null, $user_code]);
        if ($stmt->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'code_not_pending'];
        }
        return ['ok' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'deny_failed'];
    }
}

function cli_consume_device_request($device_code) {
    ensure_cli_tables();
    $device_code = trim((string) $device_code);
    if ($device_code === '') {
        return ['ok' => false, 'error' => 'device_not_found'];
    }
    $db = db();
    try {
        $db->beginTransaction();
        $lookup = $db->prepare("SELECT * FROM cli_device_codes WHERE device_code = ? LIMIT 1 FOR UPDATE");
        $lookup->execute([$device_code]);
        $row = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'device_not_found'];
        }

        if (in_array((string) $row['status'], ['pending', 'approved'], true)
            && strtotime((string) ($row['expires_at'] ?? '')) <= time()) {
            $expire = $db->prepare("UPDATE cli_device_codes SET status = 'expired' WHERE id = ? AND status IN ('pending','approved') LIMIT 1");
            $expire->execute([(int) $row['id']]);
            $db->commit();
            return ['ok' => false, 'error' => 'expired'];
        }
        if (in_array((string) $row['status'], ['expired', 'denied', 'used'], true)) {
            $db->rollBack();
            return ['ok' => false, 'error' => (string) $row['status']];
        }
        if ((string) $row['status'] !== 'approved') {
            $db->rollBack();
            return ['ok' => false, 'error' => 'authorization_pending'];
        }
        if (!empty($row['consumed_at'])) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'already_used'];
        }

        $user_id = (int) ($row['user_id'] ?? 0);
        if ($user_id <= 0) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'no_user_bound'];
        }

        $user_stmt = $db->prepare("SELECT id, email, role, plan, is_pro
            FROM users WHERE id = ? AND COALESCE(disabled, 0) = 0 LIMIT 1 FOR UPDATE");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$user) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'user_unavailable'];
        }

        $issued = cli_issue_access_token($user_id, (string) ($row['label'] ?? 'ZeroThink CLI'), ['device_code' => (string) ($row['user_code'] ?? '')]);
        if (empty($issued['ok'])) {
            throw new RuntimeException('CLI token issue failed.');
        }

        $consume = $db->prepare("UPDATE cli_device_codes
            SET status = 'used', consumed_at = NOW(), token_id = ?
            WHERE id = ? AND status = 'approved' AND consumed_at IS NULL
            LIMIT 1");
        $consume->execute([(int) ($issued['token_id'] ?? 0), (int) $row['id']]);
        if ($consume->rowCount() !== 1) {
            throw new RuntimeException('CLI device code was consumed concurrently.');
        }
        $db->commit();

        return [
            'ok' => true,
            'token' => $issued['token'],
            'token_hint' => $issued['hint'],
            'expires_at' => $issued['expires_at'],
            'user' => $user,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('ZeroThink CLI device consume failed.');
        return ['ok' => false, 'error' => 'consume_failed'];
    }
}

function cli_authenticate_token_user($token) {
    ensure_cli_tables();
    $hash = cli_token_hash($token);
    if ($hash === cli_token_hash('')) {
        return null;
    }

    try {
        $stmt = db()->prepare("
            SELECT u.*, ct.id AS cli_token_id, ct.label AS cli_label
            FROM cli_tokens ct
            INNER JOIN users u ON u.id = ct.user_id
            WHERE ct.token_hash = ?
              AND ct.revoked_at IS NULL
              AND (ct.expires_at IS NULL OR ct.expires_at > NOW())
              AND COALESCE(u.disabled, 0) = 0
            LIMIT 1
        ");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row) {
            if (function_exists('token_gate_sync_user_row')) {
                $row = token_gate_sync_user_row($row);
            }
            $touch = db()->prepare("UPDATE cli_tokens SET last_used_at = NOW() WHERE id = ? LIMIT 1");
            $touch->execute([(int) $row['cli_token_id']]);
        }
        return $row;
    } catch (Throwable $e) {
        return null;
    }
}

function revoke_cli_token($token, $user_id = 0) {
    ensure_cli_tables();
    $hash = cli_token_hash($token);
    if ($hash === cli_token_hash('')) {
        return false;
    }

    try {
        if ((int) $user_id > 0) {
            $stmt = db()->prepare("UPDATE cli_tokens SET revoked_at = NOW() WHERE token_hash = ? AND user_id = ? AND revoked_at IS NULL LIMIT 1");
            $stmt->execute([$hash, (int) $user_id]);
        } else {
            $stmt = db()->prepare("UPDATE cli_tokens SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL LIMIT 1");
            $stmt->execute([$hash]);
        }
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function zerothink_api_token_hash($token) {
    return hash('sha256', 'zerothink-api:' . trim((string) $token));
}

function zerothink_api_token_hint($token) {
    $token = trim((string) $token);
    if ($token === '') {
        return '';
    }
    return substr($token, 0, 10) . '...' . substr($token, -6);
}

function zerothink_api_generate_secret($prefix = 'ztapi_', $bytes = 36) {
    return $prefix . rtrim(strtr(base64_encode(random_bytes(max(24, (int) $bytes))), '+/', '-_'), '=');
}

function ensure_zerothink_api_tables() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        db()->exec("CREATE TABLE IF NOT EXISTS zerothink_api_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            token_hint VARCHAR(32) NOT NULL,
            label VARCHAR(120) NULL,
            scopes VARCHAR(255) NULL,
            client_meta TEXT NULL,
            created_by_user_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            expires_at DATETIME NULL,
            revoked_at DATETIME NULL,
            revoked_by_user_id INT NULL,
            revoke_reason VARCHAR(255) NULL,
            UNIQUE KEY uniq_zerothink_api_token_hash (token_hash),
            KEY idx_zerothink_api_tokens_user (user_id),
            KEY idx_zerothink_api_tokens_active (revoked_at, expires_at),
            KEY idx_zerothink_api_tokens_created_by (created_by_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
    }
}

function zerothink_api_issue_token($user_id, $label = '', $created_by_user_id = 0, $ttl_days = 365) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['ok' => false, 'error' => 'user_required'];
    }

    ensure_zerothink_api_tables();
    $plain = zerothink_api_generate_secret('ztapi_', 36);
    $hash = zerothink_api_token_hash($plain);
    $hint = zerothink_api_token_hint($plain);
    $ttl_days = (int) $ttl_days;
    $expires_at = $ttl_days > 0 ? gmdate('Y-m-d H:i:s', time() + max(1, $ttl_days) * 86400) : null;

    try {
        $stmt = db()->prepare("INSERT INTO zerothink_api_tokens
            (user_id, token_hash, token_hint, label, scopes, client_meta, created_by_user_id, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $hash,
            $hint,
            trim((string) $label) ?: 'ZeroThink API',
            'chat,models,tools',
            cli_request_meta(['issued_by' => 'master']),
            (int) $created_by_user_id ?: null,
            $expires_at,
        ]);
        return [
            'ok' => true,
            'token' => $plain,
            'hint' => $hint,
            'expires_at' => $expires_at,
            'token_id' => (int) db()->lastInsertId(),
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'token_insert_failed'];
    }
}

function zerothink_api_list_tokens($user_id) {
    ensure_zerothink_api_tables();
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }
    try {
        $stmt = db()->prepare("SELECT id, token_hint, label, scopes, created_at, last_used_at, expires_at, revoked_at, revoke_reason
            FROM zerothink_api_tokens
            WHERE user_id = ?
            ORDER BY revoked_at IS NULL DESC, id DESC
            LIMIT 20");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function zerothink_api_token_summary_for_users($user_ids) {
    ensure_zerothink_api_tables();
    $ids = [];
    foreach ((array) $user_ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = db()->prepare("SELECT user_id,
                SUM(CASE WHEN revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) AS active_count,
                MAX(CASE WHEN revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) THEN token_hint ELSE NULL END) AS latest_hint
            FROM zerothink_api_tokens
            WHERE user_id IN ($placeholders)
            GROUP BY user_id");
        $stmt->execute(array_values($ids));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }

    $summary = [];
    foreach ($rows as $row) {
        $summary[(int) ($row['user_id'] ?? 0)] = [
            'active_count' => (int) ($row['active_count'] ?? 0),
            'latest_hint' => (string) ($row['latest_hint'] ?? ''),
        ];
    }
    return $summary;
}

function zerothink_api_revoke_token_id($token_id, $user_id = 0, $revoked_by_user_id = 0, $reason = '') {
    ensure_zerothink_api_tables();
    $token_id = (int) $token_id;
    if ($token_id <= 0) {
        return false;
    }
    try {
        $sql = "UPDATE zerothink_api_tokens
            SET revoked_at = NOW(), revoked_by_user_id = ?, revoke_reason = ?
            WHERE id = ? AND revoked_at IS NULL";
        $params = [(int) $revoked_by_user_id ?: null, substr(trim((string) $reason), 0, 255), $token_id];
        if ((int) $user_id > 0) {
            $sql .= " AND user_id = ?";
            $params[] = (int) $user_id;
        }
        $sql .= " LIMIT 1";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function zerothink_api_revoke_user_tokens($user_id, $revoked_by_user_id = 0, $reason = '') {
    ensure_zerothink_api_tables();
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }
    try {
        $stmt = db()->prepare("UPDATE zerothink_api_tokens
            SET revoked_at = NOW(), revoked_by_user_id = ?, revoke_reason = ?
            WHERE user_id = ? AND revoked_at IS NULL");
        $stmt->execute([
            (int) $revoked_by_user_id ?: null,
            substr(trim((string) $reason), 0, 255),
            $user_id,
        ]);
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

function zerothink_api_authenticate_token_user($token) {
    $token = trim((string) $token);
    if ($token === '' || strpos($token, 'ztapi_') !== 0) {
        return null;
    }

    ensure_zerothink_api_tables();
    $hash = zerothink_api_token_hash($token);
    if ($hash === zerothink_api_token_hash('')) {
        return null;
    }

    try {
        $stmt = db()->prepare("
            SELECT u.*, zat.id AS zerothink_api_token_id, zat.label AS zerothink_api_label
            FROM zerothink_api_tokens zat
            INNER JOIN users u ON u.id = zat.user_id
            WHERE zat.token_hash = ?
              AND zat.revoked_at IS NULL
              AND (zat.expires_at IS NULL OR zat.expires_at > NOW())
              AND COALESCE(u.disabled, 0) = 0
            LIMIT 1
        ");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            return null;
        }

        $role = strtolower((string) ($row['role'] ?? ''));
        $plan = strtolower((string) ($row['plan'] ?? ''));
        $sub = strtolower((string) ($row['subscription_status'] ?? ''));
        $is_pro = !empty($row['is_pro'])
            || $role === 'admin'
            || in_array($plan, ['pro', 'god_mode'], true)
            || in_array($sub, ['active', 'pro'], true);
        if (!$is_pro) {
            return null;
        }

        if (function_exists('token_gate_sync_user_row')) {
            $row = token_gate_sync_user_row($row);
        }
        $touch = db()->prepare("UPDATE zerothink_api_tokens SET last_used_at = NOW() WHERE id = ? LIMIT 1");
        $touch->execute([(int) $row['zerothink_api_token_id']]);
        return $row;
    } catch (Throwable $e) {
        return null;
    }
}

function request_user_record($refresh = false) {
    $session_user = current_user_record($refresh);
    if ($session_user) {
        return $session_user;
    }

    $bearer = request_bearer_token();
    if ($bearer !== '') {
        $api_user = zerothink_api_authenticate_token_user($bearer);
        if ($api_user) {
            return $api_user;
        }
        return cli_authenticate_token_user($bearer);
    }

    return null;
}

function request_user_is_authenticated() {
    $user = request_user_record();
    return (int) ($user['id'] ?? 0) > 0;
}

function is_architect_user($user = null) {
    if (!$user) {
        $user = current_user_record();
    }
    if (!$user || !is_array($user)) {
        return false;
    }

    $uid = (int) ($user['id'] ?? ($_SESSION['user_id'] ?? 0));
    $email = strtolower(trim((string) ($user['email'] ?? '')));
    $role = strtolower((string) ($user['role'] ?? ''));
    $plan = strtolower((string) ($user['plan'] ?? ''));

    return $role === 'admin';
}

function zerothink_token_gate_mint() {
    $mint = trim((string) (getenv('ZEROTHINK_TOKEN_MINT') ?: getenv('TOKEN_GATE_MINT') ?: ''));
    return $mint !== '' ? $mint : 'dHdkhZsmBQhXjQoKbkyajZ3Ff4W2XumGyVJX4jypump';
}

function zerothink_token_gate_min_usd() {
    $raw = trim((string) (getenv('ZEROTHINK_TOKEN_MIN_USD') ?: getenv('TOKEN_GATE_MIN_USD') ?: '10'));
    $value = is_numeric($raw) ? (float) $raw : 10.0;
    return max(0.0, $value);
}
?>
