<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/core/pdf_creator.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

function zt_pdf_api_json($status, $payload) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    zt_pdf_api_json(405, ['ok' => false, 'error' => 'PDF Creator accepts POST requests only.']);
}

$user = current_user_record();
if (!$user) {
    zt_pdf_api_json(403, ['ok' => false, 'error' => 'Sign in to ZeroThink before creating a PDF.']);
}

$content_length = (int) (isset($_SERVER['CONTENT_LENGTH']) ? $_SERVER['CONTENT_LENGTH'] : 0);
if ($content_length > 180000) {
    zt_pdf_api_json(413, ['ok' => false, 'error' => 'This PDF payload is too large. Keep exports under about 120,000 characters.']);
}

$raw = file_get_contents('php://input');
$data = [];
if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
if (!$data && $_POST) {
    $data = $_POST;
}
if (!$data) {
    zt_pdf_api_json(400, ['ok' => false, 'error' => 'Send JSON or form fields with title and body.']);
}

$title = trim((string) (isset($data['title']) ? $data['title'] : 'ZeroThink PDF'));
$subtitle = trim((string) (isset($data['subtitle']) ? $data['subtitle'] : ''));
$author = trim((string) (isset($data['author']) ? $data['author'] : (isset($user['email']) ? $user['email'] : '')));
$body = trim((string) (isset($data['body']) ? $data['body'] : (isset($data['content']) ? $data['content'] : '')));

if ($body === '') {
    zt_pdf_api_json(422, ['ok' => false, 'error' => 'Add text before creating a PDF.']);
}
if (strlen($body) > 120000) {
    zt_pdf_api_json(413, ['ok' => false, 'error' => 'This document is too large for the safe PDF export limit. Shorten it and try again.']);
}

$filename_source = trim((string) (isset($data['filename']) ? $data['filename'] : $title));
$filename = zt_pdf_sanitize_filename($filename_source, 'zerothink-document');

try {
    $pdf = zt_pdf_create_bytes($title, $body, [
        'subtitle' => $subtitle,
        'author' => $author,
        'footer' => 'ZeroThink PDF Creator',
    ]);
} catch (Throwable $e) {
    error_log('ZeroThink PDF Creator failed.');
    zt_pdf_api_json(500, ['ok' => false, 'error' => 'PDF generation failed.']);
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
