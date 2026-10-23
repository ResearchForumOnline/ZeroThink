<?php
// Supplemental public-cohort comparisons. No raw calls or donor identities leave this module.
function zlr_boundary() {
    return 'These are shared-allele comparisons with small published research cohorts, not ethnicity percentages, nationality, religion, community membership or direct ancestry. Scores are comparable only within this panel on the same markers. Internal tests fail to distinguish several named cohorts, including Egyptian and Ashkenazi references, so no closest-origin ranking is provided. Coverage does not establish a particular origin. No Khazar identity is inferred.';
}

function zlr_pack_path($path = null) {
    return $path ?: dirname(__DIR__) . '/data/packs/regional_refs.sqlite';
}

function zlr_reference_groups($db) {
    $meta = zl_dna_sqlite_meta($db);
    if (($meta['schema_version'] ?? '') !== 'regional-refs-v1' || ($meta['genome_build'] ?? '') !== 'GRCh37' || ($meta['strand'] ?? '') !== 'forward') {
        throw new RuntimeException('Regional reference alignment contract missing');
    }
    $result = $db->query('SELECT * FROM regional_reference_groups ORDER BY group_code');
    if (!$result) { throw new RuntimeException('Regional reference groups unavailable'); }
    $groups = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $code = (string) $row['group_code'];
        if (!preg_match('/^[A-Z0-9_]{2,16}$/', $code) || (int) $row['sample_count'] < 5 || (int) $row['marker_count'] <= 0 || isset($groups[$code])) {
            throw new RuntimeException('Regional reference group metadata invalid');
        }
        $groups[$code] = [
            'code' => $code, 'label' => (string) $row['group_label'], 'region' => (string) $row['region'],
            'reference_role' => (string) $row['reference_role'], 'sample_count' => (int) $row['sample_count'],
            'panel_marker_count' => (int) $row['marker_count'], 'source_name' => 'Allen Ancient DNA Resource: current public modern Human Origins cohorts',
            'source_version' => (string) $row['source_version'], 'source_url' => (string) $row['source_url'],
            'source_dois' => (array) json_decode((string) $row['source_dois'], true),
            'limitations' => (string) $row['limitations'],
        ];
    }
    if (count($groups) < 2 || count($groups) !== (int) ($meta['group_count'] ?? 0) || count(array_unique(array_column($groups, 'panel_marker_count'))) !== 1) {
        throw new RuntimeException('Regional reference cohort denominator mismatch');
    }
    return [$groups, $meta];
}

function zl_dna_regional_refs_catalog($pack_path = null) {
    $out = ['installed' => false, 'status' => 'reference_pack_unavailable', 'groups' => [], 'boundary' => zlr_boundary()];
    $path = zlr_pack_path($pack_path);
    if (!is_readable($path) || !class_exists('SQLite3')) { return $out; }
    $db = null;
    try {
        $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
        [$groups, $meta] = zlr_reference_groups($db);
        $db->close(); $db = null;
        return array_merge($out, ['installed' => true, 'status' => 'public_reference_cohorts_installed', 'groups' => array_values($groups), 'group_count' => count($groups), 'sample_count' => (int) $meta['sample_count'], 'panel_marker_count' => (int) $meta['marker_count'], 'source_version' => (string) $meta['source_version'], 'source_url' => (string) $meta['source_url']]);
    } catch (Throwable $e) {
        if ($db) { $db->close(); }
        $out['status'] = 'source_contract_unavailable';
        return $out;
    }
}

function zlr_add_locus($rows, $coordinates, $groups, &$sums, &$diag) {
    if (!$rows) { return; }
    $first = reset($rows);
    $key = (string) $first['chrom'] . ':' . (string) $first['pos'];
    if (!array_key_exists($key, $coordinates)) { return; }
    $diag['observed_target_positions']++;
    if (count($rows) !== count($groups) || array_diff(array_keys($groups), array_keys($rows))) {
        $diag['excluded_loci']['incomplete_group_coverage']++;
        return;
    }
    $aligned = zlo_align_call($coordinates[$key], $first, 'GRCh37');
    if (empty($aligned['ready'])) {
        $reason = (string) $aligned['reason'];
        $diag['excluded_loci'][$reason] = ($diag['excluded_loci'][$reason] ?? 0) + 1;
        return;
    }
    foreach ($rows as $code => $row) {
        if ($row['ref'] !== $first['ref'] || $row['alt'] !== $first['alt'] || $row['build'] !== 'GRCh37' || !is_numeric($row['alt_frequency']) || !is_finite((float) $row['alt_frequency']) || $row['alt_frequency'] < 0 || $row['alt_frequency'] > 1 || (int) $row['observed_samples'] < (int) ceil($groups[$code]['sample_count'] * .9) || (int) $row['observed_samples'] > $groups[$code]['sample_count'] || (int) $row['allele_number'] !== 2 * (int) $row['observed_samples']) {
            $diag['excluded_loci']['invalid_source_frequency_or_alleles']++;
            return;
        }
    }
    $fraction = (int) $aligned['dose'] / 2;
    $diag['common_marker_count']++;
    $diag['heterozygous_calls'] += (int) ($aligned['dose'] === 1);
    $diag['homozygous_calls'] += (int) ($aligned['dose'] !== 1);
    $chrom = (string) $first['chrom'];
    $diag['chromosome_counts'][$chrom] = ($diag['chromosome_counts'][$chrom] ?? 0) + 1;
    $frequencies = [];
    foreach ($rows as $code => $row) {
        $p = (float) $row['alt_frequency'];
        $sums[$code] += $fraction * $p + (1 - $fraction) * (1 - $p);
        $frequencies[] = $p;
    }
    if ($aligned['dose'] !== 1 && max($frequencies) - min($frequencies) > 0.000000001) { $diag['differentiating_loci']++; }
}

