<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M015ImportStaging extends AbstractMigration
{
    public function name(): string
    {
        return '015_import_staging';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS import_sources (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                code VARCHAR(30) NOT NULL,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_import_sources_uuid (uuid),
                UNIQUE KEY uk_import_sources_code (code),
                KEY idx_import_sources_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS import_batches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                import_source_id BIGINT UNSIGNED NOT NULL,
                source_file_path VARCHAR(500) NULL,
                source_checksum CHAR(64) NOT NULL,
                status ENUM(
                    "uploaded", "validating", "preview_ready", "validation_failed",
                    "committing", "committed", "commit_failed", "rolled_back"
                ) NOT NULL DEFAULT "uploaded",
                total_rows INT UNSIGNED NOT NULL DEFAULT 0,
                valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
                error_rows INT UNSIGNED NOT NULL DEFAULT 0,
                parser_config JSON NULL,
                committed_at DATETIME(6) NULL,
                rolled_back_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_import_batches_uuid (uuid),
                KEY idx_import_batches_status (status),
                KEY idx_import_batches_source (import_source_id),
                KEY idx_import_batches_deleted (deleted_at),
                CONSTRAINT fk_import_batches_source FOREIGN KEY (import_source_id) REFERENCES import_sources (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS import_batch_rows (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                import_batch_id BIGINT UNSIGNED NOT NULL,
                `row_number` INT UNSIGNED NOT NULL,
                raw_payload JSON NOT NULL,
                mapped_payload JSON NULL,
                validation_errors JSON NULL,
                status ENUM("pending", "valid", "invalid", "committed", "skipped") NOT NULL DEFAULT "pending",
                entity_uuid CHAR(36) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_import_batch_rows_uuid (uuid),
                KEY idx_import_batch_rows_batch (import_batch_id, `row_number`),
                KEY idx_import_batch_rows_status (import_batch_id, status),
                KEY idx_import_batch_rows_entity (entity_uuid),
                CONSTRAINT fk_import_batch_rows_batch FOREIGN KEY (import_batch_id) REFERENCES import_batches (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->seedImportSources();
    }

    public function down(): void
    {
        // Drop in reverse dependency order (FK children first).
        $this->exec(
            'DROP TABLE IF EXISTS import_batch_rows;
            DROP TABLE IF EXISTS import_batches;
            DROP TABLE IF EXISTS import_sources'
        );
    }

    private function seedImportSources(): void
    {
        $sources = ['manual', 'csv', 'excel', 'api', 'xml', 'json', 'ftp'];
        foreach ($sources as $code) {
            $uuid = $this->uuidForCode($code);
            $this->exec(
                'INSERT IGNORE INTO import_sources (uuid, code, status) VALUES ("' . $uuid . '", "' . $code . '", "active")'
            );
        }
    }

    private function uuidForCode(string $code): string
    {
        $hash = md5('rateb-import-source-' . $code);

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
