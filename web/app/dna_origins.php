<?php
// Origins evidence: direct local calls, independent reference comparisons, no raw rows in output.
require_once __DIR__ . '/dna_ancestry_story.php';
require_once __DIR__ . '/dna_regional_refs.php';
require_once __DIR__ . '/dna_grafanc.php';

function zlo_region_metadata() {
    return [
        'EUR' => ['label' => 'Europe-related references', 'lat' => 51.0, 'lon' => 14.0],
        'SAS' => ['label' => 'South Asia-related references', 'lat' => 23.0, 'lon' => 78.0],
        'EAS' => ['label' => 'East Asia-related references', 'lat' => 34.0, 'lon' => 110.0],
        'AFR' => ['label' => 'African and African-diaspora references', 'lat' => 7.0, 'lon' => 15.0],
        'AMR' => ['label' => 'Admixed American study references', 'lat' => 4.0, 'lon' => -76.0],
    ];
}

function zlo_population_locations() {
    // Coarse centres of the public study sampling regions, never the user's location or migration.
    return [
        'ACB' => [13,-59,'Barbados study reference'], 'ASW' => [33,-112,'Southwest USA study reference'],
        'BEB' => [24,90,'Bangladesh study reference'], 'CDX' => [22,101,'Xishuangbanna study reference'],
        'CEU' => [40,-112,'Utah study participants with northern/western European ancestry'],
        'CHB' => [40,116,'Beijing study reference'], 'CHS' => [25,113,'Southern Han Chinese study reference'],
        'CLM' => [6,-76,'Medellin study reference'], 'ESN' => [7,6,'Nigeria study reference'],
        'FIN' => [64,26,'Finland study reference'], 'GBR' => [54,-2,'England and Scotland study reference'],
        'GIH' => [30,-95,'Houston Gujarati Indian study reference'], 'GWD' => [13,-16,'The Gambia study reference'],
        'IBS' => [40,-4,'Spain study reference'], 'ITU' => [53,-2,'UK Telugu study reference'],
        'JPT' => [36,140,'Tokyo study reference'], 'KHV' => [11,107,'Ho Chi Minh City study reference'],
        'LWK' => [1,35,'Webuye study reference'], 'MSL' => [8,-12,'Sierra Leone study reference'],
        'MXL' => [34,-118,'Los Angeles Mexican-ancestry study reference'], 'PEL' => [-12,-77,'Lima study reference'],
        'PJL' => [32,74,'Lahore study reference'], 'PUR' => [18,-66,'Puerto Rico study reference'],
        'STU' => [53,-2,'UK Sri Lankan Tamil study reference'], 'TSI' => [44,11,'Tuscany study reference'],
        'YRI' => [7,4,'Ibadan study reference'],
    ];
}

function zlo_location($lat, $lon, $label) {
    return ['lat' => (float) $lat, 'lon' => (float) $lon, 'label' => (string) $label, 'precision' => 'reference_region', 'boundary' => 'Approximate public reference sampling region; not personal origin, residence or migration.'];
}

function zlo_comparison_leads($rows, $score_key) {
    // Display precision is not statistical certainty: equal displayed scores have no unique lead.
    $measured = array_values(array_filter((array) $rows, function ($row) use ($score_key) { return isset($row[$score_key]) && is_numeric($row[$score_key]); }));
    if (!$measured) { return ['closest' => null, 'tied_top' => [], 'nearby_count' => 0, 'comparison_tied' => false]; }
    usort($measured, function ($a, $b) use ($score_key) { return $b[$score_key] <=> $a[$score_key]; });
    $top = $measured[0];
    $tied = array_values(array_filter($measured, function ($row) use ($top, $score_key) { return round((float) $row[$score_key], 1) === round((float) $top[$score_key], 1); }));
    $nearby = array_filter($measured, function ($row) use ($top, $score_key) { return round((float) $top[$score_key] - (float) $row[$score_key], 4) <= 0.5; });
    return ['closest' => count($tied) === 1 ? $top : null, 'tied_top' => $tied, 'nearby_count' => count($nearby), 'comparison_tied' => count($tied) > 1];
}

function zlo_modern_origins($heritage, $summary = []) {
    $pack = (array) ($heritage['local_reference_pack'] ?? []);
    $file_gate = zl_dna_health_interpretation_gate($summary);
    $boundary = 'These are genetic affinities to public study references, not ancestry proportions or a determination of ethnicity, nationality or identity. Broad regional leads are more defensible than fine study-group labels; nearby populations overlap.';
    $out = ['status' => 'needs_file_validation', 'headline' => 'Modern origins comparison needs file validation', 'plain' => (string) ($pack['reason'] ?? 'Confirm the genome build and forward orientation to compare this file.'), 'broad_regions' => [], 'study_populations' => [], 'closest_region' => null, 'closest_population' => null, 'boundary' => $boundary, 'calibration' => ['status' => 'reference_diagnostics_only; not an ancestry probability']];
    $out['analysis_gate'] = $file_gate;
    if (empty($pack['interpretation_ready']) || empty($file_gate['ready'])) { return $out; }
    $locations = zlo_population_locations();
    $regions = zlo_region_metadata();
    $rows = [];
    foreach ((array) ($pack['population_scores'] ?? []) as $row) {
        if (($row['panel_name'] ?? '') !== '1000G_Phase3_optimized_population') { continue; }
        $code = strtoupper((string) ($row['population_code'] ?? ''));
        $region = strtoupper((string) ($row['super_population'] ?? ''));
        if (!isset($locations[$code]) || !isset($regions[$region]) || (int) ($row['matched_marker_count'] ?? 0) <= 0) { continue; }
        $entry = [
            'code' => $code, 'label' => (string) ($row['population_label'] ?? $code), 'region_code' => $region,
            'affinity_score' => (float) ($row['affinity_score'] ?? 0), 'matched_markers' => (int) ($row['matched_marker_count'] ?? 0),
            'chromosome_count' => (int) ($row['matched_chromosome_count'] ?? 0), 'chromosomes' => (array) ($row['matched_chromosomes'] ?? []),
            'confidence_band' => 'tentative study-group affinity', 'source_version' => (string) ($row['source_version'] ?? 'not supplied'),
            'source_name' => '1000 Genomes Phase 3 named study populations', 'panel_name' => (string) $row['panel_name'],
            'source_url' => 'https://www.internationalgenome.org/data-portal/data-collection/phase-3',
            'map_location' => zlo_location($locations[$code][0], $locations[$code][1], $locations[$code][2]),
        ];
        $rows[] = $entry;
    }
    usort($rows, function ($a, $b) { return $b['affinity_score'] <=> $a['affinity_score']; });
    if (!$rows) {
        $out['status'] = 'no_named_reference_overlap';
        $out['headline'] = 'No callable overlap with the named population panel';
        $out['plain'] = 'The file was checked, but it did not overlap the named reference panel. This is missing comparison evidence, not absence of ancestry.';
        return $out;
    }
    foreach ($rows as $i => &$row) {
        $row['rank'] = $i + 1;
        $row['rank_gap'] = isset($rows[$i + 1]) ? round($row['affinity_score'] - $rows[$i + 1]['affinity_score'], 3) : null;
    }
    unset($row);
    $broad = [];
    foreach ($rows as $row) {
        $code = $row['region_code'];
        if (isset($broad[$code])) { continue; }
        $broad[$code] = array_merge($row, [
            'code' => $code, 'label' => $regions[$code]['label'], 'representative_population' => $row['code'],
            'score_basis' => 'Best matching named study reference within this broad region, using the same population panel.',
            'map_location' => zlo_location($regions[$code]['lat'], $regions[$code]['lon'], $regions[$code]['label']),
        ]);
    }
    $broad = array_values($broad);
    foreach ($broad as $i => &$row) {
        $row['rank'] = $i + 1;
        $row['rank_gap'] = isset($broad[$i + 1]) ? round($row['affinity_score'] - $broad[$i + 1]['affinity_score'], 3) : null;
        $enough = $row['matched_markers'] >= 1000 && $row['chromosome_count'] >= 10;
        $row['confidence_band'] = $enough ? 'well-covered reference comparison; identity uncalibrated' : 'limited marker coverage';
    }
    unset($row);
    $first = $rows[0];
    $lead = $broad[0];
    $regional_leads = zlo_comparison_leads($broad, 'affinity_score');
    $population_leads = zlo_comparison_leads($rows, 'affinity_score');
    $near = array_values(array_filter($rows, function ($row) use ($first) { return $first['affinity_score'] - $row['affinity_score'] <= 0.5; }));
    $near_names = array_map(function ($row) { return $row['label']; }, array_slice($near, 0, 3));
    $enough = $lead['matched_markers'] >= 1000 && $lead['chromosome_count'] >= 10;
    $out['status'] = $enough ? 'reference_findings_available' : 'limited_reference_findings';
    $region_names = implode('; ', array_column($regional_leads['tied_top'], 'label'));
    $population_names = implode('; ', array_column($population_leads['tied_top'], 'label'));
    $out['headline'] = $regional_leads['comparison_tied'] ? 'Comparable broad reference groups' : 'Closest broad comparison: ' . $lead['label'];
    $out['plain'] = ($regional_leads['comparison_tied'] ? 'The leading regional comparisons are tied at the displayed precision: ' . $region_names . '. No unique closest region is resolved. ' : 'Among the installed study references, this file is closest to ' . $lead['label'] . '. ')
        . ($population_leads['comparison_tied'] ? 'The leading study-group scores are also tied: ' . $population_names . '. ' : 'The closest study-group comparison is ' . $first['label'] . '. ')
        . 'These comparisons use ' . number_format($first['matched_markers']) . ' aligned markers across ' . $first['chromosome_count'] . ' autosomes. ' . (count($near) > 1 ? 'Nearby results overlap: ' . implode('; ', $near_names) . '. ' : '') . (!$enough ? 'Coverage is limited, so the ordering is provisional. ' : '') . 'The names describe reference participants, not a fixed personal ethnic identity.';
    $out['broad_regions'] = $broad;
    $out['study_populations'] = $rows;
    $out['closest_region'] = $regional_leads['closest'];
    $out['closest_population'] = $population_leads['closest'];
    $out['comparison_tied'] = $regional_leads['comparison_tied'];
    $out['population_comparison_tied'] = $population_leads['comparison_tied'];
    $out['tied_top_regions'] = $regional_leads['tied_top'];
    $out['tied_top_populations'] = $population_leads['tied_top'];
    $out['nearby_region_count'] = $regional_leads['nearby_count'];
    $out['comparison_resolution'] = 0.1;
    $out['comparison_method'] = 'Broad scores are the best named-reference score in each region. Ties use one-decimal display precision; nearby references are within 0.5 score units as a descriptive display convention. Coverage labels require 1,000 markers on 10 autosomes and are not a calibrated ancestry probability.';
    $out['nearby_reference_count'] = count($near);
    if (strpos($first['source_version'], 'balanced shared-marker candidate 20260905 / training donors only') !== false) {
        $out['calibration'] = [
            'status' => 'internal_held_out_reference_diagnostic', 'heldout_samples' => 130,
            'study_label_correct' => 87, 'broad_region_of_top_study_label_correct' => 125,
            'source_reference_hash' => '259dd2857aac4a19ef4e856b1d6035a15bb9c76b1776756963beb527a68e6df9',
            'plain' => 'In an internal test of 130 excluded public reference donors, the unrounded similarity method recovered 87 study labels and the correct broad group for 125 donors. This small reference-only diagnostic is not your probability of belonging to a group; consumer-array masks, relatives, mixed ancestry and out-of-reference populations need further validation. Rounded scores can tie.',
        ];
    }
    return $out;
}

