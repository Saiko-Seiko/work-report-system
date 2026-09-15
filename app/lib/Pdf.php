<?php
/**
 * PDF の作成（概要書 9章「確定した報告書はPDF等で出力・保存」）。
 *
 * TCPDF 6 系を app/vendor/tcpdf に同梱している。Composer は使わない。
 * 日本語は IPAex ゴシック・明朝を埋め込む（IPAフォントライセンス、再配布可）ので、
 * 受け取った側の端末にフォントが無くても同じ見た目で開ける。
 *
 * 用紙の型紙は app/views/pdf/ にある。プレビュー・印刷・メール添付・保存の
 * すべてがこの1枚から作られるので、見た目がずれない。
 */
declare(strict_types=1);

final class Pdf
{
    private static bool $booted = false;

    /** TCPDF を読み込む。設定は外部ファイルではなくここで決める */
    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $base = APP_ROOT . '/app/vendor/tcpdf/';
        $defs = [
            'K_TCPDF_EXTERNAL_CONFIG'      => true,
            'K_PATH_MAIN'                  => $base,
            'K_PATH_FONTS'                 => $base . 'fonts/',
            'K_PATH_URL'                   => '',
            'K_PATH_IMAGES'                => $base,
            'K_BLANK_IMAGE'                => '',
            'PDF_PAGE_FORMAT'              => 'A4',
            'PDF_PAGE_ORIENTATION'         => 'P',
            'PDF_CREATOR'                  => config('app_name'),
            'PDF_AUTHOR'                   => config('company_name'),
            'PDF_HEADER_TITLE'             => '',
            'PDF_HEADER_STRING'            => '',
            'PDF_HEADER_LOGO'              => '',
            'PDF_HEADER_LOGO_WIDTH'        => 0,
            'PDF_UNIT'                     => 'mm',
            'PDF_MARGIN_HEADER'            => 0,
            'PDF_MARGIN_FOOTER'            => 0,
            'PDF_MARGIN_TOP'               => 11,
            'PDF_MARGIN_BOTTOM'            => 10,
            'PDF_MARGIN_LEFT'              => 12,
            'PDF_MARGIN_RIGHT'             => 12,
            'PDF_FONT_NAME_MAIN'           => 'ipaexg',
            'PDF_FONT_SIZE_MAIN'           => 10,
            'PDF_FONT_NAME_DATA'           => 'ipaexg',
            'PDF_FONT_SIZE_DATA'           => 8,
            'PDF_FONT_MONOSPACED'          => 'courier',
            'PDF_IMAGE_SCALE_RATIO'        => 1.25,
            'HEAD_MAGNIFICATION'           => 1.1,
            'K_CELL_HEIGHT_RATIO'          => 1.25,
            'K_TITLE_MAGNIFICATION'        => 1.3,
            'K_SMALL_RATIO'                => 2 / 3,
            'K_THAI_TOPCHARS'              => true,
            'K_TCPDF_CALLS_IN_HTML'        => false,
            'K_TCPDF_THROW_EXCEPTION_ERROR' => true,
            'K_TIMEZONE'                   => 'Asia/Tokyo',
        ];
        foreach ($defs as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }

