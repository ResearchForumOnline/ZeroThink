<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/core/oracle_security.php';

if (!function_exists('is_architect_user')) {
    function is_architect_user($user = null): bool
    {
        return is_array($user) && !empty($user['test_architect']);
    }
}

$api = (string) file_get_contents($root . '/api_oracle.php');
$page = (string) file_get_contents($root . '/oracle.php');
$bootstrap = (string) file_get_contents($root . '/app/bootstrap.php');
$checks = 0;
$failures = [];

function zt_oracle_assert(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function zt_oracle_source_block(string $source, string $start, string $end): string
{
    $from = strpos($source, $start);
    if ($from === false) {
        return '';
    }
    $to = strpos($source, $end, $from + strlen($start));
    return $to === false ? substr($source, $from) : substr($source, $from, $to - $from);
}

zt_oracle_assert(
    zt_oracle_allowed_providers() === ['groq', 'openai', 'xai', 'gemini', 'nvidia']
        && !zt_oracle_provider_is_allowed('http')
        && !zt_oracle_provider_is_allowed(''),
    'Oracle provider routing must use the exact reviewed allowlist.'
);

$catalogStart = strpos($page, 'const ORACLE_MODELS = {');
$catalogEnd = $catalogStart === false ? false : strpos($page, '        };', $catalogStart);
$catalogBlock = $catalogStart !== false && $catalogEnd !== false
    ? substr($page, $catalogStart, $catalogEnd - $catalogStart)
    : '';
$providerPositions = [];
foreach (zt_oracle_allowed_providers() as $provider) {
    $position = strpos($catalogBlock, $provider . ': [');
    if ($position !== false) {
        $providerPositions[$provider] = $position;
    }
}
asort($providerPositions, SORT_NUMERIC);
$orderedProviders = array_keys($providerPositions);
$uiCatalogAllowed = true;
$uiModelCount = 0;
foreach ($orderedProviders as $index => $provider) {
    $from = $providerPositions[$provider];
    $to = isset($orderedProviders[$index + 1])
        ? $providerPositions[$orderedProviders[$index + 1]]
        : strlen($catalogBlock);
    $section = substr($catalogBlock, $from, $to - $from);
    preg_match_all("/\\['([^']+)',\\s*'[^']+'\\]/", $section, $matches);
    $models = $matches[1] ?? [];
    $uiCatalogAllowed = $uiCatalogAllowed && $models !== [];
    foreach ($models as $model) {
        $normalized = zt_oracle_normalize_model($provider, $model);
        $uiCatalogAllowed = $uiCatalogAllowed && zt_oracle_model_is_allowed($provider, $normalized);
        $uiModelCount++;
    }
}
zt_oracle_assert(
    count($providerPositions) === count(zt_oracle_allowed_providers())
        && $uiModelCount >= 40
        && $uiCatalogAllowed,
    'Every provider/model exposed by the Oracle page must survive normalization and the server allowlists.'
);
zt_oracle_assert(
    zt_oracle_normalize_model('groq', 'gpt-oss-120b') === 'openai/gpt-oss-120b'
        && zt_oracle_normalize_model('openai', 'gpt-5.4-pro') === 'gpt-5.2-pro'
        && zt_oracle_normalize_model('nvidia', 'deepseek-v3') === 'deepseek-ai/deepseek-v3.1',
    'Reviewed legacy Oracle model aliases must remain compatible.'
);
zt_oracle_assert(
    !zt_oracle_model_is_allowed('groq', zt_oracle_normalize_model('groq', 'attacker/model'))
        && !zt_oracle_model_is_allowed('gemini', '../models/anything'),
    'Arbitrary caller-controlled model ids must be rejected.'
);

zt_oracle_assert(
    !zt_oracle_user_can_use_service_keys(['role' => 'user', 'plan' => 'free', 'is_pro' => 0])
        && zt_oracle_user_can_use_service_keys(['role' => 'admin'])
        && zt_oracle_user_can_use_service_keys(['is_pro' => 1])
        && zt_oracle_user_can_use_service_keys(['plan' => 'pro'])
        && zt_oracle_user_can_use_service_keys(['subscription_status' => 'active'])
        && zt_oracle_user_can_use_service_keys(['test_architect' => true]),
    'Only explicit Pro/admin/architect-style entitlement may unlock site service credentials.'
);
zt_oracle_assert(
    zt_oracle_parse_boolean(true) === true
        && zt_oracle_parse_boolean('1') === true
        && zt_oracle_parse_boolean(false) === false
        && zt_oracle_parse_boolean('0') === false
        && zt_oracle_parse_boolean([]) === null,
    'Live-web input must use a bounded, strict boolean parser.'
);

$first = zt_oracle_rate_window([], 1000, 2, 60);
$second = zt_oracle_rate_window($first['timestamps'], 1000, 2, 60);
$third = zt_oracle_rate_window($second['timestamps'], 1000, 2, 60);
$afterWindow = zt_oracle_rate_window($third['timestamps'], 1060, 2, 60);
zt_oracle_assert(
    $first['allowed'] && $second['allowed'] && !$third['allowed']
        && $third['remaining'] === 0 && $third['retry_after'] === 60
        && $afterWindow['allowed'],
    'Sliding-window state must reject the request over limit and recover at the window boundary.'
);

$sandbox = sys_get_temp_dir() . '/zerothink-oracle-rate-test-' . bin2hex(random_bytes(6));
try {
    $diskFirst = zt_oracle_consume_rate_limit(77, $sandbox, 2000, 1, 60);
    $diskSecond = zt_oracle_consume_rate_limit(77, $sandbox, 2000, 1, 60);
    $diskLater = zt_oracle_consume_rate_limit(77, $sandbox, 2060, 1, 60);
    zt_oracle_assert(
        $diskFirst['allowed'] && !$diskSecond['allowed'] && $diskLater['allowed'],
        'Locked per-user rate state must persist safely across independent calls.'
    );
} finally {
    if (is_dir($sandbox)) {
        foreach (glob($sandbox . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($sandbox);
    }
}

zt_oracle_assert(
    strpos($api, 'current_user_record(true)') !== false
        && strpos($api, "'Active sign-in required.'") !== false
        && strpos($bootstrap, 'COALESCE(disabled, 0) = 0') !== false
        && strpos($api, 'zt_vault_secret_from_row($user, $column)') !== false,
    'Oracle must refresh the shared active-user record instead of trusting the session id alone.'
);
zt_oracle_assert(
    strpos($api, 'ZT_ORACLE_MAX_BODY_BYTES = 24576') !== false
        && strpos($api, 'ZT_ORACLE_MAX_QUERY_BYTES = 12000') !== false
        && strpos($api, 'stream_get_contents($stream, ZT_ORACLE_MAX_BODY_BYTES + 1)') !== false
        && strpos($api, 'json_decode($raw, true, 16, JSON_THROW_ON_ERROR)') !== false,
    'Oracle must cap the raw body/query and bound JSON nesting before request processing.'
);
zt_oracle_assert(
    strpos($api, "header('Allow: POST')") !== false
        && strpos($api, "'JSON request required.'") !== false
        && strpos($api, 'zt_csrf_validate($csrfToken)') !== false,
    'Oracle requests must be same-session CSRF-protected JSON POSTs.'
);
zt_oracle_assert(
    strpos($api, 'zt_oracle_provider_is_allowed($provider)') !== false
        && strpos($api, 'zt_oracle_model_is_allowed($provider, $model)') !== false,
    'The endpoint must enforce both provider and normalized-model allowlists.'
);

$providerKeyBlock = zt_oracle_source_block($api, 'function oracle_get_key', 'function oracle_service_serper_key');
$providerDb = strpos($providerKeyBlock, 'oracle_user_secret($user, $column)');
$providerSession = strpos($providerKeyBlock, '$_SESSION[$column]');
$providerInline = strpos($providerKeyBlock, 'oracle_secret_value($inline)');
$providerService = strpos($providerKeyBlock, '$allowServiceFallback ? oracle_service_provider_key');
zt_oracle_assert(
    $providerDb !== false && $providerSession !== false && $providerInline !== false && $providerService !== false
        && $providerDb < $providerSession && $providerSession < $providerInline && $providerInline < $providerService,
    'Provider key precedence must be user DB, then legacy session/inline key, then entitled service fallback.'
);

$searchKeyBlock = zt_oracle_source_block($api, 'function oracle_get_serper_key', 'function oracle_consume_user_rate_limit');
$searchDb = strpos($searchKeyBlock, 'oracle_user_secret($user, \'serper_api_key\')');
$searchSession = strpos($searchKeyBlock, '$_SESSION[\'serper_api_key\']');
$searchInline = strpos($searchKeyBlock, 'oracle_secret_value($inline)');
$searchService = strpos($searchKeyBlock, '$allowServiceFallback ? oracle_service_serper_key');
zt_oracle_assert(
    $searchDb !== false && $searchSession !== false && $searchInline !== false && $searchService !== false
        && $searchDb < $searchSession && $searchSession < $searchInline && $searchInline < $searchService,
    'Search-key precedence must be user DB, then legacy session/inline key, then entitled service fallback.'
);
zt_oracle_assert(
    strpos($api, '$allowServiceFallback = zt_oracle_user_can_use_service_keys($user)') !== false
        && strpos($api, 'oracle_get_key($provider, $user, $inlineKey, $allowServiceFallback)') !== false
        && strpos($api, 'oracle_get_serper_key($user, $inlineSerper, $allowServiceFallback)') !== false,
    'The same explicit entitlement decision must gate provider and search service credentials.'
);
zt_oracle_assert(
    strpos($api, 'oracle_consume_user_rate_limit($uid)') !== false
        && strpos($api, "header('Retry-After: '") !== false
        && strpos($api, "'Too many Oracle requests. Try again shortly.'") !== false,
    'The endpoint must consume the cross-session user limiter and emit a bounded 429 retry response.'
);
zt_oracle_assert(
    strpos($api, 'CURLOPT_SSL_VERIFYPEER => true') !== false
        && strpos($api, 'ZT_ORACLE_MAX_PROVIDER_RESPONSE_BYTES') !== false
        && strpos($api, 'ZT_ORACLE_MAX_SEARCH_RESPONSE_BYTES') !== false
        && strpos($api, 'Provider error (') === false
        && strpos($api, 'Network error:') === false,
    'Outbound calls must verify TLS, cap responses, and not expose raw provider/network errors.'
);
zt_oracle_assert(
    strpos($page, "'X-CSRF-Token': ZT_VAULT_CSRF_TOKEN") !== false
        && strpos($page, 'csrf_token: ZT_VAULT_CSRF_TOKEN') !== false
        && strpos($page, "credentials: 'same-origin'") !== false
        && strpos($page, 'data.request_id') !== false
        && strpos($page, "data.data[field + '_saved']") !== false,
    'The Oracle page must send the shared session CSRF token with its JSON request.'
);
zt_oracle_assert(
    strpos($page, 'li.textContent = String(item)') !== false
        && strpos($page, 'safe.map((item) => `<li>${item}</li>`)') === false,
    'Model-generated list items must be rendered as text instead of executable HTML.'
);

if ($failures) {
    fwrite(STDERR, 'FAIL: ' . count($failures) . ' of ' . $checks . " Oracle security checks failed\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo 'OK: ' . $checks . " Oracle security checks passed\n";
