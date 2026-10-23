<?php
declare(strict_types=1);

// Compatibility shim for historical internal includes. Database credentials
// are loaded only by the protected configuration path used by db.php.
require_once __DIR__ . '/db.php';
$GLOBALS['db'] = db();

