<?php

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo 'METHOD_NOT_ALLOWED';
    exit;
}

require_once __DIR__ . '/app/core/ipn_paypal.php';
ipn_paypal_handle();

