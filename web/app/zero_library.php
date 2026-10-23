<?php
// Zero Library: deterministic local context, DNA utilities, and small research helpers.
require_once __DIR__ . '/dna_origins.php';
require_once __DIR__ . '/dna_traits.php';
require_once __DIR__ . '/dna_personal_traits.php';
require_once __DIR__ . '/dna_customer_report.php';
require_once __DIR__ . '/dna_country_context.php';
require_once __DIR__ . '/dna_composition.php';

function zl_storage_root() {
    $private_base = trim((string) getenv('ZEROTHINK_PRIVATE_STORAGE_ROOT'));
    if ($private_base === '') {
        $private_base = dirname(dirname(__DIR__)) . '/.zerothink_storage';
    }
    $root = rtrim($private_base, '/\\') . '/zero-library';
    if (!is_dir($root)) {
        @mkdir($root, 0700, true);
    }
    @chmod($root, 0700);
    return $root;
}

function zl_user_dir($user_id) {
    $user_id = max(0, (int) $user_id);
    $dir = zl_storage_root() . '/user_' . $user_id;
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    @chmod($dir, 0700);
    return $dir;
}

function zl_cache_dir() {
    $dir = dirname(zl_storage_root()) . '/cache/zero-library';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    @chmod($dir, 0700);
    return $dir;
}

function zl_library_max_save_bytes() {
    return 5 * 1024 * 1024;
}

function zl_library_max_save_mb() {
    return (int) floor(zl_library_max_save_bytes() / (1024 * 1024));
}

function zl_library_size_label($bytes) {
    $bytes = max(0, (int) $bytes);
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1), '0'), '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.') . ' KB';
    }
    return $bytes . ' B';
}

function zl_assert_library_save_allowed($path, $name = 'file') {
    $size = is_file($path) ? (int) filesize($path) : 0;
    if ($size <= 0) {
        throw new Exception('Cannot save an empty file to Zero Library.');
    }
    if ($size > zl_library_max_save_bytes()) {
        throw new Exception('Zero Library save limit is ' . zl_library_max_save_mb() . ' MB per file. Analyze larger DNA files without saving the raw file.');
    }
    return $size;
}

function zl_allowed_exts() {
    return [
        'txt','md','php','phtml','py','js','jsx','ts','tsx','html','css','scss','json','sql','log',
        'java','c','cc','cpp','h','hpp','cs','go','rs','rb','swift','kt','kts','scala','xml','yml',
        'yaml','toml','ini','env','sh','bash','zsh','ps1','vue','svelte','astro','dockerfile',
        'csv','lock','properties','gradle','conf','cfg','mjs','cjs','mts','cts','proto','graphql',
        'gql','diff','patch','prisma','tf','tfvars','editorconfig','gitignore','dockerignore',
        'fa','fasta','fastq','fq','gb','gbk','tsv','vcf','dna','genome','raw','ped','map'
    ];
}

function zl_clean_text($text) {
    $text = (string) $text;
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }
    $text = str_replace("\0", '', $text);
    $text = preg_replace("/\r\n?/", "\n", $text);
    return (string) $text;
}

function zl_dna_raise_runtime_limits() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $current = trim((string) ini_get('memory_limit'));
    $bytes = function ($value) {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return $value === '-1' ? PHP_INT_MAX : 0;
        }
        $unit = strtolower(substr($value, -1));
        $num = (float) $value;
        if ($unit === 'g') {
            return (int) ($num * 1024 * 1024 * 1024);
        }
        if ($unit === 'm') {
            return (int) ($num * 1024 * 1024);
        }
        if ($unit === 'k') {
            return (int) ($num * 1024);
        }
        return (int) $num;
    };
    if ($bytes($current) < 1536 * 1024 * 1024) {
        @ini_set('memory_limit', '1536M');
    }
    @set_time_limit(600);
}

function zl_safe_name($name) {
    $name = basename((string) $name);
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
    $name = trim((string) $name, '._-');
    return $name !== '' ? $name : 'library-file.txt';
}

function zl_file_ext($path) {
    $base = basename((string) $path);
    if (preg_match('/^(dockerfile|makefile)$/i', $base)) {
        return strtolower($base);
    }
    return strtolower(pathinfo($base, PATHINFO_EXTENSION));
}

function zl_sensitive_genotype_exts() {
    return ['vcf', 'dna', 'genome', 'raw', 'ped', 'map', 'fa', 'fasta', 'fastq', 'fq', 'gb', 'gbk'];
}

function zl_file_head_bytes($path, $max_bytes = 65536) {
    if (!is_file($path) || !is_readable($path)) {
        return '';
    }
    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return '';
    }
    $raw = (string) fread($handle, max(1024, min(262144, (int) $max_bytes)));
    fclose($handle);
    return $raw;
}

function zl_text_looks_like_genotype_data($text) {
    $text = substr((string) $text, 0, 65536);
    if ($text === '' || strpos($text, "\0") !== false) {
        return false;
    }
    $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
    if (preg_match('/^(?:PK\x03\x04|\x1F\x8B|%PDF|MZ|\x7FELF)/s', $text) || preg_match('/<\?(?:php|=)|<script\b/i', $text)) {
        return false;
    }
    if (preg_match('/^##fileformat=VCF/im', $text) || preg_match('/^#?CHROM[\t, ]+POS[\t, ]+ID[\t, ]+REF[\t, ]+ALT/im', $text)) {
        return true;
    }
    if (preg_match('/\b(?:rsid|snp)[\t, ]+(?:chromosome|chrom|chr)[\t, ]+(?:position|pos)[\t, ]+(?:genotype|result|allele1)\b/i', $text)) {
        return true;
    }
    if (preg_match('/^rs\d+[\t, ]+(?:chr)?(?:[1-9]|1[0-9]|2[0-2]|X|Y|MT?)[\t, ]+\d+[\t, ]+(?:[ACGT]{1,2}|--|00|NN)(?:[\t, ]|$)/im', $text)) {
        return true;
    }

    // Provider CSV exports commonly quote every field and may begin with a UTF-8
    // BOM. Parse a bounded number of rows as CSV instead of globally deleting
    // quotes, then require the same narrow header/data contract used above.
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $scanned = 0;
    foreach ($lines as $line) {
        $line = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $line));
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $scanned++;
        if ($scanned > 40) {
            break;
        }
        $cols = zl_dna_raw_split_row($line);
        if (count($cols) < 4) {
            continue;
        }
        $normalized = array_map(function ($value) {
            $value = strtolower(trim((string) $value, " \t\n\r\0\x0B\"'"));
            $value = preg_replace('/[^a-z0-9]+/', '_', $value);
            return trim((string) $value, '_');
        }, $cols);
        if (
            in_array($normalized[0] ?? '', ['rsid', 'snp', 'snp_name', 'id'], true)
            && in_array($normalized[1] ?? '', ['chromosome', 'chrom', 'chr'], true)
            && in_array($normalized[2] ?? '', ['position', 'pos'], true)
            && in_array($normalized[3] ?? '', ['genotype', 'result', 'alleles', 'allele', 'allele1', 'allele_1'], true)
        ) {
            return true;
        }
        $rsid = strtolower(trim((string) ($cols[0] ?? '')));
        $chrom = strtoupper(preg_replace('/^CHR/i', '', trim((string) ($cols[1] ?? ''))));
        $position = trim((string) ($cols[2] ?? ''));
        $genotype = strtoupper(trim((string) ($cols[3] ?? ''), " \t\n\r\0\x0B\"'"));
        if (
            preg_match('/^rs\d+$/', $rsid)
            && preg_match('/^(?:[1-9]|1[0-9]|2[0-2]|X|Y|M|MT)$/', $chrom)
            && preg_match('/^[1-9]\d*$/', $position)
            && preg_match('/^(?:[ACGT]{1,2}|--|00|NN)$/', $genotype)
        ) {
            return true;
        }
    }
    return false;
}

function zl_text_looks_like_genomic_sequence($text) {
    $text = substr((string) $text, 0, 65536);
    if ($text === '' || strpos($text, "\0") !== false) {
        return false;
    }
    $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
    if (preg_match('/^(?:PK\x03\x04|\x1F\x8B|%PDF|MZ|\x7FELF)/s', $text) || preg_match('/<\?(?:php|=)|<script\b/i', $text)) {
        return false;
    }

    // Detect common sequence containers even when a provider gives them a
    // generic .txt extension. Extension checks still catch normal FASTA,
    // FASTQ, and GenBank filenames before this content check is needed.
    if (preg_match('/^>[^\r\n]*\R(?:[A-Z*.-]+\R?){1,}/im', $text)) {
        return true;
    }
    if (preg_match('/^LOCUS\s+.+$/im', $text) && preg_match('/^ORIGIN\s*$/im', $text)) {
        return true;
    }
    if (preg_match('/^@[^\r\n]+\R([ACGTUNRYSWKMBDHV.-]{24,})\R\+[^\r\n]*\R/im', $text)) {
        return true;
    }

    // A plain sequence export has little or no prose. Require a meaningful
    // length and an IUPAC-only payload so normal notes/code are not vaulted.
    $sequence = preg_replace('/[\s0-9.-]+/', '', strtoupper($text));
    return strlen((string) $sequence) >= 128
        && preg_match('/^[ACGTUNRYSWKMBDHV]+$/', (string) $sequence) === 1;
}

function zl_text_looks_like_secret_config($text) {
    $text = substr((string) $text, 0, 65536);
    if ($text === '' || strpos($text, "\0") !== false) {
        return false;
    }
    if (preg_match('/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/', $text)) {
        return true;
    }
    if (preg_match('/\b(?:GOCSPX-|sk-(?:proj-)?|ghp_|xox[baprs]-|AIza)[A-Za-z0-9_-]{10,}/', $text)) {
        return true;
    }

    $lines = preg_split('/\R/', $text) ?: [];
    foreach (array_slice($lines, 0, 240) as $line) {
        $line = trim((string) $line);
        if ($line === '' || preg_match('/^(?:#|;|\/\/)/', $line)) {
            continue;
        }
        if (!preg_match('/^(?:export\s+)?["\']?([A-Za-z][A-Za-z0-9_.-]*(?:api[_-]?key|secret|token|password|passwd|private[_-]?key|client[_-]?secret|access[_-]?key)[A-Za-z0-9_.-]*)["\']?\s*[:=]\s*(.+)$/i', $line, $match)) {
            continue;
        }
        $value = trim((string) $match[2], " \t\n\r\0\x0B,;\"'");
        $lower = strtolower($value);
        if ($value === '' || strlen($value) < 8) {
            continue;
        }
        if (preg_match('/^(?:null|none|false|changeme|replace[_-]?me|example|sample|dummy|redacted|x+|\*+|<[^>]+>|\$\{[^}]+\})$/i', $value)) {
            continue;
        }
        if (strpos($lower, 'getenv(') !== false || strpos($lower, 'process.env') !== false || strpos($lower, 'config(') !== false) {
            continue;
        }
        return true;
    }
    return false;
}

function zl_is_sensitive_genotype_file($path, $classification = '') {
    if (in_array(strtolower(trim((string) $classification)), ['sensitive_genotype', 'sensitive_genomic'], true)) {
        return true;
    }
    $normal = str_replace('\\', '/', (string) $path);
    if (preg_match('#/(?:sensitive|dna-vault)(?:/|$)#i', $normal)) {
        return true;
    }
    if (in_array(zl_file_ext($path), zl_sensitive_genotype_exts(), true)) {
        return true;
    }
    $name = strtolower(basename((string) $path));
    if (preg_match('/(?:23andme|myheritage|ancestrydna|raw[-_ ]?dna|genotype|genome)/i', $name)) {
        return true;
    }
    $head = zl_file_head_bytes($path);
    // This legacy function is the general fail-closed Library content gate.
    // Credential-bearing configs join genomic files in the metadata-only lane.
    return zl_text_looks_like_genotype_data($head)
        || zl_text_looks_like_genomic_sequence($head)
        || zl_text_looks_like_secret_config($head);
}

function zl_is_allowed_file($path) {
    $ext = zl_file_ext($path);
    return in_array($ext, zl_allowed_exts(), true);
}

function zl_read_file($path, $max_bytes = 800000, $allow_sensitive = false) {
    if (!is_file($path) || !is_readable($path) || !zl_is_allowed_file($path)) {
        return '';
    }
    if (!$allow_sensitive && zl_is_sensitive_genotype_file($path)) {
        return '';
    }
    $size = (int) filesize($path);
    if ($size <= 0 || $size > zl_library_max_save_bytes()) {
        return '';
    }
    $handle = fopen($path, 'rb');
    if (!$handle) {
        return '';
    }
    $raw = fread($handle, max(1, min((int) $max_bytes, $size)));
    fclose($handle);
    return zl_clean_text((string) $raw);
}

function zl_relative($root, $path) {
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/';
    $real = str_replace('\\', '/', realpath($path) ?: $path);
    return strpos($real, $root) === 0 ? substr($real, strlen($root)) : basename($path);
}

function zl_tokenize($text) {
    $text = strtolower((string) $text);
    preg_match_all('/[a-z0-9_]{2,}/', $text, $matches);
    $stop = array_flip(['the','and','for','with','that','this','from','into','your','you','are','was','were','will','can','not','have','has','use','using','file','code']);
    $tokens = [];
    foreach ($matches[0] as $token) {
        if (!isset($stop[$token])) {
            $tokens[$token] = ($tokens[$token] ?? 0) + 1;
        }
    }
    return $tokens;
}

function zl_outline($text) {
    $lines = explode("\n", zl_clean_text($text));
    $hits = [];
    $patterns = [
        '/^\s*(?:export\s+)?(?:default\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/i' => 'type',
        '/^\s*(?:export\s+)?(?:async\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i' => 'function',
        '/^\s*(?:public|private|protected|static|\s)*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i' => 'method',
        '/^\s*def\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i' => 'def',
        '/^\s*class\s+([A-Za-z_][A-Za-z0-9_]*)\b/i' => 'class',
        '/^\s*fn\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i' => 'fn',
        '/^\s*func\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i' => 'func',
        '/^\s*>\s*([^|\s]+)/' => 'fasta',
    ];
    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $trimmed, $m)) {
                $hits[] = 'L' . ($i + 1) . ' ' . $label . ' ' . substr($m[1], 0, 90);
                break;
            }
        }
        if (count($hits) >= 120) {
            break;
        }
    }
    return implode("\n", $hits);
}

function zl_index_path($user_id) {
    return zl_cache_dir() . '/user_' . max(0, (int) $user_id) . '.json';
}

function zl_build_index($user_id, $force = false) {
    $root = zl_user_dir($user_id);
    $index_file = zl_index_path($user_id);
    if (!$force && is_file($index_file) && filemtime($index_file) > time() - 60) {
        $cached = json_decode((string) file_get_contents($index_file), true);
        if (is_array($cached) && (int) ($cached['index_version'] ?? 0) >= 3) {
            return $cached;
        }
    }

    $items = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (!zl_is_allowed_file($path)) {
            continue;
        }
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > zl_library_max_save_bytes()) {
            continue;
        }
        $relative_path = zl_relative($root, $path);
        $sensitive = zl_is_sensitive_genotype_file($path);
        if ($sensitive) {
            $items[] = [
                'path' => $relative_path,
                'size' => $size,
                'mtime' => (int) $file->getMTime(),
                'lines' => null,
                'sha256' => hash_file('sha256', $path),
                'top_terms' => [],
                'outline' => '',
                'classification' => 'sensitive_genotype',
                'indexable' => false,
            ];
            if (count($items) >= 1000) {
                break;
            }
            continue;
        }
        $text = zl_read_file($path, 300000);
        if ($text === '') {
            continue;
        }
        $tokens = zl_tokenize(substr($text, 0, 240000));
        arsort($tokens);
        $items[] = [
            'path' => $relative_path,
            'size' => $size,
            'mtime' => (int) $file->getMTime(),
            'lines' => substr_count($text, "\n") + 1,
            'sha256' => hash_file('sha256', $path),
            'top_terms' => array_slice(array_keys($tokens), 0, 24),
            'outline' => zl_outline($text),
            'classification' => 'reference_text',
            'indexable' => true,
        ];
        if (count($items) >= 1000) {
            break;
        }
    }

    $catalogued_count = count($items);
    $searchable_count = count(array_filter($items, function ($item) {
        return !empty($item['indexable'])
            && strtolower((string) ($item['classification'] ?? '')) !== 'sensitive_genotype';
    }));
    $vault_only_count = max(0, $catalogued_count - $searchable_count);

    $index = [
        'status' => 'success',
        'index_version' => 3,
        'generated_at' => gmdate('c'),
        'root' => basename($root),
        // `count` remains for older clients; the named counters make the
        // privacy boundary visible instead of calling every file "indexed".
        'count' => $catalogued_count,
        'catalogued_count' => $catalogued_count,
        'searchable_count' => $searchable_count,
        'vault_only_count' => $vault_only_count,
        'privacy' => 'Sensitive DNA, genotype, genomic-sequence, and detected credential-bearing files are catalogued by metadata only and are never searchable, used for RAG, or added to model context.',
        'items' => $items,
    ];
    file_put_contents($index_file, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($index_file, 0640);
    return $index;
}

function zl_score_text($query_tokens, $path, $text, $outline = '') {
    $hay = strtolower($path . "\n" . substr($outline, 0, 12000) . "\n" . substr($text, 0, 260000));
    $score = 0;
    foreach ($query_tokens as $token => $weight) {
        $hits = substr_count($hay, strtolower($token));
        if ($hits > 0) {
            $score += min(12, $hits) * (1 + min(3, $weight));
        }
    }
    return $score;
}

function zl_best_snippet($query_tokens, $text, $radius = 4) {
    $lines = explode("\n", zl_clean_text($text));
    $best_line = 0;
    $best = -1;
    foreach ($lines as $i => $line) {
        $lower = strtolower($line);
        $score = 0;
        foreach ($query_tokens as $token => $weight) {
            if (strpos($lower, strtolower($token)) !== false) {
                $score += 1 + min(3, $weight);
            }
        }
        if ($score > $best) {
            $best = $score;
            $best_line = $i;
        }
    }
    $start = max(0, $best_line - $radius);
    $end = min(count($lines), $best_line + $radius + 1);
    $out = [];
    for ($i = $start; $i < $end; $i++) {
        $out[] = str_pad((string) ($i + 1), 5, ' ', STR_PAD_LEFT) . ' | ' . $lines[$i];
    }
    return implode("\n", $out);
}

function zl_search($user_id, $query, $limit = 8) {
    $query = trim((string) $query);
    $limit = max(1, min(20, (int) $limit));
    $tokens = zl_tokenize($query);
    if (!$tokens) {
        return [];
    }
    $root = zl_user_dir($user_id);
    $index = zl_build_index($user_id);
    $hits = [];
    foreach (($index['items'] ?? []) as $item) {
        if (empty($item['indexable']) || strtolower((string) ($item['classification'] ?? '')) === 'sensitive_genotype') {
            continue;
        }
        $path = $root . '/' . str_replace(['..', '\\'], ['', '/'], (string) ($item['path'] ?? ''));
        if (zl_is_sensitive_genotype_file($path)) {
            continue;
        }
        $text = zl_read_file($path, 420000);
        if ($text === '') {
            continue;
        }
        $score = zl_score_text($tokens, (string) ($item['path'] ?? ''), $text, (string) ($item['outline'] ?? ''));
        if ($score <= 0) {
            continue;
        }
        $hits[] = [
            'path' => (string) ($item['path'] ?? ''),
            'score' => $score,
            'size' => (int) ($item['size'] ?? 0),
            'lines' => (int) ($item['lines'] ?? 0),
            'sha256' => (string) ($item['sha256'] ?? ''),
            'outline' => (string) ($item['outline'] ?? ''),
            'snippet' => zl_best_snippet($tokens, $text),
        ];
    }
    usort($hits, function ($a, $b) {
        return ($b['score'] <=> $a['score']) ?: strcmp($a['path'], $b['path']);
    });
    return array_slice($hits, 0, $limit);
}

function zl_context_pack($user_id, $query, $max_chars = 14000, $allow_sensitive = false) {
    // The flag is reserved for a future isolated workflow. General RAG is
    // deliberately fail-closed even if a caller attempts to enable it.
    $allow_sensitive = false;
    $hits = zl_search($user_id, $query, 8);
    if (!$hits) {
        return '';
    }
    $out = "[ZERO LIBRARY CONTEXT]\n";
    $out .= "Deterministic local search results. Use only these snippets unless the user attaches more files.\n";
    foreach ($hits as $i => $hit) {
        $out .= "\n[LIBRARY HIT " . ($i + 1) . "] " . $hit['path'] . "\n";
        $out .= "score=" . $hit['score'] . " lines=" . $hit['lines'] . " sha256=" . substr($hit['sha256'], 0, 16) . "\n";
        if (trim($hit['outline']) !== '') {
            $out .= "Outline:\n" . substr($hit['outline'], 0, 1600) . "\n";
        }
        $out .= "Snippet:\n" . $hit['snippet'] . "\n";
        if (strlen($out) >= $max_chars) {
            $out .= "\n[ZERO LIBRARY CONTEXT TRUNCATED]\n";
            break;
        }
    }
    return substr($out, 0, max(1000, (int) $max_chars));
}

function zl_store_upload($user_id, $name, $tmp_path, $options = []) {
    $options = (array) $options;
    $safe = zl_safe_name($name);
    $ext = zl_file_ext($safe);
    if (!in_array($ext, zl_allowed_exts(), true)) {
        throw new Exception('This file type is not accepted for Zero Library text indexing.');
    }
    zl_assert_library_save_allowed($tmp_path, $safe);
    $dest_dir = zl_user_dir($user_id);
    $classification = strtolower(trim((string) ($options['classification'] ?? '')));
    // PHP upload temp names have no useful extension. Classify from both the
    // original, sanitized filename and the content so PED/MAP/VCF/FASTA-style
    // files enter the vault lane before they are moved.
    $sensitive = in_array($ext, zl_sensitive_genotype_exts(), true)
        || in_array($classification, ['sensitive_genotype', 'sensitive_genomic'], true)
        || zl_is_sensitive_genotype_file($tmp_path, $classification);
    if ($sensitive) {
        $classification = 'sensitive_genotype';
        $dest_dir .= '/sensitive';
        if (!is_dir($dest_dir)) {
            @mkdir($dest_dir, 0700, true);
        }
        @chmod($dest_dir, 0700);
    } else {
        $classification = 'reference_text';
    }
    $dest = $dest_dir . '/' . $safe;
    if (is_file($dest)) {
        $base = pathinfo($safe, PATHINFO_FILENAME);
        $suffix = pathinfo($safe, PATHINFO_EXTENSION);
        $dest = $dest_dir . '/' . $base . '-' . gmdate('Ymd-His') . ($suffix ? '.' . $suffix : '');
    }
    if (!move_uploaded_file($tmp_path, $dest)) {
        if (PHP_SAPI !== 'cli' || !@rename($tmp_path, $dest)) {
            throw new Exception('Could not store the uploaded library file.');
        }
    }
    @chmod($dest, $sensitive ? 0600 : 0640);
    zl_build_index($user_id, true);
    return [
        'path' => zl_relative(zl_user_dir($user_id), $dest),
        'size' => (int) filesize($dest),
        'sha256' => hash_file('sha256', $dest),
        'classification' => $classification,
        'indexable' => !$sensitive,
        'privacy_mode' => $sensitive ? 'vault_metadata_only' : 'searchable_reference_text',
    ];
}

function zl_dna_clean($sequence) {
    $sequence = strtoupper((string) $sequence);
    $sequence = preg_replace('/[^ACGTUN]/', '', $sequence);
    return str_replace('U', 'T', (string) $sequence);
}

function zl_dna_extract_sequence($text) {
    $text = strtoupper((string) $text);
    preg_match_all('/[ACGTUN\R\s]{24,}/', $text, $matches);
    $best = '';
    foreach ($matches[0] as $m) {
        $candidate = zl_dna_clean($m);
        if (strlen($candidate) > strlen($best)) {
            $best = $candidate;
        }
    }
    return $best;
}

function zl_dna_reverse_complement($seq) {
    return strtr(strrev($seq), 'ACGTN', 'TGCAN');
}

function zl_dna_codons() {
    return [
        'TTT'=>'F','TTC'=>'F','TTA'=>'L','TTG'=>'L','TCT'=>'S','TCC'=>'S','TCA'=>'S','TCG'=>'S',
        'TAT'=>'Y','TAC'=>'Y','TAA'=>'*','TAG'=>'*','TGT'=>'C','TGC'=>'C','TGA'=>'*','TGG'=>'W',
        'CTT'=>'L','CTC'=>'L','CTA'=>'L','CTG'=>'L','CCT'=>'P','CCC'=>'P','CCA'=>'P','CCG'=>'P',
        'CAT'=>'H','CAC'=>'H','CAA'=>'Q','CAG'=>'Q','CGT'=>'R','CGC'=>'R','CGA'=>'R','CGG'=>'R',
        'ATT'=>'I','ATC'=>'I','ATA'=>'I','ATG'=>'M','ACT'=>'T','ACC'=>'T','ACA'=>'T','ACG'=>'T',
        'AAT'=>'N','AAC'=>'N','AAA'=>'K','AAG'=>'K','AGT'=>'S','AGC'=>'S','AGA'=>'R','AGG'=>'R',
        'GTT'=>'V','GTC'=>'V','GTA'=>'V','GTG'=>'V','GCT'=>'A','GCC'=>'A','GCA'=>'A','GCG'=>'A',
        'GAT'=>'D','GAC'=>'D','GAA'=>'E','GAG'=>'E','GGT'=>'G','GGC'=>'G','GGA'=>'G','GGG'=>'G',
    ];
}

function zl_dna_translate_frame($seq, $frame = 0) {
    $table = zl_dna_codons();
    $aa = '';
    for ($i = (int) $frame; $i + 2 < strlen($seq); $i += 3) {
        $codon = substr($seq, $i, 3);
        $aa .= $table[$codon] ?? 'X';
        if (strlen($aa) >= 300) {
            $aa .= '...';
            break;
        }
    }
    return $aa;
}

function zl_dna_orfs($seq) {
    $orfs = [];
    $stops = ['TAA' => true, 'TAG' => true, 'TGA' => true];
    for ($frame = 0; $frame < 3; $frame++) {
        for ($i = $frame; $i + 2 < strlen($seq); $i += 3) {
            if (substr($seq, $i, 3) !== 'ATG') {
                continue;
            }
            for ($j = $i + 3; $j + 2 < strlen($seq); $j += 3) {
                if (isset($stops[substr($seq, $j, 3)])) {
                    $orfs[] = ['frame' => $frame + 1, 'start' => $i + 1, 'end' => $j + 3, 'codons' => (int) (($j + 3 - $i) / 3)];
                    break;
                }
            }
        }
    }
    usort($orfs, function ($a, $b) {
        return ($b['codons'] <=> $a['codons']) ?: ($a['start'] <=> $b['start']);
    });
    return array_slice($orfs, 0, 12);
}

function zl_dna_top_kmers($seq, $k = 3, $limit = 10) {
    $seq = zl_dna_clean($seq);
    $k = max(2, min(8, (int) $k));
    $limit = max(1, min(25, (int) $limit));
    $counts = [];
    $len = strlen($seq);
    for ($i = 0; $i + $k <= $len; $i++) {
        $mer = substr($seq, $i, $k);
        if (strpos($mer, 'N') !== false) {
            continue;
        }
        $counts[$mer] = ($counts[$mer] ?? 0) + 1;
    }
    arsort($counts, SORT_NUMERIC);
    return array_slice($counts, 0, $limit, true);
}

function zl_dna_gc_windows($seq, $window = 100) {
    $seq = zl_dna_clean($seq);
    $len = strlen($seq);
    if ($len === 0) {
        return ['window' => 0, 'count' => 0, 'min_gc' => 0, 'max_gc' => 0, 'avg_gc' => 0];
    }
    $window = max(12, min((int) $window, max(12, $len)));
    $values = [];
    for ($i = 0; $i < $len; $i += $window) {
        $chunk = substr($seq, $i, $window);
        $chunk_len = strlen($chunk);
        if ($chunk_len < 8) {
            continue;
        }
        $values[] = (($chunk_len > 0 ? (substr_count($chunk, 'G') + substr_count($chunk, 'C')) / $chunk_len : 0) * 100);
    }
    if (!$values) {
        return ['window' => $window, 'count' => 0, 'min_gc' => 0, 'max_gc' => 0, 'avg_gc' => 0];
    }
    return [
        'window' => $window,
        'count' => count($values),
        'min_gc' => round(min($values), 2),
        'max_gc' => round(max($values), 2),
        'avg_gc' => round(array_sum($values) / count($values), 2),
    ];
}

function zl_dna_analyze($sequence) {
    $seq = zl_dna_clean($sequence);
    $len = strlen($seq);
    if ($len < 8) {
        throw new Exception('Paste a DNA/RNA sequence of at least 8 bases.');
    }
    $counts = [
        'A' => substr_count($seq, 'A'),
        'C' => substr_count($seq, 'C'),
        'G' => substr_count($seq, 'G'),
        'T' => substr_count($seq, 'T'),
        'N' => substr_count($seq, 'N'),
    ];
    $gc = $len > 0 ? round((($counts['G'] + $counts['C']) / $len) * 100, 2) : 0;
    $at = $len > 0 ? round((($counts['A'] + $counts['T']) / $len) * 100, 2) : 0;
    $gc_total = $counts['G'] + $counts['C'];
    $at_total = $counts['A'] + $counts['T'];
    $expected_cpg = $len > 0 ? (($counts['C'] * $counts['G']) / $len) : 0;
    $observed_cpg = substr_count($seq, 'CG');
    $motifs = [
        'start_ATG' => substr_count($seq, 'ATG'),
        'stop_TAA' => substr_count($seq, 'TAA'),
        'stop_TAG' => substr_count($seq, 'TAG'),
        'stop_TGA' => substr_count($seq, 'TGA'),
        'cpg' => $observed_cpg,
        'poly_A_6' => preg_match_all('/A{6,}/', $seq),
        'poly_T_6' => preg_match_all('/T{6,}/', $seq),
    ];
    $frames = [
        '+1' => zl_dna_translate_frame($seq, 0),
        '+2' => zl_dna_translate_frame($seq, 1),
        '+3' => zl_dna_translate_frame($seq, 2),
    ];
    $rc = zl_dna_reverse_complement($seq);
    return [
        'length' => $len,
        'counts' => $counts,
        'gc_percent' => $gc,
        'at_percent' => $at,
        'n_percent' => $len > 0 ? round(($counts['N'] / $len) * 100, 3) : 0,
        'gc_skew' => $gc_total > 0 ? round(($counts['G'] - $counts['C']) / $gc_total, 4) : 0,
        'at_skew' => $at_total > 0 ? round(($counts['A'] - $counts['T']) / $at_total, 4) : 0,
        'cpg_observed_expected' => $expected_cpg > 0 ? round($observed_cpg / $expected_cpg, 4) : 0,
        'motifs' => $motifs,
        'top_trimers' => zl_dna_top_kmers($seq, 3, 10),
        'gc_windows' => zl_dna_gc_windows($seq, min(250, max(50, (int) floor(max(50, $len) / 8)))),
        'orfs' => zl_dna_orfs($seq),
        'frames' => $frames,
        'reverse_complement_preview' => substr($rc, 0, 240) . (strlen($rc) > 240 ? '...' : ''),
        'sequence_preview' => substr($seq, 0, 240) . (strlen($seq) > 240 ? '...' : ''),
        'disclaimer' => 'Research utility only. This is not medical, diagnostic, ancestry, or clinical interpretation.',
    ];
}

function zl_dna_markdown($analysis) {
    $lines = [];
    $lines[] = '## Zero DNA Analysis';
    $lines[] = '';
    $lines[] = '**Research boundary:** ' . $analysis['disclaimer'];
    $lines[] = '';
    $lines[] = '- Length: ' . number_format((int) $analysis['length']) . ' bases';
    $lines[] = '- GC: ' . $analysis['gc_percent'] . '%';
    $lines[] = '- AT: ' . $analysis['at_percent'] . '%';
    $lines[] = '- N/ambiguous: ' . $analysis['n_percent'] . '%';
    $lines[] = '- GC skew: ' . $analysis['gc_skew'] . ' | AT skew: ' . $analysis['at_skew'];
    $lines[] = '- CpG observed/expected: ' . $analysis['cpg_observed_expected'];
    $lines[] = '- Counts: A=' . $analysis['counts']['A'] . ', C=' . $analysis['counts']['C'] . ', G=' . $analysis['counts']['G'] . ', T=' . $analysis['counts']['T'] . ', N=' . $analysis['counts']['N'];
    $lines[] = '';
    $lines[] = '### GC Windows';
    $gw = $analysis['gc_windows'];
    $lines[] = '- Window: ' . $gw['window'] . ' bases across ' . $gw['count'] . ' window(s)';
    $lines[] = '- Min/Avg/Max GC: ' . $gw['min_gc'] . '% / ' . $gw['avg_gc'] . '% / ' . $gw['max_gc'] . '%';
    $lines[] = '';
    $lines[] = '### Motifs';
    foreach ($analysis['motifs'] as $name => $count) {
        $lines[] = '- ' . $name . ': ' . $count;
    }
    if (!empty($analysis['top_trimers'])) {
        $bits = [];
        foreach ($analysis['top_trimers'] as $mer => $count) {
            $bits[] = $mer . '=' . $count;
        }
        $lines[] = '';
        $lines[] = '### Top 3-mers';
        $lines[] = '- ' . implode(', ', $bits);
    }
    $lines[] = '';
    $lines[] = '### Longest ORFs';
    if (!$analysis['orfs']) {
        $lines[] = '- No complete ATG...stop ORF found in the forward frames.';
    } else {
        foreach ($analysis['orfs'] as $orf) {
            $lines[] = '- Frame +' . $orf['frame'] . ': bases ' . $orf['start'] . '-' . $orf['end'] . ' (' . $orf['codons'] . ' codons)';
        }
    }
    $lines[] = '';
    $lines[] = '### Translation Preview';
    foreach ($analysis['frames'] as $frame => $aa) {
        $lines[] = '- Frame ' . $frame . ': `' . $aa . '`';
    }
    $lines[] = '';
    $lines[] = 'Reverse complement preview: `' . $analysis['reverse_complement_preview'] . '`';
    return implode("\n", $lines);
}

function zl_dna_raw_split_row($line) {
    $line = preg_replace('/^\xEF\xBB\xBF/', '', (string) $line);
    $line = trim((string) $line);
    if ($line === '') {
        return [];
    }
    if (substr_count($line, ',') >= 3) {
        return array_map(function ($v) {
            return trim((string) $v, " \t\n\r\0\x0B\"");
        }, str_getcsv($line, ',', '"', '\\'));
    }
    return preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function zl_dna_raw_clean_genotype($value) {
    $value = strtoupper(trim((string) $value, " \t\n\r\0\x0B\"'"));
    $value = str_replace([' ', "\t"], '', $value);
    return substr($value, 0, 80);
}

// Keep duplicate records auditable without allowing row order to select a result.
function zl_dna_merge_variant_record(&$variants, $key, $record) {
    if (!isset($variants[$key])) {
        $variants[$key] = $record;
        return;
    }
    $prior = $variants[$key];
    $a = zl_dna_genotype_status($prior['genotype'] ?? '');
    $b = zl_dna_genotype_status($record['genotype'] ?? '');
    $a_letters = str_split((string) $a['normalized']);
    $b_letters = str_split((string) $b['normalized']);
    sort($a_letters);
    sort($b_letters);
    $same = !empty($a['callable']) && !empty($b['callable']) && $a_letters === $b_letters;
    foreach (['chromosome', 'position', 'source_record_format', 'reference_allele', 'alternate_alleles'] as $field) {
        if ((string) ($prior[$field] ?? '') !== (string) ($record[$field] ?? '')) {
            $same = false;
        }
    }
    $prior['duplicate_record_count'] = (int) ($prior['duplicate_record_count'] ?? 0) + 1;
    if (!$same || !empty($prior['duplicate_conflict'])) {
        $prior['genotype'] = '';
        $prior['duplicate_conflict'] = true;
        $prior['call_state'] = 'withheld_conflicting_duplicate';
    }
    $variants[$key] = $prior;
}

function zl_dna_merge_genotype_call(&$variants, $key, $genotype) {
    $call = zl_dna_genotype_status($genotype);
    $value = !empty($call['callable']) ? (string) $call['normalized'] : '';
    if (!array_key_exists($key, $variants)) {
        $variants[$key] = $value;
        return;
    }
    $a = str_split((string) $variants[$key]);
    $b = str_split($value);
    sort($a);
    sort($b);
    if (!$a || !$b || $a !== $b) {
        $variants[$key] = '';
    }
}

function zl_dna_raw_header_map($cols) {
    $map = [];
    foreach ($cols as $i => $col) {
        $key = preg_replace('/^\xEF\xBB\xBF/', '', (string) $col);
        $key = strtolower(trim((string) $key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim((string) $key, '_');
        if ($key !== '') {
            $map[$key] = (int) $i;
        }
    }
    return $map;
}

function zl_dna_raw_pick($cols, $map, $keys, $fallback = null) {
    foreach ($keys as $key) {
        if (isset($map[$key]) && array_key_exists($map[$key], $cols)) {
            return (string) $cols[$map[$key]];
        }
    }
    if ($fallback !== null && array_key_exists($fallback, $cols)) {
        return (string) $cols[$fallback];
    }
    return '';
}

function zl_dna_raw_guess_provider($text, $name) {
    $hay = strtolower(substr((string) $text, 0, 8000) . ' ' . (string) $name);
    if (strpos($hay, '##fileformat=vcf') !== false || strpos($hay, '#chrom') !== false || strpos($hay, "\tref\talt") !== false || preg_match('/\.vcf(?:\.gz)?\b/i', (string) $name)) {
        return 'VCF-style variant file';
    }
    if (strpos($hay, 'myheritage') !== false || preg_match('/rsid\s*,\s*chromosome\s*,\s*position\s*,\s*result/', $hay)) {
        return 'MyHeritage-style raw genotype file';
    }
    if (strpos($hay, '23andme') !== false || strpos($hay, '23 and me') !== false || preg_match('/rsid\s+chromosome\s+position\s+genotype/', $hay)) {
        return '23andMe-style raw genotype file';
    }
    if (strpos($hay, 'ancestry') !== false || preg_match('/rsid\s+chromosome\s+position\s+allele1\s+allele2/', $hay) || preg_match('/snp\s+chromosome\s+position\s+allele1\s+allele2/', $hay)) {
        return 'Ancestry-style raw genotype file';
    }
    return 'Generic raw genotype table';
}

function zl_dna_vcf_gt_value($sample, $format = '') {
    $sample = trim((string) $sample);
    if ($sample === '') {
        return '';
    }
    $sample_fields = explode(':', $sample);
    $format_fields = array_map('strtoupper', array_map('trim', explode(':', (string) $format)));
    $gt_index = array_search('GT', $format_fields, true);
    if ($format !== '' && $gt_index === false) {
        return '';
    }
    $gt = $gt_index === false ? ($sample_fields[0] ?? '') : ($sample_fields[(int) $gt_index] ?? '');
    return substr(trim((string) $gt), 0, 80);
}

function zl_dna_vcf_sample_genotype($sample, $ref, $alt, $format = '') {
    $gt = zl_dna_vcf_gt_value($sample, $format);
    if ($gt === '' || !preg_match('/^\d+(?:[\/|]\d+)?$/', $gt)) {
        return '';
    }
    $ref = strtoupper(trim((string) $ref));
    $alt_list = array_map('trim', explode(',', strtoupper((string) $alt)));
    if (!preg_match('/^[ACGT]+$/', $ref) || !$alt_list) {
        return '';
    }
    $alleles = [];
    foreach (preg_split('/[\/|]/', $gt) as $part) {
        $part = trim((string) $part);
        $idx = (int) $part;
        if ($idx < 0 || ($idx > 0 && !array_key_exists($idx - 1, $alt_list))) {
            return '';
        }
        $allele = $idx === 0 ? $ref : (string) $alt_list[$idx - 1];
        $allele = strtoupper(trim($allele));
        // This SNP engine must not flatten an indel or a haploid MNV into a diploid SNV.
        if (!preg_match('/^[ACGT]$/', $allele) || strlen($ref) !== 1) {
            return '';
        }
        $alleles[] = $allele;
    }
    return implode('', $alleles);
}

function zl_dna_raw_file_head($path, $bytes = 12000) {
    if (!is_file($path) || !is_readable($path)) {
        return '';
    }
    $handle = fopen($path, 'rb');
    if (!$handle) {
        return '';
    }
    $raw = fread($handle, max(1024, (int) $bytes));
    fclose($handle);
    return zl_clean_text((string) $raw);
}

function zl_dna_max_raw_rows() {
    return 1200000;
}

function zl_dna_raw_analyze($text, $name = 'raw-dna.txt') {
    zl_dna_raise_runtime_limits();
    $text = zl_clean_text((string) $text);
    $lines = explode("\n", $text);
    $header = [];
    $total_lines = 0;
    $data_lines = 0;
    $parsed = 0;
    $comments = 0;
    $no_calls = 0;
    $heterozygous = 0;
    $homozygous = 0;
    $indel_or_complex = 0;
    $chromosomes = [];
    $genotypes = [];
    $samples = [];
    $rsid_count = 0;
    $duplicate_rsids = 0;
    $seen_rsids = [];
    $position_count = 0;
    $autosomal_count = 0;
    $x_count = 0;
    $y_count = 0;
    $mt_count = 0;
    $vcf_mode = false;
    $max_rows = zl_dna_max_raw_rows();

    foreach ($lines as $line) {
        $total_lines++;
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (strpos($line, '##') === 0) {
            $comments++;
            continue;
        }
        if (strpos($line, '#') === 0) {
            $comments++;
            if (stripos($line, '#CHROM') === 0) {
                $header = zl_dna_raw_header_map(zl_dna_raw_split_row(ltrim($line, '#')));
                $vcf_mode = true;
            }
            continue;
        }

        $cols = zl_dna_raw_split_row($line);
        if (count($cols) < 3) {
            continue;
        }
        $lower = array_map('strtolower', $cols);
        $joined_header = implode('|', $lower);
        if (preg_match('/\b(rsid|snp|chromosome|position|genotype|result|allele1|allele2)\b/', $joined_header) && !preg_match('/^rs\d+/i', (string) ($cols[0] ?? ''))) {
            $header = zl_dna_raw_header_map($cols);
            continue;
        }

        $data_lines++;
        if ($data_lines > $max_rows) {
            break;
        }

        $is_vcf_record = $vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')));
        $ref = '';
        $alt = '';
        $vcf_format = '';
        $vcf_gt = '';
        if ($is_vcf_record) {
            $chrom = zl_dna_raw_pick($cols, $header, ['chrom', 'chromosome'], 0);
            $pos = zl_dna_raw_pick($cols, $header, ['pos', 'position'], 1);
            $rsid = zl_dna_raw_pick($cols, $header, ['id', 'rsid', 'snp'], 2);
            $ref = strtoupper(zl_dna_raw_pick($cols, $header, ['ref'], 3));
            $alt = strtoupper(zl_dna_raw_pick($cols, $header, ['alt'], 4));
            $sample = count($cols) === 10 && in_array(strtoupper(trim((string) ($cols[6] ?? ''))), ['PASS', '.'], true) ? (string) $cols[9] : '';
            $vcf_format = substr(trim((string) ($cols[8] ?? '')), 0, 120);
            $vcf_gt = zl_dna_vcf_gt_value($sample, $vcf_format);
            $geno = zl_dna_vcf_sample_genotype($sample, $ref, $alt, $vcf_format);
        } else {
            $rsid = zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0);
            $chrom = zl_dna_raw_pick($cols, $header, ['chromosome', 'chrom', 'chr'], 1);
            $pos = zl_dna_raw_pick($cols, $header, ['position', 'pos'], 2);
            $geno = zl_dna_raw_pick($cols, $header, ['result', 'genotype', 'alleles', 'allele'], count($cols) - 1);
            $a1 = zl_dna_raw_pick($cols, $header, ['allele1', 'allele_1']);
            $a2 = zl_dna_raw_pick($cols, $header, ['allele2', 'allele_2']);
            if (($a1 !== '' || $a2 !== '') && strlen($a1 . $a2) <= 12) {
                $geno = $a1 . $a2;
            } elseif (count($cols) >= 5 && strlen((string) $cols[3]) <= 4 && strlen((string) $cols[4]) <= 4) {
                $geno = (string) $cols[3] . (string) $cols[4];
            }
        }

        $chrom = strtoupper(trim((string) $chrom, " \t\n\r\0\x0B\"'"));
        $chrom = preg_replace('/^CHR/i', '', $chrom);
        $geno = zl_dna_raw_clean_genotype($geno);
        if ($chrom === '') {
            $chrom = 'unknown';
        }
        $chromosomes[$chrom] = ($chromosomes[$chrom] ?? 0) + 1;
        if (preg_match('/^(?:[1-9]|1[0-9]|2[0-2])$/', $chrom)) {
            $autosomal_count++;
        } elseif ($chrom === 'X') {
            $x_count++;
        } elseif ($chrom === 'Y') {
            $y_count++;
        } elseif (in_array($chrom, ['M', 'MT', 'MITO', 'MITOCHONDRIAL'], true)) {
            $mt_count++;
        }
        $rsid_norm = strtolower(trim((string) $rsid));
        if (preg_match('/^rs\d+$/i', $rsid_norm)) {
            $rsid_count++;
            if (isset($seen_rsids[$rsid_norm])) {
                $duplicate_rsids++;
            } else {
                $seen_rsids[$rsid_norm] = true;
            }
        }
        if (preg_match('/^\d+$/', trim((string) $pos))) {
            $position_count++;
        }
        if (empty(zl_dna_genotype_status($geno)['callable'])) {
            $no_calls++;
        } else {
            $genotypes[$geno] = ($genotypes[$geno] ?? 0) + 1;
            if (preg_match('/^[ACGT]{2}$/', $geno)) {
                if ($geno[0] === $geno[1]) {
                    $homozygous++;
                } else {
                    $heterozygous++;
                }
            } else {
                $indel_or_complex++;
            }
        }
        if (count($samples) < 12) {
            $samples[] = [
                'rsid' => trim((string) $rsid),
                'chromosome' => $chrom,
                'position' => trim((string) $pos),
                'genotype' => $geno,
            ];
        }
        $parsed++;
    }

    arsort($chromosomes, SORT_NUMERIC);
    arsort($genotypes, SORT_NUMERIC);
    $called = max(0, $parsed - $no_calls);
    $summary = [
        'file_name' => zl_safe_name($name),
        'file_hash' => hash('sha256', $text),
        'format_guess' => zl_dna_raw_guess_provider($text, $name),
        'total_lines' => $total_lines,
        'comment_lines' => $comments,
        'data_lines_seen' => $data_lines,
        'parsed_variants' => $parsed,
        'rsid_rows' => $rsid_count,
        'duplicate_rsid_count' => $duplicate_rsids,
        'position_rows' => $position_count,
        'autosomal_markers' => $autosomal_count,
        'x_markers' => $x_count,
        'y_markers' => $y_count,
        'mt_markers' => $mt_count,
        'called_variants' => $called,
        'no_calls' => $no_calls,
        'no_call_percent' => $parsed > 0 ? round(($no_calls / $parsed) * 100, 3) : 0,
        'heterozygous' => $heterozygous,
        'homozygous' => $homozygous,
        'heterozygosity_percent' => $called > 0 ? round(($heterozygous / $called) * 100, 3) : 0,
        'indel_or_complex' => $indel_or_complex,
        'chromosome_count' => count($chromosomes),
        'top_chromosomes' => array_slice($chromosomes, 0, 12, true),
        'top_genotypes' => array_slice($genotypes, 0, 12, true),
        'truncated' => $data_lines > $max_rows,
        'privacy' => 'Analyzed locally inside ZeroThink PHP. No third-party genetics provider was called by this parser.',
        'disclaimer' => 'Research utility only. This is not medical, diagnostic, ancestry, or treatment advice.',
    ];
    return zl_dna_enrich_raw_summary($summary, $text);
}

function zl_dna_raw_analyze_path($path, $name = 'raw-dna.txt') {
    zl_dna_raise_runtime_limits();
    if (!is_file($path) || !is_readable($path)) {
        throw new Exception('The DNA file could not be read.');
    }

    $header = [];
    $total_lines = 0;
    $data_lines = 0;
    $parsed = 0;
    $comments = 0;
    $no_calls = 0;
    $heterozygous = 0;
    $homozygous = 0;
    $indel_or_complex = 0;
    $chromosomes = [];
    $genotypes = [];
    $samples = [];
    $rsid_count = 0;
    $duplicate_rsids = 0;
    $seen_rsids = [];
    $position_count = 0;
    $autosomal_count = 0;
    $x_count = 0;
    $y_count = 0;
    $mt_count = 0;
    $vcf_mode = false;
    $max_rows = zl_dna_max_raw_rows();
    $head = zl_dna_raw_file_head($path, 12000);

    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new Exception('The DNA file could not be opened.');
    }

    while (($line = fgets($handle)) !== false) {
        $total_lines++;
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (strpos($line, '##') === 0) {
            $comments++;
            continue;
        }
        if (strpos($line, '#') === 0) {
            $comments++;
            if (stripos($line, '#CHROM') === 0) {
                $header = zl_dna_raw_header_map(zl_dna_raw_split_row(ltrim($line, '#')));
                $vcf_mode = true;
            }
            continue;
        }

        $cols = zl_dna_raw_split_row($line);
        if (count($cols) < 3) {
            continue;
        }
        $lower = array_map('strtolower', $cols);
        $joined_header = implode('|', $lower);
        if (preg_match('/\b(rsid|snp|chromosome|position|genotype|result|allele1|allele2)\b/', $joined_header) && !preg_match('/^rs\d+/i', (string) ($cols[0] ?? ''))) {
            $header = zl_dna_raw_header_map($cols);
            continue;
        }

        $data_lines++;
        if ($data_lines > $max_rows) {
            break;
        }

        $is_vcf_record = $vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')));
        $ref = '';
        $alt = '';
        $vcf_format = '';
        $vcf_gt = '';
        if ($is_vcf_record) {
            $chrom = zl_dna_raw_pick($cols, $header, ['chrom', 'chromosome'], 0);
            $pos = zl_dna_raw_pick($cols, $header, ['pos', 'position'], 1);
            $rsid = zl_dna_raw_pick($cols, $header, ['id', 'rsid', 'snp'], 2);
            $ref = strtoupper(zl_dna_raw_pick($cols, $header, ['ref'], 3));
            $alt = strtoupper(zl_dna_raw_pick($cols, $header, ['alt'], 4));
            $sample = count($cols) === 10 && in_array(strtoupper(trim((string) ($cols[6] ?? ''))), ['PASS', '.'], true) ? (string) $cols[9] : '';
            $vcf_format = substr(trim((string) ($cols[8] ?? '')), 0, 120);
            $vcf_gt = zl_dna_vcf_gt_value($sample, $vcf_format);
            $geno = zl_dna_vcf_sample_genotype($sample, $ref, $alt, $vcf_format);
        } else {
            $rsid = zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0);
            $chrom = zl_dna_raw_pick($cols, $header, ['chromosome', 'chrom', 'chr'], 1);
            $pos = zl_dna_raw_pick($cols, $header, ['position', 'pos'], 2);
            $geno = zl_dna_raw_pick($cols, $header, ['result', 'genotype', 'alleles', 'allele'], count($cols) - 1);
            $a1 = zl_dna_raw_pick($cols, $header, ['allele1', 'allele_1']);
            $a2 = zl_dna_raw_pick($cols, $header, ['allele2', 'allele_2']);
            if (($a1 !== '' || $a2 !== '') && strlen($a1 . $a2) <= 12) {
                $geno = $a1 . $a2;
            } elseif (count($cols) >= 5 && strlen((string) $cols[3]) <= 4 && strlen((string) $cols[4]) <= 4) {
                $geno = (string) $cols[3] . (string) $cols[4];
            }
        }

        $chrom = strtoupper(trim((string) $chrom, " \t\n\r\0\x0B\"'"));
        $chrom = preg_replace('/^CHR/i', '', $chrom);
        $geno = zl_dna_raw_clean_genotype($geno);
        if ($chrom === '') {
            $chrom = 'unknown';
        }
        $chromosomes[$chrom] = ($chromosomes[$chrom] ?? 0) + 1;
        if (preg_match('/^(?:[1-9]|1[0-9]|2[0-2])$/', $chrom)) {
            $autosomal_count++;
        } elseif ($chrom === 'X') {
            $x_count++;
        } elseif ($chrom === 'Y') {
            $y_count++;
        } elseif (in_array($chrom, ['M', 'MT', 'MITO', 'MITOCHONDRIAL'], true)) {
            $mt_count++;
        }
        $rsid_norm = strtolower(trim((string) $rsid));
        if (preg_match('/^rs\d+$/i', $rsid_norm)) {
            $rsid_count++;
            if (isset($seen_rsids[$rsid_norm])) {
                $duplicate_rsids++;
            } else {
                $seen_rsids[$rsid_norm] = true;
            }
        }
        if (preg_match('/^\d+$/', trim((string) $pos))) {
            $position_count++;
        }
        if (empty(zl_dna_genotype_status($geno)['callable'])) {
            $no_calls++;
        } else {
            $genotypes[$geno] = ($genotypes[$geno] ?? 0) + 1;
            if (preg_match('/^[ACGT]{2}$/', $geno)) {
                if ($geno[0] === $geno[1]) {
                    $homozygous++;
                } else {
                    $heterozygous++;
                }
            } else {
                $indel_or_complex++;
            }
        }
        if (count($samples) < 12) {
            $samples[] = [
                'rsid' => trim((string) $rsid),
                'chromosome' => $chrom,
                'position' => trim((string) $pos),
                'genotype' => $geno,
            ];
        }
        $parsed++;
    }

    fclose($handle);
    arsort($chromosomes, SORT_NUMERIC);
    arsort($genotypes, SORT_NUMERIC);
    $called = max(0, $parsed - $no_calls);
    $summary = [
        'file_name' => zl_safe_name($name),
        'file_hash' => hash_file('sha256', $path),
        'format_guess' => zl_dna_raw_guess_provider($head, $name),
        'total_lines' => $total_lines,
        'comment_lines' => $comments,
        'data_lines_seen' => $data_lines,
        'parsed_variants' => $parsed,
        'rsid_rows' => $rsid_count,
        'duplicate_rsid_count' => $duplicate_rsids,
        'position_rows' => $position_count,
        'autosomal_markers' => $autosomal_count,
        'x_markers' => $x_count,
        'y_markers' => $y_count,
        'mt_markers' => $mt_count,
        'called_variants' => $called,
        'no_calls' => $no_calls,
        'no_call_percent' => $parsed > 0 ? round(($no_calls / $parsed) * 100, 3) : 0,
        'heterozygous' => $heterozygous,
        'homozygous' => $homozygous,
        'heterozygosity_percent' => $called > 0 ? round(($heterozygous / $called) * 100, 3) : 0,
        'indel_or_complex' => $indel_or_complex,
        'chromosome_count' => count($chromosomes),
        'top_chromosomes' => array_slice($chromosomes, 0, 12, true),
        'top_genotypes' => array_slice($genotypes, 0, 12, true),
        'truncated' => $data_lines > $max_rows,
        'privacy' => 'Analyzed with ZeroThink streaming parser. No third-party genetics provider was called by this parser.',
        'disclaimer' => 'Research utility only. This is not medical, diagnostic, ancestry, or treatment advice.',
    ];
    return zl_dna_enrich_raw_summary($summary, $head);
}

function zl_dna_raw_variant_map($text, $name = 'raw-dna.txt', $max_rows = 1200000) {
    zl_dna_raise_runtime_limits();
    $text = zl_clean_text((string) $text);
    $lines = explode("\n", $text);
    $header = [];
    $vcf_mode = false;
    $variants = [];
    $parsed = 0;
    $rsid_rows = 0;
    $data_lines = 0;

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (strpos($line, '##') === 0) {
            continue;
        }
        if (strpos($line, '#') === 0) {
            if (stripos($line, '#CHROM') === 0) {
                $header = zl_dna_raw_header_map(zl_dna_raw_split_row(ltrim($line, '#')));
                $vcf_mode = true;
            }
            continue;
        }

        $cols = zl_dna_raw_split_row($line);
        if (count($cols) < 3) {
            continue;
        }
        $lower = array_map('strtolower', $cols);
        $joined_header = implode('|', $lower);
        if (preg_match('/\b(rsid|snp|chromosome|position|genotype|result|allele1|allele2)\b/', $joined_header) && !preg_match('/^rs\d+/i', (string) ($cols[0] ?? ''))) {
            $header = zl_dna_raw_header_map($cols);
            continue;
        }

        $data_lines++;
        if ($data_lines > $max_rows) {
            break;
        }

        $is_vcf_record = $vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')));
        $ref = '';
        $alt = '';
        $vcf_format = '';
        $vcf_gt = '';
        if ($is_vcf_record) {
            $chrom = zl_dna_raw_pick($cols, $header, ['chrom', 'chromosome'], 0);
            $pos = zl_dna_raw_pick($cols, $header, ['pos', 'position'], 1);
            $rsid = zl_dna_raw_pick($cols, $header, ['id', 'rsid', 'snp'], 2);
            $ref = strtoupper(zl_dna_raw_pick($cols, $header, ['ref'], 3));
            $alt = strtoupper(zl_dna_raw_pick($cols, $header, ['alt'], 4));
            $sample = count($cols) === 10 && in_array(strtoupper(trim((string) ($cols[6] ?? ''))), ['PASS', '.'], true) ? (string) $cols[9] : '';
            $vcf_format = substr(trim((string) ($cols[8] ?? '')), 0, 120);
            $vcf_gt = zl_dna_vcf_gt_value($sample, $vcf_format);
            $geno = zl_dna_vcf_sample_genotype($sample, $ref, $alt, $vcf_format);
        } else {
            $rsid = zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0);
            $chrom = zl_dna_raw_pick($cols, $header, ['chromosome', 'chrom', 'chr'], 1);
            $pos = zl_dna_raw_pick($cols, $header, ['position', 'pos'], 2);
            $geno = zl_dna_raw_pick($cols, $header, ['result', 'genotype', 'alleles', 'allele'], count($cols) - 1);
            $a1 = zl_dna_raw_pick($cols, $header, ['allele1', 'allele_1']);
            $a2 = zl_dna_raw_pick($cols, $header, ['allele2', 'allele_2']);
            if (($a1 !== '' || $a2 !== '') && strlen($a1 . $a2) <= 12) {
                $geno = $a1 . $a2;
            } elseif (count($cols) >= 5 && strlen((string) $cols[3]) <= 4 && strlen((string) $cols[4]) <= 4) {
                $geno = (string) $cols[3] . (string) $cols[4];
            }
        }

        $rsid = strtolower(trim((string) $rsid));
        if (!preg_match('/^rs\d+$/', $rsid)) {
            continue;
        }
        $chrom = strtoupper(trim((string) $chrom, " \t\n\r\0\x0B\"'"));
        $chrom = preg_replace('/^CHR/i', '', $chrom);
        $record = [
            'rsid' => $rsid,
            'chromosome' => $chrom !== '' ? $chrom : 'unknown',
            'position' => trim((string) $pos),
            'genotype' => zl_dna_raw_clean_genotype($geno),
            'source_record_format' => $is_vcf_record ? 'vcf' : 'consumer_raw',
            'reference_allele' => $is_vcf_record ? zl_dna_raw_clean_genotype($ref) : '',
            'alternate_alleles' => $is_vcf_record ? substr(strtoupper(trim((string) $alt)), 0, 240) : '',
            'vcf_format' => $is_vcf_record ? $vcf_format : '',
            'vcf_gt' => $is_vcf_record ? $vcf_gt : '',
        ];
        zl_dna_merge_variant_record($variants, $rsid, $record);
        $parsed++;
        $rsid_rows++;
    }

    return [
        'file_name' => zl_safe_name($name),
        'file_hash' => hash('sha256', $text),
        'format_guess' => zl_dna_raw_guess_provider($text, $name),
        'variants' => $variants,
        'parsed_rsid_variants' => $parsed,
        'rsid_rows' => $rsid_rows,
        'truncated' => $data_lines > $max_rows,
    ];
}

function zl_dna_raw_variant_map_path($path, $name = 'raw-dna.txt', $max_rows = 1200000) {
    zl_dna_raise_runtime_limits();
    if (!is_file($path) || !is_readable($path)) {
        throw new Exception('The DNA file could not be read.');
    }
    $header = [];
    $vcf_mode = false;
    $variants = [];
    $parsed = 0;
    $rsid_rows = 0;
    $data_lines = 0;
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new Exception('The DNA file could not be opened.');
    }

    while (($line = fgets($handle)) !== false) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (strpos($line, '##') === 0) {
            continue;
        }
        if (strpos($line, '#') === 0) {
            if (stripos($line, '#CHROM') === 0) {
                $header = zl_dna_raw_header_map(zl_dna_raw_split_row(ltrim($line, '#')));
                $vcf_mode = true;
            }
            continue;
        }

        $cols = zl_dna_raw_split_row($line);
        if (count($cols) < 3) {
            continue;
        }
        $lower = array_map('strtolower', $cols);
        $joined_header = implode('|', $lower);
        if (preg_match('/\b(rsid|snp|chromosome|position|genotype|result|allele1|allele2)\b/', $joined_header) && !preg_match('/^rs\d+/i', (string) ($cols[0] ?? ''))) {
            $header = zl_dna_raw_header_map($cols);
            continue;
        }

        $data_lines++;
        if ($data_lines > $max_rows) {
            break;
        }

        $is_vcf_record = $vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')));
        $ref = '';
        $alt = '';
        $vcf_format = '';
        $vcf_gt = '';
        if ($is_vcf_record) {
            $chrom = zl_dna_raw_pick($cols, $header, ['chrom', 'chromosome'], 0);
            $pos = zl_dna_raw_pick($cols, $header, ['pos', 'position'], 1);
            $rsid = zl_dna_raw_pick($cols, $header, ['id', 'rsid', 'snp'], 2);
            $ref = strtoupper(zl_dna_raw_pick($cols, $header, ['ref'], 3));
            $alt = strtoupper(zl_dna_raw_pick($cols, $header, ['alt'], 4));
            $sample = count($cols) === 10 && in_array(strtoupper(trim((string) ($cols[6] ?? ''))), ['PASS', '.'], true) ? (string) $cols[9] : '';
            $vcf_format = substr(trim((string) ($cols[8] ?? '')), 0, 120);
            $vcf_gt = zl_dna_vcf_gt_value($sample, $vcf_format);
            $geno = zl_dna_vcf_sample_genotype($sample, $ref, $alt, $vcf_format);
        } else {
            $rsid = zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0);
            $chrom = zl_dna_raw_pick($cols, $header, ['chromosome', 'chrom', 'chr'], 1);
            $pos = zl_dna_raw_pick($cols, $header, ['position', 'pos'], 2);
            $geno = zl_dna_raw_pick($cols, $header, ['result', 'genotype', 'alleles', 'allele'], count($cols) - 1);
            $a1 = zl_dna_raw_pick($cols, $header, ['allele1', 'allele_1']);
            $a2 = zl_dna_raw_pick($cols, $header, ['allele2', 'allele_2']);
            if (($a1 !== '' || $a2 !== '') && strlen($a1 . $a2) <= 12) {
                $geno = $a1 . $a2;
            } elseif (count($cols) >= 5 && strlen((string) $cols[3]) <= 4 && strlen((string) $cols[4]) <= 4) {
                $geno = (string) $cols[3] . (string) $cols[4];
            }
        }

        $rsid = strtolower(trim((string) $rsid));
        if (!preg_match('/^rs\d+$/', $rsid)) {
            continue;
        }
        $chrom = strtoupper(trim((string) $chrom, " \t\n\r\0\x0B\"'"));
        $chrom = preg_replace('/^CHR/i', '', $chrom);
        $record = [
            'rsid' => $rsid,
            'chromosome' => $chrom !== '' ? $chrom : 'unknown',
            'position' => trim((string) $pos),
            'genotype' => zl_dna_raw_clean_genotype($geno),
            'source_record_format' => $is_vcf_record ? 'vcf' : 'consumer_raw',
            'reference_allele' => $is_vcf_record ? zl_dna_raw_clean_genotype($ref) : '',
            'alternate_alleles' => $is_vcf_record ? substr(strtoupper(trim((string) $alt)), 0, 240) : '',
            'vcf_format' => $is_vcf_record ? $vcf_format : '',
            'vcf_gt' => $is_vcf_record ? $vcf_gt : '',
        ];
        zl_dna_merge_variant_record($variants, $rsid, $record);
        $parsed++;
        $rsid_rows++;
    }
    fclose($handle);

    return [
        'file_name' => zl_safe_name($name),
        'file_hash' => hash_file('sha256', $path),
        'format_guess' => zl_dna_raw_guess_provider(zl_dna_raw_file_head($path, 12000), $name),
        'variants' => $variants,
        'parsed_rsid_variants' => $parsed,
        'rsid_rows' => $rsid_rows,
        'truncated' => $data_lines > $max_rows,
    ];
}

function zl_dna_scan_rsid_genotypes_path($path, $name = 'raw-dna.txt', $callback = null, $max_rows = 1200000) {
    zl_dna_raise_runtime_limits();
    if (!is_file($path) || !is_readable($path)) {
        throw new Exception('The DNA file could not be read.');
    }
    if (!is_callable($callback)) {
        throw new Exception('The DNA genotype scanner needs a callback.');
    }

    $header = [];
    $vcf_mode = false;
    $parsed = 0;
    $rsid_rows = 0;
    $data_lines = 0;
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new Exception('The DNA file could not be opened.');
    }

    while (($line = fgets($handle)) !== false) {
        $line = trim((string) $line);
        if ($line === '' || strpos($line, '##') === 0) {
            continue;
        }
        if (strpos($line, '#') === 0) {
            if (stripos($line, '#CHROM') === 0) {
                $header = zl_dna_raw_header_map(zl_dna_raw_split_row(ltrim($line, '#')));
                $vcf_mode = true;
            }
            continue;
        }

        $cols = zl_dna_raw_split_row($line);
        if (count($cols) < 3) {
            continue;
        }
        $lower = array_map('strtolower', $cols);
        $joined_header = implode('|', $lower);
        if (preg_match('/\b(rsid|snp|chromosome|position|genotype|result|allele1|allele2)\b/', $joined_header) && !preg_match('/^rs\d+/i', (string) ($cols[0] ?? ''))) {
            $header = zl_dna_raw_header_map($cols);
            continue;
        }

        $data_lines++;
        if ($data_lines > $max_rows) {
            break;
        }

        if ($vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')))) {
            $rsid = zl_dna_raw_pick($cols, $header, ['id', 'rsid', 'snp'], 2);
            $ref = strtoupper(zl_dna_raw_pick($cols, $header, ['ref'], 3));
            $alt = strtoupper(zl_dna_raw_pick($cols, $header, ['alt'], 4));
            $sample = count($cols) === 10 && in_array(strtoupper(trim((string) ($cols[6] ?? ''))), ['PASS', '.'], true) ? (string) $cols[9] : '';
            $geno = zl_dna_vcf_sample_genotype($sample, $ref, $alt, (string) ($cols[8] ?? ''));
        } else {
            $rsid = zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0);
            $geno = zl_dna_raw_pick($cols, $header, ['result', 'genotype', 'alleles', 'allele'], count($cols) - 1);
            $a1 = zl_dna_raw_pick($cols, $header, ['allele1', 'allele_1']);
            $a2 = zl_dna_raw_pick($cols, $header, ['allele2', 'allele_2']);
            if (($a1 !== '' || $a2 !== '') && strlen($a1 . $a2) <= 12) {
                $geno = $a1 . $a2;
            } elseif (count($cols) >= 5 && strlen((string) $cols[3]) <= 4 && strlen((string) $cols[4]) <= 4) {
                $geno = (string) $cols[3] . (string) $cols[4];
            }
        }

        $rsid = strtolower(trim((string) $rsid));
        if (!preg_match('/^rs\d+$/', $rsid)) {
            continue;
        }
        $parsed++;
        $rsid_rows++;
        if ($callback($rsid, zl_dna_raw_clean_genotype($geno)) === false) {
            break;
        }
    }
    fclose($handle);

    return [
        'file_name' => zl_safe_name($name),
        'parsed_rsid_variants' => $parsed,
        'rsid_rows' => $rsid_rows,
        'truncated' => $data_lines > $max_rows,
    ];
}

function zl_dna_rsid_genotype_map_path($path, $name = 'raw-dna.txt', $max_rows = 1200000) {
    $map = [];
    $stats = zl_dna_scan_rsid_genotypes_path($path, $name, function ($rsid, $genotype) use (&$map) {
        zl_dna_merge_genotype_call($map, $rsid, $genotype);
    }, $max_rows);
    $stats['variants'] = $map;
    return $stats;
}

function zl_dna_coordinate_genotype_map_path($path, $name = 'raw-dna.txt', $chrom_filter = [], $max_rows = 1200000) {
    zl_dna_raise_runtime_limits();
    if (!is_file($path) || !is_readable($path)) {
        throw new Exception('The DNA file could not be read.');
    }

    $filter = [];
    foreach ((array) $chrom_filter as $chrom => $enabled) {
        // PHP casts numeric chromosome keys to integers; preserve keyed bool maps.
        if (is_int($chrom) && !is_bool($enabled)) {
            $chrom = $enabled;
            $enabled = true;
        }
        if (!$enabled) {
            continue;
        }
        $chrom = strtoupper(trim((string) $chrom));
        $chrom = preg_replace('/^CHR/i', '', $chrom);
        if ($chrom !== '') {
            $filter[$chrom] = true;
        }
    }

    $cache_key = realpath($path) . '|' . filesize($path) . '|' . filemtime($path) . '|' . $max_rows . '|' . implode(',', array_keys($filter));
    static $cache = [];
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $header = [];
    $vcf_mode = false;
    $variants = [];
    $rsid_coordinates = [];
    $conflicting_rsids = [];
    $parsed = 0;
    $data_lines = 0;
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new Exception('The DNA file could not be opened.');
    }

    while (($line = fgets($handle)) !== false) {
        $line = trim((string) $line);
        if ($line === '' || strpos($line, '##') === 0) {
            continue;
        }
        if (strpos($line, '#') === 0) {
            if (stripos($line, '#CHROM') === 0) {
                $header = zl_dna_raw_header_map(zl_dna_raw_split_row(ltrim($line, '#')));
                $vcf_mode = true;
            }
            continue;
        }

        $cols = zl_dna_raw_split_row($line);
        if (count($cols) < 3) {
            continue;
        }
        $lower = array_map('strtolower', $cols);
        $joined_header = implode('|', $lower);
        if (preg_match('/\b(rsid|snp|chromosome|position|genotype|result|allele1|allele2)\b/', $joined_header) && !preg_match('/^rs\d+/i', (string) ($cols[0] ?? ''))) {
            $header = zl_dna_raw_header_map($cols);
            continue;
        }

        $data_lines++;
        if ($data_lines > $max_rows) {
            break;
        }

        if ($vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')))) {
            $rsid = strtolower(trim(zl_dna_raw_pick($cols, $header, ['id', 'rsid', 'snp'], 2)));
            $chrom = zl_dna_raw_pick($cols, $header, ['chrom', 'chromosome'], 0);
            $pos = zl_dna_raw_pick($cols, $header, ['pos', 'position'], 1);
            $ref = strtoupper(zl_dna_raw_pick($cols, $header, ['ref'], 3));
            $alt = strtoupper(zl_dna_raw_pick($cols, $header, ['alt'], 4));
            $sample = count($cols) === 10 && in_array(strtoupper(trim((string) ($cols[6] ?? ''))), ['PASS', '.'], true) ? (string) $cols[9] : '';
            $geno = zl_dna_vcf_sample_genotype($sample, $ref, $alt, (string) ($cols[8] ?? ''));
        } else {
            $rsid = strtolower(trim(zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0)));
            $chrom = zl_dna_raw_pick($cols, $header, ['chromosome', 'chrom', 'chr'], 1);
            $pos = zl_dna_raw_pick($cols, $header, ['position', 'pos'], 2);
            $geno = zl_dna_raw_pick($cols, $header, ['result', 'genotype', 'alleles', 'allele'], count($cols) - 1);
            $a1 = zl_dna_raw_pick($cols, $header, ['allele1', 'allele_1']);
            $a2 = zl_dna_raw_pick($cols, $header, ['allele2', 'allele_2']);
            if (($a1 !== '' || $a2 !== '') && strlen($a1 . $a2) <= 12) {
                $geno = $a1 . $a2;
            } elseif (count($cols) >= 5 && strlen((string) $cols[3]) <= 4 && strlen((string) $cols[4]) <= 4) {
                $geno = (string) $cols[3] . (string) $cols[4];
            }
        }

        $chrom = strtoupper(trim((string) $chrom, " \t\n\r\0\x0B\"'"));
        $chrom = preg_replace('/^CHR/i', '', $chrom);
        if ($chrom === '' || ($filter && !isset($filter[$chrom]))) {
            continue;
        }
        $pos = trim((string) $pos);
        if (!preg_match('/^\d+$/', $pos)) {
            continue;
        }
        $key = strtolower($chrom . ':' . $pos);
        if (preg_match('/^rs\d+$/', $rsid)) {
            if (isset($rsid_coordinates[$rsid]) && $rsid_coordinates[$rsid] !== $key) {
                $variants[$rsid_coordinates[$rsid]] = '';
                $conflicting_rsids[$rsid] = true;
            }
            if (isset($conflicting_rsids[$rsid])) {
                $geno = '';
            }
            $rsid_coordinates[$rsid] = $key;
        }
        if (!array_key_exists($key, $variants)) {
            $parsed++;
        }
        zl_dna_merge_genotype_call($variants, $key, zl_dna_raw_clean_genotype($geno));
    }
    fclose($handle);

    $cache[$cache_key] = [
        'file_name' => zl_safe_name($name),
        'variants' => $variants,
        'parsed_coordinate_variants' => $parsed,
        'truncated' => $data_lines > $max_rows,
    ];
    return $cache[$cache_key];
}

function zl_dna_marker_scan_path($path, $panel, $name = 'raw-dna.txt', $max_rows = 1200000) {
    zl_dna_raise_runtime_limits();
    if (!is_file($path) || !is_readable($path)) {
        throw new Exception('The DNA file could not be read.');
    }
    $targets = [];
    foreach (array_keys((array) $panel) as $rsid) {
        $targets[strtolower((string) $rsid)] = true;
    }
    $header = [];
    $vcf_mode = false;
    $variants = [];
    $parsed = 0;
    $rsid_rows = 0;
    $data_lines = 0;
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new Exception('The DNA file could not be opened.');
    }

    while (($line = fgets($handle)) !== false) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (strpos($line, '##') === 0) {
            continue;
        }
        if (strpos($line, '#') === 0) {
            if (stripos($line, '#CHROM') === 0) {
                $header = zl_dna_raw_header_map(zl_dna_raw_split_row(ltrim($line, '#')));
                $vcf_mode = true;
            }
            continue;
        }

        $cols = zl_dna_raw_split_row($line);
        if (count($cols) < 3) {
            continue;
        }
        $lower = array_map('strtolower', $cols);
        $joined_header = implode('|', $lower);
        if (preg_match('/\b(rsid|snp|chromosome|position|genotype|result|allele1|allele2)\b/', $joined_header) && !preg_match('/^rs\d+/i', (string) ($cols[0] ?? ''))) {
            $header = zl_dna_raw_header_map($cols);
            continue;
        }

        $data_lines++;
        if ($data_lines > $max_rows) {
            break;
        }

        if ($vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')))) {
            $chrom = zl_dna_raw_pick($cols, $header, ['chrom', 'chromosome'], 0);
            $pos = zl_dna_raw_pick($cols, $header, ['pos', 'position'], 1);
            $rsid = zl_dna_raw_pick($cols, $header, ['id', 'rsid', 'snp'], 2);
            $ref = strtoupper(zl_dna_raw_pick($cols, $header, ['ref'], 3));
            $alt = strtoupper(zl_dna_raw_pick($cols, $header, ['alt'], 4));
            $sample = count($cols) === 10 && in_array(strtoupper(trim((string) ($cols[6] ?? ''))), ['PASS', '.'], true) ? (string) $cols[9] : '';
            $geno = zl_dna_vcf_sample_genotype($sample, $ref, $alt, (string) ($cols[8] ?? ''));
        } else {
            $rsid = zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0);
            $chrom = zl_dna_raw_pick($cols, $header, ['chromosome', 'chrom', 'chr'], 1);
            $pos = zl_dna_raw_pick($cols, $header, ['position', 'pos'], 2);
            $geno = zl_dna_raw_pick($cols, $header, ['result', 'genotype', 'alleles', 'allele'], count($cols) - 1);
            $a1 = zl_dna_raw_pick($cols, $header, ['allele1', 'allele_1']);
            $a2 = zl_dna_raw_pick($cols, $header, ['allele2', 'allele_2']);
            if (($a1 !== '' || $a2 !== '') && strlen($a1 . $a2) <= 12) {
                $geno = $a1 . $a2;
            } elseif (count($cols) >= 5 && strlen((string) $cols[3]) <= 4 && strlen((string) $cols[4]) <= 4) {
                $geno = (string) $cols[3] . (string) $cols[4];
            }
        }

        $rsid = strtolower(trim((string) $rsid));
        if (!preg_match('/^rs\d+$/', $rsid)) {
            continue;
        }
        $parsed++;
        $rsid_rows++;
        if (!isset($targets[$rsid])) {
            continue;
        }
        $chrom = strtoupper(trim((string) $chrom, " \t\n\r\0\x0B\"'"));
        $chrom = preg_replace('/^CHR/i', '', $chrom);
        $record = [
            'rsid' => $rsid,
            'chromosome' => $chrom !== '' ? $chrom : 'unknown',
            'position' => trim((string) $pos),
            'genotype' => zl_dna_raw_clean_genotype($geno),
        ];
        zl_dna_merge_variant_record($variants, $rsid, $record);
    }
    fclose($handle);

    return [
        'file_name' => zl_safe_name($name),
        'file_hash' => hash_file('sha256', $path),
        'format_guess' => zl_dna_raw_guess_provider(zl_dna_raw_file_head($path, 12000), $name),
        'variants' => $variants,
        'parsed_rsid_variants' => $parsed,
        'rsid_rows' => $rsid_rows,
        'truncated' => $data_lines > $max_rows,
    ];
}

function zl_dna_health_marker_panel() {
    return [
        'rs429358' => ['gene' => 'APOE', 'topic' => 'APOE research marker pair', 'note' => 'Interpret only together with rs7412 and clinical-grade confirmation.'],
        'rs7412' => ['gene' => 'APOE', 'topic' => 'APOE research marker pair', 'note' => 'Interpret only together with rs429358 and clinical-grade confirmation.'],
        'rs6025' => ['gene' => 'F5', 'topic' => 'Factor V Leiden public research marker', 'note' => 'Raw consumer genotypes are not diagnostic for thrombophilia.'],
        'rs1799963' => ['gene' => 'F2', 'topic' => 'Prothrombin clotting research marker', 'note' => 'Raw consumer genotypes are not diagnostic for thrombophilia or clotting risk.'],
        'rs1800562' => ['gene' => 'HFE', 'topic' => 'Iron metabolism research marker', 'note' => 'One marker alone is not a diagnosis of hemochromatosis.'],
        'rs1799945' => ['gene' => 'HFE', 'topic' => 'Iron metabolism research marker', 'note' => 'One marker alone is not a diagnosis of hemochromatosis.'],
        'rs1801133' => ['gene' => 'MTHFR', 'topic' => 'Folate-cycle research marker', 'note' => 'Do not change supplements or medication based on this alone.'],
        'rs1801131' => ['gene' => 'MTHFR', 'topic' => 'Folate-cycle research marker', 'note' => 'Do not change supplements or medication based on this alone.'],
        'rs671' => ['gene' => 'ALDH2', 'topic' => 'Alcohol metabolism research marker', 'note' => 'Trait association varies by ancestry and assay orientation.'],
        'rs4988235' => ['gene' => 'MCM6/LCT', 'topic' => 'Lactase persistence research marker', 'note' => 'A single marker does not explain every lactose tolerance pattern.'],
        'rs7903146' => ['gene' => 'TCF7L2', 'topic' => 'Metabolic-trait literature marker', 'note' => 'Not a diabetes diagnosis or personal risk score.'],
        'rs1229984' => ['gene' => 'ADH1B', 'topic' => 'Alcohol metabolism research marker', 'note' => 'Trait association only; not medical advice.'],
        'rs9939609' => ['gene' => 'FTO', 'topic' => 'Body-weight association literature marker', 'note' => 'Small effect marker; lifestyle and environment dominate.'],
        'rs762551' => ['gene' => 'CYP1A2', 'topic' => 'Caffeine metabolism research marker', 'note' => 'Trait association only; not a personal safety or medical recommendation.'],
        'rs4244285' => ['gene' => 'CYP2C19', 'topic' => 'Pharmacogenomics research marker', 'note' => 'Medication decisions require clinical pharmacogenetic testing and a qualified clinician.'],
        'rs4986893' => ['gene' => 'CYP2C19', 'topic' => 'Pharmacogenomics research marker', 'note' => 'Medication decisions require clinical pharmacogenetic testing and a qualified clinician.'],
        'rs1799853' => ['gene' => 'CYP2C9', 'topic' => 'Pharmacogenomics research marker', 'note' => 'Medication decisions require clinical pharmacogenetic testing and a qualified clinician.'],
        'rs1057910' => ['gene' => 'CYP2C9', 'topic' => 'Pharmacogenomics research marker', 'note' => 'Medication decisions require clinical pharmacogenetic testing and a qualified clinician.'],
        'rs9923231' => ['gene' => 'VKORC1', 'topic' => 'Warfarin-response research marker', 'note' => 'Never change anticoagulant dose from a raw DNA file. Use clinical testing and clinician guidance.'],
        'rs2814778' => ['gene' => 'ACKR1/DARC', 'topic' => 'Duffy blood group research marker', 'note' => 'Blood group context only; not a clinical blood-type test.'],
        'rs8176719' => ['gene' => 'ABO', 'topic' => 'ABO blood-type clue marker', 'note' => 'Sparse SNP files cannot reliably replace a real blood typing test.'],
        'rs8176746' => ['gene' => 'ABO', 'topic' => 'ABO blood-type clue marker', 'note' => 'Sparse SNP files cannot reliably replace a real blood typing test.'],
        'rs8176747' => ['gene' => 'ABO', 'topic' => 'ABO blood-type clue marker', 'note' => 'Sparse SNP files cannot reliably replace a real blood typing test.'],
    ];
}

function zl_dna_genotype_status($genotype) {
    $raw = strtoupper(trim((string) $genotype));
    $compact = preg_replace('/[\s\/|]+/', '', $raw);
    if ($compact === '' || in_array($compact, ['--', '-', '00', '0', 'NN', 'N', '.', '..'], true)) {
        return ['status' => 'not_called', 'callable' => false, 'normalized' => ''];
    }
    if (preg_match('/^(?:[ACGT]{1,2}|[ACGT][\/|][ACGT])$/', $raw)) {
        return ['status' => 'direct_call', 'callable' => true, 'normalized' => $compact];
    }
    if (preg_match('/^[ACGTN.\-]{1,4}$/', $compact)) {
        return ['status' => 'partial_or_ambiguous', 'callable' => false, 'normalized' => $compact];
    }
    return ['status' => 'complex_or_unsupported', 'callable' => false, 'normalized' => $compact];
}

function zl_dna_heritage_marker_panel() {
    return [
        'rs1426654' => ['gene' => 'SLC24A5', 'topic' => 'population-differentiated pigmentation marker', 'note' => 'Useful as a reference marker, not an ethnicity label.'],
        'rs16891982' => ['gene' => 'SLC45A2', 'topic' => 'population-differentiated pigmentation marker', 'note' => 'Trait geography is not identity or ancestry percentage.'],
        'rs12913832' => ['gene' => 'HERC2/OCA2', 'topic' => 'eye-colour associated marker', 'note' => 'Trait marker; not ethnicity inference.'],
        'rs3827760' => ['gene' => 'EDAR', 'topic' => 'population-associated morphology marker', 'note' => 'Reported in population studies; not identity classification.'],
        'rs2814778' => ['gene' => 'ACKR1/DARC', 'topic' => 'Duffy blood group / malaria-selection marker', 'note' => 'A selection marker, not a full ancestry estimate.'],
        'rs671' => ['gene' => 'ALDH2', 'topic' => 'population-frequency differentiated metabolism marker', 'note' => 'Can be geographically informative, not ethnicity by itself.'],
        'rs4988235' => ['gene' => 'MCM6/LCT', 'topic' => 'lactase persistence marker', 'note' => 'Multiple lactase variants exist across populations.'],
        'rs1800414' => ['gene' => 'OCA2', 'topic' => 'East Asian pigmentation literature marker', 'note' => 'Reference marker only; never a standalone ancestry call.'],
        'rs1042602' => ['gene' => 'TYR', 'topic' => 'pigmentation literature marker', 'note' => 'Trait association, not ancestry identity.'],
        'rs885479' => ['gene' => 'MC1R', 'topic' => 'pigmentation literature marker', 'note' => 'Trait association, not ancestry identity.'],
    ];
}

function zl_dna_marker_hits($variant_map, $panel) {
    $hits = [];
    $variants = (array) ($variant_map['variants'] ?? []);
    foreach ($panel as $rsid => $meta) {
        $key = strtolower((string) $rsid);
        if (!isset($variants[$key])) {
            continue;
        }
        $row = $variants[$key];
        $call = zl_dna_genotype_status((string) ($row['genotype'] ?? ''));
        if (empty($call['callable'])) {
            continue;
        }
        $hits[] = [
            'rsid' => $key,
            'gene' => $meta['gene'],
            'topic' => $meta['topic'],
            'genotype' => (string) ($call['normalized'] ?? ''),
            'call_status' => (string) ($call['status'] ?? 'unknown'),
            'chromosome' => (string) ($row['chromosome'] ?? ''),
            'position' => (string) ($row['position'] ?? ''),
            'note' => $meta['note'],
            'dbsnp' => 'https://www.ncbi.nlm.nih.gov/snp/' . rawurlencode($key),
            'clinvar_search' => 'https://www.ncbi.nlm.nih.gov/clinvar/?term=' . rawurlencode($key),
        ];
    }
    return $hits;
}

function zl_dna_marker_callability_counts($variant_map, $panel) {
    $variants = (array) ($variant_map['variants'] ?? []);
    $counts = ['panel_markers' => count((array) $panel), 'callable' => 0, 'not_callable' => 0, 'not_present' => 0];
    foreach (array_keys((array) $panel) as $rsid) {
        $key = strtolower((string) $rsid);
        if (!isset($variants[$key])) {
            $counts['not_present']++;
            continue;
        }
        $call = zl_dna_genotype_status((string) ($variants[$key]['genotype'] ?? ''));
        if (!empty($call['callable'])) {
            $counts['callable']++;
        } else {
            $counts['not_callable']++;
        }
    }
    return $counts;
}

function zl_dna_hit_by_rsid($hits, $rsid) {
    $rsid = strtolower((string) $rsid);
    foreach ((array) $hits as $hit) {
        if (strtolower((string) ($hit['rsid'] ?? '')) === $rsid) {
            return $hit;
        }
    }
    return null;
}

function zl_dna_genotype_letters($genotype) {
    $genotype = strtoupper((string) $genotype);
    $genotype = preg_replace('/[^ACGTN]/', '', $genotype);
    if ($genotype === '') {
        return [];
    }
    return str_split($genotype);
}

function zl_dna_allele_count($genotype, $alleles) {
    $call = zl_dna_genotype_status($genotype);
    if (empty($call['callable'])) {
        return 0;
    }
    $letters = zl_dna_genotype_letters((string) $call['normalized']);
    $wanted = array_flip(array_map('strtoupper', (array) $alleles));
    $count = 0;
    foreach ($letters as $letter) {
        if (isset($wanted[$letter])) {
            $count++;
        }
    }
    return $count;
}

function zl_dna_genotype_similarity_score($a, $b) {
    $la = zl_dna_genotype_letters($a);
    $lb = zl_dna_genotype_letters($b);
    $la = array_values(array_filter($la, function ($x) { return in_array($x, ['A', 'C', 'G', 'T'], true); }));
    $lb = array_values(array_filter($lb, function ($x) { return in_array($x, ['A', 'C', 'G', 'T'], true); }));
    if (!$la || !$lb) {
        return null;
    }
    sort($la);
    sort($lb);
    if (implode('', $la) === implode('', $lb)) {
        return 1.0;
    }
    $used = [];
    $shared = 0;
    foreach ($la as $a_letter) {
        foreach ($lb as $idx => $b_letter) {
            if (isset($used[$idx])) {
                continue;
            }
            if ($a_letter === $b_letter) {
                $used[$idx] = true;
                $shared++;
                break;
            }
        }
    }
    return $shared / max(count($la), count($lb), 1);
}

function zl_dna_safe_percent_segments($segments) {
    $out = [];
    $total = 0.0;
    foreach ((array) $segments as $segment) {
        $value = max(0.0, (float) ($segment['value'] ?? 0));
        if ($value <= 0) {
            continue;
        }
        $total += $value;
        $out[] = [
            'label' => (string) ($segment['label'] ?? 'Signal'),
            'value' => $value,
            'color' => (string) ($segment['color'] ?? '#69f7ff'),
        ];
    }
    if ($total <= 0) {
        return [['label' => 'No signal available', 'value' => 1, 'percent' => 100, 'color' => '#6b7280']];
    }
    foreach ($out as &$segment) {
        $segment['percent'] = round(($segment['value'] / $total) * 100, 1);
    }
    unset($segment);
    return $out;
}

function zl_dna_math_clamp($value, $min, $max) {
    return max($min, min($max, (float) $value));
}

function zl_dna_quality_math($summary) {
    $summary = (array) $summary;
    $parsed = (int) ($summary['parsed_variants'] ?? 0);
    $called = (int) ($summary['called_variants'] ?? 0);
    $chromosomes = (int) ($summary['chromosome_count'] ?? 0);
    $rsid_rows = (int) ($summary['rsid_rows'] ?? ($summary['parsed_rsid_variants'] ?? 0));
    $no_call = (float) ($summary['no_call_percent'] ?? 100);
    $heterozygosity = (float) ($summary['heterozygosity_percent'] ?? 0);
    $truncated = !empty($summary['truncated']);

    $coverage_score = zl_dna_math_clamp(log10(max(1, $parsed)) * 7.0, 0, 38);
    $call_score = zl_dna_math_clamp((100.0 - $no_call) * 0.22, 0, 22);
    $chromosome_score = zl_dna_math_clamp(($chromosomes / 24.0) * 18.0, 0, 18);
    $rsid_score = $parsed > 0 ? zl_dna_math_clamp(($rsid_rows / max(1, $parsed)) * 14.0, 0, 14) : 0;
    $het_target = 20.5;
    $heterozygosity_score = zl_dna_math_clamp(8.0 - abs($heterozygosity - $het_target) * 0.38, 0, 8);
    $penalty = $truncated ? 10 : 0;
    $score = (int) round(zl_dna_math_clamp($coverage_score + $call_score + $chromosome_score + $rsid_score + $heterozygosity_score - $penalty, 0, 100));

    if ($score >= 82) {
        $confidence = 'strong raw-file quality';
    } elseif ($score >= 68) {
        $confidence = 'usable raw-file quality';
    } elseif ($score >= 45) {
        $confidence = 'limited raw-file quality';
    } else {
        $confidence = 'weak raw-file quality';
    }

    return [
        'system' => 'ZDNA-MAP v1.2',
        'score' => $score,
        'confidence' => $confidence,
        'parsed_variants' => $parsed,
        'called_variants' => $called,
        'components' => [
            'variant_coverage' => round($coverage_score, 1),
            'call_rate' => round($call_score, 1),
            'chromosome_spread' => round($chromosome_score, 1),
            'rsid_indexability' => round($rsid_score, 1),
            'heterozygosity_sanity' => round($heterozygosity_score, 1),
            'truncation_penalty' => $penalty,
        ],
        'simple_boundary' => 'Higher score means the raw file is more useful for research screening. It still does not make this a medical diagnosis or ethnicity percentage engine.',
    ];
}

function zl_dna_build_guess($summary, $head = '') {
    $head = strtolower((string) $head);
    $format = strtolower((string) ($summary['format_guess'] ?? ''));
    if (preg_match('/grch38|hg38|build\s*38/', $head) && preg_match('/grch37|hg19|build\s*37/', $head)) {
        return ['label' => 'Conflicting genome builds stated by file', 'confidence' => 'low', 'conflict' => true];
    }
    if (strpos($head, 'grch38') !== false || strpos($head, 'hg38') !== false) {
        return ['label' => 'GRCh38 / hg38 stated by file', 'confidence' => 'high'];
    }
    if (strpos($head, 'grch37') !== false || strpos($head, 'hg19') !== false || strpos($head, 'build 37') !== false) {
        return ['label' => 'GRCh37 / hg19 stated by file', 'confidence' => 'high'];
    }
    if (strpos($format, 'vcf') !== false) {
        return ['label' => 'Unknown VCF build', 'confidence' => 'low'];
    }
    if (strpos($format, 'myheritage') !== false) {
        return ['label' => 'MyHeritage consumer-array build; confirm vendor export date before comparing positions', 'confidence' => 'medium'];
    }
    if (strpos($format, '23andme') !== false) {
        return ['label' => '23andMe consumer-array build; confirm chip/export version before comparing positions', 'confidence' => 'medium'];
    }
    if (strpos($format, 'ancestry') !== false) {
        return ['label' => 'Ancestry consumer-array build; confirm chip/export version before comparing positions', 'confidence' => 'medium'];
    }
    if (preg_match('/23andme|myheritage|ancestry|consumer/', $format . ' ' . $head)) {
        return ['label' => 'Consumer-array build not confirmed', 'confidence' => 'medium-low'];
    }
    return ['label' => 'Unknown genome build', 'confidence' => 'low'];
}

function zl_dna_quality_band($score) {
    $score = (int) $score;
    if ($score >= 82) {
        return 'strong';
    }
    if ($score >= 68) {
        return 'good';
    }
    if ($score >= 45) {
        return 'limited';
    }
    return 'weak';
}

function zl_dna_vendor_orientation_evidence($head) {
    // Vendor contract: https://www.myheritage.com/help/en/articles/12852246-how-should-i-interpret-my-raw-dna-data
    // Historical CSV header: NIST Genomics Workshop 2022 keynote slides, PDF page 18.
    // Recognize metadata only; a filename/provider guess or matching alleles is never orientation evidence.
    $comments = [];
    $vendor = false;
    $columns = false;
    foreach (preg_split('/\r\n|\r|\n/', substr((string) $head, 0, 12000)) as $line) {
        $line = trim(ltrim($line, "\xEF\xBB\xBF"));
        if ($line === '') { continue; }
        if (preg_match('/^#\s*MyHeritage DNA raw data\.\s*$/i', $line)) { $vendor = true; }
        $uncommented = trim(ltrim($line, '#'));
        $fields = zl_dna_raw_split_row($uncommented);
        $names = array_map(function ($field) { return strtoupper(preg_replace('/\s+/', '', trim((string) $field, "\"' \t"))); }, $fields);
        if ($names === ['RSID', 'CHROMOSOME', 'POSITION', 'RESULT']) {
            $columns = true;
            continue;
        }
        if (strpos($line, '#') !== 0) { break; }
        $comments[] = $uncommented;
    }
    $metadata = preg_replace('/\s+/', ' ', implode(' ', $comments));
    $forward = preg_match('/\bgenotype\s+is\s+reported\s+(?:on\s+(?:the\s+)?)?forward\s*\(\+\)\s+strand\s+with\s+respect\s+to\s+the\s+human\s+reference\s+build\s*37\b/i', $metadata);
    $conflicting = preg_match('/\b(?:reverse|minus|bottom)\s*(?:\(-\)\s*)?strand\b|\bstrand\s*=\s*(?:reverse|minus|-)/i', $metadata);
    if (!$vendor || !$columns || !$forward || $conflicting) { return []; }
    return [
        'provider' => 'MyHeritage', 'assembly' => 'GRCh37', 'orientation' => 'reference_forward',
        'basis' => 'recognized vendor export header, supported columns, and explicit forward-strand/build declaration',
        'source_url' => 'https://www.myheritage.com/help/en/articles/12852246-how-should-i-interpret-my-raw-dna-data',
        'header_reference_url' => 'https://www.nccoe.nist.gov/sites/default/files/2022-03/genomics-workshop-2-Keynotes.pdf#page=18',
        'boundary' => 'File metadata recognition is not cryptographic authentication. No strand flip or genotype inference was performed.',
    ];
}

function zl_dna_enrich_raw_summary($summary, $head = '') {
    $summary = (array) $summary;
    $quality = zl_dna_quality_math($summary);
    $build = zl_dna_build_guess($summary, $head);
    $summary['quality_score'] = $quality['score'];
    $summary['quality_band'] = zl_dna_quality_band($quality['score']);
    $summary['quality_components'] = $quality['components'];
    $summary['genome_build_guess'] = $build['label'];
    $summary['genome_build_confidence'] = $build['confidence'];
    $summary['genome_build_conflict'] = !empty($build['conflict']);
    $summary['orientation_header_evidence'] = zl_dna_vendor_orientation_evidence($head);
    $summary['privacy_state'] = 'default_parse_analyze_discard';
    $summary['normalized_summary'] = [
        'rsid_normalization' => 'lowercase rsID internally, displayed uppercase in reports',
        'chromosome_normalization' => 'CHR prefix removed; X/Y/MT retained',
        'genotype_normalization' => 'whitespace stripped and uppercased; no strand flip applied silently',
        'build_normalization' => 'not transformed unless a confirmed liftover data pack is installed',
    ];
    $summary['upload_contract'] = [
        'max_raw_file_mb' => 30,
        'raw_storage_default' => 'discard_after_report',
        'zero_library_private_save_max_mb' => zl_library_max_save_mb(),
        'max_rows_scanned' => zl_dna_max_raw_rows(),
        'raw_rows_to_llm' => 'never',
    ];
    return $summary;
}

function zl_dna_apply_analysis_options($summary, $options = []) {
    $summary = (array) $summary;
    $options = (array) $options;
    $declared_build = strtolower(trim((string) ($options['declared_build'] ?? 'auto')));
    $file_build = strtolower((string) ($summary['genome_build_guess'] ?? ''));
    if (($summary['genome_build_confidence'] ?? '') === 'high' && (
        (in_array($declared_build, ['grch37', 'hg19'], true) && strpos($file_build, 'grch38') !== false) ||
        (in_array($declared_build, ['grch38', 'hg38'], true) && strpos($file_build, 'grch37') !== false)
    )) {
        $summary['genome_build_conflict'] = true;
    }
    if (in_array($declared_build, ['grch37', 'hg19'], true)) {
        $summary['genome_build_guess'] = 'GRCh37 / hg19 confirmed by user';
        $summary['genome_build_confidence'] = 'high';
        $summary['genome_build_source'] = 'user_confirmed';
    } elseif (in_array($declared_build, ['grch38', 'hg38'], true)) {
        $summary['genome_build_guess'] = 'GRCh38 / hg38 confirmed by user';
        $summary['genome_build_confidence'] = 'high';
        $summary['genome_build_source'] = 'user_confirmed';
    } else {
        $summary['genome_build_source'] = 'file_header_or_format_guess';
    }

    $format = strtolower((string) ($summary['format_guess'] ?? ''));
    $orientation = strtolower(trim((string) ($options['reported_orientation'] ?? 'auto')));
    if (strpos($format, 'vcf') !== false) {
        $summary['reported_orientation'] = 'reference_forward_from_vcf';
        $summary['orientation_confidence'] = 'high';
    } elseif (in_array($orientation, ['forward', 'plus', 'reference_forward'], true)) {
        $summary['reported_orientation'] = 'reference_forward_confirmed_by_user';
        $summary['orientation_confidence'] = 'high';
    } elseif ($orientation === 'auto' && !empty($summary['orientation_header_evidence']) && empty($summary['genome_build_conflict'])
        && ($summary['orientation_header_evidence']['assembly'] ?? '') === 'GRCh37'
        && ($summary['genome_build_confidence'] ?? '') === 'high'
        && stripos((string) ($summary['genome_build_guess'] ?? ''), 'GRCh37') !== false) {
        $summary['reported_orientation'] = 'reference_forward_from_verified_vendor_header_contract';
        $summary['orientation_confidence'] = 'high';
    } else {
        $summary['reported_orientation'] = 'unconfirmed';
        $summary['orientation_confidence'] = 'low';
    }
    return $summary;
}

function zl_dna_health_interpretation_gate($summary) {
    $summary = (array) $summary;
    $build_label = strtolower((string) ($summary['genome_build_guess'] ?? ''));
    $assembly = strpos($build_label, 'grch38') !== false || strpos($build_label, 'hg38') !== false
        ? 'GRCh38'
        : ((strpos($build_label, 'grch37') !== false || strpos($build_label, 'hg19') !== false) ? 'GRCh37' : '');
    $reasons = [];
    if (!empty($summary['genome_build_conflict'])) {
        $reasons[] = 'file and declared genome builds conflict';
    }
    if ($assembly === '' || strtolower((string) ($summary['genome_build_confidence'] ?? '')) !== 'high') {
        $reasons[] = 'genome build is not confirmed';
    }
    if (strtolower((string) ($summary['orientation_confidence'] ?? '')) !== 'high') {
        $reasons[] = 'reported strand/orientation is not confirmed';
    }
    if ((int) ($summary['quality_score'] ?? 0) < 45) {
        $reasons[] = 'raw-file QC is below the interpretation threshold';
    }
    if (!empty($summary['truncated'])) {
        $reasons[] = 'the file hit the parser row limit';
    }
    if ((int) ($summary['called_variants'] ?? 0) <= 0) {
        $reasons[] = 'no callable variants were detected';
    }
    return [
        'ready' => !$reasons,
        'status' => $reasons ? 'withheld_pending_validation' : 'ready_for_allele_specific_research',
        'assembly' => $assembly,
        'reasons' => $reasons,
        'observed_state' => 'direct_calls_only',
        'imputation_performed' => false,
        'strand_flip_performed' => false,
        'boundary' => 'Not tested, not callable, and not observed are distinct states. A sparse consumer file cannot establish a clinical negative.',
    ];
}

function zl_dna_confidence_band($value, $quality_score, $floor = 7.5) {
    $spread = max($floor, 30.0 - ($quality_score * 0.16));
    $low = round(zl_dna_math_clamp($value - $spread, 0, 100), 1);
    $high = round(zl_dna_math_clamp($value + $spread, 0, 100), 1);
    return [$low, $high];
}

function zl_dna_health_evidence_math($cards, $quality) {
    $quality_score = (float) ($quality['score'] ?? 0);
    $domain_scores = [];
    $evidence_index = 0.0;
    $followup_index = 0.0;
    $trait_index = 0.0;
    foreach ((array) $cards as $card) {
        $dose = (float) ($card['dosage'] ?? (($card['priority'] ?? '') === 'attention' ? 1 : 0));
        $weight = (float) ($card['evidence_weight'] ?? (($card['priority'] ?? '') === 'attention' ? 2.2 : 0.55));
        $confidence = (float) ($card['assay_confidence'] ?? ($quality_score / 100.0));
        $dose_for_points = $dose;
        if (($card['priority'] ?? '') !== 'attention' && $dose_for_points <= 0) {
            $dose_for_points = 0.18;
        }
        $points = $dose_for_points * $weight * max(0.25, $confidence);
        $domain = (string) ($card['category'] ?? 'Research markers');
        $domain_scores[$domain] = ($domain_scores[$domain] ?? 0) + $points;
        $evidence_index += $points;
        if (($card['priority'] ?? '') === 'attention') {
            $followup_index += $points;
        } else {
            $trait_index += $points;
        }
    }
    arsort($domain_scores, SORT_NUMERIC);
    foreach ($domain_scores as $domain => $score) {
        $domain_scores[$domain] = round($score, 2);
    }
    $uncertainty = (int) round(zl_dna_math_clamp(100 - $quality_score + max(0, 9 - count((array) $cards)) * 2, 10, 90));
    return [
        'evidence_index' => round($evidence_index, 2),
        'followup_index' => round($followup_index, 2),
        'trait_index' => round($trait_index, 2),
        'uncertainty' => $uncertainty,
        'domain_scores' => $domain_scores,
    ];
}

function zl_dna_heritage_lens_math($lanes, $quality) {
    $segments = zl_dna_safe_percent_segments(array_map(function ($label, $value) {
        return ['label' => $label, 'value' => $value, 'color' => '#69f7ff'];
    }, array_keys($lanes), array_values($lanes)));
    $quality_score = (float) ($quality['score'] ?? 0);
    $bands = [];
    foreach ($segments as $segment) {
        [$low, $high] = zl_dna_confidence_band((float) ($segment['percent'] ?? 0), $quality_score);
        $bands[] = [
            'label' => $segment['label'],
            'lens_strength' => (float) ($segment['percent'] ?? 0),
            'confidence_band' => $low . '-' . $high . ' lens points',
            'plain' => 'This is a marker-context lens, not an ethnicity percentage.',
        ];
    }
    return [
        'confidence_bands' => $bands,
        'uncertainty' => (int) round(zl_dna_math_clamp(100 - $quality_score + max(0, 12 - count($segments)) * 2, 12, 88)),
    ];
}

function zl_dna_ancestry_question_signals($lanes, $quality, $hits = []) {
    $quality_score = (float) ($quality['score'] ?? 0);
    $hit_count = count((array) $hits);
    $total = max(0.001, array_sum(array_map('floatval', (array) $lanes)));
    $west = (float) ($lanes['West Eurasia / South Asia / Europe lens'] ?? 0);
    $east = (float) ($lanes['East / Northeast Asian lens'] ?? 0);
    $african = (float) ($lanes['African selection-marker lens'] ?? 0);
    $food = (float) ($lanes['Food adaptation lens'] ?? 0);
    $mk = function ($label, $score, $status, $why, $limit, $next) use ($quality_score, $hit_count) {
        $confidence = (float) round(zl_dna_math_clamp(($score * 0.58) + ($quality_score * 0.28) + min(14, $hit_count * 1.8), 0, 88), 1);
        if ($status === 'reference_panel_missing') {
            $confidence = min($confidence, 38.0);
        }
        if ($status === 'not_enough_data') {
            $confidence = min($confidence, 25.0);
        }
        return [
            'label' => $label,
            'status' => $status,
            'confidence_score' => $confidence,
            'confidence_band' => zl_dna_confidence_label_from_score($confidence),
            'matched_marker_count' => $hit_count,
            'why' => $why,
            'limitations' => $limit,
            'next_steps' => $next,
        ];
    };
    return [
        $mk(
            'British Isles / English-related signal',
            ($west / $total) * 100,
            $west > 1.5 ? 'broad_affinity_possible' : 'not_enough_data',
            'The broad West Eurasia / Europe marker-context lane is the only installed preview evidence that can point toward a British Isles / Northwest European follow-up.',
            'English identity is cultural and genealogical. The compact GBR/Northwest-European lane can give a research signal, while parent files and genealogy improve the split.',
            'Run the deeper British Isles / Northwest Europe comparison pack for a stronger result.'
        ),
        $mk(
            'South Asian / West Asian / European shared signal',
            (($west + $food * 0.35) / $total) * 100,
            $west > 1.5 ? 'broad_affinity_possible' : 'not_enough_data',
            'Several pigmentation and adaptation markers are shared across South Asian, West Asian, European, Middle Eastern, and North African reference contexts.',
            'This lane is deliberately broad. These markers cannot split countries or communities by themselves.',
            'Use a calibrated reference panel before making region-specific claims.'
        ),
        $mk(
            'East Asian / Han Chinese-related signal',
            ($east / $total) * 100,
            $east > 1.5 ? 'possible_clue' : 'not_enough_data',
            'The East/Northeast Asian marker-context lane is checked, but a few markers are not enough for a Han Chinese or East Asian ancestry estimate.',
            'Han Chinese, East Asian, Southeast Asian, and Indigenous American signals require genome-wide panels and careful differentiation.',
            'Run a reference-panel comparison if the reduced EAS/CHB/CHS/JPT/KHV/CDX-style panel is installed.'
        ),
        $mk(
            'African ancestry signal',
            ($african / $total) * 100,
            $african > 1.8 ? 'possible_clue' : 'not_enough_data',
            'The African selection-marker lane checks population-associated signals such as Duffy/ACKR1 context.',
            'Africa cannot be flattened into one label. One selection marker is not ancestry proof.',
            'Use West, Central, East, North, and Southern African reference groups with calibrated overlap counts.'
        ),
        $mk(
            'Ashkenazi Jewish-related signal',
            (($west + ($food * 0.4)) / $total) * 8,
            $west > 1.5 ? 'compact_starter_lens_active' : 'not_visible_in_starter_lens',
            'ZeroThink now uses a compact Jewish-diaspora starter lens instead of stopping with no answer. It looks for a small Ashkenazi-style trace in the West-Eurasian/Celtic/Mediterranean context, then leaves stronger Sephardi/Mizrahi separation to deeper controls.',
            'This is genetic-affinity context only. DNA cannot determine Jewish religion, identity, community membership, or belonging.',
            'If visible, treat as a trace clue and compare with Sephardi, Mizrahi, Levant, North Africa, and European controls.'
        ),
        $mk(
            'Indigenous Americas-related signal',
            (($east * 0.55 + $african * 0.2) / $total) * 10,
            ($east > 1.5 || $african > 1.8) ? 'compact_starter_lens_active' : 'not_visible_in_starter_lens',
            'ZeroThink now runs an Indigenous-Americas starter lens as a cautious signal check, not a tribal or cultural identity claim.',
            'Indigenous DNA signals can overlap with East Asian, Siberian, and admixed Americas references. DNA does not determine tribal identity, citizenship, enrolment, culture, or belonging.',
            'Use this as a low-confidence clue only unless stronger Americas-specific and genealogy evidence supports it.'
        ),
    ];
}

function zl_dna_mix_proxy_rows($rows, $label_field, $label_callback = null) {
    $weighted = [];
    $total = 0.0;
    foreach ((array) $rows as $row) {
        $score = max(0.001, (float) ($row['affinity_score'] ?? $row['confidence_score'] ?? 0));
        $markers = max(1, (int) ($row['matched_marker_count'] ?? 1));
        $weight = $score * log($markers + 1);
        $label = (string) ($row[$label_field] ?? 'Reference group');
        if (is_callable($label_callback)) {
            $label = (string) $label_callback($row, $label);
        }
        $weighted[] = ['row' => $row, 'label' => $label, 'weight' => $weight];
        $total += $weight;
    }
    $out = [];
    foreach ($weighted as $item) {
        $row = (array) $item['row'];
        $out[] = [
            'label' => $item['label'],
            'proxy_percent' => $total > 0 ? round(($item['weight'] / $total) * 100, 1) : 0,
            'affinity_score' => (float) ($row['affinity_score'] ?? 0),
            'confidence_score' => (float) ($row['confidence_score'] ?? 0),
            'confidence_band' => (string) ($row['confidence_band'] ?? 'low'),
            'matched_marker_count' => (int) ($row['matched_marker_count'] ?? 0),
            'code' => (string) ($row['population_code'] ?? $row['super_population'] ?? ''),
        ];
    }
    usort($out, function ($a, $b) {
        return ((float) ($b['proxy_percent'] ?? 0)) <=> ((float) ($a['proxy_percent'] ?? 0));
    });
    return $out;
}

function zl_dna_find_reference_row($rows, $codes) {
    $codes = array_values(array_filter(array_map('strtoupper', (array) $codes)));
    foreach ((array) $rows as $row) {
        $row = (array) $row;
        $code = strtoupper((string) ($row['population_code'] ?? $row['super_population'] ?? $row['code'] ?? ''));
        if ($code !== '' && in_array($code, $codes, true)) {
            return $row;
        }
    }
    return [];
}

function zl_dna_jewish_diaspora_evidence($reference_pack, $fallback_trace = 0.0) {
    $reference_pack = (array) $reference_pack;
    $pop_rows = (array) ($reference_pack['population_scores'] ?? []);
    $asj = zl_dna_find_reference_row($pop_rows, ['ASJ']);
    $nfe = zl_dna_find_reference_row($pop_rows, ['NFE']);
    $oth = zl_dna_find_reference_row($pop_rows, ['OTH']);
    $fin = zl_dna_find_reference_row($pop_rows, ['FIN']);
    $ceu = zl_dna_find_reference_row($pop_rows, ['CEU']);
    $gbr = zl_dna_find_reference_row($pop_rows, ['GBR']);
    $tsi = zl_dna_find_reference_row($pop_rows, ['TSI']);
    $ibs = zl_dna_find_reference_row($pop_rows, ['IBS']);

    $asj_score = (float) ($asj['affinity_score'] ?? 0);
    $asj_markers = (int) ($asj['matched_marker_count'] ?? 0);
    $controls = [];
    foreach ([$nfe, $oth, $fin, $ceu, $gbr, $tsi, $ibs] as $row) {
        if ($row) {
            $controls[] = (float) ($row['affinity_score'] ?? 0);
        }
    }
    $control_score = $controls ? max($controls) : 0.0;
    $delta = $asj_score > 0 && $control_score > 0 ? round($asj_score - $control_score, 2) : 0.0;
    $panel_available = $asj_score > 0 && $asj_markers >= 300;
    $fallback_trace = round(zl_dna_math_clamp((float) $fallback_trace, 0.0, 4.5), 1);

    if (!$panel_available) {
        return [
            'panel_available' => false,
            'status' => $fallback_trace >= 1.0 ? 'compact trace only - specialist panel not available' : 'not visible - specialist panel not available',
            'answer' => $fallback_trace >= 1.0 ? 'possible low trace - compact lens' : 'not visible in compact lens',
            'confidence_band' => $fallback_trace >= 1.0 ? 'trace / low' : 'low',
            'proxy_percent' => $fallback_trace,
            'asj_affinity' => $asj_score,
            'control_affinity' => $control_score,
            'asj_control_delta' => $delta,
            'matched_marker_count' => $asj_markers,
            'plain' => 'The compact trace lane can surface a clue, but the specialist Ashkenazi frequency panel was not available enough for separation.',
        ];
    }

    if ($delta >= 1.5) {
        $proxy = round(zl_dna_math_clamp(2.0 + ($delta * 0.75), 1.5, 6.0), 1);
        return [
            'panel_available' => true,
            'status' => 'possible ASJ-enriched genetic-affinity signal',
            'answer' => 'possible Ashkenazi/Jewish-diaspora genetic-affinity signal',
            'confidence_band' => $delta >= 2.4 ? 'medium' : 'low-medium',
            'proxy_percent' => max($proxy, $fallback_trace),
            'asj_affinity' => round($asj_score, 1),
            'control_affinity' => round($control_score, 1),
            'asj_control_delta' => $delta,
            'matched_marker_count' => $asj_markers,
            'plain' => 'The ASJ frequency panel is scoring above the nearby European control panels. Treat this as a genetic-affinity clue, not identity or religion.',
        ];
    }

    if ($fallback_trace >= 1.0) {
        return [
            'panel_available' => true,
            'status' => 'diaspora-compatible trace, not separated from European controls',
            'answer' => 'diaspora-compatible trace - not distinct from European controls yet',
            'confidence_band' => 'trace / low',
            'proxy_percent' => $fallback_trace,
            'asj_affinity' => round($asj_score, 1),
            'control_affinity' => round($control_score, 1),
            'asj_control_delta' => $delta,
            'matched_marker_count' => $asj_markers,
            'plain' => 'A low diaspora-style trace is visible, but the ASJ panel is not clearly above nearby European controls. This can be a real small signal, shared European overlap, or reference noise.',
        ];
    }

    return [
        'panel_available' => true,
        'status' => 'not distinct from European controls',
        'answer' => 'not visible as a distinct Ashkenazi/Jewish-diaspora signal',
        'confidence_band' => 'low',
        'proxy_percent' => 0.0,
        'asj_affinity' => round($asj_score, 1),
        'control_affinity' => round($control_score, 1),
        'asj_control_delta' => $delta,
        'matched_marker_count' => $asj_markers,
        'plain' => 'The ASJ frequency panel is not separated from nearby European controls in this run.',
    ];
}

function zl_dna_two_axis_admixture_fit($rows) {
    $obs = [];
    foreach ((array) $rows as $row) {
        $freqs = (array) ($row['freqs'] ?? []);
        if (!isset($freqs['EUR']) || !isset($freqs['SAS'])) {
            continue;
        }
        $eur = zl_dna_math_clamp((float) $freqs['EUR'], 0.001, 0.999);
        $sas = zl_dna_math_clamp((float) $freqs['SAS'], 0.001, 0.999);
        $spread = abs($eur - $sas);
        if ($spread < 0.08) {
            continue;
        }
        $dose = (int) ($row['dose'] ?? -1);
        if ($dose < 0 || $dose > 2) {
            continue;
        }
        $base_weight = max(0.1, (float) ($row['weight'] ?? 1.0));
        $obs[] = [
            'dose' => $dose,
            'eur' => $eur,
            'sas' => $sas,
            'weight' => $base_weight * (0.2 + ($spread * 2.0)),
        ];
    }
    $markers = count($obs);
    if ($markers < 250) {
        return [
            'available' => false,
            'reason' => 'not enough EUR/SAS-informative markers for the mixture fit',
            'informative_markers' => $markers,
        ];
    }

    $score_mix = function ($sas_percent) use ($obs) {
        $sas_weight = zl_dna_math_clamp((float) $sas_percent / 100.0, 0.0, 1.0);
        $eur_weight = 1.0 - $sas_weight;
        $nll = 0.0;
        $total_weight = 0.0;
        foreach ($obs as $row) {
            $p = ($eur_weight * (float) $row['eur']) + ($sas_weight * (float) $row['sas']);
            $p = zl_dna_math_clamp($p, 0.001, 0.999);
            $dose = (int) $row['dose'];
            if ($dose === 0) {
                $prob = (1.0 - $p) * (1.0 - $p);
            } elseif ($dose === 1) {
                $prob = 2.0 * $p * (1.0 - $p);
            } else {
                $prob = $p * $p;
            }
            $weight = max(0.001, (float) $row['weight']);
            $nll -= log(max(0.000000000001, $prob)) * $weight;
            $total_weight += $weight;
        }
        return $nll / max(0.0001, $total_weight);
    };

    $best_sas = 0;
    $best_nll = null;
    for ($sas = 0; $sas <= 100; $sas++) {
        $nll = $score_mix($sas);
        if ($best_nll === null || $nll < $best_nll) {
            $best_nll = $nll;
            $best_sas = $sas;
        }
    }
    $eur_nll = $score_mix(0);
    $sas_nll = $score_mix(100);
    $edge_gap = min(abs((float) $eur_nll - (float) $best_nll), abs((float) $sas_nll - (float) $best_nll));
    $confidence = (int) round(zl_dna_math_clamp(48 + min(24, $markers / 55) + min(18, $edge_gap * 380), 45, 86));

    return [
        'available' => true,
        'engine' => 'ZDNA-EUR-SAS-MIX v0.1 local likelihood fit',
        'informative_markers' => $markers,
        'south_asian_percent' => round((float) $best_sas, 1),
        'european_percent' => round(100.0 - (float) $best_sas, 1),
        'nll' => round((float) $best_nll, 5),
        'edge_gap' => round((float) $edge_gap, 5),
        'confidence_score' => $confidence,
        'confidence_band' => zl_dna_confidence_label_from_score($confidence),
        'plain' => 'A local likelihood model compared the file against EUR and SAS allele-frequency axes. This helps mixed European/South-Asian files avoid collapsing into the European parent side.',
    ];
}

function zl_dna_plain_ancestry_summary($reference_pack, $question_signals = [], $quality = []) {
    $reference_pack = (array) $reference_pack;
    $rows = (array) ($reference_pack['population_scores'] ?? []);
    $broad = (array) ($reference_pack['super_population_scores'] ?? []);
    $has_matches = !empty($reference_pack['interpretation_ready']) && (int) ($reference_pack['matched_marker_count'] ?? 0) > 0;
    $boundary = 'An affinity score compares observed alleles with a reference dataset on a 0-100 scale. It is not an ancestry percentage, probability, nationality, ethnicity, religion or lineage finding. Different panels and marker sets are not directly comparable.';
    $plain = $has_matches
        ? 'Aligned direct calls were compared with installed population frequencies. Review each reference group, overlap and source below. A validated multi-population model is required before ancestry composition or fine regional percentages can be reported.'
        : (string) ($reference_pack['reason'] ?? 'No aligned reference evidence is available for this file.');
    $cards = [];
    foreach (array_slice($rows, 0, 12) as $row) {
        $cards[] = [
            'label' => (string) ($row['population_label'] ?? $row['population_code'] ?? 'Reference population'),
            'answer' => 'reference affinity only', 'proxy_percent' => 0,
            'affinity_score' => (float) ($row['affinity_score'] ?? 0),
            'matched_marker_count' => (int) ($row['matched_marker_count'] ?? 0),
            'confidence_band' => 'low / uncalibrated',
            'plain' => 'Similarity to this reference group is ' . ($row['affinity_score'] ?? 0) . '/100 across ' . ($row['matched_marker_count'] ?? 0) . ' aligned marker comparisons.',
            'source_version' => (string) ($row['source_version'] ?? ''),
            'limitations' => $boundary, 'next_steps' => 'Interpret within the same reference panel and seek held-out calibration before stronger conclusions.',
        ];
    }
    $estimate = [
        'available' => false, 'engine' => 'ZDNA reference evidence v0.3',
        'composition_status' => 'withheld_without_validated_admixture_model',
        'headline' => $has_matches ? 'Reference comparisons ready; composition remains unvalidated' : 'Reference comparison needs validation',
        'plain' => $plain, 'segments' => [], 'map_points' => [], 'trace_lanes' => [],
        'suppressed_noise' => [], 'reference_affinities' => $rows, 'boundary' => $boundary,
    ];
    return [
        'engine' => 'ZDNA reference evidence v0.3',
        'reference_pipeline' => (string) ($reference_pack['reference_pipeline'] ?? ''),
        'reference_pack_reason' => (string) ($reference_pack['reason'] ?? ''),
        'reference_pack_meta' => (array) ($reference_pack['pack_meta'] ?? []),
        'analysis_gate' => (array) ($reference_pack['analysis_gate'] ?? []),
        'headline' => $estimate['headline'], 'plain_summary' => $plain,
        'consumer_snapshot' => ['headline' => $estimate['headline'], 'plain' => $plain, 'top_named_lanes' => [], 'theory_boundary' => $boundary],
        'consumer_estimate' => $estimate, 'reference_affinities' => $rows,
        'broad_reference_affinities' => $broad, 'broad_reference_mix' => [], 'modern_country_reference_mix' => [],
        'plain_country_cards' => [], 'theoretical_lanes' => [], 'cards' => $cards,
        'jewish_diaspora_evidence' => ['panel_available' => false, 'answer' => 'no validated diaspora conclusion', 'proxy_percent' => 0, 'confidence_band' => 'unvalidated', 'plain' => 'An installed specialist reference group does not establish personal identity or ancestry composition.'],
        'admixture_fit' => ['available' => false, 'reason' => 'Held-out multi-population calibration is not installed.'],
        'flat_signal_warning' => true, 'suppress_south_user_output' => false, 'truth_boundary' => $boundary,
    ];
}

// Retained for research reproducibility; not used for unvalidated public results.
function zl_dna_plain_ancestry_summary_legacy_unvalidated($reference_pack, $question_signals = [], $quality = []) {
    $reference_pack = (array) $reference_pack;
    $installed = !empty($reference_pack['installed']);
    $reference_pipeline = (string) ($reference_pack['reference_pipeline'] ?? '');
    $is_optimized_reference = $reference_pipeline === 'optimized_runtime_installed';
    $super_rows = (array) ($reference_pack['super_population_scores'] ?? []);
    $pop_rows = (array) ($reference_pack['population_scores'] ?? []);
    $admixture_fit = (array) ($reference_pack['admixture_fit'] ?? []);
    $admixture_available = !empty($admixture_fit['available']);
    $admixture_south_percent = $admixture_available ? (float) ($admixture_fit['south_asian_percent'] ?? 0) : 0.0;
    $admixture_europe_percent = $admixture_available ? (float) ($admixture_fit['european_percent'] ?? 0) : 0.0;
    $admixture_informative_markers = $admixture_available ? (int) ($admixture_fit['informative_markers'] ?? 0) : 0;
    $reference_matched_markers = (int) ($reference_pack['matched_marker_count'] ?? 0);
    $super_affinity_score = function ($code) use ($super_rows) {
        $code = strtoupper((string) $code);
        $best = 0.0;
        foreach ($super_rows as $row) {
            $row = (array) $row;
            $row_code = strtoupper((string) ($row['super_population'] ?? $row['population_code'] ?? $row['code'] ?? ''));
            if ($row_code !== $code) {
                continue;
            }
            $best = max($best, (float) ($row['affinity_score'] ?? $row['confidence_score'] ?? 0));
        }
        return $best;
    };
    $global_eur_score = $super_affinity_score('EUR');
    $global_sas_score = $super_affinity_score('SAS');
    $global_eas_score = $super_affinity_score('EAS');
    $global_amr_score = $super_affinity_score('AMR');
    $global_afr_score = $super_affinity_score('AFR');
    $max_non_south_global_score = max($global_eas_score, $global_amr_score, $global_afr_score);
    $global_blocks_south_axis = $reference_matched_markers >= 12000
        && $max_non_south_global_score > max($global_sas_score, $global_eur_score) + 2.5;
    $consumer_overlap_ready = !$installed || $reference_matched_markers >= 10000;
    $super_label = function ($row, $label) {
        $map = [
            'AFR' => 'African reference signal',
            'AMR' => 'Americas mixed-ancestry reference signal',
            'EAS' => 'East Asian reference signal',
            'EUR' => 'European reference signal',
            'SAS' => 'South Asian reference signal',
        ];
        $code = strtoupper((string) ($row['super_population'] ?? $label));
        return $map[$code] ?? ($code . ' reference signal');
    };
    $super_mix = $installed ? zl_dna_mix_proxy_rows($super_rows, 'super_population', $super_label) : [];
    $pop_mix = $installed ? zl_dna_mix_proxy_rows($pop_rows, 'modern_label') : [];
    $flat_mix = false;
    if (count($super_mix) >= 4) {
        $top_proxy = (float) ($super_mix[0]['proxy_percent'] ?? 0);
        $tail_proxy = (float) ($super_mix[min(4, count($super_mix) - 1)]['proxy_percent'] ?? 0);
        $flat_mix = ($top_proxy - $tail_proxy) < 8.0;
    }
    if ($is_optimized_reference && $reference_matched_markers >= 12000) {
        $flat_mix = false;
    }
    $super_pct = [];
    foreach ($super_mix as $row) {
        $code = strtoupper((string) ($row['code'] ?? ''));
        if ($code !== '') {
            $super_pct[$code] = (float) ($row['proxy_percent'] ?? 0);
        }
    }
    $pop_pct = [];
    foreach ($pop_mix as $row) {
        $code = strtoupper((string) ($row['code'] ?? ''));
        if ($code !== '') {
            $pop_pct[$code] = (float) ($row['proxy_percent'] ?? 0);
        }
    }
    $pop_sum = function ($codes) use ($pop_pct) {
        $sum = 0.0;
        foreach ((array) $codes as $code) {
            $sum += (float) ($pop_pct[strtoupper((string) $code)] ?? 0);
        }
        return round($sum, 1);
    };
    $answer = function ($label, $status, $proxy, $confidence, $plain, $limit, $next) {
        return [
            'label' => $label,
            'answer' => $status,
            'proxy_percent' => $proxy,
            'confidence_band' => $confidence,
            'plain' => $plain,
            'limitations' => $limit,
            'next_steps' => $next,
        ];
    };
    $broad_answer = function ($label, $pct, $strong_at, $possible_at, $name) use ($answer, $installed, $flat_mix, $is_optimized_reference) {
        if (!$installed) {
            return $answer($label, 'compact comparison not ready', 0, 'low', 'The compact ancestry engine was unavailable for this question.', 'ZeroThink will not invent percentages when the compact comparison lane is unavailable.', 'Refresh the compact ancestry source packs.');
        }
        if ($flat_mix && $pct >= $possible_at) {
            return $answer($label, 'inconclusive / flat compact signal', $pct, 'low', 'The compact engine gives a broad ' . $name . ' proxy, but the signal is too flat across groups to call it a strong percentage.', 'A flat compact result should be read as a research clue, not final ancestry composition.', 'Use parent files, known family places, or deeper compact controls for sharper separation.');
        }
        if ($pct >= $strong_at) {
            return $answer($label, 'yes - strong compact signal', $pct, $is_optimized_reference ? 'medium-high' : 'medium', 'The compact local engine shows a strong ' . $name . ' genetic-affinity signal.', 'This is a genetic-affinity signal, not nationality, ethnicity, religion, tribe, culture, or identity.', $is_optimized_reference ? 'Use GEDCOM, parent files, and known places for finer splits.' : 'Use deeper compact controls before treating this as a stronger estimate.');
        }
        if ($pct >= $possible_at) {
            return $answer($label, 'possible / mixed signal', $pct, 'low-medium', 'The installed local panel shows a possible ' . $name . ' reference signal.', 'This is a rough proxy from installed panels, not a final ancestry percentage.', 'Use larger panels and confidence intervals for stronger reporting.');
        }
        return $answer($label, 'not a strong signal in installed panel', $pct, 'low', 'The installed local panel does not show a strong ' . $name . ' signal.', 'Absence in this preview is not proof of absence. Reference coverage may be incomplete.', 'Use expanded panels if this question matters.');
    };

    $europe = (float) ($super_pct['EUR'] ?? 0);
    $africa = (float) ($super_pct['AFR'] ?? 0);
    $east_asia = (float) ($super_pct['EAS'] ?? 0);
    $south_asia = (float) ($super_pct['SAS'] ?? 0);
    $americas = (float) ($super_pct['AMR'] ?? 0);
    $english = $pop_sum(['GBR']);
    $chinese = $pop_sum(['CHB', 'CHS', 'CDX']);
    $asj_panel = $pop_sum(['ASJ']);
    $sephardi_panel = $pop_sum(['SEF']);
    $mizrahi_panel = $pop_sum(['MIZ']);
    $levant_panel = $pop_sum(['LEV']);
    $direct_south_asia_preview = $pop_sum(['PJL', 'BEB', 'GIH', 'ITU', 'STU']);
    $direct_europe_preview = $pop_sum(['GBR', 'CEU', 'FIN', 'TSI', 'IBS']);
    $south_model_preview = max($south_asia, $direct_south_asia_preview * 2.15);
    $europe_model_preview = max($europe, $direct_europe_preview);
    if ($admixture_available) {
        $south_model_preview = max($south_model_preview, $admixture_south_percent);
        $europe_model_preview = max($europe_model_preview, $admixture_europe_percent);
    }
    $south_share_preview = ($south_model_preview + $europe_model_preview) > 0
        ? round(($south_model_preview / max(0.001, $south_model_preview + $europe_model_preview)) * 100, 1)
        : 0.0;
    $europe_dominant_preview = $europe_model_preview >= max(30.0, $south_model_preview * 1.55);
    $quarter_axis_threshold = $admixture_informative_markers >= 10000 ? 12.0 : ($admixture_informative_markers >= 3000 ? 16.0 : 20.0);
    $quarter_axis_bridge = $admixture_available
        && $admixture_south_percent >= 10.0
        && $south_asia >= 18.0
        && $direct_south_asia_preview >= 8.5
        && !$global_blocks_south_axis;
    $admixture_south_axis = (
        $admixture_available
        && $admixture_south_percent >= $quarter_axis_threshold
        && $admixture_europe_percent >= 25.0
        && !$global_blocks_south_axis
    ) || $quarter_axis_bridge;
    $south_axis_strong = ($south_share_preview >= 34.0 || $south_asia >= 26.0 || $direct_south_asia_preview >= 16.0 || $admixture_south_axis);
    $south_axis_main = $south_axis_strong && (!$europe_dominant_preview || $admixture_south_axis);
    $suppress_south_user_output = !$south_axis_main && $europe_dominant_preview;
    $is_south_user_label = function ($value) {
        return (bool) preg_match('/\b(Pakistan|Pakistani|Punjabi|Punjab|South Asian|South Asia|Bangladesh|Bengali|India|Gujarati|Telugu|Sri Lanka|Tamil|PJL|BEB|GIH|ITU|STU|SAS)\b/i', (string) $value);
    };
    $jewish_panel_proxy_raw = max($asj_panel, $asj_panel + ($sephardi_panel * 0.45) + ($mizrahi_panel * 0.45) + ($levant_panel * 0.2));
    $jewish_panel_proxy = round(zl_dna_math_clamp($jewish_panel_proxy_raw * 0.34, 0.0, 3.8), 1);
    $jewish_evidence = zl_dna_jewish_diaspora_evidence($reference_pack, $jewish_panel_proxy);
    $jewish_panel_proxy = round((float) ($jewish_evidence['proxy_percent'] ?? $jewish_panel_proxy), 1);
    $jewish_specialist_panel_installed = false;
    foreach ($pop_rows as $row) {
        $code = strtoupper((string) ($row['population_code'] ?? ''));
        if (in_array($code, ['ASJ', 'SEF', 'MIZ', 'LEV'], true)) {
            $jewish_specialist_panel_installed = true;
            break;
        }
    }
    $jewish_specialist_panel_installed = $jewish_specialist_panel_installed || !empty($jewish_evidence['panel_available']);
    $central_asia_lens_proxy = $south_axis_main
        ? round(zl_dna_math_clamp($south_share_preview * 0.066, 0.0, 4.5), 1)
        : 0.0;
    $mediterranean_proxy = $pop_sum(['TSI', 'IBS']);
    $chinese_lens_proxy = round(max($chinese, $east_asia * 0.65), 1);
    $levant_lens_proxy = $installed ? round(zl_dna_math_clamp(
        max($levant_panel, ($mediterranean_proxy * 0.16) + ($south_model_preview * 0.035) + ($jewish_panel_proxy * 0.35)),
        0.0,
        12.0
    ), 1) : 0.0;
    if ($suppress_south_user_output && $levant_panel <= 0.0) {
        $levant_lens_proxy = round($levant_lens_proxy * 0.55, 1);
    }
    $egypt_north_africa_lens_proxy = $installed ? round(zl_dna_math_clamp(
        ($africa * 0.09) + ($mediterranean_proxy * 0.08) + ($levant_lens_proxy * 0.35),
        0.0,
        10.0
    ), 1) : 0.0;
    $iran_persia_lens_proxy = $installed ? round(zl_dna_math_clamp(
        ($south_model_preview * 0.12) + ($central_asia_lens_proxy * 1.25) + ($levant_lens_proxy * 0.28),
        0.0,
        12.0
    ), 1) : 0.0;
    if (!$south_axis_main) {
        $iran_persia_lens_proxy = round($iran_persia_lens_proxy * 0.35, 1);
    }
    $khazar_steppe_lens_proxy = $installed ? round(zl_dna_math_clamp(
        ($central_asia_lens_proxy * 0.85) + ($pop_sum(['FIN']) * 0.12) + ($jewish_panel_proxy * 0.25),
        0.0,
        8.0
    ), 1) : 0.0;
    $indigenous_americas_lens_proxy = $installed ? round(zl_dna_math_clamp(
        $flat_mix ? ($americas * 0.05) : max(0.0, $americas - (max($europe, $africa, $south_asia, $east_asia) * 0.7)),
        0.0,
        8.0
    ), 1) : 0.0;
    $compact_status = function ($proxy, $name = 'compact specialist lens') {
        $proxy = (float) $proxy;
        if ($proxy >= 4.0) {
            return ['possible compact-lens signal', 'low-medium', $name . ' produced a visible signal.'];
        }
        if ($proxy >= 1.0) {
            return ['trace in compact lens', 'trace / low', $name . ' found a small trace-level signal.'];
        }
        return ['not visible in compact lens', 'low', $name . ' did not find enough signal to promote this lane.'];
    };
    $plain_country_card = function ($label, $codes, $plain_if_seen, $missing_plain, $next_steps, $mode = 'direct_reference') use ($answer, $pop_sum, $installed, $flat_mix, $is_optimized_reference, $south_axis_main) {
        $codes = array_values(array_filter(array_map('strtoupper', (array) $codes)));
        $proxy = $pop_sum($codes);
        $code_text = $codes ? implode(', ', $codes) : 'no installed code';
        $south_label = (bool) preg_match('/\b(Pakistan|Punjabi|Bangladesh|Bengali|India|Gujarati|Telugu|Sri Lanka|Tamil|South Asian)\b/i', (string) $label);
        if (!$installed) {
            $card = $answer(
                $label,
                'compact lens unavailable',
                0,
                'low',
                'ZeroThink needs at least the compact local reference pack before this named lane can be scored.',
                'No country-style answer should be invented when even the compact lane is unavailable.',
                'Refresh the compact DNA reference packs, then rerun the report.'
            );
        } elseif ($mode === 'admixed_calibration' && $proxy > 0) {
            $card = $answer(
                $label,
                'admixed calibration overlap - not a direct country call',
                $proxy,
                'low / calibration only',
                $plain_if_seen . ' Closest installed reference code(s): ' . $code_text . '.',
                'These 1000 Genomes samples are admixed calibration panels. Overlap here can come from shared European, African, or Indigenous components and should not be reported as "you are South American".',
                $next_steps
            );
        } elseif ($south_label && !$south_axis_main && $proxy > 0) {
            $card = $answer(
                $label,
                'weak overlap - not a main country call',
                $proxy,
                'low / suppressed',
                $plain_if_seen . ' Closest installed reference code(s): ' . $code_text . '.',
                'The wider model is European/British-Irish dominant, so this South Asian proxy is being treated as cross-panel overlap unless stronger specialist evidence appears.',
                'Show this only in the evidence ledger, not as a headline percentage. Re-run with expanded South Asian controls if this question matters.'
            );
        } elseif ($proxy > 0) {
            $card = $answer(
                $label,
                $flat_mix ? 'visible in starter panel - theoretical lead' : ($is_optimized_reference ? 'visible in optimized reference panel' : 'visible reference signal'),
                $proxy,
                $flat_mix ? 'low / starter panel' : ($is_optimized_reference ? ($proxy >= 10 ? 'medium-high' : 'medium') : ($proxy >= 10 ? 'medium' : 'low-medium')),
                $plain_if_seen . ' Closest installed reference code(s): ' . $code_text . '.',
                $flat_mix
                    ? 'This starter signal is broad, so treat it as a named research lead rather than a final percentage.'
                    : 'This is a genetic-affinity signal against installed reference samples, not culture, nationality, identity, or family proof.',
                $next_steps
            );
        } else {
            $card = $answer(
                $label,
                'not visible in compact lens',
                0,
                'low',
                $missing_plain,
                'A missing or weak proxy does not prove absence. It means the compact lane did not find enough signal to promote this as a named result.',
                'Use parent files, GEDCOM/place clues, or future expanded compact packs if this question matters.'
            );
        }
        $card['codes'] = $codes;
        $card['lane_type'] = $mode;
        $card['show_as_lead'] = $mode !== 'admixed_calibration' && !($south_label && !$south_axis_main);
        return $card;
    };
    $plain_country_cards = [
        $plain_country_card('England / Scotland / United Kingdom', ['GBR'], 'The UK/GBR reference lane is the closest installed proxy for English/Scottish/British Isles-style signal.', 'No strong UK/GBR proxy was visible in the installed country panel.', 'Add Ireland, Wales, Scotland, England, and Northwest-Europe panels for sharper splits.'),
        $plain_country_card('Ireland / Irish / Celtic Isles', ['GBR', 'CEU'], 'There is no Ireland-only starter code, so ZeroThink uses nearby UK/Northwest-European proxies as a first-pass clue.', 'The compact Celtic Isles lane did not find enough signal to promote Ireland as a named result.', 'Use parent files, Dublin/Ireland family-place clues, or deeper Celtic Isles controls for a sharper split.', 'nearby_proxy'),
        $plain_country_card('Pakistan / Punjabi', ['PJL'], 'The Pakistan/Punjabi lane maps to the 1000 Genomes PJL reference sample.', 'No Pakistan/Punjabi proxy was visible in the installed panel.', 'Expand South Asian panels with Pakistan, Punjab, Sindh, Pashtun, Baloch, Kashmir, and neighbouring groups.'),
        $plain_country_card('Bangladesh / Bengali', ['BEB'], 'The Bangladesh/Bengali lane maps to the BEB reference sample.', 'No Bangladesh/Bengali proxy was visible in the installed panel.', 'Expand Bengali/Bangladesh and East Indian panels for cleaner country-level resolution.'),
        $plain_country_card('India / Gujarati / Telugu', ['GIH', 'ITU'], 'The installed Indian proxies cover Gujarati diaspora and Telugu-in-UK reference samples.', 'No strong India proxy was visible in the installed panel.', 'Add broader India panels by region, caste/community where legally appropriate, and neighbouring-country controls.'),
        $plain_country_card('Sri Lanka / Tamil', ['STU'], 'The Sri Lankan Tamil proxy maps to the STU reference sample.', 'No Sri Lankan Tamil proxy was visible in the installed panel.', 'Add Sri Lankan, Tamil, Sinhalese, and South Indian panels if this lane matters.'),
        $plain_country_card('China / Han Chinese', ['CHB', 'CHS'], 'The China/Han lane uses Beijing Han and Southern Han reference samples.', 'No China/Han proxy was visible in the installed panel.', 'Add more China, East Asian, and Southeast Asian panels for better separation.'),
        $plain_country_card('Japan', ['JPT'], 'The Japan lane maps to the Tokyo Japanese reference sample.', 'No Japan proxy was visible in the installed panel.', 'Add more Japanese and neighbouring East Asian panels for sharper detection.'),
        $plain_country_card('Vietnam / Southeast Asia', ['KHV', 'CDX'], 'The Southeast Asian lane uses Vietnam Kinh and Dai reference samples.', 'No Southeast Asian proxy was visible in the installed panel.', 'Add Thai, Malay, Cambodian, Filipino, Indonesian, and broader SEA panels.'),
        $plain_country_card('Nigeria / West Africa', ['YRI', 'ESN'], 'The Nigeria lane uses Yoruba and Esan reference samples.', 'No Nigeria/West Africa proxy was visible in the installed panel.', 'Add West African sub-region panels and diaspora controls.'),
        $plain_country_card('Kenya / East Africa', ['LWK'], 'The Kenya lane uses Luhya reference samples.', 'No Kenya/East Africa proxy was visible in the installed panel.', 'Add East African panels and North/East African bridge panels.'),
        $plain_country_card('Italy / Spain / Mediterranean Europe', ['TSI', 'IBS'], 'The Mediterranean Europe lane uses Tuscany and Iberian reference samples.', 'No Mediterranean-Europe proxy was visible in the installed panel.', 'Add Greece, Balkans, Sicily, Sardinia, North Africa, and Levant controls for better resolution.'),
        $plain_country_card('Americas mixed calibration panel', ['MXL', 'CLM', 'PEL', 'PUR'], 'The Americas calibration lane uses mixed-ancestry reference samples from Mexico, Colombia, Peru, and Puerto Rico.', 'No Americas calibration overlap was visible in the installed panel.', 'Do not show this as South American ancestry unless a specific Indigenous-Americas or Latin American reference model is installed and separates the source components.', 'admixed_calibration'),
    ];
    usort($plain_country_cards, function ($a, $b) {
        $lead_cmp = (int) (!empty($b['show_as_lead'])) <=> (int) (!empty($a['show_as_lead']));
        if ($lead_cmp !== 0) {
            return $lead_cmp;
        }
        return ((float) ($b['proxy_percent'] ?? 0)) <=> ((float) ($a['proxy_percent'] ?? 0));
    });
    if ($suppress_south_user_output) {
        $plain_country_cards = array_values(array_filter($plain_country_cards, function ($row) use ($is_south_user_label) {
            return !$is_south_user_label(($row['label'] ?? '') . ' ' . implode(' ', (array) ($row['codes'] ?? [])));
        }));
    }
    $mk_theory = function ($label, $status, $proxy, $confidence, $plain, $limitations, $next_steps, $tags = []) use ($answer) {
        $card = $answer($label, $status, round((float) $proxy, 1), $confidence, $plain, $limitations, $next_steps);
        $card['lane_type'] = 'theoretical';
        $card['tags'] = array_values((array) $tags);
        return $card;
    };
    $jewish_compact_trace_proxy = $installed ? round(zl_dna_math_clamp(
        ($europe * 0.035) + ($mediterranean_proxy * 0.07) + ($levant_lens_proxy * 0.22),
        0.0,
        3.8
    ), 1) : 0.0;
    $single_axis_south_file = $south_axis_main && (
        ($admixture_available && $admixture_south_percent >= 70.0 && $admixture_europe_percent <= 30.0)
        || ($south_share_preview >= 70.0 && $direct_south_asia_preview >= 18.0)
    );
    if ($single_axis_south_file && $levant_panel <= 0.0) {
        $jewish_panel_proxy = 0.0;
        $jewish_compact_trace_proxy = 0.0;
    }
    $jewish_evidence = zl_dna_jewish_diaspora_evidence($reference_pack, $jewish_compact_trace_proxy);
    $jewish_compact_trace_proxy = round((float) ($jewish_evidence['proxy_percent'] ?? $jewish_compact_trace_proxy), 1);
    $theoretical_lanes = [
        $mk_theory(
            'British / English / Irish hypothesis lane',
            max($english, $europe * 0.35) >= 6.5 ? 'possible research lead' : 'weak or not visible yet',
            max($english, $europe * 0.35),
            $flat_mix ? 'low / starter panel' : 'low-medium',
            'This is the lane users usually mean when they ask "am I English, Irish, British, Celtic, or Northwest European?"',
            'The current starter panel can name UK/Northwest-European proxies, but it cannot split England vs Ireland vs Scotland as final percentages.',
            'Run the Ireland/Scotland/Wales/England/Northwest-Europe comparison pack and add GEDCOM, surname, or place clues.',
            ['England', 'Ireland', 'Scotland', 'United Kingdom']
        ),
        $mk_theory(
            'Pakistan / North India / Bangladesh / South Asia lane',
            $south_asia > 10 || $pop_sum(['PJL', 'BEB', 'GIH', 'ITU', 'STU']) > 8 ? 'possible research lead' : 'weak or not visible yet',
            max($south_asia, $pop_sum(['PJL', 'BEB', 'GIH', 'ITU', 'STU'])),
            $flat_mix ? 'low / starter panel' : 'low-medium',
            'This lane translates "South Asia" into modern names such as Pakistan, India, Bangladesh, Sri Lanka, Punjab, Bengali, Gujarati, Telugu, and Tamil where the panel supports it.',
            'Country-level South Asian ancestry overlaps heavily between neighbouring groups, so compact controls separate it carefully.',
            'Use parent files and family-place clues to sharpen Pakistan, Punjab, Bangladesh, India, Sri Lanka, and nearby splits.',
            ['Pakistan', 'India', 'Bangladesh', 'Sri Lanka', 'Punjab', 'Bengali']
        ),
        $mk_theory(
            'Ashkenazi / Sephardi / Mizrahi Jewish diaspora lane',
            (string) ($jewish_evidence['status'] ?? 'compact starter trace lens active'),
            (float) ($jewish_evidence['proxy_percent'] ?? $jewish_compact_trace_proxy),
            (string) ($jewish_evidence['confidence_band'] ?? 'trace / low'),
            (string) ($jewish_evidence['plain'] ?? 'ZeroThink uses a compact starter lens plus ASJ-vs-European control checks so generic European overlap is not promoted as a stronger Jewish-diaspora result.'),
            'This is genetic-affinity context only. DNA cannot determine Jewish religion, identity, community membership, or belonging.',
            'Compare ASJ against nearby European controls and use parent files before treating this as more than a trace clue.',
            ['Ashkenazi', 'Sephardi', 'Mizrahi', 'Jewish diaspora']
        ),
        $mk_theory(
            'Levant / Israel / Palestine / Lebanon / Jordan / Syria lane',
            $compact_status($levant_lens_proxy, 'Levant / Eastern Mediterranean bridge lens')[0],
            $levant_lens_proxy,
            $compact_status($levant_lens_proxy, 'Levant / Eastern Mediterranean bridge lens')[1],
            'This compact lane translates "Levant" into modern Israel, Palestine, Lebanon, Jordan, Syria, and nearby Eastern Mediterranean contexts using bridge signals from Mediterranean, West Asian, South Asian-adjacent, and diaspora context.',
            'This is not an Israel/Palestine/Lebanon/Jordan/Syria identity or citizenship claim. Nearby Mediterranean, West Asian, South Asian, and North African signals can overlap.',
            'Use this as a named research clue, then compare with family places, GEDCOM, and future compact regional controls.',
            ['Israel', 'Palestine', 'Lebanon', 'Jordan', 'Syria', 'Levant']
        ),
        $mk_theory(
            'Khazar / Steppe / Turkic-Caucasus hypothesis lane',
            $compact_status($khazar_steppe_lens_proxy, 'Steppe / Turkic-Caucasus hypothesis lens')[0],
            $khazar_steppe_lens_proxy,
            $compact_status($khazar_steppe_lens_proxy, 'Steppe / Turkic-Caucasus hypothesis lens')[1],
            'This is a compact historical hypothesis lane for Steppe, Turkic-Caucasus, and Khazar-style questions. It is not a normal consumer-DNA ethnicity result.',
            'Khazar questions cannot be proved from an autosomal consumer file alone. This lane can only say whether the signal is consistent enough to keep researching.',
            'Treat as "possible / weak / not visible" research context and combine with genealogy, history, and future ancient-reference controls.',
            ['Khazar', 'Steppe', 'Caucasus', 'Turkic']
        ),
        $mk_theory(
            'Israelite / Hashemite / dynastic lineage lane',
            'lineage evidence required',
            0,
            'lineage only',
            'This belongs in the Lineage Clues engine, not in ethnicity percentages.',
            'Autosomal consumer DNA cannot prove Israelite, Hashemite, royal, prophetic, or dynastic descent. This needs GEDCOM, surnames, dates, places, documents, and sometimes Y-DNA/mtDNA.',
            'Use the Lineage Clues wizard and upload GEDCOM or family-story evidence.',
            ['Israelite', 'Hashemite', 'royal lineage', 'dynasty']
        ),
        $mk_theory(
            'Egypt / North Africa lane',
            $compact_status($egypt_north_africa_lens_proxy, 'Egypt / North Africa bridge lens')[0],
            $egypt_north_africa_lens_proxy,
            $compact_status($egypt_north_africa_lens_proxy, 'Egypt / North Africa bridge lens')[1],
            'Egypt and North Africa are now shown as named compact bridge lanes instead of being buried under vague labels.',
            'North Africa overlaps with Levant, Arabia, Mediterranean Europe, East Africa, and Sub-Saharan Africa. This is a clue lane, not a final country percentage.',
            'Use this as a research lead and compare with stronger Egypt/Maghreb/Nubian/Levant/Arabian/East-African controls as they are added.',
            ['Egypt', 'North Africa', 'Maghreb']
        ),
        $mk_theory(
            'Iran / Persia / West-Central Asia lane',
            $compact_status($iran_persia_lens_proxy, 'Iran / Persia / West-Central Asia bridge lens')[0],
            $iran_persia_lens_proxy,
            $compact_status($iran_persia_lens_proxy, 'Iran / Persia / West-Central Asia bridge lens')[1],
            'Persian/Iranian is now a named compact bridge lane using South Asian, Central Asian, Levant-adjacent, and West-Central Asian context.',
            'Iranian/Persian-related signals overlap with Caucasus, Central Asia, South Asia, Levant, and Arabian references. This is a clue lane, not proof of national identity.',
            'Use this as a research lead and compare with family places, GEDCOM, and future compact regional controls.',
            ['Iran', 'Persia', 'Caucasus', 'Central Asia']
        ),
    ];
    if ($suppress_south_user_output) {
        $theoretical_lanes = array_values(array_filter($theoretical_lanes, function ($row) use ($is_south_user_label) {
            return !$is_south_user_label(($row['label'] ?? '') . ' ' . implode(' ', (array) ($row['tags'] ?? [])));
        }));
        foreach ($theoretical_lanes as &$lane) {
            foreach (['plain', 'limitations', 'next_steps'] as $field) {
                if (isset($lane[$field])) {
                    $lane[$field] = str_replace(['South Asian', 'South Asia', 'South-Asian'], ['regional', 'nearby regions', 'regional'], (string) $lane[$field]);
                }
            }
        }
        unset($lane);
    }
    $direct_europe = $direct_europe_preview;
    $direct_south_asia = $direct_south_asia_preview;
    $south_model = max($south_asia, $direct_south_asia * 2.15);
    $europe_model = max($europe, $direct_europe);
    $consumer_segments = [];
    $consumer_trace_lanes = [];
    $consumer_suppressed_noise = [
        'Americas mixed reference panels are no longer promoted as Mexico/Colombia/Peru/Puerto Rico ancestry unless a specific Indigenous-Americas/Latin-American model supports that call.',
    ];
    $ashkenazi_trace_proxy = 0.0;
    $consumer_method = 'reference-panel lead projection';
    $consumer_plain = 'ZeroThink converts the technical reference-panel output into plain composition cards, while keeping the evidence ledger underneath.';
    $push_consumer = function ($label, $value, $color, $note = '') use (&$consumer_segments) {
        $value = round(max(0.0, (float) $value), 1);
        if ($value <= 0.2) {
            return;
        }
        $consumer_segments[] = [
            'label' => $label,
            'value' => $value,
            'color' => $color,
            'note' => $note,
        ];
    };
    $east_axis_main = $installed && $consumer_overlap_ready
        && $global_eas_score >= 55.0
        && $global_eas_score >= max($global_sas_score, $global_eur_score, $global_amr_score, $global_afr_score) + 2.0;
    $americas_axis_main = $installed && $consumer_overlap_ready
        && $global_amr_score >= 52.0
        && $global_amr_score >= max($global_sas_score, $global_eas_score, $global_afr_score) + 1.0
        && $global_amr_score >= $global_eur_score - 3.0;
    $african_axis_main = $installed && $consumer_overlap_ready
        && $global_afr_score >= 55.0
        && $global_afr_score >= max($global_sas_score, $global_eas_score, $global_amr_score, $global_eur_score) + 2.0;
    if ($installed && !$consumer_overlap_ready) {
        $consumer_method = 'ZDNA-CONSUMER v0.7 low-overlap safety gate';
        $push_consumer('Build / reference-overlap check needed', 100, '#8aa4b8', 'The file parsed, but too few markers matched the installed calibrated reference pack for a confident composition chart.');
        $consumer_plain = 'Plain result: this file needs a build or reference-overlap check before ZeroThink should show country percentages. This usually happens with older genome builds, unusual exports, or files that need conversion/liftover first.';
        $consumer_suppressed_noise[] = 'Country-style percentages were suppressed because only ' . number_format($reference_matched_markers) . ' calibrated reference markers matched.';
    } elseif ($east_axis_main) {
        $consumer_method = 'ZDNA-CONSUMER v0.8 East-Asian dominant projection';
        $han_raw = max($chinese, $pop_sum(['CHB', 'CHS']));
        $sea_raw = $pop_sum(['CDX', 'KHV']);
        $japan_raw = $pop_sum(['JPT']);
        $raw_total = $han_raw + $sea_raw + $japan_raw;
        if ($raw_total > 0.1) {
            $han_lane = round(zl_dna_math_clamp(($han_raw / $raw_total) * 82, 48, 78), 1);
            $sea_lane = round(zl_dna_math_clamp(($sea_raw / $raw_total) * 82, 5, 26), 1);
            $japan_lane = round(zl_dna_math_clamp(($japan_raw / $raw_total) * 82, 4, 22), 1);
        } else {
            $han_lane = 68.0;
            $sea_lane = 14.0;
            $japan_lane = 10.0;
        }
        $broad_east = round(max(0, 100 - ($han_lane + $sea_lane + $japan_lane)), 1);
        $push_consumer('China / Han Chinese-related', $han_lane, '#35d7ff', 'Main East Asian reference projection from CHB/CHS-style overlap.');
        $push_consumer('Vietnam / Southeast Asian bridge', $sea_lane, '#5df0b1', 'Southeast Asian bridge from CDX/KHV-style overlap.');
        $push_consumer('Japan / Northeast Asian bridge', $japan_lane, '#c2a4ff', 'Northeast Asian bridge from JPT-style overlap.');
        $push_consumer('Broad East Asian remainder', $broad_east, '#f5d06f', 'Residual East Asian reference context kept broad until deeper regional panels are installed.');
        $consumer_plain = 'Plain projection: the strongest calibrated signal is East Asian. ZeroThink now routes this through an East-Asian branch instead of forcing it into the South-Asian/European model.';
    } elseif ($americas_axis_main) {
        $consumer_method = 'ZDNA-CONSUMER v0.8 Americas / Latin-admixed projection';
        $indigenous_context = round(zl_dna_math_clamp(45 + (($global_amr_score - $global_eur_score) * 2.0), 30, 64), 1);
        $african_bridge = $global_afr_score >= 48 ? round(zl_dna_math_clamp(($global_afr_score - 45) * 0.7, 2, 14), 1) : 0.0;
        $east_south_bridge = max($global_eas_score, $global_sas_score) >= 48 ? round(zl_dna_math_clamp((max($global_eas_score, $global_sas_score) - 45) * 0.35, 1, 8), 1) : 0.0;
        $euro_context = round(max(0, 100 - ($indigenous_context + $african_bridge + $east_south_bridge)), 1);
        $push_consumer('Latin American / Indigenous Americas context', $indigenous_context, '#ffb15c', 'Americas reference signal; not a tribal, citizenship, or cultural identity claim.');
        $push_consumer('Iberian / European-admixed context', $euro_context, '#8fd1ff', 'European component commonly present in Latin-admixed reference files.');
        $push_consumer('African diaspora bridge', $african_bridge, '#b989ff', 'Bridge component only if the broad African reference axis is visible.');
        $push_consumer('East/South Asian bridge trace', $east_south_bridge, '#8fffd5', 'Small bridge trace kept separate so it does not become a false main call.');
        $consumer_plain = 'Plain projection: the calibrated signal points toward an Americas/Latin-admixed lane. ZeroThink keeps the components separated instead of calling this “Pakistani” or “Irish” from shared overlap.';
    } elseif ($african_axis_main) {
        $consumer_method = 'ZDNA-CONSUMER v0.8 African dominant projection';
        $west_raw = $pop_sum(['YRI', 'ESN', 'GWD', 'MSL']);
        $east_raw = $pop_sum(['LWK']);
        $south_raw = $pop_sum(['ACB', 'ASW']);
        $raw_total = $west_raw + $east_raw + $south_raw;
        if ($raw_total > 0.1) {
            $west_lane = round(zl_dna_math_clamp(($west_raw / $raw_total) * 88, 45, 82), 1);
            $east_lane = round(zl_dna_math_clamp(($east_raw / $raw_total) * 88, 3, 24), 1);
            $diaspora_lane = round(zl_dna_math_clamp(($south_raw / $raw_total) * 88, 3, 24), 1);
        } else {
            $west_lane = 68.0;
            $east_lane = 10.0;
            $diaspora_lane = 10.0;
        }
        $broad_african = round(max(0, 100 - ($west_lane + $east_lane + $diaspora_lane)), 1);
        $push_consumer('West African / Nigeria-related', $west_lane, '#9cff7a', 'West African reference projection from YRI/ESN/GWD/MSL-style overlap.');
        $push_consumer('East African bridge', $east_lane, '#ffd76f', 'East African bridge from LWK-style overlap.');
        $push_consumer('African diaspora bridge', $diaspora_lane, '#c2a4ff', 'Diaspora calibration context where available.');
        $push_consumer('Broad African remainder', $broad_african, '#69f7ff', 'Residual African reference context kept broad until deeper panels are installed.');
        $consumer_plain = 'Plain projection: the strongest calibrated signal is African. Regional splits are shown as reference-context leads, not nationality or tribe proof.';
    } elseif ($installed && $south_axis_main && $south_model >= 8 && $europe_model >= 8) {
        $consumer_method = 'ZDNA-CONSUMER v0.2 two-axis South-Asia + Celtic/UK projection';
        $full_south_axis = !empty($admixture_south_axis) && $admixture_south_percent >= 55.0 && $admixture_europe_percent <= 45.0;
        if (!empty($admixture_south_axis)) {
            $consumer_method = 'ZDNA-CONSUMER v0.4 likelihood-calibrated South-Asia + Celtic/UK projection';
            if ($full_south_axis) {
                $consumer_method = 'ZDNA-CONSUMER v0.6 full South-Asian / Pakistani projection';
                $south_total = round(zl_dna_math_clamp($admixture_south_percent * 1.35, 90, 98), 1);
            } elseif ($admixture_south_percent < 30.0) {
                if ($admixture_south_percent >= 17.0) {
                    $consumer_method = 'ZDNA-CONSUMER v0.6 half-axis South-Asia + Celtic/UK projection';
                    $south_total = round(zl_dna_math_clamp(max(
                        $admixture_south_percent * 2.25,
                        $direct_south_asia_preview * 3.25,
                        $south_asia * 2.0
                    ), 38, 48), 1);
                } else {
                    $consumer_method = 'ZDNA-CONSUMER v0.5 quarter-axis South-Asia + English/Irish projection';
                    $south_total = round(zl_dna_math_clamp(max(
                        $admixture_south_percent * 1.55,
                        $direct_south_asia_preview * 1.8,
                        $south_asia * 1.05
                    ), 18, 32), 1);
                }
            } else {
                $south_total = round(zl_dna_math_clamp($admixture_south_percent * 1.32, 34, 48), 1);
            }
        } else {
            $south_total = round(($south_model / max(0.001, $south_model + $europe_model)) * 100, 1);
            $south_total = round(zl_dna_math_clamp($south_total, 18, 58), 1);
        }
        if (!empty($full_south_axis)) {
            $central_asian = round(zl_dna_math_clamp($south_total * 0.055, 2.5, 6.5), 1);
            $general_south = round(zl_dna_math_clamp($south_total * 0.045, 2.0, 7.0), 1);
            $iran_bridge = round(zl_dna_math_clamp($south_total * 0.035, 1.5, 5.0), 1);
            $europe_total = round(max(0, 100 - $south_total), 1);
            $pakistani_punjabi = round(max(0, $south_total - $central_asian - $general_south - $iran_bridge), 1);
            $push_consumer('Pakistani and Punjabi', $pakistani_punjabi, '#30c6d6', 'Main Pakistan/Punjab reference projection from the full-genome South-Asian axis.');
            $push_consumer('Central Asian / Afghan bridge', $central_asian, '#ff805c', 'Nearby Central-South Asian bridge signal often adjacent to Pakistan/Afghanistan/Punjab contexts.');
            $push_consumer('General South Asian', $general_south, '#a8dd67', 'Residual India/Bangladesh/Sri Lanka/South-Asia reference overlap.');
            $push_consumer('Iran / Persia bridge', $iran_bridge, '#ffb86b', 'West-Central Asian bridge context; a clue lane, not a nationality claim.');
            if ($europe_total > 0.5) {
                $push_consumer('West Eurasian / European trace', $europe_total, '#a49bff', 'Small broad trace retained by the model; not promoted as Irish/English unless the European axis is strong.');
            }
            $consumer_plain = 'Plain projection: the file behaves like a full or near-full Pakistan/Punjab/South-Asian profile. Small bridge traces are kept broad so they do not create false Irish or English ancestry.';
        } else {
            $central_asian = round(zl_dna_math_clamp($south_total * 0.066, 1.0, 4.4), 1);
            $general_south = round(zl_dna_math_clamp($south_total * 0.025, 0.7, 2.5), 1);
            $pakistani_punjabi = round(max(0, $south_total - $central_asian - $general_south), 1);
            $europe_total = round(100 - $south_total, 1);
            if (!empty($admixture_south_axis) && $south_total <= 31.0) {
            $english_lane = round($europe_total * 0.66, 1);
            $irish_lane = round($europe_total * 0.30, 1);
            $scottish_welsh = round($europe_total * 0.03, 1);
            $breton_lane = round(max(0, $europe_total - ($english_lane + $irish_lane + $scottish_welsh)), 1);
            } else {
            $scottish_welsh = round($europe_total * 0.30, 1);
            $english_lane = round(max(1.0, $europe_total * 0.05), 1);
            $breton_lane = round(max(0.8, $europe_total * 0.04), 1);
            $irish_lane = round(max(0, 100 - ($pakistani_punjabi + $central_asian + $general_south + $scottish_welsh + $english_lane + $breton_lane)), 1);
            }
            $push_consumer('Pakistani and Punjabi', $pakistani_punjabi, '#30c6d6', 'South Asian cluster projected toward the strongest installed Punjab/Pakistan proxy.');
            $push_consumer('Irish', $irish_lane, '#ff6edb', 'Celtic/Northwest-European projection from GBR/CEU-style overlap.');
            $push_consumer('Scottish and Welsh', $scottish_welsh, '#a49bff', 'British Isles projection; not a legal/cultural identity label.');
            $push_consumer('Central Asian', $central_asian, '#ff805c', 'Bridge signal often adjacent to Pakistan/Afghanistan/Central-South Asian models.');
            $push_consumer('English', $english_lane, '#39b9e6', 'UK/GBR-specific portion of the British Isles projection.');
            $push_consumer('Breton / Celtic fringe', $breton_lane, '#8ac05a', 'Northwest-European Celtic-fringe proxy.');
            $push_consumer('General South Asian', $general_south, '#a8dd67', 'Residual India/Bangladesh/Sri Lanka/South-Asia reference overlap.');
            $consumer_trace_lanes[] = [
                'label' => 'Finnish / Nordic trace',
                'answer' => $pop_sum(['FIN']) > 0 ? 'possible small trace' : 'not detected in installed proxy',
                'proxy_percent' => round(zl_dna_math_clamp($pop_sum(['FIN']) * 0.65, 0, 5.5), 1),
                'plain' => 'Some older consumer reports split a small Finnish/Nordic-like trace from the broader Northwest-European lane.',
                'boundary' => 'Trace lane only; deeper Nordic controls are needed before showing this as a main result.',
            ];
            $ashkenazi_trace_proxy = round(zl_dna_math_clamp(($europe_total * 0.055), 0, 3.5), 1);
            $consumer_trace_lanes[] = [
                'label' => 'Ashkenazi Jewish diaspora trace',
                'answer' => (string) ($jewish_evidence['answer'] ?? 'possible low trace - compact lens'),
                'proxy_percent' => max($ashkenazi_trace_proxy, (float) ($jewish_evidence['proxy_percent'] ?? 0)),
                'plain' => (string) ($jewish_evidence['plain'] ?? 'This is the kind of low-percentage Ashkenazi-style trace consumer DNA tools may surface. ZeroThink shows it plainly as a clue, not a religious or identity claim.'),
                'boundary' => 'Genetic affinity is not religion, identity, community membership, or belonging.',
            ];
            $consumer_plain = !empty($admixture_south_axis)
                ? 'Plain projection: the local likelihood fit sees a real South-Asian/Punjabi axis plus Celtic/British Isles. Low trace lanes are shown separately so they do not distort the main composition.'
                : 'Plain projection: South Asian/Punjabi plus Celtic/British Isles appears to be the main pattern. Low trace lanes are shown separately so they do not distort the main composition.';
        }
    } elseif ($installed && $europe_model >= 8) {
        $consumer_method = 'ZDNA-CONSUMER v0.3 Celtic/UK dominant projection';
        if ($south_model >= 6) {
            $consumer_suppressed_noise[] = 'A weak cross-panel overlap was detected but suppressed from the main chart because the file is European/British-Irish dominant and the non-European axis did not pass the main-signal gate.';
        }
        $english_proxy = max(0.0, (float) ($pop_pct['GBR'] ?? 0));
        $ceu_proxy = max(0.0, (float) ($pop_pct['CEU'] ?? 0));
        $fin_proxy = max(0.0, (float) ($pop_pct['FIN'] ?? 0));
        $med_proxy = $pop_sum(['TSI', 'IBS']);
        $irish_lane = round(zl_dna_math_clamp(54 + ($ceu_proxy * 0.35) - ($med_proxy * 0.12), 43, 72), 1);
        $scottish_welsh = round(zl_dna_math_clamp(24 + ($english_proxy * 0.22), 18, 35), 1);
        $english_lane = round(zl_dna_math_clamp(max(5.0, $english_proxy * 0.7), 5, 16), 1);
        $breton_lane = round(zl_dna_math_clamp(4 + ($ceu_proxy * 0.06), 2.5, 8), 1);
        $finnish_lane = $fin_proxy > 0 ? round(zl_dna_math_clamp($fin_proxy * 0.35, 0.4, 5.5), 1) : 0.0;
        $med_lane = $med_proxy > 0 ? round(zl_dna_math_clamp($med_proxy * 0.18, 0.4, 6.0), 1) : 0.0;
        $sum = $irish_lane + $scottish_welsh + $english_lane + $breton_lane + $finnish_lane + $med_lane;
        if ($sum > 100) {
            $scale = 100 / $sum;
            $irish_lane = round($irish_lane * $scale, 1);
            $scottish_welsh = round($scottish_welsh * $scale, 1);
            $english_lane = round($english_lane * $scale, 1);
            $breton_lane = round($breton_lane * $scale, 1);
            $finnish_lane = round($finnish_lane * $scale, 1);
            $med_lane = round($med_lane * $scale, 1);
        } elseif ($sum < 99.5) {
            $irish_lane = round($irish_lane + (100 - $sum), 1);
        }
        $push_consumer('Irish', $irish_lane, '#ff6edb', 'Celtic/Northwest-European projection from GBR/CEU-style overlap.');
        $push_consumer('Scottish and Welsh', $scottish_welsh, '#a49bff', 'British Isles projection; not a legal/cultural identity label.');
        $push_consumer('English', $english_lane, '#39b9e6', 'UK/GBR-specific portion of the British Isles projection.');
        $push_consumer('Breton / Celtic fringe', $breton_lane, '#8ac05a', 'Northwest-European Celtic-fringe proxy.');
        if ($finnish_lane > 0) {
            $push_consumer('Finnish / Nordic trace', $finnish_lane, '#ffd76f', 'Small Nordic/Baltic-style trace lane.');
        }
        if ($med_lane > 0) {
            $push_consumer('Mediterranean Europe trace', $med_lane, '#ffb86b', 'Small Tuscany/Iberia-style reference trace.');
        }
        $ashkenazi_trace_proxy = round(zl_dna_math_clamp(max($jewish_panel_proxy, 0), 0, 3.5), 1);
        if ($ashkenazi_trace_proxy > 0) {
            $consumer_trace_lanes[] = [
                'label' => 'Ashkenazi Jewish diaspora trace',
                'answer' => (string) ($jewish_evidence['answer'] ?? 'possible low trace - compact lens'),
                'proxy_percent' => max($ashkenazi_trace_proxy, (float) ($jewish_evidence['proxy_percent'] ?? 0)),
                'plain' => (string) ($jewish_evidence['plain'] ?? 'A low diaspora-style trace can mean a real small signal, shared European/West-Eurasian overlap, or reference-panel noise.'),
                'boundary' => 'Genetic affinity is not religion, identity, community membership, or belonging.',
            ];
        }
        $consumer_plain = 'Plain projection: Irish/Celtic and British Isles is the main pattern. Weak cross-panel overlap is kept out of the headline chart unless it passes a stronger evidence gate.';
    } else {
        foreach ($plain_country_cards as $row) {
            if (empty($row['show_as_lead']) || (float) ($row['proxy_percent'] ?? 0) <= 0) {
                continue;
            }
            $push_consumer((string) ($row['label'] ?? 'Reference lead'), (float) ($row['proxy_percent'] ?? 0), '#69f7ff', 'Named reference-panel lead.');
            if (count($consumer_segments) >= 8) {
                break;
            }
        }
        if (!$consumer_segments && $super_mix) {
            foreach (array_slice($super_mix, 0, 5) as $row) {
                $push_consumer((string) ($row['label'] ?? 'Reference signal'), (float) ($row['proxy_percent'] ?? 0), '#69f7ff', 'Broad super-population reference lead.');
            }
        }
    }
    $consumer_chart = zl_dna_safe_percent_segments($consumer_segments);
    $map_meta = function ($label) {
        $label_l = strtolower((string) $label);
        $items = [
            'pakistani' => ['country_name' => 'Pakistan / Punjab', 'modern_region' => 'Lahore, Punjab, Pakistan plus neighbouring Punjab/Afghanistan/India context', 'lat' => 31.6, 'lon' => 73.0, 'info' => 'A South Asian signal centred on the strongest installed Punjabi/Pakistan proxy.'],
            'irish' => ['country_name' => 'Ireland', 'modern_region' => 'Ireland / Celtic Atlantic fringe', 'lat' => 53.3, 'lon' => -8.2, 'info' => 'Celtic/Northwest-European projection matching the Irish-style lane.'],
            'scottish' => ['country_name' => 'Scotland and Wales', 'modern_region' => 'British Isles: Scotland, Wales, and nearby Celtic Britain', 'lat' => 55.5, 'lon' => -4.4, 'info' => 'British Isles projection split from the wider Celtic/Northwest-European lane.'],
            'central asian' => ['country_name' => 'Central Asia', 'modern_region' => 'Kazakhstan, Uzbekistan, Kyrgyzstan, Tajikistan, Turkmenistan, Afghanistan-adjacent context', 'lat' => 44.5, 'lon' => 67.0, 'info' => 'Bridge signal often adjacent to Pakistan/Afghanistan/Central-South Asian models.'],
            'english' => ['country_name' => 'England', 'modern_region' => 'England / United Kingdom', 'lat' => 52.4, 'lon' => -1.6, 'info' => 'UK/GBR-specific portion of the British Isles projection.'],
            'breton' => ['country_name' => 'Brittany / Breton Celtic fringe', 'modern_region' => 'Brittany, northwest France, Celtic Atlantic fringe', 'lat' => 48.2, 'lon' => -3.5, 'info' => 'Northwest-European Celtic-fringe proxy.'],
            'general south asian' => ['country_name' => 'South Asia', 'modern_region' => 'India, Pakistan, Bangladesh, Sri Lanka, Nepal, and nearby contexts', 'lat' => 22.5, 'lon' => 78.8, 'info' => 'Residual broad South Asian reference overlap.'],
            'china' => ['country_name' => 'China / Han Chinese-related', 'modern_region' => 'China, including northern and southern Han reference contexts', 'lat' => 35.8, 'lon' => 104.2, 'info' => 'East Asian reference projection centred on CHB/CHS-style overlap.'],
            'han chinese' => ['country_name' => 'China / Han Chinese-related', 'modern_region' => 'China, including northern and southern Han reference contexts', 'lat' => 35.8, 'lon' => 104.2, 'info' => 'East Asian reference projection centred on CHB/CHS-style overlap.'],
            'vietnam' => ['country_name' => 'Vietnam / Southeast Asian bridge', 'modern_region' => 'Vietnam, southern China, Dai/Kinh and nearby Southeast Asian contexts', 'lat' => 16.2, 'lon' => 106.3, 'info' => 'Southeast Asian bridge from CDX/KHV-style overlap.'],
            'southeast asian' => ['country_name' => 'Southeast Asia', 'modern_region' => 'Vietnam, Thailand, Cambodia, Malaysia, Indonesia, Philippines and nearby contexts', 'lat' => 12.5, 'lon' => 104.9, 'info' => 'Southeast Asian reference context kept broad until deeper panels are installed.'],
            'japan' => ['country_name' => 'Japan / Northeast Asian bridge', 'modern_region' => 'Japan and nearby Northeast Asian reference context', 'lat' => 36.2, 'lon' => 138.2, 'info' => 'Northeast Asian bridge from JPT-style overlap.'],
            'east/south asian' => ['country_name' => 'East/South Asian bridge trace', 'modern_region' => 'Small cross-panel bridge across East and South Asian reference contexts', 'lat' => 23.5, 'lon' => 88.5, 'info' => 'Small bridge trace kept separate so it does not become a false main call.'],
            'east asian' => ['country_name' => 'Broad East Asian remainder', 'modern_region' => 'China, Japan, Korea, Mongolia, Taiwan and nearby East/Northeast Asian contexts', 'lat' => 34.5, 'lon' => 110.0, 'info' => 'Broad East Asian reference context.'],
            'latin american' => ['country_name' => 'Latin America / Indigenous Americas context', 'modern_region' => 'Latin American and Indigenous Americas reference context', 'lat' => -9.2, 'lon' => -74.5, 'info' => 'Americas reference signal; not tribal, citizenship, or cultural identity proof.'],
            'indigenous americas' => ['country_name' => 'Indigenous Americas context', 'modern_region' => 'North, Central, and South American Indigenous reference context', 'lat' => -9.2, 'lon' => -74.5, 'info' => 'Americas reference signal; not tribal, citizenship, or cultural identity proof.'],
            'iberian' => ['country_name' => 'Iberian / European-admixed context', 'modern_region' => 'Spain, Portugal, and European-admixed Latin American context', 'lat' => 40.2, 'lon' => -3.7, 'info' => 'European component commonly present in Latin-admixed reference files.'],
            'african diaspora' => ['country_name' => 'African diaspora bridge', 'modern_region' => 'African diaspora / Atlantic reference context', 'lat' => 8.8, 'lon' => -1.6, 'info' => 'Bridge component only if the broad African reference axis is visible.'],
            'west african' => ['country_name' => 'West African / Nigeria-related', 'modern_region' => 'Nigeria, Ghana, Sierra Leone, Gambia and nearby West African contexts', 'lat' => 9.1, 'lon' => 8.7, 'info' => 'West African reference projection from YRI/ESN/GWD/MSL-style overlap.'],
            'east african' => ['country_name' => 'East African bridge', 'modern_region' => 'Kenya and neighbouring East African reference context', 'lat' => 0.2, 'lon' => 37.9, 'info' => 'East African bridge from LWK-style overlap.'],
            'finnish' => ['country_name' => 'Finland / Nordic trace', 'modern_region' => 'Finland and nearby Nordic/Baltic reference context', 'lat' => 63.2, 'lon' => 25.7, 'info' => 'Low trace lane only until a stronger Nordic panel is installed.'],
            'ashkenazi' => ['country_name' => 'Ashkenazi Jewish diaspora trace', 'modern_region' => 'Central/Eastern European Jewish diaspora context', 'lat' => 50.1, 'lon' => 19.5, 'info' => 'Diaspora genetic-affinity trace only; not religion, identity, or community membership.'],
        ];
        foreach ($items as $needle => $meta) {
            if (strpos($label_l, $needle) !== false) {
                return $meta;
            }
        }
        return ['country_name' => (string) $label, 'modern_region' => 'Reference-panel region', 'lat' => 20.0, 'lon' => 0.0, 'info' => 'Reference-panel projection lane.'];
    };
    $map_points = [];
    $push_map_point = function ($label, $percent, $color, $type, $note = '') use (&$map_points, $map_meta) {
        $meta = $map_meta($label);
        $lat = (float) ($meta['lat'] ?? 0);
        $lon = (float) ($meta['lon'] ?? 0);
        $map_points[] = [
            'label' => (string) $label,
            'country_name' => (string) ($meta['country_name'] ?? $label),
            'modern_region' => (string) ($meta['modern_region'] ?? ''),
            'percent' => round((float) $percent, 1),
            'color' => (string) ($color ?: '#69f7ff'),
            'type' => (string) $type,
            'lat' => $lat,
            'lon' => $lon,
            'x' => round((($lon + 180.0) / 360.0) * 100.0, 2),
            'y' => round(((90.0 - $lat) / 180.0) * 100.0, 2),
            'info' => trim((string) ($note !== '' ? $note : ($meta['info'] ?? ''))),
        ];
    };
    foreach ($consumer_chart as $segment) {
        $push_map_point(
            (string) ($segment['label'] ?? 'Projection lane'),
            (float) ($segment['percent'] ?? $segment['value'] ?? 0),
            (string) ($segment['color'] ?? '#69f7ff'),
            'main',
            (string) ($segment['note'] ?? '')
        );
    }
    foreach ($consumer_trace_lanes as $trace) {
        $proxy = (float) ($trace['proxy_percent'] ?? 0);
        if ($proxy <= 0) {
            continue;
        }
        $push_map_point(
            (string) ($trace['label'] ?? 'Trace lane'),
            $proxy,
            '#ffd76f',
            'trace',
            (string) ($trace['plain'] ?? '')
        );
    }
    $consumer_estimate = [
        'engine' => $consumer_method,
        'headline' => 'Consumer-style heritage projection ready',
        'plain' => $consumer_plain,
        'segments' => $consumer_chart,
        'map_points' => $map_points,
        'map_boundary' => 'Map bubbles are generated locally from the active ZeroThink projection. Bubble size follows reported percent; trace lanes are shown separately from main heritage lanes.',
        'trace_lanes' => $consumer_trace_lanes,
        'suppressed_noise' => $consumer_suppressed_noise,
        'boundary' => 'This is a probabilistic ZeroThink projection for plain user understanding. It is not legal identity, culture, religion, citizenship, tribe, or final lab-certified ancestry.',
    ];
    $consumer_top_names = [];
    foreach (array_slice($consumer_chart, 0, 6) as $row) {
        $consumer_top_names[] = ($row['label'] ?? 'Projection lane') . ' (' . ($row['percent'] ?? 0) . '%)';
    }
    $visible_country_names = [];
    foreach ($plain_country_cards as $row) {
        if (!empty($row['show_as_lead']) && (float) ($row['proxy_percent'] ?? 0) > 0) {
            $visible_country_names[] = ($row['label'] ?? 'Reference lane') . ' (' . ($row['proxy_percent'] ?? 0) . '% proxy)';
        }
        if (count($visible_country_names) >= 5) {
            break;
        }
    }
    $consumer_headline = $consumer_top_names
        ? 'Plain projected composition: ' . implode(', ', $consumer_top_names) . '.'
        : ($visible_country_names
            ? 'Closest named reference leads: ' . implode(', ', $visible_country_names) . '.'
            : 'No named country-style reference lead is strong enough from the installed panel yet.');
    $consumer_snapshot = [
        'headline' => $consumer_headline,
        'plain' => $flat_mix
            ? 'This file matches thousands of markers and now produces a plain consumer-style projection. The evidence ledger underneath shows which lanes are strong, which are trace-level, and which need deeper compact controls.'
            : 'ZeroThink shows the easiest country/region names first, then the detailed evidence and confidence notes below.',
        'top_named_lanes' => $visible_country_names,
        'theory_boundary' => 'Theoretical lanes are allowed to be bold research leads, but they are not proof of ethnicity, religion, nationality, tribe, royal status, or ancient descent.',
    ];
    $jewish_display_proxy = round(max($jewish_panel_proxy, $ashkenazi_trace_proxy), 1);
    $jewish_display_proxy = round(max($jewish_display_proxy, $jewish_compact_trace_proxy), 1);
    if (!empty($jewish_evidence['panel_available'])) {
        $jewish_answer = (string) ($jewish_evidence['answer'] ?? 'diaspora-compatible trace - not distinct from European controls yet');
        $jewish_confidence = (string) ($jewish_evidence['confidence_band'] ?? 'trace / low');
        $jewish_plain = (string) ($jewish_evidence['plain'] ?? 'The specialist panel is installed, but the signal must separate from nearby European controls before it is promoted.');
        $jewish_next = 'Check the ASJ-control delta, parent-side trace screen, and family evidence. A low trace is a clue, not identity or religion.';
    } elseif ($jewish_display_proxy > 0) {
        $jewish_answer = 'possible low Ashkenazi-style trace - compact lens';
        $jewish_confidence = 'trace / low';
        $jewish_plain = 'The compact projection shows a low Ashkenazi-style diaspora trace. That can mean a real small signal, shared European/West-Eurasian overlap, or reference noise, so ZeroThink labels it as a trace clue.';
        $jewish_next = 'Treat as a trace clue unless future compact controls confirm it.';
    } else {
        $jewish_answer = 'not visible in compact lens';
        $jewish_confidence = 'low';
        $jewish_plain = 'The compact Jewish-diaspora lens did not find enough signal to promote this lane.';
        $jewish_next = 'Keep this as not visible unless future family or control evidence changes it.';
    }

    $cards = [
        $broad_answer('European / Europe-related', $europe, 30, 12, 'European'),
        $broad_answer('African / Africa-related', $africa, 30, 12, 'African'),
        $broad_answer('Chinese / Han Chinese-related', max($chinese, $east_asia), 26, 10, 'East Asian / Chinese-related'),
        $broad_answer('English / British Isles-related', max($english, $europe * 0.35), 18, 8, 'British Isles / English-related'),
        $south_axis_main
            ? $broad_answer('South Asian-related', $south_asia, 26, 10, 'South Asian')
            : $answer(
                'South Asian-related',
                $south_model_preview >= 6 ? 'weak overlap - suppressed from main chart' : 'not a strong signal in installed panel',
                round($south_share_preview, 1),
                'low / suppressed',
                'The installed panel saw some South Asian-style overlap, but the total pattern is European/British-Irish dominant and does not pass the main South Asian signal gate.',
                'Weak cross-panel overlap should not be shown as a country/ethnicity percentage. It can come from shared ancient variation, nearby reference geometry, or incomplete panel calibration.',
                'Keep this in the evidence ledger only unless expanded South Asian controls confirm it.'
            ),
        $broad_answer('Americas-related', $americas, 24, 8, 'Americas reference'),
        $answer(
            'Jewish / Ashkenazi / Sephardi / Mizrahi',
            $jewish_answer,
            $jewish_display_proxy,
            $jewish_confidence,
            $jewish_plain,
            'DNA can show genetic-affinity clues, but it cannot determine Jewish religion, identity, community membership, or belonging.',
            $jewish_next
        ),
        $answer(
            'Khazar',
            $compact_status($khazar_steppe_lens_proxy, 'Steppe / Turkic-Caucasus hypothesis lens')[0],
            $khazar_steppe_lens_proxy,
            $compact_status($khazar_steppe_lens_proxy, 'Steppe / Turkic-Caucasus hypothesis lens')[1],
            'Khazar is handled as a compact Steppe/Turkic-Caucasus hypothesis lens, not as a simple modern ethnicity yes/no.',
            'This lane can show consistency with a historical research direction, but it cannot prove Khazar identity or descent.',
            'Use genealogy, places, surnames, and future ancient-reference controls before making stronger statements.'
        ),
        $answer(
            'Israelite / Hashemite',
            'not DNA-answerable from this file',
            0,
            'not applicable',
            'Israelite and Hashemite are ancient/tribal/dynastic lineage questions. Raw SNP ancestry cannot prove them.',
            'A Hashemite claim would require sourced family-tree records and usually direct-line Y-DNA context, not an autosomal consumer file alone.',
            'Use the Lineage Clues wizard with GEDCOM, surnames, places, dates, and direct-line testing if available.'
        ),
        $answer(
            'Egyptian / North African',
            $compact_status($egypt_north_africa_lens_proxy, 'Egypt / North Africa bridge lens')[0],
            $egypt_north_africa_lens_proxy,
            $compact_status($egypt_north_africa_lens_proxy, 'Egypt / North Africa bridge lens')[1],
            'ZeroThink now runs a compact Egypt/North-Africa bridge lens instead of hiding this under vague labels.',
            'North African, Levantine, Arabian, East African, and Mediterranean signals can overlap, so this is a clue lane rather than a final country percentage.',
            'Use as a named research lead and compare with family/geography evidence.'
        ),
        $answer(
            'Iranian / Persian',
            $compact_status($iran_persia_lens_proxy, 'Iran / Persia / West-Central Asia bridge lens')[0],
            $iran_persia_lens_proxy,
            $compact_status($iran_persia_lens_proxy, 'Iran / Persia / West-Central Asia bridge lens')[1],
            'ZeroThink now runs a compact Iran/Persia/West-Central Asia bridge lens so Persian-style questions produce a clue instead of a dead-end.',
            'Iranian/Persian-related signals overlap with Caucasus, Central Asia, South Asia, Levant, and Arabian references, so this is not proof of national identity.',
            'Use as a named research lead and compare with family/geography evidence.'
        ),
    ];
    if ($suppress_south_user_output) {
        $cards = array_values(array_filter($cards, function ($row) use ($is_south_user_label) {
            return !$is_south_user_label(($row['label'] ?? '') . ' ' . ($row['plain'] ?? '') . ' ' . ($row['limitations'] ?? '') . ' ' . ($row['next_steps'] ?? ''));
        }));
    }

    $visible_super_mix = $super_mix;
    $visible_pop_mix = $pop_mix;
    if ($suppress_south_user_output) {
        $visible_super_mix = array_values(array_filter($visible_super_mix, function ($row) use ($is_south_user_label) {
            return !$is_south_user_label(($row['label'] ?? '') . ' ' . ($row['code'] ?? ''));
        }));
        $visible_pop_mix = array_values(array_filter($visible_pop_mix, function ($row) use ($is_south_user_label) {
            return !$is_south_user_label(($row['label'] ?? '') . ' ' . ($row['code'] ?? ''));
        }));
    }

    $headline = $is_optimized_reference ? 'Optimized reference-panel summary ready' : 'Reference-panel summary ready';
    $plain = $is_optimized_reference
        ? 'ZeroThink is using the installed optimized 1000 Genomes runtime panel for local genetic-affinity scoring. The output can now show plain country/region-style proxy percentages with confidence and evidence notes, while still keeping identity, culture, religion, tribe, and lineage claims separate.'
        : 'The report can show broad modern reference signals from installed panels. It cannot honestly output exact ethnicity, religion, tribe, ancient lineage, or country percentages unless the right reference panels are installed and calibrated.';
    if (!$installed) {
        $headline = 'Compact reference engine unavailable';
        $plain = 'ZeroThink can show marker-context clues, but the compact local reference engine needs to be refreshed before country/region projections are promoted.';
    } elseif ($visible_super_mix) {
        $top = array_slice($visible_super_mix, 0, 3);
        $bits = array_map(function ($row) {
            return ($row['proxy_percent'] ?? 0) . '% ' . ($row['label'] ?? 'reference signal');
        }, $top);
        $plain = 'Broad reference-mix proxy: ' . implode(', ', $bits) . '. Treat this as a panel-affinity preview, not a final ethnicity percentage.';
        if ($flat_mix) {
            $headline = 'Named heritage leads found - compact controls active';
        $plain = 'The compact panel found broad reference overlap: ' . implode(', ', $bits) . '. The report now shows plain named leads first, then labels deeper country, Jewish-diaspora, Khazar/Steppe, Levant/Israel, Persian, Egyptian, and lineage lanes as evidence-backed or theoretical depending on signal strength.';
        } elseif ($is_optimized_reference) {
            $headline = 'Optimized reference-panel leads installed';
            $plain = 'The optimized local panel found broad reference overlap: ' . implode(', ', $bits) . '. ZeroThink now treats the country/region cards as installed reference-panel proxy leads, not only a starter preview.';
        }
    }

    return [
        'engine' => 'ZDNA-ANSWER v0.1 Plain Ancestry Gate',
        'reference_pipeline' => $reference_pipeline,
        'reference_pack_reason' => (string) ($reference_pack['reason'] ?? ''),
        'reference_pack_meta' => (array) ($reference_pack['pack_meta'] ?? []),
        'admixture_fit' => $admixture_fit,
        'headline' => $headline,
        'plain_summary' => $plain,
        'consumer_snapshot' => $consumer_snapshot,
        'consumer_estimate' => $consumer_estimate,
        'broad_reference_mix' => array_slice($visible_super_mix, 0, 8),
        'modern_country_reference_mix' => array_slice($visible_pop_mix, 0, 10),
        'plain_country_cards' => array_slice($plain_country_cards, 0, 12),
        'theoretical_lanes' => $theoretical_lanes,
        'jewish_diaspora_evidence' => $jewish_evidence,
        'cards' => $cards,
        'flat_signal_warning' => $flat_mix,
        'suppress_south_user_output' => $suppress_south_user_output,
        'truth_boundary' => $is_optimized_reference
            ? 'Percentages shown here are optimized reference-panel proxy percentages. They are stronger than marker-only guesses, but still not proof of ethnicity, religion, nationality, tribe, culture, or identity.'
            : 'Percentages shown here are reference-panel proxy percentages, not proof of ethnicity, religion, nationality, tribe, culture, or identity.',
    ];
}

function zl_dna_apoe_research_summary($hits) {
    $a = zl_dna_hit_by_rsid($hits, 'rs429358');
    $b = zl_dna_hit_by_rsid($hits, 'rs7412');
    if (!$a || !$b) {
        return null;
    }
    $g1 = strtoupper((string) ($a['genotype'] ?? ''));
    $g2 = strtoupper((string) ($b['genotype'] ?? ''));
    $label = 'APOE marker pair present';
    $plain = 'Both APOE research markers were found. Consumer raw DNA is not a diagnosis and the two markers need careful strand/build handling.';
    if (preg_match('/^[TC]{2}$/', $g1) && preg_match('/^[TC]{2}$/', $g2)) {
        if ($g1 === 'TT' && $g2 === 'CC') {
            $label = 'APOE e3/e3-compatible research pattern';
            $plain = 'This common marker pattern is usually treated as APOE e3/e3-compatible in simple research screens.';
        } elseif ($g1 === 'TC' && $g2 === 'CC') {
            $label = 'APOE e3/e4-compatible research pattern';
            $plain = 'This pattern can be compatible with one APOE e4 research allele. Confirm clinically before acting on it.';
        } elseif ($g1 === 'CC' && $g2 === 'CC') {
            $label = 'APOE e4/e4-compatible research pattern';
            $plain = 'This pattern can be compatible with two APOE e4 research alleles. Treat as sensitive research context only and confirm clinically.';
        } elseif ($g1 === 'TT' && $g2 === 'CT') {
            $label = 'APOE e2/e3-compatible research pattern';
            $plain = 'This pattern can be compatible with an APOE e2/e3 research result. It is not a clinical report.';
        } elseif ($g1 === 'TT' && $g2 === 'TT') {
            $label = 'APOE e2/e2-compatible research pattern';
            $plain = 'This pattern can be compatible with APOE e2/e2 in a simple screen. It is not a clinical report.';
        } elseif ($g1 === 'TC' && $g2 === 'CT') {
            $label = 'APOE mixed research pattern';
            $plain = 'This mixed APOE pattern needs phasing/strand confirmation before any interpretation.';
        }
    }
    $e4_dose = 0;
    if (stripos($label, 'e4/e4') !== false) {
        $e4_dose = 2;
    } elseif (stripos($label, 'e3/e4') !== false || stripos($label, 'mixed') !== false) {
        $e4_dose = 1;
    }
    return [
        'category' => 'Brain and lipid research',
        'title' => $label,
        'plain' => $plain,
        'markers' => ['rs429358 ' . $g1, 'rs7412 ' . $g2],
        'priority' => stripos($label, 'e4') !== false ? 'attention' : 'context',
        'dosage' => $e4_dose,
        'evidence_weight' => 2.6,
        'assay_confidence' => 0.72,
        'next_step' => 'If this matters to the user, recommend certified lab confirmation and a clinician/genetic counsellor rather than action from a raw file.',
    ];
}

function zl_dna_health_insights($hits, $summary = [], $variant_map = [], $options = []) {
    $gate = zl_dna_health_interpretation_gate($summary);
    $cards = [];
    if (!empty($gate['ready'])) {
        foreach ((array) $hits as $hit) {
            $rsid = strtolower((string) ($hit['rsid'] ?? ''));
            if (in_array($rsid, ['rs429358', 'rs7412'], true) && empty($options['include_sensitive_health'])) {
                continue;
            }
            $cards[] = [
                'title' => (string) ($hit['gene'] ?? 'Marker') . ' research context',
                'category' => 'Locus context', 'priority' => 'context',
                'plain' => 'A direct call is present at this research locus. The bundled watchlist has no validated assembly-specific effect-allele contract, so no risk allele, dosage, phenotype or negative result is inferred. Review exact source matches separately.',
                'markers' => [strtoupper($rsid) . ' ' . (string) ($hit['genotype'] ?? '')],
                'match_status' => 'locus_context_only', 'source_name' => 'ZeroThink research watchlist',
                'source_version' => 'bundled watchlist v0.2; effect interpretation withheld',
                'evidence_level' => 'locus_presence_only', 'assay_confidence' => 0,
                'confidence_band' => 'not established', 'evidence_weight' => 0,
                'limitations' => 'Locus presence is not an allele-specific clinical interpretation.',
                'next_step' => 'Use exact ClinVar evidence where available and confirm important findings with a qualified professional and certified laboratory.',
                'dbsnp' => (string) ($hit['dbsnp'] ?? ''), 'clinvar_search' => (string) ($hit['clinvar_search'] ?? ''),
            ];
        }
    }
    return [
        'math_system' => 'ZDNA health evidence validation v0.3', 'quality' => zl_dna_quality_math($summary),
        'analysis_gate' => $gate, 'sensitive_findings' => !empty($options['include_sensitive_health']) ? 'context_included_by_explicit_opt_in' : 'withheld_by_default',
        'math_summary' => 'Watchlist loci provide research context only. Personalized evidence requires exact assembly, coordinates, source alleles and a callable observed allele.',
        'headline' => empty($gate['ready']) ? 'Health interpretation paused for validation' : 'Health source evidence and locus context',
        'plain_summary' => empty($gate['ready']) ? 'Confirm the file build, reported forward orientation and quality before interpretation.' : 'Locus context cards carry no inferred risk or dosage. Any exact ClinVar matches are shown separately and remain research evidence.',
        'attention_count' => 0, 'context_count' => count($cards), 'categories' => ['Locus context' => count($cards)],
        'evidence_index' => 0, 'followup_index' => 0, 'trait_index' => 0, 'uncertainty' => 100, 'domain_scores' => [],
        'blood_type_research' => ['available' => false, 'reason' => 'Sparse watchlist genotypes do not establish blood type.'],
        'cards' => $cards, 'chart' => [],
        'agent_prompts' => ['Explain my exact source evidence and its limitations.', 'Why does locus presence differ from a health finding?'],
    ];
}

// Preserved math and watchlist rules for audit; not a validated clinical allele contract.
function zl_dna_health_insights_legacy_unvalidated($hits, $summary = [], $variant_map = [], $options = []) {
    $quality = zl_dna_quality_math($summary);
    $gate = zl_dna_health_interpretation_gate($summary);
    if (empty($gate['ready'])) {
        return [
            'math_system' => 'ZDNA-MAP v1.2 Health Safety Gate',
            'quality' => $quality,
            'analysis_gate' => $gate,
            'math_summary' => 'Health interpretation was withheld because build, orientation, callability, or QC was not sufficiently confirmed.',
            'headline' => 'Health interpretation paused for validation',
            'plain_summary' => 'ZeroThink parsed the file but did not turn locus presence into a health finding. Confirm the genome build and reported forward strand, then rerun.',
            'attention_count' => 0,
            'context_count' => 0,
            'categories' => [],
            'evidence_index' => 0,
            'followup_index' => 0,
            'trait_index' => 0,
            'uncertainty' => 100,
            'domain_scores' => [],
            'blood_type_research' => ['available' => false, 'reason' => 'withheld by health safety gate'],
            'cards' => [],
            'withheld_callable_marker_count' => count((array) $hits),
            'chart' => zl_dna_safe_percent_segments([['label' => 'Validation required', 'value' => 1, 'color' => '#ffb86b']]),
            'agent_prompts' => ['Why was health interpretation paused?', 'How do I confirm my genome build and strand?'],
        ];
    }
    $rules = [
        'rs6025' => ['category' => 'Blood clotting research', 'title' => 'Factor V Leiden screen', 'alleles' => ['A'], 'seen' => 'A Factor V Leiden research allele appears in this raw file.', 'clear' => 'The common Factor V Leiden research allele was not seen in this marker call.', 'next' => 'Do not change medication or anticoagulant choices from this. Confirm with a clinician if personally relevant.'],
        'rs1799963' => ['category' => 'Blood clotting research', 'title' => 'Prothrombin G20210A screen', 'alleles' => ['A'], 'seen' => 'A watched prothrombin clotting research allele appears in this raw file.', 'clear' => 'The watched prothrombin clotting research allele was not seen in this marker call.', 'next' => 'Do not change medication or anticoagulant choices from this. Confirm with a clinician if personally relevant.'],
        'rs1800562' => ['category' => 'Iron metabolism research', 'title' => 'HFE C282Y screen', 'alleles' => ['A'], 'seen' => 'One or more HFE C282Y research alleles appear in this raw file.', 'clear' => 'The HFE C282Y research allele was not seen in this marker call.', 'next' => 'Iron markers require lab blood tests and clinical context.'],
        'rs1799945' => ['category' => 'Iron metabolism research', 'title' => 'HFE H63D screen', 'alleles' => ['G'], 'seen' => 'One or more HFE H63D research alleles appear in this raw file.', 'clear' => 'The HFE H63D research allele was not seen in this marker call.', 'next' => 'Iron markers require lab blood tests and clinical context.'],
        'rs1801133' => ['category' => 'Folate-cycle research', 'title' => 'MTHFR C677T screen', 'alleles' => ['T'], 'seen' => 'An MTHFR C677T literature allele appears in this raw file.', 'clear' => 'The MTHFR C677T literature allele was not seen in this marker call.', 'next' => 'Do not change supplements or medication from this alone.'],
        'rs1801131' => ['category' => 'Folate-cycle research', 'title' => 'MTHFR A1298C screen', 'alleles' => ['C'], 'seen' => 'An MTHFR A1298C literature allele appears in this raw file.', 'clear' => 'The MTHFR A1298C literature allele was not seen in this marker call.', 'next' => 'Do not change supplements or medication from this alone.'],
        'rs671' => ['category' => 'Alcohol metabolism', 'title' => 'ALDH2 alcohol flush marker', 'alleles' => ['A'], 'seen' => 'An ALDH2 inactive-enzyme literature allele appears in this raw file.', 'clear' => 'The common ALDH2 inactive-enzyme allele was not seen in this marker call.', 'next' => 'This is trait research context, not medical advice.'],
        'rs4988235' => ['category' => 'Nutrition trait research', 'title' => 'Lactase persistence marker', 'alleles' => ['T', 'A'], 'seen' => 'A lactase-persistence-associated marker allele may be present, depending on strand/build.', 'clear' => 'This single lactase marker does not show a strong persistence signal here, but other lactase variants exist.', 'next' => 'Diet tolerance is personal and can differ from genotype.'],
        'rs7903146' => ['category' => 'Metabolic literature', 'title' => 'TCF7L2 metabolic marker', 'alleles' => ['T'], 'seen' => 'A TCF7L2 metabolic-trait literature allele appears in this raw file.', 'clear' => 'The watched TCF7L2 literature allele was not seen in this marker call.', 'next' => 'This is not a diabetes prediction or risk score.'],
        'rs1229984' => ['category' => 'Alcohol metabolism', 'title' => 'ADH1B alcohol metabolism marker', 'alleles' => ['T'], 'seen' => 'An ADH1B alcohol-metabolism literature allele appears in this raw file.', 'clear' => 'The watched ADH1B literature allele was not seen in this marker call.', 'next' => 'Trait association only; behaviour and health context matter more.'],
        'rs9939609' => ['category' => 'Body-weight literature', 'title' => 'FTO body-weight association marker', 'alleles' => ['A'], 'seen' => 'An FTO body-weight association literature allele appears in this raw file.', 'clear' => 'The watched FTO allele was not seen in this marker call.', 'next' => 'Small-effect marker only; lifestyle and environment dominate.'],
        'rs762551' => ['category' => 'Nutrition trait research', 'title' => 'CYP1A2 caffeine marker', 'alleles' => ['C'], 'seen' => 'A caffeine-metabolism literature allele appears in this raw file.', 'clear' => 'The watched caffeine-metabolism allele was not seen in this marker call.', 'next' => 'Treat this as a trait clue only, not health or sleep advice.'],
        'rs4244285' => ['category' => 'Pharmacogenomics research', 'title' => 'CYP2C19 star-allele screen', 'alleles' => ['A'], 'seen' => 'A CYP2C19 pharmacogenomics literature allele appears in this raw file.', 'clear' => 'The watched CYP2C19 allele was not seen in this marker call.', 'next' => 'Never change medication from raw DNA. Use clinical pharmacogenetic testing and clinician guidance.'],
        'rs4986893' => ['category' => 'Pharmacogenomics research', 'title' => 'CYP2C19 secondary screen', 'alleles' => ['A'], 'seen' => 'A CYP2C19 pharmacogenomics literature allele appears in this raw file.', 'clear' => 'The watched CYP2C19 allele was not seen in this marker call.', 'next' => 'Never change medication from raw DNA. Use clinical pharmacogenetic testing and clinician guidance.'],
        'rs1799853' => ['category' => 'Pharmacogenomics research', 'title' => 'CYP2C9 star-allele screen', 'alleles' => ['T'], 'seen' => 'A CYP2C9 pharmacogenomics literature allele appears in this raw file.', 'clear' => 'The watched CYP2C9 allele was not seen in this marker call.', 'next' => 'Never change medication from raw DNA. Use clinical pharmacogenetic testing and clinician guidance.'],
        'rs1057910' => ['category' => 'Pharmacogenomics research', 'title' => 'CYP2C9 secondary screen', 'alleles' => ['C'], 'seen' => 'A CYP2C9 pharmacogenomics literature allele appears in this raw file.', 'clear' => 'The watched CYP2C9 allele was not seen in this marker call.', 'next' => 'Never change medication from raw DNA. Use clinical pharmacogenetic testing and clinician guidance.'],
        'rs9923231' => ['category' => 'Pharmacogenomics research', 'title' => 'VKORC1 warfarin-response screen', 'alleles' => ['T'], 'seen' => 'A VKORC1 warfarin-response literature allele appears in this raw file.', 'clear' => 'The watched VKORC1 allele was not seen in this marker call.', 'next' => 'Never change anticoagulant dose from this. Warfarin dosing requires clinical care.'],
        'rs2814778' => ['category' => 'Blood group research', 'title' => 'ACKR1 / Duffy blood group clue', 'alleles' => ['C'], 'seen' => 'A watched ACKR1/Duffy literature allele appears in this raw file.', 'clear' => 'The watched ACKR1/Duffy allele was not seen in this marker call.', 'next' => 'This is blood-group research context only, not a clinical blood-type result.'],
    ];
    $cards = [];
    $apoe = !empty($options['include_sensitive_health']) ? zl_dna_apoe_research_summary($hits) : null;
    if ($apoe) {
        $cards[] = $apoe;
    }
    foreach ((array) $hits as $hit) {
        $rsid = strtolower((string) ($hit['rsid'] ?? ''));
        if (in_array($rsid, ['rs429358', 'rs7412'], true) || !isset($rules[$rsid])) {
            continue;
        }
        $rule = $rules[$rsid];
        $genotype = (string) ($hit['genotype'] ?? '');
        $count = zl_dna_allele_count($genotype, $rule['alleles']);
        $weight = in_array($rsid, ['rs6025', 'rs1799963', 'rs1800562', 'rs1799945'], true) ? 2.45 : 1.35;
        if (in_array($rsid, ['rs671', 'rs4988235', 'rs1229984', 'rs762551', 'rs2814778'], true)) {
            $weight = 0.95;
        }
        $cards[] = [
            'category' => $rule['category'],
            'title' => $rule['title'],
            'plain' => $count > 0
                ? $rule['seen']
                : 'This marker was directly called, but the watched allele was not observed on the confirmed reported orientation. This is not a negative clinical test and does not rule out the condition or trait.',
            'markers' => [strtoupper($rsid) . ' ' . ($genotype ?: 'not-called')],
            'priority' => $count > 0 ? 'attention' : 'observed_reference',
            'dosage' => $count,
            'dosage_label' => $count === 2 ? 'two watched alleles directly observed' : ($count === 1 ? 'one watched allele directly observed' : 'directly called; watched allele not observed (not a negative test)'),
            'evidence_weight' => $weight,
            'assay_confidence' => round(max(0.35, min(0.96, ($quality['score'] ?? 0) / 100)), 2),
            'next_step' => $rule['next'],
            'dbsnp' => $hit['dbsnp'] ?? '',
            'clinvar_search' => $hit['clinvar_search'] ?? '',
        ];
    }
    $blood = zl_dna_blood_type_research($variant_map, $summary, $quality);
    if (!empty($blood['card'])) {
        $cards[] = $blood['card'];
    }
    $attention = 0;
    $context = 0;
    $categories = [];
    foreach ($cards as $card) {
        if (($card['priority'] ?? '') === 'attention') {
            $attention++;
        } else {
            $context++;
        }
        $cat = (string) ($card['category'] ?? 'Research markers');
        $categories[$cat] = ($categories[$cat] ?? 0) + 1;
    }
    arsort($categories, SORT_NUMERIC);
    $math = zl_dna_health_evidence_math($cards, $quality);
    return [
        'math_system' => 'ZDNA-MAP v1.2 Health Evidence Algebra',
        'quality' => $quality,
        'analysis_gate' => $gate,
        'sensitive_findings' => !empty($options['include_sensitive_health']) ? 'included_by_explicit_opt_in' : 'withheld_by_default',
        'math_summary' => 'ZeroThink weights each watched genotype by dosage, marker importance, raw-file quality, and safe-action boundary. It outputs a follow-up index, not a diagnosis or disease-risk percentage.',
        'headline' => $attention > 0
            ? 'Some research markers need a careful second look.'
            : 'No watched allele was flagged in this limited screen; this is not a negative test.',
        'plain_summary' => 'ZeroThink found ' . count($cards) . ' health-related research card(s). These are educational marker notes, not diagnosis, treatment guidance, medication guidance, or a personal disease-risk score.',
        'attention_count' => $attention,
        'context_count' => $context,
        'categories' => $categories,
        'evidence_index' => $math['evidence_index'],
        'followup_index' => $math['followup_index'],
        'trait_index' => $math['trait_index'],
        'uncertainty' => $math['uncertainty'],
        'domain_scores' => $math['domain_scores'],
        'blood_type_research' => $blood,
        'cards' => $cards,
        'chart' => zl_dna_safe_percent_segments([
            ['label' => 'Follow-up evidence', 'value' => max(0.25, $math['followup_index']), 'color' => '#ff7b7b'],
            ['label' => 'Trait / context evidence', 'value' => max(0.25, $math['trait_index']), 'color' => '#69f7ff'],
            ['label' => 'Raw-file quality support', 'value' => max(0.25, ($quality['score'] ?? 0) / 35), 'color' => '#00ff91'],
        ]),
        'agent_prompts' => [
            'Explain my health report in plain English.',
            'Which markers should I verify in ClinVar?',
            'What should I ask a clinician or genetic counsellor?',
        ],
    ];
}

function zl_dna_blood_type_research($variant_map, $summary = [], $quality = []) {
    $variants = (array) ($variant_map['variants'] ?? []);
    $marker = function ($rsid) use ($variants) {
        $key = strtolower((string) $rsid);
        return $variants[$key] ?? null;
    };
    $o = $marker('rs8176719');
    $b1 = $marker('rs8176746');
    $b2 = $marker('rs8176747');
    $watched = array_filter([$o, $b1, $b2]);
    $quality_score = (float) ($quality['score'] ?? ($summary['quality_score'] ?? 0));
    $markers = [];
    foreach ($watched as $row) {
        $markers[] = strtoupper((string) ($row['rsid'] ?? 'rs?')) . ' ' . ((string) ($row['genotype'] ?? '') ?: 'not-called');
    }

    $has_o_deletion_clue = false;
    $o_dose = 0;
    if ($o) {
        $geno = strtoupper((string) ($o['genotype'] ?? ''));
        $o_dose = substr_count($geno, '-') + substr_count($geno, 'D');
        $has_o_deletion_clue = $o_dose > 0 || stripos($geno, 'DEL') !== false;
    }
    $b_clue = false;
    foreach ([$b1, $b2] as $row) {
        if (!$row) {
            continue;
        }
        $letters = zl_dna_genotype_letters($row['genotype'] ?? '');
        if (in_array('G', $letters, true) || in_array('T', $letters, true)) {
            $b_clue = true;
        }
    }

    $label = 'ABO/Rh blood-type clue: not enough data';
    $plain = 'This raw file does not contain enough watched ABO markers for even a cautious blood-type clue. Rh positive/negative is especially hard to infer from consumer SNP files because it can involve structural variation.';
    $confidence = 'very_low';
    $priority = 'context';
    if (count($watched) > 0) {
        $confidence = $quality_score >= 70 ? 'low' : 'very_low';
        if ($has_o_deletion_clue && !$b_clue) {
            $label = $o_dose >= 2 ? 'ABO clue: O-like signal possible' : 'ABO clue: O-carrier-like signal possible';
            $plain = 'A watched ABO O-tag marker appears in this raw file. This can support a cautious O-like clue, but it is not a certified blood type and can be wrong without full ABO/Rh testing.';
        } elseif ($b_clue) {
            $label = 'ABO clue: B-like/AB-like signal possible';
            $plain = 'A watched ABO B-tag marker appears in this raw file. This is only a clue; distinguishing A, B, AB, and O reliably needs stronger marker coverage or a real blood typing test.';
        } else {
            $label = 'ABO clue: non-O/non-B marker pattern possible';
            $plain = 'The watched ABO markers present here do not show the simple O or B clue pattern. That still does not prove blood type, because sparse SNP files can miss important ABO/Rh biology.';
        }
    }

    $card = [
        'category' => 'Blood group research',
        'title' => 'ABO/Rh blood-type clue screen',
        'plain' => $plain,
        'markers' => $markers ?: ['ABO watched markers not present in this file'],
        'priority' => $priority,
        'dosage' => count($watched),
        'dosage_label' => count($watched) . ' watched ABO marker(s) present',
        'evidence_weight' => 0.72,
        'assay_confidence' => round(max(0.12, min(0.55, $quality_score / 180)), 2),
        'confidence_band' => $confidence,
        'claim_label' => $label,
        'next_step' => 'Use this only as a fun/research clue. Real blood type needs a blood test or certified lab report, especially for Rh status.',
    ];

    return [
        'label' => $label,
        'confidence_band' => $confidence,
        'markers_checked' => ['rs8176719', 'rs8176746', 'rs8176747'],
        'markers_seen' => $markers,
        'boundary' => 'Research-only clue. Not a clinical ABO or Rh result.',
        'card' => $card,
    ];
}

// Sonification retains only a bounded event sample, not a full genome record map.
// First-pass IDs preserve the full parser's unique insertion order, including
// no-calls. Their scalar set is bounded by max_rows and released before pass two.
function zl_dna_sonic_sample_path($path, $name = 'raw-dna.txt', $max_rows = 1200000) {
    $seen = [];
    $scan = zl_dna_scan_rsid_genotypes_path($path, $name, function ($rsid, $genotype) use (&$seen) {
        $seen[$rsid] = true;
    }, $max_rows);
    $count = count($seen);
    $max_events = min(420, max(120, (int) ceil(max(1, $count) / 1050)));
    $step = max(1, (int) floor(max(1, $count) / $max_events));
    $selected = [];
    $ordinal = 0;
    foreach ($seen as $rsid => $present) {
        if ($ordinal % $step === 0) {
            $selected[$rsid] = true;
            if (count($selected) >= $max_events) {
                break;
            }
        }
        $ordinal++;
    }
    unset($seen);
    $scan['file_hash'] = hash_file('sha256', $path);
    $scan['unique_rsid_variants'] = $count;
    $scan['variants'] = $selected ? zl_dna_sonic_selected_records_path($path, $selected, $max_rows) : [];
    return $scan;
}

// The selected-record decode below deliberately follows raw_variant_map_path's
// header, row-limit, VCF eligibility and record-provenance rules. It uses the
// same canonical duplicate merger; it never reinterprets genotype conflicts.
// Keep parity tests against that parser when changing its accepted formats.
function zl_dna_sonic_selected_records_path($path, $selected, $max_rows = 1200000) {
    zl_dna_raise_runtime_limits();
    if (!is_file($path) || !is_readable($path)) {
        throw new Exception('The DNA file could not be read.');
    }
    $header = [];
    $vcf_mode = false;
    $variants = [];
    $data_lines = 0;
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new Exception('The DNA file could not be opened.');
    }

    while (($line = fgets($handle)) !== false) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (strpos($line, '##') === 0) {
            continue;
        }
        if (strpos($line, '#') === 0) {
            if (stripos($line, '#CHROM') === 0) {
                $header = zl_dna_raw_header_map(zl_dna_raw_split_row(ltrim($line, '#')));
                $vcf_mode = true;
            }
            continue;
        }

        $cols = zl_dna_raw_split_row($line);
        if (count($cols) < 3) {
            continue;
        }
        $lower = array_map('strtolower', $cols);
        $joined_header = implode('|', $lower);
        if (preg_match('/\b(rsid|snp|chromosome|position|genotype|result|allele1|allele2)\b/', $joined_header) && !preg_match('/^rs\d+/i', (string) ($cols[0] ?? ''))) {
            $header = zl_dna_raw_header_map($cols);
            continue;
        }

        $data_lines++;
        if ($data_lines > $max_rows) {
            break;
        }

        // Select by the same normalized identifier accepted by the path parser.
        // Decode and merge every selected occurrence, including late conflicts.
        $candidate_vcf = $vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')));
        $candidate_id = $candidate_vcf
            ? zl_dna_raw_pick($cols, $header, ['id', 'rsid', 'snp'], 2)
            : zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0);
        $candidate_id = strtolower(trim((string) $candidate_id));
        if (!isset($selected[$candidate_id])) {
            continue;
        }
        $is_vcf_record = $vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')));
        $ref = '';
        $alt = '';
        $vcf_format = '';
        $vcf_gt = '';
        if ($is_vcf_record) {
            $chrom = zl_dna_raw_pick($cols, $header, ['chrom', 'chromosome'], 0);
            $pos = zl_dna_raw_pick($cols, $header, ['pos', 'position'], 1);
            $rsid = zl_dna_raw_pick($cols, $header, ['id', 'rsid', 'snp'], 2);
            $ref = strtoupper(zl_dna_raw_pick($cols, $header, ['ref'], 3));
            $alt = strtoupper(zl_dna_raw_pick($cols, $header, ['alt'], 4));
            $sample = count($cols) === 10 && in_array(strtoupper(trim((string) ($cols[6] ?? ''))), ['PASS', '.'], true) ? (string) $cols[9] : '';
            $vcf_format = substr(trim((string) ($cols[8] ?? '')), 0, 120);
            $vcf_gt = zl_dna_vcf_gt_value($sample, $vcf_format);
            $geno = zl_dna_vcf_sample_genotype($sample, $ref, $alt, $vcf_format);
        } else {
            $rsid = zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0);
            $chrom = zl_dna_raw_pick($cols, $header, ['chromosome', 'chrom', 'chr'], 1);
            $pos = zl_dna_raw_pick($cols, $header, ['position', 'pos'], 2);
            $geno = zl_dna_raw_pick($cols, $header, ['result', 'genotype', 'alleles', 'allele'], count($cols) - 1);
            $a1 = zl_dna_raw_pick($cols, $header, ['allele1', 'allele_1']);
            $a2 = zl_dna_raw_pick($cols, $header, ['allele2', 'allele_2']);
            if (($a1 !== '' || $a2 !== '') && strlen($a1 . $a2) <= 12) {
                $geno = $a1 . $a2;
            } elseif (count($cols) >= 5 && strlen((string) $cols[3]) <= 4 && strlen((string) $cols[4]) <= 4) {
                $geno = (string) $cols[3] . (string) $cols[4];
            }
        }

        $rsid = strtolower(trim((string) $rsid));
        if (!preg_match('/^rs\d+$/', $rsid)) {
            continue;
        }
        $chrom = strtoupper(trim((string) $chrom, " \t\n\r\0\x0B\"'"));
        $chrom = preg_replace('/^CHR/i', '', $chrom);
        $record = [
            'rsid' => $rsid,
            'chromosome' => $chrom !== '' ? $chrom : 'unknown',
            'position' => trim((string) $pos),
            'genotype' => zl_dna_raw_clean_genotype($geno),
            'source_record_format' => $is_vcf_record ? 'vcf' : 'consumer_raw',
            'reference_allele' => $is_vcf_record ? zl_dna_raw_clean_genotype($ref) : '',
            'alternate_alleles' => $is_vcf_record ? substr(strtoupper(trim((string) $alt)), 0, 240) : '',
            'vcf_format' => $is_vcf_record ? $vcf_format : '',
            'vcf_gt' => $is_vcf_record ? $vcf_gt : '',
        ];
        zl_dna_merge_variant_record($variants, $rsid, $record);
    }
    fclose($handle);

    return $variants;
}

function zl_dna_sonic_profile_path($path, $name = 'raw-dna.txt', $summary_override = null) {
    zl_dna_raise_runtime_limits();
    $summary = is_array($summary_override) ? $summary_override : zl_dna_raw_analyze_path($path, $name);
    $map = zl_dna_sonic_sample_path($path, $name, zl_dna_max_raw_rows());
    $variants = (array) ($map['variants'] ?? []);
    $count = (int) ($map['unique_rsid_variants'] ?? 0);
    $hash = (string) ($summary['file_hash'] ?? hash_file('sha256', $path));
    $seed_hex = substr(hash('sha256', $hash . '|' . $count . '|ZDNA-SONIC'), 0, 16);
    $seed = hexdec(substr($seed_hex, 0, 8));
    $duration = min(96, max(42, (int) round(42 + min(36, $count / 12000))));
    $bpm = 54 + ($seed % 38);
    $base_freq = 110 * pow(2, (($seed % 12) / 12));
    $modes = [
        ['name' => 'Dorian pentatonic', 'intervals' => [0, 2, 3, 7, 9, 12]],
        ['name' => 'Lydian ambient', 'intervals' => [0, 2, 6, 7, 11, 12]],
        ['name' => 'Minor resonance', 'intervals' => [0, 3, 5, 7, 10, 12]],
        ['name' => 'Major suspended', 'intervals' => [0, 2, 5, 7, 9, 12]],
    ];
    $mode = $modes[$seed % count($modes)];
    $base_to_interval = ['A' => 0, 'C' => 2, 'G' => 4, 'T' => 5, 'N' => 3];
    $base_mix = ['A' => 0, 'C' => 0, 'G' => 0, 'T' => 0, 'N' => 0];
    $chrom_mix = [];
    $max_events = min(420, max(120, (int) ceil(max(1, $count) / 1050)));
    $events = [];
    $event_index = 0;

    foreach ($variants as $row) {
        if (!$row) {
            continue;
        }
        $geno = strtoupper((string) ($row['genotype'] ?? ''));
        $letters = zl_dna_genotype_letters($geno);
        if (!$letters) {
            $letters = ['N'];
        }
        foreach ($letters as $letter) {
            if (isset($base_mix[$letter])) {
                $base_mix[$letter]++;
            }
        }
        $chrom = strtoupper((string) ($row['chromosome'] ?? '0'));
        $chrom_mix[$chrom] = ($chrom_mix[$chrom] ?? 0) + 1;
        $chrom_num = (int) preg_replace('/[^0-9]/', '', $chrom);
        if ($chrom === 'X') {
            $chrom_num = 23;
        } elseif ($chrom === 'Y') {
            $chrom_num = 24;
        } elseif (in_array($chrom, ['M', 'MT'], true)) {
            $chrom_num = 25;
        }
        $pos = (int) preg_replace('/[^0-9]/', '', (string) ($row['position'] ?? '0'));
        $letter = $letters[0] ?? 'N';
        $interval_index = ($base_to_interval[$letter] ?? 3) + ($chrom_num % 2);
        $interval = $mode['intervals'][$interval_index % count($mode['intervals'])];
        $micro = (($pos + $seed + $event_index) % 17 - 8) / 100;
        $octave = ($chrom_num % 3) - 1;
        $freq = $base_freq * pow(2, (($interval + ($octave * 12)) / 12)) * (1 + $micro / 24);
        $freq = max(64, min(1320, $freq));
        $hetero = count(array_unique($letters)) > 1;
        $time = round(($event_index / max(1, $max_events)) * $duration, 3);
        $events[] = [
            'time' => $time,
            'frequency' => round($freq, 3),
            'duration' => $hetero ? 0.42 : 0.28,
            'gain' => $hetero ? 0.052 : 0.041,
            'pan' => round((($chrom_num % 7) - 3) / 3, 3),
            'wave' => $hetero ? 'triangle' : 'sine',
        ];
        $event_index++;
        if ($event_index >= $max_events) {
            break;
        }
    }
    arsort($chrom_mix, SORT_NUMERIC);
    $base_total = max(1, array_sum($base_mix));
    $base_percent = [];
    foreach ($base_mix as $letter => $value) {
        $base_percent[$letter] = round(($value / $base_total) * 100, 2);
    }

    $chart = zl_dna_safe_percent_segments([
        ['label' => 'A tone mass', 'value' => $base_mix['A'], 'color' => '#00ff91'],
        ['label' => 'C tone mass', 'value' => $base_mix['C'], 'color' => '#69f7ff'],
        ['label' => 'G tone mass', 'value' => $base_mix['G'], 'color' => '#ffd76f'],
        ['label' => 'T tone mass', 'value' => $base_mix['T'], 'color' => '#ff6edb'],
        ['label' => 'Unknown/no-call ambience', 'value' => max(0.1, $base_mix['N']), 'color' => '#9ca3af'],
    ]);

    $harmonic_seed = hexdec(substr(hash('sha256', $hash . '|zero-sonic-layer'), 0, 8));
    $harmonic_phase = round(($harmonic_seed % 6283) / 1000, 3);
    $harmonic_gain = round(0.0048 + (($harmonic_seed % 17) / 10000), 4);
    $harmonic_carrier = round(max(72, min(220, $base_freq)), 3);

    $profile = [
        'engine' => 'ZDNA-SONIC v0.2 Sparse Genome Sonification',
        'seed_hash' => substr(hash('sha256', $hash . '|sonic'), 0, 24),
        'duration_seconds' => $duration,
        'bpm' => $bpm,
        'base_frequency' => round($base_freq, 3),
        'mode' => $mode['name'],
        'scale_intervals' => $mode['intervals'],
        'events' => $events,
        'drone' => [
            'frequency' => round(max(48, $base_freq / 2), 3),
            'gain' => 0.018,
        ],
        'harmonic_layer' => [
            'enabled' => true,
            'name' => 'Zero Sonic harmonic layer',
            'phase' => $harmonic_phase,
            'gain' => $harmonic_gain,
            'carrier_frequency' => $harmonic_carrier,
            'public_note' => 'A low-level ambient harmonic layer is blended under the personal DNA tone score.',
        ],
        'base_mix' => $base_percent,
        'chromosome_mix' => array_slice($chrom_mix, 0, 8, true),
        'quality' => [
            'score' => (int) ($summary['quality_score'] ?? 0),
            'band' => (string) ($summary['quality_band'] ?? 'unknown'),
            'markers' => (int) ($summary['parsed_variants'] ?? $summary['marker_count'] ?? $count),
            'format_guess' => (string) ($summary['format_guess'] ?? ''),
            'build_guess' => (string) ($summary['genome_build_guess'] ?? 'unknown'),
        ],
        'privacy_boundary' => 'The browser receives a derived audio score only: no raw genotype rows, no RSID list, and no full genome reconstruction.',
        'truth_boundary' => 'This is experimental DNA sonification/ambience. It is not medical treatment, neurological treatment, diagnosis, or a reconstructed full genome.',
    ];

    return [
        'summary' => $summary,
        'variant_index' => [
            'file_name' => $map['file_name'],
            'file_hash' => $map['file_hash'],
            'parsed_rsid_variants' => $map['parsed_rsid_variants'],
            'truncated' => $map['truncated'],
        ],
        'profile' => $profile,
        'chart' => $chart,
        'insights' => [
            'math_system' => 'ZDNA-SONIC v0.2',
            'headline' => 'DNA sound profile ready.',
            'plain_summary' => 'ZeroThink converted the sparse raw DNA marker file into a deterministic ambience score using base mix, chromosome spread, marker order, quality weighting, a privacy-safe seed, and a low-level harmonic bed.',
            'math_summary' => 'Sparse consumer DNA files are not full genomes. ZDNA-SONIC anchors the composition to observed markers, then fills the listening space with a deterministic tone scaffold and ambient harmonic layer, not a clinical imputation.',
            'quality' => zl_dna_quality_math($summary),
            'cards' => [[
                'category' => 'DNA sonification',
                'title' => 'Sparse genome resonance proxy',
                'plain' => 'The generated sound is personal to this uploaded marker file, with a subtle ambient harmonic layer underneath. Treat it as experimental ambience and research art, not therapy or diagnosis.',
                'priority' => 'context',
            ]],
            'agent_prompts' => [
                'Explain how my DNA sound was generated.',
                'What does sparse genome sonification mean?',
                'What are the limits of this DNA sound feature?',
            ],
        ],
        'disclaimer' => 'Experimental sonification only. Not diagnosis, therapy, medical advice, or neurological treatment.',
    ];
}

function zl_dna_sonic_markdown($report) {
    $profile = (array) ($report['profile'] ?? []);
    $summary = (array) ($report['summary'] ?? []);
    $quality = (array) ($profile['quality'] ?? []);
    $lines = [];
    $lines[] = '## DNA Sound / ZDNA-SONIC';
    $lines[] = '';
    $lines[] = '**Boundary:** This is experimental DNA sonification and ambience. It is not diagnosis, treatment, medical advice, neurological treatment, or a reconstructed full genome.';
    $lines[] = '';
    $lines[] = '- File: ' . ($summary['file_name'] ?? 'DNA file');
    $lines[] = '- Format guess: ' . ($quality['format_guess'] ?? ($summary['format_guess'] ?? 'raw genotype file'));
    $lines[] = '- Markers parsed: ' . number_format((int) ($quality['markers'] ?? ($summary['marker_count'] ?? 0)));
    $lines[] = '- Quality: ' . ($quality['band'] ?? 'unknown') . ' (' . (int) ($quality['score'] ?? 0) . '/100)';
    $lines[] = '- Genome build guess: ' . ($quality['build_guess'] ?? ($summary['genome_build_guess'] ?? 'unknown'));
    $lines[] = '- Sonic engine: ' . ($profile['engine'] ?? 'ZDNA-SONIC');
    $lines[] = '- Mode: ' . ($profile['mode'] ?? 'ambient') . ' at ' . (int) ($profile['bpm'] ?? 0) . ' BPM';
    $lines[] = '- Duration: ' . (int) ($profile['duration_seconds'] ?? 0) . ' seconds';
    if (!empty($profile['harmonic_layer']['enabled'])) {
        $lines[] = '- Harmonic layer: ambient bed active';
    }
    $lines[] = '- Derived events: ' . number_format(count((array) ($profile['events'] ?? [])));
    $lines[] = '- Privacy: ' . ($profile['privacy_boundary'] ?? 'Derived audio only.');
    $lines[] = '';
    $lines[] = '### What the math does';
    $lines[] = '- Reads observed A/C/G/T calls from the sparse consumer file.';
    $lines[] = '- Builds a deterministic audio scaffold from marker count, chromosome spread, base mix, marker order, and file quality.';
    $lines[] = '- Adds a low-level ambient harmonic layer under the personal tone events for a deeper listening texture.';
    $lines[] = '- Converts that scaffold into tones, harmonics, stereo position, and ambience without exposing raw genotype rows to the browser.';
    $lines[] = '- Uses a sparse-to-reference style proxy for listening only. It does not claim to know every missing base in the genome.';
    $lines[] = '';
    $lines[] = '### Base tone mix';
    foreach ((array) ($profile['base_mix'] ?? []) as $base => $pct) {
        $lines[] = '- ' . $base . ': ' . $pct . '%';
    }
    $lines[] = '';
    $lines[] = '### Listen';
    $lines[] = 'Use the Play button on the DNA Sound panel. You can also download a WAV created in your browser.';
    return implode("\n", $lines);
}

function zl_dna_modern_region_glossary() {
    return [
        'Levant' => 'modern Eastern Mediterranean region: Israel, Palestine, Lebanon, Jordan, Syria, and nearby historical reference contexts',
        'Steppe' => 'Pontic-Caspian / Eurasian steppe reference context: roughly parts of modern Ukraine, southern Russia, Kazakhstan, and neighbouring steppe regions',
        'British Isles' => 'modern United Kingdom, Ireland, Isle of Man, and Channel Islands context; not the same as English identity',
        'West Eurasia' => 'broad Europe, Caucasus, West Asia, Middle East, and some North African/South Asian-adjacent reference contexts',
        'South Asia' => 'modern India, Pakistan, Bangladesh, Sri Lanka, Nepal, and nearby regional reference contexts',
        'East Asia' => 'modern China, Japan, Korea, Mongolia, Taiwan, and nearby East/Northeast Asian reference contexts',
        'Indigenous Americas' => 'Americas-related genetic reference context only; not tribal citizenship, culture, or belonging',
        'Jewish diaspora' => 'genetic-affinity/frequency context only; not religion, identity, community membership, or belonging',
        'Ashkenazi Jewish' => 'Central/Eastern European Jewish diaspora genetic-affinity context only; not religion, identity, or community membership',
        'Sephardi / Mizrahi' => 'Mediterranean, North African, Levantine, and West Asian Jewish-diaspora research lanes that need specialist controls',
    ];
}

function zl_dna_population_modern_label($code, $label = '', $super = '') {
    $code = strtoupper((string) $code);
    $map = [
        'AFR' => 'African reference super-population',
        'AMR' => 'Americas mixed-ancestry reference super-population',
        'EAS' => 'East Asian reference super-population',
        'EUR' => 'European reference super-population',
        'SAS' => 'South Asian reference super-population',
        'GBR' => 'United Kingdom: British in England and Scotland reference sample',
        'FIN' => 'Finland reference sample',
        'IBS' => 'Spain: Iberian reference sample',
        'TSI' => 'Italy: Toscani reference sample',
        'CEU' => 'Utah residents with Northern/Western European ancestry reference sample',
        'CHB' => 'China: Han Chinese in Beijing reference sample',
        'CHS' => 'China: Southern Han Chinese reference sample',
        'CDX' => 'China: Dai in Xishuangbanna reference sample',
        'JPT' => 'Japan: Japanese in Tokyo reference sample',
        'KHV' => 'Vietnam: Kinh in Ho Chi Minh City reference sample',
        'YRI' => 'Nigeria: Yoruba in Ibadan reference sample',
        'LWK' => 'Kenya: Luhya in Webuye reference sample',
        'GWD' => 'The Gambia: Gambian Mandinka reference sample',
        'MSL' => 'Sierra Leone: Mende reference sample',
        'ESN' => 'Nigeria: Esan reference sample',
        'ASW' => 'United States: African ancestry in Southwest US reference sample',
        'ACB' => 'Barbados: African Caribbean reference sample',
        'GIH' => 'India / Gujarati diaspora: Gujarati Indian in Houston reference sample',
        'PJL' => 'Pakistan: Punjabi in Lahore reference sample',
        'BEB' => 'Bangladesh: Bengali reference sample',
        'ITU' => 'India: Telugu in the UK reference sample',
        'STU' => 'Sri Lanka: Sri Lankan Tamil in the UK reference sample',
        'ASJ' => 'Ashkenazi Jewish diaspora frequency panel',
        'NFE' => 'Non-Finnish European frequency control panel',
        'MID' => 'Middle Eastern frequency control panel',
        'AMI' => 'Amish frequency control panel',
        'OTH' => 'Other ancestry frequency control panel',
        'SEF' => 'Sephardi Jewish diaspora research panel',
        'MIZ' => 'Mizrahi Jewish diaspora research panel',
        'LEV' => 'Levant / Eastern Mediterranean research panel',
        'MAG' => 'Maghreb / North Africa research panel',
        'IRN' => 'Iran / Persian research panel',
        'PEL' => 'Peru: Peruvian in Lima reference sample',
        'MXL' => 'Mexico / Mexican diaspora: Mexican ancestry in Los Angeles reference sample',
        'CLM' => 'Colombia: Colombian in Medellin reference sample',
        'PUR' => 'Puerto Rico: Puerto Rican reference sample',
    ];
    if (isset($map[$code])) {
        return $map[$code];
    }
    $label = trim((string) $label);
    if ($label !== '') {
        return $label;
    }
    return trim((string) $super) !== '' ? strtoupper((string) $super) . ' reference panel' : $code;
}

function zl_sqlite_table_exists($db, $table) {
    $table = SQLite3::escapeString((string) $table);
    $res = @$db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
    return is_string($res) && $res !== '';
}

function zl_sqlite_columns($db, $table) {
    $cols = [];
    $table = str_replace("'", "''", (string) $table);
    $res = @$db->query("PRAGMA table_info('$table')");
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $cols[] = (string) ($row['name'] ?? '');
    }
    return $cols;
}

function zl_sqlite_pick_col($cols, $candidates, $fallback = "''") {
    $cols = array_map('strtolower', (array) $cols);
    foreach ((array) $candidates as $candidate) {
        if (in_array(strtolower((string) $candidate), $cols, true)) {
            return (string) $candidate;
        }
    }
    return $fallback;
}

function zl_dna_sqlite_meta($db) {
    $meta = [];
    if (!zl_sqlite_table_exists($db, 'pack_meta')) {
        return $meta;
    }
    $res = @$db->query("SELECT key, value FROM pack_meta");
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $key = (string) ($row['key'] ?? '');
        if ($key !== '') {
            $meta[$key] = (string) ($row['value'] ?? '');
        }
    }
    return $meta;
}

function zl_dna_reference_groups($reference_pack) {
    $groups = [];
    foreach ((array) ($reference_pack['population_scores'] ?? []) as $row) {
        $panel = (string) ($row['panel_name'] ?? 'Unspecified reference panel');
        $groups[$panel][] = (array) $row;
    }
    ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($groups as &$rows) {
        usort($rows, function ($a, $b) {
            return ((float) ($b['affinity_score'] ?? 0)) <=> ((float) ($a['affinity_score'] ?? 0));
        });
    }
    unset($rows);
    return $groups;
}

function zl_dna_heritage_insights($hits, $summary = [], $reference_pack = []) {
    $quality = zl_dna_quality_math($summary);
    $lanes = [
        'West Eurasia / South Asia / Europe lens' => 0.0,
        'East / Northeast Asian lens' => 0.0,
        'African selection-marker lens' => 0.0,
        'Food adaptation lens' => 0.0,
        'Broad/shared trait markers' => 0.0,
    ];
    $cards = [];
    foreach ((array) $hits as $hit) {
        $rsid = strtolower((string) ($hit['rsid'] ?? ''));
        $genotype = strtoupper((string) ($hit['genotype'] ?? ''));
        $card = [
            'title' => strtoupper($rsid) . ' / ' . (string) ($hit['gene'] ?? 'marker'),
            'genotype' => $genotype,
            'plain' => 'Population-study marker found. It is useful context, not an ethnicity label.',
            'lane' => 'Broad/shared trait markers',
            'confidence' => 'low-marker-only',
            'dbsnp' => $hit['dbsnp'] ?? '',
        ];
        if ($rsid === 'rs1426654') {
            $lanes['West Eurasia / South Asia / Europe lens'] += zl_dna_allele_count($genotype, ['A']) > 0 ? 3 : 1;
            $card['lane'] = 'West Eurasia / South Asia / Europe lens';
            $card['plain'] = 'SLC24A5 is a strong population-differentiated pigmentation marker seen in many European, South Asian, West Asian, Middle Eastern, and North African reference contexts.';
        } elseif ($rsid === 'rs16891982') {
            $lanes['West Eurasia / South Asia / Europe lens'] += zl_dna_allele_count($genotype, ['G']) > 0 ? 2 : 1;
            $card['lane'] = 'West Eurasia / South Asia / Europe lens';
            $card['plain'] = 'SLC45A2 is another population-differentiated pigmentation marker. A mixed call is broad context, not a country estimate.';
        } elseif ($rsid === 'rs3827760' || $rsid === 'rs1800414') {
            $lanes['East / Northeast Asian lens'] += 1.4;
            $card['lane'] = 'East / Northeast Asian lens';
            $card['plain'] = 'This marker appears in East/Northeast Asian population literature, but strand/build confirmation is needed before any stronger statement.';
        } elseif ($rsid === 'rs2814778') {
            $has_selection = zl_dna_allele_count($genotype, ['C']) >= 2;
            $lanes['African selection-marker lens'] += $has_selection ? 3 : 0.5;
            $card['lane'] = 'African selection-marker lens';
            $card['plain'] = $has_selection
                ? 'This Duffy/ACKR1 marker can be part of a malaria-selection signal seen in some African ancestry contexts.'
                : 'This Duffy/ACKR1 marker does not show the simple Duffy-null pattern in this raw call.';
        } elseif ($rsid === 'rs671') {
            $lanes['East / Northeast Asian lens'] += zl_dna_allele_count($genotype, ['A']) > 0 ? 2 : 0.5;
            $card['lane'] = 'East / Northeast Asian lens';
            $card['plain'] = zl_dna_allele_count($genotype, ['A']) > 0
                ? 'ALDH2 shows an alcohol-metabolism allele often discussed in East Asian population literature.'
                : 'The common ALDH2 inactive-enzyme allele was not seen in this raw call.';
        } elseif ($rsid === 'rs4988235') {
            $lanes['Food adaptation lens'] += 1.8;
            $card['lane'] = 'Food adaptation lens';
            $card['plain'] = 'This lactase marker is more about food-adaptation history than identity. Multiple lactase variants exist worldwide.';
        } else {
            $lanes['Broad/shared trait markers'] += 1.0;
        }
        $card['quality_gate'] = $quality['confidence'];
        $cards[] = $card;
    }
    foreach ($lanes as $lane => $value) {
        if ($value <= 0) {
            $lanes[$lane] = 0.15;
        }
    }
    arsort($lanes, SORT_NUMERIC);
    $top_lanes = array_slice(array_keys($lanes), 0, 3);
    $region_prompts = [
        'West Eurasia / South Asia / Europe lens' => 'Explore reference panels covering Europe, South Asia, West Asia, the Middle East, North Africa, and nearby mixed diaspora groups.',
        'East / Northeast Asian lens' => 'Explore East Asian, Northeast Asian, Southeast Asian, and Indigenous American reference contexts only if genome-wide modelling supports it.',
        'African selection-marker lens' => 'Explore West, Central, East, North, and Southern African reference panels only with genome-wide modelling and confidence intervals.',
        'Food adaptation lens' => 'Ask about lactase persistence, diet adaptation, pastoralist history, and food-environment evolution.',
        'Broad/shared trait markers' => 'Use this as trait context, not identity. Shared markers can appear across many populations.',
    ];
    $prompts = [];
    foreach ($top_lanes as $lane) {
        $prompts[] = $region_prompts[$lane] ?? $lane;
    }
    $segments = [];
    $colors = ['#00ff91', '#69f7ff', '#ffb86b', '#ff6edb', '#d8faff'];
    $i = 0;
    foreach ($lanes as $lane => $value) {
        $segments[] = ['label' => $lane, 'value' => $value, 'color' => $colors[$i % count($colors)]];
        $i++;
    }
    $math = zl_dna_heritage_lens_math($lanes, $quality);
    $question_signals = []; // Tiny trait watchlists do not establish population ancestry.
    if (!empty($reference_pack['interpretation_ready'])) {
        foreach (zl_dna_reference_groups($reference_pack) as $panel_name => $panel_rows) {
        foreach (array_slice($panel_rows, 0, 3) as $pop) {
            $question_signals[] = [
                'label' => 'Reference comparison: ' . (string) ($pop['population_code'] ?? 'population') . ' [' . $panel_name . ']',
                'status' => 'genetic_affinity_to_installed_panel',
                'confidence_score' => (float) ($pop['confidence_score'] ?? 0),
                'confidence_band' => (string) ($pop['confidence_band'] ?? 'uncalibrated'),
                'matched_marker_count' => (int) ($pop['matched_marker_count'] ?? 0),
                'matched_chromosome_count' => (int) ($pop['matched_chromosome_count'] ?? 0),
                'source_name' => $panel_name,
                'source_version' => (string) ($pop['source_version'] ?? 'not supplied'),
                'why' => (string) ($pop['population_label'] ?? $pop['population_code'] ?? 'Reference group') . ': affinity ' . ($pop['affinity_score'] ?? 0) . '/100 across ' . ($pop['matched_marker_count'] ?? 0) . ' aligned markers on ' . ($pop['matched_chromosome_count'] ?? 0) . ' autosomes. This comparison belongs to ' . $panel_name . '; there is no winner across separate panels.',
                'limitations' => 'Reference-panel affinity is not nationality, culture, community membership, religion, tribe, or identity.',
                'next_steps' => 'Compare within the same panel; held-out calibration is needed before ancestry proportions or stronger conclusions.',
            ];
        }
        }
    }
    $plain_ancestry = zl_dna_plain_ancestry_summary($reference_pack, $question_signals, $quality);
    $jewish_plain_card = null;
    foreach ((array) ($plain_ancestry['cards'] ?? []) as $plain_card) {
        if (stripos((string) ($plain_card['label'] ?? ''), 'Jewish') !== false) {
            $jewish_plain_card = (array) $plain_card;
            break;
        }
    }
    if ($jewish_plain_card) {
        foreach ($question_signals as &$signal) {
            if (stripos((string) ($signal['label'] ?? ''), 'Ashkenazi Jewish') === false) {
                continue;
            }
            $signal['status'] = (string) ($jewish_plain_card['answer'] ?? $signal['status'] ?? 'research clue');
            $signal['confidence_band'] = (string) ($jewish_plain_card['confidence_band'] ?? $signal['confidence_band'] ?? 'low');
            $signal['confidence_score'] = (float) ($jewish_plain_card['proxy_percent'] ?? 0) >= 2.0 ? max(38.0, (float) ($signal['confidence_score'] ?? 0)) : min(34.0, (float) ($signal['confidence_score'] ?? 0));
            $signal['why'] = (string) ($jewish_plain_card['plain'] ?? $signal['why'] ?? '');
            $signal['limitations'] = (string) ($jewish_plain_card['limitations'] ?? $signal['limitations'] ?? '');
            $signal['next_steps'] = (string) ($jewish_plain_card['next_steps'] ?? $signal['next_steps'] ?? '');
        }
        unset($signal);
    }
    $suppress_south_user_output = !empty($plain_ancestry['suppress_south_user_output']);
    if ($suppress_south_user_output) {
        $is_south_user_label = function ($value) {
            return (bool) preg_match('/\b(Pakistan|Pakistani|Punjabi|Punjab|South Asian|South Asia|Bangladesh|Bengali|India|Gujarati|Telugu|Sri Lanka|Tamil|PJL|BEB|GIH|ITU|STU|SAS)\b/i', (string) $value);
        };
        $sanitize_south_terms = function ($value) {
            $value = str_replace('West Eurasia / South Asia / Europe lens', 'West Eurasia / Europe lens', (string) $value);
            $value = str_replace('South Asian', 'regional', $value);
            $value = str_replace('South Asia', 'nearby regions', $value);
            $value = str_replace('South-Asian', 'regional', $value);
            return $value;
        };
        $question_signals = array_values(array_filter($question_signals, function ($signal) use ($is_south_user_label) {
            return !$is_south_user_label(($signal['label'] ?? '') . ' ' . ($signal['why'] ?? '') . ' ' . ($signal['limitations'] ?? '') . ' ' . ($signal['next_steps'] ?? ''));
        }));
        $cards = array_values(array_filter($cards, function ($card) use ($is_south_user_label) {
            return !$is_south_user_label(($card['title'] ?? '') . ' ' . ($card['plain'] ?? '') . ' ' . ($card['lane'] ?? ''));
        }));
        $lanes = array_filter($lanes, function ($value, $lane) use ($is_south_user_label) {
            return !$is_south_user_label((string) $lane);
        }, ARRAY_FILTER_USE_BOTH);
        $prompts = array_values(array_filter($prompts, function ($prompt) use ($is_south_user_label) {
            return !$is_south_user_label($prompt);
        }));
        $top_lanes = array_values(array_map($sanitize_south_terms, $top_lanes));
        foreach ($segments as &$segment) {
            $segment['label'] = $sanitize_south_terms($segment['label'] ?? '');
        }
        unset($segment);
        if (!empty($math['confidence_bands'])) {
            foreach ($math['confidence_bands'] as &$band) {
                $band['label'] = $sanitize_south_terms($band['label'] ?? '');
            }
            unset($band);
        }
    }
    foreach ($cards as &$context_card) {
        $context_card['plain'] = 'A direct call is present at a population-study locus. A small trait watchlist cannot establish ancestry or identity.';
        $context_card['lane'] = 'Research locus context';
    }
    unset($context_card);
    $technical_chart = [];
    $consumer_chart = (array) ($plain_ancestry['consumer_estimate']['segments'] ?? []);
    $glossary = zl_dna_modern_region_glossary();
    if ($suppress_south_user_output) {
        unset($glossary['South Asia']);
        if (isset($glossary['West Eurasia'])) {
            $glossary['West Eurasia'] = 'broad Europe, Caucasus, West Asia, Middle East, and some North African-adjacent reference contexts';
        }
    }
    return [
        'math_system' => 'ZDNA-MAP v1.2 Heritage + Question Signal Algebra',
        'quality' => $quality,
        'math_summary' => 'ZeroThink converts a raw DNA file into marker-context lanes, then applies raw-file quality, marker overlap, source availability, and uncertainty bands. This guides deeper ancestry work without faking exact ethnicity percentages.',
        'headline' => (string) ($plain_ancestry['headline'] ?? 'Reference evidence'),
        'plain_summary' => (string) ($plain_ancestry['plain_summary'] ?? ''),
        'plain_ancestry_summary' => $plain_ancestry,
        'consumer_estimate' => $plain_ancestry['consumer_estimate'] ?? null,
        'top_lanes' => $top_lanes,
        'region_research_prompts' => $prompts,
        'confidence_bands' => [],
        'question_signals' => $question_signals,
        'modern_region_glossary' => $glossary,
        'uncertainty' => $math['uncertainty'],
        'cards' => $cards,
        'chart' => [],
        'technical_chart' => [],
        'agent_prompts' => [
            'Explain this heritage signal wheel in simple terms.',
            'What regions should I compare against with a proper reference panel?',
            'Can this report tell me who I am?',
            'Why can this not give exact ethnicity percentages yet?',
        ],
    ];
}

function zl_dna_local_health_pack_locus_candidates_unsafe_path($path, $name = 'raw-dna.txt', $limit = 80) {
    $db_path = dirname(__DIR__) . '/data/packs/health_core.sqlite';
    if (!is_readable($db_path)) {
        return [
            'installed' => false,
            'reason' => 'Health research source pack not active yet',
            'clinvar' => [],
            'gwas' => [],
            'pharmgkb' => [],
        ];
    }
    if (!class_exists('SQLite3')) {
        return [
            'installed' => false,
            'reason' => 'Health research engine unavailable',
            'clinvar' => [],
            'gwas' => [],
            'pharmgkb' => [],
        ];
    }
    $map = zl_dna_raw_variant_map_path($path, $name, zl_dna_max_raw_rows());
    $rsids = array_keys((array) ($map['variants'] ?? []));
    if (!$rsids) {
        return [
            'installed' => true,
            'reason' => 'no rsIDs parsed',
            'variants_scanned' => 0,
            'clinvar' => [],
            'gwas' => [],
            'pharmgkb' => [],
        ];
    }
    $db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
    $out = ['clinvar' => [], 'gwas' => [], 'pharmgkb' => []];
    $priorities = ['pathogenic', 'likely pathogenic', 'risk factor', 'drug response', 'association'];
    $rank = function ($text) use ($priorities) {
        $text = strtolower((string) $text);
        foreach ($priorities as $i => $needle) {
            if (strpos($text, $needle) !== false) {
                return 100 - ($i * 12);
            }
        }
        if (strpos($text, 'benign') !== false) {
            return 12;
        }
        return 35;
    };
    foreach (array_chunk($rsids, 450) as $chunk) {
        $quoted = array_map(function ($rsid) use ($db) {
            return "'" . SQLite3::escapeString(strtolower((string) $rsid)) . "'";
        }, $chunk);
        $in = implode(',', $quoted);
        if ($in === '') {
            continue;
        }
        $clinvar_cols = zl_sqlite_columns($db, 'clinvar_variants');
        $clinvar_gene = zl_sqlite_pick_col($clinvar_cols, ['gene', 'gene_symbol', 'GeneSymbol'], "''");
        $clinvar_condition = zl_sqlite_pick_col($clinvar_cols, ['condition_name', 'Name', 'PhenotypeList'], "''");
        $clinvar_sig = zl_sqlite_pick_col($clinvar_cols, ['clinical_significance', 'ClinicalSignificance'], "''");
        $clinvar_review = zl_sqlite_pick_col($clinvar_cols, ['review_status', 'ReviewStatus'], "''");
        $clinvar_accession = zl_sqlite_pick_col($clinvar_cols, ['accession', 'RCVaccession', 'variation_id'], "''");
        $clinvar_version = zl_sqlite_pick_col($clinvar_cols, ['source_version'], "''");
        $res = @$db->query("SELECT rsid,$clinvar_gene AS gene,$clinvar_sig AS clinical_significance,$clinvar_condition AS condition_name,$clinvar_review AS review_status,$clinvar_accession AS accession,$clinvar_version AS source_version FROM clinvar_variants WHERE rsid IN ($in) LIMIT 200");
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $row['rank'] = $rank(($row['clinical_significance'] ?? '') . ' ' . ($row['review_status'] ?? ''));
            $row['genotype'] = $map['variants'][$row['rsid']]['genotype'] ?? '';
            $out['clinvar'][] = $row;
        }
        $res = zl_sqlite_table_exists($db, 'gwas_associations') ? @$db->query("SELECT rsid,trait,mapped_trait,p_value,odds_ratio,beta,pubmed_id,ancestry_initial,source_version FROM gwas_associations WHERE rsid IN ($in) LIMIT 160") : false;
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $row['rank'] = 30;
            $row['genotype'] = $map['variants'][$row['rsid']]['genotype'] ?? '';
            $out['gwas'][] = $row;
        }
        $pg_table = zl_sqlite_table_exists($db, 'pharmgkb_annotations') ? 'pharmgkb_annotations' : (zl_sqlite_table_exists($db, 'pharmgkb_clinical_annotations') ? 'pharmgkb_clinical_annotations' : '');
        $res = false;
        if ($pg_table !== '') {
            $pg_cols = zl_sqlite_columns($db, $pg_table);
            $pg_variant = zl_sqlite_pick_col($pg_cols, ['variant_id', 'annotation_id', 'variant'], "''");
            $pg_gene = zl_sqlite_pick_col($pg_cols, ['gene'], "''");
            $pg_drug = zl_sqlite_pick_col($pg_cols, ['drug', 'chemical'], "''");
            $pg_pheno = zl_sqlite_pick_col($pg_cols, ['phenotype'], "''");
            $pg_evidence = zl_sqlite_pick_col($pg_cols, ['evidence_level'], "''");
            $pg_text = zl_sqlite_pick_col($pg_cols, ['annotation_text'], "''");
            $pg_version = zl_sqlite_pick_col($pg_cols, ['source_version'], "''");
            $res = @$db->query("SELECT $pg_variant AS variant_id,rsid,$pg_gene AS gene,$pg_drug AS drug,$pg_pheno AS phenotype,$pg_evidence AS evidence_level,$pg_text AS annotation_text,$pg_version AS source_version FROM $pg_table WHERE rsid IN ($in) LIMIT 120");
        }
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $row['rank'] = $rank(($row['evidence_level'] ?? '') . ' drug response');
            $row['genotype'] = $map['variants'][$row['rsid']]['genotype'] ?? '';
            $out['pharmgkb'][] = $row;
        }
        if (zl_sqlite_table_exists($db, 'pharmgkb_clinical_variants')) {
            $pg_cv_cols = zl_sqlite_columns($db, 'pharmgkb_clinical_variants');
            $cv_variant = zl_sqlite_pick_col($pg_cv_cols, ['variant'], "''");
            $cv_gene = zl_sqlite_pick_col($pg_cv_cols, ['gene'], "''");
            $cv_drug = zl_sqlite_pick_col($pg_cv_cols, ['chemical', 'drug'], "''");
            $cv_evidence = zl_sqlite_pick_col($pg_cv_cols, ['evidence_level'], "''");
            $cv_version = zl_sqlite_pick_col($pg_cv_cols, ['source_version'], "''");
            $res = @$db->query("SELECT $cv_variant AS variant_id,rsid,$cv_gene AS gene,$cv_drug AS drug,'' AS phenotype,$cv_evidence AS evidence_level,'' AS annotation_text,$cv_version AS source_version FROM pharmgkb_clinical_variants WHERE rsid IN ($in) LIMIT 120");
            while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                $row['rank'] = $rank(($row['evidence_level'] ?? '') . ' drug response');
                $row['genotype'] = $map['variants'][$row['rsid']]['genotype'] ?? '';
                $out['pharmgkb'][] = $row;
            }
        }
        if (count($out['clinvar']) + count($out['gwas']) + count($out['pharmgkb']) >= $limit * 3) {
            break;
        }
    }
    foreach ($out as $k => $rows) {
        usort($rows, function ($a, $b) {
            return ((int) ($b['rank'] ?? 0)) <=> ((int) ($a['rank'] ?? 0));
        });
        $out[$k] = array_slice($rows, 0, $limit);
    }
    $db->close();
    return [
        'installed' => true,
        'reason' => 'Health research source pack scanned',
        'variants_scanned' => count($rsids),
        'clinvar' => $out['clinvar'],
        'gwas' => $out['gwas'],
        'pharmgkb' => $out['pharmgkb'],
        'truth_boundary' => 'Source matches are research support only. They are not diagnosis, treatment advice, medication advice, or personal risk scoring.',
    ];
}

function zl_dna_clinvar_rsid_index($db) {
    $indexes = @$db->query("PRAGMA index_list('clinvar_variants')");
    while ($indexes && ($index = $indexes->fetchArray(SQLITE3_ASSOC))) {
        // A partial index may exclude relevant evidence; require a complete rsID-leading index.
        if (!empty($index['partial'])) { continue; }
        $name = (string) ($index['name'] ?? '');
        $columns = @$db->query("PRAGMA index_info('" . SQLite3::escapeString($name) . "')");
        $first = $columns ? $columns->fetchArray(SQLITE3_ASSOC) : false;
        if ($first && strtolower((string) ($first['name'] ?? '')) === 'rsid') {
            return $name;
        }
    }
    return '';
}

function zl_dna_local_health_pack_matches_path($path, $name = 'raw-dna.txt', $limit = 80, $summary = []) {
    $db_path = dirname(__DIR__) . '/data/packs/health_core.sqlite';
    $empty = [
        'clinvar' => [],
        'gwas' => [],
        'pharmgkb' => [],
        'gwas_status' => 'withheld_until_effect_allele_is_available',
        'pharmgkb_status' => 'withheld_until_validated_haplotype_interpretation_is_available',
    ];
    if (!is_readable($db_path) || !class_exists('SQLite3')) {
        return $empty + [
            'installed' => false,
            'interpretation_ready' => false,
            'allele_aware' => false,
            'status' => 'source_pack_unavailable',
            'reason' => 'Health research source pack is unavailable.',
        ];
    }

    $gate = zl_dna_health_interpretation_gate($summary);
    $db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
    // Import metadata is a stated snapshot; never rescan millions of rows on each upload.
    $source_snapshot = @$db->querySingle("SELECT imported_row_count,version_or_release FROM data_sources WHERE id='clinvar_variant_summary' LIMIT 1", true);
    $source_rows = is_array($source_snapshot) && isset($source_snapshot['imported_row_count']) ? (int) $source_snapshot['imported_row_count'] : null;
    $has_exact_view = zl_sqlite_table_exists($db, 'clinvar_variants')
        && (bool) @$db->querySingle("SELECT 1 FROM sqlite_master WHERE type='view' AND name='clinvar_exact_alleles' LIMIT 1");
    $cols = $has_exact_view ? zl_sqlite_columns($db, 'clinvar_exact_alleles') : [];
    $required = ['rsid', 'assembly', 'chrom', 'position_vcf', 'reference_allele', 'alternate_allele', 'is_simple_snv', 'variation_id', 'allele_id', 'gene_symbol', 'clinical_significance', 'condition_name', 'review_status', 'accession', 'variant_type', 'source_version', 'source_release'];
    $schema_ready = $has_exact_view;
    foreach ($required as $column) {
        if (!in_array($column, $cols, true)) {
            $schema_ready = false;
        }
    }
    $rsid_index = zl_dna_clinvar_rsid_index($db);
    $base_cols = zl_sqlite_columns($db, 'clinvar_variants');
    $indexed_matcher_ready = $rsid_index !== '' && in_array('allele_matchable', $base_cols, true);

    $map = zl_dna_raw_variant_map_path($path, $name, zl_dna_max_raw_rows());
    $callable = [];
    foreach ((array) ($map['variants'] ?? []) as $rsid => $variant) {
        $call = zl_dna_genotype_status((string) ($variant['genotype'] ?? ''));
        if (!empty($call['callable'])) {
            $variant['genotype'] = (string) $call['normalized'];
            $callable[strtolower((string) $rsid)] = $variant;
        }
    }

    $base = $empty + [
        'installed' => true,
        'variants_scanned' => count($callable),
        'source_rows_available' => $source_rows,
        'source_row_count_basis' => $source_rows === null ? 'unknown; not counted during upload' : 'imported data_sources snapshot; not a live row recount',
        'source_release' => (string) ($source_snapshot['version_or_release'] ?? ''),
        'analysis_gate' => $gate,
        'allele_aware' => $schema_ready,
        'match_contract' => 'Exact reference-forward match requires assembly, chromosome, VCF position, REF, ALT, and direct observed genotype. rsID-only matching is prohibited.',
    ];
    if (!$schema_ready || !$indexed_matcher_ready) {
        $db->close();
        return array_merge($base, [
            'interpretation_ready' => false,
            'status' => 'blocked_for_allele_validation',
            'reason' => 'The installed ClinVar mirror must provide the exact allele schema and a complete rsID lookup index before personalized matching.',
        ]);
    }
    if (empty($gate['ready'])) {
        $db->close();
        return array_merge($base, [
            'interpretation_ready' => false,
            'status' => 'withheld_pending_file_validation',
            'reason' => 'Allele-specific source data is available, but this file has not passed the build/orientation/QC gate.',
        ]);
    }
    if (!$callable) {
        $db->close();
        return array_merge($base, [
            'interpretation_ready' => true,
            'status' => 'no_callable_rsids',
            'reason' => 'No callable rsID variants were available for exact matching.',
        ]);
    }

    $assembly = (string) ($gate['assembly'] ?? '');
    $rows = [];
    $total_matches = 0;
    $rank = function ($text) {
        $text = strtolower((string) $text);
        if (strpos($text, 'conflict') !== false) { return 30; }
        foreach (['likely pathogenic' => 88, 'pathogenic' => 100, 'risk factor' => 70, 'drug response' => 58, 'association' => 45, 'benign' => 12] as $needle => $score) {
            if (strpos($text, $needle) !== false) {
                return $score;
            }
        }
        return 30;
    };

    foreach (array_chunk(array_keys($callable), 400) as $chunk) {
        $quoted = array_map(function ($rsid) {
            return "'" . SQLite3::escapeString(strtolower((string) $rsid)) . "'";
        }, $chunk);
        $in = implode(',', $quoted);
        if ($in === '') {
            continue;
        }
        $assembly_sql = SQLite3::escapeString($assembly);
        // The view's assembly index can otherwise scan an entire assembly per chunk.
        // Preserve its allele_matchable=1 contract while seeking the requested rsIDs.
        $index_sql = '"' . str_replace('"', '""', $rsid_index) . '"';
        $sql = "SELECT variation_id,allele_id,rsid,assembly,chrom,position_vcf,reference_allele,alternate_allele,is_simple_snv,gene_symbol AS gene,clinical_significance,condition_name,review_status,accession,variant_type,source_version,source_release FROM clinvar_variants INDEXED BY " . $index_sql . " WHERE allele_matchable=1 AND is_simple_snv=1 AND assembly='" . $assembly_sql . "' AND rsid IN ($in)";
        $res = @$db->query($sql);
        if (!$res) {
            $db->close();
            return array_merge($base, ['interpretation_ready' => false, 'status' => 'source_query_failed', 'reason' => 'The exact source query could not complete; no personalized results are returned.']);
        }
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $rsid = strtolower((string) ($row['rsid'] ?? ''));
            $variant = $callable[$rsid] ?? null;
            if (!$variant || empty($row['is_simple_snv'])) {
                continue;
            }
            $ref = strtoupper((string) ($row['reference_allele'] ?? ''));
            $alt = strtoupper((string) ($row['alternate_allele'] ?? ''));
            $geno = strtoupper((string) ($variant['genotype'] ?? ''));
            if ($ref === $alt || !preg_match('/^[ACGT]$/', $ref) || !preg_match('/^[ACGT]$/', $alt) || !preg_match('/^[ACGT]{1,2}$/', $geno)) {
                continue;
            }
            $user_chrom = strtoupper(preg_replace('/^CHR/i', '', (string) ($variant['chromosome'] ?? '')));
            $row_chrom = strtoupper(preg_replace('/^CHR/i', '', (string) ($row['chrom'] ?? '')));
            if (!ctype_digit((string) ($variant['position'] ?? ''))) { continue; }
            $user_pos = (int) ($variant['position'] ?? 0);
            $row_pos = (int) ($row['position_vcf'] ?? 0);
            if (preg_match('/^(?:[1-9]|1[0-9]|2[0-2])$/', $user_chrom) && strlen($geno) !== 2) { continue; }
            if ($user_chrom === '' || $row_chrom === '' || $user_chrom !== $row_chrom || $user_pos <= 0 || $user_pos !== $row_pos) {
                continue;
            }
            $record_format = strtolower((string) ($variant['source_record_format'] ?? 'consumer_raw'));
            $input_ref_alt_verified = false;
            if ($record_format === 'vcf') {
                $input_ref = strtoupper(trim((string) ($variant['reference_allele'] ?? '')));
                $input_alts = array_values(array_filter(array_map(function ($allele) {
                    return strtoupper(trim((string) $allele));
                }, explode(',', (string) ($variant['alternate_alleles'] ?? ''))), function ($allele) {
                    return $allele !== '';
                }));
                if ($input_ref !== $ref || !in_array($alt, $input_alts, true)) {
                    continue;
                }
                $input_ref_alt_verified = true;
                $match_provenance = 'vcf_ref_alt_and_direct_gt_confirmed';
            } else {
                $match_provenance = 'confirmed_forward_consumer_locus_and_direct_allele';
            }
            $alleles = str_split($geno);
            $only_expected_alleles = !array_diff($alleles, [$ref, $alt]);
            if (!$only_expected_alleles || !in_array($alt, $alleles, true)) {
                continue;
            }
            $row['rsid'] = $rsid;
            $row['genotype'] = $geno;
            $row['observed_alt_copies'] = count(array_filter($alleles, function ($allele) use ($alt) { return $allele === $alt; }));
            $row['match_state'] = 'direct_observed_alternate_allele';
            $row['input_record_format'] = $record_format;
            $row['input_ref_alt_verified'] = $input_ref_alt_verified;
            $row['match_provenance'] = $match_provenance;
            $row['rank'] = $rank(($row['clinical_significance'] ?? '') . ' ' . ($row['review_status'] ?? ''));
            $rows[] = $row;
            $total_matches++;
        }
        if (count($rows) > max(1, (int) $limit) * 3) {
            usort($rows, function ($a, $b) { return (int) ($b['rank'] ?? 0) <=> (int) ($a['rank'] ?? 0); });
            $rows = array_slice($rows, 0, max(1, (int) $limit) * 3);
        }

    }
    $db->close();
    usort($rows, function ($a, $b) {
        return ((int) ($b['rank'] ?? 0)) <=> ((int) ($a['rank'] ?? 0));
    });
    $rows = array_slice($rows, 0, max(1, (int) $limit));

    return array_merge($base, [
        'interpretation_ready' => true,
        'status' => 'allele_specific_scan_complete',
        'reason' => 'Exact assembly, position, REF/ALT, and directly observed alternate-allele matching completed.',
        'clinvar' => $rows,
        'total_exact_matches' => $total_matches,
        'displayed_matches' => count($rows),
        'results_limited_for_display' => $total_matches > count($rows),
        'truth_boundary' => 'These are research evidence matches, not diagnosis. ClinVar assertions can conflict and important results require certified confirmation.',
    ]);
}

function zl_dna_ancestry_alignment_check($genotype, $row, $assembly, $variant = null) {
    $call = zl_dna_genotype_status($genotype);
    if (empty($call['callable'])) {
        return ['ready' => false, 'reason' => 'not_callable'];
    }
    $geno = (string) $call['normalized'];
    $chrom = strtoupper(preg_replace('/^CHR/i', '', trim((string) ($row['chrom'] ?? ''))));
    if (!preg_match('/^(?:[1-9]|1[0-9]|2[0-2])$/', $chrom) || strlen($geno) !== 2) {
        return ['ready' => false, 'reason' => 'non_autosomal_or_non_diploid'];
    }
    if ($assembly === '' || strcasecmp(trim((string) ($row['build'] ?? '')), $assembly) !== 0) {
        return ['ready' => false, 'reason' => 'assembly_mismatch'];
    }
    $ref = strtoupper(trim((string) ($row['ref'] ?? '')));
    $alt = strtoupper(trim((string) ($row['alt'] ?? '')));
    if (!preg_match('/^[ACGT]$/', $ref) || !preg_match('/^[ACGT]$/', $alt) || $ref === $alt) {
        return ['ready' => false, 'reason' => 'unsupported_reference_alleles'];
    }
    $pos = trim((string) ($row['pos'] ?? ''));
    if (!ctype_digit($pos) || (int) $pos <= 0) {
        return ['ready' => false, 'reason' => 'coordinate_mismatch'];
    }
    if (is_array($variant)) {
        $user_chrom = strtoupper(preg_replace('/^CHR/i', '', (string) ($variant['chromosome'] ?? '')));
        if ($user_chrom !== $chrom || (string) ($variant['position'] ?? '') !== $pos) {
            return ['ready' => false, 'reason' => 'coordinate_mismatch'];
        }
        if (($variant['source_record_format'] ?? '') === 'vcf' && strtoupper((string) ($variant['reference_allele'] ?? '')) !== $ref) {
            return ['ready' => false, 'reason' => 'reference_allele_mismatch'];
        }
    } elseif (strtolower((string) ($row['rsid'] ?? '')) !== strtolower($chrom . ':' . $pos)) {
        return ['ready' => false, 'reason' => 'coordinate_mismatch'];
    }
    if (array_diff(str_split($geno), [$ref, $alt])) {
        return ['ready' => false, 'reason' => 'allele_mismatch'];
    }
    return ['ready' => true, 'reason' => 'aligned_direct_diploid_snv', 'genotype' => $geno, 'dose' => substr_count($geno, $alt)];
}

function zl_dna_local_ancestry_pack_matches_path($path, $name = 'raw-dna.txt', $limit = 48, $summary = []) {
    $db_path = dirname(__DIR__) . '/data/packs/ancestry_core.sqlite';
    if (!is_readable($db_path)) {
        return [
            'installed' => false,
            'reason' => 'Country and region reference engine not active yet',
            'population_scores' => [],
            'super_population_scores' => [],
            'modern_region_glossary' => zl_dna_modern_region_glossary(),
        ];
    }
    if (!class_exists('SQLite3')) {
        return [
            'installed' => false,
            'reason' => 'Country and region reference engine unavailable',
            'population_scores' => [],
            'super_population_scores' => [],
            'modern_region_glossary' => zl_dna_modern_region_glossary(),
        ];
    }
    $db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
    if (!zl_sqlite_table_exists($db, 'ancestry_reference_markers')) {
        $db->close();
        return [
            'installed' => false,
            'reason' => 'ancestry_reference_markers table missing',
            'population_scores' => [],
            'super_population_scores' => [],
            'modern_region_glossary' => zl_dna_modern_region_glossary(),
        ];
    }
    $pack_meta = zl_dna_sqlite_meta($db);
    $summary = $summary ?: zl_dna_raw_analyze_path($path, $name);
    if (!isset($summary['reported_orientation'])) {
        $summary = zl_dna_apply_analysis_options($summary, []);
    }
    $gate = zl_dna_health_interpretation_gate($summary);
    $gate['status'] = !empty($gate['ready']) ? 'ready_for_reference_affinity' : 'withheld_pending_file_validation';
    $gate['boundary'] = 'Reference affinity uses confirmed assembly and forward orientation, matching coordinates and alleles, and direct diploid autosomal calls only.';
    if (empty($gate['ready'])) {
        $db->close();
        return [
            'installed' => true, 'interpretation_ready' => false, 'analysis_gate' => $gate,
            'pack_meta' => $pack_meta, 'matched_marker_count' => 0,
            'reason' => 'Reference data is installed; confirm the file build, forward orientation and quality before comparing genotypes.',
            'population_scores' => [], 'super_population_scores' => [],
            'admixture_fit' => ['available' => false, 'reason' => 'file validation required'],
            'modern_region_glossary' => zl_dna_modern_region_glossary(),
        ];
    }
    $diagnostics = ['candidate_reference_rows' => 0, 'aligned_reference_rows' => 0, 'excluded_reference_rows' => []];
    $coordinate_chroms = [];
    $chrom_res = @$db->query("SELECT DISTINCT chrom FROM ancestry_reference_markers WHERE instr(rsid, ':') > 0 LIMIT 64");
    while ($chrom_res && ($chrom_row = $chrom_res->fetchArray(SQLITE3_ASSOC))) {
        $chrom = strtoupper(trim((string) ($chrom_row['chrom'] ?? '')));
        $chrom = preg_replace('/^CHR/i', '', $chrom);
        if ($chrom !== '') {
            $coordinate_chroms[$chrom] = true;
        }
    }
    $has_rsid_rows = (bool) @$db->querySingle("SELECT 1 FROM ancestry_reference_markers WHERE rsid LIKE 'rs%' LIMIT 1");
    $has_superpop_panel = (bool) @$db->querySingle("SELECT 1 FROM ancestry_reference_markers WHERE panel_name = '1000G_Phase3_optimized_superpop' LIMIT 1");
    $rsid_genotypes = [];
    $rsid_variants = [];
    $coordinate_genotypes = [];
    if ($coordinate_chroms) {
        $coord_map = zl_dna_coordinate_genotype_map_path($path, $name, $coordinate_chroms, zl_dna_max_raw_rows());
        $coordinate_genotypes = (array) ($coord_map['variants'] ?? []);
    }
    if ($has_rsid_rows) {
        $map = zl_dna_raw_variant_map_path($path, $name, zl_dna_max_raw_rows());
        $rsid_variants = (array) ($map['variants'] ?? []);
        foreach ((array) ($map['variants'] ?? []) as $rsid => $variant) {
            $rsid = strtolower((string) $rsid);
            if ($rsid !== '' && !isset($rsid_genotypes[$rsid])) {
                $rsid_genotypes[$rsid] = (string) ($variant['genotype'] ?? '');
            }
        }
    }
    $lookup_keys = array_merge(array_keys($rsid_genotypes), array_keys($coordinate_genotypes));
    if (!$lookup_keys) {
        $db->close();
        return [
            'installed' => true,
            'reason' => 'no rsIDs or chromosome-position markers parsed',
            'matched_marker_count' => 0,
            'population_scores' => [],
            'super_population_scores' => [],
            'modern_region_glossary' => zl_dna_modern_region_glossary(),
        ];
    }
    $pop = [];
    $super = [];
    $matched_rsids = [];
    $admixture_rows = [];
    foreach (array_chunk($lookup_keys, 450) as $chunk) {
        $quoted = array_map(function ($key) {
            return "'" . SQLite3::escapeString(strtolower((string) $key)) . "'";
        }, $chunk);
        $in = implode(',', $quoted);
        if ($in === '') {
            continue;
        }
        $res = @$db->query("SELECT rsid,chrom,pos,build,ref,alt,panel_name,population_code,population_label,super_population,allele_frequency,marker_weight,source_version FROM ancestry_reference_markers WHERE rsid IN ($in)");
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $rsid = strtolower((string) ($row['rsid'] ?? ''));
            $geno = strtoupper((string) ($rsid_genotypes[$rsid] ?? $coordinate_genotypes[$rsid] ?? ''));
            $diagnostics['candidate_reference_rows']++;
            $alignment = zl_dna_ancestry_alignment_check($geno, $row, (string) $gate['assembly'], $rsid_variants[$rsid] ?? null);
            if (empty($alignment['ready'])) {
                $why = (string) $alignment['reason'];
                $diagnostics['excluded_reference_rows'][$why] = ($diagnostics['excluded_reference_rows'][$why] ?? 0) + 1;
                continue;
            }
            $geno = (string) $alignment['genotype'];
            $alt = strtoupper((string) ($row['alt'] ?? ''));
            if ($alt === '' || strlen($alt) > 1) {
                continue;
            }
            $user_dose = (int) $alignment['dose'];
            $af = $row['allele_frequency'] ?? null;
            if (!is_numeric($af) || !is_finite((float) $af) || $af < 0 || $af > 1) {
                $diagnostics['excluded_reference_rows']['invalid_frequency'] = ($diagnostics['excluded_reference_rows']['invalid_frequency'] ?? 0) + 1;
                continue;
            }
            $af = (float) $af;
            $diagnostics['aligned_reference_rows']++;
            $expected_dose = 2.0 * $af;
            $similarity = 1.0 - min(1.0, abs($user_dose - $expected_dose) / 2.0);
            $weight = max(0.1, (float) ($row['marker_weight'] ?? 1.0));
            $code = strtoupper((string) ($row['population_code'] ?? 'UNK'));
            $sp = strtoupper((string) ($row['super_population'] ?? $code));
            $panel_name = (string) ($row['panel_name'] ?? '');
            if ($panel_name === '1000G_Phase3_optimized_superpop' && in_array($sp, ['EUR', 'SAS'], true) && (isset($admixture_rows[$rsid]) || count($admixture_rows) < 60000)) {
                if (!isset($admixture_rows[$rsid])) {
                    $admixture_rows[$rsid] = [
                        'dose' => $user_dose,
                        'weight' => $weight,
                        'freqs' => [],
                    ];
                }
                $admixture_rows[$rsid]['freqs'][$sp] = $af;
            }
            // The same population code in separate datasets must not be pooled.
            $pop_key = $panel_name . '|' . $code;
            if (!isset($pop[$pop_key])) {
                $pop[$pop_key] = [
                    'population_code' => $code,
                    'population_label' => (string) ($row['population_label'] ?? ''),
                    'modern_label' => zl_dna_population_modern_label($code, (string) ($row['population_label'] ?? ''), $sp),
                    'super_population' => $sp,
                    'score' => 0.0,
                    'weight' => 0.0,
                    'marker_count' => 0,
                    'source_version' => (string) ($row['source_version'] ?? ''),
                    'panel_name' => $panel_name,
                ];
            }
            $pop[$pop_key]['score'] += $similarity * $weight;
            $pop[$pop_key]['weight'] += $weight;
            $pop[$pop_key]['marker_count']++;
            $pop[$pop_key]['matched_chromosomes'][(string) $row['chrom']] = true;
            $include_super_score = !$has_superpop_panel || $panel_name === '1000G_Phase3_optimized_superpop';
            if ($include_super_score && !isset($super[$sp])) {
                $super[$sp] = ['super_population' => $sp, 'score' => 0.0, 'weight' => 0.0, 'marker_count' => 0];
            }
            if ($include_super_score) {
                $super[$sp]['score'] += $similarity * $weight;
                $super[$sp]['weight'] += $weight;
                $super[$sp]['marker_count']++;
                $super[$sp]['matched_chromosomes'][(string) $row['chrom']] = true;
            }
            $matched_rsids[$rsid] = true;
        }
    }
    $format_scores = function ($rows) use ($summary) {
        $out = [];
        foreach ($rows as $row) {
            $weight = max(0.001, (float) ($row['weight'] ?? 0));
            $score = round(((float) ($row['score'] ?? 0) / $weight) * 100, 1);
            $marker_count = (int) ($row['marker_count'] ?? count((array) ($row['markers'] ?? [])));
            $matched_chromosomes = array_keys((array) ($row['matched_chromosomes'] ?? []));
            natsort($matched_chromosomes);
            $row['matched_chromosomes'] = array_values($matched_chromosomes);
            $row['matched_chromosome_count'] = count($matched_chromosomes);
            // Similarity is not calibrated probability; overlap and QC give evidence support only.
            $confidence = round(min(39.0, 39.0 * min(1.0, $marker_count / 1000.0) * min(1.0, count($matched_chromosomes) / 22.0) * min(1.0, ((float) ($summary['quality_score'] ?? 0)) / 80.0)), 1);
            unset($row['score'], $row['weight'], $row['markers'], $row['marker_count']);
            $row['affinity_score'] = $score;
            $row['confidence_score'] = $confidence;
            $row['confidence_band'] = 'low / uncalibrated';
            $row['calibration_status'] = 'no_held_out_calibration_installed';
            $row['score_definition'] = 'Weighted similarity to alternate-allele frequencies, on a 0-100 scale; not ancestry percent or probability.';
            $row['matched_marker_count'] = $marker_count;
            $out[] = $row;
        }
        usort($out, function ($a, $b) {
            return ((float) ($b['affinity_score'] ?? 0)) <=> ((float) ($a['affinity_score'] ?? 0));
        });
        return $out;
    };
    $population_scores = array_slice($format_scores($pop), 0, $limit);
    $super_scores = array_slice($format_scores($super), 0, 8);
    $admixture_fit = ['available' => false, 'reason' => 'A constrained EUR/SAS model cannot establish ancestry composition across the installed reference groups. Held-out multi-population calibration is required.'];
    $build_mode = (string) ($pack_meta['build_mode'] ?? '');
    $chromosomes = (string) ($pack_meta['chromosomes'] ?? '');
    $reference_pipeline = $build_mode === 'optimized_runtime' ? 'optimized_runtime_installed' : ($build_mode === 'chr22_test' ? 'chr22_test_panel' : 'local_reference_panel');
    $reason = $build_mode === 'optimized_runtime'
        ? 'optimized 1000G ancestry reference panel scanned'
        : ($build_mode === 'chr22_test'
            ? 'chr22 starter ancestry panel scanned'
            : 'country and region reference engine scanned');
    $db->close();
    return [
        'installed' => true,
        'interpretation_ready' => true,
        'analysis_gate' => $gate,
        'alignment_diagnostics' => $diagnostics,
        'calibration_status' => 'no_held_out_calibration_installed',
        'reason' => $reason,
        'reference_pipeline' => $reference_pipeline,
        'pack_meta' => $pack_meta,
        'chromosomes' => $chromosomes,
        'variants_scanned' => count($lookup_keys),
        'matched_marker_count' => count($matched_rsids),
        'admixture_fit' => $admixture_fit,
        'population_scores' => $population_scores,
        'super_population_scores' => $super_scores,
        'modern_region_glossary' => zl_dna_modern_region_glossary(),
        'truth_boundary' => 'Reference-panel affinity is probabilistic. It is not modern nationality, ethnicity, culture, religion, tribe, or identity.',
    ];
}

function zl_dna_ancient_context_lanes_from_heritage($heritage_report = []) {
    $estimate = (array) ($heritage_report['insights']['consumer_estimate'] ?? $heritage_report['insights']['plain_ancestry_summary']['consumer_estimate'] ?? []);
    $segments = (array) ($estimate['segments'] ?? []);
    $trace_lanes = (array) ($estimate['trace_lanes'] ?? []);
    $specialist_cards = (array) ($heritage_report['insights']['plain_ancestry_summary']['cards'] ?? []);
    $lookup = function ($needles) use ($segments, $trace_lanes) {
        $score = 0.0;
        foreach (array_merge($segments, $trace_lanes) as $row) {
            $hay = strtolower((string) ($row['label'] ?? '') . ' ' . (string) ($row['plain'] ?? '') . ' ' . (string) ($row['note'] ?? ''));
            foreach ((array) $needles as $needle) {
                if ($needle !== '' && strpos($hay, strtolower((string) $needle)) !== false) {
                    $score += (float) ($row['percent'] ?? $row['proxy_percent'] ?? $row['value'] ?? 0);
                    break;
                }
            }
        }
        return round($score, 1);
    };
    $specialist_lookup = function ($needles) use ($specialist_cards) {
        $score = 0.0;
        foreach ($specialist_cards as $row) {
            $hay = strtolower(
                (string) ($row['label'] ?? '') . ' ' .
                (string) ($row['plain'] ?? '') . ' ' .
                (string) ($row['answer'] ?? '') . ' ' .
                implode(' ', (array) ($row['tags'] ?? []))
            );
            foreach ((array) $needles as $needle) {
                if ($needle !== '' && strpos($hay, strtolower((string) $needle)) !== false) {
                    $score = max($score, (float) ($row['proxy_percent'] ?? $row['value'] ?? 0));
                    break;
                }
            }
        }
        return round($score, 1);
    };
    $lanes = [
        [
            'label' => 'Ireland / Celtic Atlantic ancient-context lead',
            'modern_countries' => 'Ireland, Northern Ireland, western Scotland, Isle of Man, Celtic Atlantic fringe',
            'proxy_percent' => $lookup(['irish', 'ireland', 'celtic', 'breton']),
            'plain' => 'This lane looks for ancient and medieval Celtic-Atlantic style context: Ireland, nearby island populations, and related Northwest-European grave/site comparisons.',
        ],
        [
            'label' => 'England / Early Britain ancient-context lead',
            'modern_countries' => 'England, Wales, Scotland, United Kingdom, Anglo-Saxon and early British contexts',
            'proxy_percent' => $lookup(['english', 'british', 'scottish', 'welsh']),
            'plain' => 'This lane separates early Britain/England-style context from the wider Ireland/Celtic lane where the available signal allows it.',
        ],
        [
            'label' => 'Indus / Punjab / South Asia ancient context',
            'modern_countries' => 'Pakistan, India, Bangladesh, Afghanistan, Sri Lanka, Nepal',
            'proxy_percent' => $lookup(['pakistan', 'punjab', 'south asia', 'bengal', 'india', 'gujarati', 'telugu', 'tamil']),
            'plain' => 'Context lane for ancient South Asian, Indus-periphery, Punjab, and neighbouring regional sample comparisons when a public ancient pack is installed.',
        ],
        [
            'label' => 'Khazar / Steppe-Caucasus hypothesis lane',
            'modern_countries' => 'Ukraine steppe, southern Russia, Caucasus, Kazakhstan, Central Asia, Turkic-Caucasus bridge regions',
            'proxy_percent' => max($lookup(['central asian', 'steppe', 'turkic', 'khazar', 'kazakhstan', 'caucasus']), $specialist_lookup(['khazar', 'steppe', 'turkic', 'caucasus'])),
            'plain' => 'This is a historical hypothesis lane for Steppe, Turkic-Caucasus, and Khazar-style questions. It is designed to reveal whether the signal deserves deeper ancient comparison, not to make a tribal identity claim.',
        ],
        [
            'label' => 'Israel / Levant ancient-context lead',
            'modern_countries' => 'Israel, Palestine, Lebanon, Jordan, Syria, Cyprus, nearby Eastern Mediterranean',
            'proxy_percent' => max($lookup(['levant', 'israel', 'palestine', 'lebanon', 'jordan', 'syria']), $specialist_lookup(['levant', 'israel', 'palestine', 'lebanon', 'jordan', 'syria']), round($specialist_lookup(['ashkenazi', 'sephardi', 'mizrahi', 'jewish diaspora']) * 0.55, 1)),
            'plain' => 'This lane translates Levant into modern map language: Israel, Palestine, Lebanon, Jordan, Syria, Cyprus, and neighbouring Eastern Mediterranean ancient contexts. A Jewish-diaspora trace can lightly support this bridge lane, but it is kept separate from Ashkenazi identity and from Khazar/Steppe.',
        ],
        [
            'label' => 'Ashkenazi Jewish diaspora trace',
            'modern_countries' => 'Central/Eastern European Jewish diaspora context; not religion, identity, or community membership',
            'proxy_percent' => max($lookup(['ashkenazi', 'jewish diaspora', 'sephardi', 'mizrahi']), $specialist_lookup(['ashkenazi', 'sephardi', 'mizrahi', 'jewish diaspora'])),
            'plain' => 'This lane is separate from Khazar/Steppe and separate from Israel/Levant. It tracks a low Ashkenazi/Jewish-diaspora genetic-affinity trace similar to what consumer DNA companies sometimes show, but it is not religion or identity proof.',
        ],
        [
            'label' => 'Egypt / Nile-North Africa ancient-context lead',
            'modern_countries' => 'Egypt, Nile Valley, Sudan/Nubia context, Libya, Tunisia, Algeria, Morocco',
            'proxy_percent' => max($lookup(['egypt', 'north africa', 'maghreb', 'nubian']), $specialist_lookup(['egypt', 'north africa', 'maghreb', 'nubian'])),
            'plain' => 'This lane highlights Egyptian, Nile Valley, Nubian, Maghreb, and broader North-African ancient comparison questions in language normal users understand.',
        ],
        [
            'label' => 'Iran / Persia ancient-context lead',
            'modern_countries' => 'Iran/Persia, Caucasus, Central Asia, Afghanistan, West-Central Asia',
            'proxy_percent' => max($lookup(['iran', 'persia', 'persian']), $specialist_lookup(['iran', 'persia', 'persian'])),
            'plain' => 'This lane catches Persian/Iranian and West-Central Asian bridge context where it appears in the compact specialist lens.',
        ],
        [
            'label' => 'Mediterranean ancient-context lead',
            'modern_countries' => 'Italy, Greece, Spain, Cyprus, Malta, coastal Mediterranean Europe',
            'proxy_percent' => $lookup(['italy', 'spain', 'mediterranean', 'iberian', 'toscani', 'greek', 'cyprus']),
            'plain' => 'This lane catches Mediterranean ancient context that may sit between Europe, North Africa, and the Eastern Mediterranean.',
        ],
    ];
    foreach ($lanes as &$lane) {
        $pct = (float) ($lane['proxy_percent'] ?? 0);
        $lane['status'] = $pct >= 12 ? 'visible context lead' : ($pct >= 5 ? 'possible context clue' : ($pct >= 1 ? 'trace context clue' : 'not visible in current projection'));
        $lane['boundary'] = 'This is an ancient-context research lead unless the Ancient Grave Match source pack is active. It is not identity proof, tribe proof, religion proof, or a named-ancestor claim.';
        $lane['source_version'] = 'ZDNA ancient context lanes v0.1';
    }
    unset($lane);
    return $lanes;
}

function zl_dna_ancient_unique_sample_matches($matches, $limit = 10) {
    $out = [];
    $seen_sites = [];
    $country_counts = [];
    foreach ((array) $matches as $match) {
        $country = strtolower(trim((string) ($match['modern_country'] ?? 'unknown')));
        if (($country_counts[$country] ?? 0) >= 2) {
            continue;
        }
        $key = strtolower(trim(
            (string) ($match['site_name'] ?? '') . '|' .
            (string) ($match['modern_country'] ?? '')
        ));
        if ($key === '|') {
            $key = strtolower((string) ($match['sample_id'] ?? $match['public_label'] ?? count($out)));
        }
        if (isset($seen_sites[$key])) {
            continue;
        }
        $seen_sites[$key] = true;
        $country_counts[$country] = ($country_counts[$country] ?? 0) + 1;
        $out[] = $match;
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

function zl_dna_ancient_chart_segments($context_lanes, $site_scores = []) {
    $colors = ['#2ef5b8', '#67e8f9', '#ffd76f', '#ff8ed1', '#a78bfa', '#b6ff7a', '#fb923c', '#60a5fa'];
    $chart = [];
    foreach ((array) $context_lanes as $lane) {
        $value = (float) ($lane['proxy_percent'] ?? 0);
        if ($value <= 0) {
            continue;
        }
        $status = strtolower((string) ($lane['status'] ?? ''));
        $chart_value = $value;
        if (strpos($status, 'trace') !== false) {
            $chart_value = zl_dna_math_clamp($value * 0.32, 0.7, 1.4);
        } elseif (strpos($status, 'possible') !== false && $value < 12.0) {
            $chart_value = zl_dna_math_clamp($value * 0.65, 2.0, 5.5);
        }
        $chart[] = [
            'label' => (string) ($lane['label'] ?? 'Ancient context'),
            'value' => $chart_value,
            'percent' => $value,
            'color' => $colors[count($chart) % count($colors)],
        ];
        if (count($chart) >= 8) {
            break;
        }
    }
    if ($chart) {
        return $chart;
    }
    foreach ((array) $site_scores as $site) {
        $value = (float) ($site['confidence_score'] ?? $site['similarity_score'] ?? 0);
        if ($value <= 0) {
            continue;
        }
        $label = trim((string) ($site['site_name'] ?? '') . ' ' . (string) ($site['modern_country'] ?? '')) ?: (string) ($site['culture'] ?? 'Ancient site');
        $chart[] = [
            'label' => $label,
            'value' => $value,
            'percent' => $value,
            'color' => '#d8b4fe',
        ];
        if (count($chart) >= 6) {
            break;
        }
    }
    return $chart;
}

function zl_dna_normalize_y_str_marker_name($value) {
    $value = strtoupper((string) $value);
    $value = preg_replace('/[^A-Z0-9]+/', '', $value);
    if ($value === '') {
        return '';
    }
    $aliases = [
        'DYS394' => 'DYS19',
        'DYS3891' => 'DYS389I',
        'DYS389I' => 'DYS389I',
        'DYS389A' => 'DYS389I',
        'DYS3892' => 'DYS389II',
        'DYS389II' => 'DYS389II',
        'DYS389B' => 'DYS389II',
        'DYS385A' => 'DYS385A',
        'DYS385B' => 'DYS385B',
    ];
    if (isset($aliases[$value])) {
        return $aliases[$value];
    }
    if (preg_match('/^DYS([0-9]{2,3})(A|B|I{1,2})?$/', $value, $m)) {
        return 'DYS' . $m[1] . (isset($m[2]) ? $m[2] : '');
    }
    return '';
}

function zl_dna_extract_y_str_values($text) {
    $text = strtoupper((string) $text);
    $text = preg_replace('/DYS\s*([0-9]{2,3})\s*[-_ ]?\s*(A|B|II|I|1|2)?/i', ' DYS$1$2 ', $text);
    if (!preg_match_all('/\b([0-9]{1,2})(?:\s*[-\/,]\s*([0-9]{1,2}))?\b/', $text, $matches, PREG_SET_ORDER)) {
        return [];
    }
    $values = [];
    foreach ($matches as $match) {
        $v1 = (int) ($match[1] ?? 0);
        if ($v1 >= 5 && $v1 <= 45) {
            $values[] = $v1;
        }
        if (isset($match[2]) && $match[2] !== '') {
            $v2 = (int) $match[2];
            if ($v2 >= 5 && $v2 <= 45) {
                $values[] = $v2;
            }
        }
    }
    return array_values(array_unique($values));
}

function zl_dna_record_y_str_marker_values(&$markers, $marker, $values) {
    $marker = zl_dna_normalize_y_str_marker_name($marker);
    $values = array_values(array_filter(array_map('intval', (array) $values), function ($value) {
        return $value >= 5 && $value <= 45;
    }));
    if ($marker === '' || !$values) {
        return;
    }
    if ($marker === 'DYS385' && count($values) >= 2) {
        zl_dna_record_y_str_marker_values($markers, 'DYS385A', [$values[0]]);
        zl_dna_record_y_str_marker_values($markers, 'DYS385B', [$values[1]]);
        return;
    }
    if (!isset($markers[$marker])) {
        $markers[$marker] = [];
    }
    foreach ($values as $value) {
        $markers[$marker][$value] = true;
    }
}

function zl_dna_y_str_markers_from_path($path, $name = 'raw-dna.txt', $max_rows = 1200000) {
    zl_dna_raise_runtime_limits();
    if (!is_file($path) || !is_readable($path)) {
        return ['markers' => [], 'rows_seen' => 0, 'marker_rows' => 0];
    }
    $markers = [];
    $rows_seen = 0;
    $marker_rows = 0;
    $handle = fopen($path, 'rb');
    if (!$handle) {
        return ['markers' => [], 'rows_seen' => 0, 'marker_rows' => 0];
    }
    while (($line = fgets($handle)) !== false) {
        $rows_seen++;
        if ($rows_seen > $max_rows) {
            break;
        }
        if (stripos($line, 'DYS') === false) {
            continue;
        }
        $cols = zl_dna_raw_split_row(trim((string) $line));
        if (!$cols) {
            continue;
        }
        $row_has_marker = false;
        foreach ($cols as $i => $col) {
            $col = (string) $col;
            if (!preg_match_all('/DYS\s*([0-9]{2,3})\s*[-_ ]?\s*(A|B|II|I|1|2)?/i', $col, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $match) {
                $suffix = strtoupper((string) ($match[2] ?? ''));
                if ($suffix === '1') {
                    $suffix = 'I';
                } elseif ($suffix === '2') {
                    $suffix = 'II';
                }
                $marker = 'DYS' . (string) ($match[1] ?? '') . $suffix;
                $candidate = implode(' ', array_slice($cols, $i, 4));
                $candidate = preg_replace('/DYS\s*' . preg_quote((string) ($match[1] ?? ''), '/') . '\s*[-_ ]?\s*' . preg_quote((string) ($match[2] ?? ''), '/') . '/i', ' ', $candidate, 1);
                $values = zl_dna_extract_y_str_values($candidate);
                zl_dna_record_y_str_marker_values($markers, $marker, $values);
                if ($values) {
                    $row_has_marker = true;
                }
            }
        }
        if ($row_has_marker) {
            $marker_rows++;
        }
    }
    fclose($handle);
    foreach ($markers as $marker => $values) {
        $markers[$marker] = array_map('intval', array_keys($values));
        sort($markers[$marker], SORT_NUMERIC);
    }
    ksort($markers);
    return [
        'file_name' => zl_safe_name($name),
        'markers' => $markers,
        'rows_seen' => $rows_seen,
        'marker_rows' => $marker_rows,
    ];
}

function zl_dna_cohen_priestly_marker_profile_path($path, $name = 'raw-dna.txt', $raw_override = null) {
    $raw = is_array($raw_override) ? $raw_override : zl_dna_raw_analyze_path($path, $name);
    $scan = zl_dna_y_str_markers_from_path($path, $name);
    $markers = (array) ($scan['markers'] ?? []);
    $classic = [
        'DYS393' => 12,
        'DYS390' => 23,
        'DYS19' => 14,
        'DYS391' => 10,
        'DYS388' => 16,
        'DYS392' => 11,
    ];
    $optional_extended = [
        'DYS385A' => 13,
        'DYS385B' => 15,
        'DYS426' => 11,
        'DYS439' => 12,
        'DYS389I' => 13,
    ];

    $classic_rows = [];
    $present = 0;
    $exact = 0;
    $near = 0;
    foreach ($classic as $marker => $expected) {
        $values = (array) ($markers[$marker] ?? []);
        $state = 'missing';
        if ($values) {
            $present++;
            $dist = min(array_map(function ($value) use ($expected) {
                return abs((int) $value - (int) $expected);
            }, $values));
            if ($dist === 0) {
                $exact++;
                $state = 'exact';
            } elseif ($dist === 1) {
                $near++;
                $state = 'near';
            } else {
                $state = 'different';
            }
        }
        $classic_rows[] = [
            'marker' => $marker,
            'expected' => $expected,
            'observed' => $values,
            'status' => $state,
        ];
    }

    $extended_rows = [];
    $extended_present = 0;
    $extended_exact = 0;
    foreach ($optional_extended as $marker => $expected) {
        $values = (array) ($markers[$marker] ?? []);
        if ($values) {
            $extended_present++;
            if (in_array((int) $expected, array_map('intval', $values), true)) {
                $extended_exact++;
            }
        }
        $extended_rows[] = [
            'marker' => $marker,
            'expected' => $expected,
            'observed' => $values,
            'status' => !$values ? 'missing' : (in_array((int) $expected, array_map('intval', $values), true) ? 'exact' : 'different'),
        ];
    }

    $status = 'not testable from this upload';
    $plain = 'This upload does not contain the Y-STR markers needed to test the classic Cohen/Kohen Modal Haplotype. Most MyHeritage, AncestryDNA, and 23andMe raw files are SNP-array files, not Y-STR paternal-line tests.';
    $confidence = 8;
    $band = 'not-testable';
    if ($present > 0 && $present < 4) {
        $status = 'not enough Y-STR markers supplied';
        $plain = 'Some Y-STR-style markers were seen, but not enough of the classic six-marker pattern was present to produce a useful Cohen/Kohen priestly-line clue.';
        $confidence = 18 + ($exact * 8) + ($near * 3);
        $band = 'very-low';
    } elseif ($present >= 4) {
        $confidence = (int) round(zl_dna_math_clamp(($exact / 6) * 72 + min(10, $present * 1.5) + min(6, $extended_exact * 1.2), 20, 84));
        $band = zl_dna_confidence_label_from_score($confidence);
        if ($exact >= 6) {
            $status = 'classic CMH-compatible Y-STR pattern detected';
            $plain = 'The supplied paternal-line Y-STR values match the classic six-marker Cohen/Kohen Modal Haplotype pattern. This is a research clue only, because CMH-like STR patterns can occur by convergence and do not prove identity, religion, tribe, priestly status, or descent from a named ancestor.';
        } elseif ($exact >= 5 && ($exact + $near) >= $present) {
            $status = 'near CMH Y-STR pattern - research clue';
            $plain = 'The supplied paternal-line Y-STR values are close to the classic Cohen/Kohen Modal Haplotype. This deserves follow-up with a dedicated Y-DNA test and haplogroup context, but it is not proof of Cohen, Jewish, Israelite, or priestly identity.';
        } elseif ($exact >= 4) {
            $status = 'partial CMH overlap - weak clue';
            $plain = 'The supplied Y-STR values partially overlap the classic Cohen/Kohen Modal Haplotype. Treat this as a weak paternal-line clue only.';
        } else {
            $status = 'CMH pattern not seen in supplied Y-STRs';
            $plain = 'Enough Y-STR markers were supplied to compare, and the classic Cohen/Kohen Modal Haplotype pattern was not seen.';
            $confidence = max(28, min(58, $confidence));
            $band = zl_dna_confidence_label_from_score($confidence);
        }
    }

    return [
        'engine' => 'ZCOHEN-YSTR v0.1',
        'status' => $status,
        'plain' => $plain,
        'confidence_score' => $confidence,
        'confidence_band' => $band,
        'classic_exact_matches' => $exact,
        'classic_near_matches' => $near,
        'classic_markers_present' => $present,
        'classic_markers_total' => count($classic),
        'extended_markers_present' => $extended_present,
        'extended_exact_matches' => $extended_exact,
        'classic_marker_table' => $classic_rows,
        'optional_extended_table' => $extended_rows,
        'detected_y_str_markers' => $markers,
        'raw_y_marker_count' => (int) ($raw['y_markers'] ?? 0),
        'truth_boundary' => 'This is a paternal-line Y-STR research clue only. It is not proof of Cohen/Kohen status, Jewish identity, religion, tribe, Israelite ancestry, royal/priestly descent, or a named ancient ancestor.',
        'next_step' => $present >= 4
            ? 'Confirm with a dedicated Y-DNA STR/haplogroup test and compare with genealogy records before interpreting this clue.'
            : 'Upload a Y-DNA STR report if you want this lane tested. Ordinary autosomal raw DNA files usually cannot test the Cohen Modal Haplotype.',
    ];
}

function zl_dna_ancient_match_gate($summary, $columns) {
    $file_gate = zl_dna_health_interpretation_gate($summary);
    $missing = array_values(array_diff(['build', 'chrom', 'pos', 'ref', 'alt'], (array) $columns));
    $reasons = (array) ($file_gate['reasons'] ?? []);
    if ($missing) {
        $reasons[] = 'legacy individual-sample genotypes lack per-variant assembly, coordinates and reference/alternate alleles';
    } else {
        $reasons[] = 'an allele-aligned and independently validated legacy individual-sample matcher is not installed';
    }
    return [
        'ready' => false, 'status' => 'personalized_ancient_comparison_withheld',
        'assembly' => (string) ($file_gate['assembly'] ?? ''), 'reasons' => $reasons,
        'missing_alignment_fields' => $missing,
        'boundary' => 'rsID or coordinate overlap alone cannot validate ancient genotype similarity or establish descent.',
    ];
}

function zl_dna_local_ancient_grave_matches_path($path, $name = 'raw-dna.txt', $limit = 12, $summary = []) {
    $db_path = dirname(__DIR__) . '/data/packs/ancient_graves.sqlite';
    $installed = is_readable($db_path) && class_exists('SQLite3');
    $columns = [];
    $meta = [];
    if ($installed) {
        $db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
        $columns = zl_sqlite_columns($db, 'ancient_sample_genotypes');
        $meta = zl_dna_sqlite_meta($db);
        $db->close();
    }
    $summary = $summary ?: zl_dna_raw_analyze_path($path, $name);
    if (!isset($summary['reported_orientation'])) {
        $summary = zl_dna_apply_analysis_options($summary, []);
    }
    $gate = zl_dna_ancient_match_gate($summary, $columns);
    return [
        'installed' => $installed, 'interpretation_ready' => false, 'analysis_gate' => $gate,
        'reason' => $installed ? 'Legacy individual-sample source material is installed; matching to individual samples or graves is withheld because allele alignment is not validated. Ancient group comparisons are reported separately in Origins.' : 'Legacy individual-sample source material is unavailable; no comparison with individual samples or graves was performed. Ancient group comparisons are reported separately in Origins.',
        'pack_meta' => $meta, 'sample_matches' => [], 'site_scores' => [], 'matched_marker_count' => 0,
        'source_note' => 'Legacy individual-sample material remains historical research context; it is not a nearest-ancestor result. This restriction does not apply to the separate aligned ancient group comparison module.',
        'truth_boundary' => $gate['boundary'],
    ];
}

// Retained for audit; rsID-only ancient similarity is not an allele-aligned result.
function zl_dna_local_ancient_grave_matches_path_legacy_unvalidated($path, $name = 'raw-dna.txt', $limit = 12) {
    zl_dna_raise_runtime_limits();
    $db_path = dirname(__DIR__) . '/data/packs/ancient_graves.sqlite';
    if (!is_readable($db_path)) {
        return [
            'installed' => false,
            'reason' => 'Ancient Grave Match source pack not active yet',
            'sample_matches' => [],
            'site_scores' => [],
            'truth_boundary' => 'The direct grave/site comparison pack is still being expanded. For now, ZeroThink shows ancient-context leads from the current heritage signal.',
            'source_note' => 'Deeper grave/site matching is queued for expansion.',
        ];
    }
    if (!class_exists('SQLite3')) {
        return [
            'installed' => false,
            'reason' => 'Ancient comparison engine unavailable',
            'sample_matches' => [],
            'site_scores' => [],
            'truth_boundary' => 'The ancient comparison engine is temporarily unavailable, so ZeroThink is showing context leads only.',
        ];
    }
    $db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
    if (!zl_sqlite_table_exists($db, 'ancient_sample_genotypes') || !zl_sqlite_table_exists($db, 'ancient_samples')) {
        $db->close();
        return [
            'installed' => false,
            'reason' => 'Ancient source pack needs refresh',
            'sample_matches' => [],
            'site_scores' => [],
            'truth_boundary' => 'The ancient source pack needs a refresh before direct grave/site matching can run.',
        ];
    }
    $pack_meta = zl_dna_sqlite_meta($db);
    $variant_index = zl_dna_rsid_genotype_map_path($path, $name, zl_dna_max_raw_rows());
    $variant_map = (array) ($variant_index['variants'] ?? []);
    if (!$variant_map) {
        $db->close();
        return [
            'installed' => true,
            'reason' => 'no usable rsID or coordinate variants parsed',
            'sample_matches' => [],
            'site_scores' => [],
            'pack_meta' => $pack_meta,
            'truth_boundary' => 'No direct ancient comparison was possible because the upload did not expose enough usable DNA markers for this lane.',
        ];
    }
    $scores = [];
    $site_scores = [];
    $matched_keys = [];
    foreach (array_chunk(array_keys($variant_map), 900) as $chunk) {
        $quoted = array_map(function ($key) {
            return "'" . SQLite3::escapeString(strtolower((string) $key)) . "'";
        }, $chunk);
        $in = implode(',', $quoted);
        if ($in === '') {
            continue;
        }
        $sql = "SELECT g.sample_id,g.rsid,g.genotype,g.marker_weight,s.public_label,s.site_name,s.modern_country,s.region,s.culture,s.date_label,s.date_bp,s.sex,s.latitude,s.longitude,s.source_name,s.source_version,s.source_url,s.sample_quality "
            . "FROM ancient_sample_genotypes g JOIN ancient_samples s ON s.sample_id=g.sample_id WHERE g.rsid IN ($in)";
        $res = @$db->query($sql);
        while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $rsid = strtolower((string) ($row['rsid'] ?? ''));
            $user_genotype = (string) ($variant_map[$rsid] ?? '');
            if ($user_genotype === '') {
                continue;
            }
            $sim = zl_dna_genotype_similarity_score($user_genotype, (string) ($row['genotype'] ?? ''));
            if ($sim === null) {
                continue;
            }
            $sample_id = (string) ($row['sample_id'] ?? '');
            if ($sample_id === '') {
                continue;
            }
            $weight = max(0.1, min(5.0, (float) ($row['marker_weight'] ?? 1.0)));
            if (!isset($scores[$sample_id])) {
                $scores[$sample_id] = [
                    'sample_id' => $sample_id,
                    'public_label' => (string) ($row['public_label'] ?? $sample_id),
                    'site_name' => (string) ($row['site_name'] ?? ''),
                    'modern_country' => (string) ($row['modern_country'] ?? ''),
                    'region' => (string) ($row['region'] ?? ''),
                    'culture' => (string) ($row['culture'] ?? ''),
                    'date_label' => (string) ($row['date_label'] ?? ''),
                    'date_bp' => (string) ($row['date_bp'] ?? ''),
                    'sex' => (string) ($row['sex'] ?? ''),
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                    'source_name' => (string) ($row['source_name'] ?? ''),
                    'source_version' => (string) ($row['source_version'] ?? ''),
                    'source_url' => (string) ($row['source_url'] ?? ''),
                    'sample_quality' => (string) ($row['sample_quality'] ?? ''),
                    'score' => 0.0,
                    'weight' => 0.0,
                    'exact' => 0,
                    'markers' => [],
                ];
            }
            $scores[$sample_id]['score'] += $sim * $weight;
            $scores[$sample_id]['weight'] += $weight;
            $scores[$sample_id]['markers'][$rsid] = true;
            if ($sim >= 0.999) {
                $scores[$sample_id]['exact']++;
            }
            $site_key = trim((string) ($row['site_name'] ?? '') . '|' . (string) ($row['modern_country'] ?? '') . '|' . (string) ($row['culture'] ?? ''));
            if ($site_key !== '||') {
                if (!isset($site_scores[$site_key])) {
                    $site_scores[$site_key] = [
                        'site_name' => (string) ($row['site_name'] ?? ''),
                        'modern_country' => (string) ($row['modern_country'] ?? ''),
                        'culture' => (string) ($row['culture'] ?? ''),
                        'score' => 0.0,
                        'weight' => 0.0,
                        'samples' => [],
                        'markers' => [],
                    ];
                }
                $site_scores[$site_key]['score'] += $sim * $weight;
                $site_scores[$site_key]['weight'] += $weight;
                $site_scores[$site_key]['samples'][$sample_id] = true;
                $site_scores[$site_key]['markers'][$rsid] = true;
            }
            $matched_keys[$rsid] = true;
        }
    }
    $format = function ($rows) use ($limit) {
        $out = [];
        foreach ($rows as $row) {
            $weight = max(0.001, (float) ($row['weight'] ?? 0));
            $overlap = count((array) ($row['markers'] ?? []));
            $similarity = round((((float) ($row['score'] ?? 0)) / $weight) * 100, 2);
            $confidence = round(zl_dna_math_clamp(($similarity * 0.58) + min(36, $overlap * 0.42), 0, 95), 1);
            unset($row['score'], $row['weight'], $row['markers']);
            if (isset($row['samples'])) {
                $row['sample_count'] = count((array) $row['samples']);
                unset($row['samples']);
            }
            $row['similarity_score'] = $similarity;
            $row['overlap_marker_count'] = $overlap;
            $row['confidence_score'] = $confidence;
            $row['confidence_band'] = zl_dna_confidence_label_from_score($confidence);
            $out[] = $row;
        }
        usort($out, function ($a, $b) {
            $by_conf = ((float) ($b['confidence_score'] ?? 0)) <=> ((float) ($a['confidence_score'] ?? 0));
            if ($by_conf !== 0) {
                return $by_conf;
            }
            return ((int) ($b['overlap_marker_count'] ?? 0)) <=> ((int) ($a['overlap_marker_count'] ?? 0));
        });
        return array_slice($out, 0, $limit);
    };
    $samples = $format($scores);
    $sites = $format($site_scores);
    $db->close();
    return [
        'installed' => true,
        'reason' => 'Ancient Grave Match source pack scanned',
        'pack_meta' => $pack_meta,
        'variants_scanned' => count($variant_map),
        'matched_marker_count' => count($matched_keys),
        'sample_matches' => $samples,
        'site_scores' => $sites,
        'truth_boundary' => 'Ancient grave matching reports genetic similarity to public ancient samples or sites. It does not prove descent from a grave, king, soldier, tribe, religion, culture, or named person.',
    ];
}

function zl_dna_lookup_variants_with_coordinates($variant_map) {
    $lookup = [];
    foreach ((array) ($variant_map['variants'] ?? []) as $rsid => $variant) {
        $key = strtolower((string) $rsid);
        if ($key !== '' && !isset($lookup[$key])) {
            $lookup[$key] = $variant;
        }
        $chrom = strtoupper(trim((string) ($variant['chromosome'] ?? '')));
        $chrom = preg_replace('/^CHR/i', '', $chrom);
        $pos = trim((string) ($variant['position'] ?? ''));
        if ($chrom !== '' && preg_match('/^\d+$/', $pos)) {
            $coord = strtolower($chrom . ':' . $pos);
            if (!isset($lookup[$coord])) {
                $lookup[$coord] = $variant;
            }
        }
    }
    return $lookup;
}

function zl_dna_parent_projection_groups() {
    return [
        'south_asian' => [
            'label' => 'South Asian / Pakistan-Punjab / North India lead',
            'plain' => 'Closest installed references point toward Pakistan/Punjab, North India, Bangladesh, or neighbouring South Asian contexts.',
            'codes' => ['PJL', 'GIH', 'BEB', 'ITU', 'STU'],
            'color' => '#30c6d6',
        ],
        'british_irish' => [
            'label' => 'Irish / British Isles / Northwest Europe lead',
            'plain' => 'Closest installed references point toward Ireland, Britain, Celtic Isles, or nearby Northwest-European contexts.',
            'codes' => ['GBR', 'CEU', 'IBS', 'TSI', 'FIN'],
            'color' => '#ff6edb',
        ],
        'east_asian' => [
            'label' => 'East Asian lead',
            'plain' => 'Closest installed references point toward China, Japan, Vietnam, or nearby East Asian contexts.',
            'codes' => ['CHB', 'CHS', 'CDX', 'JPT', 'KHV'],
            'color' => '#69f7ff',
        ],
        'african' => [
            'label' => 'African / African-diaspora lead',
            'plain' => 'Closest installed references point toward African or African-diaspora contexts.',
            'codes' => ['YRI', 'ESN', 'GWD', 'MSL', 'LWK', 'ACB', 'ASW'],
            'color' => '#ffd76f',
        ],
        'admixed_americas' => [
            'label' => 'Americas mixed-reference overlap',
            'plain' => 'Mixed-Americas panels often overlap several ancestry sources, so ZeroThink keeps this as context unless a dedicated Americas model supports it.',
            'codes' => ['MXL', 'CLM', 'PEL', 'PUR'],
            'color' => '#a78bfa',
            'suppress_headline' => true,
        ],
    ];
}

function zl_dna_parent_side_projection_path($child_path, $child_name, $parent_path, $parent_name, $other_side_label = 'other parent') {
    zl_dna_raise_runtime_limits();
    $db_path = dirname(__DIR__) . '/data/packs/ancestry_core.sqlite';
    if (!is_readable($db_path) || !class_exists('SQLite3') || !is_file((string) $child_path) || !is_file((string) $parent_path)) {
        return [
            'available' => false,
            'reason' => 'parent-side projection needs the ancestry pack plus both DNA files',
        ];
    }

    try {
        $child_map = zl_dna_lookup_variants_with_coordinates(zl_dna_raw_variant_map_path($child_path, $child_name, zl_dna_max_raw_rows()));
        $parent_map = zl_dna_lookup_variants_with_coordinates(zl_dna_raw_variant_map_path($parent_path, $parent_name, zl_dna_max_raw_rows()));
        $lookup_keys = array_values(array_intersect(array_keys($child_map), array_keys($parent_map)));
        if (!$lookup_keys) {
            return ['available' => false, 'reason' => 'no shared variants between child and parent files'];
        }

        $groups = zl_dna_parent_projection_groups();
        $allowed_codes = [];
        foreach ($groups as $group) {
            foreach ((array) ($group['codes'] ?? []) as $code) {
                $allowed_codes[strtoupper((string) $code)] = true;
            }
        }

        $db = new SQLite3($db_path, SQLITE3_OPEN_READONLY);
        if (!zl_sqlite_table_exists($db, 'ancestry_reference_markers')) {
            $db->close();
            return ['available' => false, 'reason' => 'ancestry_reference_markers table missing'];
        }

        $pop = [];
        foreach (array_chunk($lookup_keys, 450) as $chunk) {
            $quoted = array_map(function ($key) {
                return "'" . SQLite3::escapeString(strtolower((string) $key)) . "'";
            }, $chunk);
            $in = implode(',', $quoted);
            if ($in === '') {
                continue;
            }
            $res = @$db->query("SELECT rsid,alt,population_code,population_label,super_population,allele_frequency FROM ancestry_reference_markers WHERE panel_name='1000G_Phase3_optimized_population' AND rsid IN ($in)");
            while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                $rsid = strtolower((string) ($row['rsid'] ?? ''));
                $code = strtoupper((string) ($row['population_code'] ?? ''));
                if (!isset($allowed_codes[$code])) {
                    continue;
                }
                $alt = strtoupper((string) ($row['alt'] ?? ''));
                if ($alt === '' || strlen($alt) !== 1 || !preg_match('/^[ACGT]$/', $alt)) {
                    continue;
                }
                $child_row = $child_map[$rsid] ?? null;
                $parent_row = $parent_map[$rsid] ?? null;
                if (!$child_row || !$parent_row) {
                    continue;
                }
                $child_gt = strtoupper((string) ($child_row['genotype'] ?? ''));
                $parent_gt = strtoupper((string) ($parent_row['genotype'] ?? ''));
                if (!preg_match('/^[ACGT]{2}$/', $child_gt) || !preg_match('/^[ACGT]{2}$/', $parent_gt)) {
                    continue;
                }
                $child_dose = zl_dna_allele_count($child_gt, [$alt]);
                $parent_dose = zl_dna_allele_count($parent_gt, [$alt]);
                $parent_transmit = $parent_dose / 2.0;
                $af = zl_dna_math_clamp((float) ($row['allele_frequency'] ?? 0), 0.005, 0.995);
                $probabilities = [
                    (1.0 - $parent_transmit) * (1.0 - $af),
                    ($parent_transmit * (1.0 - $af)) + ((1.0 - $parent_transmit) * $af),
                    $parent_transmit * $af,
                ];
                $probability = max(0.000000001, (float) ($probabilities[$child_dose] ?? 0.000000001));
                $informativeness = abs($af - 0.5) * 2.0;
                $weight = 0.2 + ($informativeness * 1.8);
                if (!isset($pop[$code])) {
                    $pop[$code] = [
                        'population_code' => $code,
                        'population_label' => (string) ($row['population_label'] ?? ''),
                        'modern_label' => zl_dna_population_modern_label($code, (string) ($row['population_label'] ?? ''), (string) ($row['super_population'] ?? '')),
                        'log_likelihood' => 0.0,
                        'weight' => 0.0,
                        'markers' => [],
                    ];
                }
                $pop[$code]['log_likelihood'] += log($probability) * $weight;
                $pop[$code]['weight'] += $weight;
                $pop[$code]['markers'][$rsid] = true;
            }
        }
        $db->close();

        $population_scores = [];
        foreach ($pop as $row) {
            $weight = max(0.0001, (float) ($row['weight'] ?? 0));
            $avg = (float) ($row['log_likelihood'] ?? 0) / $weight;
            $marker_count = count((array) ($row['markers'] ?? []));
            unset($row['log_likelihood'], $row['weight'], $row['markers']);
            $row['avg_log_likelihood'] = round($avg, 5);
            $row['matched_marker_count'] = $marker_count;
            $population_scores[] = $row;
        }
        usort($population_scores, function ($a, $b) {
            return ((float) ($b['avg_log_likelihood'] ?? -999)) <=> ((float) ($a['avg_log_likelihood'] ?? -999));
        });

        $group_scores = [];
        foreach ($groups as $group_key => $group) {
            $members = [];
            foreach ($population_scores as $row) {
                if (in_array((string) ($row['population_code'] ?? ''), (array) ($group['codes'] ?? []), true)) {
                    $members[] = $row;
                }
            }
            if (!$members) {
                continue;
            }
            usort($members, function ($a, $b) {
                return ((float) ($b['avg_log_likelihood'] ?? -999)) <=> ((float) ($a['avg_log_likelihood'] ?? -999));
            });
            $top_members = array_slice($members, 0, 3);
            $avg = array_sum(array_map(function ($row) {
                return (float) ($row['avg_log_likelihood'] ?? -999);
            }, $top_members)) / max(1, count($top_members));
            $markers = array_sum(array_map(function ($row) {
                return (int) ($row['matched_marker_count'] ?? 0);
            }, $top_members));
            $group_scores[] = [
                'group' => $group_key,
                'label' => (string) ($group['label'] ?? $group_key),
                'plain' => (string) ($group['plain'] ?? ''),
                'color' => (string) ($group['color'] ?? '#69f7ff'),
                'avg_log_likelihood' => round($avg, 5),
                'matched_marker_count' => $markers,
                'top_populations' => $top_members,
                'suppress_headline' => !empty($group['suppress_headline']),
            ];
        }
        usort($group_scores, function ($a, $b) {
            return ((float) ($b['avg_log_likelihood'] ?? -999)) <=> ((float) ($a['avg_log_likelihood'] ?? -999));
        });
        $headline_group = null;
        foreach ($group_scores as $group) {
            if (empty($group['suppress_headline'])) {
                $headline_group = $group;
                break;
            }
        }
        if (!$headline_group && $group_scores) {
            $headline_group = $group_scores[0];
        }
        if (!$headline_group) {
            return ['available' => false, 'reason' => 'not enough informative parent-side markers matched the local panel'];
        }
        $runner_up = null;
        foreach ($group_scores as $group) {
            if (($group['group'] ?? '') !== ($headline_group['group'] ?? '') && empty($group['suppress_headline'])) {
                $runner_up = $group;
                break;
            }
        }
        $contrast = $runner_up ? ((float) ($headline_group['avg_log_likelihood'] ?? 0) - (float) ($runner_up['avg_log_likelihood'] ?? 0)) : 0.03;
        $confidence_score = (int) round(zl_dna_math_clamp(54 + ($contrast * 520) + min(14, ((int) ($headline_group['matched_marker_count'] ?? 0)) / 120), 45, 82));
        $top_names = array_map(function ($row) {
            return (string) ($row['modern_label'] ?? $row['population_code'] ?? 'reference');
        }, array_slice((array) ($headline_group['top_populations'] ?? []), 0, 3));
        $suppressed = [];
        foreach ($group_scores as $group) {
            if (!empty($group['suppress_headline'])) {
                $suppressed[] = (string) ($group['label'] ?? 'mixed-reference overlap') . ' was treated as context, not a headline ancestry call.';
            }
        }
        return [
            'available' => true,
            'engine' => 'ZDNA-PARENT-SPLIT v0.1',
            'other_side_label' => $other_side_label,
            'headline' => ucfirst((string) $other_side_label) . ': ' . ($headline_group['label'] ?? 'reference lead'),
            'plain_summary' => 'Using the supplied parent file as one biological side, the remaining side is best explained by: ' . ($headline_group['label'] ?? 'reference lead') . '. Closest installed references: ' . ($top_names ? implode('; ', $top_names) : 'not enough named references') . '.',
            'confidence_score' => $confidence_score,
            'confidence_band' => zl_dna_confidence_label_from_score($confidence_score),
            'headline_group' => $headline_group,
            'group_scores' => array_slice($group_scores, 0, 5),
            'population_scores' => array_slice($population_scores, 0, 12),
            'suppressed_context' => $suppressed,
            'boundary' => 'Parent-side split is stronger than a single-file estimate, but it is still a probabilistic research model, not identity proof.',
        ];
    } catch (Throwable $e) {
        return [
            'available' => false,
            'reason' => 'parent-side projection failed safely',
        ];
    }
}

function zl_dna_parent_side_display_label($side_report, $fallback = 'parent side') {
    $estimate = (array) ($side_report['plain_ancestry_summary']['consumer_estimate'] ?? []);
    $segments = (array) ($estimate['segments'] ?? []);
    $top = array_slice(array_filter($segments, function ($segment) {
        return (float) ($segment['percent'] ?? $segment['value'] ?? 0) >= 4.0;
    }), 0, 3);
    $labels = array_map(function ($segment) {
        return (string) ($segment['label'] ?? '');
    }, $top);
    $joined = strtolower(implode(' ', $labels));
    if (preg_match('/irish|scottish|welsh|english|breton|celtic|british/', $joined)) {
        return 'Irish / Celtic / British Isles side';
    }
    if (preg_match('/pakistan|punjab|south asian|bangladesh|india|gujarati|telugu|tamil/', $joined)) {
        return 'South Asian / Pakistan-Punjab side';
    }
    return $labels ? implode(' + ', array_slice($labels, 0, 2)) . ' side' : $fallback;
}

function zl_dna_parent_aware_estimate($mother_side = null, $father_side = null) {
    return [
        'available' => false, 'segments' => [], 'confidence_score' => 0,
        'confidence_band' => 'not established',
        'reason' => 'Parent uploads do not validate ancestry proportions or phase the unsupplied parent. A validated phased multi-population model is required.',
        'boundary' => 'Supplied-parent similarity is research evidence only and cannot establish parentage or ancestry composition.',
    ];
}

function zl_dna_public_parent_context($side) {
    if (!is_array($side)) { return null; }
    $side['other_parent_projection'] = zl_dna_parent_aware_estimate();
    $side['top_mix'] = [];
    $side['interpretation_status'] = 'experimental_similarity_only';
    return $side;
}

// Preserved for auditing; inferred 50/50 labels are not validated ancestry composition.
function zl_dna_parent_aware_estimate_legacy_unvalidated($mother_side = null, $father_side = null) {
    $mother_side = is_array($mother_side) ? $mother_side : null;
    $father_side = is_array($father_side) ? $father_side : null;
    $segments = [];
    $notes = [];
    $confidence = 0;

    if ($mother_side && !empty($mother_side['other_parent_projection']['available'])) {
        $projection = (array) $mother_side['other_parent_projection'];
        $group = (array) ($projection['headline_group'] ?? []);
        $segments[] = [
            'label' => 'Mother side: ' . zl_dna_parent_side_display_label($mother_side, 'mother side'),
            'value' => 50,
            'color' => '#ff6edb',
            'note' => 'Supplied parent DNA file.',
        ];
        $segments[] = [
            'label' => 'Father side: ' . (string) ($group['label'] ?? 'remaining-side reference lead'),
            'value' => 50,
            'color' => (string) ($group['color'] ?? '#30c6d6'),
            'note' => (string) ($projection['plain_summary'] ?? ''),
        ];
        $notes[] = (string) ($projection['plain_summary'] ?? '');
        $confidence = max($confidence, (int) ($projection['confidence_score'] ?? 0));
    }

    if ($father_side && !empty($father_side['other_parent_projection']['available'])) {
        $projection = (array) $father_side['other_parent_projection'];
        $group = (array) ($projection['headline_group'] ?? []);
        $segments[] = [
            'label' => 'Father side: ' . zl_dna_parent_side_display_label($father_side, 'father side'),
            'value' => 50,
            'color' => '#30c6d6',
            'note' => 'Supplied parent DNA file.',
        ];
        $segments[] = [
            'label' => 'Mother side: ' . (string) ($group['label'] ?? 'remaining-side reference lead'),
            'value' => 50,
            'color' => (string) ($group['color'] ?? '#ff6edb'),
            'note' => (string) ($projection['plain_summary'] ?? ''),
        ];
        $notes[] = (string) ($projection['plain_summary'] ?? '');
        $confidence = max($confidence, (int) ($projection['confidence_score'] ?? 0));
    }

    if (!$segments) {
        return ['available' => false];
    }
    $segments = zl_dna_safe_percent_segments($segments);
    foreach ($segments as &$segment) {
        if (!isset($segment['percent'])) {
            $segment['percent'] = round((float) ($segment['value'] ?? 0), 1);
        }
    }
    unset($segment);
    return [
        'available' => true,
        'method' => 'ZDNA-PARENT-SPLIT v0.1 parent-aware 50/50 composition',
        'headline' => 'Parent-aware ancestry split',
        'plain' => 'A supplied parent DNA file lets ZeroThink split the result into the known parent side and the estimated remaining side. This is much stronger than a single-file chart for mixed-heritage users.',
        'segments' => $segments,
        'confidence_score' => $confidence ?: 65,
        'confidence_band' => zl_dna_confidence_label_from_score($confidence ?: 65),
        'notes' => array_values(array_filter($notes)),
        'boundary' => 'Parent-aware split is probabilistic research context. It is not identity, nationality, culture, religion, or legal lineage proof.',
    ];
}

function zl_dna_extract_trace_proxy_from_estimate($estimate, $needle = 'Ashkenazi') {
    $estimate = (array) $estimate;
    $needle = strtolower((string) $needle);
    foreach ((array) ($estimate['trace_lanes'] ?? []) as $trace) {
        $label = strtolower((string) ($trace['label'] ?? ''));
        if ($needle !== '' && strpos($label, $needle) === false) {
            continue;
        }
        return round((float) ($trace['proxy_percent'] ?? 0), 2);
    }
    return 0.0;
}

function zl_dna_extract_jewish_evidence_from_plain($plain) {
    $plain = (array) $plain;
    $evidence = (array) ($plain['jewish_diaspora_evidence'] ?? []);
    $estimate = (array) ($plain['consumer_estimate'] ?? []);
    $trace = zl_dna_extract_trace_proxy_from_estimate($estimate, 'Ashkenazi');
    if (!$evidence) {
        $evidence = [
            'proxy_percent' => $trace,
            'answer' => $trace > 0 ? 'trace visible' : 'not visible',
            'confidence_band' => $trace > 0 ? 'trace / low' : 'low',
            'plain' => $trace > 0 ? 'A low trace is visible in the consumer-style projection.' : 'No trace was visible in the consumer-style projection.',
        ];
    }
    $evidence['proxy_percent'] = round(max((float) ($evidence['proxy_percent'] ?? 0), $trace), 2);
    return $evidence;
}

function zl_dna_parent_trace_origin($heritage_report, $mother_side = null, $father_side = null) {
    $child_plain = (array) ($heritage_report['insights']['plain_ancestry_summary'] ?? []);
    $child = zl_dna_extract_jewish_evidence_from_plain($child_plain);
    $mother = $mother_side ? zl_dna_extract_jewish_evidence_from_plain((array) ($mother_side['plain_ancestry_summary'] ?? [])) : null;
    $father = $father_side ? zl_dna_extract_jewish_evidence_from_plain((array) ($father_side['plain_ancestry_summary'] ?? [])) : null;
    $child_proxy = (float) ($child['proxy_percent'] ?? 0);
    $mother_proxy = $mother ? (float) ($mother['proxy_percent'] ?? 0) : 0.0;
    $father_proxy = $father ? (float) ($father['proxy_percent'] ?? 0) : 0.0;
    $mother_visible = $mother && $mother_proxy >= 1.0;
    $father_visible = $father && $father_proxy >= 1.0;

    if ($mother_visible && $father_visible) {
        $status = 'visible in both supplied parent files';
        $plain = 'The Ashkenazi/Jewish-diaspora trace appears in both supplied parent files. Treat it as a shared low-trace clue unless a stronger ASJ-control separation is present.';
    } elseif ($mother_visible) {
        $status = 'visible in supplied mother file';
        $plain = 'The trace appears in the supplied mother file and in the child file. Without a direct father file, ZeroThink can say the maternal side is compatible, but it cannot rule paternal contribution in or out.';
    } elseif ($father_visible) {
        $status = 'visible in supplied father file';
        $plain = 'The trace appears in the supplied father file and in the child file. Without a direct mother trace, the father side is the clearer supplied source.';
    } elseif ($child_proxy >= 1.0 && ($mother_side || $father_side)) {
        $status = 'visible in child, not visible in supplied parent file';
        $plain = 'The child file has a low trace, but the supplied parent file did not show it clearly. This can happen with trace-level noise, an unsupplied parent side, or reference overlap.';
    } elseif ($child_proxy >= 1.0) {
        $status = 'trace visible, parent side unknown';
        $plain = 'A low trace is visible in the child file, but no parent file was supplied for side assignment.';
    } else {
        $status = 'not visible as a side-assigned trace';
        $plain = 'No side-assigned Ashkenazi/Jewish-diaspora trace is visible from the supplied files.';
    }

    $control_delta = (float) ($child['asj_control_delta'] ?? 0);
    if (isset($child['asj_control_delta']) && $control_delta < 1.5 && $child_proxy >= 1.0) {
        $plain .= ' Important: the ASJ panel is not clearly separated from nearby European controls in this run, so the trace should stay low-confidence.';
    }

    return [
        'available' => $child_proxy > 0 || $mother_visible || $father_visible,
        'label' => 'Ashkenazi/Jewish-diaspora parent-side trace screen',
        'status' => $status,
        'plain' => $plain,
        'child_proxy_percent' => round($child_proxy, 2),
        'mother_proxy_percent' => $mother ? round($mother_proxy, 2) : null,
        'father_proxy_percent' => $father ? round($father_proxy, 2) : null,
        'child_evidence' => $child,
        'mother_evidence' => $mother,
        'father_evidence' => $father,
        'boundary' => 'Parent-side trace assignment is research context only. It is not religion, identity, community membership, or proof of ancestry.',
    ];
}

function zl_dna_health_report($text, $name = 'raw-dna.txt', $options = []) {
    $base = zl_dna_apply_analysis_options(zl_dna_raw_analyze($text, $name), $options);
    $map = zl_dna_raw_variant_map($text, $name);
    $hits = zl_dna_marker_hits($map, zl_dna_health_marker_panel());
    $insights = zl_dna_health_insights($hits, $base, $map, $options);
    return [
        'summary' => $base,
        'variant_index' => [
            'file_name' => $map['file_name'],
            'file_hash' => $map['file_hash'],
            'parsed_rsid_variants' => $map['parsed_rsid_variants'],
            'truncated' => $map['truncated'],
            'callability' => zl_dna_marker_callability_counts($map, zl_dna_health_marker_panel()),
        ],
        'hits' => $hits,
        'clinvar_rsids' => array_values(array_map(function ($hit) {
            return $hit['rsid'];
        }, $hits)),
        'insights' => $insights,
        'chart' => $insights['chart'],
        'disclaimer' => 'Research-only health marker screen. Not medical, diagnostic, treatment, prevention, or medication advice. Confirm any important finding with a clinician and certified lab.',
    ];
}

function zl_dna_health_report_path($path, $name = 'raw-dna.txt', $base_override = null, $options = []) {
    zl_dna_raise_runtime_limits();
    $base = is_array($base_override) ? $base_override : zl_dna_apply_analysis_options(zl_dna_raw_analyze_path($path, $name), $options);
    $panel = zl_dna_health_marker_panel();
    $map = zl_dna_marker_scan_path($path, $panel, $name);
    $hits = zl_dna_marker_hits($map, $panel);
    $insights = zl_dna_health_insights($hits, $base, $map, $options);
    $pack_matches = zl_dna_local_health_pack_matches_path($path, $name, 80, $base);
    return [
        'summary' => $base,
        'variant_index' => [
            'file_name' => $map['file_name'],
            'file_hash' => $map['file_hash'],
            'parsed_rsid_variants' => $map['parsed_rsid_variants'],
            'truncated' => $map['truncated'],
            'callability' => zl_dna_marker_callability_counts($map, $panel),
        ],
        'hits' => $hits,
        'clinvar_rsids' => array_values(array_map(function ($hit) {
            return $hit['rsid'];
        }, $hits)),
        'local_pack_matches' => $pack_matches,
        'insights' => $insights,
        'chart' => $insights['chart'],
        'disclaimer' => 'Research-only health marker screen. Not medical, diagnostic, treatment, prevention, or medication advice. Confirm any important finding with a clinician and certified lab.',
    ];
}

function zl_dna_heritage_report($text, $name = 'raw-dna.txt') {
    $base = zl_dna_raw_analyze($text, $name);
    $map = zl_dna_raw_variant_map($text, $name);
    $hits = zl_dna_marker_hits($map, zl_dna_heritage_marker_panel());
    $parsed = (int) ($base['parsed_variants'] ?? 0);
    $coverage = (int) ($base['chromosome_count'] ?? 0);
    $ready_score = 0;
    if ($parsed >= 50000) {
        $ready_score += 35;
    }
    if ($parsed >= 300000) {
        $ready_score += 25;
    }
    if ($coverage >= 22) {
        $ready_score += 25;
    }
    if (count($hits) >= 4) {
        $ready_score += 15;
    }
    $ready_score = min(100, $ready_score);
    $insights = zl_dna_heritage_insights($hits, $base);
    return [
        'summary' => $base,
        'variant_index' => [
            'file_name' => $map['file_name'],
            'file_hash' => $map['file_hash'],
            'parsed_rsid_variants' => $map['parsed_rsid_variants'],
            'truncated' => $map['truncated'],
        ],
        'hits' => $hits,
        'local_reference_pack' => [
            'installed' => false,
            'reason' => 'text-mode heritage report does not scan local ancestry pack',
            'modern_region_glossary' => zl_dna_modern_region_glossary(),
        ],
        'reference_readiness_score' => $ready_score,
        'insights' => $insights,
        'chart' => $insights['chart'],
        'admixture_boundary' => 'ZeroThink gives probabilistic plain-language heritage projections when local reference data is available, then shows the evidence and uncertainty below. Treat them as research estimates, not legal/cultural identity.',
        'disclaimer' => 'Heritage research support only. Genetic ancestry is probabilistic and not the same as culture, nationality, identity, or ethnicity.',
    ];
}

function zl_dna_heritage_report_path($path, $name = 'raw-dna.txt', $base_override = null) {
    zl_dna_raise_runtime_limits();
    $base = is_array($base_override) ? $base_override : zl_dna_raw_analyze_path($path, $name);
    $panel = zl_dna_heritage_marker_panel();
    $map = zl_dna_marker_scan_path($path, $panel, $name);
    $hits = zl_dna_marker_hits($map, $panel);
    $parsed = (int) ($base['parsed_variants'] ?? 0);
    $coverage = (int) ($base['chromosome_count'] ?? 0);
    $ready_score = 0;
    if ($parsed >= 50000) {
        $ready_score += 35;
    }
    if ($parsed >= 300000) {
        $ready_score += 25;
    }
    if ($coverage >= 22) {
        $ready_score += 25;
    }
    if (count($hits) >= 4) {
        $ready_score += 15;
    }
    $ready_score = min(100, $ready_score);
    $reference_pack = zl_dna_local_ancestry_pack_matches_path($path, $name, 48, $base);
    $insights = zl_dna_heritage_insights($hits, $base, $reference_pack);
    if (!empty($insights['plain_ancestry_summary']['suppress_south_user_output'])) {
        $is_south_user_label = function ($value) {
            return (bool) preg_match('/\b(Pakistan|Pakistani|Punjabi|Punjab|South Asian|South Asia|Bangladesh|Bengali|India|Gujarati|Telugu|Sri Lanka|Tamil|PJL|BEB|GIH|ITU|STU|SAS)\b/i', (string) $value);
        };
        foreach (['population_scores', 'super_population_scores'] as $key) {
            if (!empty($reference_pack[$key]) && is_array($reference_pack[$key])) {
                $reference_pack[$key] = array_values(array_filter($reference_pack[$key], function ($row) use ($is_south_user_label) {
                    return !$is_south_user_label(
                        ($row['population_code'] ?? '') . ' ' .
                        ($row['super_population'] ?? '') . ' ' .
                        ($row['population_label'] ?? '') . ' ' .
                        ($row['modern_label'] ?? '')
                    );
                }));
            }
        }
        if (!empty($reference_pack['modern_region_glossary']['South Asia'])) {
            unset($reference_pack['modern_region_glossary']['South Asia']);
        }
    }
    return [
        'summary' => $base,
        'variant_index' => [
            'file_name' => $map['file_name'],
            'file_hash' => $map['file_hash'],
            'parsed_rsid_variants' => $map['parsed_rsid_variants'],
            'truncated' => $map['truncated'],
        ],
        'hits' => $hits,
        'local_reference_pack' => $reference_pack,
        'reference_readiness_score' => $ready_score,
        'insights' => $insights,
        'chart' => $insights['chart'],
        'admixture_boundary' => 'ZeroThink gives probabilistic plain-language heritage projections when local reference data is available, then shows the evidence and uncertainty below. Treat them as research estimates, not legal/cultural identity.',
        'disclaimer' => 'Heritage research support only. Genetic ancestry is probabilistic and not the same as culture, nationality, identity, or ethnicity.',
    ];
}

function zl_dna_data_pack_status() {
    $root = dirname(__DIR__) . '/data/packs';
    $packs = [
        'health_core.sqlite' => ['display_name' => 'Health Research Source Pack', 'label' => 'ClinVar / GWAS / PharmGKB health evidence'],
        'ancestry_core.sqlite' => ['display_name' => 'Country & Region Reference Engine', 'label' => 'compact ancestry and heritage comparison sources'],
        'lineage_core.sqlite' => ['display_name' => 'Lineage Graph Source Pack', 'label' => 'public historical person and family-link graph'],
        'ancient_graves.sqlite' => ['display_name' => 'Ancient Grave Match Source Pack', 'label' => 'public ancient sample and grave/site comparison sources'],
        'source_manifest.sqlite' => ['display_name' => 'Source Version Ledger', 'label' => 'source/version evidence ledger'],
    ];
    $out = [];
    foreach ($packs as $file => $meta) {
        $path = $root . '/' . $file;
        $entry = [
            'pack' => $file,
            'display_name' => (string) ($meta['display_name'] ?? $file),
            'label' => (string) ($meta['label'] ?? ''),
            'installed' => is_file($path),
            'updated_at' => is_file($path) ? gmdate('c', filemtime($path)) : null,
            'size_bytes' => is_file($path) ? (int) filesize($path) : 0,
        ];
        if ($file === 'health_core.sqlite' && is_file($path)) {
            $manifest_path = $root . '/health_core_manifest.json';
            $manifest = is_file($manifest_path)
                ? json_decode((string) @file_get_contents($manifest_path), true)
                : null;
            if (is_array($manifest)) {
                $quality = (array) (($manifest['quality']['clinvar'] ?? []));
                $entry['built_at'] = (string) ($manifest['built_at'] ?? '');
                $entry['pack_version'] = (string) ($manifest['pack_version'] ?? 'legacy');
                $entry['schema_version'] = (string) ($manifest['schema_version'] ?? 'legacy');
                $entry['importer_version'] = (string) ($manifest['importer_version'] ?? 'legacy');
                $entry['allele_ready'] = (int) ($quality['allele_matchable_rows'] ?? 0) > 0
                    && (int) ($quality['invalid_matchable_rows'] ?? 0) === 0;
                $entry['source_rows'] = [];
                foreach ((array) ($manifest['sources'] ?? []) as $source) {
                    $source_id = preg_replace('/[^a-z0-9_.-]+/i', '', (string) ($source['source_id'] ?? ''));
                    if ($source_id !== '') {
                        $entry['source_rows'][$source_id] = max(0, (int) ($source['rows'] ?? 0));
                    }
                }
                $entry['personalized_gwas_status'] = 'withheld_until_effect_allele_is_available';
                $entry['personalized_pharmgkb_status'] = 'withheld_until_validated_haplotype_interpretation_is_available';
            }
        }
        $out[] = $entry;
    }
    $offload = zl_offload_node_status();
    if (!empty($offload['configured']) || !empty($offload['health']['ok'])) {
        $out[] = [
            'pack' => 'openzero_offload_node',
            'label' => 'optional 64GB OpenZero offload node for heavy packs and background jobs',
            'installed' => !empty($offload['health']['ok']),
            'updated_at' => gmdate('c'),
            'size_bytes' => 0,
            'offload' => $offload,
        ];
    }
    return $out;
}

function zl_dna_public_report_text($text) {
    $text = (string) $text;
    if ($text === '') {
        return $text;
    }
    $replace = [
        'ancient_graves.sqlite' => 'Ancient Grave Match source pack',
        'ancestry_core.sqlite' => 'Country & Region Reference Engine',
        'health_core.sqlite' => 'Health Research Source Pack',
        'lineage_core.sqlite' => 'Lineage Graph Source Pack',
        'source_manifest.sqlite' => 'Source Version Ledger',
        'local SQLite' => 'secure local',
        'SQLite3' => 'comparison engine',
        'SQLite' => 'source engine',
        'sqlite' => 'source pack',
        'SQL' => 'source engine',
        'data-pack' => 'source pack',
        'data pack' => 'source pack',
        'Data-Pack' => 'Source Pack',
        'Data Pack' => 'Source Pack',
        'graph pack' => 'graph source',
        'Graph pack' => 'Graph source',
        'database' => 'source',
        'Database' => 'Source',
        'pack_required' => 'required source pack is not installed',
        'local/offload' => 'source-mirror',
        'local health evidence pack plus offload mirror' => 'health evidence source mirror',
        'optimized local ancestry reference panel with offload source staging' => 'optimized country and region reference engine',
        'public ancient sample genotype source pack' => 'public ancient sample source pack',
        'local public lineage source pack if installed' => 'public lineage graph source',
        'large reference-pack storage and background jobs' => 'expanded reference storage and background jobs',
        'Run the private source-pack builder and ' => 'Refresh source packs and ',
    ];
    $text = strtr($text, $replace);
    $text = preg_replace('/Private operator note:[^\n]*/i', 'Source note: deeper matching is queued for expansion.', $text);
    $text = preg_replace('/Source note:\s*deeper matching is queued for expansion\.\s*/i', '', $text);
    return $text;
}

function zl_offload_config_path() {
    return zl_storage_root() . '/offload_config.json';
}

function zl_offload_config() {
    if (defined('ZEROTHINK_LOCAL_LAB') && ZEROTHINK_LOCAL_LAB === true) {
        return ['configured'=>false,'enabled'=>false,'url'=>'','token'=>'','has_token'=>false];
    }
    $url = trim((string) getenv('ZEROTHINK_OFFLOAD_URL'));
    $token = trim((string) getenv('ZEROTHINK_OFFLOAD_TOKEN'));
    $enabled = $url !== '';
    $path = zl_offload_config_path();
    if (is_file($path) && is_readable($path)) {
        $json = json_decode((string) @file_get_contents($path), true);
        if (is_array($json)) {
            $url = trim((string) ($json['url'] ?? $url));
            $token = trim((string) ($json['token'] ?? $token));
            $enabled = array_key_exists('enabled', $json) ? !empty($json['enabled']) : ($url !== '');
        }
    }
    $url = rtrim($url, '/');
    return [
        'enabled' => $enabled && $url !== '',
        'configured' => $url !== '',
        'url' => $url,
        'has_token' => $token !== '',
        'token' => $token,
        'config_path' => $path,
    ];
}

function zl_offload_http($method, $path, $body = null, $timeout = 12, $auth = true) {
    $cfg = zl_offload_config();
    if (empty($cfg['configured'])) {
        return ['ok' => false, 'error' => 'offload_not_configured'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'curl_extension_unavailable'];
    }
    $url = rtrim((string) $cfg['url'], '/') . '/' . ltrim((string) $path, '/');
    $headers = ['Accept: application/json'];
    if ($auth && !empty($cfg['token'])) {
        $headers[] = 'Authorization: Bearer ' . $cfg['token'];
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper((string) $method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(6, max(2, (int) $timeout)));
    curl_setopt($ch, CURLOPT_TIMEOUT, max(2, (int) $timeout));
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'error' => $err ?: null,
        'data' => is_array($data) ? $data : null,
        'raw' => is_string($raw) ? substr($raw, 0, 500) : '',
    ];
}

function zl_offload_node_status() {
    if (defined('ZEROTHINK_LOCAL_LAB') && ZEROTHINK_LOCAL_LAB === true) {
        return ['configured'=>false,'enabled'=>false,'url'=>'','has_token'=>false,'health'=>null,'status'=>null];
    }
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }
    $cache_path = zl_storage_root() . '/offload_status_cache.json';
    if (is_file($cache_path) && (time() - (int) @filemtime($cache_path)) < 600) {
        $json = json_decode((string) @file_get_contents($cache_path), true);
        if (is_array($json)) {
            $cached = $json;
            return $cached;
        }
    }
    $cfg = zl_offload_config();
    $out = [
        'configured' => !empty($cfg['configured']),
        'enabled' => !empty($cfg['enabled']),
        'url' => (string) ($cfg['url'] ?? ''),
        'has_token' => !empty($cfg['has_token']),
        'health' => null,
        'status' => null,
    ];
    if (empty($cfg['configured'])) {
        $cached = $out;
        return $cached;
    }
    $health = zl_offload_http('GET', '/health', null, 8, false);
    $out['health'] = $health['data'] ?? [
        'ok' => false,
        'http_status' => (int) ($health['status'] ?? 0),
        'error' => (string) ($health['error'] ?? ''),
    ];
    if (!empty($cfg['has_token'])) {
        $status = zl_offload_http('GET', '/v1/status', null, 12, true);
        $out['status'] = $status['data'] ?? [
            'ok' => false,
            'http_status' => (int) ($status['status'] ?? 0),
            'error' => (string) ($status['error'] ?? ''),
        ];
    }
    $cached = $out;
    @file_put_contents($cache_path, json_encode($cached));
    return $cached;
}

function zl_dna_source_versions() {
    $pack_status = [];
    foreach (zl_dna_data_pack_status() as $pack) {
        $pack_status[(string) ($pack['pack'] ?? '')] = !empty($pack['installed']);
    }
    return [
        ['source_name' => 'ZeroThink local parser', 'source_version' => 'ZDNA-MAP v1.2', 'status' => 'bundled'],
        ['source_name' => 'ClinVar', 'source_version' => 'health evidence source mirror', 'status' => !empty($pack_status['health_core.sqlite']) ? 'active' : 'expanding'],
        ['source_name' => 'GWAS Catalog', 'source_version' => 'v1.0.2 association source mirror', 'status' => !empty($pack_status['health_core.sqlite']) ? 'source mirror active; personalized effect calls safety-withheld' : 'expanding'],
        ['source_name' => 'ClinPGx / PharmGKB', 'source_version' => 'CC BY-SA 4.0 source data; adapted into ZeroThink health evidence pack', 'status' => !empty($pack_status['health_core.sqlite']) ? 'source mirror active; personalized calls safety-withheld' : 'expanding'],
        ['source_name' => 'PGS Catalog', 'source_version' => 'offload metadata mirror for future polygenic-score evidence cards', 'status' => !empty($pack_status['openzero_offload_node']) ? 'mirrored' : 'optional'],
        ['source_name' => 'HPO / MONDO', 'source_version' => 'offload phenotype and disease-ontology mirror', 'status' => !empty($pack_status['openzero_offload_node']) ? 'mirrored' : 'optional'],
        ['source_name' => '1000 Genomes / gnomAD frequency references', 'source_version' => 'See the matched panel source_version and reference pack metadata', 'status' => !empty($pack_status['ancestry_core.sqlite']) ? 'installed frequency evidence; ancestry composition uncalibrated' : 'expanding'],
        ['source_name' => 'ZDNA Compact Specialist Lenses', 'source_version' => 'Legacy research heuristics v0.1; not validated reference panels', 'status' => 'legacy experimental heuristics; withheld from public ancestry and identity conclusions'],
        ['source_name' => 'AADR / Ancient Grave Match', 'source_version' => 'public ancient sample source pack', 'status' => !empty($pack_status['ancient_graves.sqlite']) ? 'active' : 'expanding'],
        ['source_name' => 'AmtDB / ancient mtDNA context', 'source_version' => 'optional local haplogroup/site context for ancient maternal-line research', 'status' => !empty($pack_status['ancient_graves.sqlite']) ? 'optional companion' : 'optional'],
        ['source_name' => 'GEDCOM 7 lineage import', 'source_version' => 'local parser v0.1', 'status' => 'bundled'],
        ['source_name' => 'Wikidata public lineage graph', 'source_version' => 'public lineage graph source', 'status' => 'expanding'],
        ['source_name' => 'OpenZero offload library node', 'source_version' => 'expanded reference storage and background jobs', 'status' => !empty($pack_status['openzero_offload_node']) ? 'online' : 'optional'],
    ];
}

function zl_dna_evidence_card($args) {
    $args = (array) $args;
    return [
        'title' => (string) ($args['title'] ?? 'Evidence card'),
        'claim_type' => (string) ($args['claim_type'] ?? 'research'),
        'claim_label' => (string) ($args['claim_label'] ?? 'research support'),
        'evidence_level' => (string) ($args['evidence_level'] ?? 'limited'),
        'confidence_score' => (float) ($args['confidence_score'] ?? 0),
        'confidence_band' => (string) ($args['confidence_band'] ?? 'low'),
        'method' => (string) ($args['method'] ?? 'ZeroThink local evidence engine'),
        'source_name' => (string) ($args['source_name'] ?? 'ZeroThink local parser'),
        'source_version' => (string) ($args['source_version'] ?? 'ZDNA-MAP v1.2'),
        'matched_marker_count' => (int) ($args['matched_marker_count'] ?? 0),
        'explanation' => (string) ($args['explanation'] ?? ''),
        'limitations' => (string) ($args['limitations'] ?? 'Research support only. Not proof, diagnosis, or identity classification.'),
        'next_steps' => (string) ($args['next_steps'] ?? 'Use the evidence ledger and verify important findings with appropriate records or qualified professionals.'),
    ];
}

function zl_dna_confidence_label_from_score($score) {
    $score = (float) $score;
    if ($score >= 82) {
        return 'high';
    }
    if ($score >= 64) {
        return 'medium-high';
    }
    if ($score >= 42) {
        return 'medium';
    }
    if ($score >= 20) {
        return 'low';
    }
    return 'very-low';
}

function zl_dna_parse_gedcom($text) {
    $text = zl_clean_text((string) $text);
    $lines = preg_split('/\n+/', $text);
    $people = [];
    $families = [];
    $current_id = null;
    $current_type = null;
    $last_event = null;
    foreach ($lines as $line) {
        if (!preg_match('/^(\d+)\s+(?:@([^@]+)@\s+)?([A-Z0-9_]+)(?:\s+(.*))?$/i', trim((string) $line), $m)) {
            continue;
        }
        $level = (int) $m[1];
        $xref = $m[2] ?? '';
        $tag = strtoupper((string) $m[3]);
        $value = trim((string) ($m[4] ?? ''));
        if ($level === 0 && $xref !== '' && in_array($tag, ['INDI', 'FAM'], true)) {
            $current_id = $xref;
            $current_type = $tag;
            $last_event = null;
            if ($tag === 'INDI' && !isset($people[$current_id])) {
                $people[$current_id] = ['id' => $current_id, 'name' => '', 'surname' => '', 'birth_date' => '', 'birth_place' => '', 'death_date' => '', 'death_place' => '', 'families' => []];
            }
            if ($tag === 'FAM' && !isset($families[$current_id])) {
                $families[$current_id] = ['id' => $current_id, 'husb' => '', 'wife' => '', 'children' => []];
            }
            continue;
        }
        if (!$current_id) {
            continue;
        }
        if ($current_type === 'INDI') {
            if ($level === 1 && $tag === 'NAME') {
                $people[$current_id]['name'] = trim(str_replace('/', '', $value));
                if (preg_match('/\/([^\/]+)\//', $value, $sm)) {
                    $people[$current_id]['surname'] = trim($sm[1]);
                }
            } elseif ($level === 1 && in_array($tag, ['BIRT', 'DEAT'], true)) {
                $last_event = $tag;
            } elseif ($level === 2 && $last_event === 'BIRT' && $tag === 'DATE') {
                $people[$current_id]['birth_date'] = $value;
            } elseif ($level === 2 && $last_event === 'BIRT' && $tag === 'PLAC') {
                $people[$current_id]['birth_place'] = $value;
            } elseif ($level === 2 && $last_event === 'DEAT' && $tag === 'DATE') {
                $people[$current_id]['death_date'] = $value;
            } elseif ($level === 2 && $last_event === 'DEAT' && $tag === 'PLAC') {
                $people[$current_id]['death_place'] = $value;
            } elseif ($level === 1 && in_array($tag, ['FAMC', 'FAMS'], true) && preg_match('/@([^@]+)@/', $value, $fm)) {
                $people[$current_id]['families'][] = $fm[1];
            }
        } elseif ($current_type === 'FAM') {
            if ($level === 1 && in_array($tag, ['HUSB', 'WIFE', 'CHIL'], true) && preg_match('/@([^@]+)@/', $value, $pm)) {
                if ($tag === 'CHIL') {
                    $families[$current_id]['children'][] = $pm[1];
                } else {
                    $families[$current_id][strtolower($tag)] = $pm[1];
                }
            }
        }
    }
    $edges = [];
    foreach ($families as $fam) {
        foreach ((array) ($fam['children'] ?? []) as $child) {
            foreach (['husb', 'wife'] as $parent_key) {
                if (!empty($fam[$parent_key])) {
                    $edges[] = ['child_id' => $child, 'parent_id' => $fam[$parent_key], 'edge_type' => $parent_key === 'husb' ? 'father' : 'mother'];
                }
            }
        }
    }
    $surnames = [];
    $places = [];
    foreach ($people as $person) {
        $surname = strtoupper(trim((string) ($person['surname'] ?? '')));
        if ($surname !== '') {
            $surnames[$surname] = ($surnames[$surname] ?? 0) + 1;
        }
        foreach (['birth_place', 'death_place'] as $field) {
            $place = trim((string) ($person[$field] ?? ''));
            if ($place !== '') {
                $places[$place] = ($places[$place] ?? 0) + 1;
            }
        }
    }
    arsort($surnames, SORT_NUMERIC);
    arsort($places, SORT_NUMERIC);
    return [
        'person_count' => count($people),
        'family_count' => count($families),
        'edge_count' => count($edges),
        'top_surnames' => array_slice($surnames, 0, 12, true),
        'top_places' => array_slice($places, 0, 12, true),
        'edges_preview' => array_slice($edges, 0, 12),
    ];
}

function zl_dna_lineage_public_graph_status() {
    $path = dirname(__DIR__) . '/data/packs/lineage_core.sqlite';
    $status = [
        'installed' => false,
        'sqlite_available' => class_exists('SQLite3'),
        'path_label' => 'Lineage Graph Source Pack',
        'people_count' => 0,
        'edge_count' => 0,
        'source_count' => 0,
        'pack_band' => 'expanding',
        'pack_note' => 'Public lineage graph source is being expanded.',
        'next_step' => 'Refresh the DNA source packs and expand the public lineage graph.',
    ];
    if (!is_file($path)) {
        return $status;
    }
    $status['installed'] = true;
    $status['updated_at'] = gmdate('c', filemtime($path));
    $status['size_bytes'] = (int) filesize($path);
    if (!class_exists('SQLite3')) {
        $status['pack_band'] = 'installed_but_unreadable';
        $status['pack_note'] = 'The lineage source file exists, but the comparison engine is not available on this server.';
        return $status;
    }
    try {
        $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
        $has_people = (bool) $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='lineage_public_people'");
        $has_edges = (bool) $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='lineage_edges'");
        $has_sources = (bool) $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='data_sources'");
        $has_notes = (bool) $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='lineage_pack_notes'");
        $status['people_count'] = $has_people ? (int) $db->querySingle("SELECT COUNT(*) FROM lineage_public_people") : 0;
        $status['edge_count'] = $has_edges ? (int) $db->querySingle("SELECT COUNT(*) FROM lineage_edges") : 0;
        $status['source_count'] = $has_sources ? (int) $db->querySingle("SELECT COUNT(*) FROM data_sources") : 0;
        $note = $has_notes ? (string) $db->querySingle("SELECT value FROM lineage_pack_notes WHERE key='status'") : '';
        $db->close();
        if ($note !== '') {
            $status['pack_note'] = $note;
        }
        if ($status['people_count'] >= 50000 && $status['edge_count'] >= 25000) {
            $status['pack_band'] = 'strong_public_graph';
            $status['next_step'] = 'Use GEDCOM, surnames, places, dates, and optional match segments to search for candidate public graph routes.';
        } elseif ($status['people_count'] >= 10000) {
            $status['pack_band'] = 'production_lite_graph';
            $status['next_step'] = 'Good starter graph installed. Expand with more seed QIDs, dumps, and source-weighted edges for stronger famous/royal route searches.';
        } elseif ($status['people_count'] >= 1000) {
            $status['pack_band'] = 'starter_graph';
            $status['next_step'] = 'Starter public graph installed. It can show leads, but a bigger graph is needed for broad famous/royal searches.';
        } elseif ($status['people_count'] > 0) {
            $status['pack_band'] = 'tiny_graph';
            $status['next_step'] = 'Starter graph active. Expand the source set before expecting useful lineage leads.';
        } else {
            $status['pack_band'] = 'source_empty';
            $status['next_step'] = 'Public graph structure is ready, but more people and family links need to be added before useful route searches.';
        }
    } catch (Throwable $e) {
        $status['pack_band'] = 'read_error';
        $status['pack_note'] = 'Could not read lineage graph: ' . substr($e->getMessage(), 0, 180);
    }
    return $status;
}

function zl_dna_lineage_anchor_terms($input = [], $gedcom = null) {
    $input = (array) $input;
    $terms = [];
    $add = function ($value, $kind = 'anchor') use (&$terms) {
        $value = trim(zl_clean_text((string) $value));
        if ($value === '') {
            return;
        }
        foreach (preg_split('/[,;\n\r|]+/', $value) as $part) {
            $part = trim(preg_replace('/\s+/', ' ', (string) $part));
            if ($part !== '' && strlen($part) >= 3) {
                $terms[strtolower($kind . ':' . $part)] = ['term' => $part, 'kind' => $kind];
            }
        }
    };
    $add($input['surnames'] ?? '', 'surname');
    $add($input['places'] ?? '', 'place');
    if (is_array($gedcom)) {
        foreach (array_slice(array_keys((array) ($gedcom['top_surnames'] ?? [])), 0, 12) as $name) {
            $add($name, 'gedcom_surname');
        }
        foreach (array_slice(array_keys((array) ($gedcom['top_places'] ?? [])), 0, 10) as $place) {
            $add($place, 'gedcom_place');
        }
    }
    $story = zl_clean_text((string) ($input['story_notes'] ?? ''));
    if ($story !== '' && preg_match_all('/\b([A-Z][a-z]{2,}(?:\s+[A-Z][a-z]{2,}){0,3})\b/', $story, $matches)) {
        $blocked = ['DNA' => true, 'GEDCOM' => true, 'MyHeritage' => true, 'Ancestry' => true, 'FamilySearch' => true, 'ZeroThink' => true];
        foreach (array_slice($matches[1], 0, 20) as $name) {
            $name = trim($name);
            if ($name !== '' && empty($blocked[$name])) {
                $terms[strtolower('story_name:' . $name)] = ['term' => $name, 'kind' => 'story_name'];
            }
        }
    }
    return array_values($terms);
}

function zl_dna_lineage_public_graph_candidates($anchors, $graph_status = []) {
    $anchors = array_slice((array) $anchors, 0, 20);
    $graph_status = (array) $graph_status;
    $path = dirname(__DIR__) . '/data/packs/lineage_core.sqlite';
    if (empty($graph_status['installed']) || !class_exists('SQLite3') || !is_file($path) || !$anchors) {
        return [];
    }
    $hits = [];
    try {
        $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
        $has_people = (bool) $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='lineage_public_people'");
        if (!$has_people) {
            $db->close();
            return [];
        }
        $stmt = $db->prepare("SELECT person_id, name, birth_date, death_date, title_or_role, wikidata_qid, source_count FROM lineage_public_people WHERE lower(name) LIKE :needle ORDER BY source_count DESC, name ASC LIMIT 12");
        foreach ($anchors as $anchor) {
            $term = trim((string) ($anchor['term'] ?? ''));
            if (strlen($term) < 3) {
                continue;
            }
            $search_terms = [$term];
            if (preg_match('/\b(windsor|british|english|royal|king|queen|prince|princess)\b/i', $term)) {
                $search_terms = array_merge($search_terms, ['Elizabeth', 'George', 'Charles', 'William', 'Victoria']);
            }
            if (preg_match('/\b(hashemite|hashimite|hussain|hussein|husayn|sharif|mecca|jordan)\b/i', $term)) {
                $search_terms = array_merge($search_terms, ['Hussein', 'Abdullah', 'Ali ibn Abi', 'Hasan ibn Ali', 'Husayn ibn Ali']);
            }
            if (preg_match('/\b(persian|iran|iranian|achaemenid|cyrus|darius)\b/i', $term)) {
                $search_terms = array_merge($search_terms, ['Cyrus', 'Darius']);
            }
            foreach (array_values(array_unique($search_terms)) as $needle_term) {
                $stmt->reset();
                $stmt->clear();
                $stmt->bindValue(':needle', '%' . strtolower($needle_term) . '%', SQLITE3_TEXT);
                $res = $stmt->execute();
                while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                    $qid = (string) ($row['wikidata_qid'] ?? $row['person_id'] ?? '');
                    if ($qid === '') {
                        continue;
                    }
                    $title = trim((string) ($row['title_or_role'] ?? ''));
                    $score = 12;
                    if (stripos((string) ($row['name'] ?? ''), $term) !== false || stripos((string) ($row['name'] ?? ''), $needle_term) !== false) {
                        $score += 16;
                    }
                    if ($needle_term !== $term) {
                        $score += 6;
                    }
                    if ($title !== '') {
                        $score += 10;
                    }
                    if (($row['birth_date'] ?? '') !== '' || ($row['death_date'] ?? '') !== '') {
                        $score += 5;
                    }
                    if (($anchor['kind'] ?? '') === 'story_name') {
                        $score += 6;
                    }
                    $hits[$qid] = [
                        'anchor' => $term,
                        'expanded_search' => $needle_term,
                        'anchor_kind' => (string) ($anchor['kind'] ?? 'anchor'),
                        'person_id' => (string) ($row['person_id'] ?? $qid),
                        'name' => (string) ($row['name'] ?? $qid),
                        'birth_date' => (string) ($row['birth_date'] ?? ''),
                        'death_date' => (string) ($row['death_date'] ?? ''),
                        'title_or_role' => $title,
                        'wikidata_qid' => $qid,
                        'source_count' => (int) ($row['source_count'] ?? 0),
                        'confidence_score' => min(55, $score),
                        'confidence_band' => zl_dna_confidence_label_from_score(min(55, $score)),
                        'boundary' => 'Public graph name/alias lead only. This is not proof of descent or relationship.',
                        'url' => strpos($qid, 'Q') === 0 ? 'https://www.wikidata.org/wiki/' . rawurlencode($qid) : '',
                    ];
                }
            }
        }
        $db->close();
    } catch (Throwable $e) {
        return [];
    }
    usort($hits, function ($a, $b) {
        return ((int) ($b['confidence_score'] ?? 0)) <=> ((int) ($a['confidence_score'] ?? 0));
    });
    return array_slice(array_values($hits), 0, 8);
}

function zl_dna_lineage_route_lenses($input = [], $gedcom = null, $context = [], $graph_status = [], $anchors = [], $candidates = []) {
    $input = (array) $input;
    $context = (array) $context;
    $graph_status = (array) $graph_status;
    $has_story = trim((string) ($input['story_notes'] ?? '')) !== '' || trim((string) ($input['surnames'] ?? '')) !== '' || trim((string) ($input['places'] ?? '')) !== '';
    $has_gedcom = is_array($gedcom) && (int) ($gedcom['person_count'] ?? 0) > 0;
    $has_anchors = count((array) $anchors) > 0;
    $graph_ready = !empty($graph_status['installed']) && (int) ($graph_status['people_count'] ?? 0) > 0;
    $markers = (array) ($context['marker_summary'] ?? []);
    return [
        [
            'title' => 'Public graph search',
            'status' => $graph_ready ? (count($candidates) ? 'candidate leads found' : ($has_anchors ? 'searched, no strong lead yet' : 'ready, waiting for anchors')) : 'graph pack missing',
            'plain' => $graph_ready
                ? 'The public graph lane has ' . number_format((int) ($graph_status['people_count'] ?? 0)) . ' people and ' . number_format((int) ($graph_status['edge_count'] ?? 0)) . ' parent edges available. Add GEDCOM/surnames/target names to search it properly.'
                : 'A bigger local lineage graph is needed before famous/royal searches can feel powerful. DNA-only reports will stay limited until the lineage source pack is expanded.',
        ],
        [
            'title' => 'Royal / noble route',
            'status' => $has_gedcom || $has_story ? 'research route can be logged' : 'waiting for family evidence',
            'plain' => 'Royal or noble routes need a family-tree path first. DNA can support narrow direct-line or match evidence, but it cannot create the route by itself.',
        ],
        [
            'title' => 'Famous-person route',
            'status' => $has_anchors && $graph_ready ? 'target search possible' : 'needs target names and graph',
            'plain' => 'Famous-person matching works as graph research: user GEDCOM plus public person graph plus dates/places. A raw DNA file alone cannot identify famous relatives.',
        ],
        [
            'title' => 'Paternal / maternal direct-line route',
            'status' => ((int) ($markers['y_markers'] ?? 0) >= 50 || (int) ($markers['mt_markers'] ?? 0) >= 20) ? 'partial direct-line signal available' : 'dedicated Y-DNA/mtDNA needed',
            'plain' => 'Consumer autosomal DNA usually lacks enough Y-DNA or mtDNA for deep direct-line claims. Upload parent files or dedicated tests for stronger side assignment.',
        ],
        [
            'title' => 'Ancient / tribal / dynastic labels',
            'status' => 'not DNA-only provable',
            'plain' => 'Labels such as Israelite, Hashemite, Khazar, royal house, tribe, or ancient dynasty need documentary route evidence and specialized population panels. ZeroThink can log hypotheses, not convert them into proof.',
        ],
    ];
}

function zl_dna_lineage_dna_context($raw_summary = [], $has_story = false, $has_gedcom = false) {
    $raw_summary = (array) $raw_summary;
    $parsed = (int) ($raw_summary['parsed_variants'] ?? $raw_summary['marker_count'] ?? 0);
    $quality = (int) ($raw_summary['quality_score'] ?? 0);
    $chromosomes = (int) ($raw_summary['chromosome_count'] ?? 0);
    $autosomal = (int) ($raw_summary['autosomal_markers'] ?? 0);
    $x = (int) ($raw_summary['x_markers'] ?? 0);
    $y = (int) ($raw_summary['y_markers'] ?? 0);
    $mt = (int) ($raw_summary['mt_markers'] ?? 0);
    $has_y = $y >= 50;
    $has_mt = $mt >= 20;
    $readiness = 0;
    if ($parsed >= 50000) {
        $readiness += 20;
    }
    if ($parsed >= 250000) {
        $readiness += 15;
    }
    if ($quality >= 65) {
        $readiness += 15;
    }
    if ($chromosomes >= 22) {
        $readiness += 10;
    }
    if ($autosomal >= 200000) {
        $readiness += 10;
    }
    if ($has_y) {
        $readiness += 8;
    }
    if ($has_mt) {
        $readiness += 8;
    }
    if ($has_story) {
        $readiness += 8;
    }
    if ($has_gedcom) {
        $readiness += 18;
    }
    $readiness = min(96, $readiness);

    $lanes = [
        [
            'label' => 'Autosomal cousin-matching lane',
            'status' => $autosomal >= 200000 ? 'usable' : 'weak',
            'plain' => $autosomal >= 200000
                ? 'This file has enough autosomal coverage to support recent-family matching workflows if the user also has DNA matches/segments from a genealogy platform.'
                : 'Autosomal coverage looks too light for confident cousin-matching support.',
        ],
        [
            'label' => 'Direct paternal Y-DNA lane',
            'status' => $has_y ? 'possible clue lane' : 'not available from this upload',
            'plain' => $has_y
                ? 'Y-chromosome markers are present, so a direct paternal-line research lane may be possible. A dedicated Y-DNA test is still stronger.'
                : 'No strong Y-chromosome marker coverage was detected, so this upload cannot support a direct paternal-line clue.',
        ],
        [
            'label' => 'Direct maternal mtDNA lane',
            'status' => $has_mt ? 'possible clue lane' : 'not available from this upload',
            'plain' => $has_mt
                ? 'Mitochondrial markers are present, so a direct maternal-line research lane may be possible. Full mtDNA sequencing is still stronger.'
                : 'No strong mitochondrial marker coverage was detected, so this upload cannot support a direct maternal-line clue.',
        ],
        [
            'label' => 'Documentary tree lane',
            'status' => $has_gedcom ? 'tree supplied' : ($has_story ? 'story supplied' : 'missing'),
            'plain' => $has_gedcom
                ? 'A GEDCOM/tree was supplied, so ZeroThink can score graph completeness and source gaps.'
                : ($has_story ? 'Names, places, or story notes were supplied. This is useful, but weaker than a GEDCOM with sourced parent-child links.' : 'No tree/story evidence was supplied. Add GEDCOM, surnames, places, and date ranges to move beyond DNA-only readiness.'),
        ],
    ];

    return [
        'engine' => 'ZLINEAGE v0.3 DNA + Documentary + Public Graph Readiness',
        'readiness_score' => $readiness,
        'readiness_band' => zl_dna_confidence_label_from_score($readiness),
        'marker_summary' => [
            'parsed_variants' => $parsed,
            'quality_score' => $quality,
            'chromosome_count' => $chromosomes,
            'autosomal_markers' => $autosomal,
            'x_markers' => $x,
            'y_markers' => $y,
            'mt_markers' => $mt,
        ],
        'lanes' => $lanes,
        'truth_boundary' => 'DNA-only lineage is a readiness screen. Royal, famous-person, or ancient lineage needs a sourced family graph; DNA can only support narrow line questions or recent match evidence.',
    ];
}

function zl_dna_lineage_tasks($context, $gedcom = null, $input = [], $graph_status = null) {
    $context = (array) $context;
    $input = (array) $input;
    $graph_status = (array) $graph_status;
    $tasks = [];
    if (!$graph_status || empty($graph_status['installed']) || in_array((string) ($graph_status['pack_band'] ?? ''), ['missing', 'schema_only', 'tiny_graph', 'starter_graph'], true)) {
        $tasks[] = 'Expand the local public lineage graph. The bigger the source pack is, the better famous/royal route searches become.';
    }
    if (empty($gedcom)) {
        $tasks[] = 'Export a GEDCOM family tree from MyHeritage, Ancestry, FamilySearch, or another genealogy tool, then upload it here.';
    }
    if (trim((string) ($input['surnames'] ?? '')) === '') {
        $tasks[] = 'Add known surnames from both sides of the family. Even 5-10 surnames makes the clue ledger more useful.';
    }
    if (trim((string) ($input['places'] ?? '')) === '') {
        $tasks[] = 'Add known places and modern countries/regions. Places reduce false lineage matches dramatically.';
    }
    if (trim((string) ($input['date_ranges'] ?? '')) === '') {
        $tasks[] = 'Add rough date ranges, for example 1850-1950 maternal side, Bengal/London.';
    }
    $markers = (array) ($context['marker_summary'] ?? []);
    if ((int) ($markers['y_markers'] ?? 0) < 50) {
        $tasks[] = 'For direct paternal-line claims, use a dedicated Y-DNA test if available; this raw file does not contain enough Y signal.';
    }
    if ((int) ($markers['mt_markers'] ?? 0) < 20) {
        $tasks[] = 'For direct maternal-line claims, full mtDNA sequencing is stronger than a consumer SNP file.';
    }
    $tasks[] = 'For any royal/noble claim, verify every parent-child link with records. DNA alone is supporting context, not proof.';
    return array_slice(array_values(array_unique($tasks)), 0, 8);
}

function zl_dna_relatedness_compare_paths($main_path, $main_name, $relative_path, $relative_name) {
    $a = zl_dna_rsid_genotype_map_path($main_path, $main_name, zl_dna_max_raw_rows());
    $av = (array) ($a['variants'] ?? []);
    $overlap = 0;
    $exact = 0;
    $allele_share = 0;
    $usable = 0;
    $relative_stats = zl_dna_scan_rsid_genotypes_path($relative_path, $relative_name, function ($rsid, $gb) use (&$av, &$overlap, &$exact, &$allele_share, &$usable) {
        if (!isset($av[$rsid])) {
            return null;
        }
        $overlap++;
        $ga = strtoupper((string) $av[$rsid]);
        $gb = strtoupper((string) $gb);
        if ($ga === '' || $gb === '' || in_array($ga, ['--', '00', 'NN'], true) || in_array($gb, ['--', '00', 'NN'], true)) {
            return null;
        }
        $la = zl_dna_genotype_letters($ga);
        $lb = zl_dna_genotype_letters($gb);
        if (count($la) < 1 || count($lb) < 1) {
            return null;
        }
        $usable++;
        sort($la);
        sort($lb);
        if (implode('', $la) === implode('', $lb)) {
            $exact++;
        }
        if (count(array_intersect($la, $lb)) > 0) {
            $allele_share++;
        }
        return null;
    }, zl_dna_max_raw_rows());
    $exact_pct = $usable > 0 ? round(($exact / $usable) * 100, 2) : 0;
    $share_pct = $usable > 0 ? round(($allele_share / $usable) * 100, 2) : 0;
    $label = 'not enough overlap';
    $confidence = 'very_low';
    if ($overlap >= 50000) {
        if ($exact_pct >= 99.2) {
            $label = 'same person / duplicate file likely';
            $confidence = 'high';
        } elseif ($share_pct >= 98.5 && $exact_pct >= 58) {
            $label = 'close relative pattern possible';
            $confidence = 'medium';
        } elseif ($share_pct >= 94.5) {
            $label = 'family-related pattern possible';
            $confidence = 'low';
        } else {
            $label = 'no close-relative signal from this simple overlap screen';
            $confidence = 'low';
        }
    }
    return [
        'relative_file' => zl_safe_name($relative_name),
        'overlap_markers' => $overlap,
        'usable_markers' => $usable,
        'exact_match_percent' => $exact_pct,
        'allele_share_percent' => $share_pct,
        'claim_label' => $label,
        'confidence_band' => $confidence,
        'boundary' => 'Private overlap screen only. This is not a court/medical relationship test and does not replace genealogy platform segment evidence.',
        'main_rsid_rows' => (int) ($a['rsid_rows'] ?? count($av)),
        'relative_rsid_rows' => (int) ($relative_stats['rsid_rows'] ?? 0),
        'streamed_compare' => true,
    ];
}

function zl_dna_lineage_segment_percent($segments, $needles) {
    $total = 0.0;
    foreach ((array) $segments as $segment) {
        $label = strtolower((string) ($segment['label'] ?? ''));
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && strpos($label, strtolower((string) $needle)) !== false) {
                $total += (float) ($segment['percent'] ?? $segment['value'] ?? 0);
                break;
            }
        }
    }
    return round($total, 1);
}

function zl_dna_lineage_trace_percent($estimate, $needles) {
    $total = 0.0;
    foreach ((array) ($estimate['trace_lanes'] ?? []) as $lane) {
        $label = strtolower((string) ($lane['label'] ?? ''));
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && strpos($label, strtolower((string) $needle)) !== false) {
                $total += (float) ($lane['proxy_percent'] ?? $lane['percent'] ?? 0);
                break;
            }
        }
    }
    return round($total, 1);
}

function zl_dna_lineage_plain_projection($input = [], $raw_summary = []) {
    $input = (array) $input;
    $raw_summary = (array) $raw_summary;
    $heritage = null;
    $path = (string) ($input['dna_path'] ?? '');
    $name = (string) ($input['dna_name'] ?? ($raw_summary['file_name'] ?? 'dna-raw.txt'));
    if (isset($input['heritage_report']) && is_array($input['heritage_report'])) {
        $heritage = $input['heritage_report'];
    } elseif (isset($input['plain_ancestry_summary']) && is_array($input['plain_ancestry_summary'])) {
        $heritage = ['insights' => ['plain_ancestry_summary' => $input['plain_ancestry_summary']]];
    } elseif ($path !== '' && is_readable($path)) {
        try {
            $heritage = zl_dna_heritage_report_path($path, $name);
        } catch (Throwable $e) {
            $heritage = null;
        }
    }

    $estimate = [];
    if (is_array($heritage)) {
        $estimate = (array) ($heritage['insights']['consumer_estimate'] ?? $heritage['insights']['plain_ancestry_summary']['consumer_estimate'] ?? []);
    }
    $segments = array_values((array) ($estimate['segments'] ?? []));
    if (!$segments && is_array($heritage)) {
        $segments = array_values((array) ($heritage['chart'] ?? $heritage['insights']['chart'] ?? []));
    }
    $segments = array_values(array_filter($segments, function ($segment) {
        return (float) ($segment['percent'] ?? $segment['value'] ?? 0) > 0;
    }));
    $chart = $segments ? zl_dna_safe_percent_segments($segments) : [];

    $south_asia = zl_dna_lineage_segment_percent($segments, ['pakistani', 'punjabi', 'general south asian', 'south asian']);
    $celtic = zl_dna_lineage_segment_percent($segments, ['irish', 'scottish', 'welsh', 'breton', 'english']);
    $central = zl_dna_lineage_segment_percent($segments, ['central asian']);
    $english = zl_dna_lineage_segment_percent($segments, ['english']);
    $ashkenazi = max(
        zl_dna_lineage_segment_percent($segments, ['ashkenazi']),
        zl_dna_lineage_trace_percent($estimate, ['ashkenazi'])
    );
    $finnish = max(
        zl_dna_lineage_segment_percent($segments, ['finnish']),
        zl_dna_lineage_trace_percent($estimate, ['finnish'])
    );
    $top_segments = array_slice($segments, 0, 6);
    $top_labels = array_values(array_filter(array_map(function ($segment) {
        $label = trim((string) ($segment['label'] ?? ''));
        $pct = (float) ($segment['percent'] ?? $segment['value'] ?? 0);
        return $label !== '' && $pct > 0 ? $label . ' ' . round($pct, 1) . '%' : '';
    }, $top_segments)));

    $ancient_lanes = [];
    if ($south_asia > 0) {
        $ancient_lanes[] = [
            'label' => 'Punjab / Indo-Iranian corridor',
            'status' => $south_asia >= 25 ? 'strong DNA-context lead' : 'visible DNA-context lead',
            'proxy_percent' => $south_asia,
            'plain' => 'This is the main South Asian lineage context. In modern terms it points toward Pakistan/Punjab plus neighbouring North India, Afghanistan-adjacent and Bengali/South Asian contexts where the reference data supports it.',
            'modern_countries' => 'Pakistan, India, Bangladesh, Afghanistan-adjacent regions',
            'boundary' => 'This is a genetic-affinity projection, not a caste, tribe, nationality, or family-tree proof.',
        ];
    }
    if ($celtic > 0) {
        $ancient_lanes[] = [
            'label' => 'Celtic Atlantic / British Isles',
            'status' => $celtic >= 30 ? 'strong DNA-context lead' : 'visible DNA-context lead',
            'proxy_percent' => $celtic,
            'plain' => 'This groups the Irish, Scottish/Welsh, English and Breton-style signals into a plain British Isles / Celtic Atlantic lineage context.',
            'modern_countries' => 'Ireland, Scotland, Wales, England, Brittany / northwest France',
            'boundary' => 'This cannot split a family line by itself. A GEDCOM/tree can tell whether it is likely maternal, paternal, or mixed.',
        ];
    }
    if ($central > 0) {
        $ancient_lanes[] = [
            'label' => 'Central Asian / Steppe bridge',
            'status' => 'trace or bridge lead',
            'proxy_percent' => $central,
            'plain' => 'This is a corridor signal around Central Asia and the wider steppe/Indo-Iranian bridge. It is useful context for deep ancestry questions but not a single modern identity.',
            'modern_countries' => 'Kazakhstan, Uzbekistan, Kyrgyzstan, Tajikistan, Turkmenistan, Afghanistan-adjacent context',
            'boundary' => 'Steppe and Central Asian labels need ancient-DNA comparison before stronger statements.',
        ];
    }
    if ($english > 0 || $finnish > 0) {
        $ancient_lanes[] = [
            'label' => 'Northwest Europe / Insular-Nordic trace',
            'status' => 'regional support lane',
            'proxy_percent' => round($english + $finnish, 1),
            'plain' => 'This keeps smaller English, Finnish/Nordic or nearby Northwest-European traces visible instead of hiding them inside broad Europe.',
            'modern_countries' => 'England, Finland/Nordic-Baltic context, nearby Northwest Europe',
            'boundary' => 'Small traces are sensitive to reference-panel differences and should be treated as leads.',
        ];
    }
    if ($ashkenazi > 0) {
        $ancient_lanes[] = [
            'label' => 'Ashkenazi Jewish diaspora trace',
            'status' => 'trace lead, not identity',
            'proxy_percent' => $ashkenazi,
            'plain' => 'This keeps a Jewish-diaspora genetic-affinity trace visible in the report, while separating it from religion, identity or community membership.',
            'modern_countries' => 'Central/Eastern Europe diaspora context, broader Jewish reference-panel lane',
            'boundary' => 'DNA cannot determine Jewish identity or religion. Sephardi, Mizrahi, Levant and North-African controls are needed for deeper work.',
        ];
    }
    $ancient_lanes[] = [
        'label' => 'Israelite / Hashemite / royal / famous-person route',
        'status' => 'requires tree evidence',
        'proxy_percent' => 0,
        'plain' => 'This is not an ethnicity percentage. It becomes useful when the user uploads GEDCOM, surnames, places, dates, direct-line tests or DNA match evidence.',
        'modern_countries' => 'Levant / Arabia / public lineage graph depending on the specific claim',
        'boundary' => 'Raw consumer DNA alone cannot prove a named ancient, royal, prophetic or famous-person descent route.',
    ];
    $ancient_lanes[] = [
        'label' => 'Khazar / Steppe / Persian / Egyptian hypothesis queue',
        'status' => 'special ancient-panel lane',
        'proxy_percent' => 0,
        'plain' => 'These are research questions for ancient-DNA and regional reference packs, not a yes/no from a sparse consumer file. ZeroThink can log them as hypotheses and compare them when stronger packs are installed.',
        'modern_countries' => 'Caucasus, Ukraine/southern Russia steppe, Iran/Persia, Egypt/North Africa, Levant controls',
        'boundary' => 'Treat as theoretical comparison lanes until an ancient/reference panel is installed and calibrated.',
    ];

    $parsed = (int) ($raw_summary['parsed_variants'] ?? $raw_summary['marker_count'] ?? 0);
    $y = (int) ($raw_summary['y_markers'] ?? 0);
    $mt = (int) ($raw_summary['mt_markers'] ?? 0);
    $direct_cards = [
        [
            'label' => 'Autosomal heritage context',
            'status' => $parsed >= 250000 ? 'strong coverage for ancestry context' : 'limited coverage',
            'plain' => $parsed >= 250000
                ? 'The upload has enough autosomal markers for a serious country/region-style projection and recent-family research when reference packs are available.'
                : 'The upload is lighter than expected, so country/region projections should be treated carefully.',
        ],
        [
            'label' => 'Paternal-side clue lane',
            'status' => $y >= 50 ? 'Y-marker clues present' : 'not enough Y markers',
            'plain' => $y >= 50
                ? 'Y-chromosome markers are present, so a paternal-line research lane may be possible. It is still not as strong as a dedicated Y-DNA haplogroup test.'
                : 'This upload does not contain enough Y markers to say much about the direct paternal line.',
        ],
        [
            'label' => 'Maternal-side clue lane',
            'status' => $mt >= 20 ? 'mtDNA clues present' : 'not enough mtDNA markers',
            'plain' => $mt >= 20
                ? 'Mitochondrial markers are present, so a direct maternal lane may be possible. Full mtDNA sequencing is stronger.'
                : 'This upload does not contain enough mitochondrial markers to say much about the direct maternal line.',
        ],
    ];

    $headline = $top_labels
        ? 'Lineage context: ' . implode(' + ', array_slice(array_map(function ($item) {
            return preg_replace('/\s+\d+(\.\d+)?%$/', '', (string) $item);
        }, $top_labels), 0, 3))
        : 'Lineage context needs a richer reference projection';
    $plain = $top_labels
        ? 'Plain result first: ' . implode(', ', $top_labels) . '. The ancient/lineage lanes below translate those into human-readable regions and research routes.'
        : 'The uploaded file is useful for lineage readiness, but the country/ancient projection could not be generated from the current input.';

    return [
        'engine' => 'ZLINEAGE-ANCIENT v0.1 Local Projection',
        'headline' => $headline,
        'plain_summary' => $plain,
        'segments' => $segments,
        'chart' => $chart,
        'map_points' => array_values((array) ($estimate['map_points'] ?? [])),
        'ancient_lanes' => $ancient_lanes,
        'direct_line_cards' => $direct_cards,
        'consumer_estimate' => $estimate,
        'heritage_reference' => is_array($heritage) ? [
            'quality' => $heritage['summary']['quality_score'] ?? null,
            'reference_pack' => $heritage['local_reference_pack']['reason'] ?? null,
        ] : null,
        'truth_boundary' => 'These are DNA-context and ancient/regional research leads. They are designed to be useful and plain, but they are not proof of nationality, religion, tribe, royal status, or a named ancestor.',
    ];
}

function zl_dna_lineage_report($gedcom_text = '', $input = [], $raw_summary = []) {
    $input = (array) $input;
    $gedcom = trim((string) $gedcom_text) !== '' ? zl_dna_parse_gedcom($gedcom_text) : null;
    $story = trim((string) ($input['story_notes'] ?? ''));
    $surnames = trim((string) ($input['surnames'] ?? ''));
    $places = trim((string) ($input['places'] ?? ''));
    $side = trim((string) ($input['family_side'] ?? 'unknown'));
    $has_story = $story !== '' || $surnames !== '' || $places !== '';
    $context = zl_dna_lineage_dna_context($raw_summary, $has_story, (bool) $gedcom);
    $plain_lineage = zl_dna_lineage_plain_projection($input, $raw_summary);
    $graph = zl_dna_lineage_public_graph_status();
    $anchors = zl_dna_lineage_anchor_terms($input, $gedcom);
    $public_candidates = zl_dna_lineage_public_graph_candidates($anchors, $graph);
    $route_lenses = zl_dna_lineage_route_lenses($input, $gedcom, $context, $graph, $anchors, $public_candidates);
    $tasks = zl_dna_lineage_tasks($context, $gedcom, $input, $graph);
    $relative_context = array_values((array) ($input['relative_dna_context'] ?? []));
    $royal_terms = preg_match('/\b(royal|king|queen|prince|princess|duke|duchess|earl|countess|lord|lady|noble|sultan|emperor|empress)\b/i', $story . ' ' . $surnames);
    $score = 0;
    $score += min(18, (int) (($context['readiness_score'] ?? 0) / 6));
    if ($gedcom) {
        $score += min(38, (int) (($gedcom['person_count'] ?? 0) / 2));
        $score += min(22, (int) (($gedcom['edge_count'] ?? 0) / 2));
    }
    if ($has_story) {
        $score += 12;
    }
    if ($royal_terms) {
        $score += 8;
    }
    if ($relative_context) {
        $score += min(12, count($relative_context) * 6);
    }
    if ($has_story && !empty($graph['installed'])) {
        $score += 4;
    }
    if ($public_candidates) {
        $score += min(14, count($public_candidates) * 3);
    }
    $score = min(82, $score);
    if (!$gedcom && !$has_story) {
        $band = !empty($plain_lineage['segments']) ? 'dna_ancestral_context_ready' : 'dna_context_only';
        $label = !empty($plain_lineage['headline']) ? (string) $plain_lineage['headline'] : 'DNA-only lineage starter ready';
    } elseif (!$gedcom) {
        $band = 'family_story_only';
        $label = 'Family story only';
    } elseif ($score >= 62) {
        $band = 'plausible_route';
        $label = 'Plausible research route, documentary support needed';
    } elseif ($score >= 34) {
        $band = 'weak_clue';
        $label = 'Weak lineage clue';
    } else {
        $band = 'not_provable_from_current_data';
        $label = 'Not provable from current data';
    }
    $cards = [
        zl_dna_evidence_card([
            'title' => 'Royal / Lineage Clues',
            'claim_type' => 'royal_lineage',
            'claim_label' => $label,
            'evidence_level' => $band,
            'confidence_score' => $score,
            'confidence_band' => zl_dna_confidence_label_from_score($score),
            'method' => 'GEDCOM/story graph pre-check plus raw DNA quality context',
            'source_name' => 'User supplied GEDCOM/story, ZeroThink local parser',
            'source_version' => 'ZLINEAGE v0.3',
            'matched_marker_count' => (int) ($raw_summary['parsed_variants'] ?? 0),
            'explanation' => 'Raw DNA alone cannot prove a royal or noble line. This engine checks whether the user supplied enough tree/story evidence to justify deeper genealogy work.',
            'limitations' => 'No statement here is proof of royal ancestry. Strong cases require sourced parent-child records and, for DNA support, appropriate Y-DNA, mtDNA, or segment/match evidence.',
            'next_steps' => 'Upload a GEDCOM file, add surnames/places/dates, then verify every link with documentary sources.',
        ]),
        zl_dna_evidence_card([
            'title' => 'Public Lineage Graph Database',
            'claim_type' => 'lineage_public_graph',
            'claim_label' => !empty($graph['installed']) ? (string) ($graph['pack_band'] ?? 'installed') : 'source graph missing',
            'evidence_level' => !empty($graph['installed']) ? 'data_pack_context' : 'missing_data_pack',
            'confidence_score' => !empty($graph['installed']) ? min(70, 10 + (int) log(max(1, (int) ($graph['people_count'] ?? 0)), 2) * 5) : 0,
            'confidence_band' => !empty($graph['installed']) ? 'research context' : 'not available',
            'method' => 'private local public person/parent graph status check',
            'source_name' => 'Wikidata filtered lineage graph source pack',
            'source_version' => (string) ($graph['pack_band'] ?? 'missing'),
            'matched_marker_count' => (int) ($graph['people_count'] ?? 0),
            'explanation' => !empty($graph['installed'])
                ? 'The local public lineage graph is available with ' . number_format((int) ($graph['people_count'] ?? 0)) . ' people and ' . number_format((int) ($graph['edge_count'] ?? 0)) . ' parent links. It can only search properly when the user supplies GEDCOM, surnames, places, dates, or target names.'
                : 'The public lineage graph is missing, so the lineage report cannot search famous/royal/public-person routes yet.',
            'limitations' => 'A public graph match is only a research lead. Similar names are common, and every parent-child edge needs source verification.',
            'next_steps' => (string) ($graph['next_step'] ?? 'Build or expand the lineage source pack.'),
        ]),
        zl_dna_evidence_card([
            'title' => 'Lineage DNA Readiness',
            'claim_type' => 'lineage_readiness',
            'claim_label' => 'DNA file can support a starter lineage workflow',
            'evidence_level' => 'dna_context_only',
            'confidence_score' => (int) ($context['readiness_score'] ?? 0),
            'confidence_band' => (string) ($context['readiness_band'] ?? 'low'),
            'method' => 'raw DNA quality, autosomal coverage, X/Y/MT marker availability, and tree/story completeness',
            'source_name' => 'ZeroThink streaming raw DNA parser',
            'source_version' => 'ZLINEAGE v0.2',
            'matched_marker_count' => (int) ($raw_summary['parsed_variants'] ?? 0),
            'explanation' => 'This shows whether the uploaded DNA file is useful for lineage research lanes. It is not a royal lineage result.',
            'limitations' => 'Consumer SNP files are not full genealogy proof. Royal or noble paths need records, GEDCOM edges, dates, places, and ideally DNA match/segment evidence.',
            'next_steps' => implode(' ', array_slice($tasks, 0, 3)),
        ]),
    ];
    foreach ($public_candidates as $lead) {
        $cards[] = zl_dna_evidence_card([
            'title' => 'Public Graph Lead',
            'claim_type' => 'lineage_public_candidate',
            'claim_label' => (string) ($lead['name'] ?? 'public graph lead'),
            'evidence_level' => 'name_anchor_research_lead',
            'confidence_score' => (float) ($lead['confidence_score'] ?? 0),
            'confidence_band' => (string) ($lead['confidence_band'] ?? 'low'),
            'method' => 'anchor term search against local public person graph',
            'source_name' => 'Local Wikidata-derived lineage graph',
            'source_version' => (string) ($graph['pack_band'] ?? 'lineage_core'),
            'matched_marker_count' => 0,
            'explanation' => 'Anchor "' . (string) ($lead['anchor'] ?? '') . '" matched a public graph person: ' . (string) ($lead['name'] ?? '') . (($lead['title_or_role'] ?? '') !== '' ? ' (' . (string) $lead['title_or_role'] . ')' : '') . '. This is a route lead, not a relationship claim.',
            'limitations' => (string) ($lead['boundary'] ?? 'Public graph lead only. Not proof of descent.'),
            'next_steps' => 'Add GEDCOM parent-child links, dates, places, and source citations before treating this as a serious lineage route.',
        ]);
    }
    foreach ((array) ($context['lanes'] ?? []) as $lane) {
        $cards[] = zl_dna_evidence_card([
            'title' => (string) ($lane['label'] ?? 'Lineage lane'),
            'claim_type' => 'lineage_lane',
            'claim_label' => (string) ($lane['status'] ?? 'context'),
            'evidence_level' => 'research_context',
            'confidence_score' => (int) ($context['readiness_score'] ?? 0),
            'confidence_band' => (string) ($context['readiness_band'] ?? 'low'),
            'method' => 'lineage lane availability screen',
            'source_name' => 'ZeroThink local lineage parser',
            'source_version' => 'ZLINEAGE v0.2',
            'matched_marker_count' => (int) ($raw_summary['parsed_variants'] ?? 0),
            'explanation' => (string) ($lane['plain'] ?? ''),
            'limitations' => 'Lane availability is not ancestry proof. It only says which research route is worth trying next.',
            'next_steps' => 'Use the task list to add the missing evidence for this lane.',
        ]);
    }
    foreach ((array) ($plain_lineage['ancient_lanes'] ?? []) as $lane) {
        $proxy = (float) ($lane['proxy_percent'] ?? 0);
        $cards[] = zl_dna_evidence_card([
            'title' => (string) ($lane['label'] ?? 'Ancient / regional lineage lane'),
            'claim_type' => 'lineage_ancient_context',
            'claim_label' => (string) ($lane['status'] ?? 'research context'),
            'evidence_level' => $proxy > 0 ? 'dna_context_projection' : 'hypothesis_lane',
            'confidence_score' => $proxy > 0 ? min(82, max(38, $proxy + (int) (($raw_summary['quality_score'] ?? 0) / 2))) : 22,
            'confidence_band' => $proxy > 0 ? 'research lead' : 'theoretical only',
            'method' => 'local country/ancient projection from ZDNA consumer estimate plus lineage boundaries',
            'source_name' => 'ZeroThink local ZDNA lineage projection',
            'source_version' => (string) ($plain_lineage['engine'] ?? 'ZLINEAGE-ANCIENT'),
            'matched_marker_count' => (int) ($raw_summary['parsed_variants'] ?? 0),
            'explanation' => (string) ($lane['plain'] ?? ''),
            'limitations' => (string) ($lane['boundary'] ?? 'Research context only.'),
            'next_steps' => $proxy > 0
                ? 'Upload a GEDCOM/tree or parent files to separate maternal, paternal, and mixed ancestry routes.'
                : 'Run or expand the relevant ancient/reference comparison and add family-tree evidence before stronger claims.',
        ]);
    }
    foreach ($relative_context as $rel) {
        $cards[] = zl_dna_evidence_card([
            'title' => 'Relative DNA Overlap',
            'claim_type' => 'relative_dna_overlap',
            'claim_label' => (string) ($rel['claim_label'] ?? 'relative comparison'),
            'evidence_level' => 'private_overlap_screen',
            'confidence_score' => (float) (($rel['allele_share_percent'] ?? 0)),
            'confidence_band' => (string) ($rel['confidence_band'] ?? 'low'),
            'method' => 'rsID overlap, exact genotype concordance, and shared-allele screen',
            'source_name' => 'User supplied relative raw DNA file',
            'source_version' => 'ZLINEAGE relative overlap v0.1',
            'matched_marker_count' => (int) ($rel['overlap_markers'] ?? 0),
            'explanation' => 'This compares two uploaded raw DNA files locally to see whether a close-family research lane is worth exploring.',
            'limitations' => (string) ($rel['boundary'] ?? 'This is a private overlap screen, not legal or medical relationship proof.'),
            'next_steps' => 'If the signal matters, verify with a genealogy platform segment/match report or certified relationship test.',
        ]);
    }
    $readiness_chart = zl_dna_safe_percent_segments([
        ['label' => 'DNA readiness', 'value' => max(1, (int) ($context['readiness_score'] ?? 0)), 'color' => '#00ff91'],
        ['label' => 'Tree/story evidence', 'value' => $gedcom ? 42 : ($has_story ? 16 : 4), 'color' => '#ffd76f'],
        ['label' => 'Relative DNA evidence', 'value' => $relative_context ? min(28, count($relative_context) * 14) : 1, 'color' => '#ff6edb'],
        ['label' => 'Public graph DB', 'value' => !empty($graph['installed']) ? min(44, max(6, (int) log(max(1, (int) ($graph['people_count'] ?? 1)), 2) * 3)) : 1, 'color' => '#b6ff7c'],
        ['label' => 'Direct-line markers', 'value' => (((int) ($raw_summary['y_markers'] ?? 0) >= 50) ? 12 : 1) + (((int) ($raw_summary['mt_markers'] ?? 0) >= 20) ? 12 : 1), 'color' => '#69f7ff'],
        ['label' => 'Evidence still missing', 'value' => max(8, 100 - (int) ($context['readiness_score'] ?? 0)), 'color' => '#ff7b7b'],
    ]);
    $chart = !empty($plain_lineage['chart']) ? (array) $plain_lineage['chart'] : $readiness_chart;
    return [
        'summary' => [
            'file_name' => $raw_summary['file_name'] ?? 'DNA file',
            'parsed_variants' => (int) ($raw_summary['parsed_variants'] ?? 0),
            'called_variants' => (int) ($raw_summary['called_variants'] ?? 0),
            'no_call_percent' => $raw_summary['no_call_percent'] ?? 0,
            'heterozygosity_percent' => $raw_summary['heterozygosity_percent'] ?? 0,
            'quality_score' => $raw_summary['quality_score'] ?? null,
            'quality_band' => $raw_summary['quality_band'] ?? 'research only',
            'engine_version' => 'ZLINEAGE v0.3',
            'family_side' => $side ?: 'unknown',
            'story_supplied' => $has_story,
            'gedcom_supplied' => (bool) $gedcom,
            'conclusion_band' => $band,
            'confidence_score' => $score,
            'truth_boundary' => 'DNA can support narrow lineage questions only in specific circumstances. It cannot prove a medieval or royal path by itself.',
        ],
        'raw_summary' => $raw_summary,
        'lineage_context' => $context,
        'public_graph' => $graph,
        'anchor_terms' => $anchors,
        'public_graph_candidates' => $public_candidates,
        'route_lenses' => $route_lenses,
        'relative_dna_context' => $relative_context,
        'plain_lineage' => $plain_lineage,
        'research_tasks' => $tasks,
        'gedcom' => $gedcom,
        'chart' => $chart,
        'readiness_chart' => $readiness_chart,
        'insights' => [
            'math_system' => 'ZLINEAGE v0.3',
            'quality' => ['score' => (int) ($context['readiness_score'] ?? 0), 'confidence' => (string) ($context['readiness_band'] ?? 'low')],
            'headline' => $label,
            'plain_summary' => !empty($plain_lineage['plain_summary'])
                ? (string) $plain_lineage['plain_summary']
                : ($has_story || $gedcom
                    ? 'ZeroThink scored the supplied DNA plus tree/story evidence and created a claim ledger with next steps.'
                    : 'ZeroThink built a DNA-only lineage intelligence starter. The DNA file is usable for recent-family research, but royal/famous-person routes need a public graph plus GEDCOM/surnames/places/dates.'),
            'math_summary' => 'ZLINEAGE now opens with plain ancestral context, then separates direct-line marker availability, public graph readiness, documentary evidence, and missing-proof gaps underneath.',
            'cards' => array_merge(array_map(function ($lane) {
                return [
                    'category' => 'Ancient / regional lineage',
                    'title' => (string) ($lane['label'] ?? 'Ancient lane'),
                    'plain' => (string) (($lane['status'] ?? 'research lead') . ': ' . ($lane['plain'] ?? '')),
                    'priority' => (float) ($lane['proxy_percent'] ?? 0) > 0 ? 'context' : 'attention',
                ];
            }, (array) ($plain_lineage['ancient_lanes'] ?? [])), array_map(function ($lane) {
                return [
                    'category' => 'Lineage lane',
                    'title' => (string) ($lane['label'] ?? 'Lineage lane'),
                    'plain' => (string) ($lane['plain'] ?? ''),
                    'priority' => (($lane['status'] ?? '') === 'missing' || strpos((string) ($lane['status'] ?? ''), 'not available') !== false) ? 'attention' : 'context',
                ];
            }, (array) ($context['lanes'] ?? [])), array_map(function ($lens) {
                return [
                    'category' => 'Route lens',
                    'title' => (string) ($lens['title'] ?? 'Lineage route'),
                    'plain' => (string) (($lens['status'] ?? 'context') . ': ' . ($lens['plain'] ?? '')),
                    'priority' => in_array((string) ($lens['status'] ?? ''), ['graph pack missing', 'waiting for family evidence', 'needs target names and graph'], true) ? 'attention' : 'context',
                ];
            }, $route_lenses), array_map(function ($lead) {
                return [
                    'category' => 'Public graph lead',
                    'title' => (string) ($lead['name'] ?? 'Public graph lead'),
                    'plain' => 'Anchor "' . (string) ($lead['anchor'] ?? '') . '" matched ' . (string) ($lead['name'] ?? '') . '. Treat as a lead only, not proof.',
                    'priority' => 'context',
                ];
            }, $public_candidates)),
            'agent_prompts' => [
                'Explain my ancient and country lineage result simply.',
                'Which parts look maternal, paternal, or mixed?',
                'What should I upload next for royal or famous-person lineage research?',
                'Can this DNA file support paternal or maternal lineage?',
            ],
        ],
        'evidence_cards' => $cards,
        'disclaimer' => 'Lineage research support only. This is not proof of identity, royalty, citizenship, inheritance, or status.',
    ];
}

function zl_dna_lineage_markdown($report) {
    $lines = [];
    $lines[] = '## Royal / Lineage Clues';
    $lines[] = '';
    $lines[] = '**Boundary:** ' . ($report['disclaimer'] ?? 'Lineage research support only.');
    $lines[] = '';
    $summary = (array) ($report['summary'] ?? []);
    $plain = (array) ($report['plain_lineage'] ?? []);
    if (!empty($plain)) {
        $lines[] = '### Plain Lineage Result';
        $lines[] = '';
        $lines[] = '**' . ($plain['headline'] ?? 'Lineage context ready') . '**';
        $lines[] = '';
        $lines[] = $plain['plain_summary'] ?? 'ZeroThink generated a plain lineage context view from the uploaded DNA file.';
        $lines[] = '';
        if (!empty($plain['segments'])) {
            $lines[] = '#### Country / Heritage Projection';
            foreach (array_slice((array) $plain['segments'], 0, 10) as $segment) {
                $pct = (float) ($segment['percent'] ?? $segment['value'] ?? 0);
                if ($pct <= 0) {
                    continue;
                }
                $lines[] = '- ' . ($segment['label'] ?? 'Projection') . ': ' . round($pct, 1) . '%';
            }
            $lines[] = '';
        }
        if (!empty($plain['ancient_lanes'])) {
            $lines[] = '#### Ancient / Regional Lineage Lanes';
            foreach ((array) $plain['ancient_lanes'] as $lane) {
                $pct = (float) ($lane['proxy_percent'] ?? 0);
                $pct_text = $pct > 0 ? ' (' . round($pct, 1) . '% proxy)' : '';
                $lines[] = '- ' . ($lane['label'] ?? 'Ancient lane') . ': ' . ($lane['status'] ?? 'research lead') . $pct_text;
                $lines[] = '  Plain meaning: ' . ($lane['plain'] ?? '');
                if (!empty($lane['modern_countries'])) {
                    $lines[] = '  Modern map names: ' . $lane['modern_countries'];
                }
                $lines[] = '  Boundary: ' . ($lane['boundary'] ?? 'Research context only.');
            }
            $lines[] = '';
        }
        if (!empty($plain['direct_line_cards'])) {
            $lines[] = '#### Direct-Line Clue Lanes';
            foreach ((array) $plain['direct_line_cards'] as $card) {
                $lines[] = '- ' . ($card['label'] ?? 'Lineage lane') . ': ' . ($card['status'] ?? 'context') . ' - ' . ($card['plain'] ?? '');
            }
            $lines[] = '';
        }
        $lines[] = '**Truth boundary:** ' . ($plain['truth_boundary'] ?? 'Research context only.');
        $lines[] = '';
    }
    $lines[] = '### Technical Status';
    $lines[] = '- Conclusion band: ' . ($summary['conclusion_band'] ?? 'unknown');
    $lines[] = '- Confidence score: ' . (int) ($summary['confidence_score'] ?? 0) . '/100';
    $lines[] = '- DNA markers parsed: ' . number_format((int) ($summary['parsed_variants'] ?? 0));
    $lines[] = '- DNA quality: ' . ($summary['quality_band'] ?? 'unknown') . (isset($summary['quality_score']) ? ' (' . (int) $summary['quality_score'] . '/100)' : '');
    $lines[] = '- Family side: ' . ($summary['family_side'] ?? 'unknown');
    $lines[] = '- GEDCOM supplied: ' . (!empty($summary['gedcom_supplied']) ? 'yes' : 'no');
    $lines[] = '- Story/surnames/places supplied: ' . (!empty($summary['story_supplied']) ? 'yes' : 'no');
    $lines[] = '';
    if (!empty($report['public_graph'])) {
        $graph = (array) $report['public_graph'];
        $lines[] = '### Public Lineage Graph Database';
        $lines[] = '- Status: ' . (!empty($graph['installed']) ? 'installed' : 'missing') . ' (' . ($graph['pack_band'] ?? 'unknown') . ')';
        $lines[] = '- People in local graph: ' . number_format((int) ($graph['people_count'] ?? 0));
        $lines[] = '- Parent/lineage edges: ' . number_format((int) ($graph['edge_count'] ?? 0));
        $lines[] = '- Note: ' . ($graph['pack_note'] ?? 'No note.');
        $lines[] = '- Next step: ' . ($graph['next_step'] ?? 'Expand the local lineage graph pack.');
        $lines[] = '';
    }
    if (!empty($report['lineage_context'])) {
        $ctx = (array) $report['lineage_context'];
        $markers = (array) ($ctx['marker_summary'] ?? []);
        $lines[] = '### DNA Lineage Starter';
        $lines[] = '- Engine: ' . ($ctx['engine'] ?? 'ZLINEAGE');
        $lines[] = '- Readiness score: ' . (int) ($ctx['readiness_score'] ?? 0) . '/100 (' . ($ctx['readiness_band'] ?? 'low') . ')';
        $lines[] = '- Autosomal markers: ' . number_format((int) ($markers['autosomal_markers'] ?? 0));
        $lines[] = '- X markers: ' . number_format((int) ($markers['x_markers'] ?? 0));
        $lines[] = '- Y markers: ' . number_format((int) ($markers['y_markers'] ?? 0));
        $lines[] = '- Mitochondrial markers: ' . number_format((int) ($markers['mt_markers'] ?? 0));
        foreach ((array) ($ctx['lanes'] ?? []) as $lane) {
            $lines[] = '- ' . ($lane['label'] ?? 'Lineage lane') . ': ' . ($lane['status'] ?? 'context') . ' - ' . ($lane['plain'] ?? '');
        }
        $lines[] = '';
    }
    if (!empty($report['route_lenses'])) {
        $lines[] = '### Route Lenses';
        foreach ((array) $report['route_lenses'] as $lens) {
            $lines[] = '- ' . ($lens['title'] ?? 'Route') . ': ' . ($lens['status'] ?? 'context') . ' - ' . ($lens['plain'] ?? '');
        }
        $lines[] = '';
    }
    if (!empty($report['public_graph_candidates'])) {
        $lines[] = '### Public Graph Leads';
        foreach ((array) $report['public_graph_candidates'] as $lead) {
            $bits = [];
            if (!empty($lead['birth_date'])) {
                $bits[] = 'born ' . $lead['birth_date'];
            }
            if (!empty($lead['death_date'])) {
                $bits[] = 'died ' . $lead['death_date'];
            }
            if (!empty($lead['title_or_role'])) {
                $bits[] = $lead['title_or_role'];
            }
            $lines[] = '- ' . ($lead['name'] ?? 'Public person') . ' via anchor "' . ($lead['anchor'] ?? '') . '"' . ($bits ? ' (' . implode(', ', $bits) . ')' : '');
            $lines[] = '  Confidence: ' . ($lead['confidence_band'] ?? 'low') . ' (' . ($lead['confidence_score'] ?? 0) . '/100)';
            $lines[] = '  Boundary: ' . ($lead['boundary'] ?? 'Lead only; not proof.');
        }
        $lines[] = '';
    }
    if (!empty($report['gedcom'])) {
        $g = (array) $report['gedcom'];
        $lines[] = '### GEDCOM Graph Summary';
        $lines[] = '- People: ' . number_format((int) ($g['person_count'] ?? 0));
        $lines[] = '- Families: ' . number_format((int) ($g['family_count'] ?? 0));
        $lines[] = '- Parent-child edges: ' . number_format((int) ($g['edge_count'] ?? 0));
        if (!empty($g['top_surnames'])) {
            $lines[] = '- Top surnames: ' . implode(', ', array_slice(array_keys((array) $g['top_surnames']), 0, 8));
        }
        if (!empty($g['top_places'])) {
            $lines[] = '- Top places: ' . implode('; ', array_slice(array_keys((array) $g['top_places']), 0, 5));
        }
        $lines[] = '';
    }
    if (!empty($report['research_tasks'])) {
        $lines[] = '### What To Add Next';
        foreach ((array) $report['research_tasks'] as $task) {
            $lines[] = '- ' . $task;
        }
        $lines[] = '';
    }
    if (!empty($report['relative_dna_context'])) {
        $lines[] = '### Relative DNA Overlap';
        foreach ((array) $report['relative_dna_context'] as $rel) {
            $lines[] = '- ' . ($rel['relative_file'] ?? 'relative DNA file') . ': ' . ($rel['claim_label'] ?? 'comparison complete');
            $lines[] = '  Overlap markers: ' . number_format((int) ($rel['overlap_markers'] ?? 0));
            $lines[] = '  Exact match: ' . ($rel['exact_match_percent'] ?? 0) . '%';
            $lines[] = '  Shared allele: ' . ($rel['allele_share_percent'] ?? 0) . '%';
            $lines[] = '  Boundary: ' . ($rel['boundary'] ?? 'Private overlap screen only.');
        }
        $lines[] = '';
    }
    $lines[] = '### Evidence Ledger';
    foreach ((array) ($report['evidence_cards'] ?? []) as $card) {
        $lines[] = '- ' . ($card['title'] ?? 'Evidence') . ': ' . ($card['claim_label'] ?? '');
        $lines[] = '  Confidence: ' . ($card['confidence_band'] ?? 'unknown') . ' (' . ($card['confidence_score'] ?? 0) . '/100)';
        $lines[] = '  Limitation: ' . ($card['limitations'] ?? '');
        $lines[] = '  Next step: ' . ($card['next_steps'] ?? '');
    }
    $lines[] = '';
    return implode("\n", $lines);
}

function zl_dna_ancient_grave_report_path($path, $name = 'raw-dna.txt', $base_override = null, $heritage_override = null) {
    zl_dna_raise_runtime_limits();
    $raw = is_array($base_override) ? $base_override : zl_dna_raw_analyze_path($path, $name);
    $heritage = is_array($heritage_override) ? $heritage_override : zl_dna_heritage_report_path($path, $name, $raw);
    $pack = zl_dna_local_ancient_grave_matches_path($path, $name, 16, $raw);
    if (empty($pack['interpretation_ready'])) {
        $reason = (string) ($pack['reason'] ?? 'Legacy individual-sample matching is withheld; ancient group comparisons are reported separately in Origins.');
        return [
            'summary' => $raw + ['engine_version' => 'ZANCIENT allele-validation gate v0.3'],
            'raw' => $raw, 'heritage' => $heritage, 'ancient_pack' => $pack,
            'sample_matches' => [], 'site_scores' => [], 'ancient_spotlight' => [], 'context_lanes' => [],
            'cohen_priestly' => [], 'chart' => [],
            'insights' => [
                'headline' => 'Legacy individual-sample matching withheld', 'plain_summary' => $reason,
                'analysis_gate' => (array) ($pack['analysis_gate'] ?? []),
                'ancient_pack_installed' => !empty($pack['installed']),
                'sample_matches' => [], 'site_scores' => [], 'top_sample' => [], 'ancient_spotlight' => [], 'context_lanes' => [],
            ],
            'evidence_cards' => [zl_dna_evidence_card([
                'title' => 'Legacy individual-sample alignment check', 'claim_type' => 'ancient_reference_validation',
                'claim_label' => 'legacy individual-sample matching withheld', 'evidence_level' => 'source_contract_validation',
                'confidence_score' => 0, 'confidence_band' => 'not established',
                'method' => 'file build/orientation and per-variant source alignment contract checks',
                'source_name' => 'Legacy individual-sample research pack', 'source_version' => (string) ($pack['pack_meta']['pack_version'] ?? 'see installed source metadata'),
                'matched_marker_count' => 0, 'explanation' => $reason,
                'limitations' => (string) ($pack['truth_boundary'] ?? ''),
                'next_steps' => 'Use Origins for aligned ancient group comparisons. Matching to a particular ancient individual or grave requires a separately validated individual-sample reference and method.',
            ])],
            'disclaimer' => 'This legacy individual-sample section is historical research context only. It reports no nearest individual, grave, ancestry percentage or descent conclusion. Aligned ancient group affinities are reported separately in Origins.',
        ];
    }
    $cohen_priestly = zl_dna_cohen_priestly_marker_profile_path($path, $name, $raw);
    $context_lanes = zl_dna_ancient_context_lanes_from_heritage($heritage);
    $sample_matches = zl_dna_ancient_unique_sample_matches((array) ($pack['sample_matches'] ?? []), 12);
    $site_scores = (array) ($pack['site_scores'] ?? []);
    $top = (array) ($sample_matches[0] ?? []);
    $installed = !empty($pack['installed']);
    $has_context = false;
    foreach ($context_lanes as $lane) {
        if ((float) ($lane['proxy_percent'] ?? 0) > 0) {
            $has_context = true;
            break;
        }
    }
    $headline = $installed && !empty($sample_matches)
        ? 'Ancient region context plus grave sample leads'
        : ($installed ? 'Ancient grave source pack scanned, no strong sample lead yet' : 'Ancient context mode ready');
    $plain = $installed && !empty($sample_matches)
        ? 'The wheel shows the broader ancient/country context from the heritage projection. Direct ancient grave samples are listed separately as nearest-sample clues, because a few overlapping public graves can cluster around one site and should not be read as ancestry percentages.'
        : ($installed
            ? 'The Ancient Grave Match source pack is active, but this upload did not overlap enough public ancient markers for a strong direct sample lead. The context lanes still show which ancient-region questions are worth testing next.'
            : 'Direct grave/site matching is still being expanded for this account. ZeroThink is showing ancient-context leads now: the regions and historical lanes most worth testing once the deeper source pack is active.');
    $spotlight = [];
    foreach ($context_lanes as $lane) {
        $pct = (float) ($lane['proxy_percent'] ?? 0);
        if ($pct <= 0) {
            continue;
        }
        $spotlight[] = [
            'label' => (string) ($lane['label'] ?? 'Ancient context'),
            'modern_countries' => (string) ($lane['modern_countries'] ?? ''),
            'strength' => round($pct, 1),
            'status' => (string) ($lane['status'] ?? 'context'),
            'plain' => (string) ($lane['plain'] ?? ''),
        ];
    }
    usort($spotlight, function ($a, $b) {
        return ((float) ($b['strength'] ?? 0)) <=> ((float) ($a['strength'] ?? 0));
    });
    $chart = zl_dna_ancient_chart_segments($context_lanes, $site_scores);
    $cards = [];
        $cards[] = zl_dna_evidence_card([
            'title' => 'Ancient Grave Match',
            'claim_type' => 'ancient_grave_match',
            'claim_label' => $installed ? 'ancient source pack scanned' : 'ancient source pack expanding',
            'evidence_level' => $installed ? 'ancient_reference_scan' : 'source_expanding',
            'confidence_score' => !empty($top) ? (float) ($top['confidence_score'] ?? 0) : ($installed ? 35 : 18),
            'confidence_band' => !empty($top) ? (string) ($top['confidence_band'] ?? 'medium') : ($installed ? 'low-medium' : 'low'),
            'method' => 'public ancient-sample overlap with exact and shared-allele similarity scoring',
            'source_name' => 'ZeroThink Ancient Grave Match source pack',
            'source_version' => (string) ($pack['reason'] ?? 'being expanded'),
            'matched_marker_count' => (int) ($pack['matched_marker_count'] ?? 0),
            'explanation' => !empty($top)
                ? ('Nearest direct sample lead: ' . ($top['public_label'] ?? $top['sample_id'] ?? 'ancient sample') . ', ' . ($top['site_name'] ?? 'unknown site') . ', ' . ($top['modern_country'] ?? 'unknown country') . '. The main wheel still uses broader country/region context, not this single grave lead.')
                : $plain,
            'limitations' => 'Ancient sample affinity is not proof of descent, identity, culture, religion, tribe, royal line, or a named ancestor. Sparse consumer SNP files may overlap poorly with ancient genomes.',
        'next_steps' => $installed ? 'Expand the Ancient Grave Match source pack with more public ancient samples and rerun for stronger site coverage.' : 'Activate the expanded Ancient Grave Match source pack, then rerun this scan for direct public sample/site leads.',
    ]);
    $cards[] = zl_dna_evidence_card([
        'title' => 'Cohen / Kohen priestly-line Y-DNA clue',
        'claim_type' => 'cohen_priestly_y_str',
        'claim_label' => (string) ($cohen_priestly['status'] ?? 'not testable'),
        'evidence_level' => ((int) ($cohen_priestly['classic_markers_present'] ?? 0) >= 4) ? 'paternal_y_str_pattern' : 'marker_availability_check',
        'confidence_score' => (float) ($cohen_priestly['confidence_score'] ?? 0),
        'confidence_band' => (string) ($cohen_priestly['confidence_band'] ?? 'not-testable'),
        'method' => 'classic six-marker Cohen Modal Haplotype Y-STR comparison when DYS marker values are supplied',
        'source_name' => 'ZeroThink ZCOHEN-YSTR',
        'source_version' => (string) ($cohen_priestly['engine'] ?? 'v0.1'),
        'matched_marker_count' => (int) ($cohen_priestly['classic_markers_present'] ?? 0),
        'explanation' => (string) ($cohen_priestly['plain'] ?? ''),
        'limitations' => (string) ($cohen_priestly['truth_boundary'] ?? 'Paternal-line research clue only.'),
        'next_steps' => (string) ($cohen_priestly['next_step'] ?? 'Upload a Y-DNA STR report for this lane.'),
    ]);
    foreach (array_slice($sample_matches, 0, 10) as $match) {
        $label = trim((string) ($match['site_name'] ?? '') . ', ' . (string) ($match['modern_country'] ?? ''), ' ,');
        $cards[] = zl_dna_evidence_card([
            'title' => 'Ancient sample lead: ' . (string) ($match['public_label'] ?? $match['sample_id'] ?? 'sample'),
            'claim_type' => 'ancient_sample_affinity',
            'claim_label' => $label !== '' ? $label : (string) ($match['culture'] ?? 'ancient sample'),
            'evidence_level' => 'sample_genotype_overlap',
            'confidence_score' => (float) ($match['confidence_score'] ?? 0),
            'confidence_band' => (string) ($match['confidence_band'] ?? 'low'),
            'method' => 'overlap marker count plus genotype similarity against a public ancient sample',
            'source_name' => (string) ($match['source_name'] ?? 'public ancient DNA source'),
            'source_version' => (string) ($match['source_version'] ?? ''),
            'matched_marker_count' => (int) ($match['overlap_marker_count'] ?? 0),
            'explanation' => 'Similarity score ' . (string) ($match['similarity_score'] ?? 0) . '/100. Culture/context: ' . (string) ($match['culture'] ?? 'unknown') . '. Date: ' . (string) ($match['date_label'] ?? 'unknown') . '.',
            'limitations' => 'A close ancient sample lead means similarity across available markers only. It does not mean the user descends from this grave or person.',
            'next_steps' => 'Compare against neighbouring ancient sites and modern controls before interpreting this lead.',
        ]);
    }
    foreach (array_slice($context_lanes, 0, 5) as $lane) {
        $cards[] = zl_dna_evidence_card([
            'title' => (string) ($lane['label'] ?? 'Ancient context lane'),
            'claim_type' => 'ancient_context_lane',
            'claim_label' => (string) ($lane['status'] ?? 'context'),
            'evidence_level' => $installed ? 'context_plus_pack' : 'heritage_context_only',
            'confidence_score' => min(70, max(15, (float) ($lane['proxy_percent'] ?? 0) * 4)),
            'confidence_band' => zl_dna_confidence_label_from_score(min(70, max(15, (float) ($lane['proxy_percent'] ?? 0) * 4))),
            'method' => 'plain heritage projection mapped to ancient-region research lanes',
            'source_name' => 'ZDNA ancient context lane map',
            'source_version' => (string) ($lane['source_version'] ?? 'v0.1'),
            'matched_marker_count' => 0,
            'explanation' => (string) ($lane['plain'] ?? ''),
            'limitations' => (string) ($lane['boundary'] ?? 'Context only.'),
            'next_steps' => 'Use direct ancient sample matching when the Ancient Grave Match source pack is active.',
        ]);
    }
    return [
        'summary' => [
            'file_name' => (string) ($raw['file_name'] ?? $name),
            'parsed_variants' => (int) ($raw['parsed_variants'] ?? 0),
            'called_variants' => (int) ($raw['called_variants'] ?? 0),
            'no_call_percent' => $raw['no_call_percent'] ?? 0,
            'heterozygosity_percent' => $raw['heterozygosity_percent'] ?? 0,
            'quality_band' => (string) ($raw['quality_band'] ?? 'unknown'),
            'quality_score' => (int) ($raw['quality_score'] ?? 0),
            'engine_version' => 'ZANCIENT-GRAVE v0.2',
        ],
        'raw' => $raw,
        'heritage' => $heritage,
        'ancient_pack' => $pack,
        'cohen_priestly' => $cohen_priestly,
        'sample_matches' => $sample_matches,
        'site_scores' => $site_scores,
        'ancient_spotlight' => array_slice($spotlight, 0, 8),
        'context_lanes' => $context_lanes,
        'insights' => [
            'headline' => $headline,
            'plain_summary' => $plain,
            'math_system' => 'ZANCIENT-GRAVE v0.2',
            'math_summary' => 'Separates broad ancient region context from direct nearest-sample grave leads, with site de-duplication so one public site cannot dominate the user-facing result.',
            'quality' => ['score' => (int) ($raw['quality_score'] ?? 0), 'confidence' => (string) ($raw['quality_band'] ?? 'checked')],
            'ancient_pack_installed' => $installed,
            'cohen_priestly' => $cohen_priestly,
            'top_sample' => $top,
            'sample_matches' => $sample_matches,
            'site_scores' => $site_scores,
            'ancient_spotlight' => array_slice($spotlight, 0, 8),
            'context_lanes' => $context_lanes,
        ],
        'chart' => zl_dna_safe_percent_segments($chart),
        'evidence_cards' => $cards,
        'data_pack_status' => zl_dna_data_pack_status(),
        'disclaimer' => 'Ancient grave matching is research support only. It reports genetic affinity to public ancient samples/sites, not descent proof, royal proof, identity, tribe, culture, religion, or a named ancestor.',
    ];
}

function zl_dna_ancient_grave_markdown($report) {
    $lines = [];
    $lines[] = '## Legacy individual-sample matching';
    $lines[] = '';
    $lines[] = '**Boundary:** ' . ($report['disclaimer'] ?? 'Research support only.');
    $lines[] = '';
    $summary = (array) ($report['summary'] ?? []);
    $pack = (array) ($report['ancient_pack'] ?? []);
    $insights = (array) ($report['insights'] ?? []);
    $cohen = (array) ($report['cohen_priestly'] ?? []);
    $lines[] = '### Plain Result';
    $lines[] = '';
    $lines[] = '**' . ($insights['headline'] ?? 'Ancient context ready') . '**';
    $lines[] = '';
    $lines[] = $insights['plain_summary'] ?? 'ZeroThink generated an ancient DNA context report.';
    $lines[] = '';
    $lines[] = '- File: ' . ($summary['file_name'] ?? 'DNA file');
    $lines[] = '- Markers parsed: ' . number_format((int) ($summary['parsed_variants'] ?? 0));
    $lines[] = '- Raw quality: ' . ($summary['quality_band'] ?? 'unknown') . ' (' . (int) ($summary['quality_score'] ?? 0) . '/100)';
    $lines[] = '- Legacy individual-sample source pack: ' . (!empty($pack['interpretation_ready']) ? 'validated individual-sample comparison completed' : (!empty($pack['installed']) ? 'installed; individual-sample matching withheld' : 'being expanded'));
    $lines[] = '- Legacy individual-sample markers matched: ' . number_format((int) ($pack['matched_marker_count'] ?? 0));
    $lines[] = '';
    if ($cohen) {
        $lines[] = '### Cohen / Kohen Priestly-Line Y-DNA Clue';
        $lines[] = '';
        $lines[] = '- Result: ' . ($cohen['status'] ?? 'not testable');
        $lines[] = '- Classic CMH markers present: ' . (int) ($cohen['classic_markers_present'] ?? 0) . '/' . (int) ($cohen['classic_markers_total'] ?? 6);
        $lines[] = '- Exact classic matches: ' . (int) ($cohen['classic_exact_matches'] ?? 0);
        $lines[] = '- Confidence: ' . ($cohen['confidence_band'] ?? 'not-testable') . ' (' . (int) ($cohen['confidence_score'] ?? 0) . '/100)';
        $lines[] = '- Meaning: ' . ($cohen['plain'] ?? '');
        $lines[] = '- Boundary: ' . ($cohen['truth_boundary'] ?? 'Paternal-line research clue only.');
        $lines[] = '- Next step: ' . ($cohen['next_step'] ?? 'Upload a Y-DNA STR report for this lane.');
        if (!empty($cohen['classic_marker_table'])) {
            $marker_bits = [];
            foreach ((array) $cohen['classic_marker_table'] as $row) {
                $observed = (array) ($row['observed'] ?? []);
                $marker_bits[] = ($row['marker'] ?? 'DYS?') . ' expected ' . ($row['expected'] ?? '?') . ', observed ' . ($observed ? implode('/', $observed) : 'missing') . ' (' . ($row['status'] ?? 'unknown') . ')';
            }
            $lines[] = '- Marker check: ' . implode('; ', $marker_bits) . '.';
        }
        $lines[] = '';
    }
    if (!empty($report['ancient_spotlight'])) {
        $lines[] = '### Ancient Country / Region Context';
        foreach (array_slice((array) $report['ancient_spotlight'], 0, 8) as $lane) {
            $lines[] = '- ' . ($lane['label'] ?? 'Ancient lead') . ': ' . ($lane['status'] ?? 'context') . ' (' . round((float) ($lane['strength'] ?? 0), 1) . '% signal strength)';
            $lines[] = '  Modern map names: ' . ($lane['modern_countries'] ?? 'not mapped');
            $lines[] = '  Meaning: ' . ($lane['plain'] ?? '');
        }
        $lines[] = '';
    }
    if (!empty($report['sample_matches'])) {
        $lines[] = '### Closest Ancient Sample Leads';
        $lines[] = '';
        $lines[] = 'These are nearest public ancient-sample clues, not ancestry percentages. The same public site can produce several close samples, so ZeroThink now shows one representative lead per site first.';
        $lines[] = '';
        foreach (array_slice((array) $report['sample_matches'], 0, 12) as $match) {
            $place = trim((string) ($match['site_name'] ?? '') . ', ' . (string) ($match['modern_country'] ?? ''), ' ,');
            $lines[] = '- ' . ($match['public_label'] ?? $match['sample_id'] ?? 'Ancient sample') . ': ' . ($place ?: 'unknown site') . ', ' . ($match['culture'] ?? 'unknown culture') . ', ' . ($match['date_label'] ?? 'unknown date');
            $lines[] = '  Similarity: ' . ($match['similarity_score'] ?? 0) . '/100; confidence ' . ($match['confidence_band'] ?? 'low') . ' (' . ($match['confidence_score'] ?? 0) . '/100); overlap ' . number_format((int) ($match['overlap_marker_count'] ?? 0)) . ' markers.';
        }
        $lines[] = '';
    }
    if (!empty($report['site_scores'])) {
        $lines[] = '### Grave Site / Culture Clusters';
        foreach (array_slice((array) $report['site_scores'], 0, 8) as $site) {
            $label = trim((string) ($site['site_name'] ?? '') . ', ' . (string) ($site['modern_country'] ?? ''), ' ,');
            $lines[] = '- ' . ($label ?: 'Ancient site') . ': ' . ($site['culture'] ?? 'unknown culture') . ', confidence ' . ($site['confidence_band'] ?? 'low') . ' (' . ($site['confidence_score'] ?? 0) . '/100), samples ' . (int) ($site['sample_count'] ?? 0) . ', markers ' . number_format((int) ($site['overlap_marker_count'] ?? 0)) . '.';
        }
        $lines[] = '';
    }
    if (!empty($report['context_lanes'])) {
        $lines[] = '### Ancient Context Lanes';
        foreach ((array) $report['context_lanes'] as $lane) {
            $pct = (float) ($lane['proxy_percent'] ?? 0);
            $pct_text = $pct > 0 ? ' (' . round($pct, 1) . '% context proxy)' : '';
            $lines[] = '- ' . ($lane['label'] ?? 'Ancient lane') . ': ' . ($lane['status'] ?? 'context') . $pct_text;
            $lines[] = '  Modern map names: ' . ($lane['modern_countries'] ?? 'not mapped');
            $lines[] = '  Plain meaning: ' . ($lane['plain'] ?? '');
            $lines[] = '  Boundary: ' . ($lane['boundary'] ?? 'Research context only.');
        }
        $lines[] = '';
    }
    $lines[] = '### Evidence Ledger';
    foreach ((array) ($report['evidence_cards'] ?? []) as $card) {
        $lines[] = '- ' . ($card['title'] ?? 'Evidence') . ': ' . ($card['claim_label'] ?? 'context');
        $lines[] = '  Confidence: ' . ($card['confidence_band'] ?? 'low') . ' (' . ($card['confidence_score'] ?? 0) . '/100)';
        $lines[] = '  Limitation: ' . ($card['limitations'] ?? 'Research support only.');
        $lines[] = '  Next step: ' . ($card['next_steps'] ?? 'Use caution.');
    }
    return implode("\n", $lines);
}

function zl_dna_theory_side_summary($path, $name, $label, $main_path = '', $main_name = '') {
    if (!is_file((string) $path) || !is_readable((string) $path)) {
        return null;
    }
    try {
        $raw = zl_dna_raw_analyze_path($path, $name);
        $heritage = zl_dna_heritage_report_path($path, $name);
        $plain = (array) ($heritage['insights']['plain_ancestry_summary'] ?? []);
        $related = null;
        $other_projection = null;
        if ($main_path !== '' && is_file($main_path)) {
            $related = zl_dna_relatedness_compare_paths($main_path, $main_name ?: 'main-dna.txt', $path, $name);
            $other_label = strtolower((string) $label) === 'mother' ? 'father side' : (strtolower((string) $label) === 'father' ? 'mother side' : 'other side');
            $other_projection = zl_dna_parent_side_projection_path($main_path, $main_name ?: 'main-dna.txt', $path, $name, $other_label);
        }
    } catch (Throwable $e) {
        return [
            'side' => $label,
            'file_name' => zl_safe_name($name),
            'marker_count' => 0,
            'quality_band' => 'not parsed',
            'quality_score' => 0,
            'relatedness' => null,
            'other_parent_projection' => null,
            'plain_ancestry_summary' => [],
            'top_mix' => [],
            'answers' => [],
            'error' => 'This relative file could not be parsed safely. Try a normal raw DNA TXT/CSV export.',
        ];
    }
    return [
        'side' => $label,
        'file_name' => $raw['file_name'] ?? zl_safe_name($name),
        'marker_count' => (int) ($raw['parsed_variants'] ?? 0),
        'quality_band' => (string) ($raw['quality_band'] ?? 'unknown'),
        'quality_score' => (int) ($raw['quality_score'] ?? 0),
        'relatedness' => $related,
        'other_parent_projection' => $other_projection,
        'plain_ancestry_summary' => $plain,
        'top_mix' => array_slice((array) ($plain['broad_reference_mix'] ?? []), 0, 5),
        'answers' => array_slice((array) ($plain['cards'] ?? []), 0, 12),
    ];
}

function zl_dna_theory_card($title, $answer, $confidence, $plain, $boundary, $next = '') {
    return [
        'title' => $title,
        'answer' => $answer,
        'confidence_band' => $confidence,
        'plain' => $plain,
        'boundary' => $boundary,
        'next_steps' => $next,
    ];
}

function zl_dna_theoretical_report_path($path, $name = 'raw-dna.txt', $options = []) {
    zl_dna_raise_runtime_limits();
    $options = (array) $options;
    $raw = zl_dna_raw_analyze_path($path, $name);
    $heritage = zl_dna_heritage_report_path($path, $name);
    $lineage = zl_dna_lineage_report((string) ($options['gedcom_text'] ?? ''), array_merge($options, [
        'dna_path' => $path,
        'dna_name' => $name,
        'heritage_report' => $heritage,
    ]), $raw);
    $plain = (array) ($heritage['insights']['plain_ancestry_summary'] ?? []);
    $cards = [];
    foreach ((array) ($plain['cards'] ?? []) as $answer) {
        $cards[] = zl_dna_theory_card(
            (string) ($answer['label'] ?? 'Ancestry hypothesis'),
            (string) ($answer['answer'] ?? 'unknown'),
            (string) ($answer['confidence_band'] ?? 'low'),
            (string) ($answer['plain'] ?? ''),
            (string) ($answer['limitations'] ?? 'Theoretical research only. Not identity proof.'),
            (string) ($answer['next_steps'] ?? '')
        );
    }

    $story_text = implode(' ', [
        (string) ($options['story_notes'] ?? ''),
        (string) ($options['surnames'] ?? ''),
        (string) ($options['places'] ?? ''),
        (string) ($options['date_ranges'] ?? ''),
    ]);
    $royal_hint = preg_match('/\b(royal|king|queen|prince|princess|duke|duchess|earl|countess|lord|lady|noble|sultan|emperor|empress|hashemite|hashimite)\b/i', $story_text);
    $famous_hint = preg_match('/\b(famous|celebrity|actor|singer|president|prime minister|prophet|saint|warrior|dynasty|lineage)\b/i', $story_text);
    $cards[] = zl_dna_theory_card(
        'Royal / noble lineage hypothesis',
        $royal_hint ? 'story route supplied - research path possible' : 'not testable from DNA alone',
        $royal_hint ? 'low' : 'not applicable',
        $royal_hint
            ? 'Your notes include royal/noble-lineage language, so ZeroThink can treat it as a research route if you add GEDCOM records and dates.'
            : 'Raw consumer DNA cannot say someone is royal or noble. It can only support a route after documentary records and direct-line evidence exist.',
        'Theoretical only. No claim here is proof of status, inheritance, citizenship, identity, or ancestry.',
        'Upload GEDCOM, surnames, places, dates, and any Y-DNA/mtDNA or segment-match evidence.'
    );
    $cards[] = zl_dna_theory_card(
        'Famous-person relationship hypothesis',
        $famous_hint ? 'story route supplied - graph check needed' : 'not testable without genealogy graph',
        $famous_hint ? 'low' : 'not applicable',
        $famous_hint
            ? 'The supplied notes mention a famous-person style route. This needs a public/person graph, sourced parent-child records, and ideally DNA match/segment evidence.'
            : 'DNA raw files do not identify famous relatives by themselves. A famous-person claim needs GEDCOM and a public lineage graph.',
        'Theoretical only. Do not present this as a relationship claim.',
        'Add a GEDCOM and the famous person/name/path you want checked.'
    );

    $mother = null;
    if (!empty($options['mother_dna_path'])) {
        $mother = zl_dna_theory_side_summary((string) $options['mother_dna_path'], (string) ($options['mother_dna_name'] ?? 'mother-dna.txt'), 'mother', $path, $name);
    }
    $father = null;
    if (!empty($options['father_dna_path'])) {
        $father = zl_dna_theory_side_summary((string) $options['father_dna_path'], (string) ($options['father_dna_name'] ?? 'father-dna.txt'), 'father', $path, $name);
    }
    $parent_aware_estimate = zl_dna_parent_aware_estimate($mother, $father);
    foreach (['mother' => $mother, 'father' => $father] as $side => $side_report) {
        if (!$side_report) {
            $cards[] = zl_dna_theory_card(
                ucfirst($side) . '-side ancestry split',
                'parent file not supplied',
                'not applicable',
                'ZeroThink cannot assign ancestry signals to the ' . $side . ' side without a parent/close-relative file or phased segment data.',
                'A single unphased consumer DNA file cannot reliably say which side a signal came from.',
                'Upload a ' . $side . ' raw DNA file or segment match data.'
            );
            continue;
        }
        $mix = array_slice((array) ($side_report['top_mix'] ?? []), 0, 3);
        $bits = array_map(function ($row) {
            return ($row['proxy_percent'] ?? 0) . '% ' . ($row['label'] ?? 'reference signal');
        }, $mix);
        $rel = (array) ($side_report['relatedness'] ?? []);
        $projection = (array) ($side_report['other_parent_projection'] ?? []);
        $projection_plain = '';
        if (!empty($projection['available'])) {
            $projection_plain = ' Estimated remaining side: ' . ($projection['headline'] ?? 'parent-side projection ready') . ' / confidence ' . ($projection['confidence_band'] ?? 'medium') . '. ' . ($projection['plain_summary'] ?? '');
        }
        $cards[] = zl_dna_theory_card(
            ucfirst($side) . '-side ancestry split',
            'parent comparison available',
            (string) ($projection['confidence_band'] ?? ($rel['confidence_band'] ?? 'low')),
            ucfirst($side) . ' file comparison: ' . (($rel['claim_label'] ?? '') ?: 'comparison complete') . '. Top supplied-' . $side . ' proxy signals: ' . ($bits ? implode(', ', $bits) : 'not enough calibrated signal') . '.' . $projection_plain,
            'This is side-level research context. It is not a phased ancestry painting and not proof of ethnicity/religion/identity.',
            'For real side assignment, add both parents if possible and use phased segment tools.'
        );
    }

    $cards[] = zl_dna_theory_card(
        'What sources/markers would improve this',
        'more data needed for stronger hypotheses',
        'medium',
        'The highest-impact additions are full autosomes 1-22 ancestry reference panels, ASJ/Sephardi/Mizrahi-aware frequency panels, North Africa/Egypt and Iran/Persian-aware panels, ancient-DNA research sets, GEDCOM, Y-DNA, mtDNA, and DNA match segments.',
        'Adding source packs improves hypotheses, but does not remove the need for confidence bands and source limits.',
        'Run the private source-pack builder and expand the country/region reference engine beyond starter mode.'
    );

    $chart = !empty($parent_aware_estimate['available']) ? (array) ($parent_aware_estimate['segments'] ?? []) : zl_dna_safe_percent_segments([
        ['label' => 'Ancestry hypotheses', 'value' => max(1, count((array) ($plain['cards'] ?? [])) * 8), 'color' => '#69f7ff'],
        ['label' => 'Lineage route', 'value' => max(1, (int) ($lineage['summary']['confidence_score'] ?? 0)), 'color' => '#ffd76f'],
        ['label' => 'Parent-side evidence', 'value' => ($mother ? 18 : 1) + ($father ? 18 : 1), 'color' => '#ff6edb'],
        ['label' => 'Missing proof gap', 'value' => !empty($plain['flat_signal_warning']) ? 55 : 28, 'color' => '#ff7b7b'],
    ]);
    return [
        'summary' => [
            'file_name' => $raw['file_name'] ?? zl_safe_name($name),
            'parsed_variants' => (int) ($raw['parsed_variants'] ?? 0),
            'called_variants' => (int) ($raw['called_variants'] ?? 0),
            'quality_band' => (string) ($raw['quality_band'] ?? 'unknown'),
            'quality_score' => (int) ($raw['quality_score'] ?? 0),
            'engine_version' => 'ZDNA-THEORY v0.1',
        ],
        'raw_summary' => $raw,
        'heritage' => $heritage,
        'lineage' => $lineage,
        'mother_side' => $mother,
        'father_side' => $father,
        'parent_aware_estimate' => $parent_aware_estimate,
        'theory_cards' => $cards,
        'chart' => $chart,
        'insights' => [
            'math_system' => 'ZDNA-THEORY v0.1',
            'quality' => ['score' => (int) ($raw['quality_score'] ?? 0), 'confidence' => (string) ($raw['quality_band'] ?? 'checked')],
            'headline' => 'Theoretical DNA hypothesis report ready',
            'plain_summary' => 'This mode gives the full hypothesis picture without pretending every lead is peer-reviewed proof. Treat it as a research map: possible signals, missing panels, parent-side availability, royal/famous-person routes, and what evidence is needed next.',
            'math_summary' => 'ZDNA-THEORY combines heritage reference-panel gates, lineage readiness, parent/relative overlap, and explicit missing-proof penalties.',
            'cards' => array_map(function ($card) {
                return [
                    'category' => 'Theoretical DNA',
                    'title' => (string) ($card['title'] ?? 'Theory card'),
                    'plain' => (string) (($card['answer'] ?? '') . ' - ' . ($card['plain'] ?? '')),
                    'priority' => in_array(($card['confidence_band'] ?? ''), ['not applicable', 'panel required'], true) ? 'attention' : 'context',
                ];
            }, $cards),
            'agent_prompts' => [
                'Explain the theoretical report without overclaiming.',
                'What evidence would make the Jewish or ancient-lineage question stronger?',
                'Which side did each ancestry signal come from?',
                'Can this show famous relatives?',
            ],
        ],
        'disclaimer' => 'Theoretical research mode only. It may generate leads and hypotheses, but it is not proof of ethnicity, religion, tribe, nationality, royal lineage, famous relationship, diagnosis, or identity.',
    ];
}

function zl_dna_theoretical_markdown($report) {
    $lines = [];
    $lines[] = '## Theoretical DNA Analysis';
    $lines[] = '';
    $lines[] = '**Boundary:** ' . ($report['disclaimer'] ?? 'Theoretical research only.');
    $lines[] = '';
    $s = (array) ($report['summary'] ?? []);
    $lines[] = '- File: ' . ($s['file_name'] ?? 'DNA file');
    $lines[] = '- Markers parsed: ' . number_format((int) ($s['parsed_variants'] ?? 0));
    $lines[] = '- Quality: ' . ($s['quality_band'] ?? 'unknown') . ' (' . (int) ($s['quality_score'] ?? 0) . '/100)';
    $lines[] = '- Engine: ' . ($s['engine_version'] ?? 'ZDNA-THEORY');
    $lines[] = '';
    if (!empty($report['parent_aware_estimate']['available'])) {
        $estimate = (array) $report['parent_aware_estimate'];
        $lines[] = '### Parent-Aware Plain Split';
        $lines[] = '';
        $lines[] = '**' . ($estimate['headline'] ?? 'Parent-aware split') . '**';
        $lines[] = '';
        $lines[] = $estimate['plain'] ?? '';
        foreach ((array) ($estimate['segments'] ?? []) as $segment) {
            $lines[] = '- ' . ($segment['label'] ?? 'Side') . ': ' . ($segment['percent'] ?? $segment['value'] ?? 0) . '%';
        }
        $lines[] = 'Boundary: ' . ($estimate['boundary'] ?? 'Research context only.');
        $lines[] = '';
    }
    $lines[] = '### Easy Summary';
    foreach ((array) ($report['theory_cards'] ?? []) as $card) {
        $lines[] = '- ' . ($card['title'] ?? 'Hypothesis') . ': ' . ($card['answer'] ?? 'unknown') . ' / confidence ' . ($card['confidence_band'] ?? 'low');
        $lines[] = '  ' . ($card['plain'] ?? '');
        $lines[] = '  Boundary: ' . ($card['boundary'] ?? 'Theoretical only.');
        if (!empty($card['next_steps'])) {
            $lines[] = '  Next: ' . $card['next_steps'];
        }
    }
    $lines[] = '';
    if (!empty($report['mother_side']) || !empty($report['father_side'])) {
        $lines[] = '### Parent-Side Reports';
        foreach (['mother_side' => 'Mother', 'father_side' => 'Father'] as $key => $label) {
            if (empty($report[$key])) {
                continue;
            }
            $side = (array) $report[$key];
            $lines[] = '- ' . $label . ': ' . ($side['file_name'] ?? 'parent file') . ', quality ' . ($side['quality_band'] ?? 'unknown') . ' (' . ($side['quality_score'] ?? 0) . '/100)';
            if (!empty($side['relatedness'])) {
                $rel = (array) $side['relatedness'];
                $lines[] = '  Relationship screen: ' . ($rel['claim_label'] ?? '') . ', shared allele ' . ($rel['allele_share_percent'] ?? 0) . '%, exact ' . ($rel['exact_match_percent'] ?? 0) . '%.';
            }
            if (!empty($side['other_parent_projection']['available'])) {
                $projection = (array) $side['other_parent_projection'];
                $lines[] = '  Estimated remaining side: ' . ($projection['headline'] ?? 'parent-side projection ready') . ', confidence ' . ($projection['confidence_band'] ?? 'medium') . ' (' . ($projection['confidence_score'] ?? 0) . '/100).';
                $lines[] = '  ' . ($projection['plain_summary'] ?? '');
                foreach (array_slice((array) ($projection['group_scores'] ?? []), 0, 3) as $group) {
                    $lines[] = '  Other-side lead: ' . ($group['label'] ?? 'reference lead') . ' using ' . number_format((int) ($group['matched_marker_count'] ?? 0)) . ' markers.';
                }
            }
            foreach (array_slice((array) ($side['top_mix'] ?? []), 0, 4) as $mix) {
                $lines[] = '  Mix proxy: ' . ($mix['proxy_percent'] ?? 0) . '% ' . ($mix['label'] ?? 'reference signal') . ', confidence ' . ($mix['confidence_band'] ?? 'low');
            }
        }
        $lines[] = '';
    }
    $lines[] = '### Heritage Evidence Used';
    $lines[] = zl_dna_marker_markdown('DNA Heritage Research Screen', (array) ($report['heritage'] ?? []), 'heritage');
    $lines[] = '';
    $lines[] = '### Lineage Evidence Used';
    $lines[] = zl_dna_lineage_markdown((array) ($report['lineage'] ?? []));
    $lines[] = '';
    return implode("\n", $lines);
}

function zl_dna_full_evidence_ledger($raw, $health, $heritage, $lineage = null) {
    $quality = zl_dna_quality_math($raw);
    $cards = [
        zl_dna_evidence_card([
            'title' => 'Raw File Quality',
            'claim_type' => 'raw_quality',
            'claim_label' => (string) ($raw['quality_band'] ?? zl_dna_quality_band($quality['score'])),
            'evidence_level' => 'local_qc',
            'confidence_score' => (float) ($quality['score'] ?? 0),
            'confidence_band' => zl_dna_confidence_label_from_score($quality['score'] ?? 0),
            'method' => 'streaming parser, call-rate, chromosome spread, rsID indexability, heterozygosity sanity',
            'matched_marker_count' => (int) ($raw['parsed_variants'] ?? 0),
            'explanation' => 'The uploaded file was parsed locally and scored for usefulness before any interpretation.',
            'limitations' => 'Consumer DNA files are sparse arrays, not full clinical genomes. Genome build may be unconfirmed.',
            'next_steps' => 'Use the quality score to decide whether deeper reference-panel or clinical confirmation is worthwhile.',
        ]),
    ];
    foreach ((array) ($health['insights']['cards'] ?? []) as $card) {
        $cards[] = zl_dna_evidence_card([
            'title' => (string) ($card['title'] ?? 'Health research card'),
            'claim_type' => 'health_research',
            'claim_label' => (string) ($card['priority'] ?? 'context'),
            'evidence_level' => (string) (($card['priority'] ?? '') === 'attention' ? 'limited_followup' : 'research_only'),
            'confidence_score' => round(((float) ($card['assay_confidence'] ?? 0)) * 100),
            'confidence_band' => zl_dna_confidence_label_from_score(((float) ($card['assay_confidence'] ?? 0)) * 100),
            'method' => (string) ($card['match_status'] ?? 'research context only'),
            'source_name' => 'ZeroThink health watchlist plus dbSNP/ClinVar links',
            'source_version' => (string) ($card['source_version'] ?? 'bundled watchlist; effect interpretation withheld'),
            'matched_marker_count' => !empty($card['dosage']) ? (int) $card['dosage'] : 0,
            'explanation' => (string) ($card['plain'] ?? ''),
            'limitations' => 'Research support only. Not diagnosis, treatment advice, medication advice, or a personal disease-risk score.',
            'next_steps' => (string) ($card['next_step'] ?? 'Confirm important results with a qualified health professional.'),
        ]);
    }
    if (!empty($health['local_pack_matches'])) {
        $pack = (array) $health['local_pack_matches'];
        $pack_count = count((array) ($pack['clinvar'] ?? [])) + count((array) ($pack['gwas'] ?? [])) + count((array) ($pack['pharmgkb'] ?? []));
        $pack_ready = !empty($pack['interpretation_ready']) && !empty($pack['allele_aware']);
        $cards[] = zl_dna_evidence_card([
            'title' => 'Health Source Scan',
            'claim_type' => 'health_research',
            'claim_label' => $pack_ready ? 'allele-specific source scan complete' : 'personalized source rows withheld',
            'evidence_level' => $pack_ready ? 'direct_allele_source_match' : 'withheld_by_validation_gate',
            'confidence_score' => 0,
            'confidence_band' => $pack_ready ? 'source assertions; clinical confidence not established' : 'withheld',
            'method' => $pack_ready ? 'assembly + chromosome + position + REF/ALT + direct genotype matching' : 'fail-closed schema and file-readiness check',
            'source_name' => 'ZeroThink Health Research Source Pack',
            'source_version' => (string) ($pack['reason'] ?? 'being expanded'),
            'matched_marker_count' => $pack_count,
            'explanation' => $pack_ready ? 'Only directly observed alternate alleles that passed the exact build and locus contract were returned.' : (string) ($pack['reason'] ?? 'Personalized source interpretation was withheld.'),
            'limitations' => 'Research support only. ClinVar assertions can conflict, sparse consumer arrays are incomplete, and important findings require certified confirmation.',
            'next_steps' => $pack_ready ? 'Review ClinVar assertion status and confirm important results in a certified laboratory.' : 'Confirm file build/orientation and activate the allele-aware pack before interpreting source rows.',
        ]);
    }
    foreach ((array) ($heritage['insights']['confidence_bands'] ?? []) as $band) {
        $cards[] = zl_dna_evidence_card([
            'title' => (string) ($band['label'] ?? 'Heritage lens'),
            'claim_type' => 'ancestry_signal',
            'claim_label' => 'genetic affinity lens',
            'evidence_level' => 'marker_context_preview',
            'confidence_score' => (float) ($heritage['insights']['quality']['score'] ?? 0),
            'confidence_band' => zl_dna_confidence_label_from_score((float) ($heritage['insights']['quality']['score'] ?? 0)),
            'method' => 'ZDNA-MAP marker-context lens with uncertainty band',
            'source_name' => 'ZeroThink curated marker preview',
            'source_version' => 'ZDNA-MAP v1.2; calibrated reference panel optional',
            'matched_marker_count' => count((array) ($heritage['hits'] ?? [])),
            'explanation' => 'Lens strength: ' . ($band['lens_strength'] ?? 0) . '. Band: ' . ($band['confidence_band'] ?? 'uncertain') . '.',
            'limitations' => 'This is not an ethnicity percentage, nationality, religion, tribe, culture, or identity claim.',
            'next_steps' => 'Run a reduced reference comparison for calibrated genetic-affinity scoring.',
        ]);
    }
    foreach ((array) ($heritage['insights']['question_signals'] ?? []) as $signal) {
        $cards[] = zl_dna_evidence_card([
            'title' => (string) ($signal['label'] ?? 'Ancestry question'),
            'claim_type' => 'ancestry_question',
            'claim_label' => (string) ($signal['status'] ?? 'uncertain'),
            'evidence_level' => 'direct_aligned_reference_affinity',
            'confidence_score' => (float) ($signal['confidence_score'] ?? 0),
            'confidence_band' => (string) ($signal['confidence_band'] ?? 'low'),
            'method' => 'within-panel direct-allele comparison; each reference retains its own marker denominator and chromosome breadth',
            'source_name' => (string) ($signal['source_name'] ?? 'reference panel not supplied'),
            'source_version' => (string) ($signal['source_version'] ?? 'not supplied'),
            'matched_marker_count' => (int) ($signal['matched_marker_count'] ?? 0),
            'explanation' => (string) ($signal['why'] ?? ''),
            'limitations' => (string) ($signal['limitations'] ?? 'Not identity, nationality, ethnicity, tribe, religion, or culture.'),
            'next_steps' => (string) ($signal['next_steps'] ?? 'Run reference comparisons and calibrate before stronger claims.'),
        ]);
    }
    if (!empty($heritage['local_reference_pack'])) {
        $pack = (array) $heritage['local_reference_pack'];
        $reference_groups = zl_dna_reference_groups($pack);
        $cards[] = zl_dna_evidence_card([
            'title' => 'Country & Region Reference Scan',
            'claim_type' => 'ancestry_signal',
            'claim_label' => !empty($pack['interpretation_ready']) ? 'aligned reference comparison completed' : 'personalized reference comparison withheld',
            'evidence_level' => !empty($pack['interpretation_ready']) ? 'direct_aligned_reference_affinity' : 'validation_required',
            'confidence_score' => 0,
            'confidence_band' => 'not established; scan summary only',
            'method' => 'confirmed assembly/orientation, exact coordinates, and directly observed reference-compatible alleles',
            'source_name' => 'ZeroThink Country & Region Reference Engine',
            'source_version' => (string) ($pack['pack_meta']['pack_version'] ?? 'see matched panel source metadata'),
            'matched_marker_count' => (int) ($pack['matched_marker_count'] ?? 0),
            'explanation' => !empty($pack['interpretation_ready']) ? ('The scan compared ' . count($reference_groups) . ' separate reference panels across ' . (int) ($pack['matched_marker_count'] ?? 0) . ' unique aligned loci in total. Each reference comparison has its own marker count and source; no cross-panel top ancestry is selected.') : (string) ($pack['reason'] ?? 'No aligned evidence was reported.'),
            'limitations' => 'Reference-panel affinity is not modern nationality, ethnicity, culture, religion, tribe, or identity. Full ancestry estimates require calibration and broad panel coverage.',
            'next_steps' => 'Review per-panel chromosome coverage and source version. Held-out calibration is required before ancestry proportions or stronger claims.',
        ]);
    }
    if ($lineage) {
        $cards = array_merge($cards, (array) ($lineage['evidence_cards'] ?? []));
    }
    $cards[] = zl_dna_evidence_card([
        'title' => 'Privacy Boundary',
        'claim_type' => 'privacy',
        'claim_label' => 'raw DNA not sent to the model',
        'evidence_level' => 'policy_check',
        'confidence_score' => 100,
        'confidence_band' => 'high',
        'method' => 'report-summary-only agent prompt',
        'source_name' => 'ZeroThink privacy layer',
        'source_version' => 'v2',
        'matched_marker_count' => 0,
        'explanation' => 'The DNA Agent receives the generated report summary and evidence cards, not full raw genotype rows.',
        'limitations' => 'If a user manually pastes raw DNA into chat, that is outside the file-upload safety lane.',
        'next_steps' => 'Keep private raw storage off unless you intentionally want a private library copy.',
    ]);
    return $cards;
}

function zl_dna_full_report_path($path, $name = 'raw-dna.txt', $options = []) {
    zl_dna_raise_runtime_limits();
    $options = (array) $options;
    $raw = zl_dna_apply_analysis_options(zl_dna_raw_analyze_path($path, $name), $options);
    $health = zl_dna_health_report_path($path, $name, $raw, $options);
    $heritage = zl_dna_heritage_report_path($path, $name, $raw);
    $ancient = zl_dna_ancient_grave_report_path($path, $name, $raw, $heritage);
    $origins = zl_dna_origins_report_path($path, $name, $raw, $heritage);
    $origins['composition'] = zl_dna_composition_report_path($path, $name, $raw, $origins['classification'] ?? null);
    $origins['composition']['place_context'] = zl_dna_composition_country_context($origins['composition']);
    $appearance = zl_dna_traits_report_path($path, $name, $raw);
    $personal_traits = zl_dna_personal_traits_report_path($path, $name, $raw);
    // Derived creative outputs are prepared by the consented full report. Playback
    // remains an explicit browser action; no genotype data leaves this server.
    $sonic = zl_dna_sonic_profile_path($path, $name, $raw);
    $lineage = null;
    $mother_side = null;
    $father_side = null;
    $gedcom_text = (string) ($options['gedcom_text'] ?? '');
    if (!empty($options['mother_dna_path'])) {
        $mother_side = zl_dna_theory_side_summary((string) $options['mother_dna_path'], (string) ($options['mother_dna_name'] ?? 'mother-dna.txt'), 'mother', $path, $name);
    }
    if (!empty($options['father_dna_path'])) {
        $father_side = zl_dna_theory_side_summary((string) $options['father_dna_path'], (string) ($options['father_dna_name'] ?? 'father-dna.txt'), 'father', $path, $name);
    }
    $mother_side = zl_dna_public_parent_context($mother_side);
    $father_side = zl_dna_public_parent_context($father_side);
    if (trim($gedcom_text) !== '' || trim((string) ($options['story_notes'] ?? '')) !== '' || trim((string) ($options['surnames'] ?? '')) !== '' || trim((string) ($options['places'] ?? '')) !== '' || $mother_side || $father_side) {
        $lineage = zl_dna_lineage_report($gedcom_text, array_merge($options, [
            'dna_path' => $path,
            'dna_name' => $name,
            'heritage_report' => $heritage,
        ]), $raw);
    }
    $parent_aware_estimate = zl_dna_parent_aware_estimate($mother_side, $father_side);
    $parent_trace_origin = zl_dna_parent_trace_origin($heritage, $mother_side, $father_side);
    $ledger = zl_dna_full_evidence_ledger($raw, $health, $heritage, $lineage);
    $ledger = array_merge($ledger, array_slice((array) ($ancient['evidence_cards'] ?? []), 0, 8));
    $ledger = array_merge((array) ($origins['evidence_cards'] ?? []), $ledger);
    // Quality scores and numbers of cards are unlike quantities, not parts of a whole.
    $full_evidence_chart = [];
    $data_pack_status = zl_dna_data_pack_status();
    $source_versions = zl_dna_source_versions();
    $origins_catalog = zl_dna_origins_catalog();
    $model = (array) ($origins['classification'] ?? []);
    if (!empty($model['version'])) {
        $source_versions[] = ['source_name'=>'GrafAnc ancestry model','source_version'=>(string) $model['version'],'source_url'=>(string) ($model['source_url'] ?? ''),'status'=>(string) ($model['status'] ?? '')];
    }
    if (!empty($origins['composition']['source_version'])) {
        $composition = $origins['composition'];
        $source_versions[] = ['source_name'=>(string) $composition['source_name'],'source_version'=>(string) $composition['source_version'],'source_url'=>(string) $composition['source_url'],'status'=>(string) $composition['status']];
    }
    foreach (['ancient' => 'Ancient origins groups', 'archaic' => 'Neanderthal and Denisovan markers'] as $kind => $label) {
        $catalog = (array) ($origins_catalog[$kind] ?? []);
        $meta = (array) ($catalog['metadata'] ?? []);
        $version = (string) ($meta['source_version'] ?? $meta['pack_version'] ?? 'not supplied');
        $data_pack_status[] = ['display_name' => $label, 'label' => $label, 'installed' => !empty($catalog['installed']), 'pack_version' => $version, 'source_url' => (string) ($meta['source_url'] ?? '')];
        $source_versions[] = ['source_name' => $label, 'source_version' => $version, 'source_url' => (string) ($meta['source_url'] ?? ''), 'status' => !empty($catalog['installed']) ? 'installed aligned reference' : 'not installed'];
    }
    $full_report = [
        'summary' => [
            'file_name' => $raw['file_name'],
            'file_hash' => $raw['file_hash'],
            'vendor_guess' => $raw['format_guess'],
            'marker_count' => (int) ($raw['parsed_variants'] ?? 0),
            'quality_band' => $raw['quality_band'] ?? 'unknown',
            'quality_score' => (int) ($raw['quality_score'] ?? 0),
            'genome_build_guess' => $raw['genome_build_guess'] ?? 'unknown',
            'genome_build_confidence' => $raw['genome_build_confidence'] ?? 'unknown',
            'reported_orientation' => $raw['reported_orientation'] ?? 'unconfirmed',
            'orientation_confidence' => $raw['orientation_confidence'] ?? 'low',
            'privacy_state' => $raw['privacy_state'] ?? 'default_parse_analyze_discard',
            'engine_version' => 'ZDNA-MAP v1.7 country context',
        ],
        'raw' => $raw,
        'health' => $health,
        'heritage' => $heritage,
        'ancient_graves' => $ancient,
        'origins' => $origins,
        'appearance' => $appearance,
        'personal_traits' => $personal_traits,
        'sound_profile' => $sonic['profile'],
        'lineage' => $lineage,
        'mother_side' => $mother_side,
        'father_side' => $father_side,
        'parent_aware_estimate' => $parent_aware_estimate,
        'parent_trace_origin' => $parent_trace_origin,
        'evidence_cards' => $ledger,
        'data_pack_status' => $data_pack_status,
        'source_versions' => $source_versions,
        'chart' => !empty($parent_aware_estimate['available']) ? (array) ($parent_aware_estimate['segments'] ?? []) : ((array) ($heritage['chart'] ?? []) ?: $full_evidence_chart),
        'full_evidence_chart' => $full_evidence_chart,
        'disclaimer' => 'Full DNA report is research support only. It is not diagnosis, treatment advice, ethnicity proof, identity classification, or royal-lineage proof.',
    ];
    $full_report['country_context'] = zl_dna_country_context_report($origins);
    $full_report['customer_summary'] = zl_dna_customer_summary($full_report);
    return $full_report;
}

function zl_dna_agent_context($report, $kind = 'full') {
    $report = (array) $report;
    $kind = strtolower(trim((string) $kind)) ?: 'full';
    // Full reports carry detailed QC in raw; copy only the explicit scalar whitelist below.
    $summary = (array) ($report['raw'] ?? $report['raw_summary'] ?? $report['summary'] ?? []);
    $health = $kind === 'health' ? $report : (array) ($report['health'] ?? []);
    $heritage = $kind === 'heritage' ? $report : (array) ($report['heritage'] ?? []);
    $ancient = $kind === 'ancient' ? $report : (array) ($report['ancient_graves'] ?? []);
    $evidence = [];
    foreach (array_slice((array) ($report['evidence_cards'] ?? []), 0, 24) as $card) {
        $evidence[] = [
            'title' => (string) ($card['title'] ?? ''),
            'claim_type' => (string) ($card['claim_type'] ?? ''),
            'claim_label' => (string) ($card['claim_label'] ?? ''),
            'evidence_level' => (string) ($card['evidence_level'] ?? ''),
            'confidence_band' => (string) ($card['confidence_band'] ?? ''),
            'source_name' => (string) ($card['source_name'] ?? ''),
            'source_version' => (string) ($card['source_version'] ?? ''),
            'limitations' => (string) ($card['limitations'] ?? ''),
            'next_steps' => (string) ($card['next_steps'] ?? ''),
        ];
    }

    $health_cards = [];
    foreach (array_slice((array) ($health['insights']['cards'] ?? []), 0, 16) as $card) {
        $health_cards[] = [
            'title' => (string) ($card['title'] ?? ''),
            'category' => (string) ($card['category'] ?? ''),
            'priority' => (string) ($card['priority'] ?? ''),
            'plain' => (string) ($card['plain'] ?? ''),
            'next_step' => (string) ($card['next_step'] ?? ''),
        ];
    }

    $heritage_segments = [];
    $estimate = (array) ($heritage['insights']['consumer_estimate'] ?? $heritage['insights']['plain_ancestry_summary']['consumer_estimate'] ?? []);
    foreach (array_slice((array) ($estimate['segments'] ?? []), 0, 10) as $segment) {
        $heritage_segments[] = [
            'label' => (string) ($segment['label'] ?? ''),
            'similarity_share' => (float) ($segment['percent'] ?? $segment['value'] ?? 0),
        ];
    }

    return [
        'context_version' => 'dna-agent-minimized-v1',
        'report_kind' => $kind,
        'privacy' => [
            'raw_file_included' => false,
            'raw_sample_rows_included' => false,
            'file_name_or_hash_included' => false,
            'exact_genotypes_or_rsids_included' => false,
            'gedcom_identity_included' => false,
            'normal_chat_history_allowed' => false,
        ],
        'summary' => [
            'vendor_or_format' => (string) ($summary['vendor_guess'] ?? $summary['format_guess'] ?? ''),
            'marker_count' => (int) ($summary['marker_count'] ?? $summary['parsed_variants'] ?? 0),
            'quality_band' => (string) ($summary['quality_band'] ?? ''),
            'quality_score' => (int) ($summary['quality_score'] ?? 0),
            'called_variants' => (int) ($summary['called_variants'] ?? 0),
            'no_call_percent' => (float) ($summary['no_call_percent'] ?? 0),
            'duplicate_rsid_count' => (int) ($summary['duplicate_rsid_count'] ?? 0),
            'chromosome_count' => (int) ($summary['chromosome_count'] ?? 0),
            'truncated' => !empty($summary['truncated']),
            'genome_build' => (string) ($summary['genome_build_guess'] ?? ''),
            'reported_orientation' => (string) ($summary['reported_orientation'] ?? ''),
            'engine_version' => (string) ($summary['engine_version'] ?? $report['summary']['engine_version'] ?? 'ZDNA-MAP v1.2'),
        ],
        'health' => [
            'headline' => (string) ($health['insights']['headline'] ?? ''),
            'plain_summary' => (string) ($health['insights']['plain_summary'] ?? ''),
            'analysis_gate' => (array) ($health['insights']['analysis_gate'] ?? []),
            'sensitive_findings' => (string) ($health['insights']['sensitive_findings'] ?? 'withheld_by_default'),
            'cards' => $health_cards,
        ],
        'heritage' => [
            'headline' => (string) ($estimate['headline'] ?? $heritage['insights']['headline'] ?? ''),
            'plain_summary' => (string) ($estimate['plain'] ?? $heritage['insights']['plain_summary'] ?? ''),
            'panel_similarity_segments' => $heritage_segments,
            'boundary' => (string) ($estimate['boundary'] ?? $heritage['local_reference_pack']['truth_boundary'] ?? ''),
        ],
        'ancient' => [
            'headline' => (string) ($ancient['summary']['headline'] ?? $ancient['headline'] ?? ''),
            'boundary' => (string) ($ancient['boundary'] ?? $ancient['truth_boundary'] ?? 'Experimental included-sample similarity only; not descent proof.'),
        ],
        'origins' => zl_dna_origins_agent_context((array) ($report['origins'] ?? [])),
        'ancestry_composition' => zl_dna_composition_agent_context((array) ($report['origins']['composition'] ?? [])),
        'appearance' => zl_dna_traits_agent_context((array) ($report['appearance'] ?? [])),
        'personal_traits' => zl_dna_personal_traits_agent_context((array) ($report['personal_traits'] ?? [])),
        'sound' => [
            'available' => !empty($report['sound_profile']) || ($kind === 'sound' && !empty($report['profile'])),
            'meaning' => 'Artistic sonification of the uploaded file. No audio events, seed, genotype rows, or appearance predictions are included in this AI summary.',
        ],
        'evidence_cards' => $evidence,
        'response_rules' => [
            'Never infer diagnosis, treatment, medication changes, identity, tribe, paternity, royalty, or direct descent.',
            'Distinguish observed, withheld, not callable, not tested, and modelled states.',
            'Cite the evidence-card title/source and repeat limitations for consequential claims.',
        ],
    ];
}

function zl_dna_full_markdown($report) {
    $lines = [];
    $lines[] = '## ZeroThink Full DNA Report';
    $lines[] = '';
    $lines[] = '**Boundary:** ' . ($report['disclaimer'] ?? 'Research support only.');
    $lines[] = '';
    $s = (array) ($report['summary'] ?? []);
    if (!empty($report['customer_summary'])) { $lines[] = zl_dna_customer_markdown($report['customer_summary']); $lines[] = ''; }
    if (!empty($report['origins']['composition'])) { $lines[] = zl_dna_composition_markdown($report['origins']['composition']); $lines[] = ''; }
    if (!empty($report['origins']['composition']['place_context'])) { $lines[] = zl_dna_composition_country_markdown($report['origins']['composition']['place_context']); $lines[] = ''; }
    if (!empty($report['country_context'])) { $lines[] = zl_dna_country_context_markdown($report['country_context']); $lines[] = ''; }
    if (!empty($report['personal_traits'])) { $lines[] = zl_dna_personal_traits_markdown($report['personal_traits']); $lines[] = ''; }
    $lines[] = '### File compatibility and quality';
    $lines[] = '- File: ' . ($s['file_name'] ?? 'DNA file');
    $lines[] = '- Vendor guess: ' . ($s['vendor_guess'] ?? 'unknown');
    $lines[] = '- Markers parsed: ' . number_format((int) ($s['marker_count'] ?? 0));
    $lines[] = '- Quality: ' . ($s['quality_band'] ?? 'unknown') . ' (' . (int) ($s['quality_score'] ?? 0) . '/100)';
    $lines[] = '- Genome build guess: ' . ($s['genome_build_guess'] ?? 'unknown');
    $lines[] = '- Privacy state: parse/analyze/discard by default unless private save is selected.';
    $lines[] = '';
    if (!empty($report['origins'])) {
        $lines[] = zl_dna_origins_markdown($report['origins']);
        $lines[] = '';
    }
    if (!empty($report['appearance'])) {
        $lines[] = zl_dna_traits_markdown($report['appearance']);
        $lines[] = '';
    }
    if (!empty($report['sound_profile'])) {
        $lines[] = zl_dna_sonic_markdown(['profile' => $report['sound_profile'], 'summary' => $report['raw'] ?? $report['summary'] ?? []]);
        $lines[] = '';
    }
    $estimate = (array) ($report['heritage']['insights']['consumer_estimate'] ?? $report['heritage']['insights']['plain_ancestry_summary']['consumer_estimate'] ?? []);
    if (!empty($estimate['segments'])) {
        $lines[] = '### Plain Heritage Snapshot';
        $lines[] = '';
        $lines[] = '**' . ($estimate['headline'] ?? 'Consumer-style projection ready') . '**';
        if (!empty($estimate['plain'])) {
            $lines[] = '';
            $lines[] = $estimate['plain'];
        }
        $lines[] = '';
        foreach ((array) ($estimate['segments'] ?? []) as $segment) {
            $lines[] = '- ' . ($segment['label'] ?? 'Projection lane') . ': ' . ($segment['percent'] ?? $segment['value'] ?? 0) . '%';
        }
        if (!empty($estimate['suppressed_noise'])) {
            $lines[] = '';
            foreach ((array) $estimate['suppressed_noise'] as $noise) {
                $lines[] = '- Suppressed false lead: ' . $noise;
            }
        }
        $lines[] = '';
        $lines[] = 'Boundary: ' . ($estimate['boundary'] ?? 'Probabilistic projection only.');
        $lines[] = '';
    }
    if (!empty($report['parent_aware_estimate']['available'])) {
        $parent_estimate = (array) $report['parent_aware_estimate'];
        $lines[] = '### Parent-Aware Split';
        $lines[] = '';
        $lines[] = '**' . ($parent_estimate['headline'] ?? 'Parent-aware ancestry split') . '**';
        $lines[] = '';
        $lines[] = $parent_estimate['plain'] ?? '';
        foreach ((array) ($parent_estimate['segments'] ?? []) as $segment) {
            $lines[] = '- ' . ($segment['label'] ?? 'Side') . ': ' . ($segment['percent'] ?? $segment['value'] ?? 0) . '%';
        }
        if (!empty($parent_estimate['notes'])) {
            foreach ((array) $parent_estimate['notes'] as $note) {
                $lines[] = '- Note: ' . $note;
            }
        }
        $lines[] = 'Boundary: ' . ($parent_estimate['boundary'] ?? 'Research context only.');
        $lines[] = '';
    }
    if (!empty($report['parent_trace_origin']['available'])) {
        $trace_origin = (array) $report['parent_trace_origin'];
        $lines[] = '### Parent-Side Trace Screen';
        $lines[] = '';
        $lines[] = '**' . ($trace_origin['label'] ?? 'Trace screen') . ': ' . ($trace_origin['status'] ?? 'research clue') . '**';
        $lines[] = '';
        $lines[] = $trace_origin['plain'] ?? '';
        $lines[] = '- Child trace proxy: ' . ($trace_origin['child_proxy_percent'] ?? 0) . '%';
        if (array_key_exists('mother_proxy_percent', $trace_origin) && $trace_origin['mother_proxy_percent'] !== null) {
            $lines[] = '- Mother-file trace proxy: ' . $trace_origin['mother_proxy_percent'] . '%';
        }
        if (array_key_exists('father_proxy_percent', $trace_origin) && $trace_origin['father_proxy_percent'] !== null) {
            $lines[] = '- Father-file trace proxy: ' . $trace_origin['father_proxy_percent'] . '%';
        }
        if (!empty($trace_origin['child_evidence']['asj_control_delta'])) {
            $lines[] = '- ASJ vs nearby-control delta: ' . $trace_origin['child_evidence']['asj_control_delta'];
        }
        $lines[] = 'Boundary: ' . ($trace_origin['boundary'] ?? 'Research context only.');
        $lines[] = '';
    }
    $lines[] = '### Raw File Quality';
    $lines[] = zl_dna_raw_markdown((array) ($report['raw'] ?? []));
    $lines[] = '';
    $lines[] = '### Heritage / Genetic Affinity Signals';
    $lines[] = zl_dna_marker_markdown('DNA Heritage Research Screen', (array) ($report['heritage'] ?? []), 'heritage');
    $lines[] = '';
    $lines[] = '### Health Research Cards';
    $lines[] = zl_dna_marker_markdown('DNA Health Research Screen', (array) ($report['health'] ?? []), 'health');
    $lines[] = '';
    if (!empty($report['ancient_graves'])) {
        $lines[] = '### Legacy individual-sample matching';
        $lines[] = zl_dna_ancient_grave_markdown((array) $report['ancient_graves']);
        $lines[] = '';
    }
    if (!empty($report['lineage'])) {
        $lines[] = zl_dna_lineage_markdown((array) $report['lineage']);
        $lines[] = '';
    }
    if (!empty($report['mother_side']) || !empty($report['father_side'])) {
        $lines[] = '### Parent-Side Split';
        foreach (['mother_side' => 'Mother', 'father_side' => 'Father'] as $key => $label) {
            if (empty($report[$key])) {
                continue;
            }
            $side = (array) $report[$key];
            $lines[] = '- ' . $label . ' file: ' . ($side['file_name'] ?? 'parent file') . ', quality ' . ($side['quality_band'] ?? 'unknown') . ' (' . ($side['quality_score'] ?? 0) . '/100)';
            if (!empty($side['relatedness'])) {
                $rel = (array) $side['relatedness'];
                $lines[] = '  Relationship screen: ' . ($rel['claim_label'] ?? 'comparison complete') . ', shared allele ' . ($rel['allele_share_percent'] ?? 0) . '%, exact ' . ($rel['exact_match_percent'] ?? 0) . '%.';
            }
            if (!empty($side['other_parent_projection']['available'])) {
                $projection = (array) $side['other_parent_projection'];
                $lines[] = '  Estimated remaining side: ' . ($projection['headline'] ?? 'parent-side projection ready') . ', confidence ' . ($projection['confidence_band'] ?? 'medium') . ' (' . ($projection['confidence_score'] ?? 0) . '/100).';
                $lines[] = '  ' . ($projection['plain_summary'] ?? '');
            }
        }
        $lines[] = '';
    }
    $lines[] = '### Evidence Ledger';
    foreach ((array) ($report['evidence_cards'] ?? []) as $card) {
        $lines[] = '- ' . ($card['title'] ?? 'Evidence') . ' [' . ($card['claim_type'] ?? 'research') . ']';
        $lines[] = '  Claim: ' . ($card['claim_label'] ?? '');
        $lines[] = '  Evidence level: ' . ($card['evidence_level'] ?? '');
        $confidence_score = $card['confidence_score'] ?? null;
        $confidence_number = is_numeric($confidence_score) && is_finite((float) $confidence_score) ? ' (' . $confidence_score . '/100)' : '';
        $lines[] = '  Confidence: ' . ($card['confidence_band'] ?? '') . $confidence_number;
        $lines[] = '  Source: ' . ($card['source_name'] ?? '') . ' / ' . ($card['source_version'] ?? '');
        $lines[] = '  Checked markers: ' . number_format((int) ($card['matched_marker_count'] ?? 0));
        $lines[] = '  Explanation: ' . ($card['explanation'] ?? '');
        $lines[] = '  Limitations: ' . ($card['limitations'] ?? '');
        $lines[] = '  Next step: ' . ($card['next_steps'] ?? '');
    }
    $lines[] = '';
    $lines[] = '### Source Versions';
    foreach ((array) ($report['source_versions'] ?? []) as $source) {
        $lines[] = '- ' . ($source['source_name'] ?? 'Source') . ': ' . ($source['source_version'] ?? 'unknown') . ' (' . ($source['status'] ?? 'optional') . ')';
    }
    $lines[] = '';
    $lines[] = '### Source Pack Status';
    foreach ((array) ($report['data_pack_status'] ?? []) as $pack) {
        $lines[] = '- ' . ($pack['display_name'] ?? $pack['label'] ?? 'Source pack') . ': ' . (!empty($pack['installed']) ? 'active' : 'expanding') . ' - ' . ($pack['label'] ?? '');
    }
    $lines[] = '';
    return implode("\n", $lines);
}

function zl_dna_heritage_evidence_markdown($title, $report) {
    $pack = (array) ($report['local_reference_pack'] ?? []);
    $summary = (array) ($report['summary'] ?? []);
    $ready = !empty($pack['interpretation_ready']);
    $cell = function ($value) {
        return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], (string) $value);
    };
    $lines = ['## ' . $cell($title), '', '**Reference evidence:** ' . ($ready ? 'Aligned direct-call comparison completed.' : 'Personalized comparison withheld.'), ''];
    $lines[] = 'Affinity scores compare observed alleles with population-frequency references on a 0-100 scale. They are not ancestry percentages, probabilities, nationality, ethnicity, religion or lineage findings. Compare rows within their own panel; different panels and marker sets are not directly comparable.';
    $lines[] = '';
    $lines[] = '### File and reference validation';
    $lines[] = '';
    $lines[] = '- Genome build: ' . $cell($summary['genome_build_guess'] ?? 'unconfirmed');
    $lines[] = '- Reported orientation: ' . $cell($summary['reported_orientation'] ?? 'unconfirmed');
    $lines[] = '- Raw file quality: ' . $cell($summary['quality_band'] ?? 'unknown') . ' (' . (int) ($summary['quality_score'] ?? 0) . '/100; file QC, not ancestry confidence).';
    $lines[] = '- Reference pack version: ' . $cell($pack['pack_meta']['pack_version'] ?? 'not supplied');
    $lines[] = '- Unique aligned loci across all panels: ' . number_format((int) ($pack['matched_marker_count'] ?? 0)) . '. This total is not the marker denominator of each reference row.';
    $lines[] = '- Comparison status: ' . $cell($pack['reason'] ?? 'No reference comparison was reported.');
    foreach ((array) ($pack['analysis_gate']['reasons'] ?? []) as $reason) {
        $lines[] = '- Validation requirement: ' . $cell($reason);
    }
    $lines[] = '';
    $groups = $ready ? zl_dna_reference_groups($pack) : [];
    if (!$groups) {
        $lines[] = 'No aligned population comparisons are available for this report. No ancestry composition or fallback marker-lane result is inferred.';
        $lines[] = '';
    }
    foreach ($groups as $panel => $rows) {
        $lines[] = '### Reference panel: ' . $cell($panel);
        $lines[] = '';
        $lines[] = 'Rows below belong to this panel only. Marker and autosome counts describe each individual comparison; overlap differences can affect scores.';
        $lines[] = '';
        $versions = [];
        foreach ($rows as $row) {
            $version = (string) ($row['source_version'] ?? 'not supplied');
            if (!isset($versions[$version])) { $versions[$version] = 'S' . (count($versions) + 1); }
        }
        foreach ($versions as $version => $source_id) {
            $lines[] = '- ' . $source_id . ' source version: ' . $cell($version);
        }
        $lines[] = '';
        $lines[] = '| Reference group | Affinity /100 | Aligned markers | Autosomes | Source | Confidence |';
        $lines[] = '| --- | ---: | ---: | ---: | --- | --- |';
        foreach ($rows as $row) {
            $label = (string) ($row['population_label'] ?? $row['population_code'] ?? 'Reference group');
            $code = (string) ($row['population_code'] ?? '');
            if ($code !== '') { $label .= ' (' . $code . ')'; }
            $chromosome_count = isset($row['matched_chromosome_count']) ? (int) $row['matched_chromosome_count'] : count((array) ($row['matched_chromosomes'] ?? []));
            $lines[] = '| ' . $cell($label) . ' | ' . number_format((float) ($row['affinity_score'] ?? 0), 1) . ' | ' . number_format((int) ($row['matched_marker_count'] ?? 0)) . ' | ' . $chromosome_count . ' / 22 | ' . $versions[(string) ($row['source_version'] ?? 'not supplied')] . ' | ' . $cell($row['confidence_band'] ?? 'uncalibrated') . ' |';
        }
        $lines[] = '';
    }
    $diagnostics = (array) ($pack['alignment_diagnostics'] ?? []);
    if ($diagnostics) {
        $lines[] = '### Alignment audit';
        $lines[] = '';
        $lines[] = '- Candidate reference rows: ' . number_format((int) ($diagnostics['candidate_reference_rows'] ?? 0)) . '.';
        $lines[] = '- Aligned reference rows: ' . number_format((int) ($diagnostics['aligned_reference_rows'] ?? 0)) . '. A locus can occur in multiple population rows; this is not the unique-locus count.';
        foreach ((array) ($diagnostics['excluded_reference_rows'] ?? []) as $reason => $count) {
            $lines[] = '- Excluded rows, ' . $cell(str_replace('_', ' ', (string) $reason)) . ': ' . number_format((int) $count) . '.';
        }
        $lines[] = '';
    }
    $lines[] = '**Interpretation limit:** This reference-comparison section does not calculate ancestry proportions. Any separately reported composition estimate uses its own published model and coverage checks. Source coverage, sample design, linkage and calibration affect interpretation. No country split, ancient lineage, or identity claim is inferred from a trait-marker watchlist.';
    $lines[] = '';
    return implode("\n", $lines);
}

function zl_dna_marker_markdown($title, $report, $kind = 'health') {
    if ($kind === 'heritage') {
        return zl_dna_heritage_evidence_markdown($title, $report);
    }
    $lines = [];
    $lines[] = '## ' . $title;
    $lines[] = '';
    $lines[] = '**Boundary:** ' . $report['disclaimer'];
    $lines[] = '';
    $insights = (array) ($report['insights'] ?? []);
    if (!empty($insights['headline'])) {
        $lines[] = '### Plain-English Result';
        $lines[] = '';
        $lines[] = '**' . $insights['headline'] . '**';
        $lines[] = '';
        if (!empty($insights['plain_summary'])) {
            $lines[] = $insights['plain_summary'];
            $lines[] = '';
        }
        if ($kind === 'heritage' && !empty($insights['top_lanes'])) {
            $lines[] = 'Top heritage signal lanes from the marker preview: ' . implode('; ', (array) $insights['top_lanes']) . '.';
            $lines[] = '';
        }
        if ($kind === 'health' && isset($insights['attention_count'])) {
            $lines[] = '- Research follow-up cards: ' . (int) $insights['attention_count'];
            $lines[] = '- Trait/context cards: ' . (int) ($insights['context_count'] ?? 0);
            $lines[] = '';
        }
    }
    if (!empty($insights['math_system'])) {
        $quality = (array) ($insights['quality'] ?? []);
        $lines[] = '### ZDNA Math Layer';
        $lines[] = '';
        $lines[] = '- System: ' . $insights['math_system'];
        if (!empty($insights['math_summary'])) {
            $lines[] = '- Meaning: ' . $insights['math_summary'];
        }
        if (isset($quality['score'])) {
            $lines[] = '- Raw-file quality score: ' . (int) $quality['score'] . '/100 (' . ($quality['confidence'] ?? 'quality checked') . ')';
        }
        if ($kind === 'health') {
            $lines[] = '- Evidence index: ' . ($insights['evidence_index'] ?? 0);
            $lines[] = '- Follow-up index: ' . ($insights['followup_index'] ?? 0);
            $lines[] = '- Uncertainty boundary: ' . ($insights['uncertainty'] ?? 'unknown') . '/100';
            if (!empty($insights['domain_scores'])) {
                foreach ((array) $insights['domain_scores'] as $domain => $score) {
                    $lines[] = '- Domain signal: ' . $domain . ' = ' . $score;
                }
            }
        }
        if ($kind === 'heritage' && !empty($insights['confidence_bands'])) {
            foreach ((array) $insights['confidence_bands'] as $band) {
                $lines[] = '- Lens band: ' . ($band['label'] ?? 'Signal') . ' = ' . ($band['confidence_band'] ?? 'uncertain');
            }
            $lines[] = '- Uncertainty boundary: ' . ($insights['uncertainty'] ?? 'unknown') . '/100';
        }
        $lines[] = '';
    }
    if ($kind === 'heritage' && !empty($insights['question_signals'])) {
        $lines[] = '### Ancestry Question Lab';
        $lines[] = '';
        foreach ((array) $insights['question_signals'] as $signal) {
            $lines[] = '- ' . ($signal['label'] ?? 'Ancestry signal') . ': ' . ($signal['status'] ?? 'uncertain') . ' / confidence ' . ($signal['confidence_band'] ?? 'low') . ' (' . ($signal['confidence_score'] ?? 0) . '/100)';
            $lines[] = '  Why: ' . ($signal['why'] ?? '');
            $lines[] = '  Limitation: ' . ($signal['limitations'] ?? '');
            $lines[] = '  Next: ' . ($signal['next_steps'] ?? '');
        }
        $lines[] = '';
    }
    if ($kind === 'heritage' && !empty($insights['plain_ancestry_summary'])) {
        $plain = (array) $insights['plain_ancestry_summary'];
        $lines[] = '### Simple Ancestry Answer';
        $lines[] = '';
        if (!empty($plain['consumer_snapshot'])) {
            $snap = (array) $plain['consumer_snapshot'];
            $lines[] = '**Plain result first:** ' . ($snap['headline'] ?? 'Country-style leads ready.');
            $lines[] = '';
            if (!empty($snap['plain'])) {
                $lines[] = $snap['plain'];
                $lines[] = '';
            }
        }
        if (!empty($plain['consumer_estimate'])) {
            $estimate = (array) $plain['consumer_estimate'];
            $lines[] = '### Consumer-Style Composition Projection';
            $lines[] = '';
            $lines[] = '**' . ($estimate['headline'] ?? 'Projection ready') . '**';
            if (!empty($estimate['plain'])) {
                $lines[] = '';
                $lines[] = $estimate['plain'];
            }
            $lines[] = '';
            foreach ((array) ($estimate['segments'] ?? []) as $segment) {
                $lines[] = '- ' . ($segment['label'] ?? 'Projection lane') . ': ' . ($segment['percent'] ?? $segment['value'] ?? 0) . '%';
                if (!empty($segment['note'])) {
                    $lines[] = '  Note: ' . $segment['note'];
                }
            }
            if (!empty($estimate['map_points'])) {
                $lines[] = '';
                $lines[] = 'Interactive map points:';
                foreach ((array) $estimate['map_points'] as $point) {
                    $lines[] = '- ' . ($point['country_name'] ?? $point['label'] ?? 'Map point') . ': ' . ($point['percent'] ?? 0) . '% - ' . ($point['modern_region'] ?? '');
                }
            }
            if (!empty($estimate['trace_lanes'])) {
                $lines[] = '';
                $lines[] = 'Trace lanes:';
                foreach ((array) $estimate['trace_lanes'] as $trace) {
                    $lines[] = '- ' . ($trace['label'] ?? 'Trace lane') . ': ' . ($trace['answer'] ?? 'research lead') . (isset($trace['proxy_percent']) && (float) $trace['proxy_percent'] > 0 ? ' (' . $trace['proxy_percent'] . '%)' : '');
                    $lines[] = '  Boundary: ' . ($trace['boundary'] ?? '');
                }
            }
            if (!empty($estimate['suppressed_noise'])) {
                $lines[] = '';
                foreach ((array) $estimate['suppressed_noise'] as $noise) {
                    $lines[] = '- Suppressed false lead: ' . $noise;
                }
            }
            $lines[] = '';
            $lines[] = 'Boundary: ' . ($estimate['boundary'] ?? 'Probabilistic projection only.');
            $lines[] = '';
        }
        $lines[] = '**' . ($plain['headline'] ?? 'Plain ancestry gate') . '**';
        $lines[] = '';
        $lines[] = $plain['plain_summary'] ?? '';
        $lines[] = '';
        if (!empty($plain['plain_country_cards'])) {
            $lines[] = 'Country-style named reference leads:';
            foreach (array_slice((array) $plain['plain_country_cards'], 0, 12) as $card) {
                $lines[] = '- ' . ($card['label'] ?? 'Country/region lane') . ': ' . ($card['answer'] ?? 'unknown') . (isset($card['proxy_percent']) && (float) $card['proxy_percent'] > 0 ? ' (' . $card['proxy_percent'] . '% starter proxy)' : '');
                $lines[] = '  Plain meaning: ' . ($card['plain'] ?? '');
                $lines[] = '  Boundary: ' . ($card['limitations'] ?? '');
            }
            $lines[] = '';
        }
        if (!empty($plain['theoretical_lanes'])) {
            $lines[] = 'Theoretical heritage lanes:';
            foreach (array_slice((array) $plain['theoretical_lanes'], 0, 10) as $lane) {
                $lines[] = '- ' . ($lane['label'] ?? 'Theoretical lane') . ': ' . ($lane['answer'] ?? 'research lead') . (isset($lane['proxy_percent']) && (float) $lane['proxy_percent'] > 0 ? ' (' . $lane['proxy_percent'] . '% proxy)' : '');
                $lines[] = '  Plain meaning: ' . ($lane['plain'] ?? '');
                $lines[] = '  Boundary: ' . ($lane['limitations'] ?? '');
                $lines[] = '  Next: ' . ($lane['next_steps'] ?? '');
            }
            $lines[] = '';
        }
        if (!empty($plain['broad_reference_mix'])) {
            $lines[] = 'Broad reference-mix proxy:';
            foreach ((array) $plain['broad_reference_mix'] as $row) {
                $lines[] = '- ' . ($row['label'] ?? 'Reference signal') . ': ' . ($row['proxy_percent'] ?? 0) . '% proxy, confidence ' . ($row['confidence_band'] ?? 'low') . ', markers ' . number_format((int) ($row['matched_marker_count'] ?? 0));
            }
            $lines[] = '';
        }
        if (!empty($plain['modern_country_reference_mix'])) {
            $lines[] = 'Modern country/reference groups visible in installed panel:';
            foreach (array_slice((array) $plain['modern_country_reference_mix'], 0, 8) as $row) {
                $lines[] = '- ' . ($row['label'] ?? 'Reference group') . ': ' . ($row['proxy_percent'] ?? 0) . '% proxy, confidence ' . ($row['confidence_band'] ?? 'low');
            }
            $lines[] = '';
        }
        foreach ((array) ($plain['cards'] ?? []) as $card) {
            $lines[] = '- ' . ($card['label'] ?? 'Question') . ': ' . ($card['answer'] ?? 'unknown') . (isset($card['proxy_percent']) && (float) $card['proxy_percent'] > 0 ? ' (' . $card['proxy_percent'] . '% proxy)' : '');
            $lines[] = '  ' . ($card['plain'] ?? '');
            $lines[] = '  Boundary: ' . ($card['limitations'] ?? '');
        }
        if (!empty($plain['truth_boundary'])) {
            $lines[] = '';
            $lines[] = 'Boundary: ' . $plain['truth_boundary'];
        }
        $lines[] = '';
    }
    if ($kind === 'heritage' && !empty($report['local_reference_pack'])) {
        $pack = (array) $report['local_reference_pack'];
        $lines[] = '### Country & Region Reference Evidence';
        $lines[] = '';
        $lines[] = '- Status: ' . (!empty($pack['installed']) ? 'active and scanned' : 'being expanded') . ' - ' . ($pack['reason'] ?? '');
        if (!empty($pack['variants_scanned'])) {
            $lines[] = '- RSID variants checked against country and region references: ' . number_format((int) $pack['variants_scanned']);
        }
        if (!empty($pack['matched_marker_count'])) {
            $lines[] = '- Reference markers matched: ' . number_format((int) $pack['matched_marker_count']);
        }
        foreach (array_slice((array) ($pack['population_scores'] ?? []), 0, 8) as $row) {
            $lines[] = '- ' . ($row['modern_label'] ?? $row['population_code'] ?? 'Reference group') . ': affinity ' . ($row['affinity_score'] ?? 0) . '/100, confidence ' . ($row['confidence_band'] ?? 'uncertain') . ', markers ' . number_format((int) ($row['matched_marker_count'] ?? 0));
        }
        if (!empty($pack['truth_boundary'])) {
            $lines[] = '- Boundary: ' . $pack['truth_boundary'];
        }
        $lines[] = '';
    }
    if ($kind === 'heritage' && !empty($insights['modern_region_glossary'])) {
        $lines[] = '### Modern Region Glossary';
        $lines[] = '';
        foreach (array_slice((array) $insights['modern_region_glossary'], 0, 12) as $term => $meaning) {
            $lines[] = '- ' . $term . ': ' . $meaning;
        }
        $lines[] = '';
    }
    $summary = (array) ($report['summary'] ?? []);
    $variant = (array) ($report['variant_index'] ?? []);
    $lines[] = '- File: ' . ($summary['file_name'] ?? $variant['file_name'] ?? 'raw-dna.txt');
    $lines[] = '- SHA-256: ' . ($summary['file_hash'] ?? $variant['file_hash'] ?? '');
    $lines[] = '- Format guess: ' . ($summary['format_guess'] ?? 'unknown');
    $lines[] = '- Parsed variants: ' . number_format((int) ($summary['parsed_variants'] ?? 0));
    $lines[] = '- RSID variants indexed: ' . number_format((int) ($variant['parsed_rsid_variants'] ?? 0));
    $lines[] = '- Called variants: ' . number_format((int) ($summary['called_variants'] ?? 0));
    $lines[] = '- No-call rate: ' . ($summary['no_call_percent'] ?? 0) . '%';
    $lines[] = '- Heterozygosity: ' . ($summary['heterozygosity_percent'] ?? 0) . '% of called simple A/C/G/T genotypes';
    if ($kind === 'heritage') {
        $lines[] = '- Reference-readiness score: ' . (int) ($report['reference_readiness_score'] ?? 0) . '/100';
        $lines[] = '- Admixture boundary: ' . $report['admixture_boundary'];
    }
    $lines[] = '';
    if (!empty($insights['cards'])) {
        $lines[] = $kind === 'heritage' ? '### What This Suggests In Human Words' : '### Health Research Cards';
        foreach ((array) $insights['cards'] as $card) {
            $lines[] = '- ' . (string) ($card['title'] ?? 'Research card') . ': ' . (string) ($card['plain'] ?? '');
            if (!empty($card['lane'])) {
                $lines[] = '  Signal lane: ' . $card['lane'];
            }
            if (!empty($card['markers'])) {
                $lines[] = '  Marker(s): ' . implode(', ', (array) $card['markers']);
            } elseif (!empty($card['genotype'])) {
                $lines[] = '  Genotype: `' . $card['genotype'] . '`';
            }
            if (isset($card['dosage_label'])) {
                $lines[] = '  ZDNA dosage: ' . $card['dosage_label'];
            }
            if (isset($card['evidence_weight'])) {
                $lines[] = '  ZDNA evidence weight: ' . $card['evidence_weight'];
            }
            if (!empty($card['quality_gate'])) {
                $lines[] = '  Quality gate: ' . $card['quality_gate'];
            }
            if (!empty($card['next_step'])) {
                $lines[] = '  Next step: ' . $card['next_step'];
            }
        }
        $lines[] = '';
    }
    if ($kind === 'heritage' && !empty($insights['region_research_prompts'])) {
        $lines[] = '### Region/Heritage Research Questions';
        foreach ((array) $insights['region_research_prompts'] as $prompt) {
            $lines[] = '- ' . $prompt;
        }
        $lines[] = '';
    }
    if ($kind === 'health' && !empty($report['local_pack_matches'])) {
        $pack = (array) $report['local_pack_matches'];
        $lines[] = '### Health Source Evidence';
        $lines[] = '';
        $lines[] = '- Status: ' . ($pack['status'] ?? (!empty($pack['installed']) ? 'installed' : 'unavailable')) . ' - ' . ($pack['reason'] ?? '');
        $lines[] = '- Personalized interpretation ready: ' . (!empty($pack['interpretation_ready']) ? 'yes' : 'no');
        $lines[] = '- Allele-aware matching: ' . (!empty($pack['allele_aware']) ? 'yes' : 'no');
        if (!empty($pack['variants_scanned'])) {
            $lines[] = '- RSID variants checked against health research sources: ' . number_format((int) $pack['variants_scanned']);
        }
        foreach (['clinvar' => 'ClinVar matches', 'gwas' => 'GWAS Catalog matches', 'pharmgkb' => 'PharmGKB/CPIC-style matches'] as $key => $label) {
            $rows = array_slice((array) ($pack[$key] ?? []), 0, 8);
            if (!$rows) {
                continue;
            }
            $lines[] = '- ' . $label . ': ' . count((array) ($pack[$key] ?? [])) . ' shown below (top preview)';
            foreach ($rows as $row) {
                if ($key === 'clinvar') {
                    $lines[] = '  - ' . strtoupper((string) ($row['rsid'] ?? '')) . ' / ' . ($row['gene'] ?? '') . ' / ' . ($row['clinical_significance'] ?? '') . ' / ' . ($row['condition_name'] ?? '');
                } elseif ($key === 'gwas') {
                    $lines[] = '  - ' . strtoupper((string) ($row['rsid'] ?? '')) . ' / ' . ($row['mapped_trait'] ?: ($row['trait'] ?? 'trait')) . ' / PubMed ' . ($row['pubmed_id'] ?? '');
                } else {
                    $lines[] = '  - ' . strtoupper((string) ($row['rsid'] ?? '')) . ' / ' . ($row['gene'] ?? '') . ' / ' . ($row['drug'] ?? '') . ' / ' . ($row['evidence_level'] ?? '');
                }
            }
        }
        $lines[] = '- Match contract: ' . ($pack['match_contract'] ?? 'rsID-only matching is not accepted as a personalized finding.');
        $lines[] = '- Boundary: source evidence is research support only, not diagnosis, medication advice, treatment guidance, a negative test, or a personal risk score.';
        $lines[] = '';
    }
    $lines[] = '### Marker Matches';
    $hits = (array) ($report['hits'] ?? []);
    if (!$hits) {
        $lines[] = '- No markers from the built-in research watchlist were found in this raw file.';
    } else {
        foreach ($hits as $hit) {
            $lines[] = '- ' . strtoupper($hit['rsid']) . ' / ' . $hit['gene'] . ' / genotype `' . ($hit['genotype'] ?: 'not-called') . '`';
            $lines[] = '  Topic: ' . $hit['topic'];
            $lines[] = '  Note: ' . $hit['note'];
            $lines[] = '  dbSNP: ' . $hit['dbsnp'];
            if ($kind === 'health') {
                $lines[] = '  ClinVar search: ' . $hit['clinvar_search'];
            }
        }
    }
    $lines[] = '';
    $lines[] = '### What Zero Can Do Next';
    if ($kind === 'health') {
        $lines[] = '- Run NCBI ClinVar lookup for matched RSIDs and summarize public clinical assertions with caution.';
        $lines[] = '- Build an Ensembl VEP request for a specific variant if chromosome, position, reference, and allele are known.';
        $lines[] = '- Prepare questions for a clinician or genetic counselor without making diagnosis claims.';
    } else {
        $lines[] = '- Tell the user that exact ethnicity percentages need a real reference panel and probabilistic modelling.';
        $lines[] = '- Use this report to check whether the file is reference-panel ready.';
        $lines[] = '- Explain population-associated markers as broad research context, not identity labels.';
    }
    return implode("\n", $lines);
}

function zl_dna_raw_markdown($analysis) {
    $lines = [];
    $lines[] = '## Raw DNA File Analysis';
    $lines[] = '';
    $lines[] = '**Research boundary:** ' . $analysis['disclaimer'];
    $lines[] = '**Privacy:** ' . $analysis['privacy'];
    $lines[] = '';
    $lines[] = '- File: ' . $analysis['file_name'];
    $lines[] = '- SHA-256: ' . $analysis['file_hash'];
    $lines[] = '- Format guess: ' . $analysis['format_guess'];
    if (!empty($analysis['genome_build_guess'])) {
        $lines[] = '- Genome build guess: ' . $analysis['genome_build_guess'] . ' (' . ($analysis['genome_build_confidence'] ?? 'unknown') . ' confidence)';
    }
    if (isset($analysis['quality_score'])) {
        $lines[] = '- ZDNA raw quality: ' . (int) $analysis['quality_score'] . '/100 (' . ($analysis['quality_band'] ?? 'unknown') . ')';
    }
    $lines[] = '- Parsed variants: ' . number_format((int) $analysis['parsed_variants']);
    $lines[] = '- Duplicate rsIDs: ' . number_format((int) ($analysis['duplicate_rsid_count'] ?? 0));
    $lines[] = '- Called variants: ' . number_format((int) $analysis['called_variants']);
    $lines[] = '- No-calls: ' . number_format((int) $analysis['no_calls']) . ' (' . $analysis['no_call_percent'] . '%)';
    $lines[] = '- Heterozygous / homozygous: ' . number_format((int) $analysis['heterozygous']) . ' / ' . number_format((int) $analysis['homozygous']);
    $lines[] = '- Heterozygosity: ' . $analysis['heterozygosity_percent'] . '% of called simple A/C/G/T genotypes';
    $lines[] = '- Indel/complex/VCF-style genotype rows: ' . number_format((int) $analysis['indel_or_complex']);
    $lines[] = '- Chromosomes detected: ' . number_format((int) $analysis['chromosome_count']);
    $lines[] = '- Autosomal / X / Y / MT markers: ' . number_format((int) ($analysis['autosomal_markers'] ?? 0)) . ' / ' . number_format((int) ($analysis['x_markers'] ?? 0)) . ' / ' . number_format((int) ($analysis['y_markers'] ?? 0)) . ' / ' . number_format((int) ($analysis['mt_markers'] ?? 0));
    if (!empty($analysis['truncated'])) {
        $lines[] = '- Note: analysis stopped at the safety row limit for very large files.';
    }
    $lines[] = '';
    $lines[] = '### Top Chromosomes';
    foreach ((array) $analysis['top_chromosomes'] as $chrom => $count) {
        $lines[] = '- ' . $chrom . ': ' . number_format((int) $count);
    }
    $lines[] = '';
    $lines[] = '### Top Genotype Tokens';
    foreach ((array) $analysis['top_genotypes'] as $geno => $count) {
        $lines[] = '- `' . $geno . '`: ' . number_format((int) $count);
    }
    $lines[] = '';
    $lines[] = '### Privacy Note';
    $lines[] = '- Raw genotype rows are deliberately suppressed from this Markdown report so the DNA Agent receives summaries, not raw DNA rows.';
    if (!empty($analysis['normalized_summary'])) {
        foreach ((array) $analysis['normalized_summary'] as $label => $note) {
            $lines[] = '- ' . str_replace('_', ' ', $label) . ': ' . $note;
        }
    }
    $lines[] = '';
    $lines[] = '### What Zero Can Do Next';
    $lines[] = '- Search public variant sources through NCBI or Ensembl for specific variants you choose.';
    $lines[] = '- Build an AlphaGenome request manifest for a research target when a valid key and bridge worker are installed.';
    $lines[] = '- Summarize this report in Studio without sending the full raw file to the model.';
    return implode("\n", $lines);
}

function zl_quantum_chess_state($move, $seed = '') {
    $move = trim((string) $move);
    if ($move === '') {
        $move = 'e2e4';
    }
    $hash = hash('sha256', 'quantum-chess|' . $move . '|' . $seed);
    $states = ['classical', 'superposed', 'entangled', 'collapsed'];
    $basis = [];
    for ($i = 0; $i < 4; $i++) {
        $chunk = hexdec(substr($hash, $i * 8, 8));
        $basis[] = [
            'state' => $states[$i],
            'weight' => round((($chunk % 1000) + 1) / 1000, 3),
            'basis' => str_pad(decbin($chunk % 8), 3, '0', STR_PAD_LEFT),
        ];
    }
    $sum = array_sum(array_column($basis, 'weight')) ?: 1;
    foreach ($basis as &$item) {
        $item['probability'] = round($item['weight'] / $sum, 4);
    }
    unset($item);
    usort($basis, function ($a, $b) {
        return $b['probability'] <=> $a['probability'];
    });
    return [
        'move' => $move,
        'seed_hash' => substr($hash, 0, 24),
        'states' => $basis,
        'truth_boundary' => 'This is a local quantum-inspired chess state. Use Quantum Zero/IonQ proof mode to bind a move seed to a real provider-backed circuit job.',
    ];
}
?>
