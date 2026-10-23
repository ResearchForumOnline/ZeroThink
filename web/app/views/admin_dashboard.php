<div class="section">
  <div class="card pad">
    <h2 style="margin:0 0 10px">Admin</h2>
    <div class="kv">
      <div class="item">
        <div class="k">Users</div>
        <div class="v"><?= e((string)$users) ?></div>
      </div>
      <div class="item">
        <div class="k">Logs</div>
        <div class="v">JSONL viewer built in</div>
      </div>
    </div>
    <div class="cta-row" style="margin-top:14px">
      <a class="btn" href="/admin/users">Manage users</a>
      <a class="btn secondary" href="/admin/logs">View logs</a>
      <a class="btn secondary" href="/settings">Site settings</a>
    </div>
  </div>
</div>
