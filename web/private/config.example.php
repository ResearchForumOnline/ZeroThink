<?php
/** Copy to ignored config.local.php only for an account-backed installation. */
return [
    'db' => [
        'dsn' => getenv('ZEROTHINK_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=zerothink;charset=utf8mb4',
        'user' => getenv('ZEROTHINK_DB_USER') ?: '',
        'pass' => getenv('ZEROTHINK_DB_PASSWORD') ?: '',
    ],
];
