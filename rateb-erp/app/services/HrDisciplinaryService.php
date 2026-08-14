<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Employee;
use Rateb\App\Models\HrmDisciplinaryAction;
use Rateb\App\Models\HrmEmployeeProfile;

/**
 * Phase M — Disciplinary actions linked to rateb_employees via HRMS profile + legacy_employee_id.
 * Reuses rateb_hrm_disciplinary_actions (no parallel disciplinary table).
 */
final class HrDisciplinaryService
{
    /** @var list<string> */
    public const ACTION_TYPES = [
        'warning',
        'written_warning',
        'deduction',
        'suspension',
        'termination_notice',
        'other',
    ];

    public function schemaReady(): bool
    {
        try {
            return Database::tableExists('rateb_hrm_disciplinary_actions')
                && Database::tableExists('rateb_hrm_employee_profiles');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id:int,code:string}
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
        if ($employeeId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $emp = $this->assertEmployee($companyId, $employeeId);
        $profileId = $this->ensureProfileForEmployee($companyId, $emp);
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException(__('hr_disciplinary_title_required'));
        }
        $actionType = trim((string) ($input['action_type'] ?? 'warning'));
        if (!in_array($actionType, self::ACTION_TYPES, true)) {
            $actionType = 'warning';
        }
        $actionDate = trim((string) ($input['action_date'] ?? ''));
        if ($actionDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actionDate)) {
            $actionDate = date('Y-m-d');
        }
        if ($actorUserId < 1) {
            $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        }

        $code = HumanResourcesSupport::nextCode('rateb_hrm_disciplinary_actions', 'HRM-DISC', $companyId);
        $data = [
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::intOrNull($emp['branch_id'] ?? null)
                ?? HumanResourcesSupport::branchId(),
            'employee_profile_id' => $profileId,
            'code' => substr($code, 0, 40),
            'action_type' => $actionType,
            'title' => substr($title, 0, 190),
            'action_date' => $actionDate,
            'description' => HumanResourcesSupport::nullIfEmpty($input['description'] ?? null),
            'notes' => HumanResourcesSupport::nullIfEmpty($input['notes'] ?? null),
            'status' => 'active',
            'version' => 1,
            'created_by' => $actorUserId > 0 ? $actorUserId : null,
            'updated_by' => $actorUserId > 0 ? $actorUserId : null,
        ];
        if ($this->hasLegacyEmployeeColumn()) {
            $data['legacy_employee_id'] = $employeeId;
        }

        $id = (int) (new HrmDisciplinaryAction())->create($data);
        if ($id < 1) {
            throw new \RuntimeException(__('save_failed'));
        }

        (new AuditService())->log('hr_disciplinary_create', 'hr_disciplinary', $id, [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'employee_profile_id' => $profileId,
            'action_type' => $actionType,
            'code' => $code,
        ]);

        return ['id' => $id, 'code' => $code];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(int $companyId, ?int $employeeId = null, int $limit = 200): array
    {
        if ($companyId < 1 || !$this->schemaReady()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $hasLegacy = $this->hasLegacyEmployeeColumn();
        $sql = 'SELECT d.*, e.name AS employee_name, e.employee_code, e.id AS employee_id
                FROM rateb_hrm_disciplinary_actions d
                INNER JOIN rateb_hrm_employee_profiles p
                    ON p.id = d.employee_profile_id AND p.company_id = d.company_id
                LEFT JOIN rateb_employees e
                    ON e.company_id = d.company_id
                   AND e.id = ' . ($hasLegacy
                ? 'COALESCE(d.legacy_employee_id, p.legacy_employee_id)'
                : 'p.legacy_employee_id') . '
                WHERE d.company_id = :cid AND d.deleted_at IS NULL';
        $params = ['cid' => $companyId];
        if ($employeeId !== null && $employeeId > 0) {
            if ($hasLegacy) {
                $sql .= ' AND (d.legacy_employee_id = :eid OR p.legacy_employee_id = :eid2)';
                $params['eid'] = $employeeId;
                $params['eid2'] = $employeeId;
            } else {
                $sql .= ' AND p.legacy_employee_id = :eid';
                $params['eid'] = $employeeId;
            }
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
        return $this->list($companyId, $employeeId, $limit);
    }

    private function hasLegacyEmployeeColumn(): bool
    {
        try {
            return Database::liveTableHasColumn('rateb_hrm_disciplinary_actions', 'legacy_employee_id');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $emp
     */
    private function ensureProfileForEmployee(int $companyId, array $emp): int
    {
        $employeeId = (int) ($emp['id'] ?? 0);
        $existing = (new HrmEmployeeProfile())->queryOne(
            'SELECT id FROM rateb_hrm_employee_profiles
             WHERE company_id = :cid AND legacy_employee_id = :eid AND deleted_at IS NULL
             LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
            return (int) $existing['id'];
        }

        $name = trim((string) ($emp['name'] ?? 'Employee'));
        $parts = preg_split('/\s+/u', $name, 2) ?: [$name];
        $first = substr(trim((string) ($parts[0] ?? 'Employee')), 0, 120) ?: 'Employee';
        $last = substr(trim((string) ($parts[1] ?? '-')), 0, 120) ?: '-';
        HumanResourcesSupport::assertLegacyEmployee($employeeId, $companyId);
        $code = HumanResourcesSupport::nextCode('rateb_hrm_employee_profiles', 'HRM-EMP', $companyId);
        $id = (int) (new HrmEmployeeProfile())->create(array_merge([
            'public_uuid' => HumanResourcesSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => HumanResourcesSupport::intOrNull($emp['branch_id'] ?? null)
                ?? HumanResourcesSupport::branchId(),
            'code' => substr($code, 0, 40),
            'legacy_employee_id' => $employeeId,
            'first_name' => $first,
            'last_name' => $last,
            'email' => HumanResourcesSupport::nullIfEmpty($emp['email'] ?? null),
            'phone' => HumanResourcesSupport::nullIfEmpty($emp['phone'] ?? null),
            'hire_date' => HumanResourcesSupport::nullIfEmpty($emp['hire_date'] ?? null),
            'employment_type' => 'full_time',
            'workflow_status' => 'active',
            'status' => 'active',
            'version' => 1,
        ], HumanResourcesSupport::actorFields(true)));
        if ($id < 1) {
            throw new \RuntimeException(__('save_failed'));
        }

        return $id;
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
}
