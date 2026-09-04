<?php
/**
 * 2-1〜2-6 報告書作成ウィザード
 *
 * 全画面に共通のきまり
 *   - 「つぎへ」「もどる」「検索」など、画面を離れる操作は必ず先に保存する。
 *     概要書「画面の入力情報は…前画面等に移動しても入力情報が無くならいようにする」への対応。
 *   - 保存に成功したらリダイレクトしてから表示する（戻るボタンで二重送信にならない）。
 *   - 入力エラーのときだけ、その場で打った値のまま描き直す。
 */
declare(strict_types=1);

/** ウィザードの並び。もどる／つぎへの行き先を1か所で決める */
const REPORT_FLOW = ['basic', 'work', 'parts', 'measure', 'confirm'];

function report_step_url(int $id, string $step): string
{
    return "/report/{$id}/{$step}";
}

/** $dir = 1 で次、-1 で前。端に来たらダッシュボード／完了画面へ */
function report_move(int $id, string $current, int $dir): void
{
    $i = array_search($current, REPORT_FLOW, true);
    $next = REPORT_FLOW[$i + $dir] ?? null;

    if ($next === null) {
        redirect($dir > 0 ? "/report/{$id}/done" : '/dashboard');
    }
    redirect(report_step_url($id, $next));
}

/**
 * オフライン層（offline.js）に、この画面の行き先を教えるための属性。
 * 「つぎへ」「もどる」の遷移先をサーバー側で決めているので、
 * 同じ判断をJavaScript側に書き写す必要がない。
 */
function report_form_attrs(array $report, string $step): string
{
    $id   = (int) $report['id'];
    $flow = REPORT_FLOW;

    if ($step === 'sign') {
        $next = $back = report_step_url($id, 'confirm');
    } else {
        $i    = (int) array_search($step, $flow, true);
        $next = isset($flow[$i + 1]) ? report_step_url($id, $flow[$i + 1]) : "/report/{$id}/done";
        $back = $i > 0 ? report_step_url($id, $flow[$i - 1]) : '/dashboard';
    }

    return sprintf(
        'data-offline data-report="%d" data-step="%s" data-next-url="%s" data-back-url="%s"',
        $id,
        h($step),
        h($next),
        h($back)
    );
}

/** POST のボタン名から、どこへ行きたいのかを読む */
function report_action(): string
{
    foreach (['next', 'back', 'search', 'sort', 'add_row', 'add_model', 'insert_texts'] as $a) {
        if (post($a) !== null) {
            return $a;
        }
    }
    if (post('delete_id') !== null) {
        return 'delete';
    }
    return 'save';
}

// ================================================================ 新規作成

function report_new(): void
{
    $user = Auth::requireUser();

    // client_uuid はタブレット側が発行する。同じ値で二度届いても1件にまとめる
    $uuid = (string) (post('client_uuid') ?? query('uuid') ?? '');
    $id   = Report::createDraft($user, $uuid);

    redirect(report_step_url($id, 'basic'));
}

// ================================================================ 2-1 基本情報

function report_basic(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);
    $id     = (int) $report['id'];

    $workers = Report::loadWorkers($id);
    $form = [
        'created_date' => (string) $report['created_date'],
        'hospital_name' => (string) $report['hospital_name'],
        'work_date'    => (string) $report['work_date'],
        'work_place'   => (string) $report['work_place'],
        'work_title'   => (string) $report['work_title'],
        'worker_ids'   => $workers['ids'],
        'worker_free'  => $workers['free'],
    ];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = report_action();

        $form = [
            'created_date'  => trim((string) post('created_date', '')),
            'hospital_name' => trim((string) post('hospital_name', '')),
            'work_date'     => trim((string) post('work_date', '')),
            'work_place'    => trim((string) post('work_place', '')),
            'work_title'    => trim((string) post('work_title', '')),
            'worker_ids'    => array_map('intval', (array) post('worker_ids', [])),
            'worker_free'   => trim((string) post('worker_free', '')),
        ];

        // 「つぎへ」のときだけ必須チェック。もどるは書きかけでも保存して戻す
        if ($action === 'next') {
            $errors = report_validate_basic($form);
        }

        if (!$errors) {
            $workersText = Report::saveWorkers($id, $form['worker_ids'], $form['worker_free']);
            Report::touch($id, [
                'created_date'  => $form['created_date'] ?: null,
                'hospital_name' => mb_substr($form['hospital_name'], 0, 255),
                'work_date'     => $form['work_date'] ?: null,
                'work_place'    => mb_substr($form['work_place'], 0, 255),
                'work_title'    => mb_substr($form['work_title'], 0, 255),
                'workers_text'  => mb_substr($workersText, 0, 255),
            ]);
            report_move($id, 'basic', $action === 'back' ? -1 : 1);
        }
    }

    view('user/report_basic', [
        'report'  => $report,
        'form'    => $form,
        'errors'  => $errors,
        'workers' => Database::all(
            'SELECT id, name FROM workers WHERE account_id = ? AND deleted_at IS NULL ORDER BY kana, id',
            [$user['id']]
        ),
        'step'     => 'basic',
        'progress' => Report::progress($report),
        'title'    => '基本情報登録',
        'showSync' => true,
    ], 'layout_user');
}