function zlo_align_call($genotype, $marker, $assembly) {
    $call = zl_dna_genotype_status($genotype);
    $chrom = (string) ($marker['chrom'] ?? '');
    $ref = strtoupper((string) ($marker['ref'] ?? ''));
    $alt = strtoupper((string) ($marker['alt'] ?? ''));
    if (empty($call['callable']) || strlen((string) $call['normalized']) !== 2) { return ['ready' => false, 'reason' => 'not_direct_diploid_snv']; }
    if (!preg_match('/^(?:[1-9]|1[0-9]|2[0-2])$/', $chrom)) { return ['ready' => false, 'reason' => 'non_autosomal']; }
    if ($assembly === '' || strcasecmp((string) ($marker['build'] ?? ''), $assembly) !== 0) { return ['ready' => false, 'reason' => 'assembly_mismatch']; }
    if (!ctype_digit((string) ($marker['pos'] ?? '')) || (int) $marker['pos'] <= 0) { return ['ready' => false, 'reason' => 'invalid_coordinate']; }
    if (!preg_match('/^[ACGT]$/', $ref) || !preg_match('/^[ACGT]$/', $alt) || $ref === $alt) { return ['ready' => false, 'reason' => 'invalid_source_alleles']; }
    $pair = [$ref, $alt]; sort($pair);
    if (in_array(implode('', $pair), ['AT', 'CG'], true)) { return ['ready' => false, 'reason' => 'strand_ambiguous_pair']; }
    if (array_diff(str_split((string) $call['normalized']), [$ref, $alt])) { return ['ready' => false, 'reason' => 'allele_mismatch']; }
    return ['ready' => true, 'dose' => substr_count((string) $call['normalized'], $alt), 'genotype' => (string) $call['normalized']];
}

function zlo_group_sharing($observations, $groups) {
    // Compare every group on the SAME upload loci; missing coverage cannot select a winner.
    $scores = [];
    $common = 0;
    $chromosomes = [];
    $chromosome_stats = [];
    foreach ((array) $observations as $locus => $obs) {
        if (!empty($obs['conflicting_source_alleles'])) { continue; }
        $frequencies = (array) ($obs['frequencies'] ?? []);
        if (array_diff(array_keys($groups), array_keys($frequencies))) { continue; }
        $valid = true;
        foreach ($groups as $code => $meta) {
            $f = $frequencies[$code];
            if (!is_numeric($f) || !is_finite((float) $f) || $f < 0 || $f > 1) { $valid = false; break; }
        }
        $dose = $obs['dose'] ?? -1;
        if (!$valid || !in_array($dose, [0, 1, 2], true)) { continue; }
        $chrom = (string) ($obs['chrom'] ?? '');
        if (!preg_match('/^(?:[1-9]|1[0-9]|2[0-2])$/', $chrom)) { continue; }
        $common++;
        $chromosomes[$chrom] = true;
        if (!isset($chromosome_stats[$chrom])) {
            $chromosome_stats[$chrom] = ['matched_markers' => 0, 'heterozygous_calls' => 0, 'homozygous_reference_calls' => 0, 'homozygous_alternate_calls' => 0, 'reference_variable_loci' => 0, 'differentiating_loci' => 0, 'group_sums' => []];
        }
        $chromosome_stats[$chrom]['matched_markers']++;
        $chromosome_stats[$chrom][$dose === 1 ? 'heterozygous_calls' : ($dose === 0 ? 'homozygous_reference_calls' : 'homozygous_alternate_calls')]++;
        $selected_frequencies = array_intersect_key($frequencies, $groups);
        $variable = count($selected_frequencies) > 1 && (max($selected_frequencies) - min($selected_frequencies)) > 1.0e-9;
        if ($variable) { $chromosome_stats[$chrom]['reference_variable_loci']++; }
        if ($variable && $dose !== 1) { $chromosome_stats[$chrom]['differentiating_loci']++; }
        foreach ($groups as $code => $meta) {
            $p = (float) $frequencies[$code];
            $sharing = ($dose / 2.0) * $p + (1.0 - $dose / 2.0) * (1.0 - $p);
            $scores[$code] = ($scores[$code] ?? 0.0) + $sharing;
            $chromosome_stats[$chrom]['group_sums'][$code] = ($chromosome_stats[$chrom]['group_sums'][$code] ?? 0.0) + $sharing;
        }
    }
    $rows = [];
    foreach ($groups as $code => $meta) {
        $rows[] = array_merge((array) $meta, [
            'code' => (string) $code, 'similarity_score' => $common > 0 ? round(100.0 * ($scores[$code] ?? 0) / $common, 2) : null,
            'matched_markers' => $common, 'chromosome_count' => count(array_filter(array_keys($chromosomes), 'strlen')),
            'shared_allele_equivalents' => $common > 0 ? round(2.0 * ($scores[$code] ?? 0), 3) : 0,
            'compared_allele_count' => 2 * $common,
            'score_definition' => 'Expected fraction of paired alleles shared with a randomly drawn source-group allele, on common directly called loci. Not an inherited ancestry proportion.',
            'confidence_band' => $common >= 1000 ? 'descriptive sharing; ancestry contribution uncalibrated' : ($common >= 50 ? 'limited descriptive sharing' : 'very sparse descriptive sharing'),
        ]);
    }
    usort($rows, function ($a, $b) { return (float) ($b['similarity_score'] ?? -1) <=> (float) ($a['similarity_score'] ?? -1); });
    $diagnostics = zlo_ancient_chromosome_diagnostics($chromosome_stats, $groups, $scores, $common);
    foreach ($rows as &$row) { $row = array_merge($row, $diagnostics['group_statistics'][$row['code']] ?? []); }
    unset($row, $diagnostics['group_statistics']);
    $leads = zlo_comparison_leads($rows, 'similarity_score');
    return array_merge(['groups' => $rows, 'common_marker_count' => $common, 'chromosome_count' => count(array_filter(array_keys($chromosomes), 'strlen')),
        'closest_group' => $common >= 50 ? $leads['closest'] : null, 'comparison_tied' => $leads['comparison_tied'],
        'tied_top_groups' => $leads['tied_top'], 'nearby_group_count' => $leads['nearby_count'], 'comparison_resolution' => 0.1,
        'comparison_method' => 'Every group uses the same directly called loci. Equal one-decimal scores are tied; nearby groups fall within 0.5 score units. These are display conventions, not calibrated statistical uncertainty.'], $diagnostics);
}

