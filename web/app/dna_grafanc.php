<?php
// Pinned, locally executed published population-structure model. No genotype is sent to a service.
function zlg_boundary() {
    return 'This is a published population-structure model prediction, not an ethnicity percentage, nationality, religion or community-membership test. Neighboring and admixed populations overlap. The model does not guarantee detection of every unrepresented origin. Its regional labels describe broad research groupings; British and Irish references share a Western European grouping, and no Ashkenazi or Khazar category is defined. Consumer-file accuracy has not been independently calibrated.';
}
function zlg_bundle_path() {
    $path = trim((string) getenv('ZEROTHINK_GRAFANC_BUNDLE'));
    return rtrim($path !== '' ? $path : '/mnt/data/zerothink/dna-engines/grafanc-b5e51c78', '/\\');
}
function zlg_bundle() {
    static $cache = [];
    $path = zlg_bundle_path();
    if (isset($cache[$path])) { return $cache[$path]; }
    $manifest = $path . '/manifest.json';
    if (!is_readable($manifest) || hash_file('sha256', $manifest) !== 'c2e64f1d7fb13e7a41c9ebb2ad84a3556d63388ea3d6f4008acbe8bcc4d1f8f8') { throw new RuntimeException('Pinned model manifest unavailable'); }
    $data = json_decode(file_get_contents($manifest), true);
    if (!is_array($data) || ($data['schema_version'] ?? '') !== 'grafanc-bundle-v1' || ($data['revision'] ?? '') !== 'b5e51c78b5004a8388418ce56755bb2f71f24eb5') { throw new RuntimeException('Model version unavailable'); }
    foreach ($data['files'] as $relative => $expected) {
        if (strpos($relative, '..') !== false || strpos($relative, '/') === 0) { throw new RuntimeException('Invalid model path'); }
        $file = $path . '/' . $relative;
        if (!is_readable($file) || filesize($file) !== $expected['bytes'] || hash_file('sha256', $file) !== $expected['sha256']) { throw new RuntimeException('Pinned model source mismatch'); }
    }
    if (!is_executable($path . '/bin/grafanc') || !is_executable('/usr/bin/timeout') || !is_executable('/usr/bin/prlimit') || !function_exists('proc_open') || !class_exists('SQLite3')) { throw new RuntimeException('Bounded local model runtime unavailable'); }
    return $cache[$path] = $data;
}
function zl_dna_grafanc_catalog() {
    $out = ['available' => false, 'status' => 'model_unavailable', 'model' => 'GrafAnc', 'source_url' => 'https://github.com/jimmy-penn/grafanc', 'paper_url' => 'https://doi.org/10.1016/j.xhgg.2025.100530', 'version' => '1.0.0 / b5e51c78b5004a8388418ce56755bb2f71f24eb5', 'minimum_broad_markers' => 10000, 'minimum_regional_markers' => 50000, 'boundary' => zlg_boundary()];
    try { $data = zlg_bundle(); return array_merge($out, ['available' => true, 'status' => 'published_model_installed', 'source_marker_count' => $data['source_marker_count'], 'usable_marker_count' => $data['usable_marker_count'], 'regional_group_count' => count($data['regions']), 'license' => 'MIT; University of Pennsylvania', 'genome_builds' => ['GRCh37', 'GRCh38']]); }
    catch (Throwable $e) { return $out; }
}

