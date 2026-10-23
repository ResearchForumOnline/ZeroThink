<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/core/pdf_artifacts.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: private, no-store');

$user = current_user_record();
if (!$user) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Sign in to download this ZeroThink PDF.';
    exit;
}

$token = isset($_GET['t']) ? (string) $_GET['t'] : '';
$artifact = zt_pdf_artifact_open((int) $user['id'], $token);
if (!$artifact) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PDF artifact not found or expired.';
    exit;
}

$filename = zt_pdf_sanitize_filename(isset($artifact['filename']) ? $artifact['filename'] : 'zerothink-ai-report.pdf', 'zerothink-ai-report');
$path = (string) $artifact['path'];

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
