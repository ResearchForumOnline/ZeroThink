<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/google_config.php';

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    error_log('ZeroThink Google OAuth callback is not configured.');
    header('Location: /login?error=google_unavailable');
    exit;
}

$return_to = sanitize_local_return_path($_SESSION['oauth_return_to'] ?? '', '/studio');
$expected_state = (string) ($_SESSION['oauth_state'] ?? '');
$state_issued_at = (int) ($_SESSION['oauth_state_issued_at'] ?? 0);
unset($_SESSION['oauth_return_to'], $_SESSION['oauth_state'], $_SESSION['oauth_state_issued_at']);

$received_state = trim((string) ($_GET['state'] ?? ''));
$state_valid = $expected_state !== ''
    && $received_state !== ''
    && hash_equals($expected_state, $received_state)
    && $state_issued_at > 0
    && (time() - $state_issued_at) <= 600;

if (!$state_valid || empty($_GET['code']) || !empty($_GET['error'])) {
    error_log('ZeroThink Google OAuth rejected an invalid or expired callback.');
    header('Location: /login?error=google_failed');
    exit;
}

function zt_google_json_request($url, $options) {
    $ch = curl_init($url);
    curl_setopt_array($ch, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $status < 200 || $status >= 300) {
        error_log('ZeroThink Google OAuth provider request failed: HTTP ' . $status . ($error !== '' ? ' transport error' : ''));
        return null;
    }
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : null;
}

$token = zt_google_json_request('https://oauth2.googleapis.com/token', [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => (string) $_GET['code'],
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URL,
        'grant_type' => 'authorization_code',
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);

$access_token = is_array($token) ? trim((string) ($token['access_token'] ?? '')) : '';
if ($access_token === '') {
    header('Location: /login?error=google_failed');
    exit;
}

$profile = zt_google_json_request('https://www.googleapis.com/oauth2/v2/userinfo', [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access_token, 'Accept: application/json'],
]);
$email = is_array($profile) ? filter_var((string) ($profile['email'] ?? ''), FILTER_VALIDATE_EMAIL) : false;
$verified = is_array($profile) && !empty($profile['verified_email']);

if (!$email || !$verified) {
    error_log('ZeroThink Google OAuth profile did not contain a verified email.');
    header('Location: /login?error=google_failed');
    exit;
}

try {
    $db = db();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && !empty($user['disabled'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        error_log('ZeroThink Google OAuth rejected a disabled account.');
        header('Location: /login?error=account_disabled');
        exit;
    }
    if (!$user) {
        $stmt = $db->prepare("INSERT INTO users (email, role, plan, email_verified, google_sub, last_login_at, created_at) VALUES (?, 'user', 'free', 1, ?, NOW(), NOW())");
        $stmt->execute([$email, trim((string) ($profile['id'] ?? '')) ?: null]);
        $user = [
            'id' => (int) $db->lastInsertId(),
            'email' => $email,
            'role' => 'user',
            'plan' => 'free',
            'email_verified' => 1,
        ];
    } else {
        $update = $db->prepare("UPDATE users
            SET email_verified = 1,
                google_sub = COALESCE(NULLIF(google_sub, ''), ?),
                last_login_at = NOW()
            WHERE id = ? AND COALESCE(disabled, 0) = 0
            LIMIT 1");
        $update->execute([trim((string) ($profile['id'] ?? '')) ?: null, (int) $user['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['email'] = (string) ($user['email'] ?? $email);
    $_SESSION['role'] = (string) ($user['role'] ?? 'user');
    $_SESSION['plan'] = (string) ($user['plan'] ?? 'free');
    $_SESSION['is_pro'] = (int) ($user['is_pro'] ?? 0);
    $_SESSION['avatar'] = (string) ($profile['picture'] ?? ($user['avatar'] ?? ''));
    header('Location: ' . $return_to);
    exit;
} catch (Throwable $e) {
    error_log('ZeroThink Google OAuth account lookup failed.');
}

header('Location: /login?error=google_failed');
exit;
