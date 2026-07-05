-- RATEB ERP — POS batch/serial lifecycle (Phase 6.1 + 7)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_order_lines' AND COLUMN_NAME = 'batch_allocations_json');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_order_lines ADD COLUMN batch_allocations_json JSON NULL AFTER batch_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_pos_batch_ledger (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    order_line_id INT UNSIGNED NULL,
    original_line_id INT UNSIGNED NULL,
    movement_id INT UNSIGNED NULL,
    batch_id INT UNSIGNED NOT NULL,
    direction ENUM('out','in') NOT NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    reference_type VARCHAR(40) NOT NULL DEFAULT 'pos_order',
    reference_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_batch_ledger_line (company_id, order_line_id, direction),
    INDEX idx_pos_batch_ledger_orig (company_id, original_line_id, batch_id),
    INDEX idx_pos_batch_ledger_order (company_id, order_id),
    CONSTRAINT fk_pos_batch_ledger_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_serial_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    serial_id INT UNSIGNED NULL,
    serial_no VARCHAR(100) NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NULL,
    reference_type VARCHAR(40) NULL,
    reference_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_serial_hist_sn (company_id, serial_no),
    INDEX idx_pos_serial_hist_ref (company_id, reference_type, reference_id),
    CONSTRAINT fk_pos_serial_hist_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
