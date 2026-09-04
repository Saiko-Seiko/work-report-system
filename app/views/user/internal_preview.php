<?php
/**
 * 4-7 社内用報告書 プレビュー画面 ／ 4-8 印刷_画面
 *
 * @var array  $report
 * @var string $mode  preview|print
 */
$id      = (int) $report['id'];
$isPrint = $mode === 'print';
$src     = "/report/{$id}/internal/sheet" . ($isPrint ? '?print=1' : '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#1b7a46">
<title><?= $isPrint ? '社内用 印刷' : '社内用 プレビュー' ?> | <?= h(config('app_name')) ?></title>
<link rel="stylesheet" href="/assets/css/app.css?v=5">
</head>
<body class="paper-page">
<div class="viewport viewport--wide">

  <header class="app-header">
    <div class="app-header__title"><?= h(config('app_name')) ?></div>
    <a class="app-header__close" href="/report/<?= $id ?>/internal/confirm">［×閉じる］</a>
  </header>

  <div class="paper-bar">
    <span>社内用　No.<?= (int) $report['report_no'] ?>　<?= h((string) $report['hospital_name']) ?></span>
<?php if ($isPrint): ?>
    <button class="btn btn--sm" type="button" id="js-reprint">もう一度印刷</button>
<?php else: ?>
    <a class="btn btn--sm" href="/report/<?= $id ?>/internal/print">印刷する</a>
<?php endif; ?>
  </div>

  <div class="paper-stage">
    <iframe class="paper-frame" id="js-sheet" src="<?= h($src) ?>"
            title="社内用報告書"></iframe>
  </div>

</div>
<script>
(function () {
  var frame = document.getElementById('js-sheet');
  if (!frame) return;

  function fit() {
    try {
      var doc = frame.contentDocument;
      if (!doc || !doc.body) return;
      frame.style.height = (doc.documentElement.scrollHeight + 24) + 'px';
    } catch (e) {}
  }
  frame.addEventListener('load', function () { setTimeout(fit, 60); });
  window.addEventListener('resize', fit);

  var again = document.getElementById('js-reprint');
  if (again) {
    again.addEventListener('click', function () {
      try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch (e) {}
    });
  }
}());
</script>
</body>
</html>
