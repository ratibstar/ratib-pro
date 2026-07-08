<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M019DuplicatesSavedFilters extends AbstractMigration
{
    public function name(): string
    {
        return '019_duplicates_saved_filters';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS duplicate_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                code VARCHAR(50) NOT NULL,
                match_field ENUM("sku", "barcode", "name", "supplier_sku") NOT NULL,
                match_type ENUM("exact", "fuzzy", "phonetic") NOT NULL DEFAULT "exact",
                threshold DECIMAL(5,4) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                priority INT NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_duplicate_rules_uuid (uuid),
                UNIQUE KEY uk_duplicate_rules_code (code),
                KEY idx_duplicate_rules_active (is_active, deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS duplicate_groups (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                group_key VARCHAR(64) NOT NULL,
                match_rule_id BIGINT UNSIGNED NULL,
                status ENUM("open", "reviewing", "resolved", "ignored") NOT NULL DEFAULT "open",
                resolved_by BIGINT UNSIGNED NULL,
                resolved_at DATETIME(6) NULL,
                resolution_note TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_duplicate_groups_uuid (uuid),
                UNIQUE KEY uk_duplicate_groups_key (group_key),
                KEY idx_duplicate_groups_status (status),
                KEY idx_duplicate_groups_rule (match_rule_id),
                KEY idx_duplicate_groups_deleted (deleted_at),
                CONSTRAINT fk_duplicate_groups_rule FOREIGN KEY (match_rule_id) REFERENCES duplicate_rules (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS duplicate_group_products (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                duplicate_group_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                match_score DECIMAL(5,4) NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_duplicate_group_products_uuid (uuid),
                UNIQUE KEY uk_duplicate_group_products_pair (duplicate_group_id, product_id),
                KEY idx_duplicate_group_products_group (duplicate_group_id),
                KEY idx_duplicate_group_products_product (product_id),
                KEY idx_duplicate_group_products_deleted (deleted_at),
                CONSTRAINT fk_duplicate_group_products_group FOREIGN KEY (duplicate_group_id) REFERENCES duplicate_groups (id),
                CONSTRAINT fk_duplicate_group_products_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS saved_filters (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                platform_user_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(150) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                filter_json JSON NOT NULL,
                sort_json JSON NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                is_shared TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_saved_filters_uuid (uuid),
                KEY idx_saved_filters_user_entity (platform_user_id, entity_type),
                KEY idx_saved_filters_deleted (deleted_at),
                CONSTRAINT fk_saved_filters_user FOREIGN KEY (platform_user_id) REFERENCES platform_users (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->seedDuplicateRules();
    }

    public function down(): void
    {
        // Drop in reverse dependency order (FK children first).
        $this->exec(
            'DROP TABLE IF EXISTS duplicate_group_products;
             DROP TABLE IF EXISTS duplicate_groups;
             DROP TABLE IF EXISTS duplicate_rules;
             DROP TABLE IF EXISTS saved_filters'
        );
    }

    private function seedDuplicateRules(): void
    {
        $rules = [
            ['sku_exact', 'sku', 'exact', null, 100],
            ['barcode_exact', 'barcode', 'exact', null, 90],
            ['name_fuzzy', 'name', 'fuzzy', '0.8500', 50],
        ];

        foreach ($rules as [$code, $field, $type, $threshold, $priority]) {
            $uuid = $this->uuidFor('dup-rule-' . $code);
            $thresholdSql = $threshold === null ? 'NULL' : $threshold;
            $this->exec(
                'INSERT IGNORE INTO duplicate_rules (uuid, code, match_field, match_type, threshold, priority, is_active)
                 VALUES ("' . $uuid . '", "' . $code . '", "' . $field . '", "' . $type . '", ' . $thresholdSql . ', ' . $priority . ', 1)'
            );
        }
    }

    private function uuidFor(string $seed): string
    {
        $hash = md5('rateb-s2-' . $seed);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }
}
