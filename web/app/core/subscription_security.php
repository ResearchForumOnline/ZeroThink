<?php

/**
 * PayPal subscription verification and token-gate entitlement helpers.
 *
 * Every externally supplied PayPal subscription is verified against PayPal's
 * server API before it can grant access. If live API credentials are absent,
 * activation fails closed and the upgrade UI does not render payment buttons.
 */

function zt_subscription_root_env(): array
{
    static $values = null;
    if (is_array($values)) {
        return $values;
    }

    $values = [];
    $root = dirname(__DIR__, 2);
    foreach ([$root . '/.env', $root . '/app/.env'] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $parsed = @parse_ini_file($path, false, INI_SCANNER_RAW);
        if (is_array($parsed)) {
            $values = array_replace($values, $parsed);
        }
    }
    return $values;
}

function zt_subscription_private_value(array $path, $default = '')
{
    try {
        $value = zerothink_private_config();
    } catch (Throwable $e) {
        return $default;
    }
    foreach ($path as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value === null || $value === '' ? $default : $value;
}

function zt_subscription_config(string $envName, array $privatePath = [], string $default = ''): string
{
    $runtime = getenv($envName);
    if (is_string($runtime) && trim($runtime) !== '') {
        return trim($runtime);
    }

    $env = zt_subscription_root_env();
    if (isset($env[$envName]) && trim((string) $env[$envName]) !== '') {
        return trim((string) $env[$envName]);
    }

    if ($privatePath) {
        $private = zt_subscription_private_value($privatePath, '');
        if (trim((string) $private) !== '') {
            return trim((string) $private);
        }
    }
    return $default;
}

function zt_paypal_client_id(): string
{
    // Client IDs are intentionally public. The existing live app ID remains a
    // compatibility fallback; the client secret never has a source fallback.
    return zt_subscription_config(
        'PAYPAL_CLIENT_ID',
        ['paypal', 'client_id'],
        'AVmkjXoWaxtMObfkKJUpnKt1iSK0hwI6e6-CoR_wOY5XcGr5cmz6ArvZ-6csMvefWU3srObyoyHIxYBd'
    );
}

function zt_paypal_client_secret(): string
{
    return zt_subscription_config('PAYPAL_CLIENT_SECRET', ['paypal', 'client_secret']);
}

function zt_paypal_receiver_email(): string
{
    return strtolower(zt_subscription_config('PAYPAL_RECEIVER_EMAIL', ['paypal', 'receiver_email']));
}

function zt_paypal_environment(): string
{
    $environment = strtolower(zt_subscription_config('PAYPAL_ENVIRONMENT', ['paypal', 'environment'], 'live'));
    return $environment === 'sandbox' ? 'sandbox' : 'live';
}

function zt_paypal_api_base(): string
{
    return zt_paypal_environment() === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';
}

function zt_paypal_plan_ids(): array
{
    return [
        'monthly' => zt_subscription_config(
            'PAYPAL_MONTHLY_PLAN_ID',
            ['paypal', 'plans', 'monthly'],
            'P-9VC68144A54091518NFQW2WY'
        ),
        'yearly' => zt_subscription_config(
            'PAYPAL_YEARLY_PLAN_ID',
            ['paypal', 'plans', 'yearly'],
            'P-7438348533122952LNFQW4BQ'
        ),
    ];
}

function zt_paypal_server_verification_ready(): bool
{
    $plans = zt_paypal_plan_ids();
    return zt_paypal_client_id() !== ''
        && zt_paypal_client_secret() !== ''
        && zt_paypal_binding_secret() !== ''
        && filter_var(zt_paypal_receiver_email(), FILTER_VALIDATE_EMAIL) !== false
        && preg_match('/^P-[A-Z0-9]{10,64}$/', (string) ($plans['monthly'] ?? '')) === 1
        && preg_match('/^P-[A-Z0-9]{10,64}$/', (string) ($plans['yearly'] ?? '')) === 1;
}

function zt_paypal_binding_secret(): string
{
    $configured = zt_subscription_config('PAYPAL_BINDING_SECRET', ['paypal', 'binding_secret']);
    if ($configured !== '') {
        return hash('sha256', $configured, true);
    }

    $encoded = (string) zt_subscription_private_value(['security', 'master_key_b64'], '');
    $decoded = $encoded !== '' ? base64_decode($encoded, true) : false;
    return is_string($decoded) && strlen($decoded) >= 32 ? $decoded : '';
}

function zt_paypal_custom_id(int $userId, ?int $issuedAt = null): string
{
    $secret = zt_paypal_binding_secret();
    if ($userId <= 0 || $secret === '') {
        return '';
    }
    $issuedAt = $issuedAt ?? time();
    $payload = 'zt1.' . $userId . '.' . $issuedAt;
    $mac = substr(hash_hmac('sha256', $payload, $secret), 0, 40);
    return $payload . '.' . $mac;
}

function zt_paypal_custom_id_matches(string $customId, int $userId, int $maxAgeSeconds = 172800): bool
{
    if ($userId <= 0 || !preg_match('/^zt1\.(\d{1,12})\.(\d{10})\.([a-f0-9]{40})$/', $customId, $match)) {
        return false;
    }
    $issuedAt = (int) $match[2];
    if ((int) $match[1] !== $userId || $issuedAt > time() + 300 || time() - $issuedAt > $maxAgeSeconds) {
        return false;
    }
    $expected = zt_paypal_custom_id($userId, $issuedAt);
    return $expected !== '' && hash_equals($expected, $customId);
}

function zt_paypal_custom_id_user_id(string $customId): int
{
    if (!preg_match('/^zt1\.(\d{1,12})\./', $customId, $match)) {
        return 0;
    }
    $userId = (int) $match[1];
    return zt_paypal_custom_id_matches($customId, $userId) ? $userId : 0;
}

function zt_paypal_valid_subscription_id(string $subscriptionId): bool
{
    return preg_match('/^I-[A-Z0-9]{6,64}$/', strtoupper(trim($subscriptionId))) === 1;
}

function zt_paypal_http(string $method, string $path, ?array $payload = null, string $accessToken = ''): array
{
    if (!preg_match('#^/v1/[A-Za-z0-9/_-]+$#', $path)) {
        return ['ok' => false, 'status' => 0, 'error' => 'invalid_path'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'error' => 'transport_unavailable'];
    }

    $method = strtoupper($method);
    if (!in_array($method, ['GET', 'POST'], true)) {
        return ['ok' => false, 'status' => 0, 'error' => 'invalid_method'];
    }

    $body = '';
    $headers = ['Accept: application/json'];
    if ($payload !== null) {
        $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
    }
    if ($accessToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }

    $response = '';
    $tooLarge = false;
    $ch = curl_init(zt_paypal_api_base() . $path);
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'ZeroThinkBilling/1.0',
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response, &$tooLarge): int {
            if (strlen($response) + strlen($chunk) > 524288) {
                $tooLarge = true;
                return 0;
            }
            $response .= $chunk;
            return strlen($chunk);
        },
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    if ($payload !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $options);
    $executed = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch);
    curl_close($ch);

    $decoded = $response !== '' ? json_decode($response, true) : [];
    return [
        'ok' => $executed !== false && !$tooLarge && $curlError === 0 && $status >= 200 && $status < 300,
        'status' => $status,
        'data' => is_array($decoded) ? $decoded : [],
        'error' => $tooLarge ? 'response_too_large' : ($curlError ? 'transport_failed' : ''),
    ];
}

