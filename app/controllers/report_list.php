<?php
/**
 * 3 報告書一覧_画面
 *
 * ダッシュボードの「一覧表（コピーして作成、修正、プレビュー）」がここ。
 *   病院名をタップ → 基本情報登録_画面（修正）
 *   PDF ●         → プレビュー
 *   社内用 ●      → 社内用報告書（Phase 7）
 *   複製          → コピーして新しい下書きを作る
 */
declare(strict_types=1);

/** 並べ替えを許す列だけを持つ。ここに無い値が来たら既定に戻す */
const REPORT_LIST_SORTS = [
    'no'       => ['column' => 'r.report_no',     'label' => 'No.'],
    'work'     => ['column' => 'r.work_date',     'label' => '作業日'],
    'created'  => ['column' => 'r.created_date',  'label' => '作成日'],
    'hospital' => ['column' => 'r.hospital_name', 'label' => '病院名'],
];

const REPORT_LIST_PER_PAGE = 100;

function report_list(): void
{
    $user = Auth::requireUser();

    $sort = (string) query('sort', 'no');
    if (!isset(REPORT_LIST_SORTS[$sort])) {
        $sort = 'no';
    }
    $dir  = strtolower((string) query('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $page = max(1, (int) query('page', 1));

    $total = (int) Database::value(
        'SELECT COUNT(*) FROM reports WHERE account_id = ? AND deleted_at IS NULL',
        [$user['id']]
    );
    $pages  = max(1, (int) ceil($total / REPORT_LIST_PER_PAGE));
    $page   = min($page, $pages);
    $offset = ($page - 1) * REPORT_LIST_PER_PAGE;

    $column = REPORT_LIST_SORTS[$sort]['column'];

    $rows = Database::all(
        "SELECT r.*, i.pdf_at AS internal_pdf_at, i.completed_at AS internal_completed_at
           FROM reports r
           LEFT JOIN internal_reports i ON i.report_id = r.id
          WHERE r.account_id = ? AND r.deleted_at IS NULL
          ORDER BY {$column} {$dir}, r.id {$dir}
          LIMIT " . REPORT_LIST_PER_PAGE . " OFFSET {$offset}",
        [$user['id']]
    );

    view('user/report_list', [
        'rows'   => $rows,
        'total'  => $total,
        'page'   => $page,
        'pages'  => $pages,
        'from'   => $total === 0 ? 0 : $offset + 1,
        'to'     => min($offset + REPORT_LIST_PER_PAGE, $total),
        'sort'   => $sort,
        'dir'    => $dir,
        'title'  => '報告書一覧',
    ], 'layout_user');
}

/** 一覧の「複製」。写した下書きの基本情報画面へ送る */
function report_copy(): void
{
    $user = Auth::requireUser();
    csrf_check();

    $sourceId = (int) post('source_id', 0);
    $newId    = Report::copyFrom($user, $sourceId, (string) post('client_uuid', ''));

    redirect("/report/{$newId}/basic");
}
