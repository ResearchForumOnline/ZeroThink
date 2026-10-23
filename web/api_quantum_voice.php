<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/core/quantum_resource_guard.php';

function qv_json($payload, $code = 200) {
    http_response_code((int) $code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function qv_limit($env, $site, $default, $min, $max) {
    return zt_qguard_limit($env, $site, $default, $min, $max);
}

function qv_user() {
    $user = function_exists('request_user_record') ? request_user_record() : null;
    if (!$user && function_exists('current_user_record')) {
        $user = current_user_record();
    }
    if (!$user) {
        throw new ZtQuantumRequestException('Auth required.', 403);
    }
    $isArchitect = function_exists('is_architect_user')
        ? is_architect_user($user)
        : ((int) ($_SESSION['user_id'] ?? 0) === 1);
    $isPro = $isArchitect
        || !empty($user['is_pro'])
        || in_array(strtolower((string) ($user['plan'] ?? '')), ['pro', 'god_mode'], true)
        || strtolower((string) ($user['subscription_status'] ?? '')) === 'active';
    if (!$isPro) {
        throw new ZtQuantumRequestException('Quantum Zero voice is Pro only.', 403);
    }
    return $user;
}

function qv_require_session_csrf(array $input) {
    $sessionUser = function_exists('current_user_record') ? current_user_record() : null;
    if ((int) ($sessionUser['id'] ?? 0) <= 0) {
        return;
    }
    $token = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ($input['_csrf'] ?? ''))));
    if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($token)) {
        throw new ZtQuantumRequestException('Request verification failed. Refresh the page and try again.', 403);
    }
}

function qv_cache_root() {
    $root = zt_qguard_lane_dir('voice') . DIRECTORY_SEPARATOR . 'cache';
    $previous = umask(0077);
    try {
        if ((!is_dir($root) && !@mkdir($root, 0700, true)) || !is_dir($root)) {
            throw new RuntimeException('Voice cache is unavailable.');
        }
        @chmod($root, 0700);
        if (!is_writable($root)) {
            throw new RuntimeException('Voice cache is not writable.');
        }
    } finally {
        umask($previous);
    }
    return $root;
}

function qv_clean_text($text) {
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/```.*?```/s', ' code block omitted. ', $text);
    $text = preg_replace('/`([^`]{1,120})`/', '$1', $text);
    $text = preg_replace('/https?:\/\/\S+/i', ' link ', $text);
    $text = preg_replace('/\[[^\]]+\]/', ' ', $text);
    $text = preg_replace('/[#*_>{}\[\]\\\\|~^]+/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return zt_qguard_clean_text($text, qv_limit(
        'ZEROTHINK_QVOICE_MAX_CHARACTERS', 'quantum_voice_max_characters', 1200, 200, 5000
    ));
}

function qv_find_readable($paths) {
    foreach ($paths as $path) {
        $path = trim((string) $path);
        if ($path !== '' && is_file($path) && is_readable($path)) {
            return $path;
        }
    }
    return '';
}

function qv_find_executable($paths) {
    foreach ($paths as $path) {
        $path = trim((string) $path);
        if ($path !== '' && is_file($path) && is_executable($path)) {
            return $path;
        }
    }
    return '';
}

function qv_engine_status() {
    $model = qv_find_readable([
        getenv('ZT_PIPER_MODEL') ?: '',
        '/opt/zerothink-voice/models/en_US-ryan-medium.onnx',
        '/opt/zerothink-voice/models/en_GB-alan-medium.onnx',
        __DIR__ . '/private/voice_models/en_US-ryan-medium.onnx',
    ]);
    $config = $model !== '' && is_readable($model . '.json') ? $model . '.json' : '';
    $bin = qv_find_executable([
        getenv('ZT_PIPER_BIN') ?: '',
        '/opt/zerothink-voice/venv/bin/piper',
        '/opt/zerothink-voice/piper/piper',
        '/opt/zerothink-voice/bin/piper',
        '/usr/local/bin/piper',
        '/usr/bin/piper',
    ]);
    return [
        'available' => $bin !== '' && $model !== '' && $config !== '',
        'engine' => 'piper',
        'binary' => $bin,
        'model' => $model,
        'config' => $config,
        'runtime' => 'local-open-source',
    ];
}

