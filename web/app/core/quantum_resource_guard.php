<?php
/**
 * Shared, fail-closed resource controls for ZeroThink's local quantum workers.
 *
 * The state directory deliberately lives outside the public site root. All
 * counters are updated under advisory locks and are keyed by both account and
 * immediate peer address. Do not use forwarded-address headers here: the
 * backend can also be reached directly, so those headers are not trustworthy.
 */

if (!class_exists('ZtQuantumRequestException')) {
    class ZtQuantumRequestException extends RuntimeException {
        private $httpStatus;

        public function __construct($message, $httpStatus = 400) {
            parent::__construct((string) $message);
            $this->httpStatus = max(400, min(499, (int) $httpStatus));
        }

        public function httpStatus() {
            return $this->httpStatus;
        }
    }
}

function zt_qguard_limit($envName, $siteName, $default, $minimum, $maximum) {
    $raw = trim((string) getenv((string) $envName));
    if ($raw === '' && $siteName !== '' && function_exists('site_setting')) {
        $raw = trim((string) site_setting((string) $siteName, ''));
    }
    $value = $raw !== '' && preg_match('/^-?\d+$/', $raw) ? (int) $raw : (int) $default;
    return max((int) $minimum, min((int) $maximum, $value));
}

function zt_qguard_normalize_text($value) {
    $text = trim((string) $value);
    $text = preg_replace('/[^\P{C}\r\n\t]/u', '', $text);
    if (!is_string($text)) {
        return '';
    }
    return $text;
}

function zt_qguard_clean_text($value, $maxCharacters) {
    $text = zt_qguard_normalize_text($value);
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($length > (int) $maxCharacters) {
        throw new ZtQuantumRequestException('A text field exceeds the supported length.', 413);
    }
    return $text;
}

function zt_qguard_truncate_text($value, $maxCharacters) {
    $text = zt_qguard_normalize_text($value);
    $maxCharacters = max(0, (int) $maxCharacters);
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($length <= $maxCharacters) {
        return $text;
    }
    return function_exists('mb_substr')
        ? mb_substr($text, 0, $maxCharacters, 'UTF-8')
        : substr($text, 0, $maxCharacters);
}

function zt_qguard_read_json($maxBytes) {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        throw new ZtQuantumRequestException('POST JSON required.', 405);
    }
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (strpos($contentType, 'application/json') !== 0) {
        throw new ZtQuantumRequestException('JSON request required.', 415);
    }
    $maxBytes = max(1024, (int) $maxBytes);
    $declared = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($declared < 0 || $declared > $maxBytes) {
        throw new ZtQuantumRequestException('Request is too large.', 413);
    }
    $raw = (string) file_get_contents('php://input');
    if (strlen($raw) > $maxBytes) {
        throw new ZtQuantumRequestException('Request is too large.', 413);
    }
    $input = json_decode($raw, true);
    if (!is_array($input) || array_values($input) === $input && $input !== []) {
        throw new ZtQuantumRequestException('Valid JSON object required.', 400);
    }
    return $input;
}

function zt_qguard_root() {
    static $root = null;
    if (is_string($root)) {
        return $root;
    }
    $testOverride = defined('ZT_QUANTUM_GUARD_TESTING')
        ? trim((string) ($GLOBALS['zt_quantum_guard_test_root'] ?? ''))
        : '';
    $configured = $testOverride !== ''
        ? $testOverride
        : trim((string) getenv('ZEROTHINK_QUANTUM_STATE_ROOT'));
    $root = $configured !== ''
        ? rtrim($configured, '/\\')
        : dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.zerothink_storage'
            . DIRECTORY_SEPARATOR . 'quantum-guards';
    $previous = umask(0077);
    try {
        if ((!is_dir($root) && !@mkdir($root, 0700, true)) || !is_dir($root)) {
            throw new RuntimeException('Quantum resource guard storage is unavailable.');
        }
        @chmod($root, 0700);
        if (!is_writable($root)) {
            throw new RuntimeException('Quantum resource guard storage is not writable.');
        }
    } finally {
        umask($previous);
    }
    return $root;
}