function zl_dna_regional_refs_report($coordinates, $summary, $pack_path = null) {
    $catalog = zl_dna_regional_refs_catalog($pack_path);
    $gate = zl_dna_health_interpretation_gate($summary);
    $out = ['installed' => $catalog['installed'], 'status' => $catalog['status'], 'headline' => 'Additional regional research references', 'plain' => 'No aligned supplemental reference pack is installed.', 'groups' => [], 'common_marker_count' => 0, 'chromosome_count' => 0, 'boundary' => zlr_boundary(), 'analysis_gate' => $gate, 'closest' => null, 'comparison_tied' => false, 'method' => 'Mean expected shared allele per observed diploid call, expressed on a 0–100 scale. Every group uses the same callable markers. Heterozygous calls contribute exactly 50/100 to every group; group contrasts arise from homozygous calls and frequency differences. These scores are not ancestry proportions or probabilities.'];
    $out['reference_groups'] = (array) ($catalog['groups'] ?? []);
    $out['ranking_status'] = 'withheld_reference_nonseparability';
    if (!$catalog['installed']) { return $out; }
    $out['source_version'] = $catalog['source_version'];
    $out['source_url'] = $catalog['source_url'];
    if (empty($gate['ready']) || ($gate['assembly'] ?? '') !== 'GRCh37') {
        $out['status'] = 'needs_file_validation';
        $out['plain'] = 'This reference panel needs confirmed GRCh37 coordinates and forward allele orientation before comparing this file.';
        return $out;
    }
    $db = null;
    try {
        $db = new SQLite3(zlr_pack_path($pack_path), SQLITE3_OPEN_READONLY);
        [$groups, $meta] = zlr_reference_groups($db);
        $diag = ['observed_target_positions' => 0, 'common_marker_count' => 0, 'heterozygous_calls' => 0, 'homozygous_calls' => 0, 'differentiating_loci' => 0, 'chromosome_counts' => [], 'excluded_loci' => ['incomplete_group_coverage' => 0, 'invalid_source_frequency_or_alleles' => 0]];
        $sums = array_fill_keys(array_keys($groups), 0.0);
        $result = $db->query('SELECT chrom,pos,build,ref,alt,group_code,alt_frequency,observed_samples,allele_number FROM regional_reference_markers ORDER BY chrom,pos,group_code');
        if (!$result) { throw new RuntimeException('Regional source query unavailable'); }
        $key = null; $locus = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $next = (string) $row['chrom'] . ':' . (string) $row['pos'];
            if ($next !== $key) {
                zlr_add_locus($locus, $coordinates, $groups, $sums, $diag);
                $locus = []; $key = $next;
            }
            if (!isset($groups[$row['group_code']]) || isset($locus[$row['group_code']])) { throw new RuntimeException('Unexpected or duplicate reference group'); }
            $locus[$row['group_code']] = $row;
        }
        zlr_add_locus($locus, $coordinates, $groups, $sums, $diag);
        $db->close(); $db = null;
        $n = $diag['common_marker_count']; $chromosomes = count($diag['chromosome_counts']);
        $out['common_marker_count'] = $n; $out['chromosome_count'] = $chromosomes; $out['diagnostics'] = $diag;
        if ($n === 0) {
            $out['status'] = 'no_callable_reference_overlap'; $out['plain'] = 'No directly called, correctly aligned common markers were found for these research references. This is missing evidence, not zero ancestry.';
            return $out;
        }
        $ranked = [];
        foreach ($groups as $code => $group) {
            $ranked[] = array_merge($group, ['similarity_score' => round(100 * $sums[$code] / $n, 3), 'matched_markers' => $n, 'chromosome_count' => $chromosomes, 'confidence_band' => 'uncalibrated research comparison', 'coverage_band' => $n >= 1000 && $chromosomes >= 10 ? 'substantial common-marker coverage' : 'limited common-marker coverage']);
        }
        // Failed cohort-separability diagnostics prohibit using sharing as an origin classifier.
        // Keep source labels alphabetically ordered instead of implying a winning reference.
        usort($ranked, function ($a, $b) { return strcmp($a['label'], $b['label']); });
        $top = round(max(array_column($ranked, 'similarity_score')), 1); $tied = [];
        foreach ($ranked as $i => &$group) {
            $group['rank'] = null;
            if (round($group['similarity_score'], 1) === $top) { $tied[] = ['code' => $group['code'], 'label' => $group['label']]; }
        }
        unset($group);
        $out['groups'] = $ranked; $out['comparison_tied'] = count($tied) > 1;
        $enough = $n >= 1000 && $chromosomes >= 10 && $diag['differentiating_loci'] >= 100;
        $out['closest'] = null;
        $out['status'] = $enough ? 'descriptive_reference_comparisons' : 'limited_reference_overlap';
        $out['headline'] = count($ranked) . ' research cohorts compared on ' . number_format($n) . ' shared markers';
        $out['plain'] = 'This comparison includes Egyptian, Ashkenazi Jewish and Levantine research cohorts alongside comparison groups from other regions. '
            . 'Internal tests could not reliably distinguish several named cohorts, including Egyptian and Ashkenazi references. No closest origin or membership ranking is reported. '
            . 'The names describe the study participants. Small reference cohorts and shared variation limit how specifically these results can be interpreted.';
        $out['comparison_resolution'] = 0.1;
        $out['validation'] = ['status' => 'reference_nonseparability', 'plain' => 'Leave-one-individual-out tests of both shared alleles and a separate genotype-likelihood model failed to distinguish key named cohorts. These comparisons cannot identify your ethnicity, membership probability or ancestry amount.'];
        return $out;
    } catch (Throwable $e) {
        if ($db) { $db->close(); }
        $out['status'] = 'source_lookup_incomplete'; $out['plain'] = 'The supplemental source lookup did not complete. No partial comparison is presented.';
        return $out;
    }
}

