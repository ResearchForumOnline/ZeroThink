<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/core/token_gate_market.php';

if (!defined('TOKEN_GATE_LIBRARY_MODE')) {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function tg_json($payload, $code = 200) {
    http_response_code((int) $code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

class TgWalletClaimConflictException extends RuntimeException {}

function tg_user_id() {
    $user = current_user_record(true);
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) {
        tg_json(['status' => 'error', 'message' => 'Login required.'], 401);
    }
    return $uid;
}

function tg_input() {
    $input = json_decode((string) file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function tg_csrf_token() {
    if (empty($_SESSION['token_gate_csrf']) || !is_string($_SESSION['token_gate_csrf'])) {
        $_SESSION['token_gate_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_gate_csrf'];
}

function tg_require_csrf($input) {
    $expected = (string) ($_SESSION['token_gate_csrf'] ?? '');
    $sent = (string) ($_SERVER['HTTP_X_ZEROTHINK_CSRF'] ?? ($input['csrf'] ?? ''));
    if ($expected === '' || $sent === '' || !hash_equals($expected, $sent)) {
        tg_json(['status' => 'error', 'message' => 'Security token expired. Refresh the page and try again.'], 403);
    }
}

function tg_ensure_tables() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $db = db();
    $db->exec("CREATE TABLE IF NOT EXISTS token_gate_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        wallet VARCHAR(64) NOT NULL,
        message_hash CHAR(64) NOT NULL,
        message TEXT NOT NULL,
        ip VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        KEY idx_tg_challenge_lookup (user_id, wallet, used_at, expires_at),
        KEY idx_tg_challenge_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS token_gate_wallets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        wallet VARCHAR(64) NOT NULL,
        token_mint VARCHAR(64) NOT NULL,
        last_balance DECIMAL(38, 12) NOT NULL DEFAULT 0,
        last_price_usd DECIMAL(24, 12) NOT NULL DEFAULT 0,
        last_value_usd DECIMAL(24, 8) NOT NULL DEFAULT 0,
        access_expires_at DATETIME NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'inactive',
        last_error VARCHAR(255) NULL,
        verified_at DATETIME NULL,
        last_checked_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_tg_user_wallet_mint (user_id, wallet, token_mint),
        KEY idx_tg_wallet (wallet, token_mint),
        KEY idx_tg_user_status (user_id, status, access_expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS token_gate_wallet_claims (
        wallet VARCHAR(64) NOT NULL,
        token_mint VARCHAR(64) NOT NULL,
        user_id INT NOT NULL DEFAULT 0,
        claimed_at DATETIME NULL,
        released_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (wallet, token_mint),
        KEY idx_tg_wallet_claim_user (user_id, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Preserve existing entitlements while introducing the cross-account claim
    // invariant. If historic duplicates exist, the oldest numeric account owns
    // the initial claim and the other rows remain available for audit.
    $db->exec("INSERT IGNORE INTO token_gate_wallet_claims
        (wallet, token_mint, user_id, claimed_at, released_at)
        SELECT wallet, token_mint, MIN(user_id), NOW(), NULL
        FROM token_gate_wallets
        WHERE status = 'active' AND access_expires_at > NOW()
        GROUP BY wallet, token_mint");

    $db->exec("CREATE TABLE IF NOT EXISTS token_gate_cache (
        cache_key VARCHAR(100) PRIMARY KEY,
        cache_value MEDIUMTEXT NOT NULL,
        expires_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS token_gate_audit (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        wallet VARCHAR(64) NOT NULL,
        token_mint VARCHAR(64) NOT NULL,
        event VARCHAR(40) NOT NULL,
        details MEDIUMTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_tg_audit_user (user_id, created_at),
        KEY idx_tg_audit_wallet (wallet, token_mint, created_at),
        KEY idx_tg_audit_event (event, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function tg_audit($user_id, $wallet, $event, $details = []) {
    try {
        $stmt = db()->prepare("INSERT INTO token_gate_audit (user_id, wallet, token_mint, event, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            (int) $user_id,
            substr((string) $wallet, 0, 64),
            zerothink_token_gate_mint(),
            substr((string) $event, 0, 40),
            json_encode(is_array($details) ? $details : ['details' => $details], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
    }
}

function tg_cache_get($key) {
    try {
        $stmt = db()->prepare("SELECT cache_value FROM token_gate_cache WHERE cache_key = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $decoded = json_decode((string) $row['cache_value'], true);
        return is_array($decoded) ? $decoded : null;
    } catch (Throwable $e) {
        return null;
    }
}

function tg_cache_set($key, $value, $ttl_seconds) {
    try {
        $expires = gmdate('Y-m-d H:i:s', time() + max(10, (int) $ttl_seconds));
        $stmt = db()->prepare("REPLACE INTO token_gate_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $expires]);
    } catch (Throwable $e) {
    }
}

function tg_base58_decode($input) {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $input = trim((string) $input);
    if ($input === '') {
        return false;
    }

    $bytes = [0];
    $length = strlen($input);
    for ($i = 0; $i < $length; $i++) {
        $value = strpos($alphabet, $input[$i]);
        if ($value === false) {
            return false;
        }

        $carry = $value;
        for ($j = count($bytes) - 1; $j >= 0; $j--) {
            $carry += $bytes[$j] * 58;
            $bytes[$j] = $carry & 0xff;
            $carry >>= 8;
        }

        while ($carry > 0) {
            array_unshift($bytes, $carry & 0xff);
            $carry >>= 8;
        }
    }

    for ($i = 0; $i < $length && $input[$i] === '1'; $i++) {
        array_unshift($bytes, 0);
    }

    return pack('C*', ...$bytes);
}

function tg_valid_solana_pubkey($wallet) {
    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', (string) $wallet)) {
        return false;
    }
    $decoded = tg_base58_decode($wallet);
    return is_string($decoded) && strlen($decoded) === 32;
}

function tg_http_json($url, $payload = null, $timeout = 18) {
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'ZeroThinkTokenGate/1.0',
    ];

    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $body;
        $opts[CURLOPT_HTTPHEADER] = array_merge($headers, ['Content-Type: application/json']);
    }

    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode((string) $raw, true);
    if ($http < 200 || $http >= 300 || !is_array($decoded)) {
        return ['ok' => false, 'http_code' => $http, 'error' => $error ?: 'bad_json'];
    }

    return ['ok' => true, 'data' => $decoded, 'http_code' => $http];
}

function tg_fetch_price_usd() {
    $mint = zerothink_token_gate_mint();
    $cached = tg_cache_get('price:' . $mint);
    if ($cached && isset($cached['price_usd'])) {
        return $cached;
    }

    $urls = [
        'https://api.dexscreener.com/tokens/v1/solana/' . rawurlencode($mint),
        'https://api.dexscreener.com/latest/dex/tokens/' . rawurlencode($mint),
    ];
    $pairs = [];
    foreach ($urls as $url) {
        $res = tg_http_json($url, null, 16);
        if (empty($res['ok'])) {
            continue;
        }
        if (isset($res['data']['pairs']) && is_array($res['data']['pairs'])) {
            $pairs = $res['data']['pairs'];
        } elseif (is_array($res['data'])) {
            $pairs = $res['data'];
        }
        if ($pairs) {
            break;
        }
    }

    if (!$pairs) {
        return ['ok' => false, 'reason' => 'price_unavailable'];
    }

    $best = zt_token_gate_select_liquid_pair($pairs, $mint);

    if (!$best) {
        return ['ok' => false, 'reason' => 'token_pair_not_found'];
    }

    tg_cache_set('price:' . $mint, $best, 90);
    return $best;
}

function tg_fetch_wallet_balance($wallet) {
    $mint = zerothink_token_gate_mint();
    $payload = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'getTokenAccountsByOwner',
        'params' => [
            $wallet,
            ['mint' => $mint],
            ['encoding' => 'jsonParsed', 'commitment' => 'finalized'],
        ],
    ];

    $rpc = getenv('SOLANA_RPC_URL') ?: 'https://api.mainnet-beta.solana.com';
    $res = tg_http_json($rpc, $payload, 20);
    if (empty($res['ok'])) {
        return ['ok' => false, 'reason' => 'rpc_unavailable'];
    }
    if (!empty($res['data']['error'])) {
        return ['ok' => false, 'reason' => 'rpc_error', 'details' => $res['data']['error']];
    }

    $balance = 0.0;
    foreach (($res['data']['result']['value'] ?? []) as $account) {
        $amount = $account['account']['data']['parsed']['info']['tokenAmount']['uiAmountString'] ?? '0';
        $balance += (float) $amount;
    }

    return ['ok' => true, 'balance' => $balance];
}

function tg_assess_wallet($wallet) {
    $price = tg_fetch_price_usd();
    if (empty($price['ok'])) {
        return ['ok' => false, 'reason' => $price['reason'] ?? 'price_unavailable'];
    }
    $configured_min_liquidity = trim((string) (getenv('ZEROTHINK_TOKEN_MIN_LIQUIDITY_USD') ?: getenv('TOKEN_GATE_MIN_LIQUIDITY_USD') ?: ''));
    $min_liquidity = is_numeric($configured_min_liquidity)
        ? max(100.0, (float) $configured_min_liquidity)
        : max(100.0, zerothink_token_gate_min_usd() * 20.0);
    $actual_liquidity = (float) ($price['liquidity_usd'] ?? 0);
    if ($actual_liquidity < $min_liquidity) {
        return ['ok' => false, 'reason' => 'market_depth_too_low'];
    }

    $balance = tg_fetch_wallet_balance($wallet);
    if (empty($balance['ok'])) {
        return ['ok' => false, 'reason' => $balance['reason'] ?? 'balance_unavailable'];
    }

    $value = (float) $balance['balance'] * (float) $price['price_usd'];
    return [
        'ok' => true,
        'wallet' => $wallet,
        'mint' => zerothink_token_gate_mint(),
        'balance' => (float) $balance['balance'],
        'price_usd' => (float) $price['price_usd'],
        'value_usd' => $value,
        'min_usd' => zerothink_token_gate_min_usd(),
        'eligible' => $value >= zerothink_token_gate_min_usd(),
        'price_source' => $price['source'] ?? 'dexscreener',
        'liquidity_usd' => (float) ($price['liquidity_usd'] ?? 0),
        'volume_24h_usd' => (float) ($price['volume_24h_usd'] ?? 0),
        'pair' => $price['pair'] ?? '',
        'dex' => $price['dex'] ?? '',
    ];
}

function tg_wallet_active_elsewhere($wallet, $user_id) {
    $stmt = db()->prepare("SELECT user_id FROM token_gate_wallets WHERE wallet = ? AND token_mint = ? AND user_id <> ? AND status = 'active' AND access_expires_at > NOW() LIMIT 1");
    $stmt->execute([$wallet, zerothink_token_gate_mint(), (int) $user_id]);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function tg_claim_wallet_for_user(PDO $db, $wallet, $user_id) {
    $wallet = trim((string) $wallet);
    $user_id = (int) $user_id;
    $mint = zerothink_token_gate_mint();
    if ($wallet === '' || $user_id <= 0) {
        throw new InvalidArgumentException('A valid wallet and account are required.');
    }

    $insert = $db->prepare("INSERT IGNORE INTO token_gate_wallet_claims
        (wallet, token_mint, user_id, claimed_at, released_at)
        VALUES (?, ?, ?, NOW(), NULL)");
    $insert->execute([$wallet, $mint, $user_id]);

    $claim = $db->prepare("SELECT user_id FROM token_gate_wallet_claims
        WHERE wallet = ? AND token_mint = ? FOR UPDATE");
    $claim->execute([$wallet, $mint]);
    $owner_id = (int) $claim->fetchColumn();
    if ($owner_id === $user_id) {
        return;
    }

    if ($owner_id > 0) {
        $active = $db->prepare("SELECT 1 FROM token_gate_wallets
            WHERE user_id = ? AND wallet = ? AND token_mint = ?
              AND status = 'active' AND access_expires_at > NOW()
            LIMIT 1 FOR UPDATE");
        $active->execute([$owner_id, $wallet, $mint]);
        if ($active->fetchColumn()) {
            throw new TgWalletClaimConflictException('This wallet is already linked to another active ZeroThink account.');
        }
    }

    $transfer = $db->prepare("UPDATE token_gate_wallet_claims
        SET user_id = ?, claimed_at = NOW(), released_at = NULL
        WHERE wallet = ? AND token_mint = ?");
    $transfer->execute([$user_id, $wallet, $mint]);
    if ($transfer->rowCount() !== 1) {
        throw new RuntimeException('Wallet claim could not be acquired atomically.');
    }
}

function tg_release_wallet_claim(PDO $db, $wallet, $user_id) {
    $release = $db->prepare("UPDATE token_gate_wallet_claims
        SET user_id = 0, released_at = NOW()
        WHERE wallet = ? AND token_mint = ? AND user_id = ?");
    $release->execute([
        trim((string) $wallet),
        zerothink_token_gate_mint(),
        (int) $user_id,
    ]);
}

function tg_save_wallet_state($user_id, $wallet, $assessment, $status, $error = '') {
    $expires = !empty($assessment['eligible'])
        ? gmdate('Y-m-d H:i:s', time() + zerothink_token_gate_access_ttl_seconds())
        : null;

    $stmt = db()->prepare("
        INSERT INTO token_gate_wallets
            (user_id, wallet, token_mint, last_balance, last_price_usd, last_value_usd, access_expires_at, status, last_error, verified_at, last_checked_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            last_balance = VALUES(last_balance),
            last_price_usd = VALUES(last_price_usd),
            last_value_usd = VALUES(last_value_usd),
            access_expires_at = VALUES(access_expires_at),
            status = VALUES(status),
            last_error = VALUES(last_error),
            verified_at = NOW(),
            last_checked_at = NOW()
    ");
    $stmt->execute([
        (int) $user_id,
        $wallet,
        zerothink_token_gate_mint(),
        (float) ($assessment['balance'] ?? 0),
        (float) ($assessment['price_usd'] ?? 0),
        (float) ($assessment['value_usd'] ?? 0),
        $expires,
        $status,
        substr((string) $error, 0, 240),
    ]);

    return $expires;
}

function tg_grant_or_revoke($user_id, $wallet, $assessment) {
    $user_id = (int) $user_id;
    $db = db();
    $owns_transaction = !$db->inTransaction();
    if ($owns_transaction) {
        $db->beginTransaction();
    }

    try {
        $eligible = !empty($assessment['eligible']);
        if ($eligible) {
            tg_claim_wallet_for_user($db, $wallet, $user_id);
        } else {
            tg_release_wallet_claim($db, $wallet, $user_id);
        }

        $status = $eligible ? 'active' : 'insufficient';
        $expires = tg_save_wallet_state($user_id, $wallet, $assessment, $status, '');

        $stmt = $db->prepare("SELECT plan, subscription_status, subscription_id FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $plan = strtolower((string) ($user['plan'] ?? 'free'));
        $subscription_id = (string) ($user['subscription_id'] ?? '');
        $is_paypal_active = strtolower((string) ($user['subscription_status'] ?? '')) === 'active'
            && $subscription_id !== ''
            && !token_gate_is_subscription_id($subscription_id);

        if ($eligible) {
            if (!$is_paypal_active && $plan !== 'god_mode') {
                $update = $db->prepare("UPDATE users SET plan = 'pro', is_pro = 1, subscription_status = 'active', subscription_id = ?, subscription_end = ? WHERE id = ? LIMIT 1");
                $update->execute([token_gate_subscription_id($wallet), $expires, $user_id]);
            }
            $result = ['granted' => true, 'expires_at' => $expires, 'paypal_preserved' => $is_paypal_active];
        } else {
            if (token_gate_is_subscription_id($subscription_id) && $plan !== 'god_mode') {
                $update = $db->prepare("UPDATE users SET plan = 'free', is_pro = 0, subscription_status = 'cancelled', subscription_id = NULL, subscription_end = NULL WHERE id = ? AND subscription_id = ? LIMIT 1");
                $update->execute([$user_id, $subscription_id]);
            }
            $result = ['granted' => false, 'expires_at' => null, 'paypal_preserved' => $is_paypal_active];
        }

        if ($owns_transaction) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($owns_transaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $user_id) {
        current_user_record(true);
    }
    return $result;
}

function tg_latest_wallet($user_id) {
    $stmt = db()->prepare("SELECT * FROM token_gate_wallets WHERE user_id = ? AND token_mint = ? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([(int) $user_id, zerothink_token_gate_mint()]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function tg_public_wallet_state($row = null) {
    if (!$row) {
        return [
            'wallet' => null,
            'balance' => 0,
            'price_usd' => 0,
            'value_usd' => 0,
            'min_usd' => zerothink_token_gate_min_usd(),
            'access_expires_at' => null,
            'active' => false,
            'status' => 'not_linked',
        ];
    }

    $expires = (string) ($row['access_expires_at'] ?? '');
    return [
        'wallet' => (string) ($row['wallet'] ?? ''),
        'balance' => (float) ($row['last_balance'] ?? 0),
        'price_usd' => (float) ($row['last_price_usd'] ?? 0),
        'value_usd' => (float) ($row['last_value_usd'] ?? 0),
        'min_usd' => zerothink_token_gate_min_usd(),
        'access_expires_at' => $expires ?: null,
        'active' => strtolower((string) ($row['status'] ?? '')) === 'active' && $expires !== '' && strtotime($expires) > time(),
        'status' => (string) ($row['status'] ?? 'inactive'),
        'last_checked_at' => (string) ($row['last_checked_at'] ?? ''),
        'last_error' => (string) ($row['last_error'] ?? ''),
    ];
}

if (!defined('TOKEN_GATE_LIBRARY_MODE')) {
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tg_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
}

$uid = tg_user_id();
tg_ensure_tables();
$input = tg_input();
tg_require_csrf($input);
$action = strtolower(trim((string) ($input['action'] ?? 'status')));

if ($action === 'status') {
    tg_json(['status' => 'success', 'token' => tg_public_wallet_state(tg_latest_wallet($uid))]);
}

if ($action === 'challenge') {
    $wallet = trim((string) ($input['wallet'] ?? ''));
    if (!tg_valid_solana_pubkey($wallet)) {
        tg_json(['status' => 'error', 'message' => 'Invalid Solana wallet address.'], 400);
    }

    $count = db()->prepare("SELECT COUNT(*) FROM token_gate_challenges WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $count->execute([$uid]);
    if ((int) $count->fetchColumn() > 8) {
        tg_json(['status' => 'error', 'message' => 'Too many verification attempts. Wait a minute and try again.'], 429);
    }

    $issued = gmdate('c');
    $expires = gmdate('Y-m-d H:i:s', time() + 10 * 60);
    $nonce = bin2hex(random_bytes(18));
    $message = implode("\n", [
        'ZeroThink $AI Pro Verification',
        'Domain: zerothink.talktoai.org',
        'User ID: ' . $uid,
        'Wallet: ' . $wallet,
        'Token Mint: ' . zerothink_token_gate_mint(),
        'Minimum USD Value: $' . number_format(zerothink_token_gate_min_usd(), 2, '.', ''),
        'Nonce: ' . $nonce,
        'Issued: ' . $issued,
        'Expires: ' . gmdate('c', strtotime($expires)),
        'Sign this message only on zerothink.talktoai.org. This proves wallet ownership and does not move funds.',
    ]);

    $stmt = db()->prepare("INSERT INTO token_gate_challenges (user_id, wallet, message_hash, message, ip, user_agent, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $uid,
        $wallet,
        hash('sha256', $message),
        $message,
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        $expires,
    ]);

    tg_json([
        'status' => 'success',
        'message' => $message,
        'wallet' => $wallet,
        'expires_at' => $expires,
    ]);
}

if ($action === 'verify') {
    if (!function_exists('sodium_crypto_sign_verify_detached')) {
        tg_json(['status' => 'error', 'message' => 'Server signature verification is unavailable.'], 500);
    }

    $wallet = trim((string) ($input['wallet'] ?? ''));
    $message = (string) ($input['message'] ?? '');
    $signature = base64_decode((string) ($input['signature'] ?? ''), true);
    if (!tg_valid_solana_pubkey($wallet) || $message === '' || !is_string($signature) || strlen($signature) !== 64) {
        tg_json(['status' => 'error', 'message' => 'Invalid wallet signature payload.'], 400);
    }

    $stmt = db()->prepare("SELECT * FROM token_gate_challenges WHERE user_id = ? AND wallet = ? AND message_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$uid, $wallet, hash('sha256', $message)]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$challenge || !hash_equals((string) $challenge['message'], $message)) {
        tg_json(['status' => 'error', 'message' => 'Verification challenge expired or not found. Please try again.'], 400);
    }

    $pubkey = tg_base58_decode($wallet);
    if (!sodium_crypto_sign_verify_detached($signature, $message, $pubkey)) {
        tg_json(['status' => 'error', 'message' => 'Wallet signature did not verify.'], 403);
    }

    if (tg_wallet_active_elsewhere($wallet, $uid)) {
        tg_json(['status' => 'error', 'message' => 'This wallet is already linked to another active ZeroThink account.'], 409);
    }

    $mark = db()->prepare("UPDATE token_gate_challenges SET used_at = NOW()
        WHERE id = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
    $mark->execute([(int) $challenge['id']]);
    if ($mark->rowCount() !== 1) {
        tg_json(['status' => 'error', 'message' => 'Verification challenge was already used. Please try again.'], 409);
    }

    $assessment = tg_assess_wallet($wallet);
    if (empty($assessment['ok'])) {
        tg_save_wallet_state($uid, $wallet, [], 'error', $assessment['reason'] ?? 'verification_failed');
        tg_json(['status' => 'error', 'message' => 'Could not verify token holdings right now: ' . ($assessment['reason'] ?? 'unknown')], 502);
    }

    try {
        $grant = tg_grant_or_revoke($uid, $wallet, $assessment);
    } catch (TgWalletClaimConflictException $e) {
        tg_json(['status' => 'error', 'message' => $e->getMessage()], 409);
    }
    $public = tg_public_wallet_state(tg_latest_wallet($uid));
    tg_json([
        'status' => 'success',
        'eligible' => (bool) $assessment['eligible'],
        'message' => $assessment['eligible']
            ? 'Token holder Pro access is active.'
            : 'Wallet verified, but the $AI value is below the $10 minimum.',
        'token' => $public,
        'grant' => $grant,
    ]);
}

if ($action === 'refresh') {
    $row = tg_latest_wallet($uid);
    if (!$row || empty($row['wallet'])) {
        tg_json(['status' => 'error', 'message' => 'No verified wallet is linked yet. Connect and sign first.'], 400);
    }

    $wallet = (string) $row['wallet'];
    if (tg_wallet_active_elsewhere($wallet, $uid)) {
        tg_json(['status' => 'error', 'message' => 'This wallet is already linked to another active ZeroThink account.'], 409);
    }

    $assessment = tg_assess_wallet($wallet);
    if (empty($assessment['ok'])) {
        tg_save_wallet_state($uid, $wallet, [], 'error', $assessment['reason'] ?? 'refresh_failed');
        tg_json(['status' => 'error', 'message' => 'Could not refresh token holdings right now: ' . ($assessment['reason'] ?? 'unknown')], 502);
    }

    try {
        $grant = tg_grant_or_revoke($uid, $wallet, $assessment);
    } catch (TgWalletClaimConflictException $e) {
        tg_json(['status' => 'error', 'message' => $e->getMessage()], 409);
    }
    tg_json([
        'status' => 'success',
        'eligible' => (bool) $assessment['eligible'],
        'message' => $assessment['eligible']
            ? 'Token holdings refreshed. Pro access is active.'
            : 'Token holdings refreshed, but the value is below the $10 minimum.',
        'token' => tg_public_wallet_state(tg_latest_wallet($uid)),
        'grant' => $grant,
    ]);
}

tg_json(['status' => 'error', 'message' => 'Unknown token gate action.'], 400);
}
?>
