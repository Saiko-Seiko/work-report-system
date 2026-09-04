<?php
/**
 * 5-1 ユーザー情報変更 / 5-2 作業者テーブル変更 / 5-3 報告事項テーブル変更
 *
 * 5-2 と 5-3 は同じ作りにしてある。
 *   ＋追加 → 空の行を1つ足す
 *   登録   → 全行まとめて保存。空のままの行は自動で捨てる
 *   削除   → 過去の報告書から参照が切れないよう、印を付けて隠すだけにする
 */
declare(strict_types=1);

// ================================================================ 5-1 ユーザー情報

function mypage_user(): void
{
    $user = Auth::requireUser();

    $form = [
        'email'   => (string) $user['email'],
        'company' => (string) $user['company_name'],
    ];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        $form = [
            'email'   => trim((string) post('email', '')),
            'company' => trim((string) post('company_name', '')),
        ];
        $password = (string) post('password', '');
        $confirm  = (string) post('password_confirm', '');

        $errors = mypage_validate_user($form, $password, $confirm);

        if (!$errors) {
            $data = [
                'email'        => mb_substr($form['email'], 0, 255),
                'company_name' => mb_substr($form['company'], 0, 255),
                'updated_at'   => now(),
            ];
            if ($password !== '') {
                $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            Database::update('accounts', $data, 'id = :id', ['id' => $user['id']]);
            audit('account_updated', 'accounts:' . $user['account_id'],
                $password !== '' ? 'パスワード変更あり' : '');

            redirect('/dashboard');
        }
    }

    view('user/mypage_user', [
        'user'   => $user,
        'form'   => $form,
        'errors' => $errors,
        'title'  => 'ユーザー情報変更',
    ], 'layout_user');
}

function mypage_validate_user(array $f, string $password, string $confirm): array
{
    $e = [];

    if ($f['company'] === '') {
        $e['company_name'] = '会社名を入れてください。';
    }
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $e['email'] = 'メールアドレスの形式が正しくありません。';
    }

    // 概要書 5-1-2「パスワードチェックを入れる（半角英数8文字）」
    if ($password !== '') {
        if (!preg_match('/^[0-9A-Za-z]{8,64}$/', $password)) {
            $e['password'] = 'パスワードは半角英数字のみ、8文字以上で入れてください。';
        } elseif ($password !== $confirm) {
            $e['password_confirm'] = '確認用のパスワードが一致しません。';
        }
    }

    return $e;
}

// ================================================================ 5-2 作業者テーブル

const MYPAGE_WORKER_SORTS = [
    'created' => 'created_at',
    'updated' => 'updated_at',
    'name'    => 'kana',
];
const MYPAGE_PER_PAGE = 10;

