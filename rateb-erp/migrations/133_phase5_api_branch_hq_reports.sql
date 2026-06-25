-- RATEB ERP Phase 5 — API token branch claim + HQ financial report permissions
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_api_tokens' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_api_tokens ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id, ADD INDEX idx_api_token_branch (branch_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Consolidated Trial Balance', 'Consolidated Trial Balance', 'branch.financial.consolidated.tb', 'accounting', 'HQ consolidated trial balance', 'HQ consolidated trial balance'),
('Consolidated General Ledger', 'Consolidated General Ledger', 'branch.financial.consolidated.gl', 'accounting', 'HQ consolidated general ledger', 'HQ consolidated general ledger'),
('Branch AR Aging', 'Branch AR Aging', 'branch.financial.araging', 'accounting', 'Accounts receivable aging by branch', 'Accounts receivable aging by branch'),
('Branch AP Aging', 'Branch AP Aging', 'branch.financial.apaging', 'accounting', 'Accounts payable aging by branch', 'Accounts payable aging by branch'),
('Branch Receivables', 'Branch Receivables', 'branch.financial.receivables', 'accounting', 'Receivables summary by branch', 'Receivables summary by branch'),
('Branch Payables', 'Branch Payables', 'branch.financial.payables', 'accounting', 'Payables summary by branch', 'Payables summary by branch')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'branch.financial.consolidated.tb',
    'branch.financial.consolidated.gl',
    'branch.financial.araging',
    'branch.financial.apaging',
    'branch.financial.receivables',
    'branch.financial.payables'
)
WHERE r.slug IN ('hq_admin', 'hq_manager', 'company-full-access');