function zlo_ancient_chromosome_diagnostics($stats, $groups, $totals, $common) {
    // All inputs are aggregate sums from the same accepted-locus loop as the headline score.
    uksort($stats, function ($a, $b) { return (int) $a <=> (int) $b; });
    $breakdown = [];
    $group_statistics = [];
    $counts = ['heterozygous_calls' => 0, 'homozygous_reference_calls' => 0, 'homozygous_alternate_calls' => 0, 'reference_variable_loci' => 0, 'differentiating_loci' => 0];
    $pooled = [];
    $balanced = [];
    $panel_mean = $common > 0 && count($groups) > 0 ? 100.0 * array_sum($totals) / ($common * count($groups)) : null;
    foreach ($groups as $code => $meta) {
        $score = $common > 0 ? 100.0 * ($totals[$code] ?? 0) / $common : null;
        $pooled[] = ['code' => (string) $code, 'label' => (string) ($meta['label'] ?? $code), 'similarity_score' => $score === null ? null : round($score, 2)];
        $group_statistics[$code] = ['relative_to_panel_mean' => $score === null ? null : round($score - $panel_mean, 3), 'mean_chromosome_affinity_score' => null, 'unique_lead_omission_runs' => 0, 'tied_lead_omission_runs' => 0, 'omission_score_min' => null, 'omission_score_max' => null];
    }
    $dominant = null;
    foreach ($stats as $chrom => $row) {
        $n = (int) $row['matched_markers'];
        foreach ($counts as $key => $unused) { $counts[$key] += (int) ($row[$key] ?? 0); }
        $chr_mean = $n > 0 && count($groups) > 0 ? 100.0 * array_sum($row['group_sums']) / ($n * count($groups)) : null;
        $entry = ['chromosome' => (string) $chrom, 'matched_markers' => $n, 'compared_allele_count' => 2 * $n,
            'heterozygous_calls' => (int) $row['heterozygous_calls'], 'homozygous_calls' => (int) $row['homozygous_reference_calls'] + (int) $row['homozygous_alternate_calls'],
            'differentiating_loci' => (int) $row['differentiating_loci'], 'groups' => []];
        foreach ($groups as $code => $meta) {
            $score = 100.0 * ($row['group_sums'][$code] ?? 0.0) / $n;
            $balanced[$code] = ($balanced[$code] ?? 0.0) + $score;
            $entry['groups'][] = ['code' => (string) $code, 'label' => (string) ($meta['label'] ?? $code), 'similarity_score' => round($score, 2),
                'relative_to_panel_mean' => round($score - $chr_mean, 3), 'shared_allele_equivalents' => round(2.0 * ($row['group_sums'][$code] ?? 0.0), 3),
                'compared_allele_count' => 2 * $n];
        }
        if ($dominant === null || $n > $dominant['matched_markers']) { $dominant = ['chromosome' => (string) $chrom, 'matched_markers' => $n, 'fraction_of_common_loci' => $common > 0 ? round($n / $common, 4) : null]; }
        $breakdown[] = $entry;
    }
    $balanced_rows = [];
    foreach ($groups as $code => $meta) {
        $score = count($stats) > 0 ? round(($balanced[$code] ?? 0) / count($stats), 2) : null;
        $group_statistics[$code]['mean_chromosome_affinity_score'] = $score;
        $balanced_rows[] = ['code' => (string) $code, 'label' => (string) ($meta['label'] ?? $code), 'similarity_score' => $score];
    }
    $pooled_leads = zlo_comparison_leads($pooled, 'similarity_score');
    $balanced_leads = zlo_comparison_leads($balanced_rows, 'similarity_score');
    $pooled_codes = array_column($pooled_leads['tied_top'], 'code'); sort($pooled_codes);
    $balanced_codes = array_column($balanced_leads['tied_top'], 'code'); sort($balanced_codes);
    $runs = [];
    $unique_retained = 0;
    $lead_present = 0;
    foreach ($stats as $chrom => $row) {
        $remaining = $common - (int) $row['matched_markers'];
        // A run needs at least two remaining chromosomes and 50 loci; this is a display rule, not calibration.
        if (count($stats) < 3 || $remaining < 50) { continue; }
        $remaining_rows = [];
        foreach ($groups as $code => $meta) {
            $score = round(100.0 * (($totals[$code] ?? 0) - ($row['group_sums'][$code] ?? 0)) / $remaining, 2);
            $remaining_rows[] = ['code' => (string) $code, 'label' => (string) ($meta['label'] ?? $code), 'similarity_score' => $score];
            $prev_min = $group_statistics[$code]['omission_score_min'];
            $prev_max = $group_statistics[$code]['omission_score_max'];
            $group_statistics[$code]['omission_score_min'] = $prev_min === null ? $score : min($score, $prev_min);
            $group_statistics[$code]['omission_score_max'] = $prev_max === null ? $score : max($score, $prev_max);
        }
        $leads = zlo_comparison_leads($remaining_rows, 'similarity_score');
        $run_codes = array_column($leads['tied_top'], 'code');
        foreach ($run_codes as $code) { $group_statistics[$code][$leads['comparison_tied'] ? 'tied_lead_omission_runs' : 'unique_lead_omission_runs']++; }
        $original_code = $pooled_leads['closest']['code'] ?? null;
        if ($original_code !== null && in_array($original_code, $run_codes, true)) { $lead_present++; }
        if ($original_code !== null && ($leads['closest']['code'] ?? null) === $original_code) { $unique_retained++; }
        $runs[] = ['omitted_chromosome' => (string) $chrom, 'remaining_markers' => $remaining, 'remaining_chromosome_count' => count($stats) - 1,
            'closest_group' => $leads['closest'], 'comparison_tied' => $leads['comparison_tied'], 'leading_group_codes' => $run_codes, 'group_scores' => $remaining_rows];
    }
    $run_count = count($runs);
    $stable = $run_count > 0 && $pooled_leads['closest'] !== null && $unique_retained === $run_count;
    $stability = ['status' => !$run_count ? 'insufficient_chromosome_coverage' : ($pooled_leads['closest'] === null ? 'no_unique_pooled_lead' : ($stable ? 'lead_retained_in_all_omissions' : 'lead_sensitive_to_chromosome_omission')),
        'method' => 'Recompute the same shared-allele score after removing each covered chromosome once. Each run retains at least 50 loci on two chromosomes. Counts and score ranges are a sensitivity check, not a confidence interval, ancestry probability, or independent validation.',
        'evaluated_omissions' => $run_count, 'pooled_closest_group' => $pooled_leads['closest'],
        'unique_lead_retained_count' => $pooled_leads['closest'] === null ? null : $unique_retained,
        'lead_present_including_ties_count' => $pooled_leads['closest'] === null ? null : $lead_present,
        'runs' => $runs, 'equal_chromosome_weighting' => ['closest_group' => $balanced_leads['closest'], 'leading_group_codes' => $balanced_codes,
            'same_leading_groups_as_pooled' => $common > 0 ? $pooled_codes === $balanced_codes : null,
            'method' => 'Average the per-chromosome scores with equal chromosome weights to reveal sensitivity to marker density. This is a diagnostic alternative, not a replacement ancestry estimate.']];
    $stability['plain'] = !$run_count ? 'The overlap is too concentrated for a chromosome-omission check.'
        : ($pooled_leads['closest'] === null ? 'The pooled scores do not resolve a unique leading group; chromosome-omission results are shown without assigning confidence to an arbitrary winner.'
        : 'The leading group remained the sole leader in ' . $unique_retained . ' of ' . $run_count . ' chromosome-omission checks. This measures sensitivity to the compared chromosomes, not the probability of ancestry.');
    $diagnostics = array_merge($counts, ['common_marker_count' => $common, 'compared_allele_count' => 2 * $common,
        'homozygous_calls' => $counts['homozygous_reference_calls'] + $counts['homozygous_alternate_calls'],
        'non_differentiating_loci' => $common - $counts['differentiating_loci'], 'panel_mean_score' => $panel_mean === null ? null : round($panel_mean, 3),
        'dominant_chromosome' => $dominant,
        'interpretability' => $common === 0 ? 'no_common_calls' : ($counts['differentiating_loci'] === 0 ? 'no_group_differentiating_calls' : ($counts['differentiating_loci'] < 50 ? 'sparse_group_differentiation' : 'descriptive_group_differentiation')),
        'score_explainer' => 'Each heterozygous call contributes exactly 50/100 to every group. Group ordering comes only from homozygous calls at loci whose ancient group frequencies differ. A high absolute sharing score can reflect common alleles and does not establish a specific origin; 50 is not an average-human baseline.',
        'contrast_explainer' => 'Relative-to-panel-mean values subtract the equally weighted mean of the ' . count($groups) . ' compared group scores at the same loci. They express score differences in points, not ancestry fractions or statistical significance.',
        'shared_alleles_explainer' => 'Compared allele copies are two per direct diploid call. Shared-allele equivalents are an expectation using ancient group frequencies and may be fractional; they are not a count of exact ancient-person matches.']);
    return ['chromosome_breakdown' => $breakdown, 'score_diagnostics' => $diagnostics, 'rank_stability' => $stability, 'group_statistics' => $group_statistics];
}

