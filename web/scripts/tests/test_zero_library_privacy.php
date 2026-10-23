<?php
require_once dirname(__DIR__, 2) . '/app/zero_library.php';

$failures = [];
$assertions = 0;

function zlp_assert($condition, $message) {
    global $failures, $assertions;
    $assertions++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function zlp_remove_tree($path) {
    if (!is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($path);
}

$base = sys_get_temp_dir() . '/zerothink-library-privacy-' . bin2hex(random_bytes(6));
putenv('ZEROTHINK_PRIVATE_STORAGE_ROOT=' . $base);
$user_id = 4242;
$user_dir = zl_user_dir($user_id);

file_put_contents($user_dir . '/notes.txt', "Private reference notes about callback verification and release checks.\n");
file_put_contents($user_dir . '/genotypes.txt', "rsid\tchromosome\tposition\tgenotype\nrs123\t1\t12345\tAG\n");
file_put_contents($user_dir . '/sequence.txt', '>private-sequence' . "\n" . str_repeat('ACGT', 48) . "\n");
file_put_contents($user_dir . '/service.env', "SERVICE_API_KEY=super-secret-value-123456\n");

zlp_assert(zl_text_looks_like_genomic_sequence(str_repeat('ACGT', 40)), 'Plain nucleotide sequence should be sensitive.');
zlp_assert(zl_text_looks_like_genomic_sequence(">sample\n" . str_repeat('ACGT', 20) . "\n"), 'FASTA content in a generic text file should be sensitive.');
zlp_assert(!zl_text_looks_like_genomic_sequence('Normal research prose about genes and variants.'), 'Normal prose must remain searchable.');
zlp_assert(zl_text_looks_like_secret_config("SERVICE_API_KEY=super-secret-value-123456\n"), 'A populated credential config must be vault-only.');
zlp_assert(!zl_text_looks_like_secret_config("SERVICE_API_KEY=replace_me\n"), 'A placeholder config may remain searchable documentation.');
zlp_assert(in_array('ped', zl_sensitive_genotype_exts(), true), 'PED extension must stay in the vault-only extension set.');
$ped_tmp = $base . '/opaque-upload-tmp';
file_put_contents($ped_tmp, "family sample placeholder\n");
$ped_stored = zl_store_upload($user_id, 'family-sample.ped', $ped_tmp);
zlp_assert(($ped_stored['classification'] ?? '') === 'sensitive_genotype', 'Original PED filename must force vault classification even when the temp path has no extension.');
zlp_assert(($ped_stored['indexable'] ?? true) === false, 'PED upload must be non-indexable at initial storage time.');
zlp_assert(strpos((string) ($ped_stored['path'] ?? ''), 'sensitive/') === 0, 'PED upload must be stored in the sensitive directory.');

$index = zl_build_index($user_id, true);
zlp_assert(($index['index_version'] ?? 0) === 3, 'Privacy-aware index version should be 3.');
zlp_assert(($index['catalogued_count'] ?? -1) === 5, 'All five files should be catalogued.');
zlp_assert(($index['searchable_count'] ?? -1) === 1, 'Only ordinary reference text should be searchable.');
zlp_assert(($index['vault_only_count'] ?? -1) === 4, 'Genotype, sequence, PED, and credential files should be vault-only.');

$hits = zl_search($user_id, 'callback verification', 8);
zlp_assert(count($hits) === 1 && ($hits[0]['path'] ?? '') === 'notes.txt', 'Ordinary notes should remain searchable.');
zlp_assert(zl_search($user_id, 'rs123', 8) === [], 'Genotype rows must not be searchable.');
zlp_assert(zl_search($user_id, 'super secret value', 8) === [], 'Credential values must not be searchable.');
zlp_assert(strpos(zl_context_pack($user_id, 'callback verification'), 'notes.txt') !== false, 'Ordinary notes may enter an evidence pack.');
zlp_assert(zl_context_pack($user_id, 'rs123') === '', 'Genotype rows must not enter an evidence pack.');
zlp_assert(zl_context_pack($user_id, 'super secret value') === '', 'Credential values must not enter an evidence pack.');

zlp_remove_tree($base);

if ($failures) {
    fwrite(STDERR, "FAILED " . count($failures) . "/" . $assertions . " assertions\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: " . $assertions . " Zero Library privacy assertions\n";
