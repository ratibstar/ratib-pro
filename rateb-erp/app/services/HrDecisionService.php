<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Employee;
use Rateb\App\Models\HrDecision;

/**
 * Phase M — Employee HR decisions (request → Oversight/Matrix → execute once).
 *
 * Reuses ApprovalOversightService + HrApprovalMatrixService (no new approval engine).
 * Does not rewrite payroll calculation or accounting.
 */
final class HrDecisionService
{
    public const SOURCE_KEY = 'hr_decision';

    /** @var list<string> */
    public const TYPES = [
        'promotion',
        'salary_adjustment',
        'transfer',
        'salary_stop',
        'absence_deduction',
        'salary_movement',
        'termination',
    ];

    /** Sensitive: must be approved before any employee mutation. */
    /** @var list<string> */
    public const SENSITIVE_TYPES = [
        'salary_adjustment',
        'salary_movement',
        'salary_stop',
        'termination',
    ];

    public function schemaReady(): bool
    {
        try {
            return Database::tableExists('rateb_hr_decisions');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isValidType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    public static function isSensitive(string $type): bool
    {
        return in_array($type, self::SENSITIVE_TYPES, true);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id:int,decision_no:string,status:string}
     */
    public function create(int $companyId, array $input, int $actorUserId = 0): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        if ($companyId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $employeeId = (int) ($input['employee_id'] ?? 0);
        $type = trim((string) ($input['decision_type'] ?? ''));
        if ($employeeId < 1 || !self::isValidType($type)) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $emp = $this->assertEmployee($companyId, $employeeId);
        $payload = $this->normalizePayload($type, $input, $emp);
        $effective = trim((string) ($input['effective_date'] ?? ''));
        if ($effective === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective)) {
            $effective = date('Y-m-d');
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($actorUserId < 1) {
            $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        }

        $decisionNo = $this->nextDecisionNo($companyId);
        $model = new HrDecision();
        $id = (int) $model->create([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'decision_no' => $decisionNo,
            'decision_type' => $type,
            'effective_date' => $effective,
            'reason' => $reason !== '' ? $reason : null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ]);
        if ($id < 1) {
            throw new \RuntimeException(__('save_failed'));
        }

        ApprovalOversightService::notifyPendingSubmission(
            $companyId,
            self::SOURCE_KEY,
            __('hr_decisions'),
            $id
        );
        (new AuditService())->log('hr_decision_create', 'hr_decision', $id, [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'decision_type' => $type,
            'decision_no' => $decisionNo,
            'status' => 'pending',
        ]);

        return ['id' => $id, 'decision_no' => $decisionNo, 'status' => 'pending'];
    }

    /** @return array<string, mixed>|null */
    public function find(int $companyId, int $decisionId): ?array
    {
        if ($companyId < 1 || $decisionId < 1 || !$this->schemaReady()) {
            return null;
        }
        $row = (new HrDecision())->queryOne(
            'SELECT d.*, e.name AS employee_name, e.employee_code
             FROM rateb_hr_decisions d
             JOIN rateb_employees e ON e.id = d.employee_id AND e.company_id = d.company_id
             WHERE d.id = :id AND d.company_id = :cid
             LIMIT 1',
            ['id' => $decisionId, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(int $companyId, ?string $status = null, ?string $type = null, int $limit = 200): array
    {
        if ($companyId < 1 || !$this->schemaReady()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT d.*, e.name AS employee_name, e.employee_code
                FROM rateb_hr_decisions d
                JOIN rateb_employees e ON e.id = d.employee_id AND e.company_id = d.company_id
                WHERE d.company_id = :cid';
        $params = ['cid' => $companyId];
        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= ' AND d.status = :st';
            $params['st'] = $status;
        }
        if ($type !== null && $type !== '' && $type !== 'all' && self::isValidType($type)) {
            $sql .= ' AND d.decision_type = :dt';
            $params['dt'] = $type;
        }
        $sql .= ' ORDER BY d.id DESC LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForEmployee(int $companyId, int $employeeId, int $limit = 50): array
    {
        if ($companyId < 1 || $employeeId < 1 || !$this->schemaReady()) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = Database::connection()->prepare(
            'SELECT id, decision_no, decision_type, effective_date, status, reason, executed_at, created_at
             FROM rateb_hr_decisions
             WHERE company_id = :cid AND employee_id = :eid
             ORDER BY id DESC
             LIMIT ' . $limit
        );
        $stmt->execute(['cid' => $companyId, 'eid' => $employeeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function finalizeApprove(int $companyId, int $decisionId, int $actorUserId): void
    {
        $row = $this->requirePending($companyId, $decisionId);
        $db = Database::connection();
        $stmt = $db->prepare(
            "UPDATE rateb_hr_decisions
             SET status = 'approved', approved_by = :uid, approved_at = NOW(), updated_by = :uid2
             WHERE id = :id AND company_id = :cid AND status = 'pending'"
        );
        $stmt->execute([
            'uid' => $actorUserId > 0 ? $actorUserId : null,
            'uid2' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $decisionId,
            'cid' => $companyId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('leave_not_pending'));
        }
        (new AuditService())->log('hr_decision_approve', 'hr_decision', $decisionId, [
            'company_id' => $companyId,
            'employee_id' => (int) ($row['employee_id'] ?? 0),
            'decision_type' => (string) ($row['decision_type'] ?? ''),
            'actor_user_id' => $actorUserId,
        ]);
        // Final approve → execute once (domain side effects).
        $this->execute($companyId, $decisionId, $actorUserId);
    }

    public function finalizeReject(int $companyId, int $decisionId, int $actorUserId): void
    {
        $row = $this->requirePending($companyId, $decisionId);
        $db = Database::connection();
        $stmt = $db->prepare(
            "UPDATE rateb_hr_decisions
             SET status = 'rejected', rejected_by = :uid, rejected_at = NOW(), updated_by = :uid2
             WHERE id = :id AND company_id = :cid AND status = 'pending'"
        );
        $stmt->execute([
            'uid' => $actorUserId > 0 ? $actorUserId : null,
            'uid2' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $decisionId,
            'cid' => $companyId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('leave_not_pending'));
        }
        (new AuditService())->log('hr_decision_reject', 'hr_decision', $decisionId, [
            'company_id' => $companyId,
            'employee_id' => (int) ($row['employee_id'] ?? 0),
            'decision_type' => (string) ($row['decision_type'] ?? ''),
            'actor_user_id' => $actorUserId,
        ]);
    }

    /**
     * Execute approved decision exactly once (CAS on status=approved AND executed_at IS NULL).
     *
     * @return array{executed:bool,already:bool}
     */
    public function execute(int $companyId, int $decisionId, int $actorUserId = 0): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        $row = $this->find($companyId, $decisionId);
        if ($row === null) {
            throw new \RuntimeException(__('no_records'));
        }
        $status = strtolower((string) ($row['status'] ?? ''));
        $type = (string) ($row['decision_type'] ?? '');
        if ($status === 'executed' || !empty($row['executed_at'])) {
            return ['executed' => false, 'already' => true];
        }
        if ($status !== 'approved') {
            // Sensitive and non-sensitive alike: never mutate employee before approval.
            throw new \RuntimeException(__('hr_decision_not_approved'));
        }
        if (self::isSensitive($type) && $status !== 'approved') {
            throw new \RuntimeException(__('hr_decision_sensitive_requires_approval'));
        }
        if ($actorUserId < 1) {
            $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        }

        $db = Database::connection();
        $cas = $db->prepare(
            "UPDATE rateb_hr_decisions
             SET status = 'executed', executed_by = :uid, executed_at = NOW(), updated_by = :uid2
             WHERE id = :id AND company_id = :cid
               AND status = 'approved' AND executed_at IS NULL"
        );
        $cas->execute([
            'uid' => $actorUserId > 0 ? $actorUserId : null,
            'uid2' => $actorUserId > 0 ? $actorUserId : null,
            'id' => $decisionId,
            'cid' => $companyId,
        ]);
        if ($cas->rowCount() < 1) {
            return ['executed' => false, 'already' => true];
        }

        $this->applySideEffects($companyId, $row, $actorUserId);
        (new AuditService())->log('hr_decision_execute', 'hr_decision', $decisionId, [
            'company_id' => $companyId,
            'employee_id' => (int) ($row['employee_id'] ?? 0),
            'decision_type' => $type,
            'actor_user_id' => $actorUserId,
        ]);

        return ['executed' => true, 'already' => false];
    }

    public function undoToPending(int $companyId, int $decisionId): void
    {
        $row = $this->find($companyId, $decisionId);
        if ($row === null) {
            throw new \RuntimeException(__('no_records'));
        }
        if (!empty($row['executed_at']) || strtolower((string) ($row['status'] ?? '')) === 'executed') {
            throw new \RuntimeException(__('hr_decision_already_executed'));
        }
        $stmt = Database::connection()->prepare(
            "UPDATE rateb_hr_decisions
             SET status = 'pending',
                 approved_by = NULL, approved_at = NULL,
                 rejected_by = NULL, rejected_at = NULL
             WHERE id = :id AND company_id = :cid
               AND status IN ('approved','rejected')
               AND executed_at IS NULL"
        );
        $stmt->execute(['id' => $decisionId, 'cid' => $companyId]);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        (new AuditService())->log('hr_decision_undo', 'hr_decision', $decisionId, [
            'company_id' => $companyId,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function applySideEffects(int $companyId, array $row, int $actorUserId): void
    {
        $employeeId = (int) ($row['employee_id'] ?? 0);
        $type = (string) ($row['decision_type'] ?? '');
        $payload = $this->decodePayload($row['payload_json'] ?? null);
        $empModel = new Employee();
        $old = $empModel->queryOne(
            'SELECT * FROM rateb_employees WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $employeeId, 'cid' => $companyId]
        );
        if (!is_array($old)) {
            throw new \RuntimeException(__('no_records'));
        }

        $patch = [];
        switch ($type) {
            case 'promotion':
                if (isset($payload['new_job_title']) && trim((string) $payload['new_job_title']) !== '') {
                    $patch['job_title'] = substr(trim((string) $payload['new_job_title']), 0, 190);
                }
                if (isset($payload['new_job_title_id']) && (int) $payload['new_job_title_id'] > 0) {
                    $patch['job_title_id'] = (int) $payload['new_job_title_id'];
                }
                if (isset($payload['new_department_id']) && (int) $payload['new_department_id'] > 0) {
                    $patch['department_id'] = (int) $payload['new_department_id'];
                }
                if (array_key_exists('new_salary_base', $payload) && $payload['new_salary_base'] !== null && $payload['new_salary_base'] !== '') {
                    $patch['salary_base'] = round((float) $payload['new_salary_base'], 2);
                }
                break;
            case 'salary_adjustment':
            case 'salary_movement':
                if (!array_key_exists('new_salary_base', $payload)) {
                    throw new \RuntimeException(__('hr_decision_salary_required'));
                }
                $patch['salary_base'] = round((float) $payload['new_salary_base'], 2);
                break;
            case 'transfer':
                if (isset($payload['new_department_id']) && (int) $payload['new_department_id'] > 0) {
                    $patch['department_id'] = (int) $payload['new_department_id'];
                }
                if (isset($payload['new_branch_id']) && (int) $payload['new_branch_id'] > 0) {
                    $patch['branch_id'] = (int) $payload['new_branch_id'];
                }
                if (isset($payload['new_job_title']) && trim((string) $payload['new_job_title']) !== '') {
                    $patch['job_title'] = substr(trim((string) $payload['new_job_title']), 0, 190);
                }
                break;
            case 'salary_stop':
                // Prefer inactive over zeroing salary (payroll formula untouched).
                $patch['status'] = 'inactive';
                break;
            case 'absence_deduction':
                // Record-only: no payroll engine / attendance formula rewrite in Phase M.
                break;
            case 'termination':
                $patch['status'] = 'terminated';
                break;
            default:
                throw new \RuntimeException(__('invalid_request'));
        }

        if ($patch !== []) {
            $empModel->update($employeeId, $patch);
            if (array_key_exists('salary_base', $patch)) {
                (new HrEmployeeIntegrityService())->maybeAuditOpsSalaryChange(
                    $employeeId,
                    $old,
                    array_merge($old, $patch),
                    'hr_decision_' . $type
                );
            }
            (new AuditService())->log('hr_decision_employee_patch', 'hr_employees', $employeeId, [
                'company_id' => $companyId,
                'decision_id' => (int) ($row['id'] ?? 0),
                'decision_type' => $type,
                'patch' => $patch,
                'actor_user_id' => $actorUserId,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function requirePending(int $companyId, int $decisionId): array
    {
        $row = $this->find($companyId, $decisionId);
        if ($row === null) {
            throw new \RuntimeException(__('no_records'));
        }
        if (strtolower((string) ($row['status'] ?? '')) !== 'pending') {
            throw new \RuntimeException(__('leave_not_pending'));
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function assertEmployee(int $companyId, int $employeeId): array
    {
        $row = (new Employee())->queryOne(
            'SELECT * FROM rateb_employees WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $employeeId, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException(__('access_denied'));
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $emp
     * @return array<string, mixed>
     */
    private function normalizePayload(string $type, array $input, array $emp): array
    {
        $payload = [];
        if (isset($input['payload']) && is_array($input['payload'])) {
            $payload = $input['payload'];
        }
        foreach ([
            'new_salary_base', 'new_job_title', 'new_job_title_id', 'new_department_id',
            'new_branch_id', 'deduction_days', 'deduction_amount', 'note',
        ] as $key) {
            if (array_key_exists($key, $input) && !array_key_exists($key, $payload)) {
                $payload[$key] = $input[$key];
            }
        }
        $payload['previous_salary_base'] = isset($emp['salary_base']) ? (float) $emp['salary_base'] : null;
        $payload['previous_status'] = (string) ($emp['status'] ?? '');
        $payload['previous_department_id'] = (int) ($emp['department_id'] ?? 0);
        $payload['previous_job_title'] = (string) ($emp['job_title'] ?? '');

        if (in_array($type, ['salary_adjustment', 'salary_movement'], true)) {
            if (!array_key_exists('new_salary_base', $payload) || $payload['new_salary_base'] === '' || $payload['new_salary_base'] === null) {
                throw new \RuntimeException(__('hr_decision_salary_required'));
            }
        }
        if ($type === 'termination') {
            $payload['termination_marker'] = true;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function decodePayload(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }
        $raw = trim((string) $json);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function nextDecisionNo(int $companyId): string
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rateb_hr_decisions WHERE company_id = :cid'
        );
        $stmt->execute(['cid' => $companyId]);
        $n = (int) ($stmt->fetchColumn() ?: 0) + 1;

        return 'HD-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