function zt_qguard_lane_dir($lane) {
    $lane = preg_replace('/[^a-z0-9_-]+/i', '_', strtolower((string) $lane));
    $lane = trim((string) $lane, '_');
    if ($lane === '') {
        throw new RuntimeException('Quantum resource guard lane is invalid.');
    }
    $dir = zt_qguard_root() . DIRECTORY_SEPARATOR . $lane;
    $previous = umask(0077);
    try {
        if ((!is_dir($dir) && !@mkdir($dir, 0700, true)) || !is_dir($dir)) {
            throw new RuntimeException('Quantum resource guard lane is unavailable.');
        }
        @chmod($dir, 0700);
    } finally {
        umask($previous);
    }
    return $dir;
}

function zt_qguard_identity_key() {
    static $key = '';
    if ($key !== '') {
        return $key;
    }
    $path = zt_qguard_root() . DIRECTORY_SEPARATOR . '.identity-key';
    $previous = umask(0077);
    $handle = @fopen($path, 'c+b');
    umask($previous);
    if (!$handle) {
        throw new RuntimeException('Quantum identity guard is unavailable.');
    }
    try {
        @chmod($path, 0600);
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Quantum identity guard lock failed.');
        }
        rewind($handle);
        $stored = trim((string) stream_get_contents($handle));
        if (!preg_match('/^[a-f0-9]{64}$/', $stored)) {
            $stored = bin2hex(random_bytes(32));
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $stored) !== 64 || !fflush($handle)) {
                throw new RuntimeException('Quantum identity guard could not be initialized.');
            }
        }
        flock($handle, LOCK_UN);
        $key = $stored;
    } finally {
        fclose($handle);
    }
    return $key;
}

function zt_qguard_subjects($userId) {
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    if ($remote === '' || filter_var($remote, FILTER_VALIDATE_IP) === false) {
        $remote = 'unknown';
    }
    return [
        'user' => 'u_' . max(0, (int) $userId),
        'ip' => 'i_' . hash_hmac('sha256', $remote, zt_qguard_identity_key()),
    ];
}

function zt_qguard_state_path($lane, $subject, $metric) {
    $dir = zt_qguard_lane_dir($lane) . DIRECTORY_SEPARATOR . 'state';
    $previous = umask(0077);
    try {
        if ((!is_dir($dir) && !@mkdir($dir, 0700, true)) || !is_dir($dir)) {
            throw new RuntimeException('Quantum quota state is unavailable.');
        }
        @chmod($dir, 0700);
    } finally {
        umask($previous);
    }
    $name = hash('sha256', (string) $subject . "\0" . (string) $metric) . '.json';
    return $dir . DIRECTORY_SEPARATOR . $name;
}

function zt_qguard_consume_subject($lane, $subject, $metric, $units, $windowSeconds, $windowLimit, $dailyLimit) {
    $path = zt_qguard_state_path($lane, $subject, $metric);
    $previous = umask(0077);
    $handle = @fopen($path, 'c+b');
    umask($previous);
    if (!$handle) {
        throw new RuntimeException('Quantum quota guard is unavailable.');
    }
    try {
        @chmod($path, 0600);
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Quantum quota guard lock failed.');
        }
        rewind($handle);
        $state = json_decode((string) stream_get_contents($handle), true);
        if (!is_array($state)) {
            $state = [];
        }
        $now = time();
        $day = gmdate('Y-m-d', $now);
        $windowStart = (int) ($state['window_start'] ?? 0);
        $windowUsed = (int) ($state['window_used'] ?? 0);
        $dailyUsed = (int) ($state['daily_used'] ?? 0);
        if ($windowStart <= 0 || $windowStart > $now || ($now - $windowStart) >= (int) $windowSeconds) {
            $windowStart = $now;
            $windowUsed = 0;
        }
        if (($state['day'] ?? '') !== $day) {
            $dailyUsed = 0;
        }
        $units = max(1, (int) $units);
        if ($windowLimit > 0 && $windowUsed + $units > (int) $windowLimit) {
            $wait = max(1, (int) $windowSeconds - ($now - $windowStart));
            throw new ZtQuantumRequestException('This compute lane is busy. Try again in about ' . $wait . ' seconds.', 429);
        }
        if ($dailyLimit > 0 && $dailyUsed + $units > (int) $dailyLimit) {
            throw new ZtQuantumRequestException('The daily allowance for this compute lane has been reached. Try again tomorrow.', 429);
        }
        $state = [
            'window_start' => $windowStart,
            'window_used' => $windowUsed + $units,
            'day' => $day,
            'daily_used' => $dailyUsed + $units,
            'updated_at' => $now,
        ];
        $encoded = json_encode($state, JSON_UNESCAPED_SLASHES);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, (string) $encoded) === false || !fflush($handle)) {
            throw new RuntimeException('Quantum quota state could not be saved.');
        }
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function zt_qguard_consume($lane, $userId, $metric, $units, array $userLimits, array $ipLimits) {
    $subjects = zt_qguard_subjects($userId);
    foreach (['user' => $userLimits, 'ip' => $ipLimits] as $scope => $limits) {
        zt_qguard_consume_subject(
            $lane,
            $subjects[$scope],
            $metric,
            $units,
            max(1, (int) ($limits['window_seconds'] ?? 60)),
            max(0, (int) ($limits['window_limit'] ?? 0)),
            max(0, (int) ($limits['daily_limit'] ?? 0))
        );
    }
}

