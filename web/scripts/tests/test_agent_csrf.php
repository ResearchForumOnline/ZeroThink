<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$agent = (string) file_get_contents($root . '/api_agent.php');
$studio = (string) file_get_contents($root . '/studio.php');
$appJs = (string) file_get_contents($root . '/assets/js/app.js');
$legacyStudio = (string) file_get_contents($root . '/app/views/studio.php');
$dna = (string) file_get_contents($root . '/dna.php');
$pdf = (string) file_get_contents($root . '/pdf-creator.php');
$paper = (string) file_get_contents($root . '/research-paper-creator.php');
$guest = (string) file_get_contents($root . '/guest.php');
$cli = (string) file_get_contents($root . '/cli-assets/zerothink.py');

$checks = 0;
$failures = [];

function zt_agent_assert(bool $condition, string $message): void {
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

zt_agent_assert(
    strpos($agent, 'if ($session_authenticated && !$allow_direct_mode)') !== false,
    'Session-authenticated non-direct API Agent requests must enter the CSRF gate.'
);
zt_agent_assert(
    strpos($agent, 'zt_csrf_validate($agent_csrf_token)') !== false
        && strpos($agent, 'HTTP_X_CSRF_TOKEN') !== false,
    'API Agent must validate the shared token from the payload or CSRF header.'
);
zt_agent_assert(
    strpos($agent, '$preflight_respond_base64 ? base64_encode') !== false,
    'CSRF failures must retain the existing JSON/base64 response protocol.'
);
zt_agent_assert(
    strpos($agent, '&& $session_authenticated') !== false
        && strpos($agent, '&& !empty($input[\'sensitive_no_history\'])') !== false,
    'The sensitive no-history lane must remain restricted to browser sessions.'
);

zt_agent_assert(
    strpos($studio, 'csrf_token: ZT_SESSION_CSRF_TOKEN') !== false
        && strpos($studio, "'X-CSRF-Token': ZT_SESSION_CSRF_TOKEN") !== false,
    'Primary Studio must send its session CSRF token to API Agent.'
);
zt_agent_assert(
    strpos($appJs, "csrf_token: window.ZT_SESSION_CSRF_TOKEN || ''") !== false
        && strpos($appJs, "'X-CSRF-Token': window.ZT_SESSION_CSRF_TOKEN || ''") !== false,
    'Shared Studio JavaScript must send the page-provided CSRF token.'
);
zt_agent_assert(
    strpos($legacyStudio, 'window.ZT_SESSION_CSRF_TOKEN = <?= json_encode(') !== false,
    'The legacy Studio view must seed the shared JavaScript CSRF token.'
);
zt_agent_assert(
    strpos($dna, 'sensitive_no_history:true, csrf_token:DNA_CSRF_TOKEN') !== false
        && strpos($dna, "'Content-Type':'application/json'") !== false,
    'DNA Agent must retain its private-lane CSRF token and non-simple JSON request.'
);
zt_agent_assert(
    strpos($pdf, 'csrf_token: ZT_SESSION_CSRF_TOKEN') !== false
        && strpos($pdf, "'X-CSRF-Token': ZT_SESSION_CSRF_TOKEN") !== false,
    'PDF Creator must send CSRF with AI draft requests.'
);
zt_agent_assert(
    strpos($paper, 'csrf_token: ZT_SESSION_CSRF_TOKEN') !== false
        && strpos($paper, "'X-CSRF-Token': ZT_SESSION_CSRF_TOKEN") !== false,
    'Research Paper Creator must send CSRF with every agent call.'
);

zt_agent_assert(
    strpos($guest, 'direct_mode: true') !== false,
    'Guest mode must remain an explicit direct-key request.'
);
zt_agent_assert(
    strpos($cli, '"Authorization": f"Bearer {token}"') !== false
        && strpos($cli, 'request_payload["direct_mode"] = True') !== false,
    'CLI bearer and direct-key modes must retain independent request credentials.'
);

require_once $root . '/app/bootstrap.php';
unset($_SESSION['zt_csrf_token']);
$token = zt_csrf_token();
zt_agent_assert(
    preg_match('/^[a-f0-9]{64}$/', $token) === 1,
    'Shared CSRF helper must mint a 256-bit hexadecimal token.'
);
zt_agent_assert(zt_csrf_validate($token), 'Shared CSRF helper must accept the current token.');
zt_agent_assert(!zt_csrf_validate(str_repeat('0', 64)), 'Shared CSRF helper must reject a different well-formed token.');
zt_agent_assert(!zt_csrf_validate(''), 'Shared CSRF helper must reject an empty token.');

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . ' of ' . $checks . " API Agent CSRF checks failed\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo 'OK: ' . $checks . " API Agent CSRF checks passed\n";
