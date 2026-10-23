<?php
declare(strict_types=1);

/**
 * Security policy and runtime-safe helpers for Zero Oracle.
 *
 * Keep the catalog explicit: a caller-controlled model id must never be able to
 * turn the provider URL selection into an open proxy or invoke an unreviewed
 * model with a shared service credential.
 */
function zt_oracle_allowed_providers(): array
{
    return ['groq', 'openai', 'xai', 'gemini', 'nvidia'];
}

function zt_oracle_provider_is_allowed(string $provider): bool
{
    return in_array($provider, zt_oracle_allowed_providers(), true);
}

function zt_oracle_default_model(string $provider): string
{
    $defaults = [
        'groq' => 'openai/gpt-oss-120b',
        'openai' => 'gpt-5.2',
        'xai' => 'grok-4-latest',
        'gemini' => 'gemini-2.5-pro',
        'nvidia' => 'qwen/qwen3-coder-480b-a35b-instruct',
    ];

    return $defaults[$provider] ?? '';
}

function zt_oracle_allowed_models(): array
{
    return [
        'groq' => [
            'openai/gpt-oss-120b',
            'openai/gpt-oss-20b',
            'groq/compound',
            'groq/compound-mini',
            'llama-3.1-8b-instant',
            'deepseek-r1-distill-llama-70b',
            'deepseek-r1-distill-qwen-32b',
            'llama-3.3-70b-versatile',
            'qwen/qwen3-32b',
            'qwen-qwq-32b',
            'qwen/qwen3.6-27b',
            'moonshotai/kimi-k2-instruct-0905',
            'allam-2-7b',
        ],
        'openai' => [
            'gpt-5.2',
            'gpt-5.2-pro',
            'gpt-5-pro',
            'gpt-5.1',
            'gpt-5',
            'gpt-5-mini',
        ],
        'xai' => [
            'grok-4-latest',
            'grok-4-fast-reasoning',
            'grok-code-fast-1',
            'grok-3',
        ],
        'gemini' => [
            'gemini-2.5-pro',
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-3-pro-preview',
            'gemini-3-flash-preview',
        ],
        'nvidia' => [
            'qwen/qwen3-coder-480b-a35b-instruct',
            'qwen/qwen2.5-coder-32b-instruct',
            'qwen/qwen3-next-80b-a3b-instruct',
            'qwen/qwen3-5-122b-a10b',
            'meta/llama-3.3-70b-instruct',
            'meta/llama-4-maverick-17b-128e-instruct',
            'google/gemma-4-31b-it',
            'google/gemma-3-27b-it',
            'google/gemma-3n-e4b-it',
            'mistralai/mistral-2-large-instruct',
            'mistralai/mistral-nemotron',
            'mistralai/devstral-2-123b-instruct-2512',
            'mistralai/mistral-small-24b-instruct',
            'deepseek-ai/deepseek-v3.1',
            'deepseek-ai/deepseek-v3.2',
            'deepseek-ai/deepseek-r1',
            'deepseek-ai/deepseek-r1-distill-qwen-32b',
            'nvidia/nemotron-3-super-120b-a12b',
        ],
    ];
}

