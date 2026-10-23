<?php
/** Offline DNA report without accounts, MySQL, model calls, or upload storage. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 3 || in_array($argv[1], ['--help','-h'], true)) {
    fwrite(STDERR, "Usage: php scripts/dna-report.php INPUT_RAW_DNA OUTPUT_NEW.md\nThe input stays on this computer. No raw rows are saved to the report.\n");
    exit($argc === 2 ? 0 : 2);
}
$input = realpath($argv[1]);
if (!$input || !is_file($input) || !is_readable($input) || filesize($input) > 30 * 1024 * 1024) {
    fwrite(STDERR, "Input must be a readable uncompressed genotype file up to 30 MiB.\n"); exit(2);
}
define('ZEROTHINK_LOCAL_LAB', true);
require_once dirname(__DIR__) . '/app/zero_library.php';
if (!zl_text_looks_like_genotype_data(zl_file_head_bytes($input, 65536))) {
    fwrite(STDERR, "Input is not a supported raw genotype table or VCF.\n"); exit(2);
}
if (file_exists($argv[2])) { fwrite(STDERR, "Output already exists; choose a new file.\n"); exit(2); }
try {
    $report = zl_dna_full_report_path($input, 'local-dna-input.txt');
    $markdown = zl_dna_public_report_text(zl_dna_full_markdown($report));
    $out = @fopen($argv[2], 'x');
    if ($out === false) { throw new RuntimeException('Output could not be created.'); }
    try {
        @chmod($argv[2], 0600);
        $offset = 0;
        while ($offset < strlen($markdown)) {
            $written = fwrite($out, substr($markdown, $offset));
            if (!$written) { throw new RuntimeException('Output write failed.'); }
            $offset += $written;
        }
    } finally { fclose($out); }
    fwrite(STDOUT, "DNA research report written. Missing reference packs are reported as unavailable.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "DNA report could not be completed; check local runtime, input and output permissions.\n"); exit(1);
}
