<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;

/**
 * Phase O — Organization structure (read aggregation).
 *
 * Canonical: rateb_hr_departments + rateb_hr_job_titles + rateb_employees.
 * Reporting line: optional soft-read from HRMS manager_profile_id only — never invent hierarchy.
 */
final class HrOrganizationService
{
    public const EMP_LIMIT_PER_DEPT = 50;

    /**
     * @param array{department_id?:int,job_title_id?:int,status?:string} $filters
     * @return array<string, mixed>
     */
    public function structure(int $companyId, array $filters = []): array
    {
        if ($companyId < 1) {
            return ['departments' => [], 'unassigned' => [], 'totals' => ['employees' => 0, 'departments' => 0]];
        }

        $deptId = max(0, (int) ($filters['department_id'] ?? 0));
        $jobTitleId = max(0, (int) ($filters['job_title_id'] ?? 0));
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && !in_array($status, ['active', 'inactive', 'terminated'], true)) {
            $status = '';
        }

        $departments = $this->listDepartments($companyId, $deptId);
        $counts = $this->employeeCountsByDepartment($companyId, $jobTitleId, $status);
        $managers = $this->optionalManagersByEmployee($companyId);

        $tree = [];
        $totalEmployees = 0;
        foreach ($departments as $dept) {
            $id = (int) ($dept['id'] ?? 0);
            $emps = $this->employeesInDepartment($companyId, $id, $jobTitleId, $status, self::EMP_LIMIT_PER_DEPT);
            foreach ($emps as &$e) {
                $eid = (int) ($e['id'] ?? 0);
                $e['manager_name'] = (string) ($managers[$eid] ?? '');
                $e['360_url'] = rateb_url(rateb_app_route('hr/employees/' . $eid));
            }
            unset($e);
            $count = (int) ($counts[$id] ?? count($emps));
            $totalEmployees += $count;
            $tree[] = [
                'id' => $id,
                'name' => (string) ($dept['name'] ?? ''),
                'code' => (string) ($dept['code'] ?? ''),
                'employee_count' => $count,
                'employees' => $emps,
                'truncated' => count($emps) < $count,
            ];
        }

        $unassigned = $this->employeesInDepartment($companyId, 0, $jobTitleId, $status, self::EMP_LIMIT_PER_DEPT);
        foreach ($unassigned as &$e) {
            $eid = (int) ($e['id'] ?? 0);
            $e['manager_name'] = (string) ($managers[$eid] ?? '');
            $e['360_url'] = rateb_url(rateb_app_route('hr/employees/' . $eid));
        }
        unset($e);
        $unassignedCount = (int) ($counts[0] ?? count($unassigned));
        $totalEmployees += $unassignedCount;

        return [
            'departments' => $tree,
            'unassigned' => [
                'employee_count' => $unassignedCount,
                'employees' => $unassigned,
                'truncated' => count($unassigned) < $unassignedCount,
            ],
            'totals' => [
                'employees' => $totalEmployees,
                'departments' => count($tree),
            ],
            'reporting_line_note' => 'optional_hrms_soft_link_only',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listDepartments(int $companyId, int $onlyId = 0): array
    {
        $sql = 'SELECT id, name, code, status FROM rateb_hr_departments WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($onlyId > 0) {
            $sql .= ' AND id = :id';
            $params['id'] = $onlyId;
        }
        $sql .= ' ORDER BY name ASC LIMIT 200';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listJobTitles(int $companyId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, code, status FROM rateb_hr_job_titles
             WHERE company_id = :cid ORDER BY code ASC, name ASC LIMIT 300'
        );
        $stmt->execute(['cid' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, int> department_id => count (0 = unassigned)
     */
    private function employeeCountsByDepartment(int $companyId, int $jobTitleId, string $status): array
    {
        $sql = 'SELECT COALESCE(department_id, 0) AS dept_id, COUNT(*) AS c
                FROM rateb_employees WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if ($jobTitleId > 0) {
            $sql .= ' AND job_title_id = :jid';
            $params['jid'] = $jobTitleId;
        }
        if ($status !== '') {
            $sql .= ' AND status = :st';
            $params['st'] = $status;
        }
        $sql .= ' GROUP BY COALESCE(department_id, 0)';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) ($row['dept_id'] ?? 0)] = (int) ($row['c'] ?? 0);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function employeesInDepartment(
        int $companyId,
        int $departmentId,
        int $jobTitleId,
        string $status,
        int $limit
    ): array {
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT e.id, e.name, e.employee_code, e.status, e.job_title, e.job_title_id, e.department_id
                FROM rateb_employees e
                WHERE e.company_id = :cid';
        $params = ['cid' => $companyId];
        if ($departmentId > 0) {
            $sql .= ' AND e.department_id = :did';
            $params['did'] = $departmentId;
        } else {
            $sql .= ' AND (e.department_id IS NULL OR e.department_id = 0)';
        }
        if ($jobTitleId > 0) {
            $sql .= ' AND e.job_title_id = :jid';
            $params['jid'] = $jobTitleId;
        }
        if ($status !== '') {
            $sql .= ' AND e.status = :st';
            $params['st'] = $status;
        }
        $sql .= ' ORDER BY e.name ASC LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Soft reporting labels only — one batched query, no invented hierarchy.
     *
     * @return array<int, string> employee_id => manager display name
     */
    private function optionalManagersByEmployee(int $companyId): array
    {
        if (!Database::tableExists('rateb_hrm_employee_profiles')) {
            return [];
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT p.legacy_employee_id AS employee_id,
                        TRIM(CONCAT(COALESCE(m.first_name,''), ' ', COALESCE(m.last_name,''))) AS manager_name
                 FROM rateb_hrm_employee_profiles p
                 INNER JOIN rateb_hrm_employee_profiles m
                    ON m.id = p.manager_profile_id AND m.company_id = p.company_id
                 WHERE p.company_id = :cid
                   AND p.legacy_employee_id IS NOT NULL
                   AND p.legacy_employee_id > 0
                   AND p.manager_profile_id IS NOT NULL
                   AND p.deleted_at IS NULL
                 LIMIT 2000"
            );
            $stmt->execute(['cid' => $companyId]);
            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $eid = (int) ($row['employee_id'] ?? 0);
                $name = trim((string) ($row['manager_name'] ?? ''));
                if ($eid > 0 && $name !== '') {
                    $out[$eid] = $name;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
