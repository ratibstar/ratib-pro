<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\PayrollAdvance;
use Rateb\App\Models\PayrollAttachmentMeta;
use Rateb\App\Models\PayrollBatch;
use Rateb\App\Models\PayrollBonus;
use Rateb\App\Models\PayrollComment;
use Rateb\App\Models\PayrollCycle;
use Rateb\App\Models\PayrollEmployeeSalary;
use Rateb\App\Models\PayrollItem;
use Rateb\App\Models\PayrollLoan;
use Rateb\App\Models\PayrollLoanInstallment;
use Rateb\App\Models\PayrollOvertime;
use Rateb\App\Models\PayrollPayslip;
use Rateb\App\Models\PayrollRunPeriod;
use Rateb\App\Models\PayrollSalaryComponent;
use Rateb\App\Models\PayrollSalaryStructure;
use Rateb\App\Models\PayrollSettlement;

/**
 * Phase 24A — Enterprise Payroll Platform domain services (ONLINE).
 * Controllers must not embed business rules — call these services only.
 * Operates on rateb_payroll_* — workflow_status changes via PayrollWorkflowService only.
 * Soft-links HRMS / attendance / leave / legacy payroll — no auto GL posting.
 */

final class PayrollEnterpriseService
{
    /** @return array<string, array<string, int>> */
    public function boardCounts(): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $counts = [];
        foreach (PayrollWorkflowService::statuses(PayrollWorkflowService::ENTITY_BATCH) as $st) {
            $row = (new PayrollBatch())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_payroll_batches'
                . ' WHERE company_id = :cid AND deleted_at IS NULL AND workflow_status = :st',
                ['cid' => $companyId, 'st' => $st]
            );
            $counts[$st] = (int) ($row['c'] ?? 0);
        }

        return ['batch' => $counts];
    }
}

final class PayrollStructureService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR code LIKE :q2)';
            $like = '%' . $search . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
        }
        $totalRow = (new PayrollSalaryStructure())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_payroll_salary_structures WHERE ' . $where,
            $params
        );
        $items = (new PayrollSalaryStructure())->query(
            'SELECT * FROM rateb_payroll_salary_structures WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return PayrollSupport::findStructure($id, PayrollSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = PayrollSupport::nextCode('rateb_payroll_salary_structures', 'PAY-STR', $companyId);
        }
        $id = (new PayrollSalaryStructure())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::intOrNull($input['branch_id'] ?? null) ?? PayrollSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => PayrollSupport::nullIfEmpty($input['name_ar'] ?? null),
            'description' => PayrollSupport::nullIfEmpty($input['description'] ?? null),
            'currency_code' => substr(trim((string) ($input['currency_code'] ?? 'SAR')), 0, 3) ?: 'SAR',
            'status' => 'active',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        (new PayrollTimelineService())->record('structure_created', 'Salary structure: ' . $name, 'structure', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = PayrollSupport::requireCompanyId();
        $row = PayrollSupport::assertStructure($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = PayrollSupport::actorFields(false);
        foreach (['name', 'name_ar', 'description', 'notes', 'currency_code'] as $f) {
            if (!array_key_exists($f, $input)) {
                continue;
            }
            if ($f === 'name') {
                $name = substr(trim((string) $input[$f]), 0, 190);
                if ($name === '') {
                    throw new \InvalidArgumentException('name_required');
                }
                $patch[$f] = $name;
            } elseif ($f === 'currency_code') {
                $patch[$f] = substr(trim((string) ($input[$f] ?? 'SAR')), 0, 3) ?: 'SAR';
            } else {
                $patch[$f] = PayrollSupport::nullIfEmpty($input[$f]);
            }
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new PayrollSalaryStructure())->update($id, $patch);
        (new PayrollTimelineService())->record('structure_updated', 'Salary structure updated', 'structure', $id);
    }
}