function zlo_ancient_sharing_plain($sharing) {
    $count = (int) ($sharing['common_marker_count'] ?? 0);
    if ($count < 50) { return $count > 0 ? 'The panel overlaps ' . $count . ' comparable markers. The measured scores are shown for transparency, but this sparse set cannot support a strongest-origin conclusion.' : 'The source groups and their historical context are shown below. No personal similarity score is inferred where the file has no aligned common calls.'; }
    $lead = (array) ($sharing['closest_group'] ?? []);
    $opening = !empty($sharing['comparison_tied'])
        ? 'The leading ancient-reference scores are tied at the displayed precision: ' . implode('; ', array_column((array) ($sharing['tied_top_groups'] ?? []), 'label')) . '. No unique closest ancient group is resolved. '
        : 'The largest measured allele-sharing affinity in this reference set is with ' . ($lead['label'] ?? 'the leading ancient group') . '. ';
    $detail = '';
    if (isset($sharing['score_diagnostics']['differentiating_loci'])) {
        $different = (int) $sharing['score_diagnostics']['differentiating_loci'];
        $detail = ' Of these, ' . number_format($different) . ' homozygous calls occur at loci where the reference groups have different allele frequencies; those are the calls that can distinguish these groups with this score.';
    }
    return $opening . 'Every group was compared on the same ' . number_format($count) . ' directly called markers.' . $detail . ' This describes shared variation, not the fraction of your DNA inherited from that group.';
}

function zlo_add_ancient_observation(&$observations, $key, $genotype, $marker, $assembly) {
    $aligned = zlo_align_call($genotype, $marker, $assembly);
    if (empty($aligned['ready'])) { return (string) $aligned['reason']; }
    if (!is_numeric($marker['alt_frequency'] ?? null) || !is_finite((float) $marker['alt_frequency']) || $marker['alt_frequency'] < 0 || $marker['alt_frequency'] > 1 || (int) ($marker['observed_samples'] ?? 0) < 2 || (float) ($marker['allele_number'] ?? 0) <= 0) { return 'insufficient_or_invalid_source_observations'; }
    $pair = strtoupper((string) $marker['ref'] . (string) $marker['alt']);
    if (isset($observations[$key]) && $observations[$key]['source_pair'] !== $pair) {
        $observations[$key]['conflicting_source_alleles'] = true;
        return 'inconsistent_group_allele_coding';
    }
    if (!isset($observations[$key])) {
        $observations[$key] = ['dose' => (int) $aligned['dose'], 'chrom' => (string) $marker['chrom'], 'source_pair' => $pair, 'frequencies' => []];
    }
    $group = (string) ($marker['group_code'] ?? '');
    if ($group === '') { return 'group_missing'; }
    if (array_key_exists($group, $observations[$key]['frequencies']) && $observations[$key]['frequencies'][$group] !== (float) $marker['alt_frequency']) {
        $observations[$key]['conflicting_source_alleles'] = true;
        return 'conflicting_duplicate_frequency';
    }
    $observations[$key]['frequencies'][$group] = (float) $marker['alt_frequency'];
    return 'aligned';
}

