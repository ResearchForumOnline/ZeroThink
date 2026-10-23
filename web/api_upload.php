<?php
header('Content-Type: application/json');
require_once __DIR__ . '/app/bootstrap.php';

if (function_exists('request_user_is_authenticated')) {
    $is_authenticated = request_user_is_authenticated();
} else {
    $is_authenticated = isset($_SESSION['user_id']);
}

if (!$is_authenticated) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Auth Required']);
    exit;
}

function normalize_upload_text($content) {
    $content = (string) $content;
    if ($content === '') {
        return '';
    }

    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    $content = str_replace("\0", '', $content);
    $content = preg_replace("/\r\n?/", "\n", $content);
    return (string) $content;
}

function build_code_outline($filename, $content) {
    $content = normalize_upload_text($content);
    $lines = explode("\n", $content);
    $hits = [];
    $max_hits = 180;

    $patterns = [
        '/^\s*(?:export\s+)?(?:default\s+)?(class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/i' => '%s %s',
        '/^\s*(?:export\s+)?(?:async\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i' => 'function %s',
        '/^\s*(?:public|private|protected|static|async|\s)*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i' => 'method %s',
        '/^\s*(?:export\s+)?(?:const\s+)?(type|record)\s+([A-Za-z_][A-Za-z0-9_]*)/i' => '%s %s',
        '/^\s*(?:export\s+)?namespace\s+([A-Za-z_][A-Za-z0-9_]*)/i' => 'namespace %s',
        '/^\s*(?:export\s+)?module\s+([A-Za-z_][A-Za-z0-9_]*)/i' => 'module %s',
        '/^\s*def\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i' => 'def %s',
        '/^\s*class\s+([A-Za-z_][A-Za-z0-9_]*)\b/i' => 'class %s',
        '/^\s*struct\s+([A-Za-z_][A-Za-z0-9_]*)\b/i' => 'struct %s',
        '/^\s*impl\s+([A-Za-z_][A-Za-z0-9_:<>]*)\b/i' => 'impl %s',
        '/^\s*fn\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i' => 'fn %s',
        '/^\s*func\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/i' => 'func %s',
        '/^\s*(?:const|let|var)\s+([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(?:async\s*)?\(/i' => 'callable %s',
        '/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*lambda\b/i' => 'lambda %s',
    ];

    foreach ($lines as $index => $line) {
        $line_no = $index + 1;
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        foreach ($patterns as $pattern => $label) {
            if (preg_match($pattern, $trimmed, $match)) {
                if (strpos($label, '%s %s') !== false) {
                    $entry = sprintf($label, strtolower($match[1]), $match[2]);
                } else {
                    $entry = sprintf($label, $match[count($match) - 1]);
                }
                $hits[] = 'L' . $line_no . '  ' . $entry;
                break;
            }
        }

        if (count($hits) >= $max_hits) {
            break;
        }
    }

    if (!$hits) {
        return 'No obvious code symbols detected. Treat as raw text/config/log content.';
    }

    return implode("\n", $hits);
}

function pack_upload_context($filename, $content) {
    $content = normalize_upload_text($content);
    $chars = strlen($content);
    $lines = substr_count($content, "\n") + 1;
    $outline = build_code_outline($filename, $content);

    $header = [
        'FILE PACK',
        'Name: ' . $filename,
        'Chars: ' . number_format($chars),
        'Lines: ' . number_format($lines),
        'Structure Index:',
        $outline,
    ];

    if ($chars <= 260000) {
        return [
            'content' => implode("\n", $header) . "\n[FULL FILE BELOW]\n" . $content . "\n[END FILE]",
            'notice' => 'Full file loaded into ZeroThink context.',
            'chars' => $chars,
            'lines' => $lines,
            'truncated' => false,
        ];
    }

    $head = substr($content, 0, 95000);
    $quarter_start = max(0, (int) floor(($chars * 0.25) - 20000));
    $quarter = substr($content, $quarter_start, 40000);
    $middle_start = max(0, (int) floor(($chars / 2) - 27500));
    $middle = substr($content, $middle_start, 55000);
    $three_quarter_start = max(0, (int) floor(($chars * 0.75) - 20000));
    $three_quarter = substr($content, $three_quarter_start, 40000);
    $tail = substr($content, -95000);

    $packed = implode("\n", $header) .
        "\n[LARGE FILE PACKED FOR MODEL WINDOW]" .
        "\n[HEAD]\n" . $head .
        "\n[QUARTER]\n" . $quarter .
        "\n[MIDDLE]\n" . $middle .
        "\n[THREE QUARTER]\n" . $three_quarter .
        "\n[TAIL]\n" . $tail .
        "\n[END FILE]";

    return [
        'content' => $packed,
        'notice' => 'Large file packed using a structure index plus head, quarter, middle, three-quarter, and tail slices so ZeroThink can reason over bigger code safely.',
        'chars' => $chars,
        'lines' => $lines,
        'truncated' => true,
    ];
}

try {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed.');
    }

    $f = $_FILES['file'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $valid_txt = [
        'txt','php','phtml','py','js','jsx','ts','tsx','html','css','scss','json','md','sql','log',
        'java','c','cc','cpp','h','hpp','cs','go','rs','rb','swift','kt','kts','scala','xml','yml',
        'yaml','toml','ini','env','sh','bash','zsh','ps1','vue','svelte','astro','dockerfile',
        'csv','lock','properties','gradle','conf','cfg','mjs','cjs','mts','cts','proto','graphql',
        'gql','diff','patch','prisma','ipynb','tf','tfvars','pem','pub','editorconfig','gitignore'
    ];
    $valid_img = ['jpg','jpeg','png','webp','gif'];

    if (in_array($ext, $valid_txt, true) || preg_match('/^(dockerfile|makefile)$/i', basename($f['name'])) === 1) {
        if ($f['size'] > 20 * 1024 * 1024) {
            throw new Exception('Text/code file too large (max 20MB).');
        }

        $content = file_get_contents($f['tmp_name']);
        $packed = pack_upload_context($f['name'], $content);

        echo json_encode([
            'status' => 'success',
            'type' => 'text',
            'name' => $f['name'],
            'filename' => $f['name'],
            'content' => $packed['content'],
            'notice' => $packed['notice'],
            'chars' => $packed['chars'],
            'lines' => $packed['lines'],
            'truncated' => $packed['truncated'],
        ]);
    } elseif (in_array($ext, $valid_img, true)) {
        if ($f['size'] > 6 * 1024 * 1024) {
            throw new Exception('Image too large (max 6MB).');
        }
        $data = file_get_contents($f['tmp_name']);
        $base64 = 'data:' . mime_content_type($f['tmp_name']) . ';base64,' . base64_encode($data);
        echo json_encode([
            'status' => 'success',
            'type' => 'image',
            'name' => $f['name'],
            'filename' => $f['name'],
            'url' => $base64,
            'notice' => 'Image attached for visual analysis.',
        ]);
    } else {
        throw new Exception('Format .' . $ext . ' not supported for safe ingest. Upload text, code, config, or image files.');
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
