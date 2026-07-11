-- Scope WebAuthn credentials by company (POS offline auth gate)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @tbl = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_webauthn_credentials');

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_webauthn_credentials' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@tbl > 0 AND @col = 0,
    'ALTER TABLE rateb_webauthn_credentials
        ADD COLUMN company_id INT UNSIGNED NULL AFTER user_id,
        ADD INDEX idx_webauthn_company_user (company_id, user_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_branch = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_webauthn_credentials' AND COLUMN_NAME = 'branch_id');
SET @sql = IF(@tbl > 0 AND @col_branch = 0,
    'ALTER TABLE rateb_webauthn_credentials
        ADD COLUMN branch_id INT UNSIGNED NULL AFTER company_id,
        ADD INDEX idx_webauthn_company_branch (company_id, branch_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill company_id from rateb_users when missing
SET @sql = IF(@tbl > 0,
    'UPDATE rateb_webauthn_credentials wc
     INNER JOIN rateb_users u ON u.id = wc.user_id
     SET wc.company_id = u.company_id
     WHERE wc.company_id IS NULL AND u.company_id IS NOT NULL AND u.company_id > 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
