-- RATEB ERP — purchase request line: supplier, warehouse, account, attachment (idempotent)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_request_items' AND COLUMN_NAME = 'supplier_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_purchase_request_items
        ADD COLUMN supplier_id INT UNSIGNED NULL AFTER needed_by,
        ADD COLUMN warehouse_id INT UNSIGNED NULL AFTER supplier_id,
        ADD COLUMN account_id INT UNSIGNED NULL AFTER warehouse_id,
        ADD COLUMN attachment_path VARCHAR(500) NULL AFTER account_id,
        ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path,
        ADD INDEX idx_pri_supplier (supplier_id),
        ADD INDEX idx_pri_warehouse (warehouse_id),
        ADD INDEX idx_pri_account (account_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
