<?php
// Readable summaries of supplied reference results. This module does not infer percentages.
function zlas_population_labels() {
    $rows = [
        'ACB'=>['African Caribbean, Barbados','African Caribbean study participants in Barbados'],
        'ASW'=>['African American, southwest USA','African-ancestry study participants in the southwest USA'],
        'BEB'=>['Bengal, Bangladesh','Bengali study participants in Bangladesh'],
        'CDX'=>['Dai, southern China','Chinese Dai study participants in Xishuangbanna'],
        'CEU'=>['Northwestern Europe','Utah study participants with northern and western European ancestry'],
        'CHB'=>['Northern Han, China','Han Chinese study participants in Beijing'],
        'CHS'=>['Southern Han, China','Southern Han Chinese study participants'],
        'CLM'=>['Colombia','Colombian study participants in Medellin'],
        'ESN'=>['Esan, Nigeria','Esan study participants in Nigeria'],
        'FIN'=>['Finland','Finnish study participants in Finland'],
        'GBR'=>['Britain: England & Scotland','A combined British reference; it does not separate English and Scottish ancestry'],
        'GIH'=>['Gujarat, India','Gujarati Indian study participants sampled in Houston'],
        'GWD'=>['The Gambia','Gambian study participants in the Western Divisions'],
        'IBS'=>['Spain','Iberian study participants in Spain'],
        'ITU'=>['Telugu, India','Indian Telugu study participants sampled in the UK'],
        'JPT'=>['Japan','Japanese study participants in Tokyo'],
        'KHV'=>['Kinh, Vietnam','Kinh study participants in Ho Chi Minh City'],
        'LWK'=>['Luhya, Kenya','Luhya study participants in Webuye, Kenya'],
        'MSL'=>['Mende, Sierra Leone','Mende study participants in Sierra Leone'],
        'MXL'=>['Mexico','Mexican-ancestry study participants sampled in Los Angeles'],
        'PEL'=>['Peru','Peruvian study participants in Lima'],
        'PJL'=>['Punjab, Pakistan','Punjabi study participants sampled in Lahore, Pakistan'],
        'PUR'=>['Puerto Rico','Puerto Rican study participants in Puerto Rico'],
        'STU'=>['Tamil, Sri Lanka','Sri Lankan Tamil study participants sampled in the UK'],
        'TSI'=>['Tuscany, Italy','Toscani study participants in Italy'],
        'YRI'=>['Yoruba, Nigeria','Yoruba study participants in Ibadan, Nigeria'],
    ];
    $out = [];
    foreach ($rows as $code=>$row) {
        $out[$code] = ['label'=>$row[0], 'detail'=>$row[1], 'source_url'=>'https://www.internationalgenome.org/data-portal/population/' . $code];
    }
    return $out;
}

function zlas_regions() {
    return [
        ['code'=>'EUR','label'=>'Europe','plain'=>'Explore the available British, northwestern European, Finnish, Spanish and Italian study references.'],
        ['code'=>'SAS','label'=>'South Asia','plain'=>'Explore Punjab in Pakistan, Bengal in Bangladesh, Gujarati and Telugu Indian, and Sri Lankan Tamil study references.'],
        ['code'=>'EAS','label'=>'East Asia','plain'=>'Explore Chinese, Japanese and Vietnamese study references.'],
        ['code'=>'AFR','label'=>'Africa & African diaspora','plain'=>'Explore sampled West and East African groups and African-diaspora study references.'],
        ['code'=>'AMR','label'=>'The Americas','plain'=>'Explore Puerto Rican, Colombian, Mexican-ancestry and Peruvian study references. These are mixed-history populations, not a single Indigenous American component.'],
    ];
}

