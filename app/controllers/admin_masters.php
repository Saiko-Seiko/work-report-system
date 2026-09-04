<?php
/**
 * K-5 機種名マスター ／ K-6 報告事項マスター ／ K-7 管理者情報
 *
 * K-5 と K-6 は形がほぼ同じなので、ダイアログの作りを1つにまとめている。
 */
declare(strict_types=1);

require_once APP_ROOT . '/app/controllers/admin_common.php';
require_once APP_ROOT . '/app/controllers/admin_parts.php';   // お知らせ表示を借りる

// ================================================================ K-5 機種名マスター

const ADMIN_MODEL_SORTS = [
    'no'      => 'id',
    'name'    => 'name',
    'created' => 'created_at',
];

function admin_models(): void
{
    Auth::requireAdmin();

    [$sort, $dir] = admin_sort(ADMIN_MODEL_SORTS, 'no', 'ASC');
    $pager  = admin_pager((int) Database::value(
        'SELECT COUNT(*) FROM machine_models WHERE deleted_at IS NULL'));
    $column = ADMIN_MODEL_SORTS[$sort];

    view('admin/models', [
        'rows'   => Database::all(
            "SELECT * FROM machine_models WHERE deleted_at IS NULL
              ORDER BY {$column} {$dir}, id {$dir}
              LIMIT " . ADMIN_PER_PAGE . " OFFSET {$pager['offset']}"
        ),
        'pager'  => $pager,
        'sort'   => $sort,
        'dir'    => $dir,
        'dialog' => admin_simple_dialog('machine_models', 'admin_model_form', ['name', 'kana', 'sort_order']),
        'notice' => admin_take_notice(),
        'title'  => '機種名マスタ',
        'nav'    => 'models',
    ], 'layout_admin');
}

function admin_models_save(): void
{
    Auth::requireAdmin();
    csrf_check();

    $id    = (int) post('id', 0);
    $isNew = $id === 0;
    $form  = [
        'mode'       => $isNew ? 'new' : 'edit',
        'id'         => $id,
        'name'       => trim((string) post('name', '')),
        'kana'       => trim((string) post('kana', '')),
        'sort_order' => (int) post('sort_order', 0),
        'errors'     => [],
    ];

    $errors = [];
    if ($form['name'] === '') {
        $errors['name'] = '機種名を入れてください。';
    } else {
        $dup = Database::one(
            'SELECT id FROM machine_models WHERE name = ? AND id <> ? AND deleted_at IS NULL',
            [$form['name'], $id]
        );
        if ($dup) {
            $errors['name'] = 'この機種名はすでに登録されています。';
        }
    }

    if ($errors) {
        $form['errors'] = $errors;
        $_SESSION['admin_model_form'] = $form;
        redirect('/admin/models?' . ($isNew ? 'new=1' : 'edit=' . $id));
    }

    $data = [
        'name'       => mb_substr($form['name'], 0, 128),
        'kana'       => mb_substr($form['kana'], 0, 128),
        'sort_order' => max(0, min(99999, $form['sort_order'])),
        'updated_at' => now(),
    ];
    if ($isNew) {
        $data['created_at'] = now();
        Database::insert('machine_models', $data);
    } else {
        Database::update('machine_models', $data, 'id = :id', ['id' => $id]);
    }

    audit($isNew ? 'admin_model_created' : 'admin_model_updated', 'machine_models:' . $form['name']);
    redirect('/admin/models');
}

function admin_models_delete(): void
{
    Auth::requireAdmin();
    csrf_check();

    $id  = (int) post('id', 0);
    $row = Database::one('SELECT * FROM machine_models WHERE id = ?', [$id]);
    if ($row) {
        // 過去の報告書には機種名を写してあるので、隠すだけで記録は残る
        Database::update('machine_models', ['deleted_at' => now(), 'updated_at' => now()],
            'id = :id', ['id' => $id]);
        audit('admin_model_deleted', 'machine_models:' . $row['name']);
    }
    redirect('/admin/models');
}

// ================================================================ K-6 報告事項マスター

const ADMIN_TEXT_SORTS = [
    'no'      => 'id',
    'body'    => 'body',
    'created' => 'created_at',
];

function admin_texts(): void
{
    Auth::requireAdmin();

    [$sort, $dir] = admin_sort(ADMIN_TEXT_SORTS, 'no', 'ASC');

    // 事務局が管理するのは全社共通のもの（account_id が NULL）だけ
    $pager  = admin_pager((int) Database::value(
        'SELECT COUNT(*) FROM report_texts WHERE account_id IS NULL AND deleted_at IS NULL'));
    $column = ADMIN_TEXT_SORTS[$sort];

    view('admin/texts', [
        'rows'   => Database::all(
            "SELECT * FROM report_texts
              WHERE account_id IS NULL AND deleted_at IS NULL
              ORDER BY {$column} {$dir}, id {$dir}
              LIMIT " . ADMIN_PER_PAGE . " OFFSET {$pager['offset']}"
        ),
        'ownCount' => (int) Database::value(
            'SELECT COUNT(*) FROM report_texts WHERE account_id IS NOT NULL AND deleted_at IS NULL'),
        'pager'  => $pager,
        'sort'   => $sort,
        'dir'    => $dir,
        'dialog' => admin_simple_dialog('report_texts', 'admin_text_form', ['body', 'sort_order']),
        'notice' => admin_take_notice(),
        'title'  => '報告事項マスタ',
        'nav'    => 'texts',
    ], 'layout_admin');
}

