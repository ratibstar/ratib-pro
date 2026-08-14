<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\AttendanceRecord;
use Rateb\App\Models\Employee;
use Rateb\App\Models\HrEmployeeRequest;
use Rateb\App\Models\LeaveRequest;
use PDO;

/**
 * Phase P — Manager self-service over existing HR data.
 *
 * Team membership uses optional HRMS manager_profile_id soft-link only.
 * Never invents a manager hierarchy. Approvals reuse HrApprovalInboxService + matrix.
 */
final class HrManagerTeamService
{
    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function myTeam(int $userId, int $companyId): array
    {
        $gate = $this->resolveManager($userId, $companyId);
        if ((int) ($gate['status'] ?? 0) !== 200) {
            return $gate;
        }
        $teamIds = $gate['team_ids'];
        $members = $this->loadMembers($companyId, $teamIds, false);

        return $this->ok([
            'manager_employee_id' => $gate['manager_employee_id'],
            'reporting_source' => 'hrms_manager_profile_id_soft_link',
            'team_count' => count($members),
            'members' => $members,
            'note' => $teamIds === []
                ? 'no_soft_reporting_line_configured'
                : 'team_from_optional_hrms_soft_link',
        ]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function teamAttendance(int $userId, int $companyId, ?string $from = null, ?string $to = null): array
    {
        $gate = $this->resolveManager($userId, $companyId);
        if ((int) ($gate['status'] ?? 0) !== 200) {
            return $gate;
        }
        $teamIds = $gate['team_ids'];
        if ($teamIds === []) {
            return $this->ok(['items' => [], 'from' => $from, 'to' => $to]);
        }
        $from = $this->normalizeDate($from) ?? date('Y-m-01');
        $to = $this->normalizeDate($to) ?? date('Y-m-d');
        if ($from > $to) {
            return $this->fail(422, 'invalid_date_range', 'Invalid date range');
        }

        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $params = array_merge([$companyId, $from, $to], $teamIds);
        $rows = (new AttendanceRecord())->query(
            "SELECT a.id, a.employee_id, a.date, a.status, a.check_in, a.check_out, e.name AS employee_name, e.employee_code
             FROM rateb_attendance_records a
             JOIN rateb_employees e ON e.id = a.employee_id AND e.company_id = a.company_id
             WHERE a.company_id = ?
               AND a.date BETWEEN ? AND ?
               AND a.employee_id IN ({$placeholders})
             ORDER BY a.date DESC, e.name ASC
             LIMIT 500",
            $params
        );

        return $this->ok([
            'from' => $from,
            'to' => $to,
            'items' => is_array($rows) ? $rows : [],
        ]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function teamLeave(int $userId, int $companyId, ?string $status = null): array
    {
        $gate = $this->resolveManager($userId, $companyId);
        if ((int) ($gate['status'] ?? 0) !== 200) {
            return $gate;
        }
        $teamIds = $gate['team_ids'];
        if ($teamIds === []) {
            return $this->ok(['items' => []]);
        }
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $params = array_merge([$companyId], $teamIds);
        $sql = "SELECT lr.id, lr.employee_id, lr.leave_type_id, lr.start_date, lr.end_date, lr.days, lr.status, lr.created_at,
                       e.name AS employee_name, e.employee_code
                FROM rateb_leave_requests lr
                JOIN rateb_employees e ON e.id = lr.employee_id AND e.company_id = lr.company_id
                WHERE lr.company_id = ?
                  AND lr.employee_id IN ({$placeholders})";
        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= ' AND lr.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY lr.id DESC LIMIT 200';
        $rows = (new LeaveRequest())->query($sql, $params);

        return $this->ok(['items' => is_array($rows) ? $rows : []]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function teamRequests(int $userId, int $companyId, ?string $status = null): array
    {
        $gate = $this->resolveManager($userId, $companyId);
        if ((int) ($gate['status'] ?? 0) !== 200) {
            return $gate;
        }
        $teamIds = $gate['team_ids'];
        if ($teamIds === []) {
            return $this->ok(['items' => []]);
        }
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $params = array_merge([$companyId], $teamIds);
        $sql = "SELECT r.id, r.employee_id, r.request_no, r.request_type, r.request_date, r.status, r.notes, r.created_at,
                       e.name AS employee_name, e.employee_code
                FROM rateb_hr_employee_requests r
                JOIN rateb_employees e ON e.id = r.employee_id AND e.company_id = r.company_id
                WHERE r.company_id = ?
                  AND r.employee_id IN ({$placeholders})";
        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= ' AND r.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY r.id DESC LIMIT 200';
        $rows = (new HrEmployeeRequest())->query($sql, $params);

        return $this->ok(['items' => is_array($rows) ? $rows : []]);
    }

    /**
     * Team approvals — same Oversight/Matrix decide path; filtered to soft-linked team.
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function teamApprovals(int $userId, int $companyId, ?string $type = null): array
    {
        $gate = $this->resolveManager($userId, $companyId);
        if ((int) ($gate['status'] ?? 0) !== 200) {
            return $gate;
        }
        $teamIds = array_fill_keys($gate['team_ids'], true);
        $inbox = (new HrApprovalInboxService())->inbox($companyId, $type, 200, $userId);
        $items = [];
        foreach ($inbox['items'] as $item) {
            $eid = (int) ($item['employee_id'] ?? 0);
            if ($eid > 0 && isset($teamIds[$eid]) && !empty($item['can_act'])) {
                $items[] = $item;
            } elseif ($eid > 0 && isset($teamIds[$eid])) {
                $items[] = $item;
            }
        }

        return $this->ok([
            'items' => $items,
            'counts' => [
                'total' => count($items),
                'actionable' => count(array_filter($items, static fn ($i) => !empty($i['can_act']))),
            ],
            'engine' => 'ApprovalOversightService+HrApprovalMatrixService',
        ]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function decide(
        int $userId,
        int $companyId,
        string $sourceKey,
        int $recordId,
        string $action,
        ?string $comment = null
    ): array {
        $gate = $this->resolveManager($userId, $companyId);
        if ((int) ($gate['status'] ?? 0) !== 200) {
            return $gate;
        }
        $teamIds = array_fill_keys($gate['team_ids'], true);
        $employeeId = $this->resolveRecordEmployeeId($sourceKey, $recordId, $companyId);
        if ($employeeId < 1 || !isset($teamIds[$employeeId])) {
            return $this->fail(403, 'not_team_member', 'Record is outside manager soft-linked team');
        }

        try {
            $result = (new HrApprovalInboxService())->decide(
                $companyId,
                $userId,
                $sourceKey,
                $recordId,
                $action,
                $comment
            );
        } catch (\Throwable $e) {
            return $this->fail(403, 'access_denied', $e->getMessage());
        }

        return $this->ok(['decision' => $result]);
    }

    /**
     * Team member profile (no salary unless actor has salary RBAC).
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function teamEmployeeProfile(int $userId, int $companyId, int $employeeId): array
    {
        $gate = $this->resolveManager($userId, $companyId);
        if ((int) ($gate['status'] ?? 0) !== 200) {
            return $gate;
        }
        if ($employeeId < 1 || !in_array($employeeId, $gate['team_ids'], true)) {
            return $this->fail(403, 'not_team_member', 'Employee is not in soft-linked team');
        }
        $members = $this->loadMembers($companyId, [$employeeId], $this->canViewSalary($userId));
        if ($members === []) {
            return $this->fail(404, 'not_found', 'Employee not found');
        }

        return $this->ok(['profile' => $members[0]]);
    }

    /**
     * @return array{status:int,body?:array<string,mixed>,manager_employee_id?:int,team_ids?:list<int>}
     */
    private function resolveManager(int $userId, int $companyId): array
    {
        $resolved = (new HrEssEmployeeResolverService())->resolveCurrentEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 0) !== 200) {
            return $resolved;
        }
        $managerEmployeeId = (int) ($resolved['body']['employee']['id'] ?? 0);
        if ($managerEmployeeId < 1) {
            return $this->fail(404, 'employee_unbound', 'Employee not bound');
        }

        return [
            'status' => 200,
            'manager_employee_id' => $managerEmployeeId,
            'team_ids' => $this->softLinkedTeamIds($companyId, $managerEmployeeId),
        ];
    }

    /**
     * @return list<int>
     */
    private function softLinkedTeamIds(int $companyId, int $managerEmployeeId): array
    {
        if (!Database::tableExists('rateb_hrm_employee_profiles')) {
            return [];
        }
        try {
            $stmt = Database::connection()->prepare(
                'SELECT p.legacy_employee_id
                 FROM rateb_hrm_employee_profiles p
                 INNER JOIN rateb_hrm_employee_profiles m
                    ON m.id = p.manager_profile_id
                   AND m.company_id = p.company_id
                 WHERE p.company_id = :cid
                   AND m.legacy_employee_id = :mid
                   AND p.legacy_employee_id IS NOT NULL
                   AND p.legacy_employee_id > 0
                   AND p.deleted_at IS NULL
                   AND m.deleted_at IS NULL
                 LIMIT 500'
            );
            $stmt->execute(['cid' => $companyId, 'mid' => $managerEmployeeId]);
            $ids = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $eid = (int) ($row['legacy_employee_id'] ?? 0);
                if ($eid > 0 && $eid !== $managerEmployeeId) {
                    $ids[$eid] = $eid;
                }
            }

            return array_values($ids);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param list<int> $employeeIds
     * @return list<array<string,mixed>>
     */
    private function loadMembers(int $companyId, array $employeeIds, bool $includeSalary): array
    {
        if ($employeeIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $salaryCol = $includeSalary ? ', e.salary_base' : '';
        $params = array_merge([$companyId], $employeeIds);
        $rows = (new Employee())->query(
            "SELECT e.id, e.employee_code, e.name, e.email, e.phone, e.status, e.hire_date,
                    e.department_id, e.job_title_id, e.job_title AS job_title_text,
                    d.name AS department_name, jt.name AS job_title_name
                    {$salaryCol}
             FROM rateb_employees e
             LEFT JOIN rateb_hr_departments d ON d.id = e.department_id AND d.company_id = e.company_id
             LEFT JOIN rateb_hr_job_titles jt ON jt.id = e.job_title_id AND jt.company_id = e.company_id
             WHERE e.company_id = ?
               AND e.id IN ({$placeholders})
             ORDER BY e.name ASC
             LIMIT 500",
            $params
        );
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $job = trim((string) ($row['job_title_name'] ?? ''));
            if ($job === '') {
                $job = trim((string) ($row['job_title_text'] ?? ''));
            }
            $item = [
                'id' => (int) ($row['id'] ?? 0),
                'employee_code' => (string) ($row['employee_code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'hire_date' => (string) ($row['hire_date'] ?? ''),
                'department_id' => (int) ($row['department_id'] ?? 0),
                'department_name' => (string) ($row['department_name'] ?? ''),
                'job_title' => $job,
                '360_url' => rateb_url(rateb_app_route('hr/employees/' . (int) ($row['id'] ?? 0))),
            ];
            if ($includeSalary) {
                $item['salary_base'] = $row['salary_base'] ?? null;
            }
            $out[] = $item;
        }

        return $out;
    }

    private function resolveRecordEmployeeId(string $sourceKey, int $recordId, int $companyId): int
    {
        $table = match ($sourceKey) {
            'hr_leave' => 'rateb_leave_requests',
            'hr_permission' => 'rateb_hr_permission_requests',
            'hr_request' => 'rateb_hr_employee_requests',
            'hr_decision' => 'rateb_hr_decisions',
            default => '',
        };
        if ($table === '' || $recordId < 1) {
            return 0;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT employee_id FROM {$table} WHERE id = :id AND company_id = :cid LIMIT 1"
            );
            $stmt->execute(['id' => $recordId, 'cid' => $companyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) ($row['employee_id'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function canViewSalary(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }
        if (function_exists('rateb_can')) {
            return rateb_can('hr.manage')
                || rateb_can('hr-payroll.view')
                || rateb_can('hr-employees.manage')
                || rateb_can('super-admin');
        }

        return false;
    }

    private function normalizeDate(?string $raw): ?string
    {
        $s = trim((string) $raw);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $s);

        return ($dt && $dt->format('Y-m-d') === $s) ? $s : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{status:int,body:array<string,mixed>}
     */
    private function ok(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'body' => array_merge(['success' => true], $data),
        ];
    }

    /**
     * @return array{status:int,body:array{success:false,code:string,message:string}}
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
