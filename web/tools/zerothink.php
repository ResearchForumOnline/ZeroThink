#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/research_tools.php';

function zt_cli_out(string $text = ''): void {
    fwrite(STDOUT, $text . PHP_EOL);
}

function zt_cli_usage(): void {
    zt_cli_out('ZeroThink CLI');
    zt_cli_out('');
    zt_cli_out('Commands:');
    zt_cli_out('  zerothink search "generative UI topic expert literature review"');
    zt_cli_out('  zerothink search the web for "generative UI topic experts"');
    zt_cli_out('  zerothink do a web search for "AI co-scientist literature"');
    zt_cli_out('  zerothink research --topic "AI co-scientist literature discovery" --web serper');
    zt_cli_out('  zerothink research "AI co-scientist literature discovery" with web search');
    zt_cli_out('  zerothink paper --topic "AI co-scientist literature discovery"');
    zt_cli_out('  zerothink survey --topic "research-agent evaluation methods"');
    zt_cli_out('  Downloadable CLI: zerothink research paper "topic" --source ./papers --stage outline');
    zt_cli_out('  zerothink nottingham --topic "survey extension generative UI" --serper');
    zt_cli_out('  zerothink quantumzero');
    zt_cli_out('  zerothink templates');
    zt_cli_out('');
    zt_cli_out('Serper setup: add SERPER_API_KEY=your_key_here to .env in the ZeroThink root.');
}

