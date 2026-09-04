<?php
/**
 * Word（.docx）を書き出すための最小限の道具。
 *
 * .docx は「決まった形のXMLをまとめたZIP」なので、
 * 外部ライブラリを入れなくても PHP の zip 拡張だけで作れる。
 * このプロジェクトの方針（依存を足さない）に合わせている。
 */
declare(strict_types=1);

final class DocxWriter
{
    /** @var string[] 本文のXML断片 */
    private array $body = [];

    private string $font;

    public function __construct(string $font = 'Yu Gothic')
    {
        $this->font = $font;
    }

    // ------------------------------------------------------------ 書く

    /** 表紙の大きな題名 */
    public function title(string $text, string $subtitle = ''): self
    {
        $this->body[] = $this->para($text, ['size' => 40, 'bold' => true, 'align' => 'center', 'after' => 120]);
        if ($subtitle !== '') {
            $this->body[] = $this->para($subtitle, ['size' => 22, 'align' => 'center', 'color' => '56605B', 'after' => 400]);
        }
        return $this;
    }

    public function h1(string $text): self
    {
        $this->body[] = $this->para($text, [
            'size' => 30, 'bold' => true, 'color' => '145C34',
            'before' => 400, 'after' => 160, 'border' => true,
        ]);
        return $this;
    }

    public function h2(string $text): self
    {
        $this->body[] = $this->para($text, [
            'size' => 24, 'bold' => true, 'color' => '1B7A46', 'before' => 280, 'after' => 120,
        ]);
        return $this;
    }

    public function h3(string $text): self
    {
        $this->body[] = $this->para($text, ['size' => 21, 'bold' => true, 'before' => 200, 'after' => 80]);
        return $this;
    }

    public function p(string $text): self
    {
        $this->body[] = $this->para($text, ['size' => 21, 'after' => 120]);
        return $this;
    }

    /** 手順（1. 2. 3.）。番号は呼ぶ側で振る */
    public function step(int $no, string $text): self
    {
        $this->body[] = $this->para($no . '.　' . $text, ['size' => 21, 'after' => 80, 'indent' => 340]);
        return $this;
    }

    public function bullet(string $text): self
    {
        $this->body[] = $this->para('・' . $text, ['size' => 21, 'after' => 60, 'indent' => 340]);
        return $this;
    }

    /** 注意書き。薄い色の帯にして目立たせる */
    public function note(string $text, string $kind = 'info'): self
    {
        $shade = ['info' => 'E9F3EC', 'warn' => 'FFF6E5', 'error' => 'FDECEC'][$kind] ?? 'E9F3EC';
        $color = ['info' => '0F4527', 'warn' => 'B46A00', 'error' => '97161D'][$kind] ?? '0F4527';

        $this->body[] = $this->para($text, [
            'size' => 20, 'color' => $color, 'shade' => $shade,
            'before' => 120, 'after' => 160, 'indent' => 120, 'box' => true,
        ]);
        return $this;
    }

    /** 画面の見た目を文字で表す（等幅） */
    public function screen(string $text): self
    {
        foreach (explode("\n", $text) as $line) {
            $this->body[] = $this->para($line === '' ? ' ' : $line, [
                'size' => 18, 'mono' => true, 'shade' => 'F4F6F5', 'after' => 0, 'indent' => 200,
            ]);
        }
        $this->body[] = $this->para(' ', ['size' => 12, 'after' => 120]);
        return $this;
    }

    /**
     * 表。1行目を見出しとして扱う。
     * @param string[]   $header
     * @param string[][] $rows
     * @param int[]      $widths 合計 9000 くらいになるように
     */
    public function table(array $header, array $rows, array $widths = []): self
    {
        $cols = count($header);
        if (!$widths) {
            $widths = array_fill(0, $cols, (int) (9000 / $cols));
        }

        $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/>'
             . '<w:tblW w:w="0" w:type="auto"/>'
             . '<w:tblBorders>'
             . '<w:top w:val="single" w:sz="4" w:color="D3DBD6"/>'
             . '<w:left w:val="single" w:sz="4" w:color="D3DBD6"/>'
             . '<w:bottom w:val="single" w:sz="4" w:color="D3DBD6"/>'
             . '<w:right w:val="single" w:sz="4" w:color="D3DBD6"/>'
             . '<w:insideH w:val="single" w:sz="4" w:color="D3DBD6"/>'
             . '<w:insideV w:val="single" w:sz="4" w:color="D3DBD6"/>'
             . '</w:tblBorders></w:tblPr><w:tblGrid>';
        foreach ($widths as $w) {
            $xml .= '<w:gridCol w:w="' . (int) $w . '"/>';
        }
        $xml .= '</w:tblGrid>';

        $xml .= $this->row($header, $widths, true);
        foreach ($rows as $row) {
            $xml .= $this->row($row, $widths, false);
        }
        $xml .= '</w:tbl>';

        $this->body[] = $xml;
        $this->body[] = $this->para(' ', ['size' => 12, 'after' => 120]);
        return $this;
    }

    public function pageBreak(): self
    {
        $this->body[] = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
        return $this;
    }

    // ------------------------------------------------------------ 出力

