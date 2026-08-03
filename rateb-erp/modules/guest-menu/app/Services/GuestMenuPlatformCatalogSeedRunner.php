<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Services;

use PDO;
use PDOException;

/** Fill platform catalog DB with comprehensive retail seed (ERP-local, no catalog migrate required). */
final class GuestMenuPlatformCatalogSeedRunner
{
    /**
     * @return array{ok:bool, message:string, log?:list<string>, product_count?:int}
     */
    public function ensureSeeded(): array
    {
        $pdo = $this->platformConnection();
        if ($pdo === null) {
            return ['ok' => false, 'message' => 'platform_db_unavailable'];
        }
        try {
            $published = (int) $pdo->query(
                "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status IN ('published','approved')"
            )->fetchColumn();
            if ($published >= 20) {
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
        $pdo = $this->platformConnection();
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
}
