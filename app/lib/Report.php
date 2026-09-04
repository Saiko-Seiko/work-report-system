<?php
/**
 * 報告書（客先提出用）の読み書き。
 * 2-1〜2-6 のウィザードから共通で使う。
 */
declare(strict_types=1);

final class Report
{
    /** ウィザードの並び順。step_nav とルートの両方がこれを見る */
    public const STEPS = [
        'basic'   => ['no' => 1, 'label' => '基本情報'],
        'work'    => ['no' => 2, 'label' => '作業内容'],
        'parts'   => ['no' => 3, 'label' => '交換部品'],
        'measure' => ['no' => 4, 'label' => '測定値・報告事項'],
        'confirm' => ['no' => 5, 'label' => '確認・署名'],
    ];

    /** 測定値の初期行数（概要書 2-4 は5行） */
    public const MEASURE_ROWS = 5;
    public const MEASURE_MAX  = 20;

    /**
     * 自分の会社の報告書だけを返す。他社のIDを直接叩かれても見せない。
     */
    public static function findOwned(int $id, array $user): array
    {
        $report = Database::one(
            'SELECT * FROM reports WHERE id = ? AND account_id = ? AND deleted_at IS NULL',
            [$id, $user['id']]
        );
        if (!$report) {
            render_error(404, '報告書が見つかりません。');
            exit;
        }
        return $report;
    }