function qv_cleanup($root) {
    $lockPath = $root . DIRECTORY_SEPARATOR . '.cleanup.lock';
    $lock = @fopen($lockPath, 'c+b');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        return;
    }
    @chmod($lockPath, 0600);
    try {
        $stampPath = $root . DIRECTORY_SEPARATOR . '.last_cleanup';
        $last = is_file($stampPath) ? (int) trim((string) @file_get_contents($stampPath)) : 0;
        if ($last > time() - 300) {
            return;
        }
        @file_put_contents($stampPath, (string) time(), LOCK_EX);
        @chmod($stampPath, 0600);
        $ttl = qv_limit('ZEROTHINK_QVOICE_CACHE_TTL_SECONDS', 'quantum_voice_cache_ttl_seconds', 21600, 900, 86400);
        $maxFiles = qv_limit('ZEROTHINK_QVOICE_CACHE_MAX_FILES', 'quantum_voice_cache_max_files', 256, 16, 2048);
        $maxBytes = qv_limit('ZEROTHINK_QVOICE_CACHE_MAX_BYTES', 'quantum_voice_cache_max_bytes', 268435456, 16777216, 1073741824);
        $files = [];
        $total = 0;
        foreach (new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            $name = $entry->getFilename();
            if (preg_match('/\.tmp\./', $name) && $entry->getMTime() < time() - 900) {
                @unlink($entry->getPathname());
                continue;
            }
            if (!preg_match('/^u\d+_[a-f0-9]{64}\.wav$/', $name)) {
                continue;
            }
            if ($entry->getMTime() < time() - $ttl) {
                @unlink($entry->getPathname());
                continue;
            }
            @chmod($entry->getPathname(), 0600);
            $size = max(0, (int) $entry->getSize());
            $files[] = ['path' => $entry->getPathname(), 'mtime' => $entry->getMTime(), 'size' => $size];
            $total += $size;
        }
        usort($files, function ($a, $b) { return $a['mtime'] <=> $b['mtime']; });
        while (count($files) > $maxFiles || $total > $maxBytes) {
            $oldest = array_shift($files);
            if (!$oldest) {
                break;
            }
            if (@unlink($oldest['path'])) {
                $total -= $oldest['size'];
            }
        }
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function qv_generate_piper($engine, $text, $output) {
    if (!function_exists('proc_open')) {
        throw new RuntimeException('Voice worker unavailable.');
    }
    $command = [
        $engine['binary'], '-m', $engine['model'], '-f', $output,
        '--sentence_silence', '0.18',
    ];
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $environment = ['HOME' => '/tmp', 'PATH' => '/usr/local/bin:/usr/bin:/bin:/opt/zerothink-voice/piper'];
    $process = @proc_open($command, $descriptors, $pipes, null, $environment, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Voice worker could not start.');
    }
    $timeout = qv_limit('ZEROTHINK_QVOICE_TIMEOUT_SECONDS', 'quantum_voice_timeout_seconds', 25, 5, 45);
    $deadline = microtime(true) + $timeout;
    $stderr = '';
    $lastStatus = null;
    try {
        fwrite($pipes[0], $text);
        fclose($pipes[0]);
        $pipes[0] = null;
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        while (true) {
            $lastStatus = proc_get_status($process);
            $read = [];
            if (is_resource($pipes[1]) && !feof($pipes[1])) { $read[] = $pipes[1]; }
            if (is_resource($pipes[2]) && !feof($pipes[2])) { $read[] = $pipes[2]; }
            $write = null;
            $except = null;
            if ($read) {
                @stream_select($read, $write, $except, 0, 100000);
                foreach ($read as $pipe) {
                    $chunk = (string) @fread($pipe, 8192);
                    if ($pipe === $pipes[2]) {
                        $stderr .= $chunk;
                        if (strlen($stderr) > 65536) {
                            throw new RuntimeException('Voice worker diagnostics exceeded the safety limit.');
                        }
                    }
                }
            } else {
                usleep(100000);
            }
            if (empty($lastStatus['running'])) {
                break;
            }
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Voice worker timed out.');
            }
        }
    } finally {
        $status = @proc_get_status($process);
        if (is_array($status) && !empty($status['running'])) {
            @proc_terminate($process);
            usleep(100000);
            $status = @proc_get_status($process);
            if (is_array($status) && !empty($status['running'])) {
                @proc_terminate($process, 9);
            }
        }
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) { @fclose($pipe); }
        }
        $closedCode = @proc_close($process);
    }
    $exitCode = is_array($lastStatus) && isset($lastStatus['exitcode']) && $lastStatus['exitcode'] >= 0
        ? (int) $lastStatus['exitcode'] : (int) $closedCode;
    $maxWav = qv_limit('ZEROTHINK_QVOICE_MAX_WAV_BYTES', 'quantum_voice_max_wav_bytes', 16777216, 1048576, 67108864);
    if ($exitCode !== 0 || !is_file($output) || filesize($output) < 1024 || filesize($output) > $maxWav) {
        @unlink($output);
        error_log('[ZeroThink voice worker] render_failed stderr_sha256=' . hash('sha256', $stderr));
        throw new RuntimeException('Voice render failed.');
    }
    @chmod($output, 0600);
}

