-- RATEB ERP — POS production hotfixes: 162 catch-up, checkout idempotency
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Catch-up for environments where 162 was marked applied but columns were skipped (duplicate created_by)
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

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_order_lines' AND COLUMN_NAME = 'line_kind');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_order_lines
        ADD COLUMN line_kind ENUM(''sale'',''return'') NOT NULL DEFAULT ''sale'' AFTER line_no,
        ADD COLUMN original_line_id INT UNSIGNED NULL AFTER line_kind,
        ADD COLUMN return_reason VARCHAR(255) NULL AFTER original_line_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND INDEX_NAME = 'idx_pos_order_original');
SET @sql = IF(@idx = 0, 'ALTER TABLE rateb_pos_orders ADD INDEX idx_pos_order_original (original_order_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND COLUMN_NAME = 'idempotency_key');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_orders ADD COLUMN idempotency_key VARCHAR(64) NULL AFTER order_no',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND INDEX_NAME = 'uq_pos_order_idempotency');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_pos_orders ADD UNIQUE KEY uq_pos_order_idempotency (company_id, idempotency_key)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
