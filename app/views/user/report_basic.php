<?php
/**
 * 2-1 基本情報登録_画面
 * @var array $report @var array $form @var array $errors @var array $workers
 * @var string $step @var array $progress
 */
require APP_ROOT . '/app/views/partials/step_nav.php';
?>

<?php if ($errors): ?>
<div class="alert alert--error">入力内容をご確認ください。</div>
<?php endif; ?>

<form method="post" action="/report/<?= (int) $report['id'] ?>/basic" novalidate <?= report_form_attrs($report, $step) ?>>
  <?= csrf_field() ?>

  <div class="form-row">
    <label class="form-row__label" for="created_date">作成日<span class="req">*</span></label>
    <div class="form-row__body">
      <input class="input <?= isset($errors['created_date']) ? 'is-error' : '' ?>"
             type="date" id="created_date" name="created_date"
             value="<?= h($form['created_date']) ?>">
    </div>
  </div>
<?php if (isset($errors['created_date'])): ?>
  <p class="field-error"><?= h($errors['created_date']) ?></p>
<?php endif; ?>

  <div class="form-row">
    <label class="form-row__label" for="hospital_name">病院名<span class="req">*</span></label>
    <div class="form-row__body">
      <input class="input <?= isset($errors['hospital_name']) ? 'is-error' : '' ?>"
             type="text" id="hospital_name" name="hospital_name"
             value="<?= h($form['hospital_name']) ?>"
             list="hospital-history" data-mic="1" placeholder="例）横浜市立大学附属病院">
    </div>
  </div>
<?php if (isset($errors['hospital_name'])): ?>
  <p class="field-error"><?= h($errors['hospital_name']) ?></p>
<?php endif; ?>
  <datalist id="hospital-history">
<?php foreach (Database::all(
        'SELECT DISTINCT hospital_name FROM reports
          WHERE account_id = ? AND hospital_name IS NOT NULL AND hospital_name <> ""
          ORDER BY hospital_name LIMIT 50',
        [$report['account_id']]
      ) as $row): ?>
    <option value="<?= h($row['hospital_name']) ?>"></option>
<?php endforeach; ?>
  </datalist>
  <p class="field-note">過去に入力した病院名が候補に出ます（表記のばらつきを防ぐため）。</p>

  <div class="form-row">
    <label class="form-row__label" for="work_date">作業日<span class="req">*</span></label>
    <div class="form-row__body">
      <input class="input <?= isset($errors['work_date']) ? 'is-error' : '' ?>"
             type="date" id="work_date" name="work_date"
             value="<?= h($form['work_date']) ?>" data-dow="js-dow">
      <span class="dow-badge" id="js-dow"><?= h(ymd_ja($form['work_date']) ?: '') ?></span>
    </div>
  </div>
<?php if (isset($errors['work_date'])): ?>
  <p class="field-error"><?= h($errors['work_date']) ?></p>
<?php endif; ?>

  <div class="form-row">
    <label class="form-row__label" for="work_place">作業場所<span class="req">*</span></label>
    <div class="form-row__body">
      <input class="input <?= isset($errors['work_place']) ? 'is-error' : '' ?>"
             type="text" id="work_place" name="work_place"
             value="<?= h($form['work_place']) ?>" data-mic="1" placeholder="例）4階 中央無菌室">
    </div>
  </div>
<?php if (isset($errors['work_place'])): ?>
  <p class="field-error"><?= h($errors['work_place']) ?></p>
<?php endif; ?>

  <div class="form-row">
    <label class="form-row__label">作業者<span class="req">*</span></label>
    <div class="form-row__body">
<?php
      $selectedIds = $form['worker_ids'];
      $freeText    = $form['worker_free'];
      $single      = false;
      $locked      = false;
      require APP_ROOT . '/app/views/partials/worker_picker.php';
?>
    </div>
  </div>
<?php if (isset($errors['workers'])): ?>
  <p class="field-error"><?= h($errors['workers']) ?></p>
<?php endif; ?>

  <div class="form-row">
    <label class="form-row__label" for="work_title">作業件名<span class="req">*</span></label>
    <div class="form-row__body">
      <input class="input <?= isset($errors['work_title']) ? 'is-error' : '' ?>"
             type="text" id="work_title" name="work_title"
             value="<?= h($form['work_title']) ?>" data-mic="1" placeholder="例）無菌病室 保守点検">
    </div>
  </div>
<?php if (isset($errors['work_title'])): ?>
  <p class="field-error"><?= h($errors['work_title']) ?></p>
<?php endif; ?>

  <p class="notice-red" style="margin-top:16px">※ この画面はすべて必須項目です</p>

<?php
  $showBack = true;
  require APP_ROOT . '/app/views/partials/nav_buttons.php';
?>
</form>
