-- Idempotent catchup: agency DBs may have 126_branch_ops_isolation.sql recorded in rateb_migrations
-- without branch_id columns (partial provision / reset). Re-applies branch_id on ops tables.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_requests' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_purchase_requests ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_pr_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_purchase_orders ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_po_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_suppliers' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_suppliers ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_sup_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_inventory ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_inv_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_rfq' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_rfq ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_rfq_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contracts ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_contract_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_assets' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_assets ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_asset_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_tenders' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_tenders ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_tender_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_stock_movements' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_stock_movements ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_sm_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
