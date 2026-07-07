<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M003Taxonomy extends AbstractMigration
{
    public function name(): string
    {
        return '003_taxonomy';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                parent_id BIGINT UNSIGNED NULL,
                slug VARCHAR(150) NOT NULL,
                depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
                path VARCHAR(1000) NOT NULL DEFAULT "",
                sort_order INT NOT NULL DEFAULT 0,
                image_path VARCHAR(500) NULL,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_categories_uuid (uuid),
                UNIQUE KEY uk_categories_slug (slug),
                KEY idx_categories_parent (parent_id),
                KEY idx_categories_deleted (deleted_at),
                CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS category_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                category_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_category_translations_uuid (uuid),
                UNIQUE KEY uk_category_translations_cat_lang (category_id, language_code),
                KEY idx_category_translations_deleted (deleted_at),
                CONSTRAINT fk_category_translations_category FOREIGN KEY (category_id) REFERENCES categories (id),
                CONSTRAINT fk_category_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS brands (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                slug VARCHAR(150) NOT NULL,
                logo_path VARCHAR(500) NULL,
                website VARCHAR(255) NULL,
                country_code CHAR(2) NULL,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_brands_uuid (uuid),
                UNIQUE KEY uk_brands_slug (slug),
                KEY idx_brands_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS brand_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                brand_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_brand_translations_uuid (uuid),
                UNIQUE KEY uk_brand_translations_brand_lang (brand_id, language_code),
                KEY idx_brand_translations_deleted (deleted_at),
                CONSTRAINT fk_brand_translations_brand FOREIGN KEY (brand_id) REFERENCES brands (id),
                CONSTRAINT fk_brand_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS suppliers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                code VARCHAR(50) NOT NULL,
                contact_email VARCHAR(255) NULL,
                contact_phone VARCHAR(50) NULL,
                country_code CHAR(2) NULL,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_suppliers_uuid (uuid),
                UNIQUE KEY uk_suppliers_code (code),
                KEY idx_suppliers_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS supplier_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                supplier_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(200) NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_supplier_translations_uuid (uuid),
                UNIQUE KEY uk_supplier_translations_supplier_lang (supplier_id, language_code),
                KEY idx_supplier_translations_deleted (deleted_at),
                CONSTRAINT fk_supplier_translations_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
                CONSTRAINT fk_supplier_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS supplier_translations');
        $this->exec('DROP TABLE IF EXISTS suppliers');
        $this->exec('DROP TABLE IF EXISTS brand_translations');
        $this->exec('DROP TABLE IF EXISTS brands');
        $this->exec('DROP TABLE IF EXISTS category_translations');
        $this->exec('DROP TABLE IF EXISTS categories');
    }
}
