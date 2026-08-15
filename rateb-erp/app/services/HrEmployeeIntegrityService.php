<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Employee;
use Rateb\App\Models\HrmEmployeeProfile;
use Rateb\App\Models\PayrollAudit;
use Rateb\App\Core\Database;

/**
 * Phase C — Employee Master integrity diagnostics + salary-change governance helpers.
 *
 * Canonical live employee master: rateb_employees (id = primary identity).
 * HRMS rateb_hrm_employee_profiles is additive overlay via legacy_employee_id.
 *
 * Diagnostics are READ-ONLY — never DELETE/merge/repair production rows from here.
 */
final class HrEmployeeIntegrityService
{
    public const CANONICAL_TABLE = 'rateb_employees';

    /**
     * Record ops salary_base change with old/new/company/effective marker.
     * Uses existing AuditService (+ optional PayrollAudit row). Does not change payroll engine.
     *
     * @param array<string, mixed>|null $old
     * @param array<string, mixed> $newData
     */
    public function maybeAuditOpsSalaryChange(int $employeeId, ?array $old, array $newData, string $source = 'hr_employees_crud'): void
    {
        if ($employeeId < 1 || $old === null) {
            return;
        }
        if (!array_key_exists('salary_base', $newData)) {
            return;
        }
        $oldSalary = round((float) ($old['salary_base'] ?? 0), 2);
        $newSalary = round((float) ($newData['salary_base'] ?? 0), 2);
        if (abs($oldSalary - $newSalary) < 0.005) {
            return;
        }
        $companyId = (int) ($old['company_id'] ?? $newData['company_id'] ?? 0);
        $payload = [
            'employee_id' => $employeeId,
            'company_id' => $companyId,
            'old_salary_base' => $oldSalary,
            'new_salary_base' => $newSalary,
            'effective_date' => date('Y-m-d'),
            'source' => $source,
            'change_type' => 'recurring_salary_change',
            'employee_code' => $old['employee_code'] ?? ($newData['employee_code'] ?? null),
        ];
        try {
            (new AuditService())->log('salary_changed', 'hr_employees', $employeeId, $payload);
        } catch (\Throwable $e) {
            // Audit must not block employee save.
        }
        if ($companyId < 1) {
            return;
        }
        try {
            $uid = (int) (\Rateb\App\Core\SessionManager::get('rateb_user_id') ?? 0);
            (new PayrollAudit())->create([
                'public_uuid' => $this->uuidV4(),
                'company_id' => $companyId,
                'branch_id' => isset($old['branch_id']) ? (int) $old['branch_id'] : null,
                'entity_type' => 'hr_employees',
                'entity_id' => $employeeId,
                'action' => 'salary_changed',
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status' => 'active',
                'version' => 1,
                'created_by' => $uid > 0 ? $uid : null,
                'updated_by' => $uid > 0 ? $uid : null,
            ]);
        } catch (\Throwable $e) {
            // Optional secondary audit store.
        }
    }

