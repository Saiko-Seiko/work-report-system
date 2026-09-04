<?php
/**
 * 開発中の進捗確認用インデックス（config.debug = true のときだけ有効）
 * 概要書の画面番号をそのまま並べて、どこまで組めたかが一目で分かるようにしている。
 */
declare(strict_types=1);

function dev_index(): void
{
    $stats = [
        'アカウント'   => 'accounts',
        '作業者'       => 'workers',
        '機種名'       => 'machine_models',
        '交換部品'     => 'parts',
        '報告事項'     => 'report_texts',
        '確認事項'     => 'checklist_items',
        '報告書'       => 'reports',
        '社内用報告書' => 'internal_reports',
    ];
    $counts = [];
    foreach ($stats as $label => $table) {
        $counts[$label] = (int) Database::value("SELECT COUNT(*) FROM {$table}");
    }

    // 出力系のリンク先に使う、いちばん新しい報告書
    $latest = (int) Database::value(
        "SELECT id FROM reports WHERE deleted_at IS NULL ORDER BY report_no DESC LIMIT 1"
    );

    // [概要書の番号, 画面名, URL, フェーズ]
    $screens = [
        ['1-1',  'ログイン',                 '/login',            2],
        ['1-2',  'ダッシュボード',           '/dashboard',        2],
        ['2-1',  '基本情報登録',             '/report/new',       3],
        ['2-2',  '作業内容登録',             null,                3],
        ['2-3',  '交換部品登録',             null,                3],
        ['2-4',  '測定値・報告事項登録',     null,                3],
        ['2-5',  '確認署名',                 null,                3],
        ['2-6',  'サイン入力',               null,                3],
        ['2-7',  '完了',                     $latest ? "/report/{$latest}/done" : null, 5],
        ['2-8',  'プレビュー（PDF）',        $latest ? "/report/{$latest}/preview" : null, 5],
        ['2-9',  '印刷',                     $latest ? "/report/{$latest}/print" : null, 5],
        ['2-10', 'メール送信',               $latest ? "/report/{$latest}/mail" : null, 5],
        ['3',    '報告書一覧',               '/reports',          6],
        ['5-1',  'ユーザー情報変更',         '/mypage',           6],
        ['5-2',  '作業者テーブル変更',       '/mypage/workers',   6],
        ['5-3',  '報告事項テーブル変更',     '/mypage/texts',     6],
        ['4-1', '社内用 基本情報', $latest ? "/report/{$latest}/internal/basic" : null, 7],
        ['4-2', '社内用 残作業', $latest ? "/report/{$latest}/internal/remain" : null, 7],
        ['4-3', '社内用 再手配の部材', $latest ? "/report/{$latest}/internal/parts" : null, 7],
        ['4-4', '社内用 移動・作業時間', $latest ? "/report/{$latest}/internal/hours" : null, 7],
        ['4-5', '社内用 営業アプローチ', $latest ? "/report/{$latest}/internal/sales" : null, 7],
        ['4-6', '社内用 PDF確認', $latest ? "/report/{$latest}/internal/confirm" : null, 7],
        ['K-1',  '管理者ログイン',           '/admin/login',      2],
        ['K-2',  '管理者ダッシュボード',     '/admin/dashboard',  8],
        ['K-3',  'ユーザー登録',             '/admin/users',      8],
        ['K-4',  '交換部品マスタ',           '/admin/parts',      8],
        ['K-5',  '機種名マスタ',             '/admin/models',     8],
        ['K-6',  '報告事項マスタ',           '/admin/texts',      8],
        ['K-7',  '管理者情報',               '/admin/profile',    8],
    ];

    $donePhase = 9;   // 完了しているフェーズ番号

    view('partials/dev_index', [
        'counts'    => $counts,
        'screens'   => $screens,
        'donePhase' => $donePhase,
        'driver'    => Database::driver(),
        'dbPath'    => Database::driver() === 'sqlite' ? config('sqlite.path') : config('mysql.host'),
    ], 'layout_user');
}
