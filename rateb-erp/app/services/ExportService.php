<?php
declare(strict_types=1);

namespace Rateb\App\Services;

final class ExportService
{
    /** @param array<int, array<string, mixed>> $rows */
    /** @param array<int, array{name:string,label?:string,type?:string}> $columns */
    public function download(string $format, string $filename, array $columns, array $rows, string $title = ''): void
    {
        $format = strtolower($format);
        if ($format === 'csv') {
            $this->csv($filename, $columns, $rows);
            return;
        }
        if ($format === 'excel' || $format === 'xls') {
            $this->excelSpreadsheetMl($filename, $columns, $rows, $title);
            return;
        }
        if ($format === 'pdf' || $format === 'print') {
            $this->htmlPrintView($columns, $rows, $title, $format === 'pdf');
            return;
        }
        http_response_code(400);
        echo 'Unsupported export format';
    }

    /** @param array<int, array{name:string,label?:string,type?:string}> $columns */
    /** @param array<int, array<string, mixed>> $rows */
    private function csv(string $filename, array $columns, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            return;
        }
        fputcsv($out, array_map(static fn (array $c): string => (string) ($c['label'] ?? $c['name']), $columns));
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = $this->cellText($row, $col);
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    /** @param array<int, array{name:string,label?:string,type?:string}> $columns */
    /** @param array<int, array<string, mixed>> $rows */
    private function excelSpreadsheetMl(string $filename, array $columns, array $rows, string $title): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '.xls"');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<?mso-application progid="Excel.Sheet"?>';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        echo '<Worksheet ss:Name="Export"><Table>';
        if ($title !== '') {
            echo '<Row><Cell ss:MergeAcross="' . max(count($columns) - 1, 0) . '"><Data ss:Type="String">';
            echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            echo '</Data></Cell></Row>';
        }
        echo '<Row>';
        foreach ($columns as $col) {
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars((string) ($col['label'] ?? $col['name']), ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
        }
        echo '</Row>';
        foreach ($rows as $row) {
            echo '<Row>';
            foreach ($columns as $col) {
                $val = $this->cellText($row, $col);
                $type = is_numeric(str_replace([',', ' '], '', $val)) && $val !== '' ? 'Number' : 'String';
                echo '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
            }
            echo '</Row>';
        }
        echo '</Table></Worksheet></Workbook>';
        exit;
    }

    /** @param array<int, array{name:string,label?:string,type?:string}> $columns */
    /** @param array<int, array<string, mixed>> $rows */
    private function htmlPrintView(array $columns, array $rows, string $title, bool $autoPrint): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $locale = function_exists('rateb_locale') ? rateb_locale() : 'ar';
        $rtl = function_exists('rateb_is_rtl') ? rateb_is_rtl() : ($locale === 'ar');
        $dir = $rtl ? 'rtl' : 'ltr';
        $alignStart = $rtl ? 'right' : 'left';
        $alignEnd = $rtl ? 'left' : 'right';
        $pageTitle = $title !== '' ? $title : 'Export';
        $generated = date('Y-m-d H:i');
        $noRecords = function_exists('__') ? __('no_records') : 'No records';
        $printHint = function_exists('__') ? __('export_pdf_print_hint') : 'Use Print → Save as PDF';
        $generatedLabel = function_exists('__') ? __('report_generated_at') : 'Generated';

        echo '<!DOCTYPE html><html lang="' . htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') . '" dir="' . $dir . '"><head>';
        echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">';
        echo '<style>';
        echo '*,*::before,*::after{box-sizing:border-box}';
        echo 'body{font-family:"Tajawal",Tahoma,Arial,sans-serif;margin:0;padding:24px;color:#1a3354;background:#fff;line-height:1.5;direction:' . $dir . '}';
        echo '.rateb-export-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:20px;border-bottom:2px solid #1a5fb4;padding-bottom:12px}';
        echo '.rateb-export-head h1{margin:0;font-size:1.35rem;font-weight:700}';
        echo '.rateb-export-meta{font-size:.85rem;color:#5c6b7a}';
        echo '.rateb-export-actions{margin-bottom:16px;display:flex;flex-wrap:wrap;gap:8px}';
        echo '.rateb-export-actions button{padding:.45rem 1rem;border:1px solid #1a5fb4;background:#1a5fb4;color:#fff;border-radius:6px;cursor:pointer;font-family:inherit;font-size:.9rem}';
        echo '.rateb-export-actions .hint{font-size:.85rem;color:#5c6b7a;align-self:center}';
        echo 'table{border-collapse:collapse;width:100%;font-size:.88rem}';
        echo 'th,td{border:1px solid #b8cfe8;padding:8px 10px;vertical-align:top;text-align:' . $alignStart . '}';
        echo 'th{background:#e8f1fb;font-weight:700}';
        echo 'td.rateb-num{text-align:' . $alignEnd . ';direction:ltr;unicode-bidi:embed;font-variant-numeric:tabular-nums}';
        echo 'tr:nth-child(even) td{background:#f8fbff}';
        echo '.rateb-empty td{text-align:center;color:#5c6b7a;padding:24px;font-style:italic}';
        echo '.rateb-export-foot{margin-top:16px;font-size:.8rem;color:#5c6b7a;text-align:' . $alignEnd . '}';
        echo '@media print{body{padding:12px}.no-print{display:none!important}@page{margin:12mm}}';
        echo '</style></head><body>';

        echo '<div class="rateb-export-head">';
        echo '<h1>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<div class="rateb-export-meta">' . htmlspecialchars($generatedLabel, ENT_QUOTES, 'UTF-8') . ': <span dir="ltr">' . htmlspecialchars($generated, ENT_QUOTES, 'UTF-8') . '</span></div>';
        echo '</div>';

        echo '<div class="rateb-export-actions no-print">';
        echo '<button type="button" onclick="window.print()">' . htmlspecialchars(function_exists('__') ? __('print_save_pdf') : 'Print / Save as PDF', ENT_QUOTES, 'UTF-8') . '</button>';
        echo '<span class="hint">' . htmlspecialchars($printHint, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</div>';

        echo '<table><thead><tr>';
        foreach ($columns as $col) {
            $type = (string) ($col['type'] ?? '');
            $thClass = in_array($type, ['money', 'number'], true) ? ' class="rateb-num"' : '';
            echo '<th' . $thClass . '>' . htmlspecialchars((string) ($col['label'] ?? $col['name']), ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr class="rateb-empty"><td colspan="' . count($columns) . '">' . htmlspecialchars($noRecords, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        } else {
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($columns as $col) {
                    $type = (string) ($col['type'] ?? '');
                    $tdClass = in_array($type, ['money', 'number'], true) ? ' class="rateb-num"' : '';
                    echo '<td' . $tdClass . '>' . htmlspecialchars($this->cellText($row, $col), ENT_QUOTES, 'UTF-8') . '</td>';
                }
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '<div class="rateb-export-foot">' . htmlspecialchars($generatedLabel, ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($generated, ENT_QUOTES, 'UTF-8') . '</div>';

        if ($autoPrint) {
            echo '<script>window.addEventListener("load",function(){setTimeout(function(){window.print()},400)});</script>';
        }

        echo '</body></html>';
        exit;
    }

    /** @param array<string, mixed> $row */
    /** @param array{name:string,label?:string,type?:string} $col */
    private function cellText(array $row, array $col): string
    {
        $val = $row[$col['name']] ?? '';
        $type = (string) ($col['type'] ?? '');
        if ($type === 'money' || $type === 'number') {
            if ($val === '' || $val === null) {
                return '0.00';
            }
            if (is_numeric($val)) {
                return number_format((float) $val, 2);
            }
        }

        return (string) $val;
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name) ?? 'export';

        return substr($name, 0, 80);
    }
}