        require_once $base . 'tcpdf.php';
    }

    /**
     * 報告書1件からPDFを作る（データの取り出し込み）。
     * 画面・印刷・メール添付・管理者画面のどこから呼んでも同じ1枚になる。
     */
    public static function forReport(array $report): string
    {
        $signature = '';
        if (!empty($report['signature_file'])) {
            $path = (string) config('storage.signatures') . '/' . (string) $report['signature_file'];
            if (is_file($path)) {
                $signature = self::flatSignature($path);
            }
        }

        return self::report(Report::sheetData($report), $signature);
    }

    /**
     * サイン画像（透明背景）を白地の PNG にして、その置き場所を返す。
     *
     * TCPDF は透明付き PNG に GD か Imagick を要求するが、サーバーによっては無い。
     * 先に PHP だけで白地に落とし（Png::flattenToRgb）、できなければ GD、
     * それも無ければ元のファイルをそのまま渡す。結果は data/tmp に控えて使い回す。
     */
    private static function flatSignature(string $path): string
    {
        $dir = (string) config('storage.tmp') . '/sig';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $flat = $dir . '/' . md5_file($path) . '.png';
        if (is_file($flat)) {
            return $flat;
        }

        $bytes = Png::flattenToRgb((string) file_get_contents($path));

        if ($bytes === null && function_exists('imagecreatefrompng')) {
            $src = @imagecreatefrompng($path);
            if ($src) {
                $w   = imagesx($src);
                $h   = imagesy($src);
                $dst = imagecreatetruecolor($w, $h);
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
                ob_start();
                imagepng($dst);
                $bytes = (string) ob_get_clean();
                imagedestroy($src);
                imagedestroy($dst);
            }
        }

        if ($bytes === null || @file_put_contents($flat, $bytes) === false) {
            return $path;
        }

        return $flat;
    }

    /** 社内用1件からPDFを作る */
    public static function forInternal(array $report, array $internal): string
    {
        return self::internal(InternalReport::sheetData($internal) + ['report' => $report]);
    }

    /**
     * ブラウザに見せる・メールに添付するときのファイル名。No.が入っていれば取り違えない。
     * $ascii = true は、日本語のファイル名を読めない古い環境向けの英数字だけの名前。
     */
    public static function fileName(string $kind, array $report, bool $ascii = false): string
    {
        $no = (int) $report['report_no'];
        if ($ascii) {
            return ($kind === 'internal' ? 'internal' : 'report') . "_No{$no}.pdf";
        }

        return $kind === 'internal'
            ? "社内用_作業完了報告書_No{$no}.pdf"
            : "作業完了報告書_No{$no}.pdf";
    }

    /**
     * 客先提出用の報告書（2-8）。
     * @return string PDF のバイト列
     */
    public static function report(array $data, string $signaturePath = ''): string
    {
        $data['density']       = Report::sheetDensity($data);
        $data['signaturePath'] = $signaturePath;

        return self::render('pdf/report', $data, '作業完了報告書 No.' . (int) $data['report']['report_no']);
    }

    /**
     * 社内用の報告書（4-7）。
     */
    public static function internal(array $data): string
    {
        $in = $data['internal'];
        $weight = count($data['parts'])
            + mb_strlen((string) $in['remaining_work']) / 34
            + mb_strlen((string) $in['sales_approach']) / 34
            + mb_strlen((string) $in['remarks']) / 34;
        $data['density'] = $weight <= 18 ? 'd1' : ($weight <= 30 ? 'd2' : 'd3');

        return self::render('pdf/internal', $data,
            '社内用 作業完了報告書 No.' . (int) $data['report']['report_no']);
    }

    /**
     * 型紙（app/views/pdf/*.php）を TCPDF に流し込んで1枚にする。
     */
    private static function render(string $template, array $data, string $title): string
    {
        self::boot();

        $html = (function () use ($template, $data) {
            extract($data, EXTR_SKIP);
            ob_start();
            require APP_ROOT . '/app/views/' . $template . '.php';
            return (string) ob_get_clean();
        })();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator((string) config('app_name'));
        $pdf->SetAuthor((string) config('company_name'));
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 11, 12);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->setCellPaddings(1.2, 0.8, 1.2, 0.8);
        $pdf->setFontSubsetting(true);
        $pdf->SetFont('ipaexg', '', 10);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    /**
     * data/pdf に保存して、ファイル名を返す。
     *
     *   $keep = false … 最新版として上書き（report_1001.pdf）
     *   $keep = true  … メールで送った分など、日時を付けてそのまま残す
     */
    public static function store(string $bytes, string $prefix, int $reportNo, bool $keep = false): string
    {
        $dir = (string) config('storage.pdf');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $name = $keep
            ? sprintf('%s_%d_%s.pdf', $prefix, $reportNo, date('Ymd_His'))
            : sprintf('%s_%d.pdf', $prefix, $reportNo);

        if (file_put_contents($dir . '/' . $name, $bytes) === false) {
            throw new RuntimeException('PDFを保存できませんでした: ' . $dir);
        }

        return $name;
    }

    /** 保存済みPDFのフルパス。無ければ null */
    public static function path(?string $name): ?string
    {
        if ($name === null || $name === '' || basename($name) !== $name) {
            return null;
        }
        $path = (string) config('storage.pdf') . '/' . $name;

        return is_file($path) ? $path : null;
    }

    /**
     * ブラウザへ返す。$download が true なら「保存」の挙動、false なら画面に表示。
     * $ascii は日本語名を読めない環境向けの予備の名前（省略時は英数字だけ残した名前）。
     */
    public static function send(string $bytes, string $filename, bool $download, string $ascii = ''): void
    {
        if ($ascii === '') {
            $ascii = trim((string) preg_replace('/[^A-Za-z0-9.-]+/', '_', $filename), '_');
            if (!preg_match('/[A-Za-z0-9]/', $ascii)) {
                $ascii = 'report.pdf';
            }
        }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($bytes));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header(sprintf(
            "Content-Disposition: %s; filename=\"%s\"; filename*=UTF-8''%s",
            $download ? 'attachment' : 'inline',
            $ascii,
            rawurlencode($filename)
        ));
        echo $bytes;
        exit;
    }

    /** TCPDF に渡すHTMLの中で、改行を <br> にしてエスケープする */
    public static function text(?string $s): string
    {
        return nl2br(htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    }
}