function qv_usage_limits($action) {
    if ($action === 'health') {
        return [
            ['window_seconds' => 60, 'window_limit' => qv_limit('ZEROTHINK_QVOICE_HEALTH_USER_PER_MINUTE', 'quantum_voice_health_user_per_minute', 30, 1, 300), 'daily_limit' => 1000],
            ['window_seconds' => 60, 'window_limit' => qv_limit('ZEROTHINK_QVOICE_HEALTH_IP_PER_MINUTE', 'quantum_voice_health_ip_per_minute', 120, 1, 1200), 'daily_limit' => 10000],
        ];
    }
    if ($action === 'file') {
        return [
            ['window_seconds' => 60, 'window_limit' => 120, 'daily_limit' => 0],
            ['window_seconds' => 60, 'window_limit' => 600, 'daily_limit' => 0],
        ];
    }
    return [
        ['window_seconds' => 60, 'window_limit' => qv_limit('ZEROTHINK_QVOICE_USER_PER_MINUTE', 'quantum_voice_user_per_minute', 6, 1, 60), 'daily_limit' => qv_limit('ZEROTHINK_QVOICE_USER_DAILY_REQUESTS', 'quantum_voice_user_daily_requests', 120, 10, 2000)],
        ['window_seconds' => 60, 'window_limit' => qv_limit('ZEROTHINK_QVOICE_IP_PER_MINUTE', 'quantum_voice_ip_per_minute', 30, 2, 300), 'daily_limit' => qv_limit('ZEROTHINK_QVOICE_IP_DAILY_REQUESTS', 'quantum_voice_ip_daily_requests', 1000, 50, 10000)],
    ];
}

