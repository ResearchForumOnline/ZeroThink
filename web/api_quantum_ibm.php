<?php
$zqibm_library_only = defined('ZQIBM_LIBRARY_ONLY') && ZQIBM_LIBRARY_ONLY;
if (!$zqibm_library_only) {
    ob_start();
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    require_once __DIR__ . '/app/bootstrap.php';
    ob_end_clean();
}
require_once __DIR__ . '/app/core/quantum_resource_guard.php';
if (is_file(__DIR__ . '/quantumibm_fallback.php')) {
    require_once __DIR__ . '/quantumibm_fallback.php';
}

function zqibm_send($payload, $status = 200) {
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function zqibm_current_user() {
    if (function_exists('request_user_record')) {
        $user = request_user_record();
        if ($user) { return $user; }
    }
    return function_exists('current_user_record') ? current_user_record() : null;
}

function zqibm_is_pro($user) {
    if (function_exists('is_architect_user') && is_architect_user($user)) { return true; }
    if (!$user) { return false; }
    return !empty($user['is_pro'])
        || in_array(strtolower((string) ($user['plan'] ?? '')), ['pro', 'god_mode'], true)
        || strtolower((string) ($user['subscription_status'] ?? '')) === 'active';
}

function zqibm_require_session_csrf(array $input) {
    $sessionUser = function_exists('current_user_record') ? current_user_record() : null;
    if ((int) ($sessionUser['id'] ?? 0) <= 0) { return; }
    $token = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ($input['_csrf'] ?? ''))));
    if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($token)) {
        throw new ZtQuantumRequestException('Request verification failed. Refresh the page and try again.', 403);
    }
}

function zqibm_clean_text($value, $limit = 6000) {
    return zt_qguard_clean_text($value, (int) $limit);
}

function zqibm_ensure_columns($db) {
    static $ready = false;
    if ($ready || !$db) { return; }
    foreach (['api_key_ibm_quantum', 'ibm_quantum_instance', 'ibm_quantum_region'] as $column) {
        try {
            $check = $db->query("SHOW COLUMNS FROM users LIKE " . $db->quote($column));
            if (!$check || !$check->fetch(PDO::FETCH_ASSOC)) {
                $db->exec("ALTER TABLE users ADD COLUMN `{$column}` TEXT NULL");
            }
        } catch (Throwable $e) {
        }
    }
    $ready = true;
}