function zt_qguard_acquire_subject_slots($lane, $subject, $limit) {
    $limit = max(1, min(8, (int) $limit));
    $dir = zt_qguard_lane_dir($lane) . DIRECTORY_SEPARATOR . 'locks';
    $previous = umask(0077);
    try {
        if ((!is_dir($dir) && !@mkdir($dir, 0700, true)) || !is_dir($dir)) {
            throw new RuntimeException('Quantum concurrency guard is unavailable.');
        }
        @chmod($dir, 0700);
    } finally {
        umask($previous);
    }
    $prefix = hash('sha256', (string) $subject);
    for ($slot = 0; $slot < $limit; $slot++) {
        $path = $dir . DIRECTORY_SEPARATOR . $prefix . '.' . $slot . '.lock';
        $previous = umask(0077);
        $handle = @fopen($path, 'c+b');
        umask($previous);
        if (!$handle) {
            continue;
        }
        @chmod($path, 0600);
        if (flock($handle, LOCK_EX | LOCK_NB)) {
            return $handle;
        }
        fclose($handle);
    }
    return null;
}

function zt_qguard_acquire_concurrency($lane, $userId, $userLimit, $ipLimit) {
    $subjects = zt_qguard_subjects($userId);
    $handles = [];
    foreach (['user' => $userLimit, 'ip' => $ipLimit] as $scope => $limit) {
        $handle = zt_qguard_acquire_subject_slots($lane, $subjects[$scope], $limit);
        if (!is_resource($handle)) {
            zt_qguard_release_concurrency($handles);
            throw new ZtQuantumRequestException('This compute lane is already working at capacity. Try again shortly.', 429);
        }
        $handles[] = $handle;
    }
    return $handles;
}

