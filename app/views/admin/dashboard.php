<?php
/**
 * K-2 ダッシュボード画面
 * @var array $rows @var array $pager @var string $q @var string $sort @var string $dir
 * @var array $stats
 */
$keep = ['q' => $q];
$link = fn(string $key, string $label) =>
    admin_sort_link('/admin/dashboard', $keep, $key, $label, $sort, $dir);
?>
<div class="stat-row">
<?php foreach ($stats as $label => $n): ?>
  <div class="stat">
    <b><?= number_format($n) ?></b>
    <span><?= h($label) ?></span>
  </div>
<?php endforeach; ?>
</div>

<form class="toolbar" method="get" action="/admin/dashboard">
  <label for="q">検索：</label>
  <input class="input input--wide" type="search" id="q" name="q" value="<?= h($q) ?>"
         placeholder="病院名・作業件名・作業者・協力会社・報告書No.">
  <button class="btn" type="submit">実行</button>
  <input type="hidden" name="sort" value="<?= h($sort) ?>">
  <input type="hidden" name="dir" value="<?= h(strtolower($dir)) ?>">

  <span class="toolbar__spacer"></span>
  <span class="pager">
    <span><?= $pager['from'] ?>-<?= $pager['to'] ?>/<?= number_format($pager['total']) ?></span>
<?php if ($pager['page'] > 1): ?>
    <a href="<?= h(admin_page_url('/admin/dashboard', $keep + ['sort' => $sort, 'dir' => strtolower($dir)], $pager['page'] - 1)) ?>">＜</a>
<?php else: ?>
    <span class="muted">＜</span>
<?php endif; ?>
<?php if ($pager['page'] < $pager['pages']): ?>
    <a href="<?= h(admin_page_url('/admin/dashboard', $keep + ['sort' => $sort, 'dir' => strtolower($dir)], $pager['page'] + 1)) ?>">＞</a>
<?php else: ?>
    <span class="muted">＞</span>
<?php endif; ?>
  </span>
</form>

<?php if ($q !== '' && !$rows): ?>
<div class="alert alert--warn">「<?= h($q) ?>」に一致する報告書は見つかりませんでした。</div>
<?php endif; ?>

<table class="table admin-report-table">
  <thead>
    <tr>
      <th class="c-no"><?= $link('no', 'No.') ?></th>
      <th class="c-date"><?= $link('work', '作業日') ?></th>
      <th class="c-date"><?= $link('created', '作成日') ?></th>
      <th><?= $link('hospital', '病院名') ?></th>
      <th class="c-company"><?= $link('company', '協力会社') ?></th>
      <th class="c-worker">作業者</th>
      <th class="c-mark">署名</th>
      <th class="c-mark">PDF</th>
      <th class="c-mark">Mail</th>
      <th class="c-mark">社内用</th>
      <th class="c-mark"><?= $link('status', '状態') ?></th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($rows as $r): ?>
<?php
    $id   = (int) $r['id'];
    $done = !empty($r['completed_at']) || !empty($r['internal_completed_at']);
?>
    <tr>
      <td class="c-no num"><?= (int) $r['report_no'] ?></td>
      <td class="c-date"><?= h(ymd_slash((string) $r['work_date'])) ?></td>
      <td class="c-date"><?= h(ymd_slash((string) $r['created_date'])) ?></td>
      <td><?= h((string) $r['hospital_name'] ?: '（未入力）') ?></td>
      <td class="c-company"><?= h((string) $r['company_name']) ?></td>
      <td class="c-worker"><?= h(mb_strimwidth((string) $r['workers_text'], 0, 14, '…')) ?></td>
      <td class="c-mark center"><?= $r['signature_at'] ? '有' : '－' ?></td>
      <td class="c-mark center">
<?php if ($r['pdf_at']): ?>
        <a class="mark-link" href="/admin/report/<?= $id ?>/sheet" target="_blank"
           rel="noopener" title="報告書PDFを表示">●</a>
<?php else: ?>
        －
<?php endif; ?>
      </td>
      <td class="c-mark center"><?= (int) $r['mail_count'] ?></td>
      <td class="c-mark center">
<?php if ($r['internal_pdf_at']): ?>
        <a class="mark-link" href="/admin/report/<?= $id ?>/internal-sheet" target="_blank"
           rel="noopener" title="社内用報告書PDFを表示">●</a>
<?php else: ?>
        －
<?php endif; ?>
      </td>
      <td class="c-mark center"><?= $done ? '完' : '－' ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>

<p class="muted" style="font-size:13px; margin-top:12px">
  「PDF」「社内用」の●を押すと、その報告書をA4の様式で別のタブに表示します（そのまま印刷できます）。
  「状態」の見出しを押すと完了したものを先頭にまとめられます。
</p>
