<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/core/google_auth.php';

$client = getGoogleClient();

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        if (isset($token['error'])) throw new Exception($token['error']);
        
        $client->setAccessToken($token['access_token']);
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        $email = $google_account_info->email;
        $name = $google_account_info->name;
        $avatar = $google_account_info->picture;
        
        // DATABASE CHECK
        $db = db();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // LOGIN EXISTING USER
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['avatar'] = $avatar; // Update avatar
            
            // Update DB avatar just in case
            $db->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$avatar, $user['id']]);
        } else {
            // REGISTER NEW USER
            $stmt = $db->prepare("INSERT INTO users (username, email, password, avatar, plan) VALUES (?, ?, 'google_auth_user', ?, 'free')");
            // Use name or email part as username
            $username = $name ?: explode('@', $email)[0];
            $stmt->execute([$username, $email, $avatar]);
            
            $_SESSION['user_id'] = $db->lastInsertId();
            $_SESSION['email'] = $email;
            $_SESSION['avatar'] = $avatar;
        }

        // Redirect to Studio
        header("Location: /studio");
        exit;

    } catch (Exception $e) {
        die("❌ Google Login Failed: " . $e->getMessage());
    }
} else {
    header("Location: /login");
    exit;
}
