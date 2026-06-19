-- RATEB ERP — purchase request line item enhancements (idempotent)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'needed_by');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN needed_by DATE NULL AFTER description',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
