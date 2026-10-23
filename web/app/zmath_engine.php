<?php
// ZMath / Zero Boundary Algebra: deterministic no-token math agent.

function zmath_mod9($value) {
    $r = ((int) $value) % 9;
    return $r < 0 ? $r + 9 : $r;
}

function zmath_balanced_value($residue) {
    $map = [0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => -4, 6 => -3, 7 => -2, 8 => -1];
    return $map[zmath_mod9($residue)];
}

function zmath_balanced_label($residue) {
    $v = zmath_balanced_value($residue);
    if ($v > 0) {
        return '+' . $v;
    }
    return (string) $v;
}

function zmath_role($residue, $literal = '') {
    $literal = trim((string) $literal);
    if ($literal === '-0') {
        return ['Boundary before reset', '0-', 'entering reset'];
    }
    if ($literal === '+0') {
        return ['Boundary after reset', '0+', 'leaving reset'];
    }
    if ($literal === '∞' || strtolower($literal) === 'infinity') {
        return ['Recursion layer', 'infinity', 'repeat transformations'];
    }

    $r = zmath_mod9($residue);
    if ($r === 0) {
        return ['Reset / completion', '0', 'safe reset point'];
    }
    if ($r === 3) {
        return ['Positive polarity', '+3', 'forward pole'];
    }
    if ($r === 6) {
        return ['Negative polarity', '-3', 'inverse pole'];
    }
    if ($r === 8) {
        return ['Mirror residue', '-1', 'mirror operator when used as a multiplier'];
    }
    if ($r === 1) {
        return ['Post-reset seed', '+1', 'first step after reset'];
    }
    if ($r === 7) {
        return ['Pre-reset seed', '-2', 'negative-side approach'];
    }
    return ['Residue ' . $r, zmath_balanced_label($r), 'supporting residue'];
}

function zmath_extract_terms($input) {
    $input = trim((string) $input);
    preg_match_all('/(?:[+-]0|∞|infinity|-?\d+)/iu', $input, $matches);
    $terms = [];
    foreach ($matches[0] as $i => $raw) {
        $literal = trim((string) $raw);
        if ($literal === '') {
            continue;
        }
        if ($literal === '∞' || strtolower($literal) === 'infinity') {
            $role = zmath_role(0, $literal);
            $terms[] = [
                'index' => $i + 1,
                'literal' => $literal,
                'kind' => 'recursion',
                'residue' => null,
                'balanced' => 'infinity',
                'role' => $role[0],
                'function' => $role[2],
            ];
            continue;
        }
        if ($literal === '-0' || $literal === '+0') {
            $role = zmath_role(0, $literal);
            $terms[] = [
                'index' => $i + 1,
                'literal' => $literal,
                'kind' => 'boundary',
                'residue' => 0,
                'balanced' => $role[1],
                'role' => $role[0],
                'function' => $role[2],
            ];
            continue;
        }
        $n = (int) $literal;
        $residue = zmath_mod9($n);
        $role = zmath_role($residue, $literal);
        $terms[] = [
            'index' => $i + 1,
            'literal' => $literal,
            'kind' => 'number',
            'value' => $n,
            'residue' => $residue,
            'balanced' => zmath_balanced_label($residue),
            'role' => $role[0],
            'function' => $role[2],
        ];
    }
    return $terms;
}

function zmath_apply_operator($state, $operator) {
    $state = zmath_mod9($state);
    $operator = strtolower(trim((string) $operator));
    if ($operator === 'mirror' || $operator === 'mu' || $operator === 'x8') {
        return zmath_mod9(8 * $state);
    }
    if ($operator === 'double' || $operator === 'x2') {
        return zmath_mod9(2 * $state);
    }
    if ($operator === 'reset') {
        return 0;
    }
    return zmath_mod9($state + 3);
}

function zmath_operator_trace($start = 0, $steps = 12, $pattern = 'rotate,mirror,boundary') {
    $ops = array_values(array_filter(array_map('trim', explode(',', (string) $pattern))));
    if (!$ops) {
        $ops = ['rotate'];
    }
    $steps = max(1, min(72, (int) $steps));
    $state = zmath_mod9($start);
    $trace = [];
    for ($i = 0; $i < $steps; $i++) {
        $op = strtolower($ops[$i % count($ops)]);
        $from = $state;
        if ($op === 'boundary') {
            $to = $state === 6 ? 0 : ($state === 0 ? 3 : zmath_apply_operator($state, 'rotate'));
            $op_label = $state === 6 ? 'boundary: -3 -> 0- -> 0' : ($state === 0 ? 'boundary: 0 -> 0+ -> +3' : 'boundary pass');
        } else {
            $to = zmath_apply_operator($state, $op);
            $op_label = $op;
        }
        $from_role = zmath_role($from);
        $to_role = zmath_role($to);
        $trace[] = [
            'step' => $i + 1,
            'operator' => $op_label,
            'from' => $from,
            'from_balanced' => zmath_balanced_label($from),
            'from_role' => $from_role[0],
            'to' => $to,
            'to_balanced' => zmath_balanced_label($to),
            'to_role' => $to_role[0],
        ];
        $state = $to;
    }
    return $trace;
}

