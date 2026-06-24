-- RATEB ERP — journal / cash voucher status ENUM includes rejected (idempotent)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'status');
SET @sql = IF(@col > 0,
    'ALTER TABLE rateb_journal_entries MODIFY status ENUM(''draft'',''posted'',''void'',''rejected'') NOT NULL DEFAULT ''draft''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cash_vouchers' AND COLUMN_NAME = 'status');
SET @sql = IF(@col > 0,
    'ALTER TABLE rateb_cash_vouchers MODIFY status ENUM(''draft'',''posted'',''void'',''rejected'') NOT NULL DEFAULT ''draft''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
