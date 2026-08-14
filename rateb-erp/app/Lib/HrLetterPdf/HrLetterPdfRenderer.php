<?php
declare(strict_types=1);

namespace Rateb\App\Lib\HrLetterPdf;

/**
 * Renders an A4 Arabic HR letter PDF by embedding Noto Naskh Arabic (no GD required).
 */
final class HrLetterPdfRenderer
{
    private string $fontPath;
    private ?TtfCmap $cmap = null;

    public function __construct(?string $fontPath = null)
    {
        $this->fontPath = $fontPath
            ?? (__DIR__ . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'NotoNaskhArabic-Regular.ttf');
        if (!is_file($this->fontPath)) {
            throw new \RuntimeException('hr_letter_font_missing');
        }
    }

    /**
     * @param array{
     *   title:string,
     *   company_name:string,
     *   body_lines:list<string>,
     *   employee_name:string,
     *   employee_code:string,
     *   national_id:string,
     *   job_title:string,
     *   hire_date:string,
     *   salary_line:?string,
     *   request_no:string,
     *   issue_date:string,
     *   footer:string
     * } $data
     */
    public function render(array $data): string
    {
        $fontBytes = (string) file_get_contents($this->fontPath);
        if ($fontBytes === '') {
            throw new \RuntimeException('hr_letter_font_missing');
        }
        $this->cmap = new TtfCmap($fontBytes);

        $pageW = 595.28;
        $pageH = 841.89;
        $lines = [];
        $y = $pageH - 72;
        $lines[] = $this->textOp(18, $pageW / 2, $y, (string) ($data['company_name'] ?? ''), true);
        $y -= 28;
        $lines[] = $this->textOp(16, $pageW / 2, $y, (string) ($data['title'] ?? ''), true);
        $y -= 18;
        $lines[] = sprintf('%.2F %.2F m %.2F %.2F l S', 72, $y, $pageW - 72, $y);
        $y -= 28;
        foreach ($data['body_lines'] ?? [] as $bodyLine) {
            $lines[] = $this->textOp(11, $pageW / 2, $y, (string) $bodyLine, true);
            $y -= 18;
        }
        $y -= 12;
        $rows = [
            'اسم الموظف: ' . (string) ($data['employee_name'] ?? ''),
            'الرقم الوظيفي: ' . (string) ($data['employee_code'] ?? ''),
            'رقم الهوية: ' . (string) ($data['national_id'] ?? ''),
            'المسمى الوظيفي: ' . (string) ($data['job_title'] ?? ''),
            'تاريخ التعيين: ' . (string) ($data['hire_date'] ?? ''),
        ];
        if (!empty($data['salary_line'])) {
            $rows[] = 'الراتب: ' . (string) $data['salary_line'];
        }
        foreach ($rows as $row) {
            $lines[] = $this->textOp(11, $pageW - 72, $y, $row, false);
            $y -= 18;
        }
        $y -= 16;
        $lines[] = $this->textOp(
            9,
            $pageW / 2,
            $y,
            'أُعطيت هذه الشهادة بناءً على طلب الموظف دون أدنى مسؤولية على الشركة تجاه الغير.',
            true
        );
        $y -= 16;
        $lines[] = $this->textOp(
            9,
            $pageW / 2,
            $y,
            'This certificate is issued upon employee request without liability to third parties.',
            true
        );
        $y = 90;
        $lines[] = sprintf('%.2F %.2F m %.2F %.2F l S', 72, $y + 24, $pageW - 72, $y + 24);
        $lines[] = $this->textOp(9, 72, $y, 'Request: ' . (string) ($data['request_no'] ?? ''), false);
        $lines[] = $this->textOp(9, $pageW - 72, $y, 'تاريخ الإصدار: ' . (string) ($data['issue_date'] ?? ''), false);
        $y -= 18;
        $lines[] = $this->textOp(11, $pageW / 2, $y, (string) ($data['footer'] ?? 'إدارة الموارد البشرية'), true);

        $content = "q\n0.06 0.35 0.29 RG\n2 w\n36 36 " . ($pageW - 72) . ' ' . ($pageH - 72)
            . " re S\n0.7 0.75 0.72 RG\n1 w\n42 42 " . ($pageW - 84) . ' ' . ($pageH - 84)
            . " re S\n0 0 0 RG\n0 0 0 rg\n"
            . implode("\n", $lines) . "\nQ\n";

        return $this->buildPdf($content, $fontBytes, $pageW, $pageH);
    }

