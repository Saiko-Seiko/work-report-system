<?php
/**
 * 共通ヘルパ。
 */
declare(strict_types=1);

function config(?string $key = null, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/app/config.php';
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/** HTML エスケープ */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function query(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function redirect(string $path): void
{
    header('Location: ' . $path, true, 302);
    exit;
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** POST の JSON ボディを配列で受け取る（同期API用） */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** ビューを描画する。$layout に false を渡すと部分ビューだけ返す */
function view(string $template, array $data = [], ?string $layout = null): void
{
    extract($data, EXTR_SKIP);
    $content = (function () use ($template, $data) {
        extract($data, EXTR_SKIP);
        ob_start();
        require APP_ROOT . '/app/views/' . $template . '.php';
        return (string) ob_get_clean();
    })();

    if ($layout === null) {
        echo $content;
        return;
    }
    require APP_ROOT . '/app/views/' . $layout . '.php';
}

function render_error(int $status, string $message, string $detail = ''): void
{
    http_response_code($status);
    view('partials/error', [
        'status'  => $status,
        'message' => $message,
        'detail'  => config('debug') ? $detail : '',
    ]);
}

/** CSRF トークン */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) $sent)) {
        render_error(419, 'ページの有効期限が切れました。もう一度お試しください。');
        exit;
    }

    // 受付番号を使うのは、トークンの確認を通ったあと。
    // 先に使ってしまうと、419で弾かれた直後の再送が
    //「処理済み」と誤判定され、入力が失われてしまう
    Sync::guardReplay();
}

/** 一覧の●／－ 表示 */
function mark(bool $on): string
{
    return $on ? '●' : '－';
}

/** 2026-09-03 → 26/09/03 */
function ymd_slash(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('y/m/d', $ts) : '';
}

/** 2026-09-03 → 2026年9月3日(木) */
function ymd_ja(?string $date, bool $withDow = true): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return '';
    }
    $text = date('Y年n月j日', $ts);
    if ($withDow) {
        $text .= '(' . ['日', '月', '火', '水', '木', '金', '土'][(int) date('w', $ts)] . ')';
    }
    return $text;
}

function audit(string $action, string $target = '', string $detail = ''): void
{
    Database::insert('audit_logs', [
        'actor_kind' => $_SESSION['admin_id'] ?? null ? 'admin' : (($_SESSION['account_id'] ?? null) ? 'user' : 'system'),
        'actor_id'   => (string) ($_SESSION['admin_login'] ?? $_SESSION['account_login'] ?? ''),
        'action'     => $action,
        'target'     => $target,
        'detail'     => $detail,
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
        'created_at' => now(),
    ]);
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}
