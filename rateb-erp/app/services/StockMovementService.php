<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\StockMovement;
use Rateb\App\Services\DocumentCodeService;

final class StockMovementService
{
    /** @param array<string, mixed> $data */
    public function record(array $data): int
    {
        $inventoryId = (int) ($data['inventory_id'] ?? 0);
        $movementType = (string) ($data['movement_type'] ?? 'adjustment');
        $quantity = (float) ($data['quantity'] ?? 0);
        $warehouseId = isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null;
        if ($warehouseId !== null && $warehouseId < 1) {
            $warehouseId = null;
        }

        if ($inventoryId < 1 || $quantity <= 0) {
            throw new \InvalidArgumentException('Invalid stock movement');
        }

        $invModel = new Inventory();
        $item = $invModel->find($inventoryId);
        if (!$item) {
            throw new \RuntimeException('Inventory item not found');
        }

        $currentQty = (float) ($item['quantity'] ?? 0);
        if ($movementType === 'adjustment') {
            $newQty = max(0, abs($quantity));
        } else {
            $delta = in_array($movementType, ['out', 'transfer'], true) ? -abs($quantity) : abs($quantity);
            $newQty = max(0, $currentQty + $delta);
        }

        $maxStock = isset($item['max_stock']) ? (float) $item['max_stock'] : 0;
        if ($maxStock > 0 && $newQty > $maxStock) {
            throw new \InvalidArgumentException(__('max_stock_exceeded'));
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $movementModel = new StockMovement();
            $movementNo = trim((string) ($data['movement_no'] ?? ''));
            if ($movementNo === '') {
                $movementNo = $movementModel->generateDocumentCode(
                    DocumentCodeService::PREFIX_MOVEMENT,
                    'movement_no'
                );
            }
            $movementId = $movementModel->create([
                'company_id' => $this->resolveCompanyId($item),
                'movement_no' => $movementNo,
                'inventory_id' => $inventoryId,
                'warehouse_id' => $warehouseId,
                'movement_type' => $movementType,
                'quantity' => abs($quantity),
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => isset($data['reference_id']) ? (int) $data['reference_id'] : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => SessionManager::get('rateb_user_id'),
            ]);

            $invModel->update($inventoryId, ['quantity' => $newQty]);

            if (in_array($movementType, ['out', 'transfer'], true)) {
                (new InventoryWorkflowService())->consumeBatches($inventoryId, abs($quantity), 'fefo');
            }

            $reorder = (float) ($item['reorder_level'] ?? 0);
            $companyId = (int) ($item['company_id'] ?? TenantContext::companyId() ?? 0);
            if ($newQty <= $reorder && $reorder > 0 && $companyId > 0) {
                try {
                    (new NotificationService())->triggerLowStock($companyId, (string) ($item['item_name'] ?? ''), $newQty);
                } catch (\Throwable $e) {
                    // Low-stock alerts must not roll back a valid stock movement.
                    error_log('Low stock notification failed: ' . $e->getMessage());
                }
            }

            $db->commit();
            if (in_array($movementType, ['in', 'out'], true)) {
                try {
                    (new AccountingService())->autoPostStockMovement($movementId);
                } catch (\Throwable $e) {
                    // Accounting post is best-effort; stock movement already saved.
                }
            }
            return $movementId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listRecent(int $limit = 50, ?int $companyId = null): array
    {
        $companyId = $companyId ?? TenantContext::companyId();
        $sql = 'SELECT m.*, i.item_name, w.name AS warehouse_name
                FROM rateb_stock_movements m
                LEFT JOIN rateb_inventory i ON i.id = m.inventory_id
                LEFT JOIN rateb_warehouses w ON w.id = m.warehouse_id
                WHERE 1=1';
        $params = [];
        if ($companyId !== null && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND m.company_id = :cid';
            $params['cid'] = $companyId;
        } elseif (TenantContext::isSuperAdmin() && (int) ($_GET['company_id'] ?? 0) > 0) {
            $sql .= ' AND m.company_id = :cid';
            $params['cid'] = (int) $_GET['company_id'];
        }
        $sql .= ' ORDER BY ' . rateb_list_order_sql('m') . ' LIMIT ' . max(1, min(500, $limit));
        return (new StockMovement())->query($sql, $params);
    }

    /** @param array<string, mixed> $item */
    private function resolveCompanyId(array $item): int
    {
        $companyId = (int) ($item['company_id'] ?? 0);
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
            return $companyId;
        }
        $ctx = TenantContext::companyId();
        if ($ctx !== null && $ctx > 0) {
            return (int) $ctx;
        }
        if (function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
            if ($companyId > 0) {
                TenantContext::setCompanyId($companyId);
                return $companyId;
            }
        }
        throw new \RuntimeException(function_exists('__') ? __('select_company_ops') : 'Company context required for tenant-scoped create.');
    }
}