    /**
     * 下書きを作る。
     * $clientUuid はタブレット側が発行した固有キー。同じキーで二度呼ばれても
     * 新しい報告書は作らず、既にあるものを返す（二重登録防止）。
     */
    public static function createDraft(array $user, ?string $clientUuid = null): int
    {
        $uuid = self::normalizeUuid($clientUuid);

        $exists = Database::one(
            'SELECT id FROM reports WHERE client_uuid = ? AND account_id = ?',
            [$uuid, $user['id']]
        );
        if ($exists) {
            return (int) $exists['id'];
        }

        return Database::transaction(function () use ($user, $uuid) {
            $reportId = null;

            // report_no は表示用の連番。同時に押されても衝突しないよう数回リトライする
            for ($try = 0; $try < 5; $try++) {
                try {
                    $reportId = Database::insert('reports', [
                        'report_no'       => self::nextNo(),
                        'account_id'      => $user['id'],
                        'client_uuid'     => $uuid,
                        'status'          => 'draft',
                        'created_date'    => date('Y-m-d'),
                        'work_date'       => date('Y-m-d'),
                        'device_saved_at' => now(),
                        'synced_at'       => now(),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                    break;
                } catch (PDOException $e) {
                    if ($try === 4) {
                        throw $e;
                    }
                }
            }

            // 2-2 の機種一覧と 2-4 の測定値の枠を、下書き作成時に用意しておく。
            // こうすると「対象外は削除する」の削除が、そのまま次回も残る
            $order = 0;
            foreach (Database::all(
                'SELECT id, name FROM machine_models WHERE deleted_at IS NULL ORDER BY sort_order, id'
            ) as $m) {
                Database::insert('report_models', [
                    'report_id'  => $reportId,
                    'model_id'   => $m['id'],
                    'model_name' => $m['name'],
                    'qty'        => 0,
                    'sort_order' => $order += 10,
                ]);
            }
            for ($i = 0; $i < self::MEASURE_ROWS; $i++) {
                Database::insert('report_measurements', [
                    'report_id'  => $reportId,
                    'sort_order' => ($i + 1) * 10,
                ]);
            }

            audit('report_draft_created', 'reports:' . $reportId);
            return (int) $reportId;
        });
    }

    /**
     * 既にある報告書を写して新しい下書きを作る。
     * ダッシュボードの「一覧表（コピーして作成、修正、プレビュー）」の
     * コピーにあたる。同じ病院を毎回ゼロから打ち直さなくて済む。
     *
     * 写すのは入力内容だけ。サイン・担当者・提出やメールの記録は引き継がない
     * （それは前回の作業の事実なので、新しい報告書に付いてくるとまずい）。
     */
    public static function copyFrom(array $user, int $sourceId, ?string $clientUuid = null): int
    {
        $source = self::findOwned($sourceId, $user);
        $uuid   = self::normalizeUuid($clientUuid);

        $exists = Database::one(
            'SELECT id FROM reports WHERE client_uuid = ? AND account_id = ?',
            [$uuid, $user['id']]
        );
        if ($exists) {
            return (int) $exists['id'];
        }

        return Database::transaction(function () use ($user, $source, $uuid) {
            $newId = Database::insert('reports', [
                'report_no'       => self::nextNo(),
                'account_id'      => $user['id'],
                'client_uuid'     => $uuid,
                'status'          => 'draft',
                'created_date'    => date('Y-m-d'),
                'hospital_name'   => $source['hospital_name'],
                'work_date'       => date('Y-m-d'),
                'work_place'      => $source['work_place'],
                'work_title'      => $source['work_title'],
                'workers_text'    => $source['workers_text'],
                'work_note'       => $source['work_note'],
                'parts_note'      => $source['parts_note'],
                'report_body'     => $source['report_body'],
                'device_saved_at' => now(),
                'synced_at'       => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $src = (int) $source['id'];

            foreach (Database::all(
                'SELECT worker_id, name, sort_order FROM report_workers WHERE report_id = ? ORDER BY sort_order',
                [$src]
            ) as $row) {
                Database::insert('report_workers', ['report_id' => $newId] + $row);
            }

            foreach (Database::all(
                'SELECT model_id, model_name, qty, sort_order FROM report_models WHERE report_id = ? ORDER BY sort_order',
                [$src]
            ) as $row) {
                Database::insert('report_models', ['report_id' => $newId] + $row);
            }

            foreach (Database::all(
                'SELECT part_id, part_name, unit, qty, sort_order FROM report_parts WHERE report_id = ? ORDER BY sort_order',
                [$src]
            ) as $row) {
                Database::insert('report_parts', ['report_id' => $newId] + $row);
            }

            // 測定値は枠と型式まで写し、実測値（積算時間・製造No.）は空にする。
            // 前回の数字が残ったまま提出されるのを防ぐため
            foreach (Database::all(
                'SELECT room_name, model_name, sort_order FROM report_measurements WHERE report_id = ? ORDER BY sort_order',
                [$src]
            ) as $row) {
                Database::insert('report_measurements', ['report_id' => $newId] + $row);
            }

            audit('report_copied', 'reports:' . $newId, 'from:' . $src);
            return (int) $newId;
        });
    }

    private static function nextNo(): int
    {
        return (int) Database::value('SELECT COALESCE(MAX(report_no), 1000) + 1 FROM reports');
    }

    private static function normalizeUuid(?string $uuid): string
    {
        $uuid = trim((string) $uuid);
        // 端末が発行したキーは、長さと文字種だけを見て受け取る。
        // 形を厳しく決めすぎると、端末の実装が少し違うだけで
        // 二重登録防止そのものが効かなくなるため。
        if (preg_match('/^[0-9A-Za-z_-]{8,64}$/', $uuid)) {
            return strtolower($uuid);
        }
        return sprintf(
            '%s-%s',
            date('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }

    /** 保存した印を残す。Phase 4 の同期バッジがこの2列を見る */
    public static function touch(int $reportId, array $extra = []): void
    {
        Database::update('reports', $extra + [
            'device_saved_at' => now(),
            'synced_at'       => now(),
            'updated_at'      => now(),
        ], 'id = :id', ['id' => $reportId]);
    }

    // ------------------------------------------------------------ 作業者

    /** 2-1 の作業者（複数選択＋自由入力）を保存する */
    public static function saveWorkers(int $reportId, array $workerIds, string $freeText): string
    {
        $names = [];

        if ($workerIds) {
            $ph   = implode(',', array_fill(0, count($workerIds), '?'));
            $rows = Database::all(
                "SELECT id, name FROM workers WHERE id IN ($ph) AND deleted_at IS NULL ORDER BY id",
                $workerIds
            );
            foreach ($rows as $r) {
                $names[] = ['id' => (int) $r['id'], 'name' => $r['name']];
            }
        }
        // 区切りは読点とカンマ、改行だけにする。
        // 空白で切ると「山田 太郎」が2人になってしまうため
        foreach (preg_split('/[,、\r\n]+/u', $freeText, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $name) {
            $name = trim($name);
            if ($name !== '') {
                $names[] = ['id' => null, 'name' => mb_substr($name, 0, 128)];
            }
        }

        Database::run('DELETE FROM report_workers WHERE report_id = ?', [$reportId]);
        $order = 0;
        foreach ($names as $n) {
            Database::insert('report_workers', [
                'report_id'  => $reportId,
                'worker_id'  => $n['id'],
                'name'       => $n['name'],
                'sort_order' => $order += 10,
            ]);
        }

        return implode('、', array_column($names, 'name'));
    }

    /** @return array{ids:int[], free:string} */
    public static function loadWorkers(int $reportId): array
    {
        $rows = Database::all(
            'SELECT worker_id, name FROM report_workers WHERE report_id = ? ORDER BY sort_order',
            [$reportId]
        );
        $ids  = [];
        $free = [];
        foreach ($rows as $r) {
            if ($r['worker_id'] !== null) {
                $ids[] = (int) $r['worker_id'];
            } else {
                $free[] = $r['name'];
            }
        }
        return ['ids' => $ids, 'free' => implode('、', $free)];
    }

    // ------------------------------------------------------------ 進捗

    /**
     * どのステップまで入力できているか。step_nav の色分けに使う。
     */
    public static function progress(array $report): array
    {
        $id = (int) $report['id'];

        $basic = $report['created_date'] && $report['hospital_name'] && $report['work_date']
            && $report['work_place'] && $report['workers_text'] && $report['work_title'];

        $work = (int) Database::value(
            'SELECT COUNT(*) FROM report_models WHERE report_id = ? AND qty > 0', [$id]
        ) > 0 || trim((string) $report['work_note']) !== '';

        $parts = (int) Database::value(
            'SELECT COUNT(*) FROM report_parts WHERE report_id = ? AND qty > 0', [$id]
        ) > 0 || trim((string) $report['parts_note']) !== '';

        $measure = (int) Database::value(
            "SELECT COUNT(*) FROM report_measurements
              WHERE report_id = ? AND (room_name <> '' OR cumulative_hours IS NOT NULL)", [$id]
        ) > 0 || trim((string) $report['report_body']) !== '';

        $confirm = $report['submitter_name'] && $report['signature_at'];

        return [
            'basic'   => (bool) $basic,
            'work'    => (bool) $work,
            'parts'   => (bool) $parts,
            'measure' => (bool) $measure,
            'confirm' => (bool) $confirm,
        ];
    }

    /**
     * 報告書1枚を組むのに必要な明細を全部まとめて返す。
     * プレビュー・印刷・メール・PDFがすべてこれを使う。
     */
    public static function sheetData(array $report): array
    {
        $id = (int) $report['id'];

        return [
            'report' => $report,
            'models' => Database::all(
                'SELECT model_name, qty FROM report_models
                  WHERE report_id = ? AND qty > 0 ORDER BY sort_order, id',
                [$id]
            ),
            'parts' => Database::all(
                'SELECT rp.part_name, rp.unit, rp.qty
                   FROM report_parts rp
                   LEFT JOIN parts p ON p.id = rp.part_id
                  WHERE rp.report_id = ? AND rp.qty > 0
                  ORDER BY p.priority DESC, p.kana, rp.id',
                [$id]
            ),
            'measurements' => Database::all(
                "SELECT * FROM report_measurements
                  WHERE report_id = ?
                    AND (room_name <> '' OR model_name <> '' OR cumulative_hours IS NOT NULL
                         OR serial_no <> '' OR manufactured_ym IS NOT NULL)
                  ORDER BY sort_order, id",
                [$id]
            ),
        ];
    }

    /**
     * 用紙に載る量から、文字の詰め具合を決める。
     *
     * 概要書 2-8「測定値、報告事項の文字列が長いので工夫が必要。
     * 文字を小さくしても良いと思います」への対応。
     * 少ないときは大きく、多いときは自動で小さくして1枚に収める。
     */
    public static function sheetDensity(array $data): string
    {
        $r = $data['report'];

        $weight = count($data['parts'])
            + count($data['measurements'])
            + count($data['models'])
            + mb_strlen((string) $r['report_body']) / 38
            + mb_strlen((string) $r['work_note']) / 38
            + mb_strlen((string) $r['parts_note']) / 38;

        if ($weight <= 20) {
            return 'd1';   // ゆったり
        }
        if ($weight <= 32) {
            return 'd2';   // ふつう
        }
        return 'd3';       // 詰める
    }

    /** 2-4 の型式プルダウン。「作業内容のリストと同じ」（概要書 2-4-2） */
    public static function modelOptions(int $reportId): array
    {
        $names = array_column(Database::all(
            'SELECT model_name FROM report_models WHERE report_id = ? ORDER BY sort_order',
            [$reportId]
        ), 'model_name');

        if ($names) {
            return $names;
        }
        return array_column(Database::all(
            'SELECT name FROM machine_models WHERE deleted_at IS NULL ORDER BY sort_order, id'
        ), 'name');
    }
}
