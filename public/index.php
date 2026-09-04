<?php
/**
 * フロントコントローラ。
 * さくらのレンタルサーバでは、このディレクトリの中身を ~/www に、
 * app/ と data/ は www の外（ホーム直下）に置く。
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// 開発用ビルトインサーバのときは、実ファイルはそのまま返す
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require APP_ROOT . '/app/bootstrap.php';

$router = new Router();
require APP_ROOT . '/app/routes.php';

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);
