<?php
/**
 * 4-1〜4-8 社内用報告書
 *
 * 客先提出用（2-1〜2-10）と同じ作りにしてある。
 *   「つぎへ」「もどる」で必ず保存 → リダイレクトしてから表示
 *   4-6 の「完了」でステータスを完了（請求済）にし、一覧へ戻る
 */
declare(strict_types=1);

// 入力の共通処理（report_action / report_is_date / report_clamp_int）を借りる
require_once APP_ROOT . '/app/controllers/report.php';

const INTERNAL_FLOW = ['basic', 'remain', 'parts', 'hours', 'sales'];

function internal_url(int $reportId, string $step): string
{
    return "/report/{$reportId}/internal/{$step}";
}

/** 報告書と社内用をまとめて取り出す。無ければ提出済みの内容から作る */
function internal_load(array $params): array
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $params['id'], $user);

    return [$user, $report, InternalReport::findOrCreate($report)];
}

function internal_move(int $reportId, string $current, int $dir): void
{
    $i    = (int) array_search($current, INTERNAL_FLOW, true);
    $next = INTERNAL_FLOW[$i + $dir] ?? null;

    if ($next === null) {
        redirect($dir > 0
            ? "/report/{$reportId}/internal/confirm"
            : "/report/{$reportId}/done");
    }
    redirect(internal_url($reportId, $next));
}

/** オフライン層に行き先を渡す（客先提出用と同じ仕組み） */
function internal_form_attrs(array $report, string $step): string
{
    $id   = (int) $report['id'];
    $flow = INTERNAL_FLOW;
    $i    = (int) array_search($step, $flow, true);

    $next = isset($flow[$i + 1])
        ? internal_url($id, $flow[$i + 1])
        : "/report/{$id}/internal/confirm";
    $back = $i > 0 ? internal_url($id, $flow[$i - 1]) : "/report/{$id}/done";

    return sprintf(
        'data-offline data-report="%d" data-step="internal-%s" data-next-url="%s" data-back-url="%s"',
        $id,
        h($step),
        h($next),
        h($back)
    );
}

/** 入口。完了画面や一覧の「社内用」から来る */
function internal_entry(array $p): void
{
    [, $report] = internal_load($p);
    redirect(internal_url((int) $report['id'], 'basic'));
}

// ================================================================ 4-1 基本情報

function internal_basic(array $p): void
{
    [, $report, $internal] = internal_load($p);
    $id = (int) $report['id'];

    $form = [
        'created_date'  => (string) $internal['created_date'],
        'hospital_name' => (string) $internal['hospital_name'],
        'work_date'     => (string) $internal['work_date'],
        'work_place'    => (string) $internal['work_place'],
        'workers_text'  => (string) $internal['workers_text'],
        'work_title'    => (string) $internal['work_title'],
    ];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = report_action();

        foreach (array_keys($form) as $key) {
            $form[$key] = trim((string) post($key, ''));
        }

        if ($action === 'next') {
            // 概要書 4-1「全て必須項目」
            $labels = [
                'created_date'  => '作成日',
                'hospital_name' => '病院名',
                'work_date'     => '作業日',
                'work_place'    => '作業場所',
                'workers_text'  => '作業者',
                'work_title'    => '作業件名',
            ];
            foreach ($labels as $key => $label) {
                if ($form[$key] === '') {
                    $errors[$key] = "{$label}を入れてください。";
                }
            }
            foreach (['created_date', 'work_date'] as $key) {
                if ($form[$key] !== '' && !report_is_date($form[$key])) {
                    $errors[$key] = '日付の形式が正しくありません。';
                }
            }
        }

        if (!$errors) {
            InternalReport::touch((int) $internal['id'], [
                'created_date'  => $form['created_date'] ?: null,
                'hospital_name' => mb_substr($form['hospital_name'], 0, 255),
                'work_date'     => $form['work_date'] ?: null,
                'work_place'    => mb_substr($form['work_place'], 0, 255),
                'workers_text'  => mb_substr($form['workers_text'], 0, 255),
                'work_title'    => mb_substr($form['work_title'], 0, 255),
            ]);
            internal_move($id, 'basic', $action === 'back' ? -1 : 1);
        }
    }

    internal_view('user/internal_basic', $report, $internal, 'basic', [
        'form'   => $form,
        'errors' => $errors,
        'title'  => '社内用 基本情報登録',
    ]);
}

