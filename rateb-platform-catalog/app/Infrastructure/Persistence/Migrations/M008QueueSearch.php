<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M008QueueSearch extends AbstractMigration
{
    public function name(): string
    {
        return '008_queue_search';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS job_queue (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                job_id CHAR(36) NOT NULL,
                queue VARCHAR(50) NOT NULL,
                job_type VARCHAR(80) NOT NULL,
                payload JSON NOT NULL,
                idempotency_key VARCHAR(128) NULL,
                status ENUM("pending", "processing", "completed", "failed", "dead") NOT NULL DEFAULT "pending",
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
                available_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                started_at DATETIME(6) NULL,
                completed_at DATETIME(6) NULL,
                last_error TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_job_queue_job_id (job_id),
                UNIQUE KEY uk_job_queue_idempotency (job_type, idempotency_key),
                KEY idx_job_queue_pop (queue, status, available_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS job_dead_letter_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                job_id CHAR(36) NOT NULL,
                queue VARCHAR(50) NOT NULL,
                job_type VARCHAR(80) NOT NULL,
                payload JSON NOT NULL,
                last_error TEXT NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                expires_at DATETIME(6) NOT NULL,
                UNIQUE KEY uk_job_dead_letter_uuid (uuid),
                KEY idx_job_dead_letter_job_id (job_id),
                KEY idx_job_dead_letter_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS search_index_queue (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                entity_type ENUM("product", "variant") NOT NULL,
                entity_uuid CHAR(36) NOT NULL,
                locale VARCHAR(10) NOT NULL,
                action ENUM("upsert", "delete") NOT NULL DEFAULT "upsert",
                status ENUM("pending", "processing", "completed", "failed") NOT NULL DEFAULT "pending",
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                processed_at DATETIME(6) NULL,
                UNIQUE KEY uk_search_index_queue_uuid (uuid),
                KEY idx_search_index_queue_pending (status, created_at),
                KEY idx_search_index_queue_entity (entity_type, entity_uuid, locale)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS idempotency_records (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                idempotency_key VARCHAR(128) NOT NULL,
                scope VARCHAR(80) NOT NULL DEFAULT "api",
                request_hash CHAR(64) NULL,
                response_status SMALLINT NULL,
                response_body JSON NULL,
                expires_at DATETIME(6) NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_idempotency_key_scope (idempotency_key, scope),
                KEY idx_idempotency_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS media_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_image_id BIGINT UNSIGNED NULL,
                status ENUM("uploaded", "scanning", "scan_failed", "processing", "completed", "failed") NOT NULL DEFAULT "uploaded",
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                error_message TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_media_jobs_uuid (uuid),
                KEY idx_media_jobs_status (status),
                CONSTRAINT fk_media_jobs_image FOREIGN KEY (product_image_id) REFERENCES product_images (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS media_jobs');
        $this->exec('DROP TABLE IF EXISTS idempotency_records');
        $this->exec('DROP TABLE IF EXISTS search_index_queue');
        $this->exec('DROP TABLE IF EXISTS job_dead_letter_log');
        $this->exec('DROP TABLE IF EXISTS job_queue');
    }
}
