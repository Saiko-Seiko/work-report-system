<?php
/**
 * 社内用報告書 A4 1枚（概要書 4-7 のプレビュー画像を再現）
 *
 * 客先提出用（sheet/report.php）とは中身が違う。
 *   上段：今回作業時の残作業 ／ 再手配の必要な部材（数量つき）
 *   中段：移動及び作業時間の推移 ／ 客先への営業アプローチ
 *   下段：備考（社内への報告事項等）
 *
 * @var array $report @var array $internal @var array $parts @var bool $forPrint
 */
$in = $internal;

/* 再手配の部材は、概要書の様式に合わせて9行分の枠を用意する */
$partRows = max(9, count($parts));

$moveOut  = InternalReport::spanMinutes($in['travel_out_from'], $in['travel_out_to']);
$moveBack = InternalReport::spanMinutes($in['travel_back_from'], $in['travel_back_to']);
$work     = InternalReport::spanMinutes($in['work_from'], $in['work_to']);

/* 載る量に応じて詰め具合を決める（客先提出用と同じ考え方） */
$weight = count($parts)
    + mb_strlen((string) $in['remaining_work']) / 34
    + mb_strlen((string) $in['sales_approach']) / 34
    + mb_strlen((string) $in['remarks']) / 34;
$density = $weight <= 18 ? 'd1' : ($weight <= 30 ? 'd2' : 'd3');

$time = fn($v) => (string) $v === '' ? '　　:　　' : h((string) $v);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>社内用 作業完了報告書 No.<?= (int) $report['report_no'] ?></title>
<link rel="stylesheet" href="/assets/css/sheet.css?v=2">
</head>
<body class="<?= query('guide') === '1' ? 'guide' : '' ?>">

<div class="sheet <?= h($density) ?>">

  <div class="sheet__kind">（社内用）</div>

  <h1 class="sheet__title">作業完了報告書</h1>

  <div class="sheet__head">
    <div class="sheet__to">
      <?= h((string) $in['hospital_name']) ?><em>御中</em>
    </div>
    <div class="sheet__from">
      <strong><?= h(config('company_name')) ?></strong>
      <span><?= h(config('company_address')) ?></span>
      <span><?= h(config('company_tel')) ?></span>
    </div>
  </div>
  <div class="sheet__date"><?= h(ymd_ja((string) $in['created_date'], false)) ?></div>

  <table class="s-table info-table">
    <tr><th>作業日</th><td><?= h(ymd_ja((string) $in['work_date'], false)) ?></td></tr>
    <tr><th>作業場所</th><td><?= h((string) $in['work_place']) ?></td></tr>
    <tr><th>作業者</th><td><?= h((string) $in['workers_text']) ?></td></tr>
    <tr><th>作業件名</th><td><?= h((string) $in['work_title']) ?></td></tr>
  </table>

  <!-- 上段：残作業 ／ 再手配の必要な部材 -->
  <div class="body-grid">
    <div class="body-grid__left">
      <div class="block block--tall">
        <div class="block__head">今回作業時の残作業</div>
        <div class="block__body">
<?php if (trim((string) $in['remaining_work']) !== ''): ?>
          <p class="free-text"><?= h((string) $in['remaining_work']) ?></p>
<?php else: ?>
          <p class="parts-empty">（記載なし）</p>
<?php endif; ?>
        </div>
      </div>
    </div>

    <div class="body-grid__right">
      <div class="block block--tall">
        <div class="block__head">再手配の必要な部材</div>
        <div class="block__body block__body--flush">
          <table class="parts-table parts-table--ruled">
            <tr>
              <th class="no">&nbsp;</th>
              <th>部材名</th>
              <th class="qty">数量</th>
            </tr>
<?php for ($i = 0; $i < $partRows; $i++): ?>
<?php $p = $parts[$i] ?? null; ?>
            <tr>
              <td class="no"><?= $i + 1 ?></td>
              <td><?= $p ? h((string) $p['part_name']) : '' ?></td>
              <td class="qty"><?= $p ? (int) $p['qty'] . h((string) $p['unit']) : '' ?></td>
            </tr>
<?php endfor; ?>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- 中段：移動及び作業時間 ／ 営業アプローチ -->
  <div class="body-grid">
    <div class="body-grid__left">
      <div class="block block--tall">
        <div class="block__head">移動及び作業時間の推移</div>
        <div class="block__body">
          <p class="hours-legend hours-legend--sheet">移動時間・・・I　　作業時間・・・S</p>
          <table class="hours-sheet">
            <tr>
              <td class="mark">I</td>
              <td class="label">往</td>
              <td class="time"><?= $time($in['travel_out_from']) ?></td>
              <td class="tilde">〜</td>
              <td class="time"><?= $time($in['travel_out_to']) ?></td>
              <td class="len"><?= $moveOut === null ? '' : h(InternalReport::formatMinutes($moveOut)) ?></td>
            </tr>
            <tr>
              <td class="mark">S</td>
              <td class="label">&nbsp;</td>
              <td class="time"><?= $time($in['work_from']) ?></td>
              <td class="tilde">〜</td>
              <td class="time"><?= $time($in['work_to']) ?></td>
              <td class="len"><?= $work === null ? '' : h(InternalReport::formatMinutes($work)) ?></td>
            </tr>
            <tr>
              <td class="mark">I</td>
              <td class="label">復</td>
              <td class="time"><?= $time($in['travel_back_from']) ?></td>
              <td class="tilde">〜</td>
              <td class="time"><?= $time($in['travel_back_to']) ?></td>
              <td class="len"><?= $moveBack === null ? '' : h(InternalReport::formatMinutes($moveBack)) ?></td>
            </tr>
          </table>
          <table class="hours-sheet hours-sheet--total">
            <tr>
              <td class="label">移動時間 計</td>
              <td class="len"><?= h(InternalReport::totalLabel([$moveOut, $moveBack])) ?></td>
              <td class="label">作業時間 計</td>
              <td class="len"><?= h(InternalReport::totalLabel([$work])) ?></td>
            </tr>
          </table>
        </div>
      </div>
    </div>

    <div class="body-grid__right">
      <div class="block block--tall">
        <div class="block__head">客先への営業アプローチ</div>
        <div class="block__body">
<?php if (trim((string) $in['sales_approach']) !== ''): ?>
          <p class="free-text"><?= h((string) $in['sales_approach']) ?></p>
<?php else: ?>
          <p class="parts-empty">（記載なし）</p>
<?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- 下段：備考 -->
  <div class="block report-block">
    <div class="block__head">備考（社内への報告事項等）</div>
    <div class="block__body">
<?php if (trim((string) $in['remarks']) !== ''): ?>
      <p class="free-text"><?= h((string) $in['remarks']) ?></p>
<?php else: ?>
      <p class="parts-empty">（記載なし）</p>
<?php endif; ?>
    </div>
  </div>

<?php if ($in['completed_at']): ?>
  <div class="sheet__stamp">完了（請求済）<?= h(ymd_slash(substr((string) $in['completed_at'], 0, 10))) ?></div>
<?php endif; ?>

</div>

<?php if (!empty($forPrint)): ?>
<script>
window.addEventListener('load', function () {
  setTimeout(function () { window.print(); }, 250);
});
</script>
<?php endif; ?>
</body>
</html>