// ================================================================ 4-2 残作業

function internal_remain(array $p): void
{
    [, $report, $internal] = internal_load($p);
    $id = (int) $report['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        InternalReport::touch((int) $internal['id'], [
            'remaining_work' => mb_substr(trim((string) post('remaining_work', '')), 0, 4000),
        ]);
        internal_move($id, 'remain', report_action() === 'back' ? -1 : 1);
    }

    internal_view('user/internal_remain', $report, $internal, 'remain', [
        'title' => '社内用 今回作業時の残作業',
    ]);
}

// ================================================================ 4-3 再手配の必要な部材

function internal_parts(array $p): void
{
    [, $report, $internal] = internal_load($p);
    $id         = (int) $report['id'];
    $internalId = (int) $internal['id'];

    $q = trim((string) (post('q') ?? query('q', '')));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = report_action();

        internal_save_parts($internalId, (array) post('qty', []));

        if ($action === 'search') {
            redirect(internal_url($id, 'parts') . '?' . http_build_query(['q' => $q]));
        }
        internal_move($id, 'parts', $action === 'back' ? -1 : 1);
    }

    // 選んだ部材（数量あり）は常に上に固定する
    $selected = Database::all(
        'SELECT ip.*, p.kana, p.priority
           FROM internal_report_parts ip
           LEFT JOIN parts p ON p.id = ip.part_id
          WHERE ip.internal_report_id = ? AND ip.qty > 0
          ORDER BY p.priority DESC, p.kana, ip.id',
        [$internalId]
    );
    $selectedIds = array_map('intval', array_filter(array_column($selected, 'part_id')));

    $params = [];
    $where  = 'deleted_at IS NULL';
    if ($q !== '') {
        $where .= ' AND (name LIKE ? OR kana LIKE ?)';
        $params = ['%' . $q . '%', '%' . $q . '%'];
    }
    $total   = (int) Database::value("SELECT COUNT(*) FROM parts WHERE {$where}", $params);
    $results = Database::all(
        "SELECT id, name, kana, unit FROM parts WHERE {$where}
          ORDER BY priority DESC, kana, name LIMIT 20",
        $params
    );

    internal_view('user/internal_parts', $report, $internal, 'parts', [
        'selected'    => $selected,
        'selectedIds' => $selectedIds,
        'results'     => $results,
        'total'       => $total,
        'q'           => $q,
        'title'       => '社内用 再手配の必要な部材',
    ]);
}

function internal_save_parts(int $internalId, array $qty): void
{
    $ids = array_values(array_filter(array_map('intval', array_keys($qty)), fn($i) => $i > 0));
    if (!$ids) {
        return;
    }

    $ph    = implode(',', array_fill(0, count($ids), '?'));
    $parts = Database::all("SELECT id, name, unit FROM parts WHERE id IN ($ph)", $ids);

    foreach ($parts as $part) {
        $partId = (int) $part['id'];
        $n      = report_clamp_int($qty[$partId] ?? 0, 0, 9999);
        $row    = Database::one(
            'SELECT id FROM internal_report_parts WHERE internal_report_id = ? AND part_id = ?',
            [$internalId, $partId]
        );

        if ($n === 0) {
            if ($row) {
                Database::run('DELETE FROM internal_report_parts WHERE id = ?', [$row['id']]);
            }
            continue;
        }
        if ($row) {
            Database::run('UPDATE internal_report_parts SET qty = ? WHERE id = ?', [$n, $row['id']]);
        } else {
            Database::insert('internal_report_parts', [
                'internal_report_id' => $internalId,
                'part_id'            => $partId,
                'part_name'          => $part['name'],
                'unit'               => $part['unit'],
                'qty'                => $n,
                'sort_order'         => 0,
            ]);
        }
    }
}

// ================================================================ 4-4 移動および作業時間

