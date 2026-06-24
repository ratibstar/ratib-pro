-- RATEB ERP — branch isolation: branch_id on ops tables + backfill to main branch
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

UPDATE rateb_warehouses w
JOIN rateb_branches b ON b.company_id = w.company_id AND b.is_main = 1
SET w.branch_id = b.id
WHERE w.branch_id IS NULL;

UPDATE rateb_journal_entries j
JOIN rateb_branches b ON b.company_id = j.company_id AND b.is_main = 1
SET j.branch_id = b.id
WHERE j.branch_id IS NULL;

UPDATE rateb_cash_vouchers v
JOIN rateb_branches b ON b.company_id = v.company_id AND b.is_main = 1
SET v.branch_id = b.id
WHERE v.branch_id IS NULL;

UPDATE rateb_purchase_requests t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;

UPDATE rateb_purchase_orders t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;

UPDATE rateb_suppliers t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;

UPDATE rateb_inventory t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;

UPDATE rateb_rfq t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;

UPDATE rateb_contracts t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;

UPDATE rateb_assets t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;

UPDATE rateb_tenders t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;

UPDATE rateb_stock_movements t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;
