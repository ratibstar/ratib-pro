-- RATEB ERP — normalize manager_approval / approval_status ENUM values (idempotent)
SET NAMES utf8mb4;

-- rateb_supplier_evaluations
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col > 0, 'ALTER TABLE rateb_supplier_evaluations MODIFY COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_contract_renewals
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contract_renewals' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col > 0, 'ALTER TABLE rateb_contract_renewals MODIFY COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_asset_maintenance
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_maintenance' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col > 0, 'ALTER TABLE rateb_asset_maintenance MODIFY COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_asset_assignments
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_assignments' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col > 0, 'ALTER TABLE rateb_asset_assignments MODIFY COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_device_service_history
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_service_history' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col > 0, 'ALTER TABLE rateb_device_service_history MODIFY COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_device_spare_parts
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_device_spare_parts' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col > 0, 'ALTER TABLE rateb_device_spare_parts MODIFY COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_inventory_audits
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory_audits' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col > 0, 'ALTER TABLE rateb_inventory_audits MODIFY COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_contracts
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'approval_status');
SET @sql = IF(@col > 0, 'ALTER TABLE rateb_contracts MODIFY COLUMN approval_status ENUM(''draft'',''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''draft''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
