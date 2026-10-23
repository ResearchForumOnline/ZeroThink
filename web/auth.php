<?php
// Retired legacy endpoint. Canonical OAuth lives at /auth/google and
// /auth/callback, with state verification in google_callback.php.
require_once __DIR__ . '/app/bootstrap.php';

if (strtolower((string) ($_GET['action'] ?? '')) === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: /login');
    exit;
}

$return_to = sanitize_local_return_path($_GET['return'] ?? '', '/studio');
header('Location: /auth/google?return=' . rawurlencode($return_to), true, 302);
exit;
