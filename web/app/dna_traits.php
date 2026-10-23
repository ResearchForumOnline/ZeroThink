<?php
// Published pigmentation-locus evidence only. This module does not predict appearance.
// Inputs stay in memory. Only one explicitly sourced allele count is returned; no raw rows.

function zlt_eye_marker_catalog() {
    // NCBI RefSNP GRCh37.p13 / GRCh38.p14 forward SPDI placements, verified 2026-09-05.
    // SPDI zero-based positions were converted to the one-based coordinates below.
    // The allele pairs are the published six-locus research alleles, not all rare dbSNP alleles.
    return [
        'rs12913832' => ['gene' => 'HERC2 / OCA2 region', 'chromosome' => '15', 'positions' => ['GRCh37' => 28365618, 'GRCh38' => 28120472], 'ref' => 'A', 'alt' => 'G'],
        'rs1800407' => ['gene' => 'OCA2', 'chromosome' => '15', 'positions' => ['GRCh37' => 28230318, 'GRCh38' => 27985172], 'ref' => 'C', 'alt' => 'T'],
        'rs12896399' => ['gene' => 'LOC105370627 / SLC24A4 region', 'chromosome' => '14', 'positions' => ['GRCh37' => 92773663, 'GRCh38' => 92307319], 'ref' => 'G', 'alt' => 'T'],
        'rs16891982' => ['gene' => 'SLC45A2', 'chromosome' => '5', 'positions' => ['GRCh37' => 33951693, 'GRCh38' => 33951588], 'ref' => 'C', 'alt' => 'G'],
        'rs1393350' => ['gene' => 'TYR', 'chromosome' => '11', 'positions' => ['GRCh37' => 89011046, 'GRCh38' => 89277878], 'ref' => 'G', 'alt' => 'A'],
        'rs12203592' => ['gene' => 'IRF4', 'chromosome' => '6', 'positions' => ['GRCh37' => 396321, 'GRCh38' => 396321], 'ref' => 'C', 'alt' => 'T'],
    ];
}

function zlt_file_gate($summary) {
    $summary = (array) $summary;
    $build = strtolower(trim((string) ($summary['genome_build_guess'] ?? '')));
    $is37 = preg_match('/\b(?:grch37|hg19)\b/', $build) === 1;
    $is38 = preg_match('/\b(?:grch38|hg38)\b/', $build) === 1;
    $assembly = $is37 !== $is38 ? ($is37 ? 'GRCh37' : 'GRCh38') : '';
    $reasons = [];
    if ($assembly === '' || strtolower((string) ($summary['genome_build_confidence'] ?? '')) !== 'high' || !empty($summary['genome_build_conflict'])) { $reasons[] = 'genome_build_not_confirmed'; }
    $orientation = strtolower((string) ($summary['reported_orientation'] ?? ''));
    $allowed = ['reference_forward_from_vcf', 'reference_forward_confirmed_by_user', 'reference_forward_from_verified_vendor_header_contract'];
    if (strtolower((string) ($summary['orientation_confidence'] ?? '')) !== 'high' || !in_array($orientation, $allowed, true)) { $reasons[] = 'forward_orientation_not_confirmed'; }
    if ((float) ($summary['quality_score'] ?? 0) < 45) { $reasons[] = 'file_quality_below_threshold'; }
    if (!empty($summary['truncated'])) { $reasons[] = 'parser_limit_reached'; }
    if ((int) ($summary['called_variants'] ?? 0) <= 0) { $reasons[] = 'no_callable_variants'; }
    return ['ready' => !$reasons, 'assembly' => $assembly, 'reasons' => $reasons, 'imputation_performed' => false, 'strand_flip_performed' => false];
}