    /**
     * Audit salary_base on employee create (initial assignment).
     *
     * @param array<string, mixed> $data
     */
    public function maybeAuditOpsSalaryCreated(int $employeeId, array $data, string $source = 'hr_employees_crud'): void
    {
        if ($employeeId < 1 || !array_key_exists('salary_base', $data)) {
            return;
        }
        $salary = round((float) ($data['salary_base'] ?? 0), 2);
        if ($salary <= 0) {
            return;
        }
        $companyId = (int) ($data['company_id'] ?? 0);
        $payload = [
            'employee_id' => $employeeId,
            'company_id' => $companyId,
            'old_salary_base' => null,
            'new_salary_base' => $salary,
            'effective_date' => date('Y-m-d'),
            'source' => $source,
            'change_type' => 'salary_created',
            'employee_code' => $data['employee_code'] ?? null,
        ];
        try {
            (new AuditService())->log('salary_created', 'hr_employees', $employeeId, $payload);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Enterprise payroll employee_salary basic_salary change (company-scoped path).
     *
     * @param array<string, mixed> $oldRow
     * @param array<string, mixed> $patch
     */
    public function maybeAuditEnterpriseSalaryChange(int $salaryRowId, array $oldRow, array $patch, int $companyId): void
    {
        if (!array_key_exists('basic_salary', $patch)) {
            return;
        }
        $oldSalary = round((float) ($oldRow['basic_salary'] ?? 0), 2);
        $newSalary = round((float) ($patch['basic_salary'] ?? 0), 2);
        if (abs($oldSalary - $newSalary) < 0.005) {
            return;
        }
        $payload = [
            'salary_row_id' => $salaryRowId,
            'company_id' => $companyId,
            'old_basic_salary' => $oldSalary,
            'new_basic_salary' => $newSalary,
            'effective_from' => $patch['effective_from'] ?? ($oldRow['effective_from'] ?? null),
            'effective_to' => $patch['effective_to'] ?? ($oldRow['effective_to'] ?? null),
            'legacy_employee_id' => $oldRow['legacy_employee_id'] ?? ($patch['legacy_employee_id'] ?? null),
            'hrm_employee_profile_id' => $oldRow['hrm_employee_profile_id'] ?? ($patch['hrm_employee_profile_id'] ?? null),
            'source' => 'enterprise_employee_salary',
            'change_type' => 'recurring_salary_change',
        ];
        try {
            (new AuditService())->log('salary_changed', 'payroll_employee_salary', $salaryRowId, $payload);
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $uid = (int) (\Rateb\App\Core\SessionManager::get('rateb_user_id') ?? 0);
            (new PayrollAudit())->create([
                'public_uuid' => $this->uuidV4(),
                'company_id' => $companyId,
                'branch_id' => isset($oldRow['branch_id']) ? (int) $oldRow['branch_id'] : null,
                'entity_type' => 'payroll_employee_salary',
                'entity_id' => $salaryRowId,
                'action' => 'salary_changed',
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'status' => 'active',
                'version' => 1,
                'created_by' => $uid > 0 ? $uid : null,
                'updated_by' => $uid > 0 ? $uid : null,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Read-only integrity report for one company. Never mutates data.
     *
     * @return array{
     *   company_id: int,
     *   duplicates: array<string, list<array<string,mixed>>>,
     *   orphans: array<string, int>,
     *   hrms: array<string, int>,
     *   notes: list<string>
     * }
     */
    public function diagnoseCompany(int $companyId): array
    {
        if ($companyId < 1) {
            return [
                'company_id' => 0,
                'duplicates' => [],
                'orphans' => [],
                'hrms' => [],
                'notes' => ['company_id_required'],
            ];
        }
        $emp = new Employee();
        $hrm = new HrmEmployeeProfile();

        $dupUser = $emp->query(
            'SELECT user_id, COUNT(*) AS c
             FROM rateb_employees
             WHERE company_id = :cid AND user_id IS NOT NULL AND user_id > 0
             GROUP BY user_id HAVING COUNT(*) > 1
             LIMIT 100',
            ['cid' => $companyId]
        );
        $dupNational = $emp->query(
            'SELECT national_id, COUNT(*) AS c
             FROM rateb_employees
             WHERE company_id = :cid AND national_id IS NOT NULL AND TRIM(national_id) <> \'\'
             GROUP BY national_id HAVING COUNT(*) > 1
             LIMIT 100',
            ['cid' => $companyId]
        );
        $dupEmail = $emp->query(
            'SELECT LOWER(TRIM(email)) AS email_norm, COUNT(*) AS c
             FROM rateb_employees
             WHERE company_id = :cid AND email IS NOT NULL AND TRIM(email) <> \'\'
             GROUP BY LOWER(TRIM(email)) HAVING COUNT(*) > 1
             LIMIT 100',
            ['cid' => $companyId]
        );

        $orphanAttendance = (int) (($emp->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_attendance_records a
             LEFT JOIN rateb_employees e ON e.id = a.employee_id AND e.company_id = a.company_id
             WHERE a.company_id = :cid AND e.id IS NULL',
            ['cid' => $companyId]
        )['c'] ?? 0));
        $orphanLeave = (int) (($emp->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_leave_requests lr
             LEFT JOIN rateb_employees e ON e.id = lr.employee_id AND e.company_id = lr.company_id
             WHERE lr.company_id = :cid AND e.id IS NULL',
            ['cid' => $companyId]
        )['c'] ?? 0));
        $orphanPayrollLines = (int) (($emp->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_payroll_lines pl
             LEFT JOIN rateb_employees e ON e.id = pl.employee_id AND e.company_id = pl.company_id
             WHERE pl.company_id = :cid AND e.id IS NULL',
            ['cid' => $companyId]
        )['c'] ?? 0));
        $crossCompanyLeave = (int) (($emp->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_leave_requests lr
             INNER JOIN rateb_employees e ON e.id = lr.employee_id
             WHERE lr.company_id = :cid AND e.company_id <> lr.company_id',
            ['cid' => $companyId]
        )['c'] ?? 0));

        $hrmsUnlinked = (int) (($hrm->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_employee_profiles
             WHERE company_id = :cid AND deleted_at IS NULL
               AND (legacy_employee_id IS NULL OR legacy_employee_id = 0)',
            ['cid' => $companyId]
        )['c'] ?? 0));
        $hrmsOrphanLink = (int) (($hrm->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_hrm_employee_profiles p
             LEFT JOIN rateb_employees e ON e.id = p.legacy_employee_id AND e.company_id = p.company_id
             WHERE p.company_id = :cid AND p.deleted_at IS NULL
               AND p.legacy_employee_id IS NOT NULL AND p.legacy_employee_id > 0
               AND e.id IS NULL',
            ['cid' => $companyId]
        )['c'] ?? 0));

        $orphanContracts = $this->safeCount(
            $emp,
            'SELECT COUNT(*) AS c FROM rateb_hr_employment_contracts c
             LEFT JOIN rateb_employees e ON e.id = c.employee_id AND e.company_id = c.company_id
             WHERE c.company_id = :cid AND e.id IS NULL',
            ['cid' => $companyId],
            'rateb_hr_employment_contracts'
        );
        $activeZeroSalaryContracts = $this->safeCount(
            $emp,
            "SELECT COUNT(*) AS c FROM rateb_hr_employment_contracts
             WHERE company_id = :cid AND status = 'active'
               AND (salary IS NULL OR salary <= 0)",
            ['cid' => $companyId],
            'rateb_hr_employment_contracts'
        );
        $orphanSalaryRows = $this->safeCount(
            $emp,
            'SELECT COUNT(*) AS c FROM rateb_payroll_employee_salary s
             LEFT JOIN rateb_employees e ON e.id = s.legacy_employee_id AND e.company_id = s.company_id
             WHERE s.company_id = :cid AND s.deleted_at IS NULL
               AND s.legacy_employee_id IS NOT NULL AND s.legacy_employee_id > 0
               AND e.id IS NULL',
            ['cid' => $companyId],
            'rateb_payroll_employee_salary'
        );
        $employeesZeroSalary = (int) (($emp->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_employees
             WHERE company_id = :cid AND status = 'active'
               AND (salary_base IS NULL OR salary_base <= 0)",
            ['cid' => $companyId]
        )['c'] ?? 0));

