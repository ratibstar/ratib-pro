-- RATEB ERP — manager approval columns on workflow ops tables (idempotent)
SET NAMES utf8mb4;

-- rateb_asset_maintenance
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_maintenance' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_asset_maintenance ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER status, ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval, ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_asset_assignments
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_assignments' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_asset_assignments ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER notes, ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval, ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_device_service_history
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_service_history' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_device_service_history ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER notes, ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval, ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_device_spare_parts
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_spare_parts' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_device_spare_parts ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER reorder_level, ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval, ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_inventory_audits
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory_audits' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory_audits ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER status, ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval, ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE rateb_asset_maintenance SET manager_approval = 'approved' WHERE manager_approval = 'pending' AND status IN ('completed', 'done');
