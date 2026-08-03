<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Inventory;
use PDO;
use PDOException;

/** Copy published platform catalog SKUs into tenant inventory (for guest menu / POS). */
final class GuestMenuPlatformImportService
{
    /**
     * @return array{ok:bool, imported:int, skipped:int, message?:string}
     */
    public function importToCompany(int $companyId, int $limit = 50): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'invalid_company'];
        }

        $platform = $this->platformConnection();
        if ($platform === null) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'platform_db_unavailable'];
        }

        $warehouseId = $this->resolveWarehouseId($companyId);
        if ($warehouseId < 1) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'warehouse_required'];
        }

        $limit = max(1, min(200, $limit));
        $rows = $this->fetchPlatformProducts($platform, $limit);
        if ($rows === []) {
            return ['ok' => true, 'imported' => 0, 'skipped' => 0, 'message' => 'no_platform_products'];
        }

        TenantContext::setCompanyId($companyId);
        $inventory = new Inventory();
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($sku === '' || $name === '') {
                ++$skipped;
                continue;
            }
            if ($this->skuExists($companyId, $sku)) {
                ++$skipped;
                continue;
            }
            $price = max(0.0, (float) ($row['price'] ?? 0));
            try {
                $inventory->create([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'item_name' => $name,
                    'sku' => $sku,
                    'barcode' => trim((string) ($row['primary_barcode'] ?? '')) ?: null,
                    'category' => 'menu',
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

        return ['ok' => true, 'imported' => $imported, 'skipped' => $skipped];
    }

    private function platformConnection(): ?PDO
    {
        $config = dirname(RATEB_ROOT) . '/rateb-platform-catalog/config/database.php';
        if (!is_file($config)) {
            return null;
        }
        require_once $config;
        if (!defined('RATEB_PLATFORM_CATALOG_DB_NAME')) {
            return null;
        }
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                RATEB_PLATFORM_CATALOG_DB_HOST,
                (int) RATEB_PLATFORM_CATALOG_DB_PORT,
                RATEB_PLATFORM_CATALOG_DB_NAME
            );

            return new PDO($dsn, RATEB_PLATFORM_CATALOG_DB_USER, RATEB_PLATFORM_CATALOG_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    private function fetchPlatformProducts(PDO $platform, int $limit): array
    {
        $sql = 'SELECT p.sku, p.primary_barcode,
                       COALESCE(pt_ar.name, pt_en.name, p.sku) AS name,
                       (
                           SELECT pp.amount FROM product_prices pp
                           WHERE pp.product_id = p.id AND pp.deleted_at IS NULL
                           ORDER BY pp.is_default DESC, pp.id ASC LIMIT 1
                       ) AS price
                FROM products p
                LEFT JOIN product_translations pt_ar
                    ON pt_ar.product_id = p.id AND pt_ar.language_code = \'ar\'
                LEFT JOIN product_translations pt_en
                    ON pt_en.product_id = p.id AND pt_en.language_code = \'en\'
                WHERE p.deleted_at IS NULL
                  AND p.status IN (\'published\', \'approved\')
                ORDER BY p.id DESC
                LIMIT ' . (int) $limit;

        try {
            $stmt = $platform->query($sql);

            return $stmt ? $stmt->fetchAll() : [];
        } catch (PDOException) {
            $fallback = 'SELECT p.sku, p.primary_barcode, p.sku AS name, NULL AS price
                         FROM products p
                         WHERE p.deleted_at IS NULL
                           AND p.status IN (\'published\', \'approved\')
                         ORDER BY p.id DESC
                         LIMIT ' . (int) $limit;
            try {
                $stmt = $platform->query($fallback);

                return $stmt ? $stmt->fetchAll() : [];
            } catch (PDOException) {
                return [];
            }
        }
    }

    private function resolveWarehouseId(int $companyId): int
    {
        $erp = \Rateb\App\Core\Database::connection();
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
