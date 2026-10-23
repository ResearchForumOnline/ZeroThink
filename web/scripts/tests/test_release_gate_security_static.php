<?php

$root = dirname(__DIR__, 2);
$passed = 0;
$failed = 0;
function gate_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL: {$message}\n";
}

$google = (string) file_get_contents($root . '/google_callback.php');
gate_assert(strpos($google, "!empty(\$user['disabled'])") !== false, 'Google callback rejects an existing disabled account');
gate_assert(strpos($google, 'email_verified = 1') !== false, 'Google callback records provider-verified email state');

$media = (string) file_get_contents($root . '/api_media.php');
gate_assert(strpos($media, 'request_user_record(true)') !== false, 'Media refreshes an active authenticated user');
gate_assert(strpos($media, "\$user['id'] ?? (\$_SESSION['user_id']") === false, 'Media has no raw-session account fallback');

$tokenGate = (string) file_get_contents($root . '/api_token_gate.php');
gate_assert(strpos($tokenGate, 'current_user_record(true)') !== false, 'Token gate rejects stale or disabled sessions');

$apache = (string) file_get_contents($root . '/.htaccess');
$nginx = (string) file_get_contents($root . '/ops/zerothink-private-boundary.conf');
$downloads = (string) file_get_contents($root . '/ops/zerothink-quantumzero-downloads.conf');
$nginxHttp = (string) file_get_contents($root . '/ops/live-config/zerothink.talktoai.org.conf');
$nginxTls = (string) file_get_contents($root . '/ops/live-config/zerothink.talktoai.org.ssl.conf');
$canonicalHttp = explode("\nserver {", str_replace("\r\n", "\n", $nginxHttp), 2)[0];
$canonicalTls = explode("\nserver {", str_replace("\r\n", "\n", $nginxTls), 2)[0];
foreach (['ops', 'tools', 'agent', 'chat'] as $blocked) {
    gate_assert(stripos($apache, $blocked) !== false, "Apache boundary covers {$blocked}");
    gate_assert(stripos($nginx, $blocked) !== false, "Nginx boundary covers {$blocked}");
}
foreach (['README\\.md', 'install\\.ps1', 'install\\.sh', 'quantumzero_agent\\.py'] as $download) {
    gate_assert(strpos($apache, $download) !== false, "Apache retains reviewed QuantumZero download {$download}");
    gate_assert(strpos($downloads, str_replace('\\', '', $download)) !== false, "Nginx TLS snippet retains reviewed QuantumZero download {$download}");
}
gate_assert(substr_count($downloads, 'location = /quantumzero-python/') >= 6, 'Nginx uses exact static locations for every reviewed QuantumZero download');
gate_assert(strpos($downloads, 'Content-Disposition') !== false && strpos($downloads, 'default_type text/plain') !== false, 'Python agent is a text attachment, not an executable proxy target');
gate_assert(strpos($apache, 'SetHandler None') !== false && strpos($apache, 'ForceType text/plain') !== false, 'direct Apache access cannot execute companion scripts');
gate_assert(strpos($canonicalHttp, 'return 308 https://zerothink.talktoai.org$request_uri;') !== false, 'canonical HTTP vhost redirects every application request to the fixed HTTPS origin');
gate_assert(strpos($canonicalHttp, 'zerothink-private-boundary.conf') === false && strpos($canonicalHttp, 'zerothink-quantumzero-downloads.conf') === false, 'canonical HTTP vhost cannot serve application files or download snippets');
gate_assert(strpos($canonicalHttp, 'proxy_pass') === false && strpos($canonicalHttp, 'root /home/talkto/zerothink.talktoai.org') === false, 'canonical HTTP vhost has no application backend or static document root');
gate_assert(strpos($nginxHttp, 'proxy_pass') === false && strpos($nginxHttp, 'https://$host') === false, 'every HTTP vhost redirects without proxying or reflecting an untrusted Host header');
foreach (['zerothink.talktoai.org', 'webmail.zerothink.talktoai.org', 'mail.zerothink.talktoai.org', 'cpanel.zerothink.talktoai.org'] as $httpsHost) {
    gate_assert(strpos($nginxHttp, "return 308 https://{$httpsHost}\$request_uri;") !== false, "HTTP vhost redirects to fixed TLS host {$httpsHost}");
}
gate_assert(strpos($canonicalTls, 'include /etc/nginx/snippets/zerothink-private-boundary.conf;') !== false, 'canonical TLS vhost includes the private boundary');
gate_assert(strpos($canonicalTls, 'include /etc/nginx/snippets/zerothink-quantumzero-downloads.conf;') !== false, 'canonical TLS vhost includes the reviewed download allowlist');

