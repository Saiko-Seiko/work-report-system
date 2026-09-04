<?php
/**
 * 本番へ上げる前の確認。
 *
 *   php tools/preflight.php
 *
 * さくらのサーバへ置いたあと、SSHで一度これを流すと
 * 「置き場所」「書き込み権限」「接続」「設定の消し忘れ」をまとめて見られる。
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI から実行してください');
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/lib/helpers.php';
require APP_ROOT . '/app/lib/Database.php';

$ok    = 0;
$warn  = 0;
$error = 0;

function line(string $level, string $label, string $detail = ''): void
{
    global $ok, $warn, $error;
    $mark = ['ok' => '  OK  ', 'warn' => ' 注意 ', 'ng' => ' NG   '][$level];
    ${$level === 'ok' ? 'ok' : ($level === 'warn' ? 'warn' : 'error')}++;
    echo $mark . $label . ($detail !== '' ? "\n        " . $detail : '') . "\n";
}

echo "=== 実行環境 ===\n";

version_compare(PHP_VERSION, '8.0', '>=')
    ? line('ok', 'PHPのバージョン', PHP_VERSION)
    : line('ng', 'PHPのバージョンが古い', PHP_VERSION . '（8.0以上が必要）');

$driver = (string) config('db_driver');
$needed = ['mbstring', 'json', 'session', 'pcre'];
$needed[] = $driver === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite';

foreach ($needed as $ext) {
    extension_loaded($ext)
        ? line('ok', "拡張 {$ext}")
        : line('ng', "拡張 {$ext} が入っていない", 'さくらのコントロールパネルでPHPのバージョンを確認してください');
}

echo "\n=== 置き場所 ===\n";

$publicDir = APP_ROOT . '/public';
$dataDir   = APP_ROOT . '/data';
$appDir    = APP_ROOT . '/app';

foreach (['app' => $appDir, 'data' => $dataDir] as $name => $dir) {
    str_starts_with(realpath($dir) ?: '', realpath($publicDir) ?: '@')
        ? line('ng', "{$name}/ が公開ディレクトリの中にある", 'www の外へ移してください（中身が読まれます）')
        : line('ok', "{$name}/ は公開ディレクトリの外");
}

is_file($publicDir . '/.htaccess')
    ? line('ok', 'public/.htaccess がある')
    : line('ng', 'public/.htaccess が無い', 'これが無いとログイン以外のURLが開けません');

echo "\n=== 書き込み権限 ===\n";

foreach ([
    'data'            => $dataDir,
    'data/signatures' => config('storage.signatures'),
    'data/pdf'        => config('storage.pdf'),
    'data/backups'    => $dataDir . '/backups',
    'data/tmp'        => $dataDir . '/tmp',
] as $name => $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (!is_dir($dir)) {
        line('ng', "{$name} が作れない");
        continue;
    }
    is_writable($dir)
        ? line('ok', "{$name} に書き込める")
        : line('ng', "{$name} に書き込めない", 'パーミッションを 755（必要なら 777）にしてください');
}

echo "\n=== データベース ===\n";

try {
    Database::boot(config());
    line('ok', 'データベースに接続できる', $driver);

    $tables = ['accounts', 'admins', 'reports', 'internal_reports', 'parts',
               'machine_models', 'report_texts', 'checklist_items', 'sync_ops'];
    $missing = [];
    foreach ($tables as $t) {
        try {
            Database::value("SELECT COUNT(*) FROM {$t}");
        } catch (Throwable $e) {
            $missing[] = $t;
        }
    }
    $missing
        ? line('ng', 'テーブルが足りない', implode(', ', $missing) . ' … php tools/migrate.php --fresh を実行してください')
        : line('ok', 'テーブルがそろっている', count($tables) . '個を確認');

    if (!$missing) {
        $accounts = (int) Database::value('SELECT COUNT(*) FROM accounts');
        $admins   = (int) Database::value('SELECT COUNT(*) FROM admins');
        $parts    = (int) Database::value('SELECT COUNT(*) FROM parts WHERE deleted_at IS NULL');

        $admins > 0
            ? line('ok', '管理者アカウントがある', "{$admins}件")
            : line('ng', '管理者アカウントが無い', 'これが無いと管理者サイトに入れません');

        line($parts > 0 ? 'ok' : 'warn', '交換部品マスタ',
            $parts > 0 ? "{$parts}件" : '空です。管理者サイトのインポートから登録してください');
        line($accounts > 0 ? 'ok' : 'warn', '協力会社アカウント',
            $accounts > 0 ? "{$accounts}件" : '空です。管理者サイトのユーザー登録から発行してください');
    }
} catch (Throwable $e) {
    line('ng', 'データベースに接続できない', $e->getMessage());
}

echo "\n=== 本番の設定 ===\n";

config('debug')
    ? line('ng', 'debug が true のまま', 'app/config.local.php で debug => false にしてください（/_dev が開けてしまいます）')
    : line('ok', 'debug が false');

if ($driver === 'sqlite') {
    line('warn', 'データベースが SQLite のまま',
        '本番は MySQL を想定しています。app/config.local.php で db_driver => "mysql" にしてください');
} else {
    line('ok', 'データベースは MySQL');
}

config('mail.dry_run')
    ? line('warn', 'メールが送信されない設定', 'app/config.local.php で mail.dry_run => false にすると実際に送ります')
    : line('ok', 'メールを実際に送る設定');

is_file(APP_ROOT . '/app/config.local.php')
    ? line('ok', 'app/config.local.php がある')
    : line('warn', 'app/config.local.php が無い', '本番の接続情報はこのファイルに書きます（Gitには入れません）');

$company = (string) config('company_address');
str_contains($company, '銀座1-1-1')
    ? line('warn', '自社の住所がサンプルのまま', 'app/config.php の company_address / company_tel を直してください')
    : line('ok', '自社情報が設定されている');

$demo = Database::one("SELECT id FROM admins WHERE account_id = 'admin'");
if ($demo) {
    line('warn', 'デモ用の管理者IDが残っている',
        '管理者サイトの「管理者情報」からIDとパスワードを変えてください');
}

echo "\n" . str_repeat('-', 52) . "\n";
echo "  OK {$ok}件 / 注意 {$warn}件 / NG {$error}件\n";
if ($error > 0) {
    echo "  NG があるうちは本番で正しく動きません。\n";
} elseif ($warn > 0) {
    echo "  NG はありません。注意の項目を確認してから公開してください。\n";
} else {
    echo "  問題ありません。\n";
}

exit($error === 0 ? 0 : 1);
