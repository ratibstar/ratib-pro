<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\Rfq;
use Rateb\App\Models\Supplier;
use Rateb\App\Models\Warehouse;

/**
 * Tenant + branch isolation for Procurement offline replay.
 * Additive — does not alter ProcurementService / controllers.
 */
final class ProcurementOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, supplier?: array<string, mixed>}
     */
    public function assertSupplier(int $supplierId, array $scope): array
    {
        if ($supplierId < 1) {
            return ['ok' => false, 'error' => 'invalid_supplier_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new Supplier())->find($supplierId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'supplier_not_found'];
        }
        if ((int) ($row['company_id'] ?? 0) !== $companyId) {
            return ['ok' => false, 'error' => 'tenant_mismatch'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'supplier' => $row];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string}
     */
    public function assertWarehouse(?int $warehouseId, array $scope): array
    {
        if ($warehouseId === null || $warehouseId < 1) {
            return ['ok' => true];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new Warehouse())->find($warehouseId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'warehouse_not_found'];
        }
        if ((int) ($row['company_id'] ?? 0) !== $companyId) {
            return ['ok' => false, 'error' => 'warehouse_tenant_mismatch'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'warehouse_branch_mismatch'];
            }
        }

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, request?: array<string, mixed>}
     */
    public function assertPurchaseRequest(int $requestId, array $scope): array
    {
        if ($requestId < 1) {
            return ['ok' => false, 'error' => 'invalid_purchase_request_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new PurchaseRequest())->find($requestId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'purchase_request_not_found'];
        }
        if ((int) ($row['company_id'] ?? 0) !== $companyId) {
            return ['ok' => false, 'error' => 'tenant_mismatch'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'request' => $row];
    }

    public function purchaseRequestExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->findIdByOfflineMarker('rateb_purchase_requests', 'notes', $companyId, $idempotencyKey);
    }

    public function rfqExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->findIdByOfflineMarker('rateb_rfq', 'description', $companyId, $idempotencyKey);
    }

    public function purchaseOrderExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->findIdByOfflineMarker('rateb_purchase_orders', 'notes', $companyId, $idempotencyKey);
    }

    private function findIdByOfflineMarker(string $table, string $column, int $companyId, string $idempotencyKey): ?int
    {
        if ($companyId < 1 || $idempotencyKey === '' || !OfflineSchema::hasColumn($table, 'id')) {
            return null;
        }
        if (!OfflineSchema::hasColumn($table, $column)) {
            return null;
        }
        $marker = '%[offline:' . $idempotencyKey . ']%';
        $safeTable = str_replace('`', '', $table);
        $safeCol = str_replace('`', '', $column);
        $model = match ($table) {
            'rateb_purchase_requests' => new PurchaseRequest(),
            'rateb_rfq' => new Rfq(),
            'rateb_purchase_orders' => new PurchaseOrder(),
            default => null,
        };
        if ($model === null) {
            return null;
        }
        $row = $model->queryOne(
            "SELECT id FROM `{$safeTable}`
             WHERE company_id = :cid AND `{$safeCol}` LIKE :marker
             ORDER BY id ASC LIMIT 1",
            ['cid' => $companyId, 'marker' => $marker]
        );

        return $row ? (int) ($row['id'] ?? 0) : null;
    }
}
