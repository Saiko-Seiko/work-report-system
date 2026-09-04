<?php
/** @var array $counts @var array $screens @var int $donePhase @var string $driver @var string $dbPath */
$phaseNames = [
    1 => '土台（構成・DB・デザイン）',
    2 => 'ログイン・ダッシュボード',
    3 => '報告書作成ウィザード',
    4 => 'マイク入力・署名・オフライン',
    5 => 'PDF・印刷・メール',
    6 => '一覧・マイページ',
    7 => '社内用報告書',
    8 => '管理者サイト',
];
?>
<h1 class="page-title">開発インデックス（Phase <?= h($donePhase) ?> 完了）</h1>

<div class="alert alert--info">
  この画面は開発中の確認用です。<code class="mono">config.debug = false</code> で表示されなくなります。
</div>

<h2 class="menu-heading">データベース</h2>
<p class="mono muted mt0">driver: <?= h($driver) ?> / <?= h($dbPath) ?></p>
<div class="dev-grid">
<?php foreach ($counts as $label => $n): ?>
  <div class="dev-stat">
    <b><?= number_format($n) ?></b>
    <span><?= h($label) ?></span>
  </div>
<?php endforeach; ?>
</div>

<h2 class="menu-heading">デモ用アカウント</h2>
<table class="table">
  <tr><th>用途</th><th>ID</th><th>パスワード</th></tr>
  <tr><td>協力会社（メイン）</td><td class="mono">ABCDE0001</td><td class="mono">pass1234</td></tr>
  <tr><td>協力会社（別会社）</td><td class="mono">ABCDE0002</td><td class="mono">pass1234</td></tr>
  <tr><td>事務局（管理者）</td><td class="mono">admin</td><td class="mono">admin1234</td></tr>
</table>

<h2 class="menu-heading">画面の作成状況</h2>
<table class="table">
  <tr><th>No.</th><th>画面</th><th class="center">Phase</th><th class="center">状態</th></tr>
<?php foreach ($screens as [$code, $name, $path, $phase]): ?>
  <tr>
    <td class="mono"><?= h($code) ?></td>
    <td><?php if ($path && $phase <= $donePhase): ?><a href="<?= h($path) ?>"><?= h($name) ?></a><?php else: ?><?= h($name) ?><?php endif; ?></td>
    <td class="center"><?= h($phase) ?></td>
    <td class="center"><?= $phase <= $donePhase ? '完了' : '—' ?></td>
  </tr>
<?php endforeach; ?>
</table>

<h2 class="menu-heading">フェーズ</h2>
<table class="table">
<?php foreach ($phaseNames as $n => $label): ?>
  <tr>
    <td class="center" style="width:56px"><?= h($n) ?></td>
    <td><?= h($label) ?></td>
    <td class="center" style="width:72px"><?= $n <= $donePhase ? '完了' : '予定' ?></td>
  </tr>
<?php endforeach; ?>
</table>