function zt_oracle_model_aliases(): array
{
    return [
        'openai' => [
            'gpt-5-turbo' => 'gpt-5.2',
            'gpt-5-o' => 'gpt-5.2',
            'gpt-5.5' => 'gpt-5.2',
            'gpt-5.5-pro' => 'gpt-5.2-pro',
            'gpt-5.5 pro' => 'gpt-5.2-pro',
            'gpt-5.4' => 'gpt-5.2',
            'gpt-5.4-pro' => 'gpt-5.2-pro',
            'o3-max' => 'gpt-5.2',
            'gpt-5.2 pro' => 'gpt-5.2-pro',
            'gpt-5 pro' => 'gpt-5-pro',
            'gpt-5.1-chat' => 'gpt-5.1',
            'gpt-5.1-chat-latest' => 'gpt-5.1',
        ],
        'groq' => [
            'gpt-oss-120b' => 'openai/gpt-oss-120b',
            'gpt-oss-20b' => 'openai/gpt-oss-20b',
            'llama-4-maverick' => 'openai/gpt-oss-120b',
            'meta-llama/llama-4-maverick-17b-128e-instruct' => 'openai/gpt-oss-120b',
            'llama-4-scout' => 'openai/gpt-oss-120b',
            'meta-llama/llama-4-scout-17b-16e-instruct' => 'openai/gpt-oss-120b',
            'mixtral-8x22b-v2' => 'groq/compound',
            'mixtral-8x7b-32768' => 'groq/compound',
            'gemma-7b-it' => 'llama-3.1-8b-instant',
            'gemma2-9b-it' => 'llama-3.1-8b-instant',
            'qwen-2.5-32b' => 'openai/gpt-oss-120b',
            'qwen-2.5-coder-32b' => 'openai/gpt-oss-120b',
            'qwen-3-32b' => 'openai/gpt-oss-120b',
            'qwen/qwen3-32b' => 'openai/gpt-oss-120b',
            'qwen-qwq-32b' => 'openai/gpt-oss-120b',
            'qwen3.6-27b' => 'qwen/qwen3.6-27b',
            'qwen-3.6-27b' => 'qwen/qwen3.6-27b',
            'kimi-k2' => 'openai/gpt-oss-120b',
            'moonshotai/kimi-k2-instruct-0905' => 'openai/gpt-oss-120b',
            'deepseek-r1-distill-qwen-32b' => 'openai/gpt-oss-120b',
            'deepseek-r1-distill-llama-70b' => 'openai/gpt-oss-120b',
            'llama-3.3-70b' => 'openai/gpt-oss-120b',
            'llama-3.3-70b-versatile' => 'openai/gpt-oss-120b',
            'llama-3.3-70b-specdec' => 'openai/gpt-oss-120b',
            'allam' => 'allam-2-7b',
        ],
        'gemini' => [
            'gemini-3' => 'gemini-3-pro-preview',
            'gemini-3-flash' => 'gemini-3-flash-preview',
            'gemini-3-pro' => 'gemini-3-pro-preview',
            'gemini-2.5-pro-preview-03-25' => 'gemini-2.5-pro',
            'gemini-2.5-pro-preview-05-06' => 'gemini-2.5-pro',
            'gemini-2.5-flash-preview-05-20' => 'gemini-2.5-flash',
        ],
        'nvidia' => [
            'qwen-480b-coder' => 'qwen/qwen3-coder-480b-a35b-instruct',
            'qwen-coder-480b' => 'qwen/qwen3-coder-480b-a35b-instruct',
            'qwen3-coder-480b' => 'qwen/qwen3-coder-480b-a35b-instruct',
            'qwen3-next-80b' => 'qwen/qwen3-next-80b-a3b-instruct',
            'qwen3.5-122b' => 'qwen/qwen3-5-122b-a10b',
            'mistral-large' => 'mistralai/mistral-2-large-instruct',
            'mistralai/mistral-large' => 'mistralai/mistral-2-large-instruct',
            'mistral-large-2' => 'mistralai/mistral-2-large-instruct',
            'mistralai/mistral-large-2-instruct' => 'mistralai/mistral-2-large-instruct',
            'mistralai/mistral-large-3-675b-instruct-2512' => 'mistralai/mistral-2-large-instruct',
            'llama-4-maverick' => 'meta/llama-4-maverick-17b-128e-instruct',
            'deepseek-v3' => 'deepseek-ai/deepseek-v3.1',
            'deepseek-v3.1' => 'deepseek-ai/deepseek-v3.1',
            'deepseek-v3.2' => 'deepseek-ai/deepseek-v3.2',
            'gemma-4-31b' => 'google/gemma-4-31b-it',
            'gemma-3-27b' => 'google/gemma-3-27b-it',
            'gemma-3n-e4b' => 'google/gemma-3n-e4b-it',
            'mistral-nemotron' => 'mistralai/mistral-nemotron',
            'devstral-2-123b' => 'mistralai/devstral-2-123b-instruct-2512',
        ],
    ];
}

function zt_oracle_normalize_model(string $provider, string $model): string
{
    $model = trim($model);
    if ($model === '') {
        return zt_oracle_default_model($provider);
    }

    $aliases = zt_oracle_model_aliases();
    return $aliases[$provider][$model] ?? $model;
}

