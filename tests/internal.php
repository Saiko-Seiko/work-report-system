<?php
/**
 * Phase 7：社内用報告書（4-1〜4-8）と、業務フロー ⑥完了（請求済）の確認。
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';


req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0001', 'password' => 'pass1234']);

// 社内用がまだ無い報告書を使う（提出済みの内容が写るところを見たい）
$row = Database::one(
    "SELECT r.* FROM reports r
      WHERE r.account_id = 1 AND r.deleted_at IS NULL
        AND EXISTS (SELECT 1 FROM report_parts p WHERE p.report_id = r.id AND p.qty > 0)
        AND NOT EXISTS (SELECT 1 FROM internal_reports i WHERE i.report_id = r.id)
      ORDER BY r.report_no DESC LIMIT 1"
);
if (!$row) {
    // 全件に社内用がある場合は1件消してから試す
    $row = Database::one(
        "SELECT r.* FROM reports r
          WHERE r.account_id = 1 AND EXISTS (SELECT 1 FROM report_parts p WHERE p.report_id = r.id AND p.qty > 0)
          ORDER BY r.report_no DESC LIMIT 1"
    );
    $iid = Database::value('SELECT id FROM internal_reports WHERE report_id = ?', [$row['id']]);
    Database::run('DELETE FROM internal_report_parts WHERE internal_report_id = ?', [$iid]);
    Database::run('DELETE FROM internal_reports WHERE id = ?', [$iid]);
}
$id = (int) $row['id'];
echo "対象 No.{$row['report_no']} / {$row['hospital_name']}（id={$id}）\n";

// ---------------------------------------------------------------- 入口と初期値
echo "--- 入口：客先提出済の内容が写るか（4-1） ---\n";
$r = req('GET', "/report/{$id}/internal");
check('入口から 4-1 へ進む', str_contains($r['location'], "/report/{$id}/internal/basic"));

$in = Database::one('SELECT * FROM internal_reports WHERE report_id = ?', [$id]);
check('社内用が1枚だけ作られる', $in !== null
    && (int) Database::value('SELECT COUNT(*) FROM internal_reports WHERE report_id = ?', [$id]) === 1);
check('基本情報が写る',
    $in['hospital_name'] === $row['hospital_name']
    && $in['work_place'] === $row['work_place']
    && $in['work_title'] === $row['work_title']
    && $in['workers_text'] === $row['workers_text']);

$srcParts = (int) Database::value(
    'SELECT COUNT(*) FROM report_parts WHERE report_id = ? AND qty > 0', [$id]);
check('交換部品が再手配の初期値として写る',
    (int) Database::value(
        'SELECT COUNT(*) FROM internal_report_parts WHERE internal_report_id = ?', [$in['id']]
    ) === $srcParts, "{$srcParts}点");
check('数量も写る',
    (int) Database::value('SELECT SUM(qty) FROM internal_report_parts WHERE internal_report_id = ?', [$in['id']])
    === (int) Database::value('SELECT SUM(qty) FROM report_parts WHERE report_id = ? AND qty > 0', [$id]));

// 二度開いても増えない
req('GET', "/report/{$id}/internal");
check('二度開いても1枚のまま',
    (int) Database::value('SELECT COUNT(*) FROM internal_reports WHERE report_id = ?', [$id]) === 1);

echo "--- ステップ表示（1〜6） ---\n";
$body = req('GET', "/report/{$id}/internal/basic")['body'];
foreach (['1 基本情報', '2 今回作業時の残作業', '3 再手配の必要な部材',
          '4 移動および作業時間の推移', '5 客先への営業アプローチ', '6.備考（社内への報告事項等）'] as $label) {
    check("「{$label}」がある", str_contains($body, $label));
}
check('客先の内容を写した旨を出している', str_contains($body, '客先へ提出した報告書の内容'));

// ---------------------------------------------------------------- 4-1
echo "--- 4-1 基本情報（全て必須） ---\n";
$r = req('POST', "/report/{$id}/internal/basic", [
    '_csrf' => csrf("/report/{$id}/internal/basic"),
    'created_date' => '', 'hospital_name' => '', 'work_date' => '',
    'work_place' => '', 'workers_text' => '', 'work_title' => '', 'next' => '1',
]);
$errs = preg_match_all('/class="field-error">([^<]*)/', $r['body'], $m);
check('6項目すべてで必須エラーが出る', $errs === 6, implode(' / ', array_slice($m[1], 0, 3)) . ' …');

$r = req('POST', "/report/{$id}/internal/basic", [
    '_csrf' => csrf("/report/{$id}/internal/basic"),
    'created_date' => '2026-09-03', 'hospital_name' => '横浜市立大学附属病院',
    'work_date' => '2026-08-25', 'work_place' => '4階 中央無菌室（社内用に補記）',
    'workers_text' => '落合健一、米窪花子', 'work_title' => '無菌病室 保守点検', 'next' => '1',
]);
check('つぎへで 4-2 に進む', str_contains($r['location'], "/report/{$id}/internal/remain"));
check('社内用だけ直せる（客先提出用は変わらない）',
    Database::value('SELECT work_place FROM internal_reports WHERE report_id = ?', [$id])
        === '4階 中央無菌室（社内用に補記）'
    && Database::value('SELECT work_place FROM reports WHERE id = ?', [$id]) === $row['work_place']);

// ---------------------------------------------------------------- 4-2
echo "--- 4-2 今回作業時の残作業 ---\n";
$r = req('POST', "/report/{$id}/internal/remain", [
    '_csrf' => csrf("/report/{$id}/internal/remain"),
    'remaining_work' => "HEPAフィルターは次回定期時に交換予定。\n入口扉クローザの調整は部材待ち。",
    'next' => '1',
]);
check('つぎへで 4-3 に進む', str_contains($r['location'], "/report/{$id}/internal/parts"));
check('残作業が保存される', str_contains(
    (string) Database::value('SELECT remaining_work FROM internal_reports WHERE report_id = ?', [$id]),
    'HEPAフィルター'));

// ---------------------------------------------------------------- 4-3
echo "--- 4-3 再手配の必要な部材 ---\n";
$body = req('GET', "/report/{$id}/internal/parts")['body'];
check('初期値が「再手配する部材」として並ぶ', str_contains($body, '再手配する部材'));

// まだ入っていない部材を1点足し、入っている1点を外す → 点数は変わらないはず
$extra = Database::one(
    'SELECT id, name FROM parts
      WHERE deleted_at IS NULL
        AND id NOT IN (SELECT part_id FROM internal_report_parts WHERE internal_report_id = ?)
      ORDER BY priority DESC, id LIMIT 1',
    [$in['id']]);
$drop  = Database::one(
    'SELECT part_id FROM internal_report_parts WHERE internal_report_id = ? ORDER BY sort_order LIMIT 1',
    [$in['id']]);
$before = (int) Database::value(
    'SELECT COUNT(*) FROM internal_report_parts WHERE internal_report_id = ? AND qty > 0', [$in['id']]);

req('POST', "/report/{$id}/internal/parts", [
    '_csrf' => csrf("/report/{$id}/internal/parts"),
    "qty[{$extra['id']}]"        => '4',
    "qty[{$drop['part_id']}]"    => '0',
]);
$after = (int) Database::value(
    'SELECT COUNT(*) FROM internal_report_parts WHERE internal_report_id = ? AND qty > 0', [$in['id']]);
check('部材を足せる／0で外せる', $after === $before,
    "{$before}点 → {$after}点（{$extra['name']} を追加・1点削除）");
check('追加した部材の数量が入る',
    (int) Database::value(
        'SELECT qty FROM internal_report_parts WHERE internal_report_id = ? AND part_id = ?',
        [$in['id'], $extra['id']]) === 4);

$r = req('POST', "/report/{$id}/internal/parts", [
    '_csrf' => csrf("/report/{$id}/internal/parts"), 'q' => 'フィルター', 'search' => '1',
]);
check('検索でキーワードが残る', str_contains(rawurldecode($r['location']), 'フィルター'));
check('検索結果が出る',
    str_contains(req('GET', "/report/{$id}/internal/parts?q=" . urlencode('ミズ'))['body'], '水フィルター'));

$r = req('POST', "/report/{$id}/internal/parts",
    ['_csrf' => csrf("/report/{$id}/internal/parts"), 'next' => '1']);
check('つぎへで 4-4 に進む', str_contains($r['location'], "/report/{$id}/internal/hours"));

// ---------------------------------------------------------------- 4-4
echo "--- 4-4 移動および作業時間 ---\n";
$body = req('GET', "/report/{$id}/internal/hours")['body'];
check('移動時間…I／作業時間…S の表記がある', str_contains($body, '移動時間・・・I'));
check('時刻はOS標準のピッカーで入れる', str_contains($body, 'type="time"'));

$r = req('POST', "/report/{$id}/internal/hours", [
    '_csrf' => csrf("/report/{$id}/internal/hours"),
    'travel_out_from' => '25:99', 'travel_out_to' => '09:00', 'next' => '1',
]);
check('おかしな時刻は弾く', str_contains($r['body'], '9:00 のように'));

$r = req('POST', "/report/{$id}/internal/hours", [
    '_csrf' => csrf("/report/{$id}/internal/hours"),
    'travel_out_from' => '07:30', 'travel_out_to' => '', 'next' => '1',
]);
check('片方だけの区間は知らせる', str_contains($r['body'], '開始と終了の両方'));

$r = req('POST', "/report/{$id}/internal/hours", [
    '_csrf' => csrf("/report/{$id}/internal/hours"),
    'travel_out_from' => '07:30', 'travel_out_to' => '09:00',
    'work_from' => '09:00', 'work_to' => '16:30',
    'travel_back_from' => '16:30', 'travel_back_to' => '18:00',
    'next' => '1',
]);
check('つぎへで 4-5 に進む', str_contains($r['location'], "/report/{$id}/internal/sales"));

$in = Database::one('SELECT * FROM internal_reports WHERE report_id = ?', [$id]);
check('3区間が保存される',
    $in['travel_out_from'] === '07:30' && $in['work_to'] === '16:30'
    && $in['travel_back_to'] === '18:00');
check('区間の長さを計算できる',
    InternalReport::span('07:30', '09:00') === '1時間30分'
    && InternalReport::span('09:00', '16:30') === '7時間30分');
check('日付をまたぐ区間も計算できる',
    InternalReport::span('23:00', '01:30') === '2時間30分');
check('往復の合計を出せる',
    InternalReport::totalLabel([
        InternalReport::spanMinutes('07:30', '09:00'),
        InternalReport::spanMinutes('16:30', '18:00'),
    ]) === '3時間');
check('画面に合計が出る', str_contains(
    req('GET', "/report/{$id}/internal/hours")['body'], '3時間'));

// ---------------------------------------------------------------- 4-5
echo "--- 4-5 営業アプローチ・備考 ---\n";
$r = req('POST', "/report/{$id}/internal/sales", [
    '_csrf' => csrf("/report/{$id}/internal/sales"),
    'sales_approach' => '中央棟の空調更新について、来期予算の検討状況をヒアリング。',
    'remarks'        => '立会は設備課 主任。次回は事前に入室許可申請が必要。',
    'next' => '1',
]);
check('つぎへで 4-6 に進む', str_contains($r['location'], "/report/{$id}/internal/confirm"));
$in = Database::one('SELECT * FROM internal_reports WHERE report_id = ?', [$id]);
check('営業アプローチと備考が保存される',
    str_contains((string) $in['sales_approach'], '空調更新')
    && str_contains((string) $in['remarks'], '入室許可申請'));

// ---------------------------------------------------------------- 4-7 用紙
echo "--- 4-7 社内用のA4に載る項目 ---\n";
$sheet = req('GET', "/report/{$id}/internal/sheet");
check('用紙が返る', $sheet['status'] === 200, strlen($sheet['body']) . 'B');
$sb = $sheet['body'];
foreach ([
    '表題'                 => '作業完了報告書',
    '社内用の表示'         => '（社内用）',
    '病院名'               => (string) $in['hospital_name'],
    '作業日の見出し'       => '作業日',
    '今回作業時の残作業'   => '今回作業時の残作業',
    '再手配の必要な部材'   => '再手配の必要な部材',
    '移動及び作業時間の推移' => '移動及び作業時間の推移',
    '客先への営業アプローチ' => '客先への営業アプローチ',
    '備考'                 => '備考（社内への報告事項等）',
    '残作業の中身'         => 'HEPAフィルター',
    '営業アプローチの中身' => '空調更新',
    '備考の中身'           => '入室許可申請',
    '移動時間の記号'       => '移動時間・・・I',
] as $label => $needle) {
    check("{$label} が載っている", str_contains($sb, $needle));
}
check('時刻が紙に出る', str_contains($sb, '07:30') && str_contains($sb, '16:30'));
check('作業時間の計が紙に出る', str_contains($sb, '7時間30分'));
check('部材の枠を9行分引いている', substr_count($sb, '<td class="no">') >= 9);
check('客先提出用とは別の様式（測定値・サイン欄は無い）',
    !str_contains($sb, '積算時間') && !str_contains($sb, '上記の内容を報告致します'));
check('一覧の「社内用 ●」の元になる記録が入る',
    Database::value('SELECT pdf_at FROM internal_reports WHERE report_id = ?', [$id]) !== null);

echo "--- 4-7 / 4-8 画面 ---\n";
$r = req('GET', "/report/{$id}/internal/preview");
check('プレビューが社内用の紙を読む',
    $r['status'] === 200 && str_contains($r['body'], "src=\"/report/{$id}/internal/sheet\""));
$r = req('GET', "/report/{$id}/internal/print");
check('印刷は print=1 付きで読む', str_contains($r['body'], "internal/sheet?print=1"));
check('print=1 で印刷ダイアログを呼ぶ',
    str_contains(req('GET', "/report/{$id}/internal/sheet?print=1")['body'], 'window.print()'));

// ---------------------------------------------------------------- 4-6 完了
echo "--- 4-6 完了（請求済）＝ 概要書5の ⑥ ---\n";
$r = req('GET', "/report/{$id}/internal/confirm");
check('PDF確認画面が出る', $r['status'] === 200 && str_contains($r['body'], 'PDF確認画面'));
check('プレビューと印刷の導線がある',
    str_contains($r['body'], 'internal/preview') && str_contains($r['body'], 'internal/print'));
check('完了ボタンがある', str_contains($r['body'], 'internal/confirm?complete=1'));

$r = req('GET', "/report/{$id}/internal/confirm?complete=1");
check('「本当によいですか？」が出る', str_contains($r['body'], '本当によいですか？'));
check('キャンセルで戻れる', str_contains($r['body'], 'dialog__cancel'));
check('この時点ではまだ完了していない',
    Database::value('SELECT completed_at FROM internal_reports WHERE report_id = ?', [$id]) === null);

$r = req('POST', "/report/{$id}/internal/complete", [
    '_csrf' => csrf("/report/{$id}/internal/confirm?complete=1"),
]);
check('OKで一覧に戻る', str_contains($r['location'], '/reports'));
check('社内用が完了になる',
    Database::value('SELECT completed_at FROM internal_reports WHERE report_id = ?', [$id]) !== null);
check('報告書のステータスが completed になる',
    Database::value('SELECT status FROM reports WHERE id = ?', [$id]) === 'completed');
check('一覧の状態が「完」になる', (function () use ($id) {
    $b = req('GET', '/reports')['body'];
    preg_match('#<td class="c-no num">(\d+)</td>.*?<td class="c-mark center">(完|－)</td>\s*<td class="c-copy#s',
        $b, $m);
    return str_contains($b, '>完<');
})());
check('紙に完了（請求済）の印が出る',
    str_contains(req('GET', "/report/{$id}/internal/sheet")['body'], '完了（請求済）'));
check('完了後もPDF確認画面で状態が分かる',
    str_contains(req('GET', "/report/{$id}/internal/confirm")['body'], '完了（請求済）'));

// ---------------------------------------------------------------- 導線
echo "--- 導線（概要書5の①〜⑧が繋がるか） ---\n";
check('完了画面の「社内用報告書へ」が有効',
    str_contains(req('GET', "/report/{$id}/done")['body'], "href=\"/report/{$id}/internal\""));
check('一覧の「社内用 ●」がリンクになっている',
    str_contains(req('GET', '/reports')['body'], "/report/{$id}/internal\""));

// ---------------------------------------------------------------- 他社
echo "--- 他社の社内用は触れないか ---\n";
@unlink($JAR);
req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0002', 'password' => 'pass1234']);
foreach (['', '/basic', '/parts', '/hours', '/confirm', '/sheet', '/preview'] as $p) {
    check('/internal' . ($p ?: '（入口）') . ' は404',
        req('GET', "/report/{$id}/internal{$p}")['status'] === 404);
}
check('他社は完了操作もできない',
    req('POST', "/report/{$id}/internal/complete", ['_csrf' => csrf('/reports')])['status'] === 404);

// ---------------------------------------------------------------- 警告
echo "--- 警告の有無 ---\n";
@unlink($JAR);
req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0001', 'password' => 'pass1234']);
$dirty = [];
foreach (['/basic', '/remain', '/parts', '/hours', '/sales', '/confirm',
          '/confirm?complete=1', '/sheet', '/preview', '/print'] as $p) {
    $x = req('GET', "/report/{$id}/internal{$p}");
    if ($x['status'] !== 200 || preg_match('/(Warning|Notice|Deprecated|Fatal error|Undefined)/', $x['body'])) {
        $dirty[] = $p . '(' . $x['status'] . ')';
    }
}
check('社内用10画面すべて200かつ警告なし', !$dirty, implode(' ', $dirty));

test_summary();
