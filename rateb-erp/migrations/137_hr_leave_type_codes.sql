-- Leave type code column for locale-aware labels (137)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_leave_types' AND COLUMN_NAME = 'code');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_leave_types ADD COLUMN code VARCHAR(40) NULL AFTER company_id, ADD INDEX idx_leave_type_code (company_id, code)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE rateb_leave_types SET code = 'annual' WHERE code IS NULL AND name IN ('Annual leave', 'annual leave', 'Annual Leave');
UPDATE rateb_leave_types SET code = 'sick' WHERE code IS NULL AND name IN ('Sick leave', 'sick leave', 'Sick Leave');
UPDATE rateb_leave_types SET code = 'unpaid' WHERE code IS NULL AND name IN ('Unpaid leave', 'unpaid leave', 'Unpaid Leave');
