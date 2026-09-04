<?php
/**
 * 通し試験の共通部分。
 *
 * 実際に開発サーバへHTTPで叩きにいき、画面の応答とDBの中身の両方を見る。
 * ブラウザを使わずに動かせるので、直したあとすぐ流し直せる。
 *
 * 使い方（先に serve.cmd でサーバを起動しておく）
 *   php tests/run.php            全部まとめて
 *   php tests/wizard.php         1本だけ
 *   set WCR_BASE=http://...      別のURLに向ける場合
 */
declare(strict_types=1);

define('TEST_ROOT', dirname(__DIR__));
$ROOT = TEST_ROOT;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', TEST_ROOT);
}

require TEST_ROOT . '/app/lib/helpers.php';
require TEST_ROOT . '/app/lib/Database.php';
require TEST_ROOT . '/app/lib/Report.php';
require TEST_ROOT . '/app/lib/InternalReport.php';

Database::boot(config());

$BASE = getenv('WCR_BASE') ?: 'http://127.0.0.1:8080';
$JAR  = sys_get_temp_dir() . '/wcr_test_jar.txt';
$TMP  = sys_get_temp_dir();
@unlink($JAR);

$pass = 0;
$fail = 0;

/**
 * リクエストを1回投げる。
 * $fields に配列を渡すと name[]= の形で送る。$file を渡すとファイル添付になる。
 *
 * @return array{status:int, location:string, body:string, head:string}
 */
function req(string $method, string $path, array $fields = [], ?string $file = null): array
{
    global $BASE, $JAR;

    $ch = curl_init($BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR      => $JAR,
        CURLOPT_COOKIEFILE     => $JAR,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 60,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($file !== null) {
            $fields['file'] = new CURLFile($file, 'text/csv', basename($file));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, test_body($fields));
        }
    }

    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hlen   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        fwrite(STDERR, "  !! 接続できません（{$BASE}{$path}）: {$error}\n");
        return ['status' => 0, 'location' => '', 'body' => '', 'head' => ''];
    }

    $head = substr((string) $raw, 0, $hlen);
    $body = substr((string) $raw, $hlen);
    preg_match('/^location:\s*(.+)$/mi', $head, $m);

    return ['status' => $status, 'location' => trim($m[1] ?? ''), 'body' => $body, 'head' => $head];
}

/** name[]=a&name[]=b の形も作れるようにする */
function test_body(array $fields): string
{
    $parts = [];
    foreach ($fields as $k => $v) {
        $isList = is_array($v);
        foreach ((array) $v as $one) {
            $parts[] = urlencode($k . ($isList ? '[]' : '')) . '=' . urlencode((string) $one);
        }
    }
    return implode('&', $parts);
}

/** その画面のCSRFトークンを取り出す */
function csrf(string $path): string
{
    preg_match('/name="_csrf" value="([a-f0-9]+)"/', req('GET', $path)['body'], $m);
    return $m[1] ?? '';
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  OK   ' : '  NG   ') . $label . ($detail !== '' ? "  [{$detail}]" : '') . "\n";
}

/** 各試験の最後に呼ぶ。件数を出して終了コードを返す */
function test_summary(): void
{
    global $pass, $fail;
    echo "\n================  OK {$pass} / NG {$fail}  ================\n";
    exit($fail === 0 ? 0 : 1);
}

/** ログインの短縮 */
function login_user(string $id = 'ABCDE0001', string $password = 'pass1234'): void
{
    req('POST', '/login', ['_csrf' => csrf('/login'), 'login_id' => $id, 'password' => $password]);
}

function login_admin(string $id = 'admin', string $password = 'admin1234'): void
{
    req('POST', '/admin/login', ['_csrf' => csrf('/admin/login'), 'login_id' => $id, 'password' => $password]);
}
