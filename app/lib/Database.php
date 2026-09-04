<?php
/**
 * PDO の薄いラッパ。
 * MySQL / SQLite どちらでも同じ呼び方で使えるようにしてある。
 */
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';

    public static function boot(array $config): void
    {
        self::$driver = $config['db_driver'];

        if (self::$driver === 'mysql') {
            $c   = $config['mysql'];
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'], $c['database'], $c['charset']);
            self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } else {
            $path = $config['sqlite']['path'];
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            // 同期中の書き込みで待たされないように
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
        }
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('Database::boot() が呼ばれていません');
        }
        return self::$pdo;
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** 1行取得（無ければ null） */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** 全行取得 */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** 1値取得 */
    public static function value(string $sql, array $params = [])
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(fn($c) => ':' . $c, $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($data)));
        $sql  = "UPDATE $table SET $sets WHERE $where";
        return self::run($sql, array_merge($data, $whereParams))->rowCount();
    }

    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
