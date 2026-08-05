<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\ProductCategory;
use PDO;
use PDOException;

/** Copy published platform catalog SKUs into tenant inventory (for guest menu / POS). */
final class GuestMenuPlatformImportService
{
    /**
     * Delete previously imported / demo menu SKUs (RC-* platform + GM-* demo) for a company.
     */
    public function deleteImportedForCompany(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'DELETE FROM rateb_inventory
             WHERE company_id = :cid
               AND (sku LIKE \'RC-%\' OR sku LIKE \'GM-%\')'
        );
        $stmt->execute(['cid' => $companyId]);

        return (int) $stmt->rowCount();
    }

    /**
     * Delete RC- / GM- rows that do not belong to the given industry pack.
     * Leaves manual (non-seed) inventory untouched.
     */
    public function deleteOutsidePackForCompany(int $companyId, string $pack): int
    {
        if ($companyId < 1) {
            return 0;
        }
        $pack = PlatformRetailCatalogSeedData::normalizePack($pack);
        if ($pack === 'all') {
            return 0;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, sku FROM rateb_inventory
             WHERE company_id = :cid
               AND (sku LIKE \'RC-%\' OR sku LIKE \'GM-%\')'
        );
        $stmt->execute(['cid' => $companyId]);
        $ids = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '' || PlatformRetailCatalogSeedData::skuBelongsToPack($sku, $pack)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return 0;
        }
        $deleted = 0;
        foreach (array_chunk($ids, 100) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $del = $pdo->prepare(
                'DELETE FROM rateb_inventory WHERE company_id = ? AND id IN (' . $placeholders . ')'
            );
            $del->execute(array_merge([$companyId], $chunk));
            $deleted += (int) $del->rowCount();
        }

        return $deleted;
    }

