-- RATEB ERP — manager approval audit columns catch-up (idempotent)
-- Run if oversight approve fails with db_operation_failed after 110/112.
SET NAMES utf8mb4;

-- Helper pattern: each column checked independently (manager_approval may exist without approved_by/approved_at).

-- rateb_supplier_evaluations
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_supplier_evaluations ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_supplier_evaluations ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_supplier_evaluations ADD COLUMN approved_at DATETIME NULL AFTER approved_by', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_contract_renewals
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contract_renewals' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contract_renewals ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contract_renewals' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contract_renewals ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contract_renewals' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contract_renewals ADD COLUMN approved_at DATETIME NULL AFTER approved_by', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_asset_maintenance
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_maintenance' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_asset_maintenance ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_maintenance' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_asset_maintenance ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_maintenance' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_asset_maintenance ADD COLUMN approved_at DATETIME NULL AFTER approved_by', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_asset_assignments
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_assignments' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_asset_assignments ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER notes', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_assignments' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_asset_assignments ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_assignments' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_asset_assignments ADD COLUMN approved_at DATETIME NULL AFTER approved_by', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_device_service_history
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_service_history' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_device_service_history ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER notes', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_service_history' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_device_service_history ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_service_history' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_device_service_history ADD COLUMN approved_at DATETIME NULL AFTER approved_by', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_device_spare_parts
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_spare_parts' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_device_spare_parts ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER reorder_level', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_spare_parts' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_device_spare_parts ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_spare_parts' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_device_spare_parts ADD COLUMN approved_at DATETIME NULL AFTER approved_by', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_inventory_audits
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory_audits' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_inventory_audits ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory_audits' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_inventory_audits ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory_audits' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_inventory_audits ADD COLUMN approved_at DATETIME NULL AFTER approved_by', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
