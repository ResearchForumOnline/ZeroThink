<?php

function zerothink_private_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $root = dirname(__DIR__, 2);
    foreach ([$root . '/private/config.local.php', $root . '/private/config.php'] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $loaded = require $path;
        if (is_array($loaded) && !empty($loaded['db'])) {
            $config = $loaded;
            return $config;
        }
    }

    $dsn = trim((string) getenv('ZEROTHINK_DB_DSN'));
    if ($dsn !== '') {
        return $config = ['db'=>['dsn'=>$dsn,'user'=>(string) getenv('ZEROTHINK_DB_USER'),'pass'=>(string) getenv('ZEROTHINK_DB_PASSWORD')]];
    }
    throw new RuntimeException('Private database configuration is unavailable.');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = zerothink_private_config()['db'];
    try {
        $pdo = new PDO((string) $db['dsn'], (string) $db['user'], (string) $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
        return $pdo;
    } catch (PDOException $error) {
        error_log('ZeroThink database connection failed.');
        throw new RuntimeException('Database connection unavailable.', 0, $error);
    }
}
