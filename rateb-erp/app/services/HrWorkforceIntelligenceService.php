<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;

/**
 * Phase S — Workforce Intelligence & Planning (read aggregates + plan targets).
 *
 * Decision-support only. Does NOT mutate Employee / Payroll / Leave / Accounting /
 * Approval / ESS / GOSI-WPS connectors. No per-employee 360 loops.
 */
final class HrWorkforceIntelligenceService
{
    public const LIST_LIMIT = 50;
    public const FUNNEL_STATUSES = [
        'draft', 'registered', 'documents_pending', 'interview', 'medical',
        'visa', 'contract', 'ready', 'deployed', 'archived',
    ];

    public function schemaReady(): bool
    {
        try {
            return Database::tableExists('rateb_hr_workforce_plan_targets');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Executive HR dashboard payload.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function executiveDashboard(int $companyId, array $filters = [], bool $canViewSalary = false): array
    {
        if ($companyId < 1) {
            return $this->emptyExecutive();
        }
        $filters = $this->normalizeFilters($filters);
        $planning = $this->workforcePlanning($companyId, $filters, $canViewSalary);
        $attrition = $this->attritionAnalytics($companyId, $filters);
        $cost = $this->costAnalytics($companyId, $filters, $canViewSalary);
        $risk = $this->employeeRiskDashboard($companyId, $filters);
        $succession = $this->successionIntelligence($companyId);
        $hiring = $this->hiringAnalytics($companyId, $filters);
        $saudi = (new HrSaudiComplianceService())->readinessSummary($companyId);

        return [
            'filters' => $filters,
            'schema_ready' => $this->schemaReady(),
            'headcount' => (int) ($planning['current_headcount'] ?? 0),
            'workforce_gap' => (int) ($planning['gap'] ?? 0),
            'target_headcount' => (int) ($planning['target_headcount'] ?? 0),
            'turnover_pct' => (float) ($attrition['turnover_pct'] ?? 0),
            'hires' => (int) ($attrition['hires'] ?? 0),
            'terminations' => (int) ($attrition['terminations'] ?? 0),
            'payroll_cost' => $canViewSalary ? ($cost['payroll_net'] ?? null) : null,
            'employer_cost' => $canViewSalary ? ($cost['employer_cost'] ?? null) : null,
            'contract_risk' => (int) ($risk['contracts_expiring'] ?? 0),
            'attendance_risk' => (int) ($risk['frequent_absent'] ?? 0) + (int) ($risk['frequent_late'] ?? 0),
            'hiring_candidates' => (int) ($hiring['candidates_total'] ?? 0),
            'hiring_hired' => (int) ($hiring['hired'] ?? 0),
            'saudi_readiness_pct' => (int) ($saudi['readiness_pct'] ?? 0),
            'saudi_exceptions' => (int) ($saudi['gosi_exceptions'] ?? 0) + (int) ($saudi['wps_exceptions'] ?? 0),
            'planning' => $planning,
            'attrition' => $attrition,
            'cost' => $cost,
            'risk' => $risk,
            'succession' => $succession,
            'hiring' => $hiring,
            'saudi' => [
                'readiness_pct' => (int) ($saudi['readiness_pct'] ?? 0),
                'missing_data' => (int) ($saudi['missing_data'] ?? 0),
                'gosi_exceptions' => (int) ($saudi['gosi_exceptions'] ?? 0),
                'wps_exceptions' => (int) ($saudi['wps_exceptions'] ?? 0),
                'external_send_enabled' => false,
            ],
        ];
    }

    /**
     * Compact Command Center widgets (bounded).
     *
     * @return array<string,mixed>
     */
    public function commandWidgets(int $companyId, bool $canViewSalary = false): array
    {
        if ($companyId < 1) {
            return [
                'headcount' => 0,
                'turnover_pct' => 0,
                'workforce_gap' => 0,
                'contract_risk' => 0,
                'attendance_risk' => 0,
                'hiring_hired' => 0,
                'saudi_readiness_pct' => 0,
                'payroll_cost' => null,
            ];
        }
        $filters = $this->normalizeFilters([]);
        $planning = $this->workforcePlanning($companyId, $filters, $canViewSalary);
        $attrition = $this->attritionAnalytics($companyId, $filters);
        $risk = $this->employeeRiskDashboard($companyId, $filters);
        $hiring = $this->hiringAnalytics($companyId, $filters);
        $saudiPct = 0;
        try {
            $saudiPct = (int) ((new HrSaudiComplianceService())->readinessSummary($companyId)['readiness_pct'] ?? 0);
        } catch (\Throwable $e) {
            $saudiPct = 0;
        }
        $payroll = null;
        if ($canViewSalary) {
            $cost = $this->costAnalytics($companyId, $filters, true);
            $payroll = $cost['payroll_net'] ?? null;
        }

        return [
            'headcount' => (int) ($planning['current_headcount'] ?? 0),
            'turnover_pct' => (float) ($attrition['turnover_pct'] ?? 0),
            'workforce_gap' => (int) ($planning['gap'] ?? 0),
            'contract_risk' => (int) ($risk['contracts_expiring'] ?? 0),
            'attendance_risk' => (int) ($risk['frequent_absent'] ?? 0) + (int) ($risk['frequent_late'] ?? 0),
            'hiring_hired' => (int) ($hiring['hired'] ?? 0),
            'saudi_readiness_pct' => $saudiPct,
            'payroll_cost' => $payroll,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function workforcePlanning(int $companyId, array $filters = [], bool $canViewSalary = false): array
    {
        $filters = $this->normalizeFilters($filters);
        $current = $this->countActive($companyId, $filters);
        $year = (int) date('Y', strtotime($filters['date_to']));
        $month = (int) date('n', strtotime($filters['date_to']));
        $target = $this->resolveTargetHeadcount($companyId, $year, $month, $filters);
        $gap = $target - $current;
        $vacancies = max(0, $gap);
        $avgSalary = $canViewSalary ? $this->avgActiveSalary($companyId, $filters) : null;
        $payrollCost = $canViewSalary && $avgSalary !== null
            ? round($avgSalary * $current, 2)
            : null;
        $forecastCost = $canViewSalary && $avgSalary !== null && $target > 0
            ? round($avgSalary * $target, 2)
            : null;

        return [
            'current_headcount' => $current,
            'target_headcount' => $target,
            'gap' => $gap,
            'vacancies' => $vacancies,
            'demand_forecast' => max(0, $gap),
            'payroll_cost_estimate' => $payrollCost,
            'forecast_payroll_cost' => $forecastCost,
            'period_year' => $year,
            'period_month' => $month,
            'by_department' => $this->headcountByDepartment($companyId, $filters),
        ];
    }

    /**
     * Attrition using hire_date + termination decision events (not status-only).
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function attritionAnalytics(int $companyId, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $hires = $this->countHires($companyId, $filters);
        $terms = $this->countTerminations($companyId, $filters);
        $active = $this->countActive($companyId, $filters);
        $avgBase = max(1, (int) round(($active + max(0, $active - $hires + $terms)) / 2));
        $turnover = round(($terms / $avgBase) * 100, 2);

        return [
            'hires' => $hires,
            'terminations' => $terms,
            'turnover_pct' => $turnover,
            'avg_headcount_base' => $avgBase,
            'by_department' => $this->attritionByDepartment($companyId, $filters),
            'source' => 'hire_date + hr_decisions.termination (executed/effective_date)',
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function costAnalytics(int $companyId, array $filters = [], bool $canViewSalary = false): array
    {
        if (!$canViewSalary) {
            return [
                'salary_gated' => true,
                'payroll_net' => null,
                'basic_total' => null,
                'allowances_total' => null,
                'deductions_total' => null,
                'gosi_modeled_employer' => null,
                'gosi_modeled_employee' => null,
                'employer_cost' => null,
                'by_department' => [],
                'by_position' => [],
            ];
        }
        $filters = $this->normalizeFilters($filters);
        $pay = $this->payrollCostTotals($companyId, $filters);
        $gosi = $this->gosiModeledCost($companyId, $filters);
        $employer = round((float) ($pay['basic_total'] ?? 0) + (float) ($pay['allowances_total'] ?? 0) + (float) ($gosi['employer'] ?? 0), 2);

        return [
            'salary_gated' => false,
            'payroll_net' => (float) ($pay['net_total'] ?? 0),
            'basic_total' => (float) ($pay['basic_total'] ?? 0),
            'allowances_total' => (float) ($pay['allowances_total'] ?? 0),
            'deductions_total' => (float) ($pay['deductions_total'] ?? 0),
            'gosi_modeled_employer' => (float) ($gosi['employer'] ?? 0),
            'gosi_modeled_employee' => (float) ($gosi['employee'] ?? 0),
            'employer_cost' => $employer,
            'by_department' => $this->payrollByDimension($companyId, $filters, 'department'),
            'by_position' => $this->payrollByDimension($companyId, $filters, 'position'),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function employeeRiskDashboard(int $companyId, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $contracts = $this->countContractsExpiring($companyId, 30);
        $missing = 0;
        $gosiEx = 0;
        $wpsEx = 0;
        try {
            $saudi = (new HrSaudiComplianceService())->readinessSummary($companyId);
            $missing = (int) ($saudi['missing_data'] ?? 0);
            $gosiEx = (int) ($saudi['gosi_exceptions'] ?? 0);
            $wpsEx = (int) ($saudi['wps_exceptions'] ?? 0);
        } catch (\Throwable $e) {
            // foundation optional
        }

        return [
            'contracts_expiring' => $contracts,
            'missing_saudi_data' => $missing,
            'frequent_absent' => $this->countFrequentAttendance($companyId, $filters, 'absent', 3),
            'frequent_late' => $this->countFrequentAttendance($companyId, $filters, 'late', 3),
            'overdue_requests' => $this->countOverdueRequests($companyId),
            'gosi_exceptions' => $gosiEx,
            'wps_exceptions' => $wpsEx,
            'top_absent' => $this->topAttendanceRisk($companyId, $filters, 'absent'),
            'top_late' => $this->topAttendanceRisk($companyId, $filters, 'late'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function successionIntelligence(int $companyId): array
    {
        $svc = new HrSuccessionService();
        if (!$svc->schemaReady() || $companyId < 1) {
            return [
                'critical_positions' => 0,
                'with_successors' => 0,
                'ready_successors' => 0,
                'vacancy_risk' => 0,
                'positions' => [],
            ];
        }
        $positions = $svc->listPositions($companyId, 100);
        $critical = 0;
        $withSucc = 0;
        $ready = 0;
        $vacancyRisk = 0;
        $rows = [];
        foreach ($positions as $p) {
            if (!(int) ($p['is_critical'] ?? 0)) {
                continue;
            }
            $critical++;
            $candCount = (int) ($p['candidate_count'] ?? 0);
            if ($candCount > 0) {
                $withSucc++;
            }
            $cands = $svc->listCandidates($companyId, (int) $p['id']);
            $readyHere = 0;
            $gaps = [];
            foreach ($cands as $c) {
                if (($c['readiness'] ?? '') === 'ready') {
                    $readyHere++;
                    $ready++;
                }
                $gap = trim((string) ($c['skill_gap_notes'] ?? ''));
                if ($gap !== '') {
                    $gaps[] = $gap;
                }
            }
            $vacant = empty($p['current_employee_id']) || $candCount < 1 || $readyHere < 1;
            if ($vacant) {
                $vacancyRisk++;
            }
            $rows[] = [
                'id' => (int) ($p['id'] ?? 0),
                'title' => (string) ($p['title'] ?? ''),
                'department_name' => (string) ($p['department_name'] ?? ''),
                'current_employee_name' => (string) ($p['current_employee_name'] ?? ''),
                'successors' => $candCount,
                'ready' => $readyHere,
                'skill_gaps' => implode('; ', array_slice($gaps, 0, 3)),
                'vacancy_risk' => $vacant,
            ];
            if (count($rows) >= self::LIST_LIMIT) {
                break;
            }
        }

        return [
            'critical_positions' => $critical,
            'with_successors' => $withSucc,
            'ready_successors' => $ready,
            'vacancy_risk' => $vacancyRisk,
            'positions' => $rows,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function hiringAnalytics(int $companyId, array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $funnel = $this->recruitmentFunnel($companyId);
        $candidates = 0;
        foreach ($funnel as $row) {
            $candidates += (int) ($row['count'] ?? 0);
        }
        $hired = $this->countHiredInRange($companyId, $filters);
        $converted = $this->countRecruitmentConversions($companyId);
        $avgDays = $this->avgTimeToHireDays($companyId);

        return [
            'candidates_total' => $candidates,
            'hired' => $hired,
            'conversions' => $converted,
            'avg_time_to_hire_days' => $avgDays,
            'funnel' => $funnel,
        ];
    }

    /**
     * Upsert planning target (audited write).
     *
     * @param array<string,mixed> $input
     * @return array{id:int}
     */
    public function upsertPlanTarget(int $companyId, array $input, int $actorUserId = 0): array
    {
        if (!$this->schemaReady() || $companyId < 1) {
            throw new \RuntimeException(__('db_schema_outdated'));
        }
        $year = max(2000, min(2100, (int) ($input['period_year'] ?? date('Y'))));
        $month = max(0, min(12, (int) ($input['period_month'] ?? 0)));
        $dept = max(0, (int) ($input['department_id'] ?? 0));
        $job = max(0, (int) ($input['job_title_id'] ?? 0));
        $target = max(0, (int) ($input['target_headcount'] ?? 0));
        $planned = isset($input['planned_hires']) ? max(0, (int) $input['planned_hires']) : null;
        $notes = trim((string) ($input['notes'] ?? ''));
        if (mb_strlen($notes) > 500) {
            $notes = mb_substr($notes, 0, 500);
        }
        $scopeKey = 'd' . $dept . '|j' . $job;
        if ($actorUserId < 1) {
            $actorUserId = (int) (SessionManager::get('rateb_user_id') ?? 0);
        }
        $now = date('Y-m-d H:i:s');
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_hr_workforce_plan_targets
             (company_id, period_year, period_month, department_id, job_title_id, scope_key,
              target_headcount, planned_hires, notes, created_by, updated_by, created_at, updated_at)
             VALUES (:cid,:yy,:mm,:did,:jid,:sk,:th,:ph,:nt,:cb,:ub,:ca,:ua)
             ON DUPLICATE KEY UPDATE
               target_headcount = VALUES(target_headcount),
               planned_hires = VALUES(planned_hires),
               notes = VALUES(notes),
               department_id = VALUES(department_id),
               job_title_id = VALUES(job_title_id),
               updated_by = VALUES(updated_by),
               updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'cid' => $companyId,
            'yy' => $year,
            'mm' => $month,
            'did' => $dept > 0 ? $dept : null,
            'jid' => $job > 0 ? $job : null,
            'sk' => $scopeKey,
            'th' => $target,
            'ph' => $planned,
            'nt' => $notes !== '' ? $notes : null,
            'cb' => $actorUserId > 0 ? $actorUserId : null,
            'ub' => $actorUserId > 0 ? $actorUserId : null,
            'ca' => $now,
            'ua' => $now,
        ]);
        $idStmt = $db->prepare(
            'SELECT id FROM rateb_hr_workforce_plan_targets
             WHERE company_id = :cid AND period_year = :yy AND period_month = :mm AND scope_key = :sk LIMIT 1'
        );
        $idStmt->execute(['cid' => $companyId, 'yy' => $year, 'mm' => $month, 'sk' => $scopeKey]);
        $id = (int) ($idStmt->fetchColumn() ?: 0);
        (new AuditService())->log('hr_workforce_plan_upsert', 'hr_workforce_plan', $id, [
            'company_id' => $companyId,
            'period_year' => $year,
            'period_month' => $month,
            'target_headcount' => $target,
            'scope_key' => $scopeKey,
        ]);

        return ['id' => $id];
    }

    /**
     * Flat rows for ExportController (sensitive salary columns omitted when gated).
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public function executiveExportRows(int $companyId, array $filters = [], bool $canViewSalary = false): array
    {
        $dash = $this->executiveDashboard($companyId, $filters, $canViewSalary);
        $rows = [
            ['metric' => 'headcount', 'value' => $dash['headcount'] ?? 0],
            ['metric' => 'target_headcount', 'value' => $dash['target_headcount'] ?? 0],
            ['metric' => 'workforce_gap', 'value' => $dash['workforce_gap'] ?? 0],
            ['metric' => 'turnover_pct', 'value' => $dash['turnover_pct'] ?? 0],
            ['metric' => 'hires', 'value' => $dash['hires'] ?? 0],
            ['metric' => 'terminations', 'value' => $dash['terminations'] ?? 0],
            ['metric' => 'contract_risk', 'value' => $dash['contract_risk'] ?? 0],
            ['metric' => 'attendance_risk', 'value' => $dash['attendance_risk'] ?? 0],
            ['metric' => 'hiring_candidates', 'value' => $dash['hiring_candidates'] ?? 0],
            ['metric' => 'hiring_hired', 'value' => $dash['hiring_hired'] ?? 0],
            ['metric' => 'saudi_readiness_pct', 'value' => $dash['saudi_readiness_pct'] ?? 0],
            ['metric' => 'saudi_exceptions', 'value' => $dash['saudi_exceptions'] ?? 0],
        ];
        if ($canViewSalary) {
            $rows[] = ['metric' => 'payroll_cost', 'value' => $dash['payroll_cost'] ?? 0];
            $rows[] = ['metric' => 'employer_cost', 'value' => $dash['employer_cost'] ?? 0];
        }
        (new AuditService())->log('hr_workforce_exec_export', 'hr_workforce', $companyId, [
            'company_id' => $companyId,
            'salary_included' => $canViewSalary ? 1 : 0,
        ]);

        return $rows;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function normalizeFilters(array $input): array
    {
        $dept = max(0, (int) ($input['department_id'] ?? 0));
        $job = max(0, (int) ($input['job_title_id'] ?? 0));
        $empType = trim((string) ($input['employment_type'] ?? ''));
        $cls = strtolower(trim((string) ($input['saudi_classification'] ?? '')));
        if (!in_array($cls, ['saudi', 'non_saudi'], true)) {
            $cls = '';
        }
        $from = trim((string) ($input['date_from'] ?? ''));
        $to = trim((string) ($input['date_to'] ?? ''));
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
            'department_id' => $dept,
            'job_title_id' => $job,
            'employment_type' => mb_substr($empType, 0, 32),
            'saudi_classification' => $cls,
            'date_from' => $from,
            'date_to' => $to,
        ];
    }

    /** @return array<string,mixed> */
    private function emptyExecutive(): array
    {
        return [
            'filters' => $this->normalizeFilters([]),
            'schema_ready' => false,
            'headcount' => 0,
            'workforce_gap' => 0,
            'target_headcount' => 0,
            'turnover_pct' => 0,
            'hires' => 0,
            'terminations' => 0,
            'payroll_cost' => null,
            'employer_cost' => null,
            'contract_risk' => 0,
            'attendance_risk' => 0,
            'hiring_candidates' => 0,
            'hiring_hired' => 0,
            'saudi_readiness_pct' => 0,
            'saudi_exceptions' => 0,
            'planning' => [],
            'attrition' => [],
            'cost' => ['salary_gated' => true],
            'risk' => [],
            'succession' => [],
            'hiring' => [],
            'saudi' => ['external_send_enabled' => false],
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $params
     */
    private function employeeFilterSql(array $filters, array &$params, string $alias = 'e'): string
    {
        $sql = '';
        $p = $alias !== '' ? $alias . '.' : '';
        if ((int) ($filters['department_id'] ?? 0) > 0) {
            $sql .= " AND {$p}department_id = :dept";
            $params['dept'] = (int) $filters['department_id'];
        }
        if ((int) ($filters['job_title_id'] ?? 0) > 0) {
            $sql .= " AND {$p}job_title_id = :job";
            $params['job'] = (int) $filters['job_title_id'];
        }
        $needSaudi = ($filters['employment_type'] ?? '') !== '' || ($filters['saudi_classification'] ?? '') !== '';
        if ($needSaudi && Database::tableExists('rateb_hr_saudi_employment_fields')) {
            // caller must JOIN saudi as `s`
            if (($filters['employment_type'] ?? '') !== '') {
                $sql .= ' AND s.employment_type = :etype';
                $params['etype'] = (string) $filters['employment_type'];
            }
            if (($filters['saudi_classification'] ?? '') !== '') {
                $sql .= ' AND s.saudi_classification = :scls';
                $params['scls'] = (string) $filters['saudi_classification'];
            }
        }

        return $sql;
    }

    private function needsSaudiJoin(array $filters): bool
    {
        return ($filters['employment_type'] ?? '') !== '' || ($filters['saudi_classification'] ?? '') !== '';
    }

    private function saudiJoin(string $empAlias = 'e'): string
    {
        return " LEFT JOIN rateb_hr_saudi_employment_fields s
                   ON s.company_id = {$empAlias}.company_id AND s.employee_id = {$empAlias}.id ";
    }

    /** @param array<string,mixed> $filters */
    private function countActive(int $companyId, array $filters): int
    {
        $sql = 'SELECT COUNT(*) FROM rateb_employees e';
        if ($this->needsSaudiJoin($filters)) {
            $sql .= $this->saudiJoin('e');
        }
        $sql .= " WHERE e.company_id = :cid AND e.status = 'active'";
        $params = ['cid' => $companyId];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $filters */
    private function countHires(int $companyId, array $filters): int
    {
        $sql = 'SELECT COUNT(*) FROM rateb_employees e';
        if ($this->needsSaudiJoin($filters)) {
            $sql .= $this->saudiJoin('e');
        }
        $sql .= ' WHERE e.company_id = :cid AND e.hire_date BETWEEN :df AND :dt';
        $params = ['cid' => $companyId, 'df' => $filters['date_from'], 'dt' => $filters['date_to']];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $filters */
    private function countTerminations(int $companyId, array $filters): int
    {
        if (!Database::tableExists('rateb_hr_decisions')) {
            return 0;
        }
        $sql = "SELECT COUNT(DISTINCT d.employee_id)
                FROM rateb_hr_decisions d
                JOIN rateb_employees e ON e.id = d.employee_id AND e.company_id = d.company_id";
        if ($this->needsSaudiJoin($filters)) {
            $sql .= $this->saudiJoin('e');
        }
        $sql .= " WHERE d.company_id = :cid
                    AND d.decision_type = 'termination'
                    AND d.status IN ('executed','approved')
                    AND COALESCE(d.effective_date, DATE(d.executed_at), DATE(d.updated_at), DATE(d.created_at))
                        BETWEEN :df AND :dt";
        $params = ['cid' => $companyId, 'df' => $filters['date_from'], 'dt' => $filters['date_to']];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function attritionByDepartment(int $companyId, array $filters): array
    {
        $sql = "SELECT COALESCE(dep.name, '-') AS department_name,
                       SUM(CASE WHEN e.hire_date BETWEEN :df1 AND :dt1 THEN 1 ELSE 0 END) AS hires,
                       0 AS terminations
                FROM rateb_employees e
                LEFT JOIN rateb_hr_departments dep ON dep.id = e.department_id AND dep.company_id = e.company_id";
        if ($this->needsSaudiJoin($filters)) {
            $sql .= $this->saudiJoin('e');
        }
        $sql .= ' WHERE e.company_id = :cid';
        $params = [
            'cid' => $companyId,
            'df1' => $filters['date_from'],
            'dt1' => $filters['date_to'],
        ];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        $sql .= ' GROUP BY dep.id, dep.name ORDER BY hires DESC LIMIT 20';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $hireRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $termMap = [];
        if (Database::tableExists('rateb_hr_decisions')) {
            try {
                $tsql = "SELECT COALESCE(dep.name, '-') AS department_name, COUNT(DISTINCT d.employee_id) AS terminations
                         FROM rateb_hr_decisions d
                         JOIN rateb_employees e ON e.id = d.employee_id AND e.company_id = d.company_id
                         LEFT JOIN rateb_hr_departments dep ON dep.id = e.department_id AND dep.company_id = e.company_id
                         WHERE d.company_id = :cid AND d.decision_type = 'termination'
                           AND d.status IN ('executed','approved')
                           AND COALESCE(d.effective_date, DATE(d.executed_at), DATE(d.created_at)) BETWEEN :df AND :dt
                         GROUP BY dep.id, dep.name LIMIT 20";
                $tstmt = Database::connection()->prepare($tsql);
                $tstmt->execute([
                    'cid' => $companyId,
                    'df' => $filters['date_from'],
                    'dt' => $filters['date_to'],
                ]);
                foreach ($tstmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $tr) {
                    $termMap[(string) ($tr['department_name'] ?? '-')] = (int) ($tr['terminations'] ?? 0);
                }
            } catch (\Throwable $e) {
                $termMap = [];
            }
        }
        $out = [];
        foreach ($hireRows as $row) {
            $name = (string) ($row['department_name'] ?? '-');
            $out[] = [
                'department_name' => $name,
                'hires' => (int) ($row['hires'] ?? 0),
                'terminations' => (int) ($termMap[$name] ?? 0),
            ];
            unset($termMap[$name]);
        }
        foreach ($termMap as $name => $t) {
            $out[] = ['department_name' => $name, 'hires' => 0, 'terminations' => $t];
        }

        return array_slice($out, 0, 20);
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function headcountByDepartment(int $companyId, array $filters): array
    {
        $sql = "SELECT COALESCE(dep.name, '-') AS department_name, COUNT(*) AS headcount
                FROM rateb_employees e
                LEFT JOIN rateb_hr_departments dep ON dep.id = e.department_id AND dep.company_id = e.company_id";
        if ($this->needsSaudiJoin($filters)) {
            $sql .= $this->saudiJoin('e');
        }
        $sql .= " WHERE e.company_id = :cid AND e.status = 'active'";
        $params = ['cid' => $companyId];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        $sql .= ' GROUP BY dep.id, dep.name ORDER BY headcount DESC LIMIT 20';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $filters */
    private function resolveTargetHeadcount(int $companyId, int $year, int $month, array $filters): int
    {
        if (!$this->schemaReady()) {
            return $this->countActive($companyId, $filters);
        }
        $dept = (int) ($filters['department_id'] ?? 0);
        $job = (int) ($filters['job_title_id'] ?? 0);
        $scopeKey = 'd' . $dept . '|j' . $job;
        $stmt = Database::connection()->prepare(
            'SELECT target_headcount FROM rateb_hr_workforce_plan_targets
             WHERE company_id = :cid AND period_year = :yy
               AND period_month IN (:mm, 0) AND scope_key IN (:sk, \'all\', \'d0|j0\')
             ORDER BY
               CASE WHEN scope_key = :sk2 THEN 0 WHEN scope_key IN (\'all\',\'d0|j0\') THEN 1 ELSE 2 END,
               CASE WHEN period_month = :mm2 THEN 0 ELSE 1 END
             LIMIT 1'
        );
        $stmt->execute([
            'cid' => $companyId,
            'yy' => $year,
            'mm' => $month,
            'mm2' => $month,
            'sk' => $scopeKey,
            'sk2' => $scopeKey,
        ]);
        $val = $stmt->fetchColumn();
        if ($val === false) {
            return $this->countActive($companyId, $filters);
        }

        return (int) $val;
    }

    /** @param array<string,mixed> $filters */
    private function avgActiveSalary(int $companyId, array $filters): ?float
    {
        $sql = 'SELECT ROUND(AVG(e.salary_base), 2) FROM rateb_employees e';
        if ($this->needsSaudiJoin($filters)) {
            $sql .= $this->saudiJoin('e');
        }
        $sql .= " WHERE e.company_id = :cid AND e.status = 'active' AND e.salary_base IS NOT NULL AND e.salary_base > 0";
        $params = ['cid' => $companyId];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();

        return $v !== false && $v !== null ? (float) $v : null;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,float>
     */
    private function payrollCostTotals(int $companyId, array $filters): array
    {
        $sql = 'SELECT
                    COALESCE(SUM(pl.basic_salary),0) AS basic_total,
                    COALESCE(SUM(pl.allowances),0) AS allowances_total,
                    COALESCE(SUM(pl.deductions),0) AS deductions_total,
                    COALESCE(SUM(pl.net_salary),0) AS net_total
                FROM rateb_payroll_lines pl
                JOIN rateb_payroll_periods pp ON pp.id = pl.period_id AND pp.company_id = pl.company_id
                JOIN rateb_employees e ON e.id = pl.employee_id AND e.company_id = pl.company_id';
        if ($this->needsSaudiJoin($filters)) {
            $sql .= $this->saudiJoin('e');
        }
        $sql .= " WHERE pl.company_id = :cid
                    AND STR_TO_DATE(CONCAT(pp.period_year,'-',LPAD(pp.period_month,2,'0'),'-01'), '%Y-%m-%d')
                        BETWEEN :df AND :dt";
        $params = [
            'cid' => $companyId,
            'df' => $filters['date_from'],
            'dt' => $filters['date_to'],
        ];
        $sql .= $this->employeeFilterSql($filters, $params, 'e');
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'basic_total' => (float) ($row['basic_total'] ?? 0),
                'allowances_total' => (float) ($row['allowances_total'] ?? 0),
                'deductions_total' => (float) ($row['deductions_total'] ?? 0),
                'net_total' => (float) ($row['net_total'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['basic_total' => 0.0, 'allowances_total' => 0.0, 'deductions_total' => 0.0, 'net_total' => 0.0];
        }
    }

    /**
     * Modeled GOSI employer/employee cost from active salary + Saudi classification (no external send).
     *
     * @param array<string,mixed> $filters
     * @return array{employer:float,employee:float}
     */
    private function gosiModeledCost(int $companyId, array $filters): array
    {
        $saudiPctEmp = HrSaudiComplianceService::GOSI_SAUDI_EMPLOYEE_PCT;
        $saudiPctEr = HrSaudiComplianceService::GOSI_SAUDI_EMPLOYER_PCT;
        $nonEr = HrSaudiComplianceService::GOSI_NON_SAUDI_EMPLOYER_PCT;
        $cap = HrSaudiComplianceService::GOSI_MAX_MONTHLY_BASE;

        $sql = "SELECT e.salary_base,
                       COALESCE(s.housing_allowance,0) AS housing_allowance,
                       COALESCE(s.transport_allowance,0) AS transport_allowance,
                       COALESCE(s.other_gosi_allowances,0) AS other_gosi_allowances,
                       COALESCE(s.saudi_classification, '') AS saudi_classification,
                       COALESCE(s.nationality_code, '') AS nationality_code,
                       COALESCE(s.employment_type, '') AS employment_type,
                       e.national_id
                FROM rateb_employees e
                LEFT JOIN rateb_hr_saudi_employment_fields s
                  ON s.company_id = e.company_id AND s.employee_id = e.id
                WHERE e.company_id = :cid AND e.status = 'active'";
        $params = ['cid' => $companyId];
        if ((int) ($filters['department_id'] ?? 0) > 0) {
            $sql .= ' AND e.department_id = :dept';
            $params['dept'] = (int) $filters['department_id'];
        }
        if ((int) ($filters['job_title_id'] ?? 0) > 0) {
            $sql .= ' AND e.job_title_id = :job';
            $params['job'] = (int) $filters['job_title_id'];
        }
        if (($filters['employment_type'] ?? '') !== '') {
            $sql .= ' AND s.employment_type = :etype';
            $params['etype'] = (string) $filters['employment_type'];
        }
        $sql .= ' LIMIT 2000';
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            $empTotal = 0.0;
            $erTotal = 0.0;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $cls = strtolower(trim((string) ($row['saudi_classification'] ?? '')));
                if ($cls !== 'saudi' && $cls !== 'non_saudi') {
                    $nat = strtoupper(trim((string) ($row['nationality_code'] ?? '')));
                    if ($nat === 'SA' || $nat === 'SAU' || preg_match('/^1\d{9}$/', (string) ($row['national_id'] ?? ''))) {
                        $cls = 'saudi';
                    } else {
                        $cls = 'non_saudi';
                    }
                }
                if (($filters['saudi_classification'] ?? '') !== '' && $cls !== $filters['saudi_classification']) {
                    continue;
                }
                $base = (float) ($row['salary_base'] ?? 0)
                    + (float) ($row['housing_allowance'] ?? 0)
                    + (float) ($row['transport_allowance'] ?? 0)
                    + (float) ($row['other_gosi_allowances'] ?? 0);
                $base = min(max(0.0, $base), $cap);
                if ($cls === 'saudi') {
                    $empTotal += round($base * $saudiPctEmp / 100, 2);
                    $erTotal += round($base * $saudiPctEr / 100, 2);
                } else {
                    $erTotal += round($base * $nonEr / 100, 2);
                }
            }

            return ['employer' => round($erTotal, 2), 'employee' => round($empTotal, 2)];
        } catch (\Throwable $e) {
            return ['employer' => 0.0, 'employee' => 0.0];
        }
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function payrollByDimension(int $companyId, array $filters, string $dim): array
    {
        $label = $dim === 'position'
            ? "COALESCE(j.name, '-')"
            : "COALESCE(dep.name, '-')";
        $join = $dim === 'position'
            ? 'LEFT JOIN rateb_hr_job_titles j ON j.id = e.job_title_id AND j.company_id = e.company_id'
            : 'LEFT JOIN rateb_hr_departments dep ON dep.id = e.department_id AND dep.company_id = e.company_id';
        $group = $dim === 'position' ? 'j.id, j.name' : 'dep.id, dep.name';
        $sql = "SELECT {$label} AS dimension_name,
                       COALESCE(SUM(pl.net_salary),0) AS net_total,
                       COALESCE(SUM(pl.basic_salary + pl.allowances),0) AS gross_total
                FROM rateb_payroll_lines pl
                JOIN rateb_payroll_periods pp ON pp.id = pl.period_id AND pp.company_id = pl.company_id
                JOIN rateb_employees e ON e.id = pl.employee_id AND e.company_id = pl.company_id
                {$join}
                WHERE pl.company_id = :cid
                  AND STR_TO_DATE(CONCAT(pp.period_year,'-',LPAD(pp.period_month,2,'0'),'-01'), '%Y-%m-%d')
                      BETWEEN :df AND :dt
                GROUP BY {$group}
                ORDER BY net_total DESC
                LIMIT 20";
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'cid' => $companyId,
                'df' => $filters['date_from'],
                'dt' => $filters['date_to'],
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function countContractsExpiring(int $companyId, int $days): int
    {
        if (!Database::tableExists('rateb_hr_employment_contracts')) {
            return 0;
        }
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM rateb_hr_employment_contracts
             WHERE company_id = :cid AND status = 'active'
               AND end_date IS NOT NULL
               AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)"
        );
        $stmt->execute(['cid' => $companyId, 'days' => max(1, min(365, $days))]);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $filters */
    private function countFrequentAttendance(int $companyId, array $filters, string $status, int $minDays): int
    {
        $sql = "SELECT COUNT(*) FROM (
                    SELECT a.employee_id
                    FROM rateb_attendance_records a
                    JOIN rateb_employees e ON e.id = a.employee_id AND e.company_id = a.company_id
                    WHERE a.company_id = :cid AND a.status = :st
                      AND a.attendance_date BETWEEN :df AND :dt
                    GROUP BY a.employee_id
                    HAVING COUNT(*) >= :minc
                ) t";
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'cid' => $companyId,
                'st' => $status,
                'df' => $filters['date_from'],
                'dt' => $filters['date_to'],
                'minc' => max(1, $minDays),
            ]);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    private function topAttendanceRisk(int $companyId, array $filters, string $status): array
    {
        $sql = "SELECT e.employee_code, e.name, COUNT(*) AS days_count
                FROM rateb_attendance_records a
                JOIN rateb_employees e ON e.id = a.employee_id AND e.company_id = a.company_id
                WHERE a.company_id = :cid AND a.status = :st
                  AND a.attendance_date BETWEEN :df AND :dt
                GROUP BY e.id, e.employee_code, e.name
                HAVING COUNT(*) >= 3
                ORDER BY days_count DESC
                LIMIT " . self::LIST_LIMIT;
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([
                'cid' => $companyId,
                'st' => $status,
                'df' => $filters['date_from'],
                'dt' => $filters['date_to'],
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function countOverdueRequests(int $companyId): int
    {
        if (!Database::tableExists('rateb_hr_employee_requests')) {
            return 0;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rateb_hr_employee_requests
                 WHERE company_id = :cid AND status = 'pending'
                   AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)"
            );
            $stmt->execute(['cid' => $companyId]);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** @return list<array{status:string,count:int}> */
    private function recruitmentFunnel(int $companyId): array
    {
        if (!Database::tableExists('rateb_recruitment_candidates')) {
            return [];
        }
        try {
            $stmt = Database::connection()->prepare(
                'SELECT workflow_status AS status, COUNT(*) AS count
                 FROM rateb_recruitment_candidates
                 WHERE company_id = :cid
                 GROUP BY workflow_status
                 ORDER BY count DESC
                 LIMIT 20'
            );
            $stmt->execute(['cid' => $companyId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'status' => (string) ($r['status'] ?? ''),
                    'count' => (int) ($r['count'] ?? 0),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @param array<string,mixed> $filters */
    private function countHiredInRange(int $companyId, array $filters): int
    {
        // Prefer employees linked from recruitment with hire_date in range.
        $sql = 'SELECT COUNT(*) FROM rateb_employees e
                WHERE e.company_id = :cid
                  AND e.hire_date BETWEEN :df AND :dt';
        $params = ['cid' => $companyId, 'df' => $filters['date_from'], 'dt' => $filters['date_to']];
        if (Database::liveTableHasColumn('rateb_employees', 'recruitment_candidate_id')) {
            $sql .= ' AND e.recruitment_candidate_id IS NOT NULL AND e.recruitment_candidate_id > 0';
        }
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countRecruitmentConversions(int $companyId): int
    {
        if (!Database::tableExists('rateb_recruitment_candidates')) {
            return 0;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rateb_recruitment_candidates
                 WHERE company_id = :cid AND workflow_status = 'deployed'"
            );
            $stmt->execute(['cid' => $companyId]);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function avgTimeToHireDays(int $companyId): ?float
    {
        if (!Database::tableExists('rateb_recruitment_status_history')
            || !Database::tableExists('rateb_recruitment_candidates')) {
            return null;
        }
        try {
            $sql = "SELECT AVG(DATEDIFF(h_end.created_at, h_start.created_at)) AS avg_days
                    FROM rateb_recruitment_candidates c
                    JOIN (
                        SELECT candidate_id, company_id, MIN(created_at) AS created_at
                        FROM rateb_recruitment_status_history
                        WHERE company_id = :cid1 AND to_status IN ('registered','draft')
                        GROUP BY candidate_id, company_id
                    ) h_start ON h_start.candidate_id = c.id AND h_start.company_id = c.company_id
                    JOIN (
                        SELECT candidate_id, company_id, MIN(created_at) AS created_at
                        FROM rateb_recruitment_status_history
                        WHERE company_id = :cid2 AND to_status = 'deployed'
                        GROUP BY candidate_id, company_id
                    ) h_end ON h_end.candidate_id = c.id AND h_end.company_id = c.company_id
                    WHERE c.company_id = :cid3 AND c.workflow_status = 'deployed'
                    LIMIT 1";
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(['cid1' => $companyId, 'cid2' => $companyId, 'cid3' => $companyId]);
            $v = $stmt->fetchColumn();
            if ($v === false || $v === null) {
                return null;
            }

            return round((float) $v, 1);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