    private function textOp(float $size, float $x, float $y, string $text, bool $center): string
    {
        $prepared = ArabicText::prepare($text);
        $hex = $this->glyphsHex($prepared);
        $approx = max(1, mb_strlen($prepared, 'UTF-8')) * $size * 0.55;
        if ($center) {
            $x = $x - ($approx / 2);
        } elseif (preg_match('/\p{Arabic}/u', $text)) {
            $x = $x - $approx;
        }
        return sprintf(
            "BT /F1 %.2F Tf %.2F %.2F Td <%s> Tj ET",
            $size,
            $x,
            $y,
            $hex
        );
    }

    private function glyphsHex(string $text): string
    {
        $cmap = $this->cmap;
        $hex = '';
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($text, $i, 1, 'UTF-8');
            $cp = $this->utf8Codepoint($ch);
            $gid = $cmap !== null ? $cmap->glyphId($cp) : 0;
            if ($gid < 1 && $cp < 128) {
                // ASCII often maps 1:1 for some fonts; keep trying cmap only.
                $gid = $cmap !== null ? $cmap->glyphId($cp) : 0;
            }
            $hex .= sprintf('%04X', $gid > 0 ? $gid : 0);
        }
        return $hex;
    }

    private function utf8Codepoint(string $ch): int
    {
        $u = unpack('N', "\x00\x00" . mb_convert_encoding($ch, 'UTF-16BE', 'UTF-8'));
        if ($u === false) {
            return 0;
        }
        // mb may produce 2 or 4 bytes
        $bin = mb_convert_encoding($ch, 'UTF-32BE', 'UTF-8');
        $arr = unpack('N', $bin);

        return (int) ($arr[1] ?? 0);
    }

    private function buildPdf(string $content, string $fontBytes, float $pageW, float $pageH): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[3] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 6 0 R >> >> /Contents 4 0 R >>',
            $pageW,
            $pageH
        );
        $objects[4] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        $objects[5] = '<< /Type /FontDescriptor /FontName /NotoNaskhArabic /Flags 32 /FontBBox [-1000 -400 2000 1200] '
            . '/ItalicAngle 0 /Ascent 1000 /Descent -300 /CapHeight 700 /StemV 80 /FontFile2 8 0 R >>';
        $objects[6] = '<< /Type /Font /Subtype /Type0 /BaseFont /NotoNaskhArabic /Encoding /Identity-H /DescendantFonts [7 0 R] /ToUnicode 9 0 R >>';
        $objects[7] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NotoNaskhArabic /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> '
            . '/FontDescriptor 5 0 R /DW 600 /CIDToGIDMap /Identity >>';
        $objects[8] = '<< /Length ' . strlen($fontBytes) . ' /Length1 ' . strlen($fontBytes) . " >>\nstream\n" . $fontBytes . "\nendstream";
        $cmap = $this->toUnicodeCmap();
        $objects[9] = '<< /Length ' . strlen($cmap) . " >>\nstream\n" . $cmap . "\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        for ($i = 1; $i <= 9; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 10\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= 9; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size 10 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function toUnicodeCmap(): string
    {
        return "/CIDInit /ProcSet findresource begin\n"
            . "12 dict begin\nbegincmap\n"
            . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
            . "/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
            . "1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n"
            . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";
    }
}
