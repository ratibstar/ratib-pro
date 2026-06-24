-- RATEB ERP — branches phase 1+2 catchup (limits, links, main branch flag)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_plans' AND COLUMN_NAME = 'max_branches');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_plans ADD COLUMN max_branches INT UNSIGNED NOT NULL DEFAULT 10 AFTER max_storage_mb', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_companies' AND COLUMN_NAME = 'branch_limit');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_companies ADD COLUMN branch_limit INT UNSIGNED NOT NULL DEFAULT 0 AFTER user_limit', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_branches' AND COLUMN_NAME = 'is_main');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_branches ADD COLUMN is_main TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE rateb_plans SET max_branches = 3 WHERE slug = 'starter' AND max_branches = 10;
UPDATE rateb_plans SET max_branches = 5 WHERE slug = 'professional' AND max_branches = 10;
UPDATE rateb_plans SET max_branches = 25 WHERE slug = 'enterprise' AND max_branches = 10;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_warehouses' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_warehouses ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_wh_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_entries ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_je_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cash_vouchers' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cash_vouchers ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_cv_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO rateb_branches (company_id, name, code, address, status, is_main)
SELECT c.id,
       CONVERT(UNHEX('D8A7D984D981D8B1D8B9D8A7D984D8B1D8A6D98AD8B3D98A') USING utf8mb4),
       'MB001', NULL, 'active', 1
FROM rateb_companies c
WHERE NOT EXISTS (
    SELECT 1 FROM rateb_branches b WHERE b.company_id = c.id LIMIT 1
);

UPDATE rateb_branches b
JOIN (
    SELECT company_id, MIN(id) AS mid FROM rateb_branches GROUP BY company_id
) x ON x.company_id = b.company_id AND x.mid = b.id
SET b.is_main = 1
WHERE b.is_main = 0;
