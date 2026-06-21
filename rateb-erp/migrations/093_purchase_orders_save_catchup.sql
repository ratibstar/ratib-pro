-- RATEB ERP — purchase order save catch-up (idempotent, production one-shot)
-- Fixes PO update/save: cost_center_id, warehouse_id, customs clearance columns.
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
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'shipping_amount');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN shipping_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER discount_amount',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'customs_clearance_amount');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN customs_clearance_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER shipping_amount',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'customs_declaration_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN customs_declaration_no VARCHAR(80) NULL AFTER customs_clearance_amount',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'customs_clearance_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN customs_clearance_date DATE NULL AFTER customs_declaration_no',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'customs_broker_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN customs_broker_id INT UNSIGNED NULL AFTER customs_clearance_date, ADD INDEX idx_po_customs_broker (customs_broker_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'customs_clearance_status');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN customs_clearance_status VARCHAR(30) NOT NULL DEFAULT '''' AFTER customs_broker_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
