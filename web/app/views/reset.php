<?php $t = $_GET['token'] ?? ''; ?>
<div class="section">
  <div class="card pad" style="max-width:560px;margin:0 auto">
    <h2 style="margin:0 0 10px">Set new password</h2>
    <?php if(!empty($error)): ?><div class="notice bad"><?= e($error) ?></div><?php endif; ?>
    <form class="form" method="post" action="/reset">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <label>Reset token</label>
      <input name="token" value="<?= e($t) ?>" required>
      <label>New password</label>
      <input name="password" type="password" required minlength="10">
      <button class="btn" type="submit">Update password</button>
      <a class="btn secondary" href="/login">Login</a>
    </form>
  </div>
</div>
