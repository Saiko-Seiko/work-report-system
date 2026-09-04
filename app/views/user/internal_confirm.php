<?php
/**
 * 4-6 社内用報告書作成 PDF確認_画面
 *
 * 「完了」で概要書 5-⑥「ステータスを完了（請求済）にする」を行う。
 * 確認のアラートは JavaScript の confirm ではなく画面に出す形にしている。
 * これなら JavaScript が止まっていても、押し間違いを防げる。
 *
 * @var array $report @var array $internal @var bool $askDone
 */
$id   = (int) $report['id'];
$done = !empty($internal['completed_at']);
?>
<h1 class="page-title">▼社内用報告書　PDF確認画面</h1>

<?php if ($done): ?>
<div class="alert alert--info">
  この報告書は <?= h(ymd_ja(substr((string) $internal['completed_at'], 0, 10))) ?> に
  完了（請求済）になっています。
</div>
<?php endif; ?>

<table class="table" style="margin-bottom:18px">
  <tr><th style="width:96px">No.</th><td><?= (int) $report['report_no'] ?></td></tr>
  <tr><th>病院名</th><td><?= h((string) $internal['hospital_name']) ?></td></tr>
  <tr><th>作業日</th><td><?= h(ymd_ja((string) $internal['work_date'])) ?></td></tr>
  <tr><th>作業件名</th><td><?= h((string) $internal['work_title']) ?></td></tr>
  <tr>
    <th>再手配</th>
    <td>
      <?= (int) Database::value(
        'SELECT COUNT(*) FROM internal_report_parts WHERE internal_report_id = ? AND qty > 0',
        [$internal['id']]
      ) ?> 点
    </td>
  </tr>
  <tr>
    <th>移動・作業</th>
    <td>
<?php
      $moveOut  = InternalReport::spanMinutes($internal['travel_out_from'], $internal['travel_out_to']);
      $moveBack = InternalReport::spanMinutes($internal['travel_back_from'], $internal['travel_back_to']);
      $work     = InternalReport::spanMinutes($internal['work_from'], $internal['work_to']);
?>
      移動 <?= h(InternalReport::totalLabel([$moveOut, $moveBack])) ?>
      ／ 作業 <?= h(InternalReport::totalLabel([$work])) ?>
    </td>
  </tr>
  <tr><th>状態</th><td><?= $done ? '完了（請求済）' : '作成中' ?></td></tr>
</table>

<ul class="menu-list">
  <li>
    <a href="/report/<?= $id ?>/internal/preview">
      <span>プレビュー<small>社内用報告書をA4で表示します</small></span>
    </a>
  </li>
  <li>
    <a href="/report/<?= $id ?>/internal/print">
      <span>印刷<small>ブラウザの印刷画面を開きます</small></span>
    </a>
  </li>
</ul>

<div class="nav-buttons">
  <a class="nav-btn" href="/report/<?= $id ?>/internal/sales">
    <span class="nav-btn__mark">&#9664;</span><span>もどる</span>
  </a>
  <a class="nav-btn" href="/dashboard">
    <span class="nav-btn__mark">&#9635;</span><span>ダッシュ<br>ボードへ</span>
  </a>
  <a class="nav-btn" href="/reports">
    <span class="nav-btn__mark">&#9635;</span><span>一覧へ</span>
  </a>
  <a class="nav-btn nav-btn--done" href="/report/<?= $id ?>/internal/confirm?complete=1">
    <span class="nav-btn__mark">&#9654;</span><span>完了</span>
  </a>
</div>

<?php if ($askDone): ?>
<div class="dialog-backdrop">
  <div class="dialog">
    <p class="dialog__message">本当によいですか？</p>
    <p class="dialog__sub">
      「OK」を押すと、この報告書のステータスが<strong>完了（請求済）</strong>になり、
      報告書一覧の「状態」が「完」になります。
    </p>
    <div class="dialog__actions">
      <a class="dialog__cancel" href="/report/<?= $id ?>/internal/confirm">キャンセル</a>
      <form method="post" action="/report/<?= $id ?>/internal/complete">
        <?= csrf_field() ?>
        <button class="btn" type="submit">OK</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
