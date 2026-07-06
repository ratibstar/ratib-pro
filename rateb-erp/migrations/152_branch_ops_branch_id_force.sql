-- Force branch_id on ops tables (MySQL 8 + MariaDB idempotent).
-- Run on the ERP database the app actually uses — check BOTH:
--   admin_rateb-erp  AND  admin_rateb_erp
-- Verify first: SELECT DATABASE(); SHOW COLUMNS FROM rateb_purchase_orders LIKE 'branch_id';
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_requests' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_purchase_requests ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_purchase_orders' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_purchase_orders ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_suppliers' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_suppliers ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_inventory ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_rfq' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_rfq ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_contracts' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_contracts ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_assets' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_assets ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_tenders' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_tenders ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_stock_movements' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_stock_movements ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