function zt_cli_arg_value(array $args, string $name): ?string {
    $index = array_search($name, $args, true);
    if ($index !== false && isset($args[$index + 1])) {
        return (string)$args[$index + 1];
    }
    foreach ($args as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return null;
}

function zt_cli_normalize_space(string $text): string {
    return trim((string)preg_replace('/\s+/', ' ', $text));
}

function zt_cli_words(string $text): array {
    return preg_split('/\s+/', zt_cli_normalize_space($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function zt_cli_clean_search_query(string $query): string {
    $query = zt_cli_normalize_space(trim($query, " \t\n\r\0\x0B\"'"));
    $patterns = [
        '/^(the\s+)?web\s*(for|about|on)?\s*/i',
        '/^(please\s+)?(can\s+you\s+|could\s+you\s+)?(do\s+)?(a\s+)?(web\s+search|search\s+the\s+web|search\s+web|internet\s+search|search|google|look\s+up|find\s+online)\s*(for|about|on)?\s*/i',
        '/^(please\s+)?(find|discover)\s+(papers|sources|articles|experts|reviewers|supervisors)\s*(for|about|on)?\s*/i',
    ];
    foreach ($patterns as $pattern) {
        $query = zt_cli_normalize_space((string)preg_replace($pattern, '', $query, 1));
    }
    return trim($query, " \t\n\r\0\x0B\"'");
}

function zt_cli_clean_research_topic(string $topic): string {
    $topic = zt_cli_normalize_space(trim($topic, " \t\n\r\0\x0B\"'"));
    $topic = (string)preg_replace('/^(please\s+)?(can\s+you\s+|could\s+you\s+)?(do\s+)?(a\s+)?(research\s+plan|staged\s+research|research|do\s+research|make\s+a\s+research\s+plan)\s*(for|about|on)?\s*/i', '', $topic, 1);
    $topic = (string)preg_replace('/\s+(with|using)\s+(web\s+search|serper|internet\s+search|online\s+search)$/i', '', $topic);
    return trim(zt_cli_normalize_space($topic), " \t\n\r\0\x0B\"'");
}

function zt_cli_phrase_requests_web(string $raw): bool {
    return (bool)preg_match('/(--serper|--web\s+serper|\bwith\s+(web|internet|online)\s+search\b|\busing\s+(serper|web\s+search|internet\s+search)\b)/i', $raw);
}

function zt_cli_resolve_natural(string $raw, string $command, array $args): array {
    $raw = zt_cli_normalize_space($raw);
    $lower = strtolower($raw);
    $known = ['search', 'research', 'paper', 'article', 'survey', 'review', 'proposal', 'nottingham', 'templates', 'help', '-h', '--help', 'quantumzero', 'quantum', 'quantumlab'];

    if ($command === 'web' && strtolower((string)($args[0] ?? '')) === 'search') {
        array_shift($args);
        return ['search', [zt_cli_clean_search_query(implode(' ', $args))]];
    }

    if (in_array($command, $known, true)) {
        return [$command, $args];
    }

    if (preg_match('/^(please\s+)?(show|list)\s+(research\s+)?templates\b/i', $raw)) {
        return ['templates', []];
    }

    if (preg_match('/^(please\s+)?(open\s+)?(quantum\s*zero|quantumzero|quantum\s+lab|quantumlab)\b/i', $raw)) {
        return [str_contains($lower, 'lab') ? 'quantumlab' : 'quantumzero', []];
    }

    if (preg_match('/^(please\s+)?(can\s+you\s+|could\s+you\s+)?(do\s+)?(a\s+)?(web\s+search|search\s+the\s+web|search\s+web|internet\s+search|google|look\s+up|find\s+online)\b/i', $raw)
        || preg_match('/^(please\s+)?(find|discover)\s+(papers|sources|articles|experts|reviewers|supervisors)\b/i', $raw)
        || preg_match('/^(please\s+)?literature\s+search\b/i', $raw)) {
        return ['search', [zt_cli_clean_search_query($raw)]];
    }

    if (preg_match('/^(please\s+)?(nottingham|university\s+of\s+nottingham)\b/i', $raw)) {
        $topic = (string)preg_replace('/^(please\s+)?(nottingham|university\s+of\s+nottingham)\s*(research|workflow|survey|topic|expert|paper|plan)?\s*(for|about|on)?\s*/i', '', $raw, 1);
        $args = [zt_cli_clean_research_topic($topic)];
        if (zt_cli_phrase_requests_web($raw)) {
            $args[] = '--serper';
        }
        return ['nottingham', $args];
    }

    if (preg_match('/^(please\s+)?(can\s+you\s+|could\s+you\s+)?(do\s+)?(a\s+)?(research\s+plan|staged\s+research|research|do\s+research|make\s+a\s+research\s+plan)\b/i', $raw)) {
        $args = [zt_cli_clean_research_topic($raw)];
        if (zt_cli_phrase_requests_web($raw)) {
            $args[] = '--serper';
        }
        return ['research', $args];
    }

    return [$command, $args];
}

function zt_cli_format_results(array $search): void {
    if (!$search['ok']) {
        zt_cli_out($search['message']);
        return;
    }

    foreach ($search['results'] as $i => $result) {
        $n = $i + 1;
        $timestamp = $result['timestamp'] ? " | timestamp: {$result['timestamp']}" : '';
        zt_cli_out("{$n}. {$result['title']}");
        zt_cli_out("   URL: {$result['url']}");
        zt_cli_out("   Source: {$result['source']}{$timestamp}");
        zt_cli_out("   Snippet: {$result['snippet']}");
    }
    zt_cli_out('');
    zt_cli_out('Stored in the private server-side evidence ledger.');
}

$args = $argv;
array_shift($args);
$rawInput = zt_cli_normalize_space(implode(' ', $args));
$command = array_shift($args) ?: '';
[$command, $args] = zt_cli_resolve_natural($rawInput, strtolower($command), $args);

if ($command === '' || in_array($command, ['-h', '--help', 'help'], true)) {
    zt_cli_usage();
    exit(0);
}

if ($command === 'templates') {
    foreach (zt_default_research_templates() as $template) {
        zt_cli_out('- ' . $template['name'] . ': ' . $template['description']);
    }
    exit(0);
}

if ($command === 'quantumzero' || $command === 'quantum' || $command === 'quantumlab') {
    zt_cli_out('QuantumZero routes');
    zt_cli_out('  Chat: https://zerothink.talktoai.org/quantumzero');
    zt_cli_out('  Lab: https://zerothink.talktoai.org/quantumlab');
    zt_cli_out('  Python agent: https://zerothink.talktoai.org/quantumzero-python/');
    zt_cli_out('');
    zt_cli_out('QuantumZero keeps real quantum evidence separate from symbolic or research interpretation.');
    exit(0);
}

if ($command === 'search') {
    $query = zt_cli_clean_search_query(implode(' ', $args));
    if ($query === '') {
        zt_cli_out('Missing query. Example: zerothink search the web for "generative UI topic expert literature review"');
        exit(1);
    }
    $search = zt_serper_search($query, null, [
        'surface' => 'cli',
        'plan_stage' => 'web_search',
        'workflow' => 'standalone_search',
    ]);
    zt_cli_format_results($search);
    exit($search['ok'] ? 0 : 2);
}

if (in_array($command, ['research', 'paper', 'article', 'survey', 'review', 'proposal', 'nottingham'], true)) {
    $researchKind = in_array($command, ['paper', 'article', 'survey', 'review', 'proposal'], true) ? $command : 'research plan';
    $topic = zt_cli_arg_value($args, '--topic') ?? trim(implode(' ', array_filter($args, function ($arg) {
        return !str_starts_with((string)$arg, '--');
    })));
    $topic = in_array($command, ['paper', 'article', 'survey', 'review', 'proposal'], true)
        ? trim(zt_cli_normalize_space((string)$topic), " \t\n\r\0\x0B\"'")
        : zt_cli_clean_research_topic((string)$topic);
    if ($topic === '') {
        zt_cli_out('Missing topic. Example: zerothink nottingham --topic "survey extension generative UI" --serper');
        exit(1);
    }

    $useSerper = in_array('--serper', $args, true) || (zt_cli_arg_value($args, '--web') === 'serper') || zt_cli_phrase_requests_web($rawInput);
    $workflow = $command === 'nottingham' ? 'Literature Research Collaboration Workflow' : 'Staged ' . ucfirst($researchKind) . ' Workflow';

    zt_cli_out($workflow);
    zt_cli_out('Topic: ' . $topic);
    zt_cli_out('');
    zt_cli_out('Stages:');
    foreach ([
        'Topic intake and research question refinement',
        'Literature search and paper retrieval',
        'Full-text processing where available',
        'Evidence map and claim ledger',
        'Source-status and citation verification checks',
        'Topic expert or reviewer/supervisor finder if requested',
        'Human-agent validation before synthesis',
        'Draft, critique, revision, and export planning',
    ] as $stage) {
        zt_cli_out('- ' . $stage);
    }

    if ($command === 'nottingham') {
        zt_cli_out('');
        zt_cli_out('Immediate Nottingham focus: survey extension, topic expert identification, and generative UI.');
        zt_cli_out('Long-term direction: AI co-scientist workflows after stronger search, retrieval, evidence mapping, provenance, and validation.');
    }

    if ($useSerper) {
        zt_cli_out('');
        zt_cli_out('Serper evidence search:');
        $search = zt_serper_search($topic, null, [
            'surface' => 'cli',
            'plan_stage' => 'staged_research_search',
            'workflow' => $workflow,
        ]);
        zt_cli_format_results($search);
        exit($search['ok'] ? 0 : 2);
    }

    zt_cli_out('');
    zt_cli_out('For local folders or saved-server files, use the downloadable CLI v3.7.0 research command family shown on /cli.');
    zt_cli_out('Web search not run. Add --web serper or --serper to store live evidence in the provenance ledger.');
    exit(0);
}

zt_cli_usage();
exit(1);
