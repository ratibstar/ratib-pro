<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
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

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, order?: array<string, mixed>}
     */
    public function assertPurchaseOrder(int $orderId, array $scope): array
    {
        if ($orderId < 1) {
            return ['ok' => false, 'error' => 'invalid_purchase_order_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new PurchaseOrder())->find($orderId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'purchase_order_not_found'];
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

        return ['ok' => true, 'order' => $row];
    }

    /**
     * Idempotency for Phase 14.2 GRN — stock movement notes marker `[offline:key]`.
     * Returns purchase_order_id (reference_id) when found.
     */
    public function goodsReceiptExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        if ($companyId < 1 || $idempotencyKey === '' || !OfflineSchema::hasColumn('rateb_stock_movements', 'id')) {
            return null;
        }
        if (!OfflineSchema::hasColumn('rateb_stock_movements', 'notes')) {
            return null;
        }
        $marker = '%[offline:' . $idempotencyKey . ']%';
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT reference_id FROM rateb_stock_movements
             WHERE company_id = :cid
               AND reference_type = \'purchase_order\'
               AND notes LIKE :marker
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'marker' => $marker]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $poId = (int) ($row['reference_id'] ?? 0);

        return $poId > 0 ? $poId : null;
    }

    /**
     * Stamp unstamped GRN stock movements for a PO with `[offline:key]` after receiveOrder.
     * Additive — does not alter ProcurementService.
     */
    public function stampGoodsReceiptMovements(int $companyId, int $orderId, string $idempotencyKey): void
    {
        if ($companyId < 1 || $orderId < 1 || $idempotencyKey === '') {
            return;
        }
        if (!OfflineSchema::hasColumn('rateb_stock_movements', 'notes')) {
            return;
        }
        $marker = ' [offline:' . $idempotencyKey . ']';
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE rateb_stock_movements
             SET notes = CONCAT(COALESCE(notes, \'\'), :marker)
             WHERE company_id = :cid
               AND reference_type = \'purchase_order\'
               AND reference_id = :oid
               AND (notes IS NULL OR notes NOT LIKE \'%[offline:%\')'
        );
        $stmt->execute([
            'marker' => $marker,
            'cid' => $companyId,
            'oid' => $orderId,
        ]);
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
