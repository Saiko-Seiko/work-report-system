<?php
/**
 * K-6 報告事項マスター 登録画面 ／ 修正ダイアログ
 * @var array $rows @var int $ownCount @var array $pager @var string $sort @var string $dir
 * @var array|null $dialog @var array|null $notice
 */
$link = fn(string $key, string $label) =>
    admin_sort_link('/admin/texts', [], $key, $label, $sort, $dir);
$page = fn(int $p) =>
    admin_page_url('/admin/texts', ['sort' => $sort, 'dir' => strtolower($dir)], $p);
?>
<?php if ($notice): ?>
<div class="alert alert--<?= h($notice['kind']) ?>"><?= h($notice['message']) ?></div>
<?php endif; ?>

<div class="toolbar">
  <a class="btn" href="/admin/texts?new=1">＋追加</a>
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

<table class="table">
  <thead>
    <tr>
      <th style="width:74px"><?= $link('no', 'No.') ?></th>
      <th><?= $link('body', '報告事項') ?></th>
      <th style="width:100px">並び順</th>
      <th style="width:110px"><?= $link('created', '登録日') ?></th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($rows as $t): ?>
    <tr>
      <td class="num"><?= (int) $t['id'] ?></td>
      <td><a class="link" href="/admin/texts?edit=<?= (int) $t['id'] ?>"><?= h((string) $t['body']) ?></a></td>
      <td class="num"><?= (int) $t['sort_order'] ?></td>
      <td><?= h(ymd_slash((string) $t['created_at'])) ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>

<p class="muted" style="font-size:13px; margin-top:12px">
  ここに登録した文章は全社共通で、現場の「測定値・報告事項」画面の「選択」から差し込めます。<br>
  このほかに協力会社が自分で登録した文章が <?= number_format($ownCount) ?>件あります
  （各社のマイページで管理するため、ここでは扱いません）。
</p>

<?php if ($dialog): ?>
<div class="modal-backdrop">
  <div class="modal modal--wide">
    <div class="modal__close"><a href="/admin/texts">［×閉じる］</a></div>
    <h2 class="modal__title"><?= $dialog['mode'] === 'new' ? '報告事項の追加登録' : '報告事項の修正' ?></h2>

<?php if (!empty($dialog['errors'])): ?>
    <div class="alert alert--error">
<?php foreach ($dialog['errors'] as $msg): ?>
      <div><?= h($msg) ?></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>

    <form method="post" action="/admin/texts/save">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $dialog['id'] ?>">

      <p style="margin:0 0 6px">報告事項：</p>
      <textarea class="input" name="body" rows="4" style="width:100%"><?= h($dialog['body']) ?></textarea>

      <div class="modal__row" style="margin-top:12px">
        <label for="sort_order">並び順：</label>
        <input class="input" type="number" id="sort_order" name="sort_order"
               value="<?= (int) $dialog['sort_order'] ?>" min="0" max="99999" style="max-width:160px">
      </div>

      <div class="modal__actions">
<?php if ($dialog['mode'] === 'edit'): ?>
        <button class="btn btn--danger" type="submit" formaction="/admin/texts/delete">削除</button>
<?php endif; ?>
        <a class="btn btn--ghost" href="/admin/texts">キャンセル</a>
        <button class="btn btn--green" type="submit">登録</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
