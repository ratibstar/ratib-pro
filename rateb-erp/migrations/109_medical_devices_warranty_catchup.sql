-- RATEB ERP — medical devices warranty_expiry + category_id catch-up (idempotent; fixes [warranty_expiry])
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_medical_devices' AND COLUMN_NAME = 'category_id');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_medical_devices ADD COLUMN category_id INT UNSIGNED NULL AFTER device_name',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_medical_devices' AND COLUMN_NAME = 'warranty_expiry');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_medical_devices ADD COLUMN warranty_expiry DATE NULL AFTER maintenance_due',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
