<?php
/**
 * Canonical Google OAuth configuration.
 *
 * Credentials belong in the process environment or the root .env file, never
 * in source control. The small fallback reader is needed because the public
 * login endpoint runs before the wider application configuration stack.
 */
function zt_google_config_value($name, $default = '') {
    static $file_env = null;
    $name = strtoupper(trim((string) $name));
    $value = trim((string) getenv($name));
    if ($value !== '') {
        return $value;
    }
    if ($file_env === null) {
        $path = dirname(__DIR__) . '/.env';
        $file_env = is_file($path) && is_readable($path)
            ? (@parse_ini_file($path, false, INI_SCANNER_RAW) ?: [])
            : [];
    }
    $value = trim((string) ($file_env[$name] ?? ''));
    return $value !== '' ? $value : (string) $default;
}

defined('GOOGLE_CLIENT_ID') || define('GOOGLE_CLIENT_ID', zt_google_config_value('GOOGLE_CLIENT_ID'));
defined('GOOGLE_CLIENT_SECRET') || define('GOOGLE_CLIENT_SECRET', zt_google_config_value('GOOGLE_CLIENT_SECRET'));
defined('GOOGLE_REDIRECT_URL') || define(
    'GOOGLE_REDIRECT_URL',
    zt_google_config_value('GOOGLE_REDIRECT_URL', 'https://zerothink.talktoai.org/auth/callback')
);
