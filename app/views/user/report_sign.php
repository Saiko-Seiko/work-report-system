<?php
/**
 * 2-6 サイン入力_画面
 * 概要書のとおり1画面だけ独立させている（唯一、病院の方が操作する画面）。
 * ヘッダーもメニューも出さず、書く場所だけに集中できるようにする。
 *
 * @var array $report @var string|null $error
 */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
<meta name="theme-color" content="#1b7a46">
<title>サイン入力 | <?= h(config('app_name')) ?></title>
<link rel="stylesheet" href="/assets/css/app.css?v=3">
</head>
<body class="sign-page">
<div class="viewport">

  <header class="app-header">
    <div class="app-header__title"><?= h(config('app_name')) ?></div>
  </header>

  <main class="app-main">
<?php if ($error): ?>
    <div class="alert alert--error"><?= h($error) ?></div>
<?php endif; ?>

    <p class="sign-lead">点線内に署名をしてください</p>

    <div class="sign-canvas-wrap">
      <canvas id="js-pad" class="sign-canvas"></canvas>
      <span class="sign-canvas__hint" id="js-hint">ここに指またはペンで署名</span>
    </div>

    <form method="post" action="/report/<?= (int) $report['id'] ?>/sign" id="js-sign-form" <?= report_form_attrs($report, 'sign') ?>>
      <?= csrf_field() ?>
      <input type="hidden" name="image" id="js-image">

      <div class="sign-actions">
        <a class="btn btn--muted" href="/report/<?= (int) $report['id'] ?>/confirm">やめる</a>
        <button class="btn btn--muted" type="button" id="js-clear">消去</button>
        <button class="btn" type="submit" id="js-save" disabled>保存</button>
      </div>
    </form>

    <p class="sign-note">
      書き直すときは「消去」を押してください。<br>
      保存すると確認署名の画面に戻ります。
    </p>
  </main>

</div>
<script src="/assets/js/sign.js?v=1"></script>
<script src="/assets/js/offline.js?v=1"></script>
</body>
</html>
