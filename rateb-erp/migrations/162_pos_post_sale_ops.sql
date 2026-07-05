-- RATEB ERP — POS post-sale ops (Phase 6): returns, refunds, suspend, quotes, store credit
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Per-column idempotent ALTERs (155 already defines created_by — do not re-add)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND COLUMN_NAME = 'original_order_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_pos_orders ADD COLUMN original_order_id INT UNSIGNED NULL AFTER customer_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND COLUMN_NAME = 'linked_order_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_pos_orders ADD COLUMN linked_order_id INT UNSIGNED NULL AFTER original_order_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND COLUMN_NAME = 'gift_receipt');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_pos_orders ADD COLUMN gift_receipt TINYINT(1) NOT NULL DEFAULT 0 AFTER linked_order_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND COLUMN_NAME = 'quote_expires_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_pos_orders ADD COLUMN quote_expires_at DATETIME NULL AFTER gift_receipt', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND COLUMN_NAME = 'suspended_payload');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_pos_orders ADD COLUMN suspended_payload JSON NULL AFTER quote_expires_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND COLUMN_NAME = 'notes');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_pos_orders ADD COLUMN notes TEXT NULL AFTER suspended_payload', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND INDEX_NAME = 'idx_pos_order_original');
SET @sql = IF(@idx = 0, 'ALTER TABLE rateb_pos_orders ADD INDEX idx_pos_order_original (original_order_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND INDEX_NAME = 'idx_pos_order_type_status');
SET @sql = IF(@idx = 0, 'ALTER TABLE rateb_pos_orders ADD INDEX idx_pos_order_type_status (company_id, branch_id, order_type, status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_order_lines' AND COLUMN_NAME = 'line_kind');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_order_lines
        ADD COLUMN line_kind ENUM(''sale'',''return'') NOT NULL DEFAULT ''sale'' AFTER line_no,
        ADD COLUMN original_line_id INT UNSIGNED NULL AFTER line_kind,
        ADD COLUMN return_reason VARCHAR(255) NULL AFTER original_line_id,
        ADD INDEX idx_pos_line_original (original_line_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_pos_refunds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    original_order_id INT UNSIGNED NULL,
    refund_method ENUM('cash','card','bank','wallet','store_credit') NOT NULL DEFAULT 'cash',
    amount DECIMAL(14,2) NOT NULL,
    reference_no VARCHAR(80) NULL,
    status ENUM('completed','void') NOT NULL DEFAULT 'completed',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_refund_order (order_id),
    INDEX idx_pos_refund_branch (company_id, branch_id),
    CONSTRAINT fk_pos_refund_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_store_credit_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','suspended','closed') NOT NULL DEFAULT 'active',
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pos_store_credit_customer (company_id, customer_id),
    CONSTRAINT fk_pos_sc_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_store_credit_ledger (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NULL,
    refund_id INT UNSIGNED NULL,
    entry_type ENUM('credit','debit') NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    balance_after DECIMAL(14,2) NOT NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pos_sc_ledger_account (account_id),
    CONSTRAINT fk_pos_sc_ledger_account FOREIGN KEY (account_id) REFERENCES rateb_pos_store_credit_accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_sc_ledger_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_returns' AND COLUMN_NAME = 'order_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_returns ADD COLUMN order_id INT UNSIGNED NULL AFTER original_order_id, ADD INDEX idx_pos_return_order (order_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
