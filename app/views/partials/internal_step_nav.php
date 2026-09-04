<?php
/**
 * 社内用報告書のステップ表示。
 * ［1 基本情報］［2 今回作業時の残作業］［3.再手配の必要な部材］
 * ［4 移動および作業時間の推移］［5 客先への営業アプローチ］［6.備考（社内への報告事項等）］
 *
 * 6は5と同じ画面なので、5が現在地のときは一緒に赤くする。
 *
 * @var array $report @var string $step @var array $progress
 */
$id = (int) $report['id'];
?>
<nav class="step-nav step-nav--internal">
<?php foreach (InternalReport::STEPS as $key => $meta): ?>
<?php
  $class = 'step-nav__item';
  if ($key === $step) {
      $class .= ' is-current';
  } elseif (!empty($progress[$key])) {
      $class .= ' is-done';
  }
  $label   = $meta['no'] . ' ' . $meta['label'];
  $canJump = $key !== $step && !empty($progress[$key]);
?>
<?php if ($canJump): ?>
  <a class="<?= $class ?>" href="/report/<?= $id ?>/internal/<?= h($key) ?>"><?= h($label) ?></a>
<?php else: ?>
  <span class="<?= $class ?>"><?= h($label) ?></span>
<?php endif; ?>
<?php endforeach; ?>
  <span class="step-nav__item<?= $step === 'sales' ? ' is-current' : (!empty($progress['sales']) ? ' is-done' : '') ?>">6.備考（社内への報告事項等）</span>
</nav>