function zt_qguard_release_concurrency(array &$handles) {
    foreach ($handles as $handle) {
        if (is_resource($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }
    $handles = [];
}

function zt_qguard_run_json_process(array $command, array $request, $workingDirectory, $timeoutSeconds, $maxStdout = 1048576) {
    if (!function_exists('proc_open')) {
        throw new RuntimeException('Compute worker unavailable.');
    }
    $payload = json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($payload) || strlen($payload) > 65536) {
        throw new RuntimeException('Compute worker request could not be encoded.');
    }
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = @proc_open($command, $descriptors, $pipes, (string) $workingDirectory, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Compute worker could not start.');
    }
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + max(1, (int) $timeoutSeconds);
    $timedOut = false;
    try {
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }
        $offset = 0;
        while (true) {
            $status = proc_get_status($process);
            $running = !empty($status['running']);
            $read = [];
            if (is_resource($pipes[1]) && !feof($pipes[1])) {
                $read[] = $pipes[1];
            }
            if (is_resource($pipes[2]) && !feof($pipes[2])) {
                $read[] = $pipes[2];
            }
            $write = [];
            if ($offset < strlen($payload) && is_resource($pipes[0])) {
                $write[] = $pipes[0];
            }
            $except = null;
            if ($read || $write) {
                @stream_select($read, $write, $except, 0, 100000);
            } else {
                usleep(100000);
            }
            foreach ($write as $pipe) {
                $written = @fwrite($pipe, substr($payload, $offset));
                if (is_int($written) && $written > 0) {
                    $offset += $written;
                }
            }
            if ($offset >= strlen($payload) && is_resource($pipes[0])) {
                fclose($pipes[0]);
                $pipes[0] = null;
            }
            foreach ($read as $pipe) {
                $chunk = (string) @fread($pipe, 8192);
                if ($pipe === $pipes[1]) {
                    $stdout .= $chunk;
                    if (strlen($stdout) > (int) $maxStdout) {
                        throw new RuntimeException('Compute worker response exceeded the safety limit.');
                    }
                } else {
                    $stderr .= $chunk;
                    if (strlen($stderr) > 65536) {
                        throw new RuntimeException('Compute worker diagnostics exceeded the safety limit.');
                    }
                }
            }
            if (!$running) {
                if (is_resource($pipes[1])) {
                    $stdout .= (string) stream_get_contents($pipes[1]);
                }
                if (is_resource($pipes[2])) {
                    $stderr .= (string) stream_get_contents($pipes[2]);
                }
                break;
            }
            if (microtime(true) >= $deadline) {
                $timedOut = true;
                throw new RuntimeException('Compute worker timed out.');
            }
        }
    } finally {
        if ($timedOut || isset($status) && !empty($status['running'])) {
            @proc_terminate($process);
            usleep(100000);
            $after = @proc_get_status($process);
            if (is_array($after) && !empty($after['running'])) {
                @proc_terminate($process, 9);
            }
        }
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }
        @proc_close($process);
    }
    if (strlen($stdout) > (int) $maxStdout || strlen($stderr) > 65536) {
        throw new RuntimeException('Compute worker output exceeded the safety limit.');
    }
    $decoded = json_decode($stdout, true);
    if (!is_array($decoded)) {
        error_log('[ZeroThink quantum worker] invalid_output stderr_sha256=' . hash('sha256', $stderr));
        throw new RuntimeException('Compute worker returned an invalid response.');
    }
    return $decoded;
}

function zt_qguard_reference() {
    try {
        return strtoupper(bin2hex(random_bytes(6)));
    } catch (Throwable $e) {
        return strtoupper(substr(hash('sha256', uniqid('', true)), 0, 12));
    }
}

function zt_qguard_log_failure($lane, $reference, Throwable $error) {
    error_log(sprintf(
        '[ZeroThink %s] ref=%s class=%s code=%d message_sha256=%s',
        preg_replace('/[^a-z0-9_-]+/i', '_', (string) $lane),
        (string) $reference,
        get_class($error),
        (int) $error->getCode(),
        hash('sha256', (string) $error->getMessage())
    ));
}

