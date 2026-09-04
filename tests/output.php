<?php
/**
 * Phase 5：完了・プレビュー・印刷・メール送信の確認。
 * A4の紙が「概要書の項目を全部載せているか」を実際のHTMLで確かめる。
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';


req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0001', 'password' => 'pass1234']);

$row = Database::one(
    "SELECT r.* FROM reports r
      WHERE r.account_id = 1 AND r.deleted_at IS NULL
        AND EXISTS (SELECT 1 FROM report_parts p WHERE p.report_id = r.id AND p.qty > 0)
        AND r.signature_at IS NOT NULL
      ORDER BY r.report_no DESC LIMIT 1"
);
$id  = (int) $row['id'];
echo "対象 No.{$row['report_no']} / {$row['hospital_name']}（id={$id}）\n";

// ---------------------------------------------------------------- 2-7
echo "--- 2-7 完了画面 ---\n";
$r = req('GET', "/report/{$id}/done");
check('表示される', $r['status'] === 200);
foreach (['報告書のプレビュー', '印刷', 'メール送信'] as $label) {
    check("メニューに「{$label}」がある", str_contains($r['body'], $label));
}
check('押せないボタンは無くなった', substr_count($r['body'], 'is-pending') === 0);
check('4つのナビがすべて有効（もどる・ダッシュボード・一覧・社内用）',
    str_contains($r['body'], "href=\"/report/{$id}/confirm\"")
    && str_contains($r['body'], 'href="/dashboard"')
    && str_contains($r['body'], 'href="/reports"')
    && str_contains($r['body'], "href=\"/report/{$id}/internal\""));

// ---------------------------------------------------------------- A4の中身
echo "--- A4の用紙に概要書の項目が載っているか（2-8 の記載項目） ---\n";
$sheet = req('GET', "/report/{$id}/sheet");
check('用紙が返る', $sheet['status'] === 200, strlen($sheet['body']) . 'B');

$body = $sheet['body'];
$want = [
    '表題'        => '作業完了報告書',
    '病院名'      => (string) $row['hospital_name'],
    '御中'        => '御中',
    '自社名'      => (string) config('company_name'),
    '作業日'      => '作業日',
    '作業場所'    => (string) $row['work_place'],
    '作業者'      => (string) $row['workers_text'],
    '作業件名'    => (string) $row['work_title'],
    '交換部品名'  => '交換部品名',
    '測定値の見出し' => '積算時間',
    '製造No.'     => '製造No.',
    '製造年月'    => '製造年月',
    '報告事項'    => '報告事項',
    '締めの文'    => '上記の内容を報告致します',
    'サイン欄'    => 'サイン',
    '担当欄'      => '担当',
];
foreach ($want as $label => $needle) {
    check("{$label} が載っている", $needle !== '' && str_contains($body, $needle));
}

// 明細が実際に出ているか
$parts = Database::all('SELECT part_name, qty, unit FROM report_parts WHERE report_id = ? AND qty > 0', [$id]);
$okParts = true;
foreach ($parts as $p) {
    if (!str_contains($body, $p['part_name']) || !str_contains($body, $p['qty'] . $p['unit'])) {
        $okParts = false;
    }
}
check('交換部品が数量つきで並ぶ', $okParts && count($parts) > 0, count($parts) . '件');

$ms = Database::all('SELECT * FROM report_measurements WHERE report_id = ?', [$id]);
$okM = true;
foreach ($ms as $m) {
    if ($m['room_name'] && !str_contains($body, $m['room_name'])) {
        $okM = false;
    }
    if ($m['serial_no'] && !str_contains($body, $m['serial_no'])) {
        $okM = false;
    }
}
check('測定値が1件1行で並ぶ', $okM, count($ms) . '行');

$lines = array_filter(array_map('trim', preg_split('/\R/u', (string) $row['report_body'])));
$okB = true;
foreach ($lines as $l) {
    if (!str_contains($body, $l)) {
        $okB = false;
    }
}
check('報告事項が全行載る', $okB, count($lines) . '行');

check('A4の指定が入っている', str_contains($body, 'sheet.css'));
$css = req('GET', '/assets/css/sheet.css')['body'];
check('用紙CSSに A4 と @page がある',
    str_contains($css, 'size: A4 portrait') && str_contains($css, '@media print'));
check('mm単位で組んでいる', str_contains($css, '210mm') && str_contains($css, '297mm'));

// ---------------------------------------------------------------- 文字の詰め具合
echo "--- 文字量に応じた自動縮小（概要書 2-8「文字を小さくしても良い」） ---\n";
preg_match('/class="sheet (d[123])"/', $body, $m);
$before = $m[1] ?? '?';
check('詰め具合の指定が付く', in_array($before, ['d1', 'd2', 'd3'], true), $before);

$keep = (string) $row['report_body'];

// 中くらいに増やす → 一段詰まる
Database::run('UPDATE reports SET report_body = ? WHERE id = ?', [
    str_repeat("報告事項の分量がふえた場合の挙動を確認するための行です。\n", 14), $id,
]);
preg_match('/class="sheet (d[123])"/', req('GET', "/report/{$id}/sheet")['body'], $m2);
$mid = $m2[1] ?? '?';
check("分量がふえると一段詰まる（{$before} → {$mid}）", $mid === 'd2');

// 用紙に載りきらない量まで増やす → いちばん詰める
Database::run('UPDATE reports SET report_body = ? WHERE id = ?', [
    str_repeat("報告事項をとても長く書いた場合に、1枚に収めるため文字を小さくします。\n", 30), $id,
]);
preg_match('/class="sheet (d[123])"/', req('GET', "/report/{$id}/sheet")['body'], $m3);
$heavy = $m3[1] ?? '?';
check("さらに多いと最小になる（{$mid} → {$heavy}）", $heavy === 'd3');

Database::run('UPDATE reports SET report_body = ? WHERE id = ?', [$keep, $id]);

// ---------------------------------------------------------------- サイン
echo "--- サインが紙に載るか ---\n";
$hasSign = !empty($row['signature_at']);
if (!$hasSign) {
    // 署名なしの報告書ではサイン欄が空欄のままであることを見る
    check('サイン未入力なら空欄のまま', !str_contains($body, '/signature.png'));
} else {
    check('サイン画像が紙に入る', str_contains($body, '/signature.png'));
}

// ---------------------------------------------------------------- 2-8 / 2-9
echo "--- 2-8 プレビュー / 2-9 印刷 ---\n";
$r = req('GET', "/report/{$id}/preview");
check('プレビューが用紙をiframeで読む',
    $r['status'] === 200 && str_contains($r['body'], "src=\"/report/{$id}/sheet\""));
check('［×閉じる］がある', str_contains($r['body'], '×閉じる'));

$r = req('GET', "/report/{$id}/print");
check('印刷は print=1 付きで読む',
    $r['status'] === 200 && str_contains($r['body'], "/report/{$id}/sheet?print=1"));
$r = req('GET', "/report/{$id}/sheet?print=1");
check('print=1 のとき印刷ダイアログを呼ぶ', str_contains($r['body'], 'window.print()'));
$r = req('GET', "/report/{$id}/sheet");
check('通常表示では呼ばない', !str_contains($r['body'], 'window.print()'));

// ---------------------------------------------------------------- 一覧のPDF●
echo "--- 一覧の「PDF ●」の元になる記録 ---\n";
$fresh = Database::one('SELECT id FROM reports WHERE pdf_at IS NULL ORDER BY id LIMIT 1');
if ($fresh) {
    req('GET', "/report/{$fresh['id']}/sheet");
    check('紙を起こすと pdf_at が入る',
        Database::value('SELECT pdf_at FROM reports WHERE id = ?', [$fresh['id']]) !== null);
} else {
    check('紙を起こすと pdf_at が入る（対象なしのため既存で確認）',
        Database::value('SELECT pdf_at FROM reports WHERE id = ?', [$id]) !== null);
}

// ---------------------------------------------------------------- 2-10
echo "--- 2-10 メール送信 ---\n";
$r = req('GET', "/report/{$id}/mail");
check('画面が出る', $r['status'] === 200);
check('件名の既定値が入っている', str_contains($r['body'], (string) config('mail.default_subject')));
check('報告書を横に並べている', str_contains($r['body'], "src=\"/report/{$id}/sheet\""));

// 送信履歴があるものは前回の文面を引き継ぐ。たたき台は履歴のない報告書で見る
$virgin = Database::one(
    'SELECT r.id FROM reports r
      WHERE r.account_id = 1 AND r.deleted_at IS NULL
        AND NOT EXISTS (SELECT 1 FROM mail_logs m WHERE m.report_id = r.id)
      ORDER BY r.id LIMIT 1'
);
$vb = req('GET', "/report/{$virgin['id']}/mail")['body'];
check('履歴がなければ本文のたたき台が入る', str_contains($vb, '完了報告書をお送りいたします'));
check('履歴があれば前回の文面を引き継ぐ',
    str_contains($r['body'], (string) Database::value(
        'SELECT body FROM mail_logs WHERE report_id = ? ORDER BY id DESC LIMIT 1', [$id]
    )));

$before = (int) Database::value('SELECT mail_count FROM reports WHERE id = ?', [$id]);
$logs   = (int) Database::value('SELECT COUNT(*) FROM mail_logs WHERE report_id = ?', [$id]);

// 形式チェック
$r = req('POST', "/report/{$id}/mail", [
    '_csrf'   => csrf("/report/{$id}/mail"),
    'to'      => 'これはメールアドレスではない',
    'subject' => '作業完了報告書',
    'cc'      => '',
    'body'    => 'test',
]);
check('宛先の形式チェックが効く', str_contains($r['body'], 'メールアドレスの形式が正しくありません'));
check('件名が空だと弾く',
    str_contains(req('POST', "/report/{$id}/mail", [
        '_csrf' => csrf("/report/{$id}/mail"), 'to' => 'a@example.jp', 'subject' => '', 'body' => 'x',
    ])['body'], '件名を入れてください'));
check('CCの形式チェックも効く',
    str_contains(req('POST', "/report/{$id}/mail", [
        '_csrf' => csrf("/report/{$id}/mail"), 'to' => 'a@example.jp',
        'subject' => '件名', 'cc' => 'b@example.jp, こわれた', 'body' => 'x',
    ])['body'], 'メールアドレスの形式になっていません'));
check('エラー時は送信回数が増えない',
    (int) Database::value('SELECT mail_count FROM reports WHERE id = ?', [$id]) === $before);

// 正常送信
$r = req('POST', "/report/{$id}/mail", [
    '_csrf'   => csrf("/report/{$id}/mail"),
    'to'      => 'setsubi@example-hospital.jp',
    'subject' => '作業完了報告書',
    'cc'      => 'jimu@example.co.jp, kachou@example.co.jp',
    'body'    => "お世話になっております。\n報告書をお送りいたします。",
]);
check('「送信しました」のダイアログが出る', str_contains($r['body'], '送信しました'));
check('プロトタイプでは実送信しないと明記される', str_contains($r['body'], '実際の配信は行わず'));
check('送信回数が1増える',
    (int) Database::value('SELECT mail_count FROM reports WHERE id = ?', [$id]) === $before + 1);
check('送信内容が記録される',
    (int) Database::value('SELECT COUNT(*) FROM mail_logs WHERE report_id = ?', [$id]) === $logs + 1);
$last = Database::one('SELECT * FROM mail_logs WHERE report_id = ? ORDER BY id DESC', [$id]);
check('CCが複数保存される', str_contains((string) $last['cc_addr'], 'kachou@example.co.jp'),
    (string) $last['cc_addr']);
check('次に開くと前回の宛先が入っている',
    str_contains(req('GET', "/report/{$id}/mail")['body'], 'setsubi@example-hospital.jp'));

// ---------------------------------------------------------------- 他社
echo "--- 他社の報告書は出力できないか ---\n";
@unlink($JAR);
req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0002', 'password' => 'pass1234']);
foreach (['sheet', 'preview', 'print', 'mail', 'done'] as $p) {
    check("/{$p} は404", req('GET', "/report/{$id}/{$p}")['status'] === 404);
}

// ---------------------------------------------------------------- 警告
echo "--- 警告の有無 ---\n";
@unlink($JAR);
req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0001', 'password' => 'pass1234']);
$dirty = [];
foreach (["/report/{$id}/done", "/report/{$id}/sheet", "/report/{$id}/preview",
          "/report/{$id}/print", "/report/{$id}/mail", '/_dev'] as $p) {
    $x = req('GET', $p);
    if ($x['status'] !== 200 || preg_match('/(Warning|Notice|Deprecated|Fatal error|Undefined)/', $x['body'])) {
        $dirty[] = $p . '(' . $x['status'] . ')';
    }
}
check('全画面200かつ警告なし', !$dirty, implode(' ', $dirty));

test_summary();
