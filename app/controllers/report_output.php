<?php
/**
 * 2-7 完了 / 2-8 プレビュー / 2-9 印刷 / 2-10 メール送信
 *
 * 用紙（A4）は app/views/sheet/report.php の1枚だけ。
 * プレビューも印刷もメール添付も、すべてそれを使い回す。
 * 本番でPDFにするときは、同じHTMLを mPDF に渡せばよい。
 */
declare(strict_types=1);

// ================================================================ 2-7 完了画面

function report_done(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);

    view('user/report_done', [
        'report'    => $report,
        'mailCount' => (int) Database::value(
            'SELECT COUNT(*) FROM mail_logs WHERE report_id = ?', [$report['id']]
        ),
        'title'     => '完了',
    ], 'layout_user');
}

// ================================================================ 用紙そのもの

/**
 * A4の中身。プレビュー・印刷の iframe から読み込まれる。
 * 直接開かれても困らないが、通常は 2-8 / 2-9 から見る。
 */
function report_sheet(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);

    $data = Report::sheetData($report);

    // 一覧の「PDF ●」は、提出用の紙が起こされたかどうかで決まる
    if (!$report['pdf_at']) {
        Report::touch((int) $report['id'], ['pdf_at' => now()]);
        $report['pdf_at'] = now();
        $data['report']   = $report;
    }

    view('sheet/report', $data + [
        'density'  => Report::sheetDensity($data),
        'forPrint' => query('print') === '1',
    ]);
}

// ================================================================ 2-8 プレビュー

function report_preview(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);

    view('user/report_preview', [
        'report' => $report,
        'mode'   => 'preview',
        'title'  => 'プレビュー',
    ]);
}

// ================================================================ 2-9 印刷

function report_print(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);

    view('user/report_preview', [
        'report' => $report,
        'mode'   => 'print',
        'title'  => '印刷',
    ]);
}

// ================================================================ 2-10 メール送信

function report_mail(array $p): void
{
    $user   = Auth::requireUser();
    $report = Report::findOwned((int) $p['id'], $user);
    $id     = (int) $report['id'];

    $last = Database::one(
        'SELECT * FROM mail_logs WHERE report_id = ? ORDER BY id DESC', [$id]
    );

    $form = [
        'to'      => (string) ($last['to_addr'] ?? ''),
        'subject' => (string) ($last['subject'] ?? config('mail.default_subject')),
        'cc'      => (string) ($last['cc_addr'] ?? (string) $user['email']),
        'body'    => (string) ($last['body'] ?? report_mail_template($report, $user)),
    ];
    $errors = [];
    $sent   = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        $form = [
            'to'      => trim((string) post('to', '')),
            'subject' => trim((string) post('subject', '')),
            'cc'      => trim((string) post('cc', '')),
            'body'    => (string) post('body', ''),
        ];

        $errors = report_validate_mail($form);

        if (!$errors) {
            Database::insert('mail_logs', [
                'report_id'  => $id,
                'kind'       => 'report',
                'to_addr'    => mb_substr($form['to'], 0, 255),
                'cc_addr'    => mb_substr($form['cc'], 0, 512),
                'subject'    => mb_substr($form['subject'], 0, 255),
                'body'       => mb_substr($form['body'], 0, 8000),
                'is_dry_run' => config('mail.dry_run') ? 1 : 0,
                'sent_at'    => now(),
            ]);

            Report::touch($id, [
                'mail_count' => (int) $report['mail_count'] + 1,
                'pdf_at'     => $report['pdf_at'] ?: now(),
            ]);

            audit('report_mailed', 'reports:' . $id, $form['to']);
            $sent   = true;
            $report = Report::findOwned($id, $user);
        }
    }

    view('user/report_mail', [
        'report' => $report,
        'form'   => $form,
        'errors' => $errors,
        'sent'   => $sent,
        'title'  => 'メール送信',
    ]);
}

/**
 * 送信先の形式だけは必ず見る（概要書 2-10-3「xxx@xxx.xx形式チェックはしたい」）。
 * CCは読点・カンマ・空白区切りで複数入れられる。
 */
function report_validate_mail(array $f): array
{
    $e = [];

    if ($f['to'] === '') {
        $e['to'] = '送信先のメールアドレスを入れてください。';
    } elseif (!report_is_email($f['to'])) {
        $e['to'] = 'メールアドレスの形式が正しくありません（例：taro@example.co.jp）。';
    }

    if ($f['subject'] === '') {
        $e['subject'] = '件名を入れてください。';
    }

    foreach (report_split_addresses($f['cc']) as $cc) {
        if (!report_is_email($cc)) {
            $e['cc'] = '「' . $cc . '」はメールアドレスの形式になっていません。';
            break;
        }
    }

    return $e;
}

function report_is_email(string $address): bool
{
    return (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
}

/** @return string[] */
function report_split_addresses(string $text): array
{
    return array_values(array_filter(array_map(
        'trim',
        preg_split('/[,、;\s]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []
    ), fn($s) => $s !== ''));
}

/** 本文のたたき台。そのまま送っても失礼にならない文面にしておく */
function report_mail_template(array $report, array $user): string
{
    return implode("\n", [
        (string) $report['hospital_name'] . ' 御中',
        '',
        'いつもお世話になっております。',
        (string) $user['company_name'] . 'です。',
        '',
        sprintf(
            '%s に実施いたしました作業の完了報告書をお送りいたします。',
            ymd_ja((string) $report['work_date'], false)
        ),
        '',
        '　作業場所：' . (string) $report['work_place'],
        '　作業件名：' . (string) $report['work_title'],
        '',
        'ご確認のほど、よろしくお願いいたします。',
    ]);
}
