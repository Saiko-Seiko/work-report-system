<?php
/**
 * 3 報告書一覧_画面
 *
 * 概要書 3-10「病院名は一行で表示したい。作業日、作成日をそれぞれ行に分けた方が良い」
 * に沿って、日付は上下2段・病院名は1行（長いものは末尾を省略）にしている。
 *
 * @var array $rows @var int $total @var int $page @var int $pages
 * @var int $from @var int $to @var string $sort @var string $dir
 */
$link = function (string $key, string $label) use ($sort, $dir) {
    $next = ($sort === $key && $dir === 'DESC') ? 'asc' : 'desc';
    $mark = $sort !== $key ? '▼' : ($dir === 'DESC' ? '▼' : '▲');
    $cls  = $sort === $key ? ' class="is-sorted"' : '';
    return sprintf(
        '<a href="/reports?sort=%s&dir=%s"%s>%s%s</a>',
        h($key),
        $next,
        $cls,
        h($label),
        $mark
    );
};
$pageUrl = fn(int $p) => '/reports?' . http_build_query([
    'sort' => $sort,
    'dir'  => strtolower($dir),
    'page' => $p,
]);
?>
<div class="list-bar">
  <a class="btn btn--sm btn--ghost" href="/dashboard">ダッシュボード</a>
  <span class="list-bar__count"><?= $from ?>-<?= $to ?>/<?= number_format($total) ?></span>
  <span class="pager">
<?php if ($page > 1): ?>
    <a href="<?= h($pageUrl($page - 1)) ?>" aria-label="前のページ">＜</a>
<?php else: ?>
    <span class="muted">＜</span>
<?php endif; ?>
<?php if ($page < $pages): ?>
    <a href="<?= h($pageUrl($page + 1)) ?>" aria-label="次のページ">＞</a>
<?php else: ?>
    <span class="muted">＞</span>
<?php endif; ?>
  </span>
</div>

<?php if (!$rows): ?>
<div class="alert alert--info">
  まだ報告書がありません。
  <a href="/report/new">新しく作成する</a>
</div>
<?php else: ?>

<div class="list-scroll">
  <table class="table list-table">
    <thead>
      <tr>
        <th class="c-no"><?= $link('no', 'No.') ?></th>
        <th class="c-date"><?= $link('work', '作業日') ?><br><?= $link('created', '作成日') ?></th>
        <th class="c-hospital"><?= $link('hospital', '病院名') ?></th>
        <th class="c-worker">作業者</th>
        <th class="c-mark">署名</th>
        <th class="c-mark">PDF</th>
        <th class="c-mark">Mail</th>
        <th class="c-mark">社内用</th>
        <th class="c-mark">状態</th>
        <th class="c-copy">複製</th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($rows as $r): ?>
<?php
      $id          = (int) $r['id'];
      $hasSign     = !empty($r['signature_at']);
      $hasPdf      = !empty($r['pdf_at']);
      $hasInternal = !empty($r['internal_pdf_at']);
      $done        = !empty($r['internal_completed_at']) || !empty($r['completed_at']);
?>
      <tr>
        <td class="c-no num"><?= (int) $r['report_no'] ?></td>
        <td class="c-date">
          <?= h(ymd_slash((string) $r['work_date'])) ?><br>
          <span class="muted"><?= h(ymd_slash((string) $r['created_date'])) ?></span>
        </td>
        <td class="c-hospital">
          <a class="list-hospital" href="/report/<?= $id ?>/basic"
             title="<?= h((string) $r['hospital_name']) ?>">
            <?= h((string) $r['hospital_name'] ?: '（病院名未入力）') ?>
          </a>
        </td>
        <td class="c-worker" title="<?= h((string) $r['workers_text']) ?>">
          <?= h(mb_strimwidth((string) $r['workers_text'], 0, 8, '…')) ?>
        </td>
        <td class="c-mark center"><?= $hasSign ? '有' : '－' ?></td>
        <td class="c-mark center">
<?php if ($hasPdf): ?>
          <a href="/report/<?= $id ?>/preview" title="プレビュー">●</a>
<?php else: ?>
          －
<?php endif; ?>
        </td>
        <td class="c-mark center"><?= (int) $r['mail_count'] ?></td>
        <td class="c-mark center">
<?php if ($hasInternal): ?>
          <a href="/report/<?= $id ?>/internal" title="社内用報告書">●</a>
<?php else: ?>
          －
<?php endif; ?>
        </td>
        <td class="c-mark center"><?= $done ? '完' : '－' ?></td>
        <td class="c-copy center">
          <form method="post" action="/reports/copy" data-copy>
            <?= csrf_field() ?>
            <input type="hidden" name="source_id" value="<?= $id ?>">
            <input type="hidden" name="client_uuid" value="">
            <button class="list-copy" type="submit"
                    title="この報告書を写して新しく作る">＋</button>
          </form>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>

<p class="field-note" style="margin-left:0">
  病院名をタップすると修正できます。PDFの「●」でプレビュー、「＋」でこの内容を写して新規作成します。
</p>
<?php endif; ?>
