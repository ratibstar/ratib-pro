<?php
declare(strict_types=1);

namespace App\Accounting\Admin\Services;

/**
 * Phase 7 export helpers — CSV, JSON, Excel-compatible, PDF (HTML print).
 */
final class AccountingControlExportService
{
    public function __construct(
        ?AccountingControlService $core = null,
        ?AccountingControlPhase7Service $phase7 = null,
    ) {
        $this->core = $core ?? new AccountingControlService();
        $this->phase7 = $phase7 ?? new AccountingControlPhase7Service($this->core);
    }

    private AccountingControlService $core;
    private AccountingControlPhase7Service $phase7;

    /**
     * @param array<string, mixed> $filters
     * @param list<array<string, mixed>> $rows
     */
    public function streamCsv(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new \RuntimeException('Unable to open CSV stream');
        }
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $row[$h] ?? '';
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function streamJson(string $filename, array $data): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '.json"');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Excel-compatible tab-separated export.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function streamExcel(string $filename, array $headers, array $rows): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $this->safeFilename($filename) . '.xls"');
        echo "\xEF\xBB\xBF";
        echo implode("\t", $headers) . "\n";
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $h) {
                $cells[] = str_replace(["\t", "\n", "\r"], ' ', (string) ($row[$h] ?? ''));
            }
            echo implode("\t", $cells) . "\n";
        }
        exit;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportResource(string $resource, string $format, array $filters): void
    {
        $format = strtolower($format);
        $data = $this->collectExportData($resource, $filters);
        $filename = 'accounting-' . $resource . '-' . date('Ymd-His');

        if ($format === 'json') {
            $this->streamJson($filename, $data);
        }

        $rows = $data['rows'] ?? [];
        $headers = $data['headers'] ?? [];
        if ($headers === [] && $rows !== []) {
            $headers = array_keys($rows[0]);
        }

        if ($format === 'excel' || $format === 'xls') {
            $this->streamExcel($filename, $headers, $rows);
        }

        $this->streamCsv($filename, $headers, $rows);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title:string,headers:list<string>,rows:list<array<string,mixed>>}
     */
    public function pdfHtml(string $resource, array $filters): string
    {
        $data = $this->collectExportData($resource, $filters);
        $title = htmlspecialchars($data['title'] ?? $resource, ENT_QUOTES, 'UTF-8');
        $headers = $data['headers'] ?? [];
        $rows = $data['rows'] ?? [];

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $title . '</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;font-size:12px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:4px 8px;text-align:left}th{background:#f0f0f0}@media print{body{margin:0}}</style></head><body>';
        $html .= '<h1>' . $title . '</h1><p>' . date('Y-m-d H:i:s') . '</p><table><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars((string) $h, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($headers as $h) {
                $html .= '<td>' . htmlspecialchars((string) ($row[$h] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table><script>window.onload=function(){window.print();}</script></body></html>';

        return $html;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title:string,headers:list<string>,rows:list<array<string,mixed>>}
     */
    private function collectExportData(string $resource, array $filters): array
    {
        return match ($resource) {
            'events' => $this->exportEvents($filters),
            'audit' => $this->exportAudit($filters),
            'projections' => $this->exportProjections($filters),
            'consolidation' => $this->exportConsolidation($filters),
            'drift' => $this->exportDrift($filters),
            'reconciliation' => $this->exportReconciliation($filters),
            'integrity' => $this->exportIntegrity($filters),
            'timeline' => $this->exportTimeline($filters),
            default => ['title' => $resource, 'headers' => [], 'rows' => []],
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title:string,headers:list<string>,rows:list<array<string,mixed>>}
     */
    private function exportEvents(array $filters): array
    {
        $data = $this->core->listEvents(array_merge($filters, ['per_page' => 5000]));
        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                'event_uuid' => $r['event_uuid'],
                'source_system' => $r['source_system'],
                'event_type' => $r['event_type'],
                'status' => $r['status'],
                'company_id' => $r['company_id'],
                'branch_id' => $r['branch_id'],
                'created_at' => $r['created_at'],
            ];
        }

        return [
            'title' => 'Accounting Events',
            'headers' => ['event_uuid', 'source_system', 'event_type', 'status', 'company_id', 'branch_id', 'created_at'],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title:string,headers:list<string>,rows:list<array<string,mixed>>}
     */
    private function exportAudit(array $filters): array
    {
        $data = $this->core->listAuditLogs(array_merge($filters, ['per_page' => 5000]));
        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                'created_at' => $r['created_at'],
                'event_uuid' => $r['event_uuid'] ?? '',
                'action' => $r['action'],
                'system' => $r['system'],
                'status' => $r['status'],
            ];
        }

        return [
            'title' => 'Audit Log',
            'headers' => ['created_at', 'event_uuid', 'action', 'system', 'status'],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title:string,headers:list<string>,rows:list<array<string,mixed>>}
     */
    private function exportProjections(array $filters): array
    {
        $type = (string) ($filters['type'] ?? 'trial_balance');
        $detail = $this->phase7->projectionsDetail($type, $filters);
        $rows = [];
        foreach ($detail['parsed_rows'] ?? [] as $r) {
            $rows[] = [
                'account_code' => $r['account_code'],
                'account_name' => $r['account_name'],
                'debit' => $r['debit'],
                'credit' => $r['credit'],
                'amount' => $r['amount'],
            ];
        }

        return [
            'title' => 'Projections — ' . $type,
            'headers' => ['account_code', 'account_name', 'debit', 'credit', 'amount'],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title:string,headers:list<string>,rows:list<array<string,mixed>>}
     */
    private function exportConsolidation(array $filters): array
    {
        $type = (string) ($filters['type'] ?? 'trial_balance');
        $detail = $this->phase7->consolidationDetail($type, $filters);
        $rows = [];
        foreach ($detail['parsed_rows'] ?? [] as $r) {
            $rows[] = [
                'run_id' => $r['consolidation_run_id'] ?? '',
                'account_code' => $r['account_code'],
                'account_name' => $r['account_name'],
                'debit' => $r['debit'],
                'credit' => $r['credit'],
                'amount' => $r['amount'],
            ];
        }

        return [
            'title' => 'Consolidation — ' . $type,
            'headers' => ['run_id', 'account_code', 'account_name', 'debit', 'credit', 'amount'],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title:string,headers:list<string>,rows:list<array<string,mixed>>}
     */
    private function exportDrift(array $filters): array
    {
        $detail = $this->phase7->driftDetail($filters);
        $rows = [];
        foreach ($detail['reports']['rows'] ?? [] as $r) {
            $summary = is_array($r['payload']['summary'] ?? null) ? $r['payload']['summary'] : [];
            $rows[] = [
                'id' => $r['id'],
                'period' => ($r['period_from'] ?? '') . ' — ' . ($r['period_to'] ?? ''),
                'severity' => $r['severity'] ?? '',
                'missing' => $summary['missing'] ?? 0,
                'duplicate' => $summary['duplicate'] ?? 0,
                'mismatched' => $summary['mismatched'] ?? 0,
                'created_at' => $r['created_at'],
            ];
        }

        return [
            'title' => 'Drift Reports',
            'headers' => ['id', 'period', 'severity', 'missing', 'duplicate', 'mismatched', 'created_at'],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title:string,headers:list<string>,rows:list<array<string,mixed>>}
     */
    private function exportReconciliation(array $filters): array
    {
        $detail = $this->phase7->reconciliationDetail($filters);
        $rows = [];
        foreach ($detail['reports']['rows'] ?? [] as $r) {
            $rows[] = [
                'id' => $r['id'],
                'risk_level' => $r['risk_level'],
                'period_from' => $r['period_from'],
                'period_to' => $r['period_to'],
                'created_at' => $r['created_at'],
            ];
        }

        return [
            'title' => 'Reconciliation Reports',
            'headers' => ['id', 'risk_level', 'period_from', 'period_to', 'created_at'],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title:string,headers:list<string>,rows:list<array<string,mixed>>}
     */
    private function exportIntegrity(array $filters): array
    {
        $packs = $this->core->listEvidencePacks(array_merge($filters, ['per_page' => 500]));
        $rows = [];
        foreach ($packs['rows'] ?? [] as $r) {
            $rows[] = [
                'id' => $r['id'],
                'period_from' => $r['period_from'],
                'period_to' => $r['period_to'],
                'certification_hash' => $r['certification_hash'],
                'created_at' => $r['created_at'],
            ];
        }

        return [
            'title' => 'Evidence Packs',
            'headers' => ['id', 'period_from', 'period_to', 'certification_hash', 'created_at'],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{title:string,headers:list<string>,rows:list<array<string,mixed>>}
     */
    private function exportTimeline(array $filters): array
    {
        $data = $this->phase7->activityTimeline($filters);
        $rows = [];
        foreach ($data['items'] ?? [] as $item) {
            $rows[] = [
                'kind' => $item['kind'],
                'ref' => $item['ref'] ?? '',
                'title' => $item['title'],
                'status' => $item['status'] ?? '',
                'created_at' => $item['created_at'],
            ];
        }

        return [
            'title' => 'Activity Timeline',
            'headers' => ['kind', 'ref', 'title', 'status', 'created_at'],
            'rows' => $rows,
        ];
    }

    private function safeFilename(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '-', $name) ?: 'export';
    }
}