function zqibm_vault_value($uid, $column, $envName = '') {
    $allowed = ['api_key_ibm_quantum', 'ibm_quantum_instance', 'ibm_quantum_region'];
    if (!in_array($column, $allowed, true)) { return ''; }
    if (!empty($_SESSION[$column])) { return trim((string) $_SESSION[$column]); }
    if ($envName !== '') {
        $env = trim((string) getenv($envName));
        if ($env !== '') { return $env; }
    }
    if (function_exists('site_setting')) {
        $siteValue = trim((string) site_setting('service_' . $column, ''));
        if ($siteValue !== '') { return $siteValue; }
    }
    $db = function_exists('db') ? db() : null;
    if (!$db || !$uid) { return ''; }
    zqibm_ensure_columns($db);
    try {
        $stmt = $db->prepare("SELECT {$column} FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $uid]);
        return trim((string) $stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}

function zqibm_python_candidates() {
    $root = __DIR__ . DIRECTORY_SEPARATOR . 'quantumibm-python';
    $configured = trim((string) getenv('QISKIT_PYTHON'));
    return array_values(array_unique(array_filter([
        $configured,
        $root . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
        $root . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
        'python3.12', 'python3.11', 'python3.10', 'python3', 'python',
    ], function ($value) { return trim((string) $value) !== ''; })));
}

function zqibm_run_worker($request, $timeoutSeconds = 30) {
    $script = __DIR__ . DIRECTORY_SEPARATOR . 'quantumibm-python' . DIRECTORY_SEPARATOR . 'qiskit_worker.py';
    if (!is_file($script) || !is_readable($script) || !function_exists('proc_open')) {
        if (function_exists('zt_qiskit_php_fallback_result')) {
            return zt_qiskit_php_fallback_result($request, 'Python worker unavailable.');
        }
        throw new RuntimeException('Qiskit worker unavailable.');
    }
    $deadline = microtime(true) + max(5, (int) $timeoutSeconds);
    $lastError = null;
    foreach (zqibm_python_candidates() as $python) {
        $remaining = (int) floor($deadline - microtime(true));
        if ($remaining < 1) { break; }
        try {
            return zt_qguard_run_json_process(
                [(string) $python, $script], $request, __DIR__, $remaining, 1048576
            );
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }
    if ($lastError) {
        error_log('[ZeroThink IBM worker] candidates_failed message_sha256=' . hash('sha256', $lastError->getMessage()));
    }
    if (function_exists('zt_qiskit_php_fallback_result')) {
        return zt_qiskit_php_fallback_result($request, 'Python worker unavailable.');
    }
    throw new RuntimeException('Qiskit worker unavailable.');
}

function zqibm_guard_run($uid, $shots, callable $worker) {
    zt_qguard_consume(
        'ibm_qiskit', $uid, 'requests', 1,
        ['window_seconds' => 60, 'window_limit' => zt_qguard_limit('ZEROTHINK_QIBM_USER_PER_MINUTE', 'quantum_ibm_user_per_minute', 6, 1, 30), 'daily_limit' => zt_qguard_limit('ZEROTHINK_QIBM_USER_DAILY_REQUESTS', 'quantum_ibm_user_daily_requests', 60, 5, 1000)],
        ['window_seconds' => 60, 'window_limit' => zt_qguard_limit('ZEROTHINK_QIBM_IP_PER_MINUTE', 'quantum_ibm_ip_per_minute', 30, 2, 120), 'daily_limit' => zt_qguard_limit('ZEROTHINK_QIBM_IP_DAILY_REQUESTS', 'quantum_ibm_ip_daily_requests', 300, 20, 5000)]
    );
    zt_qguard_consume(
        'ibm_qiskit', $uid, 'shots', max(32, (int) $shots),
        ['window_seconds' => 60, 'window_limit' => 16384, 'daily_limit' => zt_qguard_limit('ZEROTHINK_QIBM_USER_DAILY_SHOTS', 'quantum_ibm_user_daily_shots', 65536, 4096, 1048576)],
        ['window_seconds' => 60, 'window_limit' => 65536, 'daily_limit' => zt_qguard_limit('ZEROTHINK_QIBM_IP_DAILY_SHOTS', 'quantum_ibm_ip_daily_shots', 262144, 16384, 4194304)]
    );
    $handles = zt_qguard_acquire_concurrency(
        'ibm_qiskit_worker', $uid,
        zt_qguard_limit('ZEROTHINK_QIBM_USER_CONCURRENCY', 'quantum_ibm_user_concurrency', 1, 1, 2),
        zt_qguard_limit('ZEROTHINK_QIBM_IP_CONCURRENCY', 'quantum_ibm_ip_concurrency', 4, 1, 8)
    );
    try { return $worker(); }
    finally { zt_qguard_release_concurrency($handles); }
}

if ($zqibm_library_only) { return; }

try {
    $maxBody = zt_qguard_limit('ZEROTHINK_QIBM_MAX_BODY_BYTES', 'quantum_ibm_max_body_bytes', 32768, 8192, 65536);
    $input = zt_qguard_read_json($maxBody);
    $user = zqibm_current_user();
    $uid = (int) ($user['id'] ?? ($_SESSION['user_id'] ?? 0));
    if ($uid <= 0) { throw new ZtQuantumRequestException('Login required.', 401); }
    if (!zqibm_is_pro($user)) { throw new ZtQuantumRequestException('IBM Qiskit is a Sovereign Pro lane.', 403); }
    zqibm_require_session_csrf($input);
    if (function_exists('db')) { zqibm_ensure_columns(db()); }

    $action = strtolower(trim((string) ($input['action'] ?? 'run')));
    if (!in_array($action, ['run', 'status'], true)) {
        throw new ZtQuantumRequestException('Unknown IBM Qiskit action.', 400);
    }
    $maxPrompt = zt_qguard_limit('ZEROTHINK_QIBM_MAX_PROMPT_CHARACTERS', 'quantum_ibm_max_prompt_characters', 6000, 1000, 12000);
    $maxSignal = zt_qguard_limit('ZEROTHINK_QIBM_MAX_SIGNAL_CHARACTERS', 'quantum_ibm_max_signal_characters', 2400, 500, 6000);
    $maxShots = zt_qguard_limit('ZEROTHINK_QIBM_MAX_SHOTS', 'quantum_ibm_max_shots', 4096, 256, 8192);
    $maxQubits = zt_qguard_limit('ZEROTHINK_QIBM_MAX_QUBITS', 'quantum_ibm_max_qubits', 16, 4, 32);
    $requestedQubits = isset($input['qubits']) ? (int) $input['qubits'] : 0;
    if ($requestedQubits < 0 || $requestedQubits > $maxQubits) {
        throw new ZtQuantumRequestException('Requested qubit width exceeds this lane\'s safety limit.', 400);
    }
    $mode = strtolower(zqibm_clean_text($input['mode'] ?? 'local', 16));
    if (!in_array($mode, ['local', 'runtime', 'backend', 'ibm', 'hardware'], true)) { $mode = 'local'; }
    $algorithm = function_exists('zt_qiskit_normalize_algorithm')
        ? zt_qiskit_normalize_algorithm($input['algorithm'] ?? 'zero_lattice')
        : 'zero_lattice';
    $shots = max(32, min((int) ($input['shots'] ?? 256), $maxShots));
    $ibmToken = zqibm_clean_text($input['ibm_token'] ?? ($input['ibm_quantum_key'] ?? ''), 512);
    if ($ibmToken === '') { $ibmToken = zqibm_vault_value($uid, 'api_key_ibm_quantum', 'IBM_QUANTUM_TOKEN'); }
    $ibmToken = zqibm_clean_text($ibmToken, 512);
    $instance = zqibm_clean_text($input['ibm_instance'] ?? '', 512);
    if ($instance === '') { $instance = zqibm_vault_value($uid, 'ibm_quantum_instance', 'IBM_QUANTUM_INSTANCE'); }
    $instance = zqibm_clean_text($instance, 512);
    $region = zqibm_clean_text($input['ibm_region'] ?? '', 64);
    if ($region === '') { $region = zqibm_vault_value($uid, 'ibm_quantum_region', 'IBM_QUANTUM_REGION'); }
    $region = zqibm_clean_text($region, 64);
    $request = [
        'action' => $action,
        'prompt' => zqibm_clean_text($input['prompt'] ?? '', $maxPrompt),
        'algorithm' => $algorithm,
        'mode' => $mode,
        'shots' => $shots,
        'qubits' => $requestedQubits,
        'research_signal' => zqibm_clean_text($input['research_signal'] ?? '', $maxSignal),
        'ibm_token' => $ibmToken,
        'ibm_instance' => $instance,
        'ibm_region' => $region,
    ];
    $timeout = zt_qguard_limit('ZEROTHINK_QIBM_TIMEOUT_SECONDS', 'quantum_ibm_timeout_seconds', 30, 10, 60);
    $rawResult = zqibm_guard_run($uid, $action === 'status' ? 32 : $shots, function () use ($request, $timeout) {
        return zqibm_run_worker($request, $timeout);
    });
    $result = zt_qguard_public_qiskit_result((array) $rawResult, $mode, $maxQubits);
    if (($result['status'] ?? '') !== 'success') { throw new RuntimeException('Qiskit worker failed.'); }
    zqibm_send(['status' => 'success', 'message' => 'IBM Qiskit result returned.', 'qiskit' => $result]);
} catch (ZtQuantumRequestException $e) {
    if ($e->httpStatus() === 405) { header('Allow: POST'); }
    zqibm_send(['status' => 'error', 'message' => $e->getMessage()], $e->httpStatus());
} catch (Throwable $e) {
    $reference = zt_qguard_reference();
    zt_qguard_log_failure('ibm_qiskit', $reference, $e);
    zqibm_send(['status' => 'error', 'message' => 'IBM Qiskit could not complete that request. Reference: ' . $reference], 500);
}
