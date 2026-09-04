<?php
/**
 * 2-1〜2-6 の通し試験。日本語をそのまま送るためPHPから実行する。
 *   php -d extension=pdo_sqlite -d extension=curl smoke_wizard.php
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';


// ---------------------------------------------------------------- ログイン
echo "--- ログイン ---\n";
$r = req('POST', '/login', [
    '_csrf'    => csrf('/login'),
    'login_id' => 'ABCDE0001',
    'password' => 'pass1234',
]);
check('ログイン', str_contains($r['location'], '/dashboard'));

// ---------------------------------------------------------------- 新規作成
echo "--- 新規作成 ---\n";
$uuid = 'smoke-' . bin2hex(random_bytes(6));
$r    = req('GET', '/report/new?uuid=' . $uuid);
preg_match('#/report/(\d+)/basic#', $r['location'], $m);
$id = (int) ($m[1] ?? 0);
check('下書き作成', $id > 0, "report id={$id}");

$r2 = req('GET', '/report/new?uuid=' . $uuid);
check('同じuuidで二重登録されない', $r['location'] === $r2['location']);

$seeded = (int) Database::value('SELECT COUNT(*) FROM report_models WHERE report_id = ?', [$id]);
check('機種12件と測定値5行が用意される', $seeded === 12
    && (int) Database::value('SELECT COUNT(*) FROM report_measurements WHERE report_id = ?', [$id]) === 5);

// ---------------------------------------------------------------- 2-1
echo "--- 2-1 基本情報 ---\n";
$workerIds = array_column(Database::all(
    'SELECT id FROM workers WHERE account_id = (SELECT id FROM accounts WHERE account_id = ?) ORDER BY id LIMIT 2',
    ['ABCDE0001']
), 'id');

$r = req('POST', "/report/{$id}/basic", [
    '_csrf'         => csrf("/report/{$id}/basic"),
    'created_date'  => '2026-09-03',
    'hospital_name' => '横浜市立大学附属病院',
    'work_date'     => '2026-09-02',
    'work_place'    => '4階 中央無菌室',
    'worker_ids'    => $workerIds,
    'worker_free'   => '外注 田村',
    'work_title'    => '無菌病室(MIU-201)×3台 保守点検',
    'next'          => '1',
]);
check('つぎへで 2-2 に進む', str_contains($r['location'], "/report/{$id}/work"));

$row = Database::one('SELECT * FROM reports WHERE id = ?', [$id]);
check('病院名が化けずに保存される', $row['hospital_name'] === '横浜市立大学附属病院', $row['hospital_name']);
check('作業者3名（テーブル2名＋自由入力1名）', $row['workers_text'] !== '' &&
    (int) Database::value('SELECT COUNT(*) FROM report_workers WHERE report_id = ?', [$id]) === 3,
    (string) $row['workers_text']);

// もどるは未入力でも保存して戻れる
$r = req('POST', "/report/{$id}/basic", [
    '_csrf'      => csrf("/report/{$id}/basic"),
    'work_place' => '4階 中央無菌室（修正）',
    'back'       => '1',
]);
check('もどるはダッシュボードへ', str_contains($r['location'], '/dashboard'));
check('もどるでも入力は保存される',
    Database::value('SELECT work_place FROM reports WHERE id = ?', [$id]) === '4階 中央無菌室（修正）');

// ---------------------------------------------------------------- 2-2
echo "--- 2-2 作業内容 ---\n";
$models = Database::all('SELECT id, model_id, model_name FROM report_models WHERE report_id = ? ORDER BY sort_order', [$id]);
$r = req('POST', "/report/{$id}/work", [
    '_csrf'                        => csrf("/report/{$id}/work"),
    "qty[{$models[0]['id']}]"      => '3',
    "qty[{$models[1]['id']}]"      => '2',
    'work_note'                    => "以上、保守点検作業一式",
    'delete_id'                    => (string) $models[11]['id'],
]);
check('削除で機種が1件減る',
    (int) Database::value('SELECT COUNT(*) FROM report_models WHERE report_id = ?', [$id]) === 11);
check('削除しても台数は保存されている',
    (int) Database::value('SELECT qty FROM report_models WHERE id = ?', [$models[0]['id']]) === 3);

$r = req('POST', "/report/{$id}/work", [
    '_csrf'                   => csrf("/report/{$id}/work"),
    "qty[{$models[0]['id']}]" => '3',
    'work_note'               => '以上、保守点検作業一式',
    'add_model'               => (string) $models[11]['model_id'],
]);
check('機種を戻せる',
    (int) Database::value('SELECT COUNT(*) FROM report_models WHERE report_id = ?', [$id]) === 12);

$r = req('POST', "/report/{$id}/work", ['_csrf' => csrf("/report/{$id}/work"), 'next' => '1']);
check('つぎへで 2-3 に進む', str_contains($r['location'], "/report/{$id}/parts"));

// ---------------------------------------------------------------- 2-3
echo "--- 2-3 交換部品 ---\n";
$r = req('POST', "/report/{$id}/parts", [
    '_csrf'    => csrf("/report/{$id}/parts"),
    'q'        => 'ミズ',
    'sort_key' => 'priority',
    'search'   => '1',
]);
check('検索でリダイレクトにキーワードが残る', str_contains(rawurldecode($r['location']), 'ミズ'),
    rawurldecode($r['location']));

$body = req('GET', "/report/{$id}/parts?q=" . urlencode('ミズ'))['body'];
check('ヨミガナ検索で水フィルターが出る', str_contains($body, '水フィルター'));

$partIds = array_column(Database::all('SELECT id FROM parts ORDER BY priority DESC LIMIT 3'), 'id');
$r = req('POST', "/report/{$id}/parts", [
    '_csrf'                 => csrf("/report/{$id}/parts"),
    "qty[{$partIds[0]}]"    => '9',
    "qty[{$partIds[1]}]"    => '2',
    "qty[{$partIds[2]}]"    => '0',
    'parts_note'            => '純正品を使用',
    'sort_key'              => 'priority',
    'next'                  => '1',
]);
check('つぎへで 2-4 に進む', str_contains($r['location'], "/report/{$id}/measure"));
check('数量>0だけが登録される',
    (int) Database::value('SELECT COUNT(*) FROM report_parts WHERE report_id = ?', [$id]) === 2);

// 0 に戻すと選択から外れる
req('POST', "/report/{$id}/parts", [
    '_csrf'              => csrf("/report/{$id}/parts"),
    "qty[{$partIds[1]}]" => '0',
    'sort_key'           => 'priority',
]);
check('0にすると選択から外れる',
    (int) Database::value('SELECT COUNT(*) FROM report_parts WHERE report_id = ?', [$id]) === 1);

// ---------------------------------------------------------------- 2-4
echo "--- 2-4 測定値・報告事項 ---\n";
$mrows = array_column(Database::all(
    'SELECT id FROM report_measurements WHERE report_id = ? ORDER BY sort_order', [$id]
), 'id');

$fields = ['_csrf' => csrf("/report/{$id}/measure")];
foreach ($mrows as $i => $mid) {
    $fields["m[{$mid}][room_name]"]        = 'BCR' . ($i + 1);
    $fields["m[{$mid}][model_name]"]       = $models[0]['model_name'];
    $fields["m[{$mid}][cumulative_hours]"] = (string) (12000 + $i * 3175);
    $fields["m[{$mid}][serial_no]"]        = sprintf('%06d', 204100 + $i);
    $fields["m[{$mid}][manufactured_ym]"]  = '2019-04';
}
$fields['report_body'] = '';
$fields['next']        = '1';
$r = req('POST', "/report/{$id}/measure", $fields);
check('つぎへで 2-5 に進む', str_contains($r['location'], "/report/{$id}/confirm"));
check('測定値5行が保存される', (int) Database::value(
    "SELECT COUNT(*) FROM report_measurements WHERE report_id = ? AND room_name <> ''", [$id]) === 5);

// 積算時間の上限
$r = req('POST', "/report/{$id}/measure", [
    '_csrf'                                  => csrf("/report/{$id}/measure"),
    "m[{$mrows[0]}][cumulative_hours]"       => '999999',
    "m[{$mrows[0]}][room_name]"              => 'BCR1',
    'next'                                   => '1',
]);
check('積算時間10万超はエラーになる', str_contains($r['body'], '積算時間は0〜100000'));
check('値は上限に丸めて保存される',
    (int) Database::value('SELECT cumulative_hours FROM report_measurements WHERE id = ?', [$mrows[0]]) === 100000);

// 報告事項テーブルからの追記
$textIds = array_column(Database::all('SELECT id FROM report_texts WHERE account_id IS NULL ORDER BY sort_order LIMIT 2'), 'id');
req('POST', "/report/{$id}/measure", [
    '_csrf'        => csrf("/report/{$id}/measure"),
    'report_body'  => '',
    'text_ids'     => $textIds,
    'insert_texts' => '1',
]);
$body = (string) Database::value('SELECT report_body FROM reports WHERE id = ?', [$id]);
check('定型文が2件追記される', substr_count($body, "\n") === 1 && str_contains($body, '水フィルター等'),
    mb_substr($body, 0, 28) . '…');

// ---------------------------------------------------------------- 2-6 サイン
echo "--- 2-6 サイン入力 ---\n";
$im = imagecreatetruecolor(600, 200);
imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
$ink = imagecolorallocate($im, 16, 24, 32);
imagesetthickness($im, 4);
imageline($im, 60, 150, 180, 60, $ink);
imageline($im, 180, 60, 300, 150, $ink);
imagearc($im, 420, 110, 140, 90, 0, 300, $ink);
ob_start();
imagepng($im);
$png = (string) ob_get_clean();

$r = req('POST', "/report/{$id}/sign", [
    '_csrf' => csrf("/report/{$id}/sign"),
    'image' => 'data:image/png;base64,' . base64_encode($png),
]);
check('サイン保存後は 2-5 に戻る', str_contains($r['location'], "/report/{$id}/confirm"));
$signFile = (string) Database::value('SELECT signature_file FROM reports WHERE id = ?', [$id]);
check('サイン画像がdata/配下に保存される',
    $signFile !== '' && is_file($ROOT . '/data/signatures/' . $signFile), $signFile);

$r = req('GET', "/report/{$id}/signature.png");
check('ログイン確認を通して画像が配信される',
    $r['status'] === 200 && str_starts_with($r['body'], "\x89PNG"));

$r = req('POST', "/report/{$id}/sign", [
    '_csrf' => csrf("/report/{$id}/sign"),
    'image' => 'data:image/png;base64,' . base64_encode('not a png'),
]);
check('壊れた画像は受け取らない', str_contains($r['body'], 'サインを保存できませんでした'));

// ---------------------------------------------------------------- 2-5
echo "--- 2-5 確認署名 ---\n";
$checkIds = array_column(Database::all('SELECT id FROM checklist_items WHERE is_active = 1 ORDER BY sort_order'), 'id');

// チェックが足りないのに作業者を送る
$r = req('POST', "/report/{$id}/confirm", [
    '_csrf'          => csrf("/report/{$id}/confirm"),
    'checked'        => array_slice($checkIds, 0, 3),
    'worker_pick'    => (string) $workerIds[0],
    'next'           => '1',
]);
check('チェック未完了では作業者を受け付けない',
    str_contains($r['body'], '確認事項すべてにチェックを入れてから'));
check('作業者は保存されない',
    (string) Database::value('SELECT submitter_name FROM reports WHERE id = ?', [$id]) === '');

// 全部チェックして作業者を選ぶ
$r = req('POST', "/report/{$id}/confirm", [
    '_csrf'       => csrf("/report/{$id}/confirm"),
    'checked'     => $checkIds,
    'worker_pick' => (string) $workerIds[0],
    'next'        => '1',
]);
check('つぎへで完了画面に進む', str_contains($r['location'], "/report/{$id}/done"));

$row = Database::one('SELECT * FROM reports WHERE id = ?', [$id]);
check('ステータスが submitted になる', $row['status'] === 'submitted' && $row['submitted_at'] !== null);
check('担当者名が保存される', $row['submitter_name'] !== '', (string) $row['submitter_name']);
check('チェック5件が記録される', count(explode(',', (string) $row['checked_ids'])) === 5);

// ---------------------------------------------------------------- 2-7
echo "--- 2-7 完了画面 ---\n";
$r = req('GET', "/report/{$id}/done");
check('完了画面が表示される', $r['status'] === 200 && str_contains($r['body'], '登録しました'));

// ---------------------------------------------------------------- 他社のデータ
echo "--- 他社の報告書は見えないか ---\n";
@unlink($JAR);
req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0002', 'password' => 'pass1234']);
$r = req('GET', "/report/{$id}/basic");
check('別会社のIDで開くと404', $r['status'] === 404);
$r = req('GET', "/report/{$id}/signature.png");
check('別会社のサイン画像も404', $r['status'] === 404);

test_summary();
