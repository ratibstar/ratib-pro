-- RATEB ERP — supplier classification columns (from 009, idempotent re-apply)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_suppliers' AND COLUMN_NAME = 'classification_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_suppliers ADD COLUMN classification_id INT UNSIGNED NULL AFTER rating',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_suppliers' AND COLUMN_NAME = 'performance_kpi');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_suppliers ADD COLUMN performance_kpi DECIMAL(5,2) NULL AFTER classification_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