function zt_paypal_access_token(): string
{
    static $cached = '';
    static $expiresAt = 0;
    if ($cached !== '' && $expiresAt > time() + 60) {
        return $cached;
    }
    if (!zt_paypal_server_verification_ready() || !function_exists('curl_init')) {
        return '';
    }

    $response = '';
    $tooLarge = false;
    $ch = curl_init(zt_paypal_api_base() . '/v1/oauth2/token');
    $options = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERPWD => zt_paypal_client_id() . ':' . zt_paypal_client_secret(),
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_USERAGENT => 'ZeroThinkBilling/1.0',
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response, &$tooLarge): int {
            if (strlen($response) + strlen($chunk) > 262144) {
                $tooLarge = true;
                return 0;
            }
            $response .= $chunk;
            return strlen($chunk);
        },
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    curl_setopt_array($ch, $options);
    $executed = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch);
    curl_close($ch);

    if ($executed === false || $tooLarge || $curlError !== 0 || $status < 200 || $status >= 300) {
        error_log('ZeroThink PayPal token request failed.');
        return '';
    }
    $decoded = json_decode($response, true);
    $token = is_array($decoded) ? trim((string) ($decoded['access_token'] ?? '')) : '';
    if ($token === '' || strlen($token) > 4096) {
        return '';
    }
    $ttl = max(120, min(28800, (int) ($decoded['expires_in'] ?? 300)));
    $cached = $token;
    $expiresAt = time() + $ttl;
    return $cached;
}

