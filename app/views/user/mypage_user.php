<?php
/**
 * 5-1 ユーザー情報_変更画面
 * IDは管理者サイトで発行するものなので、ここでは表示だけ。
 *
 * @var array $user @var array $form @var array $errors
 */
?>
<h1 class="page-title">ユーザー情報変更</h1>

<?php if ($errors): ?>
<div class="alert alert--error">入力内容をご確認ください。</div>
<?php endif; ?>

<form method="post" action="/mypage" novalidate>
  <?= csrf_field() ?>

  <div class="form-row">
    <label class="form-row__label">ID</label>
    <div class="form-row__body">
      <input class="input" type="text" value="<?= h($user['account_id']) ?>" readonly tabindex="-1">
    </div>
  </div>
  <p class="field-note">IDの発行・変更は事務局が行います。</p>

  <div class="form-row">
    <label class="form-row__label" for="password">パスワード</label>
    <div class="form-row__body">
      <input class="input <?= isset($errors['password']) ? 'is-error' : '' ?>"
             type="password" id="password" name="password"
             autocomplete="new-password" placeholder="変更するときだけ入力">
    </div>
  </div>
<?php if (isset($errors['password'])): ?>
  <p class="field-error"><?= h($errors['password']) ?></p>
<?php else: ?>
  <p class="field-note">半角英数字のみ、8文字以上。空欄のままなら変更しません。</p>
<?php endif; ?>

  <div class="form-row">
    <label class="form-row__label" for="password_confirm">（確認）</label>
    <div class="form-row__body">
      <input class="input <?= isset($errors['password_confirm']) ? 'is-error' : '' ?>"
             type="password" id="password_confirm" name="password_confirm"
             autocomplete="new-password" placeholder="同じものをもう一度">
    </div>
  </div>
<?php if (isset($errors['password_confirm'])): ?>
  <p class="field-error"><?= h($errors['password_confirm']) ?></p>
<?php endif; ?>

  <div class="form-row">
    <label class="form-row__label" for="email">メールアドレス</label>
    <div class="form-row__body">
      <input class="input <?= isset($errors['email']) ? 'is-error' : '' ?>"
             type="email" id="email" name="email" value="<?= h($form['email']) ?>"
             autocomplete="email" spellcheck="false">
    </div>
  </div>
<?php if (isset($errors['email'])): ?>
  <p class="field-error"><?= h($errors['email']) ?></p>
<?php else: ?>
  <p class="field-note">報告書のメール送信で、CCの初期値として使います。</p>
<?php endif; ?>

  <div class="form-row">
    <label class="form-row__label" for="company_name">会社名<span class="req">*</span></label>
    <div class="form-row__body">
      <input class="input <?= isset($errors['company_name']) ? 'is-error' : '' ?>"
             type="text" id="company_name" name="company_name"
             value="<?= h($form['company']) ?>" data-mic="1">
    </div>
  </div>
<?php if (isset($errors['company_name'])): ?>
  <p class="field-error"><?= h($errors['company_name']) ?></p>
<?php endif; ?>

  <div class="mypage-actions">
    <a class="btn btn--muted" href="/dashboard">もどる</a>
    <button class="btn" type="submit">登録</button>
  </div>
</form>
