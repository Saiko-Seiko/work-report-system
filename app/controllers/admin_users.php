<?php
/**
 * K-3 ユーザー登録画面 / K-3-1 アカウント詳細修正（追加登録）ダイアログ
 *
 * 概要書「アカウントIDは各作業人ではなく会社毎に発行する。発行管理は事務局で行う」
 * と、1-1「3回エラーでパスワードロック。解除は事務局で行う」がここに入る。
 */
declare(strict_types=1);

require_once APP_ROOT . '/app/controllers/admin_common.php';

const ADMIN_USER_SORTS = [
    'no'      => 'id',
    'login'   => 'account_id',
    'company' => 'company_name',
    'created' => 'created_at',
];

/** K-3-1 の作業者欄は5つ（概要書の様式に合わせる） */
const ADMIN_WORKER_SLOTS = 5;

function admin_users(): void
{
    Auth::requireAdmin();

    [$sort, $dir] = admin_sort(ADMIN_USER_SORTS, 'no');
    $total = (int) Database::value('SELECT COUNT(*) FROM accounts');
    $pager = admin_pager($total);
    $column = ADMIN_USER_SORTS[$sort];

    $accounts = Database::all(
        "SELECT * FROM accounts
          ORDER BY {$column} {$dir}, id {$dir}
          LIMIT " . ADMIN_PER_PAGE . " OFFSET {$pager['offset']}"
    );

    // 一覧に出す作業者1〜5
    foreach ($accounts as $i => $row) {
        $accounts[$i]['workers'] = array_column(Database::all(
            'SELECT name FROM workers WHERE account_id = ? AND deleted_at IS NULL
              ORDER BY id LIMIT ' . ADMIN_WORKER_SLOTS,
            [$row['id']]
        ), 'name');
        $accounts[$i]['worker_total'] = (int) Database::value(
            'SELECT COUNT(*) FROM workers WHERE account_id = ? AND deleted_at IS NULL', [$row['id']]
        );
    }

    view('admin/users', [
        'accounts' => $accounts,
        'pager'    => $pager,
        'sort'     => $sort,
        'dir'      => $dir,
        'dialog'   => admin_user_dialog(),
        'title'    => 'ユーザー登録',
        'nav'      => 'users',
    ], 'layout_admin');
}

/**
 * ダイアログに出す内容を用意する。
 * ?new=1 で追加、?edit=<id> で修正。どちらでもなければ出さない。
 */
function admin_user_dialog(): ?array
{
    $flash = $_SESSION['admin_user_form'] ?? null;
    unset($_SESSION['admin_user_form']);

    if (query('new') === '1') {
        return $flash ?? [
            'mode'      => 'new',
            'id'        => 0,
            'account_id' => admin_suggest_login_id(),
            'company'   => '',
            'email'     => '',
            'workers'   => array_fill(0, ADMIN_WORKER_SLOTS, ''),
            'is_locked' => 0,
            'extra'     => 0,
            'errors'    => [],
        ];
    }

    $id = (int) query('edit', 0);
    if ($id <= 0) {
        return null;
    }
    if ($flash && (int) $flash['id'] === $id) {
        return $flash;
    }

    $account = Database::one('SELECT * FROM accounts WHERE id = ?', [$id]);
    if (!$account) {
        return null;
    }

    $names = array_column(Database::all(
        'SELECT name FROM workers WHERE account_id = ? AND deleted_at IS NULL
          ORDER BY id LIMIT ' . ADMIN_WORKER_SLOTS,
        [$id]
    ), 'name');

    return [
        'mode'       => 'edit',
        'id'         => $id,
        'account_id' => $account['account_id'],
        'company'    => (string) $account['company_name'],
        'email'      => (string) $account['email'],
        'workers'    => array_pad($names, ADMIN_WORKER_SLOTS, ''),
        'is_locked'  => (int) $account['is_locked'],
        'extra'      => max(0, (int) Database::value(
            'SELECT COUNT(*) FROM workers WHERE account_id = ? AND deleted_at IS NULL', [$id]
        ) - ADMIN_WORKER_SLOTS),
        'errors'     => [],
    ];
}

/** 次のアカウントIDの候補。事務局が手で決めてもよい */
function admin_suggest_login_id(): string
{
    $last = (string) Database::value(
        "SELECT account_id FROM accounts WHERE account_id LIKE 'ABCDE%' ORDER BY account_id DESC LIMIT 1"
    );
    if ($last !== '' && preg_match('/^([A-Z]+)(\d+)$/', $last, $m)) {
        return $m[1] . str_pad((string) ((int) $m[2] + 1), strlen($m[2]), '0', STR_PAD_LEFT);
    }
    return 'ABCDE0001';
}

// ---------------------------------------------------------------- 登録・修正