function zlo_ancient_origins($coordinates, $summary, $pack_path = null) {
    $pack_path = $pack_path ?: dirname(__DIR__) . '/data/packs/ancient_origins.sqlite';
    $gate = zl_dna_health_interpretation_gate($summary);
    $boundary = 'Shared-allele affinity describes similarity at the compared ancient-reference loci. It is not a percentage inherited from hunter-gatherers, farmers or Steppe people, and it does not prove descent from a sample, culture or place. Modern populations share much older common variation.';
    $out = ['status' => 'reference_pack_unavailable', 'headline' => 'Ancient origins reference coverage', 'plain' => 'Aligned ancient reference groups are not installed yet.', 'groups' => [], 'common_marker_count' => 0, 'chromosome_count' => 0, 'boundary' => $boundary, 'analysis_gate' => $gate, 'sources' => []];
    if (empty($gate['ready'])) {
        $out['status'] = 'needs_file_validation';
        $out['plain'] = 'Confirm this file’s build and forward orientation before comparing ancient allele frequencies.';
        return $out;
    }
    if (!is_readable($pack_path) || !class_exists('SQLite3')) { return $out; }
    $db = null;
    try {
        $db = new SQLite3($pack_path, SQLITE3_OPEN_READONLY);
        $columns = zl_sqlite_columns($db, 'ancient_origin_markers');
        $required = ['chrom', 'pos', 'build', 'ref', 'alt', 'group_code', 'group_label', 'alt_frequency', 'allele_number', 'observed_samples', 'source_version'];
        if (array_diff($required, $columns) || !zl_sqlite_table_exists($db, 'ancient_origin_groups')) {
            $db->close(); $out['status'] = 'source_alignment_contract_missing';
            $out['plain'] = 'The ancient source needs explicit aligned alleles and observed sample counts before a comparison can be made.';
            return $out;
        }
        $meta = zl_dna_sqlite_meta($db);
        $out['pack_meta'] = $meta;
        $group_query = $db->query('SELECT * FROM ancient_origin_groups ORDER BY group_code');
        $groups = [];
        while ($group_query && ($row = $group_query->fetchArray(SQLITE3_ASSOC))) {
            if ((int) ($row['sample_count'] ?? 0) < 2) { continue; }
            $groups[(string) $row['group_code']] = [
                'label' => (string) ($row['group_label'] ?? $row['group_code']), 'short_plain' => (string) ($row['description'] ?? ''),
                'region' => (string) ($row['region'] ?? ''), 'era' => (string) ($row['period_label'] ?? ''),
                'time_start_bp' => isset($row['time_start_bp']) ? (float) $row['time_start_bp'] : null,
                'time_end_bp' => isset($row['time_end_bp']) ? (float) $row['time_end_bp'] : null,
                'sample_count' => (int) ($row['sample_count'] ?? 0), 'panel_marker_count' => (int) ($row['marker_count'] ?? 0),
                'source_version' => (string) ($row['source_version'] ?? ''), 'source_url' => (string) ($row['source_url'] ?? ''),
                'source_name' => 'Allen Ancient DNA Resource', 'limitations' => (string) ($row['limitations'] ?? $boundary),
            ];
        }
        if (count($groups) < 2) {
            $db->close(); $out['status'] = 'insufficient_reference_groups';
            $out['plain'] = 'At least two adequately observed ancient reference groups are needed for this comparative view.';
            return $out;
        }
        $by_chromosome = [];
        foreach ((array) $coordinates as $key => $genotype) {
            if (!preg_match('/^([1-9]|1[0-9]|2[0-2]):([1-9][0-9]*)$/', (string) $key, $m)) { continue; }
            $by_chromosome[$m[1]][$m[2]] = $genotype;
        }
        $observations = [];
        $diagnostics = ['candidate_reference_rows' => 0, 'excluded_reference_rows' => []];
        foreach ($by_chromosome as $chrom => $calls) {
            foreach (array_chunk(array_keys($calls), 350) as $positions) {
                $sql = "SELECT chrom,pos,build,ref,alt,group_code,group_label,alt_frequency,allele_number,observed_samples,source_version FROM ancient_origin_markers WHERE build='" . SQLite3::escapeString((string) $gate['assembly']) . "' AND chrom='" . SQLite3::escapeString((string) $chrom) . "' AND pos IN (" . implode(',', array_map('intval', $positions)) . ')';
                $result = $db->query($sql);
                if (!$result) { throw new RuntimeException('source lookup unavailable'); }
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    if (!isset($groups[(string) $row['group_code']])) { continue; }
                    $diagnostics['candidate_reference_rows']++;
                    $key = (string) $row['chrom'] . ':' . (string) $row['pos'];
                    $state = zlo_add_ancient_observation($observations, $key, $calls[(string) $row['pos']] ?? '', $row, (string) $gate['assembly']);
                    if ($state !== 'aligned') { $diagnostics['excluded_reference_rows'][$state] = ($diagnostics['excluded_reference_rows'][$state] ?? 0) + 1; }
                }
            }
        }
        $db->close(); $db = null;
        $observations = array_filter($observations, function ($row) { return empty($row['conflicting_source_alleles']); });
        $sharing = zlo_group_sharing($observations, $groups);
        $count = (int) $sharing['common_marker_count'];
        $out = array_merge($out, $sharing, ['alignment_diagnostics' => $diagnostics]);
        $out['status'] = $count >= 50 ? 'descriptive_affinity_available' : ($count > 0 ? 'sparse_reference_overlap' : 'no_callable_reference_overlap');
        $out['headline'] = $count >= 50 ? 'Ancient groups compared across ' . number_format($count) . ' common markers' : ($count > 0 ? 'A small ancient-reference overlap was measured' : 'This file has no usable common ancient-reference markers');
        $out['plain'] = zlo_ancient_sharing_plain($sharing);
        return $out;
    } catch (Throwable $e) {
        if ($db) { $db->close(); }
        $out['status'] = 'source_lookup_incomplete';
        $out['plain'] = 'The aligned ancient source lookup could not complete. No partial result is presented as a finished comparison.';
        return $out;
    }
}

function zlo_archaic_categories() {
    return [
        'NEANDERTHAL' => ['code' => 'NEANDERTHAL', 'label' => 'Neanderthal-related source tags'],
        'DENISOVAN' => ['code' => 'DENISOVAN', 'label' => 'Denisovan-related source tags'],
    ];
}

function zlo_archaic_tag_summary($markers, $coordinates, $summary, $panel_totals = []) {
    $gate = zl_dna_health_interpretation_gate($summary);
    $categories = zlo_archaic_categories();
    foreach ($categories as &$category) {
        $category += ['status' => 'source_panel_unavailable', 'observed_tag_count' => 0, 'callable_tag_count' => 0, 'panel_tag_count' => 0, 'coverage_percent' => null, 'confidence_band' => 'not measured', 'sources' => [], 'observed_allele_copy_count' => 0];
    }
    unset($category);
    $seen = [];
    foreach ($markers as $marker) {
        $category = strtoupper((string) ($marker['archaic_group'] ?? ''));
        if (!isset($categories[$category])) { continue; }
        if (($marker['evidence_type'] ?? '') !== 'published_introgression_tag' || empty($marker['source_url']) || empty($marker['source_version'])) { continue; }
        $tag = strtoupper((string) ($marker['tag_allele'] ?? ''));
        if (!in_array($tag, [strtoupper((string) ($marker['ref'] ?? '')), strtoupper((string) ($marker['alt'] ?? ''))], true) || !preg_match('/^[ACGT]$/', $tag)) { continue; }
        $key = (string) ($marker['chrom'] ?? '') . ':' . (string) ($marker['pos'] ?? '');
        $unique = $category . '|' . $key . '|' . $tag;
        if (isset($seen[$unique])) { continue; }
        $seen[$unique] = true;
        $categories[$category]['panel_tag_count']++;
        $source_key = (string) $marker['source_url'] . '|' . (string) $marker['source_version'];
        $categories[$category]['sources'][$source_key] = ['source_name' => (string) ($marker['source_name'] ?? 'Published archaic tag source'), 'source_version' => (string) $marker['source_version'], 'source_url' => (string) $marker['source_url']];
        if (empty($gate['ready'])) { continue; }
        $aligned = zlo_align_call($coordinates[$key] ?? '', $marker, (string) ($gate['assembly'] ?? ''));
        if (empty($aligned['ready'])) { continue; }
        $categories[$category]['callable_tag_count']++;
        $copies = substr_count((string) $aligned['genotype'], $tag);
        $categories[$category]['observed_allele_copy_count'] += $copies;
        if ($copies > 0) { $categories[$category]['observed_tag_count']++; }
    }
    foreach ($categories as &$category) {
        $known_total = $panel_totals[$category['code']] ?? $panel_totals[strtolower($category['code'])] ?? null;
        if (is_numeric($known_total) && (int) $known_total >= $category['panel_tag_count']) {
            $category['panel_tag_count'] = (int) $known_total;
        }
        $n = $category['panel_tag_count'];
        $called = $category['callable_tag_count'];
        $category['sources'] = array_values($category['sources']);
        $category['coverage_percent'] = $n > 0 ? round(100.0 * $called / $n, 2) : null;
        $category['status'] = empty($gate['ready']) ? 'needs_file_validation' : ($n === 0 ? 'source_panel_unavailable' : ($called > 0 ? 'tag_loci_measured' : 'no_callable_tag_overlap'));
        $category['confidence_band'] = $called > 0 ? 'direct tag observations; archaic ancestry proportion unknown' : 'not measured';
        $category['plain'] = $called > 0
            ? $category['observed_tag_count'] . ' of ' . $called . ' callable source-tag loci carry the published tag allele. This measures observed tags, not the percentage of your genome from an archaic population.'
            : ($n > 0 ? 'This source has ' . $n . ' tags, but none could be confidently called in this file. This does not mean no archaic ancestry.' : 'A licensed, allele-aligned published source-tag panel is not installed for this category. No archaic ancestry amount is inferred.');
    }
    unset($category);
    return ['status' => 'tag_coverage_report', 'headline' => 'Archaic source-tag coverage', 'plain' => 'These cards distinguish observed source-tag alleles from untested sites and whole-genome archaic ancestry.', 'categories' => array_values($categories), 'analysis_gate' => $gate, 'boundary' => 'Source-tag alleles can mark linked introgressed haplotypes but are not a whole-genome Neanderthal or Denisovan percentage. Individual common alleles can have other histories; a haplotype method and validated background references are needed for ancestry amounts.'];
}

