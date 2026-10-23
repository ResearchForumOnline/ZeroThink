<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'app/google_config.php',
    'app/core/google_auth.php',
    'app/controllers/auth_google_callback.php',
    'google_login.php',
    'google_callback.php',
    'auth.php',
    'app/core/auth.php',
];
$checks = 0;
$failures = [];
function zt_oauth_assert(bool $condition, string $message): void {
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

foreach ($files as $relative) {
    $content = (string) file_get_contents($root . '/' . $relative);
    zt_oauth_assert(
        preg_match('/GOCSPX-[A-Za-z0-9_-]{10,}/', $content) !== 1,
        $relative . ' must not contain a Google client secret literal.'
    );
}

$config = (string) file_get_contents($root . '/app/google_config.php');
zt_oauth_assert(strpos($config, "getenv(\$name)") !== false, 'OAuth config must prefer process environment values.');
zt_oauth_assert(strpos($config, "dirname(__DIR__) . '/.env'") !== false, 'OAuth config must support the protected root .env fallback.');

$callback = (string) file_get_contents($root . '/google_callback.php');
zt_oauth_assert(strpos($callback, "verified_email") !== false, 'OAuth callback must require Google verified_email.');
zt_oauth_assert(strpos($callback, 'session_regenerate_id(true)') !== false, 'OAuth callback must regenerate the session id after login.');
zt_oauth_assert(strpos($callback, 'CURLOPT_SSL_VERIFYPEER => true') !== false, 'OAuth provider requests must verify TLS peers.');

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . ' of ' . $checks . " OAuth checks failed\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo 'OK: ' . $checks . " OAuth configuration checks passed\n";
