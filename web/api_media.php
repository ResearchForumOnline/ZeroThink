<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/core/media_security.php';

ob_end_clean();
$request_method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$query_action = strtolower(trim((string) ($_GET['action'] ?? '')));
$is_download_request = $request_method === 'GET' && $query_action === 'video_download';
$zm_request_id = 'med_' . bin2hex(random_bytes(8));
header('X-ZeroThink-Request-ID: ' . $zm_request_id);
if (!$is_download_request) {
    header('Content-Type: application/json; charset=UTF-8');
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function zm_reply($payload, $status = 200) {
    global $zm_request_id;
    if (is_array($payload) && !isset($payload['request_id'])) {
        $payload['request_id'] = $zm_request_id;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

class ZmMediaClientException extends RuntimeException {}

function zm_media_public_exception($message, $status = 400) {
    return new ZmMediaClientException((string) $message, (int) $status);
}

function zm_is_pro_user($user) {
    if (function_exists('is_architect_user') && is_architect_user($user)) {
        return true;
    }
    return !empty($user['is_pro'])
        || in_array(strtolower((string) ($user['plan'] ?? '')), ['pro', 'god_mode'], true)
        || strtolower((string) ($user['subscription_status'] ?? '')) === 'active';
}

function zm_media_config_int($environment_name, $site_name, $default, $minimum, $maximum) {
    $raw = getenv((string) $environment_name);
    if (($raw === false || trim((string) $raw) === '') && function_exists('site_setting')) {
        try {
            $raw = site_setting((string) $site_name, '');
        } catch (Throwable $e) {
            $raw = '';
        }
    }
    if (!is_numeric($raw)) {
        return (int) $default;
    }
    return max((int) $minimum, min((int) $maximum, (int) $raw));
}

function zm_media_max_body_bytes() {
    return zm_media_config_int(
        'ZEROTHINK_MEDIA_MAX_BODY_BYTES',
        'media_max_body_bytes',
        65536,
        8192,
        262144
    );
}

function zm_media_action_rate_limit($action) {
    $action = strtolower(trim((string) $action));
    $defaults = [
        'status' => 60,
        'speech' => 10,
        'image' => 6,
        'video' => 3,
        'video_status' => 60,
        'video_download' => 20,
    ];
    $default = (int) ($defaults[$action] ?? 10);
    $suffix = strtoupper(preg_replace('/[^a-z0-9]+/i', '_', $action));
    return zm_media_config_int(
        'ZEROTHINK_MEDIA_RATE_' . $suffix . '_PER_MINUTE',
        'media_rate_' . $action . '_per_minute',
        $default,
        1,
        600
    );
}

function zm_media_daily_generation_limit($action, $credential_source) {
    $action = strtolower(trim((string) $action));
    $lane = strtolower(trim((string) $credential_source)) === 'service' ? 'service' : 'user';
    $defaults = [
        'service' => ['speech' => 100, 'image' => 30, 'video' => 5],
        // User-owned provider keys retain a high safety ceiling. These calls do
        // not spend the ZeroThink service account's provider allowance.
        'user' => ['speech' => 1000, 'image' => 500, 'video' => 100],
    ];
    $default = (int) ($defaults[$lane][$action] ?? 100);
    $action_suffix = strtoupper(preg_replace('/[^a-z0-9]+/i', '_', $action));
    $lane_suffix = strtoupper($lane);
    return zm_media_config_int(
        'ZEROTHINK_MEDIA_' . $lane_suffix . '_' . $action_suffix . '_DAILY',
        'media_' . $lane . '_' . $action . '_daily',
        $default,
        1,
        100000
    );
}

function zm_media_usage_table() {
    static $ready = false;
    if ($ready) {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS media_usage_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(32) NOT NULL,
        credential_source VARCHAR(16) NOT NULL DEFAULT 'none',
        request_id VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_media_usage_rate (user_id, action, created_at),
        KEY idx_media_usage_daily (user_id, credential_source, action, created_at),
        KEY idx_media_usage_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;

    try {
        if (random_int(1, 200) === 1) {
            db()->exec("DELETE FROM media_usage_events WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 35 DAY) LIMIT 5000");
        }
    } catch (Throwable $e) {
    }
}

function zm_media_usage_lock(PDO $db, $uid, $action) {
    $name = 'zt_media_' . (int) $uid . '_' . substr(hash('sha256', (string) $action), 0, 20);
    $stmt = $db->prepare('SELECT GET_LOCK(?, 2)');
    $stmt->execute([$name]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException('Media usage lock unavailable.');
    }
    return $name;
}

function zm_media_usage_unlock(PDO $db, $name) {
    if ($name === '') {
        return;
    }
    try {
        $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$name]);
    } catch (Throwable $e) {
    }
}

function zm_media_consume_budget($uid, $action, $credential_source, $request_id) {
    $uid = (int) $uid;
    $action = strtolower(trim((string) $action));
    $source = in_array((string) $credential_source, ['service', 'user'], true)
        ? (string) $credential_source
        : 'none';
    if ($uid <= 0 || !in_array($action, ['status', 'speech', 'image', 'video', 'video_status', 'video_download'], true)) {
        throw new RuntimeException('Invalid media usage identity.');
    }

    zm_media_usage_table();
    $db = db();
    $lock = '';
    try {
        $lock = zm_media_usage_lock($db, $uid, $action);
        $rate_limit = zm_media_action_rate_limit($action);
        $rate = $db->prepare("SELECT COUNT(*) FROM media_usage_events
            WHERE user_id = ? AND action = ?
              AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 SECOND)");
        $rate->execute([$uid, $action]);
        if ((int) $rate->fetchColumn() >= $rate_limit) {
            throw zm_media_public_exception('Too many media requests. Wait a minute and try again.', 429);
        }

        if (in_array($action, ['speech', 'image', 'video'], true)) {
            $daily_limit = zm_media_daily_generation_limit($action, $source);
            $daily = $db->prepare("SELECT COUNT(*) FROM media_usage_events
                WHERE user_id = ? AND action = ? AND credential_source = ?
                  AND created_at >= UTC_DATE()");
            $daily->execute([$uid, $action, $source]);
            if ((int) $daily->fetchColumn() >= $daily_limit) {
                $label = $source === 'service' ? 'service-lane' : 'user-key';
                throw zm_media_public_exception(
                    'Your daily ' . $label . ' ' . str_replace('_', ' ', $action) . ' limit has been reached. Try again after 00:00 UTC.',
                    429
                );
            }
        }

        $insert = $db->prepare("INSERT INTO media_usage_events
            (user_id, action, credential_source, request_id, created_at)
            VALUES (?, ?, ?, ?, UTC_TIMESTAMP())");
        $insert->execute([$uid, $action, $source, substr((string) $request_id, 0, 32)]);
    } finally {
        zm_media_usage_unlock($db, $lock);
    }
}

function zm_db_text_column($uid, $column) {
    if (!preg_match('/^[a-z0-9_]+$/i', (string) $column) || !function_exists('db') || (int) $uid <= 0) {
        return '';
    }
    try {
        $stmt = db()->prepare("SELECT {$column} FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $uid]);
        return trim((string) $stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}

function zm_media_key_info($uid, $payload, $inline_names, $session_names, $db_columns, $env_names, $site_names, $allow_service = false) {
    foreach ((array) $inline_names as $name) {
        $value = trim((string) ($payload[$name] ?? ''));
        if ($value !== '') {
            return ['key' => $value, 'source' => 'user', 'source_detail' => 'inline'];
        }
    }
    foreach ((array) $session_names as $name) {
        $value = trim((string) ($_SESSION[$name] ?? ''));
        if ($value !== '') {
            return ['key' => $value, 'source' => 'user', 'source_detail' => 'session'];
        }
    }
    foreach ((array) $db_columns as $column) {
        $value = zm_db_text_column($uid, $column);
        if ($value !== '') {
            return ['key' => $value, 'source' => 'user', 'source_detail' => 'vault'];
        }
    }
    if (!$allow_service) {
        return ['key' => '', 'source' => 'none', 'source_detail' => ''];
    }
    foreach ((array) $env_names as $name) {
        $value = trim((string) getenv($name));
        if ($value !== '') {
            return ['key' => $value, 'source' => 'service', 'source_detail' => 'environment'];
        }
    }
    if (function_exists('site_setting')) {
        foreach ((array) $site_names as $name) {
            $value = trim((string) site_setting($name, ''));
            if ($value !== '') {
                return ['key' => $value, 'source' => 'service', 'source_detail' => 'site'];
            }
        }
    }
    return ['key' => '', 'source' => 'none', 'source_detail' => ''];
}

function zm_media_key($uid, $payload, $inline_names, $db_columns, $env_names, $site_names, $allow_service = false) {
    $info = zm_media_key_info($uid, $payload, $inline_names, [], $db_columns, $env_names, $site_names, $allow_service);
    return (string) ($info['key'] ?? '');
}

function zm_openai_key_info($uid, $payload, $allow_service = false) {
    return zm_media_key_info($uid, $payload, ['openai_key', 'key'], ['api_key_openai'], ['api_key_openai'], ['OPENAI_API_KEY'], ['service_api_key_openai'], $allow_service);
}

function zm_openai_key($uid, $payload, $allow_service = false) {
    $info = zm_openai_key_info($uid, $payload, $allow_service);
    return (string) ($info['key'] ?? '');
}

function zm_xai_key_info($uid, $payload, $allow_service = false) {
    return zm_media_key_info($uid, $payload, ['xai_key', 'api_key_xai'], [], ['api_key_xai'], ['XAI_API_KEY'], ['service_api_key_xai'], $allow_service);
}

function zm_xai_key($uid, $payload, $allow_service = false) {
    $info = zm_xai_key_info($uid, $payload, $allow_service);
    return (string) ($info['key'] ?? '');
}

function zm_seedance_key_info($uid, $payload, $allow_service = false) {
    return zm_media_key_info($uid, $payload, ['seedance_key', 'ark_key', 'byteplus_key', 'api_key_seedance'], [], ['api_key_seedance', 'api_key_byteplus'], ['ARK_API_KEY', 'SEEDANCE_API_KEY', 'BYTEPLUS_API_KEY'], ['service_api_key_seedance', 'service_api_key_byteplus', 'service_api_key_ark'], $allow_service);
}

function zm_seedance_key($uid, $payload, $allow_service = false) {
    $info = zm_seedance_key_info($uid, $payload, $allow_service);
    return (string) ($info['key'] ?? '');
}

function zm_http_json($method, $url, $api_key, $payload = null, $timeout = 120) {
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Accept: application/json',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => max(20, (int) $timeout),
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $decoded = json_decode((string) $raw, true);
    if ($http < 200 || $http >= 300) {
        $msg = is_array($decoded) ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? $decoded['error']['code'] ?? '') : '';
        throw new Exception($msg !== '' ? $msg : ($error ?: 'Provider request failed with HTTP ' . $http));
    }
    return is_array($decoded) ? $decoded : ['raw' => $raw];
}

function zm_seedance_base($input) {
    return zt_media_seedance_base(is_array($input) ? $input : []);
}

function zm_video_provider($input, $id = '') {
    $provider = strtolower(trim((string) ($input['provider'] ?? $input['video_provider'] ?? '')));
    if ($provider !== '') {
        return $provider;
    }
    $id = trim((string) $id);
    if (preg_match('/^video_/i', $id)) {
        return 'openai';
    }
    if (preg_match('/^cgt-|^task_/i', $id)) {
        return 'seedance';
    }
    if (preg_match('/^[0-9a-f-]{24,}$/i', $id)) {
        return 'xai';
    }
    return 'xai';
}

class ZmMediaAccessException extends RuntimeException {}

function zm_video_job_scope($provider, $key) {
    return hash('sha256', strtolower(trim((string) $provider)) . "\0" . (string) $key);
}

function zm_video_job_table() {
    static $ready = false;
    if ($ready) {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS media_video_jobs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider VARCHAR(24) NOT NULL,
        job_id VARCHAR(255) NOT NULL,
        job_id_hash CHAR(64) NOT NULL,
        credential_scope CHAR(64) NOT NULL,
        credential_source VARCHAR(16) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_accessed_at DATETIME NULL,
        UNIQUE KEY uniq_media_video_provider_job (provider, credential_scope, job_id_hash),
        KEY idx_media_video_owner (user_id, provider, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

function zm_video_job_record($uid, $provider, $job_id, $key_info) {
    $uid = (int) $uid;
    $provider = strtolower(trim((string) $provider));
    $job_id = trim((string) $job_id);
    $key = (string) ($key_info['key'] ?? '');
    $source = (string) ($key_info['source'] ?? 'none');
    if ($uid <= 0 || !in_array($provider, ['xai', 'seedance', 'openai'], true)
        || $job_id === '' || strlen($job_id) > 255 || $key === '') {
        throw new RuntimeException('Video provider returned an invalid job identity.');
    }

    zm_video_job_table();
    $db = db();
    $owns_transaction = !$db->inTransaction();
    if ($owns_transaction) {
        $db->beginTransaction();
    }
    try {
        $scope = zm_video_job_scope($provider, $key);
        $job_hash = hash('sha256', $job_id);
        $insert = $db->prepare("INSERT IGNORE INTO media_video_jobs
            (user_id, provider, job_id, job_id_hash, credential_scope, credential_source)
            VALUES (?, ?, ?, ?, ?, ?)");
        $insert->execute([$uid, $provider, $job_id, $job_hash, $scope, $source]);

        $owner = $db->prepare("SELECT user_id FROM media_video_jobs
            WHERE provider = ? AND credential_scope = ? AND job_id_hash = ?
            LIMIT 1 FOR UPDATE");
        $owner->execute([$provider, $scope, $job_hash]);
        if ((int) $owner->fetchColumn() !== $uid) {
            throw new ZmMediaAccessException('Video job not found for this account.');
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
}

function zm_video_job_require_owner($uid, $provider, $job_id, $key_info) {
    // Manual provider IDs remain available when the caller supplies or stores
    // their own credential. Shared service credentials require a local owner
    // record created at submission time.
    if ((string) ($key_info['source'] ?? 'none') !== 'service') {
        return;
    }

    $uid = (int) $uid;
    $provider = strtolower(trim((string) $provider));
    $job_id = trim((string) $job_id);
    $key = (string) ($key_info['key'] ?? '');
    if ($uid <= 0 || $job_id === '' || $key === '') {
        throw new ZmMediaAccessException('Video job not found for this account.');
    }
    zm_video_job_table();
    $lookup = db()->prepare("SELECT id FROM media_video_jobs
        WHERE user_id = ? AND provider = ? AND credential_scope = ? AND job_id_hash = ?
        LIMIT 1");
    $lookup->execute([
        $uid,
        $provider,
        zm_video_job_scope($provider, $key),
        hash('sha256', $job_id),
    ]);
    $row_id = (int) $lookup->fetchColumn();
    if ($row_id <= 0) {
        throw new ZmMediaAccessException('Video job not found for this account.');
    }
    $touch = db()->prepare("UPDATE media_video_jobs SET last_accessed_at = NOW() WHERE id = ? AND user_id = ? LIMIT 1");
    $touch->execute([$row_id, $uid]);
}

function zm_extract_video_url($data) {
    if (!is_array($data)) {
        return '';
    }
    $paths = [
        ['video', 'url'],
        ['content', 'video_url'],
        ['content', 'url'],
        ['data', 0, 'video_url'],
        ['data', 0, 'url'],
        ['output', 0, 'url'],
    ];
    foreach ($paths as $path) {
        $cursor = $data;
        foreach ($path as $part) {
            if (is_array($cursor) && array_key_exists($part, $cursor)) {
                $cursor = $cursor[$part];
            } else {
                $cursor = null;
                break;
            }
        }
        if (is_string($cursor) && preg_match('#^https://#', $cursor)) {
            return $cursor;
        }
    }
    return '';
}

function zm_openai_json($method, $path, $api_key, $payload = null, $timeout = 90) {
    $ch = curl_init('https://api.openai.com' . $path);
    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Accept: application/json',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => max(20, (int) $timeout),
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $decoded = json_decode((string) $raw, true);
    if ($http < 200 || $http >= 300) {
        $msg = is_array($decoded) ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? '') : '';
        throw new Exception($msg !== '' ? $msg : ($error ?: 'OpenAI request failed with HTTP ' . $http));
    }
    return is_array($decoded) ? $decoded : ['raw' => $raw];
}

function zm_openai_binary($path, $api_key, $payload, $timeout = 90) {
    $ch = curl_init('https://api.openai.com' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
            'Accept: application/octet-stream',
        ],
        CURLOPT_TIMEOUT => max(20, (int) $timeout),
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($http < 200 || $http >= 300) {
        $decoded = json_decode((string) $raw, true);
        $msg = is_array($decoded) ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? '') : '';
        throw new Exception($msg !== '' ? $msg : ($error ?: 'OpenAI binary request failed with HTTP ' . $http));
    }
    return ['bytes' => $raw, 'content_type' => $ctype ?: 'application/octet-stream'];
}

function zm_openai_binary_get($path, $api_key, $timeout = 180) {
    $ch = curl_init('https://api.openai.com' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
            'Accept: */*',
        ],
        CURLOPT_TIMEOUT => max(20, (int) $timeout),
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($http < 200 || $http >= 300) {
        $decoded = json_decode((string) $raw, true);
        $msg = is_array($decoded) ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? '') : '';
        throw new Exception($msg !== '' ? $msg : ($error ?: 'OpenAI download failed with HTTP ' . $http));
    }
    return ['bytes' => $raw, 'content_type' => $ctype ?: 'application/octet-stream'];
}

$input = [];
if ($is_download_request) {
    $input = $_GET;
} else {
    if ($request_method !== 'POST') {
        header('Allow: POST');
        zm_reply(['status' => 'error', 'message' => 'POST with a JSON body is required.'], 405);
    }

    $content_type = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (!preg_match('#^application/json(?:\s*;|$)#', $content_type)) {
        zm_reply(['status' => 'error', 'message' => 'Content-Type application/json is required.'], 415);
    }

    $max_body_bytes = zm_media_max_body_bytes();
    $declared_length = max(0, (int) ($_SERVER['CONTENT_LENGTH'] ?? 0));
    if ($declared_length > $max_body_bytes) {
        zm_reply(['status' => 'error', 'message' => 'Media request body is too large.'], 413);
    }
    $raw_input = file_get_contents('php://input', false, null, 0, $max_body_bytes + 1);
    if (!is_string($raw_input) || strlen($raw_input) > $max_body_bytes) {
        zm_reply(['status' => 'error', 'message' => 'Media request body is too large.'], 413);
    }
    if ($raw_input === '' || substr(ltrim($raw_input), 0, 1) !== '{') {
        zm_reply(['status' => 'error', 'message' => 'A JSON object is required.'], 400);
    }
    try {
        $input = json_decode($raw_input, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        zm_reply(['status' => 'error', 'message' => 'Malformed JSON request.'], 400);
    }
    if (!is_array($input)) {
        zm_reply(['status' => 'error', 'message' => 'A JSON object is required.'], 400);
    }
}

$action = strtolower(trim((string) ($input['action'] ?? '')));
if ($action === '') {
    zm_reply(['status' => 'error', 'message' => 'Media action is required.'], 400);
}
if (!in_array($action, ['status', 'speech', 'image', 'video', 'video_status', 'video_download'], true)) {
    zm_reply(['status' => 'error', 'message' => 'Unknown media action.'], 400);
}
if ($action === 'video_download' && !$is_download_request) {
    header('Allow: GET');
    zm_reply(['status' => 'error', 'message' => 'Video downloads require GET.'], 405);
}

$user = function_exists('request_user_record') ? request_user_record(true) : current_user_record(true);
$uid = (int) ($user['id'] ?? 0);
if ($uid <= 0) {
    zm_reply(['status' => 'error', 'message' => 'Auth required.'], 403);
}
if (!$is_download_request && (int) ($_SESSION['user_id'] ?? 0) > 0) {
    $csrf = (string) ($_SERVER['HTTP_X_ZEROTHINK_CSRF'] ?? '');
    if (!zt_csrf_validate($csrf)) {
        zm_reply(['status' => 'error', 'message' => 'Security token expired. Refresh Media Studio and try again.'], 403);
    }
}

$is_pro = zm_is_pro_user($user);

try {
    if ($action === 'status') {
        zm_media_consume_budget($uid, 'status', 'none', $zm_request_id);
        zm_reply([
            'status' => 'success',
            'is_pro' => $is_pro,
            'has_openai_key' => zm_openai_key($uid, [], $is_pro) !== '',
            'has_xai_key' => zm_xai_key($uid, [], $is_pro) !== '',
            'has_seedance_key' => zm_seedance_key($uid, [], $is_pro) !== '',
            'models' => [
                'speech' => ['gpt-4o-mini-tts'],
                'image' => ['gpt-image-2', 'gpt-image-1.5', 'gpt-image-1', 'gpt-image-1-mini'],
                'video' => ['grok-imagine-video', 'seedance-1-5-pro-251215', 'dreamina-seedance-2-0-fast-260128', 'dreamina-seedance-2-0-260128', 'sora-2 legacy'],
            ],
            'video_providers' => [
                'xai' => [
                    'label' => 'xAI Grok Imagine',
                    'configured' => zm_xai_key($uid, [], $is_pro) !== '',
                    'pro_only' => true,
                    'models' => ['grok-imagine-video'],
                    'docs' => 'https://docs.x.ai/developers/model-capabilities/video/generation',
                ],
                'seedance' => [
                    'label' => 'SeedDance / BytePlus Ark',
                    'configured' => zm_seedance_key($uid, [], $is_pro) !== '',
                    'pro_only' => true,
                    'models' => ['seedance-1-5-pro-251215', 'dreamina-seedance-2-0-fast-260128', 'dreamina-seedance-2-0-260128', 'seedance-1-0-pro-fast-251015', 'seedance-1-0-pro-250528'],
                    'docs' => 'https://docs.byteplus.com/en/docs/ModelArk/1520757',
                ],
                'openai' => [
                    'label' => 'OpenAI Sora legacy',
                    'configured' => zm_openai_key($uid, [], $is_pro) !== '',
                    'pro_only' => true,
                    'models' => ['sora-2', 'sora-2-pro'],
                    'note' => 'Legacy fallback. xAI is the recommended default video lane.',
                ],
            ],
            'downloads' => [
                'speech' => 'mp3 data URL',
                'image' => 'png data URL',
                'video' => 'provider temporary MP4 URL or OpenAI content proxy',
            ],
        ]);
    }

    if ($action === 'speech') {
        $key_info = zm_openai_key_info($uid, $input, $is_pro);
        $key = (string) ($key_info['key'] ?? '');
        if ($key === '') {
            throw zm_media_public_exception('OpenAI key missing. Save one in Neural Vault, pass your own key, or use a Pro account with a service key.');
        }
        $prompt = trim((string) ($input['text'] ?? $input['prompt'] ?? ''));
        if ($prompt === '') {
            throw zm_media_public_exception('Text is required for speech.');
        }
        zm_media_consume_budget($uid, 'speech', (string) ($key_info['source'] ?? 'user'), $zm_request_id);
        $audio = zm_openai_binary('/v1/audio/speech', $key, [
            'model' => trim((string) ($input['model'] ?? 'gpt-4o-mini-tts')),
            'voice' => trim((string) ($input['voice'] ?? 'alloy')),
            'input' => substr($prompt, 0, 12000),
            'instructions' => trim((string) ($input['instructions'] ?? 'Speak clearly and naturally.')),
            'response_format' => 'mp3',
        ], 120);
        zm_reply([
            'status' => 'success',
            'kind' => 'speech',
            'mime' => 'audio/mpeg',
            'download_name' => 'zerothink-voice-' . gmdate('Ymd-His') . '.mp3',
            'data_url' => 'data:audio/mpeg;base64,' . base64_encode((string) $audio['bytes']),
        ]);
    }

    if ($action === 'image') {
        $key_info = zm_openai_key_info($uid, $input, $is_pro);
        $key = (string) ($key_info['key'] ?? '');
        if ($key === '') {
            throw zm_media_public_exception('OpenAI key missing. Save one in Neural Vault, pass your own key, or use a Pro account with a service key.');
        }
        $prompt = trim((string) ($input['prompt'] ?? ''));
        if ($prompt === '') {
            throw zm_media_public_exception('Prompt is required for image generation.');
        }
        zm_media_consume_budget($uid, 'image', (string) ($key_info['source'] ?? 'user'), $zm_request_id);
        $model = trim((string) ($input['model'] ?? 'gpt-image-2'));
        $payload = [
            'model' => $model,
            'prompt' => substr($prompt, 0, 8000),
            'size' => trim((string) ($input['size'] ?? '1024x1024')),
            'n' => 1,
            'response_format' => 'b64_json',
        ];
        if (!empty($input['quality'])) {
            $payload['quality'] = trim((string) $input['quality']);
        }
        $data = zm_openai_json('POST', '/v1/images/generations', $key, $payload, 180);
        $images = [];
        foreach (($data['data'] ?? []) as $item) {
            if (!empty($item['b64_json'])) {
                $images[] = 'data:image/png;base64,' . $item['b64_json'];
            } elseif (!empty($item['url'])) {
                $images[] = (string) $item['url'];
            }
        }
        zm_reply([
            'status' => 'success',
            'kind' => 'image',
            'images' => $images,
            'download_name' => 'zerothink-image-' . gmdate('Ymd-His') . '.png',
            'raw' => $data,
        ]);
    }

    if ($action === 'video') {
        if (!$is_pro) {
            throw zm_media_public_exception('Video generation is Pro-only.', 403);
        }
        $prompt = trim((string) ($input['prompt'] ?? ''));
        if ($prompt === '') {
            throw zm_media_public_exception('Prompt is required for video generation.');
        }
        $provider = zm_video_provider($input);
        if ($provider === 'xai') {
            $key_info = zm_xai_key_info($uid, $input, $is_pro);
            $key = (string) ($key_info['key'] ?? '');
            if ($key === '') {
                throw zm_media_public_exception('xAI key missing. Save it in Vault Keys for Grok Imagine video.');
            }
            zm_media_consume_budget($uid, 'video', (string) ($key_info['source'] ?? 'user'), $zm_request_id);
            $data = zm_http_json('POST', 'https://api.x.ai/v1/videos/generations', $key, [
                'model' => trim((string) ($input['model'] ?? 'grok-imagine-video')),
                'prompt' => substr($prompt, 0, 6000),
                'duration' => max(1, min(15, (int) ($input['seconds'] ?? $input['duration'] ?? 8))),
                'aspect_ratio' => trim((string) ($input['aspect_ratio'] ?? $input['ratio'] ?? '16:9')),
                'resolution' => trim((string) ($input['resolution'] ?? '720p')),
            ], 120);
            $request_id = (string) ($data['request_id'] ?? $data['id'] ?? '');
            zm_video_job_record($uid, 'xai', $request_id, $key_info);
            zm_reply([
                'status' => 'success',
                'kind' => 'video_job',
                'provider' => 'xai',
                'job' => [
                    'id' => $request_id,
                    'request_id' => $request_id,
                    'status' => 'pending',
                    'provider' => 'xai',
                    'raw' => $data,
                ],
            ]);
        }
        if ($provider === 'seedance') {
            $key_info = zm_seedance_key_info($uid, $input, $is_pro);
            $key = (string) ($key_info['key'] ?? '');
            if ($key === '') {
                throw zm_media_public_exception('SeedDance / BytePlus Ark key missing. Save ARK_API_KEY in Vault Keys.');
            }
            zm_media_consume_budget($uid, 'video', (string) ($key_info['source'] ?? 'user'), $zm_request_id);
            $model = trim((string) ($input['model'] ?? 'seedance-1-5-pro-251215'));
            $payload = [
                'model' => $model,
                'content' => [
                    ['type' => 'text', 'text' => substr($prompt, 0, 6000)],
                ],
                'ratio' => trim((string) ($input['ratio'] ?? $input['aspect_ratio'] ?? '16:9')),
                'duration' => max(2, min(15, (int) ($input['seconds'] ?? $input['duration'] ?? 5))),
                'resolution' => trim((string) ($input['resolution'] ?? '720p')),
                'generate_audio' => !empty($input['generate_audio']),
                'return_last_frame' => true,
            ];
            if (!empty($input['draft'])) {
                $payload['draft'] = true;
                $payload['resolution'] = '480p';
            }
            $data = zm_http_json('POST', zt_media_seedance_tasks_url($input), $key, $payload, 120);
            $task_id = (string) ($data['id'] ?? $data['task_id'] ?? $data['data'][0]['task_id'] ?? '');
            zm_video_job_record($uid, 'seedance', $task_id, $key_info);
            zm_reply([
                'status' => 'success',
                'kind' => 'video_job',
                'provider' => 'seedance',
                'job' => [
                    'id' => $task_id,
                    'task_id' => $task_id,
                    'status' => (string) ($data['status'] ?? 'queued'),
                    'provider' => 'seedance',
                    'raw' => $data,
                ],
            ]);
        }
        if ($provider === 'openai') {
            $key_info = zm_openai_key_info($uid, $input, $is_pro);
            $key = (string) ($key_info['key'] ?? '');
            if ($key === '') {
                throw zm_media_public_exception('OpenAI key missing for legacy Sora fallback.');
            }
            zm_media_consume_budget($uid, 'video', (string) ($key_info['source'] ?? 'user'), $zm_request_id);
            $ratio = trim((string) ($input['aspect_ratio'] ?? $input['ratio'] ?? '9:16'));
            $resolution = trim((string) ($input['resolution'] ?? '720p'));
            $size = trim((string) ($input['size'] ?? ''));
            if ($size === '') {
                $size = $ratio === '16:9' ? '1280x720' : '720x1280';
                if ($resolution === '480p') {
                    $size = $ratio === '16:9' ? '854x480' : '480x854';
                }
            }
            $data = zm_openai_json('POST', '/v1/videos', $key, [
                'model' => trim((string) ($input['model'] ?? 'sora-2')),
                'prompt' => substr($prompt, 0, 6000),
                'size' => $size,
                'seconds' => max(4, min(12, (int) ($input['seconds'] ?? 4))),
            ], 120);
            $data['provider'] = 'openai';
            zm_video_job_record($uid, 'openai', (string) ($data['id'] ?? ''), $key_info);
            zm_reply(['status' => 'success', 'kind' => 'video_job', 'provider' => 'openai', 'job' => $data]);
        }
        throw zm_media_public_exception('Unsupported video provider.');
    }

    if ($action === 'video_status') {
        zm_media_consume_budget($uid, 'video_status', 'none', $zm_request_id);
        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '') {
            throw zm_media_public_exception('Video job id is required.');
        }
        $provider = zm_video_provider($input, $id);
        if ($provider === 'xai') {
            $key_info = zm_xai_key_info($uid, $input, $is_pro);
            $key = (string) ($key_info['key'] ?? '');
            if ($key === '') {
                throw zm_media_public_exception('xAI key missing.');
            }
            zm_video_job_require_owner($uid, 'xai', $id, $key_info);
            $data = zm_http_json('GET', 'https://api.x.ai/v1/videos/' . rawurlencode($id), $key, null, 60);
            $video_url = zm_extract_video_url($data);
            zm_reply(['status' => 'success', 'kind' => 'video_status', 'provider' => 'xai', 'job' => $data, 'video_url' => $video_url, 'download_url' => $video_url]);
        }
        if ($provider === 'seedance') {
            $key_info = zm_seedance_key_info($uid, $input, $is_pro);
            $key = (string) ($key_info['key'] ?? '');
            if ($key === '') {
                throw zm_media_public_exception('SeedDance / BytePlus Ark key missing.');
            }
            zm_video_job_require_owner($uid, 'seedance', $id, $key_info);
            $data = zm_http_json('GET', zt_media_seedance_task_url($input, $id), $key, null, 60);
            $video_url = zm_extract_video_url($data);
            $last_frame = is_array($data) ? (string) ($data['content']['last_frame_url'] ?? '') : '';
            zm_reply(['status' => 'success', 'kind' => 'video_status', 'provider' => 'seedance', 'job' => $data, 'video_url' => $video_url, 'download_url' => $video_url, 'last_frame_url' => $last_frame]);
        }
        if ($provider === 'openai') {
            $key_info = zm_openai_key_info($uid, $input, $is_pro);
            $key = (string) ($key_info['key'] ?? '');
            if ($key === '') {
                throw zm_media_public_exception('OpenAI key missing.');
            }
            zm_video_job_require_owner($uid, 'openai', $id, $key_info);
            $data = zm_openai_json('GET', '/v1/videos/' . rawurlencode($id), $key, null, 60);
            $download = '/api_media.php?action=video_download&provider=openai&id=' . rawurlencode($id) . '&variant=video';
            $thumb = '/api_media.php?action=video_download&provider=openai&id=' . rawurlencode($id) . '&variant=thumbnail';
            zm_reply(['status' => 'success', 'kind' => 'video_status', 'provider' => 'openai', 'job' => $data, 'download_url' => $download, 'thumbnail_url' => $thumb]);
        }
        throw zm_media_public_exception('Unsupported video provider.');
    }

    if ($action === 'video_download') {
        zm_media_consume_budget($uid, 'video_download', 'none', $zm_request_id);
        $id = trim((string) ($input['id'] ?? ''));
        $variant = strtolower(trim((string) ($input['variant'] ?? 'video')));
        if ($id === '') {
            throw zm_media_public_exception('Video job id is required.');
        }
        $provider = zm_video_provider($input, $id);
        if ($provider === 'xai' || $provider === 'seedance') {
            $key_info = $provider === 'xai'
                ? zm_xai_key_info($uid, $input, $is_pro)
                : zm_seedance_key_info($uid, $input, $is_pro);
            $key = (string) ($key_info['key'] ?? '');
            if ($key === '') {
                throw zm_media_public_exception($provider === 'xai' ? 'xAI key missing.' : 'SeedDance / BytePlus Ark key missing.');
            }
            zm_video_job_require_owner($uid, $provider, $id, $key_info);
            $data = $provider === 'xai'
                ? zm_http_json('GET', 'https://api.x.ai/v1/videos/' . rawurlencode($id), $key, null, 60)
                : zm_http_json('GET', zt_media_seedance_task_url($input, $id), $key, null, 60);
            $url = zm_extract_video_url($data);
            if ($url === '') {
                throw zm_media_public_exception('Video is not ready or no downloadable URL was returned.', 409);
            }
            header('Location: ' . $url, true, 302);
            exit;
        }
        $key_info = zm_openai_key_info($uid, $input, $is_pro);
        $key = (string) ($key_info['key'] ?? '');
        if ($key === '') {
            throw zm_media_public_exception('OpenAI key missing.');
        }
        zm_video_job_require_owner($uid, 'openai', $id, $key_info);
        if (!in_array($variant, ['video', 'thumbnail', 'spritesheet'], true)) {
            $variant = 'video';
        }
        $path = '/v1/videos/' . rawurlencode($id) . '/content?variant=' . rawurlencode($variant);
        $file = zm_openai_binary_get($path, $key, 300);
        $ext = $variant === 'video' ? 'mp4' : ($variant === 'spritesheet' ? 'jpg' : 'png');
        $mime = trim((string) $file['content_type']) ?: ($variant === 'video' ? 'video/mp4' : 'image/png');
        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="zerothink-' . $variant . '-' . preg_replace('/[^A-Za-z0-9_-]+/', '', $id) . '.' . $ext . '"');
            header('Content-Length: ' . strlen((string) $file['bytes']));
        }
        echo (string) $file['bytes'];
        exit;
    }

    zm_reply(['status' => 'error', 'message' => 'Unknown media action.'], 400);
} catch (ZmMediaAccessException $e) {
    zm_reply(['status' => 'error', 'message' => 'Video job not found for this account.'], 404);
} catch (ZmMediaClientException $e) {
    $status = (int) $e->getCode();
    if ($status < 400 || $status > 499) {
        $status = 400;
    }
    if ($status === 429 && !headers_sent()) {
        header('Retry-After: 60');
    }
    zm_reply(['status' => 'error', 'message' => $e->getMessage()], $status);
} catch (Throwable $e) {
    $internal_message = preg_replace('/[\r\n\x00-\x1F\x7F]+/', ' ', (string) $e->getMessage());
    error_log('ZeroThink media request ' . $zm_request_id
        . ' action=' . $action . ' user=' . $uid . ' failed: '
        . get_class($e) . ' ' . substr($internal_message, 0, 800));
    zm_reply([
        'status' => 'error',
        'message' => 'Media request could not be completed. Reference ID: ' . $zm_request_id,
    ], 500);
}
?>
