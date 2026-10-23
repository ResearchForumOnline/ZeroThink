<?php
declare(strict_types=1);

function admin_bootstrap(): array {
  $st = db()->query("SELECT COUNT(*) as c FROM users");
  $users = (int)($st->fetch()['c'] ?? 0);
  return ['csrf'=>csrf_token(),'users'=>$users];
}

function admin_users_bootstrap(): array {
  $st = db()->query("SELECT id,email,role,disabled,created_at,last_login_at FROM users ORDER BY id DESC LIMIT 200");
  return ['csrf'=>csrf_token(), 'users'=>$st->fetchAll()];
}

function admin_users_toggle(): void {
  csrf_validate($_POST['csrf'] ?? null);
  $id = (int)($_POST['id'] ?? 0);
  $st = db()->prepare("SELECT disabled FROM users WHERE id=?");
  $st->execute([$id]);
  $u = $st->fetch();
  if(!$u){ redirect('/admin/users'); }
  $new = ((int)$u['disabled']===1) ? 0 : 1;
  db()->prepare("UPDATE users SET disabled=? WHERE id=?")->execute([$new,$id]);
  log_event('admin_user_toggle', ['id'=>$id,'disabled'=>$new]);
  redirect('/admin/users');
}

function admin_logs_bootstrap(): array {
  $dir = __DIR__ . '/../../storage/logs';
  $files = [];
  if(is_dir($dir)){
    foreach(scandir($dir) as $f){
      if(str_starts_with($f,'events-') && str_ends_with($f,'.jsonl')) $files[] = $f;
    }
    rsort($files);
  }
  $selected = $_GET['file'] ?? ($files[0] ?? '');
  $rows = [];
  if($selected && is_file($dir.'/'.$selected)){
    $lines = array_slice(file($dir.'/'.$selected, FILE_IGNORE_NEW_LINES) ?: [], -500);
    foreach($lines as $ln){
      $j = json_decode($ln, true);
      if(is_array($j)) $rows[] = $j;
    }
    $rows = array_reverse($rows);
  }
  return ['files'=>$files,'selected'=>$selected,'rows'=>$rows,'csrf'=>csrf_token()];
}
