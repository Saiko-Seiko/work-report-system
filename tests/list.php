<?php
/**
 * Phase 6：報告書一覧（3）とマイページ（5-1〜5-3）の確認。
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';


/** 一覧のNo.列を上から拾う */
function listNos(string $body): array
{
    preg_match_all('/<td class="c-no num">(\d+)<\/td>/', $body, $m);
    return array_map('intval', $m[1]);
}

req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0001', 'password' => 'pass1234']);
$accountId = (int) Database::value('SELECT id FROM accounts WHERE account_id = ?', ['ABCDE0001']);

// ================================================================ 3 報告書一覧
echo "--- 3 報告書一覧 ---\n";
$r = req('GET', '/reports');
check('表示される', $r['status'] === 200);

$expect = (int) Database::value(
    'SELECT COUNT(*) FROM reports WHERE account_id = ? AND deleted_at IS NULL', [$accountId]
);
$nos = listNos($r['body']);
check('自社の報告書が全件出る', count($nos) === $expect, count($nos) . '/' . $expect . '件');
check('件数表示が出る', (bool) preg_match('/1-' . $expect . '\/' . $expect . '/', $r['body']));

echo "--- 概要書 3 の列がそろっているか ---\n";
foreach (['No.', '作業日', '作成日', '病院名', '作業者', '署名', 'PDF', 'Mail', '社内用', '状態'] as $col) {
    check("列「{$col}」がある", str_contains($r['body'], $col));
}

echo "--- ●／－ の出し方（3-5〜3-9） ---\n";
$top = Database::one(
    "SELECT r.*, i.pdf_at AS ipdf, i.completed_at AS idone
       FROM reports r LEFT JOIN internal_reports i ON i.report_id = r.id
      WHERE r.account_id = ? ORDER BY r.report_no DESC LIMIT 1", [$accountId]
);
$draft = Database::one(
    "SELECT * FROM reports WHERE account_id = ? AND status = 'draft' ORDER BY id LIMIT 1", [$accountId]
);
check('署名ありは「有」', $top['signature_at'] && str_contains($r['body'], '>有<'));
check('提出用PDFありはプレビューへのリンク',
    str_contains($r['body'], '/report/' . $top['id'] . '/preview'));
check('Mailの回数が数字で出る', str_contains($r['body'], '>' . (int) $top['mail_count'] . '<'));
check('完了は「完」', $top['idone'] && str_contains($r['body'], '>完<'));
check('未完了の行には「－」がある', $draft && substr_count($r['body'], '－') >= 3,
    '－ ' . substr_count($r['body'], '－') . '個');
check('病院名は修正画面へのリンク',
    str_contains($r['body'], '/report/' . $top['id'] . '/basic'));
check('病院名を1行で出す指定がある', str_contains(
    req('GET', '/assets/css/app.css')['body'], 'white-space: nowrap;      /* 概要書 3-10'
));

echo "--- 並べ替え（3-4） ---\n";
$desc = listNos(req('GET', '/reports?sort=no&dir=desc')['body']);
$asc  = listNos(req('GET', '/reports?sort=no&dir=asc')['body']);
check('No. で降順・昇順が入れ替わる', $desc === array_reverse($asc), $desc[0] . ' ←→ ' . $asc[0]);

$byWork = listNos(req('GET', '/reports?sort=work&dir=asc')['body']);
$want   = array_map('intval', array_column(Database::all(
    'SELECT report_no FROM reports WHERE account_id = ? AND deleted_at IS NULL
      ORDER BY work_date ASC, id ASC', [$accountId]
), 'report_no'));
check('作業日で並べ替えできる', $byWork === $want);

$byHosp = listNos(req('GET', '/reports?sort=hospital&dir=asc')['body']);
$want   = array_map('intval', array_column(Database::all(
    'SELECT report_no FROM reports WHERE account_id = ? AND deleted_at IS NULL
      ORDER BY hospital_name ASC, id ASC', [$accountId]
), 'report_no'));
check('病院名で並べ替えできる', $byHosp === $want);

$byCreated = listNos(req('GET', '/reports?sort=created&dir=desc')['body']);
check('作成日で並べ替えできる', count($byCreated) === $expect);
check('知らない並べ替えキーは既定に戻す',
    listNos(req('GET', '/reports?sort=DROP+TABLE&dir=x')['body']) === $desc);

echo "--- コピーして作成 ---\n";
$src   = (int) $top['id'];
$before = (int) Database::value('SELECT COUNT(*) FROM reports WHERE account_id = ?', [$accountId]);
$uuid  = 'copy-' . bin2hex(random_bytes(6));

