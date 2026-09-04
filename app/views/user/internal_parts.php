<?php
/**
 * 4-3 社内用報告書作成 再手配の必要な部材登録_画面
 *
 * 概要書のとおり、交換した部品を初期値として並べてから直す。
 * マスタに無いものを足したいときのために、文字検索も付けている。
 *
 * @var array $report @var array $internal @var array $selected @var array $selectedIds
 * @var array $results @var int $total @var string $q
 * @var string $step @var array $progress
 */
require APP_ROOT . '/app/views/partials/internal_step_nav.php';
?>

<div class="alert alert--info">
  客先へ提出した報告書の交換部品を初期値として表示しています。
  数量を直すか、0にして外してください。
</div>

<form method="post" action="/report/<?= (int) $report['id'] ?>/internal/parts"
      <?= internal_form_attrs($report, $step) ?>>
  <?= csrf_field() ?>

  <div class="search-bar">
    <input class="input" type="search" name="q" value="<?= h($q) ?>"
           placeholder="部品名・ヨミガナで検索" data-mic="1">
    <button class="btn btn--sm" type="submit" name="search" value="1" formnovalidate>検索</button>
  </div>

<?php if ($selected): ?>
  <h2 class="menu-heading">再手配する部材（<?= count($selected) ?>点）</h2>
  <ul class="qty-list">
<?php foreach ($selected as $row): ?>
    <li class="qty-row is-selected">
      <span class="qty-row__name"><?= h((string) $row['part_name']) ?></span>
      <span class="counter" data-counter>
        <button class="counter__btn" type="button" data-step="-1" aria-label="減らす">-</button>
        <input class="counter__value" type="text" inputmode="numeric" pattern="[0-9]*"
               name="qty[<?= (int) $row['part_id'] ?>]" value="<?= (int) $row['qty'] ?>"
               data-min="0" data-max="9999" aria-label="<?= h((string) $row['part_name']) ?> の数量">
        <button class="counter__btn" type="button" data-step="1" aria-label="増やす">+</button>
      </span>
      <span class="counter__unit"><?= h((string) $row['unit']) ?></span>
    </li>
<?php endforeach; ?>
  </ul>
  <p class="field-note" style="margin-left:0">数量を0にすると再手配の一覧から外れます。</p>
<?php else: ?>
  <div class="alert alert--warn">
    再手配する部材はまだありません。下の一覧から数量を入れてください。
  </div>
<?php endif; ?>

  <h2 class="menu-heading"><?= $q !== '' ? '検索結果' : '追加する部材を選ぶ' ?></h2>
<?php if (!$results): ?>
  <div class="alert alert--warn">
    「<?= h($q) ?>」に一致する部品は見つかりませんでした。
  </div>
<?php else: ?>
  <p class="field-note" style="margin-left:0">
    <?= $q !== '' ? '該当 ' . number_format($total) . '件のうち先頭20件' : 'よく使う部材の先頭20件' ?>
  </p>
  <ul class="qty-list">
<?php foreach ($results as $part): ?>
<?php if (in_array((int) $part['id'], $selectedIds, true)) { continue; } ?>
    <li class="qty-row">
      <span class="qty-row__name">
        <?= h($part['name']) ?>
        <small class="muted"><?= h((string) $part['kana']) ?></small>
      </span>
      <span class="counter" data-counter>
        <button class="counter__btn" type="button" data-step="-1" aria-label="減らす">-</button>
        <input class="counter__value" type="text" inputmode="numeric" pattern="[0-9]*"
               name="qty[<?= (int) $part['id'] ?>]" value="0"
               data-min="0" data-max="9999" aria-label="<?= h($part['name']) ?> の数量">
        <button class="counter__btn" type="button" data-step="1" aria-label="増やす">+</button>
      </span>
      <span class="counter__unit"><?= h($part['unit']) ?></span>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php
  $showBack = true;
  require APP_ROOT . '/app/views/partials/nav_buttons.php';
?>
</form>
