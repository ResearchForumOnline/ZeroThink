<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/security.php';

// --- HELPERS ---
function current_user() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function is_logged_in() { return (bool)current_user(); }
function is_admin() {
    $u = current_user();
    return ($u && isset($u['role']) && $u['role'] === 'admin');
}
function is_pro() {
    $u = current_user();
    if (!$u) return false;
    $role = $u['role'] ?? 'user';
    $sub = $u['subscription_status'] ?? 'free';
    $plan = strtolower((string) ($u['plan'] ?? 'free'));
    return ($role === 'admin' || !empty($u['is_pro']) || $sub === 'active' || in_array($plan, ['pro', 'god_mode'], true));
}
function require_login() { if (!is_logged_in()) redirect('/login'); }
function require_admin() { if (!is_admin()) { http_response_code(403); die("Forbidden"); } }

// --- AUTH ---
function auth_login_get() { view('login', ['csrf' => csrf_token()]); }
function auth_login_post() {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (!$email || !$pass) { view('login', ['error'=>'Missing credentials']); return; }

    $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'] ?? ''; 
        redirect('/studio');
    } else {
        view('login', ['error'=>'Invalid credentials']);
    }
}
function auth_register_get() { view('register', ['csrf' => csrf_token()]); }
function auth_register_post() {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (strlen($pass) < 6) { view('register', ['error'=>'Password too short']); return; }
    
    $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) { view('register', ['error'=>'Email taken']); return; }

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = db()->prepare("INSERT INTO users (email, password, role, subscription_status) VALUES (?, ?, 'user', 'free')");
    $stmt->execute([$email, $hash]);

    $_SESSION['user_id'] = db()->lastInsertId();
    $_SESSION['email'] = $email;
    redirect('/studio');
}
function auth_logout() { session_destroy(); redirect('/login'); }

// --- GOOGLE PROTOCOL (legacy MVC aliases) ---
// Keep old internal callers functional without maintaining a second OAuth
// implementation or embedding credentials in application code.
function auth_google_start() {
    redirect('/auth/google');
}

function auth_google_handle() {
    $query = [];
    foreach (['code', 'state', 'error'] as $key) {
        if (isset($_GET[$key])) {
            $query[$key] = (string) $_GET[$key];
        }
    }
    redirect('/auth/callback' . ($query ? '?' . http_build_query($query) : ''));
}
