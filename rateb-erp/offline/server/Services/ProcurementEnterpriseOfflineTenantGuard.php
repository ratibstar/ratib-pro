<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\EprocCollaboration;
use Rateb\App\Models\EprocContract;
use Rateb\App\Models\EprocSupplierProfile;
use Rateb\App\Models\EprocSupplierQualification;
use Rateb\App\Models\EprocTender;

/**
 * Tenant + branch isolation for Enterprise Procurement offline replay (Phase 21B).
 * Additive — does not alter Phase 21A EPROC domain services or legacy Procurement offline.
 */
final class ProcurementEnterpriseOfflineTenantGuard
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
            'rateb_eproc_supplier_profiles',
            new EprocSupplierProfile(),
            'profile_not_found',
            'invalid_profile_id',
            'profile'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, tender?: array<string, mixed>}
     */
    public function assertTender(int $tenderId, array $scope): array
    {
        return $this->assertRow(
            $tenderId,
            $scope,
            'rateb_eproc_tenders',
            new EprocTender(),
            'tender_not_found',
            'invalid_tender_id',
            'tender'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, contract?: array<string, mixed>}
     */
    public function assertContract(int $contractId, array $scope): array
    {
        return $this->assertRow(
            $contractId,
            $scope,
            'rateb_eproc_contracts',
            new EprocContract(),
            'contract_not_found',
            'invalid_contract_id',
            'contract'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, qualification?: array<string, mixed>}
     */
    public function assertQualification(int $qualificationId, array $scope): array
    {
        return $this->assertRow(
            $qualificationId,
            $scope,
            'rateb_eproc_supplier_qualification',
            new EprocSupplierQualification(),
            'qualification_not_found',
            'invalid_qualification_id',
            'qualification'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, collaboration?: array<string, mixed>}
     */
    public function assertCollaboration(int $collaborationId, array $scope): array
    {
        return $this->assertRow(
            $collaborationId,
            $scope,
            'rateb_eproc_collaboration',
            new EprocCollaboration(),
            'collaboration_not_found',
            'invalid_collaboration_id',
            'collaboration'
        );
    }

    public function profileExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        $key = trim($idempotencyKey);
        if ($companyId < 1 || $key === '') {
            return null;
        }
        $marker = '%[offline:' . $key . ']%';
        $row = (new EprocSupplierProfile())->queryOne(
            'SELECT id FROM rateb_eproc_supplier_profiles
             WHERE company_id = :cid AND deleted_at IS NULL AND notes LIKE :m
             LIMIT 1',
            ['cid' => $companyId, 'm' => $marker]
        );

        return $row !== null ? (int) ($row['id'] ?? 0) : null;
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, profile?: array<string, mixed>, tender?: array<string, mixed>, contract?: array<string, mixed>, qualification?: array<string, mixed>, collaboration?: array<string, mixed>}
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
