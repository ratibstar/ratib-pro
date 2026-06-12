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
        }
        $barcode = 'RTB' . $companyId . str_pad((string) $inventoryId, 8, '0', STR_PAD_LEFT);
        $qrPayload = json_encode([
            'type' => 'rateb_inventory',
            'company_id' => $companyId,
            'inventory_id' => $inventoryId,
            'sku' => $sku,
            'barcode' => $barcode,
        ], JSON_UNESCAPED_UNICODE);
        (new Inventory())->update($inventoryId, [
            'sku' => $sku,
            'barcode' => $barcode,
            'qr_code' => $qrPayload,
        ]);
        return ['barcode' => $barcode, 'qr_code' => $qrPayload, 'sku' => $sku];
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
        $batchId = (new InventoryBatch())->create($data);
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
        $companyId = TenantContext::companyId() ?? 0;
        $count = (int) ((new InventoryAudit())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_inventory_audits WHERE company_id = :cid',
            ['cid' => $companyId]
        )['c'] ?? 0);
        return 'AUD-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
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
}
