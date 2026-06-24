-- RATEB ERP — journal_entries.source_type ENUM catch-up (cash vouchers, stock, etc.)
SET NAMES utf8mb4;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_journal_entries' AND COLUMN_NAME = 'source_type');
SET @sql = IF(@col > 0,
    'ALTER TABLE rateb_journal_entries MODIFY source_type ENUM(
        ''manual'',''invoice'',''payment'',''purchase_order'',''subscription'',
        ''cash_voucher'',''stock_movement'',''purchase_invoice'',
        ''supplier_payment'',''year_end_close''
    ) NOT NULL DEFAULT ''manual''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
