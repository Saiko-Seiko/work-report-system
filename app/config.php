<?php
/**
 * アプリ設定
 *
 * 本番（さくらのレンタルサーバ ビジネス）では MySQL、
 * 開発機では SQLite を使う。SQL は両方で通る書き方に統一している。
 * 環境ごとの値は app/config.local.php を置けば上書きできる（Git管理外）。
 */
declare(strict_types=1);

$config = [
    // 'sqlite' | 'mysql'
    'db_driver' => 'sqlite',

    'sqlite' => [
        'path' => APP_ROOT . '/data/app.sqlite',
    ],

    // さくら側の値に差し替える
    'mysql' => [
        'host'     => 'mysqlXXX.db.sakura.ne.jp',
        'database' => 'xxxxxx_report',
        'user'     => 'xxxxxx',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // 画面サイズ（概要書「その他、仕様」）
    'viewport' => [
        'user'  => ['w' => 768,  'h' => 1024],
        'admin' => ['w' => 1600, 'h' => 900],
    ],

    'app_name'       => '作業完了報告書SYSTEM',

    // 報告書の右上に入る自社情報（概要書 2-8 のプレビュー参照）
    'company_name'    => '株式会社アイソテック',
    'company_address' => '東京都中央区銀座1-1-1 銀座ビル4F',
    'company_tel'     => 'TEL 03-0000-0000 / FAX 03-0000-0001',
    'company_branch'  => '大阪営業所 大阪市北区梅田1-1-1　TEL 06-0000-0000',
    'login_max_fail' => 3,           // 3回エラーでロック（解除は事務局）
    'session_name'   => 'wcrsid',
    'debug'          => true,        // 本番では false
    // 書き込み先。書き込めないサーバー（Vercel等）では config.vercel.php が /tmp に差し替える
    'storage'        => [
        'signatures' => APP_ROOT . '/data/signatures',
        'pdf'        => APP_ROOT . '/data/pdf',
        'backups'    => APP_ROOT . '/data/backups',
        'tmp'        => APP_ROOT . '/data/tmp',
    ],
    // メール送信（Phase 5）。本番はさくらのSMTPを使う
    'mail' => [
        'from_name'       => '作業完了報告書SYSTEM',
        'from_address'    => 'noreply@example.sakura.ne.jp',
        'default_subject' => '作業完了報告書',
        'dry_run'         => true,   // プロトタイプでは実送信しない
    ],
];

// Vercel などの書き込めないサーバーで動かすとき（VERCEL は Vercel が自動で入れる目印）
if (getenv('VERCEL') !== false && is_file(APP_ROOT . '/app/config.vercel.php')) {
    $config = array_replace_recursive($config, require APP_ROOT . '/app/config.vercel.php');
}

if (is_file(APP_ROOT . '/app/config.local.php')) {
    $config = array_replace_recursive($config, require APP_ROOT . '/app/config.local.php');
}

return $config;
