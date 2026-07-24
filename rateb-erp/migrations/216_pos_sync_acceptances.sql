-- RATEB ERP — POS Offline sync acceptance store (Phase 12)
-- Acceptance only: WAITING_COMMIT. No invoice / inventory / accounting commit.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_pos_sync_acceptances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_sync_id VARCHAR(64) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    sync_key VARCHAR(191) NOT NULL,
    sale_id VARCHAR(128) NOT NULL,
    device_id VARCHAR(128) NOT NULL,
    installation_id VARCHAR(128) NULL,
    payload JSON NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'WAITING_COMMIT',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_accept_server_id (server_sync_id),
    UNIQUE KEY uq_pos_accept_sync_key (company_id, sync_key),
    INDEX idx_pos_accept_company_status (company_id, status),
    INDEX idx_pos_accept_sale (company_id, sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