// The reducer uses the same row/header/GT and conflict-merge primitives as the canonical parser.
// It stores only model loci, with a separate permanent quarantine for contradictory provenance.
function zlg_collect_model_calls($path, $assembly, $index_path, $max_rows = 1200000) {
    if (!in_array($assembly, ['GRCh37', 'GRCh38'], true) || !is_file($path) || !is_readable($path)) { throw new RuntimeException('Model input unavailable'); }
    $db = new SQLite3($index_path, SQLITE3_OPEN_READONLY);
    $posfield = $assembly === 'GRCh37' ? 'pos37' : 'pos38';
    $result = $db->query('SELECT chrom,pos37,' . $posfield . ' AS input_pos,rsid,ref,alt FROM markers ORDER BY CAST(chrom AS INTEGER),pos37');
    $definitions = []; $rsid_keys = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $key = $row['chrom'] . ':' . $row['input_pos'];
        $definitions[$key] = implode("\t", [$row['rsid'], $row['ref'], $row['alt'], $row['chrom'], $row['pos37']]);
        $rsid_keys[$row['rsid']] = $key;
    }
    $db->close();
    $handle = fopen($path, 'rb'); if (!$handle) { throw new RuntimeException('Model input unreadable'); }
    $calls = []; $blocked = []; $header = []; $vcf_mode = false;
    $diag = ['data_lines_seen' => 0, 'model_loci_observed' => 0, 'quarantined_loci' => 0, 'matched_markers' => 0, 'chromosome_count' => 0, 'input_incomplete' => false];
    try {
        while (($line = fgets($handle, 1048577)) !== false) {
            if (strlen($line) >= 1048576 && substr($line, -1) !== "\n") { $diag['input_incomplete'] = true; break; }
            $line = trim($line); if ($line === '' || strpos($line, '##') === 0) { continue; }
            if (strpos($line, '#') === 0) { if (stripos($line, '#CHROM') === 0) { $header = zl_dna_raw_header_map(zl_dna_raw_split_row(ltrim($line, '#'))); $vcf_mode = true; } continue; }
            $cols = zl_dna_raw_split_row($line); if (count($cols) < 3) { continue; }
            $joined = implode('|', array_map('strtolower', $cols));
            if (preg_match('/\b(rsid|snp|chromosome|position|genotype|result|allele1|allele2)\b/', $joined) && !preg_match('/^rs\d+/i', (string) ($cols[0] ?? ''))) { $header = zl_dna_raw_header_map($cols); continue; }
            if (++$diag['data_lines_seen'] > $max_rows) { $diag['input_incomplete'] = true; break; }
            $is_vcf = $vcf_mode || (count($cols) >= 8 && is_numeric((string) ($cols[1] ?? '')));
            if ($is_vcf) {
                $rsid = strtolower(trim(zl_dna_raw_pick($cols, $header, ['id', 'rsid', 'snp'], 2)));
                $chrom = zl_dna_raw_pick($cols, $header, ['chrom', 'chromosome'], 0); $pos = zl_dna_raw_pick($cols, $header, ['pos', 'position'], 1);
                $ref = strtoupper(zl_dna_raw_pick($cols, $header, ['ref'], 3)); $alt = strtoupper(zl_dna_raw_pick($cols, $header, ['alt'], 4));
                $sample = count($cols) === 10 && in_array(strtoupper(trim((string) ($cols[6] ?? ''))), ['PASS', '.'], true) ? (string) $cols[9] : '';
                $genotype = zl_dna_vcf_sample_genotype($sample, $ref, $alt, (string) ($cols[8] ?? ''));
            } else {
                $rsid = strtolower(trim(zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0)));
                $chrom = zl_dna_raw_pick($cols, $header, ['chromosome', 'chrom', 'chr'], 1); $pos = zl_dna_raw_pick($cols, $header, ['position', 'pos'], 2);
                $genotype = zl_dna_raw_pick($cols, $header, ['result', 'genotype', 'alleles', 'allele'], count($cols)-1);
                $a1 = zl_dna_raw_pick($cols, $header, ['allele1', 'allele_1']); $a2 = zl_dna_raw_pick($cols, $header, ['allele2', 'allele_2']);
                if (array_intersect(['allele1','allele_1','allele2','allele_2'], array_keys($header))) { $genotype = strlen($a1.$a2) <= 12 ? $a1.$a2 : ''; }
                elseif (($a1 !== '' || $a2 !== '') && strlen($a1.$a2) <= 12) { $genotype = $a1.$a2; }
                elseif (!array_intersect(['result','genotype','alleles','allele'], array_keys($header)) && count($cols) >= 5 && strlen((string) $cols[3]) <= 4 && strlen((string) $cols[4]) <= 4) { $genotype = $cols[3].$cols[4]; }
            }
            $chrom = preg_replace('/^CHR/i', '', strtoupper(trim((string) $chrom, " \t\n\r\0\x0B\"'"))); $pos = trim((string) $pos);
            $key = preg_match('/^\d+$/', $pos) && preg_match('/^(?:[1-9]|1[0-9]|2[0-2])$/', $chrom) ? $chrom . ':' . $pos : '';
            // Check a known model ID even when the contradictory coordinate is off-panel.
            if (isset($rsid_keys[$rsid]) && $rsid_keys[$rsid] !== $key) { $blocked[$rsid_keys[$rsid]] = true; if (isset($definitions[$key])) { $blocked[$key] = true; } }
            if (!isset($definitions[$key])) { continue; }
            [$expected_id, $expected_ref, $expected_alt] = explode("\t", $definitions[$key]);
            $anonymous_vcf = $is_vcf && ($rsid === '' || $rsid === '.');
            $direct_pair = ($ref ?? '') === $expected_ref && ($alt ?? '') === $expected_alt;
            $swapped_pair = ($ref ?? '') === $expected_alt && ($alt ?? '') === $expected_ref;
            if ((!$anonymous_vcf && $rsid !== $expected_id) || ($is_vcf && !$direct_pair && !$swapped_pair)) { $blocked[$key] = true; }
            $raw_genotype = trim((string) $genotype);
            $genotype = preg_match('/^[ACGT](?:[\/|]?[ACGT])$/i', $raw_genotype) ? str_replace(['/','|'], '', zl_dna_raw_clean_genotype($raw_genotype)) : '';
            if (strlen($genotype) === 2 && strspn($genotype, $expected_ref.$expected_alt) !== 2) { $blocked[$key] = true; $genotype = ''; }
            zl_dna_merge_genotype_call($calls, $key, $genotype);
        }
    } finally { fclose($handle); }
    $diag['model_loci_observed'] = count($calls); $diag['quarantined_loci'] = count($blocked);
    foreach ($blocked as $key => $_) { unset($calls[$key]); }
    $calls = array_filter($calls, function ($call) { return strlen($call) === 2; });
    $diag['matched_markers'] = count($calls); $chromosomes = [];
    foreach ($calls as $key => $_) { $chromosomes[strtok($key, ':')] = true; }
    $diag['chromosome_count'] = count($chromosomes);
    return ['calls' => $calls, 'definitions' => $definitions, 'diagnostics' => $diag];
}