function zlt_check_marker_record($record, $rsid, $marker, $assembly) {
    if (!is_array($record)) { return ['status' => 'coordinate_not_verified']; }
    if (!empty($record['source_identity_conflict'])) { return ['status' => 'identifier_mismatch']; }
    if (!empty($record['source_alignment_conflict'])) { return ['status' => 'vcf_alleles_not_aligned']; }
    if (!empty($record['duplicate_conflict'])) { return ['status' => 'conflicting_records']; }
    $given_id = strtolower(trim((string) ($record['rsid'] ?? '')));
    if (preg_match('/^rs\d+$/', $given_id) && $given_id !== $rsid) { return ['status' => 'identifier_mismatch']; }
    $chrom = preg_replace('/^chr/i', '', trim((string) ($record['chromosome'] ?? $record['chrom'] ?? '')));
    $position = trim((string) ($record['position'] ?? $record['pos'] ?? ''));
    if ($chrom !== $marker['chromosome'] || !ctype_digit($position) || (int) $position !== $marker['positions'][$assembly]) { return ['status' => 'coordinate_mismatch']; }
    $gt = strtoupper(trim((string) ($record['genotype'] ?? '')));
    if (!preg_match('/^(?:[ACGT]{2}|[ACGT][\/|][ACGT])$/', $gt)) { return ['status' => 'not_callable']; }
    $gt = str_replace(['/', '|'], '', $gt);
    if (array_diff(str_split($gt), [$marker['ref'], $marker['alt']])) { return ['status' => 'outside_studied_alleles']; }
    if (($record['source_record_format'] ?? '') === 'vcf') {
        $ref = strtoupper(trim((string) ($record['reference_allele'] ?? '')));
        $alt = array_map('trim', explode(',', strtoupper((string) ($record['alternate_alleles'] ?? ''))));
        if ($ref !== $marker['ref'] || !$alt || array_diff($alt, [$marker['alt']])) { return ['status' => 'vcf_alleles_not_aligned']; }
    }
    $letters = str_split($gt); sort($letters);
    // Canonical call is INTERNAL ONLY, used to resolve duplicates; never placed in the report.
    return ['status' => 'observed', 'internal_call' => implode('', $letters)];
}

function zlt_marker_status_label($status) {
    $labels = [
        'observed' => 'Aligned marker observed', 'not_observed' => 'Not observed in this file',
        'not_callable' => 'No complete diploid call', 'conflicting_records' => 'Conflicting records withheld',
        'coordinate_not_verified' => 'Coordinate evidence missing', 'coordinate_mismatch' => 'Coordinate does not match this genome build',
        'identifier_mismatch' => 'Marker identity does not match', 'outside_studied_alleles' => 'Call falls outside the studied allele pair',
        'vcf_alleles_not_aligned' => 'VCF alleles do not match the reference', 'withheld_pending_validation' => 'Waiting for file validation',
    ];
    return $labels[$status] ?? 'Evidence unavailable';
}

