<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

$email = trim((string) ($argv[1] ?? 'shafaet85@gmail.com'));
$label = trim((string) ($argv[2] ?? 'Shafaet admin ZeroThink API'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/mint_zerothink_api_key.php user@example.com \"Label\"\n");
    exit(2);
}

ensure_zerothink_api_tables();

$stmt = db()->prepare("SELECT id, email, plan FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    fwrite(STDERR, "No user found for {$email}\n");
    exit(1);
}

$user_id = (int) $user['id'];
if (strtolower((string) ($user['plan'] ?? '')) !== 'god_mode') {
    db()->prepare("UPDATE users SET plan = 'pro', is_pro = 1 WHERE id = ? LIMIT 1")->execute([$user_id]);
}

$issued = zerothink_api_issue_token($user_id, $label, 0, 365);
if (empty($issued['ok'])) {
    fwrite(STDERR, "Could not issue API key: " . (string) ($issued['error'] ?? 'unknown_error') . "\n");
    exit(1);
}

echo "ZeroThink API key created for {$email}\n";
echo "Copy this now. It will not be shown again.\n";
echo "Token: " . $issued['token'] . "\n";
echo "Hint: " . $issued['hint'] . "\n";
echo "Expires UTC: " . ($issued['expires_at'] ?: 'never') . "\n";