function zmath_entropy($counts) {
    $total = array_sum($counts);
    if ($total <= 0) {
        return 0.0;
    }
    $h = 0.0;
    foreach ($counts as $count) {
        if ($count <= 0) {
            continue;
        }
        $p = $count / $total;
        $h -= $p * log($p, 2);
    }
    return round($h, 4);
}

function zmath_text_packet($text) {
    $text = (string) $text;
    if (strlen($text) > 12000) {
        $text = substr($text, 0, 12000);
    }
    $bytes = array_values(unpack('C*', $text) ?: []);
    $sum = array_sum($bytes);
    $residue = zmath_mod9($sum);
    $counts = array_fill(0, 9, 0);
    $chunks = [];
    $chunk_size = 9;
    for ($i = 0; $i < count($bytes); $i += $chunk_size) {
        $slice = array_slice($bytes, $i, $chunk_size);
        $chunk_sum = array_sum($slice);
        $r = zmath_mod9($chunk_sum);
        $counts[$r]++;
        $role = zmath_role($r);
        $chunks[] = [
            'index' => (int) floor($i / $chunk_size) + 1,
            'sum' => $chunk_sum,
            'residue' => $r,
            'balanced' => zmath_balanced_label($r),
            'role' => $role[0],
        ];
        if (count($chunks) >= 64) {
            break;
        }
    }
    $triad_total = $counts[0] + $counts[3] + $counts[6];
    $mirror_pair = min($counts[3], $counts[6]);
    $mirror_den = max(1, max($counts[3], $counts[6]));
    $role = zmath_role($residue);
    return [
        'chars' => strlen($text),
        'sha256' => hash('sha256', $text),
        'byte_sum' => $sum,
        'root_residue' => $residue,
        'root_balanced' => zmath_balanced_label($residue),
        'root_role' => $role[0],
        'residue_counts' => $counts,
        'chunk_entropy_bits' => zmath_entropy($counts),
        'triad_density' => count($chunks) ? round($triad_total / count($chunks), 4) : 0,
        'reset_pressure' => count($chunks) ? round($counts[0] / count($chunks), 4) : 0,
        'mirror_balance' => round($mirror_pair / $mirror_den, 4),
        'chunks' => $chunks,
    ];
}

function zmath_protocol_model() {
    return [
        'name' => 'Zero Boundary Algebra State Machine',
        'states' => [
            ['id' => 'P3', 'symbol' => '3', 'meaning' => '+3 polarity'],
            ['id' => 'N3', 'symbol' => '6', 'meaning' => '-3 polarity'],
            ['id' => 'RESET', 'symbol' => '9/0', 'meaning' => 'completion/reset'],
            ['id' => 'PRE_RESET', 'symbol' => '-0', 'meaning' => 'boundary before reset'],
            ['id' => 'POST_RESET', 'symbol' => '+0', 'meaning' => 'boundary after reset'],
        ],
        'operators' => [
            ['id' => 'tau', 'rule' => 'x -> x + 3 mod 9', 'use' => 'advance a three-state cycle'],
            ['id' => 'mu', 'rule' => 'x -> 8x mod 9 = -x mod 9', 'use' => 'mirror positive/negative states'],
            ['id' => 'beta', 'rule' => '6 -> -0 -> 9/0 -> +0 -> 3', 'use' => 'model reset with direction preserved'],
        ],
        'transitions' => [
            ['from' => 'RESET', 'operator' => 'tau', 'to' => 'P3'],
            ['from' => 'P3', 'operator' => 'tau', 'to' => 'N3'],
            ['from' => 'N3', 'operator' => 'tau', 'to' => 'RESET'],
            ['from' => 'P3', 'operator' => 'mu', 'to' => 'N3'],
            ['from' => 'N3', 'operator' => 'mu', 'to' => 'P3'],
            ['from' => 'N3', 'operator' => 'beta', 'to' => 'PRE_RESET'],
            ['from' => 'PRE_RESET', 'operator' => 'beta', 'to' => 'RESET'],
            ['from' => 'RESET', 'operator' => 'beta', 'to' => 'POST_RESET'],
            ['from' => 'POST_RESET', 'operator' => 'beta', 'to' => 'P3'],
        ],
    ];
}

