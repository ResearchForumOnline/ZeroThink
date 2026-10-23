<div class="section">
  <div class="card pad">
    <h2 style="margin:0 0 10px">Users</h2>
    <table class="table">
      <thead><tr><th>ID</th><th>Email</th><th>Role</th><th>Disabled</th><th>Created</th><th>Last login</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach($users as $u): ?>
        <tr>
          <td><?= e((string)$u['id']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['role']) ?></td>
          <td><?= e((string)$u['disabled']) ?></td>
          <td><?= e($u['created_at']) ?></td>
          <td><?= e($u['last_login_at'] ?? '') ?></td>
          <td>
            <form method="post" action="/admin/users/toggle">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="id" value="<?= e((string)$u['id']) ?>">
              <button class="btn secondary" type="submit"><?= ((int)$u['disabled']===1) ? 'Enable' : 'Disable' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
