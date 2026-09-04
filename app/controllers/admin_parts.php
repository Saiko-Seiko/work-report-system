<?php
/**
 * K-4 交換部品マスター 登録画面 ／ 詳細修正ダイアログ ／ ダウンロード・インポート
 *
 * 概要書は「インポートで、修正したファイルをDBに書き込む（書き込む前にDBをクリア）」
 * となっているが、そのまま作ると
 *   ・ファイルを間違えた瞬間に1万件が消える
 *   ・採番が変わり、過去の報告書から部品への紐付けが外れる
 * ため、次のようにしている。
 *   1. 取り込む前に、いまのマスタを自動でバックアップする
 *   2. 部品名をキーに「追加・変更・削除」の差分を出して見せる
 *   3. 中身を確認してから実行。実行はトランザクションで、失敗したら丸ごと元に戻す
 *   4. 削除は印を付けて隠すだけ。過去の報告書の記録は残る
 *
 * ファイル形式はエクセルがそのまま開ける CSV（UTF-8 BOM付き）。
 * 共用サーバーに変換ライブラリを置けないため、壊れにくいこちらを選んでいる。
 */
declare(strict_types=1);

require_once APP_ROOT . '/app/controllers/admin_common.php';

const ADMIN_PART_SORTS = [
    'no'       => 'id',
    'name'     => 'name',
    'kana'     => 'kana',
    'unit'     => 'unit',
    'created'  => 'created_at',
    'priority' => 'priority',
];

const ADMIN_PART_COLUMNS = ['部品名', 'ヨミガナ', '単位', '優先順位'];

// ================================================================ 一覧

function admin_parts(): void
{
    Auth::requireAdmin();

    $q = trim((string) query('q', ''));
    [$sort, $dir] = admin_sort(ADMIN_PART_SORTS, 'priority');

    $where  = 'deleted_at IS NULL';
    $params = [];
    if ($q !== '') {
        $where .= ' AND (name LIKE ? OR kana LIKE ?)';
        $params = ['%' . $q . '%', '%' . $q . '%'];
    }

    $pager  = admin_pager((int) Database::value("SELECT COUNT(*) FROM parts WHERE {$where}", $params));
    $column = ADMIN_PART_SORTS[$sort];

    view('admin/parts', [
        'rows'   => Database::all(
            "SELECT * FROM parts WHERE {$where}
              ORDER BY {$column} {$dir}, id {$dir}
              LIMIT " . ADMIN_PER_PAGE . " OFFSET {$pager['offset']}",
            $params
        ),
        'pager'  => $pager,
        'q'      => $q,
        'sort'   => $sort,
        'dir'    => $dir,
        'dialog' => admin_part_dialog(),
        'diff'   => admin_take_part_diff(),
        'notice' => admin_take_notice(),
        'title'  => '交換部品マスタ',
        'nav'    => 'parts',
    ], 'layout_admin');
}

/**
 * 取り込みの結果を1回だけ出す。
 * エラーは見せたら消す。確認待ち（ok）は実行かキャンセルまで残す。
 */
function admin_take_part_diff(): ?array
{
    $diff = $_SESSION['admin_part_diff'] ?? null;
    if ($diff && empty($diff['ok'])) {
        unset($_SESSION['admin_part_diff']);
    }
    return $diff;
}

function admin_part_dialog(): ?array
{
    $flash = $_SESSION['admin_part_form'] ?? null;
    unset($_SESSION['admin_part_form']);

    if (query('new') === '1') {
        return $flash ?? ['mode' => 'new', 'id' => 0, 'name' => '', 'kana' => '',
                          'unit' => '個', 'priority' => 0, 'errors' => []];
    }

    $id = (int) query('edit', 0);
    if ($id <= 0) {
        return null;
    }
    if ($flash && (int) $flash['id'] === $id) {
        return $flash;
    }

    $part = Database::one('SELECT * FROM parts WHERE id = ?', [$id]);
    if (!$part) {
        return null;
    }

    return [
        'mode'     => 'edit',
        'id'       => $id,
        'name'     => (string) $part['name'],
        'kana'     => (string) $part['kana'],
        'unit'     => (string) $part['unit'],
        'priority' => (int) $part['priority'],
        'errors'   => [],
    ];
}

