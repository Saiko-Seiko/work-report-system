<?php
/**
 * 4-1 社内用報告書作成 基本情報登録_画面
 * 客先に出した内容を写して表示し、ここで直せる。
 *
 * @var array $report @var array $internal @var array $form @var array $errors
 * @var string $step @var array $progress
 */
require APP_ROOT . '/app/views/partials/internal_step_nav.php';
$labels = [
    'created_date'  => ['作成日',   'date'],
    'hospital_name' => ['病院名',   'text'],
    'work_date'     => ['作業日',   'date'],
    'work_place'    => ['作業場所', 'text'],
    'workers_text'  => ['作業者',   'text'],
    'work_title'    => ['作業件名', 'text'],
];
?>

<div class="alert alert--info">
  客先へ提出した報告書の内容を表示しています。社内用として直したいところだけ変更してください。
</div>

<?php if ($errors): ?>
<div class="alert alert--error">入力内容をご確認ください。</div>
<?php endif; ?>

<form method="post" action="/report/<?= (int) $report['id'] ?>/internal/basic" novalidate
      <?= internal_form_attrs($report, $step) ?>>
  <?= csrf_field() ?>

<?php foreach ($labels as $key => [$label, $type]): ?>
  <div class="form-row">
    <label class="form-row__label" for="<?= h($key) ?>"><?= h($label) ?><span class="req">*</span></label>
    <div class="form-row__body">
      <input class="input <?= isset($errors[$key]) ? 'is-error' : '' ?>"
             type="<?= h($type) ?>" id="<?= h($key) ?>" name="<?= h($key) ?>"
             value="<?= h($form[$key]) ?>"
             <?= $type === 'text' ? 'data-mic="1"' : '' ?>
             <?= $key === 'work_date' ? 'data-dow="js-dow"' : '' ?>>
<?php if ($key === 'work_date'): ?>
      <span class="dow-badge" id="js-dow"><?= h(ymd_ja($form['work_date']) ?: '') ?></span>
<?php endif; ?>
    </div>
  </div>
<?php if (isset($errors[$key])): ?>
  <p class="field-error"><?= h($errors[$key]) ?></p>
<?php endif; ?>
<?php endforeach; ?>

  <p class="notice-red" style="margin-top:16px">※ この画面はすべて必須項目です</p>

<?php
  $showBack = true;
  require APP_ROOT . '/app/views/partials/nav_buttons.php';
?>
</form>
