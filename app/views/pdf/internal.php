<?php
/**
 * 社内用報告書のPDF型紙。概要書 4-7 のプレビュー画像を再現。
 *
 * @var array $report @var array $internal @var array $parts @var string $density
 */
$in = $internal;
$fs = ['d1' => 10.5, 'd2' => 9.5, 'd3' => 8.5][$density] ?? 10;
$sm = round($fs * 0.85, 1);

$rows = max(9, count($parts));   // 様式どおり9行の枠

$moveOut  = InternalReport::spanMinutes($in['travel_out_from'], $in['travel_out_to']);
$moveBack = InternalReport::spanMinutes($in['travel_back_from'], $in['travel_back_to']);
$work     = InternalReport::spanMinutes($in['work_from'], $in['work_to']);

$t    = fn($s) => Pdf::text($s);
$time = fn($v) => (string) $v === '' ? '　　:　　' : $t($v);
$len  = fn(?int $m) => $m === null ? '' : $t(InternalReport::formatMinutes($m));
?>
<style>
  td { font-family: ipaexg; }
  .h { background-color: #f2f2f2; text-align: center; }
</style>

<table cellpadding="0" cellspacing="0" border="0" width="100%">
  <tr>
    <td width="30%" style="font-size:7.5pt; color:#777;">（社内用）</td>
    <td width="40%" style="font-size:18pt; font-family:ipaexm; font-weight:bold; text-align:center; letter-spacing:6pt;">作業完了報告書</td>
    <td width="30%" style="font-size:<?= $sm ?>pt; text-align:right;"><?= $t(ymd_ja((string) $in['created_date'], false)) ?></td>
  </tr>
</table>

<table cellpadding="2" cellspacing="0" border="0" width="100%">
  <tr>
    <td width="58%" style="font-size:<?= $fs + 3 ?>pt; font-family:ipaexm; border-bottom:1.2pt solid #333;">
      <?= $t($in['hospital_name']) ?>　<span style="font-size:<?= $sm ?>pt;">御中</span>
    </td>
    <td width="42%" style="text-align:right;">
      <span style="font-size:<?= $fs + 3 ?>pt; font-family:ipaexm; font-weight:bold; letter-spacing:2pt;"><?= $t(config('company_name')) ?></span><br>
      <span style="font-size:7pt; color:#222;"><?= $t(config('company_address')) ?><br><?= $t(config('company_tel')) ?></span>
    </td>
  </tr>
</table>

<br>

<table cellpadding="3" cellspacing="0" border="1" width="100%" style="font-size:<?= $fs ?>pt;">
  <tr><td width="18%" class="h">作　業　日</td><td width="82%"><?= $t(ymd_ja((string) $in['work_date'], false)) ?></td></tr>
  <tr><td class="h">作 業 場 所</td><td><?= $t($in['work_place']) ?></td></tr>
  <tr><td class="h">作　業　者</td><td><?= $t($in['workers_text']) ?></td></tr>
  <tr><td class="h">作 業 件 名</td><td><?= $t($in['work_title']) ?></td></tr>
</table>

<table cellpadding="3" cellspacing="0" border="1" width="100%" style="font-size:<?= $fs ?>pt;">
  <tr>
    <td width="58%" class="h">今回作業時の残作業</td>
    <td width="42%" class="h">再手配の必要な部材</td>
  </tr>
  <tr>
    <td style="vertical-align:top;">
      <?= trim((string) $in['remaining_work']) !== '' ? $t($in['remaining_work']) : '<span style="color:#777;">（記載なし）</span>' ?>
    </td>
    <td style="vertical-align:top;">
      <table cellpadding="1" cellspacing="0" border="0" width="100%" style="font-size:<?= $sm ?>pt;">
        <tr>
          <td width="10%" style="border-bottom:0.5pt solid #333;">&nbsp;</td>
          <td width="66%" style="border-bottom:0.5pt solid #333;">部材名</td>
          <td width="24%" style="border-bottom:0.5pt solid #333; text-align:right;">数量</td>
        </tr>
<?php for ($i = 0; $i < $rows; $i++): ?>
<?php $p = $parts[$i] ?? null; ?>
        <tr>
          <td style="text-align:right; color:#777; border-bottom:0.3pt solid #bbb;"><?= $i + 1 ?></td>
          <td style="border-bottom:0.3pt solid #bbb;"><?= $p ? $t($p['part_name']) : '&nbsp;' ?></td>
          <td style="text-align:right; border-bottom:0.3pt solid #bbb;"><?= $p ? (int) $p['qty'] . $t($p['unit']) : '&nbsp;' ?></td>
        </tr>
<?php endfor; ?>
      </table>
    </td>
  </tr>
</table>

<table cellpadding="3" cellspacing="0" border="1" width="100%" style="font-size:<?= $fs ?>pt;">
  <tr>
    <td width="58%" class="h">移動及び作業時間の推移</td>
    <td width="42%" class="h">客先への営業アプローチ</td>
  </tr>
  <tr>
    <td style="vertical-align:top;">
      <span style="font-size:<?= $sm ?>pt;">移動時間・・・I　　作業時間・・・S</span><br>
      <table cellpadding="1" cellspacing="0" border="0" width="100%" style="font-size:<?= $sm ?>pt;">
        <tr>
          <td width="8%" style="text-align:center;">I</td><td width="8%" style="text-align:center;">往</td>
          <td width="26%" style="text-align:center; border-bottom:0.5pt solid #333;"><?= $time($in['travel_out_from']) ?></td>
          <td width="8%" style="text-align:center;">〜</td>
          <td width="26%" style="text-align:center; border-bottom:0.5pt solid #333;"><?= $time($in['travel_out_to']) ?></td>
          <td width="24%" style="text-align:right;"><?= $len($moveOut) ?></td>
        </tr>
        <tr>
          <td style="text-align:center;">S</td><td></td>
          <td style="text-align:center; border-bottom:0.5pt solid #333;"><?= $time($in['work_from']) ?></td>
          <td style="text-align:center;">〜</td>
          <td style="text-align:center; border-bottom:0.5pt solid #333;"><?= $time($in['work_to']) ?></td>
          <td style="text-align:right;"><?= $len($work) ?></td>
        </tr>
        <tr>
          <td style="text-align:center;">I</td><td style="text-align:center;">復</td>
          <td style="text-align:center; border-bottom:0.5pt solid #333;"><?= $time($in['travel_back_from']) ?></td>
          <td style="text-align:center;">〜</td>
          <td style="text-align:center; border-bottom:0.5pt solid #333;"><?= $time($in['travel_back_to']) ?></td>
          <td style="text-align:right;"><?= $len($moveBack) ?></td>
        </tr>
        <tr>
          <td colspan="3" style="color:#777; border-top:0.3pt dotted #999;">移動時間 計　<?= $t(InternalReport::totalLabel([$moveOut, $moveBack])) ?></td>
          <td colspan="3" style="color:#777; border-top:0.3pt dotted #999; text-align:right;">作業時間 計　<?= $t(InternalReport::totalLabel([$work])) ?></td>
        </tr>
      </table>
    </td>
    <td style="vertical-align:top;">
      <?= trim((string) $in['sales_approach']) !== '' ? $t($in['sales_approach']) : '<span style="color:#777;">（記載なし）</span>' ?>
    </td>
  </tr>
</table>

<table cellpadding="3" cellspacing="0" border="1" width="100%" style="font-size:<?= $fs ?>pt;">
  <tr><td class="h">備考（社内への報告事項等）</td></tr>
  <tr>
    <td style="vertical-align:top;">
      <?= trim((string) $in['remarks']) !== '' ? $t($in['remarks']) : '<span style="color:#777;">（記載なし）</span>' ?>
    </td>
  </tr>
</table>

<?php if ($in['completed_at']): ?>
<br>
<table cellpadding="0" cellspacing="0" border="0" width="100%">
  <tr><td style="text-align:right; color:#a3121a; font-size:<?= $sm ?>pt;">完了（請求済）　<?= $t(ymd_slash(substr((string) $in['completed_at'], 0, 10))) ?></td></tr>
</table>
<?php endif; ?>
