<?php
/**
 * 社内用報告書（4-1〜4-8）。
 *
 * 客先に出した報告書1件に対して1枚だけ作る。
 * 概要書 4-1「客先提出済で入力された内容をデフォルトで表示し、修正できるようにする」
 * のとおり、最初に開いたときに提出済みの内容を写してから編集させる。
 */
declare(strict_types=1);

final class InternalReport
{
    /** 4-1〜4-5 の並び。6（備考）は5と同じ画面にある */
    public const STEPS = [
        'basic'  => ['no' => '1', 'label' => '基本情報'],
        'remain' => ['no' => '2', 'label' => '今回作業時の残作業'],
        'parts'  => ['no' => '3', 'label' => '再手配の必要な部材'],
        'hours'  => ['no' => '4', 'label' => '移動および作業時間の推移'],
        'sales'  => ['no' => '5', 'label' => '客先への営業アプローチ'],
    ];

    /** 移動と作業の時間。往路・作業・復路の3区間 */
    public const SPANS = [
        'travel_out'  => ['label' => '移動（往）', 'mark' => 'I'],
        'work'        => ['label' => '作業',       'mark' => 'S'],
        'travel_back' => ['label' => '移動（復）', 'mark' => 'I'],
    ];

    /**
     * その報告書の社内用を返す。無ければ提出済みの内容を写して作る。
     */
    public static function findOrCreate(array $report): array
    {
        $existing = Database::one(
            'SELECT * FROM internal_reports WHERE report_id = ?', [$report['id']]
        );
        if ($existing) {
            return $existing;
        }

        return Database::transaction(function () use ($report) {
            $id = Database::insert('internal_reports', [
                'report_id'     => $report['id'],
                'created_date'  => $report['created_date'] ?: date('Y-m-d'),
                'hospital_name' => $report['hospital_name'],
                'work_date'     => $report['work_date'],
                'work_place'    => $report['work_place'],
                'workers_text'  => $report['workers_text'],
                'work_title'    => $report['work_title'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // 再手配の必要な部材は、まず交換した部品をそのまま候補として並べる
            foreach (Database::all(
                'SELECT rp.part_id, rp.part_name, rp.unit, rp.qty
                   FROM report_parts rp
                   LEFT JOIN parts p ON p.id = rp.part_id
                  WHERE rp.report_id = ? AND rp.qty > 0
                  ORDER BY p.priority DESC, p.kana, rp.id',
                [$report['id']]
            ) as $i => $row) {
                Database::insert('internal_report_parts', [
                    'internal_report_id' => $id,
                    'part_id'            => $row['part_id'],
                    'part_name'          => $row['part_name'],
                    'unit'               => $row['unit'],
                    'qty'                => (int) $row['qty'],
                    'sort_order'         => ($i + 1) * 10,
                ]);
            }

            audit('internal_created', 'internal_reports:' . $id, 'report:' . $report['id']);

            return Database::one('SELECT * FROM internal_reports WHERE id = ?', [$id]);
        });
    }

    public static function touch(int $internalId, array $extra = []): void
    {
        Database::update('internal_reports', $extra + ['updated_at' => now()],
            'id = :id', ['id' => $internalId]);
    }

    /** どこまで入力できているか。ステップ表示の色分けに使う */
    public static function progress(array $internal): array
    {
        $id = (int) $internal['id'];

        return [
            'basic'  => (bool) ($internal['created_date'] && $internal['hospital_name']
                && $internal['work_date'] && $internal['work_place']
                && $internal['workers_text'] && $internal['work_title']),
            'remain' => trim((string) $internal['remaining_work']) !== '',
            'parts'  => (int) Database::value(
                'SELECT COUNT(*) FROM internal_report_parts WHERE internal_report_id = ? AND qty > 0',
                [$id]
            ) > 0,
            'hours'  => (bool) ($internal['travel_out_from'] || $internal['work_from']
                || $internal['travel_back_from']),
            'sales'  => trim((string) $internal['sales_approach']) !== ''
                || trim((string) $internal['remarks']) !== '',
        ];
    }

    /** 用紙1枚を組むのに必要なもの */
    public static function sheetData(array $internal): array
    {
        return [
            'internal' => $internal,
            'parts'    => Database::all(
                'SELECT part_name, unit, qty FROM internal_report_parts
                  WHERE internal_report_id = ? AND qty > 0 ORDER BY sort_order, id',
                [$internal['id']]
            ),
        ];
    }

    /**
     * 「12:30」の形かどうか。空欄は許す（まだ入れていない区間があるため）
     */
    public static function isTime(string $value): bool
    {
        return $value === '' || (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value);
    }

    /** 区間の長さを分で返す。片方でも空なら null */
    public static function spanMinutes(?string $from, ?string $to): ?int
    {
        $from = (string) $from;
        $to   = (string) $to;
        if ($from === '' || $to === '' || !self::isTime($from) || !self::isTime($to)) {
            return null;
        }

        $minutes = self::minutes($to) - self::minutes($from);
        if ($minutes < 0) {
            $minutes += 24 * 60;   // 日付をまたいだ場合
        }
        return $minutes;
    }

    /**
     * 区間の長さを「1時間30分」の形で返す。
     * 請求のもとになる数字なので、打ち間違いにその場で気づけるよう画面と紙の両方に出す。
     */
    public static function span(?string $from, ?string $to): ?string
    {
        $minutes = self::spanMinutes($from, $to);
        return $minutes === null ? null : self::formatMinutes($minutes);
    }

    /** 複数区間の合計。どれも入っていなければ「－」 */
    public static function totalLabel(array $minutes): string
    {
        $values = array_filter($minutes, fn($m) => $m !== null);
        if (!$values) {
            return '－';
        }
        return self::formatMinutes((int) array_sum($values));
    }

    public static function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h === 0) {
            return "{$m}分";
        }
        return $m === 0 ? "{$h}時間" : "{$h}時間{$m}分";
    }

    private static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        return $h * 60 + $m;
    }
}
