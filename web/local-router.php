<?php
/** Database-free DNA Lab, exclusively for PHP's loopback development server. */
declare(strict_types=1);

$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (PHP_SAPI !== 'cli-server' || !in_array($remote, ['127.0.0.1', '::1'], true)
    || !preg_match('/^(?:127\.0\.0\.1|localhost)(?::[0-9]{1,5})?$/D', $host)) {
    http_response_code(403); exit('Local DNA Lab requires a loopback server and host.');
}
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
// A normal link/address navigation may be cross-site. Only the public local
// page shell can use this exception; API, asset and embedded requests cannot.
$readOnlyNavigation = $origin === ''
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '') === 'navigate'
    && ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'document'
    && in_array($path, ['/', '/dna', '/dna.php', '/privacy', '/terms', '/faq'], true);
if (($origin !== '' && $origin !== 'http://' . $host)
    || (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site' && !$readOnlyNavigation)) {
    http_response_code(403); exit('Cross-origin requests are blocked.');
}
define('ZEROTHINK_LOCAL_LAB', true);
if (str_contains($path, "\0") || str_contains($path, '\\') || str_contains($path, '..')) {
    http_response_code(404); exit('Not found.');
}
if (str_starts_with($path, '/assets/')) {
    $base = realpath(__DIR__ . '/assets');
    $file = realpath(__DIR__ . $path);
    $types = ['css'=>'text/css','js'=>'text/javascript','json'=>'application/json',
        'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp',
        'svg'=>'image/svg+xml'];
    $ext = strtolower(pathinfo($file ?: '', PATHINFO_EXTENSION));
    if (!$file || !is_file($file) || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !isset($types[$ext])) {
        http_response_code(404); exit('Not found.');
    }
    header('Content-Type: ' . $types[$ext]);
    header('X-Content-Type-Options: nosniff');
    readfile($file); return;
}
// This session is never accepted by the historical account-backed server.
session_name('zerothink_local_lab');
require_once __DIR__ . '/app/bootstrap.php';
$_SESSION['user_id'] = 1;
if (in_array($path, ['/privacy', '/terms', '/faq'], true)) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; base-uri \'none\'; frame-ancestors \'none\'');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Local DNA Lab privacy and limits</title><body style="background:#05221b;color:#efffff;max-width:760px;margin:40px auto;padding:20px;font:18px system-ui"><h1>Local DNA Lab privacy and limits</h1><p>Analysis runs on this computer. This local mode has no remote account, MySQL connection, model call, telemetry or external research API. Raw uploads are discarded after processing unless you explicitly enable private vault save. Browser downloads and exports contain sensitive research summaries: keep them private.</p><p>Optional private saves use the configured local storage directory. Stop the PHP server when finished. Any local computer account with access to your storage can read saved files.</p><p>Reference databases and model bundles install separately under their own licenses. Missing packs are reported as unavailable. Outputs support research; they do not diagnose disease, establish nationality or religion, or prove named or royal ancestors. Population models have incomplete reference coverage.</p><p><a style="color:#69f7ff" href="/dna">Return to DNA Lab</a></p></body></html>'; return;
}
if (in_array($path, ['/', '/dna', '/dna.php'], true)) {
    require __DIR__ . '/dna.php'; return;
}
if ($path === '/api_library.php') {
    $input = $_POST ?: json_decode((string) file_get_contents('php://input'), true);
    $action = is_array($input) ? (string) ($input['action'] ?? '') : '';
    $allowed = ['dna_status', 'dna_raw_file', 'dna_health_file', 'dna_heritage_file',
        'dna_full_file', 'dna_lineage_file', 'dna_sound_file', 'dna_theory_file', 'dna_ancient_file'];
    if (!in_array($action, $allowed, true)) {
        http_response_code(403); header('Content-Type: application/json');
        echo json_encode(['status'=>'error','message'=>'This local mode supports DNA analysis only. External research services and account tools require a separately configured server.']); return;
    }
    require __DIR__ . '/api_library.php'; return;
}
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['status'=>'error','message'=>'This route is unavailable in local DNA Lab mode.']);
