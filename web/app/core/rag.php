<?php
declare(strict_types=1);

function docs_bootstrap(): array {
  $uid = (int)($_SESSION['uid'] ?? 0);
  $st = db()->prepare("SELECT * FROM docs WHERE user_id=? ORDER BY id DESC LIMIT 50");
  $st->execute([$uid]);
  return ['csrf'=>csrf_token(), 'docs'=>$st->fetchAll()];
}

function docs_upload(): void {
  csrf_validate($_POST['csrf'] ?? null);
  $uid = (int)($_SESSION['uid'] ?? 0);
  if(empty($_FILES['doc']) || $_FILES['doc']['error'] !== UPLOAD_ERR_OK){
    redirect('/docs?err=upload'); 
  }
  $f = $_FILES['doc'];
  $name = basename($f['name']);
  $mime = $f['type'] ?: 'application/octet-stream';
  $bytes = (int)$f['size'];
  if($bytes > 10*1024*1024){ redirect('/docs?err=too_big'); }
  $allowed = ['text/plain','text/markdown','text/html','application/json'];
  if(!in_array($mime,$allowed,true)){
    // allow by extension too
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if(!in_array($ext,['txt','md','html','json'],true)){ redirect('/docs?err=type'); }
  }
  $dir = __DIR__ . '/../../storage/docs/'.$uid;
  if(!is_dir($dir)) @mkdir($dir, 0770, true);
  $target = $dir.'/'.time().'_'.preg_replace('/[^a-zA-Z0-9._-]+/','_',$name);
  move_uploaded_file($f['tmp_name'], $target);

  db()->prepare("INSERT INTO docs(user_id,title,filename,mime,bytes,created_at) VALUES(?,?,?,?,?,?)")
    ->execute([$uid, pathinfo($name, PATHINFO_FILENAME), basename($target), $mime, $bytes, now()]);
  log_event('docs_upload', ['filename'=>basename($target),'bytes'=>$bytes]);
  redirect('/docs?ok=1');
}

function docs_reindex(): void {
  csrf_validate($_POST['csrf'] ?? null);
  $uid = (int)($_SESSION['uid'] ?? 0);

  $st = db()->prepare("SELECT * FROM docs WHERE user_id=? ORDER BY id DESC LIMIT 200");
  $st->execute([$uid]);
  $docs = $st->fetchAll();

  $basedir = __DIR__ . '/../../storage/docs/'.$uid;
  foreach($docs as $d){
    $docId = (int)$d['id'];
    $file = $basedir.'/'.$d['filename'];
    if(!is_file($file)) continue;
    $txt = file_get_contents($file);
    if($txt === false) continue;

    // Remove old chunks
    db()->prepare("DELETE FROM doc_chunks WHERE doc_id=?")->execute([$docId]);

    // Chunking: ~900-1200 chars with overlap
    $clean = trim(strip_tags($txt));
    $chunks = chunk_text($clean, 1100, 180);
    $i = 0;
    foreach($chunks as $c){
      $hash = hash('sha256',$c);
      db()->prepare("INSERT INTO doc_chunks(doc_id,chunk_no,content,content_hash,created_at) VALUES(?,?,?,?,?)")
        ->execute([$docId, $i, $c, $hash, now()]);
      $i++;
    }
    log_event('docs_reindex_doc', ['doc_id'=>$docId,'chunks'=>$i]);
  }
  log_event('docs_reindex', ['docs'=>count($docs)]);
  redirect('/docs?reindexed=1');
}

function chunk_text(string $text, int $size, int $overlap): array {
  $out = [];
  $n = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
  $pos = 0;
  while($pos < $n){
    $chunk = function_exists('mb_substr') ? mb_substr($text, $pos, $size) : substr($text, $pos, $size);
    $chunk = trim($chunk);
    if($chunk !== '') $out[] = $chunk;
    $pos += max(1, $size - $overlap);
  }
  return $out;
}

function rag_retrieve(int $uid, string $query, int $k=5): array {
  // Basic FULLTEXT in MySQL; requires InnoDB fulltext available (most installs)
  $st = db()->prepare("SELECT dc.content, d.title, d.id as doc_id 
                       FROM doc_chunks dc 
                       JOIN docs d ON d.id = dc.doc_id
                       WHERE d.user_id=?
                       AND MATCH(dc.content) AGAINST(? IN NATURAL LANGUAGE MODE)
                       LIMIT ?");
  $st->bindValue(1, $uid, PDO::PARAM_INT);
  $st->bindValue(2, $query, PDO::PARAM_STR);
  $st->bindValue(3, $k, PDO::PARAM_INT);
  $st->execute();
  return $st->fetchAll();
}
