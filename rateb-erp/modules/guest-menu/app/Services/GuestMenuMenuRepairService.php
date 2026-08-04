<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\ProductCategory;
use PDO;

/**
 * One-shot repair: rewrite company RC- and GM- inventory names/categories
 * from authoritative UTF-8 PHP seed (never copy platform DB mojibake).
 */
final class GuestMenuMenuRepairService
{
    /**
     * @return array{
     *   ok:bool,
     *   repaired:int,
     *   imported:int,
     *   skipped:int,
     *   seed_ok:bool,
     *   seed_count:int,
     *   message?:string
     * }
     */
    public function repairCompany(int $companyId, string $pack = 'all'): array
    {
        if ($companyId < 1) {
            return [
                'ok' => false,
                'repaired' => 0,
                'imported' => 0,
                'skipped' => 0,
                'seed_ok' => false,
                'seed_count' => 0,
                'message' => 'invalid_company',
            ];
        }

        $this->ensureErpUtf8();

        // Platform seed is best-effort only — inventory rewrite uses PHP seed map.
        $seedOk = false;
        $seedCount = 0;
        try {
            $seed = (new GuestMenuPlatformCatalogSeedRunner())->run();
            $seedOk = !empty($seed['ok']);
            $seedCount = (int) ($seed['product_count'] ?? 0);
        } catch (\Throwable) {
            $seedOk = false;
        }

        $packs = PlatformRetailCatalogSeedData::industryPacks();
        if (!isset($packs[$pack])) {
            $pack = 'all';
        }
        $catFilter = $packs[$pack]['cats'] ?? null;

        $skuMap = PlatformRetailCatalogSeedData::authoritativeSkuMap();
        if ($skuMap === []) {
            return [
                'ok' => false,
                'repaired' => 0,
                'imported' => 0,
                'skipped' => 0,
                'seed_ok' => $seedOk,
                'seed_count' => $seedCount,
                'message' => 'seed_map_empty',
            ];
        }

        if (is_array($catFilter) && $catFilter !== []) {
            $allowed = array_fill_keys($catFilter, true);
            $skuMap = array_filter(
                $skuMap,
                static fn (array $row): bool => isset($allowed[$row['category_slug'] ?? ''])
            );
        }

        $warehouseId = $this->resolveWarehouseId($companyId);
        if ($warehouseId < 1) {
            return [
                'ok' => false,
                'repaired' => 0,
                'imported' => 0,
                'skipped' => 0,
                'seed_ok' => $seedOk,
                'seed_count' => $seedCount,
                'message' => 'warehouse_required',
            ];
        }

        TenantContext::setCompanyId($companyId);
        $inventory = new Inventory();
        $categoryCache = [];
        $repaired = 0;
        $imported = 0;
        $skipped = 0;

        $nameBySku = PlatformRetailCatalogSeedData::nameBySku();
        $fullMap = PlatformRetailCatalogSeedData::authoritativeSkuMap();

        // 1) Rewrite every existing RC-/GM- row from PHP seed (never platform translations).
        $existing = $this->listCompanySeedInventory($companyId);
        foreach ($existing as $inv) {
            $sku = (string) ($inv['sku'] ?? '');
            $seedRow = $fullMap[$sku] ?? null;
            $name = '';
            $categorySlug = '';
            $categoryLabelAr = '';
            $categoryLabelEn = '';
            $price = 0.0;

            if ($seedRow !== null) {
                $name = $this->readableSeedName($seedRow);
                $categorySlug = (string) ($seedRow['category_slug'] ?? '');
                $categoryLabelAr = (string) ($seedRow['category_name_ar'] ?? '');
                $categoryLabelEn = (string) ($seedRow['category_name_en'] ?? '');
                $price = (float) ($seedRow['price'] ?? 0);
            } elseif (isset($nameBySku[$sku])) {
                // GM-* demo SKUs — name only.
                $name = (string) $nameBySku[$sku];
                $categorySlug = 'retail-restaurants';
                $categoryLabelAr = 'مطاعم';
                $categoryLabelEn = 'Restaurants';
            } else {
                ++$skipped;
                continue;
            }

            if ($name === '' || $this->isCorruptedName($name)) {
                ++$skipped;
                continue;
            }
            $categoryId = $this->resolveCompanyCategoryId(
                $companyId,
                $categorySlug,
                $categoryLabelAr,
                $categoryLabelEn,
                $categoryCache
            );
            $categoryLabel = $categoryLabelAr !== '' ? $categoryLabelAr : $categoryLabelEn;
            if ($this->forceUpdateInventory(
                $companyId,
                (int) $inv['id'],
                $name,
                $categoryId,
                $categoryLabel,
                $price
            )) {
                ++$repaired;
            } else {
                ++$skipped;
            }
        }

        // 2) Ensure pack SKUs exist (create missing RC-*).
        foreach ($skuMap as $seedRow) {
            $sku = (string) $seedRow['sku'];
            $existingId = $this->findInventoryId($companyId, $sku);
            if ($existingId > 0) {
                continue;
            }
            $name = $this->readableSeedName($seedRow);
            if ($name === '' || $this->isCorruptedName($name)) {
                ++$skipped;
                continue;
            }
            $categoryId = $this->resolveCompanyCategoryId(
                $companyId,
                (string) $seedRow['category_slug'],
                (string) $seedRow['category_name_ar'],
                (string) $seedRow['category_name_en'],
                $categoryCache
            );
            $categoryLabel = (string) $seedRow['category_name_ar'];
            $barcode = trim((string) ($seedRow['barcode'] ?? '')) ?: null;
            $price = max(0.0, (float) ($seedRow['price'] ?? 0));
            try {
                $inventory->create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'item_name' => $name,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'category' => $categoryLabel,
                    'category_id' => $categoryId > 0 ? $categoryId : null,
                    'quantity' => 999,
                    'unit' => 'unit',
                    'unit_cost' => $price > 0 ? $price : 1.0,
                    'status' => 'active',
                ]);
                // If create stored ?? due to charset, force UNHEX rewrite.
                $newId = $this->findInventoryId($companyId, $sku);
                if ($newId > 0) {
                    $this->forceUpdateInventory($companyId, $newId, $name, $categoryId, $categoryLabel, $price);
                }
                ++$imported;
            } catch (\Throwable) {
                ++$skipped;
            }
        }

        return [
            'ok' => true,
            'repaired' => $repaired,
            'imported' => $imported,
            'skipped' => $skipped,
            'seed_ok' => $seedOk,
            'seed_count' => $seedCount,
        ];
    }

    private function ensureErpUtf8(): void
    {
        try {
            $pdo = Database::connection();
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('SET CHARACTER SET utf8mb4');
            $pdo->exec('SET character_set_connection = utf8mb4');
        } catch (\Throwable) {
            // ignore
        }
    }

    /** @param array{name_ar:string, name_en:string, sku:string} $seedRow */
    private function readableSeedName(array $seedRow): string
    {
        $ar = trim((string) ($seedRow['name_ar'] ?? ''));
        $en = trim((string) ($seedRow['name_en'] ?? ''));
        if ($ar !== '' && !$this->isCorruptedName($ar)) {
            return $ar;
        }
        if ($en !== '' && !$this->isCorruptedName($en)) {
            return $en;
        }

        return trim((string) ($seedRow['sku'] ?? ''));
    }

    private function isCorruptedName(string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return true;
        }
        if (str_contains($name, '??') || str_contains($name, "\u{FFFD}")) {
            return true;
        }
        if (preg_match('/^\?+$/u', $name) === 1) {
            return true;
        }
        $q = substr_count($name, '?');
        if ($q >= 2 && $q >= (int) floor(mb_strlen($name, 'UTF-8') * 0.5)) {
            return true;
        }

        return false;
    }

    /** @return list<array{id:int|string, sku:string, item_name?:string}> */
    private function listCompanySeedInventory(int $companyId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, sku, item_name, category_id, category
             FROM rateb_inventory
             WHERE company_id = :cid
               AND (sku LIKE \'RC-%\' OR sku LIKE \'GM-%\')'
        );
        $stmt->execute(['cid' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findInventoryId(int $companyId, string $sku): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM rateb_inventory WHERE company_id = :cid AND sku = :sku LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sku' => $sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
    }

    private function forceUpdateInventory(
        int $companyId,
        int $inventoryId,
        string $name,
        int $categoryId,
        string $categoryLabel,
        float $price
    ): bool {
        if ($companyId < 1 || $inventoryId < 1 || $name === '' || $this->isCorruptedName($name)) {
            return false;
        }

        $pdo = Database::connection();
        $cat = $categoryLabel !== '' ? $categoryLabel : 'menu';

        // Prefer UNHEX write — immune to connection charset mangling Arabic → ???.
        $hexName = bin2hex($name);
        $hexCat = bin2hex($cat);
        $sets = [
            'item_name = CONVERT(UNHEX(:hex_name) USING utf8mb4)',
            'category = CONVERT(UNHEX(:hex_cat) USING utf8mb4)',
        ];
        $params = [
            'id' => $inventoryId,
            'cid' => $companyId,
            'hex_name' => $hexName,
            'hex_cat' => $hexCat,
        ];
        if ($categoryId > 0) {
            $sets[] = 'category_id = :cat_id';
            $params['cat_id'] = $categoryId;
        }
        if ($price > 0) {
            $sets[] = 'unit_cost = :price';
            $params['price'] = $price;
        }

        try {
            $pdo->prepare(
                'UPDATE rateb_inventory SET ' . implode(', ', $sets)
                . ' WHERE id = :id AND company_id = :cid'
            )->execute($params);
        } catch (\Throwable) {
            // Fallback: plain parameterized UTF-8 bind.
            $sets2 = ['item_name = :name', 'category = :cat'];
            $params2 = [
                'id' => $inventoryId,
                'cid' => $companyId,
                'name' => $name,
                'cat' => $cat,
            ];
            if ($categoryId > 0) {
                $sets2[] = 'category_id = :cat_id';
                $params2['cat_id'] = $categoryId;
            }
            if ($price > 0) {
                $sets2[] = 'unit_cost = :price';
                $params2['price'] = $price;
            }
            try {
                $pdo->prepare(
                    'UPDATE rateb_inventory SET ' . implode(', ', $sets2)
                    . ' WHERE id = :id AND company_id = :cid'
                )->execute($params2);
            } catch (\Throwable) {
                return false;
            }
        }

        // Verify write stuck as Arabic (not ??).
        try {
            $check = $pdo->prepare(
                'SELECT item_name FROM rateb_inventory WHERE id = :id AND company_id = :cid LIMIT 1'
            );
            $check->execute(['id' => $inventoryId, 'cid' => $companyId]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            $stored = (string) ($row['item_name'] ?? '');
            if ($this->isCorruptedName($stored)) {
                return false;
            }
        } catch (\Throwable) {
            // assume ok if verify unavailable
        }

        return true;
    }

    /**
     * @param array<string, int> $cache
     */
    private function resolveCompanyCategoryId(
        int $companyId,
        string $slug,
        string $nameAr,
        string $nameEn,
        array &$cache
    ): int {
        if ($slug === '') {
            return (new \Rateb\App\Services\ProductCategoryService())->ensureDefaultCategory($companyId);
        }
        if (isset($cache[$slug])) {
            return $cache[$slug];
        }

        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', str_replace('retail-', '', $slug)) ?? '');
        $code = substr($code !== '' ? $code : 'GEN', 0, 32);

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, name, name_ar FROM rateb_product_categories
             WHERE company_id = :cid AND code = :code LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $id = (int) ($row['id'] ?? 0);
            $desired = $nameAr !== '' ? $nameAr : ($nameEn !== '' ? $nameEn : $code);
            if ($desired !== '' && (
                (string) ($row['name'] ?? '') !== $desired
                || (string) ($row['name_ar'] ?? '') !== $desired
                || str_contains((string) ($row['name'] ?? ''), '??')
                || str_contains((string) ($row['name_ar'] ?? ''), '??')
            )) {
                $hex = bin2hex($desired);
                try {
                    $pdo->prepare(
                        'UPDATE rateb_product_categories
                         SET name = CONVERT(UNHEX(:h) USING utf8mb4),
                             name_ar = CONVERT(UNHEX(:h2) USING utf8mb4)
                         WHERE id = :id AND company_id = :cid'
                    )->execute(['h' => $hex, 'h2' => $hex, 'id' => $id, 'cid' => $companyId]);
                } catch (\Throwable) {
                    $pdo->prepare(
                        'UPDATE rateb_product_categories SET name = :n, name_ar = :na WHERE id = :id AND company_id = :cid'
                    )->execute(['n' => $desired, 'na' => $desired, 'id' => $id, 'cid' => $companyId]);
                }
            }
            $cache[$slug] = $id;

            return $id;
        }

        $prev = TenantContext::companyId();
        TenantContext::setCompanyId($companyId);
        try {
            $displayAr = $nameAr !== '' ? $nameAr : ($nameEn !== '' ? $nameEn : $code);
            $id = (new ProductCategory())->create([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $displayAr,
                'name_ar' => $displayAr,
                'is_active' => 1,
                'is_visible' => 1,
                'sort_order' => 10,
            ]);
            // Harden category Arabic via UNHEX if create mangled it.
            if ((int) $id > 0 && $displayAr !== '') {
                $hex = bin2hex($displayAr);
                try {
                    $pdo->prepare(
                        'UPDATE rateb_product_categories
                         SET name = CONVERT(UNHEX(:h) USING utf8mb4),
                             name_ar = CONVERT(UNHEX(:h2) USING utf8mb4)
                         WHERE id = :id AND company_id = :cid'
                    )->execute(['h' => $hex, 'h2' => $hex, 'id' => (int) $id, 'cid' => $companyId]);
                } catch (\Throwable) {
                    // ignore
                }
            }
        } finally {
            TenantContext::setCompanyId($prev);
        }

        $cache[$slug] = (int) $id;

        return (int) $id;
    }

    private function resolveWarehouseId(int $companyId): int
    {
        $erp = Database::connection();
        $stmt = $erp->prepare(
            'SELECT id FROM rateb_warehouses WHERE company_id = :cid AND status = \'active\'
             ORDER BY id ASC LIMIT 1'
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
}
