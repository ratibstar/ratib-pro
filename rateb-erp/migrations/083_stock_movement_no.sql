-- RATEB ERP — stock movement document code (idempotent, from 046)
SET NAMES utf8mb4;

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
