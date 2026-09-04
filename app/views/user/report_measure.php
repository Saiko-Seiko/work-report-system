<?php
/**
 * 2-4 測定値・報告事項登録_画面
 * 幅768pxに5列を並べると窮屈なので、1件を1枚のカードにして縦に積んでいる。
 * 横スクロールさせるより、現場で押し間違えにくい。
 *
 * @var array $report @var array $rows @var array $models @var array $texts
 * @var bool $canAddRow @var array $errors @var string $step @var array $progress
 */
require APP_ROOT . '/app/views/partials/step_nav.php';
?>

<?php if ($errors): ?>
<div class="alert alert--error">入力内容をご確認ください。</div>
<?php endif; ?>

<form method="post" action="/report/<?= (int) $report['id'] ?>/measure" novalidate <?= report_form_attrs($report, $step) ?>>
  <?= csrf_field() ?>

<?php foreach ($rows as $i => $row): ?>
<?php $rid = (int) $row['id']; ?>
  <div class="measure-card">
    <div class="measure-card__no"><?= $i + 1 ?></div>

    <div class="measure-grid">
      <label>
        <span>部屋名</span>
        <input class="input" type="text" name="m[<?= $rid ?>][room_name]"
               value="<?= h((string) $row['room_name']) ?>" data-mic="1" placeholder="例）BCR1">
      </label>

      <label>
        <span>型式</span>
        <select class="select" name="m[<?= $rid ?>][model_name]">
          <option value="">選択してください</option>
<?php foreach ($models as $name): ?>
          <option value="<?= h($name) ?>" <?= $row['model_name'] === $name ? 'selected' : '' ?>>
            <?= h($name) ?>
          </option>
<?php endforeach; ?>
<?php if ($row['model_name'] && !in_array($row['model_name'], $models, true)): ?>
          <option value="<?= h($row['model_name']) ?>" selected><?= h($row['model_name']) ?></option>
<?php endif; ?>
        </select>
      </label>

      <label>
        <span>積算時間</span>
        <span class="input-unit">
          <input class="input <?= isset($errors["m.$rid.cumulative_hours"]) ? 'is-error' : '' ?>"
                 type="text" inputmode="numeric" pattern="[0-9]*"
                 name="m[<?= $rid ?>][cumulative_hours]"
                 value="<?= $row['cumulative_hours'] === null ? '' : (int) $row['cumulative_hours'] ?>"
                 maxlength="6" placeholder="0〜100000">
          <em>h</em>
        </span>
      </label>

      <label>
        <span>製造No.</span>
        <input class="input <?= isset($errors["m.$rid.serial_no"]) ? 'is-error' : '' ?>"
               type="text" name="m[<?= $rid ?>][serial_no]"
               value="<?= h((string) $row['serial_no']) ?>"
               maxlength="6" placeholder="6桁" spellcheck="false">
      </label>

      <label>
        <span>製造年月</span>
        <input class="input <?= isset($errors["m.$rid.manufactured_ym"]) ? 'is-error' : '' ?>"
               type="month" name="m[<?= $rid ?>][manufactured_ym]"
               value="<?= h((string) $row['manufactured_ym']) ?>">
      </label>
    </div>

<?php foreach (['cumulative_hours', 'serial_no', 'manufactured_ym'] as $f): ?>
<?php if (isset($errors["m.$rid.$f"])): ?>
    <p class="field-error" style="margin-left:0"><?= h($errors["m.$rid.$f"]) ?></p>
<?php endif; ?>
<?php endforeach; ?>
  </div>
<?php endforeach; ?>

<?php if ($canAddRow): ?>
  <p style="text-align:center; margin:4px 0 18px">
    <button class="btn btn--ghost btn--sm" type="submit" name="add_row" value="1" formnovalidate>
      ＋ 行を追加
    </button>
  </p>
<?php endif; ?>

  <div class="report-body-head">
    <label class="block-label mb0" for="report_body">報告事項</label>
    <details class="text-picker">
      <summary class="btn btn--sm">選択</summary>
      <div class="text-picker__body">
        <p class="muted" style="font-size:13px; margin-top:0">
          報告事項テーブルに登録されている文章です。選んで「追記」を押すと本文の下に足されます。
        </p>
        <ul class="check-list">
<?php foreach ($texts as $t): ?>
          <li>
            <label>
              <input type="checkbox" name="text_ids[]" value="<?= (int) $t['id'] ?>">
              <span>
                <?= h($t['body']) ?>
<?php if ($t['account_id'] === null): ?>
                <small class="muted">（共通）</small>
<?php endif; ?>
              </span>
            </label>
          </li>
<?php endforeach; ?>
        </ul>
        <p style="text-align:center; margin-bottom:0">
          <button class="btn btn--sm" type="submit" name="insert_texts" value="1" formnovalidate>
            選んだ文章を追記
          </button>
        </p>
      </div>
    </details>
  </div>

  <textarea class="textarea textarea--tall" id="report_body" name="report_body" data-mic="1"
            placeholder="点検結果や特記事項を入力してください（マイク入力・定型文の追記も使えます）"><?= h((string) $report['report_body']) ?></textarea>

<?php
  $showBack = true;
  require APP_ROOT . '/app/views/partials/nav_buttons.php';
?>
</form>