function zt_oracle_model_is_allowed(string $provider, string $model): bool
{
    $catalog = zt_oracle_allowed_models();
    return isset($catalog[$provider]) && in_array($model, $catalog[$provider], true);
}

function zt_oracle_user_can_use_service_keys(array $user): bool
{
    if (function_exists('is_architect_user') && is_architect_user($user)) {
        return true;
    }

    $role = strtolower(trim((string) ($user['role'] ?? '')));
    $plan = strtolower(trim((string) ($user['plan'] ?? '')));
    $subscription = strtolower(trim((string) ($user['subscription_status'] ?? '')));

    return $role === 'admin'
        || !empty($user['is_pro'])
        || in_array($plan, ['pro', 'god_mode'], true)
        || in_array($subscription, ['active', 'pro'], true);
}

function zt_oracle_parse_boolean(mixed $value): ?bool
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === 1 || $value === '1' || $value === 'true') {
        return true;
    }
    if ($value === 0 || $value === '0' || $value === 'false' || $value === null) {
        return false;
    }
    return null;
}

/**
 * Pure sliding-window transition used by both the endpoint and the regression
 * test. The returned timestamp list is always pruned and capped by $limit.
 */
function zt_oracle_rate_window(array $timestamps, int $now, int $limit = 8, int $windowSeconds = 60): array
{
    $limit = max(1, min(30, $limit));
    $windowSeconds = max(10, min(3600, $windowSeconds));
    $cutoff = $now - $windowSeconds;
    $active = [];

    foreach ($timestamps as $timestamp) {
        if (!is_int($timestamp) && !ctype_digit((string) $timestamp)) {
            continue;
        }
        $timestamp = (int) $timestamp;
        if ($timestamp > $cutoff && $timestamp <= $now) {
            $active[] = $timestamp;
        }
    }

    sort($active, SORT_NUMERIC);
    if (count($active) >= $limit) {
        $oldest = (int) $active[0];
        return [
            'allowed' => false,
            'timestamps' => array_slice($active, -$limit),
            'limit' => $limit,
            'remaining' => 0,
            'retry_after' => max(1, ($oldest + $windowSeconds) - $now),
            'window' => $windowSeconds,
        ];
    }

    $active[] = $now;
    return [
        'allowed' => true,
        'timestamps' => $active,
        'limit' => $limit,
        'remaining' => max(0, $limit - count($active)),
        'retry_after' => 0,
        'window' => $windowSeconds,
    ];
}

/**
 * Cross-session, per-user limiter backed by a locked file outside the web root.
 */
function zt_oracle_consume_rate_limit(
    int $userId,
    string $directory,
    ?int $now = null,
    int $limit = 8,
    int $windowSeconds = 60
): array {
    if ($userId <= 0 || trim($directory) === '') {
        throw new RuntimeException('Oracle rate-limit identity or path is invalid.');
    }

    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Oracle rate-limit storage is unavailable.');
    }
    @chmod($directory, 0700);

    $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR
        . 'user-' . hash('sha256', (string) $userId) . '.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Oracle rate-limit storage is unavailable.');
    }

    $locked = false;
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Oracle rate-limit lock is unavailable.');
        }
        $locked = true;

        rewind($handle);
        $raw = stream_get_contents($handle, 8192);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true, 4) : [];
        $timestamps = is_array($decoded) && is_array($decoded['timestamps'] ?? null)
            ? $decoded['timestamps']
            : [];

        $state = zt_oracle_rate_window($timestamps, $now ?? time(), $limit, $windowSeconds);
        $encoded = json_encode(
            ['timestamps' => $state['timestamps']],
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($encoded)) {
            throw new RuntimeException('Oracle rate-limit state could not be encoded.');
        }

        rewind($handle);
        if (!ftruncate($handle, 0)) {
            throw new RuntimeException('Oracle rate-limit state could not be updated.');
        }

        $offset = 0;
        $length = strlen($encoded);
        while ($offset < $length) {
            $written = fwrite($handle, substr($encoded, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Oracle rate-limit state could not be updated.');
            }
            $offset += $written;
        }
        fflush($handle);
        @chmod($path, 0600);

        return $state;
    } finally {
        if ($locked) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);
    }
}