function zmath_practical_uses($packet) {
    $root = (int) ($packet['root_residue'] ?? 0);
    $uses = [
        'State machines: encode idle, active, inverse, pre-reset, and post-reset states without losing transition direction.',
        'Reset logic: distinguish "failed and entering reset" from "reset complete and safe to continue".',
        'Mirror testing: use the 8x operator to verify paired UI states, reversible workflows, or A/B branch inversions.',
        'Deterministic checksums: create a tiny ZM9 fingerprint for prompts, files, protocol steps, or research notes before sending them to an LLM.',
        'Token saving: let this engine classify and compress symbolic/text structure before asking the AI for high-level reasoning.',
    ];
    if ($root === 0) {
        $uses[] = 'This packet lands on reset, so it is useful as a completion marker, checkpoint, or "safe boundary" tag.';
    } elseif ($root === 3) {
        $uses[] = 'This packet lands on +3, so it is useful as a forward/activation/start branch marker.';
    } elseif ($root === 6) {
        $uses[] = 'This packet lands on -3, so it is useful as an inverse/review/rollback branch marker.';
    } elseif ($root === 8) {
        $uses[] = 'This packet lands on 8/-1, so it is useful as a mirror-test marker.';
    }
    return $uses;
}

function zmath_agent_metrics($packet) {
    $reset = (float) ($packet['reset_pressure'] ?? 0);
    $triad = (float) ($packet['triad_density'] ?? 0);
    $mirror = (float) ($packet['mirror_balance'] ?? 0);
    $entropy = (float) ($packet['chunk_entropy_bits'] ?? 0);
    $root = (int) ($packet['root_residue'] ?? 0);
    $root_bonus = in_array($root, [0, 3, 6, 8], true) ? 0.18 : 0.05;

    return [
        'boundary_clarity' => round(min(1, ($reset * 0.42) + ($triad * 0.34) + $root_bonus), 4),
        'mirror_integrity' => round(min(1, $mirror), 4),
        'recursion_energy' => round(min(1, $entropy / 3.2), 4),
        'triad_alignment' => round(min(1, $triad), 4),
        'suggested_mode' => $root === 0 ? 'checkpoint / completion' : ($root === 3 ? 'activate / build' : ($root === 6 ? 'review / rollback' : ($root === 8 ? 'mirror / inverse test' : 'supporting analysis'))),
    ];
}

function zmath_agent_answer($question, $packet, $uses) {
    $question = trim((string) $question);
    $metrics = zmath_agent_metrics($packet);
    $root = (int) ($packet['root_residue'] ?? 0);
    $role = (string) ($packet['root_role'] ?? 'supporting residue');
    $mode = $metrics['suggested_mode'];
    $best_use = $uses[0] ?? 'Use this as a deterministic pre-LLM structure check.';

    $lines = [];
    $lines[] = '## ZMath Agent Answer';
    $lines[] = '';
    $lines[] = 'I read this as a **' . $mode . '** packet. The root lands on **' . $root . '**, which ZMath maps to **' . $role . '**.';
    if ($question !== '') {
        $lines[] = 'Your goal was: **' . $question . '**';
    }
    $lines[] = '';
    $lines[] = '**Practical move:** ' . $best_use;
    $lines[] = '';
    $lines[] = 'Use the visible answer above for normal work. The lab tools below show how I got there: packet fingerprint, mirror/reset trace, and exportable state-machine data.';
    return implode("\n", $lines);
}

function zmath_file_profile($name, $size, $content) {
    $name = basename((string) $name);
    $content = (string) $content;
    if (strlen($content) > 12000) {
        $content = substr($content, 0, 12000);
    }
    $packet = zmath_text_packet($name . "\n" . $content);
    $line_count = substr_count($content, "\n") + ($content !== '' ? 1 : 0);
    $ascii = $content === '' ? 1 : (preg_match('/^[\x09\x0A\x0D\x20-\x7E]*$/', $content) ? 1 : 0);
    $metrics = zmath_agent_metrics($packet);
    return [
        'name' => $name !== '' ? $name : 'uploaded-file',
        'size' => (int) $size,
        'analyzed_chars' => strlen($content),
        'line_count' => $line_count,
        'text_like' => (bool) $ascii,
        'packet' => $packet,
        'metrics' => $metrics,
        'recommendation' => $metrics['suggested_mode'],
    ];
}

