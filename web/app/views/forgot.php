<div class="section">
  <div class="card pad" style="max-width:560px;margin:0 auto">
    <h2 style="margin:0 0 10px">Password reset</h2>
    <?php if(!empty($error)): ?><div class="notice bad"><?= e($error) ?></div><?php endif; ?>
    <?php if(!empty($ok)): ?><div class="notice ok"><?= e($ok) ?></div><?php endif; ?>
    <form id="forgotform" class="form" method="post" action="/forgot">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="g-recaptcha-response" id="gcap2">
      <label>Email</label>
      <input name="email" type="email" required>
      <button class="btn" type="submit">Send reset link</button>
      <a class="btn secondary" href="/login">Back</a>
    </form>
    <?php if(app_config('recaptcha.enabled', false) && !empty(app_config('recaptcha.site_key',''))): ?>
    <script>
      grecaptcha.ready(function(){
        grecaptcha.execute('<?= e(app_config('recaptcha.site_key','')) ?>', {action:'forgot'}).then(function(token){
          document.getElementById('gcap').value = token;
        });
      });
    </script>
    <?php endif; ?>
  </div>
</div>
