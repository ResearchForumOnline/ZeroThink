<?php
$qz_library_only = defined('QZ_LIBRARY_ONLY') && QZ_LIBRARY_ONLY;
if (!$qz_library_only) {
    ob_start();
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    require_once __DIR__ . '/app/bootstrap.php';
    if (is_file(__DIR__ . '/app/core/vault_security.php')) {
        require_once __DIR__ . '/app/core/vault_security.php';
    }
    ob_end_clean();
}

class QzClientException extends RuntimeException {
    private $http_status;

    public function __construct($message, $http_status = 400) {
        parent::__construct((string) $message);
        $this->http_status = max(400, min(499, (int) $http_status));
    }

    public function httpStatus() {
        return $this->http_status;
    }
}

function qz_send($payload, $status = 200) {
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function qz_current_user() {
    if (function_exists('request_user_record')) {
        $user = request_user_record();
        if ($user) {
            return $user;
        }
    }
    if (function_exists('current_user_record')) {
        return current_user_record();
    }
    return null;
}

function qz_is_pro($user) {
    if (function_exists('is_architect_user') && is_architect_user($user)) {
        return true;
    }
    if (!$user) {
        return false;
    }
    if (strtolower((string) ($user['role'] ?? '')) === 'admin') {
        return true;
    }
    return !empty($user['is_pro'])
        || in_array(strtolower((string) ($user['plan'] ?? '')), ['pro', 'god_mode'], true)
        || strtolower((string) ($user['subscription_status'] ?? '')) === 'active';
}

function qz_is_shafaet_admin_unlimited($user) {
    if (!is_array($user)) {
        return false;
    }
    return strtolower((string) ($user['role'] ?? '')) === 'admin';
}

function qz_clean_text($value, $limit = 6000) {
    $text = trim((string) $value);
    $text = preg_replace('/[^\P{C}\r\n\t]/u', '', $text);
    if (strlen($text) > $limit) {
        $text = substr($text, 0, $limit);
    }
    return $text;
}

function qz_shared_service_entitled($user) {
    if (function_exists('is_architect_user') && is_architect_user($user)) {
        return true;
    }
    if (!is_array($user)) {
        return false;
    }
    $plan = strtolower(trim((string) ($user['plan'] ?? '')));
    $subscription = strtolower(trim((string) ($user['subscription_status'] ?? '')));
    return !empty($user['is_pro'])
        || in_array($plan, ['pro', 'god_mode'], true)
        || $subscription === 'active';
}

function qz_user_vault_credential($uid, $column) {
    $allowed = ['api_key_groq', 'api_key_quantum'];
    if (!in_array($column, $allowed, true)) {
        return ['key' => '', 'source' => ''];
    }
    if (!empty($_SESSION[$column])) {
        $key = trim((string) $_SESSION[$column]);
        if ($key !== '') {
            return ['key' => $key, 'source' => 'user_session'];
        }
    }
    $db = function_exists('db') ? db() : null;
    if (!$db || !$uid) {
        return ['key' => '', 'source' => ''];
    }
    try {
        if (function_exists('zt_vault_cipher_column') && function_exists('zt_vault_secret_from_row')) {
            $cipher_column = zt_vault_cipher_column($column);
            try {
                $stmt = $db->prepare("SELECT `{$column}`, `{$cipher_column}` FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([(int) $uid]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $key = zt_vault_secret_from_row($row, $column);
                if ($key !== '') {
                    return ['key' => $key, 'source' => !empty($row[$cipher_column]) ? 'user_vault' : 'user_vault_plain'];
                }
            } catch (Throwable $ignored) {
                // Older schemas can fall back to the allow-listed plaintext column.
            }
        }
        $stmt = $db->prepare("SELECT {$column} FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $uid]);
        $key = trim((string) $stmt->fetchColumn());
        return $key !== '' ? ['key' => $key, 'source' => 'user_vault_plain'] : ['key' => '', 'source' => ''];
    } catch (Throwable $e) {
        return ['key' => '', 'source' => ''];
    }
}

function qz_service_credential($column, $env_name = '') {
    $allowed = ['api_key_groq', 'api_key_quantum'];
    if (!in_array($column, $allowed, true)) {
        return ['key' => '', 'source' => ''];
    }
    if ($env_name !== '') {
        $key = trim((string) getenv($env_name));
        if ($key !== '') {
            return ['key' => $key, 'source' => 'service_env'];
        }
    }
    if (function_exists('site_setting')) {
        $key = trim((string) site_setting('service_' . $column, ''));
        if ($key !== '') {
            return ['key' => $key, 'source' => 'service_setting'];
        }
    }
    return ['key' => '', 'source' => ''];
}

function qz_resolve_credential($uid, $column, $env_name, $user, $allow_service_fallback) {
    $credential = qz_user_vault_credential($uid, $column);
    if (!empty($credential['key'])) {
        return $credential;
    }
    if (!$allow_service_fallback || !qz_shared_service_entitled($user)) {
        return ['key' => '', 'source' => ''];
    }
    return qz_service_credential($column, $env_name);
}

function qz_credential_scope_hash($provider, $credential) {
    $provider = strtolower(trim((string) $provider));
    $key = trim((string) ($credential['key'] ?? ''));
    if ($provider === '' || $key === '') {
        return '';
    }
    return hash('sha256', $provider . "\0" . $key);
}

function qz_credential_is_shared($credential) {
    return strpos((string) ($credential['source'] ?? ''), 'service_') === 0;
}

function qz_ionq_request($method, $path, $api_key, $body = null, $timeout = 35) {
    $url = 'https://api.ionq.co/v0.4/' . ltrim((string) $path, '/');
    $headers = [
        'Authorization: apiKey ' . trim((string) $api_key),
        'Accept: application/json',
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper((string) $method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int) $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new Exception('IonQ network error: ' . $err);
    }
    $json = json_decode((string) $raw, true);
    if ($code >= 400) {
        $msg = is_array($json) ? (string) ($json['message'] ?? $json['error'] ?? $json['detail'] ?? '') : '';
        if ($msg === '') {
            $msg = substr(trim((string) $raw), 0, 300);
        }
        throw new Exception('IonQ API error (' . $code . '): ' . $msg);
    }

    return ['status_code' => $code, 'data' => is_array($json) ? $json : []];
}

function qz_groq_request($api_key, $system, $user, $max_tokens = 1800) {
    $body = [
        'model' => 'openai/gpt-oss-120b',
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
        'temperature' => 0.35,
        'max_tokens' => (int) $max_tokens,
        'reasoning_effort' => 'high',
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim((string) $api_key),
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 160,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new Exception('Groq network error: ' . $err);
    }
    $json = json_decode((string) $raw, true);
    if ($code >= 400) {
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? $json['message'] ?? '') : '';
        if ($msg === '') {
            $msg = substr(trim((string) $raw), 0, 300);
        }
        throw new Exception('Groq API error (' . $code . '): ' . $msg);
    }
    $reply = trim((string) ($json['choices'][0]['message']['content'] ?? ''));
    return $reply !== '' ? $reply : 'Quantum Zero received no model text.';
}

function qz_cache_dir() {
    $dir = __DIR__ . '/storage/cache/quantum-zero';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    @chmod($dir, 0750);
    return $dir;
}

function qz_cache_path($uid, $name) {
    $safe_name = preg_replace('/[^a-z0-9_-]/i', '_', (string) $name);
    return qz_cache_dir() . '/user_' . (int) $uid . '_' . $safe_name . '.json';
}

function qz_cache_get($uid, $name, $ttl_seconds) {
    $path = qz_cache_path($uid, $name);
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data) || empty($data['created_at']) || !array_key_exists('payload', $data)) {
        return null;
    }
    $age = time() - (int) $data['created_at'];
    if ($age < 0 || $age > (int) $ttl_seconds) {
        return null;
    }
    return ['payload' => $data['payload'], 'age' => $age, 'created_at' => (int) $data['created_at']];
}

function qz_cache_set($uid, $name, $payload) {
    $path = qz_cache_path($uid, $name);
    $written = @file_put_contents($path, json_encode([
        'created_at' => time(),
        'payload' => $payload,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($written !== false) {
        @chmod($path, 0640);
    }
}

function qz_public_job_snapshot($job) {
    $job = is_array($job) ? $job : [];
    $allowed = [
        'id', 'name', 'status', 'backend', 'target', 'shots', 'qubits',
        'created_at', 'submitted_at', 'started_at', 'completed_at', 'failure',
    ];
    $snapshot = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $job) && !is_array($job[$field]) && !is_object($job[$field])) {
            $snapshot[$field] = $job[$field];
        }
    }
    if (isset($job['stats']) && is_array($job['stats'])) {
        $snapshot['stats'] = array_intersect_key($job['stats'], array_flip(['qubits', 'gates', 'shots']));
    }
    return $snapshot;
}

