<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\InventoryAudit;
use Rateb\App\Models\InventoryBatch;
use Rateb\App\Models\StockMovement;
use Rateb\App\Models\Warehouse;
use Rateb\App\Services\DocumentCodeService;
use Rateb\App\Services\TenantFkValidator;

final class InventoryWorkflowService
{
    public function generateCodes(int $inventoryId): array
    {
        $item = (new Inventory())->find($inventoryId);
        if (!$item) {
            throw new \RuntimeException('Inventory item not found');
        }
        $companyId = (int) ($item['company_id'] ?? TenantContext::companyId() ?? 0);
        $sku = (string) ($item['sku'] ?? '');
        if ($sku === '') {
            $sku = 'INV' . str_pad((string) $inventoryId, 6, '0', STR_PAD_LEFT);
            (new Inventory())->update($inventoryId, ['sku' => $sku]);
        }
        $codes = (new DocumentBarcodeService())->ensure('inventory', $inventoryId);
        if (!$codes) {
            throw new \RuntimeException('Could not generate inventory barcode');
        }
        return ['barcode' => $codes['barcode'], 'qr_code' => $codes['qr_code'], 'sku' => $sku];
    }

    /** @return array<int, array<string, mixed>> */
    public function listBatches(int $limit = 100): array
    {
        $companyId = TenantContext::companyId();
        $sql = 'SELECT b.*, i.item_name, w.name AS warehouse_name
                FROM rateb_inventory_batches b
                LEFT JOIN rateb_inventory i ON i.id = b.inventory_id
                LEFT JOIN rateb_warehouses w ON w.id = b.warehouse_id
                WHERE 1=1';
        $params = [];
        if ($companyId !== null && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND b.company_id = :cid';
            $params['cid'] = $companyId;
        } elseif (TenantContext::isSuperAdmin() && (int) ($_GET['company_id'] ?? 0) > 0) {
            $sql .= ' AND b.company_id = :cid';
            $params['cid'] = (int) $_GET['company_id'];
        }
        $sql .= ' ORDER BY b.id DESC LIMIT ' . max(1, min(500, $limit));
        return (new InventoryBatch())->query($sql, $params);
    }

    /** @param array<string, mixed> $data */
    public function createBatch(array $data): int
    {
        $invId = (int) ($data['inventory_id'] ?? 0);
        if ($invId < 1) {
            throw new \RuntimeException(__('inventory') . ': ' . __('invalid_request'));
        }
        $inv = (new Inventory())->find($invId);
        if (!$inv) {
            throw new \RuntimeException(__('inventory') . ': ' . __('no_records'));
        }
        $companyId = (int) ($inv['company_id'] ?? 0);
        if ($companyId < 1) {
            throw new \RuntimeException('Company context required for tenant-scoped create.');
        }
        if (!TenantContext::isSuperAdmin()) {
            $sessionCompanyId = TenantContext::companyId();
            if ($sessionCompanyId === null || $sessionCompanyId !== $companyId) {
                throw new \RuntimeException('Resource not found or access denied.');
            }
            TenantFkValidator::validate($data, ['inventory_id', 'warehouse_id']);
        }
        $data['company_id'] = $companyId;
        $batchModel = new InventoryBatch();
        $batchNo = strtoupper(trim((string) ($data['batch_no'] ?? '')));
        if ($batchNo === '') {
            $batchNo = strtoupper($batchModel->generateDocumentCode(
                DocumentCodeService::PREFIX_BATCH,
                'batch_no'
            ));
        }
        if (!preg_match('/^[A-Z]{2}\d{4}$/', $batchNo)) {
            throw new \RuntimeException(__('batch_id_format'));
        }
        $data['batch_no'] = $batchNo;
        $productionDate = trim((string) ($data['production_date'] ?? ''));
        $expiryDate = trim((string) ($data['expiry_date'] ?? ''));
        if ($productionDate !== '' && $expiryDate !== '' && $productionDate > $expiryDate) {
            throw new \RuntimeException(__('production_after_expiry'));
        }
        $batchId = $batchModel->create($data);
        $invId = (int) ($data['inventory_id'] ?? 0);
        $qty = (float) ($data['quantity'] ?? 0);
        if ($invId > 0 && $qty > 0) {
            $inv = (new Inventory())->find($invId);
            if ($inv) {
                (new Inventory())->update($invId, [
                    'quantity' => (float) ($inv['quantity'] ?? 0) + $qty,
                ]);
            }
        }
        return $batchId;
    }