function zl_dna_regional_refs_report_path($path, $name, $summary = null, $pack_path = null) {
    $summary = is_array($summary) ? $summary : zl_dna_apply_analysis_options(zl_dna_raw_analyze_path($path, $name), []);
    $gate = zl_dna_health_interpretation_gate($summary);
    $coordinates = [];
    if (!empty($gate['ready']) && ($gate['assembly'] ?? '') === 'GRCh37') {
        $map = zl_dna_coordinate_genotype_map_path($path, $name, range(1, 22), zl_dna_max_raw_rows());
        $coordinates = (array) ($map['variants'] ?? []);
    }
    return zl_dna_regional_refs_report($coordinates, $summary, $pack_path);
}

function zl_dna_regional_refs_markdown($report) {
    $lines = ['## Additional regional research references', '', (string) ($report['plain'] ?? ''), '', '**Boundary:** ' . zlr_boundary(), ''];
    foreach ((array) ($report['groups'] ?? []) as $row) {
        $lines[] = '- **' . $row['label'] . ':** shared-allele affinity ' . number_format((float) $row['similarity_score'], 1) . '/100; ' . number_format((int) $row['matched_markers']) . ' common markers across ' . (int) $row['chromosome_count'] . ' autosomes; ' . (int) $row['sample_count'] . ' reference individuals.';
    }
    $lines[] = ''; $lines[] = (string) ($report['method'] ?? '');
    if (!empty($report['source_version'])) { $lines[] = ''; $lines[] = 'Source: ' . $report['source_version'] . ' — ' . ($report['source_url'] ?? ''); }
    $dois = [];
    foreach ((array) ($report['reference_groups'] ?? []) as $group) {
        foreach ((array) ($group['source_dois'] ?? []) as $doi) { if (is_string($doi) && $doi !== '') { $dois[$doi] = true; } }
    }
    if ($dois) { $lines[] = ''; $lines[] = 'Original cohort publications: ' . implode('; ', array_keys($dois)); }
    return implode("\n", $lines);
}

function zl_dna_regional_refs_agent_context($report) {
    $out = ['status' => (string) ($report['status'] ?? 'unavailable'), 'common_marker_count' => (int) ($report['common_marker_count'] ?? 0), 'chromosome_count' => (int) ($report['chromosome_count'] ?? 0), 'groups' => [], 'boundary' => zlr_boundary(), 'method' => (string) ($report['method'] ?? '')];
    $out['closest'] = null;
    $out['comparison_tied'] = (bool) ($report['comparison_tied'] ?? false);
    $out['ranking_status'] = 'withheld_reference_nonseparability';
    foreach ((array) ($report['groups'] ?? []) as $row) {
        $out['groups'][] = ['label' => (string) $row['label'], 'similarity_score' => (float) $row['similarity_score'], 'sample_count' => (int) $row['sample_count'], 'matched_markers' => (int) $row['matched_markers'], 'source_version' => (string) $row['source_version']];
    }
    return $out;
}