function qz_ensure_job_ownership_table() {
    static $ready = false;
    if ($ready) {
        return;
    }
    if (!function_exists('db') || !db()) {
        throw new RuntimeException('Quantum job ownership storage is unavailable.');
    }
    db()->exec("CREATE TABLE IF NOT EXISTS quantum_zero_job_ownership (
        provider VARCHAR(32) NOT NULL,
        job_id VARCHAR(128) NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        credential_scope_hash CHAR(64) NOT NULL,
        credential_source VARCHAR(32) NOT NULL,
        backend VARCHAR(80) NOT NULL DEFAULT '',
        job_snapshot_json LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_accessed_at DATETIME NULL,
        PRIMARY KEY (provider, job_id),
        KEY idx_qz_job_owner_recent (user_id, provider, created_at),
        KEY idx_qz_job_owner_scope (user_id, credential_scope_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

function qz_record_job_owner($uid, $provider, $job_id, $credential, $job = []) {
    $uid = (int) $uid;
    $provider = strtolower(trim((string) $provider));
    $job_id = qz_valid_job_id($job_id);
    $scope_hash = qz_credential_scope_hash($provider, $credential);
    $source = substr(preg_replace('/[^a-z0-9_-]/i', '', (string) ($credential['source'] ?? '')), 0, 32);
    if ($uid <= 0 || $provider === '' || $scope_hash === '' || $source === '') {
        throw new RuntimeException('Quantum job owner mapping is incomplete.');
    }

    qz_ensure_job_ownership_table();
    $db = db();
    $snapshot = qz_public_job_snapshot($job);
    if (empty($snapshot['id'])) {
        $snapshot['id'] = $job_id;
    }
    $snapshot_json = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $backend = substr((string) ($snapshot['backend'] ?? $snapshot['target'] ?? ''), 0, 80);
    $own_transaction = !$db->inTransaction();

    try {
        if ($own_transaction) {
            $db->beginTransaction();
        }
        $stmt = $db->prepare("INSERT INTO quantum_zero_job_ownership
            (provider, job_id, user_id, credential_scope_hash, credential_source, backend, job_snapshot_json, created_at, last_accessed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE job_id = VALUES(job_id)");
        $stmt->execute([$provider, $job_id, $uid, $scope_hash, $source, $backend, $snapshot_json]);

        $stmt = $db->prepare('SELECT user_id, credential_scope_hash FROM quantum_zero_job_ownership WHERE provider = ? AND job_id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$provider, $job_id]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $owned = $owner
            && (int) ($owner['user_id'] ?? 0) === $uid
            && hash_equals((string) ($owner['credential_scope_hash'] ?? ''), $scope_hash);
        if (!$owned) {
            throw new QzClientException('Job not found in your Quantum Zero ledger.', 404);
        }

        $stmt = $db->prepare('UPDATE quantum_zero_job_ownership SET backend = ?, job_snapshot_json = ?, last_accessed_at = UTC_TIMESTAMP() WHERE provider = ? AND job_id = ? AND user_id = ? AND credential_scope_hash = ?');
        $stmt->execute([$backend, $snapshot_json, $provider, $job_id, $uid, $scope_hash]);
        if ($own_transaction) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($own_transaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function qz_assert_job_owner($uid, $provider, $job_id, $credential) {
    $uid = (int) $uid;
    $provider = strtolower(trim((string) $provider));
    $job_id = qz_valid_job_id($job_id);
    $scope_hash = qz_credential_scope_hash($provider, $credential);
    if ($uid <= 0 || $provider === '' || $scope_hash === '') {
        throw new QzClientException('Job not found in your Quantum Zero ledger.', 404);
    }
    qz_ensure_job_ownership_table();
    $stmt = db()->prepare('SELECT user_id, credential_scope_hash, credential_source, backend, created_at FROM quantum_zero_job_ownership WHERE provider = ? AND job_id = ? LIMIT 1');
    $stmt->execute([$provider, $job_id]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $owned = $owner
        && (int) ($owner['user_id'] ?? 0) === $uid
        && hash_equals((string) ($owner['credential_scope_hash'] ?? ''), $scope_hash);
    if (!$owned) {
        throw new QzClientException('Job not found in your Quantum Zero ledger.', 404);
    }
    $touch = db()->prepare('UPDATE quantum_zero_job_ownership SET last_accessed_at = UTC_TIMESTAMP() WHERE provider = ? AND job_id = ? AND user_id = ? AND credential_scope_hash = ?');
    $touch->execute([$provider, $job_id, $uid, $scope_hash]);
    return $owner;
}

function qz_update_owned_job_snapshot($uid, $provider, $job_id, $credential, $job) {
    qz_assert_job_owner($uid, $provider, $job_id, $credential);
    $scope_hash = qz_credential_scope_hash($provider, $credential);
    $snapshot = qz_public_job_snapshot($job);
    if (empty($snapshot['id'])) {
        $snapshot['id'] = $job_id;
    }
    $backend = substr((string) ($snapshot['backend'] ?? $snapshot['target'] ?? ''), 0, 80);
    $stmt = db()->prepare('UPDATE quantum_zero_job_ownership SET backend = ?, job_snapshot_json = ?, last_accessed_at = UTC_TIMESTAMP() WHERE provider = ? AND job_id = ? AND user_id = ? AND credential_scope_hash = ?');
    $stmt->execute([
        $backend,
        json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        strtolower((string) $provider),
        (string) $job_id,
        (int) $uid,
        $scope_hash,
    ]);
}

function qz_owned_recent_rows($uid, $provider, $credential, $limit) {
    $uid = (int) $uid;
    $provider = strtolower(trim((string) $provider));
    $limit = max(1, min(10, (int) $limit));
    $scope_hash = qz_credential_scope_hash($provider, $credential);
    if ($uid <= 0 || $scope_hash === '') {
        return [];
    }
    qz_ensure_job_ownership_table();
    $stmt = db()->prepare('SELECT job_id, backend, job_snapshot_json, created_at FROM quantum_zero_job_ownership WHERE user_id = ? AND provider = ? AND credential_scope_hash = ? ORDER BY created_at DESC, job_id DESC LIMIT ' . $limit);
    $stmt->execute([$uid, $provider, $scope_hash]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function qz_owned_recent_jobs($uid, $credential, $limit) {
    $jobs = [];
    foreach (qz_owned_recent_rows($uid, 'ionq', $credential, $limit) as $row) {
        $job_id = (string) ($row['job_id'] ?? '');
        qz_assert_job_owner($uid, 'ionq', $job_id, $credential);
        $snapshot = json_decode((string) ($row['job_snapshot_json'] ?? ''), true);
        if (!is_array($snapshot)) {
            $snapshot = [];
        }
        if (empty($snapshot['id'])) {
            $snapshot['id'] = $job_id;
        }
        if (empty($snapshot['status'])) {
            $snapshot['status'] = 'submitted';
        }
        $snapshot['ledger_created_at'] = (string) ($row['created_at'] ?? '');
        $snapshot['status_source'] = 'owned-ledger-snapshot';
        $jobs[] = $snapshot;
    }
    return ['jobs' => $jobs, 'owned_only' => true];
}

function qz_shared_daily_job_limit() {
    $raw = trim((string) getenv('ZEROTHINK_QUANTUM_SHARED_DAILY_JOB_LIMIT'));
    if ($raw === '' && function_exists('site_setting')) {
        $raw = trim((string) site_setting('quantum_shared_daily_job_limit', ''));
    }
    return max(1, min(100, (int) ($raw !== '' ? $raw : 20)));
}

function qz_assert_shared_job_budget($uid, $credential, $unlimited_usage) {
    if ($unlimited_usage || !qz_credential_is_shared($credential)) {
        return;
    }
    qz_ensure_job_ownership_table();
    $limit = qz_shared_daily_job_limit();
    $stmt = db()->prepare("SELECT COUNT(*) FROM quantum_zero_job_ownership
        WHERE user_id = ? AND provider = 'ionq' AND credential_source IN ('service_env', 'service_setting')
        AND created_at >= UTC_DATE() AND created_at < UTC_DATE() + INTERVAL 1 DAY");
    $stmt->execute([(int) $uid]);
    if ((int) $stmt->fetchColumn() >= $limit) {
        throw new QzClientException('Shared IonQ daily job allowance reached. Add your own IonQ key in Neural Vault or try again tomorrow.', 429);
    }
}

function qz_rate_limit($uid, $name, $cooldown_seconds) {
    $cache = qz_cache_get($uid, 'cooldown_' . $name, (int) $cooldown_seconds);
    if ($cache) {
        $wait = max(1, (int) $cooldown_seconds - (int) $cache['age']);
        throw new QzClientException('Low-usage guard: wait about ' . $wait . ' second(s) before repeating this IonQ action.', 429);
    }
}

function qz_mark_rate_limit($uid, $name) {
    qz_cache_set($uid, 'cooldown_' . $name, ['used_at' => time()]);
}

function qz_take_request_rate_limit($uid, $action) {
    $cooldowns = [
        'diagnose' => 10,
        'recent' => 3,
        'job' => 2,
        'results' => 2,
        'download' => 3,
        'explain_job' => 5,
        'submit' => 5,
        'proof' => 10,
        'dispatch' => 10,
        'analyze' => 4,
        'experiment' => 5,
        'ionq_submit_any' => 5,
    ];
    $cooldown = (int) ($cooldowns[$action] ?? 0);
    if ($cooldown <= 0) {
        return;
    }
    $path = qz_cache_path($uid, 'request_gate_' . $action);
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        throw new RuntimeException('Quantum request guard is unavailable.');
    }
    @chmod($path, 0640);
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Quantum request guard lock failed.');
        }
        rewind($handle);
        $raw = stream_get_contents($handle);
        $last = (int) trim((string) $raw);
        $now = time();
        if ($last > 0 && ($now - $last) < $cooldown) {
            $wait = max(1, $cooldown - ($now - $last));
            throw new QzClientException('Please wait about ' . $wait . ' second(s) before repeating this action.', 429);
        }
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, (string) $now);
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function qz_valid_job_id($job_id) {
    $job_id = trim((string) $job_id);
    if ($job_id === '' || !preg_match('/^[a-f0-9-]{16,}$/i', $job_id)) {
        throw new QzClientException('Valid IonQ job_id required.');
    }
    return $job_id;
}

function qz_profile_label($profile) {
    $map = [
        'zero-network' => 'Zero Network',
        'entanglement-probe' => 'Entanglement Probe',
        'optimization-oracle' => 'Optimization Oracle',
        'signal-integrity' => 'Signal Integrity',
    ];
    return $map[$profile] ?? $map['zero-network'];
}

function qz_private_kernel($profile, $question, $signal_text = '') {
    $profile = strtolower(trim((string) $profile));
    if (!in_array($profile, ['zero-network', 'entanglement-probe', 'optimization-oracle', 'signal-integrity'], true)) {
        $profile = 'zero-network';
    }
    $question = qz_clean_text($question, 6000);
    if ($question === '') {
        $question = 'How should Quantum Zero use a real quantum-cloud job to improve an AI answer?';
    }
    $signal_text = qz_clean_text($signal_text, 2400);

    $seed = hash('sha256', 'QuantumZero|v2|' . $profile . '|' . $question . '|' . $signal_text);
    $bytes = [];
    for ($i = 0; $i < 8; $i++) {
        $bytes[] = hexdec(substr($seed, $i * 2, 2));
    }

    $circuit = [
        ['gate' => 'h', 'target' => 0],
        ['gate' => 'cnot', 'control' => 0, 'target' => 1],
        ['gate' => 'cnot', 'control' => 1, 'target' => 2],
        ['gate' => 'cnot', 'control' => 2, 'target' => 3],
    ];
    for ($target = 0; $target < 4; $target++) {
        if (($bytes[$target] % 2) === 1) {
            $circuit[] = ['gate' => 'x', 'target' => $target];
        }
    }
    if (($bytes[4] % 2) === 1) {
        $circuit[] = ['gate' => 'cnot', 'control' => 3, 'target' => 0];
    }

    $kernel_id = 'qz-' . substr($seed, 0, 12);
    $intent_hash = hash('sha256', $question);
    $signal_hash = $signal_text !== '' ? hash('sha256', $signal_text) : '';
    $gate_count = count($circuit);

    $public = [
        'kernel_id' => $kernel_id,
        'agent' => 'Quantum Zero',
        'profile' => qz_profile_label($profile),
        'intent_hash' => $intent_hash,
        'signal_hash' => $signal_hash !== '' ? $signal_hash : null,
        'private_signal_supplied' => $signal_text !== '',
        'circuit' => [
            'type' => 'GHZ-style evidence kernel',
            'qubits' => 4,
            'gateset' => 'qis',
            'gate_count' => $gate_count,
            'fingerprint' => substr(hash('sha256', json_encode($circuit)), 0, 20),
        ],
        'usage' => [
            'default_backend' => 'simulator',
            'low_usage_cache' => true,
            'qpu_requires_confirmation' => true,
        ],
        'truth_boundary' => 'A real IonQ job ID proves the quantum-cloud provider accepted this circuit. It does not prove remote effects or that the LLM ran on qubits.',
    ];

    $job = [
        'type' => 'ionq.circuit.v1',
        'name' => 'QuantumZero ' . $kernel_id,
        'metadata' => [
            'system' => 'ZeroThink QuantumZero',
            'agent' => 'Quantum Zero',
            'kernel_id' => $kernel_id,
            'profile' => qz_profile_label($profile),
            'intent_hash' => $intent_hash,
            'signal_hash' => $signal_hash,
            'private_signal_supplied' => $signal_text !== '' ? 'yes' : 'no',
            'realism_rule' => 'job id proves IonQ accepted this circuit; it does not prove remote effects',
        ],
        'shots' => 128,
        'backend' => 'simulator',
        'input' => [
            'qubits' => 4,
            'gateset' => 'qis',
            'circuit' => $circuit,
        ],
    ];

    return [
        'profile' => $profile,
        'question' => $question,
        'signal_hash' => $signal_hash,
        'seed_hash' => $seed,
        'public' => $public,
        'job' => $job,
        'private_context' => [
            'method_family' => 'recursive lattice kernel + quantum evidence ledger + ethical usefulness gate',
            'hidden_math_policy' => 'Do not reveal private equations, ritual numeric keys, or server-side packet internals.',
            'analysis_axes' => ['real evidence', 'symbolic interpretation', 'practical next step', 'failure modes'],
        ],
    ];
}

function qz_experiment_catalog() {
    return [
        'prompt_seed' => [
            'name' => 'Prompt-Seeded QuantumZero',
            'simple' => 'Turns your question into a private circuit seed and reads the job as evidence, not magic.',
            'purpose' => 'Question-grounded research packet',
            'expected' => 'Seeded GHZ-style branches; useful for evidence ledger and dispatch packets.',
            'profile' => 'zero-network',
        ],
        'bell_pair' => [
            'name' => 'Bell Pair Correlation',
            'simple' => 'A clean two-qubit entanglement check. The useful result is mostly 00 and 11.',
            'purpose' => 'Explain entanglement/correlation in a way normal users can verify.',
            'expected' => 'Two dominant states: 00 and 11 near a 50/50 split on the ideal simulator.',
            'profile' => 'entanglement-probe',
        ],
        'ghz_lattice' => [
            'name' => 'GHZ Lattice Pulse',
            'simple' => 'A three-qubit lattice pulse. The clean result is mostly 000 and 111.',
            'purpose' => 'Show a larger shared-state branch than a Bell pair.',
            'expected' => 'Two dominant states: 000 and 111 near a 50/50 split on the ideal simulator.',
            'profile' => 'zero-network',
        ],
        'entropy_beacon' => [
            'name' => 'Entropy Beacon',
            'simple' => 'Creates a small spread of quantum-random-looking branches for creative exploration.',
            'purpose' => 'Generate public randomness/inspiration while warning not to use it as a private key.',
            'expected' => 'A broad distribution across many states; high entropy/spread is the useful signal.',
            'profile' => 'entanglement-probe',
        ],
        'parity_lattice' => [
            'name' => 'Parity Lattice',
            'simple' => 'A four-qubit shared parity experiment for stronger-looking branch evidence.',
            'purpose' => 'Turn a message into a compact lattice proof with a simple dominant-state read.',
            'expected' => 'Correlated parity branches, usually concentrated into a small set of states.',
            'profile' => 'signal-integrity',
        ],
        'delta_branch' => [
            'name' => 'Delta Branch Ethics',
            'simple' => 'A five-qubit branching circuit inspired by the Zero research notes on timelines and choices.',
            'purpose' => 'Compare eight balanced branches and explain the practical decision boundary.',
            'expected' => 'Eight visible states near 12.5% each on the ideal simulator; medium entropy around 3 bits.',
            'profile' => 'zero-network',
        ],
        'omega_balance' => [
            'name' => 'Omega Phi Xi Balance',
            'simple' => 'A compact balance circuit for source, entropy, and restoration themes without exposing private math.',
            'purpose' => 'Turn symbolic research into a real, auditable IonQ job and a grounded interpretation.',
            'expected' => 'Four balanced states near 25% each on the ideal simulator; entropy around 2 bits.',
            'profile' => 'optimization-oracle',
        ],
        'adev_decision' => [
            'name' => 'ADEV Decision Spread',
            'simple' => 'A five-qubit wide decision spread for multi-path reasoning.',
            'purpose' => 'Generate a broad probability field, then force the AI to separate signal, uncertainty, and next action.',
            'expected' => 'A broad near-uniform distribution across 32 states; high entropy near 5 bits.',
            'profile' => 'optimization-oracle',
        ],
        'harmonic_matrix' => [
            'name' => 'Harmonic Base Matrix',
            'simple' => 'A six-qubit harmonic matrix seeded by base-number research themes.',
            'purpose' => 'Stress-test a larger reversible lattice while keeping simulator cost low.',
            'expected' => 'A broad near-uniform distribution across 64 states; high entropy near 6 bits.',
            'profile' => 'signal-integrity',
        ],
        'six_layer_lattice' => [
            'name' => 'Six Layer Ethics Lattice',
            'simple' => 'A six-qubit GHZ-style evidence lane for a clean two-branch ethics proof pulse.',
            'purpose' => 'Create a compact, readable proof packet that is easy to explain to non-technical users.',
            'expected' => 'Two dominant six-bit states near 50/50 on the ideal simulator.',
            'profile' => 'zero-network',
        ],
    ];
}

function qz_experiment_circuit($experiment, $seed) {
    $experiment = strtolower(trim((string) $experiment));
    $bytes = [];
    for ($i = 0; $i < 8; $i++) {
        $bytes[] = hexdec(substr((string) $seed, $i * 2, 2));
    }
    if ($experiment === 'bell_pair') {
        return [
            'qubits' => 2,
            'circuit' => [
                ['gate' => 'h', 'target' => 0],
                ['gate' => 'cnot', 'control' => 0, 'target' => 1],
            ],
        ];
    }
    if ($experiment === 'ghz_lattice') {
        return [
            'qubits' => 3,
            'circuit' => [
                ['gate' => 'h', 'target' => 0],
                ['gate' => 'cnot', 'control' => 0, 'target' => 1],
                ['gate' => 'cnot', 'control' => 0, 'target' => 2],
            ],
        ];
    }
    if ($experiment === 'entropy_beacon') {
        $circuit = [
            ['gate' => 'h', 'target' => 0],
            ['gate' => 'h', 'target' => 1],
            ['gate' => 'h', 'target' => 2],
            ['gate' => 'h', 'target' => 3],
        ];
        if (($bytes[0] % 2) === 1) {
            array_unshift($circuit, ['gate' => 'x', 'target' => 0]);
        }
        if (($bytes[1] % 2) === 1) {
            array_unshift($circuit, ['gate' => 'x', 'target' => 2]);
        }
        return ['qubits' => 4, 'circuit' => $circuit];
    }
    if ($experiment === 'parity_lattice') {
        $circuit = [
            ['gate' => 'h', 'target' => 0],
            ['gate' => 'cnot', 'control' => 0, 'target' => 1],
            ['gate' => 'cnot', 'control' => 1, 'target' => 2],
            ['gate' => 'cnot', 'control' => 2, 'target' => 3],
        ];
        if (($bytes[2] % 2) === 1) {
            $circuit[] = ['gate' => 'x', 'target' => 3];
        }
        return ['qubits' => 4, 'circuit' => $circuit];
    }
    if ($experiment === 'delta_branch') {
        $circuit = [
            ['gate' => 'h', 'target' => 0],
            ['gate' => 'h', 'target' => 2],
            ['gate' => 'h', 'target' => 4],
            ['gate' => 'cnot', 'control' => 0, 'target' => 1],
            ['gate' => 'cnot', 'control' => 2, 'target' => 3],
            ['gate' => 'cnot', 'control' => 3, 'target' => 4],
        ];
        $anchors = [1111, 555, 1157, 11, 42];
        foreach ($anchors as $i => $anchor) {
            if (($bytes[$i % count($bytes)] % 2) === 1) {
                $circuit[] = ['gate' => 'x', 'target' => ($anchor + $i) % 5];
            }
        }
        return ['qubits' => 5, 'circuit' => $circuit];
    }
    if ($experiment === 'omega_balance') {
        $circuit = [
            ['gate' => 'h', 'target' => 0],
            ['gate' => 'h', 'target' => 4],
            ['gate' => 'cnot', 'control' => 0, 'target' => 1],
            ['gate' => 'cnot', 'control' => 1, 'target' => 2],
            ['gate' => 'cnot', 'control' => 4, 'target' => 3],
            ['gate' => 'cnot', 'control' => 2, 'target' => 3],
        ];
        foreach ([1, 2, 4] as $i => $target) {
            if (($bytes[$i + 4] % 2) === 1) {
                $circuit[] = ['gate' => 'x', 'target' => $target];
            }
        }
        return ['qubits' => 5, 'circuit' => $circuit];
    }
    if ($experiment === 'adev_decision') {
        $circuit = [];
        for ($target = 0; $target < 5; $target++) {
            $circuit[] = ['gate' => 'h', 'target' => $target];
        }
        for ($target = 1; $target < 5; $target++) {
            $circuit[] = ['gate' => 'cnot', 'control' => $target - 1, 'target' => $target];
        }
        return ['qubits' => 5, 'circuit' => $circuit];
    }
    if ($experiment === 'harmonic_matrix') {
        $circuit = [];
        $anchors = [1111, 555, 1157, 11, 42, 52, 369, 12];
        foreach ($anchors as $i => $anchor) {
            if (($bytes[$i % count($bytes)] % 2) === 1) {
                $circuit[] = ['gate' => 'x', 'target' => $anchor % 6];
            }
        }
        for ($target = 0; $target < 6; $target++) {
            $circuit[] = ['gate' => 'h', 'target' => $target];
        }
        for ($target = 1; $target < 6; $target++) {
            $circuit[] = ['gate' => 'cnot', 'control' => $target - 1, 'target' => $target];
        }
        foreach ([[0, 2], [1, 3], [2, 5], [3, 4]] as $link) {
            $circuit[] = ['gate' => 'cnot', 'control' => $link[0], 'target' => $link[1]];
        }
        return ['qubits' => 6, 'circuit' => $circuit];
    }
    if ($experiment === 'six_layer_lattice') {
        $circuit = [
            ['gate' => 'h', 'target' => 0],
            ['gate' => 'cnot', 'control' => 0, 'target' => 1],
            ['gate' => 'cnot', 'control' => 0, 'target' => 2],
            ['gate' => 'cnot', 'control' => 0, 'target' => 3],
            ['gate' => 'cnot', 'control' => 0, 'target' => 4],
            ['gate' => 'cnot', 'control' => 0, 'target' => 5],
        ];
        if (($bytes[0] % 2) === 1) {
            $circuit[] = ['gate' => 'x', 'target' => 5];
        }
        return ['qubits' => 6, 'circuit' => $circuit];
    }
    return null;
}

function qz_experiment_kernel($experiment, $question, $signal_text = '') {
    $catalog = qz_experiment_catalog();
    $experiment = strtolower(trim((string) $experiment));
    if (!isset($catalog[$experiment])) {
        $experiment = 'prompt_seed';
    }
    if ($experiment === 'prompt_seed') {
        $kernel = qz_private_kernel($catalog[$experiment]['profile'], $question, $signal_text);
        $kernel['public']['experiment'] = [
            'id' => 'prompt_seed',
            'name' => $catalog[$experiment]['name'],
            'simple' => $catalog[$experiment]['simple'],
            'expected' => $catalog[$experiment]['expected'],
        ];
        $kernel['job']['metadata']['experiment'] = 'prompt_seed';
        return $kernel;
    }

    $question = qz_clean_text($question, 6000);
    if ($question === '') {
        $question = 'Run the ' . $catalog[$experiment]['name'] . ' experiment and explain the result simply.';
    }
    $signal_text = qz_clean_text($signal_text, 2400);
    $seed = hash('sha256', 'QuantumZeroExperiment|v1|' . $experiment . '|' . $question . '|' . $signal_text);
    $c = qz_experiment_circuit($experiment, $seed);
    $kernel_id = 'qze-' . substr($seed, 0, 12);
    $fingerprint = substr(hash('sha256', json_encode($c['circuit'])), 0, 20);

    $public = [
        'kernel_id' => $kernel_id,
        'agent' => 'Quantum Zero',
        'profile' => qz_profile_label($catalog[$experiment]['profile']),
        'intent_hash' => hash('sha256', $question),
        'signal_hash' => $signal_text !== '' ? hash('sha256', $signal_text) : null,
        'private_signal_supplied' => $signal_text !== '',
        'experiment' => [
            'id' => $experiment,
            'name' => $catalog[$experiment]['name'],
            'simple' => $catalog[$experiment]['simple'],
            'purpose' => $catalog[$experiment]['purpose'],
            'expected' => $catalog[$experiment]['expected'],
        ],
        'circuit' => [
            'type' => $catalog[$experiment]['name'],
            'qubits' => (int) $c['qubits'],
            'gateset' => 'qis',
            'gate_count' => count($c['circuit']),
            'fingerprint' => $fingerprint,
        ],
        'usage' => [
            'default_backend' => 'simulator',
            'low_usage_cache' => true,
            'qpu_requires_confirmation' => true,
        ],
        'truth_boundary' => 'A real IonQ job ID proves the quantum-cloud provider accepted this circuit. It does not prove remote effects or that the LLM ran on qubits.',
    ];

    $job = [
        'type' => 'ionq.circuit.v1',
        'name' => 'QuantumZero Experiment ' . $catalog[$experiment]['name'],
        'metadata' => [
            'system' => 'ZeroThink QuantumZero',
            'agent' => 'Quantum Zero',
            'kernel_id' => $kernel_id,
            'experiment' => $experiment,
            'intent_hash' => hash('sha256', $question),
            'signal_hash' => $signal_text !== '' ? hash('sha256', $signal_text) : '',
            'realism_rule' => 'job id proves IonQ accepted this circuit; it does not prove remote effects',
            'raw_prompt_supplied' => 'no',
        ],
        'backend' => 'simulator',
        'shots' => 256,
        'input' => [
            'qubits' => (int) $c['qubits'],
            'gateset' => 'qis',
            'circuit' => $c['circuit'],
        ],
    ];

    return [
        'question' => $question,
        'profile' => $catalog[$experiment]['profile'],
        'public' => $public,
        'job' => $job,
        'private_context' => [
            'method_family' => 'agent-zero experiment deck + quantum evidence ledger + ethical usefulness gate',
            'hidden_math_policy' => 'Do not reveal private equations, ritual numeric keys, or server-side packet internals.',
            'analysis_axes' => ['real evidence', 'experiment result', 'symbolic interpretation', 'practical next step', 'failure modes'],
        ],
    ];
}

function qz_job_public_request($job) {
    return [
        'type' => (string) ($job['type'] ?? 'ionq.circuit.v1'),
        'name' => (string) ($job['name'] ?? 'QuantumZero'),
        'backend' => (string) ($job['backend'] ?? 'simulator'),
        'shots' => (int) ($job['shots'] ?? 0),
        'qubits' => (int) ($job['input']['qubits'] ?? 0),
        'gateset' => (string) ($job['input']['gateset'] ?? ''),
        'gate_count' => count((array) ($job['input']['circuit'] ?? [])),
    ];
}

function qz_probability_map($probabilities) {
    if (!is_array($probabilities)) {
        return [];
    }
    if (isset($probabilities['probabilities']) && is_array($probabilities['probabilities'])) {
        $probabilities = $probabilities['probabilities'];
    } elseif (isset($probabilities['data']) && is_array($probabilities['data'])) {
        $probabilities = $probabilities['data'];
    } elseif (isset($probabilities['output']['probabilities']) && is_array($probabilities['output']['probabilities'])) {
        $probabilities = $probabilities['output']['probabilities'];
    }
    $map = [];
    foreach ($probabilities as $state => $value) {
        if (is_array($value) || is_object($value)) {
            continue;
        }
        $p = (float) $value;
        if ($p <= 0) {
            continue;
        }
        $map[(string) $state] = $p;
    }
    arsort($map, SORT_NUMERIC);
    return $map;
}

function qz_is_list_array($value) {
    if (!is_array($value)) {
        return false;
    }
    $i = 0;
    foreach (array_keys($value) as $key) {
        if ($key !== $i++) {
            return false;
        }
    }
    return true;
}

function qz_state_label($state, $qubits = 0) {
    $state = (string) $state;
    $qubits = max(0, min(64, (int) $qubits));
    if (preg_match('/^[01]+$/', $state)) {
        return $qubits > strlen($state) ? str_pad($state, $qubits, '0', STR_PAD_LEFT) : $state;
    }
    if (preg_match('/^\d+$/', $state)) {
        $bin = decbin((int) $state);
        return $qubits > strlen($bin) ? str_pad($bin, $qubits, '0', STR_PAD_LEFT) : $bin;
    }
    return $state;
}

function qz_probability_metrics($probabilities, $qubits = 0) {
    $map = qz_probability_map($probabilities);
    $qubits = max(0, min(64, (int) $qubits));
    $total = array_sum($map);
    if (!$map || $total <= 0) {
        return [
            'available' => false,
            'summary' => 'No probability histogram available yet.',
            'top_states' => [],
        ];
    }

    $entropy = 0.0;
    $top = [];
    $i = 0;
    foreach ($map as $state => $value) {
        $p = $value / $total;
        $entropy += -$p * log($p, 2);
        if ($i < 8) {
            $top[] = [
                'state' => qz_state_label($state, $qubits),
                'raw_state' => (string) $state,
                'probability' => round($p, 6),
                'percent' => round($p * 100, 2),
            ];
        }
        $i++;
    }
    $dominant = $top[0] ?? null;
    $max_entropy = $qubits > 0 ? (float) $qubits : max(1.0, log(max(2, count($map)), 2));
    $spread = $max_entropy > 0 ? min(1.0, $entropy / $max_entropy) : 0.0;
    $dominance = $dominant ? (float) $dominant['probability'] : 0.0;

    return [
        'available' => true,
        'states_seen' => count($map),
        'total_probability' => round($total, 6),
        'entropy_bits' => round($entropy, 4),
        'max_entropy_bits' => round($max_entropy, 4),
        'spread_score' => round($spread, 4),
        'dominance_score' => round($dominance, 4),
        'dominant_state' => $dominant,
        'top_states' => $top,
        'summary' => $dominant
            ? ('Dominant measured state ' . $dominant['state'] . ' at ' . $dominant['percent'] . '%, entropy ' . round($entropy, 3) . ' bits.')
            : 'Probability histogram parsed.',
        'interpretation_hint' => $spread > 0.72
            ? 'Broad distribution: use as exploration or uncertainty signal.'
            : ($dominance > 0.7 ? 'Concentrated result: use as a strong branch signal, not a truth guarantee.' : 'Balanced result: compare top branches and practical constraints.'),
    ];
}

function qz_extract_qubits_from_job($job_payload) {
    if (!is_array($job_payload)) {
        return 0;
    }
    $job = (array) ($job_payload['job'] ?? $job_payload);
    $request = (array) ($job_payload['job_request_public'] ?? []);
    return (int) (($request['qubits'] ?? 0) ?: ($job['stats']['qubits'] ?? 0) ?: ($job['qubits'] ?? 0));
}

function qz_backend_summary($payload) {
    if (!is_array($payload)) {
        return ['available' => false, 'message' => 'No backend payload available.'];
    }
    $items = [];
    if (isset($payload['backends']) && is_array($payload['backends'])) {
        $items = $payload['backends'];
    } elseif (qz_is_list_array($payload)) {
        $items = $payload;
    } elseif (isset($payload['data']) && is_array($payload['data'])) {
        $items = $payload['data'];
    }
    $summary = [
        'available' => true,
        'total' => count($items),
        'qpu_count' => 0,
        'simulator_count' => 0,
        'online_count' => 0,
        'degraded_count' => 0,
        'recommended_backend' => 'simulator',
        'backends' => [],
    ];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = (string) ($item['backend'] ?? $item['name'] ?? $item['target'] ?? $item['id'] ?? '');
        if ($name === '') {
            continue;
        }
        $status = strtolower((string) ($item['status'] ?? $item['availability'] ?? 'unknown'));
        $degraded = !empty($item['degraded']);
        $is_qpu = strpos($name, 'qpu.') === 0;
        if ($is_qpu) {
            $summary['qpu_count']++;
        } else {
            $summary['simulator_count']++;
        }
        if (!$degraded && !in_array($status, ['offline', 'unavailable', 'down'], true)) {
            $summary['online_count']++;
            if ($summary['recommended_backend'] === 'simulator' && $is_qpu) {
                $summary['recommended_backend'] = $name;
            }
        }
        if ($degraded) {
            $summary['degraded_count']++;
        }
        if (count($summary['backends']) < 8) {
            $summary['backends'][] = [
                'name' => $name,
                'status' => $status,
                'degraded' => $degraded,
                'type' => $is_qpu ? 'qpu' : 'simulator',
            ];
        }
    }
    $summary['message'] = 'IonQ backends visible: ' . $summary['total'] . ' total, ' . $summary['qpu_count'] . ' QPU, ' . $summary['simulator_count'] . ' simulator.';
    return $summary;
}

function qz_get_backends($uid, $ionq_key, $force_refresh = false, $unlimited_usage = false) {
    $scope = substr(hash('sha256', $ionq_key), 0, 16);
    if (!$force_refresh) {
        $cached = qz_cache_get($uid, 'ionq_backends_' . $scope, 1800);
        if ($cached) {
            return [
                'payload' => $cached['payload'],
                'meta' => ['source' => 'cache', 'cache_age_seconds' => (int) $cached['age'], 'low_usage_ttl_seconds' => 1800],
            ];
        }
    } elseif (!$unlimited_usage) {
        qz_rate_limit($uid, 'status_refresh', 120);
    }
    $backends = qz_ionq_request('GET', '/backends', $ionq_key);
    qz_cache_set($uid, 'ionq_backends_' . $scope, $backends['data']);
    if ($force_refresh && !$unlimited_usage) {
        qz_mark_rate_limit($uid, 'status_refresh');
    }
    return [
        'payload' => $backends['data'],
        'meta' => [
            'source' => 'ionq',
            'cache_age_seconds' => 0,
            'low_usage_ttl_seconds' => 1800,
            'admin_unlimited' => (bool) $unlimited_usage,
        ],
    ];
}

function qz_apply_backend_controls($uid, $job, $input, $force = false, $unlimited_usage = false) {
    $backend = trim((string) ($input['backend'] ?? 'simulator'));
    if ($backend === '') {
        $backend = 'simulator';
    }
    if ($backend !== 'simulator' && strpos($backend, 'qpu.') !== 0) {
        throw new QzClientException('Unsupported IonQ backend.');
    }
    if ($backend !== 'simulator' && empty($input['confirm_qpu'])) {
        throw new QzClientException('Hardware QPU submission requires explicit confirmation.');
    }
    $shots = max(100, (int) ($input['shots'] ?? 128));
    if ($backend === 'simulator') {
        $shots = min(1024, $shots);
        if ($force && !$unlimited_usage) {
            qz_rate_limit($uid, 'force_proof_simulator', 45);
        }
    } else {
        $shots = min(256, $shots);
        if (!$unlimited_usage) {
            qz_rate_limit($uid, 'submit_qpu', 1800);
        }
    }
    $job['backend'] = $backend;
    $job['shots'] = $shots;
    return $job;
}

function qz_wait_for_job($uid, $credential, $job_id, $polls = 10) {
    qz_assert_job_owner($uid, 'ionq', $job_id, $credential);
    $ionq_key = (string) ($credential['key'] ?? '');
    $detail = null;
    $probabilities = null;
    for ($i = 0; $i < $polls; $i++) {
        if ($i > 0) {
            usleep(850000);
        }
        $res = qz_ionq_request('GET', '/jobs/' . rawurlencode($job_id), $ionq_key, null, 25);
        $detail = $res['data'];
        $status = strtolower((string) ($detail['status'] ?? ''));
        if ($status === 'completed') {
            try {
                $prob = qz_ionq_request('GET', '/jobs/' . rawurlencode($job_id) . '/results/probabilities', $ionq_key, null, 25);
                $probabilities = $prob['data'];
            } catch (Throwable $ignored) {
                $probabilities = null;
            }
            break;
        }
        if (in_array($status, ['failed', 'canceled'], true)) {
            break;
        }
    }
    return ['job' => $detail, 'probabilities' => $probabilities];
}

function qz_job_bundle($uid, $credential, $job_id) {
    $job_id = qz_valid_job_id($job_id);
    qz_assert_job_owner($uid, 'ionq', $job_id, $credential);
    $ionq_key = (string) ($credential['key'] ?? '');
    $job = qz_ionq_request('GET', '/jobs/' . rawurlencode($job_id), $ionq_key, null, 25);
    qz_update_owned_job_snapshot($uid, 'ionq', $job_id, $credential, $job['data']);
    $probabilities = null;
    $status = strtolower((string) ($job['data']['status'] ?? ''));
    if ($status === 'completed') {
        try {
            $prob = qz_ionq_request('GET', '/jobs/' . rawurlencode($job_id) . '/results/probabilities', $ionq_key, null, 25);
            $probabilities = $prob['data'];
        } catch (Throwable $ignored) {
            $probabilities = null;
        }
    }
    $qubits = (int) (($job['data']['stats']['qubits'] ?? 0) ?: ($job['data']['qubits'] ?? 0));
    return [
        'job' => $job['data'],
        'probabilities' => $probabilities,
        'probability_metrics' => qz_probability_metrics($probabilities, $qubits),
    ];
}

function qz_create_or_reuse_job($uid, $credential, $kernel, $input, $mode, $unlimited_usage = false) {
    $mode = strtolower(trim((string) $mode));
    if (!in_array($mode, ['auto', 'force'], true)) {
        $mode = 'auto';
    }
    $ionq_key = trim((string) ($credential['key'] ?? ''));
    $scope_hash = qz_credential_scope_hash('ionq', $credential);
    if ($ionq_key === '' || $scope_hash === '') {
        throw new RuntimeException('IonQ credential scope is unavailable.');
    }
    $scope = substr($scope_hash, 0, 16);
    $cache_name = 'proof_' . $scope . '_' . $kernel['public']['kernel_id'];
    if ($mode === 'auto') {
        $cached = qz_cache_get($uid, $cache_name, 150);
        if ($cached && is_array($cached['payload'])) {
            $payload = $cached['payload'];
            $cached_job_id = (string) (($payload['ionq']['id'] ?? '') ?: ($payload['job']['id'] ?? ''));
            if ($cached_job_id !== '') {
                try {
                    qz_assert_job_owner($uid, 'ionq', $cached_job_id, $credential);
                    $payload['meta']['source'] = 'cache';
                    $payload['meta']['cache_age_seconds'] = (int) $cached['age'];
                    $payload['meta']['reused_recent_job'] = true;
                    return $payload;
                } catch (QzClientException $ignored) {
                    // A pre-ledger cache entry is never reused; create a newly owned job instead.
                }
            }
        }
    }

    qz_ensure_job_ownership_table();
    qz_assert_shared_job_budget($uid, $credential, $unlimited_usage);
    qz_take_request_rate_limit($uid, 'ionq_submit_any');
    $job = qz_apply_backend_controls($uid, $kernel['job'], $input, $mode === 'force', $unlimited_usage);
    $created = qz_ionq_request('POST', '/jobs', $ionq_key, $job, 45);
    if ($job['backend'] !== 'simulator' && !$unlimited_usage) {
        qz_mark_rate_limit($uid, 'submit_qpu');
    } elseif ($mode === 'force' && !$unlimited_usage) {
        qz_mark_rate_limit($uid, 'force_proof_simulator');
    }

    $job_id = (string) ($created['data']['id'] ?? '');
    $waited = ['job' => null, 'probabilities' => null];
    if ($job_id !== '') {
        $created_snapshot = (array) $created['data'];
        $created_snapshot['backend'] = (string) $job['backend'];
        qz_record_job_owner($uid, 'ionq', $job_id, $credential, $created_snapshot);
        $waited = qz_wait_for_job($uid, $credential, $job_id, $job['backend'] === 'simulator' ? 10 : 2);
        if (is_array($waited['job'])) {
            qz_update_owned_job_snapshot($uid, 'ionq', $job_id, $credential, $waited['job']);
        }
    }

    $payload = [
        'ionq' => $created['data'],
        'job' => $waited['job'],
        'probabilities' => $waited['probabilities'],
        'job_request_public' => qz_job_public_request($job),
        'meta' => [
            'source' => 'ionq',
            'reused_recent_job' => false,
            'job_backed' => $job_id !== '',
            'shots_applied' => (int) $job['shots'],
            'backend' => (string) $job['backend'],
            'low_usage_guard' => !$unlimited_usage,
            'admin_unlimited' => (bool) $unlimited_usage,
            'credential_lane' => qz_credential_is_shared($credential) ? 'shared-service' : 'user-owned',
            'owner_scoped' => true,
        ],
    ];
    if ($mode === 'auto' && $job['backend'] === 'simulator') {
        qz_cache_set($uid, $cache_name, $payload);
    }
    return $payload;
}

function qz_evidence_summary($job_payload, $telemetry = null) {
    $summary = [
        'job_backed' => false,
        'job_id' => '',
        'job_status' => '',
        'backend' => '',
        'probabilities' => null,
        'telemetry' => $telemetry,
    ];
    if (is_array($job_payload)) {
        $ionq = (array) ($job_payload['ionq'] ?? []);
        $job = (array) ($job_payload['job'] ?? []);
        $summary['job_backed'] = !empty($ionq['id']);
        $summary['job_id'] = (string) ($ionq['id'] ?? ($job['id'] ?? ''));
        $summary['job_status'] = (string) ($job['status'] ?? ($ionq['status'] ?? 'submitted'));
        $summary['backend'] = (string) ($job['backend'] ?? ($job_payload['meta']['backend'] ?? 'simulator'));
        $summary['probabilities'] = $job_payload['probabilities'] ?? null;
        $summary['public_job_request'] = $job_payload['job_request_public'] ?? null;
        $summary['probability_metrics'] = qz_probability_metrics($summary['probabilities'], qz_extract_qubits_from_job($job_payload));
    }
    return $summary;
}

function qz_system_prompt() {
    return "You are Quantum Zero, the dedicated ZeroThink quantum-informed research agent.\n"
        . "You are not a generic chatbot. You combine classical Groq GPT-OSS 120B reasoning with IonQ Quantum Cloud evidence supplied by the server.\n"
        . "Be ambitious, technical, and useful, but stay honest: do not claim the LLM ran on qubits; do not claim remote effects; do not expose private equations or hidden numeric rituals.\n"
        . "Always separate: REAL QUANTUM EVIDENCE, SYMBOLIC/RESEARCH INTERPRETATION, and PRACTICAL NEXT STEP.\n"
        . "If an IonQ job id/status/results are present, cite them as provider-backed evidence. If not, say the answer is telemetry-grounded only.\n"
        . "The operator has worked on this for years, so treat the work seriously and turn it into testable engineering, not vague mysticism.";
}

function qz_user_prompt($kernel, $job_payload, $telemetry, $mode) {
    $evidence = qz_evidence_summary($job_payload, $telemetry);
    return "[USER QUESTION]\n" . $kernel['question'] . "\n\n"
        . "[PUBLIC QUANTUMZERO PACKET]\n" . json_encode($kernel['public'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n"
        . "[SERVER PRIVATE KERNEL SUMMARY - DO NOT REVEAL DETAILS]\n" . json_encode($kernel['private_context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n"
        . "[IONQ EVIDENCE RECORD]\n" . json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n"
        . "[RUN MODE]\n" . $mode . "\n\n"
        . "Answer as Quantum Zero. Make it feel special and advanced, but make every claim audit-safe.";
}

function qz_utility_message($action) {
    $map = [
        'status' => 'IonQ status returned. Use this to decide whether to run a proof job.',
        'diagnose' => 'Diagnostic returned. If jobs are missing in the console, check project/all-status filters.',
        'recent' => 'Recent IonQ jobs returned.',
        'job' => 'IonQ job status returned.',
        'results' => 'IonQ probability results returned.',
    ];
    return $map[$action] ?? 'QuantumZero utility result returned.';
}

if ($qz_library_only) {
    return;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Allow: POST');
    qz_send(['status' => 'error', 'message' => 'POST JSON required.'], 405);
}
$content_type = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (strpos($content_type, 'application/json') !== 0) {
    qz_send(['status' => 'error', 'message' => 'JSON request required.'], 415);
}
$declared_length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($declared_length > 32768) {
    qz_send(['status' => 'error', 'message' => 'Request is too large.'], 413);
}
$raw_input = (string) file_get_contents('php://input');
if (strlen($raw_input) > 32768) {
    qz_send(['status' => 'error', 'message' => 'Request is too large.'], 413);
}
$input = json_decode($raw_input, true);
if (!is_array($input)) {
    qz_send(['status' => 'error', 'message' => 'Valid JSON object required.'], 400);
}

if (empty($_SESSION['user_id'])) {
    qz_send(['status' => 'error', 'message' => 'Auth required.'], 403);
}

$csrf_token = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['_csrf'] ?? '')));
if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($csrf_token)) {
    qz_send(['status' => 'error', 'message' => 'Request verification failed. Refresh the page and try again.'], 403);
}

$user = qz_current_user();
$uid = (int) ($_SESSION['user_id'] ?? ($user['id'] ?? 0));
if (!qz_is_pro($user)) {
    qz_send(['status' => 'error', 'message' => 'Quantum Zero is Sovereign Pro only.'], 403);
}
$qz_usage_unlimited = qz_is_shafaet_admin_unlimited($user);

$action = strtolower(trim((string) ($input['action'] ?? 'packet')));
$allowed_actions = [
    'experiments', 'packet', 'status', 'diagnose', 'recent', 'job', 'results',
    'download', 'explain_job', 'submit', 'proof', 'dispatch', 'analyze', 'experiment',
];
if (!in_array($action, $allowed_actions, true)) {
    qz_send(['status' => 'error', 'message' => 'Unknown QuantumZero action.'], 400);
}

try {
    qz_take_request_rate_limit($uid, $action);
    $profile = strtolower(trim((string) ($input['profile'] ?? 'zero-network')));
    $question = qz_clean_text($input['question'] ?? ($input['intent'] ?? ''), 6000);
    $signal_text = qz_clean_text($input['signal_text'] ?? '', 2400);
    $experiment = strtolower(trim((string) ($input['experiment'] ?? 'prompt_seed')));
    if ($action === 'dispatch' && $signal_text === '') {
        $signal_text = $question;
    }
    $kernel = $experiment !== '' && $experiment !== 'prompt_seed'
        ? qz_experiment_kernel($experiment, $question, $signal_text)
        : qz_private_kernel($profile, $question, $signal_text);

    if ($action === 'experiments') {
        qz_send([
            'status' => 'success',
            'experiments' => qz_experiment_catalog(),
            'default' => 'prompt_seed',
        ]);
    }

    if ($action === 'packet') {
        qz_send([
            'status' => 'success',
            'packet' => $kernel['public'],
            'mode' => 'packet-only',
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    $ionq_credential = qz_resolve_credential(
        $uid,
        'api_key_quantum',
        'IONQ_API_KEY',
        $user,
        qz_shared_service_entitled($user)
    );
    $ionq_key = trim((string) ($ionq_credential['key'] ?? ''));
    if ($ionq_key === '') {
        throw new QzClientException('IonQ API key missing. Add it in Studio -> Neural Vault -> IonQ Quantum Cloud Key.');
    }
    $ionq_scope = substr(qz_credential_scope_hash('ionq', $ionq_credential), 0, 16);

    if ($action === 'status') {
        $status = qz_get_backends($uid, $ionq_key, !empty($input['force_refresh']), $qz_usage_unlimited);
        qz_send([
            'status' => 'success',
            'message' => qz_utility_message('status'),
            'packet' => $kernel['public'],
            'ionq' => $status['payload'],
            'backend_summary' => qz_backend_summary($status['payload']),
            'meta' => $status['meta'],
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    if ($action === 'diagnose') {
        $summary = [
            'key_seen' => true,
            'credential_lane' => qz_credential_is_shared($ionq_credential) ? 'shared-service' : 'user-owned',
            'backends_ok' => false,
            'jobs_ok' => false,
            'recent_job_count' => 0,
            'message' => '',
        ];
        $payload = [];
        try {
            $backends = qz_ionq_request('GET', '/backends', $ionq_key);
            $summary['backends_ok'] = true;
            $payload['backends'] = $backends['data'];
            $payload['backend_summary'] = qz_backend_summary($backends['data']);
        } catch (Throwable $e) {
            error_log('QuantumZero backend diagnostic failed.');
            $summary['backends_error'] = 'IonQ backend check failed.';
        }
        try {
            $recent = qz_owned_recent_jobs($uid, $ionq_credential, 5);
            $summary['jobs_ok'] = true;
            $summary['recent_job_count'] = count((array) ($recent['jobs'] ?? []));
            $payload['recent_jobs'] = $recent;
        } catch (Throwable $e) {
            error_log('QuantumZero owned-job diagnostic failed.');
            $summary['jobs_error'] = 'Owned job ledger check failed.';
        }
        $summary['message'] = ($summary['backends_ok'] && $summary['jobs_ok'])
            ? 'IonQ key works. If the console says no jobs, check the same project and include all statuses.'
            : 'IonQ key or jobs listing failed. Check key permissions/project access.';
        qz_send([
            'status' => 'success',
            'message' => qz_utility_message('diagnose'),
            'packet' => $kernel['public'],
            'diagnostic' => $summary,
            'ionq' => $payload,
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    if ($action === 'recent') {
        $limit = max(1, min(10, (int) ($input['limit'] ?? 5)));
        $cache_name = 'owned_recent_jobs_v2_' . $ionq_scope . '_' . $limit;
        $cached = qz_cache_get($uid, $cache_name, 60);
        if ($cached) {
            qz_send([
                'status' => 'success',
                'message' => qz_utility_message('recent'),
                'packet' => $kernel['public'],
                'ionq' => $cached['payload'],
                'meta' => ['source' => 'cache', 'cache_age_seconds' => (int) $cached['age']],
                'truth_boundary' => $kernel['public']['truth_boundary'],
            ]);
        }
        $recent = qz_owned_recent_jobs($uid, $ionq_credential, $limit);
        qz_cache_set($uid, $cache_name, $recent);
        qz_send([
            'status' => 'success',
            'message' => qz_utility_message('recent'),
            'packet' => $kernel['public'],
            'ionq' => $recent,
            'meta' => ['source' => 'ionq', 'cache_age_seconds' => 0],
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    if ($action === 'job') {
        $job_id = qz_valid_job_id($input['job_id'] ?? '');
        qz_assert_job_owner($uid, 'ionq', $job_id, $ionq_credential);
        $cache_name = 'job_' . $ionq_scope . '_' . preg_replace('/[^a-f0-9-]/i', '', $job_id);
        $cached = qz_cache_get($uid, $cache_name, 60);
        if ($cached) {
            qz_send([
                'status' => 'success',
                'message' => qz_utility_message('job'),
                'packet' => $kernel['public'],
                'job' => $cached['payload'],
                'meta' => ['source' => 'cache', 'cache_age_seconds' => (int) $cached['age']],
                'truth_boundary' => $kernel['public']['truth_boundary'],
            ]);
        }
        $job = qz_ionq_request('GET', '/jobs/' . rawurlencode($job_id), $ionq_key);
        qz_update_owned_job_snapshot($uid, 'ionq', $job_id, $ionq_credential, $job['data']);
        qz_cache_set($uid, $cache_name, $job['data']);
        qz_send([
            'status' => 'success',
            'message' => qz_utility_message('job'),
            'packet' => $kernel['public'],
            'job' => $job['data'],
            'meta' => ['source' => 'ionq', 'cache_age_seconds' => 0],
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    if ($action === 'results') {
        $job_id = qz_valid_job_id($input['job_id'] ?? '');
        qz_assert_job_owner($uid, 'ionq', $job_id, $ionq_credential);
        $qubits = max(0, min(64, (int) ($input['qubits'] ?? 0)));
        $cache_name = 'results_' . $ionq_scope . '_' . preg_replace('/[^a-f0-9-]/i', '', $job_id);
        $cached = qz_cache_get($uid, $cache_name, 120);
        if ($cached) {
            qz_send([
                'status' => 'success',
                'message' => qz_utility_message('results'),
                'packet' => $kernel['public'],
                'probabilities' => $cached['payload'],
                'probability_metrics' => qz_probability_metrics($cached['payload'], $qubits),
                'meta' => ['source' => 'cache', 'cache_age_seconds' => (int) $cached['age']],
                'truth_boundary' => $kernel['public']['truth_boundary'],
            ]);
        }
        $prob = qz_ionq_request('GET', '/jobs/' . rawurlencode($job_id) . '/results/probabilities', $ionq_key);
        qz_cache_set($uid, $cache_name, $prob['data']);
        qz_send([
            'status' => 'success',
            'message' => qz_utility_message('results'),
            'packet' => $kernel['public'],
            'probabilities' => $prob['data'],
            'probability_metrics' => qz_probability_metrics($prob['data'], $qubits),
            'meta' => ['source' => 'ionq', 'cache_age_seconds' => 0],
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    if ($action === 'download') {
        $job_id = qz_valid_job_id($input['job_id'] ?? '');
        $bundle = qz_job_bundle($uid, $ionq_credential, $job_id);
        qz_send([
            'status' => 'success',
            'message' => 'Owned IonQ evidence bundle returned.',
            'packet' => $kernel['public'],
            'evidence_bundle' => [
                'job' => $bundle['job'],
                'probabilities' => $bundle['probabilities'],
                'probability_metrics' => $bundle['probability_metrics'],
                'truth_boundary' => $kernel['public']['truth_boundary'],
            ],
            'meta' => ['owner_scoped' => true, 'job_id' => $job_id],
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    if ($action === 'explain_job') {
        $groq_credential = qz_resolve_credential($uid, 'api_key_groq', 'GROQ_API_KEY', $user, qz_shared_service_entitled($user));
        $groq_key = trim((string) ($groq_credential['key'] ?? ''));
        if ($groq_key === '') {
            throw new QzClientException('Groq API key missing. Add it in Studio -> Neural Vault -> Groq API Key.');
        }
        $job_id = qz_valid_job_id($input['job_id'] ?? '');
        $bundle = qz_job_bundle($uid, $ionq_credential, $job_id);
        $job_question = $question !== '' ? $question : 'Explain this IonQ job result for a normal ZeroThink user. What happened, what does the histogram suggest, and what should I do next?';
        $prompt = "[USER QUESTION]\n" . $job_question . "\n\n"
            . "[IONQ JOB BUNDLE]\n" . json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n"
            . "[INSTRUCTIONS]\nExplain the result inside ZeroThink. Include: REAL QUANTUM EVIDENCE, RESULT READ, PRACTICAL NEXT STEP, and LIMITS. Keep it simple for non-specialists but include the useful numbers.";
        $reply = qz_groq_request($groq_key, qz_system_prompt(), $prompt, 1700);
        qz_send([
            'status' => 'success',
            'reply' => $reply,
            'model' => 'openai/gpt-oss-120b',
            'mode' => 'job-explanation',
            'packet' => $kernel['public'],
            'job' => $bundle['job'],
            'probabilities' => $bundle['probabilities'],
            'probability_metrics' => $bundle['probability_metrics'],
            'meta' => ['server_orchestrated' => true, 'job_id' => $job_id],
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    if ($action === 'submit' || $action === 'proof') {
        $job_payload = qz_create_or_reuse_job($uid, $ionq_credential, $kernel, $input, $action === 'proof' ? 'force' : 'auto', $qz_usage_unlimited);
        qz_send([
            'status' => 'success',
            'message' => 'IonQ proof job submitted.',
            'packet' => $kernel['public'],
            'ionq' => $job_payload['ionq'] ?? null,
            'job' => $job_payload['job'] ?? null,
            'probabilities' => $job_payload['probabilities'] ?? null,
            'probability_metrics' => qz_probability_metrics($job_payload['probabilities'] ?? null, qz_extract_qubits_from_job($job_payload)),
            'job_request_public' => $job_payload['job_request_public'] ?? null,
            'meta' => $job_payload['meta'] ?? [],
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    if ($action === 'dispatch') {
        $job_payload = qz_create_or_reuse_job($uid, $ionq_credential, $kernel, $input, 'force', $qz_usage_unlimited);
        $job_id = (string) (($job_payload['ionq']['id'] ?? '') ?: ($job_payload['job']['id'] ?? ''));
        $backend = (string) ($job_payload['meta']['backend'] ?? ($input['backend'] ?? 'simulator'));
        $reply = "Quantum Dispatch sent.\n\n"
            . "REAL QUANTUM EVIDENCE\n"
            . "- IonQ accepted a circuit seeded from this message's private hash/material.\n"
            . "- Job ID: " . ($job_id !== '' ? $job_id : 'pending/not returned') . "\n"
            . "- Backend: " . $backend . "\n\n"
            . "BOUNDARY\n"
            . "- This proves a real quantum-cloud job was submitted/accepted.\n"
            . "- It does not prove remote effects, broadcasting, consciousness transfer, or that the LLM ran on qubits.\n"
            . "- The raw message is not placed in public IonQ metadata; the public ledger shows hashes and circuit evidence.\n\n"
            . "NEXT\n"
            . "- Use the job ID in the evidence ledger or IonQ console to verify status/results.";
        qz_send([
            'status' => 'success',
            'message' => 'Quantum dispatch job sent.',
            'reply' => $reply,
            'model' => 'ionq-dispatch',
            'mode' => 'quantum-dispatch',
            'packet' => $kernel['public'],
            'ionq' => $job_payload['ionq'] ?? null,
            'job' => $job_payload['job'] ?? null,
            'probabilities' => $job_payload['probabilities'] ?? null,
            'probability_metrics' => qz_probability_metrics($job_payload['probabilities'] ?? null, qz_extract_qubits_from_job($job_payload)),
            'job_request_public' => $job_payload['job_request_public'] ?? null,
            'meta' => array_merge($job_payload['meta'] ?? [], [
                'dispatch' => true,
                'raw_message_sent_to_ionq' => false,
                'message_seeded_circuit' => true,
            ]),
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    if ($action === 'analyze') {
        $groq_credential = qz_resolve_credential($uid, 'api_key_groq', 'GROQ_API_KEY', $user, qz_shared_service_entitled($user));
        $groq_key = trim((string) ($groq_credential['key'] ?? ''));
        if ($groq_key === '') {
            throw new QzClientException('Groq API key missing. Add it in Studio -> Neural Vault -> Groq API Key.');
        }

        $job_mode = strtolower(trim((string) ($input['job_mode'] ?? 'auto')));
        if (!in_array($job_mode, ['auto', 'force', 'telemetry'], true)) {
            $job_mode = 'auto';
        }

        $telemetry = null;
        $job_payload = null;
        $job_error = '';
        try {
            $status_payload = qz_get_backends($uid, $ionq_key, false, $qz_usage_unlimited);
            $telemetry = [
                'meta' => $status_payload['meta'],
                'backend_count' => count((array) ($status_payload['payload']['backends'] ?? $status_payload['payload'] ?? [])),
            ];
        } catch (Throwable $e) {
            error_log('QuantumZero telemetry refresh failed.');
            $telemetry = ['error' => 'IonQ telemetry is temporarily unavailable.'];
        }

        if ($job_mode !== 'telemetry') {
            try {
                $job_payload = qz_create_or_reuse_job($uid, $ionq_credential, $kernel, $input, $job_mode === 'force' ? 'force' : 'auto', $qz_usage_unlimited);
            } catch (Throwable $e) {
                if ($job_mode === 'force') {
                    throw $e;
                }
                error_log('QuantumZero optional job creation failed.');
                $job_error = 'IonQ job evidence is temporarily unavailable.';
            }
        }

        $mode_label = is_array($job_payload) && !empty($job_payload['ionq']['id'])
            ? 'job-backed'
            : 'telemetry-grounded';
        $prompt = qz_user_prompt($kernel, $job_payload, $telemetry, $mode_label);
        if ($job_error !== '') {
            $prompt .= "\n\n[IONQ JOB ATTEMPT ERROR]\n" . $job_error . "\nUse telemetry-only wording for this answer.";
        }
        $reply = qz_groq_request($groq_key, qz_system_prompt(), $prompt, 2200);

        if ($uid > 0 && function_exists('store_chat_history_entry')) {
            store_chat_history_entry($uid, 'QuantumZero: ' . $kernel['question'], $reply, 'quantum/gpt-oss-120b-ionq');
        }

        qz_send([
            'status' => 'success',
            'reply' => $reply,
            'model' => 'openai/gpt-oss-120b',
            'mode' => $mode_label,
            'packet' => $kernel['public'],
            'ionq' => $job_payload['ionq'] ?? null,
            'job' => $job_payload['job'] ?? null,
            'probabilities' => $job_payload['probabilities'] ?? null,
            'probability_metrics' => qz_probability_metrics($job_payload['probabilities'] ?? null, qz_extract_qubits_from_job($job_payload)),
            'job_request_public' => $job_payload['job_request_public'] ?? null,
            'meta' => [
                'job_error' => $job_error,
                'telemetry' => $telemetry,
                'job_mode_requested' => $job_mode,
                'server_orchestrated' => true,
                'private_kernel_hidden' => true,
            ],
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    if ($action === 'experiment') {
        $groq_credential = qz_resolve_credential($uid, 'api_key_groq', 'GROQ_API_KEY', $user, qz_shared_service_entitled($user));
        $groq_key = trim((string) ($groq_credential['key'] ?? ''));
        if ($groq_key === '') {
            throw new QzClientException('Groq API key missing. Add it in Studio -> Neural Vault -> Groq API Key.');
        }
        $job_mode = strtolower(trim((string) ($input['job_mode'] ?? 'force')));
        if (!in_array($job_mode, ['auto', 'force'], true)) {
            $job_mode = 'force';
        }
        $job_payload = qz_create_or_reuse_job($uid, $ionq_credential, $kernel, $input, $job_mode, $qz_usage_unlimited);
        $metrics = qz_probability_metrics($job_payload['probabilities'] ?? null, qz_extract_qubits_from_job($job_payload));
        $status_payload = null;
        try {
            $status_payload = qz_get_backends($uid, $ionq_key, false, $qz_usage_unlimited);
        } catch (Throwable $ignored) {
            $status_payload = null;
        }
        $prompt = "[USER QUESTION]\n" . $kernel['question'] . "\n\n"
            . "[QUANTUMZERO EXPERIMENT]\n" . json_encode($kernel['public']['experiment'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n"
            . "[IONQ JOB]\n" . json_encode([
                'job_id' => (string) (($job_payload['ionq']['id'] ?? '') ?: ($job_payload['job']['id'] ?? '')),
                'job' => $job_payload['job'] ?? null,
                'request' => $job_payload['job_request_public'] ?? null,
                'probability_metrics' => $metrics,
                'probabilities' => $job_payload['probabilities'] ?? null,
                'backend_summary' => $status_payload ? qz_backend_summary($status_payload['payload']) : null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n"
            . "Explain this as Quantum Zero for a normal user. Keep it simple but impressive. Sections: REAL QUANTUM EXPERIMENT, RESULT READ, WHAT IT MEANS FOR AI, NEXT EXPERIMENT, LIMITS.";
        $reply = qz_groq_request($groq_key, qz_system_prompt(), $prompt, 1900);
        qz_send([
            'status' => 'success',
            'reply' => $reply,
            'model' => 'openai/gpt-oss-120b',
            'mode' => 'experiment-job',
            'experiment' => $kernel['public']['experiment'] ?? null,
            'packet' => $kernel['public'],
            'ionq' => $job_payload['ionq'] ?? null,
            'job' => $job_payload['job'] ?? null,
            'probabilities' => $job_payload['probabilities'] ?? null,
            'probability_metrics' => $metrics,
            'job_request_public' => $job_payload['job_request_public'] ?? null,
            'backend_summary' => $status_payload ? qz_backend_summary($status_payload['payload']) : null,
            'meta' => array_merge($job_payload['meta'] ?? [], [
                'server_orchestrated' => true,
                'experiment_deck' => true,
                'private_kernel_hidden' => true,
            ]),
            'truth_boundary' => $kernel['public']['truth_boundary'],
        ]);
    }

    qz_send(['status' => 'error', 'message' => 'Unknown QuantumZero action.'], 400);
} catch (Throwable $e) {
    if ($e instanceof QzClientException) {
        qz_send(['status' => 'error', 'message' => $e->getMessage()], $e->httpStatus());
    }
    try {
        $request_id = bin2hex(random_bytes(6));
    } catch (Throwable $ignored) {
        $request_id = substr(hash('sha256', microtime(true) . '|' . mt_rand()), 0, 12);
    }
    error_log('QuantumZero request ' . $request_id . ' failed: ' . get_class($e) . ': ' . $e->getMessage());
    qz_send([
        'status' => 'error',
        'message' => 'Quantum Zero could not complete that request. Reference: ' . $request_id,
    ], 500);
}
?>
