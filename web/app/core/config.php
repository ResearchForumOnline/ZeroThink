<?php
declare(strict_types=1);
function app_root() { return realpath(__DIR__ . '/../../'); }
function app_config($key, $default = null) {
  static $env = null;
  static $private = null;
  if (!$env) $env = [];
  if (is_file(app_root().'/.env')) {
    $env = array_merge(@parse_ini_file(app_root().'/.env', false, INI_SCANNER_TYPED) ?: [], $env);
  }
  $map = ['db.host'=>'DB_HOST','db.name'=>'DB_NAME','db.user'=>'DB_USER','db.pass'=>'DB_PASS','app.url'=>'APP_BASE_URL'];
  $map['serper.api_key'] = 'SERPER_API_KEY';
  $map['openzero.api_base_url'] = 'OPENZERO_API_BASE_URL';
  $map['openzero.api_key'] = 'OPENZERO_API_KEY';
  if (isset($map[$key])) {
    $process = getenv($map[$key]);
    if ($process !== false && $process !== '') return $process;
    if (isset($env[$map[$key]])) return $env[$map[$key]];
  }

  if ($private === null) {
    $private = [];
    $basePath = app_root().'/private/config.php';
    $localPath = app_root().'/private/config.local.php';
    $base = is_file($basePath) ? require $basePath : [];
    $local = is_file($localPath) ? require $localPath : [];
    if (is_array($base)) $private = $base;
    if (is_array($local)) $private = array_replace_recursive($private, $local);
  }

  $value = $private;
  foreach (explode('.', (string) $key) as $segment) {
    if (!is_array($value) || !array_key_exists($segment, $value)) {
      return $default;
    }
    $value = $value[$segment];
  }
  if ($value !== null && $value !== '') return $value;
  return $default;
}
