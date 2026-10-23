<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$testState = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zt-quantum-guard-' . bin2hex(random_bytes(6));
define('ZT_QUANTUM_GUARD_TESTING', true);
$GLOBALS['zt_quantum_guard_test_root'] = $testState;
$GLOBALS['qcg_site_settings'] = [];
function site_setting($name, $default = '') {
    return $GLOBALS['qcg_site_settings'][$name] ?? $default;
}
require_once $root . '/app/core/quantum_resource_guard.php';

$checks = 0;
$failures = [];
function qcg_assert(bool $condition, string $message): void {
    global $checks, $failures;
    $checks++;
    if (!$condition) { $failures[] = $message; }
}

function qcg_remove_tree(string $path): void {
    if (!is_dir($path)) { return; }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

$_SERVER['REMOTE_ADDR'] = '198.51.100.27';
qcg_assert(zt_qguard_limit('QCG_MISSING', '', 7, 1, 10) === 7, 'Config defaults must be bounded.');
$GLOBALS['qcg_site_settings']['qcg_limit'] = '999';
qcg_assert(zt_qguard_limit('QCG_MISSING', 'qcg_limit', 7, 1, 10) === 10, 'Config overrides must respect the hard maximum.');

$textRejected = false;
try { zt_qguard_clean_text('12345', 4); }
catch (ZtQuantumRequestException $e) { $textRejected = $e->httpStatus() === 413; }
qcg_assert($textRejected, 'Oversized Unicode/text inputs must be rejected before worker execution.');

$userLimits = ['window_seconds' => 60, 'window_limit' => 2, 'daily_limit' => 10];
$ipLimits = ['window_seconds' => 60, 'window_limit' => 4, 'daily_limit' => 20];
zt_qguard_consume('test_lane', 42, 'requests', 1, $userLimits, $ipLimits);
zt_qguard_consume('test_lane', 42, 'requests', 1, $userLimits, $ipLimits);
$rateRejected = false;
try { zt_qguard_consume('test_lane', 42, 'requests', 1, $userLimits, $ipLimits); }
catch (ZtQuantumRequestException $e) { $rateRejected = $e->httpStatus() === 429; }
qcg_assert($rateRejected, 'User and IP rate counters must update atomically and reject excess use.');

$held = zt_qguard_acquire_concurrency('test_worker', 42, 1, 1);
$concurrencyRejected = false;
try { zt_qguard_acquire_concurrency('test_worker', 42, 1, 1); }
catch (ZtQuantumRequestException $e) { $concurrencyRejected = $e->httpStatus() === 429; }
qcg_assert($concurrencyRejected, 'A second worker must not bypass the per-user/IP concurrency lock.');
zt_qguard_release_concurrency($held);
$heldAgain = zt_qguard_acquire_concurrency('test_worker', 42, 1, 1);
qcg_assert(count($heldAgain) === 2, 'Concurrency slots must become available after an explicit release.');
zt_qguard_release_concurrency($heldAgain);

if (function_exists('proc_open')) {
    $workerResult = zt_qguard_run_json_process([
        PHP_BINARY,
        '-r',
        '$input=json_decode(stream_get_contents(STDIN),true);echo json_encode(["status"=>"success","echo"=>$input]);',
    ], ['probe' => 'bounded'], sys_get_temp_dir(), 3, 4096);
    qcg_assert(($workerResult['echo']['probe'] ?? '') === 'bounded', 'The non-blocking JSON worker runner must preserve bounded requests and responses.');
    $timedOut = false;
    try {
        zt_qguard_run_json_process([PHP_BINARY, '-r', 'usleep(2000000);echo "{}";'], [], sys_get_temp_dir(), 1, 4096);
    } catch (RuntimeException $e) {
        $timedOut = strpos($e->getMessage(), 'timed out') !== false;
    }
    qcg_assert($timedOut, 'The worker runner must terminate work that exceeds its deadline.');
}

$public = zt_qguard_public_qiskit_result([
    'status' => 'success',
    'algorithm' => 'bell',
    'mode' => 'runtime',
    'execution' => 'qiskit_local_statevector_sampler',
    'counts' => ['00' => 5, '11' => 5, str_repeat('1', 40) => 9, 'provider-error' => 3],
    'summary' => ['dominant_state' => 'attacker-controlled'],
    'circuit' => ['label' => 'Bell', 'qubits' => 99, 'error' => 'provider secret path'],
    'environment' => [
        'qiskit_available' => true,
        'python' => '/private/venv/bin/python',
        'qiskit_error' => 'Traceback: provider detail',
    ],
    'ibm_runtime' => [
        'available' => false,
        'message' => 'https://provider.invalid/internal?token=secret',
        'visible_backends' => ['safe_backend', "bad backend\nsecret"],
    ],
    'trace' => "Traceback (most recent call last):\nsecret",
    'python_candidate' => '/private/python',
], 'runtime', 16);
$encodedPublic = json_encode($public);
qcg_assert(($public['summary']['shots'] ?? 0) === 10, 'Public summaries must be recomputed from bounded counts.');
qcg_assert(($public['circuit']['qubits'] ?? 0) === 16, 'Client-visible qubit widths must respect the configured bound.');
qcg_assert(
    strpos((string) $encodedPublic, 'Traceback') === false
        && strpos((string) $encodedPublic, '/private/') === false
        && strpos((string) $encodedPublic, 'provider.invalid') === false
        && strpos((string) $encodedPublic, 'provider secret') === false,
    'Tracebacks, worker paths, and provider-internal errors must be removed from public results.'
);

$voice = (string) file_get_contents($root . '/api_quantum_voice.php');
$ibm = (string) file_get_contents($root . '/api_quantum_ibm.php');
$agent = (string) file_get_contents($root . '/api_agent.php');
$guard = (string) file_get_contents($root . '/app/core/quantum_resource_guard.php');
$lab = (string) file_get_contents($root . '/quantum_zero.php');
$chat = (string) file_get_contents($root . '/quantum_zero_chat.php');

qcg_assert(
    strpos($voice, 'zt_qguard_read_json(') !== false
        && strpos($voice, 'qv_require_session_csrf($input)') !== false
        && strpos($voice, "['health', 'speak']") !== false,
    'Voice mutations must require bounded POST JSON and shared session CSRF.'
);
qcg_assert(
    strpos($voice, 'zt_qguard_acquire_concurrency(') !== false
        && strpos($voice, 'ZEROTHINK_QVOICE_USER_DAILY_CHARACTERS') !== false
        && strpos($voice, 'quantum_voice_cache_max_bytes') !== false
        && strpos($voice, '@chmod($file, 0600)') !== false,
    'Voice rendering and cache files must have concurrency, daily, byte, and permission caps.'
);
qcg_assert(
    strpos($ibm, 'zt_qguard_read_json($maxBody)') !== false
        && strpos($ibm, 'zqibm_require_session_csrf($input)') !== false
        && strpos($ibm, 'ZEROTHINK_QIBM_MAX_QUBITS') !== false
        && strpos($ibm, 'zqibm_guard_run(') !== false,
    'Standalone IBM requests must use body, CSRF, qubit, quota, and concurrency guards.'
);
qcg_assert(
    strpos($guard, 'stream_set_blocking($pipe, false)') !== false
        && strpos($guard, 'microtime(true) >= $deadline') !== false
        && strpos($guard, '@proc_terminate($process, 9)') !== false
        && strpos($guard, 'strlen($stdout) > (int) $maxStdout') !== false,
    'Worker pipes, deadlines, termination, and output sizes must be bounded.'
);
qcg_assert(
    strpos($agent, 'if ($preflight_provider === \'quantum_ibm\' && strlen($raw_request_body) > $qiskit_body_limit)') !== false
        && strpos($agent, 'zt_qguard_public_qiskit_result((array) $raw_result') !== false
        && strpos($agent, "'python_candidate' =>") === false,
    'The active Studio Qiskit path must cap its body and remove worker-path metadata.'
);
foreach (['Quantum Lab' => $lab, 'QuantumZero Chat' => $chat] as $label => $source) {
    qcg_assert(
        substr_count($source, "'/api_quantum_voice.php'") === 2
            && substr_count($source, "'X-CSRF-Token':qzCsrfToken") >= 2
            && substr_count($source, 'csrf_token:qzCsrfToken') >= 2,
        $label . ' must send CSRF proof on both local-voice calls.'
    );
}

qcg_remove_tree($testState);
if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . ' of ' . $checks . " quantum compute checks failed\n");
    foreach ($failures as $failure) { fwrite(STDERR, ' - ' . $failure . "\n"); }
    exit(1);
}
echo 'OK: ' . $checks . " quantum compute checks passed\n";
