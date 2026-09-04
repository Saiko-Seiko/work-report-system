<?php
/**
 * K-7-1 管理者情報 修正画面
 * @var array $form @var array $errors
 */
?>
<div class="login-wrap" style="padding:60px 0">
  <div class="profile-box">
    <h1 class="modal__title">管理者情報　修正</h1>

<?php if ($errors): ?>
    <div class="alert alert--error">
<?php foreach ($errors as $msg): ?>
      <div><?= h($msg) ?></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>

    <form method="post" action="/admin/profile" novalidate>
      <?= csrf_field() ?>

      <div class="modal__row">
        <label for="account_id">アカウントID：</label>
        <input class="input" type="text" id="account_id" name="account_id"
               value="<?= h($form['account_id']) ?>" spellcheck="false">
      </div>

      <div class="modal__row">
        <label for="password">パスワード：</label>
        <input class="input" type="password" id="password" name="password"
               autocomplete="new-password" placeholder="変更するときだけ入力">
      </div>

      <div class="modal__row">
        <label for="password_confirm">パスワード（再入力）：</label>
        <input class="input" type="password" id="password_confirm" name="password_confirm"
               autocomplete="new-password">
      </div>

      <div class="modal__row">
        <label for="notify_email">報告書の受信メールアドレス：</label>
        <input class="input" type="email" id="notify_email" name="notify_email"
               value="<?= h($form['email']) ?>" spellcheck="false">
      </div>
      <p class="modal__note">
        パスワードは半角英数字のみ、8文字以上。空欄のままなら変更しません。
      </p>

      <div class="modal__actions">
        <a class="btn btn--ghost" href="/admin/dashboard">キャンセル</a>
        <button class="btn btn--green" type="submit">登録</button>
      </div>
    </form>
  </div>
</div>
