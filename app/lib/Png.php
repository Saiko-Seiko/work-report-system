<?php
/**
 * PNG の最小限の読み書き。サイン画像（透明背景の RGBA）を白地に合成して RGB にする。
 *
 * TCPDF は透明付きの PNG を扱うとき GD か Imagick を必要とする。
 * Vercel の PHP には GD が無く、さくらでも設定によっては外れていることがあるので、
 * 拡張に頼らず PHP だけで「透明を白に落とした PNG」を作れるようにしてある。
 * 対応するのはブラウザの canvas が作る形（8bit・非インターレース）。それ以外は null を返す。
 */
declare(strict_types=1);

final class Png
{
    /**
     * 透明付き PNG を白地に合成した 8bit RGB の PNG にして返す。
     * すでに透明の無い形式（RGB・グレー）ならそのまま返す。扱えない形式は null。
     */
    public static function flattenToRgb(string $bytes): ?string
    {
        if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) !== 0) {
            return null;
        }

        $pos    = 8;
        $len    = strlen($bytes);
        $ihdr   = null;
        $idat   = '';
        while ($pos + 8 <= $len) {
            $size = unpack('N', substr($bytes, $pos, 4))[1];
            $type = substr($bytes, $pos + 4, 4);
            $data = substr($bytes, $pos + 8, $size);
            $pos += 12 + $size;

            if ($type === 'IHDR') {
                $ihdr = unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccomp/Cfilter/Cinterlace', $data);
            } elseif ($type === 'IDAT') {
                $idat .= $data;
            } elseif ($type === 'IEND') {
                break;
            }
        }
        if (!$ihdr || $ihdr['depth'] !== 8 || $ihdr['interlace'] !== 0 || $idat === '') {
            return null;
        }
        if ($ihdr['color'] === 2 || $ihdr['color'] === 0) {
            return $bytes;                       // 透明が無いのでそのまま使える
        }
        $channels = ['4' => 2, '6' => 4][(string) $ihdr['color']] ?? 0;   // 4=グレー+α, 6=RGB+α
        if ($channels === 0) {
            return null;
        }

        $raw = @gzuncompress($idat);
        if ($raw === false) {
            return null;
        }

        $w      = (int) $ihdr['width'];
        $h      = (int) $ihdr['height'];
        $stride = $w * $channels;
        if (strlen($raw) < ($stride + 1) * $h) {
            return null;
        }

        $out  = '';
        $prev = str_repeat("\0", $stride);
        $p    = 0;
        for ($y = 0; $y < $h; $y++) {
            $filter = ord($raw[$p]);
            $line   = substr($raw, $p + 1, $stride);
            $p     += $stride + 1;
            $line   = self::unfilter($filter, $line, $prev, $channels);
            $prev   = $line;

            // 白地に合成して RGB にする（フィルタ 0）
            $row = "\0";
            for ($x = 0; $x < $stride; $x += $channels) {
                $a = ord($line[$x + $channels - 1]);
                if ($channels === 4) {
                    $r = ord($line[$x]);
                    $g = ord($line[$x + 1]);
                    $b = ord($line[$x + 2]);
                } else {
                    $r = $g = $b = ord($line[$x]);
                }
                $row .= chr((int) round(($r * $a + 255 * (255 - $a)) / 255))
                      . chr((int) round(($g * $a + 255 * (255 - $a)) / 255))
                      . chr((int) round(($b * $a + 255 * (255 - $a)) / 255));
            }
            $out .= $row;
        }

        return "\x89PNG\r\n\x1a\n"
            . self::chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0))
            . self::chunk('IDAT', gzcompress($out, 6))
            . self::chunk('IEND', '');
    }

    /** PNG のフィルタ（None / Sub / Up / Average / Paeth）を元に戻す */
    private static function unfilter(int $type, string $line, string $prev, int $bpp): string
    {
        if ($type === 0) {
            return $line;
        }
        $n = strlen($line);
        for ($i = 0; $i < $n; $i++) {
            $a = $i >= $bpp ? ord($line[$i - $bpp]) : 0;   // 左
            $b = ord($prev[$i]);                            // 上
            $c = $i >= $bpp ? ord($prev[$i - $bpp]) : 0;   // 左上
            $x = ord($line[$i]);
            switch ($type) {
                case 1: $x += $a; break;
                case 2: $x += $b; break;
                case 3: $x += ($a + $b) >> 1; break;
                case 4:
                    $pp = $a + $b - $c;
                    $pa = abs($pp - $a);
                    $pb = abs($pp - $b);
                    $pc = abs($pp - $c);
                    $x += ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
                    break;
            }
            $line[$i] = chr($x & 0xFF);
        }
        return $line;
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}