function internal_hours(array $p): void
{
    [, $report, $internal] = internal_load($p);
    $id     = (int) $report['id'];
    $errors = [];

    $fields = [];
    foreach (array_keys(InternalReport::SPANS) as $span) {
        $fields[] = $span . '_from';
        $fields[] = $span . '_to';
    }

    $form = [];
    foreach ($fields as $f) {
        $form[$f] = (string) $internal[$f];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = report_action();

        $data = [];
        foreach ($fields as $f) {
            $value    = trim((string) post($f, ''));
            $form[$f] = $value;

            if (!InternalReport::isTime($value)) {
                if ($action === 'next') {
                    $errors[$f] = '時刻は 9:00 のように入れてください。';
                }
                $value = '';
            }
            $data[$f] = $value ?: null;
        }

        // 片方だけ入っている区間は、そのままだと時間が出せないので知らせる
        if ($action === 'next' && !$errors) {
            foreach (InternalReport::SPANS as $span => $meta) {
                $from = $form[$span . '_from'];
                $to   = $form[$span . '_to'];
                if (($from === '') !== ($to === '')) {
                    $errors[$span . '_to'] = $meta['label'] . 'は開始と終了の両方を入れてください。';
                }
            }
        }

        if (!$errors) {
            InternalReport::touch((int) $internal['id'], $data);
            internal_move($id, 'hours', $action === 'back' ? -1 : 1);
        }
    }

    internal_view('user/internal_hours', $report, $internal, 'hours', [
        'form'   => $form,
        'errors' => $errors,
        'title'  => '社内用 移動および作業時間',
    ]);
}

// ================================================================ 4-5 営業アプローチ・備考

function internal_sales(array $p): void
{
    [, $report, $internal] = internal_load($p);
    $id = (int) $report['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        InternalReport::touch((int) $internal['id'], [
            'sales_approach' => mb_substr(trim((string) post('sales_approach', '')), 0, 4000),
            'remarks'        => mb_substr(trim((string) post('remarks', '')), 0, 4000),
        ]);
        internal_move($id, 'sales', report_action() === 'back' ? -1 : 1);
    }

    internal_view('user/internal_sales', $report, $internal, 'sales', [
        'title' => '社内用 営業アプローチ・備考',
    ]);
}

// ================================================================ 4-6 PDF確認

function internal_confirm(array $p): void
{
    [, $report, $internal] = internal_load($p);

    view('user/internal_confirm', [
        'report'    => $report,
        'internal'  => $internal,
        'askDone'   => query('complete') === '1',
        'title'     => '社内用報告書 PDF確認',
    ], 'layout_user');
}

/**
 * 「完了」。概要書 5 の ⑥「ステータスを完了（請求済）にする」がここ。
 * 一覧の「状態」が「完」になる。
 */
function internal_complete(array $p): void
{
    [, $report, $internal] = internal_load($p);
    csrf_check();

    Database::transaction(function () use ($report, $internal) {
        InternalReport::touch((int) $internal['id'], [
            'completed_at' => now(),
            'pdf_at'       => $internal['pdf_at'] ?: now(),
        ]);
        Report::touch((int) $report['id'], [
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    });

    audit('internal_completed', 'reports:' . $report['id']);
    redirect('/reports');
}

// ================================================================ 4-7 / 4-8 用紙

function internal_sheet(array $p): void
{
    [, $report, $internal] = internal_load($p);

    // 一覧の「社内用 ●」は、社内用の紙が起こされたかどうかで決まる
    if (!$internal['pdf_at']) {
        InternalReport::touch((int) $internal['id'], ['pdf_at' => now()]);
        $internal['pdf_at'] = now();
    }

    view('sheet/internal', InternalReport::sheetData($internal) + [
        'report'   => $report,
        'forPrint' => query('print') === '1',
    ]);
}

function internal_preview(array $p): void
{
    [, $report] = internal_load($p);

    view('user/internal_preview', [
        'report' => $report,
        'mode'   => 'preview',
        'title'  => '社内用 プレビュー',
    ]);
}

function internal_print(array $p): void
{
    [, $report] = internal_load($p);

    view('user/internal_preview', [
        'report' => $report,
        'mode'   => 'print',
        'title'  => '社内用 印刷',
    ]);
}

// ================================================================ 共通

/** ステップ表示に必要なものを足してから描く */
function internal_view(string $template, array $report, array $internal, string $step, array $data): void
{
    view($template, $data + [
        'report'   => $report,
        'internal' => $internal,
        'step'     => $step,
        'progress' => InternalReport::progress($internal),
        'showSync' => true,
    ], 'layout_user');
}
