<?php
/**
 * Excel（.xlsx）の読み書き。K-4 交換部品マスタのダウンロード／インポートで使う。
 *
 * .xlsx は「決まった形の XML をまとめた ZIP」なので、PhpSpreadsheet のような
 * 大きなライブラリを入れなくても PHP の zip 拡張だけで扱える
 * （さくらのレンタルサーバは Composer が使えないため、依存を足さない方針）。
 *
 * 書き出しは1シート・文字列と数値だけの素直な表。
 * 読み込みは Excel が保存した .xlsx（共有文字列・インライン文字列・数値・数式の結果）に対応する。
 */
declare(strict_types=1);

final class Xlsx
{
    // ================================================================ 書く

    /**
     * 1シートの .xlsx をバイト列で返す。
     *
     * @param string[]      $header 1行目の見出し
     * @param array<array>  $rows   2行目以降。数値は数値セル、それ以外は文字列セルになる
     * @param int[]         $widths 列幅（文字数の目安）。省略時は見出しから決める
     */
    public static function write(array $header, array $rows, string $sheetName = 'Sheet1', array $widths = []): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Excelファイルを作れませんでした。');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($header, $rows, $widths));
        $zip->close();

        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private static function sheet(array $header, array $rows, array $widths): string
    {
        // 要素の並び順は決まっている：sheetViews → sheetFormatPr → cols → sheetData → autoFilter
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
           // 1行目を固定して、スクロールしても見出しが残るようにする
           . '<sheetViews><sheetView workbookViewId="0">'
           . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
           . '</sheetView></sheetViews>'
           . '<sheetFormatPr defaultRowHeight="18"/>';

        // 列幅。指定が無ければ見出しの長さ＋余白
        $x .= '<cols>';
        foreach (array_values($header) as $i => $h) {
            $w = $widths[$i] ?? max(10, mb_strwidth((string) $h) + 6);
            $x .= sprintf('<col min="%d" max="%d" width="%s" customWidth="1"/>', $i + 1, $i + 1, $w);
        }
        $x .= '</cols><sheetData>';

        $x .= self::row(1, array_values($header), 1);   // 見出しは太字スタイル(1)
        $n  = 1;
        foreach ($rows as $row) {
            $x .= self::row(++$n, array_values($row), 0);
        }

        // 見出しにフィルタの▼を付けておく（エクセルで絞り込みやすい）
        return $x . '</sheetData>'
            . '<autoFilter ref="A1:' . self::col(count($header)) . $n . '"/>'
            . '</worksheet>';
    }

    private static function row(int $r, array $cells, int $style): string
    {
        $x = '<row r="' . $r . '">';
        foreach ($cells as $i => $v) {
            $ref = self::col($i + 1) . $r;
            if (is_int($v) || is_float($v) || (is_string($v) && $v !== '' && preg_match('/^-?\d{1,15}(\.\d+)?$/', $v) && !preg_match('/^0\d/', $v))) {
                $x .= sprintf('<c r="%s" s="%d"><v>%s</v></c>', $ref, $style, $v);
            } elseif ((string) $v === '') {
                $x .= sprintf('<c r="%s" s="%d"/>', $ref, $style);
            } else {
                $x .= sprintf('<c r="%s" s="%d" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
                    $ref, $style, self::esc((string) $v));
            }
        }
        return $x . '</row>';
    }

