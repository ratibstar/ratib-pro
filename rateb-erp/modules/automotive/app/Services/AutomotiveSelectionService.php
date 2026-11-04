<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use PDO;
use Rateb\App\Core\Database;

/** Company selections and mappings. Never writes stock, cost, price, or SKU. */
final class AutomotiveSelectionService
{
    public function __construct(private ?PDO $pdo = null, private ?AutomotiveGate $gate = null)
    {
    }

    /** @return list<array<string, mixed>> */
    public function selections(): array
    {
        $this->gate()->assert('automotive.selection.view');
        $stmt = $this->db()->prepare(
            'SELECT id, entity_type, entity_id FROM rateb_auto_company_selections WHERE company_id = :cid ORDER BY id'
        );
        $stmt->execute(['cid' => $this->gate()->companyId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function select(string $entityType, int $entityId): int
    {
        $this->gate()->assert('automotive.selection.manage');
        if (!in_array($entityType, ['market', 'brand', 'model', 'generation', 'vehicle', 'part'], true) || $entityId < 1) {
            throw new \InvalidArgumentException('Selection target is not allowed.');
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_company_selections (company_id, entity_type, entity_id) VALUES (:cid, :type, :id)'
        );
        $stmt->execute(['cid' => $this->gate()->companyId(), 'type' => $entityType, 'id' => $entityId]);
        return (int) $this->db()->lastInsertId();
    }

    public function remove(int $selectionId): void
    {
        $this->gate()->assert('automotive.selection.manage');
        $stmt = $this->db()->prepare('DELETE FROM rateb_auto_company_selections WHERE id = :id AND company_id = :cid');
        $stmt->execute(['id' => $selectionId, 'cid' => $this->gate()->companyId()]);
        if ($stmt->rowCount() < 1) {
            throw new \RuntimeException('forbidden');
        }
    }

    public function linkInventory(int $globalPartId, int $inventoryId): int
    {
        $this->gate()->assert('automotive.inventory_link.manage');
        $before = $this->inventorySnapshot($inventoryId);
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_company_links (company_id, global_part_id, inventory_id) VALUES (:cid, :part, :item)'
        );
        $stmt->execute([
            'cid' => $this->gate()->companyId(),
            'part' => $globalPartId,
            'item' => $inventoryId,
        ]);
        $after = $this->inventorySnapshot($inventoryId);
        if ($before !== $after) {
            throw new \RuntimeException('inventory_master_changed');
        }
        return (int) $this->db()->lastInsertId();
    }

    public function mapSupplier(int $globalPartId, int $supplierId, string $supplierNumber): int
    {
        $this->gate()->assert('automotive.supplier_map.manage');
        $supplierNumber = strtoupper(trim($supplierNumber));
        if ($supplierNumber === '') {
            throw new \InvalidArgumentException('Supplier number is required.');
        }
        $before = $this->supplierName($supplierId);
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_company_supplier_maps (company_id, global_part_id, supplier_id, supplier_number)
             VALUES (:cid, :part, :supplier, :number)'
        );
        $stmt->execute([
            'cid' => $this->gate()->companyId(),
            'part' => $globalPartId,
            'supplier' => $supplierId,
            'number' => $supplierNumber,
        ]);
        if ($this->supplierName($supplierId) !== $before) {
            throw new \RuntimeException('supplier_master_changed');
        }
        return (int) $this->db()->lastInsertId();
    }

    /** @return array{quantity:string,sku:string,unit_cost:string,sell_price:string,item_code:string} */
    private function inventorySnapshot(int $inventoryId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT quantity, sku, unit_cost, sell_price, item_code FROM rateb_inventory WHERE id = :id AND company_id = :cid'
        );
        $stmt->execute(['id' => $inventoryId, 'cid' => $this->gate()->companyId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \InvalidArgumentException('Inventory record was not found in this company.');
        }
        return [
            'quantity' => (string) $row['quantity'],
            'sku' => (string) ($row['sku'] ?? ''),
            'unit_cost' => (string) ($row['unit_cost'] ?? ''),
            'sell_price' => (string) ($row['sell_price'] ?? ''),
            'item_code' => (string) ($row['item_code'] ?? ''),
        ];
    }

    private function supplierName(int $supplierId): string
    {
        $stmt = $this->db()->prepare('SELECT name FROM rateb_suppliers WHERE id = :id AND company_id = :cid');
        $stmt->execute(['id' => $supplierId, 'cid' => $this->gate()->companyId()]);
        $name = $stmt->fetchColumn();
        if ($name === false) {
            throw new \InvalidArgumentException('Supplier was not found in this company.');
        }
        return (string) $name;
    }

    private function gate(): AutomotiveGate
    {
        return $this->gate ?? new AutomotiveGate();
    }

    private function db(): PDO
    {
        return $this->pdo ?? Database::connection();
    }
}
