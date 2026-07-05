-- RATEB ERP — POS sync queue + report snapshots (Phase 2)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_pos_sync_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    terminal_id INT UNSIGNED NULL,
    idempotency_key VARCHAR(64) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending','synced','conflict','failed') NOT NULL DEFAULT 'pending',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    synced_at DATETIME NULL,
    UNIQUE KEY uq_pos_sync_idem (company_id, idempotency_key),
    INDEX idx_pos_sync_status (company_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_inventory_reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    session_id INT UNSIGNED NULL,
    quantity DECIMAL(12,3) NOT NULL,
    status ENUM('active','committed','released','expired') NOT NULL DEFAULT 'active',
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_res_inv (company_id, inventory_id, status),
    CONSTRAINT fk_pos_res_inv FOREIGN KEY (inventory_id) REFERENCES rateb_inventory(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_reports_snapshots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    terminal_id INT UNSIGNED NULL,
    shift_id INT UNSIGNED NULL,
    report_type ENUM('x','z','drawer') NOT NULL,
    snapshot_json JSON NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_report_shift (shift_id, report_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
