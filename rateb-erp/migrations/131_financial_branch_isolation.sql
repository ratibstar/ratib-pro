-- RATEB ERP Phase 4 — financial branch isolation (journal lines, cost centers, bank accounts)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- rateb_journal_lines.branch_id
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_lines' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_journal_lines ADD COLUMN branch_id INT UNSIGNED NULL AFTER journal_entry_id, ADD INDEX idx_jl_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_cost_centers.branch_id
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cost_centers' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cost_centers ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_cc_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rateb_bank_accounts.branch_id
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_bank_accounts' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_bank_accounts ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_ba_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill journal_lines from parent entry
UPDATE rateb_journal_lines jl
INNER JOIN rateb_journal_entries je ON je.id = jl.journal_entry_id
SET jl.branch_id = je.branch_id
WHERE jl.branch_id IS NULL AND je.branch_id IS NOT NULL;

UPDATE rateb_journal_lines jl
INNER JOIN rateb_journal_entries je ON je.id = jl.journal_entry_id
INNER JOIN rateb_branches b ON b.company_id = je.company_id AND b.is_main = 1
SET jl.branch_id = b.id
WHERE jl.branch_id IS NULL;

-- Backfill cost centers + bank accounts to main branch
UPDATE rateb_cost_centers t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;

UPDATE rateb_bank_accounts t
JOIN rateb_branches b ON b.company_id = t.company_id AND b.is_main = 1
SET t.branch_id = b.id
WHERE t.branch_id IS NULL;
