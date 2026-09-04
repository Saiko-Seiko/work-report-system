<?php
/**
 * 2-3 交換部品登録_画面
 * 部品は1万点あるので全部は出さない。「50音ソート」と「文字検索」で絞る。
 * 選んだ部品は検索し直しても消えないよう、上に固定して表示する。
 *
 * @var array $report @var array $selected @var array $selectedIds @var array $results
 * @var int $total @var int $limit @var string $q @var string $sort
 * @var string $step @var array $progress
 */
require APP_ROOT . '/app/views/partials/step_nav.php';
?>

<form method="post" action="/report/<?= (int) $report['id'] ?>/parts" <?= report_form_attrs($report, $step) ?>>
  <?= csrf_field() ?>
  <input type="hidden" name="sort_key" value="<?= h($sort) ?>">

  <div class="search-bar">
    <button class="btn btn--sm" type="submit" name="sort" value="1" formnovalidate>
      <?= $sort === 'kana' ? '50音順' : 'よく使う順' ?>
    </button>
    <input class="input" type="search" name="q" value="<?= h($q) ?>"
           placeholder="検索する文字を入れてください" data-mic="1">
    <button class="btn btn--sm" type="submit" name="search" value="1" formnovalidate>検索</button>
  </div>
  <p class="field-note" style="margin-left:0">
    全<?= number_format((int) Database::value('SELECT COUNT(*) FROM parts WHERE deleted_at IS NULL')) ?>点。
    <?= $sort === 'kana' ? 'ヨミガナの50音順' : '使用頻度の高い順' ?>に
    <?= h($q) !== '' ? '「' . h($q) . '」で絞り込み' : '先頭' ?>
    <?= number_format(min($limit, $total)) ?>件を表示（該当 <?= number_format($total) ?>件）
  </p>

<?php if ($selected): ?>
  <h2 class="menu-heading">選択済み（<?= count($selected) ?>点）</h2>
  <ul class="qty-list">
<?php foreach ($selected as $row): ?>
    <li class="qty-row is-selected">
      <span class="qty-row__name"><?= h($row['part_name']) ?></span>
      <span class="counter" data-counter>
        <button class="counter__btn" type="button" data-step="-1" aria-label="減らす">-</button>
        <input class="counter__value" type="text" inputmode="numeric" pattern="[0-9]*"
               name="qty[<?= (int) $row['part_id'] ?>]" value="<?= (int) $row['qty'] ?>"
               data-min="0" data-max="9999" aria-label="<?= h($row['part_name']) ?> の数量">
        <button class="counter__btn" type="button" data-step="1" aria-label="増やす">+</button>
      </span>
      <span class="counter__unit"><?= h($row['unit']) ?></span>
    </li>
<?php endforeach; ?>
  </ul>
  <p class="field-note" style="margin-left:0">数量を0にすると選択から外れます。</p>
<?php endif; ?>

  <h2 class="menu-heading"><?= $q !== '' ? '検索結果' : '部品一覧' ?></h2>
<?php if (!$results): ?>
  <div class="alert alert--warn">
    「<?= h($q) ?>」に一致する部品は見つかりませんでした。
    部品名でもヨミガナでも探せます（例：ミズ、フィルター、MIU-101）。
  </div>
<?php else: ?>
  <ul class="qty-list">
<?php foreach ($results as $part): ?>
<?php if (in_array((int) $part['id'], $selectedIds, true)) { continue; } ?>
    <li class="qty-row">
      <span class="qty-row__name">
        <?= h($part['name']) ?>
        <small class="muted"><?= h($part['kana']) ?></small>
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

  <label class="block-label" for="parts_note">自由記述</label>
  <textarea class="textarea" id="parts_note" name="parts_note" data-mic="1"
            placeholder="マスタに無い部品や、補足があれば入力してください"><?= h((string) $report['parts_note']) ?></textarea>

<?php
  $showBack = true;
  require APP_ROOT . '/app/views/partials/nav_buttons.php';
?>
</form>
