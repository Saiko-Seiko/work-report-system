<?php
/**
 * K-8 確認事項マスタ
 *
 * 2-5 確認・署名画面の「☐ 確認事項」の文言を事務局が直せる画面。
 * 概要書では確認事項の 3〜5 が未定だったため、後から増やしたり文言を変えたりできるようにしている。
 *
 * 過去の報告書は checklist_items.id をカンマ区切りで持っている（reports.checked_ids）ので、
 * 行は消さず「使わない」に切り替えるだけにする。これなら昔の報告書の記録が崩れない。
 */
declare(strict_types=1);

require_once APP_ROOT . '/app/controllers/admin_common.php';
require_once APP_ROOT . '/app/controllers/admin_parts.php';     // お知らせ表示を借りる
require_once APP_ROOT . '/app/controllers/admin_masters.php';   // ダイアログの作りを借りる

const ADMIN_CHECK_SORTS = [
    'no'      => 'id',
    'label'   => 'label',
    'order'   => 'sort_order',
    'active'  => 'is_active',
    'created' => 'created_at',
];

function admin_checks(): void
{
    Auth::requireAdmin();

    [$sort, $dir] = admin_sort(ADMIN_CHECK_SORTS, 'order', 'ASC');
    $pager  = admin_pager((int) Database::value('SELECT COUNT(*) FROM checklist_items'));
    $column = ADMIN_CHECK_SORTS[$sort];

    view('admin/checks', [
        'rows'   => Database::all(
            "SELECT * FROM checklist_items
              ORDER BY {$column} {$dir}, id {$dir}
              LIMIT " . ADMIN_PER_PAGE . " OFFSET {$pager['offset']}"
        ),
        'activeCount' => (int) Database::value('SELECT COUNT(*) FROM checklist_items WHERE is_active = 1'),
        'pager'  => $pager,
        'sort'   => $sort,
        'dir'    => $dir,
        'dialog' => admin_check_dialog(),
        'notice' => admin_take_notice(),
        'title'  => '確認事項マスタ',
        'nav'    => 'checks',
    ], 'layout_admin');
}

function admin_check_dialog(): ?array
{
    $dialog = admin_simple_dialog('checklist_items', 'admin_check_form', ['label', 'sort_order', 'is_active']);
    if ($dialog && $dialog['mode'] === 'new' && $dialog['is_active'] === '') {
        $dialog['is_active'] = 1;   // 追加するものは最初から使う
    }
    return $dialog;
}

function admin_checks_save(): void
{
    Auth::requireAdmin();
    csrf_check();

    $id    = (int) post('id', 0);
    $isNew = $id === 0;
    $form  = [
        'mode'       => $isNew ? 'new' : 'edit',
        'id'         => $id,
        'label'      => trim((string) post('label', '')),
        'sort_order' => (int) post('sort_order', 0),
        'is_active'  => post('is_active') === '1' ? 1 : 0,
        'errors'     => [],
    ];

    $errors = [];
    if ($form['label'] === '') {
        $errors['label'] = '確認事項の文言を入れてください。';
    } elseif (mb_strlen($form['label']) > 255) {
        $errors['label'] = '確認事項は255文字までです。';
    } else {
        $dup = Database::one(
            'SELECT id FROM checklist_items WHERE label = ? AND id <> ?', [$form['label'], $id]
        );
        if ($dup) {
            $errors['label'] = '同じ文言の確認事項がすでにあります（No.' . (int) $dup['id'] . '）。';
        }
    }

    if ($errors) {
        $form['errors'] = $errors;
        $_SESSION['admin_check_form'] = $form;
        redirect('/admin/checks?' . ($isNew ? 'new=1' : 'edit=' . $id));
    }

    $data = [
        'label'      => $form['label'],
        'sort_order' => max(0, min(99999, $form['sort_order'])),
        'is_active'  => $form['is_active'],
        'updated_at' => now(),
    ];
    if ($isNew) {
        $data['created_at'] = now();
        Database::insert('checklist_items', $data);
    } else {
        Database::update('checklist_items', $data, 'id = :id', ['id' => $id]);
    }

    audit($isNew ? 'admin_check_created' : 'admin_check_updated', 'checklist_items:' . $id, $form['label']);
    admin_set_notice($isNew ? '確認事項を追加しました。' : '確認事項を更新しました。', 'info');
    redirect('/admin/checks');
}

/** 「削除」は使わない状態にするだけ。過去の報告書のチェック記録を保つため */
function admin_checks_delete(): void
{
    Auth::requireAdmin();
    csrf_check();

    $id  = (int) post('id', 0);
    $row = Database::one('SELECT * FROM checklist_items WHERE id = ?', [$id]);
    if ($row) {
        Database::update('checklist_items', ['is_active' => 0, 'updated_at' => now()],
            'id = :id', ['id' => $id]);
        audit('admin_check_disabled', 'checklist_items:' . $id, (string) $row['label']);
        admin_set_notice('「' . $row['label'] . '」を使わない設定にしました（過去の報告書の記録は残ります）。', 'info');
    }
    redirect('/admin/checks');
}
