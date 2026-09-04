<?php
/**
 * 起動処理。index.php から最初に読まれる。
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tokyo');

require APP_ROOT . '/app/lib/helpers.php';
require APP_ROOT . '/app/lib/Database.php';
require APP_ROOT . '/app/lib/Router.php';
require APP_ROOT . '/app/lib/Auth.php';
require APP_ROOT . '/app/lib/Report.php';
require APP_ROOT . '/app/lib/InternalReport.php';
require APP_ROOT . '/app/lib/Sync.php';

if (config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// ---- セッション -------------------------------------------------
session_name(config('session_name'));
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    // 本番（HTTPS）では true。さくらは共有SSLでもhttpsが使える
    'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
    'lifetime' => 0,
]);
session_start();

// ---- DB ---------------------------------------------------------
Database::boot(config());

// ---- 共通レスポンスヘッダ ---------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
