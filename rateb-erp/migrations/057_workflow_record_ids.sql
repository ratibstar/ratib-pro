-- RATEB ERP — workflow record IDs (2 letters + 4 digits, idempotent)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_maintenance' AND COLUMN_NAME = 'maintenance_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_asset_maintenance ADD COLUMN maintenance_no VARCHAR(6) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_assignments' AND COLUMN_NAME = 'assignment_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_asset_assignments ADD COLUMN assignment_no VARCHAR(6) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_depreciation' AND COLUMN_NAME = 'depreciation_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_asset_depreciation ADD COLUMN depreciation_no VARCHAR(6) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_service_history' AND COLUMN_NAME = 'service_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_device_service_history ADD COLUMN service_no VARCHAR(6) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contract_renewals' AND COLUMN_NAME = 'renewal_no');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contract_renewals ADD COLUMN renewal_no VARCHAR(6) NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
