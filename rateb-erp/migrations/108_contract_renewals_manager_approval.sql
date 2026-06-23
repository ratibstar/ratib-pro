-- RATEB ERP — contract renewals: manager approval + audit columns (idempotent)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contract_renewals' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contract_renewals ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contract_renewals' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contract_renewals ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contract_renewals' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contract_renewals ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE rateb_contract_renewals
SET manager_approval = 'approved', status = 'completed'
WHERE manager_approval = 'pending' AND status = 'completed';