    public function createAudit(string $auditNo, ?int $warehouseId, string $notes = ''): int
    {
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        if ($companyId < 1 && $warehouseId !== null && $warehouseId > 0) {
            $warehouse = (new Warehouse())->find($warehouseId);
            $companyId = (int) ($warehouse['company_id'] ?? 0);
        }
        if ($companyId < 1) {
            throw new \RuntimeException(function_exists('__') ? __('select_company_ops') : 'Company context required for tenant-scoped create.');
        }
        TenantContext::setCompanyId($companyId);

        return (new InventoryAudit())->create([
            'audit_no' => $auditNo,
            'warehouse_id' => $warehouseId,
            'audit_date' => date('Y-m-d'),
            'status' => 'draft',
            'notes' => $notes,
            'created_by' => SessionManager::get('rateb_user_id'),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function auditLines(int $auditId): array
    {
        return (new InventoryAudit())->query(
            'SELECT l.*, i.item_name, i.sku FROM rateb_inventory_audit_lines l
             JOIN rateb_inventory i ON i.id = l.inventory_id
             WHERE l.audit_id = :aid ORDER BY l.id',
            ['aid' => $auditId]
        );
    }

    /** @param array<int, array{inventory_id:int,counted_qty:float}> $lines */
    public function saveAuditLines(int $auditId, array $lines): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM rateb_inventory_audit_lines WHERE audit_id = :aid')->execute(['aid' => $auditId]);
        $stmt = $db->prepare(
            'INSERT INTO rateb_inventory_audit_lines (audit_id, inventory_id, system_qty, counted_qty, variance)
             VALUES (:aid, :iid, :sys, :cnt, :var)'
        );
        foreach ($lines as $line) {
            $invId = (int) ($line['inventory_id'] ?? 0);
            if ($invId < 1) {
                continue;
            }
            $inv = (new Inventory())->find($invId);
            if (!$inv) {
                continue;
            }
            $sys = (float) ($inv['quantity'] ?? 0);
            $cnt = (float) ($line['counted_qty'] ?? 0);
            $stmt->execute([
                'aid' => $auditId,
                'iid' => $invId,
                'sys' => $sys,
                'cnt' => $cnt,
                'var' => $cnt - $sys,
            ]);
        }
    }

    /** Reconcile audit: adjust inventory + stock movements for variances. */
    public function completeAudit(int $auditId): int
    {
        $audit = (new InventoryAudit())->find($auditId);
        if (!$audit || (string) ($audit['status'] ?? '') === 'completed') {
            throw new \RuntimeException('Invalid audit');
        }
        $lines = $this->auditLines($auditId);
        $adjusted = 0;
        foreach ($lines as $line) {
            $variance = (float) ($line['variance'] ?? 0);
            $invId = (int) ($line['inventory_id'] ?? 0);
            if ($invId < 1 || abs($variance) < 0.0001) {
                continue;
            }
            (new Inventory())->update($invId, ['quantity' => (float) ($line['counted_qty'] ?? 0)]);
            (new StockMovement())->create([
                'inventory_id' => $invId,
                'warehouse_id' => $audit['warehouse_id'] ?? null,
                'movement_type' => 'adjustment',
                'quantity' => abs($variance),
                'reference_type' => 'inventory_audit',
                'reference_id' => $auditId,
                'notes' => 'Audit reconciliation ' . ($audit['audit_no'] ?? ''),
                'created_by' => SessionManager::get('rateb_user_id'),
            ]);
            $adjusted++;
        }
        (new InventoryAudit())->update($auditId, ['status' => 'completed']);
        return $adjusted;
    }

    /** @return array<int, array<string, mixed>> */
    public function expiringItems(int $days = 30): array
    {
        $companyId = TenantContext::companyId();
        $sql = 'SELECT i.*, w.name AS warehouse_name FROM rateb_inventory i
                LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id
                WHERE i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)';
        $params = ['days' => $days];
        if ($companyId !== null && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND i.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY i.expiry_date ASC LIMIT 200';
        return (new Inventory())->query($sql, $params);
    }

    public function processExpiryAlerts(?int $companyId = null): int
    {
        $companyId = $companyId ?? TenantContext::companyId();
        if ($companyId === null) {
            return 0;
        }
        TenantContext::setCompanyId($companyId);
        $items = $this->expiringItems(30);
        $notifier = new NotificationService();
        $count = 0;
        foreach ($items as $item) {
            $exp = (string) ($item['expiry_date'] ?? '');
            if ($exp === '') {
                continue;
            }
            $exists = (new \Rateb\App\Models\Notification())->queryOne(
                'SELECT id FROM rateb_notifications WHERE company_id = :cid AND trigger_type = :tt AND entity_type = :et AND entity_id = :eid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1',
                ['cid' => $companyId, 'tt' => 'expiry', 'et' => 'inventory', 'eid' => (int) $item['id']]
            );
            if ($exists) {
                continue;
            }
            $notifier->notifyCompany(
                $companyId,
                __('expiry_alert'),
                __('expiry_alert_message', ['item' => (string) ($item['item_name'] ?? ''), 'date' => $exp]),
                'warning',
                'expiry',
                'inventory',
                (int) $item['id']
            );
            (new EmailAlertService())->sendExpiry($companyId, (string) ($item['item_name'] ?? ''), $exp);
            $count++;
        }
        return $count;
    }

    public function valuationReport(): array
    {
        $companyId = TenantContext::companyId();
        $sql = 'SELECT i.*, w.name AS warehouse_name, (i.quantity * i.unit_cost) AS line_value
                FROM rateb_inventory i
                LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id WHERE 1=1';
        $params = [];
        if ($companyId !== null && !TenantContext::isSuperAdmin()) {
            $sql .= ' AND i.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY line_value DESC LIMIT 500';
        $rows = (new Inventory())->query($sql, $params);
        $total = array_sum(array_map(static fn (array $r): float => (float) ($r['line_value'] ?? 0), $rows));
        return ['rows' => $rows, 'total_value' => $total];
    }

    public function nextAuditNo(): string
    {
        return (new InventoryAudit())->generateDocumentCode(
            DocumentCodeService::PREFIX_AUDIT,
            'audit_no'
        );
    }

    public function processLowStockAlerts(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        TenantContext::setCompanyId($companyId);
        $rows = (new Inventory())->query(
            'SELECT id, item_name, quantity, reorder_level FROM rateb_inventory
             WHERE company_id = :cid AND reorder_level > 0 AND quantity <= reorder_level',
            ['cid' => $companyId]
        );
        $notifier = new NotificationService();
        $count = 0;
        foreach ($rows as $row) {
            $itemId = (int) ($row['id'] ?? 0);
            $exists = (new \Rateb\App\Models\Notification())->queryOne(
                'SELECT id FROM rateb_notifications WHERE company_id = :cid AND trigger_type = :tt
                 AND entity_type = :et AND entity_id = :eid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1',
                ['cid' => $companyId, 'tt' => 'low_stock', 'et' => 'inventory', 'eid' => $itemId]
            );
            if ($exists) {
                continue;
            }
            $notifier->triggerLowStock($companyId, (string) ($row['item_name'] ?? ''), (float) ($row['quantity'] ?? 0));
            $count++;
        }
        return $count;
    }

    public function processBatchExpiryAlerts(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        TenantContext::setCompanyId($companyId);
        $rows = (new InventoryBatch())->query(
            'SELECT b.*, i.item_name FROM rateb_inventory_batches b
             JOIN rateb_inventory i ON i.id = b.inventory_id
             WHERE b.company_id = :cid AND b.expiry_date IS NOT NULL
               AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)',
            ['cid' => $companyId]
        );
        $count = 0;
        foreach ($rows as $row) {
            $batchId = (int) ($row['id'] ?? 0);
            $exists = (new \Rateb\App\Models\Notification())->queryOne(
                'SELECT id FROM rateb_notifications WHERE company_id = :cid AND trigger_type = :tt
                 AND entity_type = :et AND entity_id = :eid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1',
                ['cid' => $companyId, 'tt' => 'batch_expiry', 'et' => 'inventory_batch', 'eid' => $batchId]
            );
            if ($exists) {
                continue;
            }
            $label = (string) ($row['item_name'] ?? '') . ' [' . (string) ($row['batch_no'] ?? '') . ']';
            (new NotificationService())->notifyCompany(
                $companyId,
                __('expiry_alert'),
                __('expiry_alert_message', ['item' => $label, 'date' => (string) ($row['expiry_date'] ?? '')]),
                'warning',
                'batch_expiry',
                'inventory_batch',
                $batchId
            );
            (new EmailAlertService())->sendExpiry($companyId, $label, (string) ($row['expiry_date'] ?? ''));
            $count++;
        }
        return $count;
    }

    /** @return array<int, array<string, mixed>> */
    public function reorderSuggestions(?int $companyId = null): array
    {
        $companyId = $companyId ?? TenantContext::companyId();
        $sql = 'SELECT i.*, w.name AS warehouse_name,
                (SELECT COALESCE(SUM(ABS(m.quantity)),0) FROM rateb_stock_movements m
                 WHERE m.inventory_id = i.id AND m.movement_type IN (\'out\',\'transfer\')
                   AND m.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)) AS consumed_90d
                FROM rateb_inventory i
                LEFT JOIN rateb_warehouses w ON w.id = i.warehouse_id WHERE 1=1';
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND i.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' HAVING i.reorder_level > 0 AND (i.quantity <= i.reorder_level OR consumed_90d / 3 > i.quantity)
                  ORDER BY consumed_90d DESC LIMIT 100';
        return (new Inventory())->query($sql, $params);
    }

    public function consumeBatches(int $inventoryId, float $quantity, string $method = 'fefo'): float
    {
        $order = $method === 'fifo' ? 'b.created_at ASC' : 'b.expiry_date ASC, b.id ASC';
        $batches = (new InventoryBatch())->query(
            "SELECT * FROM rateb_inventory_batches b
             WHERE b.inventory_id = :iid AND b.quantity > 0
             ORDER BY {$order}",
            ['iid' => $inventoryId]
        );
        $remaining = $quantity;
        $db = Database::connection();
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $batchQty = (float) ($batch['quantity'] ?? 0);
            $take = min($batchQty, $remaining);
            $db->prepare('UPDATE rateb_inventory_batches SET quantity = quantity - :q WHERE id = :id')
                ->execute(['q' => $take, 'id' => (int) $batch['id']]);
            $remaining -= $take;
        }
        return $quantity - $remaining;
    }

    /** @param array<string, mixed> $data */
    public function createTransfer(array $data): int
    {
        $cid = TenantGuard::requireCompanyId();
        $src = (int) ($data['source_warehouse_id'] ?? 0);
        $dst = (int) ($data['destination_warehouse_id'] ?? 0);
        $invId = (int) ($data['inventory_id'] ?? 0);
        $qty = (float) ($data['quantity'] ?? 0);
        if ($src < 1 || $dst < 1 || $src === $dst || $invId < 1 || $qty <= 0) {
            throw new \InvalidArgumentException('Invalid transfer');
        }
        TenantFkValidator::validate($data, ['inventory_id', 'source_warehouse_id', 'destination_warehouse_id']);
        $db = Database::connection();
        $count = (int) ($db->query('SELECT COUNT(*) AS c FROM rateb_warehouse_transfers WHERE company_id = ' . $cid)->fetch()['c'] ?? 0);
        $transferNo = 'TRF-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
        $db->prepare(
            'INSERT INTO rateb_warehouse_transfers
             (company_id, transfer_no, source_warehouse_id, destination_warehouse_id, inventory_id, quantity, status, notes, created_by)
             VALUES (:cid, :no, :src, :dst, :inv, :qty, :st, :notes, :uid)'
        )->execute([
            'cid' => $cid,
            'no' => $transferNo,
            'src' => $src,
            'dst' => $dst,
            'inv' => $invId,
            'qty' => $qty,
            'st' => 'pending',
            'notes' => $data['notes'] ?? null,
            'uid' => SessionManager::get('rateb_user_id'),
        ]);
        return (int) $db->lastInsertId();
    }

    public function completeTransfer(int $transferId): bool
    {
        $row = TenantGuard::assertWarehouseTransfer($transferId);
        if ((string) ($row['status'] ?? '') !== 'approved') {
            return false;
        }
        $invId = (int) $row['inventory_id'];
        $qty = (float) $row['quantity'];
        $src = (int) $row['source_warehouse_id'];
        $dst = (int) $row['destination_warehouse_id'];
        $companyId = (int) $row['company_id'];
        (new StockMovementService())->record([
            'inventory_id' => $invId,
            'warehouse_id' => $src,
            'movement_type' => 'transfer',
            'quantity' => $qty,
            'reference_type' => 'warehouse_transfer',
            'reference_id' => $transferId,
            'notes' => 'Transfer out to WH#' . $dst,
        ]);
        (new StockMovementService())->record([
            'inventory_id' => $invId,
            'warehouse_id' => $dst,
            'movement_type' => 'in',
            'quantity' => $qty,
            'reference_type' => 'warehouse_transfer',
            'reference_id' => $transferId,
            'notes' => 'Transfer in from WH#' . $src,
        ]);
        $this->consumeBatches($invId, $qty, 'fefo');
        Database::connection()->prepare(
            'UPDATE rateb_warehouse_transfers SET status = :st, completed_at = NOW(), approved_by = :uid WHERE id = :id'
        )->execute(['st' => 'completed', 'uid' => SessionManager::get('rateb_user_id'), 'id' => $transferId]);
        (new AuditService())->log('transfer_complete', 'warehouse_transfer', $transferId, ['company_id' => $companyId]);
        return true;
    }

    public function approveTransfer(int $transferId): bool
    {
        $row = TenantGuard::assertWarehouseTransfer($transferId);
        if ((string) ($row['status'] ?? '') !== 'pending') {
            return false;
        }
        Database::connection()->prepare(
            'UPDATE rateb_warehouse_transfers SET status = :st, approved_by = :uid WHERE id = :id'
        )->execute(['st' => 'approved', 'uid' => SessionManager::get('rateb_user_id'), 'id' => $transferId]);
        return $this->completeTransfer($transferId);
    }
}
