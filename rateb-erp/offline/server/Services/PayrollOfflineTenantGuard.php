<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\PayrollBatch;
use Rateb\App\Models\PayrollEmployeeSalary;
use Rateb\App\Models\PayrollSalaryStructure;

/**
 * Tenant + branch isolation for Payroll offline replay (Phase 24B).
 * Additive — does not alter Phase 24A payroll domain services or legacy hr/payroll.
 */
final class PayrollOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, structure?: array<string, mixed>}
     */
    public function assertStructure(int $structureId, array $scope): array
    {
        return $this->assertRow(
            $structureId,
            $scope,
            'rateb_payroll_salary_structures',
            new PayrollSalaryStructure(),
            'salary_structure_not_found',
            'invalid_structure_id',
            'structure'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, employee_salary?: array<string, mixed>}
     */
    public function assertEmployeeSalary(int $employeeSalaryId, array $scope): array
    {
        return $this->assertRow(
            $employeeSalaryId,
            $scope,
            'rateb_payroll_employee_salary',
            new PayrollEmployeeSalary(),
            'employee_salary_not_found',
            'invalid_employee_salary_id',
            'employee_salary'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, batch?: array<string, mixed>}
     */
    public function assertBatch(int $batchId, array $scope): array
    {
        return $this->assertRow(
            $batchId,
            $scope,
            'rateb_payroll_batches',
            new PayrollBatch(),
            'payroll_batch_not_found',
            'invalid_batch_id',
            'batch'
        );
    }

    public function structureExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_payroll_salary_structures', new PayrollSalaryStructure(), $idempotencyKey);
    }

    public function employeeSalaryExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_payroll_employee_salary', new PayrollEmployeeSalary(), $idempotencyKey);
    }

    public function batchExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_payroll_batches', new PayrollBatch(), $idempotencyKey);
    }

    private function existsForNotesKey(int $companyId, string $table, object $model, string $idempotencyKey): ?int
    {
        $key = trim($idempotencyKey);
        if ($companyId < 1 || $key === '') {
            return null;
        }
        $marker = '%[offline:' . $key . ']%';
        $row = $model->queryOne(
            'SELECT id FROM ' . $table . '
             WHERE company_id = :cid AND deleted_at IS NULL AND notes LIKE :m
             LIMIT 1',
            ['cid' => $companyId, 'm' => $marker]
        );

        return $row !== null ? (int) ($row['id'] ?? 0) : null;
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, structure?: array<string, mixed>, employee_salary?: array<string, mixed>, batch?: array<string, mixed>}
     */
    private function assertRow(
        int $id,
        array $scope,
        string $table,
        object $model,
        string $notFound,
        string $invalidId,
        string $key
    ): array {
        if ($id < 1) {
            return ['ok' => false, 'error' => $invalidId];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        /** @var array<string, mixed>|null $row */
        $row = $model->queryOne(
            'SELECT * FROM ' . $table . ' WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => $notFound];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, $key => $row];
    }
}
