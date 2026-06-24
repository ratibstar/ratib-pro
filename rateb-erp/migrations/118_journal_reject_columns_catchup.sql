-- RATEB ERP — journal / cash voucher reject columns (idempotent)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'reject_reason');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_journal_entries ADD COLUMN reject_reason VARCHAR(500) NULL AFTER status, ADD COLUMN rejected_at DATETIME NULL AFTER reject_reason, ADD COLUMN rejected_by INT UNSIGNED NULL AFTER rejected_at',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cash_vouchers' AND COLUMN_NAME = 'reject_reason');
SET @sql = IF(@col = 0,
    'ALTER TABLE rateb_cash_vouchers ADD COLUMN reject_reason VARCHAR(500) NULL AFTER status, ADD COLUMN rejected_at DATETIME NULL AFTER reject_reason, ADD COLUMN rejected_by INT UNSIGNED NULL AFTER rejected_at',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