// ================================================================ 1件の登録・削除

function admin_parts_save(): void
{
    Auth::requireAdmin();
    csrf_check();

    $id    = (int) post('id', 0);
    $isNew = $id === 0;
    $form  = [
        'mode'     => $isNew ? 'new' : 'edit',
        'id'       => $id,
        'name'     => trim((string) post('name', '')),
        'kana'     => trim((string) post('kana', '')),
        'unit'     => trim((string) post('unit', '個')),
        'priority' => (int) post('priority', 0),
        'errors'   => [],
    ];

    $errors = [];
    if ($form['name'] === '') {
        $errors['name'] = '部品名を入れてください。';
    } else {
        $dup = Database::one(
            'SELECT id FROM parts WHERE name = ? AND id <> ? AND deleted_at IS NULL',
            [$form['name'], $id]
        );
        if ($dup) {
            $errors['name'] = 'この部品名はすでに登録されています。';
        }
    }
    if ($form['unit'] === '') {
        $form['unit'] = '個';
    }

    if ($errors) {
        $form['errors'] = $errors;
        $_SESSION['admin_part_form'] = $form;
        redirect('/admin/parts?' . ($isNew ? 'new=1' : 'edit=' . $id));
    }

    $data = [
        'name'       => mb_substr($form['name'], 0, 191),
        'kana'       => mb_substr($form['kana'], 0, 191),
        'unit'       => mb_substr($form['unit'], 0, 16),
        'priority'   => max(0, min(999999, $form['priority'])),
        'updated_at' => now(),
    ];

    if ($isNew) {
        $data['created_at'] = now();
        Database::insert('parts', $data);
    } else {
        Database::update('parts', $data, 'id = :id', ['id' => $id]);
    }

    audit($isNew ? 'admin_part_created' : 'admin_part_updated', 'parts:' . $form['name']);
    redirect('/admin/parts');
}

function admin_parts_delete(): void
{
    Auth::requireAdmin();
    csrf_check();

    $id   = (int) post('id', 0);
    $part = Database::one('SELECT * FROM parts WHERE id = ?', [$id]);
    if ($part) {
        // 過去の報告書から辿れるよう、消さずに隠す
        Database::update('parts', ['deleted_at' => now(), 'updated_at' => now()],
            'id = :id', ['id' => $id]);
        audit('admin_part_deleted', 'parts:' . $part['name']);
    }
    redirect('/admin/parts');
}

// ================================================================ ダウンロード

function admin_parts_download(): void
{
    Auth::requireAdmin();

    $rows = Database::all(
        'SELECT name, kana, unit, priority FROM parts WHERE deleted_at IS NULL
          ORDER BY priority DESC, kana, name'
    );

    audit('admin_parts_downloaded', 'parts', count($rows) . '件');
    admin_send_csv('parts_' . date('Ymd_His') . '.csv', ADMIN_PART_COLUMNS, $rows);
}

