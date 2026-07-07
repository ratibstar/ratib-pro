<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M004ProductFamiliesAndAttributes extends AbstractMigration
{
    public function name(): string
    {
        return '004_product_families_and_attributes';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_families (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                code VARCHAR(80) NOT NULL,
                brand_id BIGINT UNSIGNED NULL,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_families_uuid (uuid),
                UNIQUE KEY uk_product_families_code (code),
                KEY idx_product_families_brand (brand_id),
                KEY idx_product_families_deleted (deleted_at),
                CONSTRAINT fk_product_families_brand FOREIGN KEY (brand_id) REFERENCES brands (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS family_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_family_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_family_translations_uuid (uuid),
                UNIQUE KEY uk_family_translations_family_lang (product_family_id, language_code),
                KEY idx_family_translations_deleted (deleted_at),
                CONSTRAINT fk_family_translations_family FOREIGN KEY (product_family_id) REFERENCES product_families (id),
                CONSTRAINT fk_family_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS attributes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                code VARCHAR(80) NOT NULL,
                input_type ENUM("text", "number", "select", "multiselect", "boolean") NOT NULL DEFAULT "text",
                is_variant_defining TINYINT(1) NOT NULL DEFAULT 0,
                is_filterable TINYINT(1) NOT NULL DEFAULT 0,
                is_visible TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_attributes_uuid (uuid),
                UNIQUE KEY uk_attributes_code (code),
                KEY idx_attributes_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS attribute_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                attribute_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(150) NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_attribute_translations_uuid (uuid),
                UNIQUE KEY uk_attribute_translations_attr_lang (attribute_id, language_code),
                KEY idx_attribute_translations_deleted (deleted_at),
                CONSTRAINT fk_attribute_translations_attribute FOREIGN KEY (attribute_id) REFERENCES attributes (id),
                CONSTRAINT fk_attribute_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS attribute_values (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                attribute_id BIGINT UNSIGNED NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_attribute_values_uuid (uuid),
                KEY idx_attribute_values_attribute (attribute_id),
                KEY idx_attribute_values_deleted (deleted_at),
                CONSTRAINT fk_attribute_values_attribute FOREIGN KEY (attribute_id) REFERENCES attributes (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS attribute_value_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                attribute_value_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                value VARCHAR(255) NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_attribute_value_translations_uuid (uuid),
                UNIQUE KEY uk_attribute_value_translations_value_lang (attribute_value_id, language_code),
                KEY idx_attribute_value_translations_deleted (deleted_at),
                CONSTRAINT fk_attribute_value_translations_value FOREIGN KEY (attribute_value_id) REFERENCES attribute_values (id),
                CONSTRAINT fk_attribute_value_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS attribute_value_translations');
        $this->exec('DROP TABLE IF EXISTS attribute_values');
        $this->exec('DROP TABLE IF EXISTS attribute_translations');
        $this->exec('DROP TABLE IF EXISTS attributes');
        $this->exec('DROP TABLE IF EXISTS family_translations');
        $this->exec('DROP TABLE IF EXISTS product_families');
    }
}
