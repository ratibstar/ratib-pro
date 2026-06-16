-- Supplier payments: invoice link, due date (idempotent — safe in phpMyAdmin / re-run)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_payments' AND COLUMN_NAME = 'invoice_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_payments ADD COLUMN invoice_id INT UNSIGNED NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_payments' AND COLUMN_NAME = 'due_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_payments ADD COLUMN due_date DATE NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_payments' AND INDEX_NAME = 'idx_sp_invoice');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_supplier_payments ADD INDEX idx_sp_invoice (invoice_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
