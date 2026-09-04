<?php
/**
 * 1-1 ログイン画面 / ログアウト
 */
declare(strict_types=1);

function login_page(): void
{
    if (Auth::user()) {
        redirect('/dashboard');
    }

    // 「ユーザーID,パスワードを保持」にチェックしてあった端末は、ここで通す
    if (Auth::tryRemember('user')) {
        redirect('/dashboard');
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
        } elseif (login_ip_blocked()) {
            $error = 'ログインの失敗が続いています。しばらく時間をおいてからお試しください。';
        } else {
            $result = Auth::attemptUser($loginId, $password, $remember);
            if ($result['ok']) {
                redirect('/dashboard');
            }
            $error = $result['error'];
        }
    }

    view('user/login', [
        'error'   => $error,
        'loginId' => $loginId,
        'title'   => 'ユーザーログイン',
    ]);
}

function logout_action(): void
{
    csrf_check();
    Auth::logout('user');
    redirect('/login');
}

/**
 * アカウント単位の3回ロックとは別に、同一IPからの総当たりを抑える。
 * 会社共用IDなので、正規の利用者がロックされ続けないようこちらは時間で自動解除する。
 */
function login_ip_blocked(int $limit = 10, int $minutes = 10): bool
{
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    $n = (int) Database::value(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND succeeded = 0 AND created_at > ?',
        [client_ip(), $since]
    );
    return $n >= $limit;
}
