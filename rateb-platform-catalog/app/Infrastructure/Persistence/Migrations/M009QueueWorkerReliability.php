<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M009QueueWorkerReliability extends AbstractMigration
{
    public function name(): string
    {
        return '009_queue_worker_reliability';
    }

    public function up(): void
    {
        $this->exec(
            'ALTER TABLE job_queue
                ADD COLUMN locked_by VARCHAR(80) NULL AFTER last_error,
                ADD COLUMN heartbeat_at DATETIME(6) NULL AFTER locked_by,
                ADD COLUMN visibility_timeout_at DATETIME(6) NULL AFTER heartbeat_at,
                ADD KEY idx_job_queue_stale (status, visibility_timeout_at)'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS queue_worker_locks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                worker_id VARCHAR(80) NOT NULL,
                queue VARCHAR(50) NOT NULL,
                heartbeat_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                expires_at DATETIME(6) NOT NULL,
                UNIQUE KEY uk_queue_worker_locks_queue (queue),
                KEY idx_queue_worker_locks_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS queue_worker_locks');
        $this->exec(
            'ALTER TABLE job_queue
                DROP INDEX idx_job_queue_stale,
                DROP COLUMN visibility_timeout_at,
                DROP COLUMN heartbeat_at,
                DROP COLUMN locked_by'
        );
    }
}
