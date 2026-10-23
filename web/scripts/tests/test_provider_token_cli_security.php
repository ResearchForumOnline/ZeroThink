<?php

$root = dirname(__DIR__, 2);
$failures = [];
$passes = 0;

function security_test_assert($condition, $message) {
    global $failures, $passes;
    if ($condition) {
        $passes++;
        echo "PASS: {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "FAIL: {$message}\n";
}

function security_test_source($path) {
    $source = file_get_contents($path);
    if (!is_string($source)) {
        throw new RuntimeException('Could not read ' . $path);
    }
    return $source;
}

function security_test_function_block($source, $function_name, $next_function_name) {
    $start = strpos($source, 'function ' . $function_name . '(');
    $end = strpos($source, 'function ' . $next_function_name . '(', $start + 1);
    if ($start === false || $end === false || $end <= $start) {
        return '';
    }
    return substr($source, $start, $end - $start);
}

require_once $root . '/app/core/media_security.php';
require_once $root . '/app/core/token_gate_market.php';

$old_seedance_base = getenv('SEEDANCE_API_BASE');
try {
    putenv('SEEDANCE_API_BASE');
    security_test_assert(
        zt_media_seedance_tasks_url([]) === 'https://ark.ap-southeast.bytepluses.com/api/v3/contents/generations/tasks',
        'SeedDance uses the exact server-owned default origin.'
    );

    $rejected_request_origin = false;
    try {
        zt_media_seedance_tasks_url(['seedance_base' => 'https://attacker.example/api/v3']);
    } catch (InvalidArgumentException $e) {
        $rejected_request_origin = true;
    }
    security_test_assert($rejected_request_origin, 'A request cannot choose the SeedDance credential destination.');

    putenv('SEEDANCE_API_BASE=https://attacker.example/api/v3');
    $rejected_server_misconfiguration = false;
    try {
        zt_media_seedance_tasks_url([]);
    } catch (RuntimeException $e) {
        $rejected_server_misconfiguration = true;
    }
    security_test_assert($rejected_server_misconfiguration, 'A non-allowlisted server SeedDance origin fails closed.');

    putenv('SEEDANCE_API_BASE=https://ark.ap-southeast.bytepluses.com/api/v3');
    security_test_assert(
        zt_media_seedance_task_url([], 'task/with spaces') === 'https://ark.ap-southeast.bytepluses.com/api/v3/contents/generations/tasks/task%2Fwith%20spaces',
        'SeedDance task identifiers remain path-segment encoded.'
    );
} finally {
    if ($old_seedance_base === false) {
        putenv('SEEDANCE_API_BASE');
    } else {
        putenv('SEEDANCE_API_BASE=' . $old_seedance_base);
    }
}

$media = security_test_source($root . '/api_media.php');
security_test_assert(strpos($media, "require_once __DIR__ . '/app/core/media_security.php';") !== false, 'Media API loads the provider-origin boundary.');
security_test_assert(strpos($media, "zm_seedance_key(\$uid, \$input, true)") === false, 'SeedDance service fallback is never unconditional.');
security_test_assert(substr_count($media, 'zt_media_seedance_task_url($input, $id)') === 2, 'Both SeedDance status and download use the guarded task URL.');
security_test_assert(strpos($media, 'CREATE TABLE IF NOT EXISTS media_video_jobs') !== false, 'Video jobs have a durable local ownership ledger.');
security_test_assert(substr_count($media, 'zm_video_job_record($uid,') - 1 === 3, 'Every video provider records ownership at submission.');
security_test_assert(substr_count($media, 'zm_video_job_require_owner($uid,') - 1 === 5, 'Every shared-service status and download path enforces ownership.');
security_test_assert(strpos($media, "\$request_method !== 'POST'") !== false, 'Media JSON actions reject non-POST requests.');
security_test_assert(strpos($media, "^application/json") !== false, 'Media JSON actions require the JSON content type.');
security_test_assert(strpos($media, "file_get_contents('php://input', false, null, 0, \$max_body_bytes + 1)") !== false, 'Media reads at most one byte beyond its configured body cap.');
security_test_assert(strpos($media, "HTTP_X_ZEROTHINK_CSRF") !== false && strpos($media, 'zt_csrf_validate($csrf)') !== false, 'Session media requests validate the shared CSRF token.');
security_test_assert(strpos($media, 'CREATE TABLE IF NOT EXISTS media_usage_events') !== false, 'Media usage budgets have a durable event ledger.');
security_test_assert(strpos($media, 'SELECT GET_LOCK(?, 2)') !== false, 'Concurrent per-user media budget checks are serialized.');
security_test_assert(strpos($media, 'ZEROTHINK_MEDIA_RATE_') !== false, 'Per-action media rate limits are server configurable.');
security_test_assert(strpos($media, "'ZEROTHINK_MEDIA_' . \$lane_suffix . '_' . \$action_suffix . '_DAILY'") !== false, 'Daily generation limits are configurable by credential lane and action.');
security_test_assert(strpos($media, "'service' => ['speech' => 100, 'image' => 30, 'video' => 5]") !== false, 'Service-funded generations use conservative daily defaults.');
security_test_assert(strpos($media, "'user' => ['speech' => 1000, 'image' => 500, 'video' => 100]") !== false, 'User-owned keys retain a separate high safety ceiling.');
security_test_assert(strpos($media, "'message' => \$e->getMessage()" ) !== false, 'Safe client validation errors remain actionable.');
security_test_assert(strpos($media, "Media request could not be completed. Reference ID:") !== false, 'Unexpected provider and internal failures return an opaque request ID.');
security_test_assert(strpos($media, "zm_reply(['status' => 'error', 'message' => \$e->getMessage()], 400)") === false, 'Unexpected media failures never return raw provider error text.');

$media_page = security_test_source($root . '/media.php');
security_test_assert(strpos($media_page, '$media_csrf_token = zt_csrf_token();') !== false, 'Media Studio mints the shared CSRF token.');
security_test_assert(strpos($media_page, "'X-ZeroThink-CSRF':MEDIA_CSRF") !== false, 'Every Media Studio JSON call sends the shared CSRF header.');
security_test_assert(strpos($media_page, "method:'POST'") !== false && strpos($media_page, "'Content-Type':'application/json'") !== false, 'Media Studio keeps generation and status calls on POST JSON.');
security_test_assert(strpos($media_page, "action=video_download") !== false && strpos($media_page, 'window.open(url') !== false, 'Media Studio preserves GET download navigation for binary responses.');

$mint = 'TestMint111111111111111111111111111111111';
$selected_pair = zt_token_gate_select_liquid_pair([
    [
        'chainId' => 'solana',
        'baseToken' => ['address' => $mint],
        'priceUsd' => '99',
        'liquidity' => ['usd' => 25],
        'volume' => ['h24' => 9000000],
        'pairAddress' => 'manipulated-low-liquidity',
    ],
    [
        'chainId' => 'solana',
        'baseToken' => ['address' => $mint],
        'priceUsd' => '1',
        'liquidity' => ['usd' => 50000],
        'volume' => ['h24' => 100],
        'pairAddress' => 'liquid-reference',
    ],
], $mint);
security_test_assert(($selected_pair['pair'] ?? '') === 'liquid-reference', 'High volume cannot outrank greater actual liquidity.');

$token_gate = security_test_source($root . '/api_token_gate.php');
security_test_assert(strpos($token_gate, 'PRIMARY KEY (wallet, token_mint)') !== false, 'Wallet claims are globally unique per token mint.');
security_test_assert(strpos($token_gate, 'function tg_claim_wallet_for_user(PDO $db') !== false, 'Token gate has an authoritative wallet-claim operation.');
security_test_assert(strpos($token_gate, 'FOR UPDATE') !== false, 'Wallet claim and entitlement changes take row locks.');
security_test_assert(strpos($token_gate, "AND used_at IS NULL AND expires_at > NOW()") !== false, 'Challenge consumption is conditional and one-time.');
security_test_assert(strpos($token_gate, 'if ($mark->rowCount() !== 1)') !== false, 'Challenge races fail when the conditional consume loses.');
security_test_assert(strpos($token_gate, '$actual_liquidity = (float)') !== false, 'Token eligibility enforces actual liquidity independently of volume.');

$bootstrap = security_test_source($root . '/app/bootstrap.php');
$approve = security_test_function_block($bootstrap, 'cli_approve_device_request', 'cli_deny_device_request');
$consume = security_test_function_block($bootstrap, 'cli_consume_device_request', 'cli_authenticate_token_user');
$authenticate = security_test_function_block($bootstrap, 'cli_authenticate_token_user', 'revoke_cli_token');
security_test_assert(strpos($approve, "status = 'pending' AND user_id IS NULL") !== false, 'CLI approval cannot rebind an approved device code.');
security_test_assert(strpos($consume, 'beginTransaction()') !== false && strpos($consume, 'FOR UPDATE') !== false, 'CLI consume locks the device row inside a transaction.');
security_test_assert(strpos($consume, 'SELECT * FROM users') === false, 'CLI polling never serializes a complete user row.');
security_test_assert(strpos($consume, 'SELECT id, email, role, plan, is_pro') !== false, 'CLI polling uses an explicit public identity projection.');
security_test_assert(strpos($consume, 'if ($consume->rowCount() !== 1)') !== false, 'Concurrent CLI consume attempts cannot both mint a usable token.');
security_test_assert(strpos($authenticate, 'COALESCE(u.disabled, 0) = 0') !== false, 'Disabled users cannot authenticate with old CLI tokens.');
security_test_assert(strpos($bootstrap, 'request_ip_hash CHAR(64)') !== false, 'CLI pairing has a dedicated privacy-preserving rate key.');
security_test_assert(strpos($bootstrap, 'zt_cli_device_start_global') !== false, 'CLI device starts are serialized for global and per-IP limits.');

$connect = security_test_source($root . '/cli_connect.php');
security_test_assert(strpos($connect, "zt_csrf_validate(\$_POST['csrf_token'] ?? '')") !== false, 'CLI approval validates the session CSRF token.');
security_test_assert(substr_count($connect, 'name="csrf_token"') === 2, 'Approve and deny forms both carry the CSRF token.');

$cli_api = security_test_source($root . '/api_cli.php');
security_test_assert(strpos($cli_api, "REQUEST_METHOD'] ?? '')) !== 'POST'") !== false, 'CLI API rejects non-POST requests.');
security_test_assert(strpos($cli_api, "https://zerothink.talktoai.org/cli/connect") !== false, 'CLI verification URL is not derived from an untrusted Host header.');

echo "\n{$passes} passed, " . count($failures) . " failed.\n";
if ($failures) {
    exit(1);
}
