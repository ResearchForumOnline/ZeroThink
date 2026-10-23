<?php
declare(strict_types=1);

// Synthetic regression coverage for the DNA safety boundary. This test copies the
// library into an isolated temporary tree so its health-pack fixture can never
// replace or mutate an installed pack.

$projectRoot = dirname(__DIR__, 2);
$sourceLibrary = $projectRoot . '/app/zero_library.php';
$sandbox = sys_get_temp_dir() . '/zerothink-dna-regression-' . bin2hex(random_bytes(6));
$sandboxApp = $sandbox . '/app';
$sandboxPacks = $sandbox . '/data/packs';
$failures = [];
$assertions = 0;

function zt_test_remove_tree(string $path): void {
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($child) && !is_link($child)) {
            zt_test_remove_tree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}

function zt_assert_true(bool $condition, string $message): void {
    global $failures, $assertions;
    $assertions++;
    if (!$condition) {
        $failures[] = $message;
    }
}

function zt_assert_same($expected, $actual, string $message): void {
    global $failures, $assertions;
    $assertions++;
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

function zt_ready_summary(): array {
    return [
        'genome_build_guess' => 'GRCh38 / hg38 confirmed by user',
        'genome_build_confidence' => 'high',
        'reported_orientation' => 'reference_forward_confirmed_by_user',
        'orientation_confidence' => 'high',
        'quality_score' => 92,
        'called_variants' => 5,
        'parsed_variants' => 7,
        'truncated' => false,
    ];
}

function zt_create_old_health_pack(string $path): void {
    $db = new SQLite3($path);
    $db->exec('CREATE TABLE clinvar_variants (rsid TEXT)');
    $db->exec("INSERT INTO clinvar_variants (rsid) VALUES ('rs1001')");
    $db->close();
}

function zt_create_exact_health_pack(string $path): void {
    $db = new SQLite3($path);
    $db->exec(
        'CREATE TABLE clinvar_variants (' .
        'variation_id INTEGER, allele_id INTEGER, rsid TEXT, assembly TEXT, chrom TEXT, position_vcf INTEGER, ' .
        'reference_allele TEXT, alternate_allele TEXT, is_simple_snv INTEGER, gene_symbol TEXT, ' .
        'clinical_significance TEXT, condition_name TEXT, review_status TEXT, accession TEXT, ' .
        'variant_type TEXT, source_version TEXT, source_release TEXT, allele_matchable INTEGER)'
    );
    $insert = $db->prepare(
        'INSERT INTO clinvar_variants VALUES ' .
        '(:variation_id,:allele_id,:rsid,:assembly,:chrom,:position_vcf,:reference_allele,:alternate_allele,' .
        ':is_simple_snv,:gene_symbol,:clinical_significance,:condition_name,:review_status,:accession,' .
        ':variant_type,:source_version,:source_release,1)'
    );
    $rows = [
        [1001, 2001, 'rs1001', 100, 'G', 'A'], // exact ALT observation
        [1002, 2002, 'rs1002', 101, 'C', 'T'], // no-call input
        [1003, 2003, 'rs1003', 102, 'G', 'A'], // homozygous reference input
        [1004, 2004, 'rs1004', 104, 'G', 'A'], // input position is 999
        [1005, 2005, 'rs1005', 105, 'G', 'A'], // input VCF REF is C, not G
        [1008, 2008, 'rs1008', 108, 'G', 'A'], // forward consumer fixture
    ];
    foreach ($rows as [$variationId, $alleleId, $rsid, $position, $ref, $alt]) {
        $values = [
            ':variation_id' => $variationId,
            ':allele_id' => $alleleId,
            ':rsid' => $rsid,
            ':assembly' => 'GRCh38',
            ':chrom' => '1',
            ':position_vcf' => $position,
            ':reference_allele' => $ref,
            ':alternate_allele' => $alt,
            ':is_simple_snv' => 1,
            ':gene_symbol' => 'SYNTH' . $variationId,
            ':clinical_significance' => 'research fixture',
            ':condition_name' => 'synthetic non-human fixture',
            ':review_status' => 'test only',
            ':accession' => 'SYNTH.' . $variationId,
            ':variant_type' => 'single nucleotide variant',
            ':source_version' => 'synthetic',
            ':source_release' => 'test',
        ];
        foreach ($values as $key => $value) {
            $insert->bindValue($key, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        $insert->execute();
        $insert->reset();
    }
    $db->exec('CREATE INDEX clinvar_rsid_lookup ON clinvar_variants(rsid)');
    $db->exec(
        'CREATE VIEW clinvar_exact_alleles AS SELECT ' .
        'variation_id,allele_id,rsid,assembly,chrom,position_vcf,reference_allele,alternate_allele,' .
        'is_simple_snv,gene_symbol,clinical_significance,condition_name,review_status,accession,' .
        'variant_type,source_version,source_release FROM clinvar_variants WHERE allele_matchable=1'
    );
    $db->close();
}

try {
    if (!class_exists('SQLite3')) {
        throw new RuntimeException('The PHP SQLite3 extension is required.');
    }
    if (!is_file($sourceLibrary)) {
        throw new RuntimeException('Staged app/zero_library.php was not found.');
    }
    mkdir($sandboxApp, 0700, true);
    mkdir($sandboxPacks, 0700, true);
    copy($sourceLibrary, $sandboxApp . '/zero_library.php');
    foreach (glob($projectRoot . '/app/dna_*.php') as $dependency) {
        copy($dependency, $sandboxApp . '/' . basename($dependency));
    }
    require $sandboxApp . '/zero_library.php';

    // Upload sniffing must accept real quoted provider CSVs without accepting
    // binary or executable content that merely contains genotype-like text.
    $quotedCsv = "\xEF\xBB\xBF\"RSID\",\"CHROMOSOME\",\"POSITION\",\"RESULT\"\r\n"
        . "\"rs9001\",\"1\",\"9001\",\"AG\"\r\n";
    zt_assert_same(true, zl_text_looks_like_genotype_data($quotedCsv), 'UTF-8 BOM plus fully quoted MyHeritage-style CSV should pass genotype sniffing');
    zt_assert_same(true, zl_text_looks_like_genotype_data("\"snp\",\"chrom\",\"pos\",\"allele1\",\"allele2\"\n\"rs9002\",\"X\",\"9002\",\"A\",\"G\"\n"), 'Quoted allele-column CSV header should pass genotype sniffing');
    zt_assert_same(true, zl_text_looks_like_genotype_data("\"rs9003\",\"chr2\",\"9003\",\"CT\"\n"), 'Quoted standard genotype data row should pass genotype sniffing');
    zt_assert_same(false, zl_text_looks_like_genotype_data("\xEF\xBB\xBFPK\x03\x04\"RSID\",\"CHROMOSOME\",\"POSITION\",\"RESULT\""), 'BOM-prefixed ZIP signature must remain rejected');
    zt_assert_same(false, zl_text_looks_like_genotype_data("<?php echo 'x'; ?>\n\"RSID\",\"CHROMOSOME\",\"POSITION\",\"RESULT\"\n"), 'Executable markup must remain rejected even with a genotype header');
    zt_assert_same(false, zl_text_looks_like_genotype_data("\"RSID\",\"CHROMOSOME\",\0\"POSITION\",\"RESULT\"\n"), 'NUL-bearing text must remain rejected');
    zt_assert_same(false, zl_text_looks_like_genotype_data("\"name\",\"city\",\"year\",\"value\"\n\"sample\",\"London\",\"2026\",\"AG\"\n"), 'Unrelated quoted CSV must not pass genotype sniffing');
    $quotedMap = zl_dna_raw_variant_map($quotedCsv, 'synthetic-myheritage.csv');
    zt_assert_same('AG', $quotedMap['variants']['rs9001']['genotype'] ?? null, 'Quoted provider CSV should parse after passing the upload sniff');

    // FORMAT-aware VCF parsing, supported ploidy, and complete-call fail-closed rules.
    zt_assert_same('AG', zl_dna_vcf_sample_genotype('12:1/0:99', 'G', 'A', 'DP:GT:GQ'), 'GT must be read from its FORMAT position');
    zt_assert_same('A', zl_dna_vcf_sample_genotype('1:12', 'G', 'A', 'GT:DP'), 'Supported haploid GT should parse');
    zt_assert_same('', zl_dna_vcf_sample_genotype('12:./1:0', 'G', 'A', 'DP:GT:GQ'), 'Partial ./1 call must be withheld');
    zt_assert_same('', zl_dna_vcf_sample_genotype('12:1/.:0', 'G', 'A', 'DP:GT:GQ'), 'Partial 1/. call must be withheld');
    zt_assert_same('', zl_dna_vcf_sample_genotype('12:X/1:0', 'G', 'A', 'DP:GT:GQ'), 'Non-numeric GT token must be withheld');
    zt_assert_same('', zl_dna_vcf_sample_genotype('12:2/2:0', 'G', 'A', 'DP:GT:GQ'), 'Out-of-range ALT index must be withheld');
    zt_assert_same('', zl_dna_vcf_sample_genotype('12:0/1/1:0', 'G', 'A', 'DP:GT:GQ'), 'Unsupported polyploid GT must be withheld');
    zt_assert_same('', zl_dna_vcf_sample_genotype('12:0/1:0', 'G', 'A', 'DP:GQ:AD'), 'FORMAT without GT must be withheld');

    $vcf = implode("\n", [
        '##fileformat=VCFv4.2',
        '##reference=GRCh38',
        "#CHROM\tPOS\tID\tREF\tALT\tQUAL\tFILTER\tINFO\tFORMAT\tSAMPLE",
        "1\t100\trs1001\tG\tA\t.\tPASS\t.\tDP:GT:GQ\t12:0/1:99",
        "1\t101\trs1002\tC\tT\t.\tPASS\t.\tDP:GT\t0:./.",
        "1\t102\trs1003\tG\tA\t.\tPASS\t.\tGT:DP\t0/0:12",
        "1\t999\trs1004\tG\tA\t.\tPASS\t.\tGT:DP\t0/1:12",
        "1\t105\trs1005\tC\tA\t.\tPASS\t.\tGT:DP\t1/1:12",
        "1\t106\trs1006\tG\tA\t.\tPASS\t.\tGT:DP\t2/2:12",
        "1\t107\trs1007\tG\tA\t.\tPASS\t.\tGT:DP\t./1:12",
        '',
    ]);
    $vcfPath = $sandbox . '/synthetic.vcf';
    file_put_contents($vcfPath, $vcf);

    $map = zl_dna_raw_variant_map_path($vcfPath, 'synthetic.vcf');
    zt_assert_same('GA', $map['variants']['rs1001']['genotype'] ?? null, 'VCF map must retain the GT-not-first direct call');
    zt_assert_same('', $map['variants']['rs1002']['genotype'] ?? null, 'VCF map must retain no-call as uncallable');
    zt_assert_same('', $map['variants']['rs1007']['genotype'] ?? null, 'VCF map must not collapse partial no-call into a haploid call');
    zt_assert_same('vcf', $map['variants']['rs1001']['source_record_format'] ?? null, 'VCF map must retain record provenance');
    zt_assert_same('G', $map['variants']['rs1001']['reference_allele'] ?? null, 'VCF map must retain input REF');
    zt_assert_same('A', $map['variants']['rs1001']['alternate_alleles'] ?? null, 'VCF map must retain input ALT');
    zt_assert_same('DP:GT:GQ', $map['variants']['rs1001']['vcf_format'] ?? null, 'VCF map must retain FORMAT provenance');
    zt_assert_same('0/1', $map['variants']['rs1001']['vcf_gt'] ?? null, 'VCF map must retain GT provenance');

    $panel = [
        'rs1001' => ['gene' => 'SYNTH', 'topic' => 'synthetic called marker', 'note' => 'fixture'],
        'rs1002' => ['gene' => 'SYNTH', 'topic' => 'synthetic no-call marker', 'note' => 'fixture'],
        'rs1007' => ['gene' => 'SYNTH', 'topic' => 'synthetic partial marker', 'note' => 'fixture'],
    ];
    $hits = zl_dna_marker_hits($map, $panel);
    zt_assert_same(['rs1001'], array_column($hits, 'rsid'), 'No-call and partial-call markers must not become hits');

    $blockedSummary = [
        'genome_build_guess' => 'unknown',
        'genome_build_confidence' => 'low',
        'orientation_confidence' => 'low',
        'quality_score' => 90,
        'called_variants' => 5,
        'truncated' => false,
    ];
    $blockedGate = zl_dna_health_interpretation_gate($blockedSummary);
    zt_assert_same(false, $blockedGate['ready'], 'Unconfirmed build/orientation must block health interpretation');
    zt_assert_true(count($blockedGate['reasons']) >= 2, 'Blocked gate should explain build and orientation failures');
    $blockedInsights = zl_dna_health_insights($hits, $blockedSummary, $map, []);
    zt_assert_same([], $blockedInsights['cards'], 'Blocked health gate must return no personalized cards');
    zt_assert_same(0, $blockedInsights['attention_count'], 'Blocked health gate must return no attention findings');

    $readyGate = zl_dna_health_interpretation_gate(zt_ready_summary());
    zt_assert_same(true, $readyGate['ready'], 'Confirmed build/orientation with sufficient QC should open the research gate');
    zt_assert_same('GRCh38', $readyGate['assembly'], 'Gate should normalize the confirmed assembly');

    $rawGateFixture = [
        'format_guess' => 'Generic raw genotype table',
        'genome_build_guess' => 'unknown',
        'genome_build_confidence' => 'low',
        'quality_score' => 92,
        'called_variants' => 5,
        'parsed_variants' => 5,
        'truncated' => false,
    ];
    $autoSummary = zl_dna_apply_analysis_options($rawGateFixture, [
        'declared_build' => 'auto',
        'reported_orientation' => 'auto',
    ]);
    zt_assert_same(false, zl_dna_health_interpretation_gate($autoSummary)['ready'], 'Auto/unknown consumer build and orientation must remain withheld');
    $confirmedSummary = zl_dna_apply_analysis_options($rawGateFixture, [
        'declared_build' => 'grch38',
        'reported_orientation' => 'forward',
    ]);
    zt_assert_same('user_confirmed', $confirmedSummary['genome_build_source'] ?? null, 'Explicit build confirmation should retain its provenance');
    zt_assert_same('reference_forward_confirmed_by_user', $confirmedSummary['reported_orientation'] ?? null, 'Explicit orientation confirmation should retain its provenance');
    zt_assert_same(true, zl_dna_health_interpretation_gate($confirmedSummary)['ready'], 'Explicit build/orientation confirmation should satisfy that portion of the gate');

    $packPath = $sandboxPacks . '/health_core.sqlite';
    zt_create_old_health_pack($packPath);
    $oldPackResult = zl_dna_local_health_pack_matches_path($vcfPath, 'synthetic.vcf', 80, zt_ready_summary());
    zt_assert_same('blocked_for_allele_validation', $oldPackResult['status'], 'Legacy rsID/locus-only health pack must fail closed');
    zt_assert_same(false, $oldPackResult['interpretation_ready'], 'Legacy health pack cannot be interpretation-ready');
    zt_assert_same([], $oldPackResult['clinvar'], 'Legacy health pack must emit no personalized ClinVar rows');
    zt_assert_same([], $oldPackResult['gwas'], 'Legacy health pack must emit no personalized GWAS rows');
    zt_assert_same([], $oldPackResult['pharmgkb'], 'Legacy health pack must emit no personalized PharmGKB rows');

    unlink($packPath);
    zt_create_exact_health_pack($packPath);
    $exactResult = zl_dna_local_health_pack_matches_path($vcfPath, 'synthetic.vcf', 80, zt_ready_summary());
    zt_assert_same('allele_specific_scan_complete', $exactResult['status'], 'Allele-aware pack should complete the gated scan');
    zt_assert_same(['rs1001'], array_column($exactResult['clinvar'], 'rsid'), 'Only exact ALT observation may match; no-call, ref-only, position and input REF mismatches stay excluded');
    $exactRow = $exactResult['clinvar'][0] ?? [];
    zt_assert_same(1, $exactRow['observed_alt_copies'] ?? null, 'Exact heterozygous VCF call should report one ALT copy');
    zt_assert_same(true, $exactRow['input_ref_alt_verified'] ?? null, 'VCF exact match must verify input REF/ALT');
    zt_assert_same('vcf_ref_alt_and_direct_gt_confirmed', $exactRow['match_provenance'] ?? null, 'VCF exact match should expose its provenance');

    $consumerPath = $sandbox . '/synthetic-consumer.txt';
    file_put_contents($consumerPath, "rsid\tchromosome\tposition\tgenotype\nrs1008\t1\t108\tGA\n");
    $consumerResult = zl_dna_local_health_pack_matches_path($consumerPath, 'synthetic-consumer.txt', 80, zt_ready_summary());
    zt_assert_same(['rs1008'], array_column($consumerResult['clinvar'], 'rsid'), 'Confirmed-forward consumer direct ALT path should remain available');
    $consumerRow = $consumerResult['clinvar'][0] ?? [];
    zt_assert_same(false, $consumerRow['input_ref_alt_verified'] ?? null, 'Consumer file must not claim explicit REF/ALT verification');
    zt_assert_same('confirmed_forward_consumer_locus_and_direct_allele', $consumerRow['match_provenance'] ?? null, 'Consumer match should expose its weaker provenance');
} catch (Throwable $e) {
    $failures[] = 'Unhandled test error: ' . $e->getMessage();
} finally {
    zt_test_remove_tree($sandbox);
}

if ($failures) {
    fwrite(STDERR, "FAIL: " . count($failures) . " of $assertions assertions failed\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - $failure\n");
    }
    exit(1);
}

fwrite(STDOUT, "OK: $assertions synthetic DNA safety assertions passed\n");