function zlt_appearance_evidence($variant_map, $summary = []) {
    $catalog = zlt_eye_marker_catalog();
    $gate = zlt_file_gate($summary);
    $assembly = $gate['assembly'];
    $records = array_fill_keys(array_keys($catalog), []);
    if ($gate['ready']) {
        $variants = (array) ($variant_map['variants'] ?? $variant_map);
        $coordinate_index = [];
        foreach ($catalog as $rsid => $marker) { $coordinate_index[$marker['chromosome'] . ':' . $marker['positions'][$assembly]] = $rsid; }
        foreach ($variants as $key => $record) {
            $id = strtolower(is_array($record) ? (string) ($record['rsid'] ?? $key) : (string) $key);
            $targets = isset($catalog[$id]) ? [$id] : [];
            if (is_array($record)) {
                $chr = preg_replace('/^chr/i', '', trim((string) ($record['chromosome'] ?? $record['chrom'] ?? '')));
                $pos = trim((string) ($record['position'] ?? $record['pos'] ?? ''));
                if (ctype_digit($pos)) {
                    $coord = $chr . ':' . (int) $pos;
                    if (isset($coordinate_index[$coord])) { $targets[] = $coordinate_index[$coord]; }
                }
            }
            foreach (array_unique($targets) as $target) {
                $records[$target][] = zlt_check_marker_record($record, $target, $catalog[$target], $assembly);
            }
        }
    }
    $markers = []; $observed = 0; $research_context = null;
    foreach ($catalog as $rsid => $marker) {
        $status = !$gate['ready'] ? 'withheld_pending_validation' : 'not_observed';
        $items = $records[$rsid];
        if ($items) {
            $status = $items[0]['status'];
            // Any incompatible duplicate prevents row order from choosing a plausible result.
            foreach ($items as $item) {
                if ($item['status'] !== $items[0]['status'] || ($item['internal_call'] ?? '') !== ($items[0]['internal_call'] ?? '')) { $status = 'conflicting_records'; }
            }
        }
        if ($status === 'observed') { $observed++; }
        if ($rsid === 'rs12913832' && $status === 'observed') {
            $copies = substr_count($items[0]['internal_call'], 'G');
            $research_context = [
                'type' => 'single_locus_research_association', 'rsid' => 'rs12913832', 'gene' => 'HERC2 / OCA2 region',
                'observed_allele' => 'G', 'observed_allele_copies' => $copies,
                'observation' => $copies . ' of 2 copies at this checked locus carry the research-associated G allele.',
                'summary' => 'Published research links this allele with reduced OCA2 expression and blue-eye pigmentation. This observation alone does not determine your eye colour.',
                'population_context' => 'Much of this research concerns European study participants. The cited study includes people with two G copies and brown eyes, demonstrating exceptions.',
                'limitation' => 'Zero copies does not rule out blue eyes, and one or two copies does not establish them. Other loci and individual variation matter; no dominance rule or probability is applied.',
                'source_name' => 'Meyer et al., PLOS ONE (2020)', 'source_url' => 'https://doi.org/10.1371/journal.pone.0239131',
                'coordinate_source_url' => 'https://www.ncbi.nlm.nih.gov/snp/rs12913832',
                'genome_build' => $assembly, 'strand' => 'forward', 'prediction_available' => false,
            ];
        }
        $markers[] = [
            'rsid' => $rsid, 'gene' => $marker['gene'], 'chromosome' => $marker['chromosome'],
            'position' => $assembly !== '' ? $marker['positions'][$assembly] : null, 'build' => $assembly !== '' ? $assembly : null,
            'status' => $status, 'observed' => $status === 'observed', 'label' => zlt_marker_status_label($status),
            'context' => 'A locus examined in published eye-pigmentation research. Its observation alone does not establish eye colour.',
            'source_url' => 'https://www.ncbi.nlm.nih.gov/snp/' . $rsid,
            'research_url' => 'https://doi.org/10.3325/cmj.2013.54.381',
        ];
    }
    $limitations = [
        'This reports observed research loci, not a prediction of eye colour or a reconstruction of your appearance.',
        'Eye pigmentation is complex; six observed loci do not establish a particular colour, and missing loci are not evidence of a trait being absent.',
        'No reusable, independently checked prediction model is installed. No probabilities, facial geometry, age, sex, ethnicity or identity are inferred.',
    ];
    $plain = $gate['ready']
        ? $observed . ' of 6 published eye-pigmentation loci have an aligned, complete call. The portrait remains a neutral illustration; these observations do not determine eye colour.'
        : 'Confirm the genome build and forward orientation and meet the file quality checks to inspect pigmentation-marker coverage. The portrait remains a neutral illustration.';
    return [
        'schema_version' => 1, 'status' => !$gate['ready'] ? 'needs_file_validation' : ($observed ? 'marker_evidence_available' : 'no_marker_evidence'),
        'headline' => $gate['ready'] ? 'Eye-pigmentation research markers: ' . $observed . ' of 6 observed' : 'Pigmentation evidence needs file validation',
        'plain' => $plain, 'analysis_gate' => $gate,
        'eye_color' => [
            'status' => 'model_not_installed', 'prediction_available' => false, 'model_validated' => false,
            'category' => null, 'color' => null, 'probabilities' => null,
            'observed_marker_count' => $observed, 'required_marker_count' => 6, 'complete_marker_coverage' => $observed === 6,
            'markers' => $markers, 'research_context' => $research_context, 'source_name' => 'Published six-locus eye-pigmentation research; NCBI RefSNP coordinate evidence',
            'source_version' => 'Pigmentation locus evidence v1 / NCBI RefSNP verified 2026-09-05',
            'source_url' => 'https://doi.org/10.3325/cmj.2013.54.381', 'limitations' => $limitations,
        ],
        'rendering' => ['mode' => 'neutral_illustration', 'dna_driven_color' => false, 'face_geometry_inferred' => false, 'description' => 'An artistic code-generated illustration, not a genetic reconstruction or identification.'],
        'privacy' => ['raw_genotypes_in_output' => false, 'selective_allele_evidence_in_output' => $research_context !== null, 'external_requests_performed' => false, 'storage_performed' => false],
    ];
}

