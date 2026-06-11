<?php
declare(strict_types=1);

namespace Rateb\App\Services;

final class ExportService
{
    /** @param array<int, array<string, mixed>> $rows */
    /** @param array<int, array{name:string,label?:string}> $columns */
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
            $this->htmlPrintView($columns, $rows, $title);
            return;
        }
        http_response_code(400);
        echo 'Unsupported export format';
    }

    /** @param array<int, array{name:string,label?:string}> $columns */
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
                $line[] = (string) ($row[$col['name']] ?? '');
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    /** @param array<int, array{name:string,label?:string}> $columns */
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
                $val = (string) ($row[$col['name']] ?? '');
                $type = is_numeric($val) && $val !== '' ? 'Number' : 'String';
                echo '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</Data></Cell>';
            }
            echo '</Row>';
        }
        echo '</Table></Worksheet></Workbook>';
        exit;
    }

    /** @param array<int, array{name:string,label?:string}> $columns */
    /** @param array<int, array<string, mixed>> $rows */
    private function htmlPrintView(array $columns, array $rows, string $title): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        $dir = function_exists('rateb_is_rtl') && rateb_is_rtl() ? 'rtl' : 'ltr';
        echo '<!DOCTYPE html><html lang="' . htmlspecialchars(function_exists('rateb_locale') ? rateb_locale() : 'en', ENT_QUOTES, 'UTF-8') . '" dir="' . $dir . '"><head><meta charset="UTF-8"><title>';
        echo htmlspecialchars($title !== '' ? $title : 'Export', ENT_QUOTES, 'UTF-8');
        echo '</title><style>body{font-family:Tajawal,Arial,sans-serif;margin:24px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:8px;text-align:start}th{background:#f0f0f0}@media print{.no-print{display:none}}</style></head><body>';
        if ($title !== '') {
            echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        }
        echo '<p class="no-print"><button type="button" onclick="window.print()">Print / Save as PDF</button></p>';
        echo '<table><thead><tr>';
        foreach ($columns as $col) {
            echo '<th>' . htmlspecialchars((string) ($col['label'] ?? $col['name']), ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($columns as $col) {
                echo '<td>' . htmlspecialchars((string) ($row[$col['name']] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name) ?? 'export';
        return substr($name, 0, 80);
    }
}
