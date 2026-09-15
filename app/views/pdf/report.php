<?php
/**
 * 作業完了報告書（客先提出用）のPDF型紙。概要書 2-8 のプレビュー画像を再現。
 *
 * TCPDF の writeHTML に渡すので、CSSの flex や grid は使えない。
 * 表（table）と幅の指定だけで組んでいる。
 *
 * @var array  $report @var array $models @var array $parts @var array $measurements
 * @var string $density d1|d2|d3   @var string $signatureData  サイン画像の data: URI（無ければ ''）
 */
$r  = $report;
$fs = ['d1' => 10.5, 'd2' => 9.5, 'd3' => 8.5][$density] ?? 10;   // pt
$sm = round($fs * 0.85, 1);

$workDate = ymd_ja((string) $r['work_date'], false);
if ($r['work_date_end'] && $r['work_date_end'] !== $r['work_date']) {
    $workDate .= '〜' . ymd_ja((string) $r['work_date_end'], false);
}

$lines = array_values(array_filter(array_map('trim',
    preg_split('/\R/u', (string) $r['report_body']) ?: []), fn($s) => $s !== ''));

$t = fn($s) => Pdf::text($s);
?>
<style>
  td { font-family: ipaexg; }
  .h { background-color: #f2f2f2; text-align: center; }
</style>

<table cellpadding="0" cellspacing="0" border="0" width="100%">
  <tr>
    <td width="30%" style="font-size:7.5pt; color:#777;">（原本 社内用1）</td>
    <td width="40%" style="font-size:18pt; font-family:ipaexm; font-weight:bold; text-align:center; letter-spacing:6pt;">作業完了報告書</td>
    <td width="30%" style="font-size:<?= $sm ?>pt; text-align:right;"><?= $t(ymd_ja((string) $r['created_date'], false)) ?></td>
  </tr>
</table>

<table cellpadding="2" cellspacing="0" border="0" width="100%">
  <tr>
    <td width="58%" style="font-size:<?= $fs + 3 ?>pt; font-family:ipaexm; border-bottom:1.2pt solid #333;">
      <?= $t($r['hospital_name']) ?>　<span style="font-size:<?= $sm ?>pt;">御中</span>
    </td>
    <td width="42%" style="text-align:right;">
      <span style="font-size:<?= $fs + 3 ?>pt; font-family:ipaexm; font-weight:bold; letter-spacing:2pt;"><?= $t(config('company_name')) ?></span><br>
      <span style="font-size:7pt; color:#222;"><?= $t(config('company_address')) ?><br><?= $t(config('company_tel')) ?><br><?= $t(config('company_branch')) ?></span>
    </td>
  </tr>
</table>

<br>

<table cellpadding="3" cellspacing="0" border="1" width="100%" style="font-size:<?= $fs ?>pt;">
  <tr><td width="18%" class="h">作　業　日</td><td width="82%"><?= $t($workDate) ?></td></tr>
  <tr><td class="h">作 業 場 所</td><td><?= $t($r['work_place']) ?></td></tr>
  <tr><td class="h">作　業　者</td><td><?= $t($r['workers_text']) ?></td></tr>
  <tr><td class="h">作 業 件 名</td><td><?= $t($r['work_title']) ?></td></tr>
</table>

<table cellpadding="3" cellspacing="0" border="1" width="100%" style="font-size:<?= $fs ?>pt;">
  <tr>
    <td width="58%" class="h">作　業　内　容</td>
    <td width="42%" class="h">交換部品名・数量</td>
  </tr>
  <tr>
    <td style="vertical-align:top;">
<?php foreach ($models as $m): ?>
      ・<?= $t($m['model_name']) ?>　×<?= (int) $m['qty'] ?>台<br>
<?php endforeach; ?>
<?php if (trim((string) $r['work_note']) !== ''): ?>
      <?= $models ? '<br>' : '' ?><?= $t($r['work_note']) ?>
<?php endif; ?>
<?php if (!$models && trim((string) $r['work_note']) === ''): ?>
      <span style="color:#777;">（記載なし）</span>
<?php endif; ?>
    </td>
    <td style="vertical-align:top;">
<?php if ($parts): ?>
      <table cellpadding="1" cellspacing="0" border="0" width="100%" style="font-size:<?= $sm ?>pt;">
<?php foreach ($parts as $i => $p): ?>
        <tr>
          <td width="10%" style="text-align:right; color:#777;"><?= $i + 1 ?></td>
          <td width="66%"><?= $t($p['part_name']) ?></td>
          <td width="24%" style="text-align:right;"><?= (int) $p['qty'] ?><?= $t($p['unit']) ?></td>
        </tr>
<?php endforeach; ?>
      </table>
<?php else: ?>
      <span style="color:#777;">（交換部品なし）</span>
<?php endif; ?>
<?php if (trim((string) $r['parts_note']) !== ''): ?>
      <br><span style="font-size:<?= $sm ?>pt;"><?= $t($r['parts_note']) ?></span>
<?php endif; ?>
    </td>
  </tr>
</table>

<table cellpadding="2" cellspacing="0" border="1" width="100%" style="font-size:<?= $sm ?>pt;">
  <tr>
    <td width="14%" class="h">部屋名</td>
    <td width="36%" class="h">型式</td>
    <td width="16%" class="h">積算時間</td>
    <td width="16%" class="h">製造No.</td>
    <td width="18%" class="h">製造年月</td>
  </tr>
<?php if ($measurements): ?>
<?php foreach ($measurements as $m): ?>
  <tr>
    <td style="text-align:center;"><?= $t($m['room_name']) ?></td>
    <td><?= $t($m['model_name']) ?></td>
    <td style="text-align:center;"><?= $m['cumulative_hours'] === null ? '' : number_format((int) $m['cumulative_hours']) . ' h' ?></td>
    <td style="text-align:center;"><?= $t($m['serial_no']) ?></td>
    <td style="text-align:center;"><?= $m['manufactured_ym'] ? $t(str_replace('-', '年', (string) $m['manufactured_ym']) . '月') : '' ?></td>
  </tr>
<?php endforeach; ?>
<?php else: ?>
  <tr><td colspan="5" style="color:#777;">（測定値の記載なし）</td></tr>
<?php endif; ?>
</table>

<table cellpadding="3" cellspacing="0" border="1" width="100%" style="font-size:<?= $fs ?>pt;">
  <tr><td class="h">報　告　事　項</td></tr>
  <tr>
    <td style="vertical-align:top;">
<?php if ($lines): ?>
<?php foreach ($lines as $line): ?>
      ・<?= $t($line) ?><br>
<?php endforeach; ?>
<?php else: ?>
      <span style="color:#777;">（報告事項の記載なし）</span>
<?php endif; ?>
    </td>
  </tr>
</table>

<br>

<table cellpadding="2" cellspacing="0" border="0" width="100%" style="font-size:<?= $fs ?>pt;">
  <tr>
    <td width="34%" style="vertical-align:bottom;">上記の内容を報告致します。</td>
    <td width="10%" style="text-align:right; vertical-align:bottom;">サイン</td>
    <td width="24%" style="border-bottom:0.5pt solid #333; text-align:center; vertical-align:bottom;">
<?php if ($signatureData !== ''): ?>
      <img src="<?= $signatureData ?>" height="38">
<?php else: ?>
      &nbsp;
<?php endif; ?>
    </td>
    <td width="8%" style="text-align:right; vertical-align:bottom;">担当</td>
    <td width="24%" style="border-bottom:0.5pt solid #333; text-align:center; vertical-align:bottom; font-size:<?= $fs + 1 ?>pt;">
      <?= $t($r['submitter_name']) ?: '&nbsp;' ?>
    </td>
  </tr>
</table>
