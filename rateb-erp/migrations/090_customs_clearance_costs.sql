-- RATEB ERP — customs clearance costs on purchase orders (idempotent)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'customs_clearance_amount');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN customs_clearance_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER shipping_amount',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
