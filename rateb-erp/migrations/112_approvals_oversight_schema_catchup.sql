-- RATEB ERP — approvals oversight schema catch-up (idempotent; run if oversight approve fails)
SET NAMES utf8mb4;

-- Contracts approval_status (107)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'approval_status');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_contracts ADD COLUMN approval_status ENUM(''draft'',''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''draft'' AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Supplier evaluations manager approval (079)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'approved_by');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN approved_by INT UNSIGNED NULL AFTER manager_approval',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_supplier_evaluations' AND COLUMN_NAME = 'approved_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_supplier_evaluations ADD COLUMN approved_at DATETIME NULL AFTER approved_by',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Asset depreciation status (061)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_depreciation' AND COLUMN_NAME = 'status');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_asset_depreciation ADD COLUMN status ENUM(''draft'',''approved'') NOT NULL DEFAULT ''draft'' AFTER book_value',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Manager approval on workflow ops tables (110)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_asset_assignments' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_asset_assignments ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'', ADD COLUMN approved_by INT UNSIGNED NULL, ADD COLUMN approved_at DATETIME NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory_audits' AND COLUMN_NAME = 'manager_approval');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_inventory_audits ADD COLUMN manager_approval ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'', ADD COLUMN approved_by INT UNSIGNED NULL, ADD COLUMN approved_at DATETIME NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Assets current_value for depreciation posting
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_assets' AND COLUMN_NAME = 'current_value');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_assets ADD COLUMN current_value DECIMAL(14,2) NULL AFTER purchase_cost',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
