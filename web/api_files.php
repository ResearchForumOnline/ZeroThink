<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/app/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Auth required.']);
    exit;
}

$user = function_exists('current_user_record') ? current_user_record() : null;
if (!function_exists('is_architect_user') || !is_architect_user($user)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Architect access only.']);
    exit;
}

$uid = (int) ($_SESSION['user_id'] ?? 0);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$root = __DIR__ . '/private/uploads/' . $uid;
if (!is_dir($root)) {
    mkdir($root, 0700, true);
}
@chmod($root, 0700);

function files_json($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function files_safe_name($name) {
    $name = trim((string) $name);
    $name = str_replace(["\\", "/", "\0"], '', $name);
    $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $name = trim($name, '. ');
    return $name !== '' ? $name : '';
}

function files_target_path($root, $name) {
    $safe = files_safe_name($name);
    if ($safe === '') {
        return '';
    }
    return $root . DIRECTORY_SEPARATOR . $safe;
}

function files_is_allowed_upload($filename) {
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $blocked = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'cgi', 'pl', 'pyc',
        'sh', 'bash', 'zsh', 'ps1', 'exe', 'dll', 'so', 'com', 'bat', 'cmd',
        'msi', 'jar', 'apk', 'deb', 'rpm',
    ];
    if (in_array($ext, $blocked, true)) {
        return false;
    }

    $blocked_names = ['.htaccess', '.user.ini', 'php.ini'];
    if (in_array(strtolower((string) $filename), $blocked_names, true)) {
        return false;
    }

    return true;
}

function files_human_size($bytes) {
    $bytes = max(0, (int) $bytes);
    if ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

$action = $method === 'GET'
    ? strtolower(trim((string) ($_GET['action'] ?? 'list')))
    : strtolower(trim((string) ($_POST['action'] ?? '')));

if ($method === 'POST') {
    $csrf_token = trim((string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    if (!function_exists('zt_csrf_validate') || !zt_csrf_validate($csrf_token)) {
        files_json([
            'status' => 'error',
            'message' => 'Your secure session expired. Refresh Private Files and try again.',
        ], 403);
    }
}

if ($method === 'GET' && $action === 'list') {
    $files = [];
    foreach ((array) glob($root . DIRECTORY_SEPARATOR . '*') as $path) {
        if (!is_file($path)) {
            continue;
        }
        $files[] = [
            'name' => basename($path),
            'bytes' => filesize($path),
            'size' => files_human_size(filesize($path)),
            'modified' => date('c', (int) filemtime($path)),
        ];
    }

    usort($files, function ($a, $b) {
        return strcmp($b['modified'], $a['modified']);
    });

    files_json(['status' => 'success', 'files' => $files]);
}

if ($method === 'POST' && $action === 'upload') {
    if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
        files_json(['status' => 'error', 'message' => 'No file uploaded.'], 400);
    }

    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        files_json(['status' => 'error', 'message' => 'Upload failed with code ' . (int) $file['error'] . '.'], 400);
    }

    $name = files_safe_name($file['name'] ?? '');
    if ($name === '') {
        files_json(['status' => 'error', 'message' => 'Unsafe file name.'], 400);
    }

    if (!files_is_allowed_upload($name)) {
        files_json(['status' => 'error', 'message' => 'Blocked file type for web safety. Use SSH/SFTP for executable or server-side scripts.'], 400);
    }

    $bytes = (int) ($file['size'] ?? 0);
    if ($bytes > 12 * 1024 * 1024) {
        files_json(['status' => 'error', 'message' => 'File too large (max 12MB).'], 400);
    }

    $target = files_target_path($root, $name);
    if ($target === '') {
        files_json(['status' => 'error', 'message' => 'Unsafe target path.'], 400);
    }

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        files_json(['status' => 'error', 'message' => 'Move failed.'], 500);
    }

    @chmod($target, 0600);

    files_json([
        'status' => 'success',
        'message' => 'Stored in private architect vault. No auto-extract performed.',
        'file' => [
            'name' => basename($target),
            'bytes' => filesize($target),
            'size' => files_human_size(filesize($target)),
            'modified' => date('c', (int) filemtime($target)),
        ],
    ]);
}

if ($method === 'POST' && $action === 'delete') {
    $name = files_safe_name($_POST['name'] ?? '');
    $target = files_target_path($root, $name);
    if ($target === '' || !is_file($target)) {
        files_json(['status' => 'error', 'message' => 'File not found.'], 404);
    }

    if (!unlink($target)) {
        files_json(['status' => 'error', 'message' => 'Delete failed.'], 500);
    }

    files_json(['status' => 'success', 'message' => 'File deleted.']);
}

if ($method === 'GET' && $action === 'download') {
    $name = files_safe_name($_GET['name'] ?? '');
    $target = files_target_path($root, $name);
    if ($target === '' || !is_file($target)) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }

    header_remove('Content-Type');
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($target));
    header('Content-Disposition: attachment; filename="' . basename($target) . '"');
    readfile($target);
    exit;
}

files_json(['status' => 'error', 'message' => 'Unknown action.'], 400);
?>