function report_validate_basic(array $f): array
{
    $e = [];
    // 概要書 2-1「全て必須項目」
    if ($f['created_date'] === '') {
        $e['created_date'] = '作成日を入れてください。';
    } elseif (!report_is_date($f['created_date'])) {
        $e['created_date'] = '日付の形式が正しくありません。';
    }

    if ($f['hospital_name'] === '') {
        $e['hospital_name'] = '病院名を入れてください。';
    }

    if ($f['work_date'] === '') {
        $e['work_date'] = '作業日を入れてください。';
    } elseif (!report_is_date($f['work_date'])) {
        $e['work_date'] = '日付の形式が正しくありません。';
    }

    if ($f['work_place'] === '') {
        $e['work_place'] = '作業場所を入れてください。';
    }
    if (!$f['worker_ids'] && $f['worker_free'] === '') {
        $e['workers'] = '作業者を選ぶか、直接入力してください。';
    }
    if ($f['work_title'] === '') {
        $e['work_title'] = '作業件名を入れてください。';
    }
    return $e;
}

function report_is_date(string $v): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d !== false && $d->format('Y-m-d') === $v;
}

// ================================================================ 2-2 作業内容

function report_work(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);
    $id     = (int) $report['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = report_action();

        // 台数と任意入力を保存
        foreach ((array) post('qty', []) as $rowId => $qty) {
            Database::run(
                'UPDATE report_models SET qty = ? WHERE id = ? AND report_id = ?',
                [report_clamp_int($qty, 0, 999), (int) $rowId, $id]
            );
        }
        Report::touch($id, ['work_note' => mb_substr(trim((string) post('work_note', '')), 0, 2000)]);

        if ($action === 'delete') {
            // 概要書「対象外は削除する」。この報告書の一覧から外すだけで、機種名マスタは触らない
            Database::run(
                'DELETE FROM report_models WHERE id = ? AND report_id = ?',
                [(int) post('delete_id'), $id]
            );
            redirect(report_step_url($id, 'work'));
        }
        if ($action === 'add_model') {
            report_add_model($id, (int) post('add_model'));
            redirect(report_step_url($id, 'work'));
        }

        report_move($id, 'work', $action === 'back' ? -1 : 1);
    }

    $rows = Database::all(
        'SELECT * FROM report_models WHERE report_id = ? ORDER BY sort_order, id', [$id]
    );
    $usedIds = array_filter(array_column($rows, 'model_id'));

    view('user/report_work', [
        'report'    => $report,
        'rows'      => $rows,
        'available' => Database::all(
            'SELECT id, name FROM machine_models WHERE deleted_at IS NULL ORDER BY sort_order, id'
        ),
        'usedIds'   => array_map('intval', $usedIds),
        'step'      => 'work',
        'progress'  => Report::progress($report),
        'title'     => '作業内容登録',
        'showSync'  => true,
    ], 'layout_user');
}

/** 一度削除した機種を戻す */
function report_add_model(int $reportId, int $modelId): void
{
    $model = Database::one(
        'SELECT id, name FROM machine_models WHERE id = ? AND deleted_at IS NULL', [$modelId]
    );
    if (!$model) {
        return;
    }
    $dup = Database::value(
        'SELECT id FROM report_models WHERE report_id = ? AND model_id = ?', [$reportId, $modelId]
    );
    if ($dup) {
        return;
    }
    $max = (int) Database::value(
        'SELECT COALESCE(MAX(sort_order), 0) FROM report_models WHERE report_id = ?', [$reportId]
    );
    Database::insert('report_models', [
        'report_id'  => $reportId,
        'model_id'   => (int) $model['id'],
        'model_name' => $model['name'],
        'qty'        => 0,
        'sort_order' => $max + 10,
    ]);
}

// ================================================================ 2-3 交換部品

