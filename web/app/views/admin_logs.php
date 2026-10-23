<div class="section">
  <div class="card pad">
    <h2 style="margin:0 0 10px">Logs</h2>
    <form class="row" method="get" action="/admin/logs">
      <select name="file">
        <?php foreach($files as $f): ?>
          <option value="<?= e($f) ?>" <?= $f===$selected?'selected':'' ?>><?= e($f) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn secondary" type="submit">Load</button>
    </form>
    <hr class="sep">
    <div style="max-height:520px;overflow:auto;border:1px solid var(--line);border-radius:14px;padding:10px;background:rgba(255,255,255,.02)">
      <?php foreach($rows as $r): ?>
        <div class="msg">
          <div class="meta">
            <span class="chip"><?= e($r['ts'] ?? '') ?></span>
            <span class="chip"><?= e($r['type'] ?? '') ?></span>
            <span class="chip">uid: <?= e((string)($r['user_id'] ?? '')) ?></span>
          </div>
          <div class="content"><?= e(json_encode($r['payload'] ?? new stdClass(), JSON_UNESCAPED_SLASHES)) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
