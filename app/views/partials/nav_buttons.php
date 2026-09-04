<?php
/**
 * 画面下の ◀もどる ／ ▶つぎへ。
 * フォームの中に置いて submit として動かす（押した時点で必ず保存される）。
 *
 * @var bool|null $showBack
 * @var string|null $nextLabel
 */
$showBack  = $showBack ?? true;
$nextLabel = $nextLabel ?? 'つぎへ';
?>
<div class="nav-buttons">
<?php if ($showBack): ?>
  <button type="submit" name="back" value="1" class="nav-btn" formnovalidate>
    <span class="nav-btn__mark">&#9664;</span>
    <span>もどる</span>
  </button>
<?php endif; ?>
  <button type="submit" name="next" value="1" class="nav-btn nav-btn--primary">
    <span class="nav-btn__mark">&#9654;</span>
    <span><?= h($nextLabel) ?></span>
  </button>
</div>