function zlo_archaic_origins($coordinates, $summary, $pack_path = null) {
    $pack_path = $pack_path ?: dirname(__DIR__) . '/data/packs/archaic_markers.sqlite';
    $empty = zlo_archaic_tag_summary([], $coordinates, $summary);
    if (!is_readable($pack_path) || !class_exists('SQLite3')) { return $empty; }
    $db = null;
    try {
        $db = new SQLite3($pack_path, SQLITE3_OPEN_READONLY);
        $required = ['rsid', 'chrom', 'pos', 'build', 'ref', 'alt', 'archaic_allele', 'archaic_group', 'source_version'];
        if (array_diff($required, zl_sqlite_columns($db, 'archaic_markers'))) { $db->close(); return $empty; }
        $meta = zl_dna_sqlite_meta($db);
        if (($meta['evidence_type'] ?? '') !== 'published_introgression_tag' || empty($meta['source_url'])) { $db->close(); return $empty; }
        $totals = json_decode((string) ($meta['tag_count_by_group'] ?? '{}'), true);
        if (!is_array($totals)) { $totals = []; }
        $gate = zl_dna_health_interpretation_gate($summary);
        $by_chromosome = [];
        if (!empty($gate['ready'])) {
            foreach ($coordinates as $key => $genotype) {
                if (preg_match('/^([1-9]|1[0-9]|2[0-2]):([1-9][0-9]*)$/', (string) $key, $m)) { $by_chromosome[$m[1]][] = (int) $m[2]; }
            }
        }
        $records = function () use ($db, $by_chromosome, $gate, $meta) {
            foreach ($by_chromosome as $chrom => $positions) {
                foreach (array_chunk($positions, 350) as $chunk) {
                    $sql = "SELECT chrom,pos,build,ref,alt,archaic_allele,archaic_group,source_version FROM archaic_markers WHERE build='" . SQLite3::escapeString((string) $gate['assembly']) . "' AND chrom='" . SQLite3::escapeString((string) $chrom) . "' AND pos IN (" . implode(',', array_map('intval', $chunk)) . ')';
                    $result = $db->query($sql);
                    if (!$result) { throw new RuntimeException('archaic source lookup unavailable'); }
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $row['tag_allele'] = $row['archaic_allele'];
                        $row['evidence_type'] = 'published_introgression_tag';
                        $row['source_name'] = (string) ($meta['source_name'] ?? 'Browning et al. 2018 published Sprime results');
                        $row['source_url'] = (string) $meta['source_url'];
                        yield $row;
                    }
                }
            }
        };
        $out = zlo_archaic_tag_summary($records(), $coordinates, $summary, $totals);
        $db->close(); $db = null;
        $out['pack_meta'] = $meta;
        foreach ($out['categories'] as &$category) {
            if (!$category['sources']) {
                $category['sources'] = [['source_name' => (string) ($meta['source_name'] ?? 'Published Sprime source'), 'source_version' => (string) ($meta['source_version'] ?? 'not supplied'), 'source_url' => (string) $meta['source_url']]];
            }
            $category['source_ascertainment'] = (string) ($meta['ascertainment'] ?? $meta['rules'] ?? 'Source-selected published tags; not a representative sample of the genome.');
        }
        unset($category);
        return $out;
    } catch (Throwable $e) {
        if ($db) { $db->close(); }
        $empty['status'] = 'source_lookup_incomplete';
        $empty['plain'] = 'The archaic source lookup could not complete; partial counts are withheld.';
        return $empty;
    }
}

function zlo_evidence_cards($modern, $ancient, $archaic) {
    $cards = [];
    $lead = (array) ($modern['closest_region'] ?? []);
    if ($lead) {
        $cards[] = ['title' => 'Modern regional reference lead', 'claim_type' => 'origins_reference_affinity', 'claim_label' => (string) $lead['label'], 'evidence_level' => 'aligned_named_population_reference_comparison', 'confidence_band' => (string) $lead['confidence_band'], 'matched_marker_count' => (int) $lead['matched_markers'], 'source_name' => (string) $lead['source_name'], 'source_version' => (string) $lead['source_version'], 'explanation' => (string) $modern['plain'], 'limitations' => (string) $modern['boundary'], 'next_steps' => 'Review nearby reference groups and marker coverage; avoid interpreting affinity as a percentage.'];
    }
    if ((int) ($ancient['common_marker_count'] ?? 0) > 0) {
        foreach ((array) ($ancient['groups'] ?? []) as $group) {
            $cards[] = ['title' => 'Ancient reference: ' . (string) $group['label'], 'claim_type' => 'ancient_shared_alleles', 'claim_label' => 'descriptive allele-sharing affinity', 'evidence_level' => 'aligned_common_locus_comparison', 'confidence_band' => (string) $group['confidence_band'], 'matched_marker_count' => (int) $group['matched_markers'], 'source_name' => (string) ($group['source_name'] ?? 'Allen Ancient DNA Resource'), 'source_version' => (string) $group['source_version'], 'explanation' => 'Shared-allele affinity ' . $group['similarity_score'] . '/100 on ' . $group['matched_markers'] . ' common loci across ' . $group['chromosome_count'] . ' autosomes.', 'limitations' => (string) $ancient['boundary'], 'next_steps' => 'Interpret alongside all compared ancient groups; ancestry mixture percentages require a validated admixture model.'];
        }
    }
    foreach ((array) ($archaic['categories'] ?? []) as $category) {
        if ((int) ($category['callable_tag_count'] ?? 0) <= 0) { continue; }
        $source = (array) ($category['sources'][0] ?? []);
        $cards[] = ['title' => (string) $category['label'], 'claim_type' => 'archaic_source_tags', 'claim_label' => $category['observed_tag_count'] . ' tag loci carried among ' . $category['callable_tag_count'] . ' callable source loci', 'evidence_level' => 'direct_observed_published_tag_alleles', 'confidence_band' => (string) $category['confidence_band'], 'matched_marker_count' => (int) $category['callable_tag_count'], 'source_name' => (string) ($source['source_name'] ?? ''), 'source_version' => (string) ($source['source_version'] ?? ''), 'explanation' => (string) $category['plain'], 'limitations' => (string) $archaic['boundary'], 'next_steps' => 'A phased haplotype analysis and background reference populations are required to estimate genome-wide archaic ancestry.'];
    }
    return $cards;
}

function zl_dna_origins_report_path($path, $name, $summary = null, $heritage = null) {
    $summary = is_array($summary) ? $summary : zl_dna_apply_analysis_options(zl_dna_raw_analyze_path($path, $name), []);
    $heritage = is_array($heritage) ? $heritage : zl_dna_heritage_report_path($path, $name, $summary);
    $modern = zlo_modern_origins($heritage, $summary);
    $gate = zl_dna_health_interpretation_gate($summary);
    $coordinates = [];
    if (!empty($gate['ready'])) {
        $map = zl_dna_coordinate_genotype_map_path($path, $name, range(1, 22), zl_dna_max_raw_rows());
        $coordinates = (array) ($map['variants'] ?? []);
    }
    $modern['regional_refs'] = zl_dna_regional_refs_report($coordinates, $summary);
    $modern['consumer_summary'] = zlas_consumer_summary($modern);
    $classification = zl_dna_grafanc_report_path($path, $name, $summary);
    if (($classification['available'] ?? false) === true) {
        $modern['consumer_summary']['headline'] = (string) $classification['headline'];
        $modern['consumer_summary']['plain'] = (string) $classification['plain'];
        $modern['consumer_summary']['primary_method'] = 'published_population_structure_model';
    }
    $ancient = zlo_ancient_origins($coordinates, $summary);
    $archaic = zlo_archaic_origins($coordinates, $summary);
    return [
        'version' => 'dna-origins-evidence-v2', 'headline' => (string) (($classification['available'] ?? false) === true ? $classification['headline'] : $modern['headline']),
        'summary' => (string) (($classification['available'] ?? false) === true ? $classification['plain'] : $modern['plain']), 'modern' => $modern, 'classification'=>$classification, 'ancient' => $ancient, 'archaic' => $archaic,
        'evidence_cards' => zlo_evidence_cards($modern, $ancient, $archaic),
        'privacy' => ['raw_rows_included' => false, 'external_genotype_requests' => false, 'processing' => 'local only; report contains aggregate evidence'],
        'boundary' => 'Origins findings are reference comparisons and observed source-tag evidence. They do not establish identity, ancestry percentages, clinical findings or descent from a named ancient person.',
    ];
}

