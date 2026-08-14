<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;

/**
 * Phase O — HR analytics + report rows (read-only aggregates).
 * Company-scoped, LIMIT/GROUP BY bounded. No payroll formula / SoT changes.
 */
final class HrAnalyticsService
{
    public const REPORT_LIMIT = 500;

    /**
     * @param array{department_id?:int,job_title_id?:int,status?:string,date_from?:string,date_to?:string} $filters
     * @return array<string, mixed>
     */
    public function snapshot(int $companyId, array $filters = [], bool $canViewSalary = false): array
    {
        if ($companyId < 1) {
            return $this->emptySnapshot();
        }
        $filters = $this->normalizeFilters($filters);
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        return [
            'filters' => $filters,
            'headcount' => $this->headcount($companyId, $filters),
            'by_department' => $this->byDepartment($companyId, $filters),
            'by_status' => $this->byStatus($companyId, $filters),
            'hire_terminate' => $this->hireTerminate($companyId, $dateFrom, $dateTo, $filters),
            'attendance' => $this->attendanceSummary($companyId, $dateFrom, $dateTo, $filters),
            'leaves' => $this->leaveSummary($companyId, $dateFrom, $dateTo, $filters),
            'payroll' => $this->payrollSummary($companyId, $dateFrom, $dateTo, $canViewSalary),
            'contracts_expiring' => $this->contractsExpiring($companyId, 30),
            'recruitment' => $this->recruitmentSummary($companyId),
            'attention' => $this->attentionItems($companyId),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function employeeReportRows(int $companyId, array $filters = [], int $limit = self::REPORT_LIMIT): array
    {
        if ($companyId < 1) {
            return [];
        }
        $filters = $this->normalizeFilters($filters);
        $limit = max(1, min(self::REPORT_LIMIT, $limit));
        $sql = 'SELECT e.id, e.employee_code, e.name, e.status, e.hire_date, e.job_title,
                       d.name AS department_name, j.name AS job_title_name
                FROM rateb_employees e
                LEFT JOIN rateb_hr_departments d ON d.id = e.department_id AND d.company_id = e.company_id
                LEFT JOIN rateb_hr_job_titles j ON j.id = e.job_title_id AND j.company_id = e.company_id
                WHERE e.company_id = :cid';
        $params = ['cid' => $companyId];
        $sql .= $this->employeeFilterSql($filters, $params);
        $sql .= ' ORDER BY e.name ASC LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function attendanceReportRows(int $companyId, array $filters = [], int $limit = self::REPORT_LIMIT): array
    {
        if ($companyId < 1) {
            return [];
        }
        $filters = $this->normalizeFilters($filters);
        $limit = max(1, min(self::REPORT_LIMIT, $limit));
        $sql = "SELECT e.employee_code, e.name,
                       SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) AS present_days,
                       SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_days,
                       SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                       SUM(CASE WHEN a.status = 'leave' THEN 1 ELSE 0 END) AS leave_days
                FROM rateb_attendance_records a
                JOIN rateb_employees e ON e.id = a.employee_id AND e.company_id = a.company_id
                WHERE a.company_id = :cid
                  AND a.attendance_date BETWEEN :df AND :dt";
        $params = [
            'cid' => $companyId,
            'df' => $filters['date_from'],
            'dt' => $filters['date_to'],
        ];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        $sql .= ' GROUP BY e.id, e.employee_code, e.name ORDER BY e.name ASC LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function leaveReportRows(int $companyId, array $filters = [], int $limit = self::REPORT_LIMIT): array
    {
        if ($companyId < 1) {
            return [];
        }
        $filters = $this->normalizeFilters($filters);
        $limit = max(1, min(self::REPORT_LIMIT, $limit));
        $sql = 'SELECT e.employee_code, e.name, lt.name AS leave_type, lr.status,
                       lr.start_date, lr.end_date, lr.days
                FROM rateb_leave_requests lr
                JOIN rateb_employees e ON e.id = lr.employee_id AND e.company_id = lr.company_id
                LEFT JOIN rateb_leave_types lt ON lt.id = lr.leave_type_id AND lt.company_id = lr.company_id
                WHERE lr.company_id = :cid
                  AND lr.start_date <= :dt AND lr.end_date >= :df';
        $params = [
            'cid' => $companyId,
            'df' => $filters['date_from'],
            'dt' => $filters['date_to'],
        ];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        $sql .= ' ORDER BY lr.start_date DESC LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payrollSummaryRows(int $companyId, array $filters = [], bool $canViewSalary = false, int $limit = 100): array
    {
        if ($companyId < 1 || !$canViewSalary) {
            return [];
        }
        $filters = $this->normalizeFilters($filters);
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT pp.period_year, pp.period_month, pp.status,
                       COUNT(pl.id) AS line_count,
                       ROUND(SUM(pl.net_salary), 2) AS net_total,
                       ROUND(SUM(pl.gross_salary), 2) AS gross_total
                FROM rateb_payroll_periods pp
                LEFT JOIN rateb_payroll_lines pl ON pl.period_id = pp.id AND pl.company_id = pp.company_id
                WHERE pp.company_id = :cid
                  AND (
                    STR_TO_DATE(CONCAT(pp.period_year, \'-\', LPAD(pp.period_month, 2, \'0\'), \'-01\'), \'%Y-%m-%d\')
                    BETWEEN :df AND :dt
                  )
                GROUP BY pp.id, pp.period_year, pp.period_month, pp.status
                ORDER BY pp.period_year DESC, pp.period_month DESC
                LIMIT ' . $limit;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'cid' => $companyId,
            'df' => $filters['date_from'],
            'dt' => $filters['date_to'],
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contractsExpiryRows(int $companyId, int $withinDays = 60, int $limit = 200): array
    {
        if ($companyId < 1 || !Database::tableExists('rateb_hr_employment_contracts')) {
            return [];
        }
        $withinDays = max(1, min(180, $withinDays));
        $limit = max(1, min(300, $limit));
        try {
            $stmt = Database::connection()->prepare(
                "SELECT c.contract_no, c.end_date, c.status, e.name AS employee_name, e.employee_code
                 FROM rateb_hr_employment_contracts c
                 JOIN rateb_employees e ON e.id = c.employee_id AND e.company_id = c.company_id
                 WHERE c.company_id = :cid
                   AND c.status = 'active'
                   AND c.end_date IS NOT NULL
                   AND c.end_date >= CURDATE()
                   AND c.end_date <= DATE_ADD(CURDATE(), INTERVAL {$withinDays} DAY)
                 ORDER BY c.end_date ASC
                 LIMIT {$limit}"
            );
            $stmt->execute(['cid' => $companyId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<array{status:string,count:int}>
     */
    public function recruitmentSummary(int $companyId): array
    {
        if ($companyId < 1 || !Database::tableExists('rateb_recruitment_candidates')) {
            return [];
        }
        try {
            $stmt = Database::connection()->prepare(
                'SELECT status, COUNT(*) AS c FROM rateb_recruitment_candidates
                 WHERE company_id = :cid GROUP BY status ORDER BY c DESC LIMIT 20'
            );
            $stmt->execute(['cid' => $companyId]);
            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $out[] = [
                    'status' => (string) ($row['status'] ?? ''),
                    'count' => (int) ($row['c'] ?? 0),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Compact widgets for Command Center.
     *
     * @return array<string, mixed>
     */
    public function commandWidgets(int $companyId, bool $canViewSalary = false): array
    {
        if ($companyId < 1) {
            return [
                'headcount_active' => 0,
                'absent_30d' => 0,
                'late_30d' => 0,
                'pending_leaves' => 0,
                'contracts_30d' => 0,
                'by_department_top' => [],
                'salary_avg' => null,
            ];
        }
        $filters = $this->normalizeFilters([]);
        $from = date('Y-m-d', strtotime('-30 days'));
        $to = date('Y-m-d');
        $att = $this->attendanceSummary($companyId, $from, $to, $filters);
        $hc = $this->headcount($companyId, $filters);
        $salaryAvg = null;
        if ($canViewSalary) {
            $stmt = Database::connection()->prepare(
                "SELECT ROUND(AVG(salary_base), 2) FROM rateb_employees
                 WHERE company_id = :cid AND status = 'active' AND salary_base IS NOT NULL AND salary_base > 0"
            );
            $stmt->execute(['cid' => $companyId]);
            $salaryAvg = $stmt->fetchColumn();
            $salaryAvg = $salaryAvg !== false ? (float) $salaryAvg : null;
        }

        return [
            'headcount_active' => (int) ($hc['active'] ?? 0),
            'absent_30d' => (int) ($att['absent'] ?? 0),
            'late_30d' => (int) ($att['late'] ?? 0),
            'pending_leaves' => $this->countPendingLeaves($companyId),
            'contracts_30d' => count($this->contractsExpiryRows($companyId, 30, 50)),
            'by_department_top' => array_slice($this->byDepartment($companyId, $filters), 0, 5),
            'salary_avg' => $salaryAvg,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{total:int,active:int,inactive:int,terminated:int}
     */
    private function headcount(int $companyId, array $filters): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
                    SUM(CASE WHEN status = 'terminated' THEN 1 ELSE 0 END) AS terminated
                FROM rateb_employees WHERE company_id = :cid";
        $params = ['cid' => $companyId];
        $sql .= $this->employeeFilterSql($filters, $params, '', false);
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'inactive' => (int) ($row['inactive'] ?? 0),
            'terminated' => (int) ($row['terminated'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array{department_id:int,department_name:string,count:int}>
     */
    private function byDepartment(int $companyId, array $filters): array
    {
        $sql = 'SELECT COALESCE(e.department_id, 0) AS department_id,
                       COALESCE(d.name, :unassigned) AS department_name,
                       COUNT(*) AS count
                FROM rateb_employees e
                LEFT JOIN rateb_hr_departments d ON d.id = e.department_id AND d.company_id = e.company_id
                WHERE e.company_id = :cid';
        $params = ['cid' => $companyId, 'unassigned' => __('hr_o_unassigned')];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        $sql .= ' GROUP BY COALESCE(e.department_id, 0), d.name ORDER BY count DESC LIMIT 30';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[] = [
                'department_id' => (int) ($row['department_id'] ?? 0),
                'department_name' => (string) ($row['department_name'] ?? ''),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array{status:string,count:int}>
     */
    private function byStatus(int $companyId, array $filters): array
    {
        $sql = 'SELECT status, COUNT(*) AS count FROM rateb_employees WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        $sql .= $this->employeeFilterSql($filters, $params, '', false);
        $sql .= ' GROUP BY status ORDER BY count DESC LIMIT 10';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[] = [
                'status' => (string) ($row['status'] ?? ''),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{hired:int,terminated:int}
     */
    private function hireTerminate(int $companyId, string $from, string $to, array $filters): array
    {
        $hireParams = ['cid' => $companyId, 'df' => $from, 'dt' => $to];
        $hireSql = 'SELECT COUNT(*) FROM rateb_employees
                    WHERE company_id = :cid AND hire_date BETWEEN :df AND :dt';
        $hireSql .= $this->employeeFilterSql($filters, $hireParams, '', false);
        $hiredStmt = Database::connection()->prepare($hireSql);
        $hiredStmt->execute($hireParams);

        $termParams = ['cid' => $companyId];
        $termSql = "SELECT COUNT(*) FROM rateb_employees
                    WHERE company_id = :cid AND status = 'terminated'";
        $termSql .= $this->employeeFilterSql($filters, $termParams, '', false);
        $termStmt = Database::connection()->prepare($termSql);
        $termStmt->execute($termParams);

        return [
            'hired' => (int) ($hiredStmt->fetchColumn() ?: 0),
            'terminated' => (int) ($termStmt->fetchColumn() ?: 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{present:int,absent:int,late:int,leave:int}
     */
    private function attendanceSummary(int $companyId, string $from, string $to, array $filters): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late,
                    SUM(CASE WHEN a.status = 'leave' THEN 1 ELSE 0 END) AS leave_days
                FROM rateb_attendance_records a
                JOIN rateb_employees e ON e.id = a.employee_id AND e.company_id = a.company_id
                WHERE a.company_id = :cid AND a.attendance_date BETWEEN :df AND :dt";
        $params = ['cid' => $companyId, 'df' => $from, 'dt' => $to];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'present' => (int) ($row['present'] ?? 0),
            'absent' => (int) ($row['absent'] ?? 0),
            'late' => (int) ($row['late'] ?? 0),
            'leave' => (int) ($row['leave_days'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{pending:int,approved:int,rejected:int,days_approved:float}
     */
    private function leaveSummary(int $companyId, string $from, string $to, array $filters): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN lr.status = 'approved' THEN lr.days ELSE 0 END) AS days_approved
                FROM rateb_leave_requests lr
                JOIN rateb_employees e ON e.id = lr.employee_id AND e.company_id = lr.company_id
                WHERE lr.company_id = :cid
                  AND lr.start_date <= :dt AND lr.end_date >= :df";
        $params = ['cid' => $companyId, 'df' => $from, 'dt' => $to];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'days_approved' => (float) ($row['days_approved'] ?? 0),
        ];
    }

    /**
     * @return array{periods:int,net_total:?float}
     */
    private function payrollSummary(int $companyId, string $from, string $to, bool $canViewSalary): array
    {
        if (!$canViewSalary) {
            return ['periods' => 0, 'net_total' => null];
        }
        $rows = $this->payrollSummaryRows($companyId, [
            'date_from' => $from,
            'date_to' => $to,
        ], true, 50);
        $net = 0.0;
        foreach ($rows as $r) {
            $net += (float) ($r['net_total'] ?? 0);
        }

        return [
            'periods' => count($rows),
            'net_total' => round($net, 2),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contractsExpiring(int $companyId, int $days): array
    {
        return $this->contractsExpiryRows($companyId, $days, 10);
    }

    /**
     * @return list<array{code:string,label:string,count:int,url:string}>
     */
    private function attentionItems(int $companyId): array
    {
        $items = [];
        $pendingLeaves = $this->countPendingLeaves($companyId);
        if ($pendingLeaves > 0) {
            $items[] = [
                'code' => 'pending_leaves',
                'label' => 'hr_pending_leaves',
                'count' => $pendingLeaves,
                'url' => rateb_url(rateb_app_route('hr/approvals-inbox')) . '?type=leave',
            ];
        }
        $contracts = count($this->contractsExpiryRows($companyId, 30, 100));
        if ($contracts > 0) {
            $items[] = [
                'code' => 'contracts',
                'label' => 'hr_cc_contracts_expiring',
                'count' => $contracts,
                'url' => rateb_url(rateb_app_route('hr/employment-contracts')),
            ];
        }
        if (Database::tableExists('rateb_hr_decisions')) {
            try {
                $stmt = Database::connection()->prepare(
                    "SELECT COUNT(*) FROM rateb_hr_decisions WHERE company_id = :cid AND status = 'pending'"
                );
                $stmt->execute(['cid' => $companyId]);
                $n = (int) ($stmt->fetchColumn() ?: 0);
                if ($n > 0) {
                    $items[] = [
                        'code' => 'decisions',
                        'label' => 'hr_cc_pending_decisions',
                        'count' => $n,
                        'url' => rateb_url(rateb_app_route('hr/approvals-inbox')) . '?type=decision',
                    ];
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $items;
    }

    private function countPendingLeaves(int $companyId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM rateb_leave_requests WHERE company_id = :cid AND status = 'pending'"
        );
        $stmt->execute(['cid' => $companyId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $params
     */
    private function employeeFilterSql(array $filters, array &$params, string $alias = '', bool $includeStatus = true): string
    {
        $p = $alias !== '' ? $alias . '.' : '';
        $sql = '';
        $did = (int) ($filters['department_id'] ?? 0);
        if ($did > 0) {
            $sql .= " AND {$p}department_id = :did";
            $params['did'] = $did;
        }
        $jid = (int) ($filters['job_title_id'] ?? 0);
        if ($jid > 0) {
            $sql .= " AND {$p}job_title_id = :jid";
            $params['jid'] = $jid;
        }
        if ($includeStatus) {
            $st = trim((string) ($filters['status'] ?? ''));
            if ($st !== '' && in_array($st, ['active', 'inactive', 'terminated'], true)) {
                $sql .= " AND {$p}status = :st";
                $params['st'] = $st;
            }
        }

        return $sql;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{department_id:int,job_title_id:int,status:string,date_from:string,date_to:string}
     */
    private function normalizeFilters(array $filters): array
    {
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-01');
        }
        if ($to === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'department_id' => max(0, (int) ($filters['department_id'] ?? 0)),
            'job_title_id' => max(0, (int) ($filters['job_title_id'] ?? 0)),
            'status' => trim((string) ($filters['status'] ?? '')),
            'date_from' => $from,
            'date_to' => $to,
        ];
    }

    /** @return array<string, mixed> */
    private function emptySnapshot(): array
    {
        return [
            'filters' => $this->normalizeFilters([]),
            'headcount' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'terminated' => 0],
            'by_department' => [],
            'by_status' => [],
            'hire_terminate' => ['hired' => 0, 'terminated' => 0],
            'attendance' => ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0],
            'leaves' => ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'days_approved' => 0.0],
            'payroll' => ['periods' => 0, 'net_total' => null],
            'contracts_expiring' => [],
            'recruitment' => [],
            'attention' => [],
        ];
    }
}
