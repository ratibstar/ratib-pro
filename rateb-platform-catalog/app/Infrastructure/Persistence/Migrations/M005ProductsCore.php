<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M005ProductsCore extends AbstractMigration
{
    public function name(): string
    {
        return '005_products_core';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS products (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                sku VARCHAR(80) NOT NULL,
                brand_id BIGINT UNSIGNED NULL,
                category_id BIGINT UNSIGNED NOT NULL,
                family_id BIGINT UNSIGNED NULL,
                unit_id BIGINT UNSIGNED NOT NULL,
                is_bundle TINYINT(1) NOT NULL DEFAULT 0,
                primary_barcode VARCHAR(80) NULL,
                weight_kg DECIMAL(12,4) NULL,
                length_cm DECIMAL(10,2) NULL,
                width_cm DECIMAL(10,2) NULL,
                height_cm DECIMAL(10,2) NULL,
                manufacturer_id BIGINT UNSIGNED NULL,
                country_id BIGINT UNSIGNED NULL,
                warranty_months SMALLINT NULL,
                tax_class VARCHAR(50) NULL,
                status ENUM(
                    "draft", "pending_review", "approved", "published", "archived", "rejected"
                ) NOT NULL DEFAULT "draft",
                version_number INT UNSIGNED NOT NULL DEFAULT 1,
                lock_version INT UNSIGNED NOT NULL DEFAULT 1,
                publish_at DATETIME(6) NULL,
                archive_at DATETIME(6) NULL,
                published_at DATETIME(6) NULL,
                approved_by BIGINT UNSIGNED NULL,
                approved_at DATETIME(6) NULL,
                search_weight DECIMAL(8,4) NOT NULL DEFAULT 1.0000,
                boost_score DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_products_uuid (uuid),
                UNIQUE KEY uk_products_sku (sku),
                KEY idx_products_deleted (deleted_at),
                KEY idx_products_category (category_id),
                KEY idx_products_brand (brand_id),
                KEY idx_products_family (family_id),
                KEY idx_products_unit (unit_id),
                KEY idx_products_status (status),
                KEY idx_products_manufacturer (manufacturer_id),
                KEY idx_products_country (country_id),
                CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands (id),
                CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id),
                CONSTRAINT fk_products_family FOREIGN KEY (family_id) REFERENCES product_families (id),
                CONSTRAINT fk_products_unit FOREIGN KEY (unit_id) REFERENCES units (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(255) NOT NULL,
                short_description VARCHAR(500) NULL,
                description MEDIUMTEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_translations_uuid (uuid),
                UNIQUE KEY uk_product_translations_product_lang (product_id, language_code),
                KEY idx_product_translations_deleted (deleted_at),
                KEY idx_product_translations_language (language_code),
                CONSTRAINT fk_product_translations_product FOREIGN KEY (product_id) REFERENCES products (id),
                CONSTRAINT fk_product_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS product_translations');
        $this->exec('DROP TABLE IF EXISTS products');
    }
}