final class EmployeeSalaryService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $basic = PayrollSupport::floatOrZero($input['basic_salary'] ?? 0);
        if ($basic < 0) {
            throw new \InvalidArgumentException('basic_salary_invalid');
        }
        $structureId = PayrollSupport::intOrNull($input['structure_id'] ?? null);
        if ($structureId !== null) {
            PayrollSupport::assertStructure($structureId, $companyId);
        }
        $id = (new PayrollEmployeeSalary())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::intOrNull($input['branch_id'] ?? null) ?? PayrollSupport::branchId(),
            'hrm_employee_profile_id' => PayrollSupport::intOrNull($input['hrm_employee_profile_id'] ?? null),
            'legacy_employee_id' => PayrollSupport::intOrNull($input['legacy_employee_id'] ?? null),
            'structure_id' => $structureId,
            'basic_salary' => round($basic, 2),
            'currency_code' => substr(trim((string) ($input['currency_code'] ?? 'SAR')), 0, 3) ?: 'SAR',
            'effective_from' => PayrollSupport::dateOrNull($input['effective_from'] ?? null),
            'effective_to' => PayrollSupport::dateOrNull($input['effective_to'] ?? null),
            'status' => 'active',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        (new PayrollTimelineService())->record('employee_salary_created', 'Employee salary assigned', 'employee_salary', (int) $id);

        return ['id' => (int) $id];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = PayrollSupport::requireCompanyId();
        $row = (new PayrollEmployeeSalary())->queryOne(
            'SELECT * FROM rateb_payroll_employee_salary WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('employee_salary_not_found');
        }
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        $patch = PayrollSupport::actorFields(false);
        if (array_key_exists('basic_salary', $input)) {
            $patch['basic_salary'] = round(PayrollSupport::floatOrZero($input['basic_salary']), 2);
        }
        if (array_key_exists('structure_id', $input)) {
            $sid = PayrollSupport::intOrNull($input['structure_id']);
            if ($sid !== null) {
                PayrollSupport::assertStructure($sid, $companyId);
            }
            $patch['structure_id'] = $sid;
        }
        foreach (['hrm_employee_profile_id', 'legacy_employee_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = PayrollSupport::intOrNull($input[$f]);
            }
        }
        foreach (['effective_from', 'effective_to', 'notes', 'currency_code'] as $f) {
            if (!array_key_exists($f, $input)) {
                continue;
            }
            if ($f === 'currency_code') {
                $patch[$f] = substr(trim((string) ($input[$f] ?? 'SAR')), 0, 3) ?: 'SAR';
            } elseif ($f === 'effective_from' || $f === 'effective_to') {
                $patch[$f] = PayrollSupport::dateOrNull($input[$f]);
            } else {
                $patch[$f] = PayrollSupport::nullIfEmpty($input[$f]);
            }
        }
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new PayrollEmployeeSalary())->update($id, $patch);
    }
}

final class PayrollComponentService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $structureId = PayrollSupport::intOrNull($input['structure_id'] ?? null);
        if ($structureId === null) {
            throw new \InvalidArgumentException('structure_id_required');
        }
        PayrollSupport::assertStructure($structureId, $companyId);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = 'COMP-' . $structureId . '-' . time();
        }
        $componentType = (string) ($input['component_type'] ?? 'earning');
        if (!in_array($componentType, ['earning', 'deduction'], true)) {
            $componentType = 'earning';
        }
        $id = (new PayrollSalaryComponent())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'structure_id' => $structureId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => PayrollSupport::nullIfEmpty($input['name_ar'] ?? null),
            'component_type' => $componentType,
            'calc_method' => in_array($input['calc_method'] ?? '', ['fixed', 'percent_basic', 'percent_gross', 'formula'], true)
                ? $input['calc_method'] : 'fixed',
            'amount' => PayrollSupport::floatOrZero($input['amount'] ?? 0),
            'percent_value' => PayrollSupport::floatOrZero($input['percent_value'] ?? null) ?: null,
            'earning_type_id' => PayrollSupport::intOrNull($input['earning_type_id'] ?? null),
            'deduction_type_id' => PayrollSupport::intOrNull($input['deduction_type_id'] ?? null),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'status' => 'active',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForStructure(int $structureId): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $items = (new PayrollSalaryComponent())->query(
            'SELECT * FROM rateb_payroll_salary_components WHERE company_id = :cid AND structure_id = :sid'
            . ' AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC',
            ['cid' => $companyId, 'sid' => $structureId]
        );

        return is_array($items) ? $items : [];
    }
}