function report_parts(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);
    $id     = (int) $report['id'];

    $q    = trim((string) (post('q') ?? query('q', '')));
    $sort = (string) (post('sort_key') ?? query('sort', 'priority'));
    $sort = in_array($sort, ['priority', 'kana'], true) ? $sort : 'priority';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = report_action();

        report_save_part_quantities($id, (array) post('qty', []));
        Report::touch($id, ['parts_note' => mb_substr(trim((string) post('parts_note', '')), 0, 2000)]);

        if ($action === 'search' || $action === 'sort') {
            if ($action === 'sort') {
                // ソートボタンは50音順とよく使う順の切り替え
                $sort = $sort === 'kana' ? 'priority' : 'kana';
            }
            redirect(report_step_url($id, 'parts') . '?' . http_build_query(['q' => $q, 'sort' => $sort]));
        }

        report_move($id, 'parts', $action === 'back' ? -1 : 1);
    }

    // 選択済み（数量が入っているもの）は検索結果と別に、常に上に出す。
    // 検索し直すたびに選んだものが見えなくなるのを避ける
    $selected = Database::all(
        'SELECT rp.*, p.kana, p.priority
           FROM report_parts rp
           LEFT JOIN parts p ON p.id = rp.part_id
          WHERE rp.report_id = ? AND rp.qty > 0
          ORDER BY p.priority DESC, p.kana, rp.id',
        [$id]
    );
    $selectedIds = array_map('intval', array_filter(array_column($selected, 'part_id')));

    $limit  = 20;
    $params = [];
    $where  = 'deleted_at IS NULL';
    if ($q !== '') {
        $where .= ' AND (name LIKE ? OR kana LIKE ?)';
        $like   = '%' . $q . '%';
        $params = [$like, $like];
    }
    $order = $sort === 'kana' ? 'kana, name' : 'priority DESC, kana, name';

    $total   = (int) Database::value("SELECT COUNT(*) FROM parts WHERE {$where}", $params);
    $results = Database::all(
        "SELECT id, name, kana, unit FROM parts WHERE {$where} ORDER BY {$order} LIMIT {$limit}",
        $params
    );

    view('user/report_parts', [
        'report'      => $report,
        'selected'    => $selected,
        'selectedIds' => $selectedIds,
        'results'     => $results,
        'total'       => $total,
        'limit'       => $limit,
        'q'           => $q,
        'sort'        => $sort,
        'step'        => 'parts',
        'progress'    => Report::progress($report),
        'title'       => '交換部品登録',
        'showSync'    => true,
    ], 'layout_user');
}

/** 画面に出ていた部品だけを対象に、数量を入れ直す */
function report_save_part_quantities(int $reportId, array $qty): void
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
            'SELECT id FROM report_parts WHERE report_id = ? AND part_id = ?', [$reportId, $partId]
        );

        if ($n === 0) {
            if ($row) {
                Database::run('DELETE FROM report_parts WHERE id = ?', [$row['id']]);
            }
            continue;
        }
        if ($row) {
            Database::run('UPDATE report_parts SET qty = ? WHERE id = ?', [$n, $row['id']]);
        } else {
            Database::insert('report_parts', [
                'report_id'  => $reportId,
                'part_id'    => $partId,
                'part_name'  => $part['name'],
                'unit'       => $part['unit'],
                'qty'        => $n,
                'sort_order' => 0,
            ]);
        }
    }
}

// ================================================================ 2-4 測定値・報告事項

function report_measure(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);
    $id     = (int) $report['id'];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = report_action();

        $errors = report_save_measurements($id, (array) post('m', []), $action === 'next');
        $body   = mb_substr(trim((string) post('report_body', '')), 0, 4000);

        if ($action === 'insert_texts') {
            // 報告事項テーブルから選んだ定型文を、今の本文の後ろに足す
            $ids = array_map('intval', (array) post('text_ids', []));
            if ($ids) {
                $ph   = implode(',', array_fill(0, count($ids), '?'));
                $rows = Database::all(
                    "SELECT body FROM report_texts
                      WHERE id IN ($ph) AND deleted_at IS NULL
                        AND (account_id IS NULL OR account_id = ?)
                      ORDER BY sort_order, id",
                    array_merge($ids, [$user['id']])
                );
                $add  = implode("\n", array_column($rows, 'body'));
                $body = trim($body === '' ? $add : $body . "\n" . $add);
            }
            Report::touch($id, ['report_body' => mb_substr($body, 0, 4000)]);
            redirect(report_step_url($id, 'measure'));
        }

        Report::touch($id, ['report_body' => $body]);

        if ($action === 'add_row') {
            report_add_measure_row($id);
            redirect(report_step_url($id, 'measure'));
        }

        if (!$errors) {
            report_move($id, 'measure', $action === 'back' ? -1 : 1);
        }
        $report = Report::findOwned($id, $user);   // 保存後の値で描き直す
    }

    view('user/report_measure', [
        'report'   => $report,
        'rows'     => Database::all(
            'SELECT * FROM report_measurements WHERE report_id = ? ORDER BY sort_order, id', [$id]
        ),
        'models'   => Report::modelOptions($id),
        'texts'    => Database::all(
            'SELECT id, body, account_id FROM report_texts
              WHERE deleted_at IS NULL AND (account_id IS NULL OR account_id = ?)
              ORDER BY sort_order, id',
            [$user['id']]
        ),
        'canAddRow' => count(Database::all(
            'SELECT id FROM report_measurements WHERE report_id = ?', [$id]
        )) < Report::MEASURE_MAX,
        'errors'   => $errors,
        'step'     => 'measure',
        'progress' => Report::progress($report),
        'title'    => '測定値・報告事項登録',
        'showSync' => true,
    ], 'layout_user');
}

