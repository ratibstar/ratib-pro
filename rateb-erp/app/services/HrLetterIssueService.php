<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Lib\HrLetterPdf\HrLetterPdfRenderer;
use Rateb\App\Models\Company;
use Rateb\App\Models\Employee;
use Rateb\App\Models\HrEmployeeRequest;
use PDO;

/**
 * Phase L — HR letter issue / download (salary / employment / experience / EOS).
 * Reuses rateb_hr_employee_requests + ApprovalOversight/Matrix for approve.
 * Stores PDF via DocumentService → rateb_documents (no parallel file system).
 */
final class HrLetterIssueService
{
    /** @var list<string> */
    public const LETTER_TYPES = [
        'salary_certificate',
        'employment_certificate',
        'experience_letter',
        'end_of_service',
    ];

    public static function isLetterType(string $type): bool
    {
        return in_array($type, self::LETTER_TYPES, true);
    }

    public function schemaReady(): bool
    {
        try {
            return Database::liveTableHasColumn('rateb_hr_employee_requests', 'document_id');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLetters(int $companyId, ?string $status = null, int $limit = 200): array
    {
        if ($companyId < 1) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $placeholders = implode(',', array_fill(0, count(self::LETTER_TYPES), '?'));
        $sql = "SELECT r.*, e.name AS employee_name, e.employee_code
                FROM rateb_hr_employee_requests r
                JOIN rateb_employees e ON e.id = r.employee_id AND e.company_id = r.company_id
                WHERE r.company_id = ? AND r.request_type IN ({$placeholders})";
        $params = array_merge([$companyId], self::LETTER_TYPES);
        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= ' AND r.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY r.id DESC LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function findLetterRequest(int $companyId, int $requestId): ?array
    {
        if ($companyId < 1 || $requestId < 1) {
            return null;
        }
        $row = (new HrEmployeeRequest())->queryOne(
            'SELECT r.*, e.name AS employee_name, e.employee_code, e.national_id, e.job_title,
                    e.hire_date, e.salary_base, e.department_id
             FROM rateb_hr_employee_requests r
             JOIN rateb_employees e ON e.id = r.employee_id AND e.company_id = r.company_id
             WHERE r.id = :id AND r.company_id = :cid
             LIMIT 1',
            ['id' => $requestId, 'cid' => $companyId]
        );
        if (!is_array($row) || !self::isLetterType((string) ($row['request_type'] ?? ''))) {
            return null;
        }

        return $row;
    }

    /**
     * Issue PDF for an approved letter request.
     *
     * @return array{request_id:int,document_id:int,reissued:bool}
     */
    public function issue(int $companyId, int $requestId, int $actorUserId = 0): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        $row = $this->findLetterRequest($companyId, $requestId);
        if ($row === null) {
            throw new \RuntimeException(__('access_denied'));
        }
        if ((string) ($row['status'] ?? '') !== 'approved') {
            throw new \RuntimeException(__('hr_letter_must_be_approved'));
        }
        if ($actorUserId < 1) {
            $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        }

        $employeeId = (int) ($row['employee_id'] ?? 0);
        $type = (string) ($row['request_type'] ?? '');
        $company = (new Company())->find($companyId);
        $companyName = is_array($company)
            ? (string) ($company['name_ar'] ?? $company['name'] ?? '')
            : '';
        if ($companyName === '' && is_array($company)) {
            $companyName = (string) ($company['name'] ?? 'الشركة');
        }

        $payload = $this->buildLetterPayload($row, $companyName);
        $pdf = (new HrLetterPdfRenderer())->render($payload);
        $title = $this->typeLabelAr($type) . ' — ' . (string) ($row['request_no'] ?? $requestId);
        $fileName = 'letter-' . preg_replace('/[^a-zA-Z0-9\-]/', '', (string) ($row['request_no'] ?? (string) $requestId)) . '.pdf';

        $stored = (new DocumentService())->storeGeneratedBytes(
            $companyId,
            'hr_employees',
            $employeeId,
            $pdf,
            $fileName,
            'application/pdf',
            $title
        );
        if (empty($stored['success']) || (int) ($stored['document_id'] ?? 0) < 1) {
            throw new \RuntimeException((string) ($stored['error'] ?? __('upload_save_failed')));
        }
        $documentId = (int) $stored['document_id'];
        $reissued = (int) ($row['document_id'] ?? 0) > 0;

        (new HrEmployeeRequest())->update($requestId, [
            'document_id' => $documentId,
            'issued_at' => date('Y-m-d H:i:s'),
            'issued_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);

        (new AuditService())->log(
            $reissued ? 'hr_letter_reissue' : 'hr_letter_issue',
            'hr_employee_request',
            $requestId,
            [
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'document_id' => $documentId,
                'request_type' => $type,
                'request_no' => $row['request_no'] ?? null,
            ]
        );

        return [
            'request_id' => $requestId,
            'document_id' => $documentId,
            'reissued' => $reissued,
        ];
    }

    /** Authorize + stream download; audits access. */
    public function download(int $companyId, int $requestId): void
    {
        $row = $this->findLetterRequest($companyId, $requestId);
        if ($row === null) {
            throw new \RuntimeException(__('access_denied'));
        }
        $documentId = (int) ($row['document_id'] ?? 0);
        if ($documentId < 1) {
            throw new \RuntimeException(__('hr_letter_not_issued'));
        }
        $doc = (new DocumentService())->findById($documentId);
        if (!is_array($doc) || (int) ($doc['company_id'] ?? 0) !== $companyId) {
            throw new \RuntimeException(__('access_denied'));
        }
        // Ownership: document must belong to the request employee.
        if ((int) ($doc['entity_id'] ?? 0) !== (int) ($row['employee_id'] ?? 0)) {
            throw new \RuntimeException(__('access_denied'));
        }

        (new AuditService())->log('hr_letter_download', 'hr_employee_request', $requestId, [
            'company_id' => $companyId,
            'document_id' => $documentId,
            'employee_id' => (int) ($row['employee_id'] ?? 0),
        ]);

        $path = \Rateb\App\Helpers\StorageHelper::resolveFilePath((string) ($doc['file_path'] ?? ''));
        if ($path === '' || !is_file($path)) {
            throw new \RuntimeException(__('no_records'));
        }
        $name = (string) ($doc['file_name'] ?? ('letter-' . $requestId . '.pdf'));
        $mime = (string) ($doc['mime_type'] ?? 'application/pdf');
        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
            header('Content-Length: ' . (string) filesize($path));
            header('X-Content-Type-Options: nosniff');
        }
        readfile($path);
        exit;
    }

    public function documentIdForRequest(int $companyId, int $requestId): int
    {
        $row = $this->findLetterRequest($companyId, $requestId);

        return is_array($row) ? (int) ($row['document_id'] ?? 0) : 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function buildLetterPayload(array $row, string $companyName): array
    {
        $type = (string) ($row['request_type'] ?? '');
        $name = (string) ($row['employee_name'] ?? '');
        $title = $this->typeLabelAr($type);
        $body = match ($type) {
            'salary_certificate' => [
                'تشهد إدارة الموارد البشرية بأن الموظف المذكور أدناه يعمل لدى الشركة،',
                'وأن راتبه الأساسي وفق سجلات النظام كما هو موضح أدناه.',
            ],
            'employment_certificate' => [
                'تشهد إدارة الموارد البشرية بأن الموظف المذكور أدناه على رأس العمل لدى الشركة',
                'وفق البيانات الوظيفية الموضحة أدناه.',
            ],
            'experience_letter' => [
                'تشهد إدارة الموارد البشرية بأن الموظف المذكور أدناه قد اكتسب خبرة عملية لدى الشركة',
                'وفق تاريخ التعيين والبيانات أدناه.',
            ],
            'end_of_service' => [
                'تشهد إدارة الموارد البشرية بخصوص خدمة الموظف المذكور أدناه لدى الشركة',
                'وفق بيانات التعيين والوضع الوظيفي في سجلات النظام.',
            ],
            default => [
                'تشهد إدارة الموارد البشرية بصحة البيانات الوظيفية أدناه وفق سجلات النظام.',
            ],
        };

        $salaryLine = null;
        if ($type === 'salary_certificate') {
            $salaryLine = number_format((float) ($row['salary_base'] ?? 0), 2) . ' ر.س';
        }

        return [
            'title' => $title,
            'company_name' => $companyName !== '' ? $companyName : 'الشركة',
            'body_lines' => $body,
            'employee_name' => $name,
            'employee_code' => (string) ($row['employee_code'] ?? ''),
            'national_id' => (string) ($row['national_id'] ?? ''),
            'job_title' => (string) ($row['job_title'] ?? ''),
            'hire_date' => (string) ($row['hire_date'] ?? ''),
            'salary_line' => $salaryLine,
            'request_no' => (string) ($row['request_no'] ?? ''),
            'issue_date' => date('Y-m-d'),
            'footer' => 'إدارة الموارد البشرية',
        ];
    }

    private function typeLabelAr(string $type): string
    {
        return match ($type) {
            'salary_certificate' => 'شهادة راتب',
            'employment_certificate' => 'شهادة تعريف بالعمل',
            'experience_letter' => 'شهادة خبرة',
            'end_of_service' => 'شهادة نهاية خدمة',
            default => 'خطاب موظف',
        };
    }
}
