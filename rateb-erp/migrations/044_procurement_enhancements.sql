-- Procurement: line-item tax fields, notes history
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'tax_name');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN description VARCHAR(500) NULL AFTER item_name, ADD COLUMN tax_name VARCHAR(80) NULL DEFAULT ''Local Sales 0%'' AFTER unit_price, ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER tax_name, ADD COLUMN excluding_tax TINYINT(1) NOT NULL DEFAULT 1 AFTER tax_rate',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_items' AND COLUMN_NAME = 'tax_name');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_items ADD COLUMN description VARCHAR(500) NULL AFTER item_name, ADD COLUMN delivered_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER quantity, ADD COLUMN invoiced_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER delivered_qty, ADD COLUMN tax_name VARCHAR(80) NULL DEFAULT ''Local Sales 0%'' AFTER unit_price, ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER tax_name, ADD COLUMN excluding_tax TINYINT(1) NOT NULL DEFAULT 1 AFTER tax_rate',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_requests' AND COLUMN_NAME = 'notes_history');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_requests ADD COLUMN notes_history JSON NULL AFTER notes',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'notes_history');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN notes_history JSON NULL AFTER notes',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