/**
 * 測定値の保存。
 * 積算時間は0〜10万、製造No.は6桁（概要書 2-4-3 / 2-4-4）。
 */
function report_save_measurements(int $reportId, array $rows, bool $strict): array
{
    $errors = [];

    foreach ($rows as $rowId => $r) {
        $rowId = (int) $rowId;
        $own   = Database::value(
            'SELECT id FROM report_measurements WHERE id = ? AND report_id = ?', [$rowId, $reportId]
        );
        if (!$own) {
            continue;
        }

        $hoursRaw = trim((string) ($r['cumulative_hours'] ?? ''));
        $hours    = null;
        if ($hoursRaw !== '') {
            if (!ctype_digit($hoursRaw) || (int) $hoursRaw > 100000) {
                if ($strict) {
                    $errors["m.$rowId.cumulative_hours"] = '積算時間は0〜100000の数字で入れてください。';
                }
                $hours = report_clamp_int($hoursRaw, 0, 100000);
            } else {
                $hours = (int) $hoursRaw;
            }
        }

        $serial = trim((string) ($r['serial_no'] ?? ''));
        if ($serial !== '' && !preg_match('/^[0-9A-Za-z-]{1,6}$/', $serial)) {
            if ($strict) {
                $errors["m.$rowId.serial_no"] = '製造No.は6桁までで入れてください。';
            }
            $serial = mb_substr($serial, 0, 6);
        }

        $ym = trim((string) ($r['manufactured_ym'] ?? ''));
        if ($ym !== '' && !preg_match('/^\d{4}-\d{2}$/', $ym)) {
            if ($strict) {
                $errors["m.$rowId.manufactured_ym"] = '製造年月の形式が正しくありません。';
            }
            $ym = '';
        }

        Database::update('report_measurements', [
            'room_name'        => mb_substr(trim((string) ($r['room_name'] ?? '')), 0, 128),
            'model_name'       => mb_substr(trim((string) ($r['model_name'] ?? '')), 0, 128),
            'cumulative_hours' => $hours,
            'serial_no'        => $serial,
            'manufactured_ym'  => $ym ?: null,
        ], 'id = :id AND report_id = :report_id', ['id' => $rowId, 'report_id' => $reportId]);
    }

    return $errors;
}

function report_add_measure_row(int $reportId): void
{
    $rows = (int) Database::value(
        'SELECT COUNT(*) FROM report_measurements WHERE report_id = ?', [$reportId]
    );
    if ($rows >= Report::MEASURE_MAX) {
        return;
    }
    $max = (int) Database::value(
        'SELECT COALESCE(MAX(sort_order), 0) FROM report_measurements WHERE report_id = ?', [$reportId]
    );
    Database::insert('report_measurements', [
        'report_id'  => $reportId,
        'sort_order' => $max + 10,
    ]);
}

// ================================================================ 2-5 確認署名

