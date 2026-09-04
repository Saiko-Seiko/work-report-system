<?php
/**
 * 2-2 作業内容登録_画面
 * 作業対象の機種と台数を入れる。対象外の機種は削除して一覧から外す。
 *
 * @var array $report @var array $rows @var array $available @var array $usedIds
 * @var string $step @var array $progress
 */
require APP_ROOT . '/app/views/partials/step_nav.php';
$notAdded = array_filter($available, fn($m) => !in_array((int) $m['id'], $usedIds, true));
?>

<form method="post" action="/report/<?= (int) $report['id'] ?>/work" <?= report_form_attrs($report, $step) ?>>
  <?= csrf_field() ?>

<?php if (!$rows): ?>
  <div class="alert alert--warn">
    機種がすべて削除されています。下の「機種を戻す」から追加できます。
  </div>
<?php endif; ?>

  <ul class="qty-list">
<?php foreach ($rows as $row): ?>
    <li class="qty-row">
      <span class="qty-row__name"><?= h($row['model_name']) ?></span>
      <span class="counter" data-counter>
        <button class="counter__btn" type="button" data-step="-1" aria-label="減らす">-</button>
        <input class="counter__value" type="text" inputmode="numeric" pattern="[0-9]*"
               name="qty[<?= (int) $row['id'] ?>]" value="<?= (int) $row['qty'] ?>"
               data-min="0" data-max="999" aria-label="<?= h($row['model_name']) ?> の台数">
        <button class="counter__btn" type="button" data-step="1" aria-label="増やす">+</button>
      </span>
      <button class="qty-row__del" type="submit" name="delete_id"
              value="<?= (int) $row['id'] ?>" formnovalidate>削除</button>
    </li>
<?php endforeach; ?>
  </ul>

<?php if ($notAdded): ?>
  <details class="restore">
    <summary class="btn btn--ghost btn--sm">機種を戻す（<?= count($notAdded) ?>件）</summary>
    <ul class="restore__list">
<?php foreach ($notAdded as $m): ?>
      <li>
        <span><?= h($m['name']) ?></span>
        <button class="btn btn--sm btn--ghost" type="submit" name="add_model"
                value="<?= (int) $m['id'] ?>" formnovalidate>追加</button>
      </li>
<?php endforeach; ?>
    </ul>
  </details>
<?php endif; ?>

  <label class="block-label" for="work_note">任意入力</label>
  <textarea class="textarea" id="work_note" name="work_note" data-mic="1"
            placeholder="例）以上、保守点検作業一式"><?= h((string) $report['work_note']) ?></textarea>

<?php
  $showBack = true;
  require APP_ROOT . '/app/views/partials/nav_buttons.php';
?>
</form>
