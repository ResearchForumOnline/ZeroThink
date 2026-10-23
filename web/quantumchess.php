<?php
require_once __DIR__ . '/app/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location:/');
    exit;
}

header('Location: /nottingham-research', true, 301);
exit;
