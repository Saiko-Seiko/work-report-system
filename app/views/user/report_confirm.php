<?php
/**
 * 2-5 確認署名_画面
 * 確認事項すべてにチェックが入るまで、作業者の入力はできない（概要書 2-5-3）。
 *
 * @var array $report @var array $checklist @var array $form @var array $errors
 * @var array $workers @var string $step @var array $progress
 */
require APP_ROOT . '/app/views/partials/step_nav.php';
$allChecked = count($form['checked']) === count($checklist);
$hasSign    = !empty($report['signature_at']);
?>

<?php if ($errors): ?>
<div class="alert alert--error">
<?php foreach ($errors as $msg): ?>
  <div><?= h($msg) ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="sign-block">
  <a class="btn" href="/report/<?= (int) $report['id'] ?>/sign">サイン入力</a>

  <div class="sig-frame" style="margin-top:12px">
<?php if ($hasSign): ?>
    <img src="/report/<?= (int) $report['id'] ?>/signature.png?t=<?= h(strtotime((string) $report['signature_at'])) ?>"
         alt="お客様のサイン">
<?php else: ?>
    <span>サインはまだ入力されていません</span>
<?php endif; ?>
  </div>

<?php if ($hasSign): ?>
  <form method="post" action="/report/<?= (int) $report['id'] ?>/signature/delete"
        style="text-align:right; margin-top:6px">
    <?= csrf_field() ?>
    <button class="btn btn--sm btn--muted" type="submit">サインを消す</button>
  </form>
<?php endif; ?>
</div>

<form method="post" action="/report/<?= (int) $report['id'] ?>/confirm" novalidate id="js-confirm" <?= report_form_attrs($report, $step) ?>>
  <?= csrf_field() ?>

  <p class="notice-red" style="margin-top:18px">
    下記の注意事項の確認がとれたらチェックをいれてください
  </p>

  <ul class="check-list" id="js-checklist">
<?php foreach ($checklist as $item): ?>
    <li>
      <label>
        <input type="checkbox" name="checked[]" value="<?= (int) $item['id'] ?>"
               <?= in_array((int) $item['id'], $form['checked'], true) ? 'checked' : '' ?>>
        <span><?= h($item['label']) ?></span>
      </label>
    </li>
<?php endforeach; ?>
  </ul>

  <p class="notice-red" style="margin-top:14px">
    ※上記がすべてチェック済にならないと作業者は登録できません
  </p>

  <div class="form-row" style="margin-top:10px">
    <label class="form-row__label">作業者<span class="req">*</span></label>
    <div class="form-row__body">
<?php
      $selectedIds = $form['submitter_id'] ? [$form['submitter_id']] : [];
      $freeText    = $form['submitter_free'];
      $single      = true;
      $locked      = !$allChecked;
      require APP_ROOT . '/app/views/partials/worker_picker.php';
?>
    </div>
  </div>

<?php if (!$hasSign): ?>
  <div class="alert alert--warn" style="margin-top:16px">
    サインが未入力のままでも登録できます（報告書一覧の署名欄が「－」になります）。
  </div>
<?php endif; ?>

<?php
  $showBack  = true;
  $nextLabel = 'つぎへ';
  require APP_ROOT . '/app/views/partials/nav_buttons.php';
?>
</form>
