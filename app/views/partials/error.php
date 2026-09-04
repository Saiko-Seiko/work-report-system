<?php
/** @var int $status @var string $message @var string $detail */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($status) ?> | <?= h(config('app_name')) ?></title>
<link rel="stylesheet" href="/assets/css/app.css?v=1">
</head>
<body>
<div class="viewport">
  <header class="app-header">
    <div class="app-header__title"><?= h(config('app_name')) ?></div>
  </header>
  <main class="app-main">
    <h1 class="page-title"><?= h($status) ?></h1>
    <div class="alert alert--error"><?= h($message) ?></div>
<?php if ($detail !== ''): ?>
    <p class="mono muted"><?= h($detail) ?></p>
<?php endif; ?>
<?php if (config('debug')): ?>
    <p><a class="btn btn--ghost" href="/_dev">開発用インデックスへ</a></p>
<?php endif; ?>
  </main>
</div>
</body>
</html>
