<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\ProductCategory;
use Rateb\App\Models\Warehouse;
use RuntimeException;

final class PosDemoDataSetupService
{
    /** @return array<string, int|bool|string> */
    public function run(int $companyId): array
    {
        if ($companyId < 1) {
            throw new RuntimeException('Invalid company context.');
        }

        TenantContext::setCompanyId($companyId);

        $branchId = $this->resolveBranchId($companyId);
        if ($branchId < 1) {
            throw new RuntimeException('No branch available for this company.');
        }

        $warehouseCreated = false;
        $warehouseId = $this->resolveWarehouseId($companyId, $branchId);
        if ($warehouseId < 1) {
            $warehouseId = (new Warehouse())->create([
                'name' => 'POS Demo Warehouse',
                'code' => 'POS-DEMO',
                'location' => 'Auto seeded by POS setup',
                'manager_name' => 'System',
                'status' => 'active',
                'branch_id' => $branchId,
            ]);
            $warehouseCreated = $warehouseId > 0;
        }

        if ($warehouseId < 1) {
            throw new RuntimeException('Unable to create or resolve warehouse.');
        }

        $categoryId = $this->resolveOrCreateCategory($companyId);
        $seed = $this->demoProducts();
        $inventory = new Inventory();
        $created = 0;
        $updated = 0;

        foreach ($seed as $row) {
            $itemCode = (string) ($row['item_code'] ?? '');
            if ($itemCode === '') {
                continue;
            }
            $existing = $inventory->queryOne(
                'SELECT id, quantity, unit_cost FROM rateb_inventory WHERE company_id = :cid AND item_code = :code LIMIT 1',
                ['cid' => $companyId, 'code' => $itemCode]
            );

            if ($existing) {
                $id = (int) ($existing['id'] ?? 0);
                $inventory->update($id, [
                    'quantity' => 999,
                    'status' => 'active',
                    'warehouse_id' => $warehouseId,
                    'branch_id' => $branchId,
                    'unit_cost' => (float) ($row['unit_price'] ?? 0),
                    'category_id' => $categoryId,
                ]);
                $updated++;
                continue;
            }

            $newId = $inventory->create([
                'warehouse_id' => $warehouseId,
                'branch_id' => $branchId,
                'item_code' => $itemCode,
                'item_name' => (string) ($row['item_name'] ?? ''),
                'sku' => $itemCode,
                'barcode' => $itemCode,
                'category_id' => $categoryId,
                'quantity' => 999,
                'unit' => 'pcs',
                'unit_cost' => (float) ($row['unit_price'] ?? 0),
                'status' => 'active',
                'notes' => 'Auto-seeded POS demo item',
            ]);
            if ($newId > 0) {
                $created++;
            }
        }

        return [
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'warehouse_created' => $warehouseCreated,
            'category_id' => $categoryId,
            'products_created' => $created,
            'products_updated' => $updated,
        ];
    }

    private function resolveBranchId(int $companyId): int
    {
        $inventory = new Inventory();
        $row = $inventory->queryOne(
            'SELECT id FROM rateb_branches WHERE company_id = :cid ORDER BY is_main DESC, id ASC LIMIT 1',
            ['cid' => $companyId]
        );

        return (int) ($row['id'] ?? 0);
    }

    private function resolveWarehouseId(int $companyId, int $branchId): int
    {
        $inventory = new Inventory();
        $row = $inventory->queryOne(
            'SELECT id FROM rateb_warehouses WHERE company_id = :cid AND branch_id = :bid ORDER BY id ASC LIMIT 1',
            ['cid' => $companyId, 'bid' => $branchId]
        );

        return (int) ($row['id'] ?? 0);
    }

    private function resolveOrCreateCategory(int $companyId): int
    {
        $category = new ProductCategory();
        $row = $category->queryOne(
            'SELECT id FROM rateb_product_categories WHERE company_id = :cid AND code = :code LIMIT 1',
            ['cid' => $companyId, 'code' => 'POS-DEMO']
        );
        if ($row) {
            return (int) ($row['id'] ?? 0);
        }

        return $category->create([
            'code' => 'POS-DEMO',
            'name' => 'POS Demo',
            'name_ar' => 'POS تجريبي',
            'description_en' => 'Auto-seeded category for POS demo data',
            'description_ar' => 'تصنيف منشأ تلقائيا لبيانات تجربة نقطة البيع',
            'sort_order' => 999,
            'is_active' => 1,
            'is_visible' => 1,
        ]);
    }

    /** @return array<int, array<string, string|float>> */
    private function demoProducts(): array
    {
        return [
            ['item_code' => 'DEMO-ESP', 'item_name' => 'Espresso (Demo)', 'unit_price' => 12.00],
            ['item_code' => 'DEMO-LAT', 'item_name' => 'Latte (Demo)', 'unit_price' => 16.00],
            ['item_code' => 'DEMO-CRO', 'item_name' => 'Croissant (Demo)', 'unit_price' => 9.50],
            ['item_code' => 'DEMO-SAN', 'item_name' => 'Chicken Sandwich (Demo)', 'unit_price' => 22.00],
            ['item_code' => 'DEMO-WAT', 'item_name' => 'Water 330ml (Demo)', 'unit_price' => 3.00],
        ];
    }
}
