<?php
// Exercise the real pure dispatch helpers without bootstrapping auth, a DB or an API.
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/api_agent.php');
foreach (['zt_model_prefix_parts', 'zt_featherless_model_map', 'zt_openzero_local_model_map', 'zt_provider_model_allowed', 'detect_provider', 'normalize_model_id'] as $name) {
    if (!preg_match('/^function ' . preg_quote($name, '/') . '\([^\n]*\)\s*\{.*?^\}/ms', $source, $match)) {
        throw new RuntimeException('Cannot extract actual helper: ' . $name);
    }
    eval($match[0]);
}
require $root . '/app/core/model_catalog.php';
$checks = 0;
$verify = static function (bool $value, string $message) use (&$checks): void {
    if (!$value) { throw new RuntimeException($message); }
    $checks++;
};
foreach (['q5', 'f16'] as $precision) {
    $canonical = 'zero-qwen3-' . $precision . ':latest';
    foreach (['openzeroqwen3-' . $precision, 'openzeroqwen3-' . $precision . ':latest', 'zero-qwen3-' . $precision, $canonical] as $alias) {
        $verify(normalize_model_id('zerothink', $alias) === $canonical, 'Unprefixed alias must resolve: ' . $alias);
        $verify(normalize_model_id('zerothink', 'zerothink:' . $alias) === $canonical, 'Provider-prefixed alias must resolve: ' . $alias);
    }
    $verify(zt_provider_model_allowed('zerothink', $canonical), 'Canonical local ID must pass dispatch allowlist');
    $verify(in_array($canonical, zt_model_ids('zerothink'), true), 'Canonical local ID must be selectable');
    $verify(detect_provider('zerothink', $canonical) === 'zerothink', 'Explicit local selection must remain local');
}
$verify(!zt_provider_model_allowed('zerothink', 'zero-qwen3-q5:unreviewed'), 'Unreviewed local tag must remain rejected');
$verify(normalize_model_id('zerothink', 'openzero') === 'hf.co/shafire/OpenZero-Ministral3-8B-Runtime-Agent-GGUF:Q5_K_M', 'Existing default must remain unchanged');
echo json_encode(['status' => 'passed', 'checks' => $checks, 'scope' => 'actual pure dispatch/catalog helpers; no network, process, database or inference']) . "\n";
