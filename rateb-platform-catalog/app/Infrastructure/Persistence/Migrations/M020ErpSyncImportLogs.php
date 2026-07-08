<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M020ErpSyncImportLogs extends AbstractMigration
{
    public function name(): string
    {
        return '020_erp_sync_import_logs';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS import_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                import_source_id BIGINT UNSIGNED NOT NULL,
                erp_company_id INT UNSIGNED NOT NULL,
                erp_user_id INT UNSIGNED NULL,
                import_type ENUM("single", "bulk", "category", "brand", "search", "full_catalog") NOT NULL DEFAULT "bulk",
                scope_ref VARCHAR(200) NULL,
                language_code VARCHAR(10) NOT NULL DEFAULT "en",
                source_file_path VARCHAR(500) NULL,
                source_checksum CHAR(64) NULL,
                parser_config JSON NULL,
                requested_count INT UNSIGNED NOT NULL DEFAULT 0,
                exported_count INT UNSIGNED NOT NULL DEFAULT 0,
                status ENUM("pending", "processing", "completed", "failed", "partial") NOT NULL DEFAULT "pending",
                error_message TEXT NULL,
                request_payload JSON NULL,
                response_meta JSON NULL,
                completed_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_import_logs_uuid (uuid),
                KEY idx_import_logs_company (erp_company_id),
                KEY idx_import_logs_status (status),
                KEY idx_import_logs_source (import_source_id),
                KEY idx_import_logs_deleted (deleted_at),
                CONSTRAINT fk_import_logs_source FOREIGN KEY (import_source_id) REFERENCES import_sources (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS import_log_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                import_log_id BIGINT UNSIGNED NOT NULL,
                product_uuid CHAR(36) NOT NULL,
                product_version INT UNSIGNED NOT NULL DEFAULT 1,
                status ENUM("exported", "skipped", "failed") NOT NULL DEFAULT "exported",
                error_message TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_import_log_items_uuid (uuid),
                KEY idx_import_log_items_log (import_log_id),
                KEY idx_import_log_items_product (product_uuid),
                KEY idx_import_log_items_deleted (deleted_at),
                CONSTRAINT fk_import_log_items_log FOREIGN KEY (import_log_id) REFERENCES import_logs (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS erp_product_sync (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                erp_company_id INT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                product_variant_id BIGINT UNSIGNED NULL,
                platform_source_version INT UNSIGNED NOT NULL DEFAULT 1,
                erp_inventory_id INT UNSIGNED NULL,
                last_imported_at DATETIME(6) NULL,
                last_sync_at DATETIME(6) NULL,
                imported_by INT UNSIGNED NULL,
                sync_status ENUM(
                    "never_imported", "imported", "update_available",
                    "sync_pending", "sync_ignored", "sync_failed"
                ) NOT NULL DEFAULT "never_imported",
                sync_note TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_erp_product_sync_uuid (uuid),
                UNIQUE KEY uk_erp_product_sync_company_product (erp_company_id, product_id, product_variant_id),
                KEY idx_erp_product_sync_company (erp_company_id),
                KEY idx_erp_product_sync_product (product_id),
                KEY idx_erp_product_sync_status (sync_status),
                KEY idx_erp_product_sync_deleted (deleted_at),
                CONSTRAINT fk_erp_product_sync_product FOREIGN KEY (product_id) REFERENCES products (id),
                CONSTRAINT fk_erp_product_sync_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS sync_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                erp_company_id INT UNSIGNED NOT NULL,
                product_uuid CHAR(36) NOT NULL,
                from_version INT UNSIGNED NOT NULL,
                to_version INT UNSIGNED NOT NULL,
                sync_action VARCHAR(50) NOT NULL,
                sync_payload JSON NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_sync_logs_uuid (uuid),
                KEY idx_sync_logs_company (erp_company_id),
                KEY idx_sync_logs_product (product_uuid),
                KEY idx_sync_logs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
