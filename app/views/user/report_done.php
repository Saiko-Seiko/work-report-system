<?php
/**
 * 2-7 完了_画面
 * @var array $report @var int $mailCount
 */
$id        = (int) $report['id'];
$submitted = !empty($report['submitted_at']);
?>
<h1 class="page-title">▼完了画面</h1>

<?php if ($submitted): ?>
<div class="alert alert--info">
  報告書 No.<?= (int) $report['report_no'] ?> を登録しました。
</div>
<?php else: ?>
<div class="alert alert--warn">
  この報告書はまだ登録が済んでいません。
  <a href="/report/<?= $id ?>/confirm">確認・署名の画面</a>で「つぎへ」を押してください。
</div>
<?php endif; ?>

<table class="table" style="margin-bottom:18px">
  <tr><th style="width:96px">No.</th><td><?= (int) $report['report_no'] ?></td></tr>
  <tr><th>病院名</th><td><?= h((string) $report['hospital_name']) ?></td></tr>
  <tr><th>作業日</th><td><?= h(ymd_ja((string) $report['work_date'])) ?></td></tr>
  <tr><th>作業場所</th><td><?= h((string) $report['work_place']) ?></td></tr>
  <tr><th>作業件名</th><td><?= h((string) $report['work_title']) ?></td></tr>
  <tr><th>作業者</th><td><?= h((string) $report['workers_text']) ?></td></tr>
  <tr><th>担当</th><td><?= h((string) $report['submitter_name']) ?></td></tr>
  <tr><th>サイン</th><td><?= $report['signature_at'] ? '有' : '－' ?></td></tr>
  <tr><th>メール送信</th><td><?= $mailCount ?> 回</td></tr>
</table>

<ul class="menu-list">
  <li>
    <a href="/report/<?= $id ?>/preview">
      <span>報告書のプレビュー<small>登録した内容でA4の報告書を表示します</small></span>
    </a>
  </li>
  <li>
    <a href="/report/<?= $id ?>/print">
      <span>印刷<small>ブラウザの印刷画面を開きます</small></span>
    </a>
  </li>
  <li>
    <a href="/report/<?= $id ?>/mail">
      <span>メール送信<small>任意のアドレスに報告書を送ります</small></span>
    </a>
  </li>
</ul>

<div class="nav-buttons">
  <a class="nav-btn" href="/report/<?= $id ?>/confirm">
    <span class="nav-btn__mark">&#9664;</span><span>もどる</span>
  </a>
  <a class="nav-btn" href="/dashboard">
    <span class="nav-btn__mark">&#9635;</span><span>ダッシュ<br>ボードへ</span>
  </a>
  <a class="nav-btn" href="/reports">
    <span class="nav-btn__mark">&#9635;</span><span>一覧へ</span>
  </a>
  <a class="nav-btn nav-btn--primary" href="/report/<?= $id ?>/internal">
    <span class="nav-btn__mark">&#9654;</span><span>社内用<br>報告書へ</span>
  </a>
</div>
