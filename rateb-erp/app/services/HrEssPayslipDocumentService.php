<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Helpers\StorageHelper;
use Rateb\App\Models\Document;
use Rateb\App\Models\HrDocument;
use Rateb\App\Models\PayrollLine;
use Rateb\App\Models\PayrollPayslip;

/**
 * ESS Payslips + Documents — thin read adapters over existing ERP tables.
 * No payroll calculation. No client employee_id.
 */
final class HrEssPayslipDocumentService
{
    private const EMPLOYEE_DOC_ENTITY_TYPES = ['hr_employees', 'employees', 'employee'];

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function listPayslips(int $userId, int $companyId): array
    {
        $resolved = $this->resolve($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeId($resolved);
        $items = [];

        foreach ($this->legacyPayslipRows($companyId, $employeeId) as $row) {
            $dto = $this->payslipDto($row, 'legacy');
            if ($dto !== null) {
                $items[] = $dto;
            }
        }
        foreach ($this->enterprisePayslipRows($companyId, $employeeId) as $row) {
            $dto = $this->payslipDto($row, 'enterprise');
            if ($dto !== null) {
                $items[] = $dto;
            }
        }

        usort($items, static function (array $a, array $b): int {
            $ay = (int) ($a['year'] ?? 0);
            $by = (int) ($b['year'] ?? 0);
            if ($ay !== $by) {
                return $by <=> $ay;
            }

            return ((int) ($b['month'] ?? 0)) <=> ((int) ($a['month'] ?? 0));
        });

        return $this->ok(['items' => $items]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function getPayslip(int $userId, int $companyId, string $payslipKey): array
    {
        $resolved = $this->resolve($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeId($resolved);
        $parsed = $this->parsePayslipKey($payslipKey);
        if ($parsed === null) {
            return $this->fail(422, 'validation_error', 'Invalid payslip id');
        }

        $row = $parsed['source'] === 'enterprise'
            ? $this->findEnterprisePayslip($companyId, $employeeId, $parsed['id'])
            : $this->findLegacyPayslip($companyId, $employeeId, $parsed['id']);
        if ($row === null) {
            return $this->fail(404, 'not_found', 'Payslip not found');
        }

        $dto = $this->payslipDto($row, $parsed['source']);
        if ($dto === null) {
            return $this->fail(404, 'not_found', 'Payslip not found');
        }

        return $this->ok(['payslip' => $dto]);
    }

    /**
     * Stream a read-only payslip PDF from existing amounts (no payroll recalculation).
     *
     * @return array{status:int,body?:array<string,mixed>,stream?:array{filename:string,mime:string,content:string}}
     */
    public function downloadPayslip(int $userId, int $companyId, string $payslipKey): array
    {
        $result = $this->getPayslip($userId, $companyId, $payslipKey);
        if ((int) ($result['status'] ?? 0) !== 200) {
            return $result;
        }
        $dto = $result['body']['data']['payslip'] ?? null;
        if (!is_array($dto)) {
            return $this->fail(404, 'not_found', 'Payslip not found');
        }

        $resolved = $this->resolve($userId, $companyId);
        $employee = is_array($resolved['body']['employee'] ?? null) ? $resolved['body']['employee'] : [];
        $empName = (string) ($employee['name'] ?? '');
        $empCode = (string) ($employee['employee_code'] ?? '');

        try {
            $pdf = (new \Rateb\App\Lib\HrLetterPdf\HrLetterPdfRenderer())->render([
                'title' => 'كشف راتب / Payslip',
                'company_name' => 'RATEB ESS',
                'body_lines' => [
                    'هذا الكشف يعرض المبالغ المحسوبة مسبقاً في النظام دون إعادة احتساب.',
                    'Period: ' . (string) ($dto['period'] ?? ''),
                    'Gross: ' . (string) ($dto['gross_amount'] ?? ''),
                    'Net: ' . (string) ($dto['net_amount'] ?? ''),
                    'Status: ' . (string) ($dto['status'] ?? ''),
                ],
                'employee_name' => $empName,
                'employee_code' => $empCode,
                'national_id' => '',
                'job_title' => '',
                'hire_date' => '',
                'salary_line' => 'Net: ' . (string) ($dto['net_amount'] ?? ''),
                'request_no' => (string) ($dto['id'] ?? $payslipKey),
                'issue_date' => date('Y-m-d'),
                'footer' => 'إدارة الموارد البشرية',
            ]);
        } catch (\Throwable $e) {
            return $this->fail(500, 'pdf_unavailable', 'Payslip PDF unavailable');
        }

        $filename = 'payslip-' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($dto['id'] ?? 'slip')) . '.pdf';

        return [
            'status' => 200,
            'stream' => [
                'filename' => $filename,
                'mime' => 'application/pdf',
                'content' => $pdf,
            ],
        ];
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function listDocuments(int $userId, int $companyId, ?string $category = null): array
    {
        $resolved = $this->resolve($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeId($resolved);
        $categoryFilter = $category !== null && trim($category) !== ''
            ? strtolower(trim($category))
            : null;

        $items = [];
        foreach ($this->fileDocumentRows($companyId, $employeeId) as $row) {
            $dto = $this->documentDto($row, 'file');
            if ($dto === null) {
                continue;
            }
            if ($categoryFilter !== null && strtolower((string) $dto['category']) !== $categoryFilter) {
                continue;
            }
            $items[] = $dto;
        }
        foreach ($this->metaDocumentRows($companyId, $employeeId) as $row) {
            $dto = $this->documentDto($row, 'meta');
            if ($dto === null) {
                continue;
            }
            if ($categoryFilter !== null && strtolower((string) $dto['category']) !== $categoryFilter) {
                continue;
            }
            $items[] = $dto;
        }

        return $this->ok(['items' => $items]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function getDocument(int $userId, int $companyId, string $documentKey): array
    {
        $resolved = $this->resolve($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeId($resolved);
        $parsed = $this->parseDocumentKey($documentKey);
        if ($parsed === null) {
            return $this->fail(422, 'validation_error', 'Invalid document id');
        }

        if ($parsed['source'] === 'file') {
            $row = $this->findFileDocument($companyId, $employeeId, $parsed['id']);
            $dto = $this->documentDto($row, 'file');
        } else {
            $row = $this->findMetaDocument($companyId, $employeeId, $parsed['id']);
            $dto = $this->documentDto($row, 'meta');
        }
        if ($dto === null) {
            return $this->fail(404, 'not_found', 'Document not found');
        }

        return $this->ok(['document' => $dto]);
    }

    /**
     * Stream binary for file documents owned by the resolved employee.
     *
     * @return array{status:int,body?:array<string,mixed>,file?:array{path:string,mime:string,filename:string}}
     */
    public function downloadDocument(int $userId, int $companyId, string $documentKey): array
    {
        $resolved = $this->resolve($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $employeeId = $this->employeeId($resolved);
        $parsed = $this->parseDocumentKey($documentKey);
        if ($parsed === null || $parsed['source'] !== 'file') {
            return $this->fail(404, 'not_found', 'Document file not available');
        }
        $row = $this->findFileDocument($companyId, $employeeId, $parsed['id']);
        if ($row === null) {
            return $this->fail(404, 'not_found', 'Document not found');
        }

        $relative = (string) ($row['file_path'] ?? '');
        $absolute = StorageHelper::resolveFilePath($relative);
        if ($absolute === '' || !is_file($absolute)) {
            return $this->fail(404, 'not_found', 'Document file missing');
        }

        return [
            'status' => 200,
            'file' => [
                'path' => $absolute,
                'mime' => (string) ($row['mime_type'] ?? 'application/octet-stream'),
                'filename' => (string) ($row['file_name'] ?? ('document-' . $parsed['id'])),
            ],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function legacyPayslipRows(int $companyId, int $employeeId): array
    {
        return (new PayrollLine())->query(
            'SELECT pl.id, pl.company_id, pl.employee_id, pl.basic_salary, pl.allowances, pl.deductions, pl.net_salary,
                    pp.period_year, pp.period_month, pp.status AS period_status
             FROM rateb_payroll_lines pl
             INNER JOIN rateb_payroll_periods pp ON pp.id = pl.period_id AND pp.company_id = pl.company_id
             WHERE pl.company_id = :cid AND pl.employee_id = :eid
               AND pp.status IN (\'approved\', \'posted\')
             ORDER BY pp.period_year DESC, pp.period_month DESC, pl.id DESC
             LIMIT 100',
            ['cid' => $companyId, 'eid' => $employeeId]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function enterprisePayslipRows(int $companyId, int $employeeId): array
    {
        try {
            return (new PayrollPayslip())->query(
                'SELECT id, company_id, legacy_employee_id, payslip_number, period_start, period_end,
                        gross_amount, deduction_amount, net_amount, workflow_status, status
                 FROM rateb_payroll_payslips
                 WHERE company_id = :cid AND legacy_employee_id = :eid
                   AND deleted_at IS NULL
                   AND workflow_status IN (\'issued\', \'acknowledged\')
                 ORDER BY period_end DESC, id DESC
                 LIMIT 100',
                ['cid' => $companyId, 'eid' => $employeeId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string,mixed>|null */
    private function findLegacyPayslip(int $companyId, int $employeeId, int $id): ?array
    {
        return (new PayrollLine())->queryOne(
            'SELECT pl.id, pl.company_id, pl.employee_id, pl.basic_salary, pl.allowances, pl.deductions, pl.net_salary,
                    pp.period_year, pp.period_month, pp.status AS period_status
             FROM rateb_payroll_lines pl
             INNER JOIN rateb_payroll_periods pp ON pp.id = pl.period_id AND pp.company_id = pl.company_id
             WHERE pl.company_id = :cid AND pl.employee_id = :eid AND pl.id = :id
               AND pp.status IN (\'approved\', \'posted\')
             LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId, 'id' => $id]
        );
    }

    /** @return array<string,mixed>|null */
    private function findEnterprisePayslip(int $companyId, int $employeeId, int $id): ?array
    {
        try {
            return (new PayrollPayslip())->queryOne(
                'SELECT id, company_id, legacy_employee_id, payslip_number, period_start, period_end,
                        gross_amount, deduction_amount, net_amount, workflow_status, status
                 FROM rateb_payroll_payslips
                 WHERE company_id = :cid AND legacy_employee_id = :eid AND id = :id
                   AND deleted_at IS NULL
                   AND workflow_status IN (\'issued\', \'acknowledged\')
                 LIMIT 1',
                ['cid' => $companyId, 'eid' => $employeeId, 'id' => $id]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fileDocumentRows(int $companyId, int $employeeId): array
    {
        $placeholders = [];
        $params = ['cid' => $companyId, 'eid' => $employeeId];
        foreach (self::EMPLOYEE_DOC_ENTITY_TYPES as $i => $type) {
            $key = 'et' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $type;
        }
        $in = implode(',', $placeholders);

        return (new Document())->query(
            "SELECT id, company_id, entity_type, entity_id, title, file_name, mime_type, created_at
             FROM rateb_documents
             WHERE company_id = :cid AND entity_id = :eid AND entity_type IN ($in)
             ORDER BY id DESC
             LIMIT 200",
            $params
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function metaDocumentRows(int $companyId, int $employeeId): array
    {
        return (new HrDocument())->query(
            'SELECT id, company_id, employee_id, title, doc_type, issue_date, expiry_date, created_at
             FROM rateb_hr_documents
             WHERE company_id = :cid AND employee_id = :eid
             ORDER BY id DESC
             LIMIT 200',
            ['cid' => $companyId, 'eid' => $employeeId]
        );
    }

    /** @return array<string,mixed>|null */
    private function findFileDocument(int $companyId, int $employeeId, int $id): ?array
    {
        $placeholders = [];
        $params = ['cid' => $companyId, 'eid' => $employeeId, 'id' => $id];
        foreach (self::EMPLOYEE_DOC_ENTITY_TYPES as $i => $type) {
            $key = 'et' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $type;
        }
        $in = implode(',', $placeholders);

        return (new Document())->queryOne(
            "SELECT id, company_id, entity_type, entity_id, title, file_name, file_path, mime_type, created_at
             FROM rateb_documents
             WHERE company_id = :cid AND entity_id = :eid AND id = :id AND entity_type IN ($in)
             LIMIT 1",
            $params
        );
    }

    /** @return array<string,mixed>|null */
    private function findMetaDocument(int $companyId, int $employeeId, int $id): ?array
    {
        return (new HrDocument())->queryOne(
            'SELECT id, company_id, employee_id, title, doc_type, issue_date, expiry_date, created_at
             FROM rateb_hr_documents
             WHERE company_id = :cid AND employee_id = :eid AND id = :id
             LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId, 'id' => $id]
        );
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>|null
     */
    private function payslipDto(?array $row, string $source): ?array
    {
        if ($row === null) {
            return null;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        $key = ($source === 'enterprise' ? 'e' : 'l') . '-' . $id;

        if ($source === 'enterprise') {
            $start = (string) ($row['period_start'] ?? '');
            $year = $start !== '' ? (int) substr($start, 0, 4) : 0;
            $month = $start !== '' ? (int) substr($start, 5, 2) : 0;
            $period = trim($start . ' → ' . (string) ($row['period_end'] ?? ''));
            $gross = $row['gross_amount'] ?? null;
            $net = $row['net_amount'] ?? null;
            $status = (string) ($row['workflow_status'] ?? $row['status'] ?? '');
        } else {
            $year = (int) ($row['period_year'] ?? 0);
            $month = (int) ($row['period_month'] ?? 0);
            $period = sprintf('%04d-%02d', $year, $month);
            $basic = (float) ($row['basic_salary'] ?? 0);
            $allow = (float) ($row['allowances'] ?? 0);
            $gross = $basic + $allow;
            $net = $row['net_salary'] ?? null;
            $status = (string) ($row['period_status'] ?? '');
        }

        return [
            'id' => $key,
            'period' => $period,
            'month' => $month,
            'year' => $year,
            'gross_amount' => $gross,
            'net_amount' => $net,
            'status' => $status,
            'download_url' => '/api/v1/hr/payslips/' . rawurlencode($key) . '/file',
        ];
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>|null
     */
    private function documentDto(?array $row, string $source): ?array
    {
        if ($row === null) {
            return null;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        $key = ($source === 'file' ? 'f' : 'm') . '-' . $id;
        $category = $source === 'file'
            ? (string) ($row['entity_type'] ?? 'document')
            : (string) ($row['doc_type'] ?? 'document');
        $fileName = $source === 'file'
            ? (string) ($row['file_name'] ?? '')
            : '';
        $fileUrl = $source === 'file'
            ? '/api/v1/hr/documents/' . rawurlencode($key) . '/file'
            : null;

        return [
            'id' => $key,
            'title' => (string) ($row['title'] ?? ''),
            'category' => $category,
            'file_name' => $fileName !== '' ? $fileName : null,
            'file_url' => $fileUrl,
            'uploaded_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
        ];
    }

    /** @return array{source:string,id:int}|null */
    private function parsePayslipKey(string $key): ?array
    {
        $key = trim($key);
        if (preg_match('/^(l|e)-(\d+)$/', $key, $m)) {
            return [
                'source' => $m[1] === 'e' ? 'enterprise' : 'legacy',
                'id' => (int) $m[2],
            ];
        }
        if (ctype_digit($key)) {
            return ['source' => 'legacy', 'id' => (int) $key];
        }

        return null;
    }

    /** @return array{source:string,id:int}|null */
    private function parseDocumentKey(string $key): ?array
    {
        $key = trim($key);
        if (preg_match('/^(f|m)-(\d+)$/', $key, $m)) {
            return [
                'source' => $m[1] === 'f' ? 'file' : 'meta',
                'id' => (int) $m[2],
            ];
        }
        if (ctype_digit($key)) {
            return ['source' => 'file', 'id' => (int) $key];
        }

        return null;
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function resolve(int $userId, int $companyId): array
    {
        return (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
    }

    /** @param array{status:int,body:array<string,mixed>} $resolved */
    private function employeeId(array $resolved): int
    {
        $employee = $resolved['body']['employee'] ?? null;

        return (int) (is_array($employee) ? ($employee['id'] ?? 0) : 0);
    }

    /**
     * @param array<string,mixed> $data
     * @return array{status:int,body:array<string,mixed>}
     */
    private function ok(array $data): array
    {
        return [
            'status' => 200,
            'body' => ['success' => true, 'data' => $data],
        ];
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function fail(int $status, string $code, string $message): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
