<?php
declare(strict_types=1);

define('QZ_LIBRARY_ONLY', true);
$GLOBALS['qz_test_site_settings'] = [
    'service_api_key_quantum' => 'service-test-key-not-real',
];
function site_setting($name, $default = '')
{
    return $GLOBALS['qz_test_site_settings'][$name] ?? $default;
}
$root = dirname(__DIR__, 2);
require_once $root . '/api_quantum_zero.php';

$api = (string) file_get_contents($root . '/api_quantum_zero.php');
$lab = (string) file_get_contents($root . '/quantum_zero.php');
$chat = (string) file_get_contents($root . '/quantum_zero_chat.php');
$checks = 0;
$failures = [];

function qz_test_assert(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

$_SESSION = [];

$_SESSION['api_key_quantum'] = 'user-test-key-not-real';
$userCredential = qz_resolve_credential(17, 'api_key_quantum', '', ['is_pro' => 1], true);
qz_test_assert(
    ($userCredential['key'] ?? '') === 'user-test-key-not-real'
        && ($userCredential['source'] ?? '') === 'user_session',
    'A user credential must win over a shared service credential.'
);

unset($_SESSION['api_key_quantum']);
$deniedService = qz_resolve_credential(17, 'api_key_quantum', '', ['plan' => 'free'], true);
qz_test_assert(($deniedService['key'] ?? '') === '', 'A free account must not receive the shared service credential.');

$allowedService = qz_resolve_credential(17, 'api_key_quantum', '', ['plan' => 'pro'], true);
qz_test_assert(
    ($allowedService['key'] ?? '') === 'service-test-key-not-real'
        && ($allowedService['source'] ?? '') === 'service_setting'
        && qz_credential_is_shared($allowedService),
    'An explicitly entitled Pro account may use the shared fallback.'
);

$scopeA = qz_credential_scope_hash('ionq', ['key' => 'scope-a']);
$scopeA2 = qz_credential_scope_hash('ionq', ['key' => 'scope-a']);
$scopeB = qz_credential_scope_hash('ionq', ['key' => 'scope-b']);
qz_test_assert(
    strlen($scopeA) === 64 && hash_equals($scopeA, $scopeA2) && !hash_equals($scopeA, $scopeB),
    'Credential scope hashes must be stable, fixed length, and credential-specific.'
);

$snapshot = qz_public_job_snapshot([
    'id' => '12345678-1234-1234-1234-123456789abc',
    'status' => 'completed',
    'backend' => 'simulator',
    'metadata' => ['private' => 'must-not-persist'],
    'api_key' => 'must-not-persist',
]);
qz_test_assert(
    ($snapshot['status'] ?? '') === 'completed'
        && !isset($snapshot['metadata'])
        && !isset($snapshot['api_key']),
    'Durable job snapshots must retain operational status but drop provider metadata and secrets.'
);

qz_test_assert(
    strlen(qz_state_label('1', PHP_INT_MAX)) === 64,
    'Client-supplied qubit widths must be bounded before padding.'
);

qz_test_assert(
    strpos($api, 'CREATE TABLE IF NOT EXISTS quantum_zero_job_ownership') !== false
        && strpos($api, 'PRIMARY KEY (provider, job_id)') !== false
        && strpos($api, 'credential_scope_hash CHAR(64) NOT NULL') !== false
        && strpos($api, 'user_id BIGINT UNSIGNED NOT NULL') !== false,
    'The durable ledger must uniquely map provider/job IDs to owner and credential scope.'
);

$creationMap = strpos($api, "qz_record_job_owner(\$uid, 'ionq', \$job_id, \$credential");
$creationPoll = strpos($api, '$waited = qz_wait_for_job($uid, $credential, $job_id');
qz_test_assert(
    $creationMap !== false && $creationPoll !== false && $creationMap < $creationPoll,
    'A newly created job must be owner-mapped before polling status or results.'
);

qz_test_assert(
    strpos($api, "qz_assert_job_owner(\$uid, 'ionq', \$job_id, \$ionq_credential);") !== false
        && substr_count($api, "qz_assert_job_owner(\$uid, 'ionq', \$job_id, \$ionq_credential);") >= 2,
    'Direct job and results actions must enforce the owner ledger before cache or provider reads.'
);

qz_test_assert(
    strpos($api, "qz_job_bundle(\$uid, \$ionq_credential, \$job_id)") !== false
        && substr_count($api, "qz_job_bundle(\$uid, \$ionq_credential, \$job_id)") >= 2,
    'Explanation and evidence-download paths must use the owner-enforcing bundle loader.'
);

qz_test_assert(
    strpos($api, '/jobs?limit=') === false
        && strpos($api, 'qz_owned_recent_jobs($uid, $ionq_credential') !== false
        && strpos($api, "'owned_only' => true") !== false,
    'Recent and diagnostic listings must originate only from the user-owned ledger.'
);

qz_test_assert(
    strpos($api, 'qz_assert_shared_job_budget($uid, $credential, $unlimited_usage)') !== false
        && strpos($api, "'ionq_submit_any' => 5") !== false
        && strpos($api, 'strlen($raw_input) > 32768') !== false,
    'Shared job creation, submission frequency, and request size must be bounded.'
);

qz_test_assert(
    strpos($api, "strtoupper((string) (\$_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST'") !== false
        && strpos($api, 'zt_csrf_validate($csrf_token)') !== false
        && strpos($api, "strpos(\$content_type, 'application/json') !== 0") !== false,
    'Quantum Zero requests must require POST JSON and the shared CSRF verifier.'
);

qz_test_assert(
    substr_count($api, "'message' => \$e->getMessage()") === 1
        && strpos($api, 'if ($e instanceof QzClientException)') !== false
        && strpos($api, 'Quantum Zero could not complete that request. Reference:') !== false,
    'Unexpected provider and database errors must stay behind a generic request reference.'
);

foreach (['Quantum Lab' => $lab, 'QuantumZero Chat' => $chat] as $label => $source) {
    qz_test_assert(
        strpos($source, 'const qzCsrfToken = <?= json_encode(') !== false
            && strpos($source, "'X-CSRF-Token':qzCsrfToken") !== false,
        $label . ' must send the session CSRF token to the API.'
    );
}

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . ' of ' . $checks . " Quantum Zero security checks failed\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo 'OK: ' . $checks . " Quantum Zero security checks passed\n";
