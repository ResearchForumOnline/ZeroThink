<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/app/bootstrap.php';

function cli_json($payload, $status = 200) {
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cli_request_payload() {
    $raw = (string) file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    return [];
}

function cli_verification_url($user_code) {
    return 'https://zerothink.talktoai.org/cli/connect?user_code=' . rawurlencode((string) $user_code);
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    cli_json(['status' => 'error', 'message' => 'POST required.'], 405);
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
    cli_json(['status' => 'error', 'message' => 'Request payload is too large.'], 413);
}

$content_type = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
if (strpos($content_type, 'application/json') !== 0
    && strpos($content_type, 'application/x-www-form-urlencoded') !== 0) {
    cli_json(['status' => 'error', 'message' => 'JSON or form request required.'], 415);
}

$input = cli_request_payload();
$action = strtolower(trim((string) ($input['action'] ?? '')));

if ($action === '') {
    cli_json(['status' => 'error', 'message' => 'Missing action.'], 400);
}

if ($action === 'device_start') {
    $started = cli_start_device_request(
        trim((string) ($input['label'] ?? 'ZeroThink CLI')),
        [
            'platform' => trim((string) ($input['platform'] ?? '')),
            'hostname' => trim((string) ($input['hostname'] ?? '')),
            'cwd' => trim((string) ($input['cwd'] ?? '')),
            'version' => trim((string) ($input['version'] ?? '')),
        ]
    );

    if (empty($started['ok'])) {
        $error = (string) ($started['error'] ?? 'device_start_failed');
        $status = in_array($error, ['rate_limited', 'rate_limit_unavailable'], true) ? 429 : 500;
        $message = $error === 'rate_limited'
            ? 'Too many CLI pairing requests. Wait a minute and try again.'
            : 'Unable to start CLI login flow.';
        cli_json(['status' => 'error', 'message' => $message], $status);
    }

    cli_json([
        'status' => 'success',
        'device_code' => $started['device_code'],
        'user_code' => $started['user_code'],
        'verification_url' => cli_verification_url($started['user_code']),
        'expires_at' => $started['expires_at'],
        'expires_in' => 900,
        'interval' => (int) ($started['interval'] ?? 3),
    ]);
}

if ($action === 'device_poll') {
    $device_code = trim((string) ($input['device_code'] ?? ''));
    if ($device_code === '') {
        cli_json(['status' => 'error', 'message' => 'Missing device code.'], 400);
    }

    $result = cli_consume_device_request($device_code);
    if (!empty($result['ok'])) {
        cli_json([
            'status' => 'success',
            'access_token' => $result['token'],
            'token_hint' => $result['token_hint'],
            'expires_at' => $result['expires_at'],
            'user' => $result['user'],
        ]);
    }

    $error = (string) ($result['error'] ?? 'authorization_pending');
    $status = in_array($error, ['authorization_pending', 'expired', 'access_denied', 'already_used'], true) ? $error : 'error';
    $http = $status === 'authorization_pending' ? 202 : 400;
    cli_json([
        'status' => $status,
        'message' => str_replace('_', ' ', $error),
    ], $http);
}

if (!request_user_is_authenticated()) {
    cli_json(['status' => 'error', 'message' => 'Auth required.'], 403);
}

$user = request_user_record();
$uid = (int) ($user['id'] ?? 0);

if ($action === 'me') {
    $provider_keys = [
        'groq' => false,
        'nvidia' => false,
        'openai' => false,
        'xai' => false,
        'gemini' => false,
        'serper' => false,
    ];

    try {
        $stmt = db()->prepare("SELECT api_key_groq, api_key_nvidia, api_key_openai, api_key_xai, api_key_gemini, api_key_google, api_key_serper, serper_api_key, current_model, system_prompt FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $provider_keys = [
            'groq' => trim((string) ($row['api_key_groq'] ?? '')) !== '',
            'nvidia' => trim((string) ($row['api_key_nvidia'] ?? '')) !== '',
            'openai' => trim((string) ($row['api_key_openai'] ?? '')) !== '',
            'xai' => trim((string) ($row['api_key_xai'] ?? '')) !== '',
            'gemini' => trim((string) (($row['api_key_gemini'] ?? '') ?: ($row['api_key_google'] ?? ''))) !== '',
            'serper' => trim((string) (($row['api_key_serper'] ?? '') ?: ($row['serper_api_key'] ?? ''))) !== '',
        ];
        $current_model = trim((string) ($row['current_model'] ?? ''));
        $has_persona = trim((string) ($row['system_prompt'] ?? '')) !== '';
    } catch (Throwable $e) {
        $current_model = '';
        $has_persona = false;
    }

    cli_json([
        'status' => 'success',
        'user' => [
            'id' => $uid,
            'email' => $user['email'] ?? '',
            'role' => $user['role'] ?? 'user',
            'plan' => $user['plan'] ?? 'free',
            'is_pro' => (int) ($user['is_pro'] ?? 0),
            'current_model' => $current_model,
            'has_persona' => $has_persona,
            'provider_keys' => $provider_keys,
        ],
    ]);
}

if ($action === 'revoke') {
    $token = request_bearer_token();
    if ($token === '') {
        $token = trim((string) ($input['token'] ?? ''));
    }
    if ($token === '') {
        cli_json(['status' => 'error', 'message' => 'Missing token.'], 400);
    }

    $ok = revoke_cli_token($token, $uid);
    cli_json([
        'status' => $ok ? 'success' : 'error',
        'message' => $ok ? 'Token revoked.' : 'Token not found or already revoked.',
    ], $ok ? 200 : 404);
}

cli_json(['status' => 'error', 'message' => 'Unknown action.'], 400);
?>
