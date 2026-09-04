<?php
/**
 * 認証。
 * 概要書 1-1「3回エラーでパスワードロック。解除は事務局で行う」に対応する。
 */
declare(strict_types=1);

final class Auth
{
    /** 自動ログイン用Cookieの名前と有効期間 */
    private const REMEMBER_COOKIE = ['user' => 'wcr_keep', 'admin' => 'wcr_keep_admin'];
    private const REMEMBER_DAYS   = 90;

    // ---------------- 「ユーザーID,パスワードを保持」 ----------------
    //
    // 概要書 1-1/K-1 の要望。ただしパスワードそのものを端末に残すと、
    // タブレットを紛失したときに他の病院の報告書まで見られてしまう。
    // そこで「その端末だけで使える使い捨ての合鍵」をCookieに置き、
    // 使うたびに合鍵を作り替える方式にした。利用者から見た動きは
    //「次に開いたときログイン済み」で、要望どおりになる。

    private static function issueRemember(string $kind, int $actorId): void
    {
        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(24));
        $expires   = time() + self::REMEMBER_DAYS * 86400;

        Database::insert('remember_tokens', [
            'actor_kind'     => $kind,
            'actor_id'       => $actorId,
            'selector'       => $selector,
            'validator_hash' => hash('sha256', $validator),
            'user_agent'     => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'expires_at'     => date('Y-m-d H:i:s', $expires),
            'last_used_at'   => null,
            'created_at'     => now(),
        ]);

