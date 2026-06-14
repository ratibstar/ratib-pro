-- Procurement phase 2: inventory link, currency, GRN, barcodes, discounts
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_requests' AND COLUMN_NAME = 'expected_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_requests ADD COLUMN expected_date DATE NULL AFTER department, ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT ''SAR'' AFTER total_estimated',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'currency');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT ''SAR'' AFTER total_amount, ADD COLUMN discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER currency, ADD COLUMN shipping_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER discount_amount, ADD COLUMN warehouse_id INT UNSIGNED NULL AFTER cost_center_id, ADD COLUMN quotation_id INT UNSIGNED NULL AFTER purchase_request_id, ADD COLUMN barcode VARCHAR(80) NULL AFTER order_no, ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode, ADD INDEX idx_po_barcode (barcode), ADD INDEX idx_po_warehouse (warehouse_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'inventory_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN inventory_id INT UNSIGNED NULL AFTER purchase_request_id, ADD INDEX idx_pri_inventory (inventory_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_items' AND COLUMN_NAME = 'inventory_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_items ADD COLUMN inventory_id INT UNSIGNED NULL AFTER purchase_order_id, ADD INDEX idx_pi_inventory (inventory_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