final class PayrollCycleService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new PayrollCycle())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_payroll_cycles WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new PayrollCycle())->query(
            'SELECT * FROM rateb_payroll_cycles WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = PayrollSupport::nextCode('rateb_payroll_cycles', 'PAY-CYC', $companyId);
        }
        $freq = (string) ($input['frequency'] ?? 'monthly');
        if (!in_array($freq, ['weekly', 'biweekly', 'monthly', 'quarterly', 'annual'], true)) {
            $freq = 'monthly';
        }
        $id = (new PayrollCycle())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => PayrollSupport::nullIfEmpty($input['name_ar'] ?? null),
            'frequency' => $freq,
            'start_day' => max(1, min(28, (int) ($input['start_day'] ?? 1))),
            'status' => 'active',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        (new PayrollTimelineService())->record('cycle_created', 'Payroll cycle: ' . $name, 'cycle', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function createRunPeriod(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $cycleId = PayrollSupport::intOrNull($input['cycle_id'] ?? null);
        if ($cycleId === null || PayrollSupport::findCycle($cycleId, $companyId) === null) {
            throw new \InvalidArgumentException('cycle_id_required');
        }
        $periodStart = PayrollSupport::dateOrNull($input['period_start'] ?? null);
        $periodEnd = PayrollSupport::dateOrNull($input['period_end'] ?? null);
        if ($periodStart === null || $periodEnd === null) {
            throw new \InvalidArgumentException('period_dates_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = PayrollSupport::nextCode('rateb_payroll_run_periods', 'PAY-PER', $companyId);
        }
        $id = (new PayrollRunPeriod())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'cycle_id' => $cycleId,
            'code' => substr($code, 0, 40),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'pay_date' => PayrollSupport::dateOrNull($input['pay_date'] ?? null),
            'legacy_payroll_period_id' => PayrollSupport::intOrNull($input['legacy_payroll_period_id'] ?? null),
            'status' => 'open',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class PayrollBatchService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?string $status = null): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($status !== null && $status !== '') {
            $where .= ' AND workflow_status = :st';
            $params['st'] = $status;
        }
        $totalRow = (new PayrollBatch())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_payroll_batches WHERE ' . $where,
            $params
        );
        $items = (new PayrollBatch())->query(
            'SELECT * FROM rateb_payroll_batches WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        return PayrollSupport::findBatch($id, PayrollSupport::requireCompanyId());
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = PayrollSupport::nextCode('rateb_payroll_batches', 'PAY-BAT', $companyId);
        }
        $id = (new PayrollBatch())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::intOrNull($input['branch_id'] ?? null) ?? PayrollSupport::branchId(),
            'cycle_id' => PayrollSupport::intOrNull($input['cycle_id'] ?? null),
            'run_period_id' => PayrollSupport::intOrNull($input['run_period_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'title_ar' => PayrollSupport::nullIfEmpty($input['title_ar'] ?? null),
            'period_start' => PayrollSupport::dateOrNull($input['period_start'] ?? null),
            'period_end' => PayrollSupport::dateOrNull($input['period_end'] ?? null),
            'pay_date' => PayrollSupport::dateOrNull($input['pay_date'] ?? null),
            'workflow_status' => 'draft',
            'status' => 'active',
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'employee_count' => 0,
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        (new PayrollTimelineService())->record('batch_created', 'Payroll batch: ' . $title, 'batch', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $companyId = PayrollSupport::requireCompanyId();
        $row = PayrollSupport::assertBatch($id, $companyId);
        if (isset($input['expected_version']) && (int) $input['expected_version'] !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
        if (!in_array($row['workflow_status'] ?? '', ['draft', 'prepared'], true)) {
            throw new \RuntimeException('batch_not_editable');
        }
        $patch = PayrollSupport::actorFields(false);
        foreach (['title', 'title_ar', 'notes'] as $f) {
            if (!array_key_exists($f, $input)) {
                continue;
            }
            if ($f === 'title') {
                $title = substr(trim((string) $input[$f]), 0, 190);
                if ($title === '') {
                    throw new \InvalidArgumentException('title_required');
                }
                $patch[$f] = $title;
            } else {
                $patch[$f] = PayrollSupport::nullIfEmpty($input[$f]);
            }
        }
        foreach (['period_start', 'period_end', 'pay_date'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = PayrollSupport::dateOrNull($input[$f]);
            }
        }
        foreach (['cycle_id', 'run_period_id'] as $f) {
            if (array_key_exists($f, $input)) {
                $patch[$f] = PayrollSupport::intOrNull($input[$f]);
            }
        }
        unset($patch['workflow_status']);
        $patch['version'] = (int) ($row['version'] ?? 1) + 1;
        (new PayrollBatch())->update($id, $patch);
        (new PayrollTimelineService())->record('batch_updated', 'Payroll batch updated', 'batch', $id);
    }
}

final class PayrollCalculationService
{
    /**
     * Calculate batch totals from payroll items (no attendance/leave mutation).
     *
     * @return array{gross: float, deductions: float, net: float, employee_count: int}
     */
    public function calculateBatch(int $batchId): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $batch = PayrollSupport::assertBatch($batchId, $companyId);
        if (!in_array($batch['workflow_status'] ?? '', ['draft', 'prepared', 'calculated'], true)) {
            throw new \RuntimeException('batch_not_calculable');
        }

        $rows = (new PayrollItem())->query(
            'SELECT gross_amount, deduction_amount, net_amount FROM rateb_payroll_items'
            . ' WHERE company_id = :cid AND batch_id = :bid AND deleted_at IS NULL',
            ['cid' => $companyId, 'bid' => $batchId]
        );
        $gross = 0.0;
        $deductions = 0.0;
        $net = 0.0;
        $count = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $gross += PayrollSupport::floatOrZero($row['gross_amount'] ?? 0);
            $deductions += PayrollSupport::floatOrZero($row['deduction_amount'] ?? 0);
            $net += PayrollSupport::floatOrZero($row['net_amount'] ?? 0);
            $count++;
        }

        (new PayrollBatch())->update($batchId, array_merge([
            'total_gross' => round($gross, 2),
            'total_deductions' => round($deductions, 2),
            'total_net' => round($net, 2),
            'employee_count' => $count,
            'version' => (int) ($batch['version'] ?? 1) + 1,
        ], PayrollSupport::actorFields(false)));

        if (($batch['workflow_status'] ?? '') === 'prepared') {
            (new PayrollWorkflowService())->transition(
                PayrollWorkflowService::ENTITY_BATCH,
                $batchId,
                'calculated',
                'auto_calculate'
            );
        }

        (new PayrollTimelineService())->record(
            'batch_calculated',
            'Batch calculated: ' . ($batch['code'] ?? '') . ' — net ' . number_format($net, 2),
            'batch',
            $batchId
        );

        return [
            'gross' => round($gross, 2),
            'deductions' => round($deductions, 2),
            'net' => round($net, 2),
            'employee_count' => $count,
        ];
    }

    /**
     * Build payroll item from employee salary + components (soft-link refs only).
     *
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function addItem(int $batchId, array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $batch = PayrollSupport::assertBatch($batchId, $companyId);
        if (!in_array($batch['workflow_status'] ?? '', ['draft', 'prepared'], true)) {
            throw new \RuntimeException('batch_items_locked');
        }

        $basic = PayrollSupport::floatOrZero($input['basic_salary'] ?? 0);
        $employeeSalaryId = PayrollSupport::intOrNull($input['employee_salary_id'] ?? null);
        if ($employeeSalaryId !== null) {
            $salRow = (new PayrollEmployeeSalary())->queryOne(
                'SELECT basic_salary FROM rateb_payroll_employee_salary'
                . ' WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
                ['id' => $employeeSalaryId, 'cid' => $companyId]
            );
            if (is_array($salRow)) {
                $basic = PayrollSupport::floatOrZero($salRow['basic_salary'] ?? $basic);
            }
        }

        $gross = PayrollSupport::floatOrZero($input['gross_amount'] ?? $basic);
        $deductions = PayrollSupport::floatOrZero($input['deduction_amount'] ?? 0);
        $net = max(0, $gross - $deductions);

        $id = (new PayrollItem())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'batch_id' => $batchId,
            'hrm_employee_profile_id' => PayrollSupport::intOrNull($input['hrm_employee_profile_id'] ?? null),
            'legacy_employee_id' => PayrollSupport::intOrNull($input['legacy_employee_id'] ?? null),
            'employee_salary_id' => $employeeSalaryId,
            'basic_salary' => round($basic, 2),
            'gross_amount' => round($gross, 2),
            'deduction_amount' => round($deductions, 2),
            'net_amount' => round($net, 2),
            'attendance_ref' => PayrollSupport::nullIfEmpty($input['attendance_ref'] ?? null),
            'leave_ref' => PayrollSupport::nullIfEmpty($input['leave_ref'] ?? null),
            'status' => 'active',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class PayrollPayslipService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0, ?int $batchId = null): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $params = ['cid' => $companyId];
        $where = 'company_id = :cid AND deleted_at IS NULL';
        if ($batchId !== null && $batchId > 0) {
            $where .= ' AND batch_id = :bid';
            $params['bid'] = $batchId;
        }
        $totalRow = (new PayrollPayslip())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_payroll_payslips WHERE ' . $where,
            $params
        );
        $items = (new PayrollPayslip())->query(
            'SELECT * FROM rateb_payroll_payslips WHERE ' . $where
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            $params
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * Issue payslips for all items in a calculated+ batch.
     *
     * @return array{issued: int}
     */
    public function issueForBatch(int $batchId): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $batch = PayrollSupport::assertBatch($batchId, $companyId);
        $items = (new PayrollItem())->query(
            'SELECT * FROM rateb_payroll_items WHERE company_id = :cid AND batch_id = :bid AND deleted_at IS NULL',
            ['cid' => $companyId, 'bid' => $batchId]
        );
        $issued = 0;
        foreach (is_array($items) ? $items : [] as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId < 1) {
                continue;
            }
            $exists = (new PayrollPayslip())->queryOne(
                'SELECT id FROM rateb_payroll_payslips WHERE payroll_item_id = :pid AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
                ['pid' => $itemId, 'cid' => $companyId]
            );
            if (is_array($exists)) {
                continue;
            }
            $payslipNo = 'PS-' . ($batch['code'] ?? 'BAT') . '-' . $itemId;
            (new PayrollPayslip())->create(array_merge([
                'public_uuid' => PayrollSupport::uuidV4(),
                'company_id' => $companyId,
                'branch_id' => PayrollSupport::branchId(),
                'batch_id' => $batchId,
                'payroll_item_id' => $itemId,
                'hrm_employee_profile_id' => PayrollSupport::intOrNull($item['hrm_employee_profile_id'] ?? null),
                'legacy_employee_id' => PayrollSupport::intOrNull($item['legacy_employee_id'] ?? null),
                'payslip_number' => substr($payslipNo, 0, 40),
                'period_start' => $batch['period_start'] ?? null,
                'period_end' => $batch['period_end'] ?? null,
                'pay_date' => $batch['pay_date'] ?? null,
                'gross_amount' => PayrollSupport::floatOrZero($item['gross_amount'] ?? 0),
                'deduction_amount' => PayrollSupport::floatOrZero($item['deduction_amount'] ?? 0),
                'net_amount' => PayrollSupport::floatOrZero($item['net_amount'] ?? 0),
                'workflow_status' => 'issued',
                'status' => 'active',
                'version' => 1,
            ], PayrollSupport::actorFields(true)));
            $issued++;
        }

        (new PayrollTimelineService())->record(
            'payslips_issued',
            'Issued ' . $issued . ' payslips for batch ' . ($batch['code'] ?? ''),
            'batch',
            $batchId
        );

        return ['issued' => $issued];
    }
}

