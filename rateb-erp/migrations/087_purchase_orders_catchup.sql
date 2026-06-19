-- RATEB ERP — purchase orders missing columns (idempotent)
-- Fixes PO save after barcode/warehouse/cost-center updates (045/038).
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'cost_center_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN cost_center_id INT UNSIGNED NULL AFTER supplier_id, ADD INDEX idx_po_cost_center (cost_center_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'warehouse_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN warehouse_id INT UNSIGNED NULL AFTER cost_center_id, ADD INDEX idx_po_warehouse (warehouse_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'quotation_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN quotation_id INT UNSIGNED NULL AFTER purchase_request_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN barcode VARCHAR(80) NULL AFTER order_no, ADD INDEX idx_po_barcode (barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'qr_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
