<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use PDO;

/** Fill platform catalog DB with comprehensive retail seed (ERP-local, no catalog migrate required). */
final class GuestMenuPlatformCatalogSeedRunner
{
    /**
     * Prefer a fresh repair run when Arabic names look corrupted; otherwise skip if already full.
     *
     * @return array{ok:bool, message:string, log?:list<string>, product_count?:int}
     */
    public function ensureSeeded(): array
    {
        $pdo = PlatformCatalogConnection::connect();
        if ($pdo === null) {
            return ['ok' => false, 'message' => 'platform_db_unavailable'];
        }
        try {
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            $published = (int) $pdo->query(
                "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status IN ('published','approved')"
            )->fetchColumn();
            if ($published >= 20 && !$this->hasCorruptedArabicNames($pdo)) {
                return [
                    'ok' => true,
                    'message' => 'already_populated',
                    'product_count' => $published,
                ];
            }
        } catch (\Throwable) {
            // continue — seed may create missing rows if schema exists
        }

        return $this->run();
    }

    /**
     * @return array{ok:bool, message:string, log?:list<string>, product_count?:int}
     */
    public function run(): array
    {
        $pdo = PlatformCatalogConnection::connect();
        if ($pdo === null) {
            return ['ok' => false, 'message' => 'platform_db_unavailable'];
        }

        try {
            (new PlatformRetailCatalogSeedData($pdo))->run();
            $this->markApplied($pdo);
            $count = (int) $pdo->query(
                "SELECT COUNT(*) FROM products WHERE sku LIKE 'RC-%' AND deleted_at IS NULL"
            )->fetchColumn();

            return [
                'ok' => true,
                'message' => 'seeded',
                'product_count' => $count,
                'log' => ['products_rc=' . $count],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function hasCorruptedArabicNames(PDO $pdo): bool
    {
        try {
            $n = (int) $pdo->query(
                "SELECT COUNT(*)
                 FROM product_translations pt
                 INNER JOIN products p ON p.id = pt.product_id
                 WHERE p.sku LIKE 'RC-%'
                   AND p.deleted_at IS NULL
                   AND pt.deleted_at IS NULL
                   AND pt.language_code = 'ar'
                   AND pt.name LIKE '%??%'"
            )->fetchColumn();

            return $n > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function markApplied(PDO $pdo): void
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS catalog_migrations (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    filename VARCHAR(255) NOT NULL,
                    applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    UNIQUE KEY uk_catalog_migrations_filename (filename)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $stmt = $pdo->prepare('INSERT IGNORE INTO catalog_migrations (filename) VALUES (:f)');
            $stmt->execute(['f' => '022_comprehensive_retail_seed']);
        } catch (\Throwable) {
            // non-fatal
        }
    }
}
