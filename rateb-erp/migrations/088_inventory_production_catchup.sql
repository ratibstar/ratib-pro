-- RATEB ERP — inventory + stock movement production catch-up (idempotent)
-- Fixes inventory create/save when item_code, barcode, max_stock, movement_no, etc. are missing.
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'item_code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN item_code VARCHAR(20) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'category_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN category_id INT UNSIGNED NULL AFTER category',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'barcode');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN barcode VARCHAR(80) NULL AFTER sku, ADD COLUMN qr_code VARCHAR(255) NULL AFTER barcode, ADD COLUMN min_stock DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER reorder_level, ADD COLUMN max_stock DECIMAL(12,3) NULL AFTER min_stock',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'production_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN production_date DATE NULL AFTER reorder_level',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'document_path');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN document_path VARCHAR(500) NULL AFTER qr_code',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'notes');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN notes TEXT NULL AFTER document_path',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND INDEX_NAME = 'uq_inv_company_item_code');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_inventory ADD UNIQUE KEY uq_inv_company_item_code (company_id, item_code)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND INDEX_NAME = 'idx_inv_barcode');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_inventory ADD INDEX idx_inv_barcode (barcode)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND INDEX_NAME = 'idx_inv_category');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_inventory ADD INDEX idx_inv_category (category_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_stock_movements' AND COLUMN_NAME = 'movement_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_stock_movements ADD COLUMN movement_no VARCHAR(20) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_stock_movements' AND INDEX_NAME = 'uq_sm_company_movement_no');
SET @sql = IF(@idx = 0,
    'ALTER TABLE rateb_stock_movements ADD UNIQUE KEY uq_sm_company_movement_no (company_id, movement_no)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