        self::setRememberCookie($kind, $selector . ':' . $validator, $expires);
    }

    private static function setRememberCookie(string $kind, string $value, int $expires): void
    {
        setcookie(self::REMEMBER_COOKIE[$kind], $value, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
        ]);
    }

    /**
     * ログイン画面を開いたときに呼ぶ。合鍵が有効なら、そのままログインさせる。
     */
    public static function tryRemember(string $kind): bool
    {
        $raw = $_COOKIE[self::REMEMBER_COOKIE[$kind]] ?? '';
        if ($raw === '' || !str_contains($raw, ':')) {
            return false;
        }
        [$selector, $validator] = explode(':', $raw, 2);

        $token = Database::one(
            'SELECT * FROM remember_tokens WHERE selector = ? AND actor_kind = ?',
            [$selector, $kind]
        );
        if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            self::forgetRemember($kind);
            return false;
        }
        if (strtotime($token['expires_at']) < time()) {
            Database::run('DELETE FROM remember_tokens WHERE id = ?', [$token['id']]);
            self::forgetRemember($kind);
            return false;
        }

        $table = $kind === 'admin' ? 'admins' : 'accounts';
        $actor = Database::one("SELECT * FROM {$table} WHERE id = ?", [$token['actor_id']]);
        if (!$actor || ($kind === 'user' && (int) $actor['is_locked'] === 1)) {
            Database::run('DELETE FROM remember_tokens WHERE id = ?', [$token['id']]);
            self::forgetRemember($kind);
            return false;
        }

        // 合鍵を作り替える（使い回しを防ぐ）
        $next    = bin2hex(random_bytes(24));
        $expires = time() + self::REMEMBER_DAYS * 86400;
        Database::update('remember_tokens', [
            'validator_hash' => hash('sha256', $next),
            'expires_at'     => date('Y-m-d H:i:s', $expires),
            'last_used_at'   => now(),
        ], 'id = :id', ['id' => $token['id']]);
        self::setRememberCookie($kind, $selector . ':' . $next, $expires);

        session_regenerate_id(true);
        if ($kind === 'admin') {
            $_SESSION['admin_id']    = (int) $actor['id'];
            $_SESSION['admin_login'] = $actor['account_id'];
        } else {
            $_SESSION['account_id']    = (int) $actor['id'];
            $_SESSION['account_login'] = $actor['account_id'];
        }
        $_SESSION['auto_login'] = true;
        audit('auto_login', $table . ':' . $actor['account_id']);

        return true;
    }

    /** この端末の合鍵を捨てる */
    private static function forgetRemember(string $kind): void
    {
        $raw = $_COOKIE[self::REMEMBER_COOKIE[$kind]] ?? '';
        if ($raw !== '' && str_contains($raw, ':')) {
            Database::run(
                'DELETE FROM remember_tokens WHERE selector = ? AND actor_kind = ?',
                [explode(':', $raw, 2)[0], $kind]
            );
        }
        self::setRememberCookie($kind, '', time() - 3600);
        unset($_COOKIE[self::REMEMBER_COOKIE[$kind]]);
    }

    /** ロック時など、そのアカウントの合鍵をすべて無効にする */
    private static function revokeAllRemember(string $kind, int $actorId): void
    {
        Database::run(
            'DELETE FROM remember_tokens WHERE actor_kind = ? AND actor_id = ?',
            [$kind, $actorId]
        );
    }

    // ---------------- 利用者（協力会社） ----------------

    public static function user(): ?array
    {
        $id = $_SESSION['account_id'] ?? null;
        if (!$id) {
            return null;
        }
        return Database::one('SELECT * FROM accounts WHERE id = ?', [$id]);
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) {
            redirect('/login');
        }
        return $user;
    }

    /**
     * @return array{ok:bool, error?:string}
     */
    public static function attemptUser(string $loginId, string $password, bool $remember = false): array
    {
        $result = self::evaluateUser($loginId, $password, $remember);

        // ロック中に正しいパスワードを入れた場合も「失敗」として残す。
        // 記録上の成功と、実際にログインできたかを一致させておく
        Database::insert('login_attempts', [
            'login_kind' => 'user',
            'account_id' => $loginId,
            'ip'         => client_ip(),
            'succeeded'  => $result['ok'] ? 1 : 0,
            'created_at' => now(),
        ]);

        return $result;
    }

    private static function evaluateUser(string $loginId, string $password, bool $remember): array
    {
        $account = Database::one('SELECT * FROM accounts WHERE account_id = ?', [$loginId]);
        $ok      = $account && password_verify($password, $account['password_hash']);

        if (!$account) {
            return ['ok' => false, 'error' => 'ユーザーIDまたはパスワードが違います。'];
        }

        if ((int) $account['is_locked'] === 1) {
            return [
                'ok'    => false,
                'error' => 'このユーザーIDはロックされています。解除は事務局にご連絡ください。',
            ];
        }

        if (!$ok) {
            $failed = (int) $account['failed_count'] + 1;
            $max    = (int) config('login_max_fail', 3);
            $lock   = $failed >= $max;

            Database::update('accounts', [
                'failed_count' => $failed,
                'is_locked'    => $lock ? 1 : 0,
                'locked_at'    => $lock ? now() : null,
                'updated_at'   => now(),
            ], 'id = :id', ['id' => $account['id']]);

            if ($lock) {
                // ロックしたら、その会社の全端末の合鍵も無効にする
                self::revokeAllRemember('user', (int) $account['id']);
                audit('login_locked', 'accounts:' . $account['account_id']);
                return [
                    'ok'    => false,
                    'error' => "パスワードを{$max}回間違えたため、ロックしました。解除は事務局にご連絡ください。",
                ];
            }

            $rest = $max - $failed;
            return [
                'ok'    => false,
                'error' => "ユーザーIDまたはパスワードが違います。（あと{$rest}回間違えるとロックされます）",
            ];
        }

        Database::update('accounts', [
            'failed_count'  => 0,
            'last_login_at' => now(),
            'updated_at'    => now(),
        ], 'id = :id', ['id' => $account['id']]);

        session_regenerate_id(true);
        $_SESSION['account_id']    = (int) $account['id'];
        $_SESSION['account_login'] = $account['account_id'];
        audit('login', 'accounts:' . $account['account_id']);

        if ($remember) {
            self::issueRemember('user', (int) $account['id']);
        } else {
            self::forgetRemember('user');
        }

        return ['ok' => true];
    }

    // ---------------- 管理者（事務局） ----------------

    public static function admin(): ?array
    {
        $id = $_SESSION['admin_id'] ?? null;
        if (!$id) {
            return null;
        }
        return Database::one('SELECT * FROM admins WHERE id = ?', [$id]);
    }

    public static function requireAdmin(): array
    {
        $admin = self::admin();
        if (!$admin) {
            redirect('/admin/login');
        }
        return $admin;
    }

    public static function attemptAdmin(string $loginId, string $password, bool $remember = false): array
    {
        $admin = Database::one('SELECT * FROM admins WHERE account_id = ?', [$loginId]);
        $ok    = $admin && password_verify($password, $admin['password_hash']);

        Database::insert('login_attempts', [
            'login_kind' => 'admin',
            'account_id' => $loginId,
            'ip'         => client_ip(),
            'succeeded'  => $ok ? 1 : 0,
            'created_at' => now(),
        ]);

        if (!$ok) {
            return ['ok' => false, 'error' => 'ユーザーIDまたはパスワードが違います。'];
        }

        session_regenerate_id(true);
        $_SESSION['admin_id']    = (int) $admin['id'];
        $_SESSION['admin_login'] = $admin['account_id'];
        audit('admin_login', 'admins:' . $admin['account_id']);

        if ($remember) {
            self::issueRemember('admin', (int) $admin['id']);
        } else {
            self::forgetRemember('admin');
        }

        return ['ok' => true];
    }

    public static function logout(string $kind = 'user'): void
    {
        audit($kind === 'admin' ? 'admin_logout' : 'logout');
        self::forgetRemember($kind);
        $_SESSION = [];
        session_destroy();
    }
}
