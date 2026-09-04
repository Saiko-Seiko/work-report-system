<?php
/**
 * K-3 ユーザー登録画面 ／ K-3-1 アカウント詳細修正ダイアログ
 * @var array $accounts @var array $pager @var string $sort @var string $dir @var array|null $dialog
 */
$keep = [];
$link = fn(string $key, string $label) =>
    admin_sort_link('/admin/users', $keep, $key, $label, $sort, $dir);
?>
<div class="toolbar">
  <a class="btn" href="/admin/users?new=1">＋追加</a>
  <span class="toolbar__spacer"></span>
  <span class="pager">
    <span><?= $pager['from'] ?>-<?= $pager['to'] ?>/<?= number_format($pager['total']) ?></span>
<?php if ($pager['page'] > 1): ?>
    <a href="<?= h(admin_page_url('/admin/users', ['sort' => $sort, 'dir' => strtolower($dir)], $pager['page'] - 1)) ?>">＜</a>
<?php else: ?><span class="muted">＜</span><?php endif; ?>
<?php if ($pager['page'] < $pager['pages']): ?>
    <a href="<?= h(admin_page_url('/admin/users', ['sort' => $sort, 'dir' => strtolower($dir)], $pager['page'] + 1)) ?>">＞</a>
<?php else: ?><span class="muted">＞</span><?php endif; ?>
  </span>
</div>

<table class="table">
  <thead>
    <tr>
      <th style="width:64px"><?= $link('no', 'No.') ?></th>
      <th style="width:150px"><?= $link('login', 'アカウントID') ?></th>
      <th><?= $link('company', '会社名') ?></th>
<?php for ($i = 1; $i <= ADMIN_WORKER_SLOTS; $i++): ?>
      <th style="width:88px">作業者<?= $i ?></th>
<?php endfor; ?>
      <th style="width:100px"><?= $link('created', '登録日') ?></th>
      <th style="width:96px" class="center">状態</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($accounts as $a): ?>
    <tr>
      <td class="num"><?= (int) $a['id'] ?></td>
      <td>
        <a class="link" href="/admin/users?edit=<?= (int) $a['id'] ?>"><?= h($a['account_id']) ?></a>
      </td>
      <td>
        <?= h((string) $a['company_name']) ?>
<?php if ($a['worker_total'] > ADMIN_WORKER_SLOTS): ?>
        <span class="muted">（作業者 <?= (int) $a['worker_total'] ?>名）</span>
<?php endif; ?>
      </td>
<?php for ($i = 0; $i < ADMIN_WORKER_SLOTS; $i++): ?>
      <td><?= h($a['workers'][$i] ?? '') ?></td>
<?php endfor; ?>
      <td><?= h(ymd_slash((string) $a['created_at'])) ?></td>
      <td class="center">
<?php if ((int) $a['is_locked'] === 1): ?>
        <form method="post" action="/admin/users/unlock" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <button class="btn btn--sm btn--danger" type="submit"
                  title="3回失敗によるロックを解除します">ロック解除</button>
        </form>
<?php else: ?>
        <span class="muted">－</span>
<?php endif; ?>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>

<p class="muted" style="font-size:13px; margin-top:12px">
  アカウントIDは会社ごとに発行します。作業者は6名以上でも登録でき、6人目からは利用者側の
  「作業者テーブルの変更」で追加します（この画面には先頭5名を表示）。
</p>

<?php if ($dialog): ?>
<div class="modal-backdrop">
  <div class="modal">
    <div class="modal__close"><a href="/admin/users">［×閉じる］</a></div>
    <h2 class="modal__title">
      <?= $dialog['mode'] === 'new' ? 'アカウントの追加登録' : 'アカウント詳細修正' ?>
    </h2>

<?php if (!empty($dialog['errors'])): ?>
    <div class="alert alert--error">
<?php foreach ($dialog['errors'] as $msg): ?>
      <div><?= h($msg) ?></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ((int) $dialog['is_locked'] === 1): ?>
    <div class="alert alert--warn">
      このアカウントはパスワードを3回間違えたためロックされています。
      新しいパスワードを入れて登録するか、一覧の「ロック解除」で解けます。
    </div>
<?php endif; ?>

    <form method="post" action="/admin/users/save">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $dialog['id'] ?>">
      <input type="hidden" name="extra" value="<?= (int) $dialog['extra'] ?>">

      <div class="modal__row">
        <label for="account_id">アカウントID：</label>
        <input class="input" type="text" id="account_id" name="account_id"
               value="<?= h($dialog['account_id']) ?>" spellcheck="false">
      </div>

      <div class="modal__row">
        <label for="password">パスワード：</label>
        <input class="input" type="text" id="password" name="password"
               placeholder="<?= $dialog['mode'] === 'new' ? '半角英数8文字以上' : '変更するときだけ入力' ?>"
               autocomplete="off" spellcheck="false">
      </div>
      <p class="modal__note">
        発行したパスワードは協力会社へお伝えください。利用者はマイページで変更できます。
      </p>

      <div class="modal__row">
        <label for="company_name">会社名：</label>
        <input class="input" type="text" id="company_name" name="company_name"
               value="<?= h($dialog['company']) ?>">
      </div>

      <div class="modal__row">
        <label for="email">メールアドレス：</label>
        <input class="input" type="email" id="email" name="email"
               value="<?= h($dialog['email']) ?>" spellcheck="false">
      </div>

<?php for ($i = 0; $i < ADMIN_WORKER_SLOTS; $i++): ?>
      <div class="modal__row">
        <label for="w<?= $i ?>">作業者<?= $i + 1 ?>：</label>
        <input class="input" type="text" id="w<?= $i ?>" name="workers[]"
               value="<?= h($dialog['workers'][$i] ?? '') ?>">
      </div>
<?php endfor; ?>

<?php if ((int) $dialog['extra'] > 0): ?>
      <p class="modal__note">
        このほかに <?= (int) $dialog['extra'] ?>名が利用者側で登録されています（この画面では変更しません）。
      </p>
<?php endif; ?>

      <div class="modal__actions">
        <a class="btn btn--ghost" href="/admin/users">キャンセル</a>
        <button class="btn btn--green" type="submit">登録</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
