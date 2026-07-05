-- RATEB ERP — POS checkout columns (Phase 5)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND COLUMN_NAME = 'completed_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_orders ADD COLUMN completed_at DATETIME NULL AFTER status, ADD COLUMN receipt_json JSON NULL AFTER total',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_order_lines' AND COLUMN_NAME = 'serial_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_pos_order_lines ADD COLUMN serial_no VARCHAR(100) NULL AFTER batch_id, ADD COLUMN serial_id INT UNSIGNED NULL AFTER serial_no',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_pos_orders' AND COLUMN_NAME = 'status'
    AND COLUMN_TYPE LIKE '%processing%');
SET @sql = IF(@col = 0,
    "ALTER TABLE rateb_pos_orders MODIFY status ENUM('draft','processing','completed','void','suspended') NOT NULL DEFAULT 'draft'",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