$r2 = req('POST', '/reports/copy', [
    '_csrf' => csrf('/reports'), 'source_id' => $src, 'client_uuid' => $uuid,
]);
preg_match('#/report/(\d+)/basic#', $r2['location'], $m);
$newId = (int) ($m[1] ?? 0);
check('写した下書きの基本情報画面へ進む', $newId > 0, "id={$newId}");

$new = Database::one('SELECT * FROM reports WHERE id = ?', [$newId]);
check('病院名・作業場所・件名が写る',
    $new['hospital_name'] === $top['hospital_name']
    && $new['work_place'] === $top['work_place']
    && $new['work_title'] === $top['work_title']);
check('下書きとして作られる', $new['status'] === 'draft');
check('作成日・作業日は今日になる',
    $new['created_date'] === date('Y-m-d') && $new['work_date'] === date('Y-m-d'));
check('サイン・担当・提出記録は写さない',
    $new['signature_at'] === null && (string) $new['submitter_name'] === ''
    && $new['submitted_at'] === null && (int) $new['mail_count'] === 0
    && $new['pdf_at'] === null && $new['completed_at'] === null);
check('新しいNo.が振られる', (int) $new['report_no'] > (int) $top['report_no'],
    $top['report_no'] . ' → ' . $new['report_no']);

$cnt = fn(string $t) => (int) Database::value("SELECT COUNT(*) FROM {$t} WHERE report_id = ?", [$newId]);
$org = fn(string $t) => (int) Database::value("SELECT COUNT(*) FROM {$t} WHERE report_id = ?", [$src]);
check('作業内容・交換部品・作業者・測定値の行数が一致',
    $cnt('report_models') === $org('report_models')
    && $cnt('report_parts') === $org('report_parts')
    && $cnt('report_workers') === $org('report_workers')
    && $cnt('report_measurements') === $org('report_measurements'),
    sprintf('機種%d 部品%d 作業者%d 測定%d',
        $cnt('report_models'), $cnt('report_parts'),
        $cnt('report_workers'), $cnt('report_measurements')));
check('部品の数量も写る',
    (int) Database::value('SELECT SUM(qty) FROM report_parts WHERE report_id = ?', [$newId])
    === (int) Database::value('SELECT SUM(qty) FROM report_parts WHERE report_id = ?', [$src]));
check('測定値の実測値は空にする（前回の数字を持ち込まない）',
    (int) Database::value(
        'SELECT COUNT(*) FROM report_measurements WHERE report_id = ? AND cumulative_hours IS NOT NULL',
        [$newId]) === 0);

// 二度押し
req('POST', '/reports/copy', ['_csrf' => csrf('/reports'), 'source_id' => $src, 'client_uuid' => $uuid]);
check('同じキーで二度押しても1件だけ',
    (int) Database::value('SELECT COUNT(*) FROM reports WHERE account_id = ?', [$accountId]) === $before + 1);

// 他社のものは写せない
@unlink($JAR);
req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0002', 'password' => 'pass1234']);
check('他社の報告書は写せない',
    req('POST', '/reports/copy', [
        '_csrf' => csrf('/reports'), 'source_id' => $src, 'client_uuid' => 'x' . bin2hex(random_bytes(6)),
    ])['status'] === 404);
check('他社の一覧に自社の報告書は出ない', listNos(req('GET', '/reports')['body']) === []);

@unlink($JAR);
req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => 'ABCDE0001', 'password' => 'pass1234']);
Database::run('DELETE FROM reports WHERE id = ?', [$newId]);
foreach (['report_models', 'report_parts', 'report_workers', 'report_measurements'] as $t) {
    Database::run("DELETE FROM {$t} WHERE report_id = ?", [$newId]);
}

// ================================================================ 5-1
echo "--- 5-1 ユーザー情報変更 ---\n";
$r = req('GET', '/mypage');
check('表示される', $r['status'] === 200);
check('IDは変更できない（readonly）',
    (bool) preg_match('/value="ABCDE0001" readonly/', $r['body']));

check('会社名が空だと弾く', str_contains(req('POST', '/mypage', [
    '_csrf' => csrf('/mypage'), 'company_name' => '', 'email' => 'a@example.jp',
])['body'], '会社名を入れてください'));

check('メールの形式チェックが効く', str_contains(req('POST', '/mypage', [
    '_csrf' => csrf('/mypage'), 'company_name' => '株式会社エムテック設備', 'email' => 'こわれた',
])['body'], 'メールアドレスの形式が正しくありません'));

