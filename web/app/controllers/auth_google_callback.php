<?php
// Legacy MVC callback shim. Keep one audited OAuth implementation.
$query = [];
foreach (['code', 'state', 'error'] as $key) {
    if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
        $query[$key] = (string) $_GET[$key];
    }
}
$target = '/auth/callback' . ($query ? '?' . http_build_query($query) : '');
header('Location: ' . $target, true, 302);
exit;
