-- RATEB ERP — procurement production catch-up (idempotent, one-shot for live DBs)
-- Fixes purchase order line saves (tax/description columns) + PR line extras (084/085).
SET NAMES utf8mb4;

-- purchase_items: tax + description (from 044 — required for PO line save)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_items' AND COLUMN_NAME = 'description');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_items ADD COLUMN description VARCHAR(500) NULL AFTER item_name',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_items' AND COLUMN_NAME = 'delivered_qty');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_items ADD COLUMN delivered_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER quantity',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_items' AND COLUMN_NAME = 'invoiced_qty');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_items ADD COLUMN invoiced_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER delivered_qty',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_items' AND COLUMN_NAME = 'tax_name');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_items ADD COLUMN tax_name VARCHAR(80) NULL DEFAULT ''Local Sales 0%'' AFTER unit_price',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_items' AND COLUMN_NAME = 'tax_rate');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_items ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER tax_name',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_items' AND COLUMN_NAME = 'excluding_tax');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_items ADD COLUMN excluding_tax TINYINT(1) NOT NULL DEFAULT 1 AFTER tax_rate',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_items' AND COLUMN_NAME = 'inventory_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_items ADD COLUMN inventory_id INT UNSIGNED NULL AFTER purchase_order_id, ADD INDEX idx_pi_inventory (inventory_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- purchase_orders: currency, discount, shipping, warehouse (from 045)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'currency');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT ''SAR'' AFTER total_amount',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'discount_amount');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER currency',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'shipping_amount');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN shipping_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER discount_amount',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'notes_history');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_orders ADD COLUMN notes_history JSON NULL AFTER notes',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- purchase_request_items: description (required before needed_by)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'description');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN description VARCHAR(500) NULL AFTER item_name',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'needed_by');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN needed_by DATE NULL AFTER description',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'supplier_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN supplier_id INT UNSIGNED NULL AFTER needed_by, ADD INDEX idx_pri_supplier (supplier_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'warehouse_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN warehouse_id INT UNSIGNED NULL AFTER supplier_id, ADD INDEX idx_pri_warehouse (warehouse_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'account_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN account_id INT UNSIGNED NULL AFTER warehouse_id, ADD INDEX idx_pri_account (account_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'attachment_path');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN attachment_path VARCHAR(500) NULL AFTER account_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'attachment_name');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'tax_name');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN tax_name VARCHAR(80) NULL DEFAULT ''Local Sales 0%'' AFTER unit_price, ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER tax_name, ADD COLUMN excluding_tax TINYINT(1) NOT NULL DEFAULT 1 AFTER tax_rate',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'inventory_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items ADD COLUMN inventory_id INT UNSIGNED NULL AFTER purchase_request_id, ADD INDEX idx_pri_inventory (inventory_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
