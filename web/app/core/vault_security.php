<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
if (file_exists(__DIR__ . '/crypto.php')) {
    require_once __DIR__ . '/crypto.php';
}

function zt_vault_secret_fields(): array
{
    return [
        'groq' => 'api_key_groq',
        'openai' => 'api_key_openai',
        'gemini' => 'api_key_gemini',
        'xai' => 'api_key_xai',
        'nvidia' => 'api_key_nvidia',
        'quantum' => 'api_key_quantum',
        'ibm_quantum' => 'api_key_ibm_quantum',
        'featherless' => 'api_key_featherless',
        'zerothink_api' => 'api_key_zerothink',
        'serper' => 'serper_api_key',
    ];
}

function zt_vault_cipher_column(string $plainColumn): string
{
    return $plainColumn . '_cipher';
}

function zt_vault_config_value(string $key, string $default = ''): string
{
    if (function_exists('app_config')) {
        $value = app_config($key, null);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    $envNames = [
        'security.master_key_b64' => [
            'ZEROTHINK_VAULT_MASTER_KEY_B64',
            'ZEROTHINK_SECURITY_MASTER_KEY_B64',
            'SECURITY_MASTER_KEY_B64',
            'MASTER_KEY_B64',
        ],
        'openzero.api_base_url' => [
            'ZEROTHINK_API_BASE_URL',
            'OPENZERO_API_BASE_URL',
        ],
    ];

    foreach ($envNames[$key] ?? [] as $envName) {
        $value = trim((string) getenv($envName));
        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

function zt_vault_master_key_b64(): string
{
    $value = zt_vault_config_value('security.master_key_b64', '');
    $decoded = base64_decode($value, true);
    if (!is_string($decoded) || strlen($decoded) < 16) {
        return '';
    }
    return $value;
}

function zt_vault_can_encrypt(): bool
{
    return zt_vault_master_key_b64() !== ''
        && function_exists('crypto_encrypt')
        && function_exists('crypto_decrypt');
}

function zt_vault_encrypt_secret(string $plain): ?string
{
    $plain = trim($plain);
    if ($plain === '') {
        return null;
    }
    if (!zt_vault_can_encrypt()) {
        return null;
    }
    return crypto_encrypt($plain, zt_vault_master_key_b64());
}

function zt_vault_decrypt_secret(?string $cipher): string
{
    $cipher = trim((string) $cipher);
    if ($cipher === '' || !zt_vault_can_encrypt()) {
        return '';
    }
    try {
        return trim((string) crypto_decrypt($cipher, zt_vault_master_key_b64()));
    } catch (Throwable $e) {
        error_log('ZeroThink vault decrypt failed for encrypted key.');
        return '';
    }
}

function zt_vault_user_is_pro(PDO $db, int $uid): bool
{
    if ($uid <= 0) {
        return false;
    }

    $user = null;
    if (function_exists('current_user_record')) {
        $user = current_user_record();
    }

    if (!is_array($user) || (int) ($user['id'] ?? 0) !== $uid) {
        try {
            $stmt = $db->prepare('SELECT id, role, plan, is_pro, subscription_status FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$uid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $user = null;
        }
    }

    if (!$user) {
        return false;
    }

    if (function_exists('is_architect_user') && is_architect_user($user)) {
        return true;
    }

    $role = strtolower((string) ($user['role'] ?? ''));
    $plan = strtolower((string) ($user['plan'] ?? ''));
    $sub = strtolower((string) ($user['subscription_status'] ?? ''));

    return $role === 'admin'
        || !empty($user['is_pro'])
        || in_array($plan, ['pro', 'god_mode'], true)
        || in_array($sub, ['active', 'pro'], true);
}

function zt_vault_secret_from_row(array $row, string $plainColumn): string
{
    $cipherColumn = zt_vault_cipher_column($plainColumn);
    $decrypted = zt_vault_decrypt_secret($row[$cipherColumn] ?? '');
    if ($decrypted !== '') {
        return $decrypted;
    }
    return trim((string) ($row[$plainColumn] ?? ''));
}

function zt_vault_openzero_base_url(): string
{
    return rtrim(zt_vault_config_value('openzero.api_base_url', ''), '/');
}
?>
