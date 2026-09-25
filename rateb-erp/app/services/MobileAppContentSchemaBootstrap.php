<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;

/** Ensure Agent Apps content/offers tables exist when migrations lag. */
final class MobileAppContentSchemaBootstrap
{
    private static bool $done = false;

    public static function ensure(): void
    {
        if (self::$done) {
            return;
        }
        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_mobile_app_contents (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                slug VARCHAR(64) NOT NULL,
                title_ar VARCHAR(255) NOT NULL DEFAULT \'\',
                title_en VARCHAR(255) NOT NULL DEFAULT \'\',
                body_ar MEDIUMTEXT NULL,
                body_en MEDIUMTEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_mobile_content_company_slug (company_id, slug),
                KEY idx_mobile_content_active (company_id, is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rateb_mobile_app_offers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id INT UNSIGNED NOT NULL,
                title_ar VARCHAR(255) NOT NULL DEFAULT \'\',
                title_en VARCHAR(255) NOT NULL DEFAULT \'\',
                body_ar MEDIUMTEXT NULL,
                body_en MEDIUMTEXT NULL,
                image_path VARCHAR(500) NULL,
                discount_label VARCHAR(80) NOT NULL DEFAULT \'\',
                starts_at DATE NULL,
                ends_at DATE NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_mobile_offers_company (company_id, is_active, sort_order),
                KEY idx_mobile_offers_window (company_id, starts_at, ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::ensureTargetAppColumns($pdo);
        self::$done = true;
    }

    /** company_id NULL = every company (keeps the company FK valid); target_app: all | hr | erp | customer. */
    private static function ensureTargetAppColumns(\PDO $pdo): void
    {
        try {
            foreach (['rateb_mobile_app_contents', 'rateb_mobile_app_offers'] as $table) {
                $company = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'company_id'");
                $companyCol = $company ? $company->fetch(\PDO::FETCH_ASSOC) : false;
                if (is_array($companyCol) && strtoupper((string) ($companyCol['Null'] ?? '')) === 'NO') {
                    $pdo->exec("ALTER TABLE {$table} MODIFY company_id INT UNSIGNED NULL");
                }
                $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'target_app'");
                if ($stmt && $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    continue;
                }
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN target_app VARCHAR(16) NOT NULL DEFAULT 'all' AFTER company_id");
                if ($table === 'rateb_mobile_app_contents') {
                    $idx = $pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = 'uq_mobile_content_company_slug'");
                    if ($idx && $idx->fetch(\PDO::FETCH_ASSOC)) {
                        $pdo->exec("ALTER TABLE {$table} DROP INDEX uq_mobile_content_company_slug");
                    }
                    $pdo->exec("ALTER TABLE {$table} ADD UNIQUE KEY uq_mobile_content_company_app_slug (company_id, target_app, slug)");
                }
            }
        } catch (\Throwable $e) {
            error_log('MobileAppContentSchemaBootstrap::ensureTargetAppColumns: ' . $e->getMessage());
        }
    }

    public static function ensurePaymentMethodsColumn(): void
    {
        self::ensure();
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query("SHOW COLUMNS FROM rateb_mobile_app_configs LIKE 'payment_methods_json'");
            $exists = $stmt && $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$exists) {
                $pdo->exec(
                    'ALTER TABLE rateb_mobile_app_configs
                     ADD COLUMN payment_methods_json JSON NULL AFTER enabled_features'
                );
            }
        } catch (\Throwable $e) {
            error_log('MobileAppContentSchemaBootstrap::ensurePaymentMethodsColumn: ' . $e->getMessage());
        }
    }
}
