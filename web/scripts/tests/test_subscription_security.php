<?php

$root = dirname(__DIR__, 2);
$GLOBALS['zt_subscription_test_private_config'] = [
    'security' => ['master_key_b64' => base64_encode(str_repeat('T', 32))],
    'token_gate' => [
        'subscription_prefix' => 'zt-token:',
        'access_ttl_seconds' => 86400,
    ],
];
function zerothink_private_config(): array
{
    return $GLOBALS['zt_subscription_test_private_config'];
}
require_once $root . '/app/core/subscription_security.php';

$passed = 0;
$failed = 0;
function sub_assert(bool $condition, string $message): void
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

$required = [
    'activate_pro_subscription',
    'cancel_pro_subscription',
    'paypal_manage_url',
    'zt_paypal_receiver_email',
    'token_gate_is_subscription_id',
    'token_gate_subscription_id',
    'zerothink_token_gate_access_ttl_seconds',
    'zerothink_token_gate_subscription_prefix',
];
foreach ($required as $function) {
    sub_assert(function_exists($function), "required helper {$function} is declared");
}

$binding = zt_paypal_custom_id(42, time() - 30);
sub_assert($binding !== '' && zt_paypal_custom_id_matches($binding, 42), 'signed PayPal custom_id binds to the intended account');
sub_assert(zt_paypal_custom_id_user_id($binding) === 42, 'signed PayPal custom_id resolves only its authenticated account');
sub_assert(!zt_paypal_custom_id_matches($binding, 43), 'signed PayPal custom_id rejects a different account');
$expired = zt_paypal_custom_id(42, time() - 200000);
sub_assert(!zt_paypal_custom_id_matches($expired, 42), 'stale PayPal account bindings expire');
sub_assert(zt_paypal_valid_subscription_id('I-ABC123DEF456'), 'PayPal subscription ID allowlist accepts canonical IDs');
sub_assert(!zt_paypal_valid_subscription_id('../I-ABC123'), 'PayPal subscription ID allowlist rejects path input');

sub_assert(
    (activate_pro_subscription(42, 'I-ABC123DEF456', 'unknown', ['verify' => true])['error'] ?? '') === 'invalid_plan',
    'activation rejects an unknown plan before storage or network access'
);
sub_assert(
    (activate_pro_subscription(42, 'not-a-subscription', 'monthly', ['verify' => true])['error'] ?? '') === 'invalid_subscription_id',
    'activation rejects a malformed subscription before storage or network access'
);
sub_assert(
    (activate_pro_subscription(42, 'I-ABC123DEF456', 'monthly', ['verify' => false])['error'] ?? '') === 'verification_failed',
    'activation can never grant access when server verification is disabled'
);

$wallet = '11111111111111111111111111111111';
$tokenSubscription = token_gate_subscription_id($wallet);
sub_assert($tokenSubscription === 'zt-token:' . $wallet, 'token-gate subscription IDs use the configured namespace');
sub_assert(token_gate_is_subscription_id($tokenSubscription), 'token-gate subscription IDs are recognized');
sub_assert(!token_gate_is_subscription_id('I-ABC123DEF456'), 'PayPal IDs are not mistaken for token-gate access');

$GLOBALS['zt_subscription_test_private_config']['token_gate']['access_ttl_seconds'] = 5;
sub_assert(zerothink_token_gate_access_ttl_seconds() === 900, 'token-gate access TTL has a safe lower bound');
$GLOBALS['zt_subscription_test_private_config']['token_gate']['access_ttl_seconds'] = 99999999;
sub_assert(zerothink_token_gate_access_ttl_seconds() === 604800, 'token-gate access TTL has a safe upper bound');
sub_assert(strpos(paypal_manage_url(), 'https://www.paypal.com/') === 0, 'PayPal management link is fixed to HTTPS PayPal');
sub_assert(!zt_paypal_server_verification_ready(), 'payment buttons fail closed without a configured receiver and server credentials');

$ipn = (string) file_get_contents($root . '/app/core/ipn_paypal.php');
sub_assert(strpos($ipn, 'PAYPAL_RECEIVER_EMAIL') !== false, 'legacy IPN requires the configured merchant receiver');
sub_assert(strpos($ipn, 'zt_paypal_ipn_allowed_amount') !== false, 'legacy IPN checks currency and an allowed plan amount');
sub_assert(strpos($ipn, "'web_accept'") === false, 'unrelated one-time PayPal payments cannot grant subscriptions');

$cron = (string) file_get_contents($root . '/cron_token_gate_refresh.php');
$bootstrap = (string) file_get_contents($root . '/app/bootstrap.php');
sub_assert(strpos($cron, 'zerothink_token_gate_subscription_prefix()') !== false, 'cron uses the declared token-gate namespace helper');
sub_assert(strpos($bootstrap, "core/subscription_security.php") !== false, 'bootstrap loads entitlement helpers for web and cron paths');
$htaccess = (string) file_get_contents($root . '/.htaccess');
$entrypoint = (string) file_get_contents($root . '/paypal_ipn.php');
sub_assert(strpos($htaccess, '^ipn/paypal/?$ paypal_ipn.php') !== false, 'canonical PayPal IPN route reaches the verified handler');
sub_assert(strpos($entrypoint, "REQUEST_METHOD'] ?? 'GET') !== 'POST'") !== false, 'PayPal IPN entrypoint accepts POST only');
sub_assert(strpos($entrypoint, 'ipn_paypal_handle()') !== false, 'PayPal IPN entrypoint invokes the verified handler');

echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