function zlt_appearance_agent_context($appearance) {
    // Whitelist aggregates; exclude marker identifiers, coordinates, calls and input metadata.
    $eye = (array) ($appearance['eye_color'] ?? []);
    return [
        'type' => 'pigmentation_locus_coverage_only', 'observed_loci' => max(0, min(6, (int) ($eye['observed_marker_count'] ?? 0))),
        'catalog_loci' => 6, 'prediction_available' => false, 'portrait_is_genetic_reconstruction' => false,
        'boundary' => 'Explain research-locus coverage only. Do not infer eye, hair or skin colour, facial features, age, sex, ethnicity or identity.',
    ];
}

function zlt_appearance_markdown($appearance) {
    $count = max(0, min(6, (int) ($appearance['eye_color']['observed_marker_count'] ?? 0)));
    $text = "\n### Appearance research evidence\n\n" . $count . " of 6 eye-pigmentation research loci have aligned complete calls. No eye-colour model prediction is produced. The portrait is an artistic illustration, not a genetic reconstruction.\n\nSource: [published six-locus study](https://doi.org/10.3325/cmj.2013.54.381); coordinates checked against NCBI RefSNP on 2026-09-05. Missing markers do not establish absence of a trait.\n";
    $context = (array) ($appearance['eye_color']['research_context'] ?? []);
    $copies = $context['observed_allele_copies'] ?? null;
    if (($context['type'] ?? '') === 'single_locus_research_association' && is_int($copies) && $copies >= 0 && $copies <= 2) {
        $text .= "\nAt the aligned HERC2 rs12913832 locus, " . $copies . " of 2 copies carry the research-associated G allele. Published work links this allele with blue-eye pigmentation, but also documents people with two G copies and brown eyes. This count neither establishes nor rules out an eye colour. [Meyer et al. (2020)](https://doi.org/10.1371/journal.pone.0239131).\n";
    }
    return $text;
}

