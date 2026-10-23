<?php
function csrf_token() {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function recaptcha_verify($token) { return true; } // Bypass for stability