function mypage_workers(): void
{
    $user = Auth::requireUser();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        // まず画面に出ていた行を保存する（＋追加や削除の前に必ず通す）
        mypage_save_workers((int) $user['id'], (array) post('w', []));

        if (post('add') !== null) {
            Database::insert('workers', [
                'account_id' => $user['id'],
                'name'       => '',
                'kana'       => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            redirect('/mypage/workers');
        }
        if (post('delete_id') !== null) {
            // 過去の報告書に名前が残っているので、消さずに隠す
            Database::run(
                'UPDATE workers SET deleted_at = ?, updated_at = ? WHERE id = ? AND account_id = ?',
                [now(), now(), (int) post('delete_id'), $user['id']]
            );
            redirect('/mypage/workers');
        }

        audit('workers_updated', 'accounts:' . $user['account_id']);
        redirect('/dashboard');
    }

    [$sort, $dir, $page, $pages, $total, $offset] =
        mypage_page_params('SELECT COUNT(*) FROM workers WHERE account_id = ? AND deleted_at IS NULL',
            [$user['id']], MYPAGE_WORKER_SORTS, 'name');

    $column = MYPAGE_WORKER_SORTS[$sort];

    view('user/mypage_workers', [
        'rows' => Database::all(
            "SELECT * FROM workers
              WHERE account_id = ? AND deleted_at IS NULL
              ORDER BY {$column} {$dir}, id {$dir}
              LIMIT " . MYPAGE_PER_PAGE . " OFFSET {$offset}",
            [$user['id']]
        ),
        'total' => $total,
        'page'  => $page,
        'pages' => $pages,
        'from'  => $total === 0 ? 0 : $offset + 1,
        'to'    => min($offset + MYPAGE_PER_PAGE, $total),
        'sort'  => $sort,
        'dir'   => $dir,
        'title' => '作業者テーブル変更',
    ], 'layout_user');
}

/** 空のままの行は捨てる。＋追加を押しただけで放置しても残らない */
function mypage_save_workers(int $accountId, array $rows): void
{
    foreach ($rows as $id => $row) {
        $id   = (int) $id;
        $name = trim((string) ($row['name'] ?? ''));
        $kana = trim((string) ($row['kana'] ?? ''));

        $own = Database::value(
            'SELECT id FROM workers WHERE id = ? AND account_id = ?', [$id, $accountId]
        );
        if (!$own) {
            continue;
        }

        if ($name === '') {
            Database::run('DELETE FROM workers WHERE id = ? AND account_id = ?', [$id, $accountId]);
            continue;
        }

        Database::update('workers', [
            'name'       => mb_substr($name, 0, 128),
            'kana'       => mb_substr($kana, 0, 128),
            'updated_at' => now(),
        ], 'id = :id AND account_id = :account_id', ['id' => $id, 'account_id' => $accountId]);
    }
}

// ================================================================ 5-3 報告事項テーブル

const MYPAGE_TEXT_SORTS = [
    'body'    => 'body',
    'created' => 'created_at',
];

function mypage_texts(): void
{
    $user = Auth::requireUser();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        mypage_save_texts((int) $user['id'], (array) post('t', []));

        if (post('add') !== null) {
            $max = (int) Database::value(
                'SELECT COALESCE(MAX(sort_order), 0) FROM report_texts WHERE account_id = ?',
                [$user['id']]
            );
            Database::insert('report_texts', [
                'account_id' => $user['id'],
                'body'       => '',
                'sort_order' => $max + 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            redirect('/mypage/texts');
        }
        if (post('delete_id') !== null) {
            Database::run(
                'UPDATE report_texts SET deleted_at = ?, updated_at = ? WHERE id = ? AND account_id = ?',
                [now(), now(), (int) post('delete_id'), $user['id']]
            );
            redirect('/mypage/texts');
        }

        audit('report_texts_updated', 'accounts:' . $user['account_id']);
        redirect('/dashboard');
    }

    [$sort, $dir, $page, $pages, $total, $offset] =
        mypage_page_params(
            'SELECT COUNT(*) FROM report_texts WHERE account_id = ? AND deleted_at IS NULL',
            [$user['id']], MYPAGE_TEXT_SORTS, 'created'
        );

    $column = MYPAGE_TEXT_SORTS[$sort];

    view('user/mypage_texts', [
        'rows' => Database::all(
            "SELECT * FROM report_texts
              WHERE account_id = ? AND deleted_at IS NULL
              ORDER BY {$column} {$dir}, id {$dir}
              LIMIT " . MYPAGE_PER_PAGE . " OFFSET {$offset}",
            [$user['id']]
        ),
        // 事務局が登録した全社共通の定型文。ここでは変えられないので見せるだけ
        'common' => Database::all(
            'SELECT body FROM report_texts
              WHERE account_id IS NULL AND deleted_at IS NULL ORDER BY sort_order, id'
        ),
        'total' => $total,
        'page'  => $page,
        'pages' => $pages,
        'from'  => $total === 0 ? 0 : $offset + 1,
        'to'    => min($offset + MYPAGE_PER_PAGE, $total),
        'sort'  => $sort,
        'dir'   => $dir,
        'title' => '報告事項テーブル変更',
    ], 'layout_user');
}

function mypage_save_texts(int $accountId, array $rows): void
{
    foreach ($rows as $id => $row) {
        $id   = (int) $id;
        $body = trim((string) ($row['body'] ?? ''));

        $own = Database::value(
            'SELECT id FROM report_texts WHERE id = ? AND account_id = ?', [$id, $accountId]
        );
        if (!$own) {
            continue;
        }

        if ($body === '') {
            Database::run(
                'DELETE FROM report_texts WHERE id = ? AND account_id = ?', [$id, $accountId]
            );
            continue;
        }

        Database::update('report_texts', [
            'body'       => mb_substr($body, 0, 1000),
            'updated_at' => now(),
        ], 'id = :id AND account_id = :account_id', ['id' => $id, 'account_id' => $accountId]);
    }
}

// ================================================================ 共通

/**
 * 並べ替えとページ送りの値を、許した範囲に収めて返す。
 * @return array{0:string,1:string,2:int,3:int,4:int,5:int}
 */
function mypage_page_params(string $countSql, array $params, array $sorts, string $default): array
{
    $sort = (string) query('sort', $default);
    if (!isset($sorts[$sort])) {
        $sort = $default;
    }
    $dir = strtolower((string) query('dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

    $total  = (int) Database::value($countSql, $params);
    $pages  = max(1, (int) ceil($total / MYPAGE_PER_PAGE));
    $page   = min(max(1, (int) query('page', 1)), $pages);
    $offset = ($page - 1) * MYPAGE_PER_PAGE;

    return [$sort, $dir, $page, $pages, $total, $offset];
}
