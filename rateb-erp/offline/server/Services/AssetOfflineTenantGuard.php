<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\EamAsset;
use Rateb\App\Models\EamMaintenanceRequest;
use Rateb\App\Models\EamWorkOrder;

/**
 * Tenant + branch isolation for Assets offline replay (Phase 19B).
 * Additive — does not alter Phase 19A EAM domain services.
 */
final class AssetOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, asset?: array<string, mixed>}
     */
    public function assertAsset(int $assetId, array $scope): array
    {
        if ($assetId < 1) {
            return ['ok' => false, 'error' => 'invalid_asset_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new EamAsset())->queryOne(
            'SELECT * FROM rateb_eam_assets WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $assetId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'asset_not_found'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'asset' => $row];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, request?: array<string, mixed>}
     */
    public function assertMaintenanceRequest(int $requestId, array $scope): array
    {
        if ($requestId < 1) {
            return ['ok' => false, 'error' => 'invalid_request_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new EamMaintenanceRequest())->queryOne(
            'SELECT * FROM rateb_eam_maintenance_requests WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $requestId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'maintenance_request_not_found'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'request' => $row];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, work_order?: array<string, mixed>}
     */
    public function assertWorkOrder(int $workOrderId, array $scope): array
    {
        if ($workOrderId < 1) {
            return ['ok' => false, 'error' => 'invalid_work_order_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new EamWorkOrder())->queryOne(
            'SELECT * FROM rateb_eam_work_orders WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $workOrderId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'work_order_not_found'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'work_order' => $row];
    }

    public function assetExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        $key = trim($idempotencyKey);
        if ($companyId < 1 || $key === '') {
            return null;
        }
        $marker = '%[offline:' . $key . ']%';
        $row = (new EamAsset())->queryOne(
            'SELECT id FROM rateb_eam_assets
             WHERE company_id = :cid AND deleted_at IS NULL AND notes LIKE :m
             LIMIT 1',
            ['cid' => $companyId, 'm' => $marker]
        );

        return $row !== null ? (int) ($row['id'] ?? 0) : null;
    }
}
