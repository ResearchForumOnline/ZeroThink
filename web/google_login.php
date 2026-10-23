<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/google_config.php';

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    error_log('ZeroThink Google OAuth is not configured.');
    header('Location: /login?error=google_unavailable');
    exit;
}

$return_to = sanitize_local_return_path($_GET['return'] ?? ($_SESSION['oauth_return_to'] ?? ''), '/studio');
$_SESSION['oauth_return_to'] = $return_to;
$state = bin2hex(random_bytes(32));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_state_issued_at'] = time();

// GENERATE THE OFFICIAL LOGIN URL
$params = [
    'response_type' => 'code',
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URL,
    'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => $state,
];

$url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
header('Location: ' . $url);
exit;
?>
