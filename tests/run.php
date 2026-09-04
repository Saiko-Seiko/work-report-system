<?php
/**
 * 通し試験をまとめて流す。
 *
 *   php tests/run.php            全部
 *   php tests/run.php admin      1本だけ
 *
 * 先に serve.cmd で開発サーバを起動しておくこと。
 * 各試験の前にデモデータを入れ直すので、前の試験の結果を引きずらない。
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI から実行してください');
}

define('APP_ROOT', dirname(__DIR__));

$suites = ['wizard', 'sync', 'output', 'list', 'internal', 'admin'];
$labels = [
    'wizard'   => '報告書作成 2-1〜2-6',
    'sync'     => 'オフライン・再送',
    'output'   => '完了・A4・印刷・メール',
    'list'     => '一覧・マイページ',
    'internal' => '社内用報告書 4-1〜4-8',
    'admin'    => '管理者サイト K-1〜K-7',
];

$only = $argv[1] ?? null;
if ($only !== null) {
    if (!in_array($only, $suites, true)) {
        exit("そんな試験はありません：{$only}\n使えるのは " . implode(' / ', $suites) . "\n");
    }
    $suites = [$only];
}

/*
 * 開発機では php.ini を触らずに済むよう、足りない拡張を -d で足す。
 * このプロセス自身が -d で受け取っていても子には引き継がれないので、
 * 子と同じ条件（素の php）で何が入っているかを一度だけ調べる。
 */
$need  = ['pdo_sqlite', 'sqlite3', 'gd', 'curl'];
$probe = [];
exec(
    escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('echo implode(",", get_loaded_extensions());'),
    $probe
);
$loaded = array_map('strtolower', explode(',', implode('', $probe)));

$flags = '';
foreach ($need as $ext) {
    if (!in_array(strtolower($ext), $loaded, true)) {
        $flags .= ' -d extension=' . $ext;
    }
}
$php = escapeshellarg(PHP_BINARY) . $flags;

/** 全角を含む見出しの桁をそろえる */
function pad(string $text, int $width): string
{
    $len = mb_strwidth($text, 'UTF-8');
    return $text . str_repeat(' ', max(1, $width - $len));
}

$totalOk = 0;
$totalNg = 0;
$failed  = [];

foreach ($suites as $suite) {
    // デモデータを初期状態に戻してから流す
    foreach (glob(APP_ROOT . '/data/backups/*.csv') ?: [] as $f) {
        @unlink($f);
    }
    foreach (glob(APP_ROOT . '/data/tmp/*.json') ?: [] as $f) {
        @unlink($f);
    }
    exec($php . ' ' . escapeshellarg(APP_ROOT . '/tools/migrate.php') . ' --seed 2>&1');

    $out = [];
    exec($php . ' ' . escapeshellarg(APP_ROOT . "/tests/{$suite}.php") . ' 2>&1', $out);
    $text = implode("\n", $out);

    preg_match('/OK (\d+) \/ NG (\d+)/', $text, $m);
    $ok = (int) ($m[1] ?? 0);
    $ng = (int) ($m[2] ?? 0);
    $totalOk += $ok;
    $totalNg += $ng;

    printf("  %-9s %-26s OK %3d / NG %d\n", $suite, $labels[$suite], $ok, $ng);

    if ($ng > 0 || $ok === 0) {
        $failed[] = $suite;
        foreach ($out as $line) {
            if (str_contains($line, 'NG ') || str_contains($line, '!!')) {
                echo '        ' . $line . "\n";
            }
        }
    }
}

echo "  " . str_repeat('-', 52) . "\n";
printf("  %-36s OK %3d / NG %d\n", '合計', $totalOk, $totalNg);

// 最後にデモデータを初期状態へ
exec($php . ' ' . escapeshellarg(APP_ROOT . '/tools/migrate.php') . ' --seed 2>&1');

if ($failed) {
    echo "\n  失敗した試験：" . implode(', ', $failed) . "\n";
    echo "  個別に流すには： php tests/run.php " . $failed[0] . "\n";
}

exit($totalNg === 0 && $totalOk > 0 ? 0 : 1);
