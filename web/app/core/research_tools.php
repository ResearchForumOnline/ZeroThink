<?php
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') {
            return true;
        }
        $length = strlen($needle);
        return substr($haystack, -$length) === $needle;
    }
}

function zt_app_root(): string {
    return realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2);
}

function zt_load_env_file(string $path): array {
    if (!is_file($path)) {
        return [];
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '') {
            $values[$key] = $value;
        }
    }
    return $values;
}

function zt_env_value(string $key, ?string $default = null): ?string {
    $direct = getenv($key);
    if ($direct !== false && trim((string)$direct) !== '') {
        return trim((string)$direct);
    }

    static $env = null;
    if ($env === null) {
        $root = zt_app_root();
        $env = array_merge(
            zt_load_env_file($root . '/.env'),
            zt_load_env_file(dirname($root) . '/zerothinks')
        );
    }

    return isset($env[$key]) && trim((string)$env[$key]) !== '' ? trim((string)$env[$key]) : $default;
}

function zt_private_storage_root(): string {
    $configured = zt_env_value('ZT_PRIVATE_STORAGE_ROOT');
    $root = $configured ?: dirname(zt_app_root()) . '/.zerothink_storage';
    if (!is_dir($root)) {
        mkdir($root, 0750, true);
    }
    return $root;
}

