<?php
// Load System
require_once __DIR__ . '/app/bootstrap.php';

// Handle Logic
if (isset($_GET['code'])) {
    // We are returning from Google -> Handle Callback
    auth_google_handle();
} else {
    // We are starting -> Send to Google
    auth_google_start();
}