function admin_users_save(): void
{
    Auth::requireAdmin();
    csrf_check();

    $id      = (int) post('id', 0);
    $isNew   = $id === 0;
    $form = [
        'mode'       => $isNew ? 'new' : 'edit',
        'id'         => $id,
        'account_id' => trim((string) post('account_id', '')),
        'company'    => trim((string) post('company_name', '')),
        'email'      => trim((string) post('email', '')),
        'workers'    => array_map(
            fn($v) => trim((string) $v),
            array_pad((array) post('workers', []), ADMIN_WORKER_SLOTS, '')
        ),
        'is_locked'  => (int) post('is_locked', 0),
        'extra'      => (int) post('extra', 0),
        'errors'     => [],
    ];
    $password = (string) post('password', '');

    // ---- 確認 ----
    $errors = [];
    if ($form['account_id'] === '') {
        $errors['account_id'] = 'アカウントIDを入れてください。';
    } elseif (!preg_match('/^[0-9A-Za-z_-]{4,64}$/', $form['account_id'])) {
        $errors['account_id'] = 'アカウントIDは半角英数字（4文字以上）で入れてください。';
    } else {
        $dup = Database::one(
            'SELECT id FROM accounts WHERE account_id = ? AND id <> ?',
            [$form['account_id'], $id]
        );
        if ($dup) {
            $errors['account_id'] = 'このアカウントIDはすでに使われています。';
        }
    }

    if ($form['company'] === '') {
        $errors['company_name'] = '会社名を入れてください。';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'メールアドレスの形式が正しくありません。';
    }

    if ($isNew && $password === '') {
        $errors['password'] = '初回のパスワードを入れてください。';
    }
    if ($password !== '' && !preg_match('/^[0-9A-Za-z]{8,64}$/', $password)) {
        $errors['password'] = 'パスワードは半角英数字のみ、8文字以上で入れてください。';
    }

    if ($errors) {
        $form['errors'] = $errors;
        $_SESSION['admin_user_form'] = $form;
        redirect('/admin/users?' . ($isNew ? 'new=1' : 'edit=' . $id));
    }

    // ---- 保存 ----
    Database::transaction(function () use ($form, $password, $isNew, &$id) {
        $data = [
            'account_id'   => $form['account_id'],
            'company_name' => mb_substr($form['company'], 0, 255),
            'email'        => mb_substr($form['email'], 0, 255),
            'updated_at'   => now(),
        ];
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            // パスワードを出し直したらロックも解ける
            $data['is_locked']    = 0;
            $data['failed_count'] = 0;
            $data['locked_at']    = null;
        }

        if ($isNew) {
            $data['created_at'] = now();
            $id = Database::insert('accounts', $data);
        } else {
            Database::update('accounts', $data, 'id = :id', ['id' => $id]);
        }

        admin_sync_workers($id, $form['workers']);
    });

    audit($isNew ? 'admin_account_created' : 'admin_account_updated', 'accounts:' . $form['account_id']);
    redirect('/admin/users');
}

/**
 * 作業者1〜5を合わせる。
 * 空欄にしたら過去の報告書のために消さずに隠す（利用者側 5-2 と同じ扱い）。
 */
function admin_sync_workers(int $accountId, array $names): void
{
    $existing = Database::all(
        'SELECT id, name FROM workers WHERE account_id = ? AND deleted_at IS NULL
          ORDER BY id LIMIT ' . ADMIN_WORKER_SLOTS,
        [$accountId]
    );

    foreach ($names as $i => $name) {
        $name = mb_substr($name, 0, 128);
        $row  = $existing[$i] ?? null;

        if ($row && $name === '') {
            Database::run(
                'UPDATE workers SET deleted_at = ?, updated_at = ? WHERE id = ?',
                [now(), now(), $row['id']]
            );
            continue;
        }
        if ($row && $name !== $row['name']) {
            Database::update('workers', ['name' => $name, 'updated_at' => now()],
                'id = :id', ['id' => $row['id']]);
            continue;
        }
        if (!$row && $name !== '') {
            Database::insert('workers', [
                'account_id' => $accountId,
                'name'       => $name,
                'kana'       => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

/** 1-1「3回エラーでパスワードロック。解除は事務局で行う」の解除 */
function admin_users_unlock(): void
{
    Auth::requireAdmin();
    csrf_check();

    $id      = (int) post('id', 0);
    $account = Database::one('SELECT * FROM accounts WHERE id = ?', [$id]);
    if (!$account) {
        render_error(404, 'アカウントが見つかりません。');
        exit;
    }

    Database::update('accounts', [
        'is_locked'    => 0,
        'failed_count' => 0,
        'locked_at'    => null,
        'updated_at'   => now(),
    ], 'id = :id', ['id' => $id]);

    audit('admin_account_unlocked', 'accounts:' . $account['account_id']);
    redirect('/admin/users');
}
