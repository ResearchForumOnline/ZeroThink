<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$excluded = ['vendor', 'data', 'storage', 'private', 'config'];
$extensions = ['php', 'js', 'py', 'json', 'conf', 'ini'];
$patterns = [
    'provider token' => '/(?:sk-[A-Za-z0-9_-]{20,}|gsk_[A-Za-z0-9]{20,}|AIza[0-9A-Za-z_-]{30,}|GOCSPX-[0-9A-Za-z_-]{20,}|gh[pousr]_[0-9A-Za-z]{20,})/',
    'private key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
    'literal database password' => '/\$(?:pass|password|dbpass|db_password)\s*=\s*(["\'])[^"\']{12,}\1/i',
    'literal mapped password' => '/(["\'])(?:password|db_password)\1\s*=>\s*(["\'])[^"\']{12,}\2/i',
];

$failures = [];
$filesScanned = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $parts = explode('/', $relative);
    if (array_intersect($excluded, $parts)) {
        continue;
    }
    if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
        continue;
    }
    $source = (string) file_get_contents($file->getPathname());
    $filesScanned++;
    foreach ($patterns as $label => $pattern) {
        if (preg_match($pattern, $source)) {
            $failures[] = $relative . ': ' . $label;
        }
    }
}

$admin = (string) file_get_contents($root . '/admin.php');
$dbShim = (string) file_get_contents($root . '/app/core/database_fix.php');
$checkPro = (string) file_get_contents($root . '/check_pro.php');
if (strpos($admin, 'new PDO') !== false || strpos($admin, '$_GET') !== false) {
    $failures[] = 'admin.php: legacy credential or GET mutation remains';
}
if (strpos($dbShim, "require_once __DIR__ . '/db.php'") === false) {
    $failures[] = 'app/core/database_fix.php: protected DB shim not used';
}
if (strpos($checkPro, 'db()->prepare(') === false) {
    $failures[] = 'check_pro.php: protected DB connection not used';
}

if ($failures) {
    fwrite(STDERR, "FAIL: secret hygiene checks failed\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo 'OK: secret hygiene passed across ' . $filesScanned . " staged code files\n";

