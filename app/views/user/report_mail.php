<?php
/**
 * 2-10 メール送信_画面
 * 概要書のとおり、左に報告書、右に送信内容を並べる。
 *
 * @var array $report @var array $form @var array $errors @var bool $sent
 */
$id = (int) $report['id'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#1b7a46">
<title>メール送信 | <?= h(config('app_name')) ?></title>
<link rel="stylesheet" href="/assets/css/app.css?v=4">
</head>
<body class="paper-page">
<div class="viewport viewport--wide">

  <header class="app-header">
    <div class="app-header__title"><?= h(config('app_name')) ?></div>
    <a class="app-header__close" href="/report/<?= $id ?>/done">［×閉じる］</a>
  </header>

  <div class="mail-layout">

    <div class="mail-layout__paper">
      <iframe class="paper-frame paper-frame--thumb" src="/report/<?= $id ?>/sheet"
              title="送信する作業完了報告書"></iframe>
      <p class="muted" style="font-size:12.5px; margin:8px 0 0">
        この内容をPDFにして添付します。
      </p>
    </div>

    <div class="mail-layout__form">
      <h1 class="page-title" style="margin-top:0">メール送信</h1>

<?php if ($errors): ?>
      <div class="alert alert--error">
<?php foreach ($errors as $msg): ?>
        <div><?= h($msg) ?></div>
<?php endforeach; ?>
      </div>
<?php endif; ?>

      <form method="post" action="/report/<?= $id ?>/mail" novalidate>
        <?= csrf_field() ?>

        <label class="block-label" for="to">送信先<span class="req">*</span></label>
        <input class="input <?= isset($errors['to']) ? 'is-error' : '' ?>"
               type="email" id="to" name="to" value="<?= h($form['to']) ?>"
               placeholder="例）setsubi@example-hospital.jp"
               autocomplete="off" spellcheck="false">

        <label class="block-label" for="subject">件名<span class="req">*</span></label>
        <input class="input <?= isset($errors['subject']) ? 'is-error' : '' ?>"
               type="text" id="subject" name="subject" value="<?= h($form['subject']) ?>">

        <label class="block-label" for="cc">CC</label>
        <input class="input <?= isset($errors['cc']) ? 'is-error' : '' ?>"
               type="text" id="cc" name="cc" value="<?= h($form['cc']) ?>"
               placeholder="複数のときはカンマで区切ってください"
               autocomplete="off" spellcheck="false">

        <label class="block-label" for="body">文章</label>
        <textarea class="textarea textarea--tall" id="body" name="body"
                  data-mic="1"><?= h($form['body']) ?></textarea>

        <p style="margin-top:18px">
          <button class="btn btn--block" type="submit">送信</button>
        </p>
      </form>

      <p class="muted" style="font-size:12.5px; line-height:1.8; margin-top:16px">
        これまでの送信回数：<?= (int) $report['mail_count'] ?>回<br>
        送信内容は記録され、報告書一覧の「Mail」欄に回数が出ます。
      </p>
    </div>

  </div>
</div>

<?php if ($sent): ?>
<div class="dialog-backdrop" id="js-sent">
  <div class="dialog">
    <div class="dialog__close"><a href="/report/<?= $id ?>/done">［×閉じる］</a></div>
    <p class="dialog__message">送信しました</p>
<?php if (config('mail.dry_run')): ?>
    <p class="dialog__sub">
      ※このプロトタイプでは実際の配信は行わず、送信内容（宛先・件名・本文）を記録しています。
      本番ではさくらのSMTPからPDFを添付して送信します。
    </p>
<?php endif; ?>
    <p style="text-align:center; margin:16px 0 0">
      <a class="btn btn--sm" href="/report/<?= $id ?>/done">完了画面へ</a>
    </p>
  </div>
</div>
<?php endif; ?>

<script src="/assets/js/mic.js?v=1"></script>
</body>
</html>
