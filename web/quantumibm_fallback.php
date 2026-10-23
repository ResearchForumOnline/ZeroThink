<?php
// Shared IBM Qiskit fallback packet builder for hosts that cannot launch Python.

if (!function_exists('zt_qiskit_request_value')) {
    function zt_qiskit_request_value($request, $key, $default = '') {
        return is_array($request) && array_key_exists($key, $request) ? $request[$key] : $default;
    }
}

if (!function_exists('zt_qiskit_normalize_algorithm')) {
    function zt_qiskit_normalize_algorithm($algorithm) {
        $algorithm = strtolower(trim((string) $algorithm));
        $algorithm = str_replace([' ', '-', '.'], '_', $algorithm);
        $aliases = [
            'zero' => 'zero_lattice',
            'lattice' => 'zero_lattice',
            'zero_lattice' => 'zero_lattice',
            'bell_pair' => 'bell',
            'bell' => 'bell',
            'ghz_state' => 'ghz',
            'ghz' => 'ghz',
            'grover_search' => 'grover',
            'grover' => 'grover',
            'qaoa' => 'qaoa_maxcut',
            'maxcut' => 'qaoa_maxcut',
            'qaoa_maxcut' => 'qaoa_maxcut',
            'vqe' => 'vqe_toy',
            'vqe_toy' => 'vqe_toy',
            'qft' => 'qft',
        ];
        return isset($aliases[$algorithm]) ? $aliases[$algorithm] : 'zero_lattice';
    }
}

if (!function_exists('zt_qiskit_add_weight')) {
    function zt_qiskit_add_weight(&$weights, $state, $weight) {
        $state = (string) $state;
        $weight = max(0.0, (float) $weight);
        if ($state === '' || $weight <= 0) {
            return;
        }
        if (!isset($weights[$state])) {
            $weights[$state] = 0.0;
        }
        $weights[$state] += $weight;
    }
}

