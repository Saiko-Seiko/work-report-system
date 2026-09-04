<?php
/**
 * 4-4 社内用報告書作成 移動および作業時間の推移登録_画面
 *
 * 移動時間…I／作業時間…S（概要書の表記）。
 * 請求のもとになる数字なので、入れたその場で長さを出して
 * 打ち間違いに気づけるようにしている。
 *
 * @var array $report @var array $internal @var array $form @var array $errors
 * @var string $step @var array $progress
 */
require APP_ROOT . '/app/views/partials/internal_step_nav.php';
?>

<?php if ($errors): ?>
<div class="alert alert--error">入力内容をご確認ください。</div>
<?php endif; ?>

<p class="hours-legend">移動時間・・・I　　作業時間・・・S</p>

<form method="post" action="/report/<?= (int) $report['id'] ?>/internal/hours" novalidate
      <?= internal_form_attrs($report, $step) ?>>
  <?= csrf_field() ?>

<?php foreach (InternalReport::SPANS as $span => $meta): ?>
<?php
  $from = $form[$span . '_from'];
  $to   = $form[$span . '_to'];
  $len  = InternalReport::span($from, $to);
?>
  <div class="hours-row">
    <span class="hours-row__mark"><?= h($meta['mark']) ?></span>
    <span class="hours-row__label"><?= h($meta['label']) ?></span>
    <input class="input hours-row__time <?= isset($errors[$span . '_from']) ? 'is-error' : '' ?>"
           type="time" name="<?= h($span) ?>_from" value="<?= h($from) ?>"
           aria-label="<?= h($meta['label']) ?> 開始">
    <span class="hours-row__tilde">〜</span>
    <input class="input hours-row__time <?= isset($errors[$span . '_to']) ? 'is-error' : '' ?>"
           type="time" name="<?= h($span) ?>_to" value="<?= h($to) ?>"
           aria-label="<?= h($meta['label']) ?> 終了">
    <span class="hours-row__len"><?= $len === null ? '' : h($len) ?></span>
  </div>
<?php foreach ([$span . '_from', $span . '_to'] as $key): ?>
<?php if (isset($errors[$key])): ?>
  <p class="field-error" style="margin-left:0"><?= h($errors[$key]) ?></p>
<?php endif; ?>
<?php endforeach; ?>
<?php endforeach; ?>

<?php
  $moveOut  = InternalReport::spanMinutes($form['travel_out_from'], $form['travel_out_to']);
  $moveBack = InternalReport::spanMinutes($form['travel_back_from'], $form['travel_back_to']);
  $work     = InternalReport::spanMinutes($form['work_from'], $form['work_to']);
?>
<?php if ($moveOut !== null || $moveBack !== null || $work !== null): ?>
  <table class="table hours-total">
    <tr>
      <th>移動時間（往復）</th>
      <td><?= h(InternalReport::totalLabel([$moveOut, $moveBack])) ?></td>
    </tr>
    <tr>
      <th>作業時間</th>
      <td><?= h(InternalReport::totalLabel([$work])) ?></td>
    </tr>
  </table>
<?php endif; ?>

  <p class="field-note" style="margin-left:0">
    時刻をタップするとカレンダーと同じように選べます。<br>
    右側の時間は入力した時刻から自動で計算しています（保存はしません）。
  </p>

<?php
  $showBack = true;
  require APP_ROOT . '/app/views/partials/nav_buttons.php';
?>
</form>