final class LoanService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new PayrollLoan())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_payroll_loans WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new PayrollLoan())->query(
            'SELECT * FROM rateb_payroll_loans WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $principal = PayrollSupport::floatOrZero($input['principal_amount'] ?? 0);
        if ($principal <= 0) {
            throw new \InvalidArgumentException('principal_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = PayrollSupport::nextCode('rateb_payroll_loans', 'PAY-LOAN', $companyId);
        }
        $installments = max(1, (int) ($input['installments_total'] ?? 1));
        $installmentAmount = PayrollSupport::floatOrZero($input['installment_amount'] ?? 0);
        if ($installmentAmount <= 0) {
            $installmentAmount = round($principal / $installments, 2);
        }
        $id = (new PayrollLoan())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'hrm_employee_profile_id' => PayrollSupport::intOrNull($input['hrm_employee_profile_id'] ?? null),
            'legacy_employee_id' => PayrollSupport::intOrNull($input['legacy_employee_id'] ?? null),
            'code' => substr($code, 0, 40),
            'principal_amount' => round($principal, 2),
            'outstanding_amount' => round($principal, 2),
            'installment_amount' => round($installmentAmount, 2),
            'installments_total' => $installments,
            'installments_paid' => 0,
            'start_date' => PayrollSupport::dateOrNull($input['start_date'] ?? null) ?? date('Y-m-d'),
            'status' => 'active',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        $this->seedInstallments((int) $id, $companyId, $installments, $installmentAmount, (string) ($input['start_date'] ?? date('Y-m-d')));

        (new PayrollTimelineService())->record('loan_created', 'Loan: ' . $code, 'loan', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }

    private function seedInstallments(int $loanId, int $companyId, int $count, float $amount, string $startDate): void
    {
        $ts = strtotime($startDate) ?: time();
        for ($i = 1; $i <= $count; $i++) {
            (new PayrollLoanInstallment())->create(array_merge([
                'public_uuid' => PayrollSupport::uuidV4(),
                'company_id' => $companyId,
                'branch_id' => PayrollSupport::branchId(),
                'loan_id' => $loanId,
                'installment_no' => $i,
                'due_date' => date('Y-m-d', strtotime('+' . $i . ' month', $ts)),
                'amount' => round($amount, 2),
                'paid_amount' => 0,
                'status' => 'pending',
                'version' => 1,
            ], PayrollSupport::actorFields(true)));
        }
    }
}

final class AdvanceService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new PayrollAdvance())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_payroll_advances WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new PayrollAdvance())->query(
            'SELECT * FROM rateb_payroll_advances WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $amount = PayrollSupport::floatOrZero($input['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = PayrollSupport::nextCode('rateb_payroll_advances', 'PAY-ADV', $companyId);
        }
        $advanceDate = PayrollSupport::dateOrNull($input['advance_date'] ?? null) ?? date('Y-m-d');
        $id = (new PayrollAdvance())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'hrm_employee_profile_id' => PayrollSupport::intOrNull($input['hrm_employee_profile_id'] ?? null),
            'legacy_employee_id' => PayrollSupport::intOrNull($input['legacy_employee_id'] ?? null),
            'batch_id' => PayrollSupport::intOrNull($input['batch_id'] ?? null),
            'code' => substr($code, 0, 40),
            'amount' => round($amount, 2),
            'advance_date' => $advanceDate,
            'recovery_amount' => PayrollSupport::floatOrZero($input['recovery_amount'] ?? $amount),
            'status' => 'pending',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        (new PayrollTimelineService())->record('advance_created', 'Advance: ' . $code, 'advance', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class BonusService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $title = trim((string) ($input['title'] ?? ''));
        $amount = PayrollSupport::floatOrZero($input['amount'] ?? 0);
        if ($title === '' || $amount <= 0) {
            throw new \InvalidArgumentException('title_and_amount_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = PayrollSupport::nextCode('rateb_payroll_bonuses', 'PAY-BON', $companyId);
        }
        $id = (new PayrollBonus())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'hrm_employee_profile_id' => PayrollSupport::intOrNull($input['hrm_employee_profile_id'] ?? null),
            'legacy_employee_id' => PayrollSupport::intOrNull($input['legacy_employee_id'] ?? null),
            'batch_id' => PayrollSupport::intOrNull($input['batch_id'] ?? null),
            'code' => substr($code, 0, 40),
            'title' => substr($title, 0, 190),
            'amount' => round($amount, 2),
            'bonus_date' => PayrollSupport::dateOrNull($input['bonus_date'] ?? null),
            'status' => 'pending',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class OvertimeService
{
    /**
     * @return array{items: list<array<string,mixed>>, total: int}
     */
    public function list(int $limit = 25, int $offset = 0): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $totalRow = (new PayrollOvertime())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_payroll_overtime WHERE company_id = :cid AND deleted_at IS NULL',
            ['cid' => $companyId]
        );
        $items = (new PayrollOvertime())->query(
            'SELECT * FROM rateb_payroll_overtime WHERE company_id = :cid AND deleted_at IS NULL'
            . ' ORDER BY overtime_date DESC, id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset,
            ['cid' => $companyId]
        );

        return ['items' => is_array($items) ? $items : [], 'total' => (int) ($totalRow['c'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $hours = PayrollSupport::floatOrZero($input['hours'] ?? 0);
        if ($hours <= 0) {
            throw new \InvalidArgumentException('hours_required');
        }
        $overtimeDate = PayrollSupport::dateOrNull($input['overtime_date'] ?? null) ?? date('Y-m-d');
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = PayrollSupport::nextCode('rateb_payroll_overtime', 'PAY-OT', $companyId);
        }
        $multiplier = max(1, PayrollSupport::floatOrZero($input['rate_multiplier'] ?? 1.5));
        $amount = PayrollSupport::floatOrZero($input['amount'] ?? 0);
        $id = (new PayrollOvertime())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'hrm_employee_profile_id' => PayrollSupport::intOrNull($input['hrm_employee_profile_id'] ?? null),
            'legacy_employee_id' => PayrollSupport::intOrNull($input['legacy_employee_id'] ?? null),
            'batch_id' => PayrollSupport::intOrNull($input['batch_id'] ?? null),
            'code' => substr($code, 0, 40),
            'overtime_date' => $overtimeDate,
            'hours' => round($hours, 2),
            'rate_multiplier' => round($multiplier, 2),
            'amount' => round($amount, 2),
            'attendance_ref' => PayrollSupport::nullIfEmpty($input['attendance_ref'] ?? null),
            'status' => 'pending',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class SettlementService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int, code: string}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $amount = PayrollSupport::floatOrZero($input['amount'] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount_required');
        }
        $code = 'PAY-SET-' . date('Y') . '-' . time();
        $settlementType = (string) ($input['settlement_type'] ?? 'final');
        if (!in_array($settlementType, ['final', 'partial', 'eos'], true)) {
            $settlementType = 'final';
        }
        $id = (new PayrollSettlement())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'hrm_employee_profile_id' => PayrollSupport::intOrNull($input['hrm_employee_profile_id'] ?? null),
            'legacy_employee_id' => PayrollSupport::intOrNull($input['legacy_employee_id'] ?? null),
            'batch_id' => PayrollSupport::intOrNull($input['batch_id'] ?? null),
            'code' => substr($code, 0, 40),
            'settlement_type' => $settlementType,
            'amount' => round($amount, 2),
            'settlement_date' => PayrollSupport::dateOrNull($input['settlement_date'] ?? null),
            'status' => 'draft',
            'notes' => PayrollSupport::nullIfEmpty($input['notes'] ?? null),
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        (new PayrollTimelineService())->record('settlement_created', 'Settlement: ' . $code, 'settlement', (int) $id);

        return ['id' => (int) $id, 'code' => $code];
    }
}

final class PayrollCommentService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function create(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $entityType = trim((string) ($input['entity_type'] ?? ''));
        $entityId = PayrollSupport::intOrNull($input['entity_id'] ?? null);
        $text = trim((string) ($input['comment_text'] ?? ''));
        if ($entityType === '' || $entityId === null || $text === '') {
            throw new \InvalidArgumentException('comment_fields_required');
        }
        $id = (new PayrollComment())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'entity_type' => substr($entityType, 0, 40),
            'entity_id' => $entityId,
            'comment_text' => $text,
            'status' => 'active',
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}

final class PayrollDocumentMetaService
{
    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function createMeta(array $input): array
    {
        $companyId = PayrollSupport::requireCompanyId();
        $entityType = trim((string) ($input['entity_type'] ?? ''));
        $entityId = PayrollSupport::intOrNull($input['entity_id'] ?? null);
        $title = trim((string) ($input['title'] ?? ''));
        if ($entityType === '' || $entityId === null || $title === '') {
            throw new \InvalidArgumentException('document_meta_required');
        }
        $id = (new PayrollAttachmentMeta())->create(array_merge([
            'public_uuid' => PayrollSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => PayrollSupport::branchId(),
            'entity_type' => substr($entityType, 0, 40),
            'entity_id' => $entityId,
            'doc_type' => substr(trim((string) ($input['doc_type'] ?? 'attachment')), 0, 40),
            'title' => substr($title, 0, 190),
            'file_name' => PayrollSupport::nullIfEmpty($input['file_name'] ?? null),
            'mime_type' => PayrollSupport::nullIfEmpty($input['mime_type'] ?? null),
            'file_size' => PayrollSupport::intOrNull($input['file_size'] ?? null),
            'storage_key' => PayrollSupport::nullIfEmpty($input['storage_key'] ?? null),
            'status' => 'active',
            'version' => 1,
        ], PayrollSupport::actorFields(true)));

        return ['id' => (int) $id];
    }
}
