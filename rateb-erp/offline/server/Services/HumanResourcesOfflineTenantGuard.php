<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\HrmDepartment;
use Rateb\App\Models\HrmEmployeeProfile;
use Rateb\App\Models\HrmPerformanceReview;
use Rateb\App\Models\HrmPosition;
use Rateb\App\Models\HrmTraining;

/**
 * Tenant + branch isolation for Enterprise HRMS offline replay (Phase 23B).
 * Additive — does not alter Phase 4 HrOfflineTenantGuard or Phase 23A domain services.
 */
final class HumanResourcesOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, profile?: array<string, mixed>}
     */
    public function assertProfile(int $profileId, array $scope): array
    {
        return $this->assertRow(
            $profileId,
            $scope,
            'rateb_hrm_employee_profiles',
            new HrmEmployeeProfile(),
            'profile_not_found',
            'invalid_profile_id',
            'profile'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, department?: array<string, mixed>}
     */
    public function assertDepartment(int $departmentId, array $scope): array
    {
        return $this->assertRow(
            $departmentId,
            $scope,
            'rateb_hrm_departments',
            new HrmDepartment(),
            'department_not_found',
            'invalid_department_id',
            'department'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, position?: array<string, mixed>}
     */
    public function assertPosition(int $positionId, array $scope): array
    {
        return $this->assertRow(
            $positionId,
            $scope,
            'rateb_hrm_positions',
            new HrmPosition(),
            'position_not_found',
            'invalid_position_id',
            'position'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, training?: array<string, mixed>}
     */
    public function assertTraining(int $trainingId, array $scope): array
    {
        return $this->assertRow(
            $trainingId,
            $scope,
            'rateb_hrm_training',
            new HrmTraining(),
            'training_not_found',
            'invalid_training_id',
            'training'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, performance?: array<string, mixed>}
     */
    public function assertPerformance(int $performanceId, array $scope): array
    {
        return $this->assertRow(
            $performanceId,
            $scope,
            'rateb_hrm_performance_reviews',
            new HrmPerformanceReview(),
            'performance_not_found',
            'invalid_performance_id',
            'performance'
        );
    }

    public function profileExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_hrm_employee_profiles', new HrmEmployeeProfile(), $idempotencyKey);
    }

    public function departmentExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_hrm_departments', new HrmDepartment(), $idempotencyKey);
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
     * @return array{ok: bool, error?: string, profile?: array<string, mixed>, department?: array<string, mixed>, position?: array<string, mixed>, training?: array<string, mixed>, performance?: array<string, mixed>}
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
