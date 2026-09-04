<?php
/**
 * Phase 8：管理者サイト K-1〜K-7。
 * とくに K-4 の CSV 入出力と、部品名を鍵にした差分反映を重点的に見る。
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';


// ================================================================ K-1
echo "--- K-1 ログインと入口 ---\n";
check('未ログインの /admin/dashboard は弾かれる',
    str_contains(req('GET', '/admin/dashboard')['location'], '/admin/login'));
$r = req('POST', '/admin/login',
    ['_csrf' => csrf('/admin/login'), 'login_id' => 'admin', 'password' => 'admin1234']);
check('ログインできる', str_contains($r['location'], '/admin/dashboard'));

// ================================================================ K-2
echo "--- K-2 ダッシュボード ---\n";
$r = req('GET', '/admin/dashboard');
check('表示される', $r['status'] === 200);
foreach (['No.', '作業日', '作成日', '病院名', '協力会社', '作業者', '署名', 'PDF', 'Mail', '社内用', '状態'] as $c) {
    check("列「{$c}」がある", str_contains($r['body'], $c));
}

$allReports = (int) Database::value('SELECT COUNT(*) FROM reports WHERE deleted_at IS NULL');
preg_match_all('/<td class="c-no num">(\d+)<\/td>/', $r['body'], $m);
check('全社の報告書が出る（協力会社の縛りなし）', count($m[1]) === $allReports,
    count($m[1]) . '/' . $allReports . '件');

$target = Database::one(
    "SELECT r.*, a.company_name FROM reports r JOIN accounts a ON a.id = r.account_id
      WHERE r.pdf_at IS NOT NULL ORDER BY r.report_no DESC LIMIT 1");
check('報告書PDFへのリンクが出る',
    str_contains($r['body'], '/admin/report/' . $target['id'] . '/sheet'));
check('社内用PDFへのリンクが出る',
    str_contains($r['body'], '/admin/report/'));

echo "--- K-2 検索 ---\n";
$hospital = (string) $target['hospital_name'];
$r = req('GET', '/admin/dashboard?q=' . urlencode(mb_substr($hospital, 0, 4)));
preg_match_all('/<td class="c-no num">(\d+)<\/td>/', $r['body'], $m);
check('病院名で絞り込める', count($m[1]) >= 1 && count($m[1]) < $allReports,
    count($m[1]) . '件');
check('協力会社名でも探せる', (function () {
    $c = (string) Database::value('SELECT company_name FROM accounts WHERE id = 1');
    return str_contains(req('GET', '/admin/dashboard?q=' . urlencode($c))['body'], 'c-no num');
})());
check('報告書No.でも探せる',
    str_contains(req('GET', '/admin/dashboard?q=' . (int) $target['report_no'])['body'],
        '>' . (int) $target['report_no'] . '<'));
check('該当なしのときは知らせる',
    str_contains(req('GET', '/admin/dashboard?q=' . urlencode('該当しない文字列XYZ'))['body'],
        '見つかりませんでした'));

echo "--- K-2 並べ替え ---\n";
foreach (['no', 'work', 'created', 'hospital', 'company', 'status'] as $key) {
    check("「{$key}」で並べ替えできる", req('GET', "/admin/dashboard?sort={$key}&dir=asc")['status'] === 200);
}
check('知らないキーは既定に戻す', req('GET', '/admin/dashboard?sort=;DROP')['status'] === 200);

echo "--- K-2 からのPDF表示 ---\n";
$r = req('GET', '/admin/report/' . $target['id'] . '/sheet');
check('客先提出用のA4を出せる',
    $r['status'] === 200 && str_contains($r['body'], '作業完了報告書'));
check('サイン画像は管理者用の経路を指す',
    !$target['signature_at'] || str_contains($r['body'], '/admin/report/' . $target['id'] . '/signature.png'));
if ($target['signature_at']) {
    $img = req('GET', '/admin/report/' . $target['id'] . '/signature.png');
    check('管理者もサイン画像を見られる',
        $img['status'] === 200 && str_starts_with($img['body'], "\x89PNG"));
}
$withInternal = Database::one(
    'SELECT report_id FROM internal_reports WHERE pdf_at IS NOT NULL ORDER BY id DESC LIMIT 1');
if ($withInternal) {
    $r = req('GET', '/admin/report/' . $withInternal['report_id'] . '/internal-sheet');
    check('社内用のA4も出せる',
        $r['status'] === 200 && str_contains($r['body'], '今回作業時の残作業'));
}
$noInternal = Database::one(
    'SELECT id FROM reports WHERE id NOT IN (SELECT report_id FROM internal_reports) LIMIT 1');
if ($noInternal) {
    check('社内用が無ければその旨を返す',
        req('GET', '/admin/report/' . $noInternal['id'] . '/internal-sheet')['status'] === 404);
}

// ================================================================ K-3
echo "--- K-3 ユーザー登録 ---\n";
$r = req('GET', '/admin/users');
check('表示される', $r['status'] === 200);
check('作業者1〜5の列がある', substr_count($r['body'], '作業者') >= 5);

$before = (int) Database::value('SELECT COUNT(*) FROM accounts');
$r = req('GET', '/admin/users?new=1');
check('追加ダイアログが出る', str_contains($r['body'], 'アカウントの追加登録'));
check('次のアカウントIDを提案する', (bool) preg_match('/value="ABCDE\d+"/', $r['body']));

check('パスワードなしでは登録できない', str_contains(req('POST', '/admin/users/save', [
    '_csrf' => csrf('/admin/users?new=1'), 'id' => 0,
    'account_id' => 'TESTAC01', 'company_name' => 'テスト工業', 'password' => '',
])['location'], 'new=1'));
check('（続き）エラーが表示される',
    str_contains(req('GET', '/admin/users?new=1')['body'], '初回のパスワードを入れてください'));

check('IDの重複を弾く', (function () {
    req('POST', '/admin/users/save', [
        '_csrf' => csrf('/admin/users?new=1'), 'id' => 0,
        'account_id' => 'ABCDE0001', 'company_name' => 'かぶり工業', 'password' => 'abcd1234',
    ]);
    return str_contains(req('GET', '/admin/users?new=1')['body'], 'すでに使われています');
})());

$r = req('POST', '/admin/users/save', [
    '_csrf'        => csrf('/admin/users?new=1'),
    'id'           => 0,
    'account_id'   => 'TESTAC01',
    'company_name' => '株式会社テスト設備',
    'email'        => 'test@example.co.jp',
    'password'     => 'testpass1',
    'workers'      => ['試験 一郎', '試験 二郎', '', '', ''],
]);
check('アカウントを発行できる', str_contains($r['location'], '/admin/users')
    && (int) Database::value('SELECT COUNT(*) FROM accounts') === $before + 1);

$newAcc = Database::one("SELECT * FROM accounts WHERE account_id = 'TESTAC01'");
check('パスワードはハッシュで保存される',
    $newAcc && password_verify('testpass1', $newAcc['password_hash']));
check('作業者2名も一緒に登録される',
    (int) Database::value('SELECT COUNT(*) FROM workers WHERE account_id = ? AND deleted_at IS NULL',
        [$newAcc['id']]) === 2);
check('発行したIDでログインできる', (function () {
    global $JAR;
    $keep = file_get_contents($JAR);
    @unlink($JAR);
    $ok = str_contains(req('POST', '/login',
        ['_csrf' => csrf('/login'), 'login_id' => 'TESTAC01', 'password' => 'testpass1'])['location'],
        '/dashboard');
    file_put_contents($JAR, $keep);
    return $ok;
})());

echo "--- K-3 ロック解除（概要書 1-1） ---\n";
Database::run('UPDATE accounts SET is_locked = 1, failed_count = 3 WHERE id = ?', [$newAcc['id']]);
check('一覧にロック解除ボタンが出る',
    str_contains(req('GET', '/admin/users')['body'], 'ロック解除'));
req('POST', '/admin/users/unlock', ['_csrf' => csrf('/admin/users'), 'id' => $newAcc['id']]);
check('解除できる',
    (int) Database::value('SELECT is_locked FROM accounts WHERE id = ?', [$newAcc['id']]) === 0
    && (int) Database::value('SELECT failed_count FROM accounts WHERE id = ?', [$newAcc['id']]) === 0);

Database::run('UPDATE accounts SET is_locked = 1 WHERE id = ?', [$newAcc['id']]);
req('POST', '/admin/users/save', [
    '_csrf' => csrf('/admin/users?edit=' . $newAcc['id']),
    'id' => $newAcc['id'], 'account_id' => 'TESTAC01',
    'company_name' => '株式会社テスト設備', 'password' => 'newpass9',
    'workers' => ['試験 一郎', '', '', '', ''],
]);
check('パスワードを出し直すとロックも解ける',
    (int) Database::value('SELECT is_locked FROM accounts WHERE id = ?', [$newAcc['id']]) === 0);
check('作業者を空にすると隠れる（過去の記録は残す）',
    (int) Database::value('SELECT COUNT(*) FROM workers WHERE account_id = ? AND deleted_at IS NULL',
        [$newAcc['id']]) === 1
    && (int) Database::value('SELECT COUNT(*) FROM workers WHERE account_id = ?',
        [$newAcc['id']]) === 2);

// 後片付け
Database::run('DELETE FROM workers WHERE account_id = ?', [$newAcc['id']]);
Database::run('DELETE FROM accounts WHERE id = ?', [$newAcc['id']]);

// ================================================================ K-4
echo "--- K-4 交換部品マスタ ---\n";
$r = req('GET', '/admin/parts');
check('表示される', $r['status'] === 200);
check('ダウンロードとインポートのボタンがある',
    str_contains($r['body'], 'ダウンロード') && str_contains($r['body'], 'インポート'));
check('ヨミガナ列がある', str_contains($r['body'], 'ヨミガナ'));

echo "--- K-4 ダウンロード ---\n";
$dl = req('GET', '/admin/parts/download');
check('CSVとして返る', str_contains($dl['head'], 'text/csv')
    && str_contains($dl['head'], 'attachment'));
check('エクセルで開けるようBOMが付く', str_starts_with($dl['body'], "\xEF\xBB\xBF"));
$lines = preg_split('/\r\n|\n/', trim($dl['body']));
$dbCount = (int) Database::value('SELECT COUNT(*) FROM parts WHERE deleted_at IS NULL');
check('見出し＋全件が入る', count($lines) === $dbCount + 1,
    (count($lines) - 1) . '/' . $dbCount . '件');
check('見出しが4列', str_contains($lines[0], '部品名') && str_contains($lines[0], 'ヨミガナ')
    && str_contains($lines[0], '単位') && str_contains($lines[0], '優先順位'));

echo "--- K-4 インポート：おかしなファイルを弾く ---\n";
$bad = $TMP . '/parts_bad.csv';
file_put_contents($bad, "\xEF\xBB\xBF" . implode("\n", [
    '部品名,ヨミガナ,単位,優先順位',
    'テスト部品A,テストブヒンエー,個,100',
    ',ヨミだけ,個,10',
    'テスト部品A,かぶり,個,10',
    'テスト部品B,テストブヒンビー,個,あいうえお',
]));
req('POST', '/admin/parts/import', ['_csrf' => csrf('/admin/parts')], $bad);
$body = req('GET', '/admin/parts')['body'];
check('部品名が空の行を知らせる', str_contains($body, '部品名が空です'));
check('重複を知らせる（概要書 K-4「製品名の重複エラー」）', str_contains($body, '重複しています'));
check('優先順位が数字でない行を知らせる', str_contains($body, '優先順位は数字で'));
check('エラーがあれば取り込まない',
    (int) Database::value('SELECT COUNT(*) FROM parts WHERE deleted_at IS NULL') === $dbCount);

echo "--- K-4 インポート：差分の確認 ---\n";
// ダウンロードしたCSVを少しだけ直す（1件変更・1件追加・1件削除）
$rows = array_slice($lines, 1);
$firstName = str_getcsv($rows[0])[0];
$rows[0]   = implode(',', ['"' . $firstName . '"', '"ヘンコウズミ"', '枚', '999999']);
$dropped   = str_getcsv(array_pop($rows))[0];
$rows[]    = '"新規テスト部材","シンキテストブザイ",本,50';

$good = $TMP . '/parts_good.csv';
file_put_contents($good, "\xEF\xBB\xBF" . $lines[0] . "\n" . implode("\n", $rows) . "\n");

req('POST', '/admin/parts/import', ['_csrf' => csrf('/admin/parts')], $good);
$body = req('GET', '/admin/parts')['body'];
check('取り込む前に確認が出る', str_contains($body, '取り込む内容の確認'));
check('追加1件と出る', (bool) preg_match('/diff-box--add">\s*<b>1<\/b>/', $body));
check('変更1件と出る', (bool) preg_match('/diff-box--update">\s*<b>1<\/b>/', $body));
check('削除1件と出る', (bool) preg_match('/diff-box--remove">\s*<b>1<\/b>/', $body));
check('この時点ではDBを触っていない',
    (int) Database::value('SELECT COUNT(*) FROM parts WHERE deleted_at IS NULL') === $dbCount
    && Database::value('SELECT kana FROM parts WHERE name = ?', [$firstName]) !== 'ヘンコウズミ');

echo "--- K-4 インポート：実行 ---\n";
// 過去の報告書からの紐付けが壊れないことを確かめるため、いまの part_id を控える
$linked = Database::one(
    'SELECT rp.report_id, rp.part_id, p.name FROM report_parts rp
       JOIN parts p ON p.id = rp.part_id LIMIT 1');

preg_match('/name="token" value="([a-f0-9]+)"/', $body, $tk);
$r = req('POST', '/admin/parts/import/apply',
    ['_csrf' => csrf('/admin/parts'), 'token' => $tk[1] ?? '']);
check('実行して一覧に戻る', str_contains($r['location'], '/admin/parts'));

$body = req('GET', '/admin/parts')['body'];
check('結果を知らせる', str_contains($body, '交換部品マスタを更新しました'));
check('変更が反映される',
    Database::value('SELECT kana FROM parts WHERE name = ?', [$firstName]) === 'ヘンコウズミ');
check('追加が反映される',
    Database::value('SELECT id FROM parts WHERE name = ?', ['新規テスト部材']) !== null);
check('ファイルに無いものは消さずに隠す',
    Database::value('SELECT deleted_at FROM parts WHERE name = ?', [$dropped]) !== null,
    $dropped);
check('件数が合う',
    (int) Database::value('SELECT COUNT(*) FROM parts WHERE deleted_at IS NULL') === $dbCount,
    $dbCount . '件のまま');

check('過去の報告書からの紐付けが外れない（採番が変わらない）',
    (int) Database::value('SELECT COUNT(*) FROM parts WHERE id = ? AND name = ?',
        [$linked['part_id'], $linked['name']]) === 1,
    $linked['name']);

$backups = glob($ROOT . '/data/backups/parts_backup_*.csv');
check('取り込み前の控えが残る', count($backups) >= 1,
    $backups ? basename(end($backups)) : 'なし');
check('控えの中身が取り込み前の件数と合う', (function () use ($backups, $dbCount) {
    $n = count(preg_split('/\r\n|\n/', trim((string) file_get_contents(end($backups))))) - 1;
    return $n === $dbCount;
})());

echo "--- K-4 1件ずつの登録 ---\n";
req('POST', '/admin/parts/save', [
    '_csrf' => csrf('/admin/parts?new=1'), 'id' => 0,
    'name' => '新規テスト部材', 'kana' => 'かぶり', 'unit' => '個', 'priority' => 0,
]);
check('部品名の重複を弾く',
    str_contains(req('GET', '/admin/parts?new=1')['body'], 'すでに登録されています'));

$part = Database::one("SELECT * FROM parts WHERE name = '新規テスト部材'");
req('POST', '/admin/parts/save', [
    '_csrf' => csrf('/admin/parts?edit=' . $part['id']), 'id' => $part['id'],
    'name' => '新規テスト部材', 'kana' => 'シンキテストブザイ', 'unit' => '式', 'priority' => 12345,
]);
check('1件の修正ができる',
    Database::value('SELECT unit FROM parts WHERE id = ?', [$part['id']]) === '式'
    && (int) Database::value('SELECT priority FROM parts WHERE id = ?', [$part['id']]) === 12345);

req('POST', '/admin/parts/delete', ['_csrf' => csrf('/admin/parts'), 'id' => $part['id']]);
check('削除は隠すだけ',
    Database::value('SELECT deleted_at FROM parts WHERE id = ?', [$part['id']]) !== null);

// ================================================================ K-5
echo "--- K-5 機種名マスタ ---\n";
$r = req('GET', '/admin/models');
check('表示される', $r['status'] === 200);
$modelsBefore = (int) Database::value('SELECT COUNT(*) FROM machine_models WHERE deleted_at IS NULL');

req('POST', '/admin/models/save', [
    '_csrf' => csrf('/admin/models?new=1'), 'id' => 0,
    'name' => 'MDF', 'kana' => 'かぶり', 'sort_order' => 0,
]);
check('機種名の重複を弾く',
    str_contains(req('GET', '/admin/models?new=1')['body'], 'すでに登録されています'));

req('POST', '/admin/models/save', [
    '_csrf' => csrf('/admin/models?new=1'), 'id' => 0,
    'name' => 'テスト機 TX-1', 'kana' => 'テストキTX-1', 'sort_order' => 500,
]);
check('追加できる',
    (int) Database::value('SELECT COUNT(*) FROM machine_models WHERE deleted_at IS NULL')
        === $modelsBefore + 1);

$model = Database::one("SELECT * FROM machine_models WHERE name = 'テスト機 TX-1'");
check('現場の作業内容に出る（新しい報告書）', (function () use ($model) {
    global $JAR;
    $keep = file_get_contents($JAR);
    @unlink($JAR);
    req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0001', 'password' => 'pass1234']);
    $loc = req('GET', '/report/new?uuid=admtest' . bin2hex(random_bytes(4)))['location'];
    preg_match('#/report/(\d+)/#', $loc, $m);
    $ok = str_contains(req('GET', "/report/{$m[1]}/work")['body'], 'テスト機 TX-1');
    Database::run('DELETE FROM report_models WHERE report_id = ?', [$m[1]]);
    Database::run('DELETE FROM report_measurements WHERE report_id = ?', [$m[1]]);
    Database::run('DELETE FROM reports WHERE id = ?', [$m[1]]);
    @unlink($JAR);
    file_put_contents($JAR, $keep);
    return $ok;
})());

req('POST', '/admin/models/delete',
    ['_csrf' => csrf('/admin/models?edit=' . $model['id']), 'id' => $model['id']]);
check('削除は隠すだけ',
    Database::value('SELECT deleted_at FROM machine_models WHERE id = ?', [$model['id']]) !== null);

// ================================================================ K-6
echo "--- K-6 報告事項マスタ ---\n";
$r = req('GET', '/admin/texts');
check('表示される', $r['status'] === 200);
check('全社共通のものだけを扱うと明記', str_contains($r['body'], '全社共通'));

$commonBefore = (int) Database::value(
    'SELECT COUNT(*) FROM report_texts WHERE account_id IS NULL AND deleted_at IS NULL');
req('POST', '/admin/texts/save', [
    '_csrf' => csrf('/admin/texts?new=1'), 'id' => 0, 'body' => '', 'sort_order' => 0,
]);
check('空では登録できない',
    str_contains(req('GET', '/admin/texts?new=1')['body'], '報告事項を入れてください'));

req('POST', '/admin/texts/save', [
    '_csrf' => csrf('/admin/texts?new=1'), 'id' => 0,
    'body' => '管理者が登録したテスト用の報告事項です。', 'sort_order' => 900,
]);
check('追加できる',
    (int) Database::value('SELECT COUNT(*) FROM report_texts WHERE account_id IS NULL AND deleted_at IS NULL')
        === $commonBefore + 1);

$text = Database::one("SELECT * FROM report_texts WHERE body LIKE '管理者が登録した%'");
check('協力会社の文章は一覧に混ざらない', (function () {
    $own = (int) Database::value(
        'SELECT COUNT(*) FROM report_texts WHERE account_id IS NOT NULL AND deleted_at IS NULL');
    $body = req('GET', '/admin/texts')['body'];
    return $own === 0 || !str_contains($body, '次回点検時にHEPAフィルター');
})());
req('POST', '/admin/texts/delete',
    ['_csrf' => csrf('/admin/texts?edit=' . $text['id']), 'id' => $text['id']]);
check('削除は隠すだけ',
    Database::value('SELECT deleted_at FROM report_texts WHERE id = ?', [$text['id']]) !== null);

// ================================================================ K-7
echo "--- K-7 管理者情報 ---\n";
$r = req('GET', '/admin/profile');
check('表示される', $r['status'] === 200 && str_contains($r['body'], '報告書の受信メールアドレス'));

check('パスワードの再入力が違うと弾く', str_contains(req('POST', '/admin/profile', [
    '_csrf' => csrf('/admin/profile'), 'account_id' => 'admin',
    'notify_email' => 'a@example.jp', 'password' => 'abcd1234', 'password_confirm' => 'zzzz9999',
])['body'], '一致しません'));

check('メールの形式チェックが効く', str_contains(req('POST', '/admin/profile', [
    '_csrf' => csrf('/admin/profile'), 'account_id' => 'admin', 'notify_email' => 'こわれた',
])['body'], 'メールアドレスの形式'));

$hashBefore = (string) Database::value('SELECT password_hash FROM admins WHERE account_id = ?', ['admin']);
$r = req('POST', '/admin/profile', [
    '_csrf' => csrf('/admin/profile'), 'account_id' => 'admin',
    'notify_email' => 'jimu@example.co.jp',
]);
check('パスワード空欄なら変えずに保存できる',
    str_contains($r['location'], '/admin/dashboard')
    && Database::value('SELECT notify_email FROM admins WHERE account_id = ?', ['admin'])
        === 'jimu@example.co.jp'
    && Database::value('SELECT password_hash FROM admins WHERE account_id = ?', ['admin']) === $hashBefore);

// ================================================================ 権限
echo "--- 利用者が管理者サイトに入れないか ---\n";
@unlink($JAR);
req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0001', 'password' => 'pass1234']);
foreach (['/admin/dashboard', '/admin/users', '/admin/parts', '/admin/models',
          '/admin/texts', '/admin/profile', '/admin/parts/download'] as $p) {
    check("{$p} はログイン画面へ戻される",
        str_contains(req('GET', $p)['location'], '/admin/login'));
}
check('管理者用のPDF経路も入れない',
    str_contains(req('GET', '/admin/report/' . $target['id'] . '/sheet')['location'], '/admin/login'));

// ================================================================ 警告
echo "--- 警告の有無 ---\n";
@unlink($JAR);
req('POST', '/admin/login',
    ['_csrf' => csrf('/admin/login'), 'login_id' => 'admin', 'password' => 'admin1234']);
$dirty = [];
foreach (['/admin/dashboard', '/admin/dashboard?q=' . urlencode('病院'), '/admin/users',
          '/admin/users?new=1', '/admin/parts', '/admin/parts?new=1', '/admin/models',
          '/admin/models?new=1', '/admin/texts', '/admin/texts?new=1', '/admin/profile'] as $p) {
    $x = req('GET', $p);
    if ($x['status'] !== 200 || preg_match('/(Warning|Notice|Deprecated|Fatal error|Undefined)/', $x['body'])) {
        $dirty[] = $p . '(' . $x['status'] . ')';
    }
}
check('管理者11画面すべて200かつ警告なし', !$dirty, implode(' ', $dirty));

test_summary();
