<?php
/**
 * Phase 4：オフライン層のサーバー側の約束事を確かめる。
 * 端末が溜めた操作を送ってくる形（op_id 付きPOST）を再現する。
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';


function uuid(): string
{
    return 'op-' . bin2hex(random_bytes(10));
}

// ---------------------------------------------------------------- 準備
req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0001', 'password' => 'pass1234']);
$r = req('GET', '/report/new?uuid=' . uuid());
preg_match('#/report/(\d+)/basic#', $r['location'], $m);
$id = (int) $m[1];
echo "対象の報告書 id={$id}\n";

// ---------------------------------------------------------------- 静的ファイル
echo "--- オフライン用のファイルが配信されるか ---\n";
foreach ([
    '/sw.js'                  => 'javascript',
    '/offline.html'           => 'html',
    '/assets/js/offline.js'   => 'javascript',
    '/assets/js/mic.js'       => 'javascript',
] as $path => $kind) {
    $r = req('GET', $path);
    check($path, $r['status'] === 200 && strlen($r['body']) > 500, $r['status'] . ' / ' . strlen($r['body']) . 'B');
}

// ---------------------------------------------------------------- 画面の属性
echo "--- 画面がオフライン層に行き先を渡しているか ---\n";
foreach (['basic' => 'work', 'work' => 'parts', 'parts' => 'measure', 'measure' => 'confirm'] as $step => $next) {
    $body = req('GET', "/report/{$id}/{$step}")['body'];
    check("2-x {$step} の data-next-url",
        str_contains($body, 'data-offline') && str_contains($body, "data-next-url=\"/report/{$id}/{$next}\""));
}
$body = req('GET', "/report/{$id}/confirm")['body'];
check('2-5 の次は完了画面', str_contains($body, "data-next-url=\"/report/{$id}/done\""));
$body = req('GET', "/report/{$id}/basic")['body'];
check('2-1 のもどるはダッシュボード', str_contains($body, 'data-back-url="/dashboard"'));

echo "--- マイク入力の対象欄 ---\n";
$counts = [];
foreach (['basic', 'work', 'parts', 'measure'] as $step) {
    $counts[$step] = substr_count(req('GET', "/report/{$id}/{$step}")['body'], 'data-mic="1"');
}
check('全画面に音声入力の対象がある', min($counts) > 0,
    implode(' / ', array_map(fn($k, $v) => "{$k}:{$v}件", array_keys($counts), $counts)));

// ---------------------------------------------------------------- 受付番号による二重登録防止
echo "--- 溜めた操作の再送（op_id） ---\n";

$before = (int) Database::value('SELECT COUNT(*) FROM report_models WHERE report_id = ?', [$id]);
$model  = Database::one(
    'SELECT model_id FROM report_models WHERE report_id = ? ORDER BY sort_order DESC', [$id]
);

// まず1件削除しておいて、「機種を戻す」を再送の題材にする
req('POST', "/report/{$id}/work", [
    '_csrf'     => csrf("/report/{$id}/work"),
    'delete_id' => (string) Database::value(
        'SELECT id FROM report_models WHERE report_id = ? ORDER BY sort_order DESC', [$id]
    ),
]);
$afterDelete = (int) Database::value('SELECT COUNT(*) FROM report_models WHERE report_id = ?', [$id]);
check('削除できた', $afterDelete === $before - 1, "{$before} -> {$afterDelete}");

$op = uuid();
$r1 = req('POST', "/report/{$id}/work", [
    '_csrf'     => csrf("/report/{$id}/work"),
    'op_id'     => $op,
    'add_model' => (string) $model['model_id'],
]);
$after1 = (int) Database::value('SELECT COUNT(*) FROM report_models WHERE report_id = ?', [$id]);
check('1回目は処理される', $after1 === $before && $r1['status'] === 302, "件数 {$after1}");

// 同じ受付番号でもう一度届く（電波の切れ際で応答が返らなかった場合の再送）
$r2 = req('POST', "/report/{$id}/work", [
    '_csrf'     => csrf("/report/{$id}/work"),
    'op_id'     => $op,
    'add_model' => (string) $model['model_id'],
]);
$after2 = (int) Database::value('SELECT COUNT(*) FROM report_models WHERE report_id = ?', [$id]);
check('2回目は処理されない（件数が増えない）', $after2 === $after1, "件数 {$after2}");
check('2回目の応答は duplicate', str_contains($r2['body'], '"status":"duplicate"'), trim($r2['body']));
check('台帳に op_id が1件だけ載る',
    (int) Database::value('SELECT COUNT(*) FROM sync_ops WHERE op_id = ?', [$op]) === 1);

// ---------------------------------------------------------------- 溜めた「つぎへ」の再送
echo "--- 溜めた「つぎへ」の再送で値が壊れないか ---\n";
$op = uuid();
$fields = [
    'op_id'         => $op,
    'created_date'  => '2026-09-03',
    'hospital_name' => '都立駒込病院',
    'work_date'     => '2026-09-01',
    'work_place'    => '地下1階 機械室',
    'worker_free'   => '山田 太郎',
    'work_title'    => '圏外で入力したテスト',
    'next'          => '1',
];
$r1 = req('POST', "/report/{$id}/basic", ['_csrf' => csrf("/report/{$id}/basic")] + $fields);
check('溜めた入力が反映される',
    Database::value('SELECT hospital_name FROM reports WHERE id = ?', [$id]) === '都立駒込病院');
check('空白を含む名前が1人として入る',
    (int) Database::value('SELECT COUNT(*) FROM report_workers WHERE report_id = ?', [$id]) === 1,
    (string) Database::value('SELECT workers_text FROM reports WHERE id = ?', [$id]));

// 同じものを再送 → 何も変わらない
req('POST', "/report/{$id}/basic",
    ['_csrf' => csrf("/report/{$id}/basic")] + array_merge($fields, ['hospital_name' => '書き換わってはいけない']));
check('再送で上書きされない',
    Database::value('SELECT hospital_name FROM reports WHERE id = ?', [$id]) === '都立駒込病院',
    (string) Database::value('SELECT hospital_name FROM reports WHERE id = ?', [$id]));

// ---------------------------------------------------------------- トークン切れの扱い
echo "--- トークンが古いときに受付番号を使い切らないか ---\n";
$op = uuid();
$r = req('POST', "/report/{$id}/work", [
    '_csrf' => 'ffffffffffffffffffffffffffffffff',   // 古いトークン
    'op_id' => $op,
    'next'  => '1',
]);
check('古いトークンは419で弾かれる', $r['status'] === 419, (string) $r['status']);
check('弾かれた操作の受付番号は消費されない',
    (int) Database::value('SELECT COUNT(*) FROM sync_ops WHERE op_id = ?', [$op]) === 0);

// 取り直して同じ受付番号で送れば通る（電波が戻ってから送り直す動き）
$r = req('POST', "/report/{$id}/work", [
    '_csrf' => csrf("/report/{$id}/work"),
    'op_id' => $op,
    'next'  => '1',
]);
check('取り直したトークンで同じ受付番号が通る',
    $r['status'] === 302 && str_contains($r['location'], "/report/{$id}/parts"));

// ---------------------------------------------------------------- 画面の健全性
echo "--- 全画面の再確認 ---\n";
$dirty = 0;
foreach (['/dashboard', "/report/{$id}/basic", "/report/{$id}/work", "/report/{$id}/parts",
          "/report/{$id}/measure", "/report/{$id}/confirm", "/report/{$id}/sign",
          "/report/{$id}/done", '/_dev'] as $p) {
    $r = req('GET', $p);
    if ($r['status'] !== 200 || preg_match('/(Warning|Notice|Deprecated|Fatal error|Undefined)/', $r['body'])) {
        echo "    !! {$p} ({$r['status']})\n";
        $dirty++;
    }
}
check('9画面すべて200かつ警告なし', $dirty === 0);

test_summary();
