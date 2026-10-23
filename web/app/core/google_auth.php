<?php
// Tries to find the Composer autoloader in common locations
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
} else {
    // If we can't find it, we can't run Google Auth
    die("❌ Error: Composer 'vendor/autoload.php' not found. Please run 'composer install' in the root directory.");
}

function getGoogleClient() {
    require_once dirname(__DIR__) . '/google_config.php';
    if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
        throw new RuntimeException('Google OAuth is not configured.');
    }
    $client = new Google_Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URL);
    $client->addScope("email");
    $client->addScope("profile");
    return $client;
}