function zl_dna_traits_report_path($path, $name = 'raw-dna.txt', $summary = []) {
    if (!zlt_file_gate($summary)['ready']) { return zlt_appearance_evidence([], $summary); }
    if (!function_exists('zl_dna_raw_variant_map') || !is_file($path) || !is_readable($path)) {
        $result = zlt_appearance_evidence([], []);
        $result['status'] = 'file_unavailable'; $result['headline'] = 'Pigmentation evidence could not be inspected';
        $result['plain'] = 'The local genotype parser or file was unavailable. No appearance prediction was made.';
        return $result;
    }
    $handle = fopen($path, 'rb');
    if (!$handle) { return zlt_appearance_evidence([], []); }
    $catalog = zlt_eye_marker_catalog(); $assembly = zlt_file_gate($summary)['assembly']; $coordinate_index = [];
    foreach ($catalog as $id => $marker) { $coordinate_index[$marker['chromosome'] . ':' . $marker['positions'][$assembly]] = $id; }
    $wanted = '/(?<![a-z0-9])(?:' . implode('|', array_keys($catalog)) . ')(?![a-z0-9])/i';
    $selected = []; $data_lines = 0; $bytes = 0; $truncated = false; $vcf_mode = false; $quarantined = []; $identity_quarantined = []; $header = [];
    // Keep only source format headers and target rows in memory. Duplicate target rows remain visible.
    while (($line = fgets($handle)) !== false) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '##') === 0) { continue; }
        if (strpos($trim, '#') === 0) {
            if (stripos($trim, '#CHROM') === 0) { $selected[] = $trim; $vcf_mode = true; }
            continue;
        }
        if (!preg_match('/^rs\d+/i', $trim) && preg_match('/\b(?:rsid|snp_name|chromosome|genotype|allele1|allele2)\b/i', $trim)) {
            $selected[] = $trim; $header = zl_dna_raw_header_map(zl_dna_raw_split_row($trim)); continue;
        }
        if (++$data_lines > 1200000) { $truncated = true; break; }
        $include = preg_match($wanted, $trim) === 1;
        if ($vcf_mode) {
            $cols = explode("\t", $trim);
            $chr = preg_replace('/^chr/i', '', trim((string) ($cols[0] ?? '')));
            $pos = trim((string) ($cols[1] ?? ''));
            $target = ctype_digit($pos) ? ($coordinate_index[$chr . ':' . (int) $pos] ?? null) : null;
            if ($target !== null) {
                $include = true;
                $id = trim((string) ($cols[2] ?? ''));
                if ($id === '.' || $id === '') {
                    if (($cols[3] ?? '') === $catalog[$target]['ref'] && ($cols[4] ?? '') === $catalog[$target]['alt']) {
                        // Annotation of an exact known locus, not genotype imputation or strand inference.
                        $cols[2] = $target; $trim = implode("\t", $cols);
                    } else { $quarantined[$target] = true; }
                } elseif (!preg_match('/^rs\d+$/i', $id)) { $identity_quarantined[$target] = true; }
                // A conflicting supplied rsID is kept intact for the core identity check.
            }
        } else {
            // Preserve aliases at known coordinates in consumer files too; a conflicting ID
            // must not disappear merely because it is outside the six-ID catalog.
            $cols = zl_dna_raw_split_row($trim);
            $chr = preg_replace('/^chr/i', '', trim(zl_dna_raw_pick($cols, $header, ['chromosome', 'chrom', 'chr'], 1)));
            $pos = trim(zl_dna_raw_pick($cols, $header, ['position', 'pos'], 2));
            if (ctype_digit($pos) && isset($coordinate_index[$chr . ':' . (int) $pos])) {
                $include = true;
                $source_id = trim(zl_dna_raw_pick($cols, $header, ['rsid', 'snp', 'snp_name', 'id'], 0));
                if (!preg_match('/^rs\d+$/i', $source_id)) { $identity_quarantined[$coordinate_index[$chr . ':' . (int) $pos]] = true; }
            }
        }
        if (!$include) { continue; }
        $bytes += strlen($trim);
        if ($bytes > 2000000 || count($selected) > 1000) { $truncated = true; break; }
        $selected[] = $trim;
    }
    fclose($handle);
    if ($truncated) { $summary['truncated'] = true; return zlt_appearance_evidence([], $summary); }
    $map = zl_dna_raw_variant_map(implode("\n", $selected), 'pigmentation-local-subset.txt', 1200000);
    foreach ($quarantined as $id => $_) {
        $map['variants']['alignment-conflict-' . $id] = ['rsid' => $id, 'source_alignment_conflict' => true];
    }
    foreach ($identity_quarantined as $id => $_) {
        $map['variants']['identity-conflict-' . $id] = ['rsid' => $id, 'source_identity_conflict' => true];
    }
    if (!empty($map['truncated'])) { $summary['truncated'] = true; }
    return zlt_appearance_evidence($map, $summary);
}

function zl_dna_traits_markdown($appearance) { return zlt_appearance_markdown((array) ($appearance['appearance'] ?? $appearance)); }
function zl_dna_traits_agent_context($appearance) { return zlt_appearance_agent_context((array) ($appearance['appearance'] ?? $appearance)); }
function zl_dna_traits_catalog() {
    return [
        'status' => 'research_locus_catalog', 'name' => 'Eye-pigmentation research markers', 'marker_count' => 6,
        'prediction_model_installed' => false, 'supported_builds' => ['GRCh37', 'GRCh38'], 'orientation' => 'verified forward only',
        'source_url' => 'https://doi.org/10.3325/cmj.2013.54.381',
        'source_version' => 'Pigmentation locus evidence v1 / NCBI RefSNP verified 2026-09-05',
        'plain' => 'Six published pigmentation loci can be checked for directly observed evidence. A phenotype prediction model is not installed.',
    ];
}