function zl_dna_origins_markdown($origins) {
    $cell = function ($value) { return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], (string) $value); };
    $modern = (array) ($origins['modern'] ?? []);
    $ancient = (array) ($origins['ancient'] ?? []);
    $archaic = (array) ($origins['archaic'] ?? []);
    $lines = ['## Your origins reference findings', '', '**' . $cell($origins['headline'] ?? 'Origins evidence') . '**', '', $cell($origins['summary'] ?? ''), '', '### Modern regional comparisons', ''];
    if (!empty($origins['classification'])) { array_splice($lines, 6, 0, [zl_dna_grafanc_markdown($origins['classification']), '']); }
    if (!empty($modern['consumer_summary'])) { array_splice($lines, 6, 0, [zlas_story_markdown($modern), '']); }
    if (!empty($modern['broad_regions'])) {
        $lines[] = 'Each broad-region score is the best named study-population comparison inside that region, using the same reference panel. The scores do not sum to 100 and are not ancestry proportions.';
        $lines[] = '';
        $lines[] = '| Regional reference | Affinity /100 | Compared markers | Autosomes | Evidence support |';
        $lines[] = '| --- | ---: | ---: | ---: | --- |';
        foreach ($modern['broad_regions'] as $row) {
            $lines[] = '| ' . $cell($row['label']) . ' | ' . number_format((float) $row['affinity_score'], 1) . ' | ' . number_format((int) $row['matched_markers']) . ' | ' . (int) $row['chromosome_count'] . ' / 22 | ' . $cell($row['confidence_band']) . ' |';
        }
        $lines[] = '';
        $lines[] = '### Nearby modern study references';
        $lines[] = '';
        foreach (array_slice((array) ($modern['study_populations'] ?? []), 0, 8) as $row) {
            $lines[] = '- **' . $cell($row['label']) . ' (' . $cell($row['code']) . '):** ' . $row['affinity_score'] . '/100 affinity across ' . number_format((int) $row['matched_markers']) . ' markers on ' . $row['chromosome_count'] . ' autosomes. Source: ' . $cell($row['source_version']) . '.';
        }
        $lines[] = '';
        if (!empty($modern['calibration']['plain'])) { $lines[] = $cell($modern['calibration']['plain']); $lines[] = ''; }
    } else {
        $lines[] = $cell($modern['plain'] ?? 'No aligned modern comparison available.');
        $lines[] = '';
    }
    $lines[] = $cell($modern['boundary'] ?? 'Reference affinity is not personal identity or ancestry composition.');
    if (!empty($modern['regional_refs'])) { $lines[] = ''; $lines[] = zl_dna_regional_refs_markdown($modern['regional_refs']); }
    $lines[] = '';
    $lines[] = '### Ancient origins and shared alleles';
    $lines[] = '';
    $lines[] = '**' . $cell($ancient['headline'] ?? 'Ancient source coverage') . '**';
    $lines[] = '';
    $lines[] = $cell($ancient['plain'] ?? 'Ancient reference data is unavailable.');
    $lines[] = '';
    foreach ((array) ($ancient['groups'] ?? []) as $group) {
        $score = $group['similarity_score'] === null ? 'not measured' : $group['similarity_score'] . '/100 shared-allele affinity';
        $lines[] = '- **' . $cell($group['label']) . ':** ' . $score . '; ' . number_format((int) $group['matched_markers']) . ' common markers, ' . $group['chromosome_count'] . ' autosomes, ' . $group['sample_count'] . ' ancient reference individuals.';
        $lines[] = '  Historical context: ' . $cell($group['region'] ?? '') . '; ' . $cell($group['era'] ?? '') . '. ' . $cell($group['short_plain'] ?? '');
        $lines[] = '  Source: ' . $cell($group['source_version'] ?? '') . '. Evidence: ' . $cell($group['confidence_band'] ?? '') . '.';
        if (!empty($group['source_url'])) { $lines[] = '  [Ancient source documentation](' . str_replace(['(', ')', ' '], ['%28', '%29', '%20'], (string) $group['source_url']) . ')'; }
    }
    $lines[] = '';
    $lines[] = $cell($ancient['boundary'] ?? 'Shared ancient alleles are not a percentage inherited from an ancient group.');
    $lines[] = '';
    if (!empty($ancient['chromosome_breakdown'])) {
        $diag = (array) ($ancient['score_diagnostics'] ?? []);
        $lines[] = '### Inspect the ancient comparison';
        $lines[] = '';
        $lines[] = '- Common directly called loci: ' . number_format((int) ($diag['common_marker_count'] ?? 0)) . '; compared allele copies: ' . number_format((int) ($diag['compared_allele_count'] ?? 0)) . '.';
        $lines[] = '- Calls that can differentiate these groups: ' . number_format((int) ($diag['differentiating_loci'] ?? 0)) . '. Heterozygous calls: ' . number_format((int) ($diag['heterozygous_calls'] ?? 0)) . '; homozygous calls: ' . number_format((int) ($diag['homozygous_calls'] ?? 0)) . '.';
        $lines[] = $cell($diag['score_explainer'] ?? '');
        $lines[] = '';
        $lines[] = '| Reference group | Difference from panel mean, points | Equal-chromosome score /100 | Omission score range /100 |';
        $lines[] = '| --- | ---: | ---: | --- |';
        foreach ((array) ($ancient['groups'] ?? []) as $group) {
            $contrast = isset($group['relative_to_panel_mean']) ? sprintf('%+.3f', $group['relative_to_panel_mean']) : 'not measured';
            $balanced = isset($group['mean_chromosome_affinity_score']) ? number_format((float) $group['mean_chromosome_affinity_score'], 2) : 'not measured';
            $range = isset($group['omission_score_min'], $group['omission_score_max']) ? $group['omission_score_min'] . ' to ' . $group['omission_score_max'] : 'not enough coverage';
            $lines[] = '| ' . $cell($group['label']) . ' | ' . $contrast . ' | ' . $balanced . ' | ' . $range . ' |';
        }
        $lines[] = '';
        $lines[] = $cell($diag['contrast_explainer'] ?? '');
        $lines[] = $cell($ancient['rank_stability']['plain'] ?? '');
        $lines[] = $cell($ancient['rank_stability']['method'] ?? '');
        $lines[] = '';
        $lines[] = '#### Ancient scores by chromosome';
        $lines[] = '';
        $group_codes = array_column((array) ($ancient['groups'] ?? []), 'code');
        $lines[] = '| Chromosome | Common loci | Differentiating loci | ' . implode(' | ', array_map($cell, $group_codes)) . ' |';
        $lines[] = '| --- | ---: | ---: | ' . implode(' | ', array_fill(0, count($group_codes), '---:')) . ' |';
        foreach ($ancient['chromosome_breakdown'] as $chromosome) {
            $scores = array_column((array) ($chromosome['groups'] ?? []), 'similarity_score', 'code');
            $values = [];
            foreach ($group_codes as $code) { $values[] = isset($scores[$code]) ? number_format((float) $scores[$code], 2) : 'not measured'; }
            $lines[] = '| ' . $cell($chromosome['chromosome']) . ' | ' . number_format((int) $chromosome['matched_markers']) . ' | ' . number_format((int) $chromosome['differentiating_loci']) . ' | ' . implode(' | ', $values) . ' |';
        }
        $lines[] = '';
        $lines[] = 'Every cell in a chromosome row uses the same callable loci. Values are shared-allele affinity scores out of 100, not ancestry percentages. Unequal marker density and source-group coverage limit comparisons between chromosomes.';
        $lines[] = $cell($diag['shared_alleles_explainer'] ?? '');
        $lines[] = '';
    }
    $lines[] = '### Neanderthal and Denisovan source-tag observations';
    $lines[] = '';
    foreach ((array) ($archaic['categories'] ?? []) as $category) {
        $lines[] = '**' . $cell($category['label']) . '**';
        $lines[] = '';
        $lines[] = $cell($category['plain']);
        $lines[] = '';
        $lines[] = '- Carried source-tag loci: ' . number_format((int) $category['observed_tag_count']) . '; observed tag-allele copies: ' . number_format((int) ($category['observed_allele_copy_count'] ?? 0)) . '.';
        $lines[] = '- Callable source-tag loci: ' . number_format((int) $category['callable_tag_count']) . ' of ' . number_format((int) $category['panel_tag_count']) . ' catalogued loci.';
        if ($category['coverage_percent'] !== null) { $lines[] = '- Source-panel test coverage: ' . $category['coverage_percent'] . '% (callable loci / catalogued tags; not a percentage of archaic DNA).'; }
        foreach ((array) ($category['sources'] ?? []) as $source) {
            $lines[] = '- Source: [' . $cell($source['source_name'] ?? 'Published source') . '](' . str_replace(['(', ')', ' '], ['%28', '%29', '%20'], (string) ($source['source_url'] ?? '')) . ') — ' . $cell($source['source_version'] ?? '') . '.';
        }
        $lines[] = '';
    }
    $lines[] = $cell($archaic['boundary'] ?? 'Published source tags do not determine genome-wide archaic ancestry amounts.');
    $lines[] = '';
    return implode("\n", $lines);
}