$action = '';
try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
        $action = strtolower(trim((string) ($_GET['action'] ?? '')));
        if ($action !== 'file') {
            header('Allow: POST');
            throw new ZtQuantumRequestException('POST JSON required.', 405);
        }
        $user = qv_user();
        $uid = (int) ($user['id'] ?? ($_SESSION['user_id'] ?? 0));
        [$userLimits, $ipLimits] = qv_usage_limits('file');
        zt_qguard_consume('voice', $uid, 'file', 1, $userLimits, $ipLimits);
        $id = basename((string) ($_GET['id'] ?? ''));
        if (!preg_match('/^u' . preg_quote((string) $uid, '/') . '_[a-f0-9]{64}\.wav$/', $id)) {
            throw new ZtQuantumRequestException('Forbidden.', 403);
        }
        $file = qv_cache_root() . DIRECTORY_SEPARATOR . $id;
        $maxWav = qv_limit('ZEROTHINK_QVOICE_MAX_WAV_BYTES', 'quantum_voice_max_wav_bytes', 16777216, 1048576, 67108864);
        if (!is_file($file) || !is_readable($file) || filesize($file) < 1024 || filesize($file) > $maxWav) {
            throw new ZtQuantumRequestException('Not found.', 404);
        }
        header('Content-Type: audio/wav');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: private, max-age=3600');
        header('Content-Security-Policy: default-src \'none\'');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    $input = zt_qguard_read_json(qv_limit(
        'ZEROTHINK_QVOICE_MAX_BODY_BYTES', 'quantum_voice_max_body_bytes', 16384, 4096, 65536
    ));
    $user = qv_user();
    $uid = (int) ($user['id'] ?? ($_SESSION['user_id'] ?? 0));
    qv_require_session_csrf($input);
    $action = strtolower(trim((string) ($input['action'] ?? 'speak')));
    if (!in_array($action, ['health', 'speak'], true)) {
        throw new ZtQuantumRequestException('Unknown voice action.', 400);
    }
    [$userLimits, $ipLimits] = qv_usage_limits($action);
    zt_qguard_consume('voice', $uid, $action, 1, $userLimits, $ipLimits);
    $engine = qv_engine_status();
    if ($action === 'health') {
        qv_json([
            'status' => 'success',
            'voice' => $engine['available'] ? 'local' : 'browser-fallback',
            'engine' => $engine['engine'],
            'available' => $engine['available'],
            'runtime' => $engine['runtime'],
        ]);
    }

    $text = qv_clean_text($input['text'] ?? '');
    if ($text === '') {
        throw new ZtQuantumRequestException('No speech text supplied.', 400);
    }
    $characters = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    zt_qguard_consume(
        'voice', $uid, 'characters', $characters,
        ['window_seconds' => 60, 'window_limit' => 10000, 'daily_limit' => qv_limit('ZEROTHINK_QVOICE_USER_DAILY_CHARACTERS', 'quantum_voice_user_daily_characters', 120000, 10000, 1000000)],
        ['window_seconds' => 60, 'window_limit' => 50000, 'daily_limit' => qv_limit('ZEROTHINK_QVOICE_IP_DAILY_CHARACTERS', 'quantum_voice_ip_daily_characters', 1000000, 50000, 10000000)]
    );
    if (!$engine['available']) {
        qv_json(['status' => 'fallback', 'message' => 'Local voice is unavailable; browser voice can be used.', 'voice' => 'browser-fallback', 'engine' => 'piper']);
    }
    $root = qv_cache_root();
    qv_cleanup($root);
    $cacheKey = hash_hmac('sha256', $text . '|' . basename($engine['model']) . '|' . (string) @filemtime($engine['model']), zt_qguard_identity_key());
    $id = 'u' . $uid . '_' . $cacheKey . '.wav';
    $file = $root . DIRECTORY_SEPARATOR . $id;
    $maxWav = qv_limit('ZEROTHINK_QVOICE_MAX_WAV_BYTES', 'quantum_voice_max_wav_bytes', 16777216, 1048576, 67108864);
    if (!is_file($file) || filesize($file) < 1024 || filesize($file) > $maxWav) {
        $handles = zt_qguard_acquire_concurrency(
            'voice_render', $uid,
            qv_limit('ZEROTHINK_QVOICE_USER_CONCURRENCY', 'quantum_voice_user_concurrency', 1, 1, 2),
            qv_limit('ZEROTHINK_QVOICE_IP_CONCURRENCY', 'quantum_voice_ip_concurrency', 4, 1, 8)
        );
        try {
            if (!is_file($file) || filesize($file) < 1024 || filesize($file) > $maxWav) {
                $tmp = $file . '.tmp.' . bin2hex(random_bytes(6));
                try {
                    qv_generate_piper($engine, $text, $tmp);
                    if (!@rename($tmp, $file)) {
                        throw new RuntimeException('Voice cache publish failed.');
                    }
                    @chmod($file, 0600);
                } finally {
                    if (isset($tmp) && is_file($tmp)) { @unlink($tmp); }
                }
            }
        } finally {
            zt_qguard_release_concurrency($handles);
        }
    }
    qv_json([
        'status' => 'success', 'voice' => 'local', 'engine' => 'piper',
        'runtime' => 'local-open-source', 'chars' => $characters,
        'url' => '/api_quantum_voice.php?action=file&id=' . rawurlencode($id) . '&v=' . (string) filemtime($file),
    ]);
} catch (ZtQuantumRequestException $e) {
    if ($e->httpStatus() === 405) { header('Allow: POST'); }
    qv_json(['status' => 'error', 'message' => $e->getMessage()], $e->httpStatus());
} catch (Throwable $e) {
    $reference = zt_qguard_reference();
    zt_qguard_log_failure('quantum_voice', $reference, $e);
    if ($action === 'speak') {
        qv_json([
            'status' => 'fallback',
            'message' => 'Local voice is temporarily unavailable. Reference: ' . $reference,
            'voice' => 'browser-fallback', 'engine' => 'piper',
        ]);
    }
    qv_json(['status' => 'error', 'message' => 'Voice request failed. Reference: ' . $reference], 500);
}
