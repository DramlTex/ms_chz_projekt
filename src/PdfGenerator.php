<?php
declare(strict_types=1);

final class PdfGenerator
{
    /**
     * @param string[] $lines
     */
    public static function codesToPdf(array $lines): string
    {
        $sanitized = array_values(array_filter(array_map(static function ($line) {
            $value = trim((string)$line);
            return $value === '' ? null : $value;
        }, $lines)));

        if (!$sanitized) {
            $sanitized = ['Нет данных'];
        }

        $pageWidth = 595.28;  // A4 width (points)
        $pageHeight = 841.89; // A4 height (points)
        $marginLeft = 56.69;  // 20 mm
        $marginTop = 56.69;
        $leading = 16;

        $content = "BT\n/F1 12 Tf\n";
        $y = $pageHeight - $marginTop;
        foreach ($sanitized as $index => $line) {
            if ($index === 0) {
                $content .= sprintf('%.2f %.2f Td (%s) Tj\n', $marginLeft, $y, self::escapeText($line));
            } else {
                $content .= sprintf('0 %.2f Td (%s) Tj\n', -$leading, self::escapeText($line));
            }
        }
        $content .= "ET";

        $objects = [];
        $objects[] = "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj";
        $objects[] = "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj";
        $objects[] = sprintf(
            '3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>endobj',
            $pageWidth,
            $pageHeight
        );
        $objects[] = sprintf("4 0 obj<< /Length %d >>stream\n%s\nendstream\nendobj", strlen($content), $content);
        $objects[] = '5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj';

        $pdfBody = '';
        $offsets = [0];
        $position = strlen("%PDF-1.4\n");
        foreach ($objects as $object) {
            $offsets[] = $position;
            $pdfBody .= $object . "\n";
            $position += strlen($object) + 1;
        }

        $xrefStart = $position;
        $xref = "xref\n0 " . count($offsets) . "\n";
        foreach ($offsets as $offset) {
            $xref .= sprintf("%010d 00000 n \n", $offset);
        }

        $trailer = sprintf('trailer<< /Size %d /Root 1 0 R >>', count($offsets));

        $pdf = "%PDF-1.4\n" . $pdfBody . $xref . $trailer . "\nstartxref\n" . $xrefStart . "\n%%EOF";
        return $pdf;
    }

    private static function escapeText(string $text): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        return $escaped;
    }
}
