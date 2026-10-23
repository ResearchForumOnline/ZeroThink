<?php
// A deterministic reading guide for completed report evidence. No new genetic inference.
function zlc_finding($id, $title, $result, $meaning, $status, $chapter, $action) {
    return ['id'=>$id, 'title'=>$title, 'result'=>$result, 'meaning'=>$meaning, 'status'=>$status, 'action'=>['chapter'=>$chapter, 'label'=>$action]];
}

function zl_dna_customer_summary($report) {
    $report = (array) $report;
    $origins = (array) ($report['origins'] ?? []);
    $modern = (array) ($origins['modern'] ?? []);
    $classification = (array) ($origins['classification'] ?? []);
    $composition = (array) ($origins['composition'] ?? []);
    $story = (array) ($modern['consumer_summary'] ?? []);
    $ancient = (array) ($origins['ancient'] ?? []);
    $archaic = (array) ($origins['archaic'] ?? []);
    $traits = (array) ($report['personal_traits'] ?? []);
    $sound = (array) ($report['sound_profile'] ?? []);
    $country = (array) ($report['country_context'] ?? []);
    $findings = [];
    $next = [];
    if (($composition['available'] ?? false) === true && !empty($composition['components'])) {
        $findings[] = zlc_finding('ancestry', 'Your ancestry estimate', (string) ($composition['headline'] ?? 'Your ancestry proportions are ready'),
            (string) ($composition['plain'] ?? 'Estimated shares of the reference groups represented by this research model. Nearby groups and small components can be uncertain.'), 'ready', 'origins', 'Explore your ancestry percentages');
    } elseif (($classification['available'] ?? false) === true && !empty($classification['broad']['label'])) {
        $label = (string) $classification['broad']['label'];
        $regional = (string) ($classification['regional']['label'] ?? '');
        $country_ready = ($country['available'] ?? false) === true && !empty($country['model_headline']);
        $findings[] = zlc_finding('ancestry', 'Your modern-day origins', $country_ready ? (string) $country['model_headline'] : ($regional !== '' ? $label . ' · ' . $regional : $label),
            (string) ($country_ready ? $country['plain'] : ($classification['plain'] ?? 'This is the population-group estimate returned by the published ancestry model for your file.')), 'ready', 'origins', 'Explore places and ancestry');
    } elseif (!empty($modern['study_populations'])) {
        $status = ($modern['status'] ?? '') === 'reference_findings_available' ? 'ready' : 'limited';
        $findings[] = zlc_finding('ancestry', 'Your regional comparisons', (string) ($story['headline'] ?? 'Regional references compared'),
            (string) ($story['plain'] ?? 'Explore the regions and study groups represented in your file.') . ' These comparisons are not an ethnicity-percentage estimate.', $status, 'origins', 'Explore your regional results');
        if (!empty($classification) && empty($classification['available'])) {
            $next[] = ['label'=>'Ancestry model coverage', 'detail'=>(string) ($classification['plain'] ?? 'This file does not yet support a population-group estimate.'), 'chapter'=>'origins'];
        }
    } else {
        $findings[] = zlc_finding('ancestry', 'Your ancestry picture', 'Your file needs a closer check',
            'The report has not produced a reliable regional result from this file. Open the file checks for the exact reason and the next step.', 'needs_input', 'quality', 'See what this file needs');
        $next[] = ['label'=>'Check the original export', 'detail'=>'Use the original uncompressed genotype export. Confirm a genome build or strand only when your testing provider documents it.', 'chapter'=>'quality'];
    }

    if (!empty($ancient['groups']) && (int) ($ancient['common_marker_count'] ?? 0) > 0) {
        $groups = (array) $ancient['groups'];
        $tied = !empty($ancient['comparison_tied']);
        $lead = $ancient['closest_group'] ?? null;
        $result = $tied || !$lead ? 'Explore the ancient communities in your report' : 'Closest comparison: ' . preg_replace('/\s+references$/i', '', (string) ($lead['label'] ?? 'ancient reference group'));
        $ancient_places = array_column((array) ($country['ancient'] ?? []), null, 'code');
        $place = $ancient_places[(string) ($lead['code'] ?? '')] ?? null;
        if (!$tied && $lead && $place && !empty($place['comparison_available'])) { $result = 'Closest ancient comparison: ' . (string) $place['label']; }
        $findings[] = zlc_finding('ancient', 'Your ancient connections', $result,
            'Explore ancient people by where their remains were found in today\'s countries. Hunter-gatherers lived mainly by hunting and gathering wild food; early farmers grew crops; steppe herders kept livestock on open grasslands. These comparisons do not identify a named ancestor or an inherited percentage.',
            (int) $ancient['common_marker_count'] >= 1000 ? 'ready' : 'limited', 'ancient', 'Explore the ancient world');
    } else {
        $findings[] = zlc_finding('ancient', 'Your ancient connections', 'Ancient comparisons need more usable data',
            'No usable ancient comparison was returned. You can still explore the historical guide, which is clearly separated from your personal results.', 'needs_input', 'ancient', 'Open ancient results and guide');
    }

    $carried = []; $tested = 0;
    foreach ((array) ($archaic['categories'] ?? []) as $row) {
        $callable = (int) ($row['callable_tag_count'] ?? 0);
        if ($callable <= 0) { continue; }
        $source_label = (string) ($row['code'] ?? $row['label'] ?? '');
        if (stripos($source_label, 'Denisov') !== false) { $label = 'Denisovan'; }
        elseif (stripos($source_label, 'Neanderthal') !== false) { $label = 'Neanderthal'; }
        else { continue; }
        $n = (int) ($row['observed_tag_count'] ?? 0);
        if ($n < 0 || $n > $callable) { continue; }
        $tested += $callable;
        if ($n > 0) { $carried[] = number_format($n) . ' ' . $label . '-associated markers'; }
    }
    $findings[] = zlc_finding('archaic', 'Neanderthal & Denisovan clues',
        $carried ? implode(' and ', $carried) . ' found' : ($tested > 0 ? 'No associated archaic variants found at the sites checked' : 'This file cannot answer the archaic-marker question yet'),
        $tested > 0 ? 'The report checks published research markers that your DNA test measured. Open the results to see what was found and how much of the reference set could be checked. This is not a percentage of your whole DNA.' : 'Missing marker coverage is not zero archaic ancestry. The research guide remains available.',
        $tested > 0 ? 'ready' : 'needs_input', 'archaic', 'See your archaic clues');

    $observed = array_values(array_filter((array) ($traits['cards'] ?? []), function ($card) { return in_array((string) ($card['status'] ?? ''), ['observed','ambiguous'], true); }));
    $total = count((array) ($traits['cards'] ?? []));
    if ($total) {
        $titles = array_map(function ($card) { return (string) ($card['title'] ?? 'trait'); }, $observed);
        $needs_validation = count(array_filter((array) $traits['cards'], function ($card) { return ($card['status'] ?? '') === 'needs_validation'; })) > 0;
        $findings[] = zlc_finding('traits', 'Everyday traits', $observed ? count($observed) . ' everyday questions have usable evidence' : ($needs_validation ? 'Your trait calls need a closer check' : 'Your file is missing the markers for these traits'),
            $observed ? 'Explore ' . implode(', ', $titles) . '. Each card explains the observed pattern, what it may mean and what your DNA alone cannot tell you.' : ($needs_validation ? 'A file or marker check did not pass. Open the trait cards to see what needs validation before a result can be interpreted.' : 'An untested marker is not a negative result. Each card explains which data is missing.'),
            $observed ? 'ready' : 'needs_input', 'traits', 'Read your trait stories');
    }

    if (!empty($sound['events']) && (float) ($sound['duration_seconds'] ?? 0) > 0) {
        $findings[] = zlc_finding('sound', 'Your DNA, made into music', 'Your ' . (int) round($sound['duration_seconds']) . '-second composition is ready',
            'Play the composition generated from your file, watch its waveform, and save a WAV. You can also pair it with your animated artwork.', 'ready', 'sound', 'Play your DNA sound');
    }
    $next[] = ['label'=>'Keep a readable copy', 'detail'=>'Download the illustrated report or use Print / save PDF. Your report includes the same findings and their source notes.', 'chapter'=>'export'];
    $next[] = ['label'=>'Connect DNA with your family story', 'detail'=>'A regional result is a starting point. Compare it with documented birthplaces and family records; the optional family-history tools keep those records separate from genetic findings.', 'chapter'=>'lineage'];
    return ['version'=>'dna-customer-summary-v1', 'headline'=>'Your DNA report, explained',
        'intro'=>'Start with the findings below, then open the chapters that interest you. Each chapter explains what your file supports in everyday language.',
        'findings'=>$findings, 'next_steps'=>$next,
        'glossary'=>[
            ['term'=>'Reference group','meaning'=>'People whose published DNA data provides a comparison. A reference label describes that study group.'],
            ['term'=>'Marker','meaning'=>'A specific position in DNA that your test may have measured. Different test versions measure different positions.'],
            ['term'=>'Ancestry estimate','meaning'=>'A model-based comparison with reference populations. Its coverage and resolution depend on the model and the markers in your file.'],
            ['term'=>'Missing result','meaning'=>'The report lacks the data needed to answer that question. It does not mean the ancestry or trait is absent.'],
        ], 'privacy'=>['raw_calls_included'=>false,'file_identity_included'=>false],
    ];
}

function zl_dna_customer_markdown($summary) {
    if (!$summary) { return ''; }
    $lines = ['## ' . (string) ($summary['headline'] ?? 'Your DNA report, explained'), '', (string) ($summary['intro'] ?? ''), ''];
    foreach ((array) ($summary['findings'] ?? []) as $card) {
        $lines[] = '### ' . (string) $card['title']; $lines[] = '';
        $lines[] = '**' . (string) $card['result'] . '**'; $lines[] = '';
        $lines[] = (string) $card['meaning']; $lines[] = '';
    }
    return implode("\n", $lines);
}