    /** 1 → A, 27 → AA */
    public static function col(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + $n % 26) . $s;
            $n = intdiv($n, 26);
        }
        return $s;
    }

    /** A → 1, AA → 27 */
    public static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n;
    }

    private static function esc(string $s): string
    {
        // XML で使えない制御文字は落とす
        $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::esc(mb_substr($sheetName, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /** スタイル 0＝通常、1＝見出し（太字・薄い背景） */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Yu Gothic"/></font><font><b/><sz val="11"/><name val="Yu Gothic"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8F1EC"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    // ================================================================ 読む

    /** 先頭2バイトが PK なら zip（＝.xlsx）とみなす */
    public static function looksLike(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return false;
        }
        $head = (string) fread($fh, 2);
        fclose($fh);
        return $head === 'PK';
    }

    /**
     * 最初のシートを、行ごとの文字列の配列にして返す。
     * 空セルは '' で埋め、右端の空セルは落とす。
     *
     * 1万行の部品マスタでも数秒で読めるよう、DOM に全部載せずに
     * XMLReader で頭から順に流し読みする。
     *
     * @return string[][]
     */
    public static function read(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Excelファイルとして開けませんでした。');
        }

        try {
            $sheetPath = self::firstSheetPath($zip);
            $shared    = self::sharedStrings($zip);
            $xml       = $zip->getFromName($sheetPath);
        } finally {
            $zip->close();
        }
        if ($xml === false) {
            throw new RuntimeException('Excelファイルの中にシートが見つかりませんでした。');
        }

        $reader = new XMLReader();
        $prev   = libxml_use_internal_errors(true);
        if (!$reader->XML($xml, 'UTF-8', LIBXML_NONET)) {
            libxml_use_internal_errors($prev);
            throw new RuntimeException('Excelファイルの中身を読めませんでした。');
        }

        $rows = [];
        $more = $reader->read();
        while ($more) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                $more = $reader->read();
                continue;
            }
            $r    = (int) $reader->getAttribute('r');
            $node = $reader->expand();
            $line = $node instanceof DOMElement ? self::rowValues($node, $shared) : [];
            if ($line !== []) {
                $rows[$r > 0 ? $r : count($rows) + 1] = $line;
            }
            // 子要素はもう見たので、read() ではなく next() で次の row へ飛ぶ
            $more = $reader->next();
        }
        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        ksort($rows);

        return array_values($rows);
    }

    /** <row> 1つ分を文字列の配列にする。抜けている列は '' で埋める */
    private static function rowValues(DOMElement $rowEl, array $shared): array
    {
        $cells = [];
        $colNo = 0;
        foreach ($rowEl->childNodes as $c) {
            if (!$c instanceof DOMElement || $c->localName !== 'c') {
                continue;
            }
            $ref = $c->getAttribute('r');
            if ($ref !== '' && preg_match('/^([A-Z]+)/', $ref, $m)) {
                $colNo = self::colIndex($m[1]);
            } else {
                $colNo++;
            }
            $cells[$colNo - 1] = self::cellValue($c, $shared);
        }
        if (!$cells) {
            return [];
        }

        $max  = max(array_keys($cells));
        $line = [];
        for ($i = 0; $i <= $max; $i++) {
            $line[] = $cells[$i] ?? '';
        }
        while ($line && end($line) === '') {
            array_pop($line);
        }
        return $line;
    }

    private static function cellValue(DOMElement $c, array $shared): string
    {
        $type = $c->getAttribute('t');
        $v    = null;
        $text = '';

        foreach ($c->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if ($child->localName === 'v') {
                $v = $child->textContent;
            } elseif ($child->localName === 'is') {        // インライン文字列 <is><t>…</t></is>
                $text .= $child->textContent;
            }
        }

        if ($type === 'inlineStr') {
            return $text;
        }
        if ($v === null) {
            return '';
        }
        if ($type === 's') {
            return $shared[(int) $v] ?? '';
        }
        if ($type === 'b') {
            return $v === '1' ? 'TRUE' : 'FALSE';
        }
        if (($type === '' || $type === 'n') && is_numeric($v)) {
            // 1.0E+3 のような指数表記や 12.0 を、見た目どおりの数字に戻す
            $f = (float) $v;
            if (floor($f) == $f && abs($f) < 1e15) {
                return (string) (int) $f;
            }
            return rtrim(rtrim(sprintf('%.10F', $f), '0'), '.');
        }
        return $v;   // str（数式の結果）など
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb === false || $rels === false) {
            throw new RuntimeException('Excelファイルの形式が想定と違います（workbook がありません）。');
        }

        $wbDoc = self::parse($wb);
        $sheet = $wbDoc->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'sheet')->item(0);
        if (!$sheet instanceof DOMElement) {
            throw new RuntimeException('Excelファイルにシートがありません。');
        }
        $rid = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');

        $relDoc = self::parse($rels);
        foreach ($relDoc->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/relationships', 'Relationship') as $rel) {
            /** @var DOMElement $rel */
            if ($rel->getAttribute('Id') === $rid) {
                $target = $rel->getAttribute('Target');
                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            }
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /** @return string[] */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $reader = new XMLReader();
        $prev   = libxml_use_internal_errors(true);
        $out    = [];
        if ($reader->XML($xml, 'UTF-8', LIBXML_NONET)) {
            $more = $reader->read();
            while ($more) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    $more = $reader->read();
                    continue;
                }
                $node = $reader->expand();
                // 書式付き文字列は <r><t> が複数並ぶので、<t> をすべてつなぐ
                $text = '';
                if ($node instanceof DOMElement) {
                    foreach ($node->getElementsByTagName('t') as $t) {
                        $text .= $t->textContent;
                    }
                }
                $out[] = $text;
                $more  = $reader->next();
            }
        }
        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $out;
    }
    private static function parse(string $xml): DOMDocument
    {
        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        // 外部実体は展開しない（XXE 対策。NOENT を付けないのが既定）。NONET でネットワークも切る
        $ok = $doc->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            throw new RuntimeException('Excelファイルの中身を読めませんでした。');
        }
        return $doc;
    }
}
