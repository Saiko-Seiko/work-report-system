<?php
/**
 * 4-2 社内用報告書作成 今回作業時の残作業_画面
 * @var array $report @var array $internal @var string $step @var array $progress
 */
require APP_ROOT . '/app/views/partials/internal_step_nav.php';
?>

<label class="block-label" for="remaining_work">今回作業時の残作業</label>

<form method="post" action="/report/<?= (int) $report['id'] ?>/internal/remain"
      <?= internal_form_attrs($report, $step) ?>>
  <?= csrf_field() ?>

  <textarea class="textarea textarea--tall" id="remaining_work" name="remaining_work" data-mic="1"
            placeholder="次回に持ち越す作業、部材待ちの箇所などを入力してください"><?= h((string) $internal['remaining_work']) ?></textarea>

  <p class="field-note" style="margin-left:0">
    社内用の報告書だけに載ります。客先提出用には出ません。
  </p>

<?php
  $showBack = true;
  require APP_ROOT . '/app/views/partials/nav_buttons.php';
?>
</form>