function zlas_requested_region_coverage($modern = null, $regional_catalog = null) {
    $rows = [
        ['pakistan','Pakistan / Pakistani','direct_reference','SAS',['PJL'],'Punjab in Pakistan has a direct Lahore study reference. This does not represent every community in Pakistan.'],
        ['britain','Britain / British','direct_reference','EUR',['GBR'],'The installed British group combines participants from England and Scotland.'],
        ['england','England / English','broader_context','EUR',['GBR'],'Compared within the combined Britain reference; a separate English percentage is not available.'],
        ['scotland','Scotland / Scottish','broader_context','EUR',['GBR'],'Compared within the combined Britain reference; a separate Scottish percentage is not available.'],
        ['ireland','Ireland / Irish','not_represented','EUR',[],'No separate Irish reference is installed. A British or northwestern European match cannot establish or rule out Irish ancestry.'],
        ['wales','Wales / Welsh','not_represented','EUR',[],'No separate Welsh reference is installed. Nearby British reference matches do not measure Welsh ancestry.'],
        ['ashkenazi','Ashkenazi Jewish ancestry','broader_context','EUR',[],'A separate gnomAD frequency-context table includes an Ashkenazi group. It is not a validated Ashkenazi ancestry model and cannot provide an ancestry percentage or determine religious identity.'],
        ['egypt','Egypt / Egyptian','not_represented','',[],'No modern Egyptian ancestry reference is installed. Ancient or nearby regional similarities do not substitute for it.'],
        ['levant','Israel / Levant','not_represented','',[],'No modern Levantine ancestry reference is installed. DNA comparisons cannot determine Israeli nationality.'],
        ['khazar','Khazar history','not_represented','',[],'No validated Khazar ancestry component is installed. Steppe, Caucasus or Ashkenazi similarities do not establish descent from the historical Khazar population.'],
    ];
    $regional_catalog = $regional_catalog ?? ($modern['regional_refs'] ?? (function_exists('zl_dna_regional_refs_catalog') ? zl_dna_regional_refs_catalog() : []));
    $regional_groups = [];
    if (!empty($regional_catalog['installed'])) {
        foreach ((array) ($regional_catalog['reference_groups'] ?? $regional_catalog['groups'] ?? []) as $group) { $regional_groups[(string) ($group['code'] ?? '')] = $group; }
    }
    foreach ($rows as &$row) {
        if ($row[0] === 'ashkenazi' && isset($regional_groups['ASH'])) {
            $row[2] = 'direct_reference'; $row[3] = ''; $row[4] = ['ASH'];
            $row[5] = 'The expanded panel includes a small published Ashkenazi Jewish cohort. It provides a direct shared-allele comparison, not an ancestry percentage or a determination of religious identity.';
        } elseif ($row[0] === 'egypt' && isset($regional_groups['EGY'])) {
            $row[2] = 'direct_reference'; $row[4] = ['EGY'];
            $row[5] = 'The expanded panel includes published modern Egyptian study participants. Compare this cohort with the other groups on the same markers; it does not represent every Egyptian community.';
        } elseif ($row[0] === 'levant') {
            $codes = array_values(array_intersect(['PAL','DRU','BDA','BDB'], array_keys($regional_groups)));
            if ($codes) {
                $row[2] = 'broader_context'; $row[4] = $codes;
                $row[5] = 'Published Palestinian, Druze and Bedouin study groups provide regional Levantine context. These distinct research cohorts are not a single Israeli ancestry component and cannot establish nationality.';
            }
        }
    }
    unset($row);
    $observed = [];
    foreach ((array) ($modern['study_populations'] ?? []) as $population) { $observed[(string) ($population['code'] ?? '')] = (int) ($population['matched_markers'] ?? 0); }
    foreach ((array) ($regional_catalog['groups'] ?? []) as $group) { $observed[(string) ($group['code'] ?? '')] = (int) ($group['matched_markers'] ?? 0); }
    $out = [];
    foreach ($rows as $row) {
        $entry = ['code'=>$row[0], 'label'=>$row[1], 'status'=>$row[2], 'region_code'=>$row[3], 'reference_codes'=>$row[4], 'plain'=>$row[5], 'is_personal_finding'=>false];
        if ($modern !== null && $row[4]) {
            $entry['usable_markers'] = max(array_map(function ($code) use ($observed) { return $observed[$code] ?? 0; }, $row[4]));
            $entry['file_comparison_available'] = $entry['usable_markers'] > 0;
            if (!$entry['file_comparison_available']) { $entry['file_note'] = 'The current file did not return a usable comparison for this reference. This is not a zero-ancestry result.'; }
        }
        $out[] = $entry;
    }
    return $out;
}

function zlas_consumer_summary($modern) {
    $labels = zlas_population_labels();
    $regions = zlas_regions();
    $region_names = array_column($regions, 'label', 'code');
    $lead = $modern['closest_region'] ?? null;
    $population = $modern['closest_population'] ?? null;
    $tied = !empty($modern['comparison_tied']);
    $enough = ($modern['status'] ?? '') === 'reference_findings_available';
    $headline = 'Your ancestry overview';
    $plain = 'Run a DNA report to explore the regions represented in the reference library.';
    if (!empty($modern['study_populations'])) {
        if ($tied) {
            $names = [];
            foreach ((array) ($modern['tied_top_regions'] ?? []) as $row) { $names[] = $region_names[$row['code']] ?? $row['label']; }
            $headline = 'Your closest regional matches overlap';
            $plain = $names ? 'Your leading comparisons are ' . implode(', ', $names) . '. Explore the groups below to see the regional detail.' : 'Several reference groups are similarly close to your DNA. Explore them together.';
        } elseif ($lead && $enough) {
            $label = $region_names[$lead['code']] ?? $lead['label'];
            $headline = 'Your closest regional match: ' . $label;
            $plain = $population ? 'The closest individual study-group comparison is ' . ($labels[$population['code']]['label'] ?? $population['label']) . '. Select a region to explore its other available matches.' : 'Select a region to explore the study groups that best match your file.';
        } else {
            $headline = 'Some regional clues; more coverage needed';
            $plain = 'Your file overlaps the reference library, but too few markers are available for a clear regional answer. You can still inspect the comparisons below.';
        }
    } elseif (($modern['status'] ?? '') !== 'needs_file_validation') {
        $headline = 'This file has no usable regional comparison';
        $plain = 'Check the file format and alignment details below. Missing comparison data does not mean missing ancestry.';
    } else {
        $plain = 'Confirm the original file and its genome build or strand settings to obtain a regional comparison. The file checks below explain what is needed.';
    }
    return ['headline'=>$headline, 'plain'=>$plain, 'leading_region_code'=>$enough && !$tied ? ($lead['code'] ?? null) : null,
        'leading_population_code'=>$enough && empty($modern['population_comparison_tied']) ? ($population['code'] ?? null) : null,
        'comparison_tied'=>$tied, 'regions'=>$regions, 'population_labels'=>$labels,
        'requested_region_coverage'=>zlas_requested_region_coverage($modern),
        'meaning'=>'Reference matches describe similarity to sampled groups. They are separate from an estimated ancestry composition and do not assign nationality or identity.'];
}

function zlas_story_markdown($modern) {
    $story = (array) ($modern['consumer_summary'] ?? []);
    if (!$story) { return ''; }
    $lines = ['### Your ancestry overview', '', '**' . $story['headline'] . '**', '', $story['plain'], '', $story['meaning'], ''];
    return implode("\n", $lines);
}
