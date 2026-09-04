<?php
/**
 * 作業完了報告書 A4 1枚（概要書 2-8 のプレビュー画像を再現）
 *
 * この1ファイルが、プレビュー・印刷・本番のPDF出力で共通の「型紙」になる。
 * 本番では同じHTMLを mPDF に渡すので、画面と紙の見た目がずれない。
 *
 * @var array  $report
 * @var array  $models
 * @var array  $parts
 * @var array  $measurements
 * @var string $density   d1|d2|d3（載る量に応じた詰め具合）
 * @var string|null $signatureUrl  サイン画像の場所（利用者用と管理者用で違う）
 * @var bool   $forPrint  開いたら印刷ダイアログを出すか
 */
$r = $report;

/* 作業日。複数日にまたがる場合は「〜」でつなぐ */
$workDate = ymd_ja((string) $r['work_date'], false);
if ($r['work_date_end'] && $r['work_date_end'] !== $r['work_date']) {
    $workDate .= '〜' . ymd_ja((string) $r['work_date_end'], false);
}

/* 報告事項は行ごとに「・」を付けて並べる */
$reportLines = array_values(array_filter(array_map(
    'trim',
    preg_split('/\R/u', (string) $r['report_body']) ?: []
), fn($s) => $s !== ''));

$hasSign      = !empty($r['signature_at']);
$signatureUrl = $signatureUrl ?? ('/report/' . (int) $r['id'] . '/signature.png');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>作業完了報告書 No.<?= (int) $r['report_no'] ?></title>
<link rel="stylesheet" href="/assets/css/sheet.css?v=1">
</head>
<body class="<?= query('guide') === '1' ? 'guide' : '' ?>">

<div class="sheet <?= h($density) ?>">

  <div class="sheet__kind">（原本 社内用1）</div>

  <h1 class="sheet__title">作業完了報告書</h1>

  <div class="sheet__date"><?= h(ymd_ja((string) $r['created_date'], false)) ?></div>

  <div class="sheet__head">
    <div class="sheet__to">
      <?= h((string) $r['hospital_name']) ?><em>御中</em>
    </div>
    <div class="sheet__from">
      <strong><?= h(config('company_name')) ?></strong>
      <span><?= h(config('company_address')) ?></span>
      <span><?= h(config('company_tel')) ?></span>
      <span><?= h(config('company_branch')) ?></span>
    </div>
  </div>

  <table class="s-table info-table">
    <tr>
      <th>作業日</th>
      <td><?= h($workDate) ?></td>
    </tr>
    <tr>
      <th>作業場所</th>
      <td><?= h((string) $r['work_place']) ?></td>
    </tr>
    <tr>
      <th>作業者</th>
      <td><?= h((string) $r['workers_text']) ?></td>
    </tr>
    <tr>
      <th>作業件名</th>
      <td><?= h((string) $r['work_title']) ?></td>
    </tr>
  </table>

  <div class="body-grid">
    <div class="body-grid__left">
      <div class="block">
        <div class="block__head">作業内容</div>
        <div class="block__body">
<?php if ($models): ?>
          <ul class="work-list">
<?php foreach ($models as $m): ?>
            <li><?= h($m['model_name']) ?>　×<?= (int) $m['qty'] ?>台</li>
<?php endforeach; ?>
          </ul>
<?php endif; ?>
<?php if (trim((string) $r['work_note']) !== ''): ?>
          <p class="work-note"><?= h((string) $r['work_note']) ?></p>
<?php endif; ?>
<?php if (!$models && trim((string) $r['work_note']) === ''): ?>
          <p class="parts-empty">（記載なし）</p>
<?php endif; ?>
        </div>
      </div>
    </div>

    <div class="body-grid__right">
      <div class="block">
        <div class="block__head">交換部品名・数量</div>
        <div class="block__body">
<?php if ($parts): ?>
          <table class="parts-table">
<?php foreach ($parts as $i => $p): ?>
            <tr>
              <td class="no"><?= $i + 1 ?></td>
              <td><?= h($p['part_name']) ?></td>
              <td class="qty"><?= (int) $p['qty'] ?><?= h($p['unit']) ?></td>
            </tr>
<?php endforeach; ?>
          </table>
<?php else: ?>
          <p class="parts-empty">（交換部品なし）</p>
<?php endif; ?>
<?php if (trim((string) $r['parts_note']) !== ''): ?>
          <p class="parts-note"><?= h((string) $r['parts_note']) ?></p>
<?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <table class="s-table measure-table">
    <colgroup>
      <col class="c-room"><col class="c-model"><col class="c-hours">
      <col class="c-serial"><col class="c-ym">
    </colgroup>
    <tr>
      <th>部屋名</th>
      <th>型式</th>
      <th>積算時間</th>
      <th>製造No.</th>
      <th>製造年月</th>
    </tr>
<?php if ($measurements): ?>
<?php foreach ($measurements as $m): ?>
    <tr>
      <td><?= h((string) $m['room_name']) ?></td>
      <td class="left"><?= h((string) $m['model_name']) ?></td>
      <td><?= $m['cumulative_hours'] === null ? '' : number_format((int) $m['cumulative_hours']) . ' h' ?></td>
      <td><?= h((string) $m['serial_no']) ?></td>
      <td><?= $m['manufactured_ym'] ? h(str_replace('-', '年', (string) $m['manufactured_ym']) . '月') : '' ?></td>
    </tr>
<?php endforeach; ?>
<?php else: ?>
    <tr><td colspan="5" class="empty left">（測定値の記載なし）</td></tr>
<?php endif; ?>
  </table>

  <div class="block report-block">
    <div class="block__head">報告事項</div>
    <div class="block__body">
<?php if ($reportLines): ?>
      <ul class="report-list">
<?php foreach ($reportLines as $line): ?>
        <li><?= h($line) ?></li>
<?php endforeach; ?>
      </ul>
<?php else: ?>
      <p class="report-empty">（報告事項の記載なし）</p>
<?php endif; ?>
    </div>
  </div>

  <div class="sheet__foot">
    <div class="sheet__foot-lead">上記の内容を報告致します。</div>

    <div class="sign-row">
      <div class="sign-cell">
        <span class="sign-cell__label">サイン</span>
        <span class="sign-cell__value">
<?php if ($hasSign): ?>
          <img src="<?= h($signatureUrl) ?>?t=<?= h((string) strtotime((string) $r['signature_at'])) ?>"
               alt="">
<?php endif; ?>
        </span>
      </div>
      <div class="sign-cell">
        <span class="sign-cell__label">担当</span>
        <span class="sign-cell__value">
          <span><?= h((string) $r['submitter_name']) ?></span>
        </span>
      </div>
    </div>
  </div>

</div>

<?php if (!empty($forPrint)): ?>
<script>
/* 印刷画面（2-9）から開かれたときは、そのまま印刷ダイアログを出す */
window.addEventListener('load', function () {
  setTimeout(function () { window.print(); }, 250);
});
</script>
<?php endif; ?>
</body>
</html>