/** エクセルでそのまま開けるよう、UTF-8のBOMを付けて送る */
function admin_send_csv(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

// ================================================================ インポート

/** 手順1：ファイルを受け取り、差分を出して見せる */
function admin_parts_import(): void
{
    Auth::requireAdmin();
    csrf_check();

    unset($_SESSION['admin_part_diff']);

    $file = $_FILES['file'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        admin_set_notice('ファイルを選んでください。', 'error');
        redirect('/admin/parts');
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        admin_set_notice('ファイルが大きすぎます（8MBまで）。', 'error');
        redirect('/admin/parts');
    }

    $parsed = admin_parse_parts_csv($file['tmp_name']);

    if ($parsed['errors']) {
        $_SESSION['admin_part_diff'] = [
            'ok'     => false,
            'errors' => array_slice($parsed['errors'], 0, 20),
            'more'   => max(0, count($parsed['errors']) - 20),
        ];
        redirect('/admin/parts');
    }

    // 部品名をキーに、いまのマスタと突き合わせる
    $current = [];
    foreach (Database::all('SELECT id, name, kana, unit, priority FROM parts WHERE deleted_at IS NULL') as $r) {
        $current[$r['name']] = $r;
    }

    $add = $update = $keep = [];
    foreach ($parsed['rows'] as $row) {
        $old = $current[$row['name']] ?? null;
        if (!$old) {
            $add[] = $row;
            continue;
        }
        if ((string) $old['kana'] !== $row['kana']
            || (string) $old['unit'] !== $row['unit']
            || (int) $old['priority'] !== $row['priority']) {
            $update[] = $row + ['old' => $old];
        } else {
            $keep[] = $row;
        }
    }
    $remove = array_values(array_diff(array_keys($current), array_column($parsed['rows'], 'name')));

    // 取り込む中身を一時ファイルに置き、実行のときに読み直す
    $token = bin2hex(random_bytes(12));
    $dir   = APP_ROOT . '/data/tmp';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir . '/parts_' . $token . '.json',
        json_encode($parsed['rows'], JSON_UNESCAPED_UNICODE));

    $_SESSION['admin_part_diff'] = [
        'ok'       => true,
        'token'    => $token,
        'filename' => (string) $file['name'],
        'total'    => count($parsed['rows']),
        'add'      => count($add),
        'update'   => count($update),
        'remove'   => count($remove),
        'keep'     => count($keep),
        'samples'  => [
            'add'    => array_slice(array_column($add, 'name'), 0, 5),
            'update' => array_slice(array_column($update, 'name'), 0, 5),
            'remove' => array_slice($remove, 0, 5),
        ],
    ];
    redirect('/admin/parts');
}

