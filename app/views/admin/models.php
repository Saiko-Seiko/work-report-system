<?php
/**
 * K-5 機種名マスター 登録画面 ／ 修正ダイアログ
 * @var array $rows @var array $pager @var string $sort @var string $dir
 * @var array|null $dialog @var array|null $notice
 */
$link = fn(string $key, string $label) =>
    admin_sort_link('/admin/models', [], $key, $label, $sort, $dir);
$page = fn(int $p) =>
    admin_page_url('/admin/models', ['sort' => $sort, 'dir' => strtolower($dir)], $p);
?>
<?php if ($notice): ?>
<div class="alert alert--<?= h($notice['kind']) ?>"><?= h($notice['message']) ?></div>
<?php endif; ?>

<div class="toolbar">
  <a class="btn" href="/admin/models?new=1">＋追加</a>
  <span class="toolbar__spacer"></span>
  <span class="pager">
    <span><?= $pager['from'] ?>-<?= $pager['to'] ?>/<?= number_format($pager['total']) ?></span>
<?php if ($pager['page'] > 1): ?>
    <a href="<?= h($page($pager['page'] - 1)) ?>">＜</a>
<?php else: ?>
    <span class="muted">＜</span>
<?php endif; ?>
<?php if ($pager['page'] < $pager['pages']): ?>
    <a href="<?= h($page($pager['page'] + 1)) ?>">＞</a>
<?php else: ?>
    <span class="muted">＞</span>
<?php endif; ?>
  </span>
</div>

<table class="table" style="max-width:900px">
  <thead>
    <tr>
      <th style="width:74px"><?= $link('no', 'No.') ?></th>
      <th><?= $link('name', '機種名') ?></th>
      <th style="width:240px">ヨミガナ</th>
      <th style="width:100px">並び順</th>
      <th style="width:110px"><?= $link('created', '登録日') ?></th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($rows as $m): ?>
    <tr>
      <td class="num"><?= (int) $m['id'] ?></td>
      <td><a class="link" href="/admin/models?edit=<?= (int) $m['id'] ?>"><?= h($m['name']) ?></a></td>
      <td class="muted"><?= h((string) $m['kana']) ?></td>
      <td class="num"><?= (int) $m['sort_order'] ?></td>
      <td><?= h(ymd_slash((string) $m['created_at'])) ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>

<p class="muted" style="font-size:13px; margin-top:12px">
  ここで登録した機種名が、現場の「作業内容」と「測定値の型式」に出ます。<br>
  削除しても過去の報告書には機種名が残ります（一覧から隠すだけです）。
</p>

<?php if ($dialog): ?>
<div class="modal-backdrop">
  <div class="modal">
    <div class="modal__close"><a href="/admin/models">［×閉じる］</a></div>
    <h2 class="modal__title"><?= $dialog['mode'] === 'new' ? '機種名の追加登録' : '機種名の修正' ?></h2>

<?php if (!empty($dialog['errors'])): ?>
    <div class="alert alert--error">
<?php foreach ($dialog['errors'] as $msg): ?>
      <div><?= h($msg) ?></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>

    <form method="post" action="/admin/models/save">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $dialog['id'] ?>">

      <div class="modal__row">
        <label for="name">機種名：</label>
        <input class="input" type="text" id="name" name="name" value="<?= h($dialog['name']) ?>">
      </div>
      <div class="modal__row">
        <label for="kana">ヨミガナ：</label>
        <input class="input" type="text" id="kana" name="kana" value="<?= h($dialog['kana']) ?>">
      </div>
      <div class="modal__row">
        <label for="sort_order">並び順：</label>
        <input class="input" type="number" id="sort_order" name="sort_order"
               value="<?= (int) $dialog['sort_order'] ?>" min="0" max="99999" style="max-width:160px">
      </div>
      <p class="modal__note">並び順は小さいものから現場の画面に出ます。</p>

      <div class="modal__actions">
<?php if ($dialog['mode'] === 'edit'): ?>
        <button class="btn btn--danger" type="submit" formaction="/admin/models/delete">削除</button>
<?php endif; ?>
        <a class="btn btn--ghost" href="/admin/models">キャンセル</a>
        <button class="btn btn--green" type="submit">登録</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
