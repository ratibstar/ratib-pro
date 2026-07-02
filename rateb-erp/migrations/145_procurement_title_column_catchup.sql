-- Idempotent: agency DBs that reused Pro tenant schema may have rateb_purchase_* without title.
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_requests' AND COLUMN_NAME = 'title');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_requests ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '''' AFTER request_no',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'title');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '''' AFTER order_no',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