        return [
            'company_id' => $companyId,
            'duplicates' => [
                'user_id' => is_array($dupUser) ? $dupUser : [],
                'national_id' => is_array($dupNational) ? $dupNational : [],
                'email' => is_array($dupEmail) ? $dupEmail : [],
            ],
            'orphans' => [
                'attendance_missing_employee' => $orphanAttendance,
                'leave_missing_employee' => $orphanLeave,
                'payroll_lines_missing_employee' => $orphanPayrollLines,
                'leave_cross_company_employee' => $crossCompanyLeave,
                'contracts_missing_employee' => $orphanContracts,
                'payroll_salary_missing_employee' => $orphanSalaryRows,
            ],
            'hrms' => [
                'profiles_unlinked_legacy' => $hrmsUnlinked,
                'profiles_orphan_legacy' => $hrmsOrphanLink,
            ],
            'contracts' => [
                'orphans' => $orphanContracts,
                'active_zero_salary' => $activeZeroSalaryContracts,
            ],
            'salary' => [
                'enterprise_rows_orphan_legacy' => $orphanSalaryRows,
                'active_employees_zero_salary_base' => $employeesZeroSalary,
            ],
            'notes' => [
                'Email duplicates are informational only — email is not unique employee identity.',
                'No automatic merge/delete performed.',
                'FK migrations deferred until orphan counts are zero.',
                'Contract and salary checks are read-only COUNTs (Phase T).',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function safeCount(Employee $emp, string $sql, array $params, string $table): int
    {
        try {
            if (!Database::tableExists($table)) {
                return 0;
            }
            $row = $emp->queryOne($sql, $params);

            return (int) ($row['c'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
