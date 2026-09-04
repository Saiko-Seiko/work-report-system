<?php
/**
 * 1-1 ログイン画面（URL: /login）
 * @var string|null $error @var string $loginId
 */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1b7a46">
<title>ユーザーログイン | <?= h(config('app_name')) ?></title>
<link rel="stylesheet" href="/assets/css/app.css?v=2">
</head>
<body>
<div class="viewport">

  <header class="app-header">
    <div class="app-header__title"><?= h(config('app_name')) ?></div>
  </header>

  <main class="app-main">

<?php
      // デモ用の設置（Vercel）では、打ち間違いを防ぐため最初から入れておく
      $isDemo   = (bool) config('demo');
      $demoId   = $isDemo ? 'ABCDE0001' : '';
      $demoPass = $isDemo ? 'pass1234' : '';
?>
    <div class="login-panel">
      <h1 class="login-panel__title">ユーザーログイン</h1>

<?php if ($error): ?>
      <div class="alert alert--error" role="alert"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($isDemo && !$error): ?>
      <div class="alert alert--info" style="font-size:13px">
        デモ用のIDとパスワードを入れてあります。<br>
        そのまま <strong>「ログイン」</strong> を押してください。
      </div>
<?php endif; ?>

      <form method="post" action="/login" autocomplete="on" id="js-login">
        <?= csrf_field() ?>

        <div class="login-row">
          <label for="login_id">ユーザーID</label>
          <input class="input" type="text" id="login_id" name="login_id"
                 value="<?= h($loginId !== '' ? $loginId : $demoId) ?>" autocomplete="username"
                 inputmode="latin" autocapitalize="off" spellcheck="false" required>
        </div>

        <div class="login-row">
          <label for="password">パスワード</label>
          <input class="input" type="password" id="password" name="password"
                 value="<?= h($demoPass) ?>"
                 autocomplete="current-password" required>
        </div>

        <label class="login-keep">
          <input type="checkbox" name="remember" value="1" id="js-keep">
          <span>ユーザーID,パスワードを保持</span>
        </label>

        <div class="login-actions">
          <button type="submit" class="btn btn--login">ログイン</button>
        </div>
      </form>
    </div>

    <p class="login-help">
      パスワードを3回間違えるとロックがかかります。<br>
      ロックの解除、およびIDの発行は事務局にご連絡ください。
    </p>

  </main>
</div>

<script>
// 「保持」にチェックしていた端末では、次回ユーザーIDを入れ直さなくて済むようにする。
// パスワードは端末に保存しない（サーバーが発行した使い捨ての合鍵で自動ログインする）
(function () {
  var KEY = 'wcr.lastLoginId';
  var id = document.getElementById('login_id');
  var keep = document.getElementById('js-keep');
  if (!id || !keep) return;

  try {
    var saved = localStorage.getItem(KEY);
    if (saved && !id.value) { id.value = saved; keep.checked = true; }
  } catch (e) { /* プライベートブラウズ等では何もしない */ }

  document.getElementById('js-login').addEventListener('submit', function () {
    try {
      if (keep.checked) { localStorage.setItem(KEY, id.value); }
      else { localStorage.removeItem(KEY); }
    } catch (e) {}
  });
}());
</script>
</body>
</html>
