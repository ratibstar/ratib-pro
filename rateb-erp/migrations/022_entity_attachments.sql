-- Attachment path on invoices and inventory
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_invoices' AND COLUMN_NAME = 'document_path');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_invoices ADD COLUMN document_path VARCHAR(500) NULL AFTER qr_code',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'document_path');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN document_path VARCHAR(500) NULL AFTER qr_code',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
