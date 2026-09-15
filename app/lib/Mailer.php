<?php
/**
 * メール送信（2-10）。PDFを添付して PHP の mail() で送る。
 *
 * さくらのレンタルサーバでは mail() がそのまま sendmail 経由で配信されるので、
 * 外部ライブラリ（PHPMailer 等）は使っていない。MIME の組み立てはここで行う。
 *
 * config('mail.dry_run') が true のときは配信せず、送るはずだった内容を
 * .eml ファイルとして data/tmp に残す（Outlook 等で開いて確認できる）。
 */
declare(strict_types=1);

final class Mailer
{
    /**
     * @param array $msg  to, cc(配列), reply_to, subject, body,
     *                    attachments: [['name' => 'x.pdf', 'bytes' => '...', 'type' => 'application/pdf'], ...]
     * @return string|null 送れたら null、失敗したら理由
     */
    public static function send(array $msg): ?string
    {
        $mail = self::build($msg);

        if (config('mail.dry_run')) {
            self::keepCopy($mail);
            return null;
        }

        $from = (string) config('mail.from_address');
        $ok   = @mail(
            $mail['to'],
            $mail['subject'],
            $mail['body'],
            $mail['headers'],
            // Return-Path（配信エラーの戻り先）を差出人に揃える
            preg_match('/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+$/', $from) ? '-f ' . $from : ''
        );

        if (!$ok) {
            $err = error_get_last();
            return 'メールを送信できませんでした。'
                . ($err && config('debug') ? '（' . $err['message'] . '）' : '');
        }

        return null;
    }

    /**
     * mail() に渡す形（宛先・件名・本文・ヘッダ）に組み立てる。
     * 送信はしない。dry_run の記録やテストでも使う。
     *
     * @return array{to:string, subject:string, body:string, headers:array<string,string>, raw:string}
     */
    public static function build(array $msg): array
    {
        $boundary = '=_wcr_' . bin2hex(random_bytes(12));
        $eol      = "\n";

        $headers = [
            'From'         => self::address((string) config('mail.from_address'), (string) config('mail.from_name')),
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/mixed; boundary="' . $boundary . '"',
            'X-Mailer'     => self::encodeWord((string) config('app_name')),
        ];

        $cc = array_values(array_filter((array) ($msg['cc'] ?? []), fn($a) => (string) $a !== ''));
        if ($cc) {
            $headers['Cc'] = implode(', ', array_map(fn($a) => self::address((string) $a), $cc));
        }
        if (!empty($msg['reply_to'])) {
            $headers['Reply-To'] = self::address((string) $msg['reply_to']);
        }

        // 本文（UTF-8 / base64。日本語を含んでも配信経路で化けない）
        $text = str_replace(["\r\n", "\r"], "\n", (string) ($msg['body'] ?? ''));
        $body = '--' . $boundary . $eol
            . 'Content-Type: text/plain; charset=UTF-8' . $eol
            . 'Content-Transfer-Encoding: base64' . $eol . $eol
            . chunk_split(base64_encode($text), 76, $eol);

        foreach ((array) ($msg['attachments'] ?? []) as $file) {
            $name = (string) ($file['name'] ?? 'attachment.bin');
            $type = (string) ($file['type'] ?? 'application/octet-stream');
            $body .= '--' . $boundary . $eol
                . 'Content-Type: ' . $type . '; name="' . self::encodeWord($name, false) . '"' . $eol
                . 'Content-Transfer-Encoding: base64' . $eol
                . 'Content-Disposition: attachment; filename="' . self::encodeWord($name, false) . '";'
                . $eol . " filename*=UTF-8''" . rawurlencode($name) . $eol . $eol
                . chunk_split(base64_encode((string) ($file['bytes'] ?? '')), 76, $eol);
        }
        $body .= '--' . $boundary . '--' . $eol;

        $to      = self::address((string) $msg['to']);
        $subject = self::encodeWord((string) ($msg['subject'] ?? ''));

        $raw = 'To: ' . $to . $eol . 'Subject: ' . $subject . $eol
            . 'Date: ' . date('r') . $eol;
        foreach ($headers as $k => $v) {
            $raw .= $k . ': ' . $v . $eol;
        }
        $raw .= $eol . $body;

        return [
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
            'headers' => $headers,
            'raw'     => $raw,
        ];
    }

    /** dry_run のときに、送るはずだった内容を .eml として残す */
    private static function keepCopy(array $mail): void
    {
        $dir = (string) config('storage.tmp') . '/mail';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        // .eml は CRLF 区切りが慣例
        $raw = (string) preg_replace('/\r?\n/', "\r\n", $mail['raw']);
        @file_put_contents($dir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.eml', $raw);
    }

    /** "名前 <addr>" の形。名前に日本語があれば RFC 2047 でくるむ */
    public static function address(string $address, string $name = ''): string
    {
        $address = trim($address);
        $name    = trim($name);
        if ($name === '') {
            return $address;
        }

        return self::encodeWord($name) . ' <' . $address . '>';
    }

    /**
     * ヘッダに日本語を入れるための =?UTF-8?B?...?= 形式。
     * ASCII だけならそのまま。長いときは文字の途中で切れないよう分割する
     * （$fold = false のときは分割しない。引用符の中に入れる添付ファイル名用）。
     */
    public static function encodeWord(string $s, bool $fold = true): string
    {
        if ($s === '' || !preg_match('/[^\x20-\x7E]/', $s)) {
            return $s;
        }
        if (!$fold) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }

        $words = [];
        $chunk = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (strlen($chunk . $ch) > 42) {          // base64 後に 75 文字以内に収まる
                $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
                $chunk   = '';
            }
            $chunk .= $ch;
        }
        if ($chunk !== '') {
            $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
        }

        // 折り返しは RFC 822 の CRLF + 空白。PHP の mail() はこの形だけを折り返しとして通す
        return implode("\r\n ", $words);
    }
}
