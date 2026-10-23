<?php
function view($path, $data = []) {
    extract($data);
    $file = __DIR__ . '/../../app/views/' . $path . '.php';
    if (file_exists($file)) {
        require $file;
    } else {
        $flat = __DIR__ . '/../../' . $path . '.php';
        if (file_exists($flat)) { require $flat; return; }
        die("Critical Error: View [$path] not found.");
    }
}

function e($str) {
    return htmlspecialchars($str ? $str : '', ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header("Location: $path");
    exit;
}
