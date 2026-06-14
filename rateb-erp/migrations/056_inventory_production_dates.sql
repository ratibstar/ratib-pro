-- RATEB ERP — production dates for inventory + batches (idempotent)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'production_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory ADD COLUMN production_date DATE NULL AFTER reorder_level',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory_batches' AND COLUMN_NAME = 'production_date');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory_batches ADD COLUMN production_date DATE NULL AFTER quantity',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
