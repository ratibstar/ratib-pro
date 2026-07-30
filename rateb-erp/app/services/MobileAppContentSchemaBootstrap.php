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
        self::$done = true;
    }
}
