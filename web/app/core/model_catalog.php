<?php

/**
 * Historical provider selections and current OpenZero runtime aliases.
 * Provider catalogues and account access may change; inclusion is not a live-availability guarantee.
 * Keep provider credentials and account-specific availability outside this file.
 */
function zt_model_catalog(): array
{
    return [
        'groq' => [
            'label' => 'GROQ (Production)',
            'pro' => false,
            'models' => [
                ['id' => 'openai/gpt-oss-120b', 'label' => 'GPT-OSS 120B'],
                ['id' => 'groq/compound', 'label' => 'Groq Compound'],
                ['id' => 'groq/compound-mini', 'label' => 'Groq Compound Mini'],
                ['id' => 'openai/gpt-oss-20b', 'label' => 'GPT-OSS 20B'],
                ['id' => 'allam-2-7b', 'label' => 'ALLaM 2 7B'],
            ],
        ],
        'nvidia' => [
            'label' => 'NVIDIA (Current)',
            'pro' => false,
            'models' => [
                ['id' => 'deepseek-ai/deepseek-v4-pro', 'label' => 'DeepSeek V4 Pro'],
                ['id' => 'qwen/qwen3.5-397b-a17b', 'label' => 'Qwen 3.5 397B'],
                ['id' => 'nvidia/nemotron-3-ultra-550b-a55b', 'label' => 'Nemotron 3 Ultra 550B'],
                ['id' => 'minimaxai/minimax-m3', 'label' => 'MiniMax M3'],
                ['id' => 'z-ai/glm-5.2', 'label' => 'GLM 5.2'],
                ['id' => 'mistralai/mistral-small-4-119b-2603', 'label' => 'Mistral Small 4 119B'],
                ['id' => 'qwen/qwen3-next-80b-a3b-instruct', 'label' => 'Qwen 3 Next 80B'],
                ['id' => 'nvidia/nemotron-3-super-120b-a12b', 'label' => 'Nemotron 3 Super 120B'],
                ['id' => 'nvidia/nemotron-3-nano-30b-a3b', 'label' => 'Nemotron 3 Nano 30B'],
                ['id' => 'meta/llama-4-maverick-17b-128e-instruct', 'label' => 'Llama 4 Maverick'],
                ['id' => 'google/gemma-4-31b-it', 'label' => 'Gemma 4 31B'],
                ['id' => 'openai/gpt-oss-120b', 'label' => 'GPT-OSS 120B on NVIDIA'],
                ['id' => 'mistralai/mistral-large-3-675b-instruct-2512', 'label' => 'Mistral Large 3'],
                ['id' => 'mistralai/mistral-medium-3.5-128b', 'label' => 'Mistral Medium 3.5'],
                ['id' => 'mistralai/mistral-nemotron', 'label' => 'Mistral Nemotron'],
                ['id' => 'mistralai/codestral-22b-instruct-v0.1', 'label' => 'Codestral 22B'],
            ],
        ],
        'openai' => [
            'label' => 'OPENAI (Current Pro)',
            'pro' => true,
            'models' => [
                ['id' => 'gpt-5.6-luna', 'label' => 'GPT-5.6 Luna'],
                ['id' => 'gpt-5.6-terra', 'label' => 'GPT-5.6 Terra (account access required)'],
                ['id' => 'gpt-5.6-sol', 'label' => 'GPT-5.6 Sol (account access required)'],
                ['id' => 'gpt-5.5', 'label' => 'GPT-5.5'],
                ['id' => 'gpt-5.4', 'label' => 'GPT-5.4'],
                ['id' => 'gpt-5.4-mini', 'label' => 'GPT-5.4 Mini'],
                ['id' => 'gpt-5.4-nano', 'label' => 'GPT-5.4 Nano'],
            ],
        ],
        'xai' => [
            'label' => 'xAI (Current Pro)',
            'pro' => true,
            'models' => [
                ['id' => 'grok-4.5', 'label' => 'Grok 4.5'],
                ['id' => 'grok-4.5-latest', 'label' => 'Grok 4.5 Latest'],
                ['id' => 'grok-4.20', 'label' => 'Grok 4.20'],
                ['id' => 'grok-4.20-reasoning', 'label' => 'Grok 4.20 Reasoning'],
                ['id' => 'grok-4.20-non-reasoning', 'label' => 'Grok 4.20 Non-Reasoning'],
            ],
        ],
        'gemini' => [
            'label' => 'GEMINI (Current Pro)',
            'pro' => true,
            'models' => [
                ['id' => 'gemini-3.5-flash', 'label' => 'Gemini 3.5 Flash'],
                ['id' => 'gemini-3.1-pro-preview', 'label' => 'Gemini 3.1 Pro Preview'],
                ['id' => 'gemini-3.1-flash-lite', 'label' => 'Gemini 3.1 Flash-Lite'],
            ],
        ],
        'zerothink' => [
            'label' => 'OPENZERO LOCAL (Pro, 5 trial/BYO key)',
            'pro' => true,
            'models' => [
                ['id' => 'hf.co/shafire/OpenZero-Ministral3-8B-Runtime-Agent-GGUF:Q5_K_M', 'label' => 'OpenZero Ministral3 8B Runtime Agent'],
                ['id' => 'openzerogemma:latest', 'label' => 'OpenZero Gemma 4 E4B (compatibility)'],
            ],
        ],
        'featherless' => [
            'label' => 'FEATHERLESS HOSTED (Pro, paid key)',
            'pro' => true,
            'models' => [
                ['id' => 'shafire/talktoaiZERO', 'label' => 'TalkToAI ZERO by Shafire'],
                ['id' => 'zai-org/GLM-5.2', 'label' => 'GLM 5.2 Hosted'],
                ['id' => 'zai-org/GLM-4.5', 'label' => 'GLM 4.5 Hosted'],
                ['id' => 'Qwen/Qwen3-Coder-480B-A35B-Instruct', 'label' => 'Qwen 3 Coder 480B Hosted'],
                ['id' => 'deepseek-ai/DeepSeek-V3.2', 'label' => 'DeepSeek V3.2 Hosted'],
                ['id' => 'moonshotai/Kimi-K2-Instruct', 'label' => 'Kimi K2 Hosted'],
            ],
        ],
        'quantum' => [
            'label' => 'QUANTUM (Pro)',
            'pro' => true,
            'models' => [
                ['id' => 'quantum/gpt-oss-120b-ionq', 'label' => 'QuantumZero: GPT-OSS 120B + IonQ'],
            ],
        ],
        'quantum_ibm' => [
            'label' => 'IBM QUANTUM (Pro)',
            'pro' => true,
            'models' => [
                ['id' => 'quantum/qiskit-ibm-groq', 'label' => 'IBM Qiskit + Groq'],
            ],
        ],
    ];
}

function zt_model_ids(string $provider): array
{
    $group = zt_model_catalog()[$provider] ?? [];
    return array_values(array_map(static fn(array $model): string => $model['id'], $group['models'] ?? []));
}

function zt_model_options_html(?array $providers = null, bool $isPro = true): string
{
    $catalog = zt_model_catalog();
    $providers = $providers ?? array_keys($catalog);
    $html = [];

    foreach ($providers as $provider) {
        if (empty($catalog[$provider])) {
            continue;
        }
        $group = $catalog[$provider];
        $disabled = !empty($group['pro']) && !$isPro ? ' disabled' : '';
        $html[] = '<optgroup label="' . htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') . '"' . $disabled . '>';
        foreach ($group['models'] as $model) {
            $value = $provider . ':' . $model['id'];
            $html[] = '<option data-provider="' . htmlspecialchars($provider, ENT_QUOTES, 'UTF-8')
                . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($model['label'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $html[] = '</optgroup>';
    }

    return implode("\n", $html);
}