function admin_texts_save(): void
{
    Auth::requireAdmin();
    csrf_check();

    $id    = (int) post('id', 0);
    $isNew = $id === 0;
    $form  = [
        'mode'       => $isNew ? 'new' : 'edit',
        'id'         => $id,
        'body'       => trim((string) post('body', '')),
        'sort_order' => (int) post('sort_order', 0),
        'errors'     => [],
    ];

    if ($form['body'] === '') {
        $form['errors'] = ['body' => '報告事項を入れてください。'];
        $_SESSION['admin_text_form'] = $form;
        redirect('/admin/texts?' . ($isNew ? 'new=1' : 'edit=' . $id));
    }

    $data = [
        'body'       => mb_substr($form['body'], 0, 1000),
        'sort_order' => max(0, min(99999, $form['sort_order'])),
        'updated_at' => now(),
    ];
    if ($isNew) {
        $data['account_id'] = null;
        $data['created_at'] = now();
        Database::insert('report_texts', $data);
    } else {
        Database::update('report_texts', $data,
            'id = :id AND account_id IS NULL', ['id' => $id]);
    }

    audit($isNew ? 'admin_text_created' : 'admin_text_updated', 'report_texts:' . $id);
    redirect('/admin/texts');
}

function admin_texts_delete(): void
{
    Auth::requireAdmin();
    csrf_check();

    $id = (int) post('id', 0);
    Database::run(
        'UPDATE report_texts SET deleted_at = ?, updated_at = ? WHERE id = ? AND account_id IS NULL',
        [now(), now(), $id]
    );
    audit('admin_text_deleted', 'report_texts:' . $id);
    redirect('/admin/texts');
}

// ================================================================ K-7 管理者情報

function admin_profile(): void
{
    Auth::requireAdmin();
    $admin = Auth::admin();

    $form = [
        'account_id' => (string) $admin['account_id'],
        'email'      => (string) $admin['notify_email'],
    ];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        $form = [
            'account_id' => trim((string) post('account_id', '')),
            'email'      => trim((string) post('notify_email', '')),
        ];
        $password = (string) post('password', '');
        $confirm  = (string) post('password_confirm', '');

        if ($form['account_id'] === '') {
            $errors['account_id'] = 'アカウントIDを入れてください。';
        } elseif (!preg_match('/^[0-9A-Za-z_-]{4,64}$/', $form['account_id'])) {
            $errors['account_id'] = 'アカウントIDは半角英数字（4文字以上）で入れてください。';
        } else {
            $dup = Database::one(
                'SELECT id FROM admins WHERE account_id = ? AND id <> ?',
                [$form['account_id'], $admin['id']]
            );
            if ($dup) {
                $errors['account_id'] = 'このアカウントIDはすでに使われています。';
            }
        }

        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['notify_email'] = 'メールアドレスの形式が正しくありません。';
        }

        if ($password !== '') {
            if (!preg_match('/^[0-9A-Za-z]{8,64}$/', $password)) {
                $errors['password'] = 'パスワードは半角英数字のみ、8文字以上で入れてください。';
            } elseif ($password !== $confirm) {
                $errors['password_confirm'] = '確認用のパスワードが一致しません。';
            }
        }

        if (!$errors) {
            $data = [
                'account_id'   => $form['account_id'],
                'notify_email' => mb_substr($form['email'], 0, 255),
                'updated_at'   => now(),
            ];
            if ($password !== '') {
                $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            Database::update('admins', $data, 'id = :id', ['id' => $admin['id']]);
            $_SESSION['admin_login'] = $form['account_id'];

            audit('admin_profile_updated', 'admins:' . $form['account_id']);
            admin_set_notice('管理者情報を更新しました。', 'info');
            redirect('/admin/dashboard');
        }
    }

    view('admin/profile', [
        'form'   => $form,
        'errors' => $errors,
        'title'  => '管理者情報',
        'nav'    => '',
    ], 'layout_admin');
}

// ================================================================ 共通

/**
 * K-5 / K-6 のダイアログ。?new=1 で追加、?edit=<id> で修正。
 * 入力し直しのときはセッションに預けた値を優先する。
 */
function admin_simple_dialog(string $table, string $flashKey, array $fields): ?array
{
    $flash = $_SESSION[$flashKey] ?? null;
    unset($_SESSION[$flashKey]);

    if (query('new') === '1') {
        if ($flash) {
            return $flash;
        }
        $blank = ['mode' => 'new', 'id' => 0, 'errors' => []];
        foreach ($fields as $f) {
            $blank[$f] = $f === 'sort_order' ? 0 : '';
        }
        return $blank;
    }

    $id = (int) query('edit', 0);
    if ($id <= 0) {
        return null;
    }
    if ($flash && (int) $flash['id'] === $id) {
        return $flash;
    }

    $row = Database::one("SELECT * FROM {$table} WHERE id = ?", [$id]);
    if (!$row) {
        return null;
    }

    $dialog = ['mode' => 'edit', 'id' => $id, 'errors' => []];
    foreach ($fields as $f) {
        $dialog[$f] = $f === 'sort_order' ? (int) $row[$f] : (string) $row[$f];
    }
    return $dialog;
}
