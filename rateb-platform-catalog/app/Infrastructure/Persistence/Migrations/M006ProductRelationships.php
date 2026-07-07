<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M006ProductRelationships extends AbstractMigration
{
    public function name(): string
    {
        return '006_product_relationships';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_barcodes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                barcode VARCHAR(80) NOT NULL,
                barcode_type ENUM("EAN13", "EAN8", "UPC", "CODE128", "QR", "OTHER") NOT NULL DEFAULT "OTHER",
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_barcodes_uuid (uuid),
                UNIQUE KEY uk_product_barcodes_barcode (barcode),
                KEY idx_product_barcodes_product (product_id),
                KEY idx_product_barcodes_deleted (deleted_at),
                CONSTRAINT fk_product_barcodes_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_variants (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                sku VARCHAR(80) NOT NULL,
                primary_barcode VARCHAR(80) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                weight_kg DECIMAL(12,4) NULL,
                length_cm DECIMAL(10,2) NULL,
                width_cm DECIMAL(10,2) NULL,
                height_cm DECIMAL(10,2) NULL,
                status ENUM("draft", "pending_review", "approved", "published", "archived", "rejected") NOT NULL DEFAULT "draft",
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                approved_by BIGINT UNSIGNED NULL,
                approved_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_variants_uuid (uuid),
                UNIQUE KEY uk_product_variants_sku (sku),
                KEY idx_product_variants_product (product_id),
                KEY idx_product_variants_deleted (deleted_at),
                CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_variant_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_variant_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(255) NULL,
                description TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_variant_translations_uuid (uuid),
                UNIQUE KEY uk_product_variant_translations_variant_lang (product_variant_id, language_code),
                KEY idx_product_variant_translations_deleted (deleted_at),
                CONSTRAINT fk_product_variant_translations_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants (id),
                CONSTRAINT fk_product_variant_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_variant_barcodes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_variant_id BIGINT UNSIGNED NOT NULL,
                barcode VARCHAR(80) NOT NULL,
                barcode_type ENUM("EAN13", "EAN8", "UPC", "CODE128", "QR", "OTHER") NOT NULL DEFAULT "OTHER",
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_variant_barcodes_uuid (uuid),
                UNIQUE KEY uk_product_variant_barcodes_barcode (barcode),
                KEY idx_product_variant_barcodes_variant (product_variant_id),
                KEY idx_product_variant_barcodes_deleted (deleted_at),
                CONSTRAINT fk_product_variant_barcodes_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS variant_attributes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_variant_id BIGINT UNSIGNED NOT NULL,
                attribute_id BIGINT UNSIGNED NOT NULL,
                attribute_value_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_variant_attributes_uuid (uuid),
                UNIQUE KEY uk_variant_attributes_variant_attr (product_variant_id, attribute_id),
                KEY idx_variant_attributes_deleted (deleted_at),
                CONSTRAINT fk_variant_attributes_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants (id),
                CONSTRAINT fk_variant_attributes_attribute FOREIGN KEY (attribute_id) REFERENCES attributes (id),
                CONSTRAINT fk_variant_attributes_value FOREIGN KEY (attribute_value_id) REFERENCES attribute_values (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_attributes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                attribute_id BIGINT UNSIGNED NOT NULL,
                attribute_value_id BIGINT UNSIGNED NULL,
                value_text VARCHAR(500) NULL,
                value_number DECIMAL(18,6) NULL,
                value_boolean TINYINT(1) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_attributes_uuid (uuid),
                UNIQUE KEY uk_product_attributes_product_attr (product_id, attribute_id),
                KEY idx_product_attributes_deleted (deleted_at),
                CONSTRAINT fk_product_attributes_product FOREIGN KEY (product_id) REFERENCES products (id),
                CONSTRAINT fk_product_attributes_attribute FOREIGN KEY (attribute_id) REFERENCES attributes (id),
                CONSTRAINT fk_product_attributes_value FOREIGN KEY (attribute_value_id) REFERENCES attribute_values (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_attribute_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_attribute_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                value_text VARCHAR(500) NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_attribute_translations_uuid (uuid),
                UNIQUE KEY uk_product_attribute_translations_attr_lang (product_attribute_id, language_code),
                KEY idx_product_attribute_translations_deleted (deleted_at),
                CONSTRAINT fk_product_attribute_translations_attr FOREIGN KEY (product_attribute_id) REFERENCES product_attributes (id),
                CONSTRAINT fk_product_attribute_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_bundles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                bundle_product_id BIGINT UNSIGNED NOT NULL,
                component_product_id BIGINT UNSIGNED NOT NULL,
                component_variant_id BIGINT UNSIGNED NULL,
                quantity DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
                sort_order INT NOT NULL DEFAULT 0,
                is_optional TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_bundles_uuid (uuid),
                UNIQUE KEY uk_product_bundles_component (bundle_product_id, component_product_id, component_variant_id),
                KEY idx_product_bundles_bundle (bundle_product_id),
                KEY idx_product_bundles_component_product (component_product_id),
                KEY idx_product_bundles_component_variant (component_variant_id),
                KEY idx_product_bundles_deleted (deleted_at),
                CONSTRAINT fk_product_bundles_bundle FOREIGN KEY (bundle_product_id) REFERENCES products (id),
                CONSTRAINT fk_product_bundles_component FOREIGN KEY (component_product_id) REFERENCES products (id),
                CONSTRAINT fk_product_bundles_component_variant FOREIGN KEY (component_variant_id) REFERENCES product_variants (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_relations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                related_product_id BIGINT UNSIGNED NOT NULL,
                relation_type ENUM("related", "accessory", "replacement", "upsell", "cross_sell") NOT NULL DEFAULT "related",
                sort_order INT NOT NULL DEFAULT 0,
                is_bidirectional TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_relations_uuid (uuid),
                UNIQUE KEY uk_product_relations_unique (product_id, related_product_id, relation_type),
                KEY idx_product_relations_product (product_id),
                KEY idx_product_relations_related (related_product_id),
                KEY idx_product_relations_deleted (deleted_at),
                CONSTRAINT fk_product_relations_product FOREIGN KEY (product_id) REFERENCES products (id),
                CONSTRAINT fk_product_relations_related FOREIGN KEY (related_product_id) REFERENCES products (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS product_relations');
        $this->exec('DROP TABLE IF EXISTS product_bundles');
        $this->exec('DROP TABLE IF EXISTS product_attribute_translations');
        $this->exec('DROP TABLE IF EXISTS product_attributes');
        $this->exec('DROP TABLE IF EXISTS variant_attributes');
        $this->exec('DROP TABLE IF EXISTS product_variant_barcodes');
        $this->exec('DROP TABLE IF EXISTS product_variant_translations');
        $this->exec('DROP TABLE IF EXISTS product_variants');
        $this->exec('DROP TABLE IF EXISTS product_barcodes');
    }
}
