<?php
/**
 * 作業者の選択（作業者テーブルから複数選択可、任意入力も可）。
 * 2-1 と 2-5 で使う。
 *
 * 名簿は畳まずに出しっぱなしにしている。
 * 「選択」ボタンを一度押してから選ぶ形だと、現場でのタップが1回増えるため。
 * 名前そのものが大きなボタンになっていて、押すだけで選べる。
 *
 * @var array  $workers      作業者テーブル
 * @var array  $selectedIds  選択済みID
 * @var string $freeText     任意入力
 * @var bool|null $single    2-5 のように1名だけ選ぶ場合
 * @var bool|null $locked    チェック未完了で入力させない場合
 */
$single = $single ?? false;
$locked = $locked ?? false;
?>
<div class="picker<?= $locked ? ' is-locked' : '' ?>" <?= $locked ? 'data-locked="1"' : '' ?>>

<?php if ($locked): ?>
  <p class="picker__lock">確認事項すべてにチェックを入れると選べるようになります</p>
<?php endif; ?>

<?php if (!$workers): ?>
  <p class="muted mb0">
    作業者テーブルが空です。マイページの「作業者テーブルの変更」から登録してください。
  </p>
<?php else: ?>
  <ul class="picker__names">
<?php foreach ($workers as $w): ?>
    <li>
      <label class="pick-chip">
        <input type="<?= $single ? 'radio' : 'checkbox' ?>"
               name="<?= $single ? 'worker_pick' : 'worker_ids[]' ?>"
               value="<?= (int) $w['id'] ?>"
               data-name="<?= h($w['name']) ?>"
               <?= in_array((int) $w['id'], $selectedIds, true) ? 'checked' : '' ?>
               <?= $locked ? 'disabled' : '' ?>>
        <span><?= h($w['name']) ?></span>
      </label>
    </li>
<?php endforeach; ?>
  </ul>
<?php endif; ?>

  <label class="picker__free">
    <span>名簿にない方（<?= $single ? '直接入力' : '複数のときは読点で区切る' ?>）</span>
    <input class="input" type="text"
           name="<?= $single ? 'submitter_free' : 'worker_free' ?>"
           value="<?= h($freeText) ?>"
           data-mic="1"
           <?= $locked ? 'disabled' : '' ?>
           placeholder="<?= $single ? '例）落合健一' : '例）落合健一、米窪花子' ?>">
  </label>
</div>
