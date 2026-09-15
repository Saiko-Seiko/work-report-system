<?php
/**
 * K-8 確認事項マスタ 登録画面 ／ 修正ダイアログ
 * @var array $rows @var int $activeCount @var array $pager @var string $sort @var string $dir
 * @var array|null $dialog @var array|null $notice
 */
$link = fn(string $key, string $label) =>
    admin_sort_link('/admin/checks', [], $key, $label, $sort, $dir);
$page = fn(int $p) =>
    admin_page_url('/admin/checks', ['sort' => $sort, 'dir' => strtolower($dir)], $p);
?>
<?php if ($notice): ?>
<div class="alert alert--<?= h($notice['kind']) ?>"><?= h($notice['message']) ?></div>
<?php endif; ?>

<div class="toolbar">
  <a class="btn" href="/admin/checks?new=1">＋追加</a>
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
      <th><?= $link('label', '確認事項') ?></th>
      <th style="width:100px"><?= $link('order', '並び順') ?></th>
      <th style="width:100px"><?= $link('active', '使用') ?></th>
      <th style="width:110px"><?= $link('created', '登録日') ?></th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($rows as $c): ?>
    <tr class="<?= $c['is_active'] ? '' : 'is-muted' ?>">
      <td class="num"><?= (int) $c['id'] ?></td>
      <td><a class="link" href="/admin/checks?edit=<?= (int) $c['id'] ?>"><?= h((string) $c['label']) ?></a></td>
      <td class="num"><?= (int) $c['sort_order'] ?></td>
      <td class="center"><?= $c['is_active'] ? '使う' : '<span class="muted">使わない</span>' ?></td>
      <td><?= h(ymd_slash((string) $c['created_at'])) ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>

<p class="muted" style="font-size:13px; margin-top:12px">
  ここに登録した文言が、現場の「確認・署名」画面（2-5）のチェック欄に並びます（いま <?= (int) $activeCount ?>件を使用中）。
  現場はすべてにチェックを入れないと署名へ進めません。<br>
  「使わない」にしたものは画面に出なくなりますが、過去の報告書のチェック記録はそのまま残ります。
</p>

<?php if ($dialog): ?>
<div class="modal-backdrop">
  <div class="modal modal--wide">
    <div class="modal__close"><a href="/admin/checks">［×閉じる］</a></div>
    <h2 class="modal__title"><?= $dialog['mode'] === 'new' ? '確認事項の追加登録' : '確認事項の修正' ?></h2>

<?php if (!empty($dialog['errors'])): ?>
    <div class="alert alert--error">
<?php foreach ($dialog['errors'] as $msg): ?>
      <div><?= h($msg) ?></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>

    <form method="post" action="/admin/checks/save">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $dialog['id'] ?>">

      <p style="margin:0 0 6px">確認事項：</p>
      <textarea class="input" name="label" rows="3" style="width:100%"
                placeholder="例）作業前に電源を切ったことを確認した"><?= h((string) $dialog['label']) ?></textarea>

      <div class="modal__row" style="margin-top:12px">
        <label for="sort_order">並び順：</label>
        <input class="input" type="number" id="sort_order" name="sort_order"
               value="<?= (int) $dialog['sort_order'] ?>" min="0" max="99999" style="max-width:160px">
      </div>

      <div class="modal__row" style="margin-top:12px">
        <label for="is_active">使用：</label>
        <select class="input" id="is_active" name="is_active" style="max-width:200px">
          <option value="1" <?= (int) $dialog['is_active'] === 1 ? 'selected' : '' ?>>使う（現場に出す）</option>
          <option value="0" <?= (int) $dialog['is_active'] === 1 ? '' : 'selected' ?>>使わない</option>
        </select>
      </div>

      <div class="modal__actions">
<?php if ($dialog['mode'] === 'edit' && (int) $dialog['is_active'] === 1): ?>
        <button class="btn btn--danger" type="submit" formaction="/admin/checks/delete">使わない</button>
<?php endif; ?>
        <a class="btn btn--ghost" href="/admin/checks">キャンセル</a>
        <button class="btn btn--green" type="submit">登録</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
