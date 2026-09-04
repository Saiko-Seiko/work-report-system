<?php
/**
 * ルート定義。
 * URL は概要書「その他、仕様」のとおり
 *   ユーザーサイト  : xxxxxxx/login
 *   管理者サイト    : xxxxxxx/admin/login
 */
declare(strict_types=1);

/** @var Router $router */

$controller = function (string $file, string $fn) {
    return function (array $params = []) use ($file, $fn) {
        require_once APP_ROOT . '/app/controllers/' . $file . '.php';
        $fn($params);
    };
};

// ---- 開発中の確認用（本番では debug=false で塞ぐ） --------------
if (config('debug')) {
    $router->get('/_dev', $controller('dev', 'dev_index'));
}

// ---- ユーザーサイト --------------------------------------------
$router->get('/', function () {
    redirect(Auth::user() ? '/dashboard' : '/login');
});

$router->any('/login',      $controller('auth', 'login_page'));            // 1-1
$router->post('/logout',    $controller('auth', 'logout_action'));
$router->get('/dashboard',  $controller('dashboard', 'dashboard_page'));   // 1-2

// ---- 報告書作成ウィザード（2-1〜2-7） -------------------------
$router->any('/report/new',                $controller('report', 'report_new'));
$router->any('/report/{id}/basic',         $controller('report', 'report_basic'));    // 2-1
$router->any('/report/{id}/work',          $controller('report', 'report_work'));     // 2-2
$router->any('/report/{id}/parts',         $controller('report', 'report_parts'));    // 2-3
$router->any('/report/{id}/measure',       $controller('report', 'report_measure'));  // 2-4
$router->any('/report/{id}/confirm',       $controller('report', 'report_confirm'));  // 2-5
$router->any('/report/{id}/sign',          $controller('report', 'report_sign'));     // 2-6
$router->get('/report/{id}/signature.png', $controller('report', 'report_signature_image'));
$router->post('/report/{id}/signature/delete', $controller('report', 'report_delete_signature'));

// ---- 完了・出力（2-7〜2-10） ----------------------------------
$router->get('/report/{id}/done',    $controller('report_output', 'report_done'));     // 2-7
$router->get('/report/{id}/sheet',   $controller('report_output', 'report_sheet'));    // A4本体
$router->get('/report/{id}/preview', $controller('report_output', 'report_preview'));  // 2-8
$router->get('/report/{id}/print',   $controller('report_output', 'report_print'));    // 2-9
$router->any('/report/{id}/mail',    $controller('report_output', 'report_mail'));     // 2-10

// ---- 報告書一覧（3） ------------------------------------------
$router->get('/reports',       $controller('report_list', 'report_list'));
$router->post('/reports/copy', $controller('report_list', 'report_copy'));

// ---- マイページ（5-1〜5-3） -----------------------------------
$router->any('/mypage',         $controller('mypage', 'mypage_user'));     // 5-1
$router->any('/mypage/workers', $controller('mypage', 'mypage_workers'));  // 5-2
$router->any('/mypage/texts',   $controller('mypage', 'mypage_texts'));    // 5-3

// ---- 社内用報告書（4-1〜4-8） ---------------------------------
$router->get('/report/{id}/internal',          $controller('internal', 'internal_entry'));
$router->any('/report/{id}/internal/basic',    $controller('internal', 'internal_basic'));   // 4-1
$router->any('/report/{id}/internal/remain',   $controller('internal', 'internal_remain'));  // 4-2
$router->any('/report/{id}/internal/parts',    $controller('internal', 'internal_parts'));   // 4-3
$router->any('/report/{id}/internal/hours',    $controller('internal', 'internal_hours'));   // 4-4
$router->any('/report/{id}/internal/sales',    $controller('internal', 'internal_sales'));   // 4-5
$router->get('/report/{id}/internal/confirm',  $controller('internal', 'internal_confirm')); // 4-6
$router->post('/report/{id}/internal/complete', $controller('internal', 'internal_complete'));
$router->get('/report/{id}/internal/sheet',    $controller('internal', 'internal_sheet'));
$router->get('/report/{id}/internal/preview',  $controller('internal', 'internal_preview')); // 4-7
$router->get('/report/{id}/internal/print',    $controller('internal', 'internal_print'));   // 4-8

// ---- 管理者サイト ----------------------------------------------
$router->get('/admin', function () {
    redirect(Auth::admin() ? '/admin/dashboard' : '/admin/login');
});

$router->any('/admin/login',      $controller('admin_auth', 'admin_login_page'));            // K-1
$router->post('/admin/logout',    $controller('admin_auth', 'admin_logout_action'));
// K-2 ダッシュボード（提出された報告書の確認＝概要書5の⑦）
$router->get('/admin/dashboard', $controller('admin_dashboard', 'admin_dashboard'));
$router->get('/admin/report/{id}/sheet',          $controller('admin_dashboard', 'admin_report_sheet'));
$router->get('/admin/report/{id}/internal-sheet', $controller('admin_dashboard', 'admin_internal_sheet'));
$router->get('/admin/report/{id}/signature.png',  $controller('admin_dashboard', 'admin_signature_image'));

// K-3 ユーザー登録（アカウント発行・ロック解除）
$router->get('/admin/users',         $controller('admin_users', 'admin_users'));
$router->post('/admin/users/save',   $controller('admin_users', 'admin_users_save'));
$router->post('/admin/users/unlock', $controller('admin_users', 'admin_users_unlock'));

// K-4 交換部品マスタ（CSVの入出力を含む）
$router->get('/admin/parts',                $controller('admin_parts', 'admin_parts'));
$router->post('/admin/parts/save',          $controller('admin_parts', 'admin_parts_save'));
$router->post('/admin/parts/delete',        $controller('admin_parts', 'admin_parts_delete'));
$router->get('/admin/parts/download',       $controller('admin_parts', 'admin_parts_download'));
$router->post('/admin/parts/import',        $controller('admin_parts', 'admin_parts_import'));
$router->post('/admin/parts/import/apply',  $controller('admin_parts', 'admin_parts_import_apply'));
$router->post('/admin/parts/import/cancel', $controller('admin_parts', 'admin_parts_import_cancel'));

// K-5 機種名マスタ
$router->get('/admin/models',         $controller('admin_masters', 'admin_models'));
$router->post('/admin/models/save',   $controller('admin_masters', 'admin_models_save'));
$router->post('/admin/models/delete', $controller('admin_masters', 'admin_models_delete'));

// K-6 報告事項マスタ
$router->get('/admin/texts',         $controller('admin_masters', 'admin_texts'));
$router->post('/admin/texts/save',   $controller('admin_masters', 'admin_texts_save'));
$router->post('/admin/texts/delete', $controller('admin_masters', 'admin_texts_delete'));

// K-7 管理者情報
$router->any('/admin/profile', $controller('admin_masters', 'admin_profile'));
