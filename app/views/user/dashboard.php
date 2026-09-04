<?php
/**
 * 1-2 ダッシュボード画面
 * @var array $user @var array|null $draft @var int $reportCount @var bool $autoLogin
 */
?>
<div class="dash-greeting">
  <?= h($user['company_name']) ?><br>
  <small class="muted">
    <?= h($user['account_id']) ?>
<?php if ($autoLogin): ?>
    ／自動ログイン
<?php endif; ?>
  </small>
</div>

<?php if ($draft): ?>
<div class="alert alert--warn">
  作りかけの報告書が1件あります。<br>
  <strong><?= h($draft['hospital_name'] ?: '（病院名未入力）') ?></strong>
  <span class="muted">（<?= h(ymd_slash($draft['updated_at'])) ?> 保存）</span><br>
  <a class="btn btn--sm" href="/report/<?= (int) $draft['id'] ?>/basic" style="margin-top:6px">続きを入力する</a>
</div>
<?php endif; ?>

<h2 class="menu-heading">▼報告書</h2>
<ul class="menu-list">
  <li>
    <a href="/report/new" id="js-new-report">
      <span>作成<small>新規で作成</small></span>
    </a>
  </li>
  <li>
    <a href="/reports">
      <span>一覧表<small>コピーして作成、修正、プレビュー（<?= number_format($reportCount) ?>件）</small></span>
    </a>
  </li>
</ul>

<h2 class="menu-heading">▼マイページ</h2>
<ul class="menu-list">
  <li><a href="/mypage"><span>ユーザー情報の変更<small>パスワード・メールアドレス・会社名</small></span></a></li>
  <li><a href="/mypage/workers"><span>作業者テーブルの変更<small>点検にあたる作業者の登録</small></span></a></li>
  <li><a href="/mypage/texts"><span>報告事項テーブルの変更<small>よく使う報告事項の定型文</small></span></a></li>
</ul>

<form method="post" action="/logout" style="margin-top:28px">
  <?= csrf_field() ?>
  <button type="submit" class="btn btn--muted btn--block">ログアウト</button>
</form>