/** 手順2：中身を確認したうえで実際に書き込む */
function admin_parts_import_apply(): void
{
    Auth::requireAdmin();
    csrf_check();

    $diff = $_SESSION['admin_part_diff'] ?? null;
    $token = (string) post('token', '');
    if (!$diff || empty($diff['ok']) || $diff['token'] !== $token) {
        admin_set_notice('取り込む内容が見つかりませんでした。もう一度ファイルを選んでください。', 'error');
        redirect('/admin/parts');
    }

    $path = APP_ROOT . '/data/tmp/parts_' . $token . '.json';
    $rows = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    if (!is_array($rows)) {
        admin_set_notice('取り込むファイルが見つかりませんでした。もう一度お試しください。', 'error');
        redirect('/admin/parts');
    }

    // 取り込む前に、いまのマスタを丸ごと控える
    $backup = admin_backup_parts();

    Database::transaction(function () use ($rows) {
        // ファイルに無くなったものを先に洗い出しておく（名前で突き合わせる）
        $fileNames = array_column($rows, 'name');
        $goneNames = array_values(array_diff(
            array_column(
                Database::all('SELECT name FROM parts WHERE deleted_at IS NULL'), 'name'
            ),
            $fileNames
        ));

        foreach ($rows as $row) {
            $exists = Database::one('SELECT id FROM parts WHERE name = ?', [$row['name']]);

            if ($exists) {
                Database::update('parts', [
                    'kana'       => $row['kana'],
                    'unit'       => $row['unit'],
                    'priority'   => $row['priority'],
                    'deleted_at' => null,        // 一度隠したものが戻ってきた場合
                    'updated_at' => now(),
                ], 'id = :id', ['id' => $exists['id']]);
            } else {
                Database::insert('parts', [
                    'name'       => $row['name'],
                    'kana'       => $row['kana'],
                    'unit'       => $row['unit'],
                    'priority'   => $row['priority'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ファイルに無かったものは隠す（消さない）。
        // 名前を指定して隠すので、件数が多くても取りこぼしや消しすぎが起きない
        foreach (array_chunk($goneNames, 400) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            Database::run(
                "UPDATE parts SET deleted_at = ?, updated_at = ?
                  WHERE deleted_at IS NULL AND name IN ($ph)",
                array_merge([now(), now()], $chunk)
            );
        }
    });

    @unlink($path);
    unset($_SESSION['admin_part_diff']);

    audit('admin_parts_imported', 'parts',
        sprintf('追加%d 変更%d 削除%d / 控え:%s', $diff['add'], $diff['update'], $diff['remove'], $backup));

    admin_set_notice(sprintf(
        '交換部品マスタを更新しました（追加 %d件・変更 %d件・削除 %d件）。'
        . '取り込み前の内容は %s に控えてあります。',
        $diff['add'], $diff['update'], $diff['remove'], $backup
    ), 'info');

    redirect('/admin/parts');
}

function admin_parts_import_cancel(): void
{
    Auth::requireAdmin();
    csrf_check();

    $diff = $_SESSION['admin_part_diff'] ?? null;
    if ($diff && !empty($diff['token'])) {
        @unlink(APP_ROOT . '/data/tmp/parts_' . $diff['token'] . '.json');
    }
    unset($_SESSION['admin_part_diff']);
    redirect('/admin/parts');
}

/**
 * CSVを読む。1行目は見出しとして飛ばす。
 * 「製品名の重複エラーは実施したい」（概要書 K-4）に対応。
 * @return array{rows:array, errors:array}
 */
function admin_parse_parts_csv(string $path): array
{
    $rows   = [];
    $errors = [];
    $seen   = [];

    $fh = fopen($path, 'r');
    if (!$fh) {
        return ['rows' => [], 'errors' => ['ファイルを読み込めませんでした。']];
    }

    $lineNo = 0;
    while (($cols = fgetcsv($fh)) !== false) {
        $lineNo++;

        if ($lineNo === 1) {
            // BOM を落として見出しかどうかを見る。
            // エクセルの保存のしかたによっては BOM が重なることがあるので、まとめて外す
            $cols[0] = preg_replace('/^(?:\xEF\xBB\xBF)+/', '', (string) $cols[0]);
            if (trim((string) $cols[0]) === ADMIN_PART_COLUMNS[0]) {
                continue;
            }
            // 見出しが違うと、以降の列の対応がずれて事故になるのでここで止める
            $errors[] = '1行目は見出し（' . implode('、', ADMIN_PART_COLUMNS)
                . '）にしてください。ダウンロードしたファイルをそのまま直すのが確実です。';
            continue;
        }
        if ($cols === [null] || (count($cols) === 1 && trim((string) $cols[0]) === '')) {
            continue;
        }

        $name = trim((string) ($cols[0] ?? ''));
        $kana = trim((string) ($cols[1] ?? ''));
        $unit = trim((string) ($cols[2] ?? '')) ?: '個';
        $prio = trim((string) ($cols[3] ?? '0'));

        if ($name === '') {
            $errors[] = "{$lineNo}行目：部品名が空です。";
            continue;
        }
        if (mb_strlen($name) > 191) {
            $errors[] = "{$lineNo}行目：部品名が長すぎます（191文字まで）。";
            continue;
        }
        if (isset($seen[$name])) {
            $errors[] = "{$lineNo}行目：部品名「{$name}」が{$seen[$name]}行目と重複しています。";
            continue;
        }
        if ($prio !== '' && !ctype_digit($prio)) {
            $errors[] = "{$lineNo}行目：優先順位は数字で入れてください（「{$prio}」）。";
            continue;
        }

        $seen[$name] = $lineNo;
        $rows[]      = [
            'name'     => $name,
            'kana'     => mb_substr($kana, 0, 191),
            'unit'     => mb_substr($unit, 0, 16),
            'priority' => max(0, min(999999, (int) $prio)),
        ];
    }
    fclose($fh);

    if (!$rows && !$errors) {
        $errors[] = '取り込める行がありませんでした。1行目に見出し、2行目から中身を入れてください。';
    }

    return ['rows' => $rows, 'errors' => $errors];
}

/** 取り込み前の控え。data/backups に CSV で残す */
function admin_backup_parts(): string
{
    $dir = APP_ROOT . '/data/backups';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $name = 'parts_backup_' . date('Ymd_His') . '.csv';
    $fh   = fopen($dir . '/' . $name, 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, ADMIN_PART_COLUMNS);
    foreach (Database::all(
        'SELECT name, kana, unit, priority FROM parts WHERE deleted_at IS NULL ORDER BY id'
    ) as $row) {
        fputcsv($fh, array_values($row));
    }
    fclose($fh);

    return $name;
}

// ================================================================ お知らせ

function admin_set_notice(string $message, string $kind = 'info'): void
{
    $_SESSION['admin_notice'] = ['message' => $message, 'kind' => $kind];
}

function admin_take_notice(): ?array
{
    $notice = $_SESSION['admin_notice'] ?? null;
    unset($_SESSION['admin_notice']);
    return $notice;
}