function zlg_decode_output($text, $expected_markers, $manifest) {
    $lines = preg_split('/\r?\n/', trim($text));
    if (count($lines) !== 2) { throw new RuntimeException('Incomplete model result'); }
    $header = explode("\t", $lines[0]); $values = array_map('trim', explode("\t", $lines[1]));
    $expected = ['Sample','SNPs','GD1','GD2','GD3','EA1','EA2','EA3','EA4','AF1','AF2','AF3','EU1','EU2','EU3','SA1','SA2','IC1','IC2','IC3','Pe','Pf','Pa','RawPe','RawPf','RawPa','AncGroupID'];
    if ($header !== $expected || count($values) !== count($expected)) { throw new RuntimeException('Unexpected model columns'); }
    $row = array_combine($header, $values);
    if ($row['Sample'] !== 'SAMPLE' || !ctype_digit($row['SNPs']) || (int) $row['SNPs'] !== $expected_markers || !isset($manifest['regions'][$row['AncGroupID']])) { throw new RuntimeException('Unexpected model sample or coverage'); }
    foreach (array_slice($expected, 2, -1) as $field) { if (!is_numeric($row[$field]) || !is_finite((float) $row[$field])) { throw new RuntimeException('Nonfinite model result'); } }
    // Never return Pe/Pf/Pa, raw barycentric values, sample names or genotype records.
    return ['group_id' => (int) $row['AncGroupID'], 'matched_markers' => (int) $row['SNPs']];
}