function zt_private_storage_dir(string $name): string {
    $safeName = trim(str_replace('\\', '/', $name), '/');
    $dir = zt_private_storage_root() . '/' . $safeName;
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

function zt_serper_key(?string $explicit = null): ?string {
    if ($explicit !== null && trim($explicit) !== '') {
        return trim($explicit);
    }
    return zt_env_value('SERPER_API_KEY');
}

function zt_provenance_dir(): string {
    return zt_private_storage_dir('provenance');
}

function zt_append_evidence_ledger(array $entry): void {
    $entry['recorded_at'] = gmdate('c');
    $path = zt_provenance_dir() . '/evidence-ledger.jsonl';
    file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function zt_serper_setup_message(): string {
    return 'Serper web search is not configured. Add SERPER_API_KEY=your_key_here to .env, or pass a Serper key from the ZeroThink Vault, then retry.';
}

function zt_normalise_serper_results(array $payload): array {
    $items = [];
    foreach (($payload['organic'] ?? []) as $row) {
        $url = (string)($row['link'] ?? $row['url'] ?? '');
        $host = $url ? (parse_url($url, PHP_URL_HOST) ?: 'web') : 'web';
        $items[] = [
            'title' => (string)($row['title'] ?? 'Untitled result'),
            'url' => $url,
            'snippet' => (string)($row['snippet'] ?? ''),
            'source' => $host,
            'timestamp' => (string)($row['date'] ?? $row['publishedDate'] ?? ''),
        ];
    }
    return $items;
}

function zt_serper_search(string $query, ?string $apiKey = null, array $context = []): array {
    $key = zt_serper_key($apiKey);
    if (!$key) {
        return [
            'ok' => false,
            'message' => zt_serper_setup_message(),
            'results' => [],
        ];
    }

    $ch = curl_init('https://google.serper.dev/search');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['X-API-KEY: ' . $key, 'Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['q' => $query, 'num' => 8]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err) {
        return [
            'ok' => false,
            'message' => 'Serper search failed: ' . ($err ?: 'network error'),
            'results' => [],
        ];
    }

    $payload = json_decode((string)$raw, true);
    if ($http >= 400 || !is_array($payload)) {
        return [
            'ok' => false,
            'message' => 'Serper search returned an error. Check SERPER_API_KEY and account quota.',
            'results' => [],
        ];
    }

    $results = zt_normalise_serper_results($payload);
    zt_append_evidence_ledger([
        'type' => 'serper_search',
        'query' => $query,
        'context' => $context,
        'results' => $results,
    ]);

    return [
        'ok' => true,
        'message' => 'Serper search completed.',
        'results' => $results,
    ];
}

function zt_format_search_results_for_prompt(string $query, array $search): string {
    if (!$search['ok']) {
        return '[SERPER WEB SEARCH SETUP REQUIRED] ' . $search['message'];
    }

    $out = "LIVE SERPER SEARCH RESULTS FOR: {$query}\n";
    foreach ($search['results'] as $i => $result) {
        $n = $i + 1;
        $stamp = $result['timestamp'] ? " | timestamp: {$result['timestamp']}" : '';
        $out .= "{$n}. {$result['title']}\n";
        $out .= "   URL: {$result['url']}\n";
        $out .= "   Source: {$result['source']}{$stamp}\n";
        $out .= "   Snippet: {$result['snippet']}\n";
    }
    return $out;
}

function zt_default_research_templates(): array {
    return [
        [
            'name' => 'Systematic Review Extension',
            'description' => 'Extend an existing survey with newly published papers while preserving the original review structure.',
            'stages' => ['Define survey scope', 'Import existing review structure', 'Search new papers', 'Cluster papers by section', 'Map claims to evidence', 'Identify gaps and contradictions', 'Human validation', 'Generate updated review draft'],
            'required_sources' => ['Existing survey', 'New literature search results', 'Full-text papers where available'],
            'validation_checks' => ['Every new claim has a source', 'Section mapping is reviewed by a human', 'Contradictions are logged before synthesis'],
            'output_format' => 'Evidence map plus updated review outline',
            'provenance_requirements' => 'Source ledger with URL, title, snippet, source, retrieval timestamp, and paper metadata where available',
            'export_option' => 'Markdown, DOCX, PDF, or LaTeX where supported',
            'provenance_required' => true,
            'human_approval_required' => true,
        ],
        [
            'name' => 'Narrative Review Mapping',
            'description' => 'Map a broad topic into themes, debates, source clusters, and open questions.',
            'stages' => ['Topic intake', 'Landscape search', 'Theme clustering', 'Evidence map', 'Narrative outline', 'Human validation'],
            'required_sources' => ['Search results', 'Representative papers', 'Researcher notes'],
            'validation_checks' => ['Theme labels are inspectable', 'Important counterarguments are retained', 'Weakly sourced claims are flagged'],
            'output_format' => 'Theme map and narrative review plan',
            'provenance_requirements' => 'Ledger entries for each source cluster',
            'export_option' => 'Markdown or DOCX',
            'provenance_required' => true,
            'human_approval_required' => true,
        ],
        [
            'name' => 'Topic Expert Finder',
            'description' => 'Identify authors, labs, institutions, and reviewers connected to a topic through literature evidence.',
            'stages' => ['Define topic', 'Search papers', 'Extract authors and affiliations', 'Rank by relevance and recency', 'Human review'],
            'required_sources' => ['Paper metadata', 'Author pages if available', 'Citation context'],
            'validation_checks' => ['No person is labelled an expert without source evidence', 'Institutional wording remains careful', 'Conflicts and uncertainty are visible'],
            'output_format' => 'Expert/topic table with source links',
            'provenance_requirements' => 'Source ledger for every expert recommendation',
            'export_option' => 'CSV or Markdown',
            'provenance_required' => true,
            'human_approval_required' => true,
        ],
        [
            'name' => 'Reviewer Finder',
            'description' => 'Suggest potential reviewers from topic literature while keeping conflicts and uncertainty visible.',
            'stages' => ['Topic scope', 'Literature search', 'Candidate extraction', 'Conflict notes', 'Human shortlist'],
            'required_sources' => ['Recent papers', 'Author metadata', 'Researcher constraints'],
            'validation_checks' => ['Conflict risks are listed', 'Candidates are not overclaimed', 'Final shortlist needs human approval'],
            'output_format' => 'Reviewer shortlist with evidence notes',
            'provenance_requirements' => 'Citation-backed rationale for each candidate',
            'export_option' => 'Markdown or CSV',
            'provenance_required' => true,
            'human_approval_required' => true,
        ],
        [
            'name' => 'Supervisor Finder',
            'description' => 'Help students move from a research interest to potential supervisor topics and evidence-backed candidate areas.',
            'stages' => ['Interest intake', 'Topic expansion', 'Literature search', 'Potential supervisor/topic mapping', 'Student preference review'],
            'required_sources' => ['Topic literature', 'Public profile pages if available', 'Student constraints'],
            'validation_checks' => ['No official availability is assumed', 'Recommendations remain evidence-based', 'Student confirms fit before outreach'],
            'output_format' => 'Supervisor/topic evidence map',
            'provenance_requirements' => 'Links and source notes for each suggestion',
            'export_option' => 'Markdown',
            'provenance_required' => true,
            'human_approval_required' => true,
        ],
        [
            'name' => 'Evidence Gap Mapping',
            'description' => 'Expose gaps, contradictions, weak evidence areas, and possible future study directions.',
            'stages' => ['Scope definition', 'Search and retrieval', 'Evidence clustering', 'Gap and contradiction pass', 'Researcher validation'],
            'required_sources' => ['Search results', 'Paper extracts', 'Claim ledger'],
            'validation_checks' => ['Gaps are tied to evidence', 'Speculation is labelled', 'Contradictions are not smoothed away'],
            'output_format' => 'Gap matrix and next-study notes',
            'provenance_requirements' => 'Evidence ledger linked to every gap',
            'export_option' => 'Markdown or CSV',
            'provenance_required' => true,
            'human_approval_required' => true,
        ],
        [
            'name' => 'Provenance-Aware Survey Expansion',
            'description' => 'Take a small set of seed articles or research papers, expand the survey with a claim/evidence/provenance graph, then plan constrained optimisation tests on the evidence structure.',
            'stages' => ['Seed article intake', 'Original survey structure capture', 'New literature search', 'Claim extraction', 'Claim/evidence/provenance graph', 'Source ledger validation', 'Classical baseline', 'Quantum or simulator candidate test plan', 'Comparison and auditability report', 'Human approval before drafting'],
            'required_sources' => ['Seed articles or abstracts', 'DOI/URL/BibTeX metadata where available', 'New literature search results', 'Researcher notes', 'Full-text papers where available'],
            'validation_checks' => ['Every claim maps to a source or is marked as a candidate lead', 'Search snippets are not treated as full-text evidence', 'Contradictions and gaps are retained', 'Classical baselines are defined before quantum or simulator tests', 'Quantum/simulator tasks are limited to evidence-path selection, clustering, routing, prioritisation, and auditability', 'No claim is made that quantum computers understand documents', 'Human approval is required before drafting'],
            'output_format' => 'Evidence graph, source ledger, optimisation test plan, and survey expansion outline',
            'provenance_requirements' => 'Node and edge ledger with source status, DOI/URL where available, retrieval trail, confidence, contradiction flags, and audit notes',
            'export_option' => 'Markdown, JSON, CSV, PDF, or graph table export where supported',
            'provenance_required' => true,
            'human_approval_required' => true,
        ],
        [
            'name' => 'Human-Agent Validation',
            'description' => 'A researcher-in-the-loop validation workflow for checking retrieved evidence before synthesis.',
            'stages' => ['Source intake', 'Claim extraction', 'Provenance check', 'Researcher correction', 'Approval before synthesis'],
            'required_sources' => ['Retrieved papers', 'Claim ledger', 'Human notes'],
            'validation_checks' => ['Claims are inspectable', 'Corrections are logged', 'No final synthesis without approval'],
            'output_format' => 'Validated claim ledger',
            'provenance_requirements' => 'Full source and correction trail',
            'export_option' => 'Markdown, JSON, or CSV',
            'provenance_required' => true,
            'human_approval_required' => true,
        ],
        [
            'name' => 'Research Paper Creator',
            'description' => 'Evidence-first research drafting with a search protocol, access-status checks, source ledgers, critique passes, and human approval before synthesis.',
            'stages' => ['Topic intake', 'Research question refinement', 'Search protocol and database selection', 'Literature search', 'Duplicate removal and screening', 'Paper retrieval', 'Full-text access-status check', 'Evidence map', 'Claim ledger', 'Outline generation', 'Argument and counterargument pass', 'Methodology helper', 'Related work builder', 'Gap analysis', 'Draft generation', 'Reviewer-style critique', 'Revision planning', 'Export'],
            'required_sources' => ['Search results', 'Full-text papers where available', 'Researcher notes', 'Citation metadata'],
            'validation_checks' => ['Each claim maps to evidence', 'Search snippets are not treated as full-text evidence', 'Citation metadata and access status are visible', 'Unsupported quotations, statistics, and causal claims are flagged', 'Gaps are visible', 'Critique happens before final synthesis', 'Researcher approves each stage'],
            'output_format' => 'Research draft with source ledger and revision plan',
            'provenance_requirements' => 'Inspectable route from question to paper',
            'export_option' => 'Markdown, DOCX, PDF, or LaTeX where supported',
            'provenance_required' => true,
            'human_approval_required' => true,
        ],
        [
            'name' => 'Custom User Template',
            'description' => 'A user-defined research scaffold with stages, validation checks, provenance requirements, and export preferences.',
            'stages' => ['Define purpose', 'Add stages', 'Set required sources', 'Set validation checks', 'Choose output format', 'Review and export'],
            'required_sources' => ['User-defined'],
            'validation_checks' => ['User-defined checks are visible', 'Human approval is explicit'],
            'output_format' => 'User-defined',
            'provenance_requirements' => 'User-defined ledger requirements',
            'export_option' => 'Markdown or JSON',
            'provenance_required' => true,
            'human_approval_required' => true,
        ],
    ];
}

function zt_research_template_storage_path(int $userId = 0): string {
    $dir = zt_private_storage_dir('research_templates');
    return $dir . '/user_' . max(0, $userId) . '.json';
}

function zt_load_user_research_templates(int $userId = 0): array {
    if ($userId <= 0) {
        return [];
    }
    $path = zt_research_template_storage_path($userId);
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function zt_research_limited_text($value, int $maxChars): string {
    $text = trim(str_replace("\0", '', (string)$value));
    if ($maxChars > 0 && strlen($text) > $maxChars) {
        $text = substr($text, 0, $maxChars);
    }
    return $text;
}

function zt_research_limited_list($value, int $maxItems = 30, int $maxChars = 240): array {
    if (is_string($value)) {
        $value = preg_split('/\r?\n/', $value) ?: [];
    }
    if (!is_array($value)) {
        return [];
    }

    $items = [];
    foreach ($value as $item) {
        if (count($items) >= $maxItems) {
            break;
        }
        if (is_array($item)) {
            $item = json_encode($item, JSON_UNESCAPED_SLASHES);
        }
        $text = zt_research_limited_text($item, $maxChars);
        if ($text !== '') {
            $items[] = $text;
        }
    }
    return $items;
}

function zt_save_user_research_template(array $template, int $userId = 0): array {
    if ($userId <= 0) {
        throw new InvalidArgumentException('Sign in before saving custom research templates.');
    }

    $allowed = [
        'name', 'description', 'stages', 'required_sources', 'validation_checks',
        'output_format', 'provenance_requirements', 'export_option',
        'provenance_required', 'human_approval_required',
    ];
    $clean = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $template)) {
            $clean[$key] = $template[$key];
        }
    }
    $clean['name'] = zt_research_limited_text($clean['name'] ?? 'Custom User Template', 96);
    $clean['description'] = zt_research_limited_text($clean['description'] ?? 'User-facing research scaffold.', 1000);
    foreach (['stages', 'required_sources', 'validation_checks'] as $listKey) {
        $clean[$listKey] = zt_research_limited_list($clean[$listKey] ?? [], 30, 240);
    }
    $clean['output_format'] = zt_research_limited_text($clean['output_format'] ?? 'Markdown', 180);
    $clean['provenance_requirements'] = zt_research_limited_text($clean['provenance_requirements'] ?? 'Visible source ledger.', 800);
    $clean['export_option'] = zt_research_limited_text($clean['export_option'] ?? 'Markdown or JSON', 180);
    $clean['provenance_required'] = (bool)($clean['provenance_required'] ?? true);
    $clean['human_approval_required'] = (bool)($clean['human_approval_required'] ?? true);
    $clean['created_at'] = gmdate('c');

    $templates = zt_load_user_research_templates($userId);
    $templates[] = $clean;
    $templates = array_slice($templates, -30);
    file_put_contents(zt_research_template_storage_path($userId), json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $clean;
}