function zl_dna_origins_catalog() {
    $out = ['schema_version' => 'origins-catalog-v1', 'ancient' => ['installed' => false, 'groups' => []], 'archaic' => ['installed' => false, 'groups' => []], 'boundary' => 'Reference catalog coverage describes installed source data, not which ancestry or source-tag alleles a person carries.'];
    if (!class_exists('SQLite3')) { return $out; }
    foreach (['ancient' => 'ancient_origins.sqlite', 'archaic' => 'archaic_markers.sqlite'] as $kind => $filename) {
        $path = dirname(__DIR__) . '/data/packs/' . $filename;
        if (!is_readable($path)) { continue; }
        $db = null;
        try {
            $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
            $meta = zl_dna_sqlite_meta($db);
            $public_meta = array_intersect_key($meta, array_flip(['pack_version', 'source_name', 'source_version', 'source_url', 'license', 'license_note', 'assembly', 'reference_build', 'created_at', 'built_at', 'marker_count', 'group_count', 'evidence_type', 'tag_count_by_group', 'ascertainment', 'source_attribution']));
            if ($kind === 'ancient' && zl_sqlite_table_exists($db, 'ancient_origin_groups')) {
                $result = $db->query('SELECT group_code,group_label,description,region,period_label,sample_count,marker_count,source_version,source_url,limitations FROM ancient_origin_groups ORDER BY group_code');
                $groups = [];
                while ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
                    $groups[] = ['code' => (string) $row['group_code'], 'label' => (string) $row['group_label'], 'description' => (string) $row['description'], 'region' => (string) $row['region'], 'era' => (string) $row['period_label'], 'sample_count' => (int) $row['sample_count'], 'marker_count' => (int) $row['marker_count'], 'source_version' => (string) $row['source_version'], 'source_url' => (string) $row['source_url'], 'limitations' => (string) $row['limitations']];
                }
                $out[$kind] = ['installed' => count($groups) > 0, 'group_count' => count($groups), 'groups' => $groups, 'metadata' => $public_meta];
            } elseif ($kind === 'archaic' && zl_sqlite_table_exists($db, 'archaic_markers')) {
                $counts = json_decode((string) ($meta['tag_count_by_group'] ?? '{}'), true);
                $groups = [];
                foreach (zlo_archaic_categories() as $code => $base) {
                    $count = (array) $counts;
                    $n = $count[$code] ?? $count[strtolower($code)] ?? null;
                    $groups[] = $base + ['marker_count' => is_numeric($n) ? (int) $n : null, 'count_basis' => 'source pack build metadata', 'source_version' => (string) ($meta['source_version'] ?? ''), 'source_url' => (string) ($meta['source_url'] ?? '')];
                }
                $out[$kind] = ['installed' => true, 'group_count' => count($groups), 'groups' => $groups, 'metadata' => $public_meta];
            }
            $db->close();
        } catch (Throwable $e) {
            if ($db) { $db->close(); }
            $out[$kind]['status'] = 'catalog_temporarily_unavailable';
        }
    }
    return $out;
}

function zl_dna_origins_agent_context($origins) {
    $out = ['version' => 'origins-agent-aggregate-v2', 'modern' => [], 'ancient' => [], 'archaic' => [], 'boundary' => (string) ($origins['boundary'] ?? 'Reference comparisons are not identity or ancestry percentages.')];
    if (!empty($origins['classification'])) { $out['ancestry_model'] = zl_dna_grafanc_agent_context($origins['classification']); }
    if (!empty($origins['modern']['regional_refs'])) { $out['regional_references'] = zl_dna_regional_refs_agent_context($origins['modern']['regional_refs']); }
    foreach (array_slice((array) ($origins['modern']['broad_regions'] ?? []), 0, 5) as $row) {
        $out['modern'][] = ['label' => (string) ($row['label'] ?? ''), 'affinity_score' => (float) ($row['affinity_score'] ?? 0), 'matched_markers' => (int) ($row['matched_markers'] ?? 0), 'chromosome_count' => (int) ($row['chromosome_count'] ?? 0), 'confidence_band' => (string) ($row['confidence_band'] ?? ''), 'source_version' => (string) ($row['source_version'] ?? '')];
    }
    foreach ((array) ($origins['ancient']['groups'] ?? []) as $row) {
        $out['ancient'][] = ['label' => (string) ($row['label'] ?? ''), 'similarity_score' => isset($row['similarity_score']) ? (float) $row['similarity_score'] : null, 'matched_markers' => (int) ($row['matched_markers'] ?? 0), 'sample_count' => (int) ($row['sample_count'] ?? 0), 'confidence_band' => (string) ($row['confidence_band'] ?? ''), 'source_version' => (string) ($row['source_version'] ?? ''),
            'relative_to_panel_mean' => isset($row['relative_to_panel_mean']) ? (float) $row['relative_to_panel_mean'] : null,
            'mean_chromosome_affinity_score' => isset($row['mean_chromosome_affinity_score']) ? (float) $row['mean_chromosome_affinity_score'] : null];
    }
    if (!empty($origins['ancient']['score_diagnostics'])) {
        $diag = (array) $origins['ancient']['score_diagnostics'];
        $stability = (array) ($origins['ancient']['rank_stability'] ?? []);
        $out['ancient_diagnostics'] = [];
        foreach (['common_marker_count', 'compared_allele_count', 'heterozygous_calls', 'homozygous_calls', 'reference_variable_loci', 'differentiating_loci'] as $key) { $out['ancient_diagnostics'][$key] = (int) ($diag[$key] ?? 0); }
        foreach (['interpretability', 'score_explainer', 'contrast_explainer', 'shared_alleles_explainer'] as $key) { $out['ancient_diagnostics'][$key] = (string) ($diag[$key] ?? ''); }
        $out['ancient_diagnostics']['chromosome_omission_check'] = ['status' => (string) ($stability['status'] ?? ''), 'plain' => (string) ($stability['plain'] ?? ''),
            'evaluated_omissions' => (int) ($stability['evaluated_omissions'] ?? 0), 'unique_lead_retained_count' => isset($stability['unique_lead_retained_count']) ? (int) $stability['unique_lead_retained_count'] : null,
            'method' => (string) ($stability['method'] ?? '')];
    }
    foreach ((array) ($origins['archaic']['categories'] ?? []) as $row) {
        $out['archaic'][] = ['label' => (string) ($row['label'] ?? ''), 'status' => (string) ($row['status'] ?? ''), 'observed_tag_count' => (int) ($row['observed_tag_count'] ?? 0), 'callable_tag_count' => (int) ($row['callable_tag_count'] ?? 0), 'panel_tag_count' => (int) ($row['panel_tag_count'] ?? 0), 'confidence_band' => (string) ($row['confidence_band'] ?? '')];
    }
    $out['privacy'] = ['raw_rows_included' => false, 'genotypes_or_rsids_included' => false, 'file_name_or_hash_included' => false];
    return $out;
}
