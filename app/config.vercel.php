<?php
/**
 * Vercel で動かすときの上書き設定。
 * app/config.php が、環境変数 VERCEL があるときだけ読み込む。
 *
 * Vercel は「保存できないサーバー」で、置いたファイルは書き換えられない。
 * 唯一書ける /tmp を使い、起動のたびにデモ用のDBをそこへ複製している。
 *
 * そのため **入力したデータはしばらくすると消えます**。
 * クライアントに見せるためのデモ専用で、本番用途には使えない。
 * 本番はさくらのレンタルサーバ（docs/deploy.md）。
 */
declare(strict_types=1);

$runtime = sys_get_temp_dir() . '/wcr';

foreach (['', '/signatures', '/pdf', '/backups', '/tmp'] as $sub) {
    if (!is_dir($runtime . $sub)) {
        @mkdir($runtime . $sub, 0777, true);
    }
}

// セッションの置き場所も /tmp にする（session_start より前に決める必要がある）
if (is_writable(sys_get_temp_dir())) {
    ini_set('session.save_path', sys_get_temp_dir());
}

// リポジトリに入れてあるデモ用DBを、書ける場所へ複製する。
// 一度作ったらそのまま使うので、同じ利用者が続けて操作している間は消えない
$live = $runtime . '/app.sqlite';
$seed = APP_ROOT . '/data/demo.sqlite';

if (!is_file($live) && is_file($seed)) {
    @copy($seed, $live);

    // 報告書のサイン画像も一緒に用意する（一覧の「署名 有」と紙が食い違わないように）
    foreach (glob(APP_ROOT . '/data/demo_signatures/*.png') ?: [] as $png) {
        @copy($png, $runtime . '/signatures/' . basename($png));
    }
}

return [
    'db_driver' => 'sqlite',
    'sqlite'    => ['path' => $live],

    'debug' => false,

    'storage' => [
        'signatures' => $runtime . '/signatures',
        'pdf'        => $runtime . '/pdf',
        'backups'    => $runtime . '/backups',
        'tmp'        => $runtime . '/tmp',
    ],

    // デモなので実際には送らない
    'mail' => ['dry_run' => true],
];
