<?php
// ENABLE DEBUGGING (Turn off after it works)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/google_config.php';

$db = db();

// --- STEP 1: REDIRECT TO GOOGLE (If no code) ---
if (!isset($_GET['code'])) {
    if (GOOGLE_CLIENT_ID === 'ENTER_YOUR_CLIENT_ID_HERE') {
        die("<h2 style='color:red; font-family:sans-serif;'>⚠️ CONFIG ERROR: You must edit app/google_config.php and paste your Google Client ID.</h2>");
    }

    $url = 'https://accounts.google.com/o/oauth2/v2/auth';
    $params = [
        'response_type' => 'code',
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URL,
        'scope' => 'email profile openid',
        'access_type' => 'online'
    ];
    header('Location: ' . $url . '?' . http_build_query($params));
    exit;
}

// --- STEP 2: EXCHANGE CODE FOR TOKEN ---
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'code' => $_GET['code'],
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URL,
    'grant_type' => 'authorization_code'
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($response['access_token'])) {
    die("Error getting token: " . print_r($response, true));
}

// --- STEP 3: GET USER PROFILE ---
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $response['access_token']]);
$googleUser = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($googleUser['email'])) {
    die("Error getting user profile.");
}

// --- STEP 4: LOGIN OR REGISTER ---
$email = $googleUser['email'];

// Check if user exists
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // REGISTER NEW USER (Free Tier)
    // We generate a random password since they use Google
    $passHash = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (email, password_hash, plan) VALUES (?, ?, 'free')");
    $stmt->execute([$email, $passHash]);
    $userId = $db->lastInsertId();
    
    // Fetch newly created user
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// SET SESSION
$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['plan'] = $user['plan'];

// REDIRECT TO STUDIO
header('Location: /studio');
exit;
