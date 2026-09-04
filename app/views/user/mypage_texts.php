<?php
/**
 * 5-3 報告事項_登録画面
 * ここで登録した文章は、2-4 の「選択」から本文に差し込める。
 *
 * @var array $rows @var array $common @var int $total @var int $page @var int $pages
 * @var int $from @var int $to @var string $sort @var string $dir
 */
$link = function (string $key, string $label) use ($sort, $dir) {
    $next = ($sort === $key && $dir === 'ASC') ? 'desc' : 'asc';
    $mark = $sort !== $key ? '▼' : ($dir === 'ASC' ? '▲' : '▼');
    $cls  = $sort === $key ? ' class="is-sorted"' : '';
    return sprintf('<a href="/mypage/texts?sort=%s&dir=%s"%s>%s%s</a>',
        h($key), $next, $cls, h($label), $mark);
};
$pageUrl = fn(int $p) => '/mypage/texts?' . http_build_query([
    'sort' => $sort, 'dir' => strtolower($dir), 'page' => $p,
]);
?>
<h1 class="page-title">報告事項</h1>

<form method="post" action="/mypage/texts">
  <?= csrf_field() ?>

  <div class="list-bar">
    <a class="btn btn--sm btn--muted" href="/dashboard">もどる</a>
    <button class="btn btn--sm" type="submit">登録</button>
    <span class="list-bar__count"><?= $from ?>-<?= $to ?>/<?= number_format($total) ?></span>
    <span class="pager">
<?php if ($page > 1): ?>
      <a href="<?= h($pageUrl($page - 1)) ?>">＜</a>
<?php else: ?>
      <span class="muted">＜</span>
<?php endif; ?>
<?php if ($page < $pages): ?>
      <a href="<?= h($pageUrl($page + 1)) ?>">＞</a>
<?php else: ?>
      <span class="muted">＞</span>
<?php endif; ?>
    </span>
  </div>

  <table class="table edit-table">
    <thead>
      <tr>
        <th><?= $link('body', '報告事項') ?>　<span class="muted"><?= $link('created', '登録順') ?></span></th>
        <th class="c-mark">削除</th>
      </tr>
    </thead>
    <tbody>
<?php if (!$rows): ?>
      <tr><td colspan="2" class="muted">まだ登録がありません。「＋追加」から登録してください。</td></tr>
<?php endif; ?>
<?php foreach ($rows as $t): ?>
      <tr>
        <td>
          <textarea class="textarea textarea--row" name="t[<?= (int) $t['id'] ?>][body]"
                    data-mic="1" placeholder="よく使う報告事項を入力"
                    aria-label="報告事項"><?= h((string) $t['body']) ?></textarea>
        </td>
        <td class="c-mark center">
          <button class="row-del" type="submit" name="delete_id"
                  value="<?= (int) $t['id'] ?>" formnovalidate title="この報告事項を隠す">×</button>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>

  <p style="text-align:center; margin:14px 0 0">
    <button class="btn btn--ghost btn--sm" type="submit" name="add" value="1" formnovalidate>
      ＋追加
    </button>
  </p>

  <p class="field-note" style="margin-left:0">
    空にして「登録」を押すと、その行は消えます。
  </p>
</form>

<?php if ($common): ?>
<h2 class="menu-heading">事務局が登録した共通の文章</h2>
<ul class="common-texts">
<?php foreach ($common as $c): ?>
  <li><?= h((string) $c['body']) ?></li>
<?php endforeach; ?>
</ul>
<p class="field-note" style="margin-left:0">
  こちらは全社共通のため、この画面では変更できません。<br>
  測定値・報告事項の画面では、上の自社分と合わせて選べます。
</p>
<?php endif; ?>
