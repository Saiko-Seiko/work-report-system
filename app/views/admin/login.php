<?php
/**
 * K-1 管理者ログイン画面（URL: /admin/login）
 * @var string|null $error @var string $loginId
 */
?>
<div class="login-wrap">
  <div class="login-box">
    <h1>管理サイトログイン</h1>

<?php if ($error): ?>
    <div class="alert alert--error" role="alert"><?= h($error) ?></div>
<?php endif; ?>

    <form method="post" action="/admin/login" id="js-admin-login">
      <?= csrf_field() ?>

      <div class="modal__row">
        <label for="login_id">ユーザーID</label>
        <input class="input" type="text" id="login_id" name="login_id"
               value="<?= h($loginId) ?>" autocomplete="username" spellcheck="false" required>
      </div>

      <div class="modal__row">
        <label for="password">パスワード</label>
        <input class="input" type="password" id="password" name="password"
               autocomplete="current-password" required>
      </div>

      <div class="modal__row">
        <label>&nbsp;</label>
        <label style="flex:1 1 auto; display:flex; align-items:center; gap:6px; cursor:pointer">
          <input type="checkbox" name="remember" value="1" id="js-keep">
          <span>ユーザーID,パスワードを保持</span>
        </label>
      </div>

      <div class="modal__actions">
        <button type="submit" class="btn">ログイン</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var KEY = 'wcr.lastAdminId';
  var id = document.getElementById('login_id');
  var keep = document.getElementById('js-keep');
  if (!id || !keep) return;
  try {
    var saved = localStorage.getItem(KEY);
    if (saved && !id.value) { id.value = saved; keep.checked = true; }
  } catch (e) {}
  document.getElementById('js-admin-login').addEventListener('submit', function () {
    try {
      if (keep.checked) { localStorage.setItem(KEY, id.value); }
      else { localStorage.removeItem(KEY); }
    } catch (e) {}
  });
}());
</script>
