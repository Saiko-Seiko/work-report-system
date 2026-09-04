<?php
/**
 * ［1 基本情報］［2 作業内容］［3 交換部品］［4 測定値・報告事項］［5 確認・署名］
 * 現在の画面は赤、入力済みは緑。入力済みの画面には直接飛べる。
 *
 * @var array $report @var string $step @var array $progress
 */
?>
<nav class="step-nav">
<?php foreach (Report::STEPS as $key => $meta): ?>
<?php
  $class = 'step-nav__item';
  if ($key === $step) {
      $class .= ' is-current';
  } elseif (!empty($progress[$key])) {
      $class .= ' is-done';
  }
  $label = $meta['no'] . ' ' . $meta['label'];
  $canJump = $key !== $step && !empty($progress[$key]);
?>
<?php if ($canJump): ?>
  <a class="<?= $class ?>" href="/report/<?= (int) $report['id'] ?>/<?= h($key) ?>"><?= h($label) ?></a>
<?php else: ?>
  <span class="<?= $class ?>"><?= h($label) ?></span>
<?php endif; ?>
<?php endforeach; ?>
</nav>