if (!function_exists('zt_qiskit_seed_bits')) {
    function zt_qiskit_seed_bits($seed, $bits) {
        $value = hexdec(substr((string) $seed, 0, 8));
        $max = max(1, (int) pow(2, $bits));
        return str_pad(decbin($value % $max), $bits, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('zt_qiskit_invert_bits')) {
    function zt_qiskit_invert_bits($bits) {
        return strtr((string) $bits, ['0' => '1', '1' => '0']);
    }
}

if (!function_exists('zt_qiskit_rotate_bits')) {
    function zt_qiskit_rotate_bits($bits, $steps = 1) {
        $bits = (string) $bits;
        $len = strlen($bits);
        if ($len <= 1) {
            return $bits;
        }
        $steps = ((int) $steps) % $len;
        return substr($bits, $steps) . substr($bits, 0, $steps);
    }
}

if (!function_exists('zt_qiskit_counts_from_weights')) {
    function zt_qiskit_counts_from_weights($weights, $shots) {
        $shots = max(32, min((int) $shots, 4096));
        $total = 0.0;
        foreach ($weights as $weight) {
            $total += max(0.0, (float) $weight);
        }
        if ($total <= 0) {
            $weights = ['0' => 1.0];
            $total = 1.0;
        }

        $counts = [];
        $remainders = [];
        $assigned = 0;
        foreach ($weights as $state => $weight) {
            $raw = $shots * (max(0.0, (float) $weight) / $total);
            $count = (int) floor($raw);
            if ($count > 0) {
                $counts[(string) $state] = $count;
            } else {
                $counts[(string) $state] = 0;
            }
            $remainders[(string) $state] = $raw - $count;
            $assigned += $count;
        }

        arsort($remainders, SORT_NUMERIC);
        while ($assigned < $shots) {
            foreach ($remainders as $state => $fraction) {
                $counts[$state] = isset($counts[$state]) ? $counts[$state] + 1 : 1;
                $assigned++;
                if ($assigned >= $shots) {
                    break;
                }
            }
        }

        foreach ($counts as $state => $count) {
            if ($count <= 0) {
                unset($counts[$state]);
            }
        }
        arsort($counts, SORT_NUMERIC);
        return $counts;
    }
}

if (!function_exists('zt_qiskit_php_counts')) {
    function zt_qiskit_php_counts($algorithm, $shots, $seed) {
        $algorithm = zt_qiskit_normalize_algorithm($algorithm);
        $weights = [];

        if ($algorithm === 'bell') {
            $weights = ['00' => 1.0, '11' => 1.0];
        } elseif ($algorithm === 'ghz') {
            $weights = ['000' => 1.0, '111' => 1.0];
        } elseif ($algorithm === 'grover') {
            $marked = zt_qiskit_seed_bits($seed, 2);
            foreach (['00', '01', '10', '11'] as $state) {
                $weights[$state] = ($state === $marked) ? 7.0 : 1.0;
            }
        } elseif ($algorithm === 'qaoa_maxcut') {
            $weights = ['0101' => 5.0, '1010' => 5.0, '0011' => 1.5, '1100' => 1.5, '0110' => 1.0, '1001' => 1.0];
        } elseif ($algorithm === 'vqe_toy') {
            $weights = ['00' => 5.0, '01' => 2.0, '10' => 2.0, '11' => 1.0];
        } elseif ($algorithm === 'qft') {
            for ($i = 0; $i < 8; $i++) {
                $state = str_pad(decbin($i), 3, '0', STR_PAD_LEFT);
                $hex = hexdec(substr($seed, $i, 1));
                $weights[$state] = (($hex + $i) % 8) + 1;
            }
        } else {
            $base = zt_qiskit_seed_bits($seed, 4);
            zt_qiskit_add_weight($weights, $base, 5.0);
            zt_qiskit_add_weight($weights, zt_qiskit_invert_bits($base), 4.0);
            zt_qiskit_add_weight($weights, zt_qiskit_rotate_bits($base, 1), 2.0);
            zt_qiskit_add_weight($weights, strrev($base), 1.0);
        }

        return zt_qiskit_counts_from_weights($weights, $shots);
    }
}

if (!function_exists('zt_qiskit_summary')) {
    function zt_qiskit_summary($counts, $shots) {
        $shots = max(1, (int) $shots);
        arsort($counts, SORT_NUMERIC);
        $dominant_state = '';
        $dominant_count = 0;
        $top_states = [];
        $entropy = 0.0;
        $i = 0;

        foreach ($counts as $state => $count) {
            $count = (int) $count;
            if ($dominant_state === '') {
                $dominant_state = (string) $state;
                $dominant_count = $count;
            }
            if ($count > 0) {
                $p = $count / $shots;
                $entropy -= $p * (log($p) / log(2));
                if ($i < 8) {
                    $top_states[] = [
                        'state' => (string) $state,
                        'count' => $count,
                        'probability' => round($p, 6),
                    ];
                }
                $i++;
            }
        }

        return [
            'shots' => $shots,
            'dominant_state' => $dominant_state,
            'dominant_probability' => round($dominant_count / $shots, 6),
            'entropy_bits' => round($entropy, 6),
            'top_states' => $top_states,
        ];
    }
}

if (!function_exists('zt_qiskit_php_fallback_result')) {
    function zt_qiskit_php_fallback_result($request, $reason = '') {
        $algorithm = zt_qiskit_normalize_algorithm(zt_qiskit_request_value($request, 'algorithm', 'zero_lattice'));
        $mode = strtolower(trim((string) zt_qiskit_request_value($request, 'mode', 'local')));
        if (!in_array($mode, ['local', 'runtime', 'backend', 'ibm'], true)) {
            $mode = 'local';
        }
        $shots = max(32, min((int) zt_qiskit_request_value($request, 'shots', 256), 4096));
        $prompt = (string) zt_qiskit_request_value($request, 'prompt', '');
        $research_signal = (string) zt_qiskit_request_value($request, 'research_signal', '');
        $seed = hash('sha256', $algorithm . '|' . $mode . '|' . $shots . '|' . $prompt . '|' . $research_signal);
        $counts = zt_qiskit_php_counts($algorithm, $shots, $seed);
        $summary = zt_qiskit_summary($counts, $shots);
        $has_token = trim((string) zt_qiskit_request_value($request, 'ibm_token', '')) !== '';
        $reason = trim((string) $reason);
        if (strlen($reason) > 240) {
            $reason = substr($reason, 0, 240);
        }

        if (in_array($mode, ['runtime', 'backend', 'ibm'], true)) {
            $runtime_message = $has_token
                ? 'IBM token was available, but backend context was not checked because PHP could not launch the Python Qiskit worker.'
                : 'IBM Quantum token not supplied; backend context unavailable.';
        } else {
            $runtime_message = 'Local PHP fallback packet generated; no IBM hardware job was submitted.';
        }

        $environment = [
            'platform' => PHP_OS,
            'php' => PHP_VERSION,
            'python' => 'unavailable-from-php',
            'python_executable' => '',
            'qiskit_available' => false,
            'qiskit_error' => $reason !== '' ? $reason : 'Python Qiskit worker unavailable from PHP.',
            'qiskit_ibm_runtime_available' => false,
            'qiskit_version' => '',
            'requires_python' => '>=3.10 for current qiskit and qiskit-ibm-runtime',
            'runtime_error' => $runtime_message,
            'runtime_version' => '',
        ];

        $circuit = ['label' => 'php deterministic fallback; Python Qiskit worker unavailable'];
        $lines = [
            '[IBM QISKIT LANE]',
            'provider=IBM Quantum / Qiskit',
            'mode=' . $mode,
            'algorithm=' . $algorithm,
            'qiskit_available=no',
            'qiskit_version=not installed',
            'python=unavailable-from-php',
            'truth_boundary=Classical AI reasons over Qiskit/IBM evidence. Do not claim the LLM runs on qubits.',
            'runtime_boundary=No IBM hardware job is submitted unless the operator explicitly enables runtime/hardware mode.',
            'dominant_state=' . $summary['dominant_state'],
            'dominant_probability=' . $summary['dominant_probability'],
            'entropy_bits=' . $summary['entropy_bits'],
            'counts=' . json_encode($counts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'circuit=' . json_encode($circuit, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'fallback_reason=' . ($reason !== '' ? $reason : 'Python worker unavailable from PHP.'),
            'setup_note=Enable a Python 3.10+ Qiskit worker via QISKIT_PYTHON or a sidecar service for real Qiskit execution.',
        ];

        return [
            'status' => 'success',
            'worker' => 'php-fallback-0.1.0',
            'algorithm' => $algorithm,
            'mode' => $mode,
            'execution' => 'php_deterministic_fallback',
            'seed_hash' => $seed,
            'counts' => $counts,
            'summary' => $summary,
            'circuit' => $circuit,
            'environment' => $environment,
            'ibm_runtime' => [
                'available' => false,
                'message' => $runtime_message,
            ],
            'context' => implode("\n", $lines),
            'truth_boundary' => 'Qiskit/IBM evidence can ground the answer; the language model remains classical.',
        ];
    }
}