function zt_qguard_public_qiskit_result(array $result, $requestedMode = 'local', $maxQubits = 16) {
    $algorithms = ['zero_lattice', 'bell', 'ghz', 'grover', 'qaoa_maxcut', 'vqe_toy', 'qft'];
    $algorithm = strtolower(trim((string) ($result['algorithm'] ?? 'zero_lattice')));
    if (!in_array($algorithm, $algorithms, true)) {
        $algorithm = 'zero_lattice';
    }
    $mode = strtolower(trim((string) ($result['mode'] ?? $requestedMode)));
    if (!in_array($mode, ['local', 'runtime', 'backend', 'ibm', 'hardware'], true)) {
        $mode = 'local';
    }
    $maxQubits = max(1, min(32, (int) $maxQubits));
    $counts = [];
    foreach ((array) ($result['counts'] ?? []) as $state => $count) {
        $state = (string) $state;
        if (count($counts) >= 256 || !preg_match('/^[01]{1,' . $maxQubits . '}$/', $state)) {
            continue;
        }
        $counts[$state] = max(0, min(10000000, (int) $count));
    }
    arsort($counts, SORT_NUMERIC);
    $shots = max(1, array_sum($counts));
    $topStates = [];
    $entropy = 0.0;
    foreach ($counts as $state => $count) {
        if ($count <= 0) {
            continue;
        }
        $probability = $count / $shots;
        $entropy -= $probability * (log($probability) / log(2));
        if (count($topStates) < 8) {
            $topStates[] = ['state' => $state, 'count' => $count, 'probability' => round($probability, 6)];
        }
    }
    $dominantState = $topStates[0]['state'] ?? '';
    $dominantProbability = (float) ($topStates[0]['probability'] ?? 0.0);
    $circuitInput = is_array($result['circuit'] ?? null) ? $result['circuit'] : [];
    $circuit = [
        'label' => substr(preg_replace('/[^a-z0-9 _.,;:()\/-]+/i', '', (string) ($circuitInput['label'] ?? '')), 0, 160),
        'qubits' => max(0, min($maxQubits, (int) ($circuitInput['qubits'] ?? 0))),
        'depth' => max(0, min(100000, (int) ($circuitInput['depth'] ?? 0))),
        'gate_count' => max(0, min(1000000, (int) ($circuitInput['gate_count'] ?? 0))),
    ];
    $operations = [];
    foreach ((array) ($circuitInput['operations'] ?? []) as $operation => $count) {
        if (count($operations) >= 32 || !preg_match('/^[a-z0-9_]{1,32}$/i', (string) $operation)) {
            continue;
        }
        $operations[(string) $operation] = max(0, min(1000000, (int) $count));
    }
    if ($operations) {
        $circuit['operations'] = $operations;
    }
    $runtimeInput = is_array($result['ibm_runtime'] ?? null) ? $result['ibm_runtime'] : [];
    $runtimeAvailable = !empty($runtimeInput['available']);
    $backends = [];
    foreach ((array) ($runtimeInput['visible_backends'] ?? []) as $backend) {
        if (count($backends) >= 12) {
            break;
        }
        $backend = substr(preg_replace('/[^a-z0-9_.:-]+/i', '', (string) $backend), 0, 80);
        if ($backend !== '') {
            $backends[] = $backend;
        }
    }
    $qiskitAvailable = !empty($result['environment']['qiskit_available']);
    $execution = substr(preg_replace('/[^a-z0-9_-]+/i', '_', (string) ($result['execution'] ?? '')), 0, 80);
    $public = [
        'status' => (($result['status'] ?? '') === 'success') ? 'success' : 'error',
        'algorithm' => $algorithm,
        'mode' => $mode,
        'execution' => $execution,
        'counts' => $counts,
        'summary' => [
            'shots' => $shots,
            'dominant_state' => $dominantState,
            'dominant_probability' => $dominantProbability,
            'entropy_bits' => round($entropy, 6),
            'top_states' => $topStates,
        ],
        'circuit' => $circuit,
        'environment' => [
            'qiskit_available' => $qiskitAvailable,
            'qiskit_ibm_runtime_available' => !empty($result['environment']['qiskit_ibm_runtime_available']),
        ],
        'ibm_runtime' => [
            'available' => $runtimeAvailable,
            'message' => $runtimeAvailable
                ? 'IBM Quantum backend context is available; no hardware job was submitted.'
                : 'IBM Quantum backend context is unavailable; no hardware job was submitted.',
            'backend_count' => max(0, min(10000, (int) ($runtimeInput['backend_count'] ?? count($backends)))),
            'visible_backends' => $backends,
        ],
        'truth_boundary' => 'Qiskit/IBM evidence can ground the answer; the language model remains classical.',
    ];
    $public['context'] = implode("\n", [
        '[IBM QISKIT LANE]',
        'provider=IBM Quantum / Qiskit',
        'mode=' . $mode,
        'algorithm=' . $algorithm,
        'qiskit_available=' . ($qiskitAvailable ? 'yes' : 'no'),
        'runtime_available=' . ($runtimeAvailable ? 'yes' : 'no'),
        'truth_boundary=Classical AI reasons over Qiskit/IBM evidence. Do not claim the LLM runs on qubits.',
        'runtime_boundary=No IBM hardware job is submitted by this worker.',
        'dominant_state=' . $dominantState,
        'dominant_probability=' . $dominantProbability,
        'entropy_bits=' . round($entropy, 6),
        'counts=' . json_encode($counts, JSON_UNESCAPED_SLASHES),
        'circuit=' . json_encode($circuit, JSON_UNESCAPED_SLASHES),
    ]);
    return $public;
}
