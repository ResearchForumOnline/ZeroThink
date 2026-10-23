<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/zmath_engine.php';

ob_end_clean();
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function zmath_api_reply($payload, $status = 200) {
    http_response_code((int) $status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (!empty($_FILES['file'])) {
        $file = $_FILES['file'];
        if (!empty($file['error'])) {
            throw new Exception('File upload failed with code ' . (int) $file['error']);
        }
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new Exception('ZMath file analysis is limited to 5 MB per file.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $content = is_file($tmp) ? (string) file_get_contents($tmp, false, null, 0, 12000) : '';
        $profile = zmath_file_profile((string) ($file['name'] ?? 'uploaded-file'), (int) ($file['size'] ?? 0), $content);
        $report = zmath_build_report(($file['name'] ?? 'uploaded-file') . "\n" . $content, 'Analyze this uploaded file with ZMath and recommend the useful workflow.', ['steps' => 12]);
        zmath_api_reply([
            'status' => 'success',
            'agent' => 'ZMath Agent',
            'kind' => 'file_analysis',
            'profile' => $profile,
            'reply' => $report['markdown'],
        ]);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST ?: [];
    }

    $action = strtolower(trim((string) ($input['action'] ?? 'agent')));
    $text = (string) ($input['input'] ?? $input['text'] ?? $input['prompt'] ?? '');
    $question = (string) ($input['question'] ?? '');
    $steps = max(1, min(72, (int) ($input['steps'] ?? 12)));
    $pattern = (string) ($input['pattern'] ?? 'rotate,mirror,boundary');

    if (strlen($text) > 12000) {
        $text = substr($text, 0, 12000);
    }
    if (strlen($question) > 2000) {
        $question = substr($question, 0, 2000);
    }

    if ($action === 'status') {
        zmath_api_reply([
            'status' => 'success',
            'agent' => 'ZMath Agent',
            'mode' => 'deterministic no-token modulo-9 engine',
            'limits' => ['input_chars' => 12000, 'trace_steps' => 72],
            'truth_boundary' => 'Mathematical core is modular arithmetic over Z/9Z. Boundary labels are symbolic state annotations.',
        ]);
    }

    if ($action === 'machine') {
        zmath_api_reply([
            'status' => 'success',
            'model' => zmath_protocol_model(),
            'trace' => zmath_operator_trace((int) ($input['start'] ?? 0), $steps, $pattern),
        ]);
    }

    if ($action === 'packet') {
        zmath_api_reply([
            'status' => 'success',
            'packet' => zmath_text_packet($text),
            'terms' => zmath_extract_terms($text),
        ]);
    }

    if ($action === 'file_analysis') {
        $name = trim((string) ($input['filename'] ?? 'browser-file-preview.txt'));
        $size = max(0, (int) ($input['size'] ?? strlen($text)));
        $profile = zmath_file_profile($name, $size, $text);
        $report = zmath_build_report($name . "\n" . $text, 'Analyze this file preview with ZMath and recommend the useful workflow.', [
            'steps' => $steps,
            'pattern' => $pattern,
        ]);
        zmath_api_reply([
            'status' => 'success',
            'agent' => 'ZMath Agent',
            'kind' => 'file_analysis',
            'profile' => $profile,
            'reply' => $report['markdown'],
        ]);
    }

    if ($action === 'agent' || $action === 'analyze' || $action === 'chat') {
        $report = zmath_build_report($text, $question, [
            'steps' => $steps,
            'pattern' => $pattern,
            'start' => $input['start'] ?? null,
        ]);
        zmath_api_reply([
            'status' => 'success',
            'agent' => 'ZMath Agent',
            'reply' => $report['markdown'],
            'terms' => $report['terms'],
            'packet' => $report['packet'],
            'trace' => $report['trace'],
            'model' => $report['model'],
            'uses' => $report['uses'],
            'metrics' => $report['metrics'],
        ]);
    }

    zmath_api_reply(['status' => 'error', 'message' => 'Unknown ZMath action.'], 400);
} catch (Throwable $e) {
    zmath_api_reply(['status' => 'error', 'message' => $e->getMessage()], 400);
}
?>
