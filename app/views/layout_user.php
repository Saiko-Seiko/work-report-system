<?php
/**
 * ユーザーサイト共通レイアウト（タブレット 768 x 1024 / 高さはスクロール）
 * @var string $content
 * @var string|null $title
 * @var bool|null $showSync    同期ステータスバーを出すか（Phase 4）
 * @var string|null $navHtml   画面下の もどる／つぎへ
 */
$title    = $title ?? config('app_name');
$showSync = $showSync ?? false;
$navHtml  = $navHtml ?? '';
$user     = Auth::user();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="format-detection" content="telephone=no">
<meta name="theme-color" content="#1b7a46">
<title><?= h($title) ?> | <?= h(config('app_name')) ?></title>
<link rel="stylesheet" href="/assets/css/app.css?v=1">
</head>
<body>
<div class="viewport">

  <header class="app-header">
    <div class="app-header__title"><?= h(config('app_name')) ?></div>
    <button class="app-header__menu" type="button" id="js-menu" aria-label="メニュー">≡</button>
  </header>

<?php if ($showSync): ?>
  <div class="sync-bar" id="js-sync" data-state="synced">
    <span class="sync-dot"></span>
    <span id="js-sync-text">サーバー送信済み</span>
    <button type="button" class="sync-bar__push" id="js-sync-push" hidden>今すぐ送信</button>
  </div>
<?php endif; ?>

  <nav class="card" id="js-drawer" hidden style="margin:10px 16px 0">
    <ul class="menu-list">
      <li><a href="/dashboard">ダッシュボード</a></li>
      <li><a href="/report/new">報告書を新規作成</a></li>
      <li><a href="/reports">報告書一覧</a></li>
      <li><a href="/mypage">マイページ</a></li>
    </ul>
<?php if ($user): ?>
    <form method="post" action="/logout" style="margin-top:10px">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--muted btn--block">
        ログアウト（<?= h($user['account_id']) ?>）
      </button>
    </form>
<?php endif; ?>
  </nav>

  <main class="app-main">
    <?= $content ?>
  </main>

<?= $navHtml ?>

</div>
<script>
document.getElementById('js-menu')?.addEventListener('click', function () {
  var d = document.getElementById('js-drawer');
  if (d) { d.hidden = !d.hidden; }
});
</script>
<script src="/assets/js/app.js?v=1"></script>
<script src="/assets/js/offline.js?v=1"></script>
<script src="/assets/js/mic.js?v=1"></script>
</body>
</html>
