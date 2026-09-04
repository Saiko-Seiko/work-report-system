<?php
/**
 * 4-5 社内用報告書作成 営業アプローチ・備考登録_画面
 * @var array $report @var array $internal @var string $step @var array $progress
 */
require APP_ROOT . '/app/views/partials/internal_step_nav.php';
?>

<form method="post" action="/report/<?= (int) $report['id'] ?>/internal/sales"
      <?= internal_form_attrs($report, $step) ?>>
  <?= csrf_field() ?>

  <label class="block-label" for="sales_approach">客先への営業アプローチ</label>
  <textarea class="textarea" id="sales_approach" name="sales_approach" data-mic="1"
            placeholder="更新の相談、見積依頼の見込みなど"><?= h((string) $internal['sales_approach']) ?></textarea>

  <label class="block-label" for="remarks">備考（社内への報告事項）</label>
  <textarea class="textarea" id="remarks" name="remarks" data-mic="1"
            placeholder="立会者、入館の注意点、次回への引き継ぎなど"><?= h((string) $internal['remarks']) ?></textarea>

<?php
  $showBack  = true;
  $nextLabel = 'つぎへ';
  require APP_ROOT . '/app/views/partials/nav_buttons.php';
?>
</form>