    public function save(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE) !== true) {
            throw new RuntimeException("ファイルを作れません: {$path}");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('word/_rels/document.xml.rels', $this->docRels());
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('word/document.xml', $this->document());
        $zip->close();
    }

    // ------------------------------------------------------------ 中身

    private function row(array $cells, array $widths, bool $isHeader): string
    {
        $xml = '<w:tr>';
        if ($isHeader) {
            $xml = '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
        }

        foreach ($cells as $i => $cell) {
            $shade = $isHeader ? '1B7A46' : 'FFFFFF';
            $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . (int) ($widths[$i] ?? 2000) . '" w:type="dxa"/>'
                  . '<w:shd w:val="clear" w:fill="' . $shade . '"/>'
                  . '<w:vAlign w:val="center"/></w:tcPr>';

            foreach (explode("\n", (string) $cell) as $line) {
                $xml .= $this->para($line === '' ? ' ' : $line, [
                    'size'  => 19,
                    'bold'  => $isHeader,
                    'color' => $isHeader ? 'FFFFFF' : '1C2321',
                    'after' => 0,
                ]);
            }
            $xml .= '</w:tc>';
        }
        return $xml . '</w:tr>';
    }

    /**
     * 段落ひとつ。太字にしたい部分は **ここ** のように囲む。
     */
    private function para(string $text, array $o): string
    {
        $pr = '<w:pPr>';

        $spacing = '<w:spacing';
        if (isset($o['before'])) {
            $spacing .= ' w:before="' . (int) $o['before'] . '"';
        }
        $spacing .= ' w:after="' . (int) ($o['after'] ?? 100) . '" w:line="288" w:lineRule="auto"/>';
        $pr .= $spacing;

        if (!empty($o['indent'])) {
            $pr .= '<w:ind w:left="' . (int) $o['indent'] . '"/>';
        }
        if (!empty($o['align'])) {
            $pr .= '<w:jc w:val="' . $o['align'] . '"/>';
        }
        if (!empty($o['shade'])) {
            $pr .= '<w:shd w:val="clear" w:fill="' . $o['shade'] . '"/>';
        }
        if (!empty($o['border'])) {
            $pr .= '<w:pBdr><w:bottom w:val="single" w:sz="12" w:space="4" w:color="1B7A46"/></w:pBdr>';
        }
        if (!empty($o['box'])) {
            $pr .= '<w:pBdr>'
                 . '<w:top w:val="single" w:sz="4" w:space="4" w:color="B9D8C5"/>'
                 . '<w:left w:val="single" w:sz="4" w:space="4" w:color="B9D8C5"/>'
                 . '<w:bottom w:val="single" w:sz="4" w:space="4" w:color="B9D8C5"/>'
                 . '<w:right w:val="single" w:sz="4" w:space="4" w:color="B9D8C5"/>'
                 . '</w:pBdr>';
        }
        $pr .= '</w:pPr>';

        return '<w:p>' . $pr . $this->runs($text, $o) . '</w:p>';
    }

    /** **…** で囲んだところを太字にする */
    private function runs(string $text, array $o): string
    {
        $out   = '';
        $parts = preg_split('/(\*\*.*?\*\*)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($parts as $part) {
            $bold = !empty($o['bold']);
            if (str_starts_with($part, '**') && str_ends_with($part, '**') && mb_strlen($part) > 4) {
                $bold = true;
                $part = mb_substr($part, 2, mb_strlen($part) - 4);
            }

            $font = !empty($o['mono']) ? 'Consolas' : $this->font;

            $rpr = '<w:rPr>'
                 . '<w:rFonts w:ascii="' . $font . '" w:hAnsi="' . $font . '" w:eastAsia="' . $this->font . '"/>'
                 . ($bold ? '<w:b/>' : '')
                 . '<w:color w:val="' . ($o['color'] ?? '1C2321') . '"/>'
                 . '<w:sz w:val="' . (int) ($o['size'] ?? 21) . '"/>'
                 . '<w:szCs w:val="' . (int) ($o['size'] ?? 21) . '"/>'
                 . '</w:rPr>';

            $out .= '<w:r>' . $rpr . '<w:t xml:space="preserve">' . $this->esc($part) . '</w:t></w:r>';
        }

        return $out ?: '<w:r><w:t xml:space="preserve"></w:t></w:r>';
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function document(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
             . '<w:body>' . implode('', $this->body)
             . '<w:sectPr>'
             . '<w:pgSz w:w="11906" w:h="16838"/>'                                  // A4
             . '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"'
             . ' w:header="720" w:footer="720" w:gutter="0"/>'
             . '</w:sectPr></w:body></w:document>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
             . '<w:docDefaults><w:rPrDefault><w:rPr>'
             . '<w:rFonts w:ascii="' . $this->font . '" w:hAnsi="' . $this->font . '"'
             . ' w:eastAsia="' . $this->font . '"/>'
             . '<w:sz w:val="21"/><w:szCs w:val="21"/>'
             . '</w:rPr></w:rPrDefault></w:docDefaults>'
             . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
             . '<w:name w:val="Normal"/></w:style>'
             . '<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/></w:style>'
             . '</w:styles>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
             . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
             . '<Default Extension="xml" ContentType="application/xml"/>'
             . '<Override PartName="/word/document.xml"'
             . ' ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
             . '<Override PartName="/word/styles.xml"'
             . ' ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
             . '</Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1"'
             . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"'
             . ' Target="word/document.xml"/>'
             . '</Relationships>';
    }

    private function docRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
             . '<Relationship Id="rId1"'
             . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
             . ' Target="styles.xml"/>'
             . '</Relationships>';
    }
}
