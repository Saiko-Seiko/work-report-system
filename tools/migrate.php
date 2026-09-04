<?php
/**
 * スキーマ作成 / デモデータ投入
 *
 *   php tools/migrate.php --fresh --seed   テーブルを作り直してデモデータを入れる
 *   php tools/migrate.php --seed           デモデータだけ入れ直す
 *
 * schema.sql 内の {{PK}} / {{TAIL}} をドライバに応じて置換するので、
 * ローカル(SQLite)と本番(MySQL)で同じ定義ファイルを使える。
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI から実行してください');
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/lib/helpers.php';
require APP_ROOT . '/app/lib/Database.php';

$opts   = array_slice($argv, 1);
$fresh  = in_array('--fresh', $opts, true);
$seed   = in_array('--seed', $opts, true);
$driver = config('db_driver');

echo "driver : {$driver}\n";

$tables = [
    'mail_logs', 'internal_report_parts', 'internal_reports',
    'report_measurements', 'report_parts', 'report_models', 'report_workers', 'reports',
    'checklist_items', 'report_texts', 'parts', 'machine_models', 'workers',
    'audit_logs', 'login_attempts', 'remember_tokens', 'sync_ops', 'accounts', 'admins',
];

if ($fresh && $driver === 'sqlite') {
    $path = config('sqlite.path');
    foreach ([$path, $path . '-wal', $path . '-shm'] as $f) {
        if (is_file($f)) {
            unlink($f);
        }
    }
    echo "removed : {$path}\n";
}

Database::boot(config());
$pdo = Database::pdo();

if ($fresh && $driver === 'mysql') {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $t) {
        $pdo->exec("DROP TABLE IF EXISTS {$t}");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "dropped : " . count($tables) . " tables\n";
}

if ($fresh) {
    $sql = file_get_contents(APP_ROOT . '/app/schema/schema.sql');

    if ($driver === 'mysql') {
        $sql = str_replace(
            ['{{PK}}', '{{TAIL}}'],
            ['INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
             ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'],
            $sql
        );
    } else {
        $sql = str_replace(['{{PK}}', '{{TAIL}}'], ['INTEGER PRIMARY KEY AUTOINCREMENT', ''], $sql);
    }

    // コメント行を落としてから文単位に分割する
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);

    $count = 0;
    foreach (explode(';', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
        $count++;
    }
    echo "schema  : {$count} statements\n";
}

if ($seed) {
    require __DIR__ . '/seed.php';
    seed_all();
}

echo "done.\n";
