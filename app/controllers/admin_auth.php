<?php
/**
 * K-1 管理者ログイン / ログアウト
 * K-2 のダッシュボードは Phase 8 で作るので、ここでは受け皿だけ用意する。
 */
declare(strict_types=1);

function admin_login_page(): void
{
    if (Auth::admin()) {
        redirect('/admin/dashboard');
    }
    if (Auth::tryRemember('admin')) {
        redirect('/admin/dashboard');
    }

    $error   = null;
    $loginId = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        $loginId  = trim((string) post('login_id', ''));
        $password = (string) post('password', '');
        $remember = post('remember') === '1';

        if ($loginId === '' || $password === '') {
            $error = 'ユーザーIDとパスワードを入力してください。';
        } else {
            require_once APP_ROOT . '/app/controllers/auth.php';
            if (login_ip_blocked()) {
                $error = 'ログインの失敗が続いています。しばらく時間をおいてからお試しください。';
            } else {
                $result = Auth::attemptAdmin($loginId, $password, $remember);
                if ($result['ok']) {
                    redirect('/admin/dashboard');
                }
                $error = $result['error'];
            }
        }
    }

    view('admin/login', [
        'error'   => $error,
        'loginId' => $loginId,
        'title'   => '管理サイトログイン',
        'bare'    => true,
    ], 'layout_admin');
}

function admin_logout_action(): void
{
    csrf_check();
    Auth::logout('admin');
    redirect('/admin/login');
}
