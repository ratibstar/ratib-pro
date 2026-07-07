-- RATEB ERP — POS offline sync conflicts (Phase 3)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_pos_sync_conflicts (
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
    INDEX idx_pos_sync_conflict_status (company_id, status),
    INDEX idx_pos_sync_conflict_queue (queue_id),
    CONSTRAINT fk_pos_sync_conflict_queue FOREIGN KEY (queue_id) REFERENCES rateb_pos_sync_queue(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
