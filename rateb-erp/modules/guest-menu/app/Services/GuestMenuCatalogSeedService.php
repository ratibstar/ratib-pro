<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use PDO;

/** Seed demo F&B inventory when guest menu has no products. */
final class GuestMenuCatalogSeedService
{
    /** @return array{ok:bool, created:int, message?:string} */
    public function seedDemoForCompany(int $companyId): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'created' => 0, 'message' => 'invalid_company'];
        }

        $warehouseId = $this->resolveWarehouseId($companyId);
        if ($warehouseId < 1) {
            return ['ok' => false, 'created' => 0, 'message' => 'warehouse_required'];
        }

        TenantContext::setCompanyId($companyId);
        $inventory = new Inventory();

        $items = [
            ['sku' => 'GM-BURGER', 'name' => 'برجر كلاسيك', 'name_en' => 'Classic Burger', 'price' => 28.0],
            ['sku' => 'GM-FRIES', 'name' => 'بطاطس مقلية', 'name_en' => 'French Fries', 'price' => 12.0],
            ['sku' => 'GM-COLA', 'name' => 'كولا', 'name_en' => 'Cola', 'price' => 6.0],
            ['sku' => 'GM-SALAD', 'name' => 'سلطة خضراء', 'name_en' => 'Green Salad', 'price' => 18.0],
            ['sku' => 'GM-JUICE', 'name' => 'عصير برتقال', 'name_en' => 'Orange Juice', 'price' => 10.0],
        ];

        $created = 0;
        foreach ($items as $item) {
            if ($this->skuExists($companyId, $item['sku'])) {
                continue;
            }
            $inventory->create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'item_name' => $item['name'],
                'sku' => $item['sku'],
                'category' => 'menu',
                'quantity' => 500,
                'unit' => 'unit',
                'unit_cost' => $item['price'],
                'status' => 'active',
                'notes' => 'Guest menu demo seed',
            ]);
            ++$created;
        }

        return ['ok' => true, 'created' => $created];
    }

    private function resolveWarehouseId(int $companyId): int
    {
        $erp = \Rateb\App\Core\Database::connection();
        $stmt = $erp->prepare(
            'SELECT id FROM rateb_warehouses WHERE company_id = :cid AND status = \'active\' ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return (int) ($row['id'] ?? 0);
        }

        $erp->prepare(
            'INSERT INTO rateb_warehouses (company_id, name, code, location, status, created_at)
             VALUES (:cid, :name, :code, :loc, \'active\', NOW())'
        )->execute([
            'cid' => $companyId,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'loc' => 'Main',
        ]);

        return (int) $erp->lastInsertId();
    }

    private function skuExists(int $companyId, string $sku): bool
    {
        $stmt = \Rateb\App\Core\Database::connection()->prepare(
            'SELECT id FROM rateb_inventory WHERE company_id = :cid AND sku = :sku LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sku' => $sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row);
    }
}
