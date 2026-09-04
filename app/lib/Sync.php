<?php
/**
 * 通信が切れている間に端末へ溜めた操作を、あとで受け取るときの門番。
 *
 * 端末は溜めた操作ひとつひとつに op_id（使い捨ての受付番号）を付けて送ってくる。
 * 電波の切れ際で「サーバーには届いたが応答が返らなかった」ときは、端末が
 * 同じ op_id で再送してくるので、ここで一度だけ処理するようにする。
 *
 * 概要書「同じデータが二重登録されない」への対応。
 */
declare(strict_types=1);

final class Sync
{
    /**
     * POST に op_id が付いていたら、処理済みかどうかを見る。
     * 処理済みなら本体を動かさずに返す。初回なら台帳に載せて先に進める。
     */
    public static function guardReplay(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $opId = trim((string) ($_POST['op_id'] ?? ''));
        if ($opId === '' || !preg_match('/^[0-9A-Za-z_-]{8,64}$/', $opId)) {
            return;
        }

        try {
            Database::insert('sync_ops', [
                'op_id'      => $opId,
                'account_id' => $_SESSION['account_id'] ?? null,
                'path'       => mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
                'created_at' => now(),
            ]);
        } catch (PDOException $e) {
            // op_id が重複 = 前回すでに処理している。もう一度は動かさない
            json_out([
                'status' => 'duplicate',
                'op_id'  => $opId,
            ]);
        }
    }
}