check('パスワードが7文字だと弾く', str_contains(req('POST', '/mypage', [
    '_csrf' => csrf('/mypage'), 'company_name' => '株式会社エムテック設備',
    'email' => 'a@example.jp', 'password' => 'abc1234', 'password_confirm' => 'abc1234',
])['body'], '8文字以上'));

check('全角を含むパスワードは弾く', str_contains(req('POST', '/mypage', [
    '_csrf' => csrf('/mypage'), 'company_name' => '株式会社エムテック設備',
    'email' => 'a@example.jp', 'password' => 'ａｂｃ12345', 'password_confirm' => 'ａｂｃ12345',
])['body'], '半角英数字'));

check('確認用が違うと弾く', str_contains(req('POST', '/mypage', [
    '_csrf' => csrf('/mypage'), 'company_name' => '株式会社エムテック設備',
    'email' => 'a@example.jp', 'password' => 'abcd1234', 'password_confirm' => 'abcd9999',
])['body'], '一致しません'));

$hashBefore = (string) Database::value('SELECT password_hash FROM accounts WHERE id = ?', [$accountId]);
$r = req('POST', '/mypage', [
    '_csrf' => csrf('/mypage'), 'company_name' => '株式会社エムテック設備（更新）',
    'email' => 'new@example.co.jp',
]);
check('パスワード空欄なら変えずに保存できる',
    str_contains($r['location'], '/dashboard')
    && Database::value('SELECT password_hash FROM accounts WHERE id = ?', [$accountId]) === $hashBefore
    && Database::value('SELECT email FROM accounts WHERE id = ?', [$accountId]) === 'new@example.co.jp');

req('POST', '/mypage', [
    '_csrf' => csrf('/mypage'), 'company_name' => '株式会社エムテック設備',
    'email' => 'mtec@example.co.jp', 'password' => 'newpass1', 'password_confirm' => 'newpass1',
]);
$hashAfter = (string) Database::value('SELECT password_hash FROM accounts WHERE id = ?', [$accountId]);
check('パスワードを変えるとハッシュが変わる', $hashAfter !== $hashBefore);
check('新しいパスワードでログインできる', password_verify('newpass1', $hashAfter));
// 元に戻す
Database::run('UPDATE accounts SET password_hash = ? WHERE id = ?', [$hashBefore, $accountId]);

// ================================================================ 5-2
echo "--- 5-2 作業者テーブル変更 ---\n";
$r = req('GET', '/mypage/workers');
check('表示される', $r['status'] === 200);
check('10件ずつ表示する', (bool) preg_match('/1-10\/\d+/', $r['body']));

$wBefore = (int) Database::value(
    'SELECT COUNT(*) FROM workers WHERE account_id = ? AND deleted_at IS NULL', [$accountId]
);
req('POST', '/mypage/workers', ['_csrf' => csrf('/mypage/workers'), 'add' => '1']);
check('＋追加で1行ふえる',
    (int) Database::value('SELECT COUNT(*) FROM workers WHERE account_id = ? AND deleted_at IS NULL',
        [$accountId]) === $wBefore + 1);

$blank = Database::one(
    "SELECT id FROM workers WHERE account_id = ? AND name = '' ORDER BY id DESC", [$accountId]
);
req('POST', '/mypage/workers', [
    '_csrf' => csrf('/mypage/workers'),
    "w[{$blank['id']}][name]" => '新人 花子',
    "w[{$blank['id']}][kana]" => 'シンジンハナコ',
]);
check('名前とヨミガナが保存される',
    Database::value('SELECT name FROM workers WHERE id = ?', [$blank['id']]) === '新人 花子'
    && Database::value('SELECT kana FROM workers WHERE id = ?', [$blank['id']]) === 'シンジンハナコ');
check('空白を含む氏名が1人として入る',
    Database::value('SELECT name FROM workers WHERE id = ?', [$blank['id']]) === '新人 花子');

// 空にすると消える
req('POST', '/mypage/workers', [
    '_csrf' => csrf('/mypage/workers'), "w[{$blank['id']}][name]" => '', "w[{$blank['id']}][kana]" => '',
]);
check('氏名を空にすると行が消える',
    Database::value('SELECT id FROM workers WHERE id = ?', [$blank['id']]) === null);

