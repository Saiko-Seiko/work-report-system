<?php
/**
 * 管理者サイト共通レイアウト（PC 1600 x 900）
 * @var string $content
 * @var string|null $title
 * @var string|null $nav    現在のタブ（dashboard|users|parts|models|texts）
 * @var bool|null $bare     ログイン画面などナビ無しで出す場合
 */
$title = $title ?? '管理者';
$nav   = $nav ?? '';
$bare  = $bare ?? false;

$tabs = [
    'dashboard' => ['ダッシュボード', '/admin/dashboard'],
    'users'     => ['ユーザー登録',   '/admin/users'],
    'parts'     => ['交換部品マスタ', '/admin/parts'],
    'models'    => ['機種名マスタ',   '/admin/models'],
    'texts'     => ['報告事項マスタ', '/admin/texts'],
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=1600">
<title><?= h($title) ?> | <?= h(config('app_name')) ?> 管理者</title>
<link rel="stylesheet" href="/assets/css/admin.css?v=1">
</head>
<body>
<div class="admin-shell">

  <header class="admin-header">
    <div><?= h(config('app_name')) ?></div>
<?php if ($bare): ?>
    <div>&nbsp;</div>
<?php else: ?>
    <div class="admin-header__right">
      <button type="button" class="admin-header__user" id="js-admin-menu">管理者 ▾</button>
      <div class="admin-menu" id="js-admin-menu-body" hidden>
        <a href="/admin/profile">・管理者情報</a>
        <a href="/admin/logout">・ログアウト</a>
      </div>
    </div>
<?php endif; ?>
  </header>

<?php if (!$bare): ?>
  <nav class="admin-nav">
<?php foreach ($tabs as $key => [$label, $href]): ?>
    <a href="<?= h($href) ?>" class="<?= $nav === $key ? 'is-current' : '' ?>"><?= h($label) ?></a>
<?php endforeach; ?>
  </nav>
<?php endif; ?>

  <div class="admin-body">
    <?= $content ?>
  </div>

</div>
<script>
document.getElementById('js-admin-menu')?.addEventListener('click', function () {
  var m = document.getElementById('js-admin-menu-body');
  if (m) { m.hidden = !m.hidden; }
});
</script>
</body>
</html>