    /**
     * @return array{ok:bool, imported:int, skipped:int, updated:int, message?:string}
     */
    public function importToCompany(int $companyId, int $limit = 50, string $pack = 'all'): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'updated' => 0, 'message' => 'invalid_company'];
        }

        $packs = PlatformRetailCatalogSeedData::industryPacks();
        if (!isset($packs[$pack])) {
            $pack = 'all';
        }
        $catFilter = $packs[$pack]['cats'] ?? null;

        $platform = PlatformCatalogConnection::connect();
        if ($platform === null) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'updated' => 0, 'message' => 'platform_db_unavailable'];
        }

        $warehouseId = $this->resolveWarehouseId($companyId);
        if ($warehouseId < 1) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'updated' => 0, 'message' => 'warehouse_required'];
        }

        $limit = max(1, min(300, $limit));
        $rows = $this->fetchPlatformProducts($platform, $limit, $catFilter);
        // Always overlay PHP seed names/categories — platform DB may still hold ??.
        $rows = $this->overlayAuthoritativeSeed($rows, $catFilter, $limit);
        if ($rows === []) {
            return ['ok' => true, 'imported' => 0, 'skipped' => 0, 'updated' => 0, 'message' => 'no_platform_products'];
        }

        try {
            Database::connection()->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable) {
            // ignore
        }

        TenantContext::setCompanyId($companyId);
        $inventory = new Inventory();
        $imported = 0;
        $skipped = 0;
        $updated = 0;
        $categoryCache = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $nameAr = trim((string) ($row['name_ar'] ?? ''));
            $nameEn = trim((string) ($row['name_en'] ?? ''));
            $name = $this->preferReadableName($nameAr, $nameEn, $sku);
            if ($sku === '' || $name === '' || $this->isCorruptedName($name)) {
                ++$skipped;
                continue;
            }

            $catSlug = trim((string) ($row['category_slug'] ?? ''));
            $catNameAr = trim((string) ($row['category_name_ar'] ?? ''));
            $catNameEn = trim((string) ($row['category_name_en'] ?? ''));
            if ($this->isCorruptedName($catNameAr)) {
                $catNameAr = '';
            }
            if ($this->isCorruptedName($catNameEn)) {
                $catNameEn = '';
            }
            $categoryId = $this->resolveCompanyCategoryId(
                $companyId,
                $catSlug,
                $catNameAr !== '' ? $catNameAr : 'عام',
                $catNameEn !== '' ? $catNameEn : 'General',
                $categoryCache
            );

            $price = max(0.0, (float) ($row['price'] ?? 0));
            $barcode = trim((string) ($row['primary_barcode'] ?? '')) ?: null;
            $categoryLabel = $catNameAr !== '' ? $catNameAr : ($catNameEn !== '' ? $catNameEn : 'menu');

            $existingId = $this->findInventoryId($companyId, $sku);
            if ($existingId > 0) {
                // Always UPSERT RC-*/existing SKUs — force name + category_id rewrite.
                if ($this->repairInventoryRow($existingId, $name, $categoryId, $categoryLabel, $price, true)) {
                    ++$updated;
                } else {
                    ++$skipped;
                }
                continue;
            }

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
                ++$imported;
            } catch (\Throwable) {
                ++$skipped;
            }
        }

        return ['ok' => true, 'imported' => $imported, 'skipped' => $skipped, 'updated' => $updated];
    }

    /** @param list<string>|null $catFilter */
    private function fetchPlatformProducts(PDO $platform, int $limit, ?array $catFilter): array
    {
        $catSql = '';
        if (is_array($catFilter) && $catFilter !== []) {
            $quoted = array_map(static fn (string $s): string => $platform->quote($s), $catFilter);
            $catSql = ' AND c.slug IN (' . implode(',', $quoted) . ')';
        }

        $sql = 'SELECT p.sku, p.primary_barcode,
                       COALESCE(NULLIF(pt_ar.name, \'\'), pt_en.name, p.sku) AS name_ar,
                       COALESCE(NULLIF(pt_en.name, \'\'), pt_ar.name, p.sku) AS name_en,
                       c.slug AS category_slug,
                       COALESCE(NULLIF(ct_ar.name, \'\'), ct_en.name, c.slug) AS category_name_ar,
                       COALESCE(NULLIF(ct_en.name, \'\'), ct_ar.name, c.slug) AS category_name_en,
                       (
                           SELECT COALESCE(pp.default_price, pp.msrp, pp.cost)
                           FROM product_prices pp
                           WHERE pp.product_id = p.id
                             AND pp.deleted_at IS NULL
                             AND pp.is_active = 1
                           ORDER BY (pp.currency_code = \'SAR\') DESC, pp.id ASC
                           LIMIT 1
                       ) AS price
                FROM products p
                LEFT JOIN categories c
                    ON c.id = p.category_id AND c.deleted_at IS NULL
                LEFT JOIN category_translations ct_ar
                    ON ct_ar.category_id = c.id AND ct_ar.language_code = \'ar\' AND ct_ar.deleted_at IS NULL
                LEFT JOIN category_translations ct_en
                    ON ct_en.category_id = c.id AND ct_en.language_code = \'en\' AND ct_en.deleted_at IS NULL
                LEFT JOIN product_translations pt_ar
                    ON pt_ar.product_id = p.id AND pt_ar.language_code = \'ar\' AND pt_ar.deleted_at IS NULL
                LEFT JOIN product_translations pt_en
                    ON pt_en.product_id = p.id AND pt_en.language_code = \'en\' AND pt_en.deleted_at IS NULL
                WHERE p.deleted_at IS NULL
                  AND p.status IN (\'published\', \'approved\')
                  ' . $catSql . '
                ORDER BY c.sort_order ASC, p.id ASC
                LIMIT ' . (int) $limit;

        try {
            $stmt = $platform->query($sql);

            return $stmt ? $stmt->fetchAll() : [];
        } catch (PDOException) {
            return [];
        }
    }

    /**
     * Prefer seed PHP UTF-8 over platform COALESCE — never import ??.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<string>|null $catFilter
     * @return list<array<string, mixed>>
     */
    private function overlayAuthoritativeSeed(array $rows, ?array $catFilter, int $limit): array
    {
        $seedMap = PlatformRetailCatalogSeedData::authoritativeSkuMap();
        if ($seedMap === []) {
            return $rows;
        }
        if (is_array($catFilter) && $catFilter !== []) {
            $allowed = array_fill_keys($catFilter, true);
            $seedMap = array_filter(
                $seedMap,
                static fn (array $r): bool => isset($allowed[$r['category_slug'] ?? ''])
            );
        }

        $bySku = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $bySku[$sku] = $row;
        }

        foreach ($seedMap as $sku => $seed) {
            $base = $bySku[$sku] ?? [
                'sku' => $sku,
                'primary_barcode' => $seed['barcode'],
                'price' => $seed['price'],
            ];
            $base['sku'] = $sku;
            $base['name_ar'] = $seed['name_ar'];
            $base['name_en'] = $seed['name_en'];
            $base['category_slug'] = $seed['category_slug'];
            $base['category_name_ar'] = $seed['category_name_ar'];
            $base['category_name_en'] = $seed['category_name_en'];
            if (!isset($base['primary_barcode']) || trim((string) $base['primary_barcode']) === '') {
                $base['primary_barcode'] = $seed['barcode'];
            }
            if (!isset($base['price']) || (float) $base['price'] <= 0) {
                $base['price'] = $seed['price'];
            }
            $bySku[$sku] = $base;
        }

        $out = array_values($bySku);
        usort($out, static function (array $a, array $b): int {
            return strcmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
        });

        return array_slice($out, 0, max(1, $limit));
    }

    private function preferReadableName(string $nameAr, string $nameEn, string $sku): string
    {
        if (!$this->isCorruptedName($nameAr)) {
            return $nameAr;
        }
        if (!$this->isCorruptedName($nameEn)) {
            return $nameEn;
        }

        return $sku;
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
            $this->refreshCompanyCategoryNames($id, $nameAr, $nameEn, $row);
            $cache[$slug] = $id;

            return $id;
        }

        $prev = TenantContext::companyId();
        TenantContext::setCompanyId($companyId);
        try {
            // Prefer Arabic labels for guest-menu / RTL companies.
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
        } finally {
            TenantContext::setCompanyId($prev);
        }

        $cache[$slug] = (int) $id;

        return (int) $id;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function refreshCompanyCategoryNames(int $categoryId, string $nameAr, string $nameEn, array $row): void
    {
        if ($categoryId < 1) {
            return;
        }
        $currentName = (string) ($row['name'] ?? '');
        $currentAr = (string) ($row['name_ar'] ?? '');
        $desiredAr = $nameAr !== '' ? $nameAr : ($nameEn !== '' ? $nameEn : $currentAr);
        $desiredName = $desiredAr !== '' ? $desiredAr : ($nameEn !== '' ? $nameEn : $currentName);
        $needsName = $desiredName !== '' && (
            $currentName === '' || str_contains($currentName, '??') || $currentName !== $desiredName
        );
        $needsAr = $desiredAr !== '' && (
            $currentAr === '' || str_contains($currentAr, '??') || $currentAr !== $desiredAr
        );
        if (!$needsName && !$needsAr) {
            return;
        }
        $sets = [];
        $params = ['id' => $categoryId];
        if ($needsName) {
            $sets[] = 'name = :name';
            $params['name'] = $desiredName;
        }
        if ($needsAr) {
            $sets[] = 'name_ar = :name_ar';
            $params['name_ar'] = $desiredAr;
        }
        Database::connection()->prepare(
            'UPDATE rateb_product_categories SET ' . implode(', ', $sets) . ' WHERE id = :id'
        )->execute($params);
    }

    private function findInventoryId(int $companyId, string $sku): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, item_name, category_id FROM rateb_inventory
             WHERE company_id = :cid AND sku = :sku LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sku' => $sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
    }

    private function repairInventoryRow(
        int $inventoryId,
        string $name,
        int $categoryId,
        string $categoryLabel,
        float $price,
        bool $force = false
    ): bool {
        $stmt = Database::connection()->prepare(
            'SELECT item_name, category_id, category FROM rateb_inventory WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $inventoryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return false;
        }

        $currentName = (string) ($row['item_name'] ?? '');
        $currentCatLabel = (string) ($row['category'] ?? '');
        // Force mode: always rewrite name + category after platform Arabic repair.
        $needsName = $name !== '' && (
            $force || $currentName === '' || str_contains($currentName, '??') || $currentName !== $name
        );
        $needsCatId = $categoryId > 0 && (
            $force || (int) ($row['category_id'] ?? 0) !== $categoryId
        );
        $needsCatLabel = $categoryLabel !== '' && (
            $force || $currentCatLabel === '' || str_contains($currentCatLabel, '??') || $currentCatLabel !== $categoryLabel
        );
        if (!$needsName && !$needsCatId && !$needsCatLabel) {
            return false;
        }

        $sets = [];
        $params = ['id' => $inventoryId];
        if ($needsName) {
            $sets[] = 'item_name = :name';
            $params['name'] = $name;
        }
        if ($needsCatId || $needsCatLabel) {
            if ($categoryId > 0) {
                $sets[] = 'category_id = :cid';
                $params['cid'] = $categoryId;
            }
            $sets[] = 'category = :cat';
            $params['cat'] = $categoryLabel;
        }
        if (($needsName || $force) && $price > 0) {
            $sets[] = 'unit_cost = :price';
            $params['price'] = $price;
        }
        if ($sets === []) {
            return false;
        }
        Database::connection()->prepare(
            'UPDATE rateb_inventory SET ' . implode(', ', $sets) . ' WHERE id = :id'
        )->execute($params);

        return true;
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
