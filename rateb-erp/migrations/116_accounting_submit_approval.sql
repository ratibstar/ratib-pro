-- Accounting: draft must be submitted before oversight approval queue
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'submitted_for_approval_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_journal_entries ADD COLUMN submitted_for_approval_at DATETIME NULL AFTER posted_at, ADD INDEX idx_je_submitted (submitted_for_approval_at)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cash_vouchers' AND COLUMN_NAME = 'submitted_for_approval_at');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_cash_vouchers ADD COLUMN submitted_for_approval_at DATETIME NULL AFTER posted_at, ADD INDEX idx_cv_submitted (submitted_for_approval_at)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