function zt_paypal_subscription_details(string $subscriptionId): array
{
    $subscriptionId = strtoupper(trim($subscriptionId));
    if (!zt_paypal_valid_subscription_id($subscriptionId)) {
        return ['ok' => false, 'error' => 'invalid_subscription_id'];
    }
    $token = zt_paypal_access_token();
    if ($token === '') {
        return ['ok' => false, 'error' => 'verification_failed'];
    }
    $response = zt_paypal_http('GET', '/v1/billing/subscriptions/' . rawurlencode($subscriptionId), null, $token);
    if (empty($response['ok'])) {
        error_log('ZeroThink PayPal subscription verification failed: HTTP ' . (int) ($response['status'] ?? 0));
        return ['ok' => false, 'error' => 'verification_failed'];
    }
    return ['ok' => true, 'details' => (array) ($response['data'] ?? [])];
}

function activate_pro_subscription(int $userId, $subscriptionId, $plan, array $options = []): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'not_logged_in'];
    }
    $plan = strtolower(trim((string) $plan));
    $plans = zt_paypal_plan_ids();
    if (!isset($plans[$plan])) {
        return ['ok' => false, 'error' => 'invalid_plan'];
    }
    $subscriptionId = strtoupper(trim((string) $subscriptionId));
    if (!zt_paypal_valid_subscription_id($subscriptionId)) {
        return ['ok' => false, 'error' => 'invalid_subscription_id'];
    }
    if (empty($options['verify'])) {
        return ['ok' => false, 'error' => 'verification_failed'];
    }

    $verified = zt_paypal_subscription_details($subscriptionId);
    if (empty($verified['ok'])) {
        return $verified;
    }
    $details = (array) ($verified['details'] ?? []);
    if (strtoupper((string) ($details['id'] ?? '')) !== $subscriptionId) {
        return ['ok' => false, 'error' => 'verification_failed'];
    }
    if (!hash_equals((string) $plans[$plan], (string) ($details['plan_id'] ?? ''))) {
        return ['ok' => false, 'error' => 'plan_mismatch'];
    }
    if (strtoupper((string) ($details['status'] ?? '')) !== 'ACTIVE') {
        return ['ok' => false, 'error' => 'subscription_not_active'];
    }

    try {
        $db = db();
        $db->beginTransaction();
        $stmt = $db->prepare('SELECT id, email, email_verified, disabled FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !empty($user['disabled'])) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'not_logged_in'];
        }

        $customId = trim((string) ($details['custom_id'] ?? ''));
        $subscriberEmail = strtolower(trim((string) ($details['subscriber']['email_address'] ?? '')));
        $accountEmail = strtolower(trim((string) ($user['email'] ?? '')));
        $boundByCustomId = $customId !== '' && zt_paypal_custom_id_matches($customId, $userId);
        $boundByVerifiedEmail = $subscriberEmail !== ''
            && $accountEmail !== ''
            && !empty($user['email_verified'])
            && hash_equals($accountEmail, $subscriberEmail);
        if (!$boundByCustomId && !$boundByVerifiedEmail) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'subscription_user_mismatch'];
        }

        $duplicate = $db->prepare('SELECT id FROM users WHERE subscription_id = ? AND id <> ? LIMIT 1 FOR UPDATE');
        $duplicate->execute([$subscriptionId, $userId]);
        if ($duplicate->fetchColumn()) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'subscription_in_use'];
        }

        $update = $db->prepare("UPDATE users
            SET plan = CASE WHEN plan = 'god_mode' THEN 'god_mode' ELSE 'pro' END,
                is_pro = 1,
                subscription_status = 'active',
                subscription_id = ?,
                subscription_end = NULL
            WHERE id = ? AND COALESCE(disabled, 0) = 0
            LIMIT 1");
        $update->execute([$subscriptionId, $userId]);
        $db->commit();
    } catch (Throwable $e) {
        if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('ZeroThink verified subscription storage failed.');
        return ['ok' => false, 'error' => 'db_update_failed'];
    }

    if (function_exists('current_user_record')) {
        current_user_record(true);
    }
    return [
        'ok' => true,
        'plan' => $plan,
        'subscription_id' => $subscriptionId,
        'verified' => true,
        'verification_mode' => 'paypal_server_api',
    ];
}

