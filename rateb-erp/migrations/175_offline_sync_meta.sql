-- RATEB ERP — Enterprise offline sync queue + conflicts (Phase 2A)
-- Additive only. Does not ALTER existing tables.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_offline_sync_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    device_id VARCHAR(64) NULL,
    user_id INT UNSIGNED NULL,
    module VARCHAR(32) NOT NULL DEFAULT 'offline_meta',
    action VARCHAR(64) NOT NULL DEFAULT 'offline.ack',
    idempotency_key VARCHAR(64) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending','synced','conflict','failed') NOT NULL DEFAULT 'pending',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    synced_at DATETIME NULL,
    UNIQUE KEY uq_offline_sync_idem (company_id, idempotency_key),
    INDEX idx_offline_sync_status (company_id, status),
    INDEX idx_offline_sync_device (company_id, device_id),
    CONSTRAINT fk_offline_sync_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_offline_sync_conflicts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    queue_id INT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(64) NOT NULL,
    reason VARCHAR(64) NOT NULL DEFAULT 'server_newer',
    client_payload JSON NOT NULL,
    server_payload JSON NULL,
    status ENUM('open','resolved_server','resolved_client','merged') NOT NULL DEFAULT 'open',
    resolved_by INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_offline_sync_conflict_status (company_id, status),
    INDEX idx_offline_sync_conflict_queue (queue_id),
    CONSTRAINT fk_offline_sync_conflict_queue FOREIGN KEY (queue_id) REFERENCES rateb_offline_sync_queue(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