function report_confirm(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);
    $id     = (int) $report['id'];

    $checklist = Database::all(
        'SELECT id, label FROM checklist_items WHERE is_active = 1 ORDER BY sort_order, id'
    );
    $requiredIds = array_map('intval', array_column($checklist, 'id'));

    $workers = Database::all(
        'SELECT id, name FROM workers WHERE account_id = ? AND deleted_at IS NULL ORDER BY kana, id',
        [$user['id']]
    );

    $form = [
        'checked'   => array_filter(array_map('intval', explode(',', (string) $report['checked_ids']))),
        'submitter' => (string) $report['submitter_name'],
    ];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $action = report_action();

        $form['checked'] = array_values(array_intersect(
            array_map('intval', (array) post('checked', [])), $requiredIds
        ));

        // 作業者テーブルから選んだ場合と、直接入力した場合の両方を受ける
        $pickId = (int) post('worker_pick', 0);
        $free   = trim((string) post('submitter_free', ''));
        $picked = '';
        foreach ($workers as $w) {
            if ((int) $w['id'] === $pickId) {
                $picked = $w['name'];
            }
        }
        $form['submitter'] = $picked !== '' ? $picked : $free;

        $allChecked = count($form['checked']) === count($requiredIds);

        // 概要書 2-5-3「5つのチェック全てに印がつかないと作業者の入力ができなくする」。
        // 画面側でも押せないようにしているが、直接送られた場合はここで落とす
        if (!$allChecked && $form['submitter'] !== '') {
            $form['submitter'] = '';
            $errors['submitter_name'] = '確認事項すべてにチェックを入れてから、作業者を入力してください。';
        }

        Report::touch($id, [
            'checked_ids'    => implode(',', $form['checked']),
            'submitter_name' => mb_substr($form['submitter'], 0, 128),
        ]);

        if ($action === 'next' && !$errors) {
            if (!$allChecked) {
                $errors['checked'] = '確認事項すべてにチェックを入れてください。';
            }
            if ($form['submitter'] === '') {
                $errors['submitter_name'] = '作業者を選ぶか、直接入力してください。';
            }
            if (!$errors) {
                Report::touch($id, [
                    'status'       => 'submitted',
                    'submitted_at' => now(),
                ]);
                audit('report_submitted', 'reports:' . $id);
                redirect("/report/{$id}/done");
            }
        }

        if ($action === 'back' && !$errors) {
            report_move($id, 'confirm', -1);
        }
        $report = Report::findOwned($id, $user);
    }

    // 選んだ作業者がテーブルにいる名前なら、ラジオを選択済みにして描き直す
    $form['submitter_id'] = 0;
    $form['submitter_free'] = $form['submitter'];
    foreach ($workers as $w) {
        if ($w['name'] === $form['submitter']) {
            $form['submitter_id']   = (int) $w['id'];
            $form['submitter_free'] = '';
        }
    }

    view('user/report_confirm', [
        'report'    => $report,
        'checklist' => $checklist,
        'form'      => $form,
        'errors'    => $errors,
        'workers'   => $workers,
        'step'      => 'confirm',
        'progress'  => Report::progress($report),
        'title'     => '確認・署名',
        'showSync'  => true,
    ], 'layout_user');
}

// ================================================================ 2-6 サイン入力

function report_sign(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);
    $id     = (int) $report['id'];
    $error  = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        $png = report_decode_signature((string) post('image', ''));
        if ($png === null) {
            $error = 'サインを保存できませんでした。もう一度お書きください。';
        } else {
            $dir = config('storage.signatures');
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $name = sprintf('sign_%d_%s.png', $id, date('YmdHis'));
            file_put_contents($dir . '/' . $name, $png);

            // 前のサインが残っていたら消す
            if ($report['signature_file'] && is_file($dir . '/' . $report['signature_file'])) {
                unlink($dir . '/' . $report['signature_file']);
            }

            Report::touch($id, [
                'signature_file' => $name,
                'signature_at'   => now(),
            ]);
            audit('report_signed', 'reports:' . $id);
            redirect(report_step_url($id, 'confirm'));
        }
    }

    view('user/report_sign', [
        'report' => $report,
        'error'  => $error,
        'title'  => 'サイン入力',
    ]);
}

/**
 * canvas から届いた data URL を PNG のバイナリに戻す。
 * 画像として読めないもの、大きすぎるものは受け取らない。
 */
function report_decode_signature(string $dataUrl): ?string
{
    if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
        return null;
    }
    $png = base64_decode($m[1], true);
    if ($png === false || strlen($png) < 100 || strlen($png) > 1024 * 1024) {
        return null;
    }
    $info = @getimagesizefromstring($png);
    if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] < 50 || $info[0] > 4000) {
        return null;
    }
    return $png;
}

/** サイン画像は data/ の中にあるので、ログイン確認を通してから配信する */
function report_signature_image(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);

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

function report_delete_signature(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);
    csrf_check();

    $dir = config('storage.signatures');
    if ($report['signature_file'] && is_file($dir . '/' . $report['signature_file'])) {
        unlink($dir . '/' . $report['signature_file']);
    }
    Report::touch((int) $report['id'], ['signature_file' => null, 'signature_at' => null]);

    redirect(report_step_url((int) $report['id'], 'confirm'));
}

// ================================================================ 共通

function report_clamp_int($value, int $min, int $max): int
{
    $n = (int) preg_replace('/[^0-9-]/', '', (string) $value);
    return max($min, min($max, $n));
}