$bundle = (string) file_get_contents($root . '/ops/build_safe_bundle.ps1');
foreach (['*.tar.gz', '*.sqlite', '*.sqlite3', '*.db', '*.pem', '*.key', '*.p12', '*.pfx', '*.jks', '*.keystore', '*.pyc', '*.pyo', '*/__pycache__/*', 'app/data/pdf_artifacts/*', 'storage/research_templates/*', 'storage/zero-library/*', '*/.env*', 'ops/*', 'tools/*', 'zerothink-nginx*.conf'] as $excluded) {
    gate_assert(strpos($bundle, "'{$excluded}'") !== false, "safe bundle excludes {$excluded}");
}
gate_assert(strpos($bundle, 'Test-SecretBearingFile') !== false, 'safe bundle scans included text for high-confidence secrets');
gate_assert(strpos($bundle, '$isVendorDocumentation') !== false && strpos($bundle, '-not $isVendorDocumentation -and $content -match $privateKeyPattern') !== false, 'safe bundle limits its vendor-documentation exception to the full private-key signal');
gate_assert(strpos($bundle, 'foreach ($pattern in $credentialPatterns)') !== false, 'safe bundle scans every runtime tree for credential and provider-token patterns');
gate_assert(strpos($bundle, 'gh[pousr]_') !== false && strpos($bundle, 'AIza') !== false && strpos($bundle, 'gsk_') !== false, 'safe bundle scans for provider credential formats');
foreach (['.html', '.htm', '.css', '.cmd', '.bat', '.service', '.timer', '.htaccess', 'extensionlessText'] as $textKind) {
    gate_assert(strpos($bundle, $textKind) !== false, "safe bundle secret scanner covers {$textKind}");
}
$bundleBehaviorTest = (string) file_get_contents($root . '/scripts/tests/test_safe_bundle_builder.ps1');
gate_assert(strpos($bundleBehaviorTest, 'vendor/runtime/config.php') !== false, 'safe bundle behavior fixture covers a modified vendor runtime file');
gate_assert(strpos($bundleBehaviorTest, '__pycache__') !== false && strpos($bundleBehaviorTest, 'extensionless script') !== false, 'safe bundle behavior fixture covers bytecode exclusion and extensionless text');
foreach (['db', 'pem', 'key', 'p12', 'pfx', 'jks', 'keystore'] as $extension) {
    gate_assert(strpos($apache, $extension) !== false, "Apache blocks .{$extension} files");
    gate_assert(strpos($nginx, $extension) !== false, "Nginx blocks .{$extension} files");
}

$apacheLog = (string) file_get_contents($root . '/ops/live-config/apache-zerothink-privacy-log.conf');
gate_assert(strpos($apacheLog, '%U') !== false && strpos($apacheLog, '%q') === false, 'backend access log omits query strings');
gate_assert(strpos($apacheLog, '%{Referer}i') === false, 'backend access log omits referrers');

$csfPost = (string) file_get_contents($root . '/ops/csfpost-zerothink-backends.sh');
gate_assert(strpos($csfPost, '! -i lo') !== false && strpos($csfPost, '8181,8443') !== false, 'firewall post-hook blocks both Apache backend ports only off loopback');
gate_assert(strpos($csfPost, 'IPTABLES=/usr/sbin/iptables') !== false && strpos($csfPost, 'IP6TABLES=/usr/sbin/ip6tables') !== false, 'firewall post-hook covers IPv4 and IPv6');

$upgrade = (string) file_get_contents($root . '/upgrade.php');
gate_assert(strpos($upgrade, 'zt_paypal_server_verification_ready') !== false, 'upgrade buttons require server-side PayPal readiness');
gate_assert(strpos($upgrade, 'custom_id: accountBinding') !== false, 'new PayPal subscriptions carry signed account binding');

echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