// 削除は隠すだけ
$used = Database::one(
    'SELECT w.id, w.name FROM workers w
      WHERE w.account_id = ? AND w.deleted_at IS NULL
        AND EXISTS (SELECT 1 FROM report_workers rw WHERE rw.worker_id = w.id)
      LIMIT 1', [$accountId]
);
if ($used) {
    $refs = (int) Database::value('SELECT COUNT(*) FROM report_workers WHERE worker_id = ?', [$used['id']]);
    req('POST', '/mypage/workers', ['_csrf' => csrf('/mypage/workers'), 'delete_id' => $used['id']]);
    check('「×」は消さずに隠すだけ',
        Database::value('SELECT deleted_at FROM workers WHERE id = ?', [$used['id']]) !== null);
    check('過去の報告書の作業者名は残る',
        (int) Database::value('SELECT COUNT(*) FROM report_workers WHERE worker_id = ?', [$used['id']]) === $refs);
    check('隠した作業者は一覧に出ない',
        !str_contains(req('GET', '/mypage/workers?sort=name&dir=asc')['body'],
            'value="' . $used['name'] . '"'));
    Database::run('UPDATE workers SET deleted_at = NULL WHERE id = ?', [$used['id']]);
}

echo "--- 5-2 並べ替え ---\n";
foreach (['created', 'updated', 'name'] as $key) {
    check("「{$key}」で並べ替えできる", req('GET', "/mypage/workers?sort={$key}&dir=asc")['status'] === 200);
}
check('知らないキーは既定に戻す', req('GET', '/mypage/workers?sort=../../etc')['status'] === 200);

// ================================================================ 5-3
echo "--- 5-3 報告事項テーブル変更 ---\n";
$r = req('GET', '/mypage/texts');
check('表示される', $r['status'] === 200);
check('事務局の共通文章を見せている', str_contains($r['body'], '事務局が登録した共通の文章'));
check('共通文章はこの画面で変えられないと明記', str_contains($r['body'], 'この画面では変更できません'));

$tBefore = (int) Database::value(
    'SELECT COUNT(*) FROM report_texts WHERE account_id = ? AND deleted_at IS NULL', [$accountId]
);
req('POST', '/mypage/texts', ['_csrf' => csrf('/mypage/texts'), 'add' => '1']);
$newText = Database::one(
    "SELECT id FROM report_texts WHERE account_id = ? AND body = '' ORDER BY id DESC", [$accountId]
);
check('＋追加で1行ふえる', $newText !== null);

req('POST', '/mypage/texts', [
    '_csrf' => csrf('/mypage/texts'),
    "t[{$newText['id']}][body]" => 'ドレンパンの清掃を実施し、排水良好であることを確認しました。',
]);
check('文章が保存される', str_contains(
    (string) Database::value('SELECT body FROM report_texts WHERE id = ?', [$newText['id']]),
    'ドレンパンの清掃'));

check('2-4の「選択」に自社の文章が出る', str_contains(
    req('GET', '/report/' . $draft['id'] . '/measure')['body'], 'ドレンパンの清掃'));

req('POST', '/mypage/texts', ['_csrf' => csrf('/mypage/texts'), 'delete_id' => $newText['id']]);
check('「×」で隠せる',
    Database::value('SELECT deleted_at FROM report_texts WHERE id = ?', [$newText['id']]) !== null);
Database::run('DELETE FROM report_texts WHERE id = ?', [$newText['id']]);
check('元の件数に戻る',
    (int) Database::value('SELECT COUNT(*) FROM report_texts WHERE account_id = ? AND deleted_at IS NULL',
        [$accountId]) === $tBefore);

// ================================================================ 導線と警告
echo "--- 導線 ---\n";
check('ダッシュボードから3画面へのリンクがある', (function () {
    $b = req('GET', '/dashboard')['body'];
    return str_contains($b, '/mypage"') && str_contains($b, '/mypage/workers')
        && str_contains($b, '/mypage/texts') && str_contains($b, '/reports');
})());
check('完了画面の「一覧へ」が有効になった',
    str_contains(req('GET', '/report/' . $top['id'] . '/done')['body'], 'href="/reports"'));

echo "--- 警告の有無 ---\n";
$dirty = [];
foreach (['/reports', '/reports?sort=hospital&dir=asc', '/mypage', '/mypage/workers',
          '/mypage/texts', '/dashboard', '/_dev'] as $p) {
    $x = req('GET', $p);
    if ($x['status'] !== 200 || preg_match('/(Warning|Notice|Deprecated|Fatal error|Undefined)/', $x['body'])) {
        $dirty[] = $p . '(' . $x['status'] . ')';
    }
}
check('全画面200かつ警告なし', !$dirty, implode(' ', $dirty));

test_summary();