function zt_paypal_cancel_remote(string $subscriptionId): array
{
    $subscriptionId = strtoupper(trim($subscriptionId));
    if (!zt_paypal_valid_subscription_id($subscriptionId)) {
        return ['ok' => false, 'reason' => 'not_paypal_subscription'];
    }
    $token = zt_paypal_access_token();
    if ($token === '') {
        return ['ok' => false, 'reason' => 'paypal_credentials_unavailable'];
    }
    $response = zt_paypal_http(
        'POST',
        '/v1/billing/subscriptions/' . rawurlencode($subscriptionId) . '/cancel',
        ['reason' => 'Customer requested cancellation in ZeroThink.'],
        $token
    );
    if (!empty($response['ok']) && (int) ($response['status'] ?? 0) === 204) {
        return ['ok' => true, 'reason' => null];
    }

    // Cancellation is idempotent from the user's perspective. Confirm an
    // already-final state before asking them to repeat the action in PayPal.
    $details = zt_paypal_subscription_details($subscriptionId);
    $status = strtoupper((string) ($details['details']['status'] ?? ''));
    if (!empty($details['ok']) && in_array($status, ['CANCELLED', 'EXPIRED'], true)) {
        return ['ok' => true, 'reason' => 'already_cancelled'];
    }
    return ['ok' => false, 'reason' => 'paypal_cancel_failed'];
}

function cancel_pro_subscription(int $userId, array $options = []): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'not_logged_in'];
    }
    try {
        $stmt = db()->prepare('SELECT id, plan, subscription_id, disabled FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'db_unavailable'];
    }
    if (!$user || !empty($user['disabled'])) {
        return ['ok' => false, 'error' => 'user_not_found'];
    }

    $subscriptionId = trim((string) ($user['subscription_id'] ?? ''));
    $remote = ['ok' => false, 'reason' => 'not_requested'];
    if (!empty($options['cancel_remote']) && zt_paypal_valid_subscription_id($subscriptionId)) {
        $remote = zt_paypal_cancel_remote($subscriptionId);
    }

    try {
        $db = db();
        $db->beginTransaction();
        $update = $db->prepare("UPDATE users
            SET plan = CASE WHEN plan = 'god_mode' THEN 'god_mode' ELSE 'free' END,
                is_pro = CASE WHEN plan = 'god_mode' THEN 1 ELSE 0 END,
                subscription_status = 'cancelled',
                subscription_end = NOW(),
                subscription_id = CASE WHEN subscription_id LIKE ? THEN NULL ELSE subscription_id END
            WHERE id = ? AND COALESCE(disabled, 0) = 0
            LIMIT 1");
        $update->execute([zerothink_token_gate_subscription_prefix() . '%', $userId]);
        $db->commit();
    } catch (Throwable $e) {
        if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'db_update_failed'];
    }

    if (function_exists('current_user_record')) {
        current_user_record(true);
    }
    $isPayPal = zt_paypal_valid_subscription_id($subscriptionId);
    return [
        'ok' => true,
        'remote_cancelled' => $isPayPal && !empty($remote['ok']),
        'remote_reason' => $isPayPal ? ($remote['reason'] ?? null) : null,
        'manual_paypal_cancel_required' => $isPayPal && empty($remote['ok']),
        'paypal_manage_url' => paypal_manage_url(),
        'support_email' => 'shaf@talktoai.org',
    ];
}

function paypal_manage_url(): string
{
    return 'https://www.paypal.com/myaccount/autopay/';
}

function zerothink_token_gate_subscription_prefix(): string
{
    $configured = zt_subscription_config('TOKEN_GATE_SUBSCRIPTION_PREFIX', ['token_gate', 'subscription_prefix'], 'token-gate:');
    return preg_match('/^[a-z0-9_-]{3,24}:$/i', $configured) ? strtolower($configured) : 'token-gate:';
}

function token_gate_subscription_id(string $wallet): string
{
    $wallet = trim($wallet);
    if (!preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $wallet)) {
        throw new InvalidArgumentException('Invalid token-gate wallet.');
    }
    return zerothink_token_gate_subscription_prefix() . $wallet;
}

function token_gate_is_subscription_id(string $subscriptionId): bool
{
    $prefix = zerothink_token_gate_subscription_prefix();
    return strncmp($subscriptionId, $prefix, strlen($prefix)) === 0;
}

function zerothink_token_gate_access_ttl_seconds(): int
{
    $raw = zt_subscription_config('TOKEN_GATE_ACCESS_TTL_SECONDS', ['token_gate', 'access_ttl_seconds'], '86400');
    $ttl = is_numeric($raw) ? (int) $raw : 86400;
    return max(900, min(604800, $ttl));
}
