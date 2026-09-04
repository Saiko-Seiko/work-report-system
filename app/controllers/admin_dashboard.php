<?php
/**
 * K-2 管理者ダッシュボード
 *
 * 設備会社（事務局）が、協力会社から出てきた報告書をまとめて見る画面。
 * 概要書5の ⑦「ステータス完了の報告書を設備会社が内容を確認する」がここ。
 */
declare(strict_types=1);

require_once APP_ROOT . '/app/controllers/admin_common.php';

const ADMIN_REPORT_SORTS = [
    'no'       => 'r.report_no',
    'work'     => 'r.work_date',
    'created'  => 'r.created_date',
    'hospital' => 'r.hospital_name',
    'company'  => 'a.company_name',
    'status'   => 'r.completed_at',
];

function admin_dashboard(): void
{
    Auth::requireAdmin();

    $q = trim((string) query('q', ''));
    [$sort, $dir] = admin_sort(ADMIN_REPORT_SORTS, 'no');

    // 病院名・作業件名・作業者・会社名・No. を横断して探す
    $where  = 'r.deleted_at IS NULL';
    $params = [];
    if ($q !== '') {
        $where .= ' AND (r.hospital_name LIKE ? OR r.work_title LIKE ? OR r.workers_text LIKE ?
                         OR a.company_name LIKE ? OR r.work_place LIKE ? OR r.report_no = ?)';
        $like   = '%' . $q . '%';
        $params = [$like, $like, $like, $like, $like, ctype_digit($q) ? (int) $q : -1];
    }

    $total = (int) Database::value(
        "SELECT COUNT(*) FROM reports r JOIN accounts a ON a.id = r.account_id WHERE {$where}",
        $params
    );
    $pager  = admin_pager($total);
    $column = ADMIN_REPORT_SORTS[$sort];

    $rows = Database::all(
        "SELECT r.*, a.company_name, a.account_id AS login_id,
                i.pdf_at AS internal_pdf_at, i.completed_at AS internal_completed_at
           FROM reports r
           JOIN accounts a ON a.id = r.account_id
           LEFT JOIN internal_reports i ON i.report_id = r.id
          WHERE {$where}
          ORDER BY {$column} {$dir}, r.id {$dir}
          LIMIT " . ADMIN_PER_PAGE . " OFFSET {$pager['offset']}",
        $params
    );

    view('admin/dashboard', [
        'rows'  => $rows,
        'pager' => $pager,
        'q'     => $q,
        'sort'  => $sort,
        'dir'   => $dir,
        'stats' => [
            '報告書'       => (int) Database::value('SELECT COUNT(*) FROM reports WHERE deleted_at IS NULL'),
            '完了（請求済）' => (int) Database::value('SELECT COUNT(*) FROM reports WHERE completed_at IS NOT NULL'),
            '協力会社'     => (int) Database::value('SELECT COUNT(*) FROM accounts'),
            'ロック中'     => (int) Database::value('SELECT COUNT(*) FROM accounts WHERE is_locked = 1'),
        ],
        'title' => 'ダッシュボード',
        'nav'   => 'dashboard',
    ], 'layout_admin');
}

// ================================================================ 報告書の表示

/** 客先提出用のA4（K-2 の「PDF ●」） */
function admin_report_sheet(array $p): void
{
    Auth::requireAdmin();
    $report = admin_find_report((int) $p['id']);

    $data = Report::sheetData($report);

    view('sheet/report', $data + [
        'density'      => Report::sheetDensity($data),
        'signatureUrl' => '/admin/report/' . (int) $report['id'] . '/signature.png',
        'forPrint'     => query('print') === '1',
    ]);
}

/** 社内用のA4（K-2 の「社内用 ●」） */
function admin_internal_sheet(array $p): void
{
    Auth::requireAdmin();
    $report   = admin_find_report((int) $p['id']);
    $internal = Database::one('SELECT * FROM internal_reports WHERE report_id = ?', [$report['id']]);

    if (!$internal) {
        render_error(404, 'この報告書の社内用はまだ作成されていません。');
        exit;
    }

    view('sheet/internal', InternalReport::sheetData($internal) + [
        'report'   => $report,
        'forPrint' => query('print') === '1',
    ]);
}

/** サイン画像。管理者もログイン確認を通してから配信する */
function admin_signature_image(array $p): void
{
    Auth::requireAdmin();
    $report = admin_find_report((int) $p['id']);

    $path = config('storage.signatures') . '/' . (string) $report['signature_file'];
    if (!$report['signature_file'] || !is_file($path)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=600');
    readfile($path);
    exit;
}