function zmath_markdown_table($rows, $headers) {
    $lines = [];
    $lines[] = '| ' . implode(' | ', $headers) . ' |';
    $lines[] = '| ' . implode(' | ', array_fill(0, count($headers), '---')) . ' |';
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $key) {
            $value = is_array($row) ? ($row[$key] ?? '') : '';
            $line[] = str_replace('|', '\\|', (string) $value);
        }
        $lines[] = '| ' . implode(' | ', $line) . ' |';
    }
    return implode("\n", $lines);
}

function zmath_build_report($input, $question = '', $options = []) {
    $input = trim((string) $input);
    if ($input === '') {
        $input = '3 8 6 9 -0 +0 infinity';
    }
    if (strlen($input) > 12000) {
        $input = substr($input, 0, 12000);
    }
    $terms = zmath_extract_terms($input);
    $packet = zmath_text_packet($input);
    $trace = zmath_operator_trace((int) ($options['start'] ?? $packet['root_residue']), (int) ($options['steps'] ?? 12), (string) ($options['pattern'] ?? 'rotate,mirror,boundary'));
    $model = zmath_protocol_model();
    $uses = zmath_practical_uses($packet);
    $metrics = zmath_agent_metrics($packet);

    $lines = [];
    $lines[] = zmath_agent_answer($question, $packet, $uses);
    $lines[] = '';
    $lines[] = '---';
    $lines[] = '';
    $lines[] = '## Lab Tools';
    $lines[] = '';
    $lines[] = 'The strict core is: R = Z/9Z, H = {0,3,6}, tau(x)=x+3 mod 9, mu(x)=8x mod 9=-x mod 9. The boundary labels -0, +0, and infinity are symbolic extensions for direction and recursion.';
    $lines[] = '';
    $lines[] = '### Packet Fingerprint';
    $lines[] = '- SHA-256: `' . $packet['sha256'] . '`';
    $lines[] = '- Byte sum: ' . $packet['byte_sum'];
    $lines[] = '- Root residue: ' . $packet['root_residue'] . ' (' . $packet['root_balanced'] . ')';
    $lines[] = '- Root role: ' . $packet['root_role'];
    $lines[] = '- Triad density: ' . $packet['triad_density'];
    $lines[] = '- Reset pressure: ' . $packet['reset_pressure'];
    $lines[] = '- Mirror balance: ' . $packet['mirror_balance'];
    $lines[] = '- Chunk entropy: ' . $packet['chunk_entropy_bits'] . ' bits';
    $lines[] = '- Boundary clarity: ' . $metrics['boundary_clarity'];
    $lines[] = '- Mirror integrity: ' . $metrics['mirror_integrity'];
    $lines[] = '- Recursion energy: ' . $metrics['recursion_energy'];
    $lines[] = '- Suggested mode: ' . $metrics['suggested_mode'];
    $lines[] = '';
    $lines[] = '### Parsed ZMath Terms';
    if ($terms) {
        $term_rows = [];
        foreach ($terms as $term) {
            $term_rows[] = [
                'literal' => $term['literal'],
                'residue' => $term['residue'] === null ? '-' : $term['residue'],
                'balanced' => $term['balanced'],
                'role' => $term['role'],
                'function' => $term['function'],
            ];
        }
        $lines[] = zmath_markdown_table($term_rows, ['literal', 'residue', 'balanced', 'role', 'function']);
    } else {
        $lines[] = 'No explicit numbers were found, so ZMath analyzed the text packet itself.';
    }
    $lines[] = '';
    $lines[] = '### Operator Trace';
    $trace_rows = [];
    foreach ($trace as $row) {
        $trace_rows[] = [
            'step' => $row['step'],
            'operator' => $row['operator'],
            'from' => $row['from_balanced'] . ' / ' . $row['from_role'],
            'to' => $row['to_balanced'] . ' / ' . $row['to_role'],
        ];
    }
    $lines[] = zmath_markdown_table($trace_rows, ['step', 'operator', 'from', 'to']);
    $lines[] = '';
    $lines[] = '### Practical Uses';
    foreach ($uses as $use) {
        $lines[] = '- ' . $use;
    }
    $lines[] = '';
    $lines[] = '### Exportable State Machine';
    $lines[] = '```json';
    $lines[] = json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $lines[] = '```';

    return [
        'terms' => $terms,
        'packet' => $packet,
        'trace' => $trace,
        'model' => $model,
        'uses' => $uses,
        'metrics' => $metrics,
        'markdown' => implode("\n", $lines),
    ];
}
?>