function zl_dna_grafanc_report_path($path, $name, $summary) {
    $catalog = zl_dna_grafanc_catalog();
    $out = array_merge($catalog, ['headline' => 'Published ancestry model', 'plain' => 'The pinned ancestry model is not currently available.', 'broad' => null, 'regional' => null, 'matched_markers' => 0, 'classification_scope' => 'published_population_structure_model', 'confidence_status' => 'not_consumer_calibrated', 'method' => 'GrafAnc computes reference-normalized genetic distances and population contrasts against published allele-frequency references, then applies the author\'s population-structure classifier. No fitted ethnicity percentages are displayed.']);
    $out['installed'] = $catalog['available']; $out['available'] = false;
    if (!$catalog['available']) { return $out; }
    $gate = zlt_file_gate($summary);
    if (empty($gate['ready']) || !in_array($gate['assembly'] ?? '', ['GRCh37','GRCh38'], true)) { $out['status'] = 'needs_file_validation'; $out['plain'] = 'Confirm this file\'s genome build and forward allele orientation before running the published ancestry model.'; return $out; }
    $dir = null; $proc = null; $pipes = []; $old_umask = null;
    try {
        $bundle = zlg_bundle_path(); $manifest = zlg_bundle();
        $input = zlg_collect_model_calls($path, $gate['assembly'], $bundle.'/data/marker_index.sqlite', zl_dna_max_raw_rows());
        $out['diagnostics'] = $input['diagnostics']; $out['matched_markers'] = $input['diagnostics']['matched_markers'];
        if ($input['diagnostics']['input_incomplete'] || !empty($summary['truncated'])) { $out['status'] = 'input_incomplete'; $out['plain'] = 'The file exceeded the supported scan limit. No partial ancestry prediction is presented.'; return $out; }
        if ($out['matched_markers'] < 10000 || $input['diagnostics']['chromosome_count'] < 10) { $out['status'] = 'insufficient_markers'; $out['plain'] = 'This file supplies '.number_format($out['matched_markers']).' directly called model markers. At least 10,000 across multiple autosomes are needed for a broad prediction; 50,000 are needed for a regional model category.'; return $out; }
        $root = zl_storage_root().'/dna-grafanc-tmp'; if (!is_dir($root) && !mkdir($root, 0700, true)) { throw new RuntimeException('Private workspace unavailable'); }
        $dir = $root.'/request-'.bin2hex(random_bytes(16)); if (!mkdir($dir, 0700)) { $dir = null; throw new RuntimeException('Private workspace unavailable'); }
        $old_umask = umask(0077); $bed_path = $dir.'/input.bed'; $output = $dir.'/output.tsv';
        $bed = fopen($bed_path, 'x'); $bim = fopen($dir.'/input.bim', 'x');
        if (!$bed || !$bim) { if ($bed) { fclose($bed); } if ($bim) { fclose($bim); } throw new RuntimeException('Private input unavailable'); }
        try {
            fwrite($bed, "\x6c\x1b\x01");
            if (file_put_contents($dir.'/input.fam', "0 SAMPLE 0 0 0 -9\n", LOCK_EX) === false) { throw new RuntimeException('Private sample header unavailable'); }
            foreach ($input['definitions'] as $key => $definition) {
                if (!isset($input['calls'][$key])) { continue; }
                [$rsid,$ref,$alt,$chrom,$pos37] = explode("\t", $definition); $call = $input['calls'][$key];
                // PLINK A1/A2 are coding alleles, not a claim about genomic FASTA REF.
                // One sample per SNP: 00=A1/A1,10=A1/A2,11=A2/A2,01=missing.
                $dose = substr_count($call, $alt); fwrite($bed, chr([0,2,3][$dose]));
                fwrite($bim, implode("\t", [$chrom,$rsid,'0',$pos37,$ref,$alt])."\n");
            }
        } finally { fclose($bed); fclose($bim); }
        unset($input);
        $cmd = ['/usr/bin/timeout','--signal=TERM','--kill-after=2','25','/usr/bin/prlimit','--as=536870912','--cpu=20','--',$bundle.'/bin/grafanc',$bed_path,$output,'--threads','1','--maxmem','100'];
        $proc = proc_open($cmd, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $dir, ['GRAFPATH'=>$bundle,'PATH'=>'/usr/bin:/bin','LANG'=>'C','LC_ALL'=>'C'], ['bypass_shell'=>true]);
        if (!is_resource($proc)) { throw new RuntimeException('Local ancestry process unavailable'); }
        fclose($pipes[0]); unset($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $start = microtime(true); $output_bytes = 0; $exit_code = null;
        do {
            foreach ($pipes as $pipe) { $output_bytes += strlen((string) stream_get_contents($pipe)); }
            $state = proc_get_status($proc);
            if (!$state['running']) { $exit_code = $state['exitcode']; break; }
            if (microtime(true)-$start > 30 || $output_bytes > 131072) { proc_terminate($proc, 9); throw new RuntimeException('Local model resource limit'); }
            usleep(10000);
        } while (true);
        foreach ($pipes as $pipe) { fclose($pipe); } $pipes = [];
        proc_close($proc); $proc = null;
        // Upstream returns 1 on success and commonly 0 on failure. Output validation is mandatory.
        if ($exit_code !== 1 || !is_file($output) || filesize($output) > 8192) { throw new RuntimeException('Local model did not complete'); }
        $prediction = zlg_decode_output(file_get_contents($output), $out['matched_markers'], $manifest);
        $broad_groups = [100=>['AFR','African'],200=>['MEN','Middle East and North Africa'],300=>['EUR','European'],400=>['SAS','South Asian'],500=>['EAS','East Asian'],600=>['AMR','American'],700=>['OCN','Oceanian'],800=>['MIX','Multiple ancestry backgrounds']];
        $id = $prediction['group_id']; $broad_id = intdiv($id,100)*100;
        if (!isset($broad_groups[$broad_id])) { throw new RuntimeException('Unknown model category'); }
        $out['available'] = true;
        $out['broad'] = ['code'=>$broad_groups[$broad_id][0], 'label'=>$broad_groups[$broad_id][1]];
        $out['status'] = $broad_id === 800 ? 'mixed_model_classification' : 'broad_model_classification';
        if ($out['matched_markers'] >= 50000 && $id % 100 !== 0) { $region = $manifest['regions'][(string)$id]; $out['regional'] = ['code'=>(string)$id, 'label'=>$region['label'], 'reference_scope'=>$region['reference_scope']]; $out['status'] = 'regional_model_classification'; }
        $out['headline'] = ($out['regional']['label'] ?? $out['broad']['label']) . ' — published model prediction';
        $out['plain'] = 'GrafAnc places these observed markers in its '.$out['broad']['label'].' grouping'.($out['regional'] ? ', with the regional model category '.$out['regional']['label'] : '').'. This is an ancestry-pattern prediction, not a breakdown of inherited percentages. '.($out['matched_markers'] < 50000 ? 'There are fewer than 50,000 model markers, so the regional category is withheld. ' : '').'The label is a research grouping and may overlap several countries or communities.';
        return $out;
    } catch (Throwable $e) { $out['available'] = false; $out['status'] = 'model_processing_incomplete'; $out['plain'] = 'The local ancestry model did not complete its source, input or output checks. No partial prediction is presented.'; $out['broad'] = null; $out['regional'] = null; return $out; }
    finally {
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
        if (is_resource($proc)) { proc_terminate($proc,9); proc_close($proc); }
        if ($dir !== null) { foreach (['input.bed','input.bim','input.fam','output.tsv'] as $own_file) { $file = $dir.'/'.$own_file; if (is_file($file) && !is_link($file)) { unlink($file); } } if (is_dir($dir)) { rmdir($dir); } }
        if ($old_umask !== null) { umask($old_umask); }
    }
}
function zl_dna_grafanc_agent_context($report) {
    return ['status'=>(string)($report['status']??'unavailable'), 'headline'=>(string)($report['headline']??''), 'broad'=>$report['broad']??null, 'regional'=>$report['regional']??null, 'matched_markers'=>(int)($report['matched_markers']??0), 'confidence_status'=>'not_consumer_calibrated', 'version'=>(string)($report['version']??''), 'boundary'=>zlg_boundary()];
}
function zl_dna_grafanc_markdown($report) {
    return implode("\n", ['## Published ancestry model', '', '**'.($report['headline']??'Ancestry model unavailable').'**', '', (string)($report['plain']??''), '', 'Observed model markers: '.number_format((int)($report['matched_markers']??0)).'.', '', zlg_boundary(), '', 'Source: GrafAnc '.$report['version'].' — https://doi.org/10.1016/j.xhgg.2025.100530', '', (string)($report['method']??'')]);
}
