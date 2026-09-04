<?php
/**
 * 5-2 作業者テーブル_変更画面
 *
 * @var array $rows @var int $total @var int $page @var int $pages
 * @var int $from @var int $to @var string $sort @var string $dir
 */
$link = function (string $key, string $label) use ($sort, $dir) {
    $next = ($sort === $key && $dir === 'ASC') ? 'desc' : 'asc';
    $mark = $sort !== $key ? '▼' : ($dir === 'ASC' ? '▲' : '▼');
    $cls  = $sort === $key ? ' class="is-sorted"' : '';
    return sprintf('<a href="/mypage/workers?sort=%s&dir=%s"%s>%s%s</a>',
        h($key), $next, $cls, h($label), $mark);
};
$pageUrl = fn(int $p) => '/mypage/workers?' . http_build_query([
    'sort' => $sort, 'dir' => strtolower($dir), 'page' => $p,
]);
?>
<h1 class="page-title">作業者テーブル変更</h1>

<form method="post" action="/mypage/workers">
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
        <th class="c-date"><?= $link('created', '登録日') ?><br><?= $link('updated', '修正日') ?></th>
        <th><?= $link('name', '作業者') ?></th>
        <th class="c-mark">削除</th>
      </tr>
    </thead>
    <tbody>
<?php if (!$rows): ?>
      <tr><td colspan="3" class="muted">まだ登録がありません。「＋追加」から登録してください。</td></tr>
<?php endif; ?>
<?php foreach ($rows as $w): ?>
      <tr>
        <td class="c-date">
          <?= h(ymd_slash((string) $w['created_at'])) ?><br>
          <span class="muted"><?= h(ymd_slash((string) $w['updated_at'])) ?></span>
        </td>
        <td>
          <input class="input input--tight" type="text"
                 name="w[<?= (int) $w['id'] ?>][name]" value="<?= h((string) $w['name']) ?>"
                 data-mic="1" placeholder="氏名" aria-label="作業者名">
          <input class="input input--tight input--sub" type="text"
                 name="w[<?= (int) $w['id'] ?>][kana]" value="<?= h((string) $w['kana']) ?>"
                 placeholder="ヨミガナ（並べ替えに使います）" aria-label="ヨミガナ">
        </td>
        <td class="c-mark center">
          <button class="row-del" type="submit" name="delete_id"
                  value="<?= (int) $w['id'] ?>" formnovalidate
                  title="この作業者を隠す">×</button>
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
    氏名を空にして「登録」を押すと、その行は消えます。<br>
    「×」で隠した作業者は、過去の報告書には名前が残ります。
  </p>
</form>
