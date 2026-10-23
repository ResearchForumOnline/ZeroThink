<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$endpoint = (string) file_get_contents($root . '/api_vault.php');
$studio = (string) file_get_contents($root . '/studio.php');
$oracle = (string) file_get_contents($root . '/oracle.php');

$checks = 0;
$failures = [];

function zt_vault_assert(bool $condition, string $message): void {
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

zt_vault_assert(
    strpos($endpoint, "REQUEST_METHOD") !== false
        && strpos($endpoint, "header('Allow: POST')") !== false
        && strpos($endpoint, "'POST required.'") !== false,
    'Vault endpoint must reject non-POST requests.'
);
zt_vault_assert(
    strpos($endpoint, "application/json") !== false
        && strpos($endpoint, "'JSON request required.'") !== false,
    'Vault endpoint must require JSON requests.'
);
zt_vault_assert(
    strpos($endpoint, "['load', 'save', 'wipe']") !== false,
    'Vault endpoint must use an explicit action allowlist.'
);
zt_vault_assert(
    strpos($endpoint, 'zt_csrf_validate($csrf_token)') !== false
        && strpos($endpoint, "HTTP_X_CSRF_TOKEN") !== false,
    'Every allowed vault action must pass the shared CSRF validator.'
);
zt_vault_assert(
    strpos($endpoint, 'catch (Throwable $e)') !== false
        && strpos($endpoint, "'request_id' => \$request_id") !== false,
    'Vault failures must be caught and assigned a support request id.'
);
zt_vault_assert(
    !preg_match('/[\'\"]message[\'\"]\s*=>\s*\$e->getMessage\s*\(/', $endpoint),
    'Raw exception messages must never be returned by the vault API.'
);
zt_vault_assert(
    strpos($endpoint, 'if (!$canEncrypt)') !== false
        && strpos($endpoint, 'No provider secrets were changed.') !== false,
    'Vault save must fail closed instead of storing a non-empty provider secret as plaintext.'
);

zt_vault_assert(
    strpos($studio, 'const ZT_SESSION_CSRF_TOKEN = <?= json_encode(') !== false
        && strpos($studio, 'const ZT_VAULT_CSRF_TOKEN = ZT_SESSION_CSRF_TOKEN;') !== false
        && strpos($studio, "'X-CSRF-Token': ZT_VAULT_CSRF_TOKEN") !== false
        && strpos($studio, "'Content-Type': 'application/json'") !== false,
    'Studio must emit and send its same-session CSRF token as JSON.'
);
foreach (['load', 'save', 'wipe'] as $action) {
    zt_vault_assert(
        preg_match('/ztVaultRequest\s*\(\s*\{?[^\r\n]*action\s*:\s*[\'\"]' . preg_quote($action, '/') . '[\'\"]/', $studio) === 1
            || ($action === 'save' && strpos($studio, 'await ztVaultRequest(payload)') !== false),
        'Studio must route the ' . $action . ' action through the CSRF-aware vault helper.'
    );
}

zt_vault_assert(
    strpos($oracle, 'const ZT_VAULT_CSRF_TOKEN = <?= json_encode(') !== false
        && strpos($oracle, "'X-CSRF-Token': ZT_VAULT_CSRF_TOKEN") !== false
        && strpos($oracle, "csrf_token: ZT_VAULT_CSRF_TOKEN") !== false,
    'Oracle vault status must remain compatible with the CSRF-protected load action.'
);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . ' of ' . $checks . " vault security checks failed\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo 'OK: ' . $checks . " vault CSRF and error-boundary checks passed\n";
