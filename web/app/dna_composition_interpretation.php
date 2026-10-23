<?php
// Interpret an already validated model result. This module never fits or changes weights.
function zci_component_status($percentage) {
    if ((!is_int($percentage) && !is_float($percentage)) || !is_finite((float) $percentage) || $percentage < 0 || $percentage > 100) { return 'invalid'; }
    if ($percentage == 0) { return 'not_assigned'; }
    if ($percentage <= 0.000001) { return 'numerical_residue'; }
    return $percentage < 5 ? 'small_model_component' : 'model_component';
}

function zci_component_language($status) {
    $text = [
        'not_assigned' => ['No share assigned by this model', 'This fit assigns no share to this reference group. A zero model weight does not rule out ancestry connected with that group, especially a small contribution.'],
        'numerical_residue' => ['Effectively zero in this calculation', 'The positive weight is no more than one millionth of a percentage point. Treat it as numerical residue, not a detected ancestry contribution. An effectively zero model weight does not rule out ancestry connected with this group.'],
        'small_model_component' => ['Small share assigned by this model', 'The model assigns a small share to this reference group. Small weights can change with marker coverage and reference choice; this is not a confirmed small ancestry contribution or a specific ancestor.'],
        'model_component' => ['Share assigned by this model', 'The model assigns a share to this reference group. This remains a reference-model estimate, not a verified country percentage or evidence of nationality, religion or community membership.'],
    ];
    return $text[$status] ?? ['Interpretation unavailable', 'The model value did not pass the output checks.'];
}

function zl_dna_composition_interpretation($report) {
    $out = [
        'version' => 'composition-interpretation-v1', 'available' => false, 'status' => 'model_unavailable', 'components' => [],
        'plain' => 'A model estimate must be available before its individual weights can be interpreted.',
        'boundary' => 'A zero or effectively zero model weight does not establish absence of ancestry. A positive weight does not independently confirm it. Numerical agreement between implementations checks the calculation, not biological accuracy. These notes do not alter the model weights or add a confidence estimate.',
        'thresholds' => ['numerical_residue_max_percentage_points' => 0.000001, 'small_component_below_percent' => 5, 'threshold_role' => 'numerical_reporting_and_existing_small_component_caution_not_ancestry_detection_limits'],
    ];
    if (!is_array($report) || ($report['available'] ?? null) !== true || ($report['chart_eligible'] ?? null) === false) { return $out; }
    $out['status'] = 'invalid_model_output';
    if (($report['status'] ?? '') !== 'reference_model_proportions' || !is_array($report['components'] ?? null) || count($report['components']) !== 18) { return $out; }
    $seen = []; $sum = 0.0; $items = [];
    foreach ($report['components'] as $c) {
        if (!is_array($c) || !is_string($c['code'] ?? null) || !preg_match('/^[A-Z][A-Z0-9_]{0,31}$/D', $c['code']) || isset($seen[$c['code']]) || !is_string($c['label'] ?? null) || trim($c['label']) === '' || strlen($c['label']) > 200 || preg_match('/[\x00-\x1F\x7F]/', $c['label'])) { return $out; }
        $status = zci_component_status($c['percent'] ?? null);
        if ($status === 'invalid') { return $out; }
        $sum += $c['percent']; $seen[$c['code']] = true;
        [$title, $meaning] = zci_component_language($status);
        $items[] = ['code' => $c['code'], 'label' => $c['label'], 'model_percent' => $c['percent'], 'status' => $status, 'title' => $title, 'meaning' => $meaning, 'can_rule_out_ancestry' => false, 'confirms_ancestry' => false];
    }
    if (!is_finite($sum) || abs($sum - 100.0) > 0.000001) { return $out; }
    $out['available'] = true; $out['status'] = 'model_weight_interpretation'; $out['components'] = $items;
    $out['plain'] = 'Read each percentage as a share assigned by this reference model. Zero, tiny and small weights need different descriptions, but none is a stand-alone test for the presence or absence of an ancestry.';
    return $out;
}

function zl_dna_composition_interpretation_markdown($interpretation) {
    if (!is_array($interpretation) || ($interpretation['available'] ?? null) !== true || ($interpretation['status'] ?? '') !== 'model_weight_interpretation') { return ''; }
    $lines = ['## What the model weights mean', '', (string) $interpretation['plain'], '', '| Reference group | Interpretation |', '|---|---|'];
    foreach ((array) ($interpretation['components'] ?? []) as $c) {
        $safe = function ($s) { return str_replace(['\\', '|', "\r", "\n", '<', '>'], ['\\\\', '\\|', ' ', ' ', '&lt;', '&gt;'], (string) $s); };
        $lines[] = '| '.$safe($c['label']).' | '.$safe($c['title']).'. '.$safe($c['meaning']).' |';
    }
    $lines[] = ''; $lines[] = (string) $interpretation['boundary'];
    $lines[] = ''; $lines[] = 'The numerical-residue threshold is 0.000001 percentage points. The below-5% label follows the existing small-component caution. Neither threshold is an ancestry detection limit. Chromosome-omission ranges describe sensitivity to omitted chromosomes, not confidence intervals.';
    return implode("\n", $lines);
}
